<?php
header('Content-Type: application/json');
error_reporting(E_ALL & ~E_NOTICE);

include "../../config/koneksi.php";
$hp = $_GET['nomor'];
// Menggunakan prepared statement untuk mencegah SQL injection
$sql = "SELECT id_konsumen, nama_konsumen FROM konsumen WHERE no_hp = ? LIMIT 1";
$stmt = mysqli_prepare($conn, $sql);
mysqli_stmt_bind_param($stmt, "s", $hp);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);


// Membuat array untuk menyimpan hasil query
$data = array();

// Mengambil hasil query dan memasukkannya ke dalam array
if (mysqli_num_rows($result) > 0) {
    while($row = mysqli_fetch_assoc($result)) 
    {
     
        $data[] = $row;
    }
}

// Mengembalikan data dalam format JSON
echo json_encode($data);

mysqli_close($conn);

?>