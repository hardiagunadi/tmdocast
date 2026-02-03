#!/usr/bin/env php
<?php
/*
 *********************************************************************************************************
 * daloRADIUS - RADIUS Web Platform
 * Database Update Script for Multi-Tenant SaaS
 *
 * Usage: php update_database.php [options]
 *
 * Options:
 *   --help          Show this help message
 *   --check         Check database connection only
 *   --backup        Create backup before update
 *   --create-admin  Create super admin account
 *   --force         Skip confirmation prompts
 *********************************************************************************************************
 */

// Only run from CLI
if (php_sapi_name() !== 'cli') {
    die("This script must be run from command line.\n");
}

// Colors for terminal output
class Colors {
    const RESET = "\033[0m";
    const RED = "\033[31m";
    const GREEN = "\033[32m";
    const YELLOW = "\033[33m";
    const BLUE = "\033[34m";
    const CYAN = "\033[36m";
    const BOLD = "\033[1m";
}

function output($message, $color = '') {
    echo $color . $message . Colors::RESET . "\n";
}

function success($message) {
    output("✓ " . $message, Colors::GREEN);
}

function error($message) {
    output("✗ " . $message, Colors::RED);
}

function warning($message) {
    output("! " . $message, Colors::YELLOW);
}

function info($message) {
    output("→ " . $message, Colors::CYAN);
}

function header_text($message) {
    echo "\n" . Colors::BOLD . Colors::BLUE . "=== " . $message . " ===" . Colors::RESET . "\n\n";
}

function prompt($question, $default = '') {
    $defaultText = $default ? " [{$default}]" : "";
    echo Colors::YELLOW . $question . $defaultText . ": " . Colors::RESET;
    $input = trim(fgets(STDIN));
    return $input ?: $default;
}

function confirm($question) {
    echo Colors::YELLOW . $question . " (y/n): " . Colors::RESET;
    $input = strtolower(trim(fgets(STDIN)));
    return $input === 'y' || $input === 'yes';
}

// Parse command line arguments
$options = getopt('', ['help', 'check', 'backup', 'create-admin', 'force']);

if (isset($options['help'])) {
    echo <<<HELP

daloRADIUS Multi-Tenant SaaS Database Update Script

Usage: php update_database.php [options]

Options:
  --help          Show this help message
  --check         Check database connection only
  --backup        Create backup before update
  --create-admin  Create super admin account only
  --force         Skip confirmation prompts

Examples:
  php update_database.php                    # Run full update interactively
  php update_database.php --check            # Test database connection
  php update_database.php --backup --force   # Backup and update without prompts
  php update_database.php --create-admin     # Create super admin account


HELP;
    exit(0);
}

// Banner
echo Colors::BOLD . Colors::CYAN . "
╔═══════════════════════════════════════════════════════════════╗
║         daloRADIUS Multi-Tenant SaaS Database Update          ║
╚═══════════════════════════════════════════════════════════════╝
" . Colors::RESET;

// Load configuration
$configFile = __DIR__ . '/app/common/includes/daloradius.conf.php';
$configSample = __DIR__ . '/app/common/includes/daloradius.conf.php.sample';

if (!file_exists($configFile)) {
    if (file_exists($configSample)) {
        warning("Config file not found. Creating from sample...");
        copy($configSample, $configFile);
        success("Config file created: " . $configFile);
        warning("Please edit the config file with your database credentials and run this script again.");
        exit(1);
    } else {
        error("Config file not found: " . $configFile);
        exit(1);
    }
}

// Load config
include($configFile);

$dbHost = $configValues['CONFIG_DB_HOST'] ?? 'localhost';
$dbPort = $configValues['CONFIG_DB_PORT'] ?? '3306';
$dbUser = $configValues['CONFIG_DB_USER'] ?? 'root';
$dbPass = $configValues['CONFIG_DB_PASS'] ?? '';
$dbName = $configValues['CONFIG_DB_NAME'] ?? 'radius';

header_text("Database Configuration");
info("Host: {$dbHost}:{$dbPort}");
info("User: {$dbUser}");
info("Database: {$dbName}");

// Connect to database
header_text("Connecting to Database");

try {
    $pdo = new PDO(
        "mysql:host={$dbHost};port={$dbPort};dbname={$dbName};charset=utf8mb4",
        $dbUser,
        $dbPass,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
        ]
    );
    success("Connected to database successfully");
} catch (PDOException $e) {
    error("Database connection failed: " . $e->getMessage());
    exit(1);
}

