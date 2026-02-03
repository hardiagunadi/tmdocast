<?php
/*
 *********************************************************************************************************
 * daloRADIUS - RADIUS Web Platform
 * Super Admin Login Page
 *********************************************************************************************************
 */

require_once(dirname(__FILE__) . '/library/sessions.php');

dalo_admin_session_start();

// Already logged in? Redirect to dashboard
if (dalo_admin_is_logged_in()) {
    header("Location: home-main.php");
    exit;
}

// Get error message if any
$error = isset($_GET['error']) ? $_GET['error'] : '';
$errorMessages = [
    'invalid' => 'Username atau password salah',
    'locked' => 'Akun Anda terkunci karena terlalu banyak percobaan login',
    'inactive' => 'Akun Anda tidak aktif',
    'session' => 'Sesi Anda telah berakhir, silakan login kembali',
    'csrf' => 'Token keamanan tidak valid, silakan coba lagi'
];

$errorMessage = isset($errorMessages[$error]) ? $errorMessages[$error] : '';
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Super Admin Login - daloRADIUS SaaS</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body {
            background: linear-gradient(135deg, #1e3c72 0%, #2a5298 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        .login-container {
            width: 100%;
            max-width: 420px;
            padding: 15px;
        }
        .login-card {
            background: rgba(255, 255, 255, 0.95);
            border-radius: 20px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
            overflow: hidden;
        }
        .login-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 40px 30px;
            text-align: center;
        }
        .login-header h1 {
            margin: 0;
            font-size: 1.8rem;
            font-weight: 600;
        }
        .login-header p {
            margin: 10px 0 0;
            opacity: 0.9;
            font-size: 0.9rem;
        }
        .login-body {
            padding: 40px 30px;
        }
        .form-control {
            border-radius: 10px;
            padding: 12px 15px;
            padding-left: 45px;
            border: 2px solid #e0e0e0;
            font-size: 1rem;
        }
        .form-control:focus {
            border-color: #667eea;
            box-shadow: 0 0 0 0.2rem rgba(102, 126, 234, 0.25);
        }
        .input-group {
            position: relative;
            margin-bottom: 20px;
        }
        .input-icon {
            position: absolute;
            left: 15px;
            top: 50%;
            transform: translateY(-50%);
            color: #667eea;
            z-index: 10;
        }
        .btn-login {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border: none;
            border-radius: 10px;
            padding: 14px;
            font-size: 1.1rem;
            font-weight: 600;
            width: 100%;
            transition: all 0.3s ease;
        }
        .btn-login:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 20px rgba(102, 126, 234, 0.4);
        }
        .admin-badge {
            background: rgba(255,255,255,0.2);
            padding: 5px 15px;
            border-radius: 20px;
            font-size: 0.75rem;
            display: inline-block;
            margin-top: 10px;
        }
        .alert {
            border-radius: 10px;
            border: none;
        }
        .footer-text {
            text-align: center;
            padding: 20px;
            color: rgba(255, 255, 255, 0.7);
            font-size: 0.85rem;
        }
        .footer-text a {
            color: white;
            text-decoration: none;
        }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="login-card">
            <div class="login-header">
                <i class="fas fa-shield-alt fa-3x mb-3"></i>
                <h1>Super Admin</h1>
                <p>daloRADIUS SaaS Management</p>
                <span class="admin-badge"><i class="fas fa-lock me-1"></i> Secure Access</span>
            </div>
            <div class="login-body">
                <?php if ($errorMessage): ?>
                <div class="alert alert-danger" role="alert">
                    <i class="fas fa-exclamation-triangle me-2"></i><?php echo htmlspecialchars($errorMessage); ?>
                </div>
                <?php endif; ?>

                <form action="dologin.php" method="post" autocomplete="off">
                    <input type="hidden" name="csrf_token" value="<?php echo dalo_admin_csrf_token(); ?>">

                    <div class="input-group">
                        <i class="fas fa-user input-icon"></i>
                        <input type="text" class="form-control" name="username" placeholder="Username" required autofocus>
                    </div>

                    <div class="input-group">
                        <i class="fas fa-key input-icon"></i>
                        <input type="password" class="form-control" name="password" placeholder="Password" required>
                    </div>

                    <button type="submit" class="btn btn-primary btn-login">
                        <i class="fas fa-sign-in-alt me-2"></i>Login
                    </button>
                </form>
            </div>
        </div>
        <div class="footer-text">
            <a href="../users/login.php"><i class="fas fa-arrow-left me-1"></i>Kembali ke Portal Tenant</a>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
