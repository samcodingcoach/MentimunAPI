<?php
session_start();
require_once __DIR__ . '/../../config/koneksi.php';

if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    header('Location: login.php');
    exit();
}

$message = '';
$error = '';

// Get date parameters
$tgl_start = isset($_GET['tgl_start']) ? $_GET['tgl_start'] : date('Y-m-d');
$tgl_end = isset($_GET['tgl_end']) ? $_GET['tgl_end'] : date('Y-m-d');

// SQL 1: Pengeluaran Pembelian Bahan (PO)
$sql_po = "SELECT
  'PENGELUARAN PEMBELIAN BAHAN (PO)' as nama,
	bahan_request.tanggal_request as tanggal,
 FORMAT(sum(case when bahan_request_detail.isInvoice = 0 and bahan_request_detail.isDone = 1 THEN subtotal else 0 end),0) as cash_done,
  FORMAT(sum(case when bahan_request_detail.isInvoice = 0 and bahan_request_detail.isDone = 0 THEN subtotal else 0 end),0) as cash_hutang,
  
  FORMAT(sum(case when bahan_request_detail.isInvoice = 1 and bahan_request_detail.isDone = 1 THEN subtotal else 0 end),0) as invoice_done,
  FORMAT(sum(case when bahan_request_detail.isInvoice = 1 and bahan_request_detail.isDone = 0 THEN subtotal else 0 end),0) as invoice_hutang,
  
  FORMAT(SUM(CASE WHEN bahan_request_detail.isInvoice = 0 AND bahan_request_detail.isDone = 1 THEN subtotal ELSE 0 END) 
    + SUM(CASE WHEN bahan_request_detail.isInvoice = 1 AND bahan_request_detail.isDone = 1 THEN subtotal ELSE 0 END),0) AS total
  
FROM
	bahan_request_detail
	INNER JOIN
	bahan_request
	ON 
		bahan_request_detail.id_request = bahan_request.id_request
    where
     
    DATE(tanggal_request) BETWEEN '$tgl_start' AND '$tgl_end'
    
    GROUP BY tanggal_request
    ";

$result_po = mysqli_query($conn, $sql_po);
$rincianpo = [];
while ($rowpo = mysqli_fetch_assoc($result_po)) {
    $rincianpo[] = $rowpo;
}

// SQL 2: Pengeluaran Produk (Akumulasi Bahan Baku Terpakai)
$sql_bahan = "SELECT
  'PENGELUARAN PRODUK (AKUMULASI BAHAN BAKU TERPAKAI)' as nama,
  produk_sell.tgl_release,
  
  FORMAT(sum((harga_menu.nominal - (harga_menu.biaya_produksi + harga_menu.margin)) * (produk_sell.stok_awal - produk_sell.stok)),0) as profit
FROM
	produk_sell
  INNER JOIN
	resep
	ON 
		produk_sell.id_produk = resep.id_produk
	INNER JOIN
	harga_menu
	ON 
		resep.id_resep = harga_menu.id_resep
  WHERE DATE(tgl_release) BETWEEN '$tgl_start' AND '$tgl_end'
  GROUP BY date(tgl_release)";

$result_bahan = mysqli_query($conn, $sql_bahan);
$rincianbahan = [];
while ($rowbahan = mysqli_fetch_assoc($result_bahan)) {
    $rincianbahan[] = $rowbahan;
}

// SQL 3: Penjualan Bruto
$sql_penjualanb = "SELECT 'PENJUALAN BRUTO (TERMASUK BIAYA PENAMBAHAN)' as nama, DATE(tanggal_payment) as tanggal_payment, FORMAT(sum(jumlah_uang),0) as total
FROM proses_pembayaran where DATE(tanggal_payment) BETWEEN '$tgl_start' AND '$tgl_end' GROUP BY date(tanggal_payment)";

$result_penjualanb = mysqli_query($conn, $sql_penjualanb);
$rincianpenjualanb = [];
while ($rowpenjualanb = mysqli_fetch_assoc($result_penjualanb)) {
    $rincianpenjualanb[] = $rowpenjualanb;
}

// SQL 4: Penjualan Neto
$sql_penjualan2 = "SELECT 'PENJUALAN NETO (NON BIAYA PENAMBAHAN)' as nama, DATE(tanggal_payment) as tanggal_payment, 
FORMAT(sum(jumlah_no_pajak_qris) ,0)
as total
FROM proses_pembayaran where DATE(tanggal_payment) BETWEEN '$tgl_start' AND '$tgl_end' GROUP BY date(tanggal_payment)";

