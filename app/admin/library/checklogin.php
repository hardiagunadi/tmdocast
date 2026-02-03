<?php
/*
 *********************************************************************************************************
 * daloRADIUS - RADIUS Web Platform
 * Super Admin Login Check
 *********************************************************************************************************
 */

require_once(dirname(__FILE__) . '/sessions.php');

dalo_admin_session_start();

// Check if logged in
if (!dalo_admin_is_logged_in()) {
    $_SESSION['admin_logged_in'] = false;

    // Calculate redirect path
    $my_php_self = $_SERVER['PHP_SELF'];
    $count = substr_count($my_php_self, "/", 1);
    $location = "";
    for ($i = 1; $i < $count; $i++) {
        $location .= "../";
    }
    $location .= "login.php";

    header("Location: $location");
    exit;
}

// Set user type for tenant context
$_SESSION['user_type'] = 'super_admin';
