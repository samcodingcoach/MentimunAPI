<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
header("Content-Type: application/json; charset=UTF-8");
include "../../config/koneksi.php";
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

$json_data = file_get_contents("php://input");
$data = json_decode($json_data, true);

if ($data === null) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Data JSON tidak valid.']);
    exit();
}

// Validasi field wajib dasar
if (!isset($data['proses_pembayaran']) || !isset($data['total_cart']) || !isset($data['status_checkout']) || !isset($data['pesanan_detail'])) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Field wajib tidak lengkap.']);
    exit();
}

$pembayaran = $data['proses_pembayaran'];
$kode_payment_existing = $pembayaran['kode_payment'] ?? null;

if (!$kode_payment_existing) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Kode payment tidak ditemukan dalam data.']);
    exit();
}

mysqli_begin_transaction($conn);

try {
    // ---- 1. DAPATKAN ID PESANAN ----
    $stmt_cek = mysqli_prepare($conn, "SELECT id_pesanan FROM proses_pembayaran WHERE kode_payment = ?");
    mysqli_stmt_bind_param($stmt_cek, "s", $kode_payment_existing);
    mysqli_stmt_execute($stmt_cek);
    $result_cek = mysqli_stmt_get_result($stmt_cek);
    $row_cek = mysqli_fetch_assoc($result_cek);
    mysqli_stmt_close($stmt_cek);

    if (!$row_cek) {
        throw new Exception("Kode pembayaran '$kode_payment_existing' tidak ditemukan.");
    }
    $id_pesanan_to_update = $row_cek['id_pesanan'];

    // ---- 2. UPDATE TABEL pesanan ----
    $stmt_pesanan = mysqli_prepare($conn, "UPDATE pesanan SET total_cart = ? WHERE id_pesanan = ?");
    mysqli_stmt_bind_param($stmt_pesanan, "di", $data['total_cart'], $id_pesanan_to_update);
    mysqli_stmt_execute($stmt_pesanan);
    mysqli_stmt_close($stmt_pesanan);

    // ---- 3. INSERT HANYA ITEM BARU ke pesanan_detail ----
    $pesanan_detail = $data['pesanan_detail'];
    if (!is_array($pesanan_detail)) {
        throw new Exception("pesanan_detail harus berupa array.");
    }

    if (!empty($pesanan_detail)) {
        $stmt_detail = mysqli_prepare($conn, "INSERT INTO pesanan_detail (id_pesanan, id_produk_sell, qty, ta_dinein) VALUES (?, ?, ?, ?)");
        foreach ($pesanan_detail as $detail) {
            if (!isset($detail['id_produk_sell']) || !isset($detail['qty']) || !isset($detail['ta_dinein'])) {
                continue; // skip item tidak lengkap
            }
            // Hanya simpan item yang BUKAN frozen
            if (!isset($detail['is_frozen']) || $detail['is_frozen'] === false) {
                mysqli_stmt_bind_param($stmt_detail, "iiis", 
                    $id_pesanan_to_update, 
                    $detail['id_produk_sell'], 
                    $detail['qty'], 
                    $detail['ta_dinein']
                );
                mysqli_stmt_execute($stmt_detail);
            }
        }
        mysqli_stmt_close($stmt_detail);
    }
    
    // ---- 4. UPDATE TABEL proses_pembayaran (HANYA id_bayar dan status) ----
    $stmt_pembayaran = mysqli_prepare($conn, "UPDATE proses_pembayaran SET id_bayar = ?, status = ? WHERE kode_payment = ?");
    mysqli_stmt_bind_param($stmt_pembayaran, "iss", 
        $pembayaran['id_bayar'], 
        $pembayaran['status'],
        $kode_payment_existing
    );
    mysqli_stmt_execute($stmt_pembayaran);
    mysqli_stmt_close($stmt_pembayaran);

    // TIDAK ADA UPDATE KE proses_pembayaran_detail

    mysqli_commit($conn);
    echo json_encode(['status' => 'success', 'message' => 'Pesanan invoice berhasil diperbarui.']);

} catch (Exception $e) {
    mysqli_rollback($conn);
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Gagal memperbarui pesanan: ' . $e->getMessage()]);
} finally {
    mysqli_close($conn);
}
?>