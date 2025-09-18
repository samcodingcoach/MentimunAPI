<?php
error_reporting(E_ALL & ~E_NOTICE);
header('Content-Type: application/json');

include "../../config/koneksi.php";

$sql = "SELECT
  meja.id_meja,
  meja.nomor_meja,
  meja.pos_x,
  meja.pos_y,
  meja.in_used
FROM
  meja
  LEFT JOIN ( SELECT id_meja, MAX( id_pesanan ) AS id_pesanan FROM pesanan GROUP BY id_meja ) AS latest_pesanan ON meja.id_meja = latest_pesanan.id_meja
  LEFT JOIN pesanan ON latest_pesanan.id_pesanan = pesanan.id_pesanan
  LEFT JOIN konsumen ON pesanan.id_konsumen = konsumen.id_konsumen
  LEFT JOIN (
  SELECT id_pesanan, MAX( STATUS ) AS STATUS FROM proses_pembayaran GROUP BY id_pesanan ) AS proses_pembayaran ON pesanan.id_pesanan = proses_pembayaran.id_pesanan 
WHERE
  meja.aktif = 1 
GROUP BY
  meja.id_meja";

$result = mysqli_query($conn, $sql);

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