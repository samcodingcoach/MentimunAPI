<?php
header('Content-Type: application/json');
error_reporting(E_ALL & ~E_NOTICE);

include "../../config/koneksi.php";
$id = $_GET['id_order'];

if (empty($id)) {
    echo json_encode(['error' => 'id_order is required']);
    http_response_code(400);
    exit;
}


$sql = "SELECT
	dapur_order_detail.id_order, 
	dapur_order_detail.id_pesanan_detail, 
	view_produk2.kode_produk, 
	view_produk2.nama_produk, 
	view_produk2.nama_kategori, 
	dapur_order_detail.ready, 
	dapur_order_detail.tgl_update, 
	dapur_order_detail.id_order_detail, 
	pesanan_detail.id_pesanan,
	DATE_FORMAT(waktu_batal,'%H:%m') as waktu_batal, 
	DATE_FORMAT(dapur_order_detail.waktu_ready,'%H:%m') as waktu_ready, 
  	DATE_FORMAT(dapur_order_detail.waktu_delivered,'%H:%m') as waktu_delivered, 
	pesanan_detail.ta_dinein, 
	pesanan_detail.qty
FROM
	dapur_order_detail
	INNER JOIN
	pesanan_detail
	ON 
		dapur_order_detail.id_pesanan_detail = pesanan_detail.id_pesanan_detail
	INNER JOIN
	view_produk2
	ON 
		pesanan_detail.id_produk_sell = view_produk2.id_produk_sell
    where id_order = $id
    ";

$result = mysqli_query($conn, $sql);
$data = [];

if (mysqli_num_rows($result) > 0) {
	while($row = mysqli_fetch_assoc($result)) {
		$data[] = $row;
	}
}

echo json_encode($data);
mysqli_close($conn);
?>
