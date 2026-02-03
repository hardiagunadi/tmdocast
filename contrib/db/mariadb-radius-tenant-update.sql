-- =========================================================================================================
-- daloRADIUS Multi-Tenant Update Script
-- Adds tenant_id column to existing RADIUS tables for data isolation
-- Version: 2.0.0
-- =========================================================================================================

SET FOREIGN_KEY_CHECKS = 0;

-- =========================================================================================================
-- UPDATE EXISTING TABLES WITH TENANT_ID
-- =========================================================================================================

-- Update radcheck table
ALTER TABLE `radcheck`
ADD COLUMN IF NOT EXISTS `tenant_id` INT(11) UNSIGNED DEFAULT NULL AFTER `id`;
CREATE INDEX IF NOT EXISTS `idx_radcheck_tenant` ON `radcheck` (`tenant_id`);

-- Update radreply table
ALTER TABLE `radreply`
ADD COLUMN IF NOT EXISTS `tenant_id` INT(11) UNSIGNED DEFAULT NULL AFTER `id`;
CREATE INDEX IF NOT EXISTS `idx_radreply_tenant` ON `radreply` (`tenant_id`);

-- Update radgroupcheck table
ALTER TABLE `radgroupcheck`
ADD COLUMN IF NOT EXISTS `tenant_id` INT(11) UNSIGNED DEFAULT NULL AFTER `id`;
CREATE INDEX IF NOT EXISTS `idx_radgroupcheck_tenant` ON `radgroupcheck` (`tenant_id`);

-- Update radgroupreply table
ALTER TABLE `radgroupreply`
ADD COLUMN IF NOT EXISTS `tenant_id` INT(11) UNSIGNED DEFAULT NULL AFTER `id`;
CREATE INDEX IF NOT EXISTS `idx_radgroupreply_tenant` ON `radgroupreply` (`tenant_id`);

-- Update radusergroup table
ALTER TABLE `radusergroup`
ADD COLUMN IF NOT EXISTS `tenant_id` INT(11) UNSIGNED DEFAULT NULL AFTER `id`;
CREATE INDEX IF NOT EXISTS `idx_radusergroup_tenant` ON `radusergroup` (`tenant_id`);

-- Update radacct table
ALTER TABLE `radacct`
ADD COLUMN IF NOT EXISTS `tenant_id` INT(11) UNSIGNED DEFAULT NULL AFTER `radacctid`;
CREATE INDEX IF NOT EXISTS `idx_radacct_tenant` ON `radacct` (`tenant_id`);
CREATE INDEX IF NOT EXISTS `idx_radacct_tenant_user` ON `radacct` (`tenant_id`, `username`(50));

-- Update radpostauth table
ALTER TABLE `radpostauth`
ADD COLUMN IF NOT EXISTS `tenant_id` INT(11) UNSIGNED DEFAULT NULL AFTER `id`;
CREATE INDEX IF NOT EXISTS `idx_radpostauth_tenant` ON `radpostauth` (`tenant_id`);

-- Update nas table
ALTER TABLE `nas`
ADD COLUMN IF NOT EXISTS `tenant_id` INT(11) UNSIGNED DEFAULT NULL AFTER `id`;
CREATE INDEX IF NOT EXISTS `idx_nas_tenant` ON `nas` (`tenant_id`);

-- Update userinfo table
ALTER TABLE `userinfo`
ADD COLUMN IF NOT EXISTS `tenant_id` INT(11) UNSIGNED DEFAULT NULL AFTER `id`;
CREATE INDEX IF NOT EXISTS `idx_userinfo_tenant` ON `userinfo` (`tenant_id`);

-- Update userbillinfo table
ALTER TABLE `userbillinfo`
ADD COLUMN IF NOT EXISTS `tenant_id` INT(11) UNSIGNED DEFAULT NULL AFTER `id`;
CREATE INDEX IF NOT EXISTS `idx_userbillinfo_tenant` ON `userbillinfo` (`tenant_id`);

-- Update billing_plans table
ALTER TABLE `billing_plans`
ADD COLUMN IF NOT EXISTS `tenant_id` INT(11) UNSIGNED DEFAULT NULL AFTER `id`;
CREATE INDEX IF NOT EXISTS `idx_billing_plans_tenant` ON `billing_plans` (`tenant_id`);

-- Update billing_rates table
ALTER TABLE `billing_rates`
ADD COLUMN IF NOT EXISTS `tenant_id` INT(11) UNSIGNED DEFAULT NULL AFTER `id`;
CREATE INDEX IF NOT EXISTS `idx_billing_rates_tenant` ON `billing_rates` (`tenant_id`);

