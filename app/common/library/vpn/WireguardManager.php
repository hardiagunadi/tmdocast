<?php
/*
 *********************************************************************************************************
 * daloRADIUS - RADIUS Web Platform
 * Copyright (C) 2007 - Liran Tal <liran@lirantal.com> All Rights Reserved.
 *
 * WireGuard VPN Manager
 * Manages WireGuard server and client configurations for tenant connectivity
 *
 *********************************************************************************************************
 */

class WireguardManager {
    private $interface;
    private $configPath;
    private $serverPublicKey;
    private $serverPrivateKey;
    private $serverEndpoint;
    private $serverPort;
    private $networkAddress;
    private $lastError = null;

    /**
     * Constructor
     *
     * @param string $interface WireGuard interface name (default: wg0)
     * @param string $configPath Path to WireGuard config directory
     */
    public function __construct($interface = 'wg0', $configPath = '/etc/wireguard') {
        $this->interface = $interface;
        $this->configPath = $configPath;
        $this->loadServerConfig();
    }

    /**
     * Load server configuration from system
     */
    private function loadServerConfig() {
        global $configValues;

        $this->serverEndpoint = $configValues['VPN_SERVER_PUBLIC_IP'] ?? '';
        $this->serverPort = $configValues['VPN_SERVER_PORT'] ?? 51820;
        $this->serverPublicKey = $configValues['VPN_SERVER_PUBLIC_KEY'] ?? '';
        $this->networkAddress = $configValues['VPN_NETWORK'] ?? '10.100.0.0/24';
    }

    /**
     * Generate a new WireGuard key pair
     *
     * @return array ['private_key', 'public_key', 'preshared_key']
     */
    public function generateKeyPair() {
        $privateKey = trim(shell_exec('wg genkey 2>/dev/null'));
        if (empty($privateKey)) {
            throw new Exception("Failed to generate private key. WireGuard tools not installed?");
        }

        $publicKey = trim(shell_exec("echo '{$privateKey}' | wg pubkey 2>/dev/null"));
        $presharedKey = trim(shell_exec('wg genpsk 2>/dev/null'));

        return [
            'private_key' => $privateKey,
            'public_key' => $publicKey,
            'preshared_key' => $presharedKey
        ];
    }

    /**
     * Get next available IP in the VPN network
     *
     * @return string Next available IP address
     */
    public function getNextAvailableIp() {
        global $dbSocket;

        // Parse network address
        list($network, $prefix) = explode('/', $this->networkAddress);
        $networkParts = explode('.', $network);

        // Get all used IPs
        $sql = "SELECT client_ip FROM tenant_vpn_configs WHERE is_active = 1";
        $res = $dbSocket->query($sql);

        $usedIps = [];
        while ($row = $res->fetchRow(DB_FETCHMODE_ASSOC)) {
            $usedIps[] = $row['client_ip'];
        }

        // Server uses .1
        $usedIps[] = "{$networkParts[0]}.{$networkParts[1]}.{$networkParts[2]}.1";

        // Find next available (start from .2)
        for ($i = 2; $i <= 254; $i++) {
            $ip = "{$networkParts[0]}.{$networkParts[1]}.{$networkParts[2]}.{$i}";
            if (!in_array($ip, $usedIps)) {
                return $ip;
            }
        }

        throw new Exception("No available IP addresses in VPN network");
    }

