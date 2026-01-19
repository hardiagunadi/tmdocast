<?php
/*
 *********************************************************************************************************
 * daloRADIUS - Operator password change
 *********************************************************************************************************
 */

    include("library/checklogin.php");
    $operator = $_SESSION['operator_user'];
    $operator_id = intval($_SESSION['operator_id']);

    include_once('../common/includes/config_read.php');
    include_once("lang/main.php");
    include("../common/includes/layout.php");

    // init logging variables
    $log = "visited page: ";
    $logAction = "";
    $logDebugSQL = "";

    $minPasswordLength = intval($configValues['CONFIG_DB_PASSWORD_MIN_LENGTH']);
    $maxPasswordLength = intval($configValues['CONFIG_DB_PASSWORD_MAX_LENGTH']);

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (array_key_exists('csrf_token', $_POST) && isset($_POST['csrf_token']) && dalo_check_csrf_token($_POST['csrf_token'])) {
            $current_password = (array_key_exists('current_password', $_POST)) ? trim($_POST['current_password']) : "";
            $new_password = (array_key_exists('new_password', $_POST)) ? trim($_POST['new_password']) : "";
            $confirm_password = (array_key_exists('confirm_password', $_POST)) ? trim($_POST['confirm_password']) : "";

            if (empty($current_password) || empty($new_password) || empty($confirm_password)) {
                $failureMsg = "Current password, new password, and confirmation are required.";
                $logAction .= "Failed updating operator password (missing data) on page: ";
            } elseif ($new_password !== $confirm_password) {
                $failureMsg = "New password and confirmation do not match.";
                $logAction .= "Failed updating operator password (mismatch) on page: ";
            } elseif ($minPasswordLength > 0 && strlen($new_password) < $minPasswordLength) {
                $failureMsg = sprintf("New password must be at least %d characters.", $minPasswordLength);
                $logAction .= "Failed updating operator password (too short) on page: ";
            } elseif ($maxPasswordLength > 0 && strlen($new_password) > $maxPasswordLength) {
                $failureMsg = sprintf("New password must be at most %d characters.", $maxPasswordLength);
                $logAction .= "Failed updating operator password (too long) on page: ";
            } else {
                include('../common/includes/db_open.php');

                $sql = sprintf("SELECT password FROM %s WHERE id=%d",
                               $configValues['CONFIG_DB_TBL_DALOOPERATORS'], $operator_id);
                $stored_password = $dbSocket->getOne($sql);
                $logDebugSQL .= "$sql;\n";

                if ($stored_password !== $current_password) {
                    $failureMsg = "Current password is invalid.";
                    $logAction .= "Failed updating operator password (invalid current password) on page: ";
                } else {
                    $current_datetime = date('Y-m-d H:i:s');
                    $sql = sprintf("UPDATE %s SET password='%s', updatedate='%s', updateby='%s' WHERE id=%d",
                                   $configValues['CONFIG_DB_TBL_DALOOPERATORS'],
                                   $dbSocket->escapeSimple($new_password),
                                   $current_datetime,
                                   $dbSocket->escapeSimple($operator),
                                   $operator_id);
                    $res = $dbSocket->query($sql);
                    $logDebugSQL .= "$sql;\n";

                    $successMsg = "Operator password updated.";
                    $logAction .= "Successfully updated operator password on page: ";
                }

                include('../common/includes/db_close.php');
            }
        } else {
            $failureMsg = "CSRF token error";
            $logAction .= "$failureMsg on page: ";
        }
    }

    // print HTML prologue
    $title = t('Intro','configoperatorpassword.php');
    $help = t('helpPage','configoperatorpassword');

    print_html_prologue($title, $langCode);

    print_title_and_help($title, $help);

    include_once('include/management/actionMessages.php');

    $input_descriptors0 = array();

    $input_descriptors0[] = array(
                                    "name" => "current_password",
                                    "type" => "password",
                                    "caption" => t('all','CurrentPassword'),
                                 );

    $input_descriptors0[] = array(
                                    "name" => "new_password",
                                    "type" => "password",
                                    "caption" => t('all','NewPassword'),
                                 );

    $input_descriptors0[] = array(
                                    "name" => "confirm_password",
                                    "type" => "password",
                                    "caption" => t('all','ConfirmPassword'),
                                 );

    $input_descriptors0[] = array(
                                    "name" => "csrf_token",
                                    "type" => "hidden",
                                    "value" => dalo_csrf_token(),
                                 );

    $input_descriptors0[] = array(
                                    "type" => "submit",
                                    "name" => "submit",
                                    "value" => t('buttons','apply')
                                 );

    $fieldset0_descriptor = array(
                                    "title" => t('title','Settings'),
                                 );

    open_form();

    open_fieldset($fieldset0_descriptor);

    foreach ($input_descriptors0 as $input_descriptor) {
        print_form_component($input_descriptor);
    }

    close_fieldset();

    close_form();

    include('include/config/logging.php');
    print_footer_and_html_epilogue();
?>
