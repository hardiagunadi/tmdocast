<?php
/*
 *********************************************************************************************************
 * daloRADIUS - MikroTik NAS configuration
 *********************************************************************************************************
 */

    include ("library/checklogin.php");
    $operator = $_SESSION['operator_user'];

    include('../common/includes/config_read.php');
    include('library/check_operator_perm.php');

    include_once("lang/main.php");
    include("../common/includes/validation.php");
    include("../common/includes/layout.php");
    include_once("include/management/populate_selectbox.php");
    include_once("../common/includes/mikrotik.php");

    // init logging variables
    $log = "visited page: ";
    $logAction = "";
    $logDebugSQL = "";

    function random_string($length = 4) {
        $chars = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
        $out = '';
        for ($i = 0; $i < $length; $i++) {
            $out .= $chars[random_int(0, strlen($chars) - 1)];
        }
        return $out;
    }

    $defaults = array(
        'name' => '',
        'nasname' => '',
        'host' => '',
        'port' => 8728,
        'api_username' => 'TMDradius' . random_string(4),
        'api_password' => random_string(8),
        'radius_server' => '',
        'radius_auth_port' => 1812,
        'radius_acct_port' => 1813,
        'pppoe_pool_network' => '172.16.0.0',
        'pppoe_pool_cidr' => 20,
        'isolir_profile' => 'isolir',
        'redirect_url' => '',
        'is_active' => 1,
    );

    function radius_server_address() {
        if (!empty($_SERVER['SERVER_ADDR'])) {
            return $_SERVER['SERVER_ADDR'];
        }
        if (!empty($_SERVER['SERVER_NAME'])) {
            return $_SERVER['SERVER_NAME'];
        }
        return '127.0.0.1';
    }

    function build_mikrotik_radius_script($radiusServer, $radiusSecret, $apiUser, $apiPass, $authPort, $acctPort, $coaPort) {
        $radiusServer = trim($radiusServer) !== '' ? $radiusServer : '127.0.0.1';
        $radiusSecret = trim($radiusSecret) !== '' ? $radiusSecret : $apiPass;
        $apiUserEsc = str_replace('"', '\"', $apiUser);
        $apiPassEsc = str_replace('"', '\"', $apiPass);
        $radiusSecretEsc = str_replace('"', '\"', $radiusSecret);
        $script = array(
            '/radius remove [find comment="added by TMDRadius"]',
            '/user remove [find comment="user for mixradius authentication"]',
            '/user group remove [find comment="group for mixradius authentication"]',
            '/user group add name="mixradius.group" policy=read,write,api,test,policy,sensitive comment="group for mixradius authentication"',
            sprintf('/user add name="%s" group="mixradius.group" password="%s" comment="user for mixradius authentication"', $apiUserEsc, $apiPassEsc),
            sprintf('/radius add authentication-port=%d accounting-port=%d timeout=2s comment="added by TMDRadius" service=ppp,hotspot,login address=%s secret="%s"', $authPort, $acctPort, $radiusServer, $radiusSecretEsc),
            '/ip hotspot profile set use-radius=yes radius-accounting=yes radius-interim-update="00:10:00" nas-port-type="wireless-802.11" [find name!=""]',
            '/ppp aaa set use-radius=yes accounting=yes interim-update="00:10:00"',
            sprintf('/radius incoming set accept=yes port=%d', $coaPort),
        );
        return implode(";\n", $script) . ';';
    }

    function mikrotik_set_radius_integration($nasRow, $radiusServer, $radiusSecret, $authPort, $acctPort, $coaPort) {
        $api = mikrotik_connect($nasRow);
        if (!$api) {
            return "Gagal terhubung ke API MikroTik.";
        }

        $radiusServer = trim($radiusServer) !== '' ? $radiusServer : '127.0.0.1';
        $radiusSecret = trim($radiusSecret) !== '' ? $radiusSecret : $nasRow['api_password'];

        $commentRadius = 'added by TMDRadius';
        $commentUser = 'user for mixradius authentication';
        $commentGroup = 'group for mixradius authentication';
        $groupName = 'mixradius.group';

        $userItems = $api->command('/user/print', array('?comment' => $commentUser));
        foreach ($userItems as $item) {
            if (isset($item['.id'])) {
                $api->command('/user/remove', array('.id' => $item['.id']));
            }
        }

        $groupItems = $api->command('/user/group/print', array('?comment' => $commentGroup));
        foreach ($groupItems as $item) {
            if (isset($item['.id'])) {
                $api->command('/user/group/remove', array('.id' => $item['.id']));
            }
        }

        $policy = 'read,write,api,test,policy,sensitive';
        $groupId = mikrotik_find($api, '/user/group', $groupName);
        if ($groupId) {
            $api->command('/user/group/set', array('.id' => $groupId, 'name' => $groupName, 'policy' => $policy, 'comment' => $commentGroup));
        } else {
            $api->command('/user/group/add', array('name' => $groupName, 'policy' => $policy, 'comment' => $commentGroup));
        }

        $userId = mikrotik_find($api, '/user', $nasRow['api_username']);
        $userParams = array(
            'name' => $nasRow['api_username'],
            'group' => $groupName,
            'password' => $nasRow['api_password'],
            'comment' => $commentUser,
        );
        if ($userId) {
            $api->command('/user/set', array_merge(array('.id' => $userId), $userParams));
        } else {
            $api->command('/user/add', $userParams);
        }

        $radiusParams = array(
            'authentication-port' => $authPort,
            'accounting-port' => $acctPort,
            'timeout' => '2s',
            'comment' => $commentRadius,
            'service' => 'ppp,hotspot,login',
            'address' => $radiusServer,
            'secret' => $radiusSecret,
        );
        $radiusItems = $api->command('/radius/print');
        $matches = array();
        if (!empty($radiusItems)) {
            foreach ($radiusItems as $item) {
                if (!isset($item['.id'])) {
                    continue;
                }
                $comment = $item['comment'] ?? '';
                $address = $item['address'] ?? '';
                $authPort = $item['authentication-port'] ?? '';
                $acctPort = $item['accounting-port'] ?? '';
                if ($comment === $commentRadius) {
                    $matches[] = $item;
                    continue;
                }
                if ($address === $radiusServer && strval($authPort) === strval($radiusParams['authentication-port'])
                    && strval($acctPort) === strval($radiusParams['accounting-port'])) {
                    $matches[] = $item;
                }
            }
        }

        if (!empty($matches) && isset($matches[0]['.id'])) {
            $primaryId = $matches[0]['.id'];
            $api->command('/radius/set', array_merge(array('.id' => $primaryId), $radiusParams));
            for ($i = 1; $i < count($matches); $i++) {
                if (isset($matches[$i]['.id'])) {
                    $api->command('/radius/remove', array('.id' => $matches[$i]['.id']));
                }
            }
        } else {
            $api->command('/radius/add', $radiusParams);
        }

        $profiles = $api->command('/ip/hotspot/profile/print');
        foreach ($profiles as $profile) {
            if (!isset($profile['.id'])) {
                continue;
            }
            $api->command('/ip/hotspot/profile/set', array(
                '.id' => $profile['.id'],
                'use-radius' => 'yes',
                'radius-accounting' => 'yes',
                'radius-interim-update' => '00:10:00',
                'nas-port-type' => 'wireless-802.11',
            ));
        }

        $api->command('/ppp/aaa/set', array(
            'use-radius' => 'yes',
            'accounting' => 'yes',
            'interim-update' => '00:10:00',
        ));

        $incoming = $api->command('/radius/incoming/print');
        if (!empty($incoming) && isset($incoming[0]['.id'])) {
            $api->command('/radius/incoming/set', array('.id' => $incoming[0]['.id'], 'accept' => 'yes', 'port' => $coaPort));
        } else {
            $api->command('/radius/incoming/set', array('accept' => 'yes', 'port' => $coaPort));
        }

        return null;
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'integrate') {
        if (array_key_exists('csrf_token', $_POST) && isset($_POST['csrf_token']) && dalo_check_csrf_token($_POST['csrf_token'])) {
            $id = (array_key_exists('id', $_POST) && intval($_POST['id']) > 0) ? intval($_POST['id']) : 0;
            if ($id > 0) {
                $integrationError = null;
                include('../common/includes/db_open.php');
                $sql = sprintf("SELECT id, name, nasname, host, port, api_username, api_password, radius_server,
                                radius_auth_port, radius_acct_port FROM %s WHERE id=%d",
                               $configValues['CONFIG_DB_TBL_DALOMIKROTIKNAS'], $id);
                $nasRow = $dbSocket->getRow($sql, array(), DB_FETCHMODE_ASSOC);
                if (DB::isError($nasRow) || empty($nasRow)) {
                    include('../common/includes/db_close.php');
                    $integrationError = "Data MikroTik tidak ditemukan.";
                } else {
                    $radiusSecret = '';
                    if (!empty($nasRow['nasname'])) {
                        $sqlSecret = sprintf("SELECT secret FROM %s WHERE nasname='%s' LIMIT 1",
                                             $configValues['CONFIG_DB_TBL_RADNAS'], $dbSocket->escapeSimple($nasRow['nasname']));
                        $secretRow = $dbSocket->getRow($sqlSecret, array(), DB_FETCHMODE_ASSOC);
                        if (!DB::isError($secretRow) && !empty($secretRow['secret'])) {
                            $radiusSecret = $secretRow['secret'];
                        }
                    }
                    include('../common/includes/db_close.php');

                    $radiusServer = !empty($nasRow['radius_server']) ? $nasRow['radius_server'] : radius_server_address();
                    $authPort = (!empty($nasRow['radius_auth_port'])) ? intval($nasRow['radius_auth_port']) : 1812;
                    $acctPort = (!empty($nasRow['radius_acct_port'])) ? intval($nasRow['radius_acct_port']) : 1813;
                    $coaPort = 3799;
                    $error = mikrotik_set_radius_integration($nasRow, $radiusServer, $radiusSecret, $authPort, $acctPort, $coaPort);
                    if ($error === null) {
                        $successMsg = "Integrasi RADIUS ke MikroTik berhasil.";
                        $logAction .= "Integrated MikroTik RADIUS via API on page: ";
                    } else {
                        $integrationError = $error;
                    }
                }
                if ($integrationError !== null) {
                    $failureMsg = $integrationError;
                    $logAction .= "Failed MikroTik RADIUS integration via API on page: ";
                }
            } else {
                $failureMsg = "Data MikroTik tidak valid.";
                $logAction .= "Failed MikroTik RADIUS integration via API (invalid data) on page: ";
            }
        } else {
            $failureMsg = "CSRF token error";
            $logAction .= "$failureMsg on page: ";
        }
    } elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (array_key_exists('csrf_token', $_POST) && isset($_POST['csrf_token']) && dalo_check_csrf_token($_POST['csrf_token'])) {
            $id = (array_key_exists('id', $_POST) && intval($_POST['id']) > 0) ? intval($_POST['id']) : 0;
            $name = (array_key_exists('name', $_POST) && !empty(trim($_POST['name']))) ? trim($_POST['name']) : "";
            $nasname = (array_key_exists('nasname', $_POST) && !empty(trim($_POST['nasname']))) ? trim($_POST['nasname']) : "";
            $host = (array_key_exists('host', $_POST) && !empty(trim($_POST['host']))) ? trim($_POST['host']) : "";
            $port = (array_key_exists('port', $_POST) && intval($_POST['port']) > 0) ? intval($_POST['port']) : 8728;
            $api_username = (array_key_exists('api_username', $_POST) && !empty(trim($_POST['api_username']))) ? trim($_POST['api_username']) : "";
            $api_password = (array_key_exists('api_password', $_POST) && !empty(trim($_POST['api_password']))) ? trim($_POST['api_password']) : "";
            $radius_server = (array_key_exists('radius_server', $_POST)) ? trim($_POST['radius_server']) : "";
            $radius_auth_port = (array_key_exists('radius_auth_port', $_POST) && intval($_POST['radius_auth_port']) > 0) ? intval($_POST['radius_auth_port']) : 1812;
            $radius_acct_port = (array_key_exists('radius_acct_port', $_POST) && intval($_POST['radius_acct_port']) > 0) ? intval($_POST['radius_acct_port']) : 1813;
            $pppoe_pool_network = (array_key_exists('pppoe_pool_network', $_POST)) ? trim($_POST['pppoe_pool_network']) : "172.16.0.0";
            $pppoe_pool_cidr = (array_key_exists('pppoe_pool_cidr', $_POST) && intval($_POST['pppoe_pool_cidr']) > 0) ? intval($_POST['pppoe_pool_cidr']) : 20;
            $isolir_profile = (array_key_exists('isolir_profile', $_POST) && !empty(trim($_POST['isolir_profile']))) ? trim($_POST['isolir_profile']) : "isolir";
            $redirect_url = (array_key_exists('redirect_url', $_POST)) ? trim($_POST['redirect_url']) : "";
            $is_active = (array_key_exists('is_active', $_POST) && intval($_POST['is_active']) === 1) ? 1 : 0;

            if (empty($name) || empty($host) || empty($api_username) || empty($api_password)) {
                $failureMsg = "Nama, Host, Username API, dan Password API wajib diisi.";
                $logAction .= "Failed saving MikroTik NAS (missing data) on page: ";
            } else {
                include('../common/includes/db_open.php');

                $current_datetime = date('Y-m-d H:i:s');
                if ($id > 0) {
                    $sql = sprintf("UPDATE %s SET name='%s', nasname='%s', host='%s', port='%s', api_username='%s',
                                    api_password='%s', radius_server='%s', radius_auth_port='%s', radius_acct_port='%s',
                                    pppoe_pool_network='%s', pppoe_pool_cidr='%s',
                                    isolir_profile='%s', redirect_url='%s', is_active=%d,
                                    updatedate='%s', updateby='%s'
                                    WHERE id=%d",
                                    $configValues['CONFIG_DB_TBL_DALOMIKROTIKNAS'],
                                    $dbSocket->escapeSimple($name), $dbSocket->escapeSimple($nasname),
                                    $dbSocket->escapeSimple($host), $dbSocket->escapeSimple($port),
                                    $dbSocket->escapeSimple($api_username), $dbSocket->escapeSimple($api_password),
                                    $dbSocket->escapeSimple($radius_server), $dbSocket->escapeSimple($radius_auth_port),
                                    $dbSocket->escapeSimple($radius_acct_port),
                                    $dbSocket->escapeSimple($pppoe_pool_network), $dbSocket->escapeSimple($pppoe_pool_cidr),
                                    $dbSocket->escapeSimple($isolir_profile), $dbSocket->escapeSimple($redirect_url),
                                    $is_active, $current_datetime, $dbSocket->escapeSimple($operator), $id);
                } else {
                    $sql = sprintf("INSERT INTO %s (name, nasname, host, port, api_username, api_password, radius_server,
                                    radius_auth_port, radius_acct_port, pppoe_pool_network, pppoe_pool_cidr,
                                    isolir_profile, redirect_url, is_active,
                                    creationdate, creationby, updatedate, updateby)
                                    VALUES ('%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', %d,
                                    '%s', '%s', '%s', '%s')",
                                    $configValues['CONFIG_DB_TBL_DALOMIKROTIKNAS'],
                                    $dbSocket->escapeSimple($name), $dbSocket->escapeSimple($nasname),
                                    $dbSocket->escapeSimple($host), $dbSocket->escapeSimple($port),
                                    $dbSocket->escapeSimple($api_username), $dbSocket->escapeSimple($api_password),
                                    $dbSocket->escapeSimple($radius_server), $dbSocket->escapeSimple($radius_auth_port),
                                    $dbSocket->escapeSimple($radius_acct_port),
                                    $dbSocket->escapeSimple($pppoe_pool_network), $dbSocket->escapeSimple($pppoe_pool_cidr),
                                    $dbSocket->escapeSimple($isolir_profile), $dbSocket->escapeSimple($redirect_url),
                                    $is_active, $current_datetime, $dbSocket->escapeSimple($operator),
                                    $current_datetime, $dbSocket->escapeSimple($operator));
                }

                $res = $dbSocket->query($sql);
                $logDebugSQL .= "$sql;\n";
                if (!DB::isError($res)) {
                    $successMsg = "MikroTik NAS berhasil disimpan.";
                    $logAction .= "Saved MikroTik NAS on page: ";
                } else {
                    $failureMsg = "Gagal menyimpan MikroTik NAS.";
                    $logAction .= "Failed saving MikroTik NAS on page: ";
                }

                include('../common/includes/db_close.php');
            }
        } else {
            $failureMsg = "CSRF token error";
            $logAction .= "$failureMsg on page: ";
        }
    }

    // load record for edit
    $edit_id = (array_key_exists('id', $_GET) && intval($_GET['id']) > 0) ? intval($_GET['id']) : 0;
    $auto_show_form = ($edit_id > 0);
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'integrate') {
        $auto_show_form = false;
    }
    $record = $defaults;
    if ($edit_id > 0) {
        include('../common/includes/db_open.php');
        $sql = sprintf("SELECT id, name, nasname, host, port, api_username, api_password, radius_server,
                        radius_auth_port, radius_acct_port, pppoe_pool_network, pppoe_pool_cidr,
                        isolir_profile, redirect_url, is_active
                        FROM %s WHERE id=%d", $configValues['CONFIG_DB_TBL_DALOMIKROTIKNAS'], $edit_id);
        $row = $dbSocket->getRow($sql, array(), DB_FETCHMODE_ASSOC);
        if (!DB::isError($row) && !empty($row)) {
            $record = array_merge($record, $row);
        }
        include('../common/includes/db_close.php');
    }

    $title = "MikroTik NAS";
    $help = "";
    $extra_css = array();
    $extra_js = array(
        "static/js/productive_funcs.js",
    );
    print_html_prologue($title, $langCode, $extra_css, $extra_js);
    print_title_and_help($title, $help);
    include_once('include/management/actionMessages.php');

    $nas_names = get_nas_names();

    $input_descriptors0 = array();
    $input_descriptors0[] = array(
        "type" => "hidden",
        "name" => "id",
        "value" => $edit_id,
    );
    $input_descriptors0[] = array(
        "name" => "name",
        "caption" => "Nama MikroTik",
        "type" => "text",
        "value" => $record['name'],
    );
    $input_descriptors0[] = array(
        "name" => "nasname",
        "caption" => "NAS RADIUS (opsional)",
        "type" => "text",
        "value" => $record['nasname'],
        "datalist" => $nas_names,
    );
    $input_descriptors0[] = array(
        "name" => "host",
        "caption" => "IP/Host MikroTik",
        "type" => "text",
        "value" => $record['host'],
    );
    $input_descriptors0[] = array(
        "name" => "port",
        "caption" => "Port API",
        "type" => "number",
        "min" => "1",
        "max" => "65535",
        "value" => $record['port'],
    );
    $input_descriptors0[] = array(
        "name" => "api_username",
        "caption" => "Username API",
        "type" => "text",
        "value" => $record['api_username'],
    );
    $input_descriptors0[] = array(
        "name" => "api_password",
        "caption" => "Password API",
        "type" => "text",
        "value" => $record['api_password'],
        "random" => true,
    );
    $input_descriptors0[] = array(
        "name" => "radius_server",
        "caption" => "RADIUS Server Address",
        "type" => "text",
        "value" => $record['radius_server'],
        "placeholder" => radius_server_address(),
    );
    $input_descriptors0[] = array(
        "name" => "radius_auth_port",
        "caption" => "RADIUS Auth Port",
        "type" => "number",
        "min" => "1",
        "max" => "65535",
        "value" => $record['radius_auth_port'],
    );
    $input_descriptors0[] = array(
        "name" => "radius_acct_port",
        "caption" => "RADIUS Acct Port",
        "type" => "number",
        "min" => "1",
        "max" => "65535",
        "value" => $record['radius_acct_port'],
    );
    $input_descriptors0[] = array(
        "name" => "pppoe_pool_network",
        "caption" => "Blok IP PPPoE",
        "type" => "text",
        "value" => $record['pppoe_pool_network'],
    );
    $input_descriptors0[] = array(
        "name" => "pppoe_pool_cidr",
        "caption" => "CIDR PPPoE",
        "type" => "number",
        "min" => "8",
        "max" => "30",
        "value" => $record['pppoe_pool_cidr'],
    );
    $input_descriptors0[] = array(
        "name" => "isolir_profile",
        "caption" => "Nama Profile Isolir",
        "type" => "text",
        "value" => $record['isolir_profile'],
    );
    $input_descriptors0[] = array(
        "name" => "redirect_url",
        "caption" => "URL Redirect Isolir",
        "type" => "text",
        "value" => $record['redirect_url'],
    );
    $input_descriptors0[] = array(
        "name" => "is_active",
        "caption" => "Aktif",
        "type" => "checkbox",
        "checked" => (intval($record['is_active']) === 1),
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
        "value" => "Simpan",
    );

    $form_title = ($edit_id > 0) ? "Edit MikroTik" : "Tambah MikroTik";

    $form_button = ($edit_id > 0) ? "Edit MikroTik" : "Tambah MikroTik";
    echo '<div class="d-flex justify-content-end mb-3">';
    echo '<button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#mikrotikFormModal">' . htmlspecialchars($form_button, ENT_QUOTES, 'UTF-8') . '</button>';
    echo '</div>';

    echo '<div class="modal fade" id="mikrotikFormModal" tabindex="-1" aria-hidden="true">';
    echo '  <div class="modal-dialog modal-lg modal-dialog-centered">';
    echo '    <div class="modal-content">';
    echo '      <div class="modal-header">';
    echo '        <h5 class="modal-title">' . htmlspecialchars($form_title, ENT_QUOTES, 'UTF-8') . '</h5>';
    echo '        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>';
    echo '      </div>';
    echo '      <div class="modal-body">';

    open_form();
    open_fieldset(array("title" => "Pengaturan MikroTik"));
    foreach ($input_descriptors0 as $input_descriptor) {
        print_form_component($input_descriptor);
    }
    foreach ($input_descriptors1 as $input_descriptor) {
        print_form_component($input_descriptor);
    }
    close_fieldset();
    close_form();

    echo '      </div>';
    echo '    </div>';
    echo '  </div>';
    echo '</div>';

    include('../common/includes/db_open.php');
    $sql = sprintf("SELECT m.id, m.name, m.nasname, m.host, m.port, m.api_username, m.api_password, m.radius_server,
                    m.radius_auth_port, m.radius_acct_port, m.isolir_profile, m.redirect_url, m.is_active,
                    n.secret AS radius_secret
                    FROM %s m LEFT JOIN %s n ON m.nasname = n.nasname
                    ORDER BY m.name ASC",
                    $configValues['CONFIG_DB_TBL_DALOMIKROTIKNAS'],
                    $configValues['CONFIG_DB_TBL_RADNAS']);
    $res = $dbSocket->query($sql);
    $queryError = null;
    if (DB::isError($res)) {
        $queryError = $res->getMessage();
        $sql = sprintf("SELECT id, name, nasname, host, port, api_username, api_password, isolir_profile,
                        redirect_url, is_active
                        FROM %s ORDER BY name ASC",
                        $configValues['CONFIG_DB_TBL_DALOMIKROTIKNAS']);
        $res = $dbSocket->query($sql);
    }
    $logDebugSQL .= "$sql;\n";
    include('../common/includes/db_close.php');

    echo '<div class="mt-4">';
    echo '<h5>Daftar MikroTik</h5>';
    if ($queryError !== null) {
        echo '<div class="alert alert-warning mb-2">Struktur database belum diperbarui. Jalankan SQL update agar kolom RADIUS tampil lengkap.</div>';
    }
    echo '<div class="table-responsive"><table class="table table-sm table-striped table-bordered">';
    echo '<thead><tr><th>Nama</th><th>Host</th><th>Port</th><th>RADIUS Server</th><th>Auth</th><th>Acct</th><th>API User</th><th>Isolir</th><th>Redirect</th><th>Status</th><th>Aksi</th></tr></thead><tbody>';
    if (!DB::isError($res)) {
        while ($row = $res->fetchRow(DB_FETCHMODE_ASSOC)) {
            $status = (intval($row['is_active']) === 1) ? 'Aktif' : 'Nonaktif';
            $radiusServer = !empty($row['radius_server']) ? $row['radius_server'] : radius_server_address();
            $radiusSecret = !empty($row['radius_secret']) ? $row['radius_secret'] : $row['api_password'];
            $authPort = (!empty($row['radius_auth_port'])) ? intval($row['radius_auth_port']) : 1812;
            $acctPort = (!empty($row['radius_acct_port'])) ? intval($row['radius_acct_port']) : 1813;
            $script = build_mikrotik_radius_script($radiusServer, $radiusSecret, $row['api_username'], $row['api_password'], $authPort, $acctPort, 3799);
            $scriptJson = htmlspecialchars(json_encode($script, JSON_UNESCAPED_SLASHES), ENT_QUOTES, 'UTF-8');
            printf('<tr><td>%s</td><td>%s</td><td>%s</td><td>%s</td><td>%s</td><td>%s</td><td>%s</td><td>%s</td><td>%s</td><td>%s</td><td><a class="btn btn-sm btn-primary" href="config-mikrotik.php?id=%d">Edit</a> <button type="button" class="btn btn-sm btn-warning ms-1 js-mikrotik-script" data-script="%s">Script</button> <form method="post" action="config-mikrotik.php" class="d-inline"><input type="hidden" name="csrf_token" value="%s"><input type="hidden" name="action" value="integrate"><input type="hidden" name="id" value="%d"><button type="submit" class="btn btn-sm btn-success ms-1">Integrasikan</button></form></td></tr>',
                   htmlspecialchars($row['name'], ENT_QUOTES, 'UTF-8'),
                   htmlspecialchars($row['host'], ENT_QUOTES, 'UTF-8'),
                   htmlspecialchars($row['port'], ENT_QUOTES, 'UTF-8'),
                   htmlspecialchars($radiusServer, ENT_QUOTES, 'UTF-8'),
                   htmlspecialchars($authPort, ENT_QUOTES, 'UTF-8'),
                   htmlspecialchars($acctPort, ENT_QUOTES, 'UTF-8'),
                   htmlspecialchars($row['api_username'], ENT_QUOTES, 'UTF-8'),
                   htmlspecialchars($row['isolir_profile'], ENT_QUOTES, 'UTF-8'),
                   htmlspecialchars($row['redirect_url'], ENT_QUOTES, 'UTF-8'),
                   $status,
                   intval($row['id']),
                   $scriptJson,
                   htmlspecialchars(dalo_csrf_token(), ENT_QUOTES, 'UTF-8'),
                   intval($row['id']));
        }
    }
    echo '</tbody></table></div></div>';

    echo '<div class="modal fade" id="mikrotikScriptModal" tabindex="-1" aria-hidden="true">';
    echo '  <div class="modal-dialog modal-lg modal-dialog-centered">';
    echo '    <div class="modal-content">';
    echo '      <div class="modal-header">';
    echo '        <h5 class="modal-title">Script Integrasi MikroTik</h5>';
    echo '        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>';
    echo '      </div>';
    echo '      <div class="modal-body">';
    echo '        <p class="text-muted mb-2">Klik tombol salin untuk menyalin script 1 baris ke MikroTik.</p>';
    echo '        <textarea id="mikrotikScriptText" class="form-control" rows="10" readonly></textarea>';
    echo '      </div>';
    echo '      <div class="modal-footer">';
    echo '        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Tutup</button>';
    echo '        <button type="button" class="btn btn-primary" id="mikrotikScriptCopy">Salin</button>';
    echo '      </div>';
    echo '    </div>';
    echo '  </div>';
    echo '</div>';

    echo '<script>';
    echo '(function(){';
    echo 'var autoShowForm = ' . ($auto_show_form ? 'true' : 'false') . ';';
    echo 'var modalEl = document.getElementById("mikrotikScriptModal");';
    echo 'var modal = null;';
    echo 'var textEl = document.getElementById("mikrotikScriptText");';
    echo 'var copyBtn = document.getElementById("mikrotikScriptCopy");';
    echo 'var oneLine = "";';
    echo 'var formModalEl = document.getElementById("mikrotikFormModal");';
    echo 'var formModal = null;';
    echo 'function toOneLine(input){';
    echo 'if (!input) return "";';
    echo 'var out = input.replace(/\\r?\\n/g, " ");';
    echo 'out = out.replace(/\\s*;\\s*/g, "; ");';
    echo 'out = out.replace(/\\s+/g, " ").trim();';
    echo 'if (out.length && out[out.length - 1] !== ";") { out += ";"; }';
    echo 'return out;';
    echo '}';
    echo 'function ensureBootstrap(cb){';
    echo 'if (window.bootstrap && window.bootstrap.Modal) { cb(); return; }';
    echo 'var existing = document.querySelector("script[data-bootstrap-auto]");';
    echo 'if (existing) { existing.addEventListener("load", cb); return; }';
    echo 'var s = document.createElement("script");';
    echo 's.src = "static/js/bootstrap.bundle.min.js";';
    echo 's.defer = true;';
    echo 's.setAttribute("data-bootstrap-auto", "1");';
    echo 's.addEventListener("load", cb);';
    echo 'document.head.appendChild(s);';
    echo '}';
    echo 'document.querySelectorAll(".js-mikrotik-script").forEach(function(btn){';
    echo 'btn.addEventListener("click", function(){';
    echo 'var script = "";';
    echo 'try { script = JSON.parse(this.dataset.script); } catch (e) { script = this.dataset.script || ""; }';
    echo 'if (textEl) { textEl.value = script; }';
    echo 'oneLine = toOneLine(script);';
    echo 'ensureBootstrap(function(){';
    echo 'if (!modal && modalEl && window.bootstrap && window.bootstrap.Modal) {';
    echo 'modal = new bootstrap.Modal(modalEl);';
    echo '}';
    echo 'if (modal) { modal.show(); }';
    echo '});';
    echo '});';
    echo '});';
    echo 'if (autoShowForm && formModalEl) {';
    echo 'ensureBootstrap(function(){';
    echo 'if (!formModal && window.bootstrap && window.bootstrap.Modal) {'; 
    echo 'formModal = new bootstrap.Modal(formModalEl);';
    echo '}';
    echo 'if (formModal) { formModal.show(); }';
    echo '});';
    echo '}';
    echo 'if (copyBtn) {'; 
    echo 'copyBtn.addEventListener("click", function(){';
    echo 'if (!oneLine) return;';
    echo 'if (navigator.clipboard && navigator.clipboard.writeText) {'; 
    echo 'navigator.clipboard.writeText(oneLine).then(function(){}, function(){';
    echo 'if (textEl) { textEl.value = oneLine; textEl.select(); document.execCommand("copy"); }';
    echo '});';
    echo '} else if (textEl) {';
    echo 'textEl.value = oneLine; textEl.select(); document.execCommand("copy");';
    echo '}';
    echo '});';
    echo '}';
    echo '})();';
    echo '</script>';

    print_html_epilogue();
