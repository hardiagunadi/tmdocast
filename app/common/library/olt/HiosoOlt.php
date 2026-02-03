<?php
/*
 *********************************************************************************************************
 * daloRADIUS - RADIUS Web Platform
 * Copyright (C) 2007 - Liran Tal <liran@lirantal.com> All Rights Reserved.
 *
 * Hioso OLT Management
 * Supports Hioso EPON OLT devices for PON/ONU management
 *
 *********************************************************************************************************
 */

require_once(dirname(__FILE__) . '/OltInterface.php');
require_once(dirname(__FILE__) . '/TelnetConnection.php');

class HiosoOlt implements OltInterface {
    private $telnet = null;
    private $host;
    private $port;
    private $username;
    private $password;
    private $enablePassword;
    private $timeout;
    private $lastError = null;
    private $connected = false;
    private $inConfigMode = false;

    // Default prompts
    const PROMPT_USER = '#';
    const PROMPT_CONFIG = '(config)#';
    const PROMPT_INTERFACE = '(config-if';

    public function __construct($host, $port = 23, $username = 'admin', $password = 'admin', $enablePassword = '', $timeout = 10) {
        $this->host = $host;
        $this->port = $port;
        $this->username = $username;
        $this->password = $password;
        $this->enablePassword = $enablePassword;
        $this->timeout = $timeout;
    }

    public function connect() {
        try {
            $this->telnet = new TelnetConnection($this->host, $this->port, $this->timeout);

            // Login
            if (!$this->telnet->login($this->username, $this->password)) {
                throw new Exception("Login failed");
            }

            // Enter enable mode if password provided
            if (!empty($this->enablePassword)) {
                $this->telnet->write('enable');
                $this->telnet->waitFor('Password:');
                $this->telnet->write($this->enablePassword);
                $this->telnet->waitFor('#');
            }

            // Disable paging for full output
            $this->telnet->execute('terminal length 0');

            $this->connected = true;
            return true;

        } catch (Exception $e) {
            $this->lastError = $e->getMessage();
            return false;
        }
    }

    public function disconnect() {
        if ($this->inConfigMode) {
            $this->exitConfigMode();
        }

        if ($this->telnet) {
            $this->telnet->write('exit');
            $this->telnet->close();
            $this->telnet = null;
        }

        $this->connected = false;
    }

    public function isConnected() {
        return $this->connected && $this->telnet && $this->telnet->isConnected();
    }

    public function getSystemInfo() {
        $this->ensureConnected();

        $output = $this->telnet->execute('show version');

        $info = [
            'model' => null,
            'firmware' => null,
            'serial' => null,
            'uptime' => null,
            'mac_address' => null
        ];

        // Parse version output
        if (preg_match('/Model\s*:\s*(.+)/i', $output, $m)) {
            $info['model'] = trim($m[1]);
        }
        if (preg_match('/Version\s*:\s*(.+)/i', $output, $m)) {
            $info['firmware'] = trim($m[1]);
        }
        if (preg_match('/Serial\s*:\s*(.+)/i', $output, $m)) {
            $info['serial'] = trim($m[1]);
        }
        if (preg_match('/Uptime\s*:\s*(.+)/i', $output, $m)) {
            $info['uptime'] = trim($m[1]);
        }
        if (preg_match('/MAC\s*(?:Address)?\s*:\s*([0-9A-Fa-f:.-]+)/i', $output, $m)) {
            $info['mac_address'] = trim($m[1]);
        }

        return $info;
    }

    public function getPonPorts() {
        $this->ensureConnected();

        $output = $this->telnet->execute('show pon status');

        $ports = [];
        $lines = explode("\n", $output);

        foreach ($lines as $line) {
            // Parse PON port lines
            // Format varies: "PON 0/1  up    up    -3.5 dBm   64"
            if (preg_match('/(?:PON|EPON)\s*(\d+)\/(\d+)\s+(\w+)\s+(\w+)\s+(-?\d+\.?\d*)\s*dBm\s+(\d+)/i', $line, $m)) {
                $ports[] = [
                    'slot' => intval($m[1]),
                    'port' => intval($m[2]),
                    'port_number' => intval($m[2]),
                    'admin_status' => strtolower($m[3]),
                    'oper_status' => strtolower($m[4]),
                    'tx_power' => floatval($m[5]),
                    'active_onus' => intval($m[6])
                ];
            }
        }

        return $ports;
    }

