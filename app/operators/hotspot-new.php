<?php
/*
 *********************************************************************************************************
 * daloRADIUS - New Hotspot User
 *********************************************************************************************************
 */

    include ("library/checklogin.php");
    $operator = $_SESSION['operator_user'];

    include('../common/includes/config_read.php');
    include('library/check_operator_perm.php');

    include_once("lang/main.php");
    include("../common/includes/validation.php");
    include("../common/includes/layout.php");
    include("include/management/functions.php");
    include("include/management/populate_selectbox.php");
    include("library/attributes.php");
    include("../common/includes/mikrotik.php");

    $log = "visited page: ";
    $logAction = "";
    $logDebugSQL = "";

    $username = "";
    $password = "";
    $planName = "";
    $nas_id = "all";
    $active_days = "";
    $phone = "";

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (array_key_exists('csrf_token', $_POST) && isset($_POST['csrf_token']) && dalo_check_csrf_token($_POST['csrf_token'])) {
            $username = (array_key_exists('username', $_POST) && !empty(str_replace("%", "", trim($_POST['username']))))
                      ? str_replace("%", "", trim($_POST['username'])) : "";
            $password = (array_key_exists('password', $_POST) && isset($_POST['password'])) ? $_POST['password'] : "";
            $planName = (array_key_exists('planName', $_POST) && !empty(trim($_POST['planName']))) ? trim($_POST['planName']) : "";
            $nas_id = (array_key_exists('nas_id', $_POST)) ? trim($_POST['nas_id']) : "";
            $active_days = (array_key_exists('active_days', $_POST) && intval($_POST['active_days']) > 0) ? intval($_POST['active_days']) : "";
            $phone = (array_key_exists('phone', $_POST) && !empty(trim($_POST['phone']))) ? trim($_POST['phone']) : "";

            $username_enc = (!empty($username)) ? htmlspecialchars($username, ENT_QUOTES, 'UTF-8') : "";

            include('../common/includes/db_open.php');
            $userExists = user_exists($dbSocket, $username);

            if ($userExists) {
                $failureMsg = "User sudah ada: <b>$username_enc</b>";
                $logAction .= "Failed adding Hotspot user (exists) on page: ";
            } elseif (empty($username) || empty($password) || empty($planName)) {
                $failureMsg = "Username, password, dan paket wajib diisi.";
                $logAction .= "Failed adding Hotspot user (missing data) on page: ";
            } else {
                $passwordType = $valid_passwordTypes[0];
                $passwordValue = hashPasswordAttribute($passwordType, $password);
                $sql = sprintf("INSERT INTO %s (id, username, attribute, op, value) VALUES (0, '%s', '%s', ':=', '%s')",
                               $configValues['CONFIG_DB_TBL_RADCHECK'],
                               $dbSocket->escapeSimple($username),
                               $dbSocket->escapeSimple($passwordType),
                               $dbSocket->escapeSimple($passwordValue));
                $dbSocket->query($sql);
                $logDebugSQL .= "$sql;\n";

                $expiration_date = null;
                if (!empty($active_days)) {
                    $expiration_date = date('Y-m-d', strtotime("+$active_days days"));
                    $sql = sprintf("INSERT INTO %s (id, username, attribute, op, value) VALUES (0, '%s', 'Expiration', ':=', '%s')",
                                   $configValues['CONFIG_DB_TBL_RADCHECK'],
                                   $dbSocket->escapeSimple($username),
                                   $dbSocket->escapeSimple($expiration_date));
                    $dbSocket->query($sql);
                    $logDebugSQL .= "$sql;\n";
                }

                $current_datetime = date('Y-m-d H:i:s');
                $sql = sprintf("INSERT INTO %s (username, planName, phone, creationdate, creationby, updatedate, updateby)
                                VALUES ('%s', '%s', '%s', '%s', '%s', '%s', '%s')",
                                $configValues['CONFIG_DB_TBL_DALOUSERBILLINFO'],
                                $dbSocket->escapeSimple($username), $dbSocket->escapeSimple($planName),
                                $dbSocket->escapeSimple($phone), $current_datetime, $dbSocket->escapeSimple($operator),
                                $current_datetime, $dbSocket->escapeSimple($operator));
                $dbSocket->query($sql);
                $logDebugSQL .= "$sql;\n";

                $nas_id_value = (intval($nas_id) > 0) ? intval($nas_id) : "NULL";
                $sql = sprintf("INSERT INTO %s (username, service_type, nas_id, plan_name, expiration_date, creationdate, creationby, updatedate, updateby)
                                VALUES ('%s', 'hotspot', %s, '%s', %s, '%s', '%s', '%s', '%s')",
                                $configValues['CONFIG_DB_TBL_DALOMIKROTIKUSERS'],
                                $dbSocket->escapeSimple($username),
                                $nas_id_value,
                                $dbSocket->escapeSimple($planName),
                                ($expiration_date ? "'" . $dbSocket->escapeSimple($expiration_date) . "'" : "NULL"),
                                $current_datetime, $dbSocket->escapeSimple($operator),
                                $current_datetime, $dbSocket->escapeSimple($operator));
                $dbSocket->query($sql);
                $logDebugSQL .= "$sql;\n";

                $plan_sql = sprintf("SELECT planBandwidthUp, planBandwidthDown FROM %s WHERE planName='%s' LIMIT 1",
                                    $configValues['CONFIG_DB_TBL_DALOBILLINGPLANS'], $dbSocket->escapeSimple($planName));
                $plan_row = $dbSocket->getRow($plan_sql, array(), DB_FETCHMODE_ASSOC);
                $rate_limit = mikrotik_rate_limit($plan_row['planBandwidthDown'] ?? '', $plan_row['planBandwidthUp'] ?? '');

                $nas_list = mikrotik_list_nas($dbSocket, $configValues);
                foreach ($nas_list as $nas) {
                    if (intval($nas['is_active']) !== 1) {
                        continue;
                    }
                    if (intval($nas_id) > 0 && intval($nas['id']) !== intval($nas_id)) {
                        continue;
                    }
                    $api = mikrotik_connect($nas);
                    if ($api) {
                        mikrotik_sync_profile($api, 'hotspot', $planName, $rate_limit, $nas['isolir_profile'], $nas['redirect_url']);
                        mikrotik_sync_profile($api, 'hotspot', $nas['isolir_profile'], '', $nas['isolir_profile'], $nas['redirect_url']);
                        $api->disconnect();
                    }
                }

                $successMsg = "User Hotspot berhasil dibuat: <b>$username_enc</b>";
                $logAction .= "Added Hotspot user on page: ";
            }

            include('../common/includes/db_close.php');
        } else {
            $failureMsg = "CSRF token error";
            $logAction .= "$failureMsg on page: ";
        }
    }

    $title = "User Hotspot Baru";
    $help = "";
    $extra_css = array();
    $extra_js = array("static/js/productive_funcs.js");
    print_html_prologue($title, $langCode, $extra_css, $extra_js);
    print_title_and_help($title, $help);
    include_once('include/management/actionMessages.php');

    include('../common/includes/db_open.php');
    $nas_list = mikrotik_list_nas($dbSocket, $configValues);
    include('../common/includes/db_close.php');

    $nas_options = array("all" => "Semua NAS");
    foreach ($nas_list as $nas) {
        $nas_options[$nas['id']] = $nas['name'];
    }

    $plan_options = get_plans();

    $input_descriptors0 = array();
    $input_descriptors0[] = array(
        "name" => "username",
        "caption" => t('all','Username'),
        "type" => "text",
        "value" => $username,
    );
    $input_descriptors0[] = array(
        "name" => "password",
        "caption" => t('all','Password'),
        "type" => "text",
        "value" => $password,
        "random" => true,
    );
    $input_descriptors0[] = array(
        "name" => "planName",
        "caption" => t('all','PlanName'),
        "type" => "select",
        "options" => $plan_options,
        "selected_value" => $planName,
    );
    $input_descriptors0[] = array(
        "name" => "nas_id",
        "caption" => "NAS MikroTik",
        "type" => "select",
        "options" => $nas_options,
        "selected_value" => $nas_id,
    );
    $input_descriptors0[] = array(
        "name" => "active_days",
        "caption" => "Masa Aktif (hari)",
        "type" => "number",
        "min" => "1",
        "value" => $active_days,
    );
    $input_descriptors0[] = array(
        "name" => "phone",
        "caption" => "No. WhatsApp",
        "type" => "text",
        "value" => $phone,
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
    open_fieldset(array("title" => "Data Hotspot"));
    foreach ($input_descriptors0 as $input_descriptor) {
        print_form_component($input_descriptor);
    }
    foreach ($input_descriptors1 as $input_descriptor) {
        print_form_component($input_descriptor);
    }
    close_fieldset();
    close_form();

    print_html_epilogue();
