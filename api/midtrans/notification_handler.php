<?php
require_once 'config.php';

$json_result = file_get_contents('php://input');
$notification = json_decode($json_result);

if ($notification) {
    $transaction_status = $notification->transaction_status;
    $order_id = $notification->order_id;

    if ($transaction_status == 'settlement') {
        // Transaksi berhasil (QRIS sudah dibayar)
        echo "Transaction success: " . $order_id;
    } elseif ($transaction_status == 'pending') {
        // Menunggu pembayaran
        echo "Transaction pending: " . $order_id;
    } elseif ($transaction_status == 'expire') {
        // Transaksi kadaluwarsa
        echo "Transaction expired: " . $order_id;
    }
}
?>
