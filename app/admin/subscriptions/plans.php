<?php
/*
 *********************************************************************************************************
 * daloRADIUS - RADIUS Web Platform
 * Super Admin - Subscription Plans Management
 *********************************************************************************************************
 */

require_once(dirname(__FILE__) . '/../library/checklogin.php');
require_once(dirname(__FILE__) . '/../../common/library/db_connect.php');

$error = '';
$success = '';

// Handle form submission for adding/editing plan
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || !dalo_admin_verify_csrf($_POST['csrf_token'])) {
        $error = 'Invalid security token';
    } else {
        $action = $_POST['action'] ?? '';

        if ($action === 'add' || $action === 'edit') {
            $planId = intval($_POST['plan_id'] ?? 0);
            $name = trim($_POST['name'] ?? '');
            $description = trim($_POST['description'] ?? '');
            $price = floatval($_POST['price'] ?? 0);
            $billingDays = intval($_POST['billing_period_days'] ?? 30);
            $maxUsers = intval($_POST['max_users'] ?? 0);
            $maxMikrotik = intval($_POST['max_mikrotik'] ?? 0);
            $maxOlt = intval($_POST['max_olt'] ?? 0);
            $features = trim($_POST['features'] ?? '[]');
            $isActive = isset($_POST['is_active']) ? 1 : 0;

            // Validate features JSON
            $featuresArray = json_decode($features, true);
            if ($featuresArray === null && $features !== '[]') {
                $error = 'Invalid features JSON format';
            } elseif (empty($name)) {
                $error = 'Plan name is required';
            } else {
                if ($action === 'add') {
                    $sql = sprintf(
                        "INSERT INTO app_subscription_plans (name, description, price, billing_period_days, max_users, max_mikrotik, max_olt, features, is_active, created_at)
                         VALUES ('%s', '%s', %.2f, %d, %d, %d, %d, '%s', %d, NOW())",
                        $dbSocket->escapeSimple($name),
                        $dbSocket->escapeSimple($description),
                        $price,
                        $billingDays,
                        $maxUsers,
                        $maxMikrotik,
                        $maxOlt,
                        $dbSocket->escapeSimple($features),
                        $isActive
                    );
                    $success = 'Plan created successfully';
                } else {
                    $sql = sprintf(
                        "UPDATE app_subscription_plans SET
                         name = '%s', description = '%s', price = %.2f, billing_period_days = %d,
                         max_users = %d, max_mikrotik = %d, max_olt = %d, features = '%s',
                         is_active = %d, updated_at = NOW()
                         WHERE id = %d",
                        $dbSocket->escapeSimple($name),
                        $dbSocket->escapeSimple($description),
                        $price,
                        $billingDays,
                        $maxUsers,
                        $maxMikrotik,
                        $maxOlt,
                        $dbSocket->escapeSimple($features),
                        $isActive,
                        $planId
                    );
                    $success = 'Plan updated successfully';
                }

                $result = $dbSocket->query($sql);
                if (DB::isError($result)) {
                    $error = 'Database error: ' . $result->getMessage();
                    $success = '';
                }
            }
        } elseif ($action === 'delete') {
            $planId = intval($_POST['plan_id'] ?? 0);
            // Check if plan is in use
            $checkResult = $dbSocket->query("SELECT COUNT(*) as cnt FROM tenant_subscriptions WHERE plan_id = " . $planId);
            $checkRow = $checkResult->fetchRow(DB_FETCHMODE_ASSOC);
            if ($checkRow['cnt'] > 0) {
                $error = 'Cannot delete plan that is currently in use by tenants';
            } else {
                $dbSocket->query("DELETE FROM app_subscription_plans WHERE id = " . $planId);
                $success = 'Plan deleted successfully';
            }
        }
    }
}

