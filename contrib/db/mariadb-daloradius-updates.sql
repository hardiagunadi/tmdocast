-- daloRADIUS incremental updates for existing installations

SET @db = DATABASE();

-- Table: mikrotik_nas
CREATE TABLE IF NOT EXISTS `mikrotik_nas` (
  `id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(128) NOT NULL,
  `nasname` VARCHAR(128) DEFAULT NULL,
  `host` VARCHAR(255) NOT NULL,
  `port` INT(11) NOT NULL DEFAULT '8728',
  `api_username` VARCHAR(128) NOT NULL,
  `api_password` VARCHAR(128) NOT NULL,
  `radius_server` VARCHAR(255) DEFAULT NULL,
  `radius_auth_port` INT(11) NOT NULL DEFAULT '1812',
  `radius_acct_port` INT(11) NOT NULL DEFAULT '1813',
  `pppoe_pool_network` VARCHAR(32) NOT NULL DEFAULT '172.16.0.0',
  `pppoe_pool_cidr` INT(11) NOT NULL DEFAULT '20',
  `isolir_profile` VARCHAR(128) NOT NULL DEFAULT 'isolir',
  `redirect_url` VARCHAR(255) DEFAULT NULL,
  `is_active` TINYINT(1) NOT NULL DEFAULT '1',
  `creationdate` DATETIME DEFAULT '0000-00-00 00:00:00',
  `creationby` VARCHAR(128) DEFAULT NULL,
  `updatedate` DATETIME DEFAULT '0000-00-00 00:00:00',
  `updateby` VARCHAR(128) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `nasname` (`nasname`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Table: user_services
CREATE TABLE IF NOT EXISTS `user_services` (
  `id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
  `username` VARCHAR(128) NOT NULL,
  `service_type` ENUM('pppoe', 'hotspot') NOT NULL,
  `nas_id` INT(11) DEFAULT NULL,
  `plan_name` VARCHAR(128) DEFAULT NULL,
  `expiration_date` DATE DEFAULT NULL,
  `ip_address` VARCHAR(32) DEFAULT NULL,
  `is_active` TINYINT(1) NOT NULL DEFAULT '1',
  `creationdate` DATETIME DEFAULT '0000-00-00 00:00:00',
  `creationby` VARCHAR(128) DEFAULT NULL,
  `updatedate` DATETIME DEFAULT '0000-00-00 00:00:00',
  `updateby` VARCHAR(128) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `username` (`username`),
  KEY `nas_id` (`nas_id`),
  KEY `plan_name` (`plan_name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Table: wa_gateway_settings
CREATE TABLE IF NOT EXISTS `wa_gateway_settings` (
  `id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
  `is_enabled` TINYINT(1) NOT NULL DEFAULT '0',
  `base_url` VARCHAR(255) DEFAULT NULL,
  `api_key` VARCHAR(255) DEFAULT NULL,
  `session_name` VARCHAR(128) DEFAULT NULL,
  `due_days` INT(11) NOT NULL DEFAULT '30',
  `reminder_days_before` INT(11) NOT NULL DEFAULT '3',
  `message_template` TEXT DEFAULT NULL,
  `creationdate` DATETIME DEFAULT '0000-00-00 00:00:00',
  `creationby` VARCHAR(128) DEFAULT NULL,
  `updatedate` DATETIME DEFAULT '0000-00-00 00:00:00',
  `updateby` VARCHAR(128) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Table: wa_gateway_logs
CREATE TABLE IF NOT EXISTS `wa_gateway_logs` (
  `id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
  `invoice_id` INT(11) DEFAULT NULL,
  `phone` VARCHAR(64) DEFAULT NULL,
  `message_type` VARCHAR(64) DEFAULT NULL,
  `sent_at` DATETIME DEFAULT '0000-00-00 00:00:00',
  PRIMARY KEY (`id`),
  KEY `invoice_id` (`invoice_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Ensure new columns exist for mikrotik_nas
SET @tbl = 'mikrotik_nas';
SET @col = 'radius_server';
SELECT COUNT(*) INTO @c FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=@db AND TABLE_NAME=@tbl AND COLUMN_NAME=@col;
SET @s = IF(@c=0, "ALTER TABLE mikrotik_nas ADD COLUMN radius_server VARCHAR(255) DEFAULT NULL", "SELECT 1");
PREPARE stmt FROM @s; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col = 'radius_auth_port';
SELECT COUNT(*) INTO @c FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=@db AND TABLE_NAME=@tbl AND COLUMN_NAME=@col;
SET @s = IF(@c=0, "ALTER TABLE mikrotik_nas ADD COLUMN radius_auth_port INT(11) NOT NULL DEFAULT 1812", "ALTER TABLE mikrotik_nas MODIFY COLUMN radius_auth_port INT(11) NOT NULL DEFAULT 1812");
PREPARE stmt FROM @s; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col = 'radius_acct_port';
SELECT COUNT(*) INTO @c FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=@db AND TABLE_NAME=@tbl AND COLUMN_NAME=@col;
SET @s = IF(@c=0, "ALTER TABLE mikrotik_nas ADD COLUMN radius_acct_port INT(11) NOT NULL DEFAULT 1813", "ALTER TABLE mikrotik_nas MODIFY COLUMN radius_acct_port INT(11) NOT NULL DEFAULT 1813");
PREPARE stmt FROM @s; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col = 'pppoe_pool_network';
SELECT COUNT(*) INTO @c FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=@db AND TABLE_NAME=@tbl AND COLUMN_NAME=@col;
SET @s = IF(@c=0, "ALTER TABLE mikrotik_nas ADD COLUMN pppoe_pool_network VARCHAR(32) NOT NULL DEFAULT '172.16.0.0'", "SELECT 1");
PREPARE stmt FROM @s; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col = 'pppoe_pool_cidr';
SELECT COUNT(*) INTO @c FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=@db AND TABLE_NAME=@tbl AND COLUMN_NAME=@col;
SET @s = IF(@c=0, "ALTER TABLE mikrotik_nas ADD COLUMN pppoe_pool_cidr INT(11) NOT NULL DEFAULT 20", "SELECT 1");
PREPARE stmt FROM @s; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- Ensure new columns exist for user_services
SET @tbl = 'user_services';
SET @col = 'ip_address';
SELECT COUNT(*) INTO @c FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=@db AND TABLE_NAME=@tbl AND COLUMN_NAME=@col;
SET @s = IF(@c=0, "ALTER TABLE user_services ADD COLUMN ip_address VARCHAR(32) DEFAULT NULL", "SELECT 1");
PREPARE stmt FROM @s; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- Default WA gateway settings row
INSERT IGNORE INTO wa_gateway_settings
    (`id`, `is_enabled`, `base_url`, `api_key`, `session_name`, `due_days`, `reminder_days_before`, `message_template`,
     `creationdate`, `creationby`, `updatedate`, `updateby`)
VALUES
    (1, 0, '', '', '', 30, 3, 'Tagihan Anda akan jatuh tempo pada [InvoiceDue]. Total: [InvoiceTotalAmount]. Silakan melakukan pembayaran.',
     NOW(), 'system', NOW(), 'system');

-- ACL updates
INSERT IGNORE INTO operators_acl (`operator_id`, `file`, `access`) VALUES
    (1, 'pppoe_new', 1),
    (1, 'pppoe_list', 1),
    (1, 'hotspot_new', 1),
    (1, 'hotspot_list', 1),
    (1, 'config_mikrotik', 1),
    (1, 'config_wa_gateway', 1),
    (1, 'bill_invoice_pay', 1);

INSERT IGNORE INTO operators_acl_files (`file`, `category`, `section`) VALUES
    ('pppoe_new', 'Management', 'Users'),
    ('pppoe_list', 'Management', 'Users'),
    ('hotspot_new', 'Management', 'Users'),
    ('hotspot_list', 'Management', 'Users'),
    ('config_mikrotik', 'Configuration', 'NAS'),
    ('config_wa_gateway', 'Configuration', 'Integrations'),
    ('bill_invoice_pay', 'Billing', 'Invoice');

