<?php
/*
 *********************************************************************************************************
 * Send WA reminders for invoices nearing due date
 *********************************************************************************************************
 */

    require_once(__DIR__ . '/../../../app/common/includes/config_read.php');
    require_once(__DIR__ . '/../../../app/common/includes/db_open.php');
    require_once(__DIR__ . '/../../../app/common/includes/wa_gateway.php');

    function cron_status_write($configValues, $name, $status, $message = '') {
        $base = rtrim($configValues['CONFIG_PATH_DALO_VARIABLE_DATA'], DIRECTORY_SEPARATOR);
        $dir = $base . DIRECTORY_SEPARATOR . 'log' . DIRECTORY_SEPARATOR . 'cron-status';
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }
        $payload = array(
            'status' => $status,
            'message' => $message,
            'updated_at' => date('c'),
        );
        @file_put_contents($dir . DIRECTORY_SEPARATOR . $name . '.json', json_encode($payload));
    }

    function cron_status_message($message) {
        $message = trim((string)$message);
        if ($message === '') {
            return '';
        }
        return substr($message, 0, 240);
    }

    $status_name = 'send-wa-reminders';
    $status_message = '';
    $status_ok = true;

    try {
    $settings = wa_gateway_get_settings($dbSocket, $configValues);
    if (empty($settings) || intval($settings['is_enabled']) !== 1) {
        $status_message = 'WA gateway disabled';
        cron_status_write($configValues, $status_name, 'skipped', $status_message);
        require_once(__DIR__ . '/../../../app/common/includes/db_close.php');
        exit(0);
    }

    $due_days = intval($settings['due_days']);
    $reminder_days_before = intval($settings['reminder_days_before']);

    $sql_paid = sprintf("SELECT id FROM %s WHERE value='paid' LIMIT 1", $configValues['CONFIG_DB_TBL_DALOBILLINGINVOICESTATUS']);
    $paid_id = intval($dbSocket->getOne($sql_paid));

    $sql = sprintf("SELECT a.id, a.date, b.username, b.phone, ui.mobilephone,
                           COALESCE(SUM(i.amount + i.tax_amount), 0) AS total_amount
                    FROM %s AS a
                    INNER JOIN %s AS b ON a.user_id=b.id
                    LEFT JOIN %s AS ui ON b.username=ui.username
                    LEFT JOIN %s AS i ON i.invoice_id=a.id
                    WHERE a.status_id <> %d
                    GROUP BY a.id, a.date, b.username, b.phone",
                    $configValues['CONFIG_DB_TBL_DALOBILLINGINVOICE'],
                    $configValues['CONFIG_DB_TBL_DALOUSERBILLINFO'],
                    $configValues['CONFIG_DB_TBL_DALOUSERINFO'],
                    $configValues['CONFIG_DB_TBL_DALOBILLINGINVOICEITEMS'],
                    $paid_id);

    $res = $dbSocket->query($sql);
    if (DB::isError($res)) {
        $status_ok = false;
        $status_message = cron_status_message('DB error while loading invoices: ' . $res->getMessage());
        cron_status_write($configValues, $status_name, 'fail', $status_message);
        require_once(__DIR__ . '/../../../app/common/includes/db_close.php');
        exit(1);
    }

    $today = date('Y-m-d');
    while ($row = $res->fetchRow(DB_FETCHMODE_ASSOC)) {
        $invoice_date = $row['date'];
        if (empty($invoice_date) || $invoice_date === '0000-00-00') {
            continue;
        }

        $due_date = date('Y-m-d', strtotime($invoice_date . " +{$due_days} days"));
        $reminder_date = date('Y-m-d', strtotime($due_date . " -{$reminder_days_before} days"));
        if ($reminder_date !== $today) {
            continue;
        }

        $phone = trim($row['phone']);
        if (empty($phone)) {
            $phone = trim($row['mobilephone']);
        }
        if (empty($phone)) {
            continue;
        }

        $log_sql = sprintf("SELECT COUNT(id) FROM %s WHERE invoice_id=%d AND message_type='due_reminder'
                            AND DATE(sent_at)='%s'",
                            $configValues['CONFIG_DB_TBL_DALOWAGATEWAYLOG'],
                            intval($row['id']), $dbSocket->escapeSimple($today));
        $already_sent = intval($dbSocket->getOne($log_sql)) > 0;
        if ($already_sent) {
            continue;
        }

        $message = str_replace(
            array('[InvoiceDue]', '[InvoiceTotalAmount]'),
            array($due_date, $row['total_amount']),
            $settings['message_template']
        );

        $sent = wa_gateway_send_text($settings, $phone, $message);
        if ($sent) {
            $log_insert = sprintf("INSERT INTO %s (invoice_id, phone, message_type, sent_at)
                                   VALUES (%d, '%s', 'due_reminder', '%s')",
                                   $configValues['CONFIG_DB_TBL_DALOWAGATEWAYLOG'],
                                   intval($row['id']), $dbSocket->escapeSimple($phone), date('Y-m-d H:i:s'));
            $dbSocket->query($log_insert);
        }
    }

    require_once(__DIR__ . '/../../../app/common/includes/db_close.php');
    } catch (Throwable $e) {
        $status_ok = false;
        $status_message = cron_status_message('Unhandled error: ' . $e->getMessage());
    }

    if ($status_ok) {
        cron_status_write($configValues, $status_name, 'ok', $status_message);
    } else {
        cron_status_write($configValues, $status_name, 'fail', $status_message);
    }
