<?php
/*
 *********************************************************************************************************
 * daloRADIUS - RADIUS Web Platform
 * Copyright (C) 2007 - Liran Tal <liran@lirantal.com> All Rights Reserved.
 *
 * Subscription Check Module
 * Validates tenant subscription status and enforces access control
 *
 *********************************************************************************************************
 */

// Prevent direct access
if (!defined('DALO_INCLUDE')) {
    die('Direct access not allowed');
}

require_once(dirname(__FILE__) . '/tenant_context.php');

/**
 * Check if tenant subscription is valid
 * Redirects to subscription expired page if not valid
 *
 * @param bool $redirect Whether to redirect on failure
 * @return bool True if subscription is valid
 */
function dalo_check_subscription($redirect = true) {
    $context = TenantContext::getInstance();

    // Super admins always pass
    if ($context->isSuperAdmin()) {
        return true;
    }

    // Check subscription status
    if (!$context->isSubscriptionActive()) {
        if ($redirect) {
            // Set error message
            $_SESSION['subscription_error'] = 'Langganan Anda telah berakhir. Silakan perpanjang untuk melanjutkan.';

            // Redirect to subscription page
            header('Location: subscription-expired.php');
            exit;
        }
        return false;
    }

    // Check if subscription is about to expire (7 days warning)
    $daysRemaining = $context->getSubscriptionDaysRemaining();
    if ($daysRemaining > 0 && $daysRemaining <= 7) {
        $_SESSION['subscription_warning'] = sprintf(
            'Langganan Anda akan berakhir dalam %d hari. Perpanjang sekarang untuk menghindari gangguan layanan.',
            $daysRemaining
        );
    }

    return true;
}

/**
 * Check if tenant has access to a specific feature
 *
 * @param string $featureCode Feature code to check
 * @param bool $redirect Whether to redirect on failure
 * @return bool True if feature is accessible
 */
function dalo_check_feature($featureCode, $redirect = true) {
    $context = TenantContext::getInstance();

    // Super admins have all features
    if ($context->isSuperAdmin()) {
        return true;
    }

    // Check feature access
    if (!$context->hasFeature($featureCode)) {
        if ($redirect) {
            $_SESSION['feature_error'] = 'Fitur ini tidak tersedia dalam paket langganan Anda. Upgrade untuk mengakses fitur ini.';
            header('Location: feature-unavailable.php');
            exit;
        }
        return false;
    }

    return true;
}

/**
 * Check usage limits before creating new resources
 *
 * @param string $limitType Type of limit to check (mikrotik, pppoe, operator, olt)
 * @param bool $redirect Whether to redirect on failure
 * @return bool True if under limit
 */
function dalo_check_usage_limit($limitType, $redirect = true) {
    global $dbSocket;

    $context = TenantContext::getInstance();
    $tenantId = $context->getTenantId();

    // Super admins have no limits
    if ($context->isSuperAdmin()) {
        return true;
    }

    if (!$tenantId) {
        return false;
    }

    // Get current count based on type
    switch ($limitType) {
        case 'mikrotik':
        case 'max_mikrotik_devices':
            $sql = sprintf("SELECT COUNT(*) FROM tenant_mikrotik_devices WHERE tenant_id = %d AND is_active = 1", $tenantId);
            $currentCount = intval($dbSocket->getOne($sql));
            $limitKey = 'max_mikrotik_devices';
            $limitName = 'perangkat MikroTik';
            break;

        case 'pppoe':
        case 'max_pppoe_users':
            $sql = sprintf("SELECT COUNT(DISTINCT username) FROM radcheck WHERE tenant_id = %d", $tenantId);
            $currentCount = intval($dbSocket->getOne($sql));
            $limitKey = 'max_pppoe_users';
            $limitName = 'pelanggan PPPoE';
            break;

        case 'operator':
        case 'max_operators':
            $sql = sprintf("SELECT COUNT(*) FROM tenant_operators WHERE tenant_id = %d AND is_active = 1", $tenantId);
            $currentCount = intval($dbSocket->getOne($sql));
            $limitKey = 'max_operators';
            $limitName = 'operator';
            break;

        case 'olt':
        case 'max_olt_devices':
            $sql = sprintf("SELECT COUNT(*) FROM olt_devices WHERE tenant_id = %d AND is_active = 1", $tenantId);
            $currentCount = intval($dbSocket->getOne($sql));
            $limitKey = 'max_olt_devices';
            $limitName = 'perangkat OLT';
            break;

        default:
            return true;
    }

    // Check against limit
    if ($context->isLimitReached($limitKey, $currentCount)) {
        $limit = $context->getLimit($limitKey);
        if ($redirect) {
            $_SESSION['limit_error'] = sprintf(
                'Anda telah mencapai batas maksimal %d %s. Upgrade paket Anda untuk menambah lebih banyak.',
                $limit,
                $limitName
            );
            header('Location: limit-reached.php');
            exit;
        }
        return false;
    }

    return true;
}

