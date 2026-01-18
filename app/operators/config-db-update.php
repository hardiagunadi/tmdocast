<?php
/*
 *********************************************************************************************************
 * daloRADIUS - Database update helper
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

    function strip_sql_comments($sql) {
        $sql = preg_replace('/\\/\\*.*?\\*\\//s', '', $sql);
        $lines = preg_split('/\\r\\n|\\r|\\n/', $sql);
        $kept = array();
        foreach ($lines as $line) {
            $trim = ltrim($line);
            if ($trim === '' || strpos($trim, '--') === 0 || strpos($trim, '#') === 0) {
                continue;
            }
            $kept[] = $line;
        }
        return implode("\n", $kept);
    }

    function split_sql_statements($sql) {
        $statements = array();
        $buffer = '';
        $in_single = false;
        $in_double = false;
        $in_backtick = false;
        $length = strlen($sql);
        for ($i = 0; $i < $length; $i++) {
            $char = $sql[$i];
            $prev = ($i > 0) ? $sql[$i - 1] : '';
            if ($char === "'" && !$in_double && !$in_backtick && $prev !== '\\') {
                $in_single = !$in_single;
            } elseif ($char === '"' && !$in_single && !$in_backtick && $prev !== '\\') {
                $in_double = !$in_double;
            } elseif ($char === '`' && !$in_single && !$in_double) {
                $in_backtick = !$in_backtick;
            }

            if ($char === ';' && !$in_single && !$in_double && !$in_backtick) {
                $statement = trim($buffer);
                if ($statement !== '') {
                    $statements[] = $statement;
                }
                $buffer = '';
                continue;
            }
            $buffer .= $char;
        }
        $statement = trim($buffer);
        if ($statement !== '') {
            $statements[] = $statement;
        }
        return $statements;
    }

    $report = null;
    $reportErrors = array();

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (array_key_exists('csrf_token', $_POST) && isset($_POST['csrf_token']) && dalo_check_csrf_token($_POST['csrf_token'])) {
            $confirmed = (array_key_exists('confirm_update', $_POST) && intval($_POST['confirm_update']) === 1);
            if (!$confirmed) {
                $failureMsg = "Konfirmasi update database wajib dicentang.";
                $logAction .= "Failed database update (no confirmation) on page: ";
            } else {
                $update_file = realpath(__DIR__ . '/../../contrib/db/mariadb-daloradius-updates.sql');
                if ($update_file === false || !is_readable($update_file)) {
                    $failureMsg = "File update database tidak ditemukan.";
                    $logAction .= "Failed database update (missing file) on page: ";
                } else {
                    $sqlRaw = file_get_contents($update_file);
                    if ($sqlRaw === false) {
                        $failureMsg = "Gagal membaca file update database.";
                        $logAction .= "Failed database update (read error) on page: ";
                    } else {
                        set_time_limit(0);
                        $sqlClean = strip_sql_comments($sqlRaw);
                        $statements = split_sql_statements($sqlClean);
                        if (empty($statements)) {
                            $failureMsg = "Tidak ada statement SQL untuk dijalankan.";
                            $logAction .= "Failed database update (empty statements) on page: ";
                        } else {
                            include('../common/includes/db_open.php');
                            $report = array(
                                'total' => count($statements),
                                'success' => 0,
                                'failed' => 0,
                            );
                            foreach ($statements as $statement) {
                                $res = $dbSocket->query($statement);
                                $logDebugSQL .= $statement . ";\n";
                                if (DB::isError($res)) {
                                    $report['failed']++;
                                    $reportErrors[] = $res->getMessage() . " | " . $statement;
                                } else {
                                    $report['success']++;
                                }
                            }
                            include('../common/includes/db_close.php');
                            if ($report['failed'] > 0) {
                                $failureMsg = "Update database selesai dengan error. Berhasil: {$report['success']}, Gagal: {$report['failed']}.";
                                $logAction .= "Database update completed with errors on page: ";
                            } else {
                                $successMsg = "Update database berhasil. {$report['success']} statement dijalankan.";
                                $logAction .= "Database update completed on page: ";
                            }
                        }
                    }
                }
            }
        } else {
            $failureMsg = "CSRF token error";
            $logAction .= "$failureMsg on page: ";
        }
    }

    $title = "Update Database";
    $help = "Gunakan menu ini untuk menjalankan file pembaruan database tanpa command line. Pastikan melakukan backup terlebih dahulu.";
    print_html_prologue($title, $langCode);
    print_title_and_help($title, $help);
    include_once('include/management/actionMessages.php');

    $input_descriptors0 = array();
    $input_descriptors0[] = array(
        "name" => "confirm_update",
        "caption" => "Saya sudah backup database",
        "type" => "checkbox",
        "checked" => false,
        "value" => 1,
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
        "value" => "Jalankan Update",
    );

    open_form();
    open_fieldset(array("title" => "Update Database Otomatis"));
    foreach ($input_descriptors0 as $input_descriptor) {
        print_form_component($input_descriptor);
    }
    foreach ($input_descriptors1 as $input_descriptor) {
        print_form_component($input_descriptor);
    }
    close_fieldset();
    close_form();

    if ($report !== null) {
        echo '<div class="mt-4">';
        echo '<h5>Ringkasan Update</h5>';
        echo '<div class="alert alert-info">Total: ' . intval($report['total'])
           . ', Berhasil: ' . intval($report['success'])
           . ', Gagal: ' . intval($report['failed']) . '.</div>';
        if (!empty($reportErrors)) {
            echo '<pre class="small bg-light p-3 border">';
            echo htmlspecialchars(implode("\n\n", $reportErrors), ENT_QUOTES, 'UTF-8');
            echo '</pre>';
        }
        echo '</div>';
    }

    print_footer_and_html_epilogue();