// Check only mode
if (isset($options['check'])) {
    success("Database connection check passed!");
    exit(0);
}

// Backup option
if (isset($options['backup'])) {
    header_text("Creating Database Backup");

    $backupDir = __DIR__ . '/var/backups';
    if (!is_dir($backupDir)) {
        mkdir($backupDir, 0755, true);
    }

    $backupFile = $backupDir . '/backup_' . date('Ymd_His') . '.sql';
    $command = sprintf(
        'mysqldump -h%s -P%s -u%s %s %s > %s 2>&1',
        escapeshellarg($dbHost),
        escapeshellarg($dbPort),
        escapeshellarg($dbUser),
        $dbPass ? '-p' . escapeshellarg($dbPass) : '',
        escapeshellarg($dbName),
        escapeshellarg($backupFile)
    );

    info("Creating backup...");
    exec($command, $output, $returnCode);

    if ($returnCode === 0) {
        success("Backup created: " . $backupFile);
    } else {
        warning("Backup may have failed. Continue anyway? Check: " . $backupFile);
        if (!isset($options['force']) && !confirm("Continue without verified backup?")) {
            exit(1);
        }
    }
}

// Create admin only mode
if (isset($options['create-admin'])) {
    header_text("Create Super Admin Account");
    goto create_admin;
}

// Confirmation
if (!isset($options['force'])) {
    echo "\n";
    warning("This will update your database with multi-tenant SaaS tables.");
    warning("Make sure you have a backup before proceeding.");
    if (!confirm("Do you want to continue?")) {
        info("Aborted by user.");
        exit(0);
    }
}

header_text("Running Database Updates");

