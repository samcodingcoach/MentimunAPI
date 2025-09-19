<?php
header('Content-Type: application/json');
error_reporting(E_ALL & ~E_NOTICE);

include "../../config/koneksi.php";
$sql = "SELECT
	promo.nama_promo
    FROM
	promo
    WHERE CURDATE() BETWEEN tanggalmulai_promo 
        AND tanggalselesai_promo 
        AND aktif = 1 
        AND kuota > 0
    ORDER BY tanggalselesai_promo DESC";

$result = mysqli_query($conn, $sql);

// Membuat array untuk menyimpan hasil query
$data = array();


if (mysqli_num_rows($result) > 0) {
    while($row = mysqli_fetch_assoc($result)) 
    {
       // menampilkan semua $data[] = $row;
       $data[] = $row['nama_promo'];
       
    }
}


echo json_encode($data);
mysqli_close($conn);
?>