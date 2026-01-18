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
 * Description:    Indonesian language file
 *
 * Authors:        daloRADIUS community
 *
 *********************************************************************************************************
 */

// prevent this file to be directly accessed
if (strpos($_SERVER['PHP_SELF'], '/lang/id.php') !== false) {
    header("Location: ../index.php");
    exit;
}

$l['all']['daloRADIUS'] = sprintf("daloRADIUS %s", $configValues['DALORADIUS_VERSION']);
$l['all']['daloRADIUSVersion'] = sprintf("versi %s ", $configValues['DALORADIUS_VERSION']);
$l['all']['copyright1'] = 'Manajemen, Pelaporan, Akuntansi, dan Penagihan RADIUS oleh <a target="_blank" href="https://github.com/lirantal/daloradius">Liran Tal</a>';
$l['all']['copyright2'] = 'daloRADIUS - Copyright &copy; 2007-' . date('Y') . <<<EOF
 <span class="d-inline-block" tabindex="0" data-bs-toggle="popover" data-bs-trigger="hover focus" data-bs-content="Ikuti @filippolauria di GitHub">
  <a target="_blank" href="https://github.com/filippolauria">Filippo Lauria</a>
</span>  dan <a target="_blank" href="https://github.com/lirantal/daloradius">Liran Tal</a>.
EOF;

$l['all']['ID'] = "ID";
$l['all']['Name'] = "Nama";
$l['all']['Users'] = "Pengguna";
$l['all']['UserType'] = "Jenis Pengguna";
$l['all']['Username'] = "Nama Pengguna";
$l['all']['Password'] = "Kata Sandi";
$l['all']['PasswordType'] = "Jenis Kata Sandi";
$l['all']['IPAddress'] = "Alamat IP";
$l['all']['NASIPAddress'] = "Alamat IP NAS";
$l['all']['HotSpot'] = "Hotspot";
$l['all']['HotSpots'] = "Hotspot";
$l['all']['HotSpotName'] = "Nama Hotspot";
$l['all']['Profile'] = "Profil";
$l['all']['Group'] = "Grup";
$l['all']['Groupname'] = "Nama Grup";
$l['all']['ProfilePriority'] = "Prioritas Profil";
$l['all']['GroupPriority'] = "Prioritas Grup";
$l['all']['Priority'] = "Prioritas";
$l['all']['Attribute'] = "Atribut";
$l['all']['Operator'] = "Operator";
$l['all']['Value'] = "Nilai";
$l['all']['NewValue'] = "Nilai Baru";
$l['all']['MaxTimeExpiration'] = "Waktu Maksimum / Kedaluwarsa";
$l['all']['UsedTime'] = "Waktu Terpakai";
$l['all']['Status'] = "Status";
$l['all']['Usage'] = "Penggunaan";
$l['all']['StartTime'] = "Waktu Mulai";
$l['all']['StopTime'] = "Waktu Selesai";
$l['all']['TotalTime'] = "Total Waktu";
$l['all']['Upload'] = "Unggah";
$l['all']['Download'] = "Unduh";
$l['all']['Termination'] = "Terminasi";
$l['all']['Date'] = "Tanggal";
$l['all']['Time'] = "Waktu";
$l['all']['Daily'] = "Harian";
$l['all']['Weekly'] = "Mingguan";
$l['all']['Monthly'] = "Bulanan";
$l['all']['Yearly'] = "Tahunan";
$l['all']['TotalUsers'] = "Total Pengguna";
$l['all']['TotalSessions'] = "Total Sesi";
$l['all']['TotalLogins'] = "Total Login";
$l['all']['TotalTraffic'] = "Total Lalu Lintas";
$l['all']['NASIPAddress'] = "Alamat IP NAS";
$l['all']['NASShortName'] = "Nama Singkat NAS";
$l['all']['NasID'] = "ID NAS";
$l['all']['NasIPHost'] = "IP/Host NAS";
$l['all']['NasShortname'] = "Nama Singkat NAS";
$l['all']['NasType'] = "Jenis NAS";
$l['all']['NasPorts'] = "Port NAS";
$l['all']['NasSecret'] = "Secret NAS";
$l['all']['NasVirtualServer'] = "Virtual Server NAS";
$l['all']['NasCommunity'] = "Komunitas NAS";
$l['all']['NasDescription'] = "Deskripsi NAS";
$l['all']['Dictionary'] = "Kamus";
$l['all']['VendorID'] = "ID Vendor";
$l['all']['VendorName'] = "Nama Vendor";
$l['all']['VendorAttribute'] = "Atribut Vendor";
$l['all']['RecommendedOP'] = "OP Direkomendasikan";
$l['all']['RecommendedTable'] = "Tabel Direkomendasikan";
$l['all']['RecommendedTooltip'] = "Tooltip Direkomendasikan";
$l['all']['RecommendedHelper'] = "Bantuan Direkomendasikan";
$l['all']['CSVData'] = "Data berformat CSV";
$l['all']['Realm'] = "Realm";
$l['all']['RealmName'] = "Nama Realm";
$l['all']['RealmSecret'] = "Secret Realm";
$l['all']['Proxy'] = "Proxy";
$l['all']['ProxyName'] = "Nama Proxy";
$l['all']['ProxySecret'] = "Secret Proxy";
$l['all']['PacketType'] = "Jenis Paket";
$l['all']['ClientName'] = "Nama Klien";
$l['all']['Amount'] = "Jumlah";
$l['all']['Balance'] = "Saldo";
$l['all']['Invoice'] = "Faktur";
$l['all']['InvoiceStatus'] = "Status Faktur";
$l['all']['InvoiceType'] = "Jenis Faktur";
$l['all']['PaymentDate'] = "Tanggal Pembayaran";
$l['all']['StartingDate'] = "Tanggal Mulai";
$l['all']['EndingDate'] = "Tanggal Akhir";