// Get all plans
$plans = [];
$result = $dbSocket->query("SELECT * FROM app_subscription_plans ORDER BY price ASC");
if ($result && !DB::isError($result)) {
    while ($row = $result->fetchRow(DB_FETCHMODE_ASSOC)) {
        // Count subscribers
        $subResult = $dbSocket->query("SELECT COUNT(*) as cnt FROM tenant_subscriptions WHERE plan_id = " . $row['id'] . " AND status = 'active'");
        $subRow = $subResult->fetchRow(DB_FETCHMODE_ASSOC);
        $row['subscriber_count'] = $subRow['cnt'];
        $plans[] = $row;
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
    <title>Subscription Plans - Super Admin</title>
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
        .plan-card { border-radius: 15px; transition: all 0.3s; }
        .plan-card:hover { transform: translateY(-5px); box-shadow: 0 10px 30px rgba(0,0,0,0.1); }
        .plan-header { padding: 25px; text-align: center; border-radius: 15px 15px 0 0; color: white; }
        .plan-header.starter { background: linear-gradient(135deg, #11998e 0%, #38ef7d 100%); }
        .plan-header.professional { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); }
        .plan-header.enterprise { background: linear-gradient(135deg, #f46b45 0%, #eea849 100%); }
        .plan-header.unlimited { background: linear-gradient(135deg, #eb3349 0%, #f45c43 100%); }
        .plan-price { font-size: 2rem; font-weight: 700; }
        .plan-features { padding: 20px; }
        .plan-features li { padding: 8px 0; border-bottom: 1px solid #eee; }
        .plan-features li:last-child { border-bottom: none; }
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
            <li class="nav-item"><a class="nav-link active" href="plans.php"><i class="fas fa-tags"></i> Plans</a></li>
            <li class="nav-item"><a class="nav-link" href="active.php"><i class="fas fa-check-circle"></i> Active</a></li>
            <li class="nav-item"><a class="nav-link" href="payments.php"><i class="fas fa-credit-card"></i> Payments</a></li>
        </ul>
        <div class="nav-section">System</div>
        <ul class="nav flex-column">
            <li class="nav-item"><a class="nav-link text-danger" href="../logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
        </ul>
    </nav>

    <!-- Main Content -->
    <main class="main-content">
        <div class="top-bar d-flex justify-content-between align-items-center">
            <div>
                <h4 class="mb-0">Subscription Plans</h4>
                <small class="text-muted">Manage pricing and features</small>
            </div>
            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#planModal" onclick="openAddModal()">
                <i class="fas fa-plus me-1"></i> Add Plan
            </button>
        </div>

        <?php if ($error): ?>
        <div class="alert alert-danger"><i class="fas fa-exclamation-circle me-2"></i><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>
        <?php if ($success): ?>
        <div class="alert alert-success"><i class="fas fa-check-circle me-2"></i><?php echo htmlspecialchars($success); ?></div>
        <?php endif; ?>

        <!-- Plans Grid -->
        <div class="row g-4">
            <?php foreach ($plans as $index => $plan):
                $headerClass = ['starter', 'professional', 'enterprise', 'unlimited'][$index % 4];
            ?>
            <div class="col-lg-3 col-md-6">
                <div class="card plan-card h-100">
                    <div class="plan-header <?php echo $headerClass; ?>">
                        <h5 class="mb-2"><?php echo htmlspecialchars($plan['name']); ?></h5>
                        <div class="plan-price"><?php echo formatRupiah($plan['price']); ?></div>
                        <small>/<?php echo $plan['billing_period_days']; ?> hari</small>
                        <?php if (!$plan['is_active']): ?>
                        <span class="badge bg-dark mt-2">Inactive</span>
                        <?php endif; ?>
                    </div>
                    <div class="plan-features">
                        <ul class="list-unstyled mb-0">
                            <li><i class="fas fa-users text-primary me-2"></i>
                                <?php echo $plan['max_users'] == -1 ? 'Unlimited' : number_format($plan['max_users']); ?> Users
                            </li>
                            <li><i class="fas fa-server text-success me-2"></i>
                                <?php echo $plan['max_mikrotik'] == -1 ? 'Unlimited' : number_format($plan['max_mikrotik']); ?> MikroTik
                            </li>
                            <li><i class="fas fa-network-wired text-info me-2"></i>
                                <?php echo $plan['max_olt'] == -1 ? 'Unlimited' : number_format($plan['max_olt']); ?> OLT
                            </li>
                            <li><i class="fas fa-users-cog text-warning me-2"></i>
                                <?php echo number_format($plan['subscriber_count']); ?> Subscribers
                            </li>
                        </ul>
                    </div>
                    <div class="card-footer bg-white border-top">
                        <div class="btn-group w-100">
                            <button class="btn btn-outline-primary btn-sm" onclick="openEditModal(<?php echo htmlspecialchars(json_encode($plan)); ?>)">
                                <i class="fas fa-edit"></i> Edit
                            </button>
                            <button class="btn btn-outline-danger btn-sm" onclick="confirmDelete(<?php echo $plan['id']; ?>, '<?php echo htmlspecialchars($plan['name']); ?>')">
                                <i class="fas fa-trash"></i> Delete
                            </button>
                        </div>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>

            <?php if (empty($plans)): ?>
            <div class="col-12">
                <div class="text-center py-5 text-muted">
                    <i class="fas fa-tags fa-4x mb-3 opacity-50"></i>
                    <p>No subscription plans created yet</p>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </main>

    <!-- Plan Modal -->
    <div class="modal fade" id="planModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <form method="post">
                    <input type="hidden" name="csrf_token" value="<?php echo dalo_admin_csrf_token(); ?>">
                    <input type="hidden" name="action" id="modalAction" value="add">
                    <input type="hidden" name="plan_id" id="planId" value="0">

                    <div class="modal-header">
                        <h5 class="modal-title" id="modalTitle"><i class="fas fa-tags me-2"></i>Add Plan</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label">Plan Name <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" name="name" id="planName" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Price (Rp)</label>
                                <input type="number" class="form-control" name="price" id="planPrice" min="0" step="1000" value="0">
                            </div>
                            <div class="col-12">
                                <label class="form-label">Description</label>
                                <textarea class="form-control" name="description" id="planDescription" rows="2"></textarea>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">Billing Period (Days)</label>
                                <input type="number" class="form-control" name="billing_period_days" id="planBillingDays" min="1" value="30">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">Max Users</label>
                                <input type="number" class="form-control" name="max_users" id="planMaxUsers" min="-1" value="100">
                                <small class="text-muted">-1 = Unlimited</small>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">Max MikroTik</label>
                                <input type="number" class="form-control" name="max_mikrotik" id="planMaxMikrotik" min="-1" value="5">
                                <small class="text-muted">-1 = Unlimited</small>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">Max OLT</label>
                                <input type="number" class="form-control" name="max_olt" id="planMaxOlt" min="-1" value="1">
                                <small class="text-muted">-1 = Unlimited</small>
                            </div>
                            <div class="col-12">
                                <label class="form-label">Features (JSON Array)</label>
                                <textarea class="form-control font-monospace" name="features" id="planFeatures" rows="3">["pppoe_management", "bandwidth_management", "reporting"]</textarea>
                                <small class="text-muted">Available: pppoe_management, bandwidth_management, reporting, hotspot, olt_management, api_access, white_label</small>
                            </div>
                            <div class="col-12">
                                <div class="form-check">
                                    <input type="checkbox" class="form-check-input" name="is_active" id="planIsActive" checked>
                                    <label class="form-check-label" for="planIsActive">Active</label>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary"><i class="fas fa-save me-1"></i>Save</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Delete Modal -->
    <div class="modal fade" id="deleteModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title text-danger"><i class="fas fa-exclamation-triangle me-2"></i>Delete Plan</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p>Are you sure you want to delete <strong id="deletePlanName"></strong>?</p>
                </div>
                <div class="modal-footer">
                    <form method="post">
                        <input type="hidden" name="csrf_token" value="<?php echo dalo_admin_csrf_token(); ?>">
                        <input type="hidden" name="action" value="delete">
                        <input type="hidden" name="plan_id" id="deletePlanId">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-danger"><i class="fas fa-trash me-1"></i>Delete</button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function openAddModal() {
            document.getElementById('modalAction').value = 'add';
            document.getElementById('modalTitle').innerHTML = '<i class="fas fa-tags me-2"></i>Add Plan';
            document.getElementById('planId').value = '0';
            document.getElementById('planName').value = '';
            document.getElementById('planDescription').value = '';
            document.getElementById('planPrice').value = '0';
            document.getElementById('planBillingDays').value = '30';
            document.getElementById('planMaxUsers').value = '100';
            document.getElementById('planMaxMikrotik').value = '5';
            document.getElementById('planMaxOlt').value = '1';
            document.getElementById('planFeatures').value = '["pppoe_management", "bandwidth_management", "reporting"]';
            document.getElementById('planIsActive').checked = true;
        }

        function openEditModal(plan) {
            document.getElementById('modalAction').value = 'edit';
            document.getElementById('modalTitle').innerHTML = '<i class="fas fa-edit me-2"></i>Edit Plan';
            document.getElementById('planId').value = plan.id;
            document.getElementById('planName').value = plan.name;
            document.getElementById('planDescription').value = plan.description || '';
            document.getElementById('planPrice').value = plan.price;
            document.getElementById('planBillingDays').value = plan.billing_period_days;
            document.getElementById('planMaxUsers').value = plan.max_users;
            document.getElementById('planMaxMikrotik').value = plan.max_mikrotik;
            document.getElementById('planMaxOlt').value = plan.max_olt;
            document.getElementById('planFeatures').value = plan.features || '[]';
            document.getElementById('planIsActive').checked = plan.is_active == 1;
            new bootstrap.Modal(document.getElementById('planModal')).show();
        }

        function confirmDelete(id, name) {
            document.getElementById('deletePlanId').value = id;
            document.getElementById('deletePlanName').textContent = name;
            new bootstrap.Modal(document.getElementById('deleteModal')).show();
        }
    </script>
</body>
</html>
