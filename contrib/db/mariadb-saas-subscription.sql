-- =========================================================================================================
-- daloRADIUS SaaS Multi-Tenant Subscription System
-- Database Schema for Professional ISP Management Platform
-- Version: 2.0.0
-- =========================================================================================================

-- Disable FK checks for smooth import
SET FOREIGN_KEY_CHECKS = 0;

-- =========================================================================================================
-- PART 1: MULTI-TENANT / SaaS SYSTEM
-- =========================================================================================================

-- Tenants (ISP Organizations)
DROP TABLE IF EXISTS `tenants`;
CREATE TABLE `tenants` (
    `id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
    `tenant_code` VARCHAR(32) NOT NULL UNIQUE COMMENT 'Unique tenant identifier',
    `company_name` VARCHAR(255) NOT NULL,
    `company_email` VARCHAR(255) NOT NULL,
    `company_phone` VARCHAR(50) DEFAULT NULL,
    `company_address` TEXT DEFAULT NULL,
    `company_city` VARCHAR(100) DEFAULT NULL,
    `company_province` VARCHAR(100) DEFAULT NULL,
    `company_postal_code` VARCHAR(20) DEFAULT NULL,
    `company_country` VARCHAR(100) DEFAULT 'Indonesia',
    `company_logo` VARCHAR(500) DEFAULT NULL,
    `company_website` VARCHAR(255) DEFAULT NULL,
    `tax_id` VARCHAR(50) DEFAULT NULL COMMENT 'NPWP for Indonesia',
    `status` ENUM('pending', 'active', 'suspended', 'cancelled') DEFAULT 'pending',
    `subscription_plan_id` INT(11) UNSIGNED DEFAULT NULL,
    `subscription_expires_at` DATETIME DEFAULT NULL,
    `trial_ends_at` DATETIME DEFAULT NULL,
    `max_mikrotik_devices` INT(11) DEFAULT -1 COMMENT '-1 = unlimited',
    `max_pppoe_users` INT(11) DEFAULT -1 COMMENT '-1 = unlimited',
    `max_operators` INT(11) DEFAULT 5,
    `features_json` JSON DEFAULT NULL COMMENT 'Enabled features for this tenant',
    `settings_json` JSON DEFAULT NULL COMMENT 'Custom settings per tenant',
    `vpn_config` JSON DEFAULT NULL COMMENT 'VPN connection settings',
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_tenant_code` (`tenant_code`),
    KEY `idx_status` (`status`),
    KEY `idx_subscription_expires` (`subscription_expires_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tenant Operators (Users per Tenant)
DROP TABLE IF EXISTS `tenant_operators`;
CREATE TABLE `tenant_operators` (
    `id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
    `tenant_id` INT(11) UNSIGNED NOT NULL,
    `username` VARCHAR(128) NOT NULL,
    `password` VARCHAR(255) NOT NULL,
    `email` VARCHAR(255) NOT NULL,
    `firstname` VARCHAR(100) DEFAULT NULL,
    `lastname` VARCHAR(100) DEFAULT NULL,
    `phone` VARCHAR(50) DEFAULT NULL,
    `role` ENUM('owner', 'admin', 'operator', 'viewer') DEFAULT 'operator',
    `permissions_json` JSON DEFAULT NULL,
    `is_active` TINYINT(1) DEFAULT 1,
    `last_login` DATETIME DEFAULT NULL,
    `login_attempts` INT(11) DEFAULT 0,
    `locked_until` DATETIME DEFAULT NULL,
    `two_factor_enabled` TINYINT(1) DEFAULT 0,
    `two_factor_secret` VARCHAR(255) DEFAULT NULL,
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `idx_tenant_username` (`tenant_id`, `username`),
    KEY `idx_email` (`email`),
    CONSTRAINT `fk_tenant_operators_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Super Admin Users (Platform Administrators)
DROP TABLE IF EXISTS `super_admins`;
CREATE TABLE `super_admins` (
    `id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
    `username` VARCHAR(128) NOT NULL UNIQUE,
    `password` VARCHAR(255) NOT NULL,
    `email` VARCHAR(255) NOT NULL,
    `firstname` VARCHAR(100) DEFAULT NULL,
    `lastname` VARCHAR(100) DEFAULT NULL,
    `phone` VARCHAR(50) DEFAULT NULL,
    `is_active` TINYINT(1) DEFAULT 1,
    `last_login` DATETIME DEFAULT NULL,
    `two_factor_enabled` TINYINT(1) DEFAULT 0,
    `two_factor_secret` VARCHAR(255) DEFAULT NULL,
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Insert default super admin (password: admin123 - should be changed)
INSERT INTO `super_admins` (`username`, `password`, `email`, `firstname`, `lastname`, `is_active`)
VALUES ('superadmin', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin@localhost', 'Super', 'Admin', 1);

-- =========================================================================================================
-- PART 2: APPLICATION SUBSCRIPTION PLANS & BILLING
-- =========================================================================================================

-- App Subscription Plans (for SaaS pricing)
DROP TABLE IF EXISTS `app_subscription_plans`;
CREATE TABLE `app_subscription_plans` (
    `id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
    `plan_code` VARCHAR(50) NOT NULL UNIQUE,
    `plan_name` VARCHAR(255) NOT NULL,
    `plan_description` TEXT DEFAULT NULL,
    `billing_cycle` ENUM('monthly', 'quarterly', 'yearly') DEFAULT 'monthly',
    `price` DECIMAL(15, 2) NOT NULL DEFAULT 0,
    `currency` VARCHAR(10) DEFAULT 'IDR',
    `setup_fee` DECIMAL(15, 2) DEFAULT 0,
    `max_mikrotik_devices` INT(11) DEFAULT -1,
    `max_pppoe_users` INT(11) DEFAULT -1,
    `max_operators` INT(11) DEFAULT 5,
    `max_olt_devices` INT(11) DEFAULT 0,
    `features_json` JSON DEFAULT NULL COMMENT 'List of enabled features',
    `is_popular` TINYINT(1) DEFAULT 0,
    `is_active` TINYINT(1) DEFAULT 1,
    `trial_days` INT(11) DEFAULT 14,
    `sort_order` INT(11) DEFAULT 0,
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_plan_code` (`plan_code`),
    KEY `idx_is_active` (`is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Default subscription plans
INSERT INTO `app_subscription_plans` (`plan_code`, `plan_name`, `plan_description`, `billing_cycle`, `price`, `max_mikrotik_devices`, `max_pppoe_users`, `max_operators`, `max_olt_devices`, `is_popular`, `trial_days`, `sort_order`, `features_json`) VALUES
('starter', 'Starter', 'Cocok untuk ISP kecil dengan maksimal 100 pelanggan', 'monthly', 299000, 2, 100, 2, 0, 0, 14, 1, '["pppoe_management", "billing_basic", "customer_portal"]'),
('professional', 'Professional', 'Ideal untuk ISP menengah dengan fitur lengkap', 'monthly', 599000, 5, 500, 5, 2, 1, 14, 2, '["pppoe_management", "billing_full", "customer_portal", "qris_payment", "bank_transfer", "olt_basic", "whatsapp_notification"]'),
('enterprise', 'Enterprise', 'Solusi lengkap untuk ISP besar dengan unlimited', 'monthly', 1499000, -1, -1, -1, -1, 0, 30, 3, '["pppoe_management", "billing_full", "customer_portal", "qris_payment", "bank_transfer", "olt_full", "onu_management", "whatsapp_notification", "api_access", "priority_support"]'),
('unlimited', 'Unlimited', 'Akses penuh tanpa batasan apapun', 'monthly', 2999000, -1, -1, -1, -1, 0, 30, 4, '["all"]');

-- Tenant Subscriptions (Active subscriptions)
DROP TABLE IF EXISTS `tenant_subscriptions`;
CREATE TABLE `tenant_subscriptions` (
    `id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
    `tenant_id` INT(11) UNSIGNED NOT NULL,
    `plan_id` INT(11) UNSIGNED NOT NULL,
    `status` ENUM('active', 'pending', 'cancelled', 'expired', 'suspended') DEFAULT 'pending',
    `starts_at` DATETIME NOT NULL,
    `ends_at` DATETIME NOT NULL,
    `trial_ends_at` DATETIME DEFAULT NULL,
    `cancelled_at` DATETIME DEFAULT NULL,
    `cancellation_reason` TEXT DEFAULT NULL,
    `amount_paid` DECIMAL(15, 2) DEFAULT 0,
    `payment_method` VARCHAR(50) DEFAULT NULL,
    `payment_reference` VARCHAR(255) DEFAULT NULL,
    `auto_renew` TINYINT(1) DEFAULT 1,
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_tenant_id` (`tenant_id`),
    KEY `idx_status` (`status`),
    KEY `idx_ends_at` (`ends_at`),
    CONSTRAINT `fk_tenant_subscriptions_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_tenant_subscriptions_plan` FOREIGN KEY (`plan_id`) REFERENCES `app_subscription_plans` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- App Subscription Invoices
DROP TABLE IF EXISTS `app_subscription_invoices`;
CREATE TABLE `app_subscription_invoices` (
    `id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
    `invoice_number` VARCHAR(50) NOT NULL UNIQUE,
    `tenant_id` INT(11) UNSIGNED NOT NULL,
    `subscription_id` INT(11) UNSIGNED DEFAULT NULL,
    `plan_id` INT(11) UNSIGNED NOT NULL,
    `description` VARCHAR(500) DEFAULT NULL,
    `subtotal` DECIMAL(15, 2) NOT NULL,
    `tax_rate` DECIMAL(5, 2) DEFAULT 11 COMMENT 'PPN rate',
    `tax_amount` DECIMAL(15, 2) DEFAULT 0,
    `discount_amount` DECIMAL(15, 2) DEFAULT 0,
    `total_amount` DECIMAL(15, 2) NOT NULL,
    `currency` VARCHAR(10) DEFAULT 'IDR',
    `status` ENUM('pending', 'paid', 'failed', 'cancelled', 'refunded') DEFAULT 'pending',
    `payment_method` VARCHAR(50) DEFAULT NULL,
    `payment_reference` VARCHAR(255) DEFAULT NULL,
    `payment_date` DATETIME DEFAULT NULL,
    `due_date` DATETIME NOT NULL,
    `notes` TEXT DEFAULT NULL,
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_invoice_number` (`invoice_number`),
    KEY `idx_tenant_id` (`tenant_id`),
    KEY `idx_status` (`status`),
    CONSTRAINT `fk_app_invoices_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =========================================================================================================
-- PART 3: PAYMENT GATEWAY INTEGRATION (QRIS, Bank Transfer)
-- =========================================================================================================

-- Payment Gateways Configuration
DROP TABLE IF EXISTS `payment_gateways`;
CREATE TABLE `payment_gateways` (
    `id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
    `tenant_id` INT(11) UNSIGNED DEFAULT NULL COMMENT 'NULL for platform-level gateways',
    `gateway_code` VARCHAR(50) NOT NULL,
    `gateway_name` VARCHAR(255) NOT NULL,
    `gateway_type` ENUM('qris', 'bank_transfer', 'virtual_account', 'ewallet', 'credit_card') NOT NULL,
    `provider` ENUM('midtrans', 'xendit', 'duitku', 'tripay', 'ipaymu', 'moota', 'manual') NOT NULL,
    `is_platform` TINYINT(1) DEFAULT 0 COMMENT 'Is this a platform-level gateway?',
    `credentials_json` JSON DEFAULT NULL COMMENT 'API keys, secrets, etc (encrypted)',
    `settings_json` JSON DEFAULT NULL,
    `fee_type` ENUM('percentage', 'fixed', 'both') DEFAULT 'percentage',
    `fee_percentage` DECIMAL(5, 2) DEFAULT 0,
    `fee_fixed` DECIMAL(15, 2) DEFAULT 0,
    `min_amount` DECIMAL(15, 2) DEFAULT 10000,
    `max_amount` DECIMAL(15, 2) DEFAULT 100000000,
    `is_active` TINYINT(1) DEFAULT 1,
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_gateway_code` (`gateway_code`),
    KEY `idx_tenant_id` (`tenant_id`),
    KEY `idx_provider` (`provider`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Default platform payment gateways
INSERT INTO `payment_gateways` (`gateway_code`, `gateway_name`, `gateway_type`, `provider`, `is_platform`, `fee_percentage`, `is_active`) VALUES
('midtrans_qris', 'QRIS (Midtrans)', 'qris', 'midtrans', 1, 0.70, 1),
('xendit_qris', 'QRIS (Xendit)', 'qris', 'xendit', 1, 0.70, 1),
('midtrans_va_bca', 'Virtual Account BCA', 'virtual_account', 'midtrans', 1, 0.00, 1),
('midtrans_va_bni', 'Virtual Account BNI', 'virtual_account', 'midtrans', 1, 0.00, 1),
('midtrans_va_bri', 'Virtual Account BRI', 'virtual_account', 'midtrans', 1, 0.00, 1),
('midtrans_va_mandiri', 'Virtual Account Mandiri', 'virtual_account', 'midtrans', 1, 0.00, 1),
('moota_auto', 'Bank Transfer Auto-Confirm (Moota)', 'bank_transfer', 'moota', 1, 0.00, 1);

-- Bank Accounts (for manual/auto bank transfer)
DROP TABLE IF EXISTS `bank_accounts`;
CREATE TABLE `bank_accounts` (
    `id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
    `tenant_id` INT(11) UNSIGNED DEFAULT NULL COMMENT 'NULL for platform bank accounts',
    `bank_code` VARCHAR(20) NOT NULL,
    `bank_name` VARCHAR(100) NOT NULL,
    `account_number` VARCHAR(50) NOT NULL,
    `account_name` VARCHAR(255) NOT NULL,
    `branch` VARCHAR(255) DEFAULT NULL,
    `is_platform` TINYINT(1) DEFAULT 0,
    `moota_bank_id` VARCHAR(50) DEFAULT NULL COMMENT 'Moota integration ID',
    `moota_token` VARCHAR(255) DEFAULT NULL,
    `auto_confirm` TINYINT(1) DEFAULT 0,
    `is_active` TINYINT(1) DEFAULT 1,
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_tenant_id` (`tenant_id`),
    KEY `idx_bank_code` (`bank_code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Payment Transactions (All transactions)
DROP TABLE IF EXISTS `payment_transactions`;
CREATE TABLE `payment_transactions` (
    `id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
    `transaction_id` VARCHAR(100) NOT NULL UNIQUE,
    `tenant_id` INT(11) UNSIGNED DEFAULT NULL,
    `transaction_type` ENUM('app_subscription', 'customer_payment') NOT NULL,
    `reference_type` VARCHAR(50) DEFAULT NULL COMMENT 'invoice, subscription, etc',
    `reference_id` INT(11) UNSIGNED DEFAULT NULL,
    `gateway_id` INT(11) UNSIGNED DEFAULT NULL,
    `gateway_transaction_id` VARCHAR(255) DEFAULT NULL,
    `amount` DECIMAL(15, 2) NOT NULL,
    `fee_amount` DECIMAL(15, 2) DEFAULT 0,
    `net_amount` DECIMAL(15, 2) DEFAULT 0,
    `currency` VARCHAR(10) DEFAULT 'IDR',
    `payment_method` VARCHAR(50) DEFAULT NULL,
    `status` ENUM('pending', 'processing', 'success', 'failed', 'expired', 'refunded', 'cancelled') DEFAULT 'pending',
    `payment_url` VARCHAR(1000) DEFAULT NULL,
    `qris_string` TEXT DEFAULT NULL,
    `qris_image_url` VARCHAR(1000) DEFAULT NULL,
    `va_number` VARCHAR(50) DEFAULT NULL,
    `expires_at` DATETIME DEFAULT NULL,
    `paid_at` DATETIME DEFAULT NULL,
    `gateway_response` JSON DEFAULT NULL,
    `callback_received_at` DATETIME DEFAULT NULL,
    `metadata_json` JSON DEFAULT NULL,
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_transaction_id` (`transaction_id`),
    KEY `idx_tenant_id` (`tenant_id`),
    KEY `idx_status` (`status`),
    KEY `idx_reference` (`reference_type`, `reference_id`),
    KEY `idx_gateway_txn` (`gateway_transaction_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =========================================================================================================
-- PART 4: CUSTOMER (PPPoE Users) BILLING & PAYMENT
-- =========================================================================================================

-- Customer Internet Plans (per Tenant)
DROP TABLE IF EXISTS `customer_internet_plans`;
CREATE TABLE `customer_internet_plans` (
    `id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
    `tenant_id` INT(11) UNSIGNED NOT NULL,
    `plan_code` VARCHAR(50) NOT NULL,
    `plan_name` VARCHAR(255) NOT NULL,
    `plan_description` TEXT DEFAULT NULL,
    `speed_download` INT(11) NOT NULL COMMENT 'Kbps',
    `speed_upload` INT(11) NOT NULL COMMENT 'Kbps',
    `fup_quota` BIGINT(20) DEFAULT NULL COMMENT 'Bytes, NULL = unlimited',
    `fup_speed_download` INT(11) DEFAULT NULL COMMENT 'Speed after FUP',
    `fup_speed_upload` INT(11) DEFAULT NULL,
    `billing_cycle` ENUM('prepaid', 'postpaid') DEFAULT 'postpaid',
    `billing_period` ENUM('daily', 'weekly', 'monthly', 'quarterly', 'yearly') DEFAULT 'monthly',
    `price` DECIMAL(15, 2) NOT NULL,
    `setup_fee` DECIMAL(15, 2) DEFAULT 0,
    `tax_included` TINYINT(1) DEFAULT 1,
    `mikrotik_profile` VARCHAR(100) DEFAULT NULL,
    `radius_group` VARCHAR(100) DEFAULT NULL,
    `ip_pool` VARCHAR(100) DEFAULT NULL,
    `is_active` TINYINT(1) DEFAULT 1,
    `sort_order` INT(11) DEFAULT 0,
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `idx_tenant_plan_code` (`tenant_id`, `plan_code`),
    KEY `idx_tenant_id` (`tenant_id`),
    CONSTRAINT `fk_customer_plans_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Customer Accounts (PPPoE Users Extended Info)
DROP TABLE IF EXISTS `customer_accounts`;
CREATE TABLE `customer_accounts` (
    `id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
    `tenant_id` INT(11) UNSIGNED NOT NULL,
    `customer_code` VARCHAR(50) NOT NULL,
    `username` VARCHAR(128) NOT NULL COMMENT 'PPPoE username, links to radcheck',
    `email` VARCHAR(255) DEFAULT NULL,
    `phone` VARCHAR(50) DEFAULT NULL,
    `whatsapp` VARCHAR(50) DEFAULT NULL,
    `fullname` VARCHAR(255) NOT NULL,
    `nik` VARCHAR(20) DEFAULT NULL COMMENT 'National ID',
    `address` TEXT DEFAULT NULL,
    `rt_rw` VARCHAR(20) DEFAULT NULL,
    `kelurahan` VARCHAR(100) DEFAULT NULL,
    `kecamatan` VARCHAR(100) DEFAULT NULL,
    `city` VARCHAR(100) DEFAULT NULL,
    `province` VARCHAR(100) DEFAULT NULL,
    `postal_code` VARCHAR(10) DEFAULT NULL,
    `coordinates` VARCHAR(100) DEFAULT NULL COMMENT 'lat,lng',
    `plan_id` INT(11) UNSIGNED DEFAULT NULL,
    `onu_id` INT(11) UNSIGNED DEFAULT NULL,
    `installation_date` DATE DEFAULT NULL,
    `billing_date` INT(11) DEFAULT 1 COMMENT 'Day of month for billing',
    `balance` DECIMAL(15, 2) DEFAULT 0 COMMENT 'Prepaid balance',
    `status` ENUM('active', 'suspended', 'isolated', 'terminated', 'pending') DEFAULT 'pending',
    `isolation_reason` VARCHAR(255) DEFAULT NULL,
    `notes` TEXT DEFAULT NULL,
    `custom_fields_json` JSON DEFAULT NULL,
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `idx_tenant_customer_code` (`tenant_id`, `customer_code`),
    UNIQUE KEY `idx_tenant_username` (`tenant_id`, `username`),
    KEY `idx_phone` (`phone`),
    KEY `idx_status` (`status`),
    CONSTRAINT `fk_customer_accounts_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_customer_accounts_plan` FOREIGN KEY (`plan_id`) REFERENCES `customer_internet_plans` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Customer Invoices
DROP TABLE IF EXISTS `customer_invoices`;
CREATE TABLE `customer_invoices` (
    `id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
    `tenant_id` INT(11) UNSIGNED NOT NULL,
    `invoice_number` VARCHAR(50) NOT NULL,
    `customer_id` INT(11) UNSIGNED NOT NULL,
    `billing_period_start` DATE NOT NULL,
    `billing_period_end` DATE NOT NULL,
    `description` VARCHAR(500) DEFAULT NULL,
    `subtotal` DECIMAL(15, 2) NOT NULL,
    `tax_amount` DECIMAL(15, 2) DEFAULT 0,
    `discount_amount` DECIMAL(15, 2) DEFAULT 0,
    `additional_charges` DECIMAL(15, 2) DEFAULT 0,
    `total_amount` DECIMAL(15, 2) NOT NULL,
    `currency` VARCHAR(10) DEFAULT 'IDR',
    `status` ENUM('draft', 'pending', 'partial', 'paid', 'overdue', 'cancelled') DEFAULT 'pending',
    `due_date` DATE NOT NULL,
    `paid_amount` DECIMAL(15, 2) DEFAULT 0,
    `paid_at` DATETIME DEFAULT NULL,
    `last_reminder_sent` DATETIME DEFAULT NULL,
    `reminder_count` INT(11) DEFAULT 0,
    `notes` TEXT DEFAULT NULL,
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `idx_tenant_invoice` (`tenant_id`, `invoice_number`),
    KEY `idx_customer_id` (`customer_id`),
    KEY `idx_status` (`status`),
    KEY `idx_due_date` (`due_date`),
    CONSTRAINT `fk_customer_invoices_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_customer_invoices_customer` FOREIGN KEY (`customer_id`) REFERENCES `customer_accounts` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Customer Invoice Items
DROP TABLE IF EXISTS `customer_invoice_items`;
CREATE TABLE `customer_invoice_items` (
    `id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
    `invoice_id` INT(11) UNSIGNED NOT NULL,
    `item_type` ENUM('subscription', 'setup', 'additional', 'penalty', 'discount', 'tax') DEFAULT 'subscription',
    `description` VARCHAR(500) NOT NULL,
    `quantity` INT(11) DEFAULT 1,
    `unit_price` DECIMAL(15, 2) NOT NULL,
    `total_price` DECIMAL(15, 2) NOT NULL,
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_invoice_id` (`invoice_id`),
    CONSTRAINT `fk_invoice_items_invoice` FOREIGN KEY (`invoice_id`) REFERENCES `customer_invoices` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Customer Payments
DROP TABLE IF EXISTS `customer_payments`;
CREATE TABLE `customer_payments` (
    `id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
    `tenant_id` INT(11) UNSIGNED NOT NULL,
    `payment_number` VARCHAR(50) NOT NULL,
    `customer_id` INT(11) UNSIGNED NOT NULL,
    `invoice_id` INT(11) UNSIGNED DEFAULT NULL,
    `transaction_id` INT(11) UNSIGNED DEFAULT NULL,
    `amount` DECIMAL(15, 2) NOT NULL,
    `payment_method` VARCHAR(50) NOT NULL,
    `payment_channel` VARCHAR(50) DEFAULT NULL COMMENT 'Bank name, e-wallet type, etc',
    `reference_number` VARCHAR(255) DEFAULT NULL,
    `status` ENUM('pending', 'confirmed', 'rejected', 'refunded') DEFAULT 'pending',
    `confirmed_by` INT(11) UNSIGNED DEFAULT NULL,
    `confirmed_at` DATETIME DEFAULT NULL,
    `proof_image` VARCHAR(500) DEFAULT NULL,
    `notes` TEXT DEFAULT NULL,
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `idx_tenant_payment` (`tenant_id`, `payment_number`),
    KEY `idx_customer_id` (`customer_id`),
    KEY `idx_invoice_id` (`invoice_id`),
    KEY `idx_status` (`status`),
    CONSTRAINT `fk_customer_payments_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_customer_payments_customer` FOREIGN KEY (`customer_id`) REFERENCES `customer_accounts` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =========================================================================================================
-- PART 5: MIKROTIK DEVICE MANAGEMENT (per Tenant)
-- =========================================================================================================

-- Tenant MikroTik Devices
DROP TABLE IF EXISTS `tenant_mikrotik_devices`;
CREATE TABLE `tenant_mikrotik_devices` (
    `id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
    `tenant_id` INT(11) UNSIGNED NOT NULL,
    `device_name` VARCHAR(255) NOT NULL,
    `device_description` TEXT DEFAULT NULL,
    `host` VARCHAR(255) NOT NULL,
    `port` INT(11) DEFAULT 8728,
    `username` VARCHAR(128) NOT NULL,
    `password` VARCHAR(255) NOT NULL COMMENT 'Encrypted',
    `use_ssl` TINYINT(1) DEFAULT 0,
    `connection_type` ENUM('direct', 'vpn') DEFAULT 'direct',
    `vpn_interface` VARCHAR(50) DEFAULT NULL,
    `nas_ip` VARCHAR(45) DEFAULT NULL COMMENT 'NAS-IP-Address for RADIUS',
    `nas_secret` VARCHAR(255) DEFAULT NULL,
    `location` VARCHAR(255) DEFAULT NULL,
    `coordinates` VARCHAR(100) DEFAULT NULL,
    `model` VARCHAR(100) DEFAULT NULL,
    `ros_version` VARCHAR(50) DEFAULT NULL,
    `last_connected` DATETIME DEFAULT NULL,
    `connection_status` ENUM('connected', 'disconnected', 'error', 'unknown') DEFAULT 'unknown',
    `last_error` TEXT DEFAULT NULL,
    `is_active` TINYINT(1) DEFAULT 1,
    `sync_pppoe` TINYINT(1) DEFAULT 1,
    `sync_hotspot` TINYINT(1) DEFAULT 0,
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_tenant_id` (`tenant_id`),
    KEY `idx_nas_ip` (`nas_ip`),
    CONSTRAINT `fk_mikrotik_devices_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =========================================================================================================
-- PART 6: OLT EPON MANAGEMENT (Hioso, HSGQ)
-- =========================================================================================================

-- OLT Devices
DROP TABLE IF EXISTS `olt_devices`;
CREATE TABLE `olt_devices` (
    `id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
    `tenant_id` INT(11) UNSIGNED NOT NULL,
    `device_name` VARCHAR(255) NOT NULL,
    `device_model` ENUM('hioso', 'hsgq', 'huawei', 'zte', 'fiberhome', 'other') DEFAULT 'hioso',
    `device_description` TEXT DEFAULT NULL,
    `host` VARCHAR(255) NOT NULL,
    `telnet_port` INT(11) DEFAULT 23,
    `snmp_port` INT(11) DEFAULT 161,
    `ssh_port` INT(11) DEFAULT 22,
    `web_port` INT(11) DEFAULT 80,
    `username` VARCHAR(128) DEFAULT NULL,
    `password` VARCHAR(255) DEFAULT NULL,
    `enable_password` VARCHAR(255) DEFAULT NULL,
    `snmp_community` VARCHAR(100) DEFAULT 'public',
    `snmp_version` ENUM('v1', 'v2c', 'v3') DEFAULT 'v2c',
    `connection_type` ENUM('telnet', 'ssh', 'snmp', 'api') DEFAULT 'telnet',
    `location` VARCHAR(255) DEFAULT NULL,
    `coordinates` VARCHAR(100) DEFAULT NULL,
    `total_pon_ports` INT(11) DEFAULT 8,
    `total_uplink_ports` INT(11) DEFAULT 2,
    `firmware_version` VARCHAR(100) DEFAULT NULL,
    `serial_number` VARCHAR(100) DEFAULT NULL,
    `last_connected` DATETIME DEFAULT NULL,
    `connection_status` ENUM('connected', 'disconnected', 'error', 'unknown') DEFAULT 'unknown',
    `last_sync` DATETIME DEFAULT NULL,
    `is_active` TINYINT(1) DEFAULT 1,
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_tenant_id` (`tenant_id`),
    KEY `idx_device_model` (`device_model`),
    CONSTRAINT `fk_olt_devices_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- OLT PON Ports
DROP TABLE IF EXISTS `olt_pon_ports`;
CREATE TABLE `olt_pon_ports` (
    `id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
    `olt_id` INT(11) UNSIGNED NOT NULL,
    `port_number` INT(11) NOT NULL,
    `port_type` ENUM('epon', 'gpon', 'xgpon', 'xgspon') DEFAULT 'epon',
    `port_name` VARCHAR(100) DEFAULT NULL,
    `port_description` TEXT DEFAULT NULL,
    `admin_status` ENUM('up', 'down') DEFAULT 'up',
    `oper_status` ENUM('up', 'down', 'unknown') DEFAULT 'unknown',
    `tx_power` DECIMAL(6, 2) DEFAULT NULL COMMENT 'dBm',
    `max_onus` INT(11) DEFAULT 64,
    `active_onus` INT(11) DEFAULT 0,
    `last_updated` DATETIME DEFAULT NULL,
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `idx_olt_port` (`olt_id`, `port_number`),
    CONSTRAINT `fk_pon_ports_olt` FOREIGN KEY (`olt_id`) REFERENCES `olt_devices` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- OLT ONU Devices
DROP TABLE IF EXISTS `olt_onu_devices`;
CREATE TABLE `olt_onu_devices` (
    `id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
    `olt_id` INT(11) UNSIGNED NOT NULL,
    `pon_port_id` INT(11) UNSIGNED NOT NULL,
    `tenant_id` INT(11) UNSIGNED NOT NULL,
    `customer_id` INT(11) UNSIGNED DEFAULT NULL,
    `onu_index` INT(11) NOT NULL COMMENT 'ONU number on PON port',
    `mac_address` VARCHAR(17) NOT NULL,
    `serial_number` VARCHAR(50) DEFAULT NULL,
    `onu_type` VARCHAR(50) DEFAULT NULL COMMENT 'Model of ONU',
    `onu_name` VARCHAR(255) DEFAULT NULL,
    `description` TEXT DEFAULT NULL,
    `admin_status` ENUM('up', 'down') DEFAULT 'up',
    `oper_status` ENUM('online', 'offline', 'powersaving', 'unknown') DEFAULT 'unknown',
    `auth_status` ENUM('authorized', 'unauthorized', 'pending') DEFAULT 'pending',
    `rx_power` DECIMAL(6, 2) DEFAULT NULL COMMENT 'dBm - ONU receive power',
    `tx_power` DECIMAL(6, 2) DEFAULT NULL COMMENT 'dBm - ONU transmit power',
    `olt_rx_power` DECIMAL(6, 2) DEFAULT NULL COMMENT 'dBm - OLT receive power from this ONU',
    `distance` INT(11) DEFAULT NULL COMMENT 'meters',
    `temperature` DECIMAL(5, 2) DEFAULT NULL COMMENT 'Celsius',
    `voltage` DECIMAL(6, 3) DEFAULT NULL COMMENT 'Volts',
    `bias_current` DECIMAL(6, 2) DEFAULT NULL COMMENT 'mA',
    `uptime` BIGINT(20) DEFAULT NULL COMMENT 'seconds',
    `last_online` DATETIME DEFAULT NULL,
    `last_offline` DATETIME DEFAULT NULL,
    `firmware_version` VARCHAR(100) DEFAULT NULL,
    `hardware_version` VARCHAR(100) DEFAULT NULL,
    `pppoe_username` VARCHAR(128) DEFAULT NULL,
    `pppoe_password` VARCHAR(128) DEFAULT NULL,
    `wan_mode` ENUM('bridge', 'route', 'pppoe') DEFAULT 'pppoe',
    `wan_vlan` INT(11) DEFAULT NULL,
    `wan_cos` INT(11) DEFAULT NULL,
    `ip_address` VARCHAR(45) DEFAULT NULL,
    `wifi_ssid` VARCHAR(64) DEFAULT NULL,
    `wifi_password` VARCHAR(128) DEFAULT NULL,
    `wifi_enabled` TINYINT(1) DEFAULT 1,
    `wifi_channel` INT(11) DEFAULT NULL,
    `wifi_band` ENUM('2.4ghz', '5ghz', 'dual') DEFAULT '2.4ghz',
    `lan_ports_status` JSON DEFAULT NULL,
    `config_json` JSON DEFAULT NULL,
    `last_sync` DATETIME DEFAULT NULL,
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `idx_olt_pon_onu` (`olt_id`, `pon_port_id`, `onu_index`),
    UNIQUE KEY `idx_mac_address` (`mac_address`),
    KEY `idx_tenant_id` (`tenant_id`),
    KEY `idx_customer_id` (`customer_id`),
    KEY `idx_oper_status` (`oper_status`),
    CONSTRAINT `fk_onu_olt` FOREIGN KEY (`olt_id`) REFERENCES `olt_devices` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_onu_pon_port` FOREIGN KEY (`pon_port_id`) REFERENCES `olt_pon_ports` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_onu_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_onu_customer` FOREIGN KEY (`customer_id`) REFERENCES `customer_accounts` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- OLT Signal Logs (Historical signal data)
DROP TABLE IF EXISTS `olt_signal_logs`;
CREATE TABLE `olt_signal_logs` (
    `id` BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
    `onu_id` INT(11) UNSIGNED NOT NULL,
    `rx_power` DECIMAL(6, 2) DEFAULT NULL,
    `tx_power` DECIMAL(6, 2) DEFAULT NULL,
    `olt_rx_power` DECIMAL(6, 2) DEFAULT NULL,
    `temperature` DECIMAL(5, 2) DEFAULT NULL,
    `voltage` DECIMAL(6, 3) DEFAULT NULL,
    `oper_status` ENUM('online', 'offline', 'powersaving') DEFAULT NULL,
    `recorded_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_onu_id` (`onu_id`),
    KEY `idx_recorded_at` (`recorded_at`),
    CONSTRAINT `fk_signal_logs_onu` FOREIGN KEY (`onu_id`) REFERENCES `olt_onu_devices` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- OLT Command Logs
DROP TABLE IF EXISTS `olt_command_logs`;
CREATE TABLE `olt_command_logs` (
    `id` BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
    `olt_id` INT(11) UNSIGNED NOT NULL,
    `onu_id` INT(11) UNSIGNED DEFAULT NULL,
    `operator_id` INT(11) UNSIGNED DEFAULT NULL,
    `command_type` VARCHAR(50) NOT NULL,
    `command_sent` TEXT NOT NULL,
    `command_response` TEXT DEFAULT NULL,
    `status` ENUM('success', 'failed', 'timeout') DEFAULT 'success',
    `error_message` TEXT DEFAULT NULL,
    `executed_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_olt_id` (`olt_id`),
    KEY `idx_onu_id` (`onu_id`),
    KEY `idx_executed_at` (`executed_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =========================================================================================================
-- PART 7: VPN SERVER MANAGEMENT
-- =========================================================================================================

-- VPN Servers
DROP TABLE IF EXISTS `vpn_servers`;
CREATE TABLE `vpn_servers` (
    `id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
    `server_name` VARCHAR(255) NOT NULL,
    `server_type` ENUM('wireguard', 'openvpn', 'l2tp', 'pptp', 'ipsec') DEFAULT 'wireguard',
    `public_ip` VARCHAR(45) NOT NULL,
    `public_port` INT(11) NOT NULL,
    `private_key` TEXT DEFAULT NULL COMMENT 'Encrypted',
    `public_key` VARCHAR(255) DEFAULT NULL,
    `interface_name` VARCHAR(50) DEFAULT 'wg0',
    `network_address` VARCHAR(50) DEFAULT '10.100.0.0/24',
    `dns_servers` VARCHAR(255) DEFAULT '1.1.1.1,8.8.8.8',
    `mtu` INT(11) DEFAULT 1420,
    `max_clients` INT(11) DEFAULT 250,
    `active_clients` INT(11) DEFAULT 0,
    `is_active` TINYINT(1) DEFAULT 1,
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tenant VPN Configurations
DROP TABLE IF EXISTS `tenant_vpn_configs`;
CREATE TABLE `tenant_vpn_configs` (
    `id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
    `tenant_id` INT(11) UNSIGNED NOT NULL,
    `vpn_server_id` INT(11) UNSIGNED NOT NULL,
    `client_ip` VARCHAR(45) NOT NULL,
    `private_key` TEXT DEFAULT NULL COMMENT 'Encrypted',
    `public_key` VARCHAR(255) DEFAULT NULL,
    `preshared_key` VARCHAR(255) DEFAULT NULL,
    `allowed_ips` VARCHAR(500) DEFAULT '0.0.0.0/0',
    `persistent_keepalive` INT(11) DEFAULT 25,
    `config_file` TEXT DEFAULT NULL,
    `is_active` TINYINT(1) DEFAULT 1,
    `last_handshake` DATETIME DEFAULT NULL,
    `bytes_received` BIGINT(20) DEFAULT 0,
    `bytes_sent` BIGINT(20) DEFAULT 0,
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `idx_tenant_server` (`tenant_id`, `vpn_server_id`),
    KEY `idx_vpn_server` (`vpn_server_id`),
    CONSTRAINT `fk_vpn_configs_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_vpn_configs_server` FOREIGN KEY (`vpn_server_id`) REFERENCES `vpn_servers` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =========================================================================================================
-- PART 8: NOTIFICATION & MESSAGING
-- =========================================================================================================

-- Notification Templates
DROP TABLE IF EXISTS `notification_templates`;
CREATE TABLE `notification_templates` (
    `id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
    `tenant_id` INT(11) UNSIGNED DEFAULT NULL COMMENT 'NULL for system templates',
    `template_code` VARCHAR(50) NOT NULL,
    `template_name` VARCHAR(255) NOT NULL,
    `template_type` ENUM('email', 'whatsapp', 'sms', 'push') NOT NULL,
    `subject` VARCHAR(500) DEFAULT NULL,
    `body` TEXT NOT NULL,
    `variables_json` JSON DEFAULT NULL COMMENT 'Available variables',
    `is_active` TINYINT(1) DEFAULT 1,
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `idx_tenant_template` (`tenant_id`, `template_code`),
    KEY `idx_template_type` (`template_type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Default notification templates
INSERT INTO `notification_templates` (`template_code`, `template_name`, `template_type`, `subject`, `body`, `variables_json`) VALUES
('invoice_created', 'Invoice Dibuat', 'whatsapp', NULL, 'Halo {{customer_name}},\n\nTagihan internet Anda untuk periode {{billing_period}} telah dibuat.\n\nNo. Invoice: {{invoice_number}}\nTotal: Rp {{total_amount}}\nJatuh Tempo: {{due_date}}\n\nSilakan lakukan pembayaran sebelum jatuh tempo.\n\nTerima kasih,\n{{company_name}}', '["customer_name", "billing_period", "invoice_number", "total_amount", "due_date", "company_name"]'),
('payment_reminder', 'Pengingat Pembayaran', 'whatsapp', NULL, 'Halo {{customer_name}},\n\nIni adalah pengingat bahwa tagihan internet Anda akan jatuh tempo pada {{due_date}}.\n\nNo. Invoice: {{invoice_number}}\nTotal: Rp {{total_amount}}\n\nHindari pemutusan layanan dengan melakukan pembayaran segera.\n\nTerima kasih,\n{{company_name}}', '["customer_name", "invoice_number", "total_amount", "due_date", "company_name"]'),
('payment_confirmed', 'Pembayaran Dikonfirmasi', 'whatsapp', NULL, 'Halo {{customer_name}},\n\nPembayaran Anda telah kami terima.\n\nNo. Invoice: {{invoice_number}}\nJumlah: Rp {{amount}}\nMetode: {{payment_method}}\n\nTerima kasih telah menjadi pelanggan setia kami.\n\n{{company_name}}', '["customer_name", "invoice_number", "amount", "payment_method", "company_name"]'),
('service_isolated', 'Layanan Diisolir', 'whatsapp', NULL, 'Halo {{customer_name}},\n\nMohon maaf, layanan internet Anda telah diisolir karena tagihan yang belum dibayar.\n\nNo. Invoice: {{invoice_number}}\nTotal Tunggakan: Rp {{total_amount}}\n\nSilakan segera lakukan pembayaran untuk mengaktifkan kembali layanan Anda.\n\n{{company_name}}', '["customer_name", "invoice_number", "total_amount", "company_name"]');

-- Notification Logs
DROP TABLE IF EXISTS `notification_logs`;
CREATE TABLE `notification_logs` (
    `id` BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
    `tenant_id` INT(11) UNSIGNED DEFAULT NULL,
    `template_id` INT(11) UNSIGNED DEFAULT NULL,
    `notification_type` ENUM('email', 'whatsapp', 'sms', 'push') NOT NULL,
    `recipient` VARCHAR(255) NOT NULL,
    `subject` VARCHAR(500) DEFAULT NULL,
    `body` TEXT NOT NULL,
    `status` ENUM('pending', 'sent', 'delivered', 'failed', 'read') DEFAULT 'pending',
    `gateway_response` TEXT DEFAULT NULL,
    `error_message` TEXT DEFAULT NULL,
    `sent_at` DATETIME DEFAULT NULL,
    `delivered_at` DATETIME DEFAULT NULL,
    `read_at` DATETIME DEFAULT NULL,
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_tenant_id` (`tenant_id`),
    KEY `idx_notification_type` (`notification_type`),
    KEY `idx_status` (`status`),
    KEY `idx_created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =========================================================================================================
-- PART 9: ACTIVITY LOGS & AUDIT TRAIL
-- =========================================================================================================

-- Activity Logs
DROP TABLE IF EXISTS `activity_logs`;
CREATE TABLE `activity_logs` (
    `id` BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
    `tenant_id` INT(11) UNSIGNED DEFAULT NULL,
    `operator_id` INT(11) UNSIGNED DEFAULT NULL,
    `operator_type` ENUM('super_admin', 'tenant_operator') DEFAULT 'tenant_operator',
    `action` VARCHAR(100) NOT NULL,
    `entity_type` VARCHAR(100) DEFAULT NULL,
    `entity_id` INT(11) UNSIGNED DEFAULT NULL,
    `old_values` JSON DEFAULT NULL,
    `new_values` JSON DEFAULT NULL,
    `ip_address` VARCHAR(45) DEFAULT NULL,
    `user_agent` VARCHAR(500) DEFAULT NULL,
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_tenant_id` (`tenant_id`),
    KEY `idx_operator_id` (`operator_id`),
    KEY `idx_action` (`action`),
    KEY `idx_entity` (`entity_type`, `entity_id`),
    KEY `idx_created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =========================================================================================================
-- PART 10: SYSTEM SETTINGS
-- =========================================================================================================

-- System Settings
DROP TABLE IF EXISTS `system_settings`;
CREATE TABLE `system_settings` (
    `id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
    `setting_key` VARCHAR(100) NOT NULL UNIQUE,
    `setting_value` TEXT DEFAULT NULL,
    `setting_type` ENUM('string', 'number', 'boolean', 'json', 'encrypted') DEFAULT 'string',
    `setting_group` VARCHAR(50) DEFAULT 'general',
    `description` VARCHAR(500) DEFAULT NULL,
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_setting_group` (`setting_group`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Default system settings
INSERT INTO `system_settings` (`setting_key`, `setting_value`, `setting_type`, `setting_group`, `description`) VALUES
('platform_name', 'ISP Manager Pro', 'string', 'general', 'Platform name'),
('platform_logo', '/static/images/logo.png', 'string', 'general', 'Platform logo path'),
('platform_email', 'support@ispmanager.id', 'string', 'general', 'Platform support email'),
('default_currency', 'IDR', 'string', 'billing', 'Default currency'),
('tax_rate', '11', 'number', 'billing', 'Default tax rate (PPN)'),
('invoice_prefix', 'INV', 'string', 'billing', 'Invoice number prefix'),
('invoice_due_days', '7', 'number', 'billing', 'Default days until invoice due'),
('isolation_grace_days', '3', 'number', 'billing', 'Days after due date before isolation'),
('midtrans_server_key', '', 'encrypted', 'payment', 'Midtrans Server Key'),
('midtrans_client_key', '', 'encrypted', 'payment', 'Midtrans Client Key'),
('midtrans_is_production', '0', 'boolean', 'payment', 'Midtrans Production Mode'),
('xendit_secret_key', '', 'encrypted', 'payment', 'Xendit Secret API Key'),
('xendit_public_key', '', 'encrypted', 'payment', 'Xendit Public API Key'),
('xendit_callback_token', '', 'encrypted', 'payment', 'Xendit Callback Verification Token'),
('moota_api_key', '', 'encrypted', 'payment', 'Moota API Key'),
('wa_gateway_url', '', 'string', 'notification', 'WhatsApp Gateway URL'),
('wa_gateway_token', '', 'encrypted', 'notification', 'WhatsApp Gateway Token'),
('smtp_host', '', 'string', 'email', 'SMTP Host'),
('smtp_port', '587', 'number', 'email', 'SMTP Port'),
('smtp_username', '', 'string', 'email', 'SMTP Username'),
('smtp_password', '', 'encrypted', 'email', 'SMTP Password'),
('smtp_encryption', 'tls', 'string', 'email', 'SMTP Encryption (tls/ssl)');

-- Re-enable FK checks
SET FOREIGN_KEY_CHECKS = 1;

-- =========================================================================================================
-- VIEWS FOR EASIER QUERYING
-- =========================================================================================================

-- Active Subscriptions View
CREATE OR REPLACE VIEW `vw_active_subscriptions` AS
SELECT
    t.id AS tenant_id,
    t.tenant_code,
    t.company_name,
    t.status AS tenant_status,
    ts.id AS subscription_id,
    asp.plan_name,
    asp.price,
    ts.starts_at,
    ts.ends_at,
    ts.status AS subscription_status,
    DATEDIFF(ts.ends_at, NOW()) AS days_remaining
FROM tenants t
LEFT JOIN tenant_subscriptions ts ON t.id = ts.tenant_id AND ts.status = 'active'
LEFT JOIN app_subscription_plans asp ON ts.plan_id = asp.id;

-- Customer Billing Summary View
CREATE OR REPLACE VIEW `vw_customer_billing_summary` AS
SELECT
    ca.tenant_id,
    ca.id AS customer_id,
    ca.customer_code,
    ca.fullname,
    ca.username,
    ca.status AS customer_status,
    cip.plan_name,
    cip.price AS monthly_fee,
    ci.invoice_number AS last_invoice,
    ci.total_amount AS last_invoice_amount,
    ci.status AS last_invoice_status,
    ci.due_date AS last_invoice_due
FROM customer_accounts ca
LEFT JOIN customer_internet_plans cip ON ca.plan_id = cip.id
LEFT JOIN customer_invoices ci ON ca.id = ci.customer_id
    AND ci.id = (SELECT MAX(id) FROM customer_invoices WHERE customer_id = ca.id);

-- ONU Signal Status View
CREATE OR REPLACE VIEW `vw_onu_signal_status` AS
SELECT
    onu.id AS onu_id,
    onu.tenant_id,
    od.device_name AS olt_name,
    pp.port_number AS pon_port,
    onu.onu_index,
    onu.mac_address,
    onu.onu_name,
    ca.fullname AS customer_name,
    ca.customer_code,
    onu.oper_status,
    onu.rx_power,
    onu.tx_power,
    onu.olt_rx_power,
    onu.distance,
    onu.last_online,
    CASE
        WHEN onu.olt_rx_power >= -25 THEN 'excellent'
        WHEN onu.olt_rx_power >= -27 THEN 'good'
        WHEN onu.olt_rx_power >= -29 THEN 'fair'
        WHEN onu.olt_rx_power >= -31 THEN 'poor'
        ELSE 'critical'
    END AS signal_quality
FROM olt_onu_devices onu
JOIN olt_devices od ON onu.olt_id = od.id
JOIN olt_pon_ports pp ON onu.pon_port_id = pp.id
LEFT JOIN customer_accounts ca ON onu.customer_id = ca.id;

-- =========================================================================================================
-- STORED PROCEDURES
-- =========================================================================================================

DELIMITER //

-- Generate Invoice Number
CREATE PROCEDURE IF NOT EXISTS `sp_generate_invoice_number`(
    IN p_tenant_id INT,
    IN p_prefix VARCHAR(10),
    OUT p_invoice_number VARCHAR(50)
)
BEGIN
    DECLARE v_year VARCHAR(4);
    DECLARE v_month VARCHAR(2);
    DECLARE v_sequence INT;

    SET v_year = YEAR(CURDATE());
    SET v_month = LPAD(MONTH(CURDATE()), 2, '0');

    SELECT COALESCE(MAX(
        CAST(SUBSTRING(invoice_number, -4) AS UNSIGNED)
    ), 0) + 1 INTO v_sequence
    FROM customer_invoices
    WHERE tenant_id = p_tenant_id
    AND invoice_number LIKE CONCAT(p_prefix, '/', v_year, '/', v_month, '/%');

    SET p_invoice_number = CONCAT(p_prefix, '/', v_year, '/', v_month, '/', LPAD(v_sequence, 4, '0'));
END //

-- Generate Monthly Invoices for a Tenant
CREATE PROCEDURE IF NOT EXISTS `sp_generate_monthly_invoices`(
    IN p_tenant_id INT,
    IN p_billing_month DATE
)
BEGIN
    DECLARE v_invoice_number VARCHAR(50);
    DECLARE v_customer_id INT;
    DECLARE v_plan_id INT;
    DECLARE v_price DECIMAL(15,2);
    DECLARE v_billing_start DATE;
    DECLARE v_billing_end DATE;
    DECLARE v_due_date DATE;
    DECLARE done INT DEFAULT FALSE;

    DECLARE cur_customers CURSOR FOR
        SELECT ca.id, ca.plan_id, cip.price
        FROM customer_accounts ca
        JOIN customer_internet_plans cip ON ca.plan_id = cip.id
        WHERE ca.tenant_id = p_tenant_id
        AND ca.status IN ('active', 'suspended')
        AND cip.billing_cycle = 'postpaid';

    DECLARE CONTINUE HANDLER FOR NOT FOUND SET done = TRUE;

    SET v_billing_start = DATE_FORMAT(p_billing_month, '%Y-%m-01');
    SET v_billing_end = LAST_DAY(p_billing_month);
    SET v_due_date = DATE_ADD(v_billing_start, INTERVAL 7 DAY);

    OPEN cur_customers;

    customer_loop: LOOP
        FETCH cur_customers INTO v_customer_id, v_plan_id, v_price;
        IF done THEN
            LEAVE customer_loop;
        END IF;

        -- Check if invoice already exists for this period
        IF NOT EXISTS (
            SELECT 1 FROM customer_invoices
            WHERE tenant_id = p_tenant_id
            AND customer_id = v_customer_id
            AND billing_period_start = v_billing_start
        ) THEN
            CALL sp_generate_invoice_number(p_tenant_id, 'INV', v_invoice_number);

            INSERT INTO customer_invoices (
                tenant_id, invoice_number, customer_id,
                billing_period_start, billing_period_end,
                description, subtotal, total_amount, due_date, status
            ) VALUES (
                p_tenant_id, v_invoice_number, v_customer_id,
                v_billing_start, v_billing_end,
                CONCAT('Tagihan Internet ', DATE_FORMAT(p_billing_month, '%M %Y')),
                v_price, v_price, v_due_date, 'pending'
            );
        END IF;
    END LOOP;

    CLOSE cur_customers;
END //

-- Isolate Overdue Customers
CREATE PROCEDURE IF NOT EXISTS `sp_isolate_overdue_customers`(
    IN p_tenant_id INT,
    IN p_grace_days INT
)
BEGIN
    UPDATE customer_accounts ca
    JOIN customer_invoices ci ON ca.id = ci.customer_id
    SET
        ca.status = 'isolated',
        ca.isolation_reason = CONCAT('Tagihan ', ci.invoice_number, ' belum dibayar'),
        ca.updated_at = NOW()
    WHERE ca.tenant_id = p_tenant_id
    AND ca.status = 'active'
    AND ci.status IN ('pending', 'overdue')
    AND ci.due_date < DATE_SUB(CURDATE(), INTERVAL p_grace_days DAY);
END //

DELIMITER ;

-- =========================================================================================================
-- INDEXES FOR PERFORMANCE
-- =========================================================================================================

-- Add composite indexes for common queries
CREATE INDEX IF NOT EXISTS `idx_radcheck_tenant` ON `radcheck` (`username`(50));
CREATE INDEX IF NOT EXISTS `idx_radacct_tenant` ON `radacct` (`username`(50), `acctstarttime`);
