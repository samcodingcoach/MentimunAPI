<?php
session_start();
require_once __DIR__ . '/../../config/koneksi.php';

if (!function_exists('bind_params_dynamic')) {
    function bind_params_dynamic(mysqli_stmt $stmt, string $types, array $params): void
    {
        $bind = [$types];
        foreach ($params as $key => $value) {
            $bind[] = &$params[$key];
        }
        call_user_func_array([$stmt, 'bind_param'], $bind);
    }
}

if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit();
}

$tanggal = isset($_GET['tanggal']) ? trim($_GET['tanggal']) : '';
$id_user = isset($_GET['id_user']) ? (int)$_GET['id_user'] : 0;
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$kategori = isset($_GET['kategori']) ? trim($_GET['kategori']) : 'Semua';
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 10;
$offset = ($page - 1) * $limit;

if (empty($tanggal) || $id_user <= 0) {
    http_response_code(400);
    echo json_encode(['error' => 'Missing required parameters']);
    exit();
}

$kategoriExpression = "
    CASE
        WHEN metode_pembayaran.kategori = 'Transfer' THEN
            CASE
                WHEN proses_edc.transfer_or_edc = 0 THEN 'Transfer via Bank'
                WHEN proses_edc.transfer_or_edc = 1 THEN 'Transfer via EDC'
                ELSE 'Transfer'
            END
        ELSE metode_pembayaran.kategori
    END
";

$whereClauses = [
    "proses_pembayaran.`status` = 1",
    "DATE(proses_pembayaran.tanggal_payment) = ?",
    "proses_pembayaran.id_user = ?"
];

$params = [$tanggal, $id_user];
$types = 'si';

if ($search !== '') {
    $whereClauses[] = "proses_pembayaran.kode_payment LIKE ?";
    $params[] = "%{$search}%";
    $types .= 's';
}

if ($kategori !== '' && $kategori !== 'Semua') {
    $whereClauses[] = "{$kategoriExpression} = ?";
    $params[] = $kategori;
    $types .= 's';
}

$whereSql = implode(' AND ', $whereClauses);

$baseFrom = "
    FROM
        proses_pembayaran
    INNER JOIN
        metode_pembayaran ON proses_pembayaran.id_bayar = metode_pembayaran.id_bayar
    LEFT JOIN
        proses_edc ON proses_pembayaran.kode_payment = proses_edc.kode_payment
";

$selectSql = "
    SELECT
        proses_pembayaran.kode_payment,
        DATE_FORMAT(proses_pembayaran.tanggal_payment, '%d-%m-%Y') AS tanggal,
        DATE_FORMAT(proses_pembayaran.tanggal_payment, '%H:%i') AS jam,
        {$kategoriExpression} AS kategori,
        CASE
            WHEN proses_pembayaran.model_diskon = 'PERSENTASE' THEN CONCAT('-', proses_pembayaran.nilai_persen, '%')
            WHEN proses_pembayaran.model_diskon = 'NOMINAL' THEN CONCAT('-Rp ', FORMAT(proses_pembayaran.nilai_nominal, 0))
            ELSE ''
        END AS diskon,
        COALESCE(proses_pembayaran.total_diskon, 0) AS total_diskon,
        proses_pembayaran.jumlah_uang AS total_bersih,
        proses_pembayaran.jumlah_uang + COALESCE(proses_pembayaran.total_diskon, 0) AS total_kotor
$baseFrom
    WHERE {$whereSql}
    ORDER BY proses_pembayaran.id_checkout ASC
    LIMIT ? OFFSET ?
";

$stmt = $conn->prepare($selectSql);
$selectParams = $params;
$selectParams[] = $limit;
$selectParams[] = $offset;
bind_params_dynamic($stmt, $types . 'ii', $selectParams);
$stmt->execute();
$result = $stmt->get_result();
$data = $result->fetch_all(MYSQLI_ASSOC);

$countSql = "
    SELECT COUNT(*) AS total
$baseFrom
    WHERE {$whereSql}
";

$countStmt = $conn->prepare($countSql);
bind_params_dynamic($countStmt, $types, $params);
$countStmt->execute();
$countResult = $countStmt->get_result();
$totalRecords = (int)($countResult->fetch_assoc()['total'] ?? 0);

$summarySql = "
    SELECT 
        COUNT(*) AS total_rows,
        COALESCE(SUM(proses_pembayaran.jumlah_uang), 0) AS total_bersih
$baseFrom
    WHERE {$whereSql}
";

$summaryStmt = $conn->prepare($summarySql);
bind_params_dynamic($summaryStmt, $types, $params);
$summaryStmt->execute();
$summaryResult = $summaryStmt->get_result();
$summaryData = $summaryResult->fetch_assoc();

$kategoriSql = "
    SELECT DISTINCT {$kategoriExpression} AS kategori
$baseFrom
    WHERE {$whereSql}
    ORDER BY kategori ASC
";

$kategoriStmt = $conn->prepare($kategoriSql);
bind_params_dynamic($kategoriStmt, $types, $params);
$kategoriStmt->execute();
$kategoriResult = $kategoriStmt->get_result();
$kategoriList = [];
while ($row = $kategoriResult->fetch_assoc()) {
    if (!empty($row['kategori'])) {
        $kategoriList[] = $row['kategori'];
    }
}

$totalPages = max(1, (int)ceil($totalRecords / $limit));

foreach ($data as &$row) {
    $row['total_diskon_formatted'] = number_format((float)$row['total_diskon'], 0, ',', '.');
    $row['total_bersih_formatted'] = number_format((float)$row['total_bersih'], 0, ',', '.');
    $row['total_kotor_formatted'] = number_format((float)$row['total_kotor'], 0, ',', '.');
}
unset($row);

$totalBersih = (float)($summaryData['total_bersih'] ?? 0);

header('Content-Type: application/json');
echo json_encode([
    'data' => $data,
    'pagination' => [
        'current_page' => $page,
        'total_pages' => $totalPages,
        'total_records' => $totalRecords,
        'per_page' => $limit
    ],
    'summary' => [
        'total_rows' => (int)($summaryData['total_rows'] ?? 0),
        'total_bersih' => $totalBersih,
        'total_bersih_formatted' => number_format($totalBersih, 0, ',', '.')
    ],
    'filters' => [
        'selected_kategori' => $kategori === '' ? 'Semua' : $kategori,
        'categories' => $kategoriList
    ]
]);
?>