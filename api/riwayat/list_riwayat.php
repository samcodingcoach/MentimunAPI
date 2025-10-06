<?php
header('Content-Type: application/json');
error_reporting(E_ALL & ~E_NOTICE);

include "../../config/koneksi.php";
$tgl = empty($_GET['tgl']) ? date('Y-m-d') : $_GET['tgl'];

$sql = "SELECT
	proses_pembayaran.kode_payment, 
	proses_pembayaran.tanggal_payment, 
	metode_pembayaran.kategori, 
	konsumen.nama_konsumen, 
	pesanan.id_meja, 
	pesanan.nomor_antri, 
	pesanan.total_cart, 
	proses_pembayaran.`status`, 
	pesanan.id_tagihan
FROM
	proses_pembayaran
	INNER JOIN
	metode_pembayaran
	ON 
		proses_pembayaran.id_bayar = metode_pembayaran.id_bayar
	INNER JOIN
	pesanan
	ON 
		proses_pembayaran.id_pesanan = pesanan.id_pesanan
	INNER JOIN
	konsumen
	ON 
		pesanan.id_konsumen = konsumen.id_konsumen
		WHERE DATE(tanggal_payment) = '$tgl'
";

$result = mysqli_query($conn, $sql);
$data = [];

if (mysqli_num_rows($result) > 0) {
	while($row = mysqli_fetch_assoc($result)) {
		$row['mode_pesanan'] = ($row['id_meja'] >= 1) ? 'bag_takeaway.png' : 'dine.png';
		$row['keterangan'] = ($row['status'] == 1) ? 'DIBAYARKAN' : 'BELUM BAYAR';
		$data[] = $row;
	}
}

echo json_encode($data);
mysqli_close($conn);
?>
