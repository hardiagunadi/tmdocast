<?php
/*
 *********************************************************************************************************
 * daloRADIUS - L2TP VPN helper
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

    function l2tp_ipv4_base($network) {
        $parts = explode('.', trim($network));
        if (count($parts) !== 4) {
            return '';
        }
        foreach ($parts as $part) {
            if ($part === '' || !ctype_digit($part)) {
                return '';
            }
            $octet = intval($part);
            if ($octet < 0 || $octet > 255) {
                return '';
            }
        }
        return $parts[0] . '.' . $parts[1] . '.' . $parts[2] . '.';
    }

    function mikrotik_escape($value) {
        return str_replace('"', '\"', $value);
    }

    $defaults = array(
        'server_host' => '',
        'ipsec_secret' => '',
        'l2tp_username' => '',
        'l2tp_password' => '',
        'client_name' => 'l2tp-tmdradius',
        'pool_network' => '10.10.10.0',
        'pool_cidr' => 24,
        'local_address' => '10.10.10.1',
        'radius_server' => '10.10.10.1',
        'radius_secret' => '',
        'radius_auth_port' => 1812,
        'radius_acct_port' => 1813,
        'radius_route' => '10.10.10.1/32',
    );

    $values = $defaults;
    $server_script = "";
    $client_script = "";
    $installer_script = "";
    $installer_path = "";
    $packages_script = "";
    $packages_path = "";
    $apply_log_path = '';
    $apply_pid = '';

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (array_key_exists('csrf_token', $_POST) && isset($_POST['csrf_token']) && dalo_check_csrf_token($_POST['csrf_token'])) {
            $values['server_host'] = (array_key_exists('server_host', $_POST)) ? trim($_POST['server_host']) : "";
            $values['ipsec_secret'] = (array_key_exists('ipsec_secret', $_POST)) ? trim($_POST['ipsec_secret']) : "";
            $values['l2tp_username'] = (array_key_exists('l2tp_username', $_POST)) ? trim($_POST['l2tp_username']) : "";
            $values['l2tp_password'] = (array_key_exists('l2tp_password', $_POST)) ? trim($_POST['l2tp_password']) : "";
            $values['client_name'] = (array_key_exists('client_name', $_POST) && trim($_POST['client_name']) !== "")
                                   ? trim($_POST['client_name']) : $defaults['client_name'];
            $values['pool_network'] = (array_key_exists('pool_network', $_POST)) ? trim($_POST['pool_network']) : $defaults['pool_network'];
            $values['pool_cidr'] = (array_key_exists('pool_cidr', $_POST) && intval($_POST['pool_cidr']) > 0)
                                 ? intval($_POST['pool_cidr']) : $defaults['pool_cidr'];
            $values['local_address'] = (array_key_exists('local_address', $_POST)) ? trim($_POST['local_address']) : "";
            $values['radius_server'] = (array_key_exists('radius_server', $_POST)) ? trim($_POST['radius_server']) : "";
            $values['radius_secret'] = (array_key_exists('radius_secret', $_POST)) ? trim($_POST['radius_secret']) : "";
            $values['radius_auth_port'] = (array_key_exists('radius_auth_port', $_POST) && intval($_POST['radius_auth_port']) > 0)
                                        ? intval($_POST['radius_auth_port']) : $defaults['radius_auth_port'];
            $values['radius_acct_port'] = (array_key_exists('radius_acct_port', $_POST) && intval($_POST['radius_acct_port']) > 0)
                                        ? intval($_POST['radius_acct_port']) : $defaults['radius_acct_port'];
            $values['radius_route'] = (array_key_exists('radius_route', $_POST)) ? trim($_POST['radius_route']) : "";

            if (empty($values['server_host']) || empty($values['ipsec_secret']) || empty($values['l2tp_username']) || empty($values['l2tp_password'])
                || empty($values['pool_network']) || empty($values['radius_server']) || empty($values['radius_secret'])) {
                $failureMsg = "Server, IPsec, user, password, network pool, dan RADIUS harus diisi.";
                $logAction .= "Failed generating L2TP scripts (missing data) on page: ";
            } elseif (intval($values['pool_cidr']) !== 24) {
                $failureMsg = "CIDR pool harus /24 sesuai blok private tipe A.";
                $logAction .= "Failed generating L2TP scripts (invalid CIDR) on page: ";
            } else {
                $pool_base = l2tp_ipv4_base($values['pool_network']);
                if ($pool_base === '') {
                    $failureMsg = "Network pool tidak valid.";
                    $logAction .= "Failed generating L2TP scripts (invalid pool) on page: ";
                } elseif (strpos($pool_base, '10.') !== 0) {
                    $failureMsg = "Network pool harus menggunakan blok private A (10.0.0.0/8).";
                    $logAction .= "Failed generating L2TP scripts (non private A pool) on page: ";
                } else {
                    if ($values['local_address'] === '') {
                        $values['local_address'] = $pool_base . '1';
                    }
                    if ($values['radius_route'] === '') {
                        $values['radius_route'] = $values['radius_server'] . '/32';
                    }

                    $pool_range = $pool_base . '2-' . $pool_base . '254';
                    $server_lines = array(
                        "# Linux L2TP/IPsec server (xl2tpd + strongSwan)",
                        "# Install: apt install -y strongswan xl2tpd ppp freeradius-utils",
                        "",
                        "# /etc/ipsec.conf",
                        "config setup",
                        "    uniqueids=no",
                        "",
                        "conn l2tp-psk",
                        "    keyexchange=ikev1",
                        "    authby=psk",
                        "    type=transport",
                        "    left=" . $values['server_host'],
                        "    leftprotoport=17/1701",
                        "    right=%any",
                        "    rightprotoport=17/%any",
                        "    auto=add",
                        "",
                        "# /etc/ipsec.secrets",
                        $values['server_host'] . " %any : PSK \"" . $values['ipsec_secret'] . "\"",
                        "",
                        "# /etc/xl2tpd/xl2tpd.conf",
                        "[global]",
                        "port = 1701",
                        "",
                        "[lns default]",
                        "ip range = " . $pool_range,
                        "local ip = " . $values['local_address'],
                        "require chap = yes",
                        "refuse pap = yes",
                        "require authentication = yes",
                        "name = l2tpd",
                        "pppoptfile = /etc/ppp/options.xl2tpd",
                        "length bit = yes",
                        "",
                        "# /etc/ppp/options.xl2tpd",
                        "require-mschap-v2",
                        "refuse-pap",
                        "refuse-chap",
                        "refuse-mschap",
                        "ms-dns 8.8.8.8",
                        "ms-dns 1.1.1.1",
                        "mtu 1410",
                        "mru 1410",
                        "lock",
                        "auth",
                        "proxyarp",
                        "lcp-echo-interval 30",
                        "lcp-echo-failure 4",
                        "plugin radius.so",
                        "radius-config-file /etc/ppp/radius/radius.conf",
                        "",
                        "# /etc/ppp/radius/servers",
                        $values['radius_server'] . " " . $values['radius_secret'],
                        "",
                        "# /etc/ppp/radius/radius.conf",
                        "authserver " . $values['radius_server'],
                        "acctserver " . $values['radius_server'],
                        "radius_timeout 10",
                        "radius_retries 3",
                        "",
                        "# Enable forwarding",
                        "sysctl -w net.ipv4.ip_forward=1",
                        "",
                        "# Open firewall (adjust to your firewall tool)",
                        "ufw allow 500/udp",
                        "ufw allow 4500/udp",
                        "ufw allow 1701/udp",
                        "ufw allow " . $values['radius_auth_port'] . "/udp",
                        "ufw allow " . $values['radius_acct_port'] . "/udp",
                        "",
                        "# Restart services",
                        "systemctl restart strongswan-starter xl2tpd",
                    );

                    $client_lines = array(
                        "/interface l2tp-client add name=\"" . mikrotik_escape($values['client_name'])
                            . "\" connect-to=" . $values['server_host']
                            . " user=\"" . mikrotik_escape($values['l2tp_username']) . "\" password=\""
                            . mikrotik_escape($values['l2tp_password']) . "\" use-ipsec=yes ipsec-secret=\""
                            . mikrotik_escape($values['ipsec_secret']) . "\" add-default-route=no disabled=no",
                    );
                    if ($values['radius_route'] !== '') {
                        $client_lines[] = "/ip route add dst-address=" . $values['radius_route']
                            . " gateway=\"" . mikrotik_escape($values['client_name']) . "\"";
                    }
                    $client_lines[] = "/radius add service=ppp,hotspot,login address=" . $values['radius_server']
                        . " secret=\"" . mikrotik_escape($values['radius_secret']) . "\" authentication-port="
                        . $values['radius_auth_port'] . " accounting-port=" . $values['radius_acct_port'] . " timeout=2s";

                    $server_script = implode("\n", $server_lines);
                    $client_script = implode("\n", $client_lines);
                    $successMsg = "Script VPN L2TP berhasil dibuat.";
                    $logAction .= "Generated L2TP scripts on page: ";

                    $installer_lines = array(
                        "#!/usr/bin/env bash",
                        "set -euo pipefail",
                        "",
                        "install -d -m 0755 /etc/ppp/radius",
                        "",
                        "cat > /etc/ipsec.conf <<'EOF'",
                        "config setup",
                        "    uniqueids=no",
                        "",
                        "conn l2tp-psk",
                        "    keyexchange=ikev1",
                        "    authby=psk",
                        "    type=transport",
                        "    left=" . $values['server_host'],
                        "    leftprotoport=17/1701",
                        "    right=%any",
                        "    rightprotoport=17/%any",
                        "    auto=add",
                        "EOF",
                        "",
                        "cat > /etc/ipsec.secrets <<'EOF'",
                        $values['server_host'] . " %any : PSK \"" . $values['ipsec_secret'] . "\"",
                        "EOF",
                        "",
                        "cat > /etc/xl2tpd/xl2tpd.conf <<'EOF'",
                        "[global]",
                        "port = 1701",
                        "",
                        "[lns default]",
                        "ip range = " . $pool_range,
                        "local ip = " . $values['local_address'],
                        "require chap = yes",
                        "refuse pap = yes",
                        "require authentication = yes",
                        "name = l2tpd",
                        "pppoptfile = /etc/ppp/options.xl2tpd",
                        "length bit = yes",
                        "EOF",
                        "",
                        "cat > /etc/ppp/options.xl2tpd <<'EOF'",
                        "require-mschap-v2",
                        "refuse-pap",
                        "refuse-chap",
                        "refuse-mschap",
                        "ms-dns 8.8.8.8",
                        "ms-dns 1.1.1.1",
                        "mtu 1410",
                        "mru 1410",
                        "lock",
                        "auth",
                        "proxyarp",
                        "lcp-echo-interval 30",
                        "lcp-echo-failure 4",
                        "plugin radius.so",
                        "radius-config-file /etc/ppp/radius/radius.conf",
                        "EOF",
                        "",
                        "cat > /etc/ppp/radius/servers <<'EOF'",
                        $values['radius_server'] . " " . $values['radius_secret'],
                        "EOF",
                        "",
                        "cat > /etc/ppp/radius/radius.conf <<'EOF'",
                        "authserver " . $values['radius_server'],
                        "acctserver " . $values['radius_server'],
                        "radius_timeout 10",
                        "radius_retries 3",
                        "EOF",
                        "",
                        "cat > /etc/sysctl.d/99-l2tp.conf <<'EOF'",
                        "net.ipv4.ip_forward=1",
                        "EOF",
                        "sysctl --system",
                        "",
                        "systemctl restart strongswan-starter || systemctl restart strongswan",
                        "systemctl restart xl2tpd",
                    );

                    $installer_script = implode("\n", $installer_lines) . "\n";

                    $setup_dir = dirname(__DIR__, 2) . '/setup';
                    if (!is_dir($setup_dir)) {
                        mkdir($setup_dir, 0750, true);
                    }
                    $installer_path = $setup_dir . '/l2tp-configure.sh';
                    file_put_contents($installer_path, $installer_script);

                    $packages_lines = array(
                        "#!/usr/bin/env bash",
                        "set -euo pipefail",
                        "",
                        "apt-get update",
                        "apt-get install -y strongswan xl2tpd ppp freeradius-utils",
                    );
                    $packages_script = implode("\n", $packages_lines) . "\n";
                    $packages_path = $setup_dir . '/l2tp-packages.sh';
                    file_put_contents($packages_path, $packages_script);

                    if (function_exists('exec')) {
                        $log_dir = dirname(__DIR__, 2) . '/var/log';
                        if (!is_dir($log_dir)) {
                            mkdir($log_dir, 0750, true);
                        }
                        $apply_log_path = $log_dir . '/l2tp-config.log';
                        $command = "nohup bash " . escapeshellarg($installer_path)
                                 . " > " . escapeshellarg($apply_log_path) . " 2>&1 & echo $!";
                        $output = array();
                        $exit_code = null;
                        exec($command, $output, $exit_code);
                        if ($exit_code === 0 && !empty($output[0])) {
                            $apply_pid = trim($output[0]);
                            $successMsg .= " Konfigurasi server dijalankan di background (PID: {$apply_pid}).";
                        } else {
                            $failureMsg = "Gagal menjalankan konfigurasi di background. Jalankan manual sebagai root.";
                            $logAction .= "Failed applying L2TP config on page: ";
                        }
                    }
                }
            }
        } else {
            $failureMsg = "CSRF token error";
            $logAction .= "$failureMsg on page: ";
        }
    }

    $title = "VPN L2TP Linux + MikroTik Client";
    $help = "Generate installer paket L2TP/IPsec di Linux (server ini), terapkan konfigurasi server, dan script client MikroTik.";
    print_html_prologue($title, $langCode);
    print_title_and_help($title, $help);
    include_once('include/management/actionMessages.php');

    $input_descriptors0 = array();
    $input_descriptors0[] = array(
        "name" => "server_host",
        "caption" => "IP/Host L2TP Server (Linux)",
        "type" => "text",
        "value" => $values['server_host'],
    );
    $input_descriptors0[] = array(
        "name" => "ipsec_secret",
        "caption" => "IPsec PSK",
        "type" => "text",
        "value" => $values['ipsec_secret'],
    );
    $input_descriptors0[] = array(
        "name" => "l2tp_username",
        "caption" => "Username L2TP",
        "type" => "text",
        "value" => $values['l2tp_username'],
    );
    $input_descriptors0[] = array(
        "name" => "l2tp_password",
        "caption" => "Password L2TP",
        "type" => "text",
        "value" => $values['l2tp_password'],
    );
    $input_descriptors0[] = array(
        "name" => "client_name",
        "caption" => "Nama Interface L2TP Client",
        "type" => "text",
        "value" => $values['client_name'],
    );
    $input_descriptors0[] = array(
        "name" => "pool_network",
        "caption" => "Network Pool (private A)",
        "type" => "text",
        "value" => $values['pool_network'],
    );
    $input_descriptors0[] = array(
        "name" => "pool_cidr",
        "caption" => "CIDR Pool",
        "type" => "number",
        "min" => "24",
        "max" => "24",
        "value" => $values['pool_cidr'],
    );
    $input_descriptors0[] = array(
        "name" => "local_address",
        "caption" => "IP Local L2TP Server",
        "type" => "text",
        "value" => $values['local_address'],
    );
    $input_descriptors0[] = array(
        "name" => "radius_server",
        "caption" => "IP RADIUS Server",
        "type" => "text",
        "value" => $values['radius_server'],
    );
    $input_descriptors0[] = array(
        "name" => "radius_secret",
        "caption" => "RADIUS Secret",
        "type" => "text",
        "value" => $values['radius_secret'],
    );
    $input_descriptors0[] = array(
        "name" => "radius_auth_port",
        "caption" => "RADIUS Auth Port",
        "type" => "number",
        "min" => "1",
        "value" => $values['radius_auth_port'],
    );
    $input_descriptors0[] = array(
        "name" => "radius_acct_port",
        "caption" => "RADIUS Acct Port",
        "type" => "number",
        "min" => "1",
        "value" => $values['radius_acct_port'],
    );
    $input_descriptors0[] = array(
        "name" => "radius_route",
        "caption" => "Route ke RADIUS via L2TP (CIDR, opsional)",
        "type" => "text",
        "value" => $values['radius_route'],
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
        "value" => "Generate Script",
    );

    open_form();
    open_fieldset(array("title" => "Generate Script L2TP"));
    foreach ($input_descriptors0 as $input_descriptor) {
        print_form_component($input_descriptor);
    }
    foreach ($input_descriptors1 as $input_descriptor) {
        print_form_component($input_descriptor);
    }
    close_fieldset();
    close_form();

    if (!empty($installer_script)) {
        echo '<div class="mt-4">';
        echo '<h5>Installer Paket L2TP (Linux)</h5>';
        echo '<textarea id="l2tpPackagesScript" class="form-control" rows="6" readonly>'
            . htmlspecialchars($packages_script, ENT_QUOTES, 'UTF-8') . '</textarea>';
        echo '<button type="button" class="btn btn-outline-primary mt-2 js-copy" data-target="l2tpPackagesScript">Salin Installer Paket</button>';
        if (!empty($packages_path)) {
            echo '<div class="alert alert-info mt-2 mb-0">';
            echo 'Jalankan installer paket sekali saja: <code>bash ' . htmlspecialchars($packages_path, ENT_QUOTES, 'UTF-8') . '</code>';
            echo '</div>';
        }
        echo '</div>';

        echo '<div class="mt-4">';
        echo '<h5>Installer Konfigurasi L2TP (Linux)</h5>';
        echo '<textarea id="l2tpInstallerScript" class="form-control" rows="18" readonly>'
            . htmlspecialchars($installer_script, ENT_QUOTES, 'UTF-8') . '</textarea>';
        echo '<button type="button" class="btn btn-outline-primary mt-2 js-copy" data-target="l2tpInstallerScript">Salin Installer Konfigurasi</button>';
        if (!empty($installer_path)) {
            echo '<div class="alert alert-info mt-2 mb-0">';
            echo 'Jalankan manual jika auto-apply gagal: <code>bash ' . htmlspecialchars($installer_path, ENT_QUOTES, 'UTF-8') . '</code>';
            echo '</div>';
        }
        echo '</div>';
    }

    if (!empty($apply_log_path)) {
        echo '<div class="mt-4">';
        echo '<h5>Log Apply Konfigurasi</h5>';
        echo '<div class="alert alert-info mb-0">';
        echo 'Log tersimpan di: <code>' . htmlspecialchars($apply_log_path, ENT_QUOTES, 'UTF-8') . '</code>';
        if (!empty($apply_pid)) {
            echo '<br>PID: <code>' . htmlspecialchars($apply_pid, ENT_QUOTES, 'UTF-8') . '</code>';
        }
        echo '</div>';
        echo '</div>';
    }

    echo '<div class="mt-4">';
    echo '<h5>Script L2TP Client (MikroTik)</h5>';
    echo '<textarea id="l2tpClientScript" class="form-control" rows="8" readonly>'
        . htmlspecialchars($client_script, ENT_QUOTES, 'UTF-8') . '</textarea>';
    echo '<button type="button" class="btn btn-outline-primary mt-2 js-copy" data-target="l2tpClientScript">Salin Script Client</button>';
    echo '</div>';

    $inline_extra_js = <<<JS
document.querySelectorAll('.js-copy').forEach(function(btn){
    btn.addEventListener('click', function(){
        var target = document.getElementById(this.dataset.target);
        if (!target || !target.value) { return; }
        target.focus();
        target.select();
        if (navigator.clipboard && navigator.clipboard.writeText) {
            navigator.clipboard.writeText(target.value);
        } else {
            document.execCommand('copy');
        }
    });
});
JS;

    print_footer_and_html_epilogue($inline_extra_js);
