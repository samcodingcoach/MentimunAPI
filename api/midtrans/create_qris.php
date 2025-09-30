<?php
require_once 'config.php'; // Konfigurasi Midtrans
$data = json_decode(file_get_contents('php://input'), true);



$orderId = $data['order_id'];
$grossAmount = $data['gross_amount'];

// Data transaksi
$params = [
    'payment_type' => 'qris',
    'transaction_details' => [
        'order_id' => $orderId,
        'gross_amount' => $grossAmount,
    ],
];

try {
    // Buat transaksi QRIS dengan Midtrans
    $qrisTransaction = \Midtrans\CoreApi::charge($params);

    // Dapatkan URL QR Code langsung dari Midtrans
    $qrisImageUrl = $qrisTransaction->actions[0]->url;

    // Kirimkan respons JSON dengan status, URL QRIS, dan pesan sukses
    echo json_encode([
        'status' => 'success',
        'qris_url' => $qrisImageUrl,  // URL QR Code yang dihasilkan
        'message' => 'QRIS transaction created successfully.',
    ]);
} catch (Exception $e) {
    // Tangani error dan kirim respons error
    echo json_encode([
        'status' => 'error',
        'message' => $e->getMessage(),
    ]);
}
?>
