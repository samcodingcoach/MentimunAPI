<?php
session_start();
require_once __DIR__ . '/../../config/koneksi.php';

// Check if user is logged in
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
<html lang="en">
  <head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Laporan Pengeluaran vs Penjualan - Admin Dashboard</title>
    <link href="../css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css" rel="stylesheet">
    <link href="../css/admin.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
  </head>
  <body>
    <!-- Top Navbar -->
    <nav class="navbar navbar-expand-lg navbar-dark bg-primary">
      <div class="container-fluid">
        <button class="navbar-toggler" type="button" data-bs-toggle="offcanvas" data-bs-target="#sidebarOffcanvas" aria-controls="sidebarOffcanvas">
          <span class="navbar-toggler-icon"></span>
        </button>
        <a class="navbar-brand" href="#"><?php echo isset($_SESSION['nama_aplikasi']) ? htmlspecialchars($_SESSION['nama_aplikasi']) : 'Admin'; ?></a>
        <div class="navbar-nav ms-auto">
          <div class="nav-item dropdown">
            <a class="nav-link dropdown-toggle text-white" href="#" role="button" data-bs-toggle="dropdown">
              <?php echo htmlspecialchars($_SESSION["nama_lengkap"]); ?> (<?php echo htmlspecialchars($_SESSION["jabatan"]); ?>)
            </a>
            <ul class="dropdown-menu">
              <li><a class="dropdown-item" href="profile.php">Ubah Profil</a></li>
              <li><hr class="dropdown-divider"></li>
              <li><a class="dropdown-item" href="logout.php">Logout</a></li>
            </ul>
          </div>
        </div>
      </div>
    </nav>

    <div class="container-fluid">
      <div class="row">
        <!-- Sidebar -->
        <div class="col-md-3 col-lg-2 d-md-block sidebar collapse" id="sidebarMenu">
          <div class="position-sticky pt-3">
            <ul class="nav flex-column">
              <li class="nav-item">
                <a class="nav-link" href="index.php">
                  <i class="bi bi-house-door"></i>
                  <span>Beranda</span>
                </a>
              </li>
              <li class="nav-item">
                <a class="nav-link" href="informasi.php">
                  <i class="bi bi-info-circle"></i>
                  <span>Informasi</span>
                </a>
              </li>
              
              <?php if($_SESSION["jabatan"] == "Admin"): ?>
              <!-- Master Menu - Admin Only -->
              <li class="nav-item">
                <a class="nav-link" data-bs-toggle="collapse" href="#masterMenu" role="button">
                  <i class="bi bi-gear-fill"></i>
                  <span>Master</span>
                  <i class="bi bi-chevron-down ms-auto"></i>
                </a>
                <div class="collapse" id="masterMenu">
                  <ul class="nav flex-column ms-3">
                    <li class="nav-item"><a class="nav-link" href="resto.php"><i class="bi bi-building"></i> Resto</a></li>
                    <li class="nav-item"><a class="nav-link" href="pegawai.php"><i class="bi bi-people"></i> Pegawai</a></li>
                    <li class="nav-item"><a class="nav-link" href="konsumen.php"><i class="bi bi-person-check"></i> Konsumen</a></li>
                    <li class="nav-item"><a class="nav-link" href="vendor.php"><i class="bi bi-truck"></i> Vendor</a></li>
                    <li class="nav-item"><a class="nav-link" href="meja.php"><i class="bi bi-table"></i> Meja</a></li>
                    <li class="nav-item"><a class="nav-link" href="metode_pembayaran.php"><i class="bi bi-credit-card"></i> Metode Pembayaran</a></li>
                  </ul>
                </div>
              </li>
              <?php endif; ?>
              
              <?php if($_SESSION["jabatan"] == "Admin" || $_SESSION["jabatan"] == "Dapur"): ?>
              <!-- Produk Menu -->
              <li class="nav-item">
                <a class="nav-link" data-bs-toggle="collapse" href="#produkMenu" role="button">
                  <i class="bi bi-box-seam"></i>
                  <span>Produk</span>
                  <i class="bi bi-chevron-down ms-auto"></i>
                </a>
                <div class="collapse" id="produkMenu">
                  <ul class="nav flex-column ms-3">
                    <li class="nav-item"><a class="nav-link" href="kategori_menu.php"><i class="bi bi-tags"></i> Kategori Menu</a></li>
                    <li class="nav-item"><a class="nav-link" href="menu.php"><i class="bi bi-card-list"></i> Menu</a></li>
                    <li class="nav-item"><a class="nav-link" href="kategori_bahan.php"><i class="bi bi-collection"></i> Kategori Bahan</a></li>
                    <li class="nav-item"><a class="nav-link" href="bahan.php"><i class="bi bi-basket"></i> Bahan</a></li>
                    <li class="nav-item"><a class="nav-link" href="resep.php"><i class="bi bi-journal-text"></i> Resep</a></li>
                  </ul>
                </div>
              </li>
              
              <!-- Pembelian Menu -->
              <li class="nav-item">
                <a class="nav-link" data-bs-toggle="collapse" href="#pembelianMenu" role="button">
                  <i class="bi bi-cart-plus"></i>
                  <span>Pembelian</span>
                  <i class="bi bi-chevron-down ms-auto"></i>
                </a>
                <div class="collapse" id="pembelianMenu">
                  <ul class="nav flex-column ms-3">
                    <li class="nav-item"><a class="nav-link" href="pembelian.php"><i class="bi bi-clipboard-check"></i> Pesanan Pembelian</a></li>
                    <li class="nav-item"><a class="nav-link" href="pembayaran_pembelian.php"><i class="bi bi-currency-dollar"></i> Pembayaran</a></li>
                  </ul>
                </div>
              </li>
              <?php endif; ?>
              
              <?php if($_SESSION["jabatan"] == "Admin" || $_SESSION["jabatan"] == "Kasir"): ?>
              <!-- Penjualan Menu -->
              <li class="nav-item">
                <a class="nav-link" data-bs-toggle="collapse" href="#penjualanMenu" role="button">
                  <i class="bi bi-cash-coin"></i>
                  <span>Penjualan</span>
                  <i class="bi bi-chevron-down ms-auto"></i>
                </a>
                <div class="collapse" id="penjualanMenu">
                  <ul class="nav flex-column ms-3">
                    <li class="nav-item"><a class="nav-link" href="shift_kasir.php"><i class="bi bi-clock"></i> Shift Kasir</a></li>
                    <li class="nav-item"><a class="nav-link" href="promo.php"><i class="bi bi-percent"></i> Promo</a></li>
                    <li class="nav-item"><a class="nav-link" href="biaya_lain.php"><i class="bi bi-receipt"></i> Biaya Lain</a></li>
                    <li class="nav-item"><a class="nav-link" href="harga_pokok_penjualan.php"><i class="bi bi-calculator"></i> Harga Pokok Penjualan</a></li>
                    <li class="nav-item"><a class="nav-link" href="harga_rilis.php"><i class="bi bi-tag"></i> Harga Rilis</a></li>
                    <li class="nav-item"><a class="nav-link" href="pembatalan.php"><i class="bi bi-x-circle"></i> Pembatalan</a></li>
                  </ul>
                </div>
              </li>
              <?php endif; ?>
              
              <?php if($_SESSION["jabatan"] == "Admin" || $_SESSION["jabatan"] == "Dapur"): ?>
              <!-- Inventory Menu -->
              <li class="nav-item">
                <a class="nav-link" data-bs-toggle="collapse" href="#inventoryMenu" role="button">
                  <i class="bi bi-boxes"></i>
                  <span>Inventory</span>
                  <i class="bi bi-chevron-down ms-auto"></i>
                </a>
                <div class="collapse" id="inventoryMenu">
                  <ul class="nav flex-column ms-3">
                    <li class="nav-item"><a class="nav-link" href="inventory.php"><i class="bi bi-box-seam"></i> Inventory</a></li>
                    <li class="nav-item"><a class="nav-link" href="transaksi_inventory.php"><i class="bi bi-arrow-left-right"></i> Transaksi</a></li>
                  </ul>
                </div>
              </li>
              <?php endif; ?>
              
              <!-- Laporan Menu - All Roles -->
              <li class="nav-item">
                <a class="nav-link" data-bs-toggle="collapse" href="#laporanMenu" role="button">
                  <i class="bi bi-graph-up"></i>
                  <span>Laporan</span>
                  <i class="bi bi-chevron-down ms-auto"></i>
                </a>
                <div class="collapse show" id="laporanMenu">
                  <ul class="nav flex-column ms-3">
                    <li class="nav-item"><a class="nav-link" href="laporan_transaksi.php"><i class="bi bi-list-ul"></i> Transaksi</a></li>
                    <li class="nav-item"><a class="nav-link active" href="laporan_pengeluaran.php"><i class="bi bi-bar-chart"></i> Pengeluaran vs Penjualan</a></li>
                    <li class="nav-item"><a class="nav-link" href="laporan_kuantitas.php"><i class="bi bi-pie-chart"></i> Kuantitas</a></li>
                  </ul>
                </div>
              </li>
              
              <!-- Pengaturan Menu - All Roles -->
              <li class="nav-item">
                <a class="nav-link" href="pengaturan.php">
                  <i class="bi bi-gear"></i>
                  <span>Pengaturan</span>
                </a>
              </li>
            </ul>
          </div>
        </div>

        <!-- Main content -->
        <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
          <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
            <h1 class="h2">Laporan Pengeluaran vs Penjualan</h1>
          </div>

          <!-- Date Filter -->
          <div class="mb-4">
            <form method="GET" class="row g-3 align-items-end">
              <div class="col-md-4">
                <label for="tgl_start" class="form-label">Tanggal Mulai</label>
                <input type="date" class="form-control" id="tgl_start" name="tgl_start" 
                       value="<?php echo htmlspecialchars($tgl_start); ?>" required>
              </div>
              <div class="col-md-4">
                <label for="tgl_end" class="form-label">Tanggal Selesai</label>
                <input type="date" class="form-control" id="tgl_end" name="tgl_end" 
                       value="<?php echo htmlspecialchars($tgl_end); ?>" required>
              </div>
              <div class="col-md-4">
                <button type="submit" class="btn btn-primary">
                  <i class="bi bi-search"></i> Tampilkan
                </button>
              </div>
            </form>
          </div>

          <!-- Report Header -->
          <div class="text-center mb-4">
            <h3>RINGKASAN PENGELUARAN VS PENJUALAN</h3>
            <h4><?php echo date("d F Y", strtotime($tgl_start)) . ' s.d ' . date("d F Y", strtotime($tgl_end)); ?></h4>
          </div>

          <!-- Tabs Navigation -->
          <ul class="nav nav-tabs" id="reportTabs" role="tablist">
            <li class="nav-item" role="presentation">
              <button class="nav-link active" id="section1-tab" data-bs-toggle="tab" data-bs-target="#section1" type="button" role="tab" aria-controls="section1" aria-selected="true">Pembelian Bahan (PO)</button>
            </li>
            <li class="nav-item" role="presentation">
              <button class="nav-link" id="section2-tab" data-bs-toggle="tab" data-bs-target="#section2" type="button" role="tab" aria-controls="section2" aria-selected="false">Pengeluaran Produk</button>
            </li>
            <li class="nav-item" role="presentation">
              <button class="nav-link" id="section3-tab" data-bs-toggle="tab" data-bs-target="#section3" type="button" role="tab" aria-controls="section3" aria-selected="false">Penjualan Bruto</button>
            </li>
            <li class="nav-item" role="presentation">
              <button class="nav-link" id="section4-tab" data-bs-toggle="tab" data-bs-target="#section4" type="button" role="tab" aria-controls="section4" aria-selected="false">Penjualan Neto</button>
            </li>
            <li class="nav-item" role="presentation">
              <button class="nav-link" id="summary-tab" data-bs-toggle="tab" data-bs-target="#summary" type="button" role="tab" aria-controls="summary" aria-selected="false">Summary</button>
            </li>
          </ul>

          <!-- Tabs Content -->
          <div class="tab-content" id="reportTabsContent">
            <!-- Section 1: Pengeluaran Pembelian Bahan (PO) -->
            <div class="tab-pane fade show active" id="section1" role="tabpanel" aria-labelledby="section1-tab">
              <div class="mb-4">
                <?php echo count($rincianpo) > 0 ? $rincianpo[0]['nama'] : "Tidak ada data pengeluaran pembelian bahan"; ?>
                <div>
                  <div>
                    <table class="table table-hover">
                      <thead class="table-dark">
                        <tr>
                          <th class="text-center" width="5%">NO</th>
                          <th class="text-center" width="15%">TANGGAL</th>
                          <th class="text-center" width="15%">CASH PAID</th>
                          <th class="text-center" width="15%">CASH UNPAID</th>
                          <th class="text-center" width="15%">INVOICE PAID</th>
                          <th class="text-center" width="20%">INVOICE UNPAID</th>
                          <th class="text-center" width="15%">TOTAL</th>
                        </tr>
                      </thead>
                      <tbody>
                        <?php if (empty($rincianpo)): ?>
                        <tr>
                          <td colspan="7" class="text-center text-muted py-3">Tidak ada data</td>
                        </tr>
                        <?php else: ?>
                        <?php $no = 1; foreach ($rincianpo as $rowpo): ?>
                        <tr>
                          <td class="text-center"><?php echo $no++; ?></td>
                          <td class="text-center"><?php echo htmlspecialchars($rowpo['tanggal']); ?></td>
                          <td class="text-end"><?php echo htmlspecialchars($rowpo['cash_done']); ?></td>
                          <td class="text-end"><?php echo htmlspecialchars($rowpo['cash_hutang']); ?></td>
                          <td class="text-end"><?php echo htmlspecialchars($rowpo['invoice_done']); ?></td>
                          <td class="text-end"><?php echo htmlspecialchars($rowpo['invoice_hutang']); ?></td>
                          <td class="text-end fw-bold"><?php echo htmlspecialchars($rowpo['total']); ?></td>
                        </tr>
                        <?php endforeach; ?>
                        <?php endif; ?>
                      </tbody>
                    </table>
                    
                    <?php if (!empty($rincianpo)): ?>
                    <div class="row mt-3">
                      <div class="col-md-8">
                        <strong>GRAND TOTAL:</strong>
                      </div>
                      <div class="col-md-4 text-end">
                        <strong class="text-danger">Rp <?php echo number_format($sum_po, 0, ',', '.'); ?></strong>
                      </div>
                    </div>
                    <?php endif; ?>
                  </div>
                </div>
              </div>
            </div>

            <!-- Section 2: Pengeluaran Produk -->
            <div class="tab-pane fade" id="section2" role="tabpanel" aria-labelledby="section2-tab">
              <div class="mb-4">
                <?php echo count($rincianbahan) > 0 ? $rincianbahan[0]['nama'] : "Tidak ada data rincian pembelian bahan"; ?>
                <div>
                  <div>
                    <table class="table table-hover">
                      <thead class="table-dark">
                        <tr>
                          <th class="text-center" width="5%">NO</th>
                          <th class="text-center" width="80%">TANGGAL</th>
                          <th class="text-center" width="15%">TOTAL</th>
                        </tr>
                      </thead>
                      <tbody>
                        <?php if (empty($rincianbahan)): ?>
                        <tr>
                          <td colspan="3" class="text-center text-muted py-3">Tidak ada data</td>
                        </tr>
                        <?php else: ?>
                        <?php $no_bahan = 1; foreach ($rincianbahan as $rowbahan): ?>
                        <tr>
                          <td class="text-center"><?php echo $no_bahan++; ?></td>
                          <td class="text-center"><?php echo htmlspecialchars($rowbahan['tgl_release']); ?></td>
                          <td class="text-end fw-bold"><?php echo htmlspecialchars($rowbahan['profit']); ?></td>
                        </tr>
                        <?php endforeach; ?>
                        <?php endif; ?>
                      </tbody>
                    </table>
                    
                    <?php if (!empty($rincianbahan)): ?>
                    <div class="row mt-3">
                      <div class="col-md-8">
                        <strong>GRAND TOTAL:</strong>
                      </div>
                      <div class="col-md-4 text-end">
                        <strong class="text-danger">Rp <?php echo number_format($sum_bahan, 0, ',', '.'); ?></strong>
                      </div>
                    </div>
                    <?php endif; ?>
                  </div>
                </div>
              </div>
            </div>

            <!-- Section 3: Penjualan Bruto -->
            <div class="tab-pane fade" id="section3" role="tabpanel" aria-labelledby="section3-tab">
              <div class="mb-4">
                <?php echo count($rincianpenjualanb) > 0 ? $rincianpenjualanb[0]['nama'] : "Tidak ada data penjualan"; ?>
                <div>
                  <div>
                    <table class="table table-hover">
                      <thead class="table-dark">
                        <tr>
                          <th class="text-center" width="5%">NO</th>
                          <th class="text-center" width="80%">TANGGAL</th>
                          <th class="text-center" width="15%">TOTAL</th>
                        </tr>
                      </thead>
                      <tbody>
                        <?php if (empty($rincianpenjualanb)): ?>
                        <tr>
                          <td colspan="3" class="text-center text-muted py-3">Tidak ada data</td>
                        </tr>
                        <?php else: ?>
                        <?php $no_penjualanb = 1; foreach ($rincianpenjualanb as $rowpenjualanb): ?>
                        <tr>
                          <td class="text-center"><?php echo $no_penjualanb++; ?></td>
                          <td class="text-center"><?php echo htmlspecialchars($rowpenjualanb['tanggal_payment']); ?></td>
                          <td class="text-end fw-bold"><?php echo htmlspecialchars($rowpenjualanb['total']); ?></td>
                        </tr>
                        <?php endforeach; ?>
                        <?php endif; ?>
                      </tbody>
                    </table>
                    
                    <?php if (!empty($rincianpenjualanb)): ?>
                    <div class="row mt-3">
                      <div class="col-md-8">
                        <strong>GRAND TOTAL:</strong>
                      </div>
                      <div class="col-md-4 text-end">
                        <strong class="text-success">Rp <?php echo number_format($sum_penjualanb, 0, ',', '.'); ?></strong>
                      </div>
                    </div>
                    <?php endif; ?>
                  </div>
                </div>
              </div>
            </div>

            <!-- Section 4: Penjualan Neto -->
            <div class="tab-pane fade" id="section4" role="tabpanel" aria-labelledby="section4-tab">
              <div class="mb-4">
                <?php echo count($rincianpenjualan2) > 0 ? $rincianpenjualan2[0]['nama'] : "Tidak ada data penjualan"; ?>
                <div>
                  <div >
                    <table class="table table-hover">
                      <thead class="table-dark">
                        <tr>
                          <th class="text-center" width="5%">NO</th>
                          <th class="text-center" width="80%">TANGGAL</th>
                          <th class="text-center" width="15%">TOTAL</th>
                        </tr>
                      </thead>
                      <tbody>
                        <?php if (empty($rincianpenjualan2)): ?>
                        <tr>
                          <td colspan="3" class="text-center text-muted py-3">Tidak ada data</td>
                        </tr>
                        <?php else: ?>
                        <?php $no_penjualan2 = 1; foreach ($rincianpenjualan2 as $rowpenjualan2): ?>
                        <tr>
                          <td class="text-center"><?php echo $no_penjualan2++; ?></td>
                          <td class="text-center"><?php echo htmlspecialchars($rowpenjualan2['tanggal_payment']); ?></td>
                          <td class="text-end fw-bold"><?php echo htmlspecialchars($rowpenjualan2['total']); ?></td>
                        </tr>
                        <?php endforeach; ?>
                        <?php endif; ?>
                      </tbody>
                    </table>
                    
                    <?php if (!empty($rincianpenjualan2)): ?>
                    <div class="row mt-3">
                      <div class="col-md-8">
                        <strong>GRAND TOTAL:</strong>
                      </div>
                      <div class="col-md-4 text-end">
                        <strong class="text-success">Rp <?php echo number_format($sum_penjualan2, 0, ',', '.'); ?></strong>
                      </div>
                    </div>
                    <?php endif; ?>
                  </div>
                </div>
              </div>
            </div>

            <!-- Summary -->
            <div class="tab-pane fade" id="summary" role="tabpanel" aria-labelledby="summary-tab">
              <div class="card mb-4 border-primary">
                <div class="card-header bg-primary text-white">
                  <h5>RINGKASAN TOTAL</h5>
                </div>
                <div class="card-body">
                  <div class="row">
                    <div class="col-md-6">
                      <h6 class="text-danger">PENGELUARAN</h6>
                      <p>Pembelian Bahan (PO): <strong>Rp <?php echo number_format($sum_po, 0, ',', '.'); ?></strong></p>
                      <p>Produk Bahan Terpakai: <strong>Rp <?php echo number_format($sum_bahan, 0, ',', '.'); ?></strong></p>
                      <hr>
                      <p class="fw-bold text-danger">Total Pengeluaran: Rp <?php echo number_format($sum_po + $sum_bahan, 0, ',', '.'); ?></p>
                    </div>
                    <div class="col-md-6">
                      <h6 class="text-success">PENJUALAN</h6>
                      <p>Penjualan Bruto: <strong>Rp <?php echo number_format($sum_penjualanb, 0, ',', '.'); ?></strong></p>
                      <p>Penjualan Neto: <strong>Rp <?php echo number_format($sum_penjualan2, 0, ',', '.'); ?></strong></p>
                      <hr>
                      <p class="fw-bold text-success">Total Penjualan: Rp <?php echo number_format($sum_penjualanb + $sum_penjualan2, 0, ',', '.'); ?></p>
                    </div>
                  </div>
                  <hr>
                  <div class="text-center">
                    <?php 
                    $total_pengeluaran = $sum_po + $sum_bahan;
                    $total_penjualan = $sum_penjualanb + $sum_penjualan2;
                    $net_profit = $total_penjualan - $total_pengeluaran;
                    ?>
                    <h4 class="<?php echo $net_profit >= 0 ? 'text-success' : 'text-danger'; ?>">
                      NET PROFIT: Rp <?php echo number_format($net_profit, 0, ',', '.'); ?>
                    </h4>
                  </div>
                </div>
              </div>
            </div>
          </div>

          <small class="text-muted">Export time: <?php echo date("d F Y H:i"); ?></small>
        </main>
      </div>
    </div>

    <script src="../js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
    <script>
      // Initialize date pickers
      flatpickr("#tgl_start", {
        dateFormat: "Y-m-d",
        maxDate: "today",
        defaultDate: "<?php echo $tgl_start; ?>",
        locale: {
          firstDayOfWeek: 1
        }
      });
      
      flatpickr("#tgl_end", {
        dateFormat: "Y-m-d",
        maxDate: "today", 
        defaultDate: "<?php echo $tgl_end; ?>",
        locale: {
          firstDayOfWeek: 1
        }
      });
    </script>
  </body>
</html>