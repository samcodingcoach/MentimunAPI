<?php
error_reporting(E_ALL & ~E_NOTICE);
header('Content-Type: application/json');

include "../../config/koneksi.php";

$sql = "SELECT
	count(case when aktif = 1 then 1 ELSE 0 END) as meja_aktif,
	count(case when in_used = '1' then 0 END) as meja_terpakai,
	count(case when in_used = '0' then 1 END) as meja_kosong
FROM
	meja";

$result = mysqli_query($conn, $sql);


$data = array();


if (mysqli_num_rows($result) > 0) {
    while($row = mysqli_fetch_assoc($result)) 
    {
        $data[] = $row;
    }
}


echo json_encode($data);
mysqli_close($conn);

?>