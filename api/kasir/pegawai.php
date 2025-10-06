<?php
header('Content-Type: application/json');
error_reporting(E_ALL & ~E_NOTICE);

$id = $_GET['id_user'];
include "../../config/koneksi.php";
$sql = "SELECT * FROM pegawai WHERE id_user = '$id' and jabatan <> 'Admin'
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
