<?php
session_start();
require_once __DIR__ . '/../../config/koneksi.php';

// Check if user is logged in
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit();
}

// Get parameters from request
$tanggal_awal = isset($_GET['tanggal_awal']) ? $_GET['tanggal_awal'] : '';
$tanggal_akhir = isset($_GET['tanggal_akhir']) ? $_GET['tanggal_akhir'] : '';
$id_user = isset($_GET['id_user']) ? $_GET['id_user'] : '';
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 10; // Records per page
$offset = ($page - 1) * $limit;

if (empty($tanggal_awal) || empty($tanggal_akhir) || empty($id_user)) {
    http_response_code(400);
    echo json_encode(['error' => 'Missing required parameters']);
    exit();
}

// Query for transaction details
$sql = "
    SELECT
        proses_pembayaran.kode_payment,
        DATE_FORMAT(proses_pembayaran.tanggal_payment, '%d-%m-%Y') AS tanggal,
        DATE_FORMAT(proses_pembayaran.tanggal_payment, '%H:%i') AS jam,
        CASE
            WHEN metode_pembayaran.kategori = 'Transfer' THEN
                CASE
                    WHEN proses_edc.transfer_or_edc = 0 THEN 'Transfer via Bank'
                    WHEN proses_edc.transfer_or_edc = 1 THEN 'Transfer via EDC'
                    ELSE 'Transfer'
                END
            ELSE metode_pembayaran.kategori
        END AS kategori,
        CASE
            WHEN proses_pembayaran.model_diskon = 'PERSENTASE' THEN CONCAT('-', proses_pembayaran.nilai_persen, '%')
            WHEN proses_pembayaran.model_diskon = 'NOMINAL' THEN CONCAT('-Rp ', FORMAT(proses_pembayaran.nilai_nominal, 0))
            ELSE ''
        END as diskon,
        COALESCE(total_diskon, 0) as total_diskon,
        jumlah_uang AS total_bersih,
        jumlah_uang + COALESCE(proses_pembayaran.total_diskon, 0) as total_kotor
    FROM
        proses_pembayaran
    INNER JOIN
        metode_pembayaran ON proses_pembayaran.id_bayar = metode_pembayaran.id_bayar
    LEFT JOIN
        proses_edc ON proses_pembayaran.kode_payment = proses_edc.kode_payment
    WHERE 
        proses_pembayaran.`status` = 1 
        AND DATE(tanggal_payment) BETWEEN ? AND ? 
        AND proses_pembayaran.id_user = ?
    ORDER BY
        proses_pembayaran.id_checkout ASC
    LIMIT ? OFFSET ?
";

$stmt = $conn->prepare($sql);
$stmt->bind_param("sssii", $tanggal_awal, $tanggal_akhir, $id_user, $limit, $offset);
$stmt->execute();
$result = $stmt->get_result();
$data = $result->fetch_all(MYSQLI_ASSOC);

// Get total count for pagination
$count_sql = "
    SELECT COUNT(*) as total
    FROM proses_pembayaran
    INNER JOIN metode_pembayaran ON proses_pembayaran.id_bayar = metode_pembayaran.id_bayar
    WHERE 
        proses_pembayaran.`status` = 1 
        AND DATE(tanggal_payment) BETWEEN ? AND ? 
        AND proses_pembayaran.id_user = ?
";

$count_stmt = $conn->prepare($count_sql);
$count_stmt->bind_param("sss", $tanggal_awal, $tanggal_akhir, $id_user);
$count_stmt->execute();
$count_result = $count_stmt->get_result();
$total = $count_result->fetch_assoc()['total'];

// Calculate total pages
$total_pages = ceil($total / $limit);

// Format numbers properly for display
foreach ($data as &$row) {
    $row['total_diskon_formatted'] = number_format($row['total_diskon'], 0, ',', '.');
    $row['total_bersih_formatted'] = number_format($row['total_bersih'], 0, ',', '.');
    $row['total_kotor_formatted'] = number_format($row['total_kotor'], 0, ',', '.');
}

// Return JSON response
header('Content-Type: application/json');
echo json_encode([
    'data' => $data,
    'pagination' => [
        'current_page' => $page,
        'total_pages' => $total_pages,
        'total_records' => $total,
        'per_page' => $limit
    ]
]);
?>