-- Update billing_history table
ALTER TABLE `billing_history`
ADD COLUMN IF NOT EXISTS `tenant_id` INT(11) UNSIGNED DEFAULT NULL AFTER `id`;
CREATE INDEX IF NOT EXISTS `idx_billing_history_tenant` ON `billing_history` (`tenant_id`);

-- Update hotspots table
ALTER TABLE `hotspots`
ADD COLUMN IF NOT EXISTS `tenant_id` INT(11) UNSIGNED DEFAULT NULL AFTER `id`;
CREATE INDEX IF NOT EXISTS `idx_hotspots_tenant` ON `hotspots` (`tenant_id`);

-- Update invoice table
ALTER TABLE `invoice`
ADD COLUMN IF NOT EXISTS `tenant_id` INT(11) UNSIGNED DEFAULT NULL AFTER `id`;
CREATE INDEX IF NOT EXISTS `idx_invoice_tenant` ON `invoice` (`tenant_id`);

-- Update payment table
ALTER TABLE `payment`
ADD COLUMN IF NOT EXISTS `tenant_id` INT(11) UNSIGNED DEFAULT NULL AFTER `id`;
CREATE INDEX IF NOT EXISTS `idx_payment_tenant` ON `payment` (`tenant_id`);

-- Update mikrotik_nas table
ALTER TABLE `mikrotik_nas`
ADD COLUMN IF NOT EXISTS `tenant_id` INT(11) UNSIGNED DEFAULT NULL AFTER `id`;
CREATE INDEX IF NOT EXISTS `idx_mikrotik_nas_tenant` ON `mikrotik_nas` (`tenant_id`);

-- Update user_services table
ALTER TABLE `user_services`
ADD COLUMN IF NOT EXISTS `tenant_id` INT(11) UNSIGNED DEFAULT NULL AFTER `id`;
CREATE INDEX IF NOT EXISTS `idx_user_services_tenant` ON `user_services` (`tenant_id`);

-- Update batch_history table
ALTER TABLE `batch_history`
ADD COLUMN IF NOT EXISTS `tenant_id` INT(11) UNSIGNED DEFAULT NULL AFTER `id`;
CREATE INDEX IF NOT EXISTS `idx_batch_history_tenant` ON `batch_history` (`tenant_id`);

-- Update realms table
ALTER TABLE `realms`
ADD COLUMN IF NOT EXISTS `tenant_id` INT(11) UNSIGNED DEFAULT NULL AFTER `id`;
CREATE INDEX IF NOT EXISTS `idx_realms_tenant` ON `realms` (`tenant_id`);

-- Update proxys table
ALTER TABLE `proxys`
ADD COLUMN IF NOT EXISTS `tenant_id` INT(11) UNSIGNED DEFAULT NULL AFTER `id`;
CREATE INDEX IF NOT EXISTS `idx_proxys_tenant` ON `proxys` (`tenant_id`);

-- Update radippool table
ALTER TABLE `radippool`
ADD COLUMN IF NOT EXISTS `tenant_id` INT(11) UNSIGNED DEFAULT NULL AFTER `id`;
CREATE INDEX IF NOT EXISTS `idx_radippool_tenant` ON `radippool` (`tenant_id`);

-- Update radhuntgroup table
ALTER TABLE `radhuntgroup`
ADD COLUMN IF NOT EXISTS `tenant_id` INT(11) UNSIGNED DEFAULT NULL AFTER `id`;
CREATE INDEX IF NOT EXISTS `idx_radhuntgroup_tenant` ON `radhuntgroup` (`tenant_id`);

SET FOREIGN_KEY_CHECKS = 1;

-- =========================================================================================================
-- VIEWS FOR TENANT-FILTERED DATA ACCESS
-- =========================================================================================================

-- Note: Views are created without tenant filtering - filtering is done in application layer
-- These views provide convenient joins for common queries

-- User Complete Info View
CREATE OR REPLACE VIEW `vw_user_complete` AS
SELECT
    rc.id,
    rc.tenant_id,
    rc.username,
    rc.attribute,
    rc.op,
    rc.value AS password,
    ui.firstname,
    ui.lastname,
    ui.email,
    ui.department,
    ui.company,
    ui.workphone,
    ui.homephone,
    ui.mobilephone,
    ui.address,
    ui.city,
    ui.state,
    ui.country,
    ui.zip,
    ui.notes,
    ui.enableportallogin,
    ubi.planName,
    ubi.contactperson,
    ubi.paymentmethod,
    ubi.cash,
    ubi.creationdate,
    ubi.updatedate
