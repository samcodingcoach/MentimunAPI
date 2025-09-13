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

// Calculate total
$total_amount = 0;
$total_qty = 0;
foreach ($details as $detail) {
    $total_amount += ($detail['harga_jual'] * $detail['qty']);
    $total_qty += $detail['qty'];
}
?>

<?php if (empty($details)): ?>
    <div class="alert alert-info">
        <i class="bi bi-info-circle"></i> Tidak ada detail pembatalan untuk ID Tagihan ini
    </div>
<?php else: ?>
    <!-- Tabs Navigation -->
    <ul class="nav nav-tabs mb-3" id="detailTabs" role="tablist">
        <li class="nav-item" role="presentation">
            <button class="nav-link active" id="ringkasan-tab" data-bs-toggle="tab" data-bs-target="#ringkasan" type="button" role="tab">
                <i class="bi bi-card-list"></i> Ringkasan
            </button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link" id="items-tab" data-bs-toggle="tab" data-bs-target="#items" type="button" role="tab">
                <i class="bi bi-basket"></i> Item Dibatalkan
            </button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link" id="info-tab" data-bs-toggle="tab" data-bs-target="#info" type="button" role="tab">
                <i class="bi bi-info-circle"></i> Informasi
            </button>
        </li>
    </ul>

    <!-- Tab Content -->
    <div class="tab-content" id="detailTabsContent">
        <!-- Ringkasan Tab -->
        <div class="tab-pane fade show active" id="ringkasan" role="tabpanel">
            <div class="row g-3">
                <!-- Total Card -->
                <div class="col-12">
                    <div class="card border-0 shadow-sm" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);">
                        <div class="card-body text-white">
                            <div class="row align-items-center">
                                <div class="col">
                                    <h6 class="text-white-50 mb-1">Total Pembatalan</h6>
                                    <h2 class="mb-0">Rp <?php echo number_format($total_amount, 0, ',', '.'); ?></h2>
                                </div>
                                
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Info Cards -->
                <div class="col-md-6">
                    <div class="card border-0 shadow-sm h-100">
                        <div class="card-body">
                            <div class="d-flex align-items-center mb-2">
                                <div class="bg-primary bg-opacity-10 rounded-circle p-2 me-3">
                                    <i class="bi bi-table text-primary"></i>
                                </div>
                                <div class="flex-grow-1">
                                    <small class="text-muted d-block">Nomor Meja</small>
                                    <h5 class="mb-0"><?php echo htmlspecialchars($details[0]['nomor_meja']); ?></h5>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="col-md-6">
                    <div class="card border-0 shadow-sm h-100">
                        <div class="card-body">
                            <div class="d-flex align-items-center mb-2">
                                <div class="bg-info bg-opacity-10 rounded-circle p-2 me-3">
                                    <i class="bi bi-box text-info"></i>
                                </div>
                                <div class="flex-grow-1">
                                    <small class="text-muted d-block">Total Item</small>
                                    <h5 class="mb-0"><?php echo $total_qty; ?> item</h5>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Items Tab -->
        <div class="tab-pane fade" id="items" role="tabpanel">
            <div class="row g-3">
                <?php 
                foreach ($details as $detail): 
                    $subtotal = $detail['harga_jual'] * $detail['qty'];
                    $jenis_text = $detail['jenis_pesanan'] == 0 ? 'Dine In' : 'Takeaway';
                    $jenis_color = $detail['jenis_pesanan'] == 0 ? 'success' : 'warning';
                ?>
                <div class="col-md-6">
                    <div class="card border-0 shadow-sm h-100">
                        <div class="card-body">
                            <!-- Product Header -->
                            <div class="d-flex justify-content-between align-items-start mb-3">
                                <div>
                                    <h6 class="mb-1"><?php echo htmlspecialchars($detail['nama_produk']); ?></h6>
                                    <span class="badge bg-secondary"><?php echo htmlspecialchars($detail['kode_produk']); ?></span>
                                    <span class="badge bg-<?php echo $jenis_color; ?> ms-1"><?php echo $jenis_text; ?></span>
                                </div>
                                <span class="text-muted small"><?php echo htmlspecialchars($detail['waktu']); ?></span>
                            </div>
                            
                            <!-- Price Info -->
                            <div class="row g-2 mb-2">
                                <div class="col-4">
                                    <small class="text-muted d-block">Qty</small>
                                    <strong><?php echo $detail['qty']; ?></strong>
                                </div>
                                <div class="col-4">
                                    <small class="text-muted d-block">Harga</small>
                                    <strong>Rp <?php echo number_format($detail['harga_jual'], 0, ',', '.'); ?></strong>
                                </div>
                                <div class="col-4">
                                    <small class="text-muted d-block">Subtotal</small>
                                    <strong class="text-danger">Rp <?php echo number_format($subtotal, 0, ',', '.'); ?></strong>
                                </div>
                            </div>
                            
                            <!-- Reason -->
                            <?php if (!empty($detail['alasan'])): ?>
                            <div class="border-top pt-2 mt-2">
                                <small class="text-muted">Alasan: <?php echo htmlspecialchars($detail['alasan']); ?></small>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- Info Tab -->
        <div class="tab-pane fade" id="info" role="tabpanel">
            <div class="row g-3">
                <?php 
                // Group by employee
                $employees = [];
                foreach ($details as $detail) {
                    if (!isset($employees[$detail['nama_lengkap']])) {
                        $employees[$detail['nama_lengkap']] = [
                            'items' => [],
                            'total' => 0,
                            'qty' => 0
                        ];
                    }
                    $employees[$detail['nama_lengkap']]['items'][] = $detail;
                    $employees[$detail['nama_lengkap']]['total'] += ($detail['harga_jual'] * $detail['qty']);
                    $employees[$detail['nama_lengkap']]['qty'] += $detail['qty'];
                }
                
                foreach ($employees as $employee_name => $employee_data): 
                ?>
                <div class="col-12">
                    <div class="card border-0 shadow-sm">
                        <div class="card-body">
                            <div class="d-flex align-items-center mb-3">
                                <div class="bg-warning bg-opacity-10 rounded-circle p-2 me-3">
                                    <i class="bi bi-person-fill text-warning"></i>
                                </div>
                                <div class="flex-grow-1">
                                    <h6 class="mb-0">Dibatalkan oleh: <?php echo htmlspecialchars($employee_name); ?></h6>
                                    <small class="text-muted"><?php echo $employee_data['qty']; ?> item | Rp <?php echo number_format($employee_data['total'], 0, ',', '.'); ?></small>
                                </div>
                            </div>
                            
                            <div class="list-group list-group-flush">
                                <?php foreach ($employee_data['items'] as $item): ?>
                                <div class="list-group-item px-0">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <div>
                                            <strong><?php echo htmlspecialchars($item['nama_produk']); ?></strong>
                                            <small class="text-muted d-block">
                                                <?php echo $item['qty']; ?> x Rp <?php echo number_format($item['harga_jual'], 0, ',', '.'); ?>
                                                <?php if (!empty($item['alasan'])): ?>
                                                    | <?php echo htmlspecialchars($item['alasan']); ?>
                                                <?php endif; ?>
                                            </small>
                                        </div>
                                        <span class="badge bg-light text-dark"><?php echo htmlspecialchars($item['waktu']); ?></span>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
<?php endif; ?>
