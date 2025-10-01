<?php
error_reporting(E_ALL & ~E_NOTICE);
include "config.php";
$order_id = $_GET['kode_payment'];  // Ambil Order ID dari parameter URL
$url = "https://api.sandbox.midtrans.com/v2/$order_id/status";  // Endpoint status transaksi

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Accept: application/json',
    'Content-Type: application/json',
    'Authorization: Basic ' . base64_encode($serverKey . ':')  // Ganti dengan server key Anda
]);

$response = curl_exec($ch);

// Cek jika ada error pada cURL request
if (curl_errno($ch)) {
    echo json_encode(['error' => 'CURL Error: ' . curl_error($ch)]);
    exit;
}

curl_close($ch);

// Decode JSON response
$responseData = json_decode($response, true);

// Periksa apakah respons valid
if (isset($responseData['transaction_status'])) {
    $filteredData = [
        'order_id' => $responseData['order_id'] ?? null,
        'gross_amount' => $responseData['gross_amount'] ?? null,
        'transaction_status' => $responseData['transaction_status'] ?? null,
        'settlement_time' => $responseData['settlement_time'] ?? null,
    ];
    
    // Bungkus menjadi array untuk format yang diminta
    $responseArray = [$filteredData];
    
    header('Content-Type: application/json');
    echo json_encode($responseArray);
} else {
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Unknown status or invalid response']);
}
?>
