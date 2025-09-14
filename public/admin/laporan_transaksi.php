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

// Get date range for Per Kasir tab
$tanggal_awal = isset($_GET['tanggal_awal']) ? $_GET['tanggal_awal'] : date('Y-m-d');
$tanggal_akhir = isset($_GET['tanggal_akhir']) ? $_GET['tanggal_akhir'] : date('Y-m-d');
$selected_kasir = isset($_GET['kasir']) ? $_GET['kasir'] : '';

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

// Query for Transfer transactions
$transfer_sql = "
    SELECT
        proses_edc.id_tx_bank,
        proses_edc.kode_payment,
        CASE
            WHEN proses_edc.transfer_or_edc = 0 THEN CONCAT('TRANSFER - ', nama_bank, ' A.N ', COALESCE(nama_pengirim, 'NONE'))
            WHEN proses_edc.transfer_or_edc = 1 THEN CONCAT('EDC - ', nama_bank, ' A.N ', COALESCE(nama_pengirim, 'NONE'))
        END AS bank,
        nominal_transfer,
        proses_edc.tanggal_transfer,
        proses_edc.no_referensi,
        proses_edc.img_ss,
        proses_edc.status_pemeriksaan,
        tgl_pemeriksaan
    FROM
        proses_edc
    WHERE
        DATE(tanggal_transfer) = ?
    ORDER BY proses_edc.tanggal_transfer DESC
";

$transfer_stmt = $conn->prepare($transfer_sql);
$transfer_stmt->bind_param("s", $selected_date);
$transfer_stmt->execute();
$transfer_result = $transfer_stmt->get_result();
$transfer_data = $transfer_result->fetch_all(MYSQLI_ASSOC);

// Calculate total for Transfer
$total_transfer = 0;
foreach ($transfer_data as $row) {
    $total_transfer += $row['nominal_transfer'];
}

// Query for QRIS transactions
$qris_sql = "
    SELECT
        proses_pembayaran.kode_payment,
        DATE_FORMAT(proses_pembayaran.tanggal_payment,'%H:%i') AS jam,
        CASE
            WHEN proses_pembayaran.`status` = 1 THEN 'SETTLEMENT'
            WHEN proses_pembayaran.`status` = 0 THEN 'PENDING'
        END AS status_bayar,
        pegawai.nama_lengkap as kasir,
        FORMAT(proses_pembayaran.jumlah_uang, 0) AS nominal,
        DATE_FORMAT(proses_pembayaran.update_status,'%d %M %Y %H:%i') AS waktu_dibayar
    FROM
        proses_pembayaran
    INNER JOIN pegawai ON proses_pembayaran.id_user = pegawai.id_user
    INNER JOIN metode_pembayaran ON proses_pembayaran.id_bayar = metode_pembayaran.id_bayar
    WHERE
        metode_pembayaran.kategori = 'QRIS' AND
        DATE(tanggal_payment) = ?
    ORDER BY proses_pembayaran.tanggal_payment DESC
";

$qris_stmt = $conn->prepare($qris_sql);
$qris_stmt->bind_param("s", $selected_date);
$qris_stmt->execute();
$qris_result = $qris_stmt->get_result();
$qris_data = $qris_result->fetch_all(MYSQLI_ASSOC);

// Calculate total for QRIS
$total_qris = 0;
foreach ($qris_data as $row) {
    $amount = str_replace(',', '', $row['nominal']);
    $total_qris += (int)$amount;
}

// Query to get list of all active cashiers - simplified as per gemini.md line 44
$kasir_list_sql = "
    SELECT
        pegawai.id_user,
        pegawai.nama_lengkap
    FROM
        pegawai
    WHERE 
        jabatan = 'Kasir' AND 
        aktif = 1 
    ORDER BY 
        nama_lengkap ASC
";

$kasir_list_stmt = $conn->prepare($kasir_list_sql);
$kasir_list_stmt->execute();
$kasir_list_result = $kasir_list_stmt->get_result();
$kasir_list = $kasir_list_result->fetch_all(MYSQLI_ASSOC);

