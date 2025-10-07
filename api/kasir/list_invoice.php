<?php
header('Content-Type: application/json');
error_reporting(E_ALL & ~E_NOTICE);

include "../../config/koneksi.php";

$sql = "SELECT
	proses_pembayaran.id_pesanan, 
	proses_pembayaran.kode_payment, 
	DATE_FORMAT(proses_pembayaran.tanggal_payment,'%H:%i') AS tgl, 
	konsumen.nama_konsumen, 
	pesanan.id_meja, 
	proses_pembayaran.id_tagihan, 
	SUM(view_invoice.total_dengan_ppn) AS total_cart
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
	INNER JOIN
	view_invoice
	ON 
		proses_pembayaran.id_pesanan = view_invoice.id_pesanan
WHERE
	DATE(tanggal_payment) = CURDATE() AND
	proses_pembayaran.id_tagihan IS NOT NULL AND
	proses_pembayaran.`status` = 0
  
  GROUP BY pesanan.id_pesanan
  
  
";

$result = mysqli_query($conn, $sql);
$data = [];

if (mysqli_num_rows($result) > 0) {
	while($row = mysqli_fetch_assoc($result)) {
		$row['total_cart'] = floor($row['total_cart'] / 100) * 100;
		$data[] = $row;
	}
}

echo json_encode($data);
mysqli_close($conn);
?>
