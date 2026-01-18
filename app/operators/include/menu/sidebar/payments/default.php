<?php
/*
 *********************************************************************************************************
 * Payments sidebar menu
 *********************************************************************************************************
 */

if (strpos($_SERVER['PHP_SELF'], '/include/menu/sidebar/payments/default.php') !== false) {
    header("Location: ../../../../index.php");
    exit;
}

$descriptors = array();
$descriptors[] = array( 'type' => 'link', 'label' => 'Daftar Invoice', 'href' => 'bill-invoice-list.php', 'icon' => 'receipt' );
$descriptors[] = array( 'type' => 'link', 'label' => 'Daftar Pembayaran', 'href' => 'bill-payments-list.php', 'icon' => 'cash-stack' );
$descriptors[] = array( 'type' => 'link', 'label' => 'Pelanggan', 'href' => 'bill-pos-list.php', 'icon' => 'person-lines-fill' );

$sections = array();
$sections[] = array( 'title' => 'Pembayaran', 'descriptors' => $descriptors );

$menu = array(
                'title' => 'Pembayaran/Invoice',
                'sections' => $sections,
             );
