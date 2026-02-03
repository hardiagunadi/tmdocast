<?php
/*
 *********************************************************************************************************
 * daloRADIUS - RADIUS Web Platform
 * Tenant Portal Dashboard
 *********************************************************************************************************
 */

require_once(dirname(__FILE__) . '/library/checklogin.php');
require_once(dirname(__FILE__) . '/../common/library/db_connect.php');

$tenantId = $_SESSION['portal_tenant_id'];

// Get dashboard statistics
$stats = [
    'total_customers' => 0,
    'active_customers' => 0,
    'online_now' => 0,
    'monthly_revenue' => 0,
    'pending_invoices' => 0,
    'total_mikrotik' => 0,
    'total_olt' => 0,
    'total_onu' => 0
];

try {
    // Total customers (from radcheck with tenant_id)
    $result = $dbSocket->query("
        SELECT COUNT(DISTINCT username) as total
        FROM radcheck
        WHERE tenant_id = " . intval($tenantId)
    );
    if ($result && !DB::isError($result)) {
        $row = $result->fetchRow(DB_FETCHMODE_ASSOC);
        $stats['total_customers'] = $row['total'];
    }

    // Active customers (customers with active invoices)
    $result = $dbSocket->query("
        SELECT COUNT(DISTINCT ca.id) as total
        FROM customer_accounts ca
        WHERE ca.tenant_id = " . intval($tenantId) . "
        AND ca.status = 'active'"
    );
    if ($result && !DB::isError($result)) {
        $row = $result->fetchRow(DB_FETCHMODE_ASSOC);
        $stats['active_customers'] = $row['total'];
    }

    // Online now (active sessions)
    $result = $dbSocket->query("
        SELECT COUNT(*) as total
        FROM radacct ra
        JOIN radcheck rc ON ra.username = rc.username
        WHERE rc.tenant_id = " . intval($tenantId) . "
        AND ra.acctstoptime IS NULL"
    );
    if ($result && !DB::isError($result)) {
        $row = $result->fetchRow(DB_FETCHMODE_ASSOC);
        $stats['online_now'] = $row['total'];
    }

    // Monthly revenue
    $result = $dbSocket->query("
        SELECT COALESCE(SUM(amount), 0) as total
        FROM customer_payments
        WHERE tenant_id = " . intval($tenantId) . "
        AND status = 'completed'
        AND MONTH(payment_date) = MONTH(NOW())
        AND YEAR(payment_date) = YEAR(NOW())"
    );
    if ($result && !DB::isError($result)) {
        $row = $result->fetchRow(DB_FETCHMODE_ASSOC);
        $stats['monthly_revenue'] = $row['total'];
    }

    // Pending invoices
    $result = $dbSocket->query("
        SELECT COUNT(*) as total
        FROM customer_invoices
        WHERE tenant_id = " . intval($tenantId) . "
        AND status IN ('pending', 'unpaid')"
    );
    if ($result && !DB::isError($result)) {
        $row = $result->fetchRow(DB_FETCHMODE_ASSOC);
        $stats['pending_invoices'] = $row['total'];
    }

    // MikroTik devices
    $result = $dbSocket->query("
        SELECT COUNT(*) as total
        FROM tenant_mikrotik_devices
        WHERE tenant_id = " . intval($tenantId)
    );
    if ($result && !DB::isError($result)) {
        $row = $result->fetchRow(DB_FETCHMODE_ASSOC);
        $stats['total_mikrotik'] = $row['total'];
    }

    // OLT devices
    $result = $dbSocket->query("
        SELECT COUNT(*) as total
        FROM olt_devices
        WHERE tenant_id = " . intval($tenantId)
    );
    if ($result && !DB::isError($result)) {
        $row = $result->fetchRow(DB_FETCHMODE_ASSOC);
        $stats['total_olt'] = $row['total'];
    }

    // ONU devices
    $result = $dbSocket->query("
        SELECT COUNT(*) as total
        FROM olt_onu_devices onu
        JOIN olt_devices olt ON onu.olt_id = olt.id
        WHERE olt.tenant_id = " . intval($tenantId)
    );
    if ($result && !DB::isError($result)) {
        $row = $result->fetchRow(DB_FETCHMODE_ASSOC);
        $stats['total_onu'] = $row['total'];
    }

    // Recent activities
    $recentActivities = [];
    $result = $dbSocket->query("
        SELECT action, entity_type, description, ip_address, created_at
        FROM activity_logs
        WHERE tenant_id = " . intval($tenantId) . "
        ORDER BY created_at DESC
        LIMIT 10"
    );
    if ($result && !DB::isError($result)) {
        while ($row = $result->fetchRow(DB_FETCHMODE_ASSOC)) {
            $recentActivities[] = $row;
        }
    }

    // Get subscription info
    $subscription = null;
    $result = $dbSocket->query("
        SELECT ts.*, asp.name as plan_name, asp.max_users, asp.max_mikrotik, asp.max_olt
        FROM tenant_subscriptions ts
        JOIN app_subscription_plans asp ON ts.plan_id = asp.id
        WHERE ts.tenant_id = " . intval($tenantId) . "
        AND ts.status = 'active'
        ORDER BY ts.end_date DESC
        LIMIT 1"
    );
    if ($result && !DB::isError($result)) {
        $subscription = $result->fetchRow(DB_FETCHMODE_ASSOC);
    }

} catch (Exception $e) {
    error_log("Portal Dashboard Error: " . $e->getMessage());
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
    <title>Dashboard - <?php echo htmlspecialchars($_SESSION['portal_tenant_name']); ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --sidebar-width: 260px;
            --primary-gradient: linear-gradient(135deg, #11998e 0%, #38ef7d 100%);
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
            background: linear-gradient(180deg, #1a1a2e 0%, #16213e 100%);
            z-index: 1000;
            overflow-y: auto;
        }
        .sidebar-header {
            padding: 20px;
            text-align: center;
            border-bottom: 1px solid rgba(255,255,255,0.1);
        }
        .sidebar-header h5 {
            color: white;
            margin: 10px 0 5px;
            font-weight: 600;
        }
        .sidebar-header small {
            color: rgba(255,255,255,0.6);
        }
        .nav-link {
            color: rgba(255,255,255,0.7) !important;
            padding: 12px 20px;
            transition: all 0.3s;
            border-left: 3px solid transparent;
        }
        .nav-link:hover, .nav-link.active {
            background: rgba(255,255,255,0.1);
            color: white !important;
            border-left-color: #38ef7d;
        }
        .nav-link i {
            width: 25px;
            text-align: center;
            margin-right: 10px;
        }
        .nav-section {
            padding: 15px 20px 5px;
            color: rgba(255,255,255,0.4);
            font-size: 0.7rem;
            text-transform: uppercase;
            letter-spacing: 1px;
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
            transition: all 0.3s;
            height: 100%;
        }
        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 25px rgba(0,0,0,0.1);
        }
        .stat-card .icon {
            width: 55px;
            height: 55px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.4rem;
            color: white;
        }
        .stat-card .icon.green { background: linear-gradient(135deg, #11998e 0%, #38ef7d 100%); }
        .stat-card .icon.blue { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); }
        .stat-card .icon.orange { background: linear-gradient(135deg, #f46b45 0%, #eea849 100%); }
        .stat-card .icon.purple { background: linear-gradient(135deg, #a18cd1 0%, #fbc2eb 100%); }
        .stat-card h3 {
            font-size: 1.8rem;
            font-weight: 700;
            margin: 15px 0 5px;
        }
        .stat-card p {
            color: #6c757d;
            margin: 0;
            font-size: 0.9rem;
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
        .subscription-card {
            background: var(--primary-gradient);
            color: white;
            border-radius: 15px;
            padding: 25px;
        }
        .subscription-card .plan-name {
            font-size: 1.5rem;
            font-weight: 700;
        }
        .progress {
            height: 8px;
            border-radius: 4px;
        }
        .activity-item {
            padding: 12px 0;
            border-bottom: 1px solid #eee;
        }
        .activity-item:last-child {
            border-bottom: none;
        }
        .activity-icon {
            width: 35px;
            height: 35px;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.9rem;
        }
    </style>
</head>
<body>
    <!-- Sidebar -->
    <nav class="sidebar">
        <div class="sidebar-header">
            <i class="fas fa-broadcast-tower fa-2x text-white"></i>
            <h5><?php echo htmlspecialchars($_SESSION['portal_tenant_name']); ?></h5>
            <small>ISP Management</small>
        </div>

        <div class="nav-section">Main</div>
        <ul class="nav flex-column">
            <li class="nav-item">
                <a class="nav-link active" href="home-main.php">
                    <i class="fas fa-tachometer-alt"></i> Dashboard
                </a>
            </li>
        </ul>

        <div class="nav-section">Pelanggan</div>
        <ul class="nav flex-column">
            <li class="nav-item">
                <a class="nav-link" href="customers/list.php">
                    <i class="fas fa-users"></i> Daftar Pelanggan
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" href="customers/add.php">
                    <i class="fas fa-user-plus"></i> Tambah Pelanggan
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" href="customers/online.php">
                    <i class="fas fa-wifi"></i> Online Sekarang
                </a>
            </li>
        </ul>

        <div class="nav-section">Perangkat</div>
        <ul class="nav flex-column">
            <li class="nav-item">
                <a class="nav-link" href="mikrotik/list.php">
                    <i class="fas fa-server"></i> MikroTik
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" href="olt/list.php">
                    <i class="fas fa-network-wired"></i> OLT
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" href="olt/onu-list.php">
                    <i class="fas fa-microchip"></i> ONU/ONT
                </a>
            </li>
        </ul>

        <div class="nav-section">Keuangan</div>
        <ul class="nav flex-column">
            <li class="nav-item">
                <a class="nav-link" href="billing/invoices.php">
                    <i class="fas fa-file-invoice"></i> Invoice
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" href="billing/payments.php">
                    <i class="fas fa-money-bill-wave"></i> Pembayaran
                </a>
            </li>
        </ul>

        <div class="nav-section">Pengaturan</div>
        <ul class="nav flex-column">
            <li class="nav-item">
                <a class="nav-link" href="settings/profile.php">
                    <i class="fas fa-user-cog"></i> Profil
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" href="settings/operators.php">
                    <i class="fas fa-users-cog"></i> Operator
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
        <div class="top-bar">
            <div>
                <h4 class="mb-0">Dashboard</h4>
                <small class="text-muted">Selamat datang, <?php echo htmlspecialchars($_SESSION['portal_fullname']); ?></small>
            </div>
            <div class="d-flex align-items-center gap-3">
                <span class="text-muted"><?php echo date('l, d F Y'); ?></span>
                <div class="dropdown">
                    <button class="btn btn-light dropdown-toggle" type="button" data-bs-toggle="dropdown">
                        <i class="fas fa-user-circle me-1"></i>
                        <?php echo htmlspecialchars($_SESSION['portal_username']); ?>
                    </button>
                    <ul class="dropdown-menu dropdown-menu-end">
                        <li><a class="dropdown-item" href="settings/profile.php"><i class="fas fa-user me-2"></i>Profil</a></li>
                        <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item text-danger" href="logout.php"><i class="fas fa-sign-out-alt me-2"></i>Logout</a></li>
                    </ul>
                </div>
            </div>
        </div>

        <!-- Subscription Info -->
        <?php if ($subscription): ?>
        <div class="subscription-card mb-4">
            <div class="row align-items-center">
                <div class="col-md-8">
                    <div class="d-flex align-items-center mb-2">
                        <span class="plan-name me-3"><?php echo htmlspecialchars($subscription['plan_name']); ?></span>
                        <span class="badge bg-white text-success">Aktif</span>
                    </div>
                    <p class="mb-0 opacity-75">
                        Berlaku hingga: <?php echo date('d F Y', strtotime($subscription['end_date'])); ?>
                        <?php
                        $daysLeft = ceil((strtotime($subscription['end_date']) - time()) / 86400);
                        if ($daysLeft <= 7): ?>
                        <span class="badge bg-warning text-dark ms-2"><?php echo $daysLeft; ?> hari lagi</span>
                        <?php endif; ?>
                    </p>
                </div>
                <div class="col-md-4 text-md-end mt-3 mt-md-0">
                    <small class="d-block opacity-75">Penggunaan</small>
                    <span><?php echo number_format($stats['total_customers']); ?> / <?php echo $subscription['max_users'] == -1 ? 'Unlimited' : number_format($subscription['max_users']); ?> Users</span>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- Statistics -->
        <div class="row g-4 mb-4">
            <div class="col-md-6 col-xl-3">
                <div class="stat-card">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <h3><?php echo number_format($stats['total_customers']); ?></h3>
                            <p>Total Pelanggan</p>
                        </div>
                        <div class="icon green"><i class="fas fa-users"></i></div>
                    </div>
                    <small class="text-success"><i class="fas fa-check-circle me-1"></i><?php echo number_format($stats['active_customers']); ?> aktif</small>
                </div>
            </div>
            <div class="col-md-6 col-xl-3">
                <div class="stat-card">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <h3><?php echo number_format($stats['online_now']); ?></h3>
                            <p>Online Sekarang</p>
                        </div>
                        <div class="icon blue"><i class="fas fa-wifi"></i></div>
                    </div>
                    <small class="text-info"><i class="fas fa-signal me-1"></i>Real-time</small>
                </div>
            </div>
            <div class="col-md-6 col-xl-3">
                <div class="stat-card">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <h3><?php echo formatRupiah($stats['monthly_revenue']); ?></h3>
                            <p>Pendapatan Bulan Ini</p>
                        </div>
                        <div class="icon orange"><i class="fas fa-money-bill-wave"></i></div>
                    </div>
                    <small class="text-warning"><i class="fas fa-clock me-1"></i><?php echo number_format($stats['pending_invoices']); ?> invoice pending</small>
                </div>
            </div>
            <div class="col-md-6 col-xl-3">
                <div class="stat-card">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <h3><?php echo number_format($stats['total_onu']); ?></h3>
                            <p>ONU Terdaftar</p>
                        </div>
                        <div class="icon purple"><i class="fas fa-microchip"></i></div>
                    </div>
                    <small class="text-muted">
                        <i class="fas fa-server me-1"></i><?php echo number_format($stats['total_mikrotik']); ?> MikroTik |
                        <i class="fas fa-network-wired me-1"></i><?php echo number_format($stats['total_olt']); ?> OLT
                    </small>
                </div>
            </div>
        </div>

        <!-- Quick Actions & Activity -->
        <div class="row g-4">
            <div class="col-lg-6">
                <div class="card h-100">
                    <div class="card-header">
                        <i class="fas fa-bolt me-2"></i>Aksi Cepat
                    </div>
                    <div class="card-body">
                        <div class="row g-3">
                            <div class="col-6">
                                <a href="customers/add.php" class="btn btn-outline-success w-100 py-3">
                                    <i class="fas fa-user-plus fa-2x mb-2 d-block"></i>
                                    Tambah Pelanggan
                                </a>
                            </div>
                            <div class="col-6">
                                <a href="customers/online.php" class="btn btn-outline-primary w-100 py-3">
                                    <i class="fas fa-wifi fa-2x mb-2 d-block"></i>
                                    Monitor Online
                                </a>
                            </div>
                            <div class="col-6">
                                <a href="olt/onu-list.php" class="btn btn-outline-info w-100 py-3">
                                    <i class="fas fa-microchip fa-2x mb-2 d-block"></i>
                                    Kelola ONU
                                </a>
                            </div>
                            <div class="col-6">
                                <a href="billing/invoices.php" class="btn btn-outline-warning w-100 py-3">
                                    <i class="fas fa-file-invoice fa-2x mb-2 d-block"></i>
                                    Invoice
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-lg-6">
                <div class="card h-100">
                    <div class="card-header">
                        <i class="fas fa-history me-2"></i>Aktivitas Terbaru
                    </div>
                    <div class="card-body p-0">
                        <?php if (empty($recentActivities)): ?>
                        <div class="text-center py-5 text-muted">
                            <i class="fas fa-history fa-3x mb-3 opacity-50"></i>
                            <p>Belum ada aktivitas</p>
                        </div>
                        <?php else: ?>
                        <div class="px-3">
                            <?php foreach ($recentActivities as $activity): ?>
                            <div class="activity-item d-flex align-items-center">
                                <?php
                                $iconClass = 'bg-secondary';
                                $icon = 'fas fa-info';
                                if (strpos($activity['action'], 'login') !== false) {
                                    $iconClass = 'bg-success'; $icon = 'fas fa-sign-in-alt';
                                } elseif (strpos($activity['action'], 'create') !== false) {
                                    $iconClass = 'bg-primary'; $icon = 'fas fa-plus';
                                } elseif (strpos($activity['action'], 'update') !== false) {
                                    $iconClass = 'bg-warning'; $icon = 'fas fa-edit';
                                } elseif (strpos($activity['action'], 'delete') !== false) {
                                    $iconClass = 'bg-danger'; $icon = 'fas fa-trash';
                                }
                                ?>
                                <div class="activity-icon <?php echo $iconClass; ?> text-white me-3">
                                    <i class="<?php echo $icon; ?>"></i>
                                </div>
                                <div class="flex-grow-1">
                                    <div><?php echo htmlspecialchars($activity['description'] ?? ucfirst($activity['action'] . ' ' . $activity['entity_type'])); ?></div>
                                    <small class="text-muted"><?php echo date('d M Y H:i', strtotime($activity['created_at'])); ?></small>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
