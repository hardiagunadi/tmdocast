<?php
/*
 *********************************************************************************************************
 * PPPoE sidebar menu
 *********************************************************************************************************
 */

if (strpos($_SERVER['PHP_SELF'], '/include/menu/sidebar/pppoe/default.php') !== false) {
    header("Location: ../../../../index.php");
    exit;
}

$descriptors = array();
$descriptors[] = array( 'type' => 'link', 'label' => 'Tambah User PPPoE', 'href' => 'pppoe-new.php', 'icon' => 'person-plus-fill' );
$descriptors[] = array( 'type' => 'link', 'label' => 'Daftar User PPPoE', 'href' => 'pppoe-list.php', 'icon' => 'person-lines-fill' );

$sections = array();
$sections[] = array( 'title' => 'PPPoE', 'descriptors' => $descriptors );

$menu = array(
                'title' => 'PPPoE',
                'sections' => $sections,
             );
