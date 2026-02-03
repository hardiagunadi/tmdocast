<?php
/*
 *********************************************************************************************************
 * daloRADIUS - RADIUS Web Platform
 * Super Admin - Payment Transactions
 *********************************************************************************************************
 */

require_once(dirname(__FILE__) . '/../library/checklogin.php');
require_once(dirname(__FILE__) . '/../../common/library/db_connect.php');

// Pagination
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$perPage = 25;
$offset = ($page - 1) * $perPage;

// Filters
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$statusFilter = isset($_GET['status']) ? $_GET['status'] : '';
$typeFilter = isset($_GET['type']) ? $_GET['type'] : '';
$dateFrom = isset($_GET['date_from']) ? $_GET['date_from'] : '';
$dateTo = isset($_GET['date_to']) ? $_GET['date_to'] : '';

// Build query
$whereClause = "1=1";

if ($search) {
    $searchEsc = $dbSocket->escapeSimple($search);
    $whereClause .= " AND (t.company_name LIKE '%{$searchEsc}%' OR pt.transaction_id LIKE '%{$searchEsc}%' OR pt.external_id LIKE '%{$searchEsc}%')";
}
if ($statusFilter) {
    $whereClause .= " AND pt.status = '" . $dbSocket->escapeSimple($statusFilter) . "'";
}
if ($typeFilter) {
    $whereClause .= " AND pt.payment_type = '" . $dbSocket->escapeSimple($typeFilter) . "'";
}
if ($dateFrom) {
    $whereClause .= " AND DATE(pt.created_at) >= '" . $dbSocket->escapeSimple($dateFrom) . "'";
}
if ($dateTo) {
    $whereClause .= " AND DATE(pt.created_at) <= '" . $dbSocket->escapeSimple($dateTo) . "'";
}

// Get total count
$countSql = "SELECT COUNT(*) as total FROM payment_transactions pt LEFT JOIN tenants t ON pt.tenant_id = t.id WHERE {$whereClause}";
$result = $dbSocket->query($countSql);
$row = $result->fetchRow(DB_FETCHMODE_ASSOC);
$totalRecords = $row['total'];
$totalPages = ceil($totalRecords / $perPage);

