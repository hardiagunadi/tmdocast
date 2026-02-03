<?php
/*
 *********************************************************************************************************
 * daloRADIUS - RADIUS Web Platform
 * Copyright (C) 2007 - Liran Tal <liran@lirantal.com> All Rights Reserved.
 *
 * HSGQ OLT Management
 * Supports HSGQ EPON OLT devices for PON/ONU management
 *
 *********************************************************************************************************
 */

require_once(dirname(__FILE__) . '/OltInterface.php');
require_once(dirname(__FILE__) . '/TelnetConnection.php');

class HsgqOlt implements OltInterface {
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

    // HSGQ specific prompts
    const PROMPT_USER = 'HSGQ#';
    const PROMPT_CONFIG = 'HSGQ(config)#';
    const PROMPT_INTERFACE = 'HSGQ(config-if';
    const PROMPT_PROFILE = 'HSGQ(config-epon-onu-profile)#';

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

            // HSGQ login
            if (!$this->telnet->login($this->username, $this->password, 'Username:', 'Password:', ['HSGQ#', 'HSGQ>'])) {
                throw new Exception("Login failed");
            }

            // Enter enable mode if needed
            if (!empty($this->enablePassword)) {
                $this->telnet->write('enable');
                $this->telnet->waitFor('Password:');
                $this->telnet->write($this->enablePassword);
                $this->telnet->waitFor('HSGQ#');
            }

            // Disable paging
            $this->telnet->execute('terminal length 0', ['HSGQ#']);

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

        $output = $this->telnet->execute('show version', ['HSGQ#']);

        $info = [
            'model' => 'HSGQ OLT',
            'firmware' => null,
            'serial' => null,
            'uptime' => null,
            'mac_address' => null
        ];

        if (preg_match('/Software\s*Version\s*:\s*(.+)/i', $output, $m)) {
            $info['firmware'] = trim($m[1]);
        }
        if (preg_match('/Serial\s*Number\s*:\s*(.+)/i', $output, $m)) {
            $info['serial'] = trim($m[1]);
        }
        if (preg_match('/System\s*Uptime\s*:\s*(.+)/i', $output, $m)) {
            $info['uptime'] = trim($m[1]);
        }
        if (preg_match('/MAC\s*Address\s*:\s*([0-9A-Fa-f:.-]+)/i', $output, $m)) {
            $info['mac_address'] = trim($m[1]);
        }