$l['all']['PlanActive'] = "Paket Aktif";
$l['all']['PlanTimeType'] = "Jenis Waktu Paket";
$l['all']['PlanTimeBank'] = "Bank Waktu Paket";
$l['all']['PlanTimeRefillCost'] = "Biaya Isi Ulang Paket";
$l['all']['PlanTrafficRefillCost'] = "Biaya Isi Ulang Paket";
$l['all']['PlanBandwidthUp'] = "Bandwidth Unggah Paket";
$l['all']['PlanBandwidthDown'] = "Bandwidth Unduh Paket";
$l['all']['PlanTrafficTotal'] = "Total Trafik Paket";
$l['all']['PlanTrafficDown'] = "Trafik Unduh Paket";
$l['all']['PlanTrafficUp'] = "Trafik Unggah Paket";
$l['all']['PlanRecurring'] = "Pengulangan Paket";
$l['all']['PlanRecurringPeriod'] = "Periode Pengulangan Paket";
$l['all']['planRecurringBillingSchedule'] = "Jadwal Penagihan Berulang Paket";
$l['all']['PlanCost'] = "Biaya Paket";
$l['all']['PlanSetupCost'] = "Biaya Setup Paket";
$l['all']['PlanTax'] = "Pajak Paket";
$l['all']['PlanCurrency'] = "Mata Uang Paket";
$l['all']['PlanGroup'] = "Profil Paket (Grup)";
$l['all']['PlanType'] = "Jenis Paket";
$l['all']['PlanName'] = "Nama Paket";
$l['all']['PlanId'] = "ID Paket";

