perintah 1:
hallo gemini ini adalah project php saya, pertama saya ingin anda membuat halaman login di public/admin dengan bootstrapp terbaru , css bootstrapp anda includekan di project ini dan bukan cdn

perintah 2
nampaknya belum ditengah horizontal center vertical center
halaman login saya tidak pakai sign up

berikan copyright ITDEV 2025
berikan animasi background
berikan sebuah sistem validasi login jika kosong email dan password

perintah ke 3
kita akan melakukan login admin dengan data yang ada didatabase
-saya harap anda masuk ke database saya, silahkan baca config/koneksi.php

halaman login kita mengecek table pegawai email dan password.
id_user int(11) NO PRI auto_increment
nama_lengkap varchar(30) YES
jabatan enum('Admin','Kasir','Pramusaji','Dapur') YES
nomor_hp varchar(15) YES
email varchar(30) YES
password varchar(255) YES
aktif tinyint(4) YES 1
password sendiri di table sudah di enkripsi dengan fungsi dibawah ini (project lama saya)

<?php
function encryptPassword($password, $key) {
    $cipher = "AES-256-CBC";
    $iv = substr(hash('sha256', $key), 0, 16);
    $encrypted = openssl_encrypt($password, $cipher, $key, 0, $iv);
    return $encrypted;
}

function decryptPassword($encryptedPassword, $key) {
    $cipher = "AES-256-CBC";
    $iv = substr(hash('sha256', $key), 0, 16);
    $decrypted = openssl_decrypt($encryptedPassword, $cipher, $key, 0, $iv);
    return $decrypted;
}

// Data
$plaintext = "12341234"; //kolom password
$key = "085652024118"; //kolom nomor_hp

// Proses Enkripsi
$encrypted = encryptPassword($plaintext, $key);
echo "Encrypted: $encrypted\n";

// Proses Dekripsi
$decrypted = decryptPassword($encrypted, $key);
echo "Decrypted: $decrypted\n";

?>
