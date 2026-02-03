<?php
/*
 *********************************************************************************************************
 * daloRADIUS - RADIUS Web Platform
 * Super Admin Dashboard
 *********************************************************************************************************
 */

require_once(dirname(__FILE__) . '/library/checklogin.php');
require_once(dirname(__FILE__) . '/../common/library/db_connect.php');

// Get dashboard statistics
$stats = [
    'total_tenants' => 0,
    'active_tenants' => 0,
    'total_subscriptions' => 0,
    'monthly_revenue' => 0,
    'total_users' => 0,
    'active_users_today' => 0,
    'pending_payments' => 0,
    'expiring_soon' => 0
];

try {
    // Total and active tenants
    $result = $dbSocket->query("SELECT COUNT(*) as total, SUM(CASE WHEN is_active = 1 THEN 1 ELSE 0 END) as active FROM tenants");
    if ($result && !DB::isError($result)) {
        $row = $result->fetchRow(DB_FETCHMODE_ASSOC);
        $stats['total_tenants'] = $row['total'];
        $stats['active_tenants'] = $row['active'];
    }

    // Active subscriptions
    $result = $dbSocket->query("SELECT COUNT(*) as total FROM tenant_subscriptions WHERE status = 'active'");
    if ($result && !DB::isError($result)) {
        $row = $result->fetchRow(DB_FETCHMODE_ASSOC);
        $stats['total_subscriptions'] = $row['total'];
    }

    // Monthly revenue (current month)
    $result = $dbSocket->query("
        SELECT COALESCE(SUM(amount), 0) as total
        FROM payment_transactions
        WHERE status = 'completed'
        AND payment_type = 'subscription'
        AND MONTH(completed_at) = MONTH(NOW())
        AND YEAR(completed_at) = YEAR(NOW())
    ");
    if ($result && !DB::isError($result)) {
        $row = $result->fetchRow(DB_FETCHMODE_ASSOC);
        $stats['monthly_revenue'] = $row['total'];
    }

    // Total PPPoE users across all tenants
    $result = $dbSocket->query("SELECT COUNT(DISTINCT username) as total FROM radcheck");
    if ($result && !DB::isError($result)) {
        $row = $result->fetchRow(DB_FETCHMODE_ASSOC);
        $stats['total_users'] = $row['total'];
    }

    // Active users today
    $result = $dbSocket->query("
        SELECT COUNT(DISTINCT username) as total
        FROM radacct
        WHERE DATE(acctstarttime) = CURDATE()
    ");
    if ($result && !DB::isError($result)) {
        $row = $result->fetchRow(DB_FETCHMODE_ASSOC);
        $stats['active_users_today'] = $row['total'];
    }

    // Pending payments
    $result = $dbSocket->query("SELECT COUNT(*) as total FROM payment_transactions WHERE status = 'pending'");
    if ($result && !DB::isError($result)) {
        $row = $result->fetchRow(DB_FETCHMODE_ASSOC);
        $stats['pending_payments'] = $row['total'];
    }

    // Subscriptions expiring in 7 days
    $result = $dbSocket->query("
        SELECT COUNT(*) as total
        FROM tenant_subscriptions
        WHERE status = 'active'
        AND end_date BETWEEN NOW() AND DATE_ADD(NOW(), INTERVAL 7 DAY)
    ");
    if ($result && !DB::isError($result)) {
        $row = $result->fetchRow(DB_FETCHMODE_ASSOC);
        $stats['expiring_soon'] = $row['expiring'];
    }

    // Recent tenants
    $recentTenants = [];
    $result = $dbSocket->query("
        SELECT t.*, ts.plan_id, asp.name as plan_name, ts.status as subscription_status, ts.end_date
        FROM tenants t
        LEFT JOIN tenant_subscriptions ts ON t.id = ts.tenant_id AND ts.status = 'active'
        LEFT JOIN app_subscription_plans asp ON ts.plan_id = asp.id
        ORDER BY t.created_at DESC
        LIMIT 5
    ");
    if ($result && !DB::isError($result)) {
        while ($row = $result->fetchRow(DB_FETCHMODE_ASSOC)) {
            $recentTenants[] = $row;
        }
    }

    // Recent payments
    $recentPayments = [];
    $result = $dbSocket->query("
        SELECT pt.*, t.company_name
        FROM payment_transactions pt
        LEFT JOIN tenants t ON pt.tenant_id = t.id
        WHERE pt.payment_type = 'subscription'
        ORDER BY pt.created_at DESC
        LIMIT 5
    ");
    if ($result && !DB::isError($result)) {
        while ($row = $result->fetchRow(DB_FETCHMODE_ASSOC)) {
            $recentPayments[] = $row;
        }
    }

} catch (Exception $e) {
    error_log("Dashboard Error: " . $e->getMessage());
}

function formatRupiah($number) {
    return 'Rp ' . number_format($number, 0, ',', '.');
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - Super Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --sidebar-width: 260px;
            --primary-color: #667eea;
            --secondary-color: #764ba2;
        }
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background-color: #f4f6f9;
        }
        .sidebar {
            position: fixed;
            top: 0;
            left: 0;
            height: 100vh;
            width: var(--sidebar-width);
            background: linear-gradient(135deg, #1e3c72 0%, #2a5298 100%);
            padding-top: 0;
            z-index: 1000;
            overflow-y: auto;
        }
        .sidebar-header {
            padding: 20px;
            text-align: center;
            border-bottom: 1px solid rgba(255,255,255,0.1);
        }
        .sidebar-header h4 {
            color: white;
            margin: 0;
            font-weight: 600;
        }
        .sidebar-header small {
            color: rgba(255,255,255,0.7);
        }
        .nav-link {
            color: rgba(255,255,255,0.8) !important;
            padding: 12px 20px;
            transition: all 0.3s;
            border-left: 3px solid transparent;
        }
        .nav-link:hover, .nav-link.active {
            background: rgba(255,255,255,0.1);
            color: white !important;
            border-left-color: #fff;
        }
        .nav-link i {
            width: 25px;
            text-align: center;
            margin-right: 10px;
        }
        .main-content {
            margin-left: var(--sidebar-width);
            padding: 20px 30px;
            min-height: 100vh;
        }
        .top-bar {
            background: white;
            padding: 15px 25px;
            margin: -20px -30px 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .stat-card {
            background: white;
            border-radius: 15px;
            padding: 25px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.05);
            transition: transform 0.3s;
        }
        .stat-card:hover {
            transform: translateY(-5px);
        }
        .stat-card .icon {
            width: 60px;
            height: 60px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            color: white;
        }
        .stat-card .icon.purple { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); }
        .stat-card .icon.green { background: linear-gradient(135deg, #11998e 0%, #38ef7d 100%); }
        .stat-card .icon.blue { background: linear-gradient(135deg, #2193b0 0%, #6dd5ed 100%); }
        .stat-card .icon.orange { background: linear-gradient(135deg, #f46b45 0%, #eea849 100%); }
        .stat-card .icon.red { background: linear-gradient(135deg, #eb3349 0%, #f45c43 100%); }
        .stat-card h3 {
            font-size: 1.8rem;
            font-weight: 700;
            margin: 15px 0 5px;
        }
        .stat-card p {
            color: #6c757d;
            margin: 0;
        }
        .card {
            border: none;
            border-radius: 15px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.05);
        }
        .card-header {
            background: white;
            border-bottom: 1px solid #eee;
            padding: 15px 20px;
            font-weight: 600;
        }
        .table th {
            border-top: none;
            font-weight: 600;
            color: #495057;
        }
        .badge-status {
            padding: 5px 12px;
            border-radius: 20px;
            font-weight: 500;
            font-size: 0.8rem;
        }
        .nav-section {
            padding: 15px 20px 5px;
            color: rgba(255,255,255,0.5);
            font-size: 0.75rem;
            text-transform: uppercase;
            letter-spacing: 1px;
        }
        .user-dropdown .dropdown-toggle::after {
            display: none;
        }
    </style>
</head>
<body>
    <!-- Sidebar -->
    <nav class="sidebar">
        <div class="sidebar-header">
            <i class="fas fa-shield-alt fa-2x text-white mb-2"></i>
            <h4>Super Admin</h4>
            <small>daloRADIUS SaaS</small>
        </div>

        <div class="nav-section">Dashboard</div>
        <ul class="nav flex-column">
            <li class="nav-item">
                <a class="nav-link active" href="home-main.php">
                    <i class="fas fa-tachometer-alt"></i> Dashboard
                </a>
            </li>
        </ul>

        <div class="nav-section">Tenant Management</div>
        <ul class="nav flex-column">
            <li class="nav-item">
                <a class="nav-link" href="tenants/list.php">
                    <i class="fas fa-building"></i> Tenants
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" href="tenants/pending.php">
                    <i class="fas fa-user-clock"></i> Pending Approval
                </a>
            </li>
        </ul>

        <div class="nav-section">Subscriptions</div>
        <ul class="nav flex-column">
            <li class="nav-item">
                <a class="nav-link" href="subscriptions/plans.php">
                    <i class="fas fa-tags"></i> Plans
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" href="subscriptions/active.php">
                    <i class="fas fa-check-circle"></i> Active Subscriptions
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" href="subscriptions/payments.php">
                    <i class="fas fa-credit-card"></i> Payments
                </a>
            </li>
        </ul>

        <div class="nav-section">System</div>
        <ul class="nav flex-column">
            <li class="nav-item">
                <a class="nav-link" href="system/settings.php">
                    <i class="fas fa-cog"></i> Settings
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" href="system/gateways.php">
                    <i class="fas fa-money-check-alt"></i> Payment Gateways
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" href="system/logs.php">
                    <i class="fas fa-history"></i> Activity Logs
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" href="system/admins.php">
                    <i class="fas fa-users-cog"></i> Admin Users
                </a>
            </li>
        </ul>

        <div class="nav-section">Quick Links</div>
        <ul class="nav flex-column mb-4">
            <li class="nav-item">
                <a class="nav-link" href="../users/login.php" target="_blank">
                    <i class="fas fa-external-link-alt"></i> Tenant Portal
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link text-danger" href="logout.php">
                    <i class="fas fa-sign-out-alt"></i> Logout
                </a>
            </li>
        </ul>
    </nav>

    <!-- Main Content -->
    <main class="main-content">
        <!-- Top Bar -->
        <div class="top-bar">
            <h4 class="mb-0">Dashboard Overview</h4>
            <div class="d-flex align-items-center gap-3">
                <span class="text-muted"><?php echo date('l, d F Y'); ?></span>
                <div class="dropdown user-dropdown">
                    <button class="btn btn-light dropdown-toggle" type="button" data-bs-toggle="dropdown">
                        <i class="fas fa-user-circle me-1"></i>
                        <?php echo htmlspecialchars($_SESSION['admin_fullname']); ?>
                    </button>
                    <ul class="dropdown-menu dropdown-menu-end">
                        <li><a class="dropdown-item" href="profile.php"><i class="fas fa-user me-2"></i>Profile</a></li>
                        <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item text-danger" href="logout.php"><i class="fas fa-sign-out-alt me-2"></i>Logout</a></li>
                    </ul>
                </div>
            </div>
        </div>

        <!-- Statistics Cards -->
        <div class="row g-4 mb-4">
            <div class="col-md-6 col-xl-3">
                <div class="stat-card">
                    <div class="icon purple"><i class="fas fa-building"></i></div>
                    <h3><?php echo number_format($stats['total_tenants']); ?></h3>
                    <p>Total Tenants</p>
                    <small class="text-success"><i class="fas fa-check-circle"></i> <?php echo $stats['active_tenants']; ?> active</small>
                </div>
            </div>
            <div class="col-md-6 col-xl-3">
                <div class="stat-card">
                    <div class="icon green"><i class="fas fa-rupiah-sign"></i></div>
                    <h3><?php echo formatRupiah($stats['monthly_revenue']); ?></h3>
                    <p>Monthly Revenue</p>
                    <small class="text-muted"><i class="fas fa-calendar"></i> This month</small>
                </div>
            </div>
            <div class="col-md-6 col-xl-3">
                <div class="stat-card">
                    <div class="icon blue"><i class="fas fa-users"></i></div>
                    <h3><?php echo number_format($stats['total_users']); ?></h3>
                    <p>PPPoE Users</p>
                    <small class="text-info"><i class="fas fa-signal"></i> <?php echo $stats['active_users_today']; ?> online today</small>
                </div>
            </div>
            <div class="col-md-6 col-xl-3">
                <div class="stat-card">
                    <div class="icon orange"><i class="fas fa-clock"></i></div>
                    <h3><?php echo number_format($stats['pending_payments']); ?></h3>
                    <p>Pending Payments</p>
                    <small class="text-warning"><i class="fas fa-exclamation-triangle"></i> <?php echo $stats['expiring_soon']; ?> expiring soon</small>
                </div>
            </div>
        </div>

        <!-- Recent Data -->
        <div class="row g-4">
            <!-- Recent Tenants -->
            <div class="col-lg-6">
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <span><i class="fas fa-building me-2"></i>Recent Tenants</span>
                        <a href="tenants/list.php" class="btn btn-sm btn-outline-primary">View All</a>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-hover mb-0">
                                <thead>
                                    <tr>
                                        <th>Company</th>
                                        <th>Plan</th>
                                        <th>Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($recentTenants)): ?>
                                    <tr>
                                        <td colspan="3" class="text-center text-muted py-4">No tenants yet</td>
                                    </tr>
                                    <?php else: ?>
                                    <?php foreach ($recentTenants as $tenant): ?>
                                    <tr>
                                        <td>
                                            <strong><?php echo htmlspecialchars($tenant['company_name']); ?></strong><br>
                                            <small class="text-muted"><?php echo htmlspecialchars($tenant['domain_prefix']); ?></small>
                                        </td>
                                        <td><?php echo htmlspecialchars($tenant['plan_name'] ?? 'No Plan'); ?></td>
                                        <td>
                                            <?php if ($tenant['is_active']): ?>
                                            <span class="badge bg-success badge-status">Active</span>
                                            <?php else: ?>
                                            <span class="badge bg-secondary badge-status">Inactive</span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Recent Payments -->
            <div class="col-lg-6">
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <span><i class="fas fa-credit-card me-2"></i>Recent Payments</span>
                        <a href="subscriptions/payments.php" class="btn btn-sm btn-outline-primary">View All</a>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-hover mb-0">
                                <thead>
                                    <tr>
                                        <th>Tenant</th>
                                        <th>Amount</th>
                                        <th>Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($recentPayments)): ?>
                                    <tr>
                                        <td colspan="3" class="text-center text-muted py-4">No payments yet</td>
                                    </tr>
                                    <?php else: ?>
                                    <?php foreach ($recentPayments as $payment): ?>
                                    <tr>
                                        <td>
                                            <strong><?php echo htmlspecialchars($payment['company_name']); ?></strong><br>
                                            <small class="text-muted"><?php echo date('d M Y H:i', strtotime($payment['created_at'])); ?></small>
                                        </td>
                                        <td><?php echo formatRupiah($payment['amount']); ?></td>
                                        <td>
                                            <?php
                                            $statusColors = [
                                                'completed' => 'success',
                                                'pending' => 'warning',
                                                'failed' => 'danger',
                                                'expired' => 'secondary'
                                            ];
                                            $color = $statusColors[$payment['status']] ?? 'secondary';
                                            ?>
                                            <span class="badge bg-<?php echo $color; ?> badge-status">
                                                <?php echo ucfirst($payment['status']); ?>
                                            </span>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Quick Actions -->
        <div class="row mt-4">
            <div class="col-12">
                <div class="card">
                    <div class="card-header">
                        <i class="fas fa-bolt me-2"></i>Quick Actions
                    </div>
                    <div class="card-body">
                        <div class="row g-3">
                            <div class="col-md-3">
                                <a href="tenants/add.php" class="btn btn-outline-primary w-100 py-3">
                                    <i class="fas fa-plus-circle fa-2x mb-2 d-block"></i>
                                    Add New Tenant
                                </a>
                            </div>
                            <div class="col-md-3">
                                <a href="subscriptions/plans.php" class="btn btn-outline-success w-100 py-3">
                                    <i class="fas fa-tags fa-2x mb-2 d-block"></i>
                                    Manage Plans
                                </a>
                            </div>
                            <div class="col-md-3">
                                <a href="system/gateways.php" class="btn btn-outline-info w-100 py-3">
                                    <i class="fas fa-money-check-alt fa-2x mb-2 d-block"></i>
                                    Payment Gateway
                                </a>
                            </div>
                            <div class="col-md-3">
                                <a href="system/logs.php" class="btn btn-outline-secondary w-100 py-3">
                                    <i class="fas fa-history fa-2x mb-2 d-block"></i>
                                    View Logs
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
