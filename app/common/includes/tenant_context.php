<?php
/*
 *********************************************************************************************************
 * daloRADIUS - RADIUS Web Platform
 * Copyright (C) 2007 - Liran Tal <liran@lirantal.com> All Rights Reserved.
 *
 * Multi-Tenant Context Manager
 * Manages tenant isolation and context throughout the application
 *
 *********************************************************************************************************
 */

// Prevent direct access
if (!defined('DALO_INCLUDE')) {
    die('Direct access not allowed');
}

/**
 * TenantContext - Singleton class for managing tenant context
 */
class TenantContext {
    private static $instance = null;
    private $tenantId = null;
    private $tenantCode = null;
    private $tenantData = null;
    private $subscriptionData = null;
    private $features = [];
    private $limits = [];
    private $isSuperAdmin = false;

    private function __construct() {}

    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Initialize tenant context from session
     */
    public function initFromSession() {
        if (isset($_SESSION['tenant_id'])) {
            $this->tenantId = intval($_SESSION['tenant_id']);
        }
        if (isset($_SESSION['tenant_code'])) {
            $this->tenantCode = $_SESSION['tenant_code'];
        }
        if (isset($_SESSION['user_type']) && $_SESSION['user_type'] === 'super_admin') {
            $this->isSuperAdmin = true;
        }

        // Set global for database triggers
        if ($this->tenantId) {
            $GLOBALS['current_tenant_id'] = $this->tenantId;
        }
    }

    /**
     * Set tenant context
     */
    public function setTenant($tenantId, $tenantCode = null) {
        $this->tenantId = intval($tenantId);
        $this->tenantCode = $tenantCode;
        $_SESSION['tenant_id'] = $this->tenantId;
        $_SESSION['tenant_code'] = $this->tenantCode;
        $GLOBALS['current_tenant_id'] = $this->tenantId;

        // Load tenant data
        $this->loadTenantData();
    }

