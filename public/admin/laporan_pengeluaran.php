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
$export_query = http_build_query([
    'tgl_start' => $tgl_start,
    'tgl_end' => $tgl_end
]);

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

$total_pengeluaran = $sum_po + $sum_bahan;
$total_penjualan = $sum_penjualanb + $sum_penjualan2;
$net_profit = $total_penjualan - $total_pengeluaran;

if (isset($_GET['export']) && $_GET['export'] === 'pdf') {
    require_once __DIR__ . '/../libs/fpdf/fpdf.php';

    $convertText = static function ($text) {
        $text = (string)$text;
        if (function_exists('iconv')) {
            $converted = @iconv('UTF-8', 'ISO-8859-1//TRANSLIT', $text);
            if ($converted !== false) {
                return $converted;
            }
        }
        return preg_replace('/[^\x00-\xFF]/', '', $text);
    };

    $formatCurrency = static function ($value) use ($convertText) {
        $amount = is_numeric($value) ? (float)$value : 0.0;
        return $convertText('Rp ' . number_format($amount, 0, ',', '.'));
    };

    $chart_data = [
        'Pengeluaran PO' => $sum_po,
        'Pengeluaran Produk' => $sum_bahan,
        'Penjualan Bruto' => $sum_penjualanb,
        'Penjualan Neto' => $sum_penjualan2,
    ];

    while (ob_get_level() > 0) {
        ob_end_clean();
    }

    try {
        $pdf = new FPDF('L', 'mm', 'A4');
        $pdf->SetMargins(12, 12, 12);
        $pdf->AddPage();

        $pdf->SetDrawColor(220, 220, 220);
        $pdf->SetFont('Arial', 'B', 18);
        $pdf->Cell(0, 12, $convertText('Ringkasan Laporan Pengeluaran vs Penjualan'), 0, 1, 'C');
        $pdf->SetFont('Arial', '', 11);
        $pdf->Cell(0, 7, $convertText('Periode: ' . $tgl_start . ' s/d ' . $tgl_end), 0, 1, 'C');
        $pdf->Cell(0, 6, $convertText('Diunduh: ' . date('d M Y H:i')), 0, 1, 'C');
        $pdf->Ln(6);

    $summary_headers = ['Kategori', 'Nominal'];
    $summary_widths = [190, 83];

        $summary_rows = [
            ['Pengeluaran - Pembelian Bahan (PO)', $sum_po],
            ['Pengeluaran - Produk Bahan Terpakai', $sum_bahan],
            ['Total Pengeluaran', $total_pengeluaran],
            ['Penjualan - Bruto', $sum_penjualanb],
            ['Penjualan - Neto', $sum_penjualan2],
            ['Total Penjualan', $total_penjualan],
        ];

        $pdf->SetFont('Arial', 'B', 11);
        $pdf->SetFillColor(230, 230, 230);
        foreach ($summary_headers as $idx => $header) {
            $pdf->Cell($summary_widths[$idx], 9, $convertText($header), 1, 0, 'C', true);
        }
        $pdf->Ln();

        $pdf->SetFont('Arial', '', 10);
        foreach ($summary_rows as $row) {
            $pdf->Cell($summary_widths[0], 8, $convertText($row[0]), 1, 0, 'L');
            $pdf->Cell($summary_widths[1], 8, $formatCurrency($row[1]), 1, 1, 'R');
        }

        $pdf->Ln(6);
        $pdf->SetFont('Arial', 'B', 12);
        $pdf->Cell(0, 8, $convertText('Net Profit'), 0, 1, 'L');

        $pdf->SetFont('Arial', '', 11);
        if ($net_profit >= 0) {
            $pdf->SetFillColor(223, 240, 216);
            $pdf->SetTextColor(34, 139, 34);
        } else {
            $pdf->SetFillColor(250, 218, 221);
            $pdf->SetTextColor(178, 34, 34);
        }
        $pdf->Cell(100, 10, $convertText('Laba Bersih'), 1, 0, 'L', true);
        $pdf->Cell(70, 10, $formatCurrency($net_profit), 1, 1, 'R', true);
        $pdf->SetTextColor(0, 0, 0);

    $pdf->AddPage();
    $pdf->SetAutoPageBreak(false);
    $pdf->SetFont('Arial', 'B', 14);
    $pdf->Cell(0, 10, $convertText('Visualisasi Ringkasan'), 0, 1, 'L');
    $pdf->Ln(2);

    $chart_origin_x = 30;
    $chart_origin_y = 185;
    $chart_height = 120;
    $chart_width = 240;
    $bar_padding = 20;
    $bar_count = count($chart_data);
    $max_value = max(array_map('floatval', $chart_data)) ?: 1;
    $bar_width = ($chart_width - ($bar_padding * ($bar_count + 1))) / $bar_count;

    $pdf->SetDrawColor(180, 180, 180);
    $pdf->Line($chart_origin_x, $chart_origin_y - $chart_height, $chart_origin_x, $chart_origin_y);
    $pdf->Line($chart_origin_x, $chart_origin_y, $chart_origin_x + $chart_width, $chart_origin_y);

    $pdf->SetFont('Arial', '', 10);
    $index = 0;
    $bar_colors = [
        [231, 76, 60],
        [214, 90, 49],
        [39, 174, 96],
        [46, 134, 193],
    ];

    foreach ($chart_data as $label => $value) {
        $x = $chart_origin_x + $bar_padding + ($index * ($bar_width + $bar_padding));
        $bar_height = $max_value > 0 ? ($value / $max_value) * ($chart_height - 20) : 0;
        $y = $chart_origin_y - $bar_height;

        $color = $bar_colors[$index % count($bar_colors)];
        $pdf->SetFillColor($color[0], $color[1], $color[2]);
        $pdf->Rect($x, $y, $bar_width, $bar_height, 'DF');

        $pdf->SetXY($x, $y - 10);
        $pdf->Cell($bar_width, 6, $formatCurrency($value), 0, 0, 'C');

        $pdf->SetXY($x, $chart_origin_y + 6);
        $pdf->Cell($bar_width, 6, $convertText($label), 0, 0, 'C');

        $index++;
    }

    $pdf->SetAutoPageBreak(true, 15);

        $filename = 'ringkasan_pengeluaran_penjualan_' . date('Ymd_His') . '.pdf';
        $pdf->Output('D', $filename);
    } catch (Throwable $e) {
        error_log('Ringkasan pengeluaran PDF export failed: ' . $e->getMessage());
        http_response_code(500);
        exit('Terjadi kesalahan saat membuat PDF.');
    }
    exit;
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
                        <div class="col-12 col-md-3 col-lg-3">
                            <label for="tgl_start" class="form-label">Tanggal Mulai</label>
                            <input type="text" class="form-control" id="tgl_start" name="tgl_start" value="<?php echo htmlspecialchars($tgl_start); ?>" required>
                        </div>
                        <div class="col-12 col-md-3 col-lg-3">
                            <label for="tgl_end" class="form-label">Tanggal Selesai</label>
                            <input type="text" class="form-control" id="tgl_end" name="tgl_end" value="<?php echo htmlspecialchars($tgl_end); ?>" required>
                        </div>
                        <div class="col-12 col-md-3 col-lg-2">
                            <button type="submit" class="btn btn-primary w-100">
                                <i class="bi bi-search me-2"></i>Tampilkan
                            </button>
                        </div>
                        <div class="col-12 col-md-3 col-lg-2">
                            <a href="?export=pdf&amp;<?php echo htmlspecialchars($export_query, ENT_QUOTES, 'UTF-8'); ?>" class="btn btn-outline-danger w-100 d-flex align-items-center justify-content-center gap-2">
                                <i class="bi bi-file-earmark-pdf-fill"></i>
                                <span>Export PDF</span>
                            </a>
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
                                                <th style="width:auto;">Tanggal</th>
                                                <th class="text-end" style="width:15%;">Cash Paid</th>
                                                <th class="text-end" style="width:15%;">Cash Unpaid</th>
                                                <th class="text-end" style="width:15%;">Invoice Paid</th>
                                                <th class="text-end" style="width:15%;">Invoice Unpaid</th>
                                                <th class="text-end" style="width:20%;">Total</th>
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
                                <div class="d-flex justify-content-end mt-3 p-2 y-1">
                                    <div class="badge bg-info-subtle text-info px-3 py-2">
                                        <i class="bi bi-calculator me-1"></i>Grand Total: Rp <?php echo number_format($sum_po, 0, ',', '.'); ?>
                                    </div>
                                </div>
                                <?php endif; ?>

                            </div>
                        </div>

                        <div class="tab-pane fade" id="section2" role="tabpanel" aria-labelledby="section2-tab">
                            <div class="px-0 py-0">
                                
                                <div class="table-responsive">
                                    <table class="table table-hover align-middle mb-0">
                                        <thead class="table-light text-center">
                                            <tr>
                                                <th style="width:5%;">No</th>
                                                <th style="width:10%;">Tanggal</th>
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
                                <div class="d-flex justify-content-end mt-3 p-2 y-1">
                                    <div class="badge bg-info-subtle text-info px-3 py-2">
                                        <i class="bi bi-calculator me-1"></i>Grand Total: Rp <?php echo number_format($sum_bahan, 0, ',', '.'); ?>
                                    </div>
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>

                        <div class="tab-pane fade" id="section3" role="tabpanel" aria-labelledby="section3-tab">
                            <div class="px-0 py-0">
                                
                                <div class="table-responsive">
                                    <table class="table table-hover align-middle mb-0">
                                        <thead class="table-light text-center">
                                            <tr>
                                                <th style="width:5%;">No</th>
                                                <th style="width:10%">Tanggal</th>
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
                                <div class="d-flex justify-content-end mt-3 p-2 y-1">
                                    <div class="badge bg-success-subtle text-success px-3 py-2">
                                        <i class="bi bi-calculator me-1"></i>Grand Total: Rp <?php echo number_format($sum_penjualanb, 0, ',', '.'); ?>
                                    </div>
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>

                        <div class="tab-pane fade" id="section4" role="tabpanel" aria-labelledby="section4-tab">
                            <div class="px-0 py-0">
                                
                                <div class="table-responsive">
                                    <table class="table table-hover align-middle mb-0">
                                        <thead class="table-light text-center">
                                            <tr>
                                                <th style="width:5%;">No</th>
                                                <th style="width:10%">Tanggal</th>
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
                                <div class="d-flex justify-content-end mt-3 p-2 y-1">
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
                                    <div class="col-lg-6 ">
                                        <div class="summary-card bg-danger-subtle px-2 py-2">
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
                                        <div class="summary-card bg-success-subtle px-2 py-2">
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