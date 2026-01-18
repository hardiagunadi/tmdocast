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

$year = date('Y');
if ($year > 2023) {
    $year = "2023-$year";
}
$l['all']['copyright2'] = <<<EOF
<a target="_blank" href="https://github.com/filippolauria/daloradius">daloRADIUS</a><br>
Copyright &copy; 2007-2022 Liran Tal, Filippo Lauria $year.
EOF;

$l['all']['Amount'] = "Jumlah";
$l['all']['Balance'] = "Saldo";
$l['all']['ClientName'] = "Nama Klien";
$l['all']['Date'] = "Tanggal";
$l['all']['Download'] = "Unduh";
$l['all']['EndingDate'] = "Tanggal Akhir";
$l['all']['HotSpot'] = "Hotspot";
$l['all']['ID'] = "ID";
$l['all']['Invoice'] = "Faktur";
$l['all']['InvoiceStatus'] = "Status Faktur";
$l['all']['InvoiceType'] = "Jenis Faktur";
$l['all']['IPAddress'] = "Alamat IP";
$l['all']['Language'] = "Bahasa";
$l['all']['NASIPAddress'] = "Alamat IP NAS";
$l['all']['NewPassword'] = "Kata Sandi Baru";
$l['all']['Password'] = "Kata Sandi";
$l['all']['PaymentDate'] = "Tanggal";
$l['all']['StartingDate'] = "Tanggal Mulai";
$l['all']['StartTime'] = "Waktu Mulai";
$l['all']['Statistics'] = "Statistik";
$l['all']['Status'] = "Status";
$l['all']['StopTime'] = "Waktu Selesai";
$l['all']['Termination'] = "Terminasi";
$l['all']['TotalBilled'] = "Total Ditagih";
$l['all']['TotalPaid'] = "Total Dibayar";
$l['all']['TotalTime'] = "Total Waktu";
$l['all']['Upload'] = "Unggah";
$l['all']['Username'] = "Nama Pengguna";
$l['all']['CurrentPassword'] = "Kata Sandi Saat Ini";
$l['all']['VerifyPassword'] = "Verifikasi Kata Sandi";

$l['all']['Global'] = "Global";
$l['all']['Daily'] = "Harian";
$l['all']['Weekly'] = "Mingguan";
$l['all']['Monthly'] = "Bulanan";
$l['all']['Yearly'] = "Tahunan";

$l['button']['Accounting'] = "Akuntansi";
$l['button']['ChangeAuthPassword'] = "Ubah Kata Sandi Otentikasi";
$l['button']['ChangePortalPassword'] = "Ubah Kata Sandi Portal";
$l['button']['DateAccounting'] = "Akuntansi Berdasarkan Tanggal";
$l['button']['EditUserInfo'] = "Ubah Informasi Kontak";
$l['button']['GenerateReport'] = "Buat Laporan";
$l['button']['Graphs'] = "Grafik";
$l['button']['Preferences'] = "Preferensi";
$l['button']['ShowInvoice'] = "Tampilkan Faktur";

$l['button']['UserDownloads'] = "Lalu Lintas Unduh";
$l['button']['UserLogins'] = "Riwayat Login";
$l['button']['UserUploads'] = "Lalu Lintas Unggah";

$l['ContactInfo']['Address'] = "Alamat";
$l['ContactInfo']['City'] = "Kota";
$l['ContactInfo']['Company'] = "Organisasi";
$l['ContactInfo']['Country'] = "Negara";
$l['ContactInfo']['Department'] = "Unit Operasional";
$l['ContactInfo']['Email'] = "Email";
$l['ContactInfo']['FirstName'] = "Nama Depan";
$l['ContactInfo']['HomePhone'] = "Telepon Rumah";
$l['ContactInfo']['LastName'] = "Nama Belakang";
$l['ContactInfo']['MobilePhone'] = "Telepon Seluler";
$l['ContactInfo']['Notes'] = "Catatan";
$l['ContactInfo']['State'] = "Provinsi/Region";
$l['ContactInfo']['WorkPhone'] = "Telepon Kantor";
$l['ContactInfo']['Zip'] = "Kode Pos";

$l['helpPage']['acctdate'] = <<<EOF
<h2 class="fs-6">Akuntansi Berdasarkan Tanggal</h2>
<p>Menyediakan informasi akuntansi secara rinci untuk semua sesi di antara dua tanggal tertentu untuk pengguna tertentu.</p>
EOF;
$l['helpPage']['acctmain'] = '<h1 class="fs-5">Akuntansi Umum</h1>' . $l['helpPage']['acctdate'];
$l['helpPage']['billinvoicelist'] = "";
$l['helpPage']['billmain'] = "";

$l['helpPage']['graphsoveralldownload'] = sprintf('<h2 class="fs-6">%s</h2>', $l['button']['UserDownloads']) . <<<EOF
<p>Menghasilkan grafik yang menampilkan jumlah data yang Anda unduh dalam periode waktu tertentu.<br>
Grafik dilengkapi tabel daftar.</p>
EOF;

