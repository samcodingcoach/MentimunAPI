<?php
header('Content-Type: application/json');
error_reporting(E_ALL & ~E_NOTICE);

include "../../config/koneksi.php";

$sql = "SELECT
	informasi.id_info, 
	informasi.judul, 
	informasi.isi, 
	informasi.divisi, 
	informasi.gambar, 
	informasi.link, 
	pegawai.nama_lengkap, 
	informasi.created_time,
  CASE 
        WHEN TIMESTAMPDIFF(HOUR, informasi.created_time, NOW()) < 8 THEN
            CASE 
                WHEN TIMESTAMPDIFF(HOUR, informasi.created_time, NOW()) < 1 THEN CONCAT(TIMESTAMPDIFF(MINUTE, informasi.created_time, NOW()), ' menit yang lalu')
                WHEN TIMESTAMPDIFF(HOUR, informasi.created_time, NOW()) = 1 THEN '1 jam yang lalu'
                ELSE CONCAT(TIMESTAMPDIFF(HOUR, informasi.created_time, NOW()), ' jam yang lalu')
            END
        ELSE 
            DATE_FORMAT(informasi.created_time,'%d %M %Y %H:%i')
    END AS waktu_tampil
  
FROM
	informasi
	INNER JOIN
	pegawai
	ON 
		informasi.id_users = pegawai.id_user ORDER BY created_time desc";



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