// Query for Per Kasir data
$kasir_data = [];
$total_kasir = 0;
if (!empty($selected_kasir)) {
    $kasir_sql = "
        SELECT 
            state_open_closing.id_open, 
            DATE_FORMAT(tanggal_open, '%d/%m/%Y') as tanggal_open,
            (state_open_closing.first_cash_drawer - state_open_closing.manual_total_cash) AS cash_awal,
            state_open_closing.total_qris AS qris,
            state_open_closing.manual_total_bank AS transfer,
            state_open_closing.manual_total_cash AS cash,
            (state_open_closing.total_qris + state_open_closing.manual_total_bank + state_open_closing.manual_total_cash) + 
            (state_open_closing.first_cash_drawer - state_open_closing.manual_total_cash) AS grand_total,
            COALESCE(SUM(proses_pembayaran.total_diskon), 0) AS total_diskon,
            state_open_closing.id_user,
            pegawai.nama_lengkap AS kasir
        FROM
            state_open_closing
        LEFT JOIN proses_pembayaran ON state_open_closing.id_user = proses_pembayaran.id_user
            AND DATE(tanggal_open) BETWEEN ? AND ?
        INNER JOIN pegawai ON state_open_closing.id_user = pegawai.id_user
        WHERE
            DATE(tanggal_open) BETWEEN ? AND ?
            AND state_open_closing.id_user = ?
        GROUP BY state_open_closing.id_open
        ORDER BY tanggal_open DESC
    ";
    
    $kasir_stmt = $conn->prepare($kasir_sql);
    $kasir_stmt->bind_param("sssss", $tanggal_awal, $tanggal_akhir, $tanggal_awal, $tanggal_akhir, $selected_kasir);
    $kasir_stmt->execute();
    $kasir_result = $kasir_stmt->get_result();
    $kasir_data = $kasir_result->fetch_all(MYSQLI_ASSOC);
    
    // Calculate total for Per Kasir
    foreach ($kasir_data as $row) {
        $total_kasir += $row['grand_total'];
    }
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
    <style>
      .loading-spinner {
        display: inline-block;
        width: 16px;
        height: 16px;
        border: 2px solid rgba(0,0,0,.1);
        border-radius: 50%;
        border-top-color: #0d6efd;
        animation: spin 0.6s linear infinite;
      }
      @keyframes spin {
        to { transform: rotate(360deg); }
      }
    </style>
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
                <i class="bi bi-person-badge"></i> Per Kasir
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
              <div class="table-responsive shadow-sm">
                <table class="table table-hover align-middle">
                  <thead class="table-dark">
                    <tr>
                      <th class="text-center">NO</th>
                      <th>KODE PAYMENT</th>
                      <th>BANK/METODE</th>
                      <th class="text-center">TANGGAL TRF</th>
                      <th class="text-center">STATUS</th>
                      <th class="text-end">NOMINAL</th>
                    </tr>
                  </thead>
                  <tbody>
                    <?php if (empty($transfer_data)): ?>
                    <tr>
                      <td colspan="6" class="text-center text-muted py-5">
                        <i class="bi bi-inbox display-1 text-muted d-block mb-3"></i>
                        <span class="fs-5">Tidak ada transaksi transfer untuk tanggal <?php echo date('d F Y', strtotime($selected_date)); ?></span>
                      </td>
                    </tr>
                    <?php else: ?>
                    <?php 
                    $no = 1;
                    foreach ($transfer_data as $row): 
                    ?>
                    <tr>
                      <td class="text-center fw-bold"><?php echo $no++; ?></td>
                      <td class="fw-semibold"><?php echo htmlspecialchars($row['kode_payment']); ?></td>
                      <td><?php echo htmlspecialchars($row['bank']); ?></td>
                      <td class="text-center"><?php echo date('d/m/Y H:i', strtotime($row['tanggal_transfer'])); ?></td>
                      <td class="text-center">
                        <a href="#" class="text-decoration-none" onclick="showTransferDetail(
                          '<?php echo addslashes($row['tanggal_transfer']); ?>',
                          '<?php echo addslashes($row['no_referensi'] ?? '-'); ?>',
                          '<?php echo addslashes($row['img_ss'] ?? ''); ?>',
                          '<?php echo $row['status_pemeriksaan']; ?>',
                          '<?php echo addslashes($row['tgl_pemeriksaan'] ?? ''); ?>'
                        )" data-bs-toggle="modal" data-bs-target="#transferDetailModal">
                          <?php 
                          if ($row['status_pemeriksaan'] == 1): ?>
                            <span class="badge bg-success fs-6 px-3 py-2" style="cursor: pointer;">
                              <i class="bi bi-check-circle me-1"></i>DITERIMA
                            </span>
                          <?php elseif ($row['status_pemeriksaan'] == 2): ?>
                            <span class="badge bg-danger fs-6 px-3 py-2" style="cursor: pointer;">
                              <i class="bi bi-x-circle me-1"></i>PALSU
                            </span>
                          <?php else: ?>
                            <span class="badge bg-warning fs-6 px-3 py-2" style="cursor: pointer;">
                              <i class="bi bi-clock me-1"></i>BELUM DITERIMA
                            </span>
                          <?php endif; ?>
                        </a>
                      </td>
                      <td class="text-end fw-bold text-primary">Rp <?php echo number_format($row['nominal_transfer'], 0, ',', '.'); ?></td>
                    </tr>
                    <?php endforeach; ?>
                    <?php endif; ?>
                  </tbody>
                </table>
              </div>
              
              <!-- Total Summary -->
              <?php if (!empty($transfer_data)): ?>
              <div class="mt-3 p-3 bg-light rounded border">
                <div class="row align-items-center">
                  <div class="col-md-8">
                    <span class="text-muted">Total Transaksi Transfer/EDC (<?php echo count($transfer_data); ?> transaksi)</span>
                  </div>
                  <div class="col-md-4 text-end">
                    <span class="fw-bold fs-4 text-success">Rp <?php echo number_format($total_transfer, 0, ',', '.'); ?></span>
                  </div>
                </div>
              </div>
              <?php endif; ?>
            </div>

            <!-- QRIS Tab -->
            <div class="tab-pane fade" id="qris" role="tabpanel">
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
                    <?php if (empty($qris_data)): ?>
                    <tr>
                      <td colspan="7" class="text-center text-muted py-5">
                        <i class="bi bi-inbox display-1 text-muted d-block mb-3"></i>
                        <span class="fs-5">Tidak ada transaksi QRIS untuk tanggal <?php echo date('d F Y', strtotime($selected_date)); ?></span>
                      </td>
                    </tr>
                    <?php else: ?>
                    <?php 
                    $no = 1;
                    foreach ($qris_data as $row): 
                    ?>
                    <tr>
                      <td class="text-center fw-bold"><?php echo $no++; ?></td>
                      <td class="fw-semibold"><?php echo htmlspecialchars($row['kode_payment']); ?></td>
                      <td class="text-center"><?php echo htmlspecialchars($row['jam']); ?></td>
                      <td><?php echo htmlspecialchars($row['kasir']); ?></td>
                      <td class="text-center">
                        <?php if ($row['status_bayar'] == 'SETTLEMENT'): ?>
                          <span class="badge bg-success fs-6 px-3 py-2">
                            <i class="bi bi-check-circle me-1"></i>SETTLEMENT
                          </span>
                        <?php else: ?>
                          <span class="badge bg-warning fs-6 px-3 py-2">
                            <i class="bi bi-clock me-1"></i>PENDING
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
              <?php if (!empty($qris_data)): ?>
              <div class="mt-3 p-3 bg-light rounded border">
                <div class="row align-items-center">
                  <div class="col-md-8">
                    <span class="text-muted">Total Transaksi QRIS (<?php echo count($qris_data); ?> transaksi)</span>
                  </div>
                  <div class="col-md-4 text-end">
                    <span class="fw-bold fs-4 text-success">Rp <?php echo number_format($total_qris, 0, ',', '.'); ?></span>
                  </div>
                </div>
              </div>
              <?php endif; ?>
            </div>

            <!-- Per Kasir Tab -->
            <div class="tab-pane fade" id="kasir" role="tabpanel">
              <!-- Filter for Per Kasir -->
              <div class="mb-4 p-3 bg-light rounded">
                <form method="GET" class="row g-3 align-items-end" id="kasirFilterForm">
                  <input type="hidden" name="tab" value="kasir">
                  <div class="col-md-3">
                    <label for="tanggal_awal" class="form-label">Tanggal Awal</label>
                    <input type="date" class="form-control" id="tanggal_awal" name="tanggal_awal" 
                           value="<?php echo htmlspecialchars($tanggal_awal); ?>">
                  </div>
                  <div class="col-md-3">
                    <label for="tanggal_akhir" class="form-label">Tanggal Akhir</label>
                    <input type="date" class="form-control" id="tanggal_akhir" name="tanggal_akhir" 
                           value="<?php echo htmlspecialchars($tanggal_akhir); ?>">
                  </div>
                  <div class="col-md-4">
                    <label for="kasir" class="form-label">Pilih Kasir</label>
                    <select class="form-select" id="kasir" name="kasir">
                      <option value="">-- Pilih Kasir --</option>
                      <?php foreach ($kasir_list as $kasir): ?>
                      <option value="<?php echo htmlspecialchars($kasir['id_user']); ?>" 
                              <?php echo ($selected_kasir == $kasir['id_user']) ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($kasir['nama_lengkap']); ?>
                      </option>
                      <?php endforeach; ?>
                    </select>
                  </div>
                  <div class="col-md-2">
                    <button type="submit" class="btn btn-primary">
                      <i class="bi bi-search"></i> Tampilkan
                    </button>
                  </div>
                </form>
              </div>
              
              <!-- Table for Per Kasir -->
              <?php if (!empty($selected_kasir)): ?>
              <div class="table-responsive shadow-sm">
                <table class="table table-hover align-middle">
                  <thead class="table-dark">
                    <tr>
                      <th class="text-center">No</th>
                      <th class="text-center">Tanggal</th>
                      <th>Kasir</th>
                      <th class="text-end">Cash Awal</th>
                      <th class="text-end">QRIS</th>
                      <th class="text-end">Transfer</th>
                      <th class="text-end">Cash</th>
                      <th class="text-end">Total Diskon</th>
                      <th class="text-end">Grand Total</th>
                    </tr>
                  </thead>
                  <tbody>
                    <?php if (empty($kasir_data)): ?>
                    <tr>
                      <td colspan="9" class="text-center text-muted py-5">
                        <i class="bi bi-inbox display-1 text-muted d-block mb-3"></i>
                        <span class="fs-5">Tidak ada data untuk kasir yang dipilih pada periode tersebut</span>
                      </td>
                    </tr>
                    <?php else: ?>
                    <?php 
                    $no = 1;
                    foreach ($kasir_data as $row): 
                    ?>
                    <tr>
                      <td class="text-center fw-bold"><?php echo $no++; ?></td>
                      <td class="text-center">
                        <a href="#" class="text-decoration-none" onclick="showKasirDetail('<?php echo $tanggal_awal; ?>', '<?php echo $tanggal_akhir; ?>', '<?php echo $row['id_user']; ?>', '<?php echo htmlspecialchars($row['kasir']); ?>')" data-bs-toggle="modal" data-bs-target="#kasirDetailModal">
                          <span class="text-primary" style="cursor: pointer;">
                            <i class="bi bi-calendar3"></i> <?php echo htmlspecialchars($row['tanggal_open']); ?>
                          </span>
                        </a>
                      </td>
                      <td><?php echo htmlspecialchars($row['kasir']); ?></td>
                      <td class="text-end">Rp <?php echo number_format($row['cash_awal'], 0, ',', '.'); ?></td>
                      <td class="text-end">Rp <?php echo number_format($row['qris'], 0, ',', '.'); ?></td>
                      <td class="text-end">Rp <?php echo number_format($row['transfer'], 0, ',', '.'); ?></td>
                      <td class="text-end">Rp <?php echo number_format($row['cash'], 0, ',', '.'); ?></td>
                      <td class="text-end text-danger">Rp <?php echo number_format($row['total_diskon'], 0, ',', '.'); ?></td>
                      <td class="text-end fw-bold text-primary">Rp <?php echo number_format($row['grand_total'], 0, ',', '.'); ?></td>
                    </tr>
                    <?php endforeach; ?>
                    <?php endif; ?>
                  </tbody>
                </table>
              </div>
              
              <!-- Total Summary -->
              <?php if (!empty($kasir_data)): ?>
              <div class="mt-3 p-3 bg-light rounded border">
                <div class="row align-items-center">
                  <div class="col-md-8">
                    <span class="text-muted">Total Transaksi Per Kasir (<?php echo count($kasir_data); ?> periode)</span>
                  </div>
                  <div class="col-md-4 text-end">
                    <span class="fw-bold fs-4 text-success">Rp <?php echo number_format($total_kasir, 0, ',', '.'); ?></span>
                  </div>
                </div>
              </div>
              <?php endif; ?>
              <?php else: ?>
              <div class="text-center py-5">
                <i class="bi bi-person-badge display-1 text-muted"></i>
                <p class="mt-3">Silakan pilih rentang tanggal dan kasir untuk melihat laporan</p>
              </div>
              <?php endif; ?>
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

    <!-- Kasir Detail Modal -->
    <div class="modal fade" id="kasirDetailModal" tabindex="-1">
      <div class="modal-dialog modal-xl">
        <div class="modal-content">
          <div class="modal-header">
            <h5 class="modal-title">Detail Transaksi Kasir - <span id="kasir-name"></span></h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
          </div>
          <div class="modal-body">
            <div class="table-responsive">
              <table class="table table-hover table-sm">
                <thead class="table-dark">
                  <tr>
                    <th>No</th>
                    <th>Kode Payment</th>
                    <th>Tanggal</th>
                    <th>Jam</th>
                    <th>Kategori</th>
                    <th>Diskon</th>
                    <th class="text-end">Total Diskon</th>
                    <th class="text-end">Total Kotor</th>
                    <th class="text-end">Total Bersih</th>
                  </tr>
                </thead>
                <tbody id="kasir-detail-tbody">
                  <tr>
                    <td colspan="9" class="text-center">
                      <div class="spinner-border text-primary" role="status">
                        <span class="visually-hidden">Loading...</span>
                      </div>
                    </td>
                  </tr>
                </tbody>
              </table>
            </div>
            
            <!-- Pagination -->
            <nav aria-label="Page navigation" class="mt-3">
              <ul class="pagination justify-content-center" id="kasir-detail-pagination">
              </ul>
            </nav>
          </div>
        </div>
      </div>
    </div>
    
    <!-- Transfer Detail Modal -->
    <div class="modal fade" id="transferDetailModal" tabindex="-1">
      <div class="modal-dialog modal-lg">
        <div class="modal-content">
          <div class="modal-header">
            <h5 class="modal-title">Detail Transfer</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
          </div>
          <div class="modal-body">
            <div class="row g-3">
              <div class="col-md-6">
                <label class="form-label text-muted">Tanggal Transfer</label>
                <p class="fw-semibold" id="modal-tanggal-transfer">-</p>
              </div>
              <div class="col-md-6">
                <label class="form-label text-muted">Tanggal Pemeriksaan</label>
                <p class="fw-semibold" id="modal-tgl-pemeriksaan">-</p>
              </div>
              <div class="col-md-6">
                <label class="form-label text-muted">No. Referensi</label>
                <p class="fw-semibold" id="modal-no-referensi">-</p>
              </div>
              <div class="col-md-6">
                <label class="form-label text-muted">Status Pemeriksaan</label>
                <p id="modal-status-pemeriksaan">-</p>
              </div>
              
              <div class="col-12">
                <label class="form-label text-muted">Bukti Transfer</label>
                <div id="modal-img-container" class="text-center">
                  <p class="text-muted">Tidak ada bukti</p>
                </div>
              </div>
            </div>
          </div>
         
        </div>
      </div>
    </div>

    <script src="../js/bootstrap.bundle.min.js"></script>
    <!-- Flatpickr JS for Date Picker -->
    <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
    <script>
      document.addEventListener('DOMContentLoaded', function() {
        // Initialize date picker with Flatpickr for main date filter
        if (document.getElementById('tanggal')) {
          flatpickr("#tanggal", {
            dateFormat: "Y-m-d",
            maxDate: "today",
            defaultDate: "<?php echo $selected_date; ?>",
            locale: {
              firstDayOfWeek: 1
            }
          });
        }
        
        // Initialize date pickers for Per Kasir tab - simplified without onChange events
        if (document.getElementById('tanggal_awal')) {
          flatpickr("#tanggal_awal", {
            dateFormat: "Y-m-d",
            maxDate: "today",
            defaultDate: "<?php echo $tanggal_awal; ?>",
            locale: {
              firstDayOfWeek: 1
            }
          });
        }
        
        if (document.getElementById('tanggal_akhir')) {
          flatpickr("#tanggal_akhir", {
            dateFormat: "Y-m-d",
            maxDate: "today",
            defaultDate: "<?php echo $tanggal_akhir; ?>",
            locale: {
              firstDayOfWeek: 1
            }
          });
        }
      
        // Activate the correct tab based on URL parameter
        const urlParams = new URLSearchParams(window.location.search);
        const activeTab = urlParams.get('tab');
        
        // Use setTimeout to ensure Bootstrap is fully loaded
        setTimeout(function() {
          if (activeTab === 'kasir') {
            const kasirTabEl = document.getElementById('kasir-tab');
            if (kasirTabEl) {
              const kasirTab = new bootstrap.Tab(kasirTabEl);
              kasirTab.show();
            }
          } else if (activeTab === 'transfer') {
            const transferTabEl = document.getElementById('transfer-tab');
            if (transferTabEl) {
              const transferTab = new bootstrap.Tab(transferTabEl);
              transferTab.show();
            }
          } else if (activeTab === 'qris') {
            const qrisTabEl = document.getElementById('qris-tab');
            if (qrisTabEl) {
              const qrisTab = new bootstrap.Tab(qrisTabEl);
              qrisTab.show();
            }
          } else if (activeTab === 'semua') {
            const semuaTabEl = document.getElementById('semua-tab');
            if (semuaTabEl) {
              const semuaTab = new bootstrap.Tab(semuaTabEl);
              semuaTab.show();
            }
          }
        }, 100);
      
        // Prevent form submission on Enter key except for button
        const kasirForm = document.getElementById('kasirFilterForm');
        if (kasirForm) {
          // Only prevent Enter key submission, no validation
          kasirForm.addEventListener('keypress', function(e) {
            if (e.key === 'Enter' && e.target.tagName !== 'BUTTON') {
              e.preventDefault();
              return false;
            }
          });
        }
        
        // Handle tab clicks to maintain state
        document.querySelectorAll('[data-bs-toggle="tab"]').forEach(tab => {
          tab.addEventListener('shown.bs.tab', function (e) {
            const tabId = e.target.id.replace('-tab', '');
            const url = new URL(window.location);
            
            // Update URL parameter for active tab
            if (tabId === 'tunai') {
              url.searchParams.delete('tab');
            } else {
              url.searchParams.set('tab', tabId);
            }
            
            // For non-kasir tabs, remove kasir-specific parameters
            if (tabId !== 'kasir') {
              url.searchParams.delete('tanggal_awal');
              url.searchParams.delete('tanggal_akhir');
              url.searchParams.delete('kasir');
            }
            
            // Update URL without page reload
            window.history.replaceState({}, '', url);
          });
        });
      }); // End of DOMContentLoaded

      // Global variables for kasir detail modal
      let currentKasirPage = 1;
      let currentKasirData = {
        tanggal_awal: '',
        tanggal_akhir: '',
        id_user: '',
        kasir_name: ''
      };
      
      // Function to show kasir detail modal
      function showKasirDetail(tanggalAwal, tanggalAkhir, idUser, kasirName) {
        currentKasirData = {
          tanggal_awal: tanggalAwal,
          tanggal_akhir: tanggalAkhir,
          id_user: idUser,
          kasir_name: kasirName
        };
        currentKasirPage = 1;
        
        // Set kasir name in modal title
        document.getElementById('kasir-name').textContent = kasirName;
        
        // Load data
        loadKasirDetailData();
      }
      
      // Function to load kasir detail data
      function loadKasirDetailData() {
        const tbody = document.getElementById('kasir-detail-tbody');
        const pagination = document.getElementById('kasir-detail-pagination');
        
        // Show loading
        tbody.innerHTML = `
          <tr>
            <td colspan="9" class="text-center">
              <div class="spinner-border text-primary" role="status">
                <span class="visually-hidden">Loading...</span>
              </div>
            </td>
          </tr>
        `;
        
        // Fetch data
        fetch(`get_kasir_detail.php?tanggal_awal=${currentKasirData.tanggal_awal}&tanggal_akhir=${currentKasirData.tanggal_akhir}&id_user=${currentKasirData.id_user}&page=${currentKasirPage}`)
          .then(response => {
            if (!response.ok) {
              throw new Error('Network response was not ok');
            }
            return response.json();
          })
          .then(result => {
            // Clear tbody
            tbody.innerHTML = '';
            
            if (result.data && result.data.length > 0) {
              // Populate table
              result.data.forEach((item, index) => {
                const row = document.createElement('tr');
                row.innerHTML = `
                  <td class="text-center">${((currentKasirPage - 1) * 10) + index + 1}</td>
                  <td>${item.kode_payment}</td>
                  <td>${item.tanggal}</td>
                  <td class="text-center">${item.jam}</td>
                  <td>${item.kategori}</td>
                  <td class="text-center">${item.diskon || '-'}</td>
                  <td class="text-end">Rp ${item.total_diskon_formatted}</td>
                  <td class="text-end">Rp ${item.total_kotor_formatted}</td>
                  <td class="text-end fw-bold">Rp ${item.total_bersih_formatted}</td>
                `;
                tbody.appendChild(row);
              });
              
              // Update pagination
              updateKasirPagination(result.pagination);
            } else {
              tbody.innerHTML = `
                <tr>
                  <td colspan="9" class="text-center text-muted py-4">
                    <i class="bi bi-inbox display-4 d-block mb-2"></i>
                    Tidak ada data transaksi
                  </td>
                </tr>
              `;
              pagination.innerHTML = '';
            }
          })
          .catch(error => {
            console.error('Error loading kasir detail:', error);
            tbody.innerHTML = `
              <tr>
                <td colspan="9" class="text-center text-danger">
                  <i class="bi bi-exclamation-triangle display-4 d-block mb-2"></i>
                  Error loading data: ${error.message}
                </td>
              </tr>
            `;
          });
      }
      
      // Function to update pagination
      function updateKasirPagination(paginationData) {
        const pagination = document.getElementById('kasir-detail-pagination');
        pagination.innerHTML = '';
        
        if (paginationData.total_pages <= 1) {
          return;
        }
        
        // Previous button
        const prevLi = document.createElement('li');
        prevLi.className = `page-item ${paginationData.current_page === 1 ? 'disabled' : ''}`;
        prevLi.innerHTML = `<a class="page-link" href="#" onclick="goToKasirPage(${paginationData.current_page - 1}); return false;">Previous</a>`;
        pagination.appendChild(prevLi);
        
        // Page numbers
        const maxVisiblePages = 5;
        let startPage = Math.max(1, paginationData.current_page - Math.floor(maxVisiblePages / 2));
        let endPage = Math.min(paginationData.total_pages, startPage + maxVisiblePages - 1);
        
        if (endPage - startPage < maxVisiblePages - 1) {
          startPage = Math.max(1, endPage - maxVisiblePages + 1);
        }
        
        if (startPage > 1) {
          const firstLi = document.createElement('li');
          firstLi.className = 'page-item';
          firstLi.innerHTML = `<a class="page-link" href="#" onclick="goToKasirPage(1); return false;">1</a>`;
          pagination.appendChild(firstLi);
          
          if (startPage > 2) {
            const dotsLi = document.createElement('li');
            dotsLi.className = 'page-item disabled';
            dotsLi.innerHTML = `<span class="page-link">...</span>`;
            pagination.appendChild(dotsLi);
          }
        }
        
        for (let i = startPage; i <= endPage; i++) {
          const pageLi = document.createElement('li');
          pageLi.className = `page-item ${i === paginationData.current_page ? 'active' : ''}`;
          pageLi.innerHTML = `<a class="page-link" href="#" onclick="goToKasirPage(${i}); return false;">${i}</a>`;
          pagination.appendChild(pageLi);
        }
        
        if (endPage < paginationData.total_pages) {
          if (endPage < paginationData.total_pages - 1) {
            const dotsLi = document.createElement('li');
            dotsLi.className = 'page-item disabled';
            dotsLi.innerHTML = `<span class="page-link">...</span>`;
            pagination.appendChild(dotsLi);
          }
          
          const lastLi = document.createElement('li');
          lastLi.className = 'page-item';
          lastLi.innerHTML = `<a class="page-link" href="#" onclick="goToKasirPage(${paginationData.total_pages}); return false;">${paginationData.total_pages}</a>`;
          pagination.appendChild(lastLi);
        }
        
        // Next button
        const nextLi = document.createElement('li');
        nextLi.className = `page-item ${paginationData.current_page === paginationData.total_pages ? 'disabled' : ''}`;
        nextLi.innerHTML = `<a class="page-link" href="#" onclick="goToKasirPage(${paginationData.current_page + 1}); return false;">Next</a>`;
        pagination.appendChild(nextLi);
      }
      
      // Function to go to specific page
      function goToKasirPage(page) {
        if (page < 1) return;
        currentKasirPage = page;
        loadKasirDetailData();
      }
      
      // Function to show transfer detail modal
      function showTransferDetail(tanggalTransfer, noReferensi, imgSs, statusPemeriksaan, tglPemeriksaan) {
        // Format tanggal transfer
        if (tanggalTransfer) {
          const date = new Date(tanggalTransfer);
          const options = { 
            year: 'numeric', 
            month: 'long', 
            day: 'numeric', 
            hour: '2-digit', 
            minute: '2-digit' 
          };
          document.getElementById('modal-tanggal-transfer').textContent = date.toLocaleDateString('id-ID', options);
        } else {
          document.getElementById('modal-tanggal-transfer').textContent = '-';
        }
        
        // Set no referensi
        document.getElementById('modal-no-referensi').textContent = noReferensi || '-';
        
        // Set status pemeriksaan with badge
        let statusHtml = '';
        if (statusPemeriksaan == '1') {
          statusHtml = '<span class="badge bg-success fs-6 px-3 py-2"><i class="bi bi-check-circle me-1"></i>DITERIMA</span>';
        } else if (statusPemeriksaan == '2') {
          statusHtml = '<span class="badge bg-danger fs-6 px-3 py-2"><i class="bi bi-x-circle me-1"></i>PALSU</span>';
        } else {
          statusHtml = '<span class="badge bg-warning fs-6 px-3 py-2"><i class="bi bi-clock me-1"></i>BELUM DITERIMA</span>';
        }
        document.getElementById('modal-status-pemeriksaan').innerHTML = statusHtml;
        
        // Set tanggal pemeriksaan
        if (tglPemeriksaan && tglPemeriksaan !== '') {
          const datePeriksa = new Date(tglPemeriksaan);
          const options = { 
            year: 'numeric', 
            month: 'long', 
            day: 'numeric', 
            hour: '2-digit', 
            minute: '2-digit' 
          };
          document.getElementById('modal-tgl-pemeriksaan').textContent = datePeriksa.toLocaleDateString('id-ID', options);
        } else {
          document.getElementById('modal-tgl-pemeriksaan').textContent = 'Belum diperiksa';
        }
        
        // Set image
        // Set image
const imgContainer = document.getElementById('modal-img-container');
if (imgSs && imgSs !== '') {
  imgContainer.innerHTML = `
    <a href="../struk/images/${imgSs}" target="_blank" class="d-block">
      <img src="../struk/images/${imgSs}" class="img-fluid rounded border" style="max-height: 400px;" alt="Bukti Transfer">
    </a>
    <p class="mt-2 text-muted small">Klik gambar untuk melihat ukuran penuh</p>
  `;
} else {
  imgContainer.innerHTML = '<p class="text-muted">Tidak ada bukti transfer</p>';
}
      }
    </script>
  </body>
</html>
