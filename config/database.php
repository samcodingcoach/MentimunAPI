<?php
// Informasi koneksi ke database MySQL
$host = 'localhost';
$username = 'samsu';
$password = 'samsu';
$database = 'resto_db';

date_default_timezone_set("Asia/Makassar");

try {
    // Membuat koneksi PDO ke database MySQL
    $dsn = "mysql:host=$host;dbname=$database;charset=utf8mb4";
    $pdo = new PDO($dsn, $username, $password, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);
} catch (PDOException $e) {
    die("Koneksi ke database gagal: " . $e->getMessage());
}
?>