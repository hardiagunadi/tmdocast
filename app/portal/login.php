<?php
/*
 *********************************************************************************************************
 * daloRADIUS - RADIUS Web Platform
 * Tenant Portal Login Page
 *********************************************************************************************************
 */

require_once(dirname(__FILE__) . '/library/sessions.php');

dalo_portal_session_start();

// Already logged in? Redirect to dashboard
if (dalo_portal_is_logged_in()) {
    header("Location: index.php");
    exit;
}

// Error messages
$error = isset($_GET['error']) ? $_GET['error'] : '';
$errorMessages = [
    'invalid' => 'Username atau password salah',
    'locked' => 'Akun Anda terkunci karena terlalu banyak percobaan login',
    'inactive' => 'Akun atau tenant Anda tidak aktif',
    'expired' => 'Langganan tenant telah berakhir',
    'session' => 'Sesi Anda telah berakhir, silakan login kembali',
    'timeout' => 'Sesi Anda telah habis karena tidak aktif',
    'csrf' => 'Token keamanan tidak valid',
    'tenant' => 'Tenant tidak ditemukan'
];
$errorMessage = isset($errorMessages[$error]) ? $errorMessages[$error] : '';

// Get tenant from subdomain or query parameter
$tenantDomain = '';
if (isset($_GET['tenant'])) {
    $tenantDomain = trim($_GET['tenant']);
} else {
    // Try to get from subdomain
    $host = $_SERVER['HTTP_HOST'];
    $parts = explode('.', $host);
    if (count($parts) > 2) {
        $tenantDomain = $parts[0];
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tenant Portal Login - daloRADIUS SaaS</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        .login-container {
            width: 100%;
            max-width: 450px;
            padding: 15px;
        }
        .login-card {
            background: rgba(255, 255, 255, 0.98);
            border-radius: 20px;
            box-shadow: 0 25px 80px rgba(0, 0, 0, 0.3);
            overflow: hidden;
        }
        .login-header {
            background: linear-gradient(135deg, #11998e 0%, #38ef7d 100%);
            color: white;
            padding: 35px 30px;
            text-align: center;
        }
        .login-header h1 {
            margin: 0;
            font-size: 1.6rem;
            font-weight: 600;
        }
        .login-header p {
            margin: 8px 0 0;
            opacity: 0.9;
            font-size: 0.9rem;
        }
        .login-body {
            padding: 35px 30px;
        }
        .form-control {
            border-radius: 10px;
            padding: 12px 15px;
            padding-left: 45px;
            border: 2px solid #e0e0e0;
            font-size: 1rem;
            transition: all 0.3s;
        }
        .form-control:focus {
            border-color: #11998e;
            box-shadow: 0 0 0 0.2rem rgba(17, 153, 142, 0.2);
        }
        .input-group {
            position: relative;
            margin-bottom: 18px;
        }
        .input-icon {
            position: absolute;
            left: 15px;
            top: 50%;
            transform: translateY(-50%);
            color: #11998e;
            z-index: 10;
        }
        .btn-login {
            background: linear-gradient(135deg, #11998e 0%, #38ef7d 100%);
            border: none;
            border-radius: 10px;
            padding: 14px;
            font-size: 1.1rem;
            font-weight: 600;
            width: 100%;
            color: white;
            transition: all 0.3s ease;
        }
        .btn-login:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 25px rgba(17, 153, 142, 0.4);
            color: white;
        }
        .tenant-badge {
            background: rgba(255,255,255,0.2);
            padding: 5px 15px;
            border-radius: 20px;
            font-size: 0.8rem;
            display: inline-block;
            margin-top: 10px;
        }
        .alert {
            border-radius: 10px;
            border: none;
            font-size: 0.9rem;
        }
        .footer-text {
            text-align: center;
            padding: 20px;
            color: rgba(255, 255, 255, 0.8);
            font-size: 0.85rem;
        }
        .footer-text a {
            color: white;
            text-decoration: none;
            font-weight: 500;
        }
        .footer-text a:hover {
            text-decoration: underline;
        }
        .divider {
            display: flex;
            align-items: center;
            text-align: center;
            color: #999;
            margin: 20px 0;
        }
        .divider::before,
        .divider::after {
            content: '';
            flex: 1;
            border-bottom: 1px solid #ddd;
        }
        .divider span {
            padding: 0 10px;
            font-size: 0.85rem;
        }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="login-card">
            <div class="login-header">
                <i class="fas fa-broadcast-tower fa-3x mb-3"></i>
                <h1>Tenant Portal</h1>
                <p>ISP Management System</p>
                <?php if ($tenantDomain): ?>
                <span class="tenant-badge"><i class="fas fa-building me-1"></i><?php echo htmlspecialchars($tenantDomain); ?></span>
                <?php endif; ?>
            </div>
            <div class="login-body">
                <?php if ($errorMessage): ?>
                <div class="alert alert-danger" role="alert">
                    <i class="fas fa-exclamation-triangle me-2"></i><?php echo htmlspecialchars($errorMessage); ?>
                </div>
                <?php endif; ?>

                <form action="dologin.php" method="post" autocomplete="off">
                    <input type="hidden" name="csrf_token" value="<?php echo dalo_portal_csrf_token(); ?>">

                    <?php if (!$tenantDomain): ?>
                    <div class="input-group">
                        <i class="fas fa-building input-icon"></i>
                        <input type="text" class="form-control" name="tenant_domain" placeholder="Kode Tenant / Domain" required>
                    </div>
                    <?php else: ?>
                    <input type="hidden" name="tenant_domain" value="<?php echo htmlspecialchars($tenantDomain); ?>">
                    <?php endif; ?>

                    <div class="input-group">
                        <i class="fas fa-user input-icon"></i>
                        <input type="text" class="form-control" name="username" placeholder="Username" required autofocus>
                    </div>

                    <div class="input-group">
                        <i class="fas fa-lock input-icon"></i>
                        <input type="password" class="form-control" name="password" placeholder="Password" required>
                    </div>

                    <button type="submit" class="btn btn-login">
                        <i class="fas fa-sign-in-alt me-2"></i>Masuk
                    </button>
                </form>

                <div class="divider"><span>atau</span></div>

                <div class="text-center">
                    <a href="../users/login.php" class="text-muted text-decoration-none">
                        <i class="fas fa-user-circle me-1"></i>Login sebagai Pelanggan
                    </a>
                </div>
            </div>
        </div>
        <div class="footer-text">
            <p class="mb-2">Belum punya akun tenant? <a href="register.php">Daftar sekarang</a></p>
            <a href="../admin/login.php"><i class="fas fa-shield-alt me-1"></i>Super Admin</a>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
