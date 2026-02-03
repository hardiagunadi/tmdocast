<?php
/*
 *********************************************************************************************************
 * daloRADIUS - RADIUS Web Platform
 * Tenant Portal Logout
 *********************************************************************************************************
 */

require_once(dirname(__FILE__) . '/library/sessions.php');

dalo_portal_session_start();

// Log the logout
if (dalo_portal_is_logged_in()) {
    require_once(dirname(__FILE__) . '/../common/library/db_connect.php');

    $logSql = sprintf(
        "INSERT INTO activity_logs (tenant_id, user_type, user_id, action, entity_type, ip_address, created_at)
         VALUES (%d, 'operator', %d, 'logout', 'tenant_operator', '%s', NOW())",
        intval($_SESSION['portal_tenant_id']),
        intval($_SESSION['portal_operator_id']),
        $dbSocket->escapeSimple($_SERVER['REMOTE_ADDR'])
    );
    $dbSocket->query($logSql);
}

dalo_portal_logout();

header("Location: login.php");
exit;
