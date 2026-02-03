<?php
/*
 *********************************************************************************************************
 * daloRADIUS - RADIUS Web Platform
 * Super Admin Index - Redirect to appropriate page
 *********************************************************************************************************
 */

require_once(dirname(__FILE__) . '/library/sessions.php');

dalo_admin_session_start();

if (dalo_admin_is_logged_in()) {
    header("Location: home-main.php");
} else {
    header("Location: login.php");
}
exit;
