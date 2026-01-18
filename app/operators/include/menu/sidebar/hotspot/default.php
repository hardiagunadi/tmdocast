<?php
/*
 *********************************************************************************************************
 * Hotspot sidebar menu
 *********************************************************************************************************
 */

if (strpos($_SERVER['PHP_SELF'], '/include/menu/sidebar/hotspot/default.php') !== false) {
    header("Location: ../../../../index.php");
    exit;
}

$descriptors = array();
$descriptors[] = array( 'type' => 'link', 'label' => 'Tambah User Hotspot', 'href' => 'hotspot-new.php', 'icon' => 'wifi' );
$descriptors[] = array( 'type' => 'link', 'label' => 'Daftar User Hotspot', 'href' => 'hotspot-list.php', 'icon' => 'list-ul' );

$sections = array();
$sections[] = array( 'title' => 'Hotspot', 'descriptors' => $descriptors );

$menu = array(
                'title' => 'Hotspot',
                'sections' => $sections,
             );
