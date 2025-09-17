<?php
header('Content-Type: application/json');
error_reporting(E_ALL & ~E_NOTICE);

include "../../config/koneksi.php";
$sql = "SELECT
	nama_kategori, 
	id_kategori
FROM
	kategori_menu
WHERE
	aktif = 1";

$result = mysqli_query($conn, $sql);

// Membuat array untuk menyimpan hasil query
$data = array();

// Mengambil hasil query dan memasukkannya ke dalam array
if (mysqli_num_rows($result) > 0) {
    while($row = mysqli_fetch_assoc($result)) 
    {
       // menampilkan semua $data[] = $row;
       $data[] = $row['nama_kategori'];
       
    }
}

// Mengembalikan data dalam format JSON
echo json_encode($data);
mysqli_close($conn);

?>