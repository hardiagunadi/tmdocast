<?php
/*
 *********************************************************************************************************
 * daloRADIUS - PPPoE Users List
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

    $title = "Daftar User PPPoE";
    $help = "";
    print_html_prologue($title, $langCode);
    print_title_and_help($title, $help);

    include('../common/includes/db_open.php');
    $sql = sprintf("SELECT us.username, us.plan_name, us.expiration_date, mn.name AS nas_name
                    FROM %s AS us
                    LEFT JOIN %s AS mn ON us.nas_id = mn.id
                    WHERE us.service_type='pppoe' ORDER BY us.username ASC",
                    $configValues['CONFIG_DB_TBL_DALOMIKROTIKUSERS'],
                    $configValues['CONFIG_DB_TBL_DALOMIKROTIKNAS']);
    $res = $dbSocket->query($sql);
    include('../common/includes/db_close.php');

    echo '<div class="mb-3"><a class="btn btn-primary" href="pppoe-new.php">Tambah User PPPoE</a></div>';
    echo '<div class="table-responsive"><table class="table table-sm table-striped">';
    echo '<thead><tr><th>Username</th><th>Paket</th><th>NAS</th><th>Kedaluwarsa</th></tr></thead><tbody>';
    if (!DB::isError($res)) {
        while ($row = $res->fetchRow(DB_FETCHMODE_ASSOC)) {
            printf('<tr><td>%s</td><td>%s</td><td>%s</td><td>%s</td></tr>',
                   htmlspecialchars($row['username'], ENT_QUOTES, 'UTF-8'),
                   htmlspecialchars($row['plan_name'], ENT_QUOTES, 'UTF-8'),
                   htmlspecialchars($row['nas_name'] ?: 'Semua NAS', ENT_QUOTES, 'UTF-8'),
                   htmlspecialchars($row['expiration_date'] ?: '-', ENT_QUOTES, 'UTF-8'));
        }
    }
    echo '</tbody></table></div>';

    print_html_epilogue();