$l['button']['DashboardSettings'] = "Pengaturan Dashboard";
$l['button']['GenerateReport'] = "Buat Laporan";
$l['button']['ListPayTypes'] = "Daftar Jenis Pembayaran";
$l['button']['NewPayType'] = "Jenis Pembayaran Baru";
$l['button']['EditPayType'] = "Ubah Jenis Pembayaran";
$l['button']['RemovePayType'] = "Hapus Jenis Pembayaran";
$l['button']['ListPayments'] = "Daftar Pembayaran";
$l['button']['NewPayment'] = "Pembayaran Baru";
$l['button']['EditPayment'] = "Ubah Pembayaran";
$l['button']['RemovePayment'] = "Hapus Pembayaran";
$l['button']['NewUsers'] = "Pengguna Baru";
$l['button']['ClearSessions'] = "Bersihkan Sesi";
$l['button']['Dashboard'] = "Dashboard";
$l['button']['MailSettings'] = "Pengaturan Email";
$l['button']['Batch'] = "Batch";
$l['button']['BatchHistory'] = "Riwayat Batch";
$l['button']['BatchDetails'] = "Detail Batch";
$l['button']['ListRates'] = "Daftar Tarif";
$l['button']['NewRate'] = "Tarif Baru";
$l['button']['EditRate'] = "Ubah Tarif";
$l['button']['RemoveRate'] = "Hapus Tarif";
$l['button']['ListPlans'] = "Daftar Paket";
$l['button']['NewPlan'] = "Paket Baru";
$l['button']['EditPlan'] = "Ubah Paket";
$l['button']['RemovePlan'] = "Hapus Paket";
$l['button']['ListInvoices'] = "Daftar Faktur";
$l['button']['NewInvoice'] = "Faktur Baru";
$l['button']['EditInvoice'] = "Ubah Faktur";
$l['button']['RemoveInvoice'] = "Hapus Faktur";
$l['button']['ListRealms'] = "Daftar Realm";
$l['button']['NewRealm'] = "Realm Baru";
$l['button']['EditRealm'] = "Ubah Realm";
$l['button']['RemoveRealm'] = "Hapus Realm";
$l['button']['ListProxys'] = "Daftar Proxy";
$l['button']['NewProxy'] = "Proxy Baru";
$l['button']['EditProxy'] = "Ubah Proxy";
$l['button']['RemoveProxy'] = "Hapus Proxy";
$l['button']['ListAttributesforVendor'] = "Daftar Atribut untuk Vendor:";
$l['button']['NewVendorAttribute'] = "Atribut Vendor Baru";
$l['button']['EditVendorAttribute'] = "Ubah Atribut Vendor";
$l['button']['SearchVendorAttribute'] = "Cari Atribut";
$l['button']['RemoveVendorAttribute'] = "Hapus Atribut Vendor";
$l['button']['ImportVendorDictionary'] = "Impor Kamus Vendor";
$l['button']['BetweenDates'] = "Antara Tanggal:";
$l['button']['Where'] = "Di Mana";
$l['button']['AccountingFieldsinQuery'] = "Kolom Akuntansi dalam Kueri:";
$l['button']['OrderBy'] = "Urutkan Berdasarkan";
$l['button']['HotspotAccounting'] = "Akuntansi Hotspot";
$l['button']['HotspotsComparison'] = "Perbandingan Hotspot";
$l['button']['CleanupStaleSessions'] = "Bersihkan Sesi Kedaluwarsa";
$l['button']['DeleteAccountingRecords'] = "Hapus Catatan Akuntansi";
$l['button']['ListUsers'] = "Daftar Pengguna";
$l['button']['ListBatches'] = "Daftar Batch";
$l['button']['RemoveBatch'] = "Hapus Batch";
$l['button']['ImportUsers'] = "Impor Pengguna";
$l['button']['NewUser'] = "Pengguna Baru";
$l['button']['NewUserQuick'] = "Pengguna Baru - Tambah Cepat";
$l['button']['BatchAddUsers'] = "Tambah Pengguna Massal";
$l['button']['EditUser'] = "Ubah Pengguna";
$l['button']['SearchUsers'] = "Cari Pengguna";
$l['button']['RemoveUsers'] = "Hapus Pengguna";
$l['button']['ListHotspots'] = "Daftar Hotspot";
$l['button']['NewHotspot'] = "Hotspot Baru";
$l['button']['EditHotspot'] = "Ubah Hotspot";
$l['button']['RemoveHotspot'] = "Hapus Hotspot";
$l['button']['ListIPPools'] = "Daftar IP-Pool";
$l['button']['NewIPPool'] = "IP-Pool Baru";
$l['button']['EditIPPool'] = "Ubah IP-Pool";
$l['button']['RemoveIPPool'] = "Hapus IP-Pool";
$l['button']['ListNAS'] = "Daftar NAS";
$l['button']['NewNAS'] = "NAS Baru";
$l['button']['EditNAS'] = "Ubah NAS";
$l['button']['RemoveNAS'] = "Hapus NAS";
$l['button']['ListHG'] = "Daftar HuntGroup";
$l['button']['NewHG'] = "HuntGroup Baru";
$l['button']['EditHG'] = "Ubah HuntGroup";
$l['button']['RemoveHG'] = "Hapus HuntGroup";
$l['button']['ListUserGroup'] = "Daftar Pemetaan User-Group";
$l['button']['ListUsersGroup'] = "Daftar Pemetaan Grup Pengguna";
$l['button']['NewUserGroup'] = "Pemetaan User-Group Baru";
$l['button']['EditUserGroup'] = "Ubah Pemetaan User-Group";
$l['button']['RemoveUserGroup'] = "Hapus Pemetaan User-Group";
$l['button']['ListProfiles'] = "Daftar Profil";
$l['button']['NewProfile'] = "Profil Baru";
$l['button']['EditProfile'] = "Ubah Profil";
$l['button']['DuplicateProfile'] = "Duplikat Profil";
$l['button']['RemoveProfile'] = "Hapus Profil";
$l['button']['ListGroupReply'] = "Daftar Pemetaan Group Reply";
$l['button']['SearchGroupReply'] = "Cari Group Reply";
$l['button']['NewGroupReply'] = "Pemetaan Group Reply Baru";
$l['button']['EditGroupReply'] = "Ubah Pemetaan Group Reply";
$l['button']['RemoveGroupReply'] = "Hapus Pemetaan Group Reply";
$l['button']['ListGroupCheck'] = "Daftar Pemetaan Group Check";
$l['button']['SearchGroupCheck'] = "Cari Group Check";
$l['button']['NewGroupCheck'] = "Pemetaan Group Check Baru";
$l['button']['EditGroupCheck'] = "Ubah Pemetaan Group Check";
$l['button']['RemoveGroupCheck'] = "Hapus Pemetaan Group Check";
$l['button']['UserAccounting'] = "Akuntansi Pengguna";
$l['button']['IPAccounting'] = "Akuntansi IP";
$l['button']['NASIPAccounting'] = "Akuntansi IP NAS";
$l['button']['NASIPAccountingOnlyActive'] = "Tampilkan hanya aktif";
$l['button']['DateAccounting'] = "Akuntansi Berdasarkan Tanggal";
$l['button']['AllRecords'] = "Semua Rekaman";
$l['button']['ActiveRecords'] = "Rekaman Aktif";
$l['button']['PlanUsage'] = "Penggunaan Paket";
$l['button']['OnlineUsers'] = "Pengguna Online";
$l['button']['LastConnectionAttempts'] = "Upaya Koneksi Terakhir";
$l['button']['TopUser'] = "Pengguna Teratas";
$l['button']['History'] = "Riwayat";
$l['button']['ServerStatus'] = "Status Server";
$l['button']['ServicesStatus'] = "Status Layanan";
$l['button']['daloRADIUSLog'] = "Log daloRADIUS";
$l['button']['RadiusLog'] = "Log RADIUS";
$l['button']['SystemLog'] = "Log Sistem";
$l['button']['BootLog'] = "Log Boot";
$l['button']['UserLogins'] = "Login Pengguna";
$l['button']['UserDownloads'] = "Unduhan Pengguna";
$l['button']['UserUploads'] = "Unggahan Pengguna";
$l['button']['TotalLogins'] = "Total Login";
$l['button']['TotalTraffic'] = "Total Lalu Lintas";
$l['button']['LoggedUsers'] = "Pengguna Login";
$l['button']['ViewMAP'] = "Lihat Peta";
$l['button']['EditMAP'] = "Ubah Peta";
$l['button']['RegisterGoogleMapsAPI'] = "Daftarkan API GoogleMaps";
$l['button']['UserSettings'] = "Pengaturan Pengguna";
$l['button']['DatabaseSettings'] = "Pengaturan Database";
$l['button']['LanguageSettings'] = "Pengaturan Bahasa";
$l['button']['LoggingSettings'] = "Pengaturan Logging";
$l['button']['InterfaceSettings'] = "Pengaturan Antarmuka";
$l['button']['ReAssignPlanProfiles'] = "Tetapkan Ulang Profil Paket";
$l['button']['TestUserConnectivity'] = "Uji Konektivitas Pengguna";
$l['button']['DisconnectUser'] = "Putuskan Pengguna";
$l['button']['ManageBackups'] = "Kelola Backup";
$l['button']['CreateBackups'] = "Buat Backup";
$l['button']['ListOperators'] = "Daftar Operator";
$l['button']['NewOperator'] = "Operator Baru";
$l['button']['EditOperator'] = "Ubah Operator";
$l['button']['RemoveOperator'] = "Hapus Operator";
$l['button']['ProcessQuery'] = "Proses Kueri";

