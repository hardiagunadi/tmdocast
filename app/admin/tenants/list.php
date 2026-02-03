<?php
/*
 *********************************************************************************************************
 * daloRADIUS - RADIUS Web Platform
 * Super Admin - Tenant List
 *********************************************************************************************************
 */

require_once(dirname(__FILE__) . '/../library/checklogin.php');
require_once(dirname(__FILE__) . '/../../common/library/db_connect.php');

// Pagination
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$perPage = 20;
$offset = ($page - 1) * $perPage;

// Search & Filter
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$statusFilter = isset($_GET['status']) ? $_GET['status'] : '';

// Build query
$whereClause = "1=1";
$params = [];

if ($search) {
    $searchEsc = $dbSocket->escapeSimple($search);
    $whereClause .= " AND (t.company_name LIKE '%{$searchEsc}%' OR t.domain_prefix LIKE '%{$searchEsc}%' OR t.email LIKE '%{$searchEsc}%')";
}

if ($statusFilter === 'active') {
    $whereClause .= " AND t.is_active = 1";
} elseif ($statusFilter === 'inactive') {
    $whereClause .= " AND t.is_active = 0";
}

// Get total count
$countSql = "SELECT COUNT(*) as total FROM tenants t WHERE {$whereClause}";
$result = $dbSocket->query($countSql);
$row = $result->fetchRow(DB_FETCHMODE_ASSOC);
$totalRecords = $row['total'];
$totalPages = ceil($totalRecords / $perPage);

// Get tenants
$sql = "SELECT t.*,
               ts.plan_id, ts.status as subscription_status, ts.end_date,
               asp.name as plan_name,
               (SELECT COUNT(*) FROM radcheck rc WHERE rc.tenant_id = t.id) as user_count,
               (SELECT COUNT(*) FROM tenant_mikrotik_devices tmd WHERE tmd.tenant_id = t.id) as mikrotik_count
        FROM tenants t
        LEFT JOIN tenant_subscriptions ts ON t.id = ts.tenant_id AND ts.status = 'active'
        LEFT JOIN app_subscription_plans asp ON ts.plan_id = asp.id
        WHERE {$whereClause}
        ORDER BY t.created_at DESC
        LIMIT {$perPage} OFFSET {$offset}";

