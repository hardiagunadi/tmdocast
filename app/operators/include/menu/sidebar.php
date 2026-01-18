<?php
/*
 *********************************************************************************************************
 * daloRADIUS - RADIUS Web Platform
 * Copyright (C) 2007 - Liran Tal <liran@lirantal.com> All Rights Reserved.
 *
 * This program is free software; you can redistribute it and/or
 * modify it under the terms of the GNU General Public License
 * as published by the Free Software Foundation; either version 2
 * of the License, or (at your option) any later version.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 59 Temple Place - Suite 330, Boston, MA  02111-1307, USA.
 *
 *********************************************************************************************************
 *
 * Authors:    Filippo Lauria <filippo.lauria@iit.cnr.it>
 *
 *********************************************************************************************************
 */

// prevent this file to be directly accessed
if (strpos($_SERVER['PHP_SELF'], '/include/menu/sidebar.php') !== false) {
    header("Location: ../../index.php");
    exit;
}

$autocomplete = (isset($configValues['CONFIG_IFACE_AUTO_COMPLETE']) &&
                 strtolower($configValues['CONFIG_IFACE_AUTO_COMPLETE']) === "yes");

$cat_subcat_tree = array(
                            "pppoe" => array(),
                            "hotspot" => array(),
                            "plans" => array(),
                            "payments" => array(),
                            "nas" => array(),
                            "settings" => array(),
                        );

$allowed_categories = array_keys($cat_subcat_tree);

$basename = basename($_SERVER['PHP_SELF'], ".php");
$tmp = explode("-", $basename);
$detected_category = $tmp[0];

if (strpos($basename, 'pppoe-') === 0) {
    $detected_category = 'pppoe';
} else if (strpos($basename, 'hotspot-') === 0) {
    $detected_category = 'hotspot';
} else if (strpos($basename, 'bill-plans') === 0) {
    $detected_category = 'plans';
} else if (strpos($basename, 'bill-invoice') === 0 || strpos($basename, 'bill-payments') === 0 || strpos($basename, 'bill-pos') === 0) {
    $detected_category = 'payments';
} else if (strpos($basename, 'config-mikrotik') === 0 || strpos($basename, 'mng-rad-nas') === 0) {
    $detected_category = 'nas';
} else if (strpos($basename, 'config-') === 0) {
    $detected_category = 'settings';
}

if (!in_array($detected_category, $allowed_categories)) {
    $detected_category = 'pppoe';
}

$detected_subcategory = "default";

$sidebar_file = sprintf("include/menu/sidebar/%s/%s.php", $detected_category, $detected_subcategory);
if (file_exists($sidebar_file)) {
    include($sidebar_file);
    menu_print($menu);
}




//~ mng-main
//~ mng-users
//~ mng-batch
//~ mng-hs
//~ mng-rad-nas
//~ mng-rad-usergroup
//~ mng-rad-profiles
//~ mng-rad-attributes
//~ mng-rad-realms
//~ mng-rad-ippool

//~ rep-main
//~ rep-general
//~ rep-logs
//~ rep-status
//~ rep-batch
//~ rep-hb

//~ acct-main
//~ acct-general
//~ acct-plans
//~ acct-custom
//~ acct-hs
//~ acct-maint

//~ bill-main
//~ bill-pos
//~ bill-plans
//~ bill-rates
//~ bill-merchant
//~ bill-history
//~ bill-invoice
//~ bill-payments

//~ gis-main
//~ gis-map-view (new)
//~ gis-map-edit (new)

//~ graphs-main

//~ config-main
//~ config-general
//~ config-reports
//~ config-maint
//~ config-operators
//~ config-backup
