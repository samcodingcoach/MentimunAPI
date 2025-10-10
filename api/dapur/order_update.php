<?php
error_reporting(E_ALL & ~E_NOTICE);
header('Content-Type: application/json');
include "../../config/koneksi.php";

$data = json_decode(file_get_contents('php://input'), true);

$id_order = $data['id_order'] ?? null;
$id_pesanan_detail = $data['id_pesanan_detail'] ?? null;
$tgl_update = date('Y-m-d H:i:s');
$ready = $data['ready'] ?? null;

if ($id_order == null || $id_pesanan_detail == null || $ready === null) 
{
    $response = [
        'status' => 'error',
        'message' => 'Inputan Data Tidak Lengkap.'
    ];
} 
else 
{
    $time_column = '';
    switch ($ready) {
        case 1:
            $time_column = 'waktu_ready';
            break;
        case 2:
            $time_column = 'waktu_delivered';
            break;
        case 3:
            $time_column = 'waktu_batal';
            break;
        default:
            $response = [
                'status' => 'error',
                'message' => 'Nilai "ready" tidak valid.'
            ];
            echo json_encode($response);
            mysqli_close($conn);
            exit;
    }

    $sql = "UPDATE dapur_order_detail SET ready = ?, $time_column = ? WHERE id_order = ? AND id_pesanan_detail = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("isss", $ready, $tgl_update, $id_order, $id_pesanan_detail);

    if ($stmt->execute()) {
        if ($stmt->affected_rows > 0) {
            $response = [
                'status' => 'success',
                'message' => 'Order berhasil diupdate.'
            ];
        } else {
            $response = [
                'status' => 'error',
                'message' => 'Tidak ada order yang diupdate. Periksa kembali ID order dan ID pesanan detail.'
            ];
        }
    } else {
        $response = [
            'status' => 'error',
            'message' => 'Gagal mengupdate order: ' . $stmt->error
        ];
    }
    $stmt->close();
}

echo json_encode($response);
mysqli_close($conn);
?>
