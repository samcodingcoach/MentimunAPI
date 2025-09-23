<?php
header('Content-Type: application/json');
error_reporting(E_ALL & ~E_NOTICE);

include "../../config/koneksi.php";

$sql = "SELECT biaya_per_item FROM `takeaway_charge` ORDER BY id_ta DESC limit 1";

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
