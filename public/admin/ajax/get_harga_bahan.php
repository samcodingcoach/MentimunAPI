<?php
session_start();
require_once __DIR__ . '/../../../config/koneksi.php';

header('Content-Type: application/json');

// Check if user is logged in
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    echo json_encode(['success' => false, 'message' => 'Not authorized']);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['id_bahan']) && isset($_POST['satuan'])) {
    $id_bahan = intval($_POST['id_bahan']);
    $satuan = $_POST['satuan'];
    
    // Get the latest harga_satuan dan id_bahan_biaya for the selected bahan and satuan
    $sql = "SELECT id_bahan_biaya, harga_satuan 
            FROM bahan_biaya 
            WHERE id_bahan = ? AND satuan = ?
            ORDER BY tanggal DESC, id_bahan_biaya DESC
            LIMIT 1";
    
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, "is", $id_bahan, $satuan);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    
    if ($row = mysqli_fetch_assoc($result)) {
        echo json_encode([
            'success' => true, 
            'harga_satuan' => floatval($row['harga_satuan']),
            'id_bahan_biaya' => intval($row['id_bahan_biaya'])
        ]);
    } else {
        echo json_encode([
            'success' => false, 
            'message' => 'Harga tidak ditemukan',
            'harga_satuan' => 0,
            'id_bahan_biaya' => null
        ]);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid request']);
}
?>