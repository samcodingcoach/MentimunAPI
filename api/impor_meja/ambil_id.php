<?php
error_reporting(E_ALL & ~E_NOTICE);
header('Content-Type: application/json');
include "../../config/koneksi.php";
$data = json_decode(file_get_contents('php://input'), true);

$id_meja = $data['id_meja'] ?? null;

if (empty($id_meja)) {
    $response = [
        'status' => 'error',
        'message' => 'ID tidak boleh kosong.'
    ];
} else {
    // Using prepared statement to prevent SQL injection
    $stmt = mysqli_prepare($conn, "SELECT pesanan.id_pesanan FROM pesanan INNER JOIN proses_pembayaran ON pesanan.id_pesanan = proses_pembayaran.id_pesanan WHERE pesanan.id_meja = ? AND proses_pembayaran.status = ?");
    
    $status = 0; // status value to check
    mysqli_stmt_bind_param($stmt, "ii", $id_meja, $status);
    mysqli_stmt_execute($stmt);
    
    $result = mysqli_stmt_get_result($stmt);
    
    if ($result) {
        if ($row = mysqli_fetch_assoc($result)) {
            // Return only the first id_pesanan found
            $response = [
                'status' => 'success',
                'id_pesanan' => (int)$row['id_pesanan']
            ];
        } else {
            $response = [
                'status' => 'error',
                'message' => 'Tidak ada data ditemukan.'
            ];
        }
    } else {
        $response = [
            'status' => 'error',
            'message' => 'Query gagal: ' . mysqli_error($conn)
        ];
    }
    
    mysqli_stmt_close($stmt);
}

echo json_encode($response);
mysqli_close($conn);
?>