<?php
header('Content-Type: application/json');
error_reporting(E_ALL & ~E_NOTICE);

include "../../config/koneksi.php";

$sql = "SELECT
	dapur_order.id_order,
	dapur_order.kode_payment, 
	dapur_order.waktu_terima, 
	dapur_order.id_tagihan, 
	count(dapur_order_detail.id_pesanan_detail) as total_item, 
	SUM(CASE WHEN dapur_order_detail.ready = 1 THEN 1 ELSE 0 END) AS siap,
	SUM(CASE WHEN dapur_order_detail.ready = 2 THEN 1 ELSE 0 END) AS tersaji,
	SUM(CASE WHEN dapur_order_detail.ready = 3 THEN 1 ELSE 0 END) AS batal
FROM
	dapur_order
	INNER JOIN
	dapur_order_detail
	ON 
		dapur_order.id_order = dapur_order_detail.id_order
		WHERE DATE(waktu_terima) = CURDATE()
		GROUP BY dapur_order.id_order 
		ORDER BY id_order DESC";

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
