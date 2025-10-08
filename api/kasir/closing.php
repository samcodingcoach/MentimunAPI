<?php
error_reporting(E_ALL & ~E_NOTICE);
header('Content-Type: application/json');

include "../../config/koneksi.php";
$data = json_decode(file_get_contents('php://input'), true);

$id = $data['id_user'];

// Check if there is an open session for the user today
$check_open_sql = "SELECT * FROM state_open_closing WHERE id_user='$id' AND DATE(tanggal_open) = CURDATE() AND status = 1";
$check_open_result = mysqli_query($conn, $check_open_sql);

if (mysqli_num_rows($check_open_result) == 1) {
    // There is one row to update, so proceed.

    // First, get the payment summary as requested.
    $summary_sql = "SELECT sum(jumlah_dibayarkan) as 'TotalPembayaran',
                    sum(case when id_bayar = 1 THEN jumlah_dibayarkan else 0 END) as TotalTunai
                    FROM proses_pembayaran
                    WHERE `status` = 1 AND id_user = '$id' AND DATE(tanggal_payment) = CURDATE()";
    
    $summary_result = mysqli_query($conn, $summary_sql);
    $summary_row = mysqli_fetch_assoc($summary_result);
    
    $total_pembayaran = $summary_row['TotalPembayaran'] ?: 0;
    $total_tunai = $summary_row['TotalTunai'] ?: 0;

    // Now, perform the update (closing).
    $update_sql = "UPDATE state_open_closing SET status = 0 WHERE 
                   id_user='$id' AND DATE(tanggal_open) = CURDATE() AND status = 1";

    if (mysqli_query($conn, $update_sql)) {
        $response = [
            'status' => 'success',
            'message' => 'Closing Transaksi Berhasil',
            'total_pembayaran' => $total_pembayaran,
            'total_tunai' => $total_tunai
        ];
    } else {
        $response = [
            'status' => 'error',
            'message' => 'Gagal closing: ' . mysqli_error($conn)
        ];
    }
} else if (mysqli_num_rows($check_open_result) > 1) {
    $response = [
        'status' => 'error',
        'message' => 'Ditemukan lebih dari satu sesi open untuk hari ini. Silakan hubungi administrator.'
    ];
} else {
    // No open session found for this user today.
    $response = [
        'status' => 'error',
        'message' => 'Tidak ada sesi open yang ditemukan untuk di-closing.'
    ];
}

echo json_encode($response);
mysqli_close($conn);
?>