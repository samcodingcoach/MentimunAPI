<?php
header('Content-Type: application/json');
error_reporting(E_ALL & ~E_NOTICE);

include "../../config/koneksi.php";

$id = $_GET['id_user'] ?? null;
$tgl = $_GET['tgl'] ?? null;

if (!$id || !is_numeric($id)) {
    echo json_encode([]);
    exit;
}

if ($tgl === "0" || empty($tgl)) {
    $ndate = date('Y-m-d');
} else {
    $ndate = $tgl;
}

// Query tanpa prepared statement (untuk testing)
$sql = "
    SELECT
        state_open_closing.tanggal_open,
        (state_open_closing.first_cash_drawer - state_open_closing.manual_total_cash) AS kas_awal,
        state_open_closing.total_qris AS qris, 
        state_open_closing.manual_total_bank AS transfer, 
        state_open_closing.manual_total_cash AS tunai, 
        COUNT(proses_pembayaran.kode_payment) AS jumlah_transaksi,
        COALESCE(SUM(proses_pembayaran.total_diskon), 0) AS total_diskon
    FROM
        state_open_closing
    LEFT JOIN proses_pembayaran ON 
        state_open_closing.id_user = proses_pembayaran.id_user
        AND DATE(proses_pembayaran.tanggal_payment) = '$ndate'
    WHERE
        state_open_closing.id_user = '$id'
        AND DATE(state_open_closing.tanggal_open) = '$ndate'
    GROUP BY state_open_closing.id_user
";

$result = mysqli_query($conn, $sql);

if (!$result) {
    echo json_encode(['error' => 'Query gagal: ' . mysqli_error($conn)]);
    exit;
}

$data = array();

if (mysqli_num_rows($result) > 0) {
    while ($row = mysqli_fetch_assoc($result)) {
        $kas_awal = (float)($row['kas_awal'] ?? 0);
        $qris      = (float)($row['qris'] ?? 0);
        $transfer  = (float)($row['transfer'] ?? 0);
        $tunai     = (float)($row['tunai'] ?? 0);

        $total_transaksi = $kas_awal + $qris + $transfer + $tunai;

        $row['total_transaksi'] = $total_transaksi;
        $row['total_diskon']    = (float)($row['total_diskon'] ?? 0);

        $data[] = $row;
    }
}

echo json_encode($data);

mysqli_close($conn);
?>