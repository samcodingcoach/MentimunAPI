<?php
error_reporting(E_ALL & ~E_NOTICE);
header('Content-Type: application/json');
include "../../config/koneksi.php";

$data = json_decode(file_get_contents('php://input'), true);

$id_pesanan = $data['id_pesanan'] ?? null;
$id_importer = $data['id_importer'] ?? null;

if (empty($id_pesanan) || empty($id_importer)) {
    $response = [
        'status' => 'error',
        'message' => 'ID pesanan dan ID importer tidak boleh kosong.'
    ];
} else {
    mysqli_begin_transaction($conn);

    try {
        // Pindahkan detail pesanan dari pesanan lama ke pesanan baru (importer)
        $stmt1 = mysqli_prepare($conn, "UPDATE pesanan_detail SET id_pesanan = ? WHERE id_pesanan = ?");
        mysqli_stmt_bind_param($stmt1, "ii", $id_pesanan,$id_importer);
        mysqli_stmt_execute($stmt1);
        mysqli_stmt_close($stmt1);

        // Sesuai instruksi: update status pembayaran untuk pesanan tujuan (importer)
        $stmt2 = mysqli_prepare($conn, "UPDATE proses_pembayaran SET status = 1 WHERE id_pesanan = ?");
        mysqli_stmt_bind_param($stmt2, "i", $id_importer);
        mysqli_stmt_execute($stmt2);
        mysqli_stmt_close($stmt2);



        mysqli_commit($conn);

        $response = [
            'status' => 'success',
            'message' => 'Import Pesanan Berhasil.'
        ];
    } catch (Exception $e) {
        mysqli_rollback($conn);
        $response = [
            'status' => 'error',
            'message' => 'Operasi gagal: ' . $e->getMessage()
        ];
    }
}

echo json_encode($response);
mysqli_close($conn);
?>
