<?php
header('Content-Type: application/json');
// error_reporting(E_ALL & ~E_NOTICE);

include "../../config/koneksi.php";

$data = json_decode(file_get_contents('php://input'), true);
$id_meja = isset($data['id_meja']) ? $data['id_meja'] : null;

if (empty($id_meja)) {
    echo json_encode(['status' => 'error', 'message' => 'Harap pilih meja terlebih dahulu']);
    exit;
}

$sql = "UPDATE meja SET in_used = 0 WHERE id_meja = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("s", $id_meja);

if ($stmt->execute()) {
    $response = ['status' => 'success', 'message' => 'Meja berhasil diaktifkan'];
} else {
    $response = ['status' => 'error', 'message' => 'Gagal mengaktifkan meja'];
}

$stmt->close();
echo json_encode($response);
$conn->close();
?>