        return $info;
    }

    public function getPonPorts() {
        $this->ensureConnected();

        $output = $this->telnet->execute('show epon interface brief', ['HSGQ#']);

        $ports = [];
        $lines = explode("\n", $output);

        foreach ($lines as $line) {
            // HSGQ format: "epon 0/1   enable   up    2"
            if (preg_match('/epon\s+(\d+)\/(\d+)\s+(\w+)\s+(\w+)\s+(\d+)/i', $line, $m)) {
                $ports[] = [
                    'slot' => intval($m[1]),
                    'port' => intval($m[2]),
                    'port_number' => intval($m[2]),
                    'admin_status' => strtolower($m[3]) === 'enable' ? 'up' : 'down',
                    'oper_status' => strtolower($m[4]),
                    'tx_power' => null,
                    'active_onus' => intval($m[5])
                ];
            }
        }

        return $ports;
    }

    public function getPonPortDetail($portNumber) {
        $this->ensureConnected();

        $output = $this->telnet->execute("show epon interface epon 0/{$portNumber}", ['HSGQ#']);

        $detail = [
            'port_number' => $portNumber,
            'admin_status' => null,
            'oper_status' => null,
            'tx_power' => null,
            'max_onus' => 64,
            'active_onus' => 0
        ];

        if (preg_match('/Admin\s*Status\s*:\s*(\w+)/i', $output, $m)) {
            $detail['admin_status'] = strtolower($m[1]) === 'enable' ? 'up' : 'down';
        }
        if (preg_match('/Link\s*Status\s*:\s*(\w+)/i', $output, $m)) {
            $detail['oper_status'] = strtolower($m[1]);
        }
        if (preg_match('/TX\s*Power\s*:\s*(-?\d+\.?\d*)/i', $output, $m)) {
            $detail['tx_power'] = floatval($m[1]);
        }
        if (preg_match('/Registered\s*ONU\s*:\s*(\d+)/i', $output, $m)) {
            $detail['active_onus'] = intval($m[1]);
        }

        return $detail;
    }

    public function getOnuList($ponPort) {
        $this->ensureConnected();

        $output = $this->telnet->execute("show epon onu-info interface epon 0/{$ponPort}", ['HSGQ#']);

        $onus = [];
        $lines = explode("\n", $output);

        foreach ($lines as $line) {
            // HSGQ format: "0/1:1  00:11:22:33:44:55  online  authorized"
            if (preg_match('/\d+\/\d+:(\d+)\s+([0-9A-Fa-f:.-]+)\s+(\w+)\s+(\w+)/i', $line, $m)) {
                $onus[] = [
                    'onu_index' => intval($m[1]),
                    'mac_address' => $this->normalizeMac($m[2]),
                    'oper_status' => strtolower($m[3]),
                    'auth_status' => strtolower($m[4]),
                    'onu_type' => null
                ];
            }
        }

        return $onus;
    }

    public function getOnuDetail($ponPort, $onuIndex) {
        $this->ensureConnected();

        $output = $this->telnet->execute("show epon onu-info interface epon 0/{$ponPort}:{$onuIndex}", ['HSGQ#']);

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

        // Parse fields
        if (preg_match('/MAC\s*Address\s*:\s*([0-9A-Fa-f:.-]+)/i', $output, $m)) {
            $detail['mac_address'] = $this->normalizeMac($m[1]);
        }
        if (preg_match('/ONU\s*Status\s*:\s*(\w+)/i', $output, $m)) {
            $detail['oper_status'] = strtolower($m[1]);
        }
        if (preg_match('/Auth\s*Status\s*:\s*(\w+)/i', $output, $m)) {
            $detail['auth_status'] = strtolower($m[1]);
        }
        if (preg_match('/Distance\s*:\s*(\d+)/i', $output, $m)) {
            $detail['distance'] = intval($m[1]);
        }

        return $detail;
    }

    public function getOnuOpticalInfo($ponPort, $onuIndex) {
        $this->ensureConnected();

        $output = $this->telnet->execute("show epon optical-power interface epon 0/{$ponPort}:{$onuIndex}", ['HSGQ#']);

        $info = [
            'rx_power' => null,
            'tx_power' => null,
            'olt_rx_power' => null,
            'temperature' => null,
            'voltage' => null,
            'bias_current' => null
        ];

        // Parse HSGQ optical info format
        if (preg_match('/ONU\s*RX\s*Power\s*[:\s]+(-?\d+\.?\d*)/i', $output, $m)) {
            $info['rx_power'] = floatval($m[1]);
        }
        if (preg_match('/ONU\s*TX\s*Power\s*[:\s]+(-?\d+\.?\d*)/i', $output, $m)) {
            $info['tx_power'] = floatval($m[1]);
        }
        if (preg_match('/OLT\s*RX\s*Power\s*[:\s]+(-?\d+\.?\d*)/i', $output, $m)) {
            $info['olt_rx_power'] = floatval($m[1]);
        }
        if (preg_match('/Temperature\s*[:\s]+(-?\d+\.?\d*)/i', $output, $m)) {
            $info['temperature'] = floatval($m[1]);
        }
        if (preg_match('/Voltage\s*[:\s]+(\d+\.?\d*)/i', $output, $m)) {
            $info['voltage'] = floatval($m[1]);
        }

        // Calculate signal quality
        if ($info['olt_rx_power'] !== null) {
            $info['signal_quality'] = SignalQuality::classify($info['olt_rx_power']);
        }

        return $info;
    }

    public function getUnregisteredOnus() {
        $this->ensureConnected();

        $output = $this->telnet->execute('show epon onu-info unauth', ['HSGQ#']);

        $onus = [];
        $lines = explode("\n", $output);

        foreach ($lines as $line) {
            // HSGQ format: "epon 0/1   00:11:22:33:44:55"
            if (preg_match('/epon\s+(\d+)\/(\d+)\s+([0-9A-Fa-f:.-]+)/i', $line, $m)) {
                $onus[] = [
                    'slot' => intval($m[1]),
                    'port' => intval($m[2]),
                    'mac_address' => $this->normalizeMac($m[3]),
                    'info' => ''
                ];
            }
        }

        return $onus;
    }

    public function registerOnu($ponPort, $onuIndex, $macAddress, $onuType = null, $description = null) {
        $this->ensureConnected();

        $mac = $this->normalizeMac($macAddress);

        $this->enterConfigMode();
        $this->telnet->execute("interface epon 0/{$ponPort}", [self::PROMPT_INTERFACE, self::PROMPT_CONFIG]);

        // HSGQ uses MAC-based binding
        $cmd = "epon onu {$onuIndex} bind-mac {$mac}";
        $output = $this->telnet->execute($cmd);

        if ($description) {
            $this->telnet->execute("epon onu {$onuIndex} description \"{$description}\"");
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
        $this->telnet->execute("interface epon 0/{$ponPort}", [self::PROMPT_INTERFACE]);

        $output = $this->telnet->execute("no epon onu {$onuIndex}");

        $this->exitConfigMode();

        return (stripos($output, 'error') === false);
    }

    public function configureOnuPppoe($ponPort, $onuIndex, $username, $password, $vlan, $cos = null) {
        $this->ensureConnected();

        // HSGQ uses profile-based configuration
        $profileName = "pppoe_p{$ponPort}_o{$onuIndex}";

        $this->enterConfigMode();

        // Create/update ONU profile
        $this->telnet->execute("epon onu-profile {$profileName}", [self::PROMPT_PROFILE, self::PROMPT_CONFIG]);
        $this->telnet->execute("wan-service 1 type pppoe vlan {$vlan}");
        $this->telnet->execute("wan-service 1 username {$username}");
        $this->telnet->execute("wan-service 1 password {$password}");
        if ($cos !== null) {
            $this->telnet->execute("wan-service 1 cos {$cos}");
        }
        $this->telnet->write('exit');
        $this->telnet->waitFor(self::PROMPT_CONFIG);

        // Bind profile to ONU
        $this->telnet->execute("interface epon 0/{$ponPort}", [self::PROMPT_INTERFACE]);
        $output = $this->telnet->execute("epon onu {$onuIndex} bind-profile {$profileName}");

        $this->exitConfigMode();

        if (stripos($output, 'error') !== false) {
            $this->lastError = $output;
            return false;
        }

        return true;
    }

    public function configureOnuBridge($ponPort, $onuIndex, $vlan) {
        $this->ensureConnected();

        $profileName = "bridge_p{$ponPort}_o{$onuIndex}";

        $this->enterConfigMode();

        // Create bridge profile
        $this->telnet->execute("epon onu-profile {$profileName}", [self::PROMPT_PROFILE]);
        $this->telnet->execute("wan-service 1 type bridge vlan {$vlan}");
        $this->telnet->write('exit');
        $this->telnet->waitFor(self::PROMPT_CONFIG);

        // Bind to ONU
        $this->telnet->execute("interface epon 0/{$ponPort}", [self::PROMPT_INTERFACE]);
        $this->telnet->execute("epon onu {$onuIndex} bind-profile {$profileName}");

        $this->exitConfigMode();

        return true;
    }

    public function configureOnuWifi($ponPort, $onuIndex, $ssid, $password, $channel = null, $enabled = true) {
        $this->ensureConnected();

        $this->enterConfigMode();
        $this->telnet->execute("interface epon 0/{$ponPort}", [self::PROMPT_INTERFACE]);

        // HSGQ WiFi configuration
        $this->telnet->execute("epon onu {$onuIndex} wifi ssid \"{$ssid}\"");
        $this->telnet->execute("epon onu {$onuIndex} wifi password \"{$password}\"");

        if ($channel !== null) {
            $this->telnet->execute("epon onu {$onuIndex} wifi channel {$channel}");
        }

        if ($enabled) {
            $this->telnet->execute("epon onu {$onuIndex} wifi enable");
        } else {
            $this->telnet->execute("epon onu {$onuIndex} wifi disable");
        }

        $this->exitConfigMode();

        return true;
    }

    public function configureOnuLanPorts($ponPort, $onuIndex, $ports) {
        $this->ensureConnected();

        $this->enterConfigMode();
        $this->telnet->execute("interface epon 0/{$ponPort}", [self::PROMPT_INTERFACE]);

        foreach ($ports as $portNum => $config) {
            $vlan = $config['vlan'] ?? 1;
            $mode = $config['mode'] ?? 'access';

            $this->telnet->execute("epon onu {$onuIndex} lan-port {$portNum} vlan {$vlan} {$mode}");
        }

        $this->exitConfigMode();

        return true;
    }

    public function rebootOnu($ponPort, $onuIndex) {
        $this->ensureConnected();

        $this->enterConfigMode();
        $this->telnet->execute("interface epon 0/{$ponPort}", [self::PROMPT_INTERFACE]);

        $output = $this->telnet->execute("epon onu {$onuIndex} reboot");

        $this->exitConfigMode();

        return (stripos($output, 'error') === false);
    }

    public function resetOnuFactory($ponPort, $onuIndex) {
        $this->ensureConnected();

        $this->enterConfigMode();
        $this->telnet->execute("interface epon 0/{$ponPort}", [self::PROMPT_INTERFACE]);

        $output = $this->telnet->execute("epon onu {$onuIndex} reset factory");

        $this->exitConfigMode();

        return (stripos($output, 'error') === false);
    }

    public function getOnuUptime($ponPort, $onuIndex) {
        $detail = $this->getOnuDetail($ponPort, $onuIndex);

        if (isset($detail['uptime'])) {
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

        $output = $this->telnet->execute('write', ['HSGQ#', 'OK', 'success'], 30);

        return (stripos($output, 'error') === false);
    }

    public function getLastError() {
        return $this->lastError;
    }

    public function executeCommand($command) {
        $this->ensureConnected();
        return $this->telnet->execute($command, ['HSGQ#', 'HSGQ(config']);
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

        $this->telnet->execute('configure terminal', [self::PROMPT_CONFIG]);
        $this->inConfigMode = true;
    }

    private function exitConfigMode() {
        if (!$this->inConfigMode) {
            return;
        }

        $this->telnet->write('end');
        $this->telnet->waitFor('HSGQ#');
        $this->inConfigMode = false;
    }

    private function normalizeMac($mac) {
        $mac = strtoupper(preg_replace('/[^0-9A-Fa-f]/', '', $mac));
        if (strlen($mac) !== 12) {
            return $mac;
        }
        return implode(':', str_split($mac, 2));
    }

    private function parseUptime($uptimeStr) {
        $seconds = 0;

        if (preg_match('/(\d+)\s*days?/i', $uptimeStr, $m)) {
            $seconds += intval($m[1]) * 86400;
        }
        if (preg_match('/(\d+)\s*hours?/i', $uptimeStr, $m)) {
            $seconds += intval($m[1]) * 3600;
        }
        if (preg_match('/(\d+)\s*min/i', $uptimeStr, $m)) {
            $seconds += intval($m[1]) * 60;
        }
        if (preg_match('/(\d+):(\d+):(\d+)/', $uptimeStr, $m)) {
            $seconds += intval($m[1]) * 3600 + intval($m[2]) * 60 + intval($m[3]);
        }

        return $seconds;
    }
}
