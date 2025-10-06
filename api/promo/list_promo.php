<?php
header('Content-Type: application/json');
error_reporting(E_ALL & ~E_NOTICE);

include "../../config/koneksi.php";

$sql = "SELECT
	id_promo, 
	nama_promo, 
	kode_promo, 
	tanggalmulai_promo, 
	DATE_FORMAT(tanggalselesai_promo,'%d %M %y') as tanggalselesai_promo, 
    pilihan_promo,
	CASE 
    when pilihan_promo = 'persen' THEN persen 
    when pilihan_promo = 'nominal' THEN nominal
  END as nilai,

	kuota, 
	min_pembelian
FROM
	promo
  WHERE aktif = 1 and kuota >= 1 and DATE(tanggalselesai_promo) >= CURDATE()
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
