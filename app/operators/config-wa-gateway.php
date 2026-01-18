<?php
/*
 *********************************************************************************************************
 * daloRADIUS - WA Gateway configuration
 *********************************************************************************************************
 */

    include ("library/checklogin.php");
    $operator = $_SESSION['operator_user'];

    include('../common/includes/config_read.php');
    include('library/check_operator_perm.php');

    include_once("lang/main.php");
    include("../common/includes/validation.php");
    include("../common/includes/layout.php");

    $log = "visited page: ";
    $logAction = "";
    $logDebugSQL = "";

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (array_key_exists('csrf_token', $_POST) && isset($_POST['csrf_token']) && dalo_check_csrf_token($_POST['csrf_token'])) {
            $is_enabled = (array_key_exists('is_enabled', $_POST) && intval($_POST['is_enabled']) === 1) ? 1 : 0;
            $base_url = (array_key_exists('base_url', $_POST)) ? trim($_POST['base_url']) : "";
            $api_key = (array_key_exists('api_key', $_POST)) ? trim($_POST['api_key']) : "";
            $session_name = (array_key_exists('session_name', $_POST)) ? trim($_POST['session_name']) : "";
            $masterkey = (array_key_exists('masterkey', $_POST)) ? trim($_POST['masterkey']) : "";
            $due_days = (array_key_exists('due_days', $_POST) && intval($_POST['due_days']) > 0) ? intval($_POST['due_days']) : 30;
            $reminder_days_before = (array_key_exists('reminder_days_before', $_POST) && intval($_POST['reminder_days_before']) >= 0)
                                  ? intval($_POST['reminder_days_before']) : 3;
            $message_template = (array_key_exists('message_template', $_POST)) ? trim($_POST['message_template']) : "";

            include('../common/includes/db_open.php');
            $current_datetime = date('Y-m-d H:i:s');
            $sql = sprintf("UPDATE %s SET is_enabled=%d, base_url='%s', api_key='%s', session_name='%s', masterkey='%s',
                            due_days=%d, reminder_days_before=%d, message_template='%s',
                            updatedate='%s', updateby='%s' WHERE id=1",
                            $configValues['CONFIG_DB_TBL_DALOWAGATEWAY'], $is_enabled,
                            $dbSocket->escapeSimple($base_url), $dbSocket->escapeSimple($api_key),
                            $dbSocket->escapeSimple($session_name), $dbSocket->escapeSimple($masterkey),
                            $due_days, $reminder_days_before,
                            $dbSocket->escapeSimple($message_template), $current_datetime, $dbSocket->escapeSimple($operator));
            $res = $dbSocket->query($sql);
            $logDebugSQL .= "$sql;\n";
            include('../common/includes/db_close.php');

            if (!DB::isError($res)) {
                $successMsg = "Pengaturan WA Gateway berhasil disimpan.";
                $logAction .= "Updated WA gateway settings on page: ";
            } else {
                $failureMsg = "Gagal menyimpan pengaturan WA Gateway.";
                $logAction .= "Failed updating WA gateway settings on page: ";
            }
        } else {
            $failureMsg = "CSRF token error";
            $logAction .= "$failureMsg on page: ";
        }
    }

    include('../common/includes/db_open.php');
    $sql = sprintf("SELECT id, is_enabled, base_url, api_key, session_name, masterkey, due_days, reminder_days_before, message_template
                    FROM %s WHERE id=1", $configValues['CONFIG_DB_TBL_DALOWAGATEWAY']);
    $row = $dbSocket->getRow($sql, array(), DB_FETCHMODE_ASSOC);
    include('../common/includes/db_close.php');

    $settings = array(
        'is_enabled' => 0,
        'base_url' => '',
        'api_key' => '',
        'session_name' => '',
        'masterkey' => '',
        'due_days' => 30,
        'reminder_days_before' => 3,
        'message_template' => 'Tagihan Anda akan jatuh tempo pada [InvoiceDue]. Total: [InvoiceTotalAmount]. Silakan melakukan pembayaran.',
    );
    if (!DB::isError($row) && !empty($row)) {
        $settings = array_merge($settings, $row);
    }

    $title = "Integrasi WA Gateway";
    $help = "";
    print_html_prologue($title, $langCode);
    print_title_and_help($title, $help);
    include_once('include/management/actionMessages.php');

    $input_descriptors0 = array();
    $input_descriptors0[] = array(
        "name" => "is_enabled",
        "caption" => "Aktif",
        "type" => "checkbox",
        "checked" => (intval($settings['is_enabled']) === 1),
        "value" => 1,
    );
    $input_descriptors0[] = array(
        "name" => "base_url",
        "caption" => "Base URL",
        "type" => "text",
        "value" => $settings['base_url'],
    );
    $input_descriptors0[] = array(
        "name" => "api_key",
        "caption" => "API Key",
        "type" => "text",
        "value" => $settings['api_key'],
    );
    $input_descriptors0[] = array(
        "name" => "session_name",
        "caption" => "Session Name",
        "type" => "text",
        "value" => $settings['session_name'],
    );
    $input_descriptors0[] = array(
        "name" => "masterkey",
        "caption" => "Masterkey",
        "type" => "text",
        "value" => $settings['masterkey'],
    );
    $input_descriptors0[] = array(
        "name" => "due_days",
        "caption" => "Jatuh Tempo (hari)",
        "type" => "number",
        "min" => "1",
        "value" => $settings['due_days'],
    );
    $input_descriptors0[] = array(
        "name" => "reminder_days_before",
        "caption" => "Pengingat (hari sebelum jatuh tempo)",
        "type" => "number",
        "min" => "0",
        "value" => $settings['reminder_days_before'],
    );
    $input_descriptors0[] = array(
        "name" => "message_template",
        "caption" => "Template Pesan",
        "type" => "textarea",
        "content" => $settings['message_template'],
    );

    $input_descriptors1 = array();
    $input_descriptors1[] = array(
        "name" => "csrf_token",
        "type" => "hidden",
        "value" => dalo_csrf_token(),
    );
    $input_descriptors1[] = array(
        "type" => "submit",
        "name" => "submit",
        "value" => "Simpan",
    );

    open_form();
    open_fieldset(array("title" => "Pengaturan WA Gateway"));
    foreach ($input_descriptors0 as $input_descriptor) {
        print_form_component($input_descriptor);
    }
    foreach ($input_descriptors1 as $input_descriptor) {
        print_form_component($input_descriptor);
    }
    close_fieldset();
    close_form();

    print_html_epilogue();
