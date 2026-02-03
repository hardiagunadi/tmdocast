<?php
/*
 *********************************************************************************************************
 * daloRADIUS - RADIUS Web Platform
 * Tenant Portal Login Handler
 *********************************************************************************************************
 */

require_once(dirname(__FILE__) . '/library/sessions.php');
require_once(dirname(__FILE__) . '/../common/includes/config_read.php');

dalo_portal_session_start();

// Only accept POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: login.php");
    exit;
}

// Verify CSRF token
if (!isset($_POST['csrf_token']) || !dalo_portal_verify_csrf($_POST['csrf_token'])) {
    header("Location: login.php?error=csrf");
    exit;
}

// Get credentials
$tenantDomain = isset($_POST['tenant_domain']) ? strtolower(trim($_POST['tenant_domain'])) : '';
$username = isset($_POST['username']) ? trim($_POST['username']) : '';
$password = isset($_POST['password']) ? $_POST['password'] : '';

// Validate input
if (empty($tenantDomain) || empty($username) || empty($password)) {
    header("Location: login.php?error=invalid&tenant=" . urlencode($tenantDomain));
    exit;
}

// Rate limiting
$failedKey = 'portal_failed_' . md5($_SERVER['REMOTE_ADDR'] . '_' . $tenantDomain);
$failedAttempts = isset($_SESSION[$failedKey]) ? $_SESSION[$failedKey] : 0;
$lockoutTime = isset($_SESSION[$failedKey . '_time']) ? $_SESSION[$failedKey . '_time'] : 0;

if ($failedAttempts >= 5 && (time() - $lockoutTime) < 900) {
    header("Location: login.php?error=locked&tenant=" . urlencode($tenantDomain));
    exit;
}

// Database connection
require_once(dirname(__FILE__) . '/../common/library/db_connect.php');

try {
    // Find tenant by domain_prefix
    $tenantSql = "SELECT id, company_name, is_active
                  FROM tenants
                  WHERE domain_prefix = '" . $dbSocket->escapeSimple($tenantDomain) . "'
                  LIMIT 1";
    $tenantResult = $dbSocket->query($tenantSql);

    if (DB::isError($tenantResult) || $tenantResult->numRows() === 0) {
        header("Location: login.php?error=tenant");
        exit;
    }

    $tenant = $tenantResult->fetchRow(DB_FETCHMODE_ASSOC);

    // Check if tenant is active
    if (!$tenant['is_active']) {
        header("Location: login.php?error=inactive&tenant=" . urlencode($tenantDomain));
        exit;
    }

    // Check if tenant has active subscription
    $subscriptionSql = "SELECT id, end_date FROM tenant_subscriptions
                        WHERE tenant_id = " . intval($tenant['id']) . "
                        AND status = 'active'
                        AND end_date > NOW()
                        LIMIT 1";
    $subscriptionResult = $dbSocket->query($subscriptionSql);

    if (DB::isError($subscriptionResult) || $subscriptionResult->numRows() === 0) {
        header("Location: login.php?error=expired&tenant=" . urlencode($tenantDomain));
        exit;
    }

    // Find operator
    $operatorSql = "SELECT id, username, password_hash, full_name, email, role, is_active,
                           failed_login_attempts, locked_until
                    FROM tenant_operators
                    WHERE tenant_id = " . intval($tenant['id']) . "
                    AND username = '" . $dbSocket->escapeSimple($username) . "'
                    LIMIT 1";
    $operatorResult = $dbSocket->query($operatorSql);

    if (DB::isError($operatorResult) || $operatorResult->numRows() === 0) {
        $_SESSION[$failedKey] = $failedAttempts + 1;
        $_SESSION[$failedKey . '_time'] = time();
        header("Location: login.php?error=invalid&tenant=" . urlencode($tenantDomain));
        exit;
    }

    $operator = $operatorResult->fetchRow(DB_FETCHMODE_ASSOC);

    // Check if operator is active
    if (!$operator['is_active']) {
        header("Location: login.php?error=inactive&tenant=" . urlencode($tenantDomain));
        exit;
    }

    // Check if operator is locked
    if ($operator['locked_until'] && strtotime($operator['locked_until']) > time()) {
        header("Location: login.php?error=locked&tenant=" . urlencode($tenantDomain));
        exit;
    }

    // Verify password
    if (!password_verify($password, $operator['password_hash'])) {
        // Wrong password - update failed attempts
        $newAttempts = $operator['failed_login_attempts'] + 1;
        $lockUntil = null;

        if ($newAttempts >= 5) {
            $lockUntil = date('Y-m-d H:i:s', time() + 900); // Lock for 15 minutes
        }

        $updateSql = sprintf(
            "UPDATE tenant_operators
             SET failed_login_attempts = %d, locked_until = %s
             WHERE id = %d",
            $newAttempts,
            $lockUntil ? "'" . $lockUntil . "'" : "NULL",
            $operator['id']
        );
        $dbSocket->query($updateSql);

        $_SESSION[$failedKey] = $failedAttempts + 1;
        $_SESSION[$failedKey . '_time'] = time();
        header("Location: login.php?error=invalid&tenant=" . urlencode($tenantDomain));
        exit;
    }

    // Login successful!
    // Reset failed attempts
    $updateSql = sprintf(
        "UPDATE tenant_operators
         SET failed_login_attempts = 0,
             locked_until = NULL,
             last_login = NOW(),
             last_login_ip = '%s'
         WHERE id = %d",
        $dbSocket->escapeSimple($_SERVER['REMOTE_ADDR']),
        $operator['id']
    );
    $dbSocket->query($updateSql);

    // Clear session rate limit
    unset($_SESSION[$failedKey]);
    unset($_SESSION[$failedKey . '_time']);

    // Set session data
    dalo_portal_set_session(
        $operator['id'],
        $tenant['id'],
        $operator['username'],
        $operator['full_name'],
        $operator['role'],
        $tenant['company_name']
    );

    // Log the login activity
    $userAgent = isset($_SERVER['HTTP_USER_AGENT']) ? substr($_SERVER['HTTP_USER_AGENT'], 0, 255) : '';
    $logSql = sprintf(
        "INSERT INTO activity_logs (tenant_id, user_type, user_id, action, entity_type,
                                    ip_address, user_agent, created_at)
         VALUES (%d, 'operator', %d, 'login', 'tenant_operator', '%s', '%s', NOW())",
        $tenant['id'],
        $operator['id'],
        $dbSocket->escapeSimple($_SERVER['REMOTE_ADDR']),
        $dbSocket->escapeSimple($userAgent)
    );
    $dbSocket->query($logSql);

    // Redirect to dashboard
    header("Location: index.php");
    exit;

} catch (Exception $e) {
    error_log("Portal Login Error: " . $e->getMessage());
    header("Location: login.php?error=invalid&tenant=" . urlencode($tenantDomain));
    exit;
}