    public function getPonPortDetail($portNumber) {
        $this->ensureConnected();

        $output = $this->telnet->execute("show pon port 0/{$portNumber}");

        $detail = [
            'port_number' => $portNumber,
            'admin_status' => null,
            'oper_status' => null,
            'tx_power' => null,
            'max_onus' => 64,
            'active_onus' => 0
        ];

        if (preg_match('/Admin\s*Status\s*:\s*(\w+)/i', $output, $m)) {
            $detail['admin_status'] = strtolower($m[1]);
        }
        if (preg_match('/Oper\s*Status\s*:\s*(\w+)/i', $output, $m)) {
            $detail['oper_status'] = strtolower($m[1]);
        }
        if (preg_match('/TX\s*Power\s*:\s*(-?\d+\.?\d*)\s*dBm/i', $output, $m)) {
            $detail['tx_power'] = floatval($m[1]);
        }
        if (preg_match('/Online\s*ONU\s*:\s*(\d+)/i', $output, $m)) {
            $detail['active_onus'] = intval($m[1]);
        }

        return $detail;
    }

    public function getOnuList($ponPort) {
        $this->ensureConnected();

        $output = $this->telnet->execute("show onu info {$ponPort}");

        $onus = [];
        $lines = explode("\n", $output);

        foreach ($lines as $line) {
            // Parse ONU info lines
            // Format: "1   00:11:22:33:44:55  online  auth   HG8245H"
            if (preg_match('/^\s*(\d+)\s+([0-9A-Fa-f:.-]+)\s+(\w+)\s+(\w+)\s*(.*)$/i', $line, $m)) {
                $onus[] = [
                    'onu_index' => intval($m[1]),
                    'mac_address' => $this->normalizeMac($m[2]),
                    'oper_status' => strtolower($m[3]),
                    'auth_status' => strtolower($m[4]),
                    'onu_type' => trim($m[5])
                ];
            }
        }

        return $onus;
    }

    public function getOnuDetail($ponPort, $onuIndex) {
        $this->ensureConnected();

        $output = $this->telnet->execute("show onu detail {$ponPort} {$onuIndex}");

        $detail = [
            'onu_index' => $onuIndex,
            'mac_address' => null,
            'serial_number' => null,
            'onu_type' => null,
            'oper_status' => null,
            'auth_status' => null,
            'firmware_version' => null,
            'hardware_version' => null,
            'ip_address' => null,
            'distance' => null,
            'last_online' => null,
            'last_offline' => null
        ];

        // Parse various fields
        $patterns = [
            'mac_address' => '/MAC\s*(?:Address)?\s*:\s*([0-9A-Fa-f:.-]+)/i',
            'serial_number' => '/Serial\s*(?:Number)?\s*:\s*(\S+)/i',
            'onu_type' => '/(?:ONU\s*)?Type\s*:\s*(.+)/i',
            'oper_status' => '/(?:Oper(?:ational)?\s*)?Status\s*:\s*(\w+)/i',
            'firmware_version' => '/Firmware\s*(?:Version)?\s*:\s*(.+)/i',
            'hardware_version' => '/Hardware\s*(?:Version)?\s*:\s*(.+)/i',
            'ip_address' => '/IP\s*(?:Address)?\s*:\s*([\d.]+)/i',
            'distance' => '/Distance\s*:\s*(\d+)\s*m/i'
        ];

        foreach ($patterns as $key => $pattern) {
            if (preg_match($pattern, $output, $m)) {
                $detail[$key] = trim($m[1]);
                if ($key === 'mac_address') {
                    $detail[$key] = $this->normalizeMac($detail[$key]);
                }
                if ($key === 'distance') {
                    $detail[$key] = intval($detail[$key]);
                }
            }
        }

        return $detail;
    }

