<?php
// Informasi koneksi ke database MySQL
$host = 'localhost'; // Host database, misalnya 'localhost'
$username = 'samsu'; // Nama pengguna database
$password = 'samsu'; // Kata sandi pengguna database
$database = 'resto_db'; // Nama database yang ingin diakses

date_default_timezone_set("Asia/Makassar");

// Membuat koneksi ke database MySQL
$conn = new mysqli($host, $username, $password, $database);

// Memeriksa koneksi
if ($conn->connect_error) {
    die("Koneksi ke database gagal: " . $conn->connect_error);
} 
else 
{ 
    
}
?>
