<?php
header('Content-Type: application/json');
error_reporting(E_ALL & ~E_NOTICE);

include "../../config/koneksi.php";
/* $id = $_GET['id_order'];

if (empty($id)) {
    echo json_encode(['error' => 'id_order is required']);
    http_response_code(400);
    exit;
} */


$sql = "SELECT
	proses_pembayaran.kode_payment, 
	DATE_FORMAT(proses_pembayaran.tanggal_payment,'%H:%i') as tgl, 
	konsumen.nama_konsumen, 
	pesanan.total_cart, 
	pesanan.id_meja, 
	proses_pembayaran.id_tagihan
FROM
	proses_pembayaran
	INNER JOIN
	pesanan
	ON 
		proses_pembayaran.id_pesanan = pesanan.id_pesanan
	INNER JOIN
	konsumen
	ON 
		pesanan.id_konsumen = konsumen.id_konsumen
    
  WHERE DATE(tanggal_payment) = CURDATE()
  AND proses_pembayaran.id_tagihan IS NOT NULL
    
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