/**
 * Get usage statistics for current tenant
 *
 * @return array Usage statistics
 */
function dalo_get_usage_stats() {
    global $dbSocket;

    $context = TenantContext::getInstance();
    $tenantId = $context->getTenantId();

    if (!$tenantId) {
        return [];
    }

    $stats = [];

    // MikroTik devices
    $sql = sprintf("SELECT COUNT(*) FROM tenant_mikrotik_devices WHERE tenant_id = %d AND is_active = 1", $tenantId);
    $stats['mikrotik_count'] = intval($dbSocket->getOne($sql));
    $stats['mikrotik_limit'] = $context->getLimit('max_mikrotik_devices');
    $stats['mikrotik_percentage'] = $stats['mikrotik_limit'] > 0 ?
        round(($stats['mikrotik_count'] / $stats['mikrotik_limit']) * 100) : 0;

    // PPPoE users
    $sql = sprintf("SELECT COUNT(DISTINCT username) FROM radcheck WHERE tenant_id = %d", $tenantId);
    $stats['pppoe_count'] = intval($dbSocket->getOne($sql));
    $stats['pppoe_limit'] = $context->getLimit('max_pppoe_users');
    $stats['pppoe_percentage'] = $stats['pppoe_limit'] > 0 ?
        round(($stats['pppoe_count'] / $stats['pppoe_limit']) * 100) : 0;

    // Operators
    $sql = sprintf("SELECT COUNT(*) FROM tenant_operators WHERE tenant_id = %d AND is_active = 1", $tenantId);
    $stats['operator_count'] = intval($dbSocket->getOne($sql));
    $stats['operator_limit'] = $context->getLimit('max_operators');
    $stats['operator_percentage'] = $stats['operator_limit'] > 0 ?
        round(($stats['operator_count'] / $stats['operator_limit']) * 100) : 0;

    // OLT devices
    $sql = sprintf("SELECT COUNT(*) FROM olt_devices WHERE tenant_id = %d AND is_active = 1", $tenantId);
    $stats['olt_count'] = intval($dbSocket->getOne($sql));
    $stats['olt_limit'] = $context->getLimit('max_olt_devices');
    $stats['olt_percentage'] = $stats['olt_limit'] > 0 ?
        round(($stats['olt_count'] / $stats['olt_limit']) * 100) : 0;

    // Active sessions
    $sql = sprintf("SELECT COUNT(*) FROM radacct WHERE tenant_id = %d AND acctstoptime IS NULL", $tenantId);
    $stats['active_sessions'] = intval($dbSocket->getOne($sql));

    // Online ONUs
    $sql = sprintf("SELECT COUNT(*) FROM olt_onu_devices WHERE tenant_id = %d AND oper_status = 'online'", $tenantId);
    $stats['online_onus'] = intval($dbSocket->getOne($sql));

    return $stats;
}

/**
 * Update tenant subscription after payment
 *
 * @param int $tenantId Tenant ID
 * @param int $planId New plan ID
 * @param string $billingCycle Billing cycle (monthly, quarterly, yearly)
 * @return bool Success status
 */