$tenants = [];
$result = $dbSocket->query($sql);
if ($result && !DB::isError($result)) {
    while ($row = $result->fetchRow(DB_FETCHMODE_ASSOC)) {
        $tenants[] = $row;
    }
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
    <title>Tenant Management - Super Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --sidebar-width: 260px;
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
        .tenant-avatar {
            width: 45px;
            height: 45px;
            border-radius: 10px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: 600;
            font-size: 1.1rem;
        }
        .btn-action {
            padding: 5px 10px;
            font-size: 0.85rem;
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
                <a class="nav-link" href="../home-main.php">
                    <i class="fas fa-tachometer-alt"></i> Dashboard
                </a>
            </li>
        </ul>

        <div class="nav-section">Tenant Management</div>
        <ul class="nav flex-column">
            <li class="nav-item">
                <a class="nav-link active" href="list.php">
                    <i class="fas fa-building"></i> Tenants
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" href="pending.php">
                    <i class="fas fa-user-clock"></i> Pending Approval
                </a>
            </li>
        </ul>

        <div class="nav-section">Subscriptions</div>
        <ul class="nav flex-column">
            <li class="nav-item">
                <a class="nav-link" href="../subscriptions/plans.php">
                    <i class="fas fa-tags"></i> Plans
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" href="../subscriptions/active.php">
                    <i class="fas fa-check-circle"></i> Active Subscriptions
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" href="../subscriptions/payments.php">
                    <i class="fas fa-credit-card"></i> Payments
                </a>
            </li>
        </ul>

        <div class="nav-section">System</div>
        <ul class="nav flex-column">
            <li class="nav-item">
                <a class="nav-link" href="../system/settings.php">
                    <i class="fas fa-cog"></i> Settings
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link text-danger" href="../logout.php">
                    <i class="fas fa-sign-out-alt"></i> Logout
                </a>
            </li>
        </ul>
    </nav>

    <!-- Main Content -->
    <main class="main-content">
        <div class="top-bar">
            <div>
                <h4 class="mb-0">Tenant Management</h4>
                <small class="text-muted">Manage all registered tenants</small>
            </div>
            <a href="add.php" class="btn btn-primary">
                <i class="fas fa-plus me-1"></i> Add Tenant
            </a>
        </div>

        <!-- Filters -->
        <div class="card mb-4">
            <div class="card-body">
                <form method="get" class="row g-3">
                    <div class="col-md-6">
                        <div class="input-group">
                            <span class="input-group-text"><i class="fas fa-search"></i></span>
                            <input type="text" class="form-control" name="search" placeholder="Search company, domain, or email..." value="<?php echo htmlspecialchars($search); ?>">
                        </div>
                    </div>
                    <div class="col-md-3">
                        <select class="form-select" name="status">
                            <option value="">All Status</option>
                            <option value="active" <?php echo $statusFilter === 'active' ? 'selected' : ''; ?>>Active</option>
                            <option value="inactive" <?php echo $statusFilter === 'inactive' ? 'selected' : ''; ?>>Inactive</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <button type="submit" class="btn btn-primary me-2"><i class="fas fa-filter me-1"></i> Filter</button>
                        <a href="list.php" class="btn btn-outline-secondary"><i class="fas fa-times me-1"></i> Reset</a>
                    </div>
                </form>
            </div>
        </div>

        <!-- Tenant List -->
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span><i class="fas fa-building me-2"></i>Tenants (<?php echo number_format($totalRecords); ?>)</span>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead class="table-light">
                            <tr>
                                <th style="width: 50px;">#</th>
                                <th>Tenant</th>
                                <th>Plan</th>
                                <th>Users</th>
                                <th>MikroTik</th>
                                <th>Status</th>
                                <th>Subscription</th>
                                <th style="width: 150px;">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($tenants)): ?>
                            <tr>
                                <td colspan="8" class="text-center py-5 text-muted">
                                    <i class="fas fa-building fa-3x mb-3 d-block opacity-50"></i>
                                    No tenants found
                                </td>
                            </tr>
                            <?php else: ?>
                            <?php foreach ($tenants as $index => $tenant): ?>
                            <tr>
                                <td><?php echo $offset + $index + 1; ?></td>
                                <td>
                                    <div class="d-flex align-items-center">
                                        <div class="tenant-avatar me-3">
                                            <?php echo strtoupper(substr($tenant['company_name'], 0, 2)); ?>
                                        </div>
                                        <div>
                                            <strong><?php echo htmlspecialchars($tenant['company_name']); ?></strong><br>
                                            <small class="text-muted">
                                                <i class="fas fa-globe me-1"></i><?php echo htmlspecialchars($tenant['domain_prefix']); ?>
                                                <span class="mx-2">|</span>
                                                <i class="fas fa-envelope me-1"></i><?php echo htmlspecialchars($tenant['email']); ?>
                                            </small>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <?php if ($tenant['plan_name']): ?>
                                    <span class="badge bg-primary"><?php echo htmlspecialchars($tenant['plan_name']); ?></span>
                                    <?php else: ?>
                                    <span class="badge bg-secondary">No Plan</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <i class="fas fa-users text-info me-1"></i>
                                    <?php echo number_format($tenant['user_count']); ?>
                                </td>
                                <td>
                                    <i class="fas fa-server text-success me-1"></i>
                                    <?php echo number_format($tenant['mikrotik_count']); ?>
                                </td>
                                <td>
                                    <?php if ($tenant['is_active']): ?>
                                    <span class="badge bg-success badge-status">Active</span>
                                    <?php else: ?>
                                    <span class="badge bg-secondary badge-status">Inactive</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($tenant['end_date']): ?>
                                    <?php
                                    $endDate = strtotime($tenant['end_date']);
                                    $daysLeft = ceil(($endDate - time()) / 86400);
                                    if ($daysLeft < 0): ?>
                                    <span class="text-danger"><i class="fas fa-exclamation-circle"></i> Expired</span>
                                    <?php elseif ($daysLeft <= 7): ?>
                                    <span class="text-warning"><i class="fas fa-clock"></i> <?php echo $daysLeft; ?> days left</span>
                                    <?php else: ?>
                                    <span class="text-muted"><?php echo date('d M Y', $endDate); ?></span>
                                    <?php endif; ?>
                                    <?php else: ?>
                                    <span class="text-muted">-</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <a href="view.php?id=<?php echo $tenant['id']; ?>" class="btn btn-sm btn-info btn-action" title="View">
                                        <i class="fas fa-eye"></i>
                                    </a>
                                    <a href="edit.php?id=<?php echo $tenant['id']; ?>" class="btn btn-sm btn-warning btn-action" title="Edit">
                                        <i class="fas fa-edit"></i>
                                    </a>
                                    <button type="button" class="btn btn-sm btn-danger btn-action" onclick="confirmDelete(<?php echo $tenant['id']; ?>, '<?php echo htmlspecialchars($tenant['company_name']); ?>')" title="Delete">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <?php if ($totalPages > 1): ?>
            <div class="card-footer">
                <nav>
                    <ul class="pagination mb-0 justify-content-center">
                        <?php if ($page > 1): ?>
                        <li class="page-item">
                            <a class="page-link" href="?page=<?php echo $page - 1; ?>&search=<?php echo urlencode($search); ?>&status=<?php echo urlencode($statusFilter); ?>">
                                <i class="fas fa-chevron-left"></i>
                            </a>
                        </li>
                        <?php endif; ?>

                        <?php for ($i = max(1, $page - 2); $i <= min($totalPages, $page + 2); $i++): ?>
                        <li class="page-item <?php echo $i === $page ? 'active' : ''; ?>">
                            <a class="page-link" href="?page=<?php echo $i; ?>&search=<?php echo urlencode($search); ?>&status=<?php echo urlencode($statusFilter); ?>">
                                <?php echo $i; ?>
                            </a>
                        </li>
                        <?php endfor; ?>

                        <?php if ($page < $totalPages): ?>
                        <li class="page-item">
                            <a class="page-link" href="?page=<?php echo $page + 1; ?>&search=<?php echo urlencode($search); ?>&status=<?php echo urlencode($statusFilter); ?>">
                                <i class="fas fa-chevron-right"></i>
                            </a>
                        </li>
                        <?php endif; ?>
                    </ul>
                </nav>
            </div>
            <?php endif; ?>
        </div>
    </main>

    <!-- Delete Confirmation Modal -->
    <div class="modal fade" id="deleteModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="fas fa-exclamation-triangle text-danger me-2"></i>Confirm Delete</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p>Are you sure you want to delete tenant <strong id="deleteTenantName"></strong>?</p>
                    <p class="text-danger mb-0"><i class="fas fa-warning me-1"></i>This action cannot be undone. All tenant data will be permanently deleted.</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <form id="deleteForm" method="post" action="delete.php" class="d-inline">
                        <input type="hidden" name="id" id="deleteTenantId">
                        <input type="hidden" name="csrf_token" value="<?php echo dalo_admin_csrf_token(); ?>">
                        <button type="submit" class="btn btn-danger">
                            <i class="fas fa-trash me-1"></i>Delete
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function confirmDelete(id, name) {
            document.getElementById('deleteTenantId').value = id;
            document.getElementById('deleteTenantName').textContent = name;
            new bootstrap.Modal(document.getElementById('deleteModal')).show();
        }
    </script>
</body>
</html>
