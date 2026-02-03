<?php
/*
 *********************************************************************************************************
 * daloRADIUS - RADIUS Web Platform
 * Copyright (C) 2007 - Liran Tal <liran@lirantal.com> All Rights Reserved.
 *
 * OLT Interface - Abstract interface for OLT device management
 * Supports EPON OLT devices (Hioso, HSGQ, and compatible devices)
 *
 *********************************************************************************************************
 */

/**
 * OLT Interface - Defines common methods for OLT management
 */
interface OltInterface {

    /**
     * Connect to OLT device
     * @return bool True if connection successful
     * @throws Exception on connection failure
     */
    public function connect();

    /**
     * Disconnect from OLT device
     */
    public function disconnect();

    /**
     * Check if connected to OLT
     * @return bool True if connected
     */
    public function isConnected();

    /**
     * Get OLT system information
     * @return array System info (model, firmware, serial, uptime, etc.)
     */
    public function getSystemInfo();

    /**
     * Get all PON ports status
     * @return array List of PON ports with status
     */
    public function getPonPorts();

    /**
     * Get PON port details
     * @param int $portNumber PON port number
     * @return array Port details (admin status, oper status, tx power, active ONUs)
     */
    public function getPonPortDetail($portNumber);

    /**
     * Get all ONUs on a PON port
     * @param int $ponPort PON port number
     * @return array List of ONUs with basic info
     */
    public function getOnuList($ponPort);

    /**
     * Get detailed ONU information
     * @param int $ponPort PON port number
     * @param int $onuIndex ONU index on port
     * @return array ONU details
     */
    public function getOnuDetail($ponPort, $onuIndex);

    /**
     * Get ONU optical signal information
     * @param int $ponPort PON port number
     * @param int $onuIndex ONU index on port
     * @return array Optical info (rx power, tx power, temperature, voltage, etc.)
     */
    public function getOnuOpticalInfo($ponPort, $onuIndex);

    /**
     * Get unregistered/unauthorized ONUs
     * @return array List of unregistered ONUs with MAC addresses
     */
    public function getUnregisteredOnus();

    /**
     * Register/authorize a new ONU
     * @param int $ponPort PON port number
     * @param int $onuIndex ONU index to assign
     * @param string $macAddress ONU MAC address
     * @param string $onuType ONU type/model (optional)
     * @param string $description ONU description (optional)
     * @return bool True if successful
     */
    public function registerOnu($ponPort, $onuIndex, $macAddress, $onuType = null, $description = null);

    /**
     * Deregister/remove ONU
     * @param int $ponPort PON port number
     * @param int $onuIndex ONU index
     * @return bool True if successful
     */
    public function deregisterOnu($ponPort, $onuIndex);

    /**
     * Configure ONU PPPoE settings
     * @param int $ponPort PON port number
     * @param int $onuIndex ONU index
     * @param string $username PPPoE username
     * @param string $password PPPoE password
     * @param int $vlan WAN VLAN ID
     * @param int $cos Class of Service (optional)
     * @return bool True if successful
     */
    public function configureOnuPppoe($ponPort, $onuIndex, $username, $password, $vlan, $cos = null);

    /**
     * Configure ONU in bridge mode
     * @param int $ponPort PON port number
     * @param int $onuIndex ONU index
     * @param int $vlan VLAN ID
     * @return bool True if successful
     */
    public function configureOnuBridge($ponPort, $onuIndex, $vlan);

    /**
     * Configure ONU WiFi settings
     * @param int $ponPort PON port number
     * @param int $onuIndex ONU index
     * @param string $ssid WiFi SSID
     * @param string $password WiFi password
     * @param int $channel WiFi channel (optional, null for auto)
     * @param bool $enabled Enable/disable WiFi
     * @return bool True if successful
     */
    public function configureOnuWifi($ponPort, $onuIndex, $ssid, $password, $channel = null, $enabled = true);

    /**
     * Configure ONU LAN ports
     * @param int $ponPort PON port number
     * @param int $onuIndex ONU index
     * @param array $ports Array of port configs [port => ['vlan' => x, 'mode' => 'trunk|access']]
     * @return bool True if successful
     */
    public function configureOnuLanPorts($ponPort, $onuIndex, $ports);

    /**
     * Reboot ONU
     * @param int $ponPort PON port number
     * @param int $onuIndex ONU index
     * @return bool True if successful
     */
    public function rebootOnu($ponPort, $onuIndex);

    /**
     * Reset ONU to factory defaults
     * @param int $ponPort PON port number
     * @param int $onuIndex ONU index
     * @return bool True if successful
     */
    public function resetOnuFactory($ponPort, $onuIndex);

    /**
     * Get ONU uptime
     * @param int $ponPort PON port number
     * @param int $onuIndex ONU index
     * @return int Uptime in seconds
     */
    public function getOnuUptime($ponPort, $onuIndex);

    /**
     * Get ONU firmware version
     * @param int $ponPort PON port number
     * @param int $onuIndex ONU index
     * @return string Firmware version
     */
    public function getOnuFirmware($ponPort, $onuIndex);

    /**
     * Save OLT configuration
     * @return bool True if successful
     */
    public function saveConfig();

    /**
     * Get last error message
     * @return string|null Last error message
     */
    public function getLastError();

    /**
     * Execute raw command
     * @param string $command Command to execute
     * @return string Command output
     */
    public function executeCommand($command);
}

/**
 * ONU Status Constants
 */
class OnuStatus {
    const ONLINE = 'online';
    const OFFLINE = 'offline';
    const POWER_SAVING = 'powersaving';
    const UNKNOWN = 'unknown';
}

/**
 * ONU Auth Status Constants
 */
class OnuAuthStatus {
    const AUTHORIZED = 'authorized';
    const UNAUTHORIZED = 'unauthorized';
    const PENDING = 'pending';
}

/**
 * Signal Quality Classification
 */
class SignalQuality {
    const EXCELLENT = 'excellent';  // >= -25 dBm
    const GOOD = 'good';            // >= -27 dBm
    const FAIR = 'fair';            // >= -29 dBm
    const POOR = 'poor';            // >= -31 dBm
    const CRITICAL = 'critical';    // < -31 dBm

    /**
     * Classify signal quality based on power level
     * @param float $powerDbm Power in dBm
     * @return string Quality classification
     */
    public static function classify($powerDbm) {
        if ($powerDbm >= -25) return self::EXCELLENT;
        if ($powerDbm >= -27) return self::GOOD;
        if ($powerDbm >= -29) return self::FAIR;
        if ($powerDbm >= -31) return self::POOR;
        return self::CRITICAL;
    }

    /**
     * Get color code for signal quality
     * @param string $quality Quality classification
     * @return string Bootstrap color class
     */
    public static function getColor($quality) {
        $colors = [
            self::EXCELLENT => 'success',
            self::GOOD => 'info',
            self::FAIR => 'warning',
            self::POOR => 'danger',
            self::CRITICAL => 'dark'
        ];
        return $colors[$quality] ?? 'secondary';
    }
}
