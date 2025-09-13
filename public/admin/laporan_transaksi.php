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

// Get selected date
$selected_date = isset($_GET['tanggal']) ? $_GET['tanggal'] : date('Y-m-d');

// Get active tab
$active_tab = isset($_GET['tab']) ? $_GET['tab'] : 'tunai';

// Query for Tunai (Cash) transactions
$tunai_sql = "
    SELECT
        proses_pembayaran.kode_payment,
        DATE_FORMAT(proses_pembayaran.tanggal_payment,'%H:%i') AS jam,
        DATE_FORMAT(proses_pembayaran.update_status,'%d %M %Y %H:%i') AS waktu_dibayar,
        pegawai.nama_lengkap as kasir,
        FORMAT(proses_pembayaran.jumlah_uang, 0) AS nominal,
        CASE
            WHEN proses_pembayaran.`status` = 1 THEN 'DIBAYAR'
            WHEN proses_pembayaran.`status` = 0 THEN 'BELUM DIBAYAR'
        END AS status_bayar
    FROM
        proses_pembayaran
    INNER JOIN pegawai ON proses_pembayaran.id_user = pegawai.id_user
    INNER JOIN metode_pembayaran ON proses_pembayaran.id_bayar = metode_pembayaran.id_bayar
    WHERE
        metode_pembayaran.kategori = 'Tunai' AND
        DATE(tanggal_payment) = ?
    ORDER BY proses_pembayaran.tanggal_payment DESC
";

$tunai_stmt = $conn->prepare($tunai_sql);
$tunai_stmt->bind_param("s", $selected_date);
$tunai_stmt->execute();
$tunai_result = $tunai_stmt->get_result();
$tunai_data = $tunai_result->fetch_all(MYSQLI_ASSOC);

// Calculate total for Tunai
$total_tunai = 0;
foreach ($tunai_data as $row) {
    $amount = str_replace(',', '', $row['nominal']);
    $total_tunai += (int)$amount;
}
?>

