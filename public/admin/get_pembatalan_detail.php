<?php
session_start();
require_once __DIR__ . '/../../config/koneksi.php';

// Check if user is logged in
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    http_response_code(401);
    exit('Unauthorized');
}

// Check permission - Only Admin and Kasir can access
if ($_SESSION["jabatan"] != "Admin" && $_SESSION["jabatan"] != "Kasir") {
    http_response_code(403);
    exit('Forbidden');
}

// Get parameters
$id_tagihan = isset($_GET['id_tagihan']) ? $_GET['id_tagihan'] : '';
$tanggal_mulai = isset($_GET['tanggal_mulai']) ? $_GET['tanggal_mulai'] : date('Y-m-d');
$tanggal_selesai = isset($_GET['tanggal_selesai']) ? $_GET['tanggal_selesai'] : date('Y-m-d');

if (empty($id_tagihan)) {
    echo '<div class="alert alert-warning">ID Tagihan tidak valid</div>';
    exit;
}

// Query to get cancellation details
$sql = "
    SELECT
        dapur_batal.id_batal,
        DATE_FORMAT(dapur_batal.waktu,'%H:%i') AS waktu,
        dapur_batal.alasan,
        dapur_batal.qty,
        pesanan_detail.ta_dinein as jenis_pesanan,
        produk_sell.harga_jual,
        meja.nomor_meja,
        view_produk.kode_produk,
        view_produk.nama_produk,
        pegawai.nama_lengkap
    FROM
        dapur_batal
    INNER JOIN dapur_order_detail ON dapur_batal.id_order_detail = dapur_order_detail.id_order_detail
    INNER JOIN pesanan ON dapur_batal.id_pesanan = pesanan.id_pesanan
    INNER JOIN pesanan_detail ON dapur_order_detail.id_pesanan_detail = pesanan_detail.id_pesanan_detail
    INNER JOIN produk_sell ON pesanan_detail.id_produk_sell = produk_sell.id_produk_sell
    INNER JOIN meja ON pesanan.id_meja = meja.id_meja
    INNER JOIN view_produk ON produk_sell.id_produk = view_produk.id_produk
    INNER JOIN pegawai ON dapur_batal.id_user = pegawai.id_user
    WHERE
        DATE(dapur_batal.waktu) BETWEEN ? AND ?
        AND pesanan.id_tagihan = ?
    ORDER BY
        dapur_batal.id_batal DESC
";

$stmt = $conn->prepare($sql);
$stmt->bind_param("sss", $tanggal_mulai, $tanggal_selesai, $id_tagihan);
$stmt->execute();
$result = $stmt->get_result();
$details = $result->fetch_all(MYSQLI_ASSOC);

// Calculate total and get unique employees
$total_amount = 0;
$total_qty = 0;
$employees = [];
$orders = [];
foreach ($details as $detail) {
    $total_amount += ($detail['harga_jual'] * $detail['qty']);
    $total_qty += $detail['qty'];
    $employees[$detail['nama_lengkap']] = true;
    $orders[$detail['kode_produk']] = true;
}
$employee_names = implode(', ', array_keys($employees));
$total_transactions = count($orders);
?>

<?php if (empty($details)): ?>
    <div class="alert alert-info">
        <i class="bi bi-info-circle"></i> Tidak ada detail pembatalan untuk ID Tagihan ini
    </div>
<?php else: ?>
    <!-- Compact Summary -->
    <div class="d-flex justify-content-between align-items-center p-3 bg-light rounded mb-3">
        <div>
            <small class="text-muted">Meja <?php echo htmlspecialchars($details[0]['nomor_meja']); ?></small>
            <span class="mx-2">•</span>
            <small class="text-muted"><?php echo $total_qty; ?> item</small>
            <span class="mx-2">•</span>
            <small class="text-muted"><?php echo $total_transactions; ?> transaksi</small>
            <span class="mx-2">•</span>
            <small class="text-muted">Oleh: <?php echo htmlspecialchars($employee_names); ?></small>
        </div>
        <h5 class="mb-0 text-danger">Rp <?php echo number_format($total_amount, 0, ',', '.'); ?></h5>
    </div>

    <!-- Simple Items List -->
    <div class="list-group list-group-flush">
        <?php 
        foreach ($details as $detail): 
            $subtotal = $detail['harga_jual'] * $detail['qty'];
            $jenis_text = $detail['jenis_pesanan'] == 0 ? 'Dine In' : 'Takeaway';
            $jenis_color = $detail['jenis_pesanan'] == 0 ? 'success' : 'warning';
        ?>
        <div class="list-group-item px-0">
            <div class="d-flex justify-content-between align-items-start">
                <div class="flex-grow-1">
                    <div class="d-flex align-items-center mb-1">
                        <strong><?php echo htmlspecialchars($detail['nama_produk']); ?></strong>
                        <span class="badge bg-<?php echo $jenis_color; ?> ms-2 badge-sm"><?php echo $jenis_text; ?></span>
                    </div>
                    <small class="text-muted">
                        <?php echo htmlspecialchars($detail['kode_produk']); ?> • 
                        <?php echo $detail['qty']; ?> × Rp <?php echo number_format($detail['harga_jual'], 0, ',', '.'); ?>
                        <?php if (!empty($detail['alasan'])): ?>
                            <br><span class="text-danger">Alasan: <?php echo htmlspecialchars($detail['alasan']); ?></span>
                        <?php endif; ?>
                    </small>
                </div>
                <div class="text-end">
                    <strong>Rp <?php echo number_format($subtotal, 0, ',', '.'); ?></strong>
                    <br>
                    <small class="text-muted"><?php echo htmlspecialchars($detail['waktu']); ?></small>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>