$result_penjualan2 = mysqli_query($conn, $sql_penjualan2);
$rincianpenjualan2 = [];
while ($rowpenjualan2 = mysqli_fetch_assoc($result_penjualan2)) {
    $rincianpenjualan2[] = $rowpenjualan2;
}

// Calculate totals
$sum_po = 0;
foreach ($rincianpo as $rowpo) {
    $sum_po += str_replace(',', '', $rowpo['total']);
}

$sum_bahan = 0;
foreach ($rincianbahan as $rowbahan) {
    $sum_bahan += str_replace(',', '', $rowbahan['profit']);
}

$sum_penjualanb = 0;
foreach ($rincianpenjualanb as $rowpenjualanb) {
    $sum_penjualanb += str_replace(',', '', $rowpenjualanb['total']);
}

$sum_penjualan2 = 0;
foreach ($rincianpenjualan2 as $rowpenjualan2) {
    $sum_penjualan2 += str_replace(',', '', $rowpenjualan2['total']);
}
?>

<!doctype html>
<html lang="id" data-bs-theme="light">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Laporan Pengeluaran vs Penjualan - Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">
    <link href="../css/newadmin.css?v=3" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
</head>
<body>
    <?php include '_header_new.php'; ?>
    <?php include '_sidebar_new.php'; ?>

    <main class="main-content" id="mainContent">
        <div class="container-fluid">
            <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-3">
                <div>
                    <h2 class="mb-1"><i class="bi bi-bar-chart-line me-2"></i>Laporan Pengeluaran vs Penjualan</h2>
                    <p class="text-muted mb-0">Pantau arus keluar masuk dana Anda dalam satu layar</p>
                </div>
                <div class="d-flex gap-2">
                    <span class="badge bg-secondary-subtle text-secondary px-3 py-2">
                        <i class="bi bi-calendar-week me-1"></i><?php echo date('d M Y', strtotime($tgl_start)); ?>
                    </span>
                    <span class="badge bg-secondary-subtle text-secondary px-3 py-2">
                        <i class="bi bi-calendar-week me-1"></i><?php echo date('d M Y', strtotime($tgl_end)); ?>
                    </span>
                </div>
            </div>

            <div class="card-modern mb-4">
                <div class="card-header px-4 py-3">
                    <div class="d-flex align-items-center">
                        <i class="bi bi-funnel me-2"></i>
                        <span>Filter Periode</span>
                    </div>
                </div>
                <div class="card-body">
                    <form method="GET" class="row g-3 align-items-end">
                        <div class="col-md-4">
                            <label for="tgl_start" class="form-label">Tanggal Mulai</label>
                            <input type="text" class="form-control" id="tgl_start" name="tgl_start" value="<?php echo htmlspecialchars($tgl_start); ?>" required>
                        </div>
                        <div class="col-md-4">
                            <label for="tgl_end" class="form-label">Tanggal Selesai</label>
                            <input type="text" class="form-control" id="tgl_end" name="tgl_end" value="<?php echo htmlspecialchars($tgl_end); ?>" required>
                        </div>
                        <div class="col-md-4">
                            <button type="submit" class="btn btn-primary">
                                <i class="bi bi-search me-2"></i>Tampilkan
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <div class="card-modern mb-4">
                <div class="card-header px-4 py-3">
                    <div class="d-flex flex-wrap justify-content-between align-items-center gap-3">
                        <div class="d-flex align-items-center">
                            <i class="bi bi-diagram-3 me-2"></i>
                            <span>Rincian Laporan</span>
                        </div>
                        <ul class="nav nav-pills gap-2" id="reportTabs" role="tablist">
                            <li class="nav-item" role="presentation">
                                <button class="nav-link active" id="section1-tab" data-bs-toggle="tab" data-bs-target="#section1" type="button" role="tab">
                                    <i class="bi bi-truck me-1"></i>PO Bahan
                                </button>
                            </li>
                            <li class="nav-item" role="presentation">
                                <button class="nav-link" id="section2-tab" data-bs-toggle="tab" data-bs-target="#section2" type="button" role="tab">
                                    <i class="bi bi-basket me-1"></i>Pengeluaran Produk
                                </button>
                            </li>
                            <li class="nav-item" role="presentation">
                                <button class="nav-link" id="section3-tab" data-bs-toggle="tab" data-bs-target="#section3" type="button" role="tab">
                                    <i class="bi bi-graph-up me-1"></i>Penjualan Bruto
                                </button>
                            </li>
                            <li class="nav-item" role="presentation">
                                <button class="nav-link" id="section4-tab" data-bs-toggle="tab" data-bs-target="#section4" type="button" role="tab">
                                    <i class="bi bi-graph-up-arrow me-1"></i>Penjualan Neto
                                </button>
                            </li>
                            <li class="nav-item" role="presentation">
                                <button class="nav-link" id="summary-tab" data-bs-toggle="tab" data-bs-target="#summary" type="button" role="tab">
                                    <i class="bi bi-journal-check me-1"></i>Ringkasan
                                </button>
                            </li>
                        </ul>
                    </div>
                </div>
                <div class="card-body p-0">
                    <div class="tab-content" id="reportTabsContent">
                        <div class="tab-pane fade show active" id="section1" role="tabpanel" aria-labelledby="section1-tab">
                            <div class="px-0 py-0">
                                
                                <div class="table-responsive">
                                    <table class="table table-hover align-middle mb-0">
                                        <thead class="table-light text-center">
                                            <tr>
                                                <th style="width:5%;">No</th>
                                                <th style="width:12%;">Tanggal</th>
                                                <th class="text-end">Cash Paid</th>
                                                <th class="text-end">Cash Unpaid</th>
                                                <th class="text-end">Invoice Paid</th>
                                                <th class="text-end">Invoice Unpaid</th>
                                                <th class="text-end">Total</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php if (empty($rincianpo)): ?>
                                            <tr>
                                                <td colspan="7" class="text-center py-5">
                                                    <i class="bi bi-inbox fs-1 d-block mb-2 text-muted"></i>
                                                    <span class="text-muted">Tidak ada data</span>
                                                </td>
                                            </tr>
                                            <?php else: ?>
                                                <?php $no = 1; foreach ($rincianpo as $rowpo): ?>
                                                <tr>
                                                    <td class="text-center fw-semibold"><?php echo $no++; ?></td>
                                                    <td class="text-center text-nowrap"><?php echo htmlspecialchars($rowpo['tanggal']); ?></td>
                                                    <td class="text-end">Rp <?php echo htmlspecialchars($rowpo['cash_done']); ?></td>
                                                    <td class="text-end text-warning">Rp <?php echo htmlspecialchars($rowpo['cash_hutang']); ?></td>
                                                    <td class="text-end">Rp <?php echo htmlspecialchars($rowpo['invoice_done']); ?></td>
                                                    <td class="text-end text-warning">Rp <?php echo htmlspecialchars($rowpo['invoice_hutang']); ?></td>
                                                    <td class="text-end fw-semibold text-danger">Rp <?php echo htmlspecialchars($rowpo['total']); ?></td>
                                                </tr>
                                                <?php endforeach; ?>
                                            <?php endif; ?>
                                        </tbody>
                                    </table>
                                </div>
                                <?php if (!empty($rincianpo)): ?>
                                <div class="d-flex justify-content-end mt-3">
                                    <div class="badge bg-danger-subtle text-danger px-3 py-2">
                                        <i class="bi bi-calculator me-1"></i>Grand Total: Rp <?php echo number_format($sum_po, 0, ',', '.'); ?>
                                    </div>
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>

                        <div class="tab-pane fade" id="section2" role="tabpanel" aria-labelledby="section2-tab">
                            <div class="px-4 py-4">
                                <h6 class="fw-semibold mb-3 text-primary">Pengeluaran Produk (Akumulasi Bahan Baku Terpakai)</h6>
                                <div class="table-responsive">
                                    <table class="table table-hover align-middle mb-0">
                                        <thead class="table-light text-center">
                                            <tr>
                                                <th style="width:5%;">No</th>
                                                <th>Tanggal</th>
                                                <th class="text-end">Total</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php if (empty($rincianbahan)): ?>
                                            <tr>
                                                <td colspan="3" class="text-center py-5">
                                                    <i class="bi bi-inbox fs-1 d-block mb-2 text-muted"></i>
                                                    <span class="text-muted">Tidak ada data</span>
                                                </td>
                                            </tr>
                                            <?php else: ?>
                                                <?php $no = 1; foreach ($rincianbahan as $rowbahan): ?>
                                                <tr>
                                                    <td class="text-center fw-semibold"><?php echo $no++; ?></td>
                                                    <td class="text-center text-nowrap"><?php echo htmlspecialchars($rowbahan['tgl_release']); ?></td>
                                                    <td class="text-end fw-semibold text-danger">Rp <?php echo htmlspecialchars($rowbahan['profit']); ?></td>
                                                </tr>
                                                <?php endforeach; ?>
                                            <?php endif; ?>
                                        </tbody>
                                    </table>
                                </div>
                                <?php if (!empty($rincianbahan)): ?>
                                <div class="d-flex justify-content-end mt-3">
                                    <div class="badge bg-danger-subtle text-danger px-3 py-2">
                                        <i class="bi bi-calculator me-1"></i>Grand Total: Rp <?php echo number_format($sum_bahan, 0, ',', '.'); ?>
                                    </div>
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>

                        <div class="tab-pane fade" id="section3" role="tabpanel" aria-labelledby="section3-tab">
                            <div class="px-4 py-4">
                                <h6 class="fw-semibold mb-3 text-primary">Penjualan Bruto (Termasuk Penambahan)</h6>
                                <div class="table-responsive">
                                    <table class="table table-hover align-middle mb-0">
                                        <thead class="table-light text-center">
                                            <tr>
                                                <th style="width:5%;">No</th>
                                                <th>Tanggal</th>
                                                <th class="text-end">Total</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php if (empty($rincianpenjualanb)): ?>
                                            <tr>
                                                <td colspan="3" class="text-center py-5">
                                                    <i class="bi bi-inbox fs-1 d-block mb-2 text-muted"></i>
                                                    <span class="text-muted">Tidak ada data</span>
                                                </td>
                                            </tr>
                                            <?php else: ?>
                                                <?php $no = 1; foreach ($rincianpenjualanb as $rowpenjualanb): ?>
                                                <tr>
                                                    <td class="text-center fw-semibold"><?php echo $no++; ?></td>
                                                    <td class="text-center text-nowrap"><?php echo htmlspecialchars($rowpenjualanb['tanggal_payment']); ?></td>
                                                    <td class="text-end fw-semibold text-success">Rp <?php echo htmlspecialchars($rowpenjualanb['total']); ?></td>
                                                </tr>
                                                <?php endforeach; ?>
                                            <?php endif; ?>
                                        </tbody>
                                    </table>
                                </div>
                                <?php if (!empty($rincianpenjualanb)): ?>
                                <div class="d-flex justify-content-end mt-3">
                                    <div class="badge bg-success-subtle text-success px-3 py-2">
                                        <i class="bi bi-calculator me-1"></i>Grand Total: Rp <?php echo number_format($sum_penjualanb, 0, ',', '.'); ?>
                                    </div>
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>

                        <div class="tab-pane fade" id="section4" role="tabpanel" aria-labelledby="section4-tab">
                            <div class="px-4 py-4">
                                <h6 class="fw-semibold mb-3 text-primary">Penjualan Neto (Non Biaya Penambahan)</h6>
                                <div class="table-responsive">
                                    <table class="table table-hover align-middle mb-0">
                                        <thead class="table-light text-center">
                                            <tr>
                                                <th style="width:5%;">No</th>
                                                <th>Tanggal</th>
                                                <th class="text-end">Total</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php if (empty($rincianpenjualan2)): ?>
                                            <tr>
                                                <td colspan="3" class="text-center py-5">
                                                    <i class="bi bi-inbox fs-1 d-block mb-2 text-muted"></i>
                                                    <span class="text-muted">Tidak ada data</span>
                                                </td>
                                            </tr>
                                            <?php else: ?>
                                                <?php $no = 1; foreach ($rincianpenjualan2 as $rowpenjualan2): ?>
                                                <tr>
                                                    <td class="text-center fw-semibold"><?php echo $no++; ?></td>
                                                    <td class="text-center text-nowrap"><?php echo htmlspecialchars($rowpenjualan2['tanggal_payment']); ?></td>
                                                    <td class="text-end fw-semibold text-success">Rp <?php echo htmlspecialchars($rowpenjualan2['total']); ?></td>
                                                </tr>
                                                <?php endforeach; ?>
                                            <?php endif; ?>
                                        </tbody>
                                    </table>
                                </div>
                                <?php if (!empty($rincianpenjualan2)): ?>
                                <div class="d-flex justify-content-end mt-3">
                                    <div class="badge bg-success-subtle text-success px-3 py-2">
                                        <i class="bi bi-calculator me-1"></i>Grand Total: Rp <?php echo number_format($sum_penjualan2, 0, ',', '.'); ?>
                                    </div>
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>

                        <div class="tab-pane fade" id="summary" role="tabpanel" aria-labelledby="summary-tab">
                            <div class="px-4 py-4">
                                <?php
                                $total_pengeluaran = $sum_po + $sum_bahan;
                                $total_penjualan = $sum_penjualanb + $sum_penjualan2;
                                $net_profit = $total_penjualan - $total_pengeluaran;
                                ?>
                                <div class="row g-4">
                                    <div class="col-lg-6">
                                        <div class="summary-card bg-danger-subtle">
                                            <h6 class="text-danger mb-3"><i class="bi bi-arrow-down-circle me-2"></i>Pengeluaran</h6>
                                            <div class="d-flex justify-content-between mb-2">
                                                <span>Pembelian Bahan (PO)</span>
                                                <strong>Rp <?php echo number_format($sum_po, 0, ',', '.'); ?></strong>
                                            </div>
                                            <div class="d-flex justify-content-between mb-2">
                                                <span>Produk Bahan Terpakai</span>
                                                <strong>Rp <?php echo number_format($sum_bahan, 0, ',', '.'); ?></strong>
                                            </div>
                                            <hr>
                                            <div class="d-flex justify-content-between">
                                                <span>Total Pengeluaran</span>
                                                <strong>Rp <?php echo number_format($total_pengeluaran, 0, ',', '.'); ?></strong>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-lg-6">
                                        <div class="summary-card bg-success-subtle">
                                            <h6 class="text-success mb-3"><i class="bi bi-arrow-up-circle me-2"></i>Penjualan</h6>
                                            <div class="d-flex justify-content-between mb-2">
                                                <span>Penjualan Bruto</span>
                                                <strong>Rp <?php echo number_format($sum_penjualanb, 0, ',', '.'); ?></strong>
                                            </div>
                                            <div class="d-flex justify-content-between mb-2">
                                                <span>Penjualan Neto</span>
                                                <strong>Rp <?php echo number_format($sum_penjualan2, 0, ',', '.'); ?></strong>
                                            </div>
                                            <hr>
                                            <div class="d-flex justify-content-between">
                                                <span>Total Penjualan</span>
                                                <strong>Rp <?php echo number_format($total_penjualan, 0, ',', '.'); ?></strong>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div class="mt-4 text-center">
                                    <div class="badge <?php echo $net_profit >= 0 ? 'bg-success-subtle text-success' : 'bg-danger-subtle text-danger'; ?> px-4 py-3 fs-5">
                                        <i class="bi bi-cash-coin me-2"></i>Net Profit: Rp <?php echo number_format($net_profit, 0, ',', '.'); ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="card-footer bg-light border-top py-3 px-4 d-flex justify-content-between align-items-center">
                    <small class="text-muted">Pengeluaran: Rp <?php echo number_format($sum_po + $sum_bahan, 0, ',', '.'); ?></small>
                    <small class="text-muted">Penjualan: Rp <?php echo number_format($sum_penjualanb + $sum_penjualan2, 0, ',', '.'); ?></small>
                    <small class="text-muted"><i class="bi bi-clock-history me-1"></i>Export time: <?php echo date('d F Y H:i'); ?></small>
                </div>
            </div>
        </div>
    </main>

    <?php include '_scripts_new.php'; ?>
    <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
    <script>
        flatpickr('#tgl_start', {
            dateFormat: 'Y-m-d',
            maxDate: 'today',
            defaultDate: '<?php echo $tgl_start; ?>',
            locale: { firstDayOfWeek: 1 }
        });
        flatpickr('#tgl_end', {
            dateFormat: 'Y-m-d',
            maxDate: 'today',
            defaultDate: '<?php echo $tgl_end; ?>',
            locale: { firstDayOfWeek: 1 }
        });
    </script>
</body>
</html>