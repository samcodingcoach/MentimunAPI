<?php
error_reporting(E_ALL & ~E_NOTICE);
header('Content-Type: application/json');
include "../../config/koneksi.php";

$data = json_decode(file_get_contents('php://input'), true);

$id_order_detail = $data['id_order_detail'] ?? null;
$id_pesanan = $data['id_pesanan'] ?? null;
$alasan = $data['alasan'] ?? 'Tidak ada alasan yang diberikan.';
$qty = $data['qty'] ?? null;
$id_user = $data['id_user'] ?? null;
$status_dapur = $data['status_dapur'] ?? null;

if ($id_order_detail == null || $id_pesanan == null || $qty == null || $id_user == null || $status_dapur == null) 
{
    $response = [
        'status' => 'error',
        'message' => 'Inputan Data Tidak Lengkap.'
    ];
} 
else 
{
    $stmt = $conn->prepare("INSERT INTO dapur_batal (id_order_detail, id_pesanan, alasan, qty, id_user, status_dapur) VALUES (?, ?, ?, ?, ?, ?)");
    // Assuming the data types are: string, string, string, integer, integer, string
    $stmt->bind_param("iisiis", $id_order_detail, $id_pesanan, $alasan, $qty, $id_user, $status_dapur);

    if ($stmt->execute()) {
        $response = [
            'status' => 'success',
            'message' => 'Data pembatalan berhasil disimpan.'
        ];
    } else {
        $response = [
            'status' => 'error',
            'message' => 'Gagal menyimpan data pembatalan: ' . $stmt->error
        ];
    }
    $stmt->close();
}

echo json_encode($response);
mysqli_close($conn);
?>