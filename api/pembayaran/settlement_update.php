<?php
error_reporting(E_ALL & ~E_NOTICE);

include "../../config/koneksi.php";
$data = json_decode(file_get_contents('php://input'), true);
$kode = $data['kode_payment'];

// Insert into proses_pembayaran table
 $sql2 = "UPDATE proses_pembayaran  SET status = 1 WHERE kode_payment = '$kode'";


if (mysqli_query($conn, $sql2)) {
    $response = ['status' => 'success', 'message' => ' '];
} else {
    $response = ['status' => 'error', 'message' => 'Gagal menyimpan data: ' . mysqli_error($conn)];
}

header('Content-Type: application/json');
echo json_encode($response);
mysqli_close($conn);
?>
