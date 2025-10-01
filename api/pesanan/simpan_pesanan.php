<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

header("Content-Type: application/json; charset=UTF-8");

// Include both database and Midtrans configurations
include "../../config/koneksi.php";
require_once __DIR__ . '/../midtrans/config.php';

$json_data = file_get_contents("php://input");
$data = json_decode($json_data, true);

if ($data === null) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Data JSON tidak valid.']);
    exit();
}

// Initialize QRIS URL variable
$qrisUrl = null;

// Start database transaction
$conn->begin_transaction();

try {
    // This line will force mysqli to throw exceptions on error
    mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

    // ---- PARTS 1 & 2: ORDER & ORDER DETAILS ----
    $query_antri = "SELECT COUNT(id_pesanan) as antrian_hari_ini FROM pesanan WHERE DATE(tgl_cart) = CURDATE()";
    $result_antri = $conn->query($query_antri);
    $row_antri = $result_antri->fetch_assoc();
    $nomor_antri_baru = $row_antri['antrian_hari_ini'] + 1;

    $stmt_pesanan = $conn->prepare("INSERT INTO pesanan (id_user, id_konsumen, total_cart, status_checkout, id_meja, deviceid, nomor_antri) VALUES (?, ?, ?, ?, ?, ?, ?)");
    $stmt_pesanan->bind_param("iidsisi", $data['id_user'], $data['id_konsumen'], $data['total_cart'], $data['status_checkout'], $data['id_meja'], $data['deviceid'], $nomor_antri_baru);
    $stmt_pesanan->execute();
    $id_pesanan_baru = $conn->insert_id;

    $stmt_detail = $conn->prepare("INSERT INTO pesanan_detail (id_pesanan, id_produk_sell, qty, ta_dinein) VALUES (?, ?, ?, ?)");
    $id_produk_sell_loop = 0; $qty_loop = 0; $ta_dinein_loop = '';
    $stmt_detail->bind_param("iiis", $id_pesanan_baru, $id_produk_sell_loop, $qty_loop, $ta_dinein_loop);
    foreach ($data['pesanan_detail'] as $detail) {
        $id_produk_sell_loop = (int)$detail['id_produk_sell'];
        $qty_loop = (int)$detail['qty'];
        $ta_dinein_loop = $detail['ta_dinein'];
        $stmt_detail->execute();
    }
    
    // ---- PARTS 3 & 4: PAYMENT & QRIS INTEGRATION ----
    if (isset($data['proses_pembayaran'])) {
        $pembayaran = $data['proses_pembayaran'];
        $detail_pembayaran = $pembayaran['pembayaran_detail'];

        $query_inc = "SELECT COUNT(id_checkout) as inc_hari_ini FROM proses_pembayaran WHERE DATE(tanggal_payment) = CURDATE()";
        $result_inc = $conn->query($query_inc);
        $row_inc = $result_inc->fetch_assoc();
        $increment = str_pad($row_inc['inc_hari_ini'] + 1, 4, '0', STR_PAD_LEFT);
        $kode_payment = "POS-" . $data['id_meja'] . "-" . date("ymd") . $increment;
        
        // If payment method is QRIS (id_bayar = 3), force status to 0 (pending)
        $payment_status = $pembayaran['status'];
        if ((int)$pembayaran['id_bayar'] === 3) {
            $payment_status = 0;
        }
        
        $stmt_pembayaran = $conn->prepare("INSERT INTO proses_pembayaran (kode_payment, id_pesanan, id_bayar, id_user, status, jumlah_uang, jumlah_dibayarkan, kembalian, model_diskon, nilai_nominal, total_diskon) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt_pembayaran->bind_param("siiisdddsdd", $kode_payment, $id_pesanan_baru, $pembayaran['id_bayar'], $pembayaran['id_user'], $payment_status, $pembayaran['jumlah_uang'], $pembayaran['jumlah_dibayarkan'], $pembayaran['kembalian'], $pembayaran['model_diskon'], $pembayaran['nilai_nominal'], $pembayaran['total_diskon']);
        $stmt_pembayaran->execute();

        $stmt_pembayaran_detail = $conn->prepare("INSERT INTO proses_pembayaran_detail (kode_payment, subtotal, biaya_pengemasan, service_charge, promo_diskon, ppn_resto) 
        VALUES (?, ?, ?, ?, ?, ?)");
        $stmt_pembayaran_detail->bind_param("sddddd", $kode_payment, $detail_pembayaran['subtotal'], $detail_pembayaran['biaya_pengemasan'], $detail_pembayaran['service_charge'], $detail_pembayaran['promo_diskon'], $detail_pembayaran['ppn_resto']);
        $stmt_pembayaran_detail->execute();

        // *** NEW: QRIS Generation Logic ***
        // Check if the payment method is QRIS (id_bayar = 3)
        if ((int)$pembayaran['id_bayar'] === 3) {
            // Prepare parameters for Midtrans API call
            $params = [
                'payment_type' => 'qris',
                'transaction_details' => [
                    'order_id' => $kode_payment, // Use the generated kode_payment
                    'gross_amount' => $pembayaran['jumlah_uang'], // Use the total amount
                ],
            ];

            // The Midtrans call is inside the main try block.
            // If it fails, it will throw an Exception, which will be caught
            // by the outer catch block, triggering a full database rollback.
            $qrisTransaction = \Midtrans\CoreApi::charge($params);
            $qrisUrl = $qrisTransaction->actions[0]->url;
        }
    }

    // If all operations were successful, commit the transaction
    $conn->commit();

    // Prepare the final JSON response
    $response = [
        'status' => 'success',
        'message' => 'Transaksi Qris Berhasil.',
        'kode_payment' => $kode_payment
    ];

    // Add the qris_url to the response if it was generated
    if ($qrisUrl !== null) {
        $response['qris_url'] = $qrisUrl;
    }

    echo json_encode($response);

} catch (Exception $e) {
    // If any error occurred, rollback the entire transaction
    $conn->rollback();
    http_response_code(500);
    // Provide a detailed error message for debugging
    echo json_encode(['status' => 'error', 'message' => 'Transaksi Gagal: ' . $e->getMessage()]);
}

$conn->close();
?>