FROM radcheck rc
LEFT JOIN userinfo ui ON rc.username = ui.username
LEFT JOIN userbillinfo ubi ON rc.username = ubi.username
WHERE rc.attribute = 'Cleartext-Password' OR rc.attribute = 'User-Password';

-- Active Sessions View with Tenant
CREATE OR REPLACE VIEW `vw_active_sessions` AS
SELECT
    ra.radacctid,
    ra.tenant_id,
    ra.username,
    ra.nasipaddress,
    ra.nasportid,
    ra.acctstarttime,
    ra.acctupdatetime,
    ra.acctinputoctets,
    ra.acctoutputoctets,
    ra.framedipaddress,
    ra.callingstationid,
    TIMESTAMPDIFF(SECOND, ra.acctstarttime, NOW()) AS session_duration
FROM radacct ra
WHERE ra.acctstoptime IS NULL;

-- =========================================================================================================
-- STORED PROCEDURES FOR TENANT OPERATIONS
-- =========================================================================================================

DELIMITER //

-- Get next available username for tenant
CREATE PROCEDURE IF NOT EXISTS `sp_get_next_username`(
    IN p_tenant_id INT,
    IN p_prefix VARCHAR(50),
    OUT p_username VARCHAR(128)
)
BEGIN
    DECLARE v_max_num INT DEFAULT 0;

    SELECT COALESCE(MAX(CAST(SUBSTRING(username, LENGTH(p_prefix) + 1) AS UNSIGNED)), 0) + 1
    INTO v_max_num
    FROM radcheck
    WHERE tenant_id = p_tenant_id
    AND username LIKE CONCAT(p_prefix, '%')
    AND username REGEXP CONCAT('^', p_prefix, '[0-9]+$');

    SET p_username = CONCAT(p_prefix, LPAD(v_max_num, 5, '0'));
END //

-- Bulk update tenant_id for existing data (migration helper)
CREATE PROCEDURE IF NOT EXISTS `sp_migrate_data_to_tenant`(
    IN p_tenant_id INT,
    IN p_nas_ip VARCHAR(45)
)
BEGIN
    -- Update radcheck based on NAS IP from radacct
    UPDATE radcheck rc
    JOIN (
        SELECT DISTINCT username FROM radacct WHERE nasipaddress = p_nas_ip
    ) ra ON rc.username = ra.username
    SET rc.tenant_id = p_tenant_id
    WHERE rc.tenant_id IS NULL;

    -- Update related tables
    UPDATE radreply SET tenant_id = p_tenant_id
    WHERE username IN (SELECT username FROM radcheck WHERE tenant_id = p_tenant_id) AND tenant_id IS NULL;

    UPDATE radusergroup SET tenant_id = p_tenant_id
    WHERE username IN (SELECT username FROM radcheck WHERE tenant_id = p_tenant_id) AND tenant_id IS NULL;

    UPDATE userinfo SET tenant_id = p_tenant_id
    WHERE username IN (SELECT username FROM radcheck WHERE tenant_id = p_tenant_id) AND tenant_id IS NULL;

    UPDATE userbillinfo SET tenant_id = p_tenant_id
    WHERE username IN (SELECT username FROM radcheck WHERE tenant_id = p_tenant_id) AND tenant_id IS NULL;

    UPDATE radacct SET tenant_id = p_tenant_id
    WHERE nasipaddress = p_nas_ip AND tenant_id IS NULL;

    -- Update NAS
    UPDATE nas SET tenant_id = p_tenant_id WHERE nasname = p_nas_ip AND tenant_id IS NULL;
END //

-- Count tenant usage stats
CREATE PROCEDURE IF NOT EXISTS `sp_get_tenant_stats`(
    IN p_tenant_id INT
)
BEGIN
    SELECT
        (SELECT COUNT(DISTINCT username) FROM radcheck WHERE tenant_id = p_tenant_id) AS total_users,
        (SELECT COUNT(*) FROM radacct WHERE tenant_id = p_tenant_id AND acctstoptime IS NULL) AS active_sessions,
        (SELECT COUNT(*) FROM mikrotik_nas WHERE tenant_id = p_tenant_id AND is_active = 1) AS mikrotik_devices,
        (SELECT COUNT(*) FROM olt_devices WHERE tenant_id = p_tenant_id AND is_active = 1) AS olt_devices,
        (SELECT COUNT(*) FROM olt_onu_devices WHERE tenant_id = p_tenant_id) AS total_onus,
        (SELECT COUNT(*) FROM tenant_operators WHERE tenant_id = p_tenant_id AND is_active = 1) AS operators;
