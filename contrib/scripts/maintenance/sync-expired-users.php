<?php
/*
 *********************************************************************************************************
 * Sync expired users to isolir profile on MikroTik
 *********************************************************************************************************
 */

    require_once(__DIR__ . '/../../../app/common/includes/config_read.php');
    require_once(__DIR__ . '/../../../app/common/includes/db_open.php');
    require_once(__DIR__ . '/../../../app/common/includes/mikrotik.php');

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

    $status_name = 'sync-expired-users';
    $status_message = '';
    $status_ok = true;

    try {
    $today = date('Y-m-d');
    $due_days = 30;
    $settings_row = $dbSocket->getRow(sprintf("SELECT due_days FROM %s WHERE id=1", $configValues['CONFIG_DB_TBL_DALOWAGATEWAY']), array(), DB_FETCHMODE_ASSOC);
    if (!DB::isError($settings_row) && !empty($settings_row['due_days'])) {
        $due_days = intval($settings_row['due_days']);
    }

    $paid_status_id = intval($dbSocket->getOne(
        sprintf("SELECT id FROM %s WHERE value='paid' LIMIT 1", $configValues['CONFIG_DB_TBL_DALOBILLINGINVOICESTATUS'])
    ));

    $sql = sprintf("SELECT username, service_type, nas_id, plan_name, expiration_date
                    FROM %s WHERE expiration_date IS NOT NULL AND expiration_date <> '0000-00-00'
                    AND expiration_date < '%s'",
                    $configValues['CONFIG_DB_TBL_DALOMIKROTIKUSERS'],
                    $dbSocket->escapeSimple($today));
    $nas_list = mikrotik_list_nas($dbSocket, $configValues);

    $expired_users = array();
    $res = $dbSocket->query($sql);
    if (DB::isError($res)) {
        $status_ok = false;
        $status_message = cron_status_message('DB error while loading expired users: ' . $res->getMessage());
        cron_status_write($configValues, $status_name, 'fail', $status_message);
        require_once(__DIR__ . '/../../../app/common/includes/db_close.php');
        exit(1);
    }
    while ($row = $res->fetchRow(DB_FETCHMODE_ASSOC)) {
        $expired_users[$row['username']] = $row;
    }

    $sql_overdue = sprintf("SELECT DISTINCT ub.username
                            FROM %s AS i
                            INNER JOIN %s AS ub ON i.user_id=ub.id
                            WHERE i.status_id <> %d AND i.date <> '0000-00-00'
                            AND DATE_ADD(i.date, INTERVAL %d DAY) < '%s'",
                            $configValues['CONFIG_DB_TBL_DALOBILLINGINVOICE'],
                            $configValues['CONFIG_DB_TBL_DALOUSERBILLINFO'],
                            $paid_status_id, $due_days, $dbSocket->escapeSimple($today));
    $res_overdue = $dbSocket->query($sql_overdue);
    if (DB::isError($res_overdue)) {
        $status_ok = false;
        $status_message = cron_status_message('DB error while loading overdue invoices: ' . $res_overdue->getMessage());
        cron_status_write($configValues, $status_name, 'fail', $status_message);
        require_once(__DIR__ . '/../../../app/common/includes/db_close.php');
        exit(1);
    }
    while ($row = $res_overdue->fetchRow(DB_FETCHMODE_ASSOC)) {
        $sql_user = sprintf("SELECT username, service_type, nas_id, plan_name, expiration_date
                             FROM %s WHERE username='%s' LIMIT 1",
                             $configValues['CONFIG_DB_TBL_DALOMIKROTIKUSERS'],
                             $dbSocket->escapeSimple($row['username']));
        $user_row = $dbSocket->getRow($sql_user, array(), DB_FETCHMODE_ASSOC);
        if (!DB::isError($user_row) && !empty($user_row)) {
            $expired_users[$user_row['username']] = $user_row;
        }
    }

    foreach ($expired_users as $row) {
        $username = $row['username'];
        $service_type = $row['service_type'];
        $nas_id = intval($row['nas_id']);

        $sql_pass = sprintf("SELECT value FROM %s WHERE username='%s' LIMIT 1",
                            $configValues['CONFIG_DB_TBL_RADCHECK'], $dbSocket->escapeSimple($username));
        $password = $dbSocket->getOne($sql_pass);

        foreach ($nas_list as $nas) {
            if (intval($nas['is_active']) !== 1) {
                continue;
            }
            if ($nas_id > 0 && intval($nas['id']) !== $nas_id) {
                continue;
            }
            $api = mikrotik_connect($nas);
            if ($api) {
                mikrotik_sync_user($api, $service_type, $username, $password, $nas['isolir_profile']);
                $api->disconnect();
            }
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
