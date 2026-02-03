<?php
/*
 *********************************************************************************************************
 * daloRADIUS - RADIUS Web Platform
 * Tenant Portal Index
 *********************************************************************************************************
 */

require_once(dirname(__FILE__) . '/library/sessions.php');

dalo_portal_session_start();

if (dalo_portal_is_logged_in()) {
    header("Location: home-main.php");
} else {
    header("Location: login.php");
}
exit;