    /**
     * Load tenant data from database
     */
    public function loadTenantData() {
        global $dbSocket, $configValues;

        if (!$this->tenantId) {
            return false;
        }

        $sql = sprintf("SELECT t.*, asp.plan_name, asp.plan_code, asp.features_json AS plan_features,
                        asp.max_mikrotik_devices AS plan_max_mikrotik,
                        asp.max_pppoe_users AS plan_max_pppoe,
                        asp.max_operators AS plan_max_operators,
                        asp.max_olt_devices AS plan_max_olt
                        FROM tenants t
                        LEFT JOIN app_subscription_plans asp ON t.subscription_plan_id = asp.id
                        WHERE t.id = %d", $this->tenantId);

        $res = $dbSocket->query($sql);
        if ($res && $res->numRows() > 0) {
            $this->tenantData = $res->fetchRow(DB_FETCHMODE_ASSOC);
            $this->tenantCode = $this->tenantData['tenant_code'];

            // Parse features
            $planFeatures = json_decode($this->tenantData['plan_features'] ?? '[]', true);
            $tenantFeatures = json_decode($this->tenantData['features_json'] ?? '[]', true);
            $this->features = array_unique(array_merge($planFeatures, $tenantFeatures));

            // Set limits (tenant override > plan default)
            $this->limits = [
                'max_mikrotik_devices' => $this->tenantData['max_mikrotik_devices'] ?? $this->tenantData['plan_max_mikrotik'] ?? -1,
                'max_pppoe_users' => $this->tenantData['max_pppoe_users'] ?? $this->tenantData['plan_max_pppoe'] ?? -1,
                'max_operators' => $this->tenantData['max_operators'] ?? $this->tenantData['plan_max_operators'] ?? 5,
                'max_olt_devices' => $this->tenantData['max_olt_devices'] ?? $this->tenantData['plan_max_olt'] ?? 0,
            ];

            return true;
        }

        return false;
    }

    /**
     * Get tenant ID
     */
    public function getTenantId() {
        return $this->tenantId;
    }

    /**
     * Get tenant code
     */
    public function getTenantCode() {
        return $this->tenantCode;
    }

    /**
     * Get tenant data
     */
    public function getTenantData() {
        return $this->tenantData;
    }

    /**
     * Check if super admin
     */
    public function isSuperAdmin() {
        return $this->isSuperAdmin;
    }

    /**
     * Set super admin mode
     */
    public function setSuperAdmin($value = true) {
        $this->isSuperAdmin = $value;
        $_SESSION['user_type'] = $value ? 'super_admin' : 'tenant_operator';
    }

    /**
     * Check if tenant has a specific feature
     */
    public function hasFeature($featureCode) {
        if ($this->isSuperAdmin) {
            return true;
        }
        return in_array('all', $this->features) || in_array($featureCode, $this->features);
    }

    /**
     * Get available features
     */
    public function getFeatures() {
        return $this->features;
    }

    /**
     * Get limit value
     */
    public function getLimit($limitKey) {
        return $this->limits[$limitKey] ?? -1;
    }

    /**
     * Check if limit is reached
     */
    public function isLimitReached($limitKey, $currentCount) {
        $limit = $this->getLimit($limitKey);
        if ($limit === -1) {
            return false; // Unlimited
        }
        return $currentCount >= $limit;
    }

    /**
     * Check subscription status
     */
    public function isSubscriptionActive() {
        if ($this->isSuperAdmin) {
            return true;
        }

        if (!$this->tenantData) {
            return false;
        }

        // Check tenant status
        if ($this->tenantData['status'] !== 'active') {
            return false;
        }

        // Check subscription expiry
        if (!empty($this->tenantData['subscription_expires_at'])) {
            $expires = new DateTime($this->tenantData['subscription_expires_at']);
            $now = new DateTime();
            if ($expires < $now) {
                return false;
            }
        }

        // Check trial expiry
        if (!empty($this->tenantData['trial_ends_at']) && empty($this->tenantData['subscription_expires_at'])) {
            $trialEnds = new DateTime($this->tenantData['trial_ends_at']);
            $now = new DateTime();
            if ($trialEnds < $now) {
                return false;
            }
        }

        return true;
    }

    /**
     * Get subscription days remaining
     */
    public function getSubscriptionDaysRemaining() {
        if (!$this->tenantData) {
            return 0;
        }

        $expiryDate = $this->tenantData['subscription_expires_at'] ?? $this->tenantData['trial_ends_at'];
        if (empty($expiryDate)) {
            return -1; // Unlimited
        }

        $expires = new DateTime($expiryDate);
        $now = new DateTime();
        $diff = $now->diff($expires);

        if ($expires < $now) {
            return 0;
        }

        return $diff->days;
    }

    /**
     * Clear tenant context
     */
    public function clear() {
        $this->tenantId = null;
        $this->tenantCode = null;
        $this->tenantData = null;
        $this->subscriptionData = null;
        $this->features = [];
        $this->limits = [];
        $this->isSuperAdmin = false;

        unset($_SESSION['tenant_id']);
        unset($_SESSION['tenant_code']);
        unset($GLOBALS['current_tenant_id']);
    }
}

/**
 * Helper functions for backward compatibility
 */

function dalo_get_tenant_id() {
    return TenantContext::getInstance()->getTenantId();
}

function dalo_get_tenant_code() {
    return TenantContext::getInstance()->getTenantCode();
}

function dalo_is_super_admin() {
    return TenantContext::getInstance()->isSuperAdmin();
}

function dalo_has_feature($featureCode) {
    return TenantContext::getInstance()->hasFeature($featureCode);
}

function dalo_check_limit($limitKey, $currentCount) {
    return !TenantContext::getInstance()->isLimitReached($limitKey, $currentCount);
}

function dalo_is_subscription_active() {
    return TenantContext::getInstance()->isSubscriptionActive();
}

/**
 * Add tenant filter to SQL query
 * @param string $sql Original SQL query
 * @param string $tableAlias Table alias for tenant_id column (optional)
 * @return string Modified SQL with tenant filter
 */
function dalo_add_tenant_filter($sql, $tableAlias = '') {
    $tenantId = dalo_get_tenant_id();

    // Super admin can see all data
    if (dalo_is_super_admin() && !isset($_SESSION['view_as_tenant'])) {
        return $sql;
    }

    if ($tenantId === null) {
        // No tenant context - this shouldn't happen for tenant operators
        return $sql . " AND 1=0"; // Return no results
    }

    $prefix = empty($tableAlias) ? '' : $tableAlias . '.';
    $tenantFilter = "{$prefix}tenant_id = " . intval($tenantId);

    // Check if query already has WHERE clause
    if (preg_match('/\bWHERE\b/i', $sql)) {
        // Add to existing WHERE
        $sql = preg_replace('/\bWHERE\b/i', "WHERE {$tenantFilter} AND ", $sql, 1);
    } else {
        // Check for GROUP BY, ORDER BY, LIMIT
        if (preg_match('/\b(GROUP BY|ORDER BY|LIMIT)\b/i', $sql, $matches, PREG_OFFSET_MATCH)) {
            // Insert WHERE before GROUP BY/ORDER BY/LIMIT
            $pos = $matches[0][1];
            $sql = substr($sql, 0, $pos) . " WHERE {$tenantFilter} " . substr($sql, $pos);
        } else {
            // Append WHERE at the end
            $sql .= " WHERE {$tenantFilter}";
        }
    }

    return $sql;
}

/**
 * Set MySQL session variable for tenant (for triggers)
 */
function dalo_set_db_tenant_context() {
    global $dbSocket;
    $tenantId = dalo_get_tenant_id();

    if ($tenantId) {
        $dbSocket->query("SET @current_tenant_id = " . intval($tenantId));
    }
}

/**
 * Initialize tenant context - call this after session start
 */
function dalo_init_tenant_context() {
    $context = TenantContext::getInstance();
    $context->initFromSession();

    // Set database context for triggers
    if ($context->getTenantId()) {
        dalo_set_db_tenant_context();
    }
}