END //

DELIMITER ;

-- =========================================================================================================
-- TRIGGER FOR AUTO-SETTING TENANT_ID IN RADIUS TABLES
-- =========================================================================================================

-- Note: These triggers help maintain data consistency when data is inserted
-- through FreeRADIUS (which doesn't know about tenants)
-- The application should set @current_tenant_id before any insert operation

DELIMITER //

-- Trigger for radcheck
DROP TRIGGER IF EXISTS `trg_radcheck_tenant`//
CREATE TRIGGER `trg_radcheck_tenant` BEFORE INSERT ON `radcheck`
FOR EACH ROW
BEGIN
    IF NEW.tenant_id IS NULL AND @current_tenant_id IS NOT NULL THEN
        SET NEW.tenant_id = @current_tenant_id;
    END IF;
END//

-- Trigger for radreply
DROP TRIGGER IF EXISTS `trg_radreply_tenant`//
CREATE TRIGGER `trg_radreply_tenant` BEFORE INSERT ON `radreply`
FOR EACH ROW
BEGIN
    IF NEW.tenant_id IS NULL AND @current_tenant_id IS NOT NULL THEN
        SET NEW.tenant_id = @current_tenant_id;
    END IF;
END//

-- Trigger for radusergroup
DROP TRIGGER IF EXISTS `trg_radusergroup_tenant`//
CREATE TRIGGER `trg_radusergroup_tenant` BEFORE INSERT ON `radusergroup`
FOR EACH ROW
BEGIN
    IF NEW.tenant_id IS NULL AND @current_tenant_id IS NOT NULL THEN
        SET NEW.tenant_id = @current_tenant_id;
    END IF;
END//

-- Trigger for userinfo
DROP TRIGGER IF EXISTS `trg_userinfo_tenant`//
CREATE TRIGGER `trg_userinfo_tenant` BEFORE INSERT ON `userinfo`
FOR EACH ROW
BEGIN
    IF NEW.tenant_id IS NULL AND @current_tenant_id IS NOT NULL THEN
        SET NEW.tenant_id = @current_tenant_id;
    END IF;
END//

-- Trigger for userbillinfo
DROP TRIGGER IF EXISTS `trg_userbillinfo_tenant`//
CREATE TRIGGER `trg_userbillinfo_tenant` BEFORE INSERT ON `userbillinfo`
FOR EACH ROW
BEGIN
    IF NEW.tenant_id IS NULL AND @current_tenant_id IS NOT NULL THEN
        SET NEW.tenant_id = @current_tenant_id;
    END IF;
END//

DELIMITER ;

-- =========================================================================================================
-- DATA CLEANUP PROCEDURES
-- =========================================================================================================

DELIMITER //

-- Cleanup orphaned data (records without tenant)
CREATE PROCEDURE IF NOT EXISTS `sp_cleanup_orphan_data`()
BEGIN
    -- This procedure marks data without tenant_id for review
    -- It does NOT delete anything automatically

    CREATE TEMPORARY TABLE IF NOT EXISTS orphan_report (
        table_name VARCHAR(100),
        record_count INT
    );

    INSERT INTO orphan_report VALUES ('radcheck', (SELECT COUNT(*) FROM radcheck WHERE tenant_id IS NULL));
    INSERT INTO orphan_report VALUES ('radreply', (SELECT COUNT(*) FROM radreply WHERE tenant_id IS NULL));
    INSERT INTO orphan_report VALUES ('radusergroup', (SELECT COUNT(*) FROM radusergroup WHERE tenant_id IS NULL));
    INSERT INTO orphan_report VALUES ('userinfo', (SELECT COUNT(*) FROM userinfo WHERE tenant_id IS NULL));
    INSERT INTO orphan_report VALUES ('userbillinfo', (SELECT COUNT(*) FROM userbillinfo WHERE tenant_id IS NULL));
    INSERT INTO orphan_report VALUES ('radacct', (SELECT COUNT(*) FROM radacct WHERE tenant_id IS NULL));
    INSERT INTO orphan_report VALUES ('nas', (SELECT COUNT(*) FROM nas WHERE tenant_id IS NULL));
    INSERT INTO orphan_report VALUES ('mikrotik_nas', (SELECT COUNT(*) FROM mikrotik_nas WHERE tenant_id IS NULL));

    SELECT * FROM orphan_report WHERE record_count > 0;

    DROP TEMPORARY TABLE orphan_report;
END //

DELIMITER ;