$l['title']['Dashboard'] = "Dashboard";
$l['title']['DashboardAlerts'] = "Peringatan";
$l['title']['Invoice'] = "Faktur";
$l['title']['Invoices'] = "Faktur";
$l['title']['InvoiceRemoval'] = "Hapus Faktur";
$l['title']['Payments'] = "Pembayaran";
$l['title']['Items'] = "Item";
$l['title']['PayTypeInfo'] = "Informasi Jenis Pembayaran";
$l['title']['PaymentInfo'] = "Informasi Pembayaran";
$l['title']['RateInfo'] = "Informasi Tarif";
$l['title']['PlanInfo'] = "Informasi Paket";
$l['title']['TimeSettings'] = "Pengaturan Waktu";
$l['title']['BandwidthSettings'] = "Pengaturan Bandwidth";
$l['title']['PlanRemoval'] = "Hapus Paket";
$l['title']['BatchRemoval'] = "Hapus Batch";
$l['title']['Backups'] = "Backup";
$l['title']['BusinessInfo'] = "Informasi Bisnis";
$l['title']['RealmInfo'] = "Informasi Realm";
$l['title']['ProxyInfo'] = "Informasi Proxy";
$l['title']['AccountRemoval'] = "Hapus Akun";
$l['title']['AccountInfo'] = "Informasi Akun";
$l['title']['Profiles'] = "Profil";
$l['title']['ProfileInfo'] = "Informasi Profil";
$l['title']['GroupInfo'] = "Informasi Grup";
$l['title']['GroupAttributes'] = "Atribut Grup";
$l['title']['NASInfo'] = "Informasi NAS";
$l['title']['NASAdvanced'] = "NAS Lanjutan";
$l['title']['HGInfo'] = "Informasi HG";
$l['title']['UserInfo'] = "Informasi Pengguna";
$l['title']['BillingInfo'] = "Informasi Penagihan";
$l['title']['Attributes'] = "Atribut";
$l['title']['ProfileAttributes'] = "Atribut Profil";
$l['title']['HotspotInfo'] = "Informasi Hotspot";
$l['title']['HotspotRemoval'] = "Hapus Hotspot";

