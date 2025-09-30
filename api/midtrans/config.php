<?php

error_reporting(E_ALL & ~E_NOTICE);
include "../config/koneksi.php";

// Ambil serverKey dari database
$query = "SELECT serverkeymidtrans FROM perusahaan ORDER BY id_app DESC LIMIT 1";
$result = mysqli_query($conn, $query);

if ($result && mysqli_num_rows($result) > 0) {
    $row = mysqli_fetch_assoc($result);
    $serverKey = $row['serverkeymidtrans'];
} 
else 
{
    // Fallback jika gagal ambil dari DB
    // $serverKey = 'SB-Mid-server-IV-Hqe8N16ymtZ4Z55HnxyhY'; // Optional fallback key
}

// require_once dirname(__FILE__) . '/vendor/autoload.php'; // Jika pakai Composer
require_once 'Midtrans.php'; // Jika manual include file Midtrans SDK

\Midtrans\Config::$serverKey = $serverKey;
\Midtrans\Config::$isProduction = false; // Ganti ke true untuk mode produksi
\Midtrans\Config::$isSanitized = true;   // Aktifkan data sanitasi
\Midtrans\Config::$is3ds = true;         // Aktifkan 3DS untuk kartu kredit

?>
