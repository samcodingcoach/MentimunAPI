<?php
header('Content-Type: application/json');
error_reporting(E_ALL & ~E_NOTICE);

include "../../config/koneksi.php";

$kode = $_GET['kode'];

// Validate input
if (empty($kode)) {
    http_response_code(400);
    echo json_encode(['error' => 'Kode payment is required']);
    exit;
}

// Use prepared statements to prevent SQL injection
$stmt = mysqli_prepare($conn, "SELECT * FROM view_headstruk WHERE kode_payment = ?");
mysqli_stmt_bind_param($stmt, "s", $kode);

$result = mysqli_stmt_execute($stmt);
$result_set = mysqli_stmt_get_result($stmt);

$receipt_data = array();
$items = array();

if (mysqli_num_rows($result_set) > 0) {
    // Get the header data
    $header = mysqli_fetch_assoc($result_set);
    
    // Now get the details
    $stmt_detail = mysqli_prepare($conn, "SELECT
        view_produk.nama_produk, 
        pesanan_detail.qty, 
        case
          WHEN pesanan_detail.ta_dinein = 1 then 'TAKE AWAY'
          ELSE 'DINE IN'
        end as mode_pesanan, 
        produk_sell.harga_jual AS harga,
        pesanan_detail.qty * produk_sell.harga_jual AS subtotal
    FROM
        pesanan_detail
        INNER JOIN produk_sell ON pesanan_detail.id_produk_sell = produk_sell.id_produk_sell
        INNER JOIN view_produk ON produk_sell.id_produk = view_produk.id_produk
        INNER JOIN proses_pembayaran ON pesanan_detail.id_pesanan = proses_pembayaran.id_pesanan
    WHERE
        kode_payment = ? AND pesanan_detail.ket = 0");
    mysqli_stmt_bind_param($stmt_detail, "s", $kode);
    
    $result_detail = mysqli_stmt_execute($stmt_detail);
    $result_detail_set = mysqli_stmt_get_result($stmt_detail);
    
    if (mysqli_num_rows($result_detail_set) > 0) {
        while($row_detail = mysqli_fetch_assoc($result_detail_set)) {
            $items[] = $row_detail;
        }
    }
    
    // Combine header and items into a proper receipt structure
    $receipt_data = array(
        'success' => true,
        'receipt' => array(
            'header' => $header,
            'items' => $items
        )
    );
} else {
    // No data found
    $receipt_data = array(
        'success' => false,
        'message' => 'Receipt not found'
    );
}

echo json_encode($receipt_data);

mysqli_stmt_close($stmt);
if (isset($stmt_detail)) {
    mysqli_stmt_close($stmt_detail);
}
mysqli_close($conn);

?>