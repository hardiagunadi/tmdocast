<?php
/*
 *********************************************************************************************************
 * daloRADIUS - WireGuard VPN helper
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

    function mikrotik_escape($value) {
        return str_replace('"', '\"', $value);
    }

    function generate_radius_secret($length, $allowed_chars) {
        $chars = trim($allowed_chars);
        if ($chars === '') {
            $chars = 'abcdefghijkmnpqrstuvwxyzABCDEFGHJKMNPQRSTUVWXYZ23456789';
        }
        $chars_len = strlen($chars);
        if ($chars_len === 0) {
            return '';
        }
        $bytes = random_bytes($length);
        $secret = '';
        for ($i = 0; $i < $length; $i++) {
            $secret .= $chars[ord($bytes[$i]) % $chars_len];
        }
        return $secret;
    }

    $defaults = array(
        'server_host' => '',
        'client_name' => 'wg-vps',
        'wg_listen_port' => 51820,
        'wg_server_address' => '10.200.200.1/24',
        'wg_client_address' => '10.200.200.2/32',
        'allowed_subnet' => '10.10.10.0/24',
        'client_public_key' => '',
        'radius_server' => '10.200.200.1',
        'radius_secret' => '',
        'radius_auth_port' => 1812,
        'radius_acct_port' => 1813,
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
    $current_run_id = '';
    $log_dir = dirname(__DIR__, 2) . '/var/log';
    $status_path = $log_dir . '/wireguard-config.status.json';
    $client_key_path = $log_dir . '/wireguard-client.key';
    $server_key_path = $log_dir . '/wireguard-server.pub';
    if ($_SERVER['REQUEST_METHOD'] !== 'POST' && $values['radius_secret'] === '') {
        $allowed_chars = $configValues['CONFIG_USER_ALLOWEDRANDOMCHARS'] ?? '';
        $values['radius_secret'] = generate_radius_secret(16, $allowed_chars);
    }
    if ($_SERVER['REQUEST_METHOD'] !== 'POST' && $values['client_public_key'] === '' && is_readable($client_key_path)) {
        $saved_key = trim(file_get_contents($client_key_path));
        if ($saved_key !== '') {
            $values['client_public_key'] = $saved_key;
        }
    }

    if (array_key_exists('wg_status', $_GET)) {
        header('Content-Type: application/json; charset=UTF-8');
        $status_payload = array('status' => 'unknown');
        if (is_readable($status_path)) {
            $contents = file_get_contents($status_path);
            if ($contents !== false && trim($contents) !== '') {
                echo $contents;
                exit;
            }
        }
        echo json_encode($status_payload);
        exit;
    }

    if ($current_run_id === '' && is_readable($status_path)) {
        $status_contents = file_get_contents($status_path);
        $status_data = json_decode($status_contents, true);
        if (is_array($status_data)
            && array_key_exists('status', $status_data)
            && in_array($status_data['status'], array('running', 'queued'), true)
            && array_key_exists('run_id', $status_data)
            && is_string($status_data['run_id'])
        ) {
            $current_run_id = $status_data['run_id'];
        }
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (array_key_exists('csrf_token', $_POST) && isset($_POST['csrf_token']) && dalo_check_csrf_token($_POST['csrf_token'])) {
            $values['server_host'] = (array_key_exists('server_host', $_POST)) ? trim($_POST['server_host']) : "";
            $values['client_name'] = (array_key_exists('client_name', $_POST) && trim($_POST['client_name']) !== "")
                                   ? trim($_POST['client_name']) : $defaults['client_name'];
            $values['wg_listen_port'] = (array_key_exists('wg_listen_port', $_POST) && intval($_POST['wg_listen_port']) > 0)
                                      ? intval($_POST['wg_listen_port']) : $defaults['wg_listen_port'];
            $values['wg_server_address'] = (array_key_exists('wg_server_address', $_POST))
                                         ? trim($_POST['wg_server_address']) : $defaults['wg_server_address'];
            $values['wg_client_address'] = (array_key_exists('wg_client_address', $_POST))
                                         ? trim($_POST['wg_client_address']) : $defaults['wg_client_address'];
            $values['allowed_subnet'] = (array_key_exists('allowed_subnet', $_POST))
                                      ? trim($_POST['allowed_subnet']) : $defaults['allowed_subnet'];
            $values['client_public_key'] = (array_key_exists('client_public_key', $_POST))
                                         ? trim($_POST['client_public_key']) : "";
            $values['radius_server'] = (array_key_exists('radius_server', $_POST)) ? trim($_POST['radius_server']) : "";
            $values['radius_secret'] = (array_key_exists('radius_secret', $_POST)) ? trim($_POST['radius_secret']) : "";
            $values['radius_auth_port'] = (array_key_exists('radius_auth_port', $_POST) && intval($_POST['radius_auth_port']) > 0)
                                        ? intval($_POST['radius_auth_port']) : $defaults['radius_auth_port'];
            $values['radius_acct_port'] = (array_key_exists('radius_acct_port', $_POST) && intval($_POST['radius_acct_port']) > 0)
                                        ? intval($_POST['radius_acct_port']) : $defaults['radius_acct_port'];

            $server_wg_ip = '';
            if (strpos($values['wg_server_address'], '/') !== false) {
                $server_wg_ip = trim(explode('/', $values['wg_server_address'])[0]);
            }
            if ($values['radius_server'] === '' && $server_wg_ip !== '') {
                $values['radius_server'] = $server_wg_ip;
            }
            if ($values['client_public_key'] === '' && is_readable($client_key_path)) {
                $saved_key = trim(file_get_contents($client_key_path));
                if ($saved_key !== '') {
                    $values['client_public_key'] = $saved_key;
                }
            }
            if ($values['radius_secret'] === '') {
                $allowed_chars = $configValues['CONFIG_USER_ALLOWEDRANDOMCHARS'] ?? '';
                $values['radius_secret'] = generate_radius_secret(16, $allowed_chars);
            }

            if (empty($values['server_host']) || empty($values['wg_server_address']) || empty($values['wg_client_address'])
                || empty($values['allowed_subnet']) || empty($values['client_public_key'])
                || empty($values['radius_server']) || empty($values['radius_secret'])) {
                $failureMsg = "Server, WireGuard, client key, subnet, dan RADIUS harus diisi.";
                $logAction .= "Failed generating WireGuard scripts (missing data) on page: ";
            } else {
                if (!is_dir($log_dir)) {
                    mkdir($log_dir, 0750, true);
                }
                file_put_contents($client_key_path, $values['client_public_key'] . "\n");
                $current_run_id = uniqid('wg_', true);
                $server_lines = array(
                    "# WireGuard server (VPS)",
                    "apt-get update",
                    "apt-get install -y wireguard",
                    "",
                    "umask 077",
                    "mkdir -p /etc/wireguard",
                    "wg genkey | tee /etc/wireguard/wg0.key | wg pubkey > /etc/wireguard/wg0.pub",
                    "chmod 600 /etc/wireguard/wg0.key",
                    "chmod 644 /etc/wireguard/wg0.pub",
                    "",
                    "cat > /etc/wireguard/wg0.conf <<EOF",
                    "[Interface]",
                    "Address = " . $values['wg_server_address'],
                    "ListenPort = " . $values['wg_listen_port'],
                    "PrivateKey = $(cat /etc/wireguard/wg0.key)",
                    "PostUp = sysctl -w net.ipv4.ip_forward=1",
                    "PostUp = iptables -A FORWARD -i %i -j ACCEPT; iptables -A FORWARD -o %i -j ACCEPT",
                    "PostDown = iptables -D FORWARD -i %i -j ACCEPT; iptables -D FORWARD -o %i -j ACCEPT",
                    "",
                    "[Peer]",
                    "PublicKey = " . $values['client_public_key'],
                    "AllowedIPs = " . $values['wg_client_address'] . ", " . $values['allowed_subnet'],
                    "EOF",
                    "",
                    "cat > /etc/sysctl.d/99-wireguard.conf <<'EOF'",
                    "net.ipv4.ip_forward=1",
                    "EOF",
                    "sysctl --system",
                    "",
                    "systemctl enable wg-quick@wg0",
                    "systemctl restart wg-quick@wg0",
                    "",
                    "# Open firewall (adjust to your firewall tool)",
                    "ufw allow " . $values['wg_listen_port'] . "/udp",
                    "",
                    "echo 'Server public key:'",
                    "cat /etc/wireguard/wg0.pub",
                );

                $server_public_key = '';
                if (is_readable($server_key_path)) {
                    $server_public_key = trim(file_get_contents($server_key_path));
                } elseif (is_readable('/etc/wireguard/wg0.pub')) {
                    $server_public_key = trim(file_get_contents('/etc/wireguard/wg0.pub'));
                }
                $server_public_key = ($server_public_key !== '') ? $server_public_key : 'PASTE_SERVER_PUBLIC_KEY';
                $server_peer_ip = ($server_wg_ip !== '') ? ($server_wg_ip . '/32') : $values['wg_server_address'];
                $client_iface = mikrotik_escape($values['client_name']);
                $client_lines = array(
                    ":if ([:len [/interface wireguard find name=\"" . $client_iface . "\"]] = 0) do={",
                    "  :put \"Interface " . $client_iface . " belum ada, buat dulu di MikroTik.\"",
                    "}",
                    ":if ([:len [/ip/address find interface=\"" . $client_iface . "\" address=\"" . $values['wg_client_address'] . "\"]] = 0) do={",
                    "  /ip/address add address=" . $values['wg_client_address'] . " interface=\"" . $client_iface . "\"",
                    "}",
                    "/interface wireguard peers remove [find interface=\"" . $client_iface . "\"]",
                    "/interface wireguard peers add interface=\"" . $client_iface . "\" public-key=\""
                        . mikrotik_escape($server_public_key) . "\" endpoint-address=" . $values['server_host']
                        . " endpoint-port=" . $values['wg_listen_port'] . " allowed-address=" . $server_peer_ip
                        . "," . $values['allowed_subnet'] . " persistent-keepalive=25s",
                    ":if ([:len [/ip/route find dst-address=\"" . $values['allowed_subnet'] . "\"]] = 0) do={",
                    "  /ip/route add dst-address=" . $values['allowed_subnet'] . " gateway=\"" . $client_iface . "\"",
                    "}",
                    ":if ([:len [/radius find address=\"" . $values['radius_server'] . "\" && secret=\"" . mikrotik_escape($values['radius_secret']) . "\"]] = 0) do={",
                    "  /radius add service=ppp,hotspot,login address=" . $values['radius_server']
                        . " secret=\"" . mikrotik_escape($values['radius_secret']) . "\" authentication-port="
                        . $values['radius_auth_port'] . " accounting-port=" . $values['radius_acct_port'] . " timeout=2s",
                    "}",
                );
                if ($server_public_key === 'PASTE_SERVER_PUBLIC_KEY') {
                    array_unshift($client_lines, "# Replace PASTE_SERVER_PUBLIC_KEY with VPS public key after apply.");
                }

                $server_script = implode("\n", $server_lines);
                $client_script = implode("\n", $client_lines);
                $successMsg = "Script VPN WireGuard berhasil dibuat.";
                $logAction .= "Generated WireGuard scripts on page: ";

                $installer_lines = array(
                    "#!/usr/bin/env bash",
                    "set -euo pipefail",
                    "",
                    "STATUS_FILE=\"" . $status_path . "\"",
                    "STATUS_RUN_ID=\"" . $current_run_id . "\"",
                    "write_status() { printf '{\"status\":\"%s\",\"time\":%s,\"run_id\":\"%s\"}\\n' \"$1\" \"$(date +%s)\" \"$STATUS_RUN_ID\" > \"$STATUS_FILE\"; }",
                    "mkdir -p \"$(dirname \"$STATUS_FILE\")\"",
                    "write_status \"running\"",
                    "trap 'write_status \"failed\"' ERR",
                    "",
                    "if [ \"$(id -u)\" -ne 0 ]; then",
                    "  echo \"This script must be run as root.\"",
                    "  exit 1",
                    "fi",
                    "",
                    "apt-get update",
                    "apt-get install -y wireguard",
                    "",
                    "umask 077",
                    "mkdir -p /etc/wireguard",
                    "if [ ! -f /etc/wireguard/wg0.key ]; then",
                    "  wg genkey | tee /etc/wireguard/wg0.key | wg pubkey > /etc/wireguard/wg0.pub",
                    "else",
                    "  wg pubkey < /etc/wireguard/wg0.key > /etc/wireguard/wg0.pub",
                    "fi",
                    "mkdir -p " . $log_dir,
                    "cp /etc/wireguard/wg0.pub " . $server_key_path,
                    "chmod 600 /etc/wireguard/wg0.key",
                    "chmod 644 /etc/wireguard/wg0.pub",
                    "",
                    "cat > /etc/wireguard/wg0.conf <<EOF",
                    "[Interface]",
                    "Address = " . $values['wg_server_address'],
                    "ListenPort = " . $values['wg_listen_port'],
                    "PrivateKey = $(cat /etc/wireguard/wg0.key)",
                    "PostUp = sysctl -w net.ipv4.ip_forward=1",
                    "PostUp = iptables -A FORWARD -i %i -j ACCEPT; iptables -A FORWARD -o %i -j ACCEPT",
                    "PostDown = iptables -D FORWARD -i %i -j ACCEPT; iptables -D FORWARD -o %i -j ACCEPT",
                    "",
                    "[Peer]",
                    "PublicKey = " . $values['client_public_key'],
                    "AllowedIPs = " . $values['wg_client_address'] . ", " . $values['allowed_subnet'],
                    "EOF",
                    "",
                    "cat > /etc/sysctl.d/99-wireguard.conf <<'EOF'",
                    "net.ipv4.ip_forward=1",
                    "EOF",
                    "sysctl --system",
                    "",
                    "systemctl enable wg-quick@wg0",
                    "systemctl restart wg-quick@wg0",
                    "",
                    "write_status \"success\"",
                );

                $installer_script = implode("\n", $installer_lines) . "\n";

                $setup_dir = dirname(__DIR__, 2) . '/setup';
                if (!is_dir($setup_dir)) {
                    mkdir($setup_dir, 0750, true);
                }
                $installer_path = $setup_dir . '/wireguard-configure.sh';
                file_put_contents($installer_path, $installer_script);

                if (function_exists('exec')) {
                    if (!is_dir($log_dir)) {
                        mkdir($log_dir, 0750, true);
                    }
                    $apply_log_path = $log_dir . '/wireguard-config.log';
                    $queued_payload = json_encode(array(
                        'status' => 'queued',
                        'time' => time(),
                        'run_id' => $current_run_id,
                    ));
                    file_put_contents($status_path, $queued_payload . "\n");
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
                        $logAction .= "Failed applying WireGuard config on page: ";
                    }
                }
            }
        } else {
            $failureMsg = "CSRF token error";
            $logAction .= "$failureMsg on page: ";
        }
    }

    $title = "VPN WireGuard + RADIUS";
    $help = "Generate konfigurasi WireGuard di VPS, terapkan server, dan script client MikroTik (termasuk RADIUS).";
    print_html_prologue($title, $langCode);
    print_title_and_help($title, $help);
    include_once('include/management/actionMessages.php');

    $input_descriptors0 = array();
    $input_descriptors0[] = array(
        "name" => "server_host",
        "caption" => "IP/Host VPS (Public)",
        "type" => "text",
        "value" => $values['server_host'],
    );
    $input_descriptors0[] = array(
        "name" => "client_name",
        "caption" => "Nama Interface WireGuard Client",
        "type" => "text",
        "value" => $values['client_name'],
    );
    $input_descriptors0[] = array(
        "name" => "wg_listen_port",
        "caption" => "WireGuard Port (UDP)",
        "type" => "number",
        "min" => "1",
        "value" => $values['wg_listen_port'],
    );
    $input_descriptors0[] = array(
        "name" => "wg_server_address",
        "caption" => "IP WireGuard Server (CIDR)",
        "type" => "text",
        "value" => $values['wg_server_address'],
    );
    $input_descriptors0[] = array(
        "name" => "wg_client_address",
        "caption" => "IP WireGuard Client (CIDR)",
        "type" => "text",
        "value" => $values['wg_client_address'],
    );
    $input_descriptors0[] = array(
        "name" => "allowed_subnet",
        "caption" => "Subnet Lokal MikroTik (routed via WG)",
        "type" => "text",
        "value" => $values['allowed_subnet'],
    );
    $input_descriptors0[] = array(
        "name" => "client_public_key",
        "caption" => "WireGuard Public Key (MikroTik)",
        "type" => "text",
        "value" => $values['client_public_key'],
    );
    $input_descriptors0[] = array(
        "name" => "radius_server",
        "caption" => "IP RADIUS Server (via WG)",
        "type" => "text",
        "value" => $values['radius_server'],
    );
    $input_descriptors0[] = array(
        "name" => "radius_secret",
        "caption" => "RADIUS Secret",
        "type" => "text",
        "random" => true,
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
    open_fieldset(array("title" => "Generate Script WireGuard"));
    foreach ($input_descriptors0 as $input_descriptor) {
        print_form_component($input_descriptor);
    }
    foreach ($input_descriptors1 as $input_descriptor) {
        print_form_component($input_descriptor);
    }
    close_fieldset();
    close_form();

    if (!empty($apply_log_path) && !empty($apply_pid)) {
        echo '<div class="mt-4">';
        echo '<div class="alert alert-info mb-0">';
        echo 'Konfigurasi VPS dijalankan di background. PID: <code>' . htmlspecialchars($apply_pid, ENT_QUOTES, 'UTF-8') . '</code>';
        echo '</div>';
        echo '</div>';
    }

    echo '<div class="mt-4">';
    echo '<h5>Script WireGuard Client (MikroTik)</h5>';
    echo '<textarea id="wgClientScript" class="form-control" rows="10" readonly>'
        . htmlspecialchars($client_script, ENT_QUOTES, 'UTF-8') . '</textarea>';
    echo '<button type="button" class="btn btn-outline-primary mt-2 js-copy" data-target="wgClientScript">Salin Script Client</button>';
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

(function(){
    var runId = "{$current_run_id}";
    if (!runId) { return; }
    var statusUrl = "config-l2tp.php?wg_status=1";
    var shownKey = "wgApplyNotice_" + runId;
    var pollDelayMs = 3000;

    function showPopup(status) {
        var message = status === "success"
            ? "WireGuard konfigurasi selesai diterapkan."
            : "WireGuard konfigurasi gagal. Cek log apply untuk detail.";
        alert(message);
    }

    function scheduleNext(delay) {
        window.setTimeout(checkStatus, delay);
    }

    function checkStatus() {
        fetch(statusUrl, { cache: "no-store" })
            .then(function(response){ return response.json(); })
            .then(function(data){
                if (!data || !data.status) {
                    scheduleNext(pollDelayMs);
                    return;
                }
                if (data.run_id && data.run_id !== runId) {
                    scheduleNext(pollDelayMs);
                    return;
                }
                if (data.status === "success" || data.status === "failed") {
                    if (!sessionStorage.getItem(shownKey)) {
                        sessionStorage.setItem(shownKey, "1");
                        showPopup(data.status);
                    }
                    return;
                }
                scheduleNext(pollDelayMs);
            })
            .catch(function(){
                scheduleNext(pollDelayMs + 2000);
            });
    }

    checkStatus();
})();
JS;

    print_footer_and_html_epilogue($inline_extra_js);