<!doctype html>
<html lang="en">
  <head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Laporan Transaksi - Admin Dashboard</title>
    <link href="../css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css" rel="stylesheet">
    <link href="../css/admin.css" rel="stylesheet">
    <!-- Flatpickr CSS for Date Picker -->
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
                    <li class="nav-item"><a class="nav-link active" href="laporan_transaksi.php"><i class="bi bi-list-ul"></i> Transaksi</a></li>
                    <li class="nav-item"><a class="nav-link" href="laporan_pengeluaran.php"><i class="bi bi-bar-chart"></i> Pengeluaran vs Penjualan</a></li>
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
            <h1 class="h2">Laporan Transaksi</h1>
          </div>

          <!-- Date Filter -->
          <div class="mb-3">
          
              <form method="GET" class="row g-3 align-items-end">
                <div class="col-md-4">
                  <label for="tanggal" class="form-label">Pilih Tanggal</label>
                  <input type="date" class="form-control" id="tanggal" name="tanggal" 
                         value="<?php echo htmlspecialchars($selected_date); ?>" required>
                </div>
                <div class="col-md-2">
                  <button type="submit" class="btn btn-primary">
                    <i class="bi bi-search"></i> Filter
                  </button>
                </div>
               
              </form>
           
          </div>

          <!-- Tabs -->
          <ul class="nav nav-tabs mb-3" id="transactionTabs" role="tablist">
            <li class="nav-item" role="presentation">
              <button class="nav-link active" id="tunai-tab" data-bs-toggle="tab" data-bs-target="#tunai" type="button" role="tab">
                <i class="bi bi-cash"></i> Tunai
              </button>
            </li>
            <li class="nav-item" role="presentation">
              <button class="nav-link" id="transfer-tab" data-bs-toggle="tab" data-bs-target="#transfer" type="button" role="tab">
                <i class="bi bi-bank"></i> Transfer
              </button>
            </li>
            <li class="nav-item" role="presentation">
              <button class="nav-link" id="qris-tab" data-bs-toggle="tab" data-bs-target="#qris" type="button" role="tab">
                <i class="bi bi-qr-code"></i> QRIS
              </button>
            </li>
            <li class="nav-item" role="presentation">
              <button class="nav-link" id="kasir-tab" data-bs-toggle="tab" data-bs-target="#kasir" type="button" role="tab">
                <i class="bi bi-person-badge"></i> Kasir
              </button>
            </li>
            <li class="nav-item" role="presentation">
              <button class="nav-link" id="semua-tab" data-bs-toggle="tab" data-bs-target="#semua" type="button" role="tab">
                <i class="bi bi-list-ul"></i> Semua
              </button>
            </li>
          </ul>

          <!-- Tab Content -->
          <div class="tab-content" id="transactionTabsContent">
            <!-- Tunai Tab -->
            <div class="tab-pane fade show active" id="tunai" role="tabpanel">
              <div class="table-responsive shadow-sm">
                <table class="table table-hover align-middle">
                  <thead class="table-dark">
                    <tr>
                      <th class="text-center">No</th>
                      <th>Kode Payment</th>
                      <th class="text-center">Jam</th>
                     
                      <th>Kasir</th>
                      <th class="text-center">Status</th>
                       <th class="text-end">Nominal</th>
                    </tr>
                  </thead>
                  <tbody>
                    <?php if (empty($tunai_data)): ?>
                    <tr>
                      <td colspan="7" class="text-center text-muted py-5">
                        <i class="bi bi-inbox display-1 text-muted d-block mb-3"></i>
                        <span class="fs-5">Tidak ada transaksi tunai untuk tanggal <?php echo date('d F Y', strtotime($selected_date)); ?></span>
                      </td>
                    </tr>
                    <?php else: ?>
                    <?php 
                    $no = 1;
                    foreach ($tunai_data as $row): 
                    ?>
                    <tr>
                      <td class="text-center fw-bold"><?php echo $no++; ?></td>
                      <td class="fw-semibold"><?php echo htmlspecialchars($row['kode_payment']); ?></td>
                      <td class="text-center"><?php echo htmlspecialchars($row['jam']); ?></td>
                      
                      <td><?php echo htmlspecialchars($row['kasir']); ?></td>
                     
                      <td class="text-center">
                        <?php if ($row['status_bayar'] == 'DIBAYAR'): ?>
                          <span class="badge bg-success fs-6 px-3 py-2">
                            <i class="bi bi-check-circle me-1"></i>DIBAYAR
                          </span>
                        <?php else: ?>
                          <span class="badge bg-warning fs-6 px-3 py-2">
                            <i class="bi bi-clock me-1"></i>BELUM DIBAYAR
                          </span>
                        <?php endif; ?>
                      </td>

                       <td class="text-end fw-semibold">Rp <?php echo htmlspecialchars($row['nominal']); ?></td>
                    </tr>
                    <?php endforeach; ?>
                    <?php endif; ?>
                  </tbody>
                </table>
              </div>
              
              <!-- Total Summary -->
              <?php if (!empty($tunai_data)): ?>
              <div class="mt-3 p-3 bg-light rounded border">
                <div class="row align-items-center">
                  <div class="col-md-8">
                    <span class="text-muted">Total Transaksi Tunai (<?php echo count($tunai_data); ?> transaksi)</span>
                  </div>
                  <div class="col-md-4 text-end">
                    <span class="fw-bold fs-4 text-success">Rp <?php echo number_format($total_tunai, 0, ',', '.'); ?></span>
                  </div>
                </div>
              </div>
              <?php endif; ?>
            </div>

            <!-- Transfer Tab -->
            <div class="tab-pane fade" id="transfer" role="tabpanel">
              <div class="text-center py-5">
                <i class="bi bi-bank display-1 text-muted"></i>
                <p class="mt-3">Tab Transfer akan segera tersedia</p>
              </div>
            </div>

            <!-- QRIS Tab -->
            <div class="tab-pane fade" id="qris" role="tabpanel">
              <div class="text-center py-5">
                <i class="bi bi-qr-code display-1 text-muted"></i>
                <p class="mt-3">Tab QRIS akan segera tersedia</p>
              </div>
            </div>

            <!-- Kasir Tab -->
            <div class="tab-pane fade" id="kasir" role="tabpanel">
              <div class="text-center py-5">
                <i class="bi bi-person-badge display-1 text-muted"></i>
                <p class="mt-3">Tab Kasir akan segera tersedia</p>
              </div>
            </div>

            <!-- Semua Tab -->
            <div class="tab-pane fade" id="semua" role="tabpanel">
              <div class="text-center py-5">
                <i class="bi bi-list-ul display-1 text-muted"></i>
                <p class="mt-3">Tab Semua akan segera tersedia</p>
              </div>
            </div>
          </div>
        </main>
      </div>
    </div>

    <script src="../js/bootstrap.bundle.min.js"></script>
    <!-- Flatpickr JS for Date Picker -->
    <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
    <script>
      // Initialize date picker with Flatpickr
      flatpickr("#tanggal", {
        dateFormat: "Y-m-d",
        maxDate: "today",
        defaultDate: "<?php echo $selected_date; ?>",
        locale: {
          firstDayOfWeek: 1
        }
      });
    </script>
  </body>
</html>
