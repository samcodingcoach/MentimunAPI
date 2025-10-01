<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
header("Content-Type: application/json; charset=UTF-8");

// Include both database and Midtrans configurations
include "../../config/koneksi.php";
require_once __DIR__ . '/../midtrans/config.php';
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
$detail_pembayaran = $pembayaran['pembayaran_detail'] ?? [];
$kode_payment_existing = $pembayaran['kode_payment'] ?? null;

if (!$kode_payment_existing) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Kode payment tidak ditemukan dalam data.']);
    exit();
}

// Initialize QRIS URL variable
$qrisUrl = null;

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
    $stmt_pesanan = mysqli_prepare($conn, "UPDATE pesanan SET total_cart = ?, status_checkout = ? WHERE id_pesanan = ?");
    mysqli_stmt_bind_param($stmt_pesanan, "dsi", $data['total_cart'], $data['status_checkout'], $id_pesanan_to_update);
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
                continue; // skip item tidak lengkap, atau ganti dengan throw jika ingin strict
            }
            if (!isset($detail['is_frozen']) || $detail['is_frozen'] == false) {
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
    
    // ---- 4. UPDATE TABEL proses_pembayaran ----
    // If payment method is QRIS (id_bayar = 3), force status to 0 (pending)
    $payment_status = $pembayaran['status'];
    if ((int)$pembayaran['id_bayar'] === 3) {
        $payment_status = 0;
    }

    $stmt_pembayaran = mysqli_prepare($conn, "UPDATE proses_pembayaran SET id_bayar = ?, status = ?, jumlah_uang = ?, jumlah_dibayarkan = ?, kembalian = ?, model_diskon = ?, nilai_nominal = ?, total_diskon = ? WHERE kode_payment = ?");
    
    mysqli_stmt_bind_param($stmt_pembayaran, "isdddsdds", 
        $pembayaran['id_bayar'], 
        $payment_status, 
        $pembayaran['jumlah_uang'], 
        $pembayaran['jumlah_dibayarkan'], 
        $pembayaran['kembalian'], 
        $pembayaran['model_diskon'], 
        $pembayaran['nilai_nominal'], 
        $pembayaran['total_diskon'],
        $kode_payment_existing
    );
    mysqli_stmt_execute($stmt_pembayaran);
    mysqli_stmt_close($stmt_pembayaran);

    // ---- 5. UPDATE TABEL proses_pembayaran_detail ----
    $stmt_pembayaran_detail = mysqli_prepare($conn, "UPDATE proses_pembayaran_detail SET subtotal = ?, biaya_pengemasan = ?, service_charge = ?, promo_diskon = ?, ppn_resto = ? WHERE kode_payment = ?");
    mysqli_stmt_bind_param($stmt_pembayaran_detail, "ddddds", 
        $detail_pembayaran['subtotal'], 
        $detail_pembayaran['biaya_pengemasan'], 
        $detail_pembayaran['service_charge'], 
        $detail_pembayaran['promo_diskon'], 
        $detail_pembayaran['ppn_resto'],
        $kode_payment_existing
    );
    mysqli_stmt_execute($stmt_pembayaran_detail);
    mysqli_stmt_close($stmt_pembayaran_detail);

    // ---- 6. QRIS Generation Logic ----
    // Check if the payment method is QRIS (id_bayar = 3)
    if ((int)$pembayaran['id_bayar'] === 3) {
        // Prepare parameters for Midtrans API call
        $params = [
            'payment_type' => 'qris',
            'transaction_details' => [
                'order_id' => $kode_payment_existing, // Use the existing kode_payment
                'gross_amount' => $pembayaran['jumlah_uang'], // Use the total amount
            ],
        ];

        // The Midtrans call is inside the main try block.
        // If it fails, it will throw an Exception, which will be caught
        // by the outer catch block, triggering a full database rollback.
        $qrisTransaction = \Midtrans\CoreApi::charge($params);
        $qrisUrl = $qrisTransaction->actions[0]->url;
    }

    mysqli_commit($conn);

    // Prepare the final JSON response
    $response = [
        'status' => 'success',
        'message' => 'Pesanan berhasil diperbarui dan dibayar.'
    ];

    // Add the qris_url to the response if it was generated
    if ($qrisUrl !== null) {
        $response['qris_url'] = $qrisUrl;
        $response['message'] = 'Transaksi Qris Berhasil.'; // Overwrite message for QRIS
    }

    echo json_encode($response);

} catch (Exception $e) {
    mysqli_rollback($conn);
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Gagal memperbarui pesanan: ' . $e->getMessage()]);
} finally {
    mysqli_close($conn);
}
?>