<?php
/*
 *********************************************************************************************************
 * NAS sidebar menu
 *********************************************************************************************************
 */

if (strpos($_SERVER['PHP_SELF'], '/include/menu/sidebar/nas/default.php') !== false) {
    header("Location: ../../../../index.php");
    exit;
}

$descriptors = array();
$descriptors[] = array( 'type' => 'link', 'label' => 'MikroTik NAS', 'href' => 'config-mikrotik.php', 'icon' => 'router-fill' );
$descriptors[] = array( 'type' => 'link', 'label' => 'NAS RADIUS', 'href' => 'mng-rad-nas.php', 'icon' => 'server' );

$sections = array();
$sections[] = array( 'title' => 'NAS', 'descriptors' => $descriptors );

$menu = array(
                'title' => 'NAS',
                'sections' => $sections,
             );
