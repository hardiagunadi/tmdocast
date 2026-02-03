<?php
/*
 *********************************************************************************************************
 * daloRADIUS - RADIUS Web Platform
 * Super Admin Login Handler
 *********************************************************************************************************
 */

require_once(dirname(__FILE__) . '/library/sessions.php');
require_once(dirname(__FILE__) . '/../common/includes/config_read.php');

dalo_admin_session_start();

// Only accept POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: login.php");
    exit;
}

// Verify CSRF token
if (!isset($_POST['csrf_token']) || !dalo_admin_verify_csrf($_POST['csrf_token'])) {
    header("Location: login.php?error=csrf");
    exit;
}

// Get credentials
$username = isset($_POST['username']) ? trim($_POST['username']) : '';
$password = isset($_POST['password']) ? $_POST['password'] : '';

// Validate input
if (empty($username) || empty($password)) {
    header("Location: login.php?error=invalid");
    exit;
}

// Rate limiting - check failed attempts
$failedKey = 'admin_failed_' . md5($_SERVER['REMOTE_ADDR']);
$failedAttempts = isset($_SESSION[$failedKey]) ? $_SESSION[$failedKey] : 0;
$lockoutTime = isset($_SESSION[$failedKey . '_time']) ? $_SESSION[$failedKey . '_time'] : 0;

// If more than 5 failed attempts in last 15 minutes, block
if ($failedAttempts >= 5 && (time() - $lockoutTime) < 900) {
    header("Location: login.php?error=locked");
    exit;
}

// Database connection
require_once(dirname(__FILE__) . '/../common/library/db_connect.php');

try {
    // Query super_admins table
    $sql = "SELECT id, username, password_hash, full_name, email, role, is_active,
                   failed_login_attempts, locked_until
            FROM super_admins
            WHERE username = ?
            LIMIT 1";

    $stmt = $dbSocket->prepare($sql);
    $stmt->execute([$username]);
    $admin = $stmt->fetch(PDO::FETCH_ASSOC);

    // If no PDO, try with PEAR DB
    if (!$admin && isset($dbSocket) && method_exists($dbSocket, 'query')) {
        $sql = "SELECT id, username, password_hash, full_name, email, role, is_active,
                       failed_login_attempts, locked_until
                FROM super_admins
                WHERE username = '" . $dbSocket->escapeSimple($username) . "'
                LIMIT 1";
        $result = $dbSocket->query($sql);
        if ($result && !DB::isError($result)) {
            $admin = $result->fetchRow(DB_FETCHMODE_ASSOC);
        }
    }

    if (!$admin) {
        // User not found
        $_SESSION[$failedKey] = $failedAttempts + 1;
        $_SESSION[$failedKey . '_time'] = time();
        header("Location: login.php?error=invalid");
        exit;
    }

    // Check if account is active
    if (!$admin['is_active']) {
        header("Location: login.php?error=inactive");
        exit;
    }

    // Check if account is locked
    if ($admin['locked_until'] && strtotime($admin['locked_until']) > time()) {
        header("Location: login.php?error=locked");
        exit;
    }

    // Verify password
    if (!password_verify($password, $admin['password_hash'])) {
        // Wrong password - update failed attempts
        $newAttempts = $admin['failed_login_attempts'] + 1;
        $lockUntil = null;

        // Lock after 5 failed attempts for 15 minutes
        if ($newAttempts >= 5) {
            $lockUntil = date('Y-m-d H:i:s', time() + 900);
        }

        $updateSql = "UPDATE super_admins
                      SET failed_login_attempts = ?,
                          locked_until = ?
                      WHERE id = ?";

        if (isset($dbSocket) && $dbSocket instanceof PDO) {
            $stmt = $dbSocket->prepare($updateSql);
            $stmt->execute([$newAttempts, $lockUntil, $admin['id']]);
        } else {
            $updateSql = "UPDATE super_admins
                          SET failed_login_attempts = " . intval($newAttempts) . ",
                              locked_until = " . ($lockUntil ? "'" . $lockUntil . "'" : "NULL") . "
                          WHERE id = " . intval($admin['id']);
            $dbSocket->query($updateSql);
        }

        $_SESSION[$failedKey] = $failedAttempts + 1;
        $_SESSION[$failedKey . '_time'] = time();
        header("Location: login.php?error=invalid");
        exit;
    }

    // Login successful!
    // Reset failed attempts
    $updateSql = "UPDATE super_admins
                  SET failed_login_attempts = 0,
                      locked_until = NULL,
                      last_login = NOW(),
                      last_login_ip = ?
                  WHERE id = ?";

    if (isset($dbSocket) && $dbSocket instanceof PDO) {
        $stmt = $dbSocket->prepare($updateSql);
        $stmt->execute([$_SERVER['REMOTE_ADDR'], $admin['id']]);
    } else {
        $updateSql = "UPDATE super_admins
                      SET failed_login_attempts = 0,
                          locked_until = NULL,
                          last_login = NOW(),
                          last_login_ip = '" . $dbSocket->escapeSimple($_SERVER['REMOTE_ADDR']) . "'
                      WHERE id = " . intval($admin['id']);
        $dbSocket->query($updateSql);
    }

    // Clear session rate limit
    unset($_SESSION[$failedKey]);
    unset($_SESSION[$failedKey . '_time']);

    // Set session data
    dalo_admin_set_session($admin['id'], $admin['username'], $admin['full_name'], $admin['role']);

    // Log the login
    $logSql = "INSERT INTO activity_logs (tenant_id, user_type, user_id, action, entity_type,
                                          ip_address, user_agent, created_at)
               VALUES (NULL, 'super_admin', ?, 'login', 'super_admin', ?, ?, NOW())";

    $userAgent = isset($_SERVER['HTTP_USER_AGENT']) ? substr($_SERVER['HTTP_USER_AGENT'], 0, 255) : '';

    if (isset($dbSocket) && $dbSocket instanceof PDO) {
        $stmt = $dbSocket->prepare($logSql);
        $stmt->execute([$admin['id'], $_SERVER['REMOTE_ADDR'], $userAgent]);
    } else {
        $logSql = sprintf(
            "INSERT INTO activity_logs (tenant_id, user_type, user_id, action, entity_type,
                                        ip_address, user_agent, created_at)
             VALUES (NULL, 'super_admin', %d, 'login', 'super_admin', '%s', '%s', NOW())",
            intval($admin['id']),
            $dbSocket->escapeSimple($_SERVER['REMOTE_ADDR']),
            $dbSocket->escapeSimple($userAgent)
        );
        $dbSocket->query($logSql);
    }

    // Redirect to dashboard
    header("Location: home-main.php");
    exit;

} catch (Exception $e) {
    error_log("Super Admin Login Error: " . $e->getMessage());
    header("Location: login.php?error=invalid");
    exit;
}
