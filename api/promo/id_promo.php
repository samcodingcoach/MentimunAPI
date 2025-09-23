<?php
header('Content-Type: application/json');
error_reporting(E_ALL & ~E_NOTICE);

$nama_promo = $_GET['nama_promo'];


include "../../config/koneksi.php";
$sql = "SELECT
	id_promo,pilihan_promo,persen,nominal,min_pembelian,deskripsi
FROM
	promo
WHERE
	nama_promo = '$nama_promo'";

$result = mysqli_query($conn, $sql);

// Membuat array untuk menyimpan hasil query
$data = array();

// Mengambil hasil query dan memasukkannya ke dalam array
if (mysqli_num_rows($result) > 0) {
    while($row = mysqli_fetch_assoc($result)) 
    {
       // menampilkan semua $data[] = $row;
       $data[] = $row;
       
    }
}

// Mengembalikan data dalam format JSON
echo json_encode($data);
mysqli_close($conn);

?>