<?php
header('Content-Type: application/json');
error_reporting(E_ALL & ~E_NOTICE);

include "../../config/koneksi.php";
$id_meja = $_GET['id_meja'];

$sql = "SELECT meja.id_meja, meja.nomor_meja FROM meja WHERE in_used = 1 and id_meja <> '$id_meja'";

$result = mysqli_query($conn, $sql);

$data = [];

if (mysqli_num_rows($result) > 0) {
	while($row = mysqli_fetch_assoc($result)) 
    {
		$data[] = $row;
	}
}

echo json_encode($data);
mysqli_close($conn);
?>