// SQL statements for multi-tenant SaaS
$sqlStatements = [
    // Tenants table
    "tenants" => "
        CREATE TABLE IF NOT EXISTS tenants (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            company_name VARCHAR(255) NOT NULL,
            domain_prefix VARCHAR(100) NOT NULL UNIQUE,
            email VARCHAR(255) NOT NULL,
            phone VARCHAR(50),
            address TEXT,
            logo_url VARCHAR(500),
            is_active TINYINT(1) DEFAULT 1,
            settings JSON,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_domain (domain_prefix),
            INDEX idx_active (is_active)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ",

    // Tenant operators
    "tenant_operators" => "
        CREATE TABLE IF NOT EXISTS tenant_operators (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            tenant_id INT UNSIGNED NOT NULL,
            username VARCHAR(100) NOT NULL,
            password_hash VARCHAR(255) NOT NULL,
            full_name VARCHAR(255),
            email VARCHAR(255),
            phone VARCHAR(50),
            role ENUM('admin', 'manager', 'operator', 'viewer') DEFAULT 'operator',
            is_active TINYINT(1) DEFAULT 1,
            last_login TIMESTAMP NULL,
            last_login_ip VARCHAR(45),
            failed_login_attempts INT DEFAULT 0,
            locked_until TIMESTAMP NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY unique_tenant_user (tenant_id, username),
            INDEX idx_tenant (tenant_id),
            FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ",

    // Super admins
    "super_admins" => "
        CREATE TABLE IF NOT EXISTS super_admins (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            username VARCHAR(100) NOT NULL UNIQUE,
            password_hash VARCHAR(255) NOT NULL,
            full_name VARCHAR(255),
            email VARCHAR(255),
            role ENUM('super_admin', 'admin', 'support') DEFAULT 'admin',
            is_active TINYINT(1) DEFAULT 1,
            last_login TIMESTAMP NULL,
            last_login_ip VARCHAR(45),
            failed_login_attempts INT DEFAULT 0,
            locked_until TIMESTAMP NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ",

    // Subscription plans
    "app_subscription_plans" => "
        CREATE TABLE IF NOT EXISTS app_subscription_plans (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(100) NOT NULL,
            description TEXT,
            price DECIMAL(15,2) NOT NULL DEFAULT 0,
            billing_period_days INT DEFAULT 30,
            max_users INT DEFAULT 100,
            max_mikrotik INT DEFAULT 5,
            max_olt INT DEFAULT 1,
            features JSON,
            is_active TINYINT(1) DEFAULT 1,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ",

    // Tenant subscriptions
    "tenant_subscriptions" => "
        CREATE TABLE IF NOT EXISTS tenant_subscriptions (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            tenant_id INT UNSIGNED NOT NULL,
            plan_id INT UNSIGNED NOT NULL,
            status ENUM('active', 'expired', 'cancelled', 'suspended') DEFAULT 'active',
            start_date TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            end_date TIMESTAMP NOT NULL,
            auto_renew TINYINT(1) DEFAULT 1,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_tenant (tenant_id),
            INDEX idx_status (status),
            INDEX idx_end_date (end_date),
            FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
            FOREIGN KEY (plan_id) REFERENCES app_subscription_plans(id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ",

    // Payment gateways
    "payment_gateways" => "
        CREATE TABLE IF NOT EXISTS payment_gateways (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(100) NOT NULL,
            code VARCHAR(50) NOT NULL UNIQUE,
            type ENUM('midtrans', 'moota', 'xendit', 'manual') NOT NULL,
            config JSON,
            is_active TINYINT(1) DEFAULT 1,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ",

    // Payment transactions
    "payment_transactions" => "
        CREATE TABLE IF NOT EXISTS payment_transactions (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            tenant_id INT UNSIGNED,
            gateway_id INT UNSIGNED,
            transaction_id VARCHAR(100) NOT NULL UNIQUE,
            external_id VARCHAR(255),
            payment_type ENUM('subscription', 'customer') NOT NULL,
            payment_method VARCHAR(50),
            amount DECIMAL(15,2) NOT NULL,
            status ENUM('pending', 'completed', 'failed', 'expired', 'refunded') DEFAULT 'pending',
            metadata JSON,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            completed_at TIMESTAMP NULL,
            INDEX idx_tenant (tenant_id),
            INDEX idx_status (status),
            INDEX idx_external (external_id),
            FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE SET NULL,
            FOREIGN KEY (gateway_id) REFERENCES payment_gateways(id) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ",

    // Customer accounts (for tenant's customers)
    "customer_accounts" => "
        CREATE TABLE IF NOT EXISTS customer_accounts (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            tenant_id INT UNSIGNED NOT NULL,
            customer_id VARCHAR(50) NOT NULL,
            name VARCHAR(255) NOT NULL,
            email VARCHAR(255),
            phone VARCHAR(50),
            address TEXT,
            pppoe_username VARCHAR(100),
            status ENUM('active', 'suspended', 'isolated', 'terminated') DEFAULT 'active',
            billing_cycle_day INT DEFAULT 1,
            monthly_fee DECIMAL(15,2) DEFAULT 0,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY unique_tenant_customer (tenant_id, customer_id),
            INDEX idx_tenant (tenant_id),
            INDEX idx_status (status),
            INDEX idx_pppoe (pppoe_username),
            FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ",

    // Customer invoices
    "customer_invoices" => "
        CREATE TABLE IF NOT EXISTS customer_invoices (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            tenant_id INT UNSIGNED NOT NULL,
            customer_id INT UNSIGNED NOT NULL,
            invoice_number VARCHAR(50) NOT NULL,
            amount DECIMAL(15,2) NOT NULL,
            unique_amount DECIMAL(15,2),
            status ENUM('pending', 'unpaid', 'paid', 'cancelled', 'overdue') DEFAULT 'pending',
            due_date DATE NOT NULL,
            paid_at TIMESTAMP NULL,
            period_start DATE,
            period_end DATE,
            notes TEXT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY unique_invoice (tenant_id, invoice_number),
            INDEX idx_tenant (tenant_id),
            INDEX idx_customer (customer_id),
            INDEX idx_status (status),
            INDEX idx_due_date (due_date),
            FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
            FOREIGN KEY (customer_id) REFERENCES customer_accounts(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ",

    // Customer payments
    "customer_payments" => "
        CREATE TABLE IF NOT EXISTS customer_payments (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            tenant_id INT UNSIGNED NOT NULL,
            customer_id INT UNSIGNED NOT NULL,
            invoice_id INT UNSIGNED,
            amount DECIMAL(15,2) NOT NULL,
            payment_method VARCHAR(50),
            payment_channel VARCHAR(100),
            reference_number VARCHAR(255),
            status ENUM('pending', 'completed', 'failed') DEFAULT 'pending',
            payment_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            notes TEXT,
            INDEX idx_tenant (tenant_id),
            INDEX idx_customer (customer_id),
            INDEX idx_invoice (invoice_id),
            FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
            FOREIGN KEY (customer_id) REFERENCES customer_accounts(id) ON DELETE CASCADE,
            FOREIGN KEY (invoice_id) REFERENCES customer_invoices(id) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ",

    // MikroTik devices
    "tenant_mikrotik_devices" => "
        CREATE TABLE IF NOT EXISTS tenant_mikrotik_devices (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            tenant_id INT UNSIGNED NOT NULL,
            name VARCHAR(100) NOT NULL,
            ip_address VARCHAR(45) NOT NULL,
            api_port INT DEFAULT 8728,
            api_username VARCHAR(100),
            api_password VARCHAR(255),
            vpn_peer_id INT UNSIGNED,
            location VARCHAR(255),
            is_active TINYINT(1) DEFAULT 1,
            last_seen TIMESTAMP NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_tenant (tenant_id),
            FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ",

    // OLT devices
    "olt_devices" => "
        CREATE TABLE IF NOT EXISTS olt_devices (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            tenant_id INT UNSIGNED NOT NULL,
            name VARCHAR(100) NOT NULL,
            brand ENUM('Hioso', 'HSGQ', 'ZTE', 'Huawei', 'Other') NOT NULL,
            model VARCHAR(100),
            ip_address VARCHAR(45) NOT NULL,
            telnet_port INT DEFAULT 23,
            username VARCHAR(100),
            password VARCHAR(255),
            location VARCHAR(255),
            is_active TINYINT(1) DEFAULT 1,
            last_sync TIMESTAMP NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_tenant (tenant_id),
            FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ",

    // OLT PON ports
    "olt_pon_ports" => "
        CREATE TABLE IF NOT EXISTS olt_pon_ports (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            olt_id INT UNSIGNED NOT NULL,
            port_number INT NOT NULL,
            port_name VARCHAR(50),
            status ENUM('up', 'down', 'unknown') DEFAULT 'unknown',
            max_onu INT DEFAULT 64,
            description TEXT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY unique_olt_port (olt_id, port_number),
            FOREIGN KEY (olt_id) REFERENCES olt_devices(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ",

    // OLT ONU devices
    "olt_onu_devices" => "
        CREATE TABLE IF NOT EXISTS olt_onu_devices (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            olt_id INT UNSIGNED NOT NULL,
            pon_port_id INT UNSIGNED NOT NULL,
            onu_index INT NOT NULL,
            serial_number VARCHAR(50) NOT NULL,
            customer_name VARCHAR(255),
            pppoe_username VARCHAR(100),
            vlan_id INT,
            status ENUM('online', 'offline', 'registered', 'los', 'unknown') DEFAULT 'unknown',
            rx_power DECIMAL(6,2),
            tx_power DECIMAL(6,2),
            distance DECIMAL(8,2),
            last_online TIMESTAMP NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_olt (olt_id),
            INDEX idx_serial (serial_number),
            INDEX idx_pppoe (pppoe_username),
            FOREIGN KEY (olt_id) REFERENCES olt_devices(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ",

    // OLT signal logs
    "olt_signal_logs" => "
        CREATE TABLE IF NOT EXISTS olt_signal_logs (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            onu_id INT UNSIGNED NOT NULL,
            rx_power DECIMAL(6,2),
            tx_power DECIMAL(6,2),
            temperature DECIMAL(5,2),
            voltage DECIMAL(6,3),
            recorded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_onu (onu_id),
            INDEX idx_recorded (recorded_at),
            FOREIGN KEY (onu_id) REFERENCES olt_onu_devices(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ",

    // VPN servers
    "vpn_servers" => "
        CREATE TABLE IF NOT EXISTS vpn_servers (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(100) NOT NULL,
            public_ip VARCHAR(45) NOT NULL,
            vpn_ip VARCHAR(45) NOT NULL,
            vpn_port INT DEFAULT 51820,
            public_key VARCHAR(255) NOT NULL,
            private_key VARCHAR(255) NOT NULL,
            is_active TINYINT(1) DEFAULT 1,
            max_peers INT DEFAULT 100,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ",

    // Tenant VPN configs
    "tenant_vpn_configs" => "
        CREATE TABLE IF NOT EXISTS tenant_vpn_configs (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            tenant_id INT UNSIGNED NOT NULL,
            server_id INT UNSIGNED NOT NULL,
            peer_public_key VARCHAR(255) NOT NULL,
            peer_private_key VARCHAR(255),
            assigned_ip VARCHAR(45) NOT NULL,
            is_active TINYINT(1) DEFAULT 1,
            last_handshake TIMESTAMP NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_tenant (tenant_id),
            FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
            FOREIGN KEY (server_id) REFERENCES vpn_servers(id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ",

    // Activity logs
    "activity_logs" => "
        CREATE TABLE IF NOT EXISTS activity_logs (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            tenant_id INT UNSIGNED,
            user_type ENUM('super_admin', 'operator', 'customer', 'system') NOT NULL,
            user_id INT UNSIGNED,
            action VARCHAR(100) NOT NULL,
            entity_type VARCHAR(100),
            entity_id INT UNSIGNED,
            description TEXT,
            ip_address VARCHAR(45),
            user_agent VARCHAR(255),
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_tenant (tenant_id),
            INDEX idx_user (user_type, user_id),
            INDEX idx_action (action),
            INDEX idx_created (created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ",

    // Bank accounts for Moota
    "bank_accounts" => "
        CREATE TABLE IF NOT EXISTS bank_accounts (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            tenant_id INT UNSIGNED,
            bank_code VARCHAR(20) NOT NULL,
            bank_name VARCHAR(100) NOT NULL,
            account_number VARCHAR(50) NOT NULL,
            account_name VARCHAR(255) NOT NULL,
            moota_bank_id VARCHAR(100),
            is_active TINYINT(1) DEFAULT 1,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_tenant (tenant_id),
            INDEX idx_moota (moota_bank_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    "
];

// Add tenant_id to existing RADIUS tables
$radiusTableUpdates = [
    "radcheck_tenant" => "ALTER TABLE radcheck ADD COLUMN IF NOT EXISTS tenant_id INT UNSIGNED DEFAULT NULL, ADD INDEX IF NOT EXISTS idx_tenant (tenant_id)",
    "radreply_tenant" => "ALTER TABLE radreply ADD COLUMN IF NOT EXISTS tenant_id INT UNSIGNED DEFAULT NULL, ADD INDEX IF NOT EXISTS idx_tenant (tenant_id)",
    "radacct_tenant" => "ALTER TABLE radacct ADD COLUMN IF NOT EXISTS tenant_id INT UNSIGNED DEFAULT NULL, ADD INDEX IF NOT EXISTS idx_tenant (tenant_id)",
    "radusergroup_tenant" => "ALTER TABLE radusergroup ADD COLUMN IF NOT EXISTS tenant_id INT UNSIGNED DEFAULT NULL, ADD INDEX IF NOT EXISTS idx_tenant (tenant_id)",
    "userinfo_tenant" => "ALTER TABLE userinfo ADD COLUMN IF NOT EXISTS tenant_id INT UNSIGNED DEFAULT NULL, ADD INDEX IF NOT EXISTS idx_tenant (tenant_id)",
    "userbillinfo_tenant" => "ALTER TABLE userbillinfo ADD COLUMN IF NOT EXISTS tenant_id INT UNSIGNED DEFAULT NULL, ADD INDEX IF NOT EXISTS idx_tenant (tenant_id)",
    "nas_tenant" => "ALTER TABLE nas ADD COLUMN IF NOT EXISTS tenant_id INT UNSIGNED DEFAULT NULL, ADD INDEX IF NOT EXISTS idx_tenant (tenant_id)"
];

// Default subscription plans
$defaultPlans = [
    ['Starter', 'Paket pemula untuk ISP kecil', 299000, 30, 100, 3, 1],
    ['Professional', 'Paket profesional untuk ISP menengah', 599000, 30, 500, 10, 3],
    ['Enterprise', 'Paket enterprise untuk ISP besar', 1499000, 30, 2000, 50, 10],
    ['Unlimited', 'Paket unlimited tanpa batasan', 2999000, 30, -1, -1, -1]
];

// Default payment gateways
$defaultGateways = [
    ['Midtrans', 'midtrans', 'midtrans', '{"server_key":"","client_key":"","is_production":false}'],
    ['Moota', 'moota', 'moota', '{"api_key":"","secret_key":""}'],
    ['Manual Transfer', 'manual', 'manual', '{}']
];

$totalTables = count($sqlStatements);
$created = 0;
$skipped = 0;
$errors = 0;

foreach ($sqlStatements as $tableName => $sql) {
    try {
        // Check if table exists
        $checkSql = "SHOW TABLES LIKE '{$tableName}'";
        $result = $pdo->query($checkSql);

        if ($result->rowCount() > 0) {
            warning("Table '{$tableName}' already exists - skipped");
            $skipped++;
        } else {
            $pdo->exec($sql);
            success("Created table: {$tableName}");
            $created++;
        }
    } catch (PDOException $e) {
        error("Failed to create '{$tableName}': " . $e->getMessage());
        $errors++;
    }
}

echo "\n";
info("Tables: {$created} created, {$skipped} skipped, {$errors} errors");

// Update RADIUS tables
header_text("Updating RADIUS Tables");

foreach ($radiusTableUpdates as $name => $sql) {
    try {
        $pdo->exec($sql);
        success("Updated: " . str_replace('_tenant', '', $name));
    } catch (PDOException $e) {
        // Column might already exist
        if (strpos($e->getMessage(), 'Duplicate column') === false) {
            warning("Note for {$name}: " . $e->getMessage());
        }
    }
}

// Insert default data
header_text("Inserting Default Data");

// Subscription plans
try {
    $checkPlans = $pdo->query("SELECT COUNT(*) FROM app_subscription_plans")->fetchColumn();
    if ($checkPlans == 0) {
        $stmt = $pdo->prepare("INSERT INTO app_subscription_plans (name, description, price, billing_period_days, max_users, max_mikrotik, max_olt) VALUES (?, ?, ?, ?, ?, ?, ?)");
        foreach ($defaultPlans as $plan) {
            $stmt->execute($plan);
        }
        success("Inserted " . count($defaultPlans) . " subscription plans");
    } else {
        warning("Subscription plans already exist - skipped");
    }
} catch (PDOException $e) {
    error("Failed to insert plans: " . $e->getMessage());
}

// Payment gateways
try {
    $checkGateways = $pdo->query("SELECT COUNT(*) FROM payment_gateways")->fetchColumn();
    if ($checkGateways == 0) {
        $stmt = $pdo->prepare("INSERT INTO payment_gateways (name, code, type, config) VALUES (?, ?, ?, ?)");
        foreach ($defaultGateways as $gateway) {
            $stmt->execute($gateway);
        }
        success("Inserted " . count($defaultGateways) . " payment gateways");
    } else {
        warning("Payment gateways already exist - skipped");
    }
} catch (PDOException $e) {
    error("Failed to insert gateways: " . $e->getMessage());
}

// Create super admin
create_admin:
header_text("Super Admin Account");

try {
    $checkAdmin = $pdo->query("SELECT COUNT(*) FROM super_admins")->fetchColumn();

    if ($checkAdmin > 0 && !isset($options['create-admin'])) {
        warning("Super admin account already exists");
        $existingAdmin = $pdo->query("SELECT username, email FROM super_admins LIMIT 1")->fetch();
        info("Existing admin: " . $existingAdmin['username'] . " ({$existingAdmin['email']})");
    } else {
        if (isset($options['force'])) {
            $adminUser = 'admin';
            $adminPass = 'admin123';
            $adminName = 'Super Administrator';
            $adminEmail = 'admin@localhost';
        } else {
            echo "\n";
            $adminUser = prompt("Admin username", "admin");
            $adminPass = prompt("Admin password", "admin123");
            $adminName = prompt("Full name", "Super Administrator");
            $adminEmail = prompt("Email", "admin@localhost");
        }

        $passwordHash = password_hash($adminPass, PASSWORD_DEFAULT);

        $stmt = $pdo->prepare("INSERT INTO super_admins (username, password_hash, full_name, email, role, is_active) VALUES (?, ?, ?, ?, 'super_admin', 1)");
        $stmt->execute([$adminUser, $passwordHash, $adminName, $adminEmail]);

        success("Super admin created successfully!");
        echo "\n";
        info("Username: {$adminUser}");
        info("Password: {$adminPass}");
        info("Login URL: /app/admin/login.php");
    }
} catch (PDOException $e) {
    if (strpos($e->getMessage(), 'Duplicate entry') !== false) {
        warning("Admin username already exists");
    } else {
        error("Failed to create admin: " . $e->getMessage());
    }
}

// Summary
header_text("Update Complete");

echo Colors::GREEN . "
Database has been updated successfully!

Access Points:
" . Colors::RESET;

echo "  Super Admin Panel  : " . Colors::CYAN . "/app/admin/login.php" . Colors::RESET . "\n";
echo "  Tenant Portal      : " . Colors::CYAN . "/app/portal/login.php" . Colors::RESET . "\n";
echo "  Customer Portal    : " . Colors::CYAN . "/app/users/login.php" . Colors::RESET . "\n";

echo Colors::YELLOW . "
Next Steps:
" . Colors::RESET;
echo "  1. Login to Super Admin panel and create a tenant\n";
echo "  2. Configure payment gateway (Midtrans/Moota)\n";
echo "  3. Tenant can login at /app/portal/login.php\n";

echo "\n";