    /**
     * Create VPN configuration for a tenant
     *
     * @param int $tenantId Tenant ID
     * @param array $mikrotikNetworks Array of MikroTik LAN networks to route
     * @return array VPN configuration details
     */
    public function createTenantConfig($tenantId, $mikrotikNetworks = []) {
        global $dbSocket;

        // Check if tenant already has a config
        $sql = sprintf("SELECT * FROM tenant_vpn_configs WHERE tenant_id = %d", $tenantId);
        $existing = $dbSocket->getRow($sql, array(), DB_FETCHMODE_ASSOC);

        if (!empty($existing) && $existing['is_active']) {
            return $this->getTenantConfig($tenantId);
        }

        // Generate new keys
        $keys = $this->generateKeyPair();
        $clientIp = $this->getNextAvailableIp();

        // Build allowed IPs (client IP + MikroTik networks)
        $allowedIps = [$clientIp . '/32'];
        foreach ($mikrotikNetworks as $network) {
            if (filter_var(explode('/', $network)[0], FILTER_VALIDATE_IP)) {
                $allowedIps[] = $network;
            }
        }
        $allowedIpsStr = implode(',', $allowedIps);

        // Generate client config file content
        $clientConfig = $this->generateClientConfig(
            $keys['private_key'],
            $clientIp,
            $this->serverPublicKey,
            "{$this->serverEndpoint}:{$this->serverPort}",
            $keys['preshared_key']
        );

        // Get VPN server ID (assuming first server)
        $sql = "SELECT id FROM vpn_servers WHERE is_active = 1 LIMIT 1";
        $vpnServerId = intval($dbSocket->getOne($sql));

        if (!$vpnServerId) {
            throw new Exception("No active VPN server configured");
        }

        // Save to database
        if (!empty($existing)) {
            // Update existing (reactivate)
            $sql = sprintf("UPDATE tenant_vpn_configs SET
                    private_key = '%s',
                    public_key = '%s',
                    preshared_key = '%s',
                    client_ip = '%s',
                    allowed_ips = '%s',
                    config_file = '%s',
                    is_active = 1,
                    updated_at = NOW()
                WHERE tenant_id = %d",
                $dbSocket->escapeSimple($this->encrypt($keys['private_key'])),
                $dbSocket->escapeSimple($keys['public_key']),
                $dbSocket->escapeSimple($keys['preshared_key']),
                $dbSocket->escapeSimple($clientIp),
                $dbSocket->escapeSimple($allowedIpsStr),
                $dbSocket->escapeSimple($clientConfig),
                $tenantId
            );
        } else {
            // Insert new
            $sql = sprintf("INSERT INTO tenant_vpn_configs
                    (tenant_id, vpn_server_id, client_ip, private_key, public_key, preshared_key, allowed_ips, config_file, is_active, created_at)
                VALUES (%d, %d, '%s', '%s', '%s', '%s', '%s', '%s', 1, NOW())",
                $tenantId,
                $vpnServerId,
                $dbSocket->escapeSimple($clientIp),
                $dbSocket->escapeSimple($this->encrypt($keys['private_key'])),
                $dbSocket->escapeSimple($keys['public_key']),
                $dbSocket->escapeSimple($keys['preshared_key']),
                $dbSocket->escapeSimple($allowedIpsStr),
                $dbSocket->escapeSimple($clientConfig)
            );
        }
        $dbSocket->query($sql);

        // Add peer to WireGuard server
        $this->addPeer($keys['public_key'], $allowedIpsStr, $keys['preshared_key']);

        return [
            'client_ip' => $clientIp,
            'public_key' => $keys['public_key'],
            'server_public_key' => $this->serverPublicKey,
            'server_endpoint' => "{$this->serverEndpoint}:{$this->serverPort}",
            'config_file' => $clientConfig
        ];
    }

    /**
     * Get existing tenant VPN configuration
     *
     * @param int $tenantId Tenant ID
     * @return array|null Configuration or null if not found
     */
    public function getTenantConfig($tenantId) {
        global $dbSocket;

        $sql = sprintf("SELECT * FROM tenant_vpn_configs WHERE tenant_id = %d AND is_active = 1", $tenantId);
        $config = $dbSocket->getRow($sql, array(), DB_FETCHMODE_ASSOC);

        if (empty($config)) {
            return null;
        }

        return [
            'client_ip' => $config['client_ip'],
            'public_key' => $config['public_key'],
            'server_public_key' => $this->serverPublicKey,
            'server_endpoint' => "{$this->serverEndpoint}:{$this->serverPort}",
            'config_file' => $config['config_file'],
            'last_handshake' => $config['last_handshake'],
            'bytes_received' => $config['bytes_received'],
            'bytes_sent' => $config['bytes_sent']
        ];
    }

    /**
     * Generate client configuration file content
     *
     * @param string $clientPrivateKey Client private key
     * @param string $clientIp Client IP address
     * @param string $serverPublicKey Server public key
     * @param string $serverEndpoint Server endpoint (ip:port)
     * @param string $presharedKey Preshared key
     * @return string Configuration file content
     */
    public function generateClientConfig($clientPrivateKey, $clientIp, $serverPublicKey, $serverEndpoint, $presharedKey = null) {
        $config = <<<EOF
[Interface]
PrivateKey = {$clientPrivateKey}
Address = {$clientIp}/24
DNS = 1.1.1.1, 8.8.8.8

[Peer]
PublicKey = {$serverPublicKey}
EOF;

        if ($presharedKey) {
            $config .= "\nPresharedKey = {$presharedKey}";
        }

        $config .= <<<EOF

Endpoint = {$serverEndpoint}
AllowedIPs = 0.0.0.0/0
PersistentKeepalive = 25
EOF;

        return $config;
    }

