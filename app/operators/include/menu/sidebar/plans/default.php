<?php
/*
 *********************************************************************************************************
 * Plans sidebar menu
 *********************************************************************************************************
 */

if (strpos($_SERVER['PHP_SELF'], '/include/menu/sidebar/plans/default.php') !== false) {
    header("Location: ../../../../index.php");
    exit;
}

$descriptors = array();
$descriptors[] = array( 'type' => 'link', 'label' => 'Paket Baru', 'href' => 'bill-plans-new.php', 'icon' => 'plus-circle-fill' );
$descriptors[] = array( 'type' => 'link', 'label' => 'Daftar Paket', 'href' => 'bill-plans-list.php', 'icon' => 'list-ul' );

$sections = array();
$sections[] = array( 'title' => 'Paket', 'descriptors' => $descriptors );

$menu = array(
                'title' => 'Paket/Profil',
                'sections' => $sections,
             );
