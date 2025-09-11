<?php
/**
 * Fungsi enkripsi dan dekripsi password menggunakan AES-256-CBC
 * Menggunakan nomor HP sebagai key untuk enkripsi
 */

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

/**
 * Fungsi untuk memverifikasi password
 * @param string $inputPassword - Password yang diinput user
 * @param string $encryptedPassword - Password terenkripsi dari database
 * @param string $key - Key untuk dekripsi (nomor HP)
 * @return bool
 */
function verifyPassword($inputPassword, $encryptedPassword, $key) {
    $decryptedPassword = decryptPassword($encryptedPassword, $key);
    return $inputPassword === $decryptedPassword;
}

/**
 * Fungsi untuk generate password terenkripsi baru
 * @param string $plainPassword - Password plain text
 * @param string $key - Key untuk enkripsi (nomor HP)
 * @return string
 */
function generateEncryptedPassword($plainPassword, $key) {
    return encryptPassword($plainPassword, $key);
}
?>