    /**
     * Generate MikroTik RouterOS WireGuard configuration script
     *
     * @param int $tenantId Tenant ID
     * @return string RouterOS script
     */
    public function generateMikrotikScript($tenantId) {
        $config = $this->getTenantConfig($tenantId);

        if (!$config) {
            throw new Exception("No VPN configuration found for tenant");
        }

        global $dbSocket;

        // Get private key (encrypted in DB)
        $sql = sprintf("SELECT private_key FROM tenant_vpn_configs WHERE tenant_id = %d", $tenantId);
        $privateKeyEncrypted = $dbSocket->getOne($sql);
        $privateKey = $this->decrypt($privateKeyEncrypted);

        $clientIp = $config['client_ip'];
        $serverPublicKey = $config['server_public_key'];
        list($serverIp, $serverPort) = explode(':', $config['server_endpoint']);

        // Get preshared key
        $sql = sprintf("SELECT preshared_key FROM tenant_vpn_configs WHERE tenant_id = %d", $tenantId);
        $presharedKey = $dbSocket->getOne($sql);

        $script = <<<MIKROTIK
# ============================================
# WireGuard VPN Configuration for TMDRadius
# Generated: {date('Y-m-d H:i:s')}
# ============================================

# Remove existing WireGuard config if exists
/interface wireguard remove [find name=wg-tmdradius]

# Create WireGuard interface
/interface wireguard add name=wg-tmdradius private-key="{$privateKey}" listen-port=13231

# Add peer (TMDRadius Server)
/interface wireguard peers add \\
    interface=wg-tmdradius \\
    public-key="{$serverPublicKey}" \\
    preshared-key="{$presharedKey}" \\
    endpoint-address={$serverIp} \\
    endpoint-port={$serverPort} \\
    allowed-address=10.100.0.0/24 \\
    persistent-keepalive=25

# Assign IP address
/ip address add address={$clientIp}/24 interface=wg-tmdradius comment="TMDRadius VPN"

# Add route to reach RADIUS server (adjust as needed)
/ip route add dst-address=10.100.0.1/32 gateway=wg-tmdradius comment="TMDRadius RADIUS Server"

# Add RADIUS configuration (example - adjust IP as needed)
# /radius add address=10.100.0.1 secret=your_radius_secret service=ppp

# Enable the interface
/interface enable wg-tmdradius

:log info "TMDRadius WireGuard VPN configured successfully"
MIKROTIK;

        return $script;
    }

    /**
     * Add peer to WireGuard server
     *
     * @param string $publicKey Peer public key
     * @param string $allowedIps Allowed IPs for this peer
     * @param string $presharedKey Preshared key (optional)
     */
    public function addPeer($publicKey, $allowedIps, $presharedKey = null) {
        $cmd = sprintf('wg set %s peer %s allowed-ips %s',
            escapeshellarg($this->interface),
            escapeshellarg($publicKey),
            escapeshellarg($allowedIps)
        );

        if ($presharedKey) {
            // Write preshared key to temp file for security
            $tempFile = tempnam(sys_get_temp_dir(), 'wg_psk_');
            file_put_contents($tempFile, $presharedKey);
            $cmd .= sprintf(' preshared-key %s', escapeshellarg($tempFile));
            shell_exec($cmd . ' 2>/dev/null');
            unlink($tempFile);
        } else {
            shell_exec($cmd . ' 2>/dev/null');
        }

        // Save configuration
        $this->saveConfig();
    }

    /**
     * Remove peer from WireGuard server
     *
     * @param string $publicKey Peer public key
     */
    public function removePeer($publicKey) {
        $cmd = sprintf('wg set %s peer %s remove',
            escapeshellarg($this->interface),
            escapeshellarg($publicKey)
        );

        shell_exec($cmd . ' 2>/dev/null');
        $this->saveConfig();
    }

    /**
     * Get peer status from WireGuard
     *
     * @param string $publicKey Peer public key
     * @return array|null Peer status or null if not found
     */
    public function getPeerStatus($publicKey) {
        $output = shell_exec("wg show {$this->interface} dump 2>/dev/null");
        if (empty($output)) {
            return null;
        }

        $lines = explode("\n", $output);
        foreach ($lines as $line) {
            $parts = explode("\t", $line);
            if (count($parts) >= 8 && $parts[0] === $publicKey) {
                $latestHandshake = intval($parts[4]);
                return [
                    'public_key' => $parts[0],
                    'preshared_key' => $parts[1] !== '(none)' ? 'configured' : null,
                    'endpoint' => $parts[2] !== '(none)' ? $parts[2] : null,
                    'allowed_ips' => $parts[3],
                    'latest_handshake' => $latestHandshake > 0 ? date('Y-m-d H:i:s', $latestHandshake) : null,
                    'transfer_rx' => intval($parts[5]),
                    'transfer_tx' => intval($parts[6]),
                    'is_online' => $latestHandshake > 0 && (time() - $latestHandshake) < 180 // Online if handshake within 3 minutes
                ];
            }
        }

        return null;
    }

