<?php
/*
 *********************************************************************************************************
 * daloRADIUS - RADIUS Web Platform
 * Super Admin - Add New Tenant
 *********************************************************************************************************
 */

require_once(dirname(__FILE__) . '/../library/checklogin.php');
require_once(dirname(__FILE__) . '/../../common/library/db_connect.php');

$error = '';
$success = '';

// Get subscription plans
$plans = [];
$result = $dbSocket->query("SELECT * FROM app_subscription_plans WHERE is_active = 1 ORDER BY price ASC");
if ($result && !DB::isError($result)) {
    while ($row = $result->fetchRow(DB_FETCHMODE_ASSOC)) {
        $plans[] = $row;
    }
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Verify CSRF
    if (!isset($_POST['csrf_token']) || !dalo_admin_verify_csrf($_POST['csrf_token'])) {
        $error = 'Invalid security token';
    } else {
        // Get form data
        $companyName = trim($_POST['company_name'] ?? '');
        $domainPrefix = strtolower(trim($_POST['domain_prefix'] ?? ''));
        $email = trim($_POST['email'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        $address = trim($_POST['address'] ?? '');
        $planId = intval($_POST['plan_id'] ?? 0);
        $isActive = isset($_POST['is_active']) ? 1 : 0;

        // Admin account
        $adminUsername = trim($_POST['admin_username'] ?? '');
        $adminPassword = $_POST['admin_password'] ?? '';
        $adminFullname = trim($_POST['admin_fullname'] ?? '');
        $adminEmail = trim($_POST['admin_email'] ?? '');

        // Validation
        if (empty($companyName) || empty($domainPrefix) || empty($email)) {
            $error = 'Company name, domain prefix, and email are required';
        } elseif (!preg_match('/^[a-z0-9\-]+$/', $domainPrefix)) {
            $error = 'Domain prefix can only contain lowercase letters, numbers, and hyphens';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = 'Invalid email address';
        } elseif (empty($adminUsername) || empty($adminPassword) || empty($adminFullname)) {
            $error = 'Admin account details are required';
        } elseif (strlen($adminPassword) < 8) {
            $error = 'Admin password must be at least 8 characters';
        } else {
            // Check domain uniqueness
            $checkSql = "SELECT id FROM tenants WHERE domain_prefix = '" . $dbSocket->escapeSimple($domainPrefix) . "'";
            $result = $dbSocket->query($checkSql);
            if ($result && !DB::isError($result) && $result->numRows() > 0) {
                $error = 'Domain prefix is already taken';
            } else {
                // Create tenant
                $insertSql = sprintf(
                    "INSERT INTO tenants (company_name, domain_prefix, email, phone, address, is_active, created_at)
                     VALUES ('%s', '%s', '%s', '%s', '%s', %d, NOW())",
                    $dbSocket->escapeSimple($companyName),
                    $dbSocket->escapeSimple($domainPrefix),
                    $dbSocket->escapeSimple($email),
                    $dbSocket->escapeSimple($phone),
                    $dbSocket->escapeSimple($address),
                    $isActive
                );

                $result = $dbSocket->query($insertSql);
                if (DB::isError($result)) {
                    $error = 'Failed to create tenant: ' . $result->getMessage();
                } else {
                    // Get tenant ID
                    $tenantId = $dbSocket->getOne("SELECT LAST_INSERT_ID()");

                    // Create admin operator
                    $passwordHash = password_hash($adminPassword, PASSWORD_DEFAULT);
                    $operatorSql = sprintf(
                        "INSERT INTO tenant_operators (tenant_id, username, password_hash, full_name, email, role, is_active, created_at)
                         VALUES (%d, '%s', '%s', '%s', '%s', 'admin', 1, NOW())",
                        $tenantId,
                        $dbSocket->escapeSimple($adminUsername),
                        $dbSocket->escapeSimple($passwordHash),
                        $dbSocket->escapeSimple($adminFullname),
                        $dbSocket->escapeSimple($adminEmail ?: $email)
                    );
                    $dbSocket->query($operatorSql);

                    // Create subscription if plan selected
                    if ($planId > 0) {
                        // Get plan details
                        $planResult = $dbSocket->query("SELECT * FROM app_subscription_plans WHERE id = " . $planId);
                        if ($planResult && !DB::isError($planResult)) {
                            $plan = $planResult->fetchRow(DB_FETCHMODE_ASSOC);
                            if ($plan) {
                                $endDate = date('Y-m-d H:i:s', strtotime('+' . $plan['billing_period_days'] . ' days'));
                                $subscriptionSql = sprintf(
                                    "INSERT INTO tenant_subscriptions (tenant_id, plan_id, status, start_date, end_date, created_at)
                                     VALUES (%d, %d, 'active', NOW(), '%s', NOW())",
                                    $tenantId,
                                    $planId,
                                    $dbSocket->escapeSimple($endDate)
                                );
                                $dbSocket->query($subscriptionSql);
                            }
                        }
                    }

                    // Log activity
                    $logSql = sprintf(
                        "INSERT INTO activity_logs (tenant_id, user_type, user_id, action, entity_type, entity_id, description, ip_address, created_at)
                         VALUES (NULL, 'super_admin', %d, 'create', 'tenant', %d, 'Created tenant: %s', '%s', NOW())",
                        $_SESSION['admin_id'],
                        $tenantId,
                        $dbSocket->escapeSimple($companyName),
                        $dbSocket->escapeSimple($_SERVER['REMOTE_ADDR'])
                    );
                    $dbSocket->query($logSql);

                    $success = 'Tenant created successfully!';
                    header("Location: list.php?success=created");
                    exit;
                }
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add Tenant - Super Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root { --sidebar-width: 260px; }
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background-color: #f4f6f9; }
        .sidebar { position: fixed; top: 0; left: 0; height: 100vh; width: var(--sidebar-width); background: linear-gradient(135deg, #1e3c72 0%, #2a5298 100%); z-index: 1000; overflow-y: auto; }
        .sidebar-header { padding: 20px; text-align: center; border-bottom: 1px solid rgba(255,255,255,0.1); }
        .sidebar-header h4 { color: white; margin: 0; font-weight: 600; }
        .nav-link { color: rgba(255,255,255,0.8) !important; padding: 12px 20px; border-left: 3px solid transparent; }
        .nav-link:hover, .nav-link.active { background: rgba(255,255,255,0.1); color: white !important; border-left-color: #fff; }
        .nav-link i { width: 25px; text-align: center; margin-right: 10px; }
        .main-content { margin-left: var(--sidebar-width); padding: 20px 30px; min-height: 100vh; }
        .top-bar { background: white; padding: 15px 25px; margin: -20px -30px 20px; box-shadow: 0 2px 10px rgba(0,0,0,0.05); }
        .card { border: none; border-radius: 15px; box-shadow: 0 5px 15px rgba(0,0,0,0.05); }
        .card-header { background: white; border-bottom: 1px solid #eee; padding: 15px 20px; font-weight: 600; }
        .nav-section { padding: 15px 20px 5px; color: rgba(255,255,255,0.5); font-size: 0.75rem; text-transform: uppercase; }
        .form-label { font-weight: 500; color: #495057; }
        .plan-card { border: 2px solid #dee2e6; border-radius: 12px; padding: 20px; cursor: pointer; transition: all 0.3s; }
        .plan-card:hover { border-color: #667eea; }
        .plan-card.selected { border-color: #667eea; background-color: #f8f9ff; }
        .plan-card input[type="radio"] { display: none; }
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
            <li class="nav-item"><a class="nav-link active" href="list.php"><i class="fas fa-building"></i> Tenants</a></li>
            <li class="nav-item"><a class="nav-link" href="pending.php"><i class="fas fa-user-clock"></i> Pending</a></li>
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
                <h4 class="mb-0">Add New Tenant</h4>
                <small class="text-muted">Create a new tenant account</small>
            </div>
            <a href="list.php" class="btn btn-outline-secondary">
                <i class="fas fa-arrow-left me-1"></i> Back to List
            </a>
        </div>

        <?php if ($error): ?>
        <div class="alert alert-danger"><i class="fas fa-exclamation-circle me-2"></i><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <form method="post">
            <input type="hidden" name="csrf_token" value="<?php echo dalo_admin_csrf_token(); ?>">

            <div class="row">
                <!-- Company Info -->
                <div class="col-lg-8">
                    <div class="card mb-4">
                        <div class="card-header"><i class="fas fa-building me-2"></i>Company Information</div>
                        <div class="card-body">
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label class="form-label">Company Name <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control" name="company_name" required value="<?php echo htmlspecialchars($_POST['company_name'] ?? ''); ?>">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Domain Prefix <span class="text-danger">*</span></label>
                                    <div class="input-group">
                                        <input type="text" class="form-control" name="domain_prefix" pattern="[a-z0-9\-]+" required value="<?php echo htmlspecialchars($_POST['domain_prefix'] ?? ''); ?>">
                                        <span class="input-group-text">.yourdomain.com</span>
                                    </div>
                                    <small class="text-muted">Only lowercase letters, numbers, and hyphens</small>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Email <span class="text-danger">*</span></label>
                                    <input type="email" class="form-control" name="email" required value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Phone</label>
                                    <input type="text" class="form-control" name="phone" value="<?php echo htmlspecialchars($_POST['phone'] ?? ''); ?>">
                                </div>
                                <div class="col-12">
                                    <label class="form-label">Address</label>
                                    <textarea class="form-control" name="address" rows="2"><?php echo htmlspecialchars($_POST['address'] ?? ''); ?></textarea>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Admin Account -->
                    <div class="card mb-4">
                        <div class="card-header"><i class="fas fa-user-shield me-2"></i>Admin Account</div>
                        <div class="card-body">
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label class="form-label">Username <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control" name="admin_username" required value="<?php echo htmlspecialchars($_POST['admin_username'] ?? ''); ?>">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Password <span class="text-danger">*</span></label>
                                    <input type="password" class="form-control" name="admin_password" required minlength="8">
                                    <small class="text-muted">Minimum 8 characters</small>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Full Name <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control" name="admin_fullname" required value="<?php echo htmlspecialchars($_POST['admin_fullname'] ?? ''); ?>">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Admin Email</label>
                                    <input type="email" class="form-control" name="admin_email" value="<?php echo htmlspecialchars($_POST['admin_email'] ?? ''); ?>">
                                    <small class="text-muted">Leave empty to use company email</small>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Subscription Plan -->
                <div class="col-lg-4">
                    <div class="card mb-4">
                        <div class="card-header"><i class="fas fa-tags me-2"></i>Subscription Plan</div>
                        <div class="card-body">
                            <div class="mb-3">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="is_active" id="is_active" checked>
                                    <label class="form-check-label" for="is_active">Activate tenant immediately</label>
                                </div>
                            </div>

                            <?php foreach ($plans as $plan): ?>
                            <label class="plan-card d-block mb-3">
                                <input type="radio" name="plan_id" value="<?php echo $plan['id']; ?>">
                                <div class="d-flex justify-content-between align-items-center mb-2">
                                    <strong><?php echo htmlspecialchars($plan['name']); ?></strong>
                                    <span class="badge bg-primary">Rp <?php echo number_format($plan['price'], 0, ',', '.'); ?></span>
                                </div>
                                <small class="text-muted d-block"><?php echo htmlspecialchars($plan['description']); ?></small>
                                <small class="text-muted">
                                    <i class="fas fa-users me-1"></i><?php echo $plan['max_users'] == -1 ? 'Unlimited' : number_format($plan['max_users']); ?> users
                                    <span class="mx-1">|</span>
                                    <i class="fas fa-server me-1"></i><?php echo $plan['max_mikrotik'] == -1 ? 'Unlimited' : number_format($plan['max_mikrotik']); ?> devices
                                </small>
                            </label>
                            <?php endforeach; ?>

                            <label class="plan-card d-block">
                                <input type="radio" name="plan_id" value="0" checked>
                                <div class="d-flex justify-content-between align-items-center">
                                    <strong>No Plan (Trial)</strong>
                                    <span class="badge bg-secondary">Free</span>
                                </div>
                                <small class="text-muted d-block">Limited features, manual activation</small>
                            </label>
                        </div>
                    </div>

                    <button type="submit" class="btn btn-primary btn-lg w-100">
                        <i class="fas fa-plus-circle me-2"></i>Create Tenant
                    </button>
                </div>
            </div>
        </form>
    </main>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.querySelectorAll('.plan-card').forEach(card => {
            card.addEventListener('click', function() {
                document.querySelectorAll('.plan-card').forEach(c => c.classList.remove('selected'));
                this.classList.add('selected');
                this.querySelector('input[type="radio"]').checked = true;
            });
        });
    </script>
</body>
</html>
