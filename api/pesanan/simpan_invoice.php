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

mysqli_begin_transaction($conn);

try {
    // ---- 1. GENERATE KODE UNIK ----
    $query_inc = "SELECT COUNT(id_pesanan) as inc_hari_ini FROM pesanan WHERE DATE(tgl_cart) = CURDATE() and id_tagihan is not null";
    $result_inc = mysqli_query($conn, $query_inc);
    $row_inc = mysqli_fetch_assoc($result_inc);
    $increment = str_pad($row_inc['inc_hari_ini'] + 1, 4, '0', STR_PAD_LEFT);
    $id_tagihan = "INV-" . $data['id_meja'] . "-" . date("ymd") . $increment;
    $nomor_antri_baru = $row_inc['inc_hari_ini'] + 1;

    // ---- 2. INSERT KE TABEL pesanan ----
    $stmt_pesanan = mysqli_prepare($conn, "INSERT INTO pesanan (id_user, id_konsumen, total_cart, status_checkout, id_meja, deviceid, nomor_antri, id_tagihan) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");

    // Simpan ke variabel agar bisa di-bind
    $id_user = (int)$data['id_user'];
    $id_konsumen = (int)$data['id_konsumen'];
    $total_cart = (double)$data['total_cart'];
    $status_checkout = (string)$data['status_checkout'];
    $id_meja = (int)$data['id_meja'];
    $deviceid = (string)$data['deviceid'];

    mysqli_stmt_bind_param($stmt_pesanan, "iidsisis", 
        $id_user, $id_konsumen, $total_cart, $status_checkout, 
        $id_meja, $deviceid, $nomor_antri_baru, $id_tagihan
    );
    mysqli_stmt_execute($stmt_pesanan);
    $id_pesanan_baru = mysqli_insert_id($conn);
    mysqli_stmt_close($stmt_pesanan);

    // ---- 3. INSERT KE TABEL pesanan_detail ----
    $stmt_detail = mysqli_prepare($conn, "INSERT INTO pesanan_detail (id_pesanan, id_produk_sell, qty, ta_dinein) VALUES (?, ?, ?, ?)");
    $id_produk_sell_loop = 0; $qty_loop = 0; $ta_dinein_loop = '';
    mysqli_stmt_bind_param($stmt_detail, "iiis", $id_pesanan_baru, $id_produk_sell_loop, $qty_loop, $ta_dinein_loop);

    foreach ($data['pesanan_detail'] as $detail) {
        $id_produk_sell_loop = (int)$detail['id_produk_sell'];
        $qty_loop = (int)$detail['qty'];
        $ta_dinein_loop = (string)$detail['ta_dinein'];
        mysqli_stmt_execute($stmt_detail);
    }
    mysqli_stmt_close($stmt_detail);

    // ---- 4. INSERT SEDERHANA KE proses_pembayaran ----
    if (isset($data['proses_pembayaran'])) {
        $pembayaran = $data['proses_pembayaran'];

        // Sesuai instruksi Anda: kode_payment disamakan dengan id_tagihan
        $kode_payment = $id_tagihan;

        // Kita hanya menyertakan kolom yang disediakan di JSON
        $stmt_pembayaran = mysqli_prepare($conn, "INSERT INTO proses_pembayaran (kode_payment, id_pesanan, id_user, id_tagihan) VALUES (?, ?, ?, ?)");
        $id_user_pembayaran = (int)$pembayaran['id_user'];

        mysqli_stmt_bind_param($stmt_pembayaran, "siis", 
            $kode_payment, 
            $id_pesanan_baru, 
            $id_user_pembayaran, 
            $id_tagihan
        );
        mysqli_stmt_execute($stmt_pembayaran);
        mysqli_stmt_close($stmt_pembayaran);

        // ---- 5. INSERT SEDERHANA KE proses_pembayaran_detail ----
        $stmt_pembayaran_detail = mysqli_prepare($conn, "INSERT INTO proses_pembayaran_detail (kode_payment) VALUES (?)");
        mysqli_stmt_bind_param($stmt_pembayaran_detail, "s", $kode_payment);
        mysqli_stmt_execute($stmt_pembayaran_detail);
        mysqli_stmt_close($stmt_pembayaran_detail);
    }

    mysqli_commit($conn);
    echo json_encode(['status' => 'success', 'message' => 'Invoice berhasil disimpan.']);

} catch (Exception $e) {
    mysqli_rollback($conn);
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Gagal menyimpan invoice: ' . $e->getMessage()]);
}

mysqli_close($conn);
?>