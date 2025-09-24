<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

header("Content-Type: application/json; charset=UTF-8");

include "../../config/koneksi.php"; // File ini membuat variabel $conn

$json_data = file_get_contents("php://input");
$data = json_decode($json_data, true);

if ($data === null) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Data JSON tidak valid.']);
    exit();
}

// Mulai transaksi
mysqli_begin_transaction($conn);

try {
    // ---- BAGIAN 1: PESANAN & DETAIL ----
    $query_antri = "SELECT COUNT(id_pesanan) as antrian_hari_ini FROM pesanan WHERE DATE(tgl_cart) = CURDATE()";
    $result_antri = mysqli_query($conn, $query_antri);
    $row_antri = mysqli_fetch_assoc($result_antri);
    $nomor_antri_baru = $row_antri['antrian_hari_ini'] + 1;

    $stmt_pesanan = mysqli_prepare($conn, "INSERT INTO pesanan (id_user, id_konsumen, total_cart, status_checkout, id_meja, deviceid, nomor_antri) VALUES (?, ?, ?, ?, ?, ?, ?)");
    mysqli_stmt_bind_param($stmt_pesanan, "iidsisi", $data['id_user'], $data['id_konsumen'], $data['total_cart'], $data['status_checkout'], $data['id_meja'], $data['deviceid'], $nomor_antri_baru);
    mysqli_stmt_execute($stmt_pesanan);
    $id_pesanan_baru = mysqli_insert_id($conn);

    $stmt_detail = mysqli_prepare($conn, "INSERT INTO pesanan_detail (id_pesanan, id_produk_sell, qty, ta_dinein) VALUES (?, ?, ?, ?)");
    foreach ($data['pesanan_detail'] as $detail) {
        mysqli_stmt_bind_param($stmt_detail, "iiis", $id_pesanan_baru, $detail['id_produk_sell'], $detail['qty'], $detail['ta_dinein']);
        mysqli_stmt_execute($stmt_detail);
    }
    
    // ---- BAGIAN 2: PEMBAYARAN & DETAIL PEMBAYARAN ----
    $pembayaran = $data['proses_pembayaran'];
    $detail_pembayaran = $pembayaran['pembayaran_detail'];

    $query_inc = "SELECT COUNT(id_checkout) as inc_hari_ini FROM proses_pembayaran WHERE DATE(tanggal_payment) = CURDATE()";
    $result_inc = mysqli_query($conn, $query_inc);
    $row_inc = mysqli_fetch_assoc($result_inc);
    $increment = str_pad($row_inc['inc_hari_ini'] + 1, 4, '0', STR_PAD_LEFT);
    $kode_payment = "POS-" . $data['id_meja'] . "-" . date("ymd") . $increment;
    $id_tagihan = "INV-" . $data['id_meja'] . "-" . date("ymd") . $increment;
    
    $stmt_pembayaran = mysqli_prepare($conn, "INSERT INTO proses_pembayaran (kode_payment, id_pesanan, id_bayar, id_user, status, jumlah_uang, jumlah_dibayarkan, kembalian, id_tagihan, model_diskon, nilai_nominal, total_diskon) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
    mysqli_stmt_bind_param($stmt_pembayaran, "siiisdddssdd", 
        $kode_payment, $id_pesanan_baru, $pembayaran['id_bayar'], $pembayaran['id_user'], $pembayaran['status'], 
        $pembayaran['jumlah_uang'], $pembayaran['jumlah_dibayarkan'], $pembayaran['kembalian'], 
        $id_tagihan, $pembayaran['model_diskon'], $pembayaran['nilai_nominal'], $pembayaran['total_diskon']
    );
    mysqli_stmt_execute($stmt_pembayaran);

    $stmt_pembayaran_detail = mysqli_prepare($conn, "INSERT INTO proses_pembayaran_detail (kode_payment, subtotal, biaya_pengemasan, service_charge, promo_diskon, ppn_resto) VALUES (?, ?, ?, ?, ?, ?)");
    mysqli_stmt_bind_param($stmt_pembayaran_detail, "sddddd", 
        $kode_payment, $detail_pembayaran['subtotal'], $detail_pembayaran['biaya_pengemasan'], 
        $detail_pembayaran['service_charge'], $detail_pembayaran['promo_diskon'], $detail_pembayaran['ppn_resto']
    );
    mysqli_stmt_execute($stmt_pembayaran_detail);

    // Jika semua berhasil, commit
    mysqli_commit($conn);
    
    echo json_encode(['status' => 'success', 'message' => 'Pesanan dan pembayaran berhasil disimpan.']);

} catch (Exception $e) {
    // Jika ada error, rollback
    mysqli_rollback($conn);
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Query Gagal: ' . $e->getMessage()]);
}

mysqli_close($conn);
?>