<?php
header('Content-Type: application/json');
error_reporting(E_ALL & ~E_NOTICE);

include "../../config/koneksi.php";

$data = json_decode(file_get_contents('php://input'), true);

$nama = isset($data['nama_konsumen']) ? $data['nama_konsumen'] : null;
$no_hp = isset($data['no_hp']) ? $data['no_hp'] : null;
$email = isset($data['email']) ? $data['email'] : null;
$alamat = isset($data['alamat']) ? $data['alamat'] : null;

if (empty($nama) || empty($no_hp)) {
    echo json_encode(['status' => 'error', 'message' => 'Nama dan Nomor HP tidak boleh kosong.']);
    exit;
}

// Cek apakah no_hp atau email sudah ada menggunakan prepared statement
$checkQuery = "SELECT id_konsumen FROM konsumen WHERE no_hp = ? OR (email = ? AND email IS NOT NULL AND email != '')";
$stmt = mysqli_prepare($conn, $checkQuery);
mysqli_stmt_bind_param($stmt, "ss", $no_hp, $email);
mysqli_stmt_execute($stmt);
$resultCheck = mysqli_stmt_get_result($stmt);

if (mysqli_num_rows($resultCheck) > 0) {
    $response = [
        'status' => 'duplikat',
        'message' => 'Nomor HP atau Email sudah terdaftar.'
    ];
} else {
    // Insert data jika tidak ada duplikasi menggunakan prepared statement
    $sql2 = "INSERT INTO konsumen (nama_konsumen, no_hp, email, alamat) VALUES (?, ?, ?, ?)";
    $stmt2 = mysqli_prepare($conn, $sql2);
    mysqli_stmt_bind_param($stmt2, "ssss", $nama, $no_hp, $email, $alamat);

    if (mysqli_stmt_execute($stmt2)) {
        $id_konsumen = mysqli_insert_id($conn);
        $response = [
            'status' => 'success',
            'message' => 'Insert Success',
            'id_konsumen' => $id_konsumen
        ];
    } else {
        $response = [
            'status' => 'error',
            'message' => 'Gagal menyimpan data: ' . mysqli_stmt_error($stmt2)
        ];
    }
    mysqli_stmt_close($stmt2);
}

mysqli_stmt_close($stmt);
echo json_encode($response);
mysqli_close($conn);
?>