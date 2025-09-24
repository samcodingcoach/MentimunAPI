<?php
// Tampilkan semua error untuk debugging maksimal
error_reporting(E_ALL);
ini_set('display_errors', 1);

header("Content-Type: application/json; charset=UTF-8");

include "../../config/koneksi.php";
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

// PENTING: Ganti 999 dengan id_pesanan yang VALID dan SUDAH ADA di tabel 'pesanan' Anda.
// Cek di phpMyAdmin, ambil salah satu id_pesanan yang sudah berhasil tersimpan.
$id_pesanan_tes = 999; 

echo "Memulai tes INSERT ke proses_pembayaran...<br>";

try {
    $stmt_pembayaran = $conn->prepare(
        "INSERT INTO proses_pembayaran (kode_payment, id_pesanan, id_bayar, id_user, status, jumlah_uang, jumlah_dibayarkan, kembalian, id_tagihan, model_diskon, nilai_nominal, total_diskon) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
    );
    
    // Kita gunakan data statis/hardcoded untuk tes
    $kode_payment_tes = "TES-POS-01";
    $id_bayar_tes = 1;
    $id_user_tes = 4;
    $status_tes = '1';
    $jumlah_uang_tes = 150000.0;
    $jumlah_dibayarkan_tes = 145000.0;
    $kembalian_tes = 5000.0;
    $id_tagihan_tes = "TES-INV-01";
    $model_diskon_tes = "nominal";
    $nilai_nominal_tes = 0.0;
    $total_diskon_tes = 0.0;

    $stmt_pembayaran->bind_param("siiisdddssdd", 
        $kode_payment_tes, $id_pesanan_tes, $id_bayar_tes, $id_user_tes, $status_tes,
        $jumlah_uang_tes, $jumlah_dibayarkan_tes, $kembalian_tes, $id_tagihan_tes,
        $model_diskon_tes, $nilai_nominal_tes, $total_diskon_tes
    );

    $stmt_pembayaran->execute();

    echo json_encode(['status' => 'sukses', 'message' => 'Tes INSERT ke proses_pembayaran BERHASIL!']);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'TES GAGAL: ' . $e->getMessage()]);
}

$conn->close();

?>