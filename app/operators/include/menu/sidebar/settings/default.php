<?php
/*
 *********************************************************************************************************
 * Settings sidebar menu
 *********************************************************************************************************
 */

if (strpos($_SERVER['PHP_SELF'], '/include/menu/sidebar/settings/default.php') !== false) {
    header("Location: ../../../../index.php");
    exit;
}

$descriptors = array();
$descriptors[] = array( 'type' => 'link', 'label' => 'WA Gateway', 'href' => 'config-wa-gateway.php', 'icon' => 'chat-dots-fill' );
$descriptors[] = array( 'type' => 'link', 'label' => 'Bahasa', 'href' => 'config-lang.php', 'icon' => 'translate' );
$descriptors[] = array( 'type' => 'link', 'label' => 'Pengaturan Umum', 'href' => 'config-main.php', 'icon' => 'gear-fill' );

$sections = array();
$sections[] = array( 'title' => 'Pengaturan', 'descriptors' => $descriptors );

$menu = array(
                'title' => 'Pengaturan',
                'sections' => $sections,
             );