    /**
     * Get all peers status
     *
     * @return array Array of peer statuses
     */
    public function getAllPeersStatus() {
        $output = shell_exec("wg show {$this->interface} dump 2>/dev/null");
        if (empty($output)) {
            return [];
        }

        $peers = [];
        $lines = explode("\n", $output);

        // Skip first line (interface info)
        array_shift($lines);

        foreach ($lines as $line) {
            $parts = explode("\t", $line);
            if (count($parts) >= 8) {
                $latestHandshake = intval($parts[4]);
                $peers[] = [
                    'public_key' => $parts[0],
                    'endpoint' => $parts[2] !== '(none)' ? $parts[2] : null,
                    'allowed_ips' => $parts[3],
                    'latest_handshake' => $latestHandshake > 0 ? date('Y-m-d H:i:s', $latestHandshake) : null,
                    'transfer_rx' => intval($parts[5]),
                    'transfer_tx' => intval($parts[6]),
                    'is_online' => $latestHandshake > 0 && (time() - $latestHandshake) < 180
                ];
            }
        }

        return $peers;
    }

    /**
     * Update tenant VPN stats from WireGuard
     */
    public function updateAllTenantStats() {
        global $dbSocket;

        $peers = $this->getAllPeersStatus();

        foreach ($peers as $peer) {
            $sql = sprintf("UPDATE tenant_vpn_configs SET
                    last_handshake = %s,
                    bytes_received = %d,
                    bytes_sent = %d,
                    updated_at = NOW()
                WHERE public_key = '%s'",
                $peer['latest_handshake'] ? "'" . $peer['latest_handshake'] . "'" : 'NULL',
                $peer['transfer_rx'],
                $peer['transfer_tx'],
                $dbSocket->escapeSimple($peer['public_key'])
            );
            $dbSocket->query($sql);
        }
    }

    /**
     * Deactivate tenant VPN
     *
     * @param int $tenantId Tenant ID
     */
    public function deactivateTenantVpn($tenantId) {
        global $dbSocket;

        // Get public key
        $sql = sprintf("SELECT public_key FROM tenant_vpn_configs WHERE tenant_id = %d", $tenantId);
        $publicKey = $dbSocket->getOne($sql);

        if ($publicKey) {
            // Remove from WireGuard
            $this->removePeer($publicKey);

            // Mark as inactive in database
            $sql = sprintf("UPDATE tenant_vpn_configs SET is_active = 0, updated_at = NOW() WHERE tenant_id = %d", $tenantId);
            $dbSocket->query($sql);
        }
    }

    /**
     * Save WireGuard configuration
     */
    private function saveConfig() {
        shell_exec("wg-quick save {$this->interface} 2>/dev/null");
    }

    /**
     * Encrypt sensitive data
     *
     * @param string $data Data to encrypt
     * @return string Encrypted data (base64)
     */
    private function encrypt($data) {
        global $configValues;
        $key = $configValues['CONFIG_ENCRYPTION_KEY'] ?? 'default-key-change-this';
        $iv = openssl_random_pseudo_bytes(16);
        $encrypted = openssl_encrypt($data, 'AES-256-CBC', $key, 0, $iv);
        return base64_encode($iv . $encrypted);
    }

    /**
     * Decrypt sensitive data
     *
     * @param string $data Encrypted data (base64)
     * @return string Decrypted data
     */
    private function decrypt($data) {
        global $configValues;
        $key = $configValues['CONFIG_ENCRYPTION_KEY'] ?? 'default-key-change-this';
        $data = base64_decode($data);
        $iv = substr($data, 0, 16);
        $encrypted = substr($data, 16);
        return openssl_decrypt($encrypted, 'AES-256-CBC', $key, 0, $iv);
    }

    /**
     * Get last error
     *
     * @return string|null Last error message
     */
    public function getLastError() {
        return $this->lastError;
    }

    /**
     * Check if WireGuard is installed and interface exists
     *
     * @return bool True if WireGuard is ready
     */
    public function isReady() {
        $version = shell_exec('wg --version 2>/dev/null');
        if (empty($version)) {
            $this->lastError = 'WireGuard tools not installed';
            return false;
        }

        $interfaces = shell_exec('wg show interfaces 2>/dev/null');
        if (strpos($interfaces, $this->interface) === false) {
            $this->lastError = "Interface {$this->interface} not found";
            return false;
        }

        return true;
    }
}

/**
 * Helper function to create WireGuard manager from config
 */
function wireguard_create_manager() {
    global $configValues;

    return new WireguardManager(
        $configValues['VPN_INTERFACE'] ?? 'wg0',
        '/etc/wireguard'
    );
}