$l['Tooltip']['planNameTooltip'] = "Nama paket yang mudah dikenali untuk menjelaskan karakteristik paket.";
$l['Tooltip']['planIdTooltip'] = "ID paket.";
$l['Tooltip']['planTimeTypeTooltip'] = "Jenis waktu paket.";
$l['Tooltip']['planTimeBankTooltip'] = "Jumlah waktu yang tersedia pada paket.";
$l['Tooltip']['planTimeRefillCostTooltip'] = "Biaya isi ulang waktu.";
$l['Tooltip']['planTrafficRefillCostTooltip'] = "Biaya isi ulang trafik.";
$l['Tooltip']['planBandwidthUpTooltip'] = "Bandwidth unggah paket.";
$l['Tooltip']['planBandwidthDownTooltip'] = "Bandwidth unduh paket.";
$l['Tooltip']['planTrafficTotalTooltip'] = "Total kuota trafik paket.";
$l['Tooltip']['planTrafficDownTooltip'] = "Kuota trafik unduh paket.";
$l['Tooltip']['planTrafficUpTooltip'] = "Kuota trafik unggah paket.";
$l['Tooltip']['planRecurringTooltip'] = "Aktifkan penagihan berulang.";
$l['Tooltip']['planRecurringBillingScheduleTooltip'] = "Jadwal penagihan berulang.";
$l['Tooltip']['planRecurringPeriodTooltip'] = "Periode penagihan berulang.";
$l['Tooltip']['planCostTooltip'] = "Biaya paket.";
$l['Tooltip']['planSetupCostTooltip'] = "Biaya pemasangan paket.";
$l['Tooltip']['planTaxTooltip'] = "Pajak paket.";
$l['Tooltip']['planCurrencyTooltip'] = "Mata uang paket.";
$l['Tooltip']['planGroupTooltip'] = "Grup atau profil yang terkait dengan paket.";

$l['menu']['Home'] = "Beranda";
$l['menu']['Managment'] = "Manajemen";
$l['menu']['Reports'] = "Laporan";
$l['menu']['Accounting'] = "Akuntansi";
$l['menu']['Billing'] = "Penagihan";
$l['menu']['Gis'] = "GIS";
$l['menu']['Graphs'] = "Grafik";
$l['menu']['Config'] = "Konfigurasi";
$l['menu']['Help'] = "Bantuan";
$l['menu']['PPPoE Users'] = "PPPoE Users";
$l['menu']['Hotspot Users'] = "Hotspot Users";
$l['menu']['Plans/Profiles'] = "Paket/Profil";
$l['menu']['Payments/Invoices'] = "Pembayaran/Invoice";
$l['menu']['NAS'] = "NAS";
$l['menu']['Settings'] = "Pengaturan";

$l['submenu']['Daftar'] = "Daftar";
$l['submenu']['Tambah'] = "Tambah";
$l['submenu']['Daftar Paket'] = "Daftar Paket";
$l['submenu']['Paket Baru'] = "Paket Baru";
$l['submenu']['Invoices'] = "Invoice";
$l['submenu']['Payments'] = "Pembayaran";
$l['submenu']['Pelanggan'] = "Pelanggan";
$l['submenu']['MikroTik'] = "MikroTik";
$l['submenu']['NAS RADIUS'] = "NAS RADIUS";
$l['submenu']['WA Gateway'] = "WA Gateway";
$l['submenu']['Bahasa'] = "Bahasa";
