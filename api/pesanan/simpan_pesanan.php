<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

header("Content-Type: application/json; charset=UTF-8");

// Ganti dengan path file koneksi Anda yang benar
include "../../config/koneksi.php";

// Memaksa mysqli untuk melempar 'exception' jika ada error database
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

$json_data = file_get_contents("php://input");
$data = json_decode($json_data, true);

if ($data === null) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Data JSON tidak valid.']);
    exit();
}

$conn->begin_transaction();

try {
    // Hitung Nomor Antrian
    $query_antri = "SELECT COUNT(id_pesanan) as antrian_hari_ini FROM pesanan WHERE DATE(tgl_cart) = CURDATE()";
    $result_antri = $conn->query($query_antri);
    $row_antri = $result_antri->fetch_assoc();
    $nomor_antri_baru = $row_antri['antrian_hari_ini'] + 1;

    // Casting tipe data
    $id_user = (int)$data['id_user'];
    $id_konsumen = (int)$data['id_konsumen'];
    $total_cart = (double)$data['total_cart'];
    $id_meja = (int)$data['id_meja'];

    // Insert ke tabel 'pesanan'
    $stmt_pesanan = $conn->prepare("INSERT INTO pesanan (id_user, id_konsumen, total_cart, status_checkout, id_meja, deviceid, nomor_antri) VALUES (?, ?, ?, ?, ?, ?, ?)");
    $stmt_pesanan->bind_param("iidsisi", $id_user, $id_konsumen, $total_cart, $data['status_checkout'], $id_meja, $data['deviceid'], $nomor_antri_baru);
    $stmt_pesanan->execute();
    
    if ($stmt_pesanan->affected_rows === 0) {
        throw new Exception("Gagal menyimpan data pesanan utama.");
    }

    $id_pesanan_baru = $conn->insert_id;

    // Insert ke tabel 'pesanan_detail'
    $stmt_detail = $conn->prepare("INSERT INTO pesanan_detail (id_pesanan, id_produk_sell, qty, ta_dinein) VALUES (?, ?, ?, ?)");
    
    foreach ($data['pesanan_detail'] as $detail) {
        $id_produk_sell = (int)$detail['id_produk_sell'];
        $qty = (int)$detail['qty'];
        
        $stmt_detail->bind_param("iiis", $id_pesanan_baru, $id_produk_sell, $qty, $detail['ta_dinein']);
        $stmt_detail->execute();

        if ($stmt_detail->affected_rows === 0) {
            throw new Exception("Gagal menyimpan detail untuk produk ID: " . $id_produk_sell);
        }
    }
    
    $conn->commit();
    echo json_encode([
        'status' => 'success', 
        'message' => 'Pesanan berhasil disimpan.', 
        'id_pesanan' => $id_pesanan_baru,
        'nomor_antri' => $nomor_antri_baru
    ]);

} catch (Exception $e) {
    $conn->rollback();
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Query Gagal: ' . $e->getMessage()]);
}

$conn->close();
?>