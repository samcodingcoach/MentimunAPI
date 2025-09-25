<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

header("Content-Type: application/json; charset=UTF-8");

include "../../config/koneksi.php";

$json_data = file_get_contents("php://input");
$data = json_decode($json_data, true);

if ($data === null) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Data JSON tidak valid.']);
    exit();
}

// Mulai transaksi
$conn->begin_transaction();

try {
    // Baris ini akan memaksa mysqli untuk melempar exception di dalam blok try-catch
    mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

    // ---- BAGIAN 1 & 2: PESANAN & DETAIL ----
    $query_antri = "SELECT COUNT(id_pesanan) as antrian_hari_ini FROM pesanan WHERE DATE(tgl_cart) = CURDATE()";
    $result_antri = $conn->query($query_antri);
    $row_antri = $result_antri->fetch_assoc();
    $nomor_antri_baru = $row_antri['antrian_hari_ini'] + 1;

    $stmt_pesanan = $conn->prepare("INSERT INTO pesanan (id_user, id_konsumen, total_cart, status_checkout, id_meja, deviceid, nomor_antri) VALUES (?, ?, ?, ?, ?, ?, ?)");
    $stmt_pesanan->bind_param("iidsisi", $data['id_user'], $data['id_konsumen'], $data['total_cart'], $data['status_checkout'], $data['id_meja'], $data['deviceid'], $nomor_antri_baru);
    $stmt_pesanan->execute();
    $id_pesanan_baru = $conn->insert_id;

    $stmt_detail = $conn->prepare("INSERT INTO pesanan_detail (id_pesanan, id_produk_sell, qty, ta_dinein) VALUES (?, ?, ?, ?)");
    $id_produk_sell_loop = 0; $qty_loop = 0; $ta_dinein_loop = '';
    $stmt_detail->bind_param("iiis", $id_pesanan_baru, $id_produk_sell_loop, $qty_loop, $ta_dinein_loop);
    foreach ($data['pesanan_detail'] as $detail) {
        $id_produk_sell_loop = (int)$detail['id_produk_sell'];
        $qty_loop = (int)$detail['qty'];
        $ta_dinein_loop = $detail['ta_dinein'];
        $stmt_detail->execute();
    }
    
    // ---- BAGIAN 3 & 4: PEMBAYARAN ----
    if (isset($data['proses_pembayaran'])) {
        $pembayaran = $data['proses_pembayaran'];
        $detail_pembayaran = $pembayaran['pembayaran_detail'];

        $query_inc = "SELECT COUNT(id_checkout) as inc_hari_ini FROM proses_pembayaran WHERE DATE(tanggal_payment) = CURDATE()";
        $result_inc = $conn->query($query_inc);
        $row_inc = $result_inc->fetch_assoc();
        $increment = str_pad($row_inc['inc_hari_ini'] + 1, 4, '0', STR_PAD_LEFT);
        $kode_payment = "POS-" . $data['id_meja'] . "-" . date("ymd") . $increment;
        //$id_tagihan = "INV-" . $data['id_meja'] . "-" . date("ymd") . $increment;
        
        $stmt_pembayaran = $conn->prepare("INSERT INTO proses_pembayaran (kode_payment, id_pesanan, id_bayar, id_user, status, jumlah_uang, jumlah_dibayarkan, kembalian, model_diskon, nilai_nominal, total_diskon) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt_pembayaran->bind_param("siiisdddsdd", $kode_payment, $id_pesanan_baru, $pembayaran['id_bayar'], $pembayaran['id_user'], $pembayaran['status'], $pembayaran['jumlah_uang'], $pembayaran['jumlah_dibayarkan'], $pembayaran['kembalian'], $pembayaran['model_diskon'], $pembayaran['nilai_nominal'], $pembayaran['total_diskon']);
        $stmt_pembayaran->execute();

        $stmt_pembayaran_detail = $conn->prepare("INSERT INTO proses_pembayaran_detail (kode_payment, subtotal, biaya_pengemasan, service_charge, promo_diskon, ppn_resto) 
        VALUES (?, ?, ?, ?, ?, ?)");
        $stmt_pembayaran_detail->bind_param("sddddd", $kode_payment, $detail_pembayaran['subtotal'], $detail_pembayaran['biaya_pengemasan'], $detail_pembayaran['service_charge'], $detail_pembayaran['promo_diskon'], $detail_pembayaran['ppn_resto']);
        $stmt_pembayaran_detail->execute();
    }

    $conn->commit();
    echo json_encode(['status' => 'success', 'message' => 'Transaksi berhasil disimpan.']);

} catch (Exception $e) {
    $conn->rollback();
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Query Gagal: ' . $e->getMessage()]);
}

$conn->close();
?>