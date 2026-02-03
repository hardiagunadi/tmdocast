<?php
/*
 *********************************************************************************************************
 * daloRADIUS - RADIUS Web Platform
 * Tenant Portal Session Management
 *********************************************************************************************************
 */

// Start session for tenant portal
function dalo_portal_session_start() {
    if (session_status() === PHP_SESSION_NONE) {
        session_name('DALO_PORTAL_SESSION');
        session_start();
    }

    // Regenerate session ID periodically
    if (!isset($_SESSION['portal_created'])) {
        $_SESSION['portal_created'] = time();
    } elseif (time() - $_SESSION['portal_created'] > 1800) {
        // Regenerate after 30 minutes
        session_regenerate_id(true);
        $_SESSION['portal_created'] = time();
    }
}

// Set session data after successful login
function dalo_portal_set_session($operatorId, $tenantId, $username, $fullname, $role, $tenantName) {
    $_SESSION['portal_logged_in'] = true;
    $_SESSION['portal_operator_id'] = $operatorId;
    $_SESSION['portal_tenant_id'] = $tenantId;
    $_SESSION['portal_username'] = $username;
    $_SESSION['portal_fullname'] = $fullname;
    $_SESSION['portal_role'] = $role;
    $_SESSION['portal_tenant_name'] = $tenantName;
    $_SESSION['portal_login_time'] = time();

    // Generate new CSRF token
    $_SESSION['portal_csrf_token'] = bin2hex(random_bytes(32));
}

// Check if user is logged in
function dalo_portal_is_logged_in() {
    return isset($_SESSION['portal_logged_in']) &&
           $_SESSION['portal_logged_in'] === true &&
           isset($_SESSION['portal_operator_id']) &&
           isset($_SESSION['portal_tenant_id']);
}

// Get current tenant ID
function dalo_portal_get_tenant_id() {
    return isset($_SESSION['portal_tenant_id']) ? $_SESSION['portal_tenant_id'] : null;
}

// Get current operator ID
function dalo_portal_get_operator_id() {
    return isset($_SESSION['portal_operator_id']) ? $_SESSION['portal_operator_id'] : null;
}

// Get CSRF token
function dalo_portal_csrf_token() {
    if (!isset($_SESSION['portal_csrf_token'])) {
        $_SESSION['portal_csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['portal_csrf_token'];
}

// Verify CSRF token
function dalo_portal_verify_csrf($token) {
    if (!isset($_SESSION['portal_csrf_token'])) {
        return false;
    }
    return hash_equals($_SESSION['portal_csrf_token'], $token);
}

// Check if operator has permission
function dalo_portal_has_permission($permission) {
    $role = isset($_SESSION['portal_role']) ? $_SESSION['portal_role'] : '';

    // Admin has all permissions
    if ($role === 'admin') {
        return true;
    }

    // Define role permissions
    $permissions = [
        'manager' => ['view_users', 'add_users', 'edit_users', 'view_reports', 'view_olt', 'manage_olt'],
        'operator' => ['view_users', 'add_users', 'view_reports', 'view_olt'],
        'viewer' => ['view_users', 'view_reports']
    ];

    if (isset($permissions[$role])) {
        return in_array($permission, $permissions[$role]);
    }

    return false;
}

// Destroy session
function dalo_portal_logout() {
    $_SESSION = array();

    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $params["path"], $params["domain"],
            $params["secure"], $params["httponly"]
        );
    }

    session_destroy();
}