// Get statistics
$statsResult = $dbSocket->query("
    SELECT
        SUM(CASE WHEN status = 'completed' THEN amount ELSE 0 END) as total_completed,
        SUM(CASE WHEN status = 'pending' THEN amount ELSE 0 END) as total_pending,
        COUNT(CASE WHEN status = 'completed' THEN 1 END) as count_completed,
        COUNT(CASE WHEN status = 'pending' THEN 1 END) as count_pending,
        COUNT(CASE WHEN status = 'failed' THEN 1 END) as count_failed
    FROM payment_transactions
    WHERE payment_type = 'subscription'
");
$stats = $statsResult->fetchRow(DB_FETCHMODE_ASSOC);

// Get payments
$sql = "SELECT pt.*, t.company_name, t.email as tenant_email, pg.name as gateway_name
        FROM payment_transactions pt
        LEFT JOIN tenants t ON pt.tenant_id = t.id
        LEFT JOIN payment_gateways pg ON pt.gateway_id = pg.id
        WHERE {$whereClause}
        ORDER BY pt.created_at DESC
        LIMIT {$perPage} OFFSET {$offset}";

$payments = [];
$result = $dbSocket->query($sql);
if ($result && !DB::isError($result)) {
    while ($row = $result->fetchRow(DB_FETCHMODE_ASSOC)) {
        $payments[] = $row;
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
    <title>Payment Transactions - Super Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root { --sidebar-width: 260px; }
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background-color: #f4f6f9; }
        .sidebar { position: fixed; top: 0; left: 0; height: 100vh; width: var(--sidebar-width); background: linear-gradient(135deg, #1e3c72 0%, #2a5298 100%); z-index: 1000; overflow-y: auto; }
        .sidebar-header { padding: 20px; text-align: center; border-bottom: 1px solid rgba(255,255,255,0.1); }
        .sidebar-header h4 { color: white; margin: 0; }
        .nav-link { color: rgba(255,255,255,0.8) !important; padding: 12px 20px; border-left: 3px solid transparent; }
        .nav-link:hover, .nav-link.active { background: rgba(255,255,255,0.1); color: white !important; border-left-color: #fff; }
        .nav-link i { width: 25px; text-align: center; margin-right: 10px; }
        .main-content { margin-left: var(--sidebar-width); padding: 20px 30px; min-height: 100vh; }
        .top-bar { background: white; padding: 15px 25px; margin: -20px -30px 20px; box-shadow: 0 2px 10px rgba(0,0,0,0.05); }
        .card { border: none; border-radius: 15px; box-shadow: 0 5px 15px rgba(0,0,0,0.05); }
        .card-header { background: white; border-bottom: 1px solid #eee; padding: 15px 20px; font-weight: 600; }
        .nav-section { padding: 15px 20px 5px; color: rgba(255,255,255,0.5); font-size: 0.75rem; text-transform: uppercase; }
        .stat-card { background: white; border-radius: 12px; padding: 20px; text-align: center; }
        .stat-card .icon { font-size: 2rem; margin-bottom: 10px; }
        .stat-card h4 { font-weight: 700; margin-bottom: 5px; }
        .badge-status { padding: 5px 12px; border-radius: 20px; font-weight: 500; }
    </style>
</head>
<body>
    <!-- Sidebar -->
    <nav class="sidebar">
        <div class="sidebar-header">
            <i class="fas fa-shield-alt fa-2x text-white mb-2"></i>
            <h4>Super Admin</h4>
            <small class="text-white-50">daloRADIUS SaaS</small>
        </div>
        <div class="nav-section">Dashboard</div>
        <ul class="nav flex-column">
            <li class="nav-item"><a class="nav-link" href="../home-main.php"><i class="fas fa-tachometer-alt"></i> Dashboard</a></li>
        </ul>
        <div class="nav-section">Tenant Management</div>
        <ul class="nav flex-column">
            <li class="nav-item"><a class="nav-link" href="../tenants/list.php"><i class="fas fa-building"></i> Tenants</a></li>
        </ul>
        <div class="nav-section">Subscriptions</div>
        <ul class="nav flex-column">
            <li class="nav-item"><a class="nav-link" href="plans.php"><i class="fas fa-tags"></i> Plans</a></li>
            <li class="nav-item"><a class="nav-link" href="active.php"><i class="fas fa-check-circle"></i> Active</a></li>
            <li class="nav-item"><a class="nav-link active" href="payments.php"><i class="fas fa-credit-card"></i> Payments</a></li>
        </ul>
        <div class="nav-section">System</div>
        <ul class="nav flex-column">
            <li class="nav-item"><a class="nav-link text-danger" href="../logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
        </ul>
    </nav>

    <!-- Main Content -->
    <main class="main-content">
        <div class="top-bar">
            <h4 class="mb-0">Payment Transactions</h4>
            <small class="text-muted">Monitor all subscription payments</small>
        </div>

        <!-- Stats -->
        <div class="row g-4 mb-4">
            <div class="col-md-4">
                <div class="stat-card">
                    <div class="icon text-success"><i class="fas fa-check-circle"></i></div>
                    <h4 class="text-success"><?php echo formatRupiah($stats['total_completed'] ?? 0); ?></h4>
                    <p class="text-muted mb-0"><?php echo number_format($stats['count_completed'] ?? 0); ?> Completed</p>
                </div>
            </div>
            <div class="col-md-4">
                <div class="stat-card">
                    <div class="icon text-warning"><i class="fas fa-clock"></i></div>
                    <h4 class="text-warning"><?php echo formatRupiah($stats['total_pending'] ?? 0); ?></h4>
                    <p class="text-muted mb-0"><?php echo number_format($stats['count_pending'] ?? 0); ?> Pending</p>
                </div>
            </div>
            <div class="col-md-4">
                <div class="stat-card">
                    <div class="icon text-danger"><i class="fas fa-times-circle"></i></div>
                    <h4 class="text-danger"><?php echo number_format($stats['count_failed'] ?? 0); ?></h4>
                    <p class="text-muted mb-0">Failed Transactions</p>
                </div>
            </div>
        </div>

        <!-- Filters -->
        <div class="card mb-4">
            <div class="card-body">
                <form method="get" class="row g-3">
                    <div class="col-md-3">
                        <input type="text" class="form-control" name="search" placeholder="Search transaction..." value="<?php echo htmlspecialchars($search); ?>">
                    </div>
                    <div class="col-md-2">
                        <select class="form-select" name="status">
                            <option value="">All Status</option>
                            <option value="completed" <?php echo $statusFilter === 'completed' ? 'selected' : ''; ?>>Completed</option>
                            <option value="pending" <?php echo $statusFilter === 'pending' ? 'selected' : ''; ?>>Pending</option>
                            <option value="failed" <?php echo $statusFilter === 'failed' ? 'selected' : ''; ?>>Failed</option>
                            <option value="expired" <?php echo $statusFilter === 'expired' ? 'selected' : ''; ?>>Expired</option>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <select class="form-select" name="type">
                            <option value="">All Types</option>
                            <option value="subscription" <?php echo $typeFilter === 'subscription' ? 'selected' : ''; ?>>Subscription</option>
                            <option value="customer" <?php echo $typeFilter === 'customer' ? 'selected' : ''; ?>>Customer</option>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <input type="date" class="form-control" name="date_from" value="<?php echo htmlspecialchars($dateFrom); ?>" placeholder="From">
                    </div>
                    <div class="col-md-2">
                        <input type="date" class="form-control" name="date_to" value="<?php echo htmlspecialchars($dateTo); ?>" placeholder="To">
                    </div>
                    <div class="col-md-1">
                        <button type="submit" class="btn btn-primary w-100"><i class="fas fa-filter"></i></button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Payments Table -->
        <div class="card">
            <div class="card-header">
                <i class="fas fa-credit-card me-2"></i>Transactions (<?php echo number_format($totalRecords); ?>)
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Transaction ID</th>
                                <th>Tenant</th>
                                <th>Amount</th>
                                <th>Method</th>
                                <th>Status</th>
                                <th>Date</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($payments)): ?>
                            <tr>
                                <td colspan="7" class="text-center py-5 text-muted">
                                    <i class="fas fa-credit-card fa-3x mb-3 opacity-50 d-block"></i>
                                    No transactions found
                                </td>
                            </tr>
                            <?php else: ?>
                            <?php foreach ($payments as $payment): ?>
                            <tr>
                                <td>
                                    <code><?php echo htmlspecialchars($payment['transaction_id']); ?></code>
                                    <?php if ($payment['external_id']): ?>
                                    <br><small class="text-muted"><?php echo htmlspecialchars($payment['external_id']); ?></small>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <strong><?php echo htmlspecialchars($payment['company_name'] ?? 'N/A'); ?></strong>
                                    <br><small class="text-muted"><?php echo htmlspecialchars($payment['tenant_email'] ?? ''); ?></small>
                                </td>
                                <td>
                                    <strong><?php echo formatRupiah($payment['amount']); ?></strong>
                                    <br><small class="badge bg-secondary"><?php echo ucfirst($payment['payment_type']); ?></small>
                                </td>
                                <td>
                                    <span class="badge bg-info"><?php echo strtoupper($payment['payment_method']); ?></span>
                                    <?php if ($payment['gateway_name']): ?>
                                    <br><small class="text-muted"><?php echo htmlspecialchars($payment['gateway_name']); ?></small>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php
                                    $statusColors = ['completed' => 'success', 'pending' => 'warning', 'failed' => 'danger', 'expired' => 'secondary', 'refunded' => 'info'];
                                    $color = $statusColors[$payment['status']] ?? 'secondary';
                                    ?>
                                    <span class="badge bg-<?php echo $color; ?> badge-status">
                                        <?php echo ucfirst($payment['status']); ?>
                                    </span>
                                </td>
                                <td>
                                    <?php echo date('d M Y', strtotime($payment['created_at'])); ?>
                                    <br><small class="text-muted"><?php echo date('H:i:s', strtotime($payment['created_at'])); ?></small>
                                </td>
                                <td>
                                    <button class="btn btn-sm btn-outline-info" onclick="viewDetail(<?php echo htmlspecialchars(json_encode($payment)); ?>)">
                                        <i class="fas fa-eye"></i>
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
                        <?php for ($i = max(1, $page - 2); $i <= min($totalPages, $page + 2); $i++): ?>
                        <li class="page-item <?php echo $i === $page ? 'active' : ''; ?>">
                            <a class="page-link" href="?page=<?php echo $i; ?>&search=<?php echo urlencode($search); ?>&status=<?php echo urlencode($statusFilter); ?>&type=<?php echo urlencode($typeFilter); ?>&date_from=<?php echo urlencode($dateFrom); ?>&date_to=<?php echo urlencode($dateTo); ?>">
                                <?php echo $i; ?>
                            </a>
                        </li>
                        <?php endfor; ?>
                    </ul>
                </nav>
            </div>
            <?php endif; ?>
        </div>
    </main>

    <!-- Detail Modal -->
    <div class="modal fade" id="detailModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="fas fa-receipt me-2"></i>Transaction Detail</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body" id="detailContent"></div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function viewDetail(payment) {
            const formatRupiah = (num) => 'Rp ' + new Intl.NumberFormat('id-ID').format(num);
            const html = `
                <table class="table table-sm">
                    <tr><th width="40%">Transaction ID</th><td><code>${payment.transaction_id}</code></td></tr>
                    <tr><th>External ID</th><td>${payment.external_id || '-'}</td></tr>
                    <tr><th>Amount</th><td><strong>${formatRupiah(payment.amount)}</strong></td></tr>
                    <tr><th>Type</th><td>${payment.payment_type}</td></tr>
                    <tr><th>Method</th><td>${payment.payment_method}</td></tr>
                    <tr><th>Status</th><td><span class="badge bg-${payment.status === 'completed' ? 'success' : payment.status === 'pending' ? 'warning' : 'danger'}">${payment.status}</span></td></tr>
                    <tr><th>Created</th><td>${payment.created_at}</td></tr>
                    <tr><th>Completed</th><td>${payment.completed_at || '-'}</td></tr>
                    ${payment.metadata ? `<tr><th>Metadata</th><td><pre class="mb-0 small">${JSON.stringify(JSON.parse(payment.metadata), null, 2)}</pre></td></tr>` : ''}
                </table>
            `;
            document.getElementById('detailContent').innerHTML = html;
            new bootstrap.Modal(document.getElementById('detailModal')).show();
        }
    </script>
</body>
</html>
