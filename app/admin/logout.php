<?php
/*
 *********************************************************************************************************
 * daloRADIUS - RADIUS Web Platform
 * Super Admin Logout
 *********************************************************************************************************
 */

require_once(dirname(__FILE__) . '/library/sessions.php');

dalo_admin_session_start();

// Log the logout action
if (dalo_admin_is_logged_in()) {
    require_once(dirname(__FILE__) . '/../common/library/db_connect.php');

    $adminId = $_SESSION['admin_id'];
    $logSql = sprintf(
        "INSERT INTO activity_logs (tenant_id, user_type, user_id, action, entity_type,
                                    ip_address, user_agent, created_at)
         VALUES (NULL, 'super_admin', %d, 'logout', 'super_admin', '%s', '%s', NOW())",
        intval($adminId),
        $dbSocket->escapeSimple($_SERVER['REMOTE_ADDR']),
        $dbSocket->escapeSimple(substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 255))
    );
    $dbSocket->query($logSql);
}

// Destroy session
$_SESSION = array();

if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

session_destroy();

// Redirect to login
header("Location: login.php");
exit;
