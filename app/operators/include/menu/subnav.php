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
if (strpos($_SERVER['PHP_SELF'], '/include/menu/subnav.php') !== false) {
    header("Location: ../../index.php");
    exit;
}

// load subnav category
$subnav = array();
// PPPoE
$subnav["pppoe"] = array(
                            'Daftar' => 'pppoe-list.php',
                            'Tambah' => 'pppoe-new.php',
                        );
// Hotspot
$subnav["hotspot"] = array(
                            'Daftar' => 'hotspot-list.php',
                            'Tambah' => 'hotspot-new.php',
                        );
// Plans/Profiles
$subnav["plans"] = array(
                            'Daftar Paket' => 'bill-plans-list.php',
                            'Paket Baru' => 'bill-plans-new.php',
                        );
// Payments/Invoices
$subnav["payments"] = array(
                            'Invoices' => 'bill-invoice-list.php',
                            'Payments' => 'bill-payments-list.php',
                            'Pelanggan' => 'bill-pos-list.php',
                        );
// NAS
$subnav["nas"] = array(
                            'MikroTik' => 'config-mikrotik.php',
                            'NAS RADIUS' => 'mng-rad-nas.php',
                        );
// Settings
$subnav["settings"] = array(
                            'WA Gateway' => 'config-wa-gateway.php',
                            'VPN L2TP' => 'config-l2tp.php',
                            'Update Database' => 'config-db-update.php',
                            'Bahasa' => 'config-lang.php',
                        );




// detect category from the PHP_SELF name
$basename = basename($_SERVER['PHP_SELF']);
$detect_category = substr($basename, 0, strpos($basename, '-'));
if (strpos($basename, 'pppoe-') === 0) {
    $detect_category = 'pppoe';
} else if (strpos($basename, 'hotspot-') === 0) {
    $detect_category = 'hotspot';
} else if (strpos($basename, 'bill-plans') === 0) {
    $detect_category = 'plans';
} else if (strpos($basename, 'bill-invoice') === 0 || strpos($basename, 'bill-payments') === 0 || strpos($basename, 'bill-pos') === 0) {
    $detect_category = 'payments';
} else if (strpos($basename, 'config-mikrotik') === 0 || strpos($basename, 'mng-rad-nas') === 0) {
    $detect_category = 'nas';
} else if (strpos($basename, 'config-') === 0) {
    $detect_category = 'settings';
}

if (!in_array($detect_category, array_keys($subnav))) {
    $detect_category = 'pppoe';
}

if (!empty($detect_category) && count($subnav[$detect_category]) > 0) {

?>

<nav class="border-bottom text-bg-light py-1">
    <div class="d-flex">
        <ul class="nav ms-4">
<?php
            foreach ($subnav[$detect_category] as $label => $href) {
                $label = htmlspecialchars(strip_tags(trim(t('submenu', $label))), ENT_QUOTES, 'UTF-8');
                printf('<li><a class="nav-link link-dark px-2" href="%s">%s</a></li>', urlencode($href), $label);
            }
?>
        </ul>
    </div>
</nav>

<?php

}
