<?php
/*
 *********************************************************************************************************
 * daloRADIUS - RADIUS Web Platform
 * Super Admin Session Management
 *********************************************************************************************************
 */

// Session configuration
$session_max_lifetime = 3600; // 1 hour

/**
 * Start session with security settings
 */
function dalo_admin_session_start() {
    global $session_max_lifetime;

    if (session_status() === PHP_SESSION_NONE) {
        // Set session name
        session_name('daloradius_admin_sid');

        // Set secure cookie parameters
        session_set_cookie_params([
            'lifetime' => 0,
            'path' => '/',
            'domain' => '',
            'secure' => isset($_SERVER['HTTPS']),
            'httponly' => true,
            'samesite' => 'Lax'
        ]);

        session_start();
    }

    // Check session timeout
    $now = time();
    if (isset($_SESSION['admin_time']) && $_SESSION['admin_time'] < $now - $session_max_lifetime) {
        dalo_admin_session_destroy();
        session_start();
        dalo_admin_session_regenerate_id();
    }

    $_SESSION['admin_time'] = $now;
}

/**
 * Destroy session
 */
function dalo_admin_session_destroy() {
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

/**
 * Regenerate session ID
 */
function dalo_admin_session_regenerate_id() {
    $newId = 'daloRADIUS-admin-' . bin2hex(random_bytes(16));
    session_id($newId);
}

/**
 * Generate CSRF token
 */
function dalo_admin_csrf_token() {
    if (empty($_SESSION['admin_csrf_token'])) {
        $_SESSION['admin_csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['admin_csrf_token'];
}

/**
 * Verify CSRF token
 */
function dalo_admin_check_csrf_token($token) {
    if (empty($_SESSION['admin_csrf_token']) || empty($token)) {
        return false;
    }
    return hash_equals($_SESSION['admin_csrf_token'], $token);
}

/**
 * Set super admin session
 */
function dalo_admin_set_session($adminId, $username, $email) {
    $_SESSION['admin_logged_in'] = true;
    $_SESSION['admin_id'] = $adminId;
    $_SESSION['admin_user'] = $username;
    $_SESSION['admin_email'] = $email;
    $_SESSION['user_type'] = 'super_admin';
    $_SESSION['admin_time'] = time();

    dalo_admin_session_regenerate_id();
}

/**
 * Check if admin is logged in
 */
function dalo_admin_is_logged_in() {
    return isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true;
}

/**
 * Get admin ID
 */
function dalo_admin_get_id() {
    return $_SESSION['admin_id'] ?? null;
}

/**
 * Get admin username
 */
function dalo_admin_get_username() {
    return $_SESSION['admin_user'] ?? null;
}
