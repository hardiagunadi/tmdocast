<?php
/*
 *********************************************************************************************************
 * daloRADIUS - Mark invoice as paid (manual)
 *********************************************************************************************************
 */

    include ("library/checklogin.php");
    $operator = $_SESSION['operator_user'];

    include('../common/includes/config_read.php');
    include('library/check_operator_perm.php');

    include_once("lang/main.php");
    include("../common/includes/validation.php");
    include("../common/includes/layout.php");
    include("../common/includes/mikrotik.php");

    $log = "visited page: ";
    $logAction = "";
    $logDebugSQL = "";

    $invoice_id = (array_key_exists('invoice_id', $_GET) && intval($_GET['invoice_id']) > 0)
                ? intval($_GET['invoice_id']) : 0;

    if ($invoice_id <= 0) {
        $failureMsg = "Invoice ID tidak valid.";
    } else {
        include('../common/includes/db_open.php');

        $paid_id = intval($dbSocket->getOne(
            sprintf("SELECT id FROM %s WHERE value='paid' LIMIT 1", $configValues['CONFIG_DB_TBL_DALOBILLINGINVOICESTATUS'])
        ));

        $sql_total = sprintf("SELECT SUM(amount + tax_amount) FROM %s WHERE invoice_id=%d",
                             $configValues['CONFIG_DB_TBL_DALOBILLINGINVOICEITEMS'], $invoice_id);
        $total = floatval($dbSocket->getOne($sql_total));

        $sql_invoice = sprintf("SELECT user_id, status_id FROM %s WHERE id=%d",
                               $configValues['CONFIG_DB_TBL_DALOBILLINGINVOICE'], $invoice_id);
        $invoice_row = $dbSocket->getRow($sql_invoice, array(), DB_FETCHMODE_ASSOC);
        $user_id = isset($invoice_row['user_id']) ? intval($invoice_row['user_id']) : 0;

        $sql_user = sprintf("SELECT username, planName FROM %s WHERE id=%d",
                            $configValues['CONFIG_DB_TBL_DALOUSERBILLINFO'], $user_id);
        $user_row = $dbSocket->getRow($sql_user, array(), DB_FETCHMODE_ASSOC);

        if (DB::isError($invoice_row) || empty($invoice_row)) {
            $failureMsg = "Invoice tidak ditemukan.";
        } else if (intval($invoice_row['status_id']) === $paid_id) {
            $failureMsg = "Invoice sudah lunas.";
        } else if (DB::isError($user_row) || empty($user_row)) {
            $failureMsg = "User invoice tidak ditemukan.";
        } else {
            $current_datetime = date('Y-m-d H:i:s');
            $sql_update = sprintf("UPDATE %s SET status_id=%d, updatedate='%s', updateby='%s' WHERE id=%d",
                                  $configValues['CONFIG_DB_TBL_DALOBILLINGINVOICE'],
                                  $paid_id, $current_datetime, $dbSocket->escapeSimple($operator), $invoice_id);
            $res = $dbSocket->query($sql_update);

            if (!DB::isError($res)) {
                $sql_payment = sprintf("INSERT INTO %s (invoice_id, amount, date, type_id, notes, creationdate, creationby, updatedate, updateby)
                                        VALUES (%d, '%s', '%s', 1, 'Manual payment', '%s', '%s', '%s', '%s')",
                                        $configValues['CONFIG_DB_TBL_DALOPAYMENTS'],
                                        $invoice_id, $dbSocket->escapeSimple($total), $current_datetime,
                                        $current_datetime, $dbSocket->escapeSimple($operator),
                                        $current_datetime, $dbSocket->escapeSimple($operator));
                $dbSocket->query($sql_payment);

                $sql_service = sprintf("SELECT service_type, nas_id FROM %s WHERE username='%s' LIMIT 1",
                                       $configValues['CONFIG_DB_TBL_DALOMIKROTIKUSERS'],
                                       $dbSocket->escapeSimple($user_row['username']));
                $service = $dbSocket->getRow($sql_service, array(), DB_FETCHMODE_ASSOC);

                $password = '';
                $sql_pass = sprintf("SELECT value FROM %s WHERE username='%s' LIMIT 1",
                                    $configValues['CONFIG_DB_TBL_RADCHECK'], $dbSocket->escapeSimple($user_row['username']));
                $password = $dbSocket->getOne($sql_pass);

                $plan_name = $user_row['planName'];
                $service_type = (!empty($service['service_type'])) ? $service['service_type'] : 'hotspot';

                $nas_list = mikrotik_list_nas($dbSocket, $configValues);
                foreach ($nas_list as $nas) {
                    if (intval($nas['is_active']) !== 1) {
                        continue;
                    }
                    if (!empty($service['nas_id']) && intval($service['nas_id']) > 0 && intval($service['nas_id']) !== intval($nas['id'])) {
                        continue;
                    }

                    $api = mikrotik_connect($nas);
                    if ($api) {
                        $api->disconnect();
                    }
                }

                $successMsg = "Invoice berhasil ditandai lunas.";
                $logAction .= "Marked invoice as paid on page: ";
            } else {
                $failureMsg = "Gagal memperbarui status invoice.";
                $logAction .= "Failed marking invoice as paid on page: ";
            }
        }

        include('../common/includes/db_close.php');
    }

    $title = "Pembayaran Manual";
    $help = "";
    print_html_prologue($title, $langCode);
    print_title_and_help($title, $help);
    include_once('include/management/actionMessages.php');
    echo '<div class="mt-3"><a class="btn btn-secondary" href="bill-invoice-list.php">Kembali ke daftar invoice</a></div>';
    print_html_epilogue();