function dalo_activate_subscription($tenantId, $planId, $billingCycle = 'monthly') {
    global $dbSocket;

    // Get plan details
    $sql = sprintf("SELECT * FROM app_subscription_plans WHERE id = %d AND is_active = 1", $planId);
    $res = $dbSocket->query($sql);
    if (!$res || $res->numRows() === 0) {
        return false;
    }
    $plan = $res->fetchRow(DB_FETCHMODE_ASSOC);

    // Calculate end date based on billing cycle
    $now = new DateTime();
    switch ($billingCycle) {
        case 'yearly':
            $endDate = $now->add(new DateInterval('P1Y'));
            break;
        case 'quarterly':
            $endDate = $now->add(new DateInterval('P3M'));
            break;
        case 'monthly':
        default:
            $endDate = $now->add(new DateInterval('P1M'));
            break;
    }

    // Start transaction
    $dbSocket->query('START TRANSACTION');

    try {
        // Update tenant
        $sql = sprintf("UPDATE tenants SET
            subscription_plan_id = %d,
            subscription_expires_at = '%s',
            status = 'active',
            max_mikrotik_devices = %d,
            max_pppoe_users = %d,
            max_operators = %d,
            features_json = '%s',
            updated_at = NOW()
            WHERE id = %d",
            $planId,
            $endDate->format('Y-m-d H:i:s'),
            $plan['max_mikrotik_devices'],
            $plan['max_pppoe_users'],
            $plan['max_operators'],
            $dbSocket->escapeSimple($plan['features_json']),
            $tenantId
        );
        $dbSocket->query($sql);

        // Create subscription record
        $sql = sprintf("INSERT INTO tenant_subscriptions
            (tenant_id, plan_id, status, starts_at, ends_at, auto_renew, created_at)
            VALUES (%d, %d, 'active', NOW(), '%s', 1, NOW())",
            $tenantId,
            $planId,
            $endDate->format('Y-m-d H:i:s')
        );
        $dbSocket->query($sql);

        // Expire previous active subscriptions
        $sql = sprintf("UPDATE tenant_subscriptions
            SET status = 'expired'
            WHERE tenant_id = %d AND status = 'active' AND id != LAST_INSERT_ID()",
            $tenantId
        );
        $dbSocket->query($sql);

        $dbSocket->query('COMMIT');
        return true;

    } catch (Exception $e) {
        $dbSocket->query('ROLLBACK');
        error_log("Subscription activation failed: " . $e->getMessage());
        return false;
    }
}

/**
 * Suspend tenant for overdue payment
 *
 * @param int $tenantId Tenant ID
 * @param string $reason Suspension reason
 * @return bool Success status
 */
function dalo_suspend_tenant($tenantId, $reason = 'Pembayaran jatuh tempo') {
    global $dbSocket;

    $sql = sprintf("UPDATE tenants SET
        status = 'suspended',
        updated_at = NOW()
        WHERE id = %d",
        $tenantId
    );
    $dbSocket->query($sql);

    // Log activity
    $sql = sprintf("INSERT INTO activity_logs
        (tenant_id, action, entity_type, entity_id, new_values, ip_address, created_at)
        VALUES (%d, 'tenant_suspended', 'tenant', %d, '%s', '%s', NOW())",
        $tenantId,
        $tenantId,
        $dbSocket->escapeSimple(json_encode(['reason' => $reason])),
        $_SERVER['REMOTE_ADDR'] ?? ''
    );
    $dbSocket->query($sql);

    return true;
}

/**
 * Reactivate suspended tenant
 *
 * @param int $tenantId Tenant ID
 * @return bool Success status
 */
function dalo_reactivate_tenant($tenantId) {
    global $dbSocket;

    $sql = sprintf("UPDATE tenants SET
        status = 'active',
        updated_at = NOW()
        WHERE id = %d",
        $tenantId
    );
    $dbSocket->query($sql);

    // Log activity
    $sql = sprintf("INSERT INTO activity_logs
        (tenant_id, action, entity_type, entity_id, ip_address, created_at)
        VALUES (%d, 'tenant_reactivated', 'tenant', %d, '%s', NOW())",
        $tenantId,
        $tenantId,
        $_SERVER['REMOTE_ADDR'] ?? ''
    );
    $dbSocket->query($sql);

    return true;
}