    public function getOnuOpticalInfo($ponPort, $onuIndex) {
        $this->ensureConnected();

        $output = $this->telnet->execute("show onu optical-transceiver-diagnosis {$ponPort} {$onuIndex}");

        $info = [
            'rx_power' => null,
            'tx_power' => null,
            'olt_rx_power' => null,
            'temperature' => null,
            'voltage' => null,
            'bias_current' => null
        ];

        // Parse optical info
        if (preg_match('/(?:ONU\s*)?RX\s*Power\s*:\s*(-?\d+\.?\d*)\s*dBm/i', $output, $m)) {
            $info['rx_power'] = floatval($m[1]);
        }
        if (preg_match('/(?:ONU\s*)?TX\s*Power\s*:\s*(-?\d+\.?\d*)\s*dBm/i', $output, $m)) {
            $info['tx_power'] = floatval($m[1]);
        }
        if (preg_match('/(?:OLT\s*)?RX\s*(?:from\s*ONU\s*)?Power\s*:\s*(-?\d+\.?\d*)\s*dBm/i', $output, $m)) {
            $info['olt_rx_power'] = floatval($m[1]);
        }
        if (preg_match('/Temperature\s*:\s*(-?\d+\.?\d*)\s*(?:C|Celsius)?/i', $output, $m)) {
            $info['temperature'] = floatval($m[1]);
        }
        if (preg_match('/Voltage\s*:\s*(\d+\.?\d*)\s*V/i', $output, $m)) {
            $info['voltage'] = floatval($m[1]);
        }
        if (preg_match('/Bias\s*Current\s*:\s*(\d+\.?\d*)\s*mA/i', $output, $m)) {
            $info['bias_current'] = floatval($m[1]);
        }

        // Calculate signal quality
        if ($info['olt_rx_power'] !== null) {
            $info['signal_quality'] = SignalQuality::classify($info['olt_rx_power']);
        }

        return $info;
    }

    public function getUnregisteredOnus() {
        $this->ensureConnected();

        $output = $this->telnet->execute('show onu unauth');

        $onus = [];
        $lines = explode("\n", $output);

        foreach ($lines as $line) {
            // Parse unregistered ONU lines
            if (preg_match('/(\d+)\/(\d+)\s+([0-9A-Fa-f:.-]+)\s*(.*)$/i', $line, $m)) {
                $onus[] = [
                    'slot' => intval($m[1]),
                    'port' => intval($m[2]),
                    'mac_address' => $this->normalizeMac($m[3]),
                    'info' => trim($m[4])
                ];
            }
        }

        return $onus;
    }

    public function registerOnu($ponPort, $onuIndex, $macAddress, $onuType = null, $description = null) {
        $this->ensureConnected();

        $mac = $this->normalizeMac($macAddress);
        $type = $onuType ?? 'auto';

        $this->enterConfigMode();
        $this->enterInterfaceMode("epon 0/{$ponPort}");

        $cmd = "onu {$onuIndex} type {$type} mac {$mac}";
        $output = $this->telnet->execute($cmd, [self::PROMPT_INTERFACE, self::PROMPT_CONFIG, 'Error', 'error']);

        if ($description) {
            $this->telnet->execute("onu {$onuIndex} description \"{$description}\"");
        }

        $this->exitConfigMode();

        if (stripos($output, 'error') !== false || stripos($output, 'failed') !== false) {
            $this->lastError = $output;
            return false;
        }

        return true;
    }

    public function deregisterOnu($ponPort, $onuIndex) {
        $this->ensureConnected();

        $this->enterConfigMode();
        $this->enterInterfaceMode("epon 0/{$ponPort}");

        $output = $this->telnet->execute("no onu {$onuIndex}");

        $this->exitConfigMode();

        return (stripos($output, 'error') === false);
    }

    public function configureOnuPppoe($ponPort, $onuIndex, $username, $password, $vlan, $cos = null) {
        $this->ensureConnected();

        $this->enterConfigMode();
        $this->enterInterfaceMode("epon 0/{$ponPort}");

        // Configure WAN PPPoE
        $cmd = "onu {$onuIndex} ctc wan 1 mode pppoe username {$username} password {$password} vlan {$vlan}";
        if ($cos !== null) {
            $cmd .= " cos {$cos}";
        }

        $output = $this->telnet->execute($cmd);

        $this->exitConfigMode();

        if (stripos($output, 'error') !== false) {
            $this->lastError = $output;
            return false;
        }

        return true;
    }

    public function configureOnuBridge($ponPort, $onuIndex, $vlan) {
        $this->ensureConnected();

        $this->enterConfigMode();
        $this->enterInterfaceMode("epon 0/{$ponPort}");

        $output = $this->telnet->execute("onu {$onuIndex} ctc wan 1 mode bridge vlan {$vlan}");

        $this->exitConfigMode();

        return (stripos($output, 'error') === false);
    }

    public function configureOnuWifi($ponPort, $onuIndex, $ssid, $password, $channel = null, $enabled = true) {
        $this->ensureConnected();

        $this->enterConfigMode();
        $this->enterInterfaceMode("epon 0/{$ponPort}");

        // Configure WiFi
        $this->telnet->execute("onu {$onuIndex} ctc wifi ssid \"{$ssid}\"");
        $this->telnet->execute("onu {$onuIndex} ctc wifi password \"{$password}\"");

        if ($channel !== null) {
            $this->telnet->execute("onu {$onuIndex} ctc wifi channel {$channel}");
        }

        if ($enabled) {
            $this->telnet->execute("onu {$onuIndex} ctc wifi enable");
        } else {
            $this->telnet->execute("onu {$onuIndex} ctc wifi disable");
        }

        $this->exitConfigMode();

        return true;
    }

