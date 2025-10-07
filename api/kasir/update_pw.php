<?php
error_reporting(E_ALL & ~E_NOTICE);
header('Content-Type: application/json');
include "../../config/koneksi.php";
$data = json_decode(file_get_contents('php://input'), true);

$id_user = $data['id_user'] ?? null;
$password = $data['password'] ?? null;

if (empty($id_user) || empty($password)) {
    $response = [
        'status' => 'error',
        'message' => 'ID user dan password tidak boleh kosong.'
    ];
} else {
    // Store password in plain text as requested by user, but use prepared statement to prevent SQL injection.
    $stmt = mysqli_prepare($conn, "UPDATE pegawai SET password=? WHERE id_user=?");
    mysqli_stmt_bind_param($stmt, "ss", $password, $id_user);

    if (mysqli_stmt_execute($stmt)) {
        $response = [
            'status' => 'success',
            'message' => 'Update Password Berhasil'
        ];
    } else {
        $response = [
            'status' => 'error',
            'message' => 'Gagal menyimpan data: ' . mysqli_stmt_error($stmt)
        ];
    }
    mysqli_stmt_close($stmt);
}



echo json_encode($response);
mysqli_close($conn);
?>