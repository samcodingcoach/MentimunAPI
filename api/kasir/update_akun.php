<?php
error_reporting(E_ALL & ~E_NOTICE);
header('Content-Type: application/json');
include "../../config/koneksi.php";

$data = json_decode(file_get_contents('php://input'), true);

$id_user = $data['id_user'] ?? null;
$nama_lengkap = $data['nama_lengkap'] ?? null;
$nomor_hp = $data['nomor_hp'] ?? null;
$email = $data['email'] ?? null;
$password = $data['password'] ?? null;

if (empty($id_user) || empty($nama_lengkap) || empty($nomor_hp) || empty($email) || !isset($password)) {
    $response = [
        'status' => 'error',
        'message' => 'Semua field harus diisi.'
    ];
} else {
    $sql = "UPDATE pegawai SET nama_lengkap=?, nomor_hp=?, email=?, password=? WHERE id_user=?";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, "sssss", $nama_lengkap, $nomor_hp, $email, $password, $id_user);

    if (mysqli_stmt_execute($stmt)) {
        if (mysqli_stmt_affected_rows($stmt) > 0) {
            $response = [
                'status' => 'success',
                'message' => 'Update data pegawai berhasil'
            ];
        } else {
            $response = [
                'status' => 'no_change',
                'message' => 'Tidak ada perubahan data atau user tidak ditemukan.'
            ];
        }
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