$l['helpPage']['graphsoverallupload'] = sprintf('<h2 class="fs-6">%s</h2>', $l['button']['UserUploads']) . <<<EOF
<p>Menghasilkan grafik yang menampilkan jumlah data yang Anda unggah dalam periode waktu tertentu.<br>
Grafik dilengkapi tabel daftar.</p>
EOF;

$l['helpPage']['graphsoveralllogins'] = sprintf('<h2 class="fs-6">%s</h2>', $l['button']['UserLogins']) . <<<EOF
<p>Menghasilkan grafik yang menampilkan aktivitas login Anda dalam periode waktu tertentu.<br>
Grafik menampilkan jumlah login (atau 'hit' ke NAS) dan dilengkapi tabel daftar.</p>
EOF;

$l['helpPage']['graphmain'] = '<h1 class="fs-5">Grafik</h1>'
                            . $l['helpPage']['graphsoveralllogins'] . $l['helpPage']['graphsoveralldownload']
                            . $l['helpPage']['graphsoverallupload'];

$l['helpPage']['loginUsersPortal'] = <<<EOF
<p>Yth. Pengguna,</p>
<p>Selamat datang di Portal Pengguna. Kami senang Anda bergabung!</p>

<p>Dengan login menggunakan nama pengguna dan kata sandi akun Anda, Anda dapat mengakses berbagai fitur. Misalnya, Anda dapat dengan mudah mengubah pengaturan kontak, memperbarui informasi pribadi, dan melihat data riwayat melalui grafik visual.</p>

<p>Kami sangat menjaga privasi dan keamanan Anda, jadi Anda tidak perlu khawatir karena semua data Anda disimpan dengan aman di database kami dan hanya dapat diakses oleh Anda dan staf kami yang berwenang.</p>

<p>Jika Anda membutuhkan bantuan atau memiliki pertanyaan, jangan ragu untuk menghubungi tim dukungan kami. Kami selalu siap membantu!</p>

<p>Hormat kami,<br/>
Tim FiloRADIUS.</p>
EOF;

$l['messages']['loginerror'] = <<<EOF
<h1 class="fs-5">Tidak Dapat Masuk</h1>
<p>Jika Anda mengalami kesulitan untuk login ke akun Anda, kemungkinan Anda memasukkan nama pengguna dan/atau kata sandi yang salah. Pastikan Anda telah memasukkan kredensial login dengan benar dan coba lagi.</p>
<p>Jika Anda masih tidak dapat login setelah memverifikasi informasi, silakan hubungi tim dukungan kami untuk bantuan. Kami selalu siap membantu Anda mendapatkan kembali akses ke akun Anda secepat mungkin.</p>
EOF;

$l['helpPage']['prefmain'] = "Di bagian ini, Anda dapat mengelola <strong>informasi kontak</strong> serta kata sandi login untuk portal web dan layanan kami.";
$l['helpPage']['prefpasswordedit'] = "Gunakan formulir di bawah ini untuk mengubah kata sandi Anda. Demi keamanan, Anda akan diminta memasukkan kata sandi lama dan memasukkan kata sandi baru dua kali untuk menghindari kesalahan.";
$l['helpPage']['prefuserinfoedit'] = "Gunakan formulir di bawah ini untuk memperbarui informasi kontak Anda. Anda dapat mengubah nama depan, nama belakang, alamat email, nomor telepon, dan detail lain sesuai kebutuhan. Pastikan meninjau perubahan sebelum menyimpan agar informasi yang diperbarui akurat.";

$l['Intro']['acctdate.php'] = "Akuntansi Urut Tanggal";
$l['Intro']['acctmain.php'] = "Halaman Akuntansi";
$l['Intro']['billinvoiceedit.php'] = "Menampilkan Faktur";
$l['Intro']['billinvoicereport.php'] = "Laporan Faktur";
$l['Intro']['billmain.php'] = "Halaman Penagihan";
$l['Intro']['graphmain.php'] = "Grafik Penggunaan";
$l['Intro']['graphsoveralldownload.php'] = "Unduhan Pengguna";
$l['Intro']['graphsoveralllogins.php'] = "Login Pengguna";
$l['Intro']['graphsoverallupload.php'] = "Unggahan Pengguna";
$l['Intro']['prefmain.php'] = "Halaman Preferensi";
$l['Intro']['prefpasswordedit.php'] = "Ubah Kata Sandi";
$l['Intro']['prefuserinfoedit.php'] = "Ubah Informasi Pengguna";
$l['menu']['Accounting'] = "Akuntansi";
$l['menu']['Billing'] = "Penagihan";
$l['menu']['Graphs'] = "Grafik";
$l['menu']['Home'] = "Beranda";
$l['menu']['Preferences'] = "Preferensi";
$l['menu']['Help'] = "Bantuan";


$l['text']['LoginPlease'] = "Silakan Login";
$l['text']['LoginRequired'] = "Login Diperlukan";
$l['title']['ContactInfo'] = "Informasi Kontak";
$l['title']['BusinessInfo'] = "Informasi Bisnis";
$l['title']['Invoice'] = "Faktur";
$l['title']['Items'] = "Item";
$l['Tooltip']['invoiceID'] = "Masukkan ID faktur";
