<?php
session_start();
require_once __DIR__ . '/../../../config/koneksi.php';

header('Content-Type: application/json');

// Check if user is logged in
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    echo json_encode(['success' => false, 'message' => 'Not authorized']);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['id_bahan'])) {
    $id_bahan = intval($_POST['id_bahan']);
    
    // Get distinct satuan for the selected bahan from bahan_biaya
    $sql = "SELECT DISTINCT satuan 
            FROM bahan_biaya 
            WHERE id_bahan = ? AND satuan IS NOT NULL AND satuan != ''
            ORDER BY satuan";
    
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, "i", $id_bahan);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    
    $data = [];
    while ($row = mysqli_fetch_assoc($result)) {
        $data[] = ['satuan' => $row['satuan']];
    }
    
    echo json_encode(['success' => true, 'data' => $data]);
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid request']);
}
?>