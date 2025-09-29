<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
header("Content-Type: application/json; charset=UTF-8");
include "../../config/koneksi.php";
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['status' => 'error', 'message' => 'Metode tidak diizinkan.']);
    exit();
}

$required_fields = ['kode_payment', 'transfer_or_edc', 'nama_bank', 'nama_pengirim', 'no_referensi'];
foreach ($required_fields as $field) {
    if (!isset($_POST[$field]) || $_POST[$field] === '') {
        http_response_code(400);
        echo json_encode(['status' => 'error', 'message' => "Field '$field' wajib diisi."]);
        exit();
    }
}

$kode_payment = $_POST['kode_payment'];
$transfer_or_edc = $_POST['transfer_or_edc'];
$nama_bank = $_POST['nama_bank'];
$nama_pengirim = $_POST['nama_pengirim'];
$no_referensi = $_POST['no_referensi'];

$img_ss = ''; // Hanya simpan nama file

if (isset($_FILES['img_ss']) && $_FILES['img_ss']['error'] === UPLOAD_ERR_OK) {
    $upload_dir = __DIR__ . '/../../public/images/bukti_tf/';
    
    if (!is_dir($upload_dir)) {
        if (!mkdir($upload_dir, 0755, true)) {
            http_response_code(500);
            echo json_encode(['status' => 'error', 'message' => 'Gagal membuat folder upload.']);
            exit();
        }
    }

    $file_name = $_FILES['img_ss']['name'];
    $file_tmp = $_FILES['img_ss']['tmp_name'];
    $file_type = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));

    $allowed_types = ['jpg', 'jpeg', 'png', 'gif'];
    if (!in_array($file_type, $allowed_types)) {
        http_response_code(400);
        echo json_encode(['status' => 'error', 'message' => 'Hanya file JPG, JPEG, PNG, GIF yang diizinkan.']);
        exit();
    }

    // Generate nama unik (hanya nama file, tanpa path)
    $unique_name = 'bukti_' . preg_replace('/[^a-zA-Z0-9\-]/', '_', $kode_payment) . '_' . time() . '.' . $file_type;
    $file_path = $upload_dir . $unique_name;

    if (move_uploaded_file($file_tmp, $file_path)) {
        
        $img_ss = $unique_name;
    } else {
        http_response_code(500);
        echo json_encode(['status' => 'error', 'message' => 'Gagal menyimpan gambar.']);
        exit();
    }
}

mysqli_begin_transaction($conn);

try {
    $stmt = mysqli_prepare($conn, "
        INSERT INTO proses_edc (
            kode_payment, transfer_or_edc, nama_bank, nama_pengirim, 
            nominal_transfer, tanggal_transfer, no_referensi, img_ss
        ) VALUES (?, ?, ?, ?, 0.0, NOW(), ?, ?)
    ");

    mysqli_stmt_bind_param($stmt, "ssssss", 
        $kode_payment,
        $transfer_or_edc,
        $nama_bank,
        $nama_pengirim,
        $no_referensi,
        $img_ss  // <-- hanya nama file
    );

    mysqli_stmt_execute($stmt);
    $id_proses_edc = mysqli_insert_id($conn);
    mysqli_stmt_close($stmt);

    mysqli_commit($conn);

    // Untuk respons, kembalikan full URL jika perlu (opsional)
    $full_img_url = $img_ss ? '/_resto007/public/images/bukti_tf/' . $img_ss : '';

    echo json_encode([
        'status' => 'success',
        'message' => 'Data pembayaran berhasil disimpan.',
        'id' => $id_proses_edc,
        'img_file_name' => $img_ss,        // nama file saja
        'img_url' => $full_img_url         // URL lengkap untuk frontend (opsional)
    ]);

} catch (Exception $e) {
    mysqli_rollback($conn);
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Gagal menyimpan: ' . $e->getMessage()]);
} finally {
    mysqli_close($conn);
}
?>