    public function configureOnuLanPorts($ponPort, $onuIndex, $ports) {
        $this->ensureConnected();

        $this->enterConfigMode();
        $this->enterInterfaceMode("epon 0/{$ponPort}");

        foreach ($ports as $portNum => $config) {
            $vlan = $config['vlan'] ?? 1;
            $mode = $config['mode'] ?? 'access';

            $this->telnet->execute("onu {$onuIndex} ctc eth-port {$portNum} vlan {$vlan} mode {$mode}");
        }

        $this->exitConfigMode();

        return true;
    }

    public function rebootOnu($ponPort, $onuIndex) {
        $this->ensureConnected();

        $this->enterConfigMode();
        $this->enterInterfaceMode("epon 0/{$ponPort}");

        $output = $this->telnet->execute("onu {$onuIndex} reboot");

        $this->exitConfigMode();

        return (stripos($output, 'error') === false);
    }

    public function resetOnuFactory($ponPort, $onuIndex) {
        $this->ensureConnected();

        $this->enterConfigMode();
        $this->enterInterfaceMode("epon 0/{$ponPort}");

        $output = $this->telnet->execute("onu {$onuIndex} restore factory");

        $this->exitConfigMode();

        return (stripos($output, 'error') === false);
    }

    public function getOnuUptime($ponPort, $onuIndex) {
        $detail = $this->getOnuDetail($ponPort, $onuIndex);

        if (isset($detail['uptime'])) {
            // Parse uptime string to seconds
            return $this->parseUptime($detail['uptime']);
        }

        return 0;
    }

    public function getOnuFirmware($ponPort, $onuIndex) {
        $detail = $this->getOnuDetail($ponPort, $onuIndex);
        return $detail['firmware_version'] ?? null;
    }

    public function saveConfig() {
        $this->ensureConnected();

        if ($this->inConfigMode) {
            $this->exitConfigMode();
        }

        $output = $this->telnet->execute('write memory', ['#', 'OK', 'Success'], 30);

        return (stripos($output, 'error') === false);
    }

    public function getLastError() {
        return $this->lastError;
    }

    public function executeCommand($command) {
        $this->ensureConnected();
        return $this->telnet->execute($command);
    }

    // ==========================================
    // Private Helper Methods
    // ==========================================

    private function ensureConnected() {
        if (!$this->isConnected()) {
            throw new Exception("Not connected to OLT");
        }
    }

    private function enterConfigMode() {
        if ($this->inConfigMode) {
            return;
        }

        $this->telnet->execute('configure terminal');
        $this->inConfigMode = true;
    }

    private function enterInterfaceMode($interface) {
        $this->telnet->execute("interface {$interface}");
    }

    private function exitConfigMode() {
        if (!$this->inConfigMode) {
            return;
        }

        // Exit from any nested mode
        $this->telnet->write('end');
        $this->telnet->waitFor('#');
        $this->inConfigMode = false;
    }

    private function normalizeMac($mac) {
        // Convert various MAC formats to xx:xx:xx:xx:xx:xx
        $mac = strtoupper(preg_replace('/[^0-9A-Fa-f]/', '', $mac));
        if (strlen($mac) !== 12) {
            return $mac;
        }
        return implode(':', str_split($mac, 2));
    }

    private function parseUptime($uptimeStr) {
        // Parse "X days, HH:MM:SS" or similar formats
        $seconds = 0;

        if (preg_match('/(\d+)\s*days?/i', $uptimeStr, $m)) {
            $seconds += intval($m[1]) * 86400;
        }
        if (preg_match('/(\d+)\s*hours?/i', $uptimeStr, $m)) {
            $seconds += intval($m[1]) * 3600;
        }
        if (preg_match('/(\d+)\s*min(?:ute)?s?/i', $uptimeStr, $m)) {
            $seconds += intval($m[1]) * 60;
        }
        if (preg_match('/(\d+)\s*sec(?:ond)?s?/i', $uptimeStr, $m)) {
            $seconds += intval($m[1]);
        }
        if (preg_match('/(\d+):(\d+):(\d+)/', $uptimeStr, $m)) {
            $seconds += intval($m[1]) * 3600 + intval($m[2]) * 60 + intval($m[3]);
        }

        return $seconds;
    }
}
