<?php
/*
 *********************************************************************************************************
 * daloRADIUS - RADIUS Web Platform
 * Tenant Portal Login Check
 *********************************************************************************************************
 */

require_once(dirname(__FILE__) . '/sessions.php');

dalo_portal_session_start();

// Check if logged in
if (!dalo_portal_is_logged_in()) {
    header("Location: " . dirname($_SERVER['PHP_SELF']) . "/login.php?error=session");
    exit;
}

// Check session timeout (8 hours)
if (isset($_SESSION['portal_login_time']) && (time() - $_SESSION['portal_login_time'] > 28800)) {
    dalo_portal_logout();
    header("Location: login.php?error=timeout");
    exit;
}

// Load tenant context
require_once(dirname(__FILE__) . '/../../common/includes/tenant_context.php');

// Initialize tenant context with current tenant ID
$tenantContext = TenantContext::getInstance();
$tenantContext->setTenantId($_SESSION['portal_tenant_id']);

// Helper function for tenant-filtered queries
function dalo_tenant_where($tableAlias = '') {
    $prefix = $tableAlias ? $tableAlias . '.' : '';
    return $prefix . "tenant_id = " . intval($_SESSION['portal_tenant_id']);
}
