<?php
session_start();
require_once __DIR__ . '/../../config/koneksi.php';

// Check if user is logged in
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    header('Location: login.php');
    exit();
}

// Check permission - Only Admin and Kasir can access
if ($_SESSION["jabatan"] != "Admin" && $_SESSION["jabatan"] != "Kasir") {
    header("location: index.php");
    exit;
}

$message = '';
$error = '';

// Get date range from form
$tanggal_mulai = isset($_GET['tanggal_mulai']) ? $_GET['tanggal_mulai'] : date('Y-m-d');
$tanggal_selesai = isset($_GET['tanggal_selesai']) ? $_GET['tanggal_selesai'] : date('Y-m-d');

// Pagination
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 20;
$offset = ($page - 1) * $limit;

// Count total records for pagination
$count_sql = "
    SELECT COUNT(DISTINCT pesanan.id_tagihan) as total
    FROM dapur_batal
    INNER JOIN dapur_order_detail ON dapur_batal.id_order_detail = dapur_order_detail.id_order_detail
    INNER JOIN pesanan ON dapur_batal.id_pesanan = pesanan.id_pesanan
    INNER JOIN pesanan_detail ON dapur_order_detail.id_pesanan_detail = pesanan_detail.id_pesanan_detail
    INNER JOIN produk_sell ON pesanan_detail.id_produk_sell = produk_sell.id_produk_sell
    INNER JOIN meja ON pesanan.id_meja = meja.id_meja
    INNER JOIN view_produk ON produk_sell.id_produk = view_produk.id_produk
    INNER JOIN pegawai ON dapur_batal.id_user = pegawai.id_user
    WHERE DATE(dapur_batal.waktu) BETWEEN ? AND ?
";

$count_stmt = $conn->prepare($count_sql);
$count_stmt->bind_param("ss", $tanggal_mulai, $tanggal_selesai);
$count_stmt->execute();
$count_result = $count_stmt->get_result();
$total_records = $count_result->fetch_assoc()['total'];
$total_pages = ceil($total_records / $limit);

// Main query to get cancellation data
$sql = "
    SELECT
        dapur_batal.id_batal,
        MAX(dapur_batal.waktu) as waktu,
        pesanan.id_tagihan,
        meja.nomor_meja,
        pegawai.nama_lengkap,
        SUM(produk_sell.harga_jual) AS total_harga_jual,
        SUM(dapur_batal.qty) as total_item
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
    GROUP BY
        pesanan.id_tagihan, meja.nomor_meja, pegawai.nama_lengkap
    ORDER BY
        pesanan.id_tagihan DESC
    LIMIT ? OFFSET ?
";

$stmt = $conn->prepare($sql);
$stmt->bind_param("ssii", $tanggal_mulai, $tanggal_selesai, $limit, $offset);
$stmt->execute();
$result = $stmt->get_result();
$pembatalan_data = $result->fetch_all(MYSQLI_ASSOC);

// Calculate total amount for the period
$total_sql = "
    SELECT
        SUM(produk_sell.harga_jual * dapur_batal.qty) AS total_keseluruhan
    FROM
        dapur_batal
    INNER JOIN dapur_order_detail ON dapur_batal.id_order_detail = dapur_order_detail.id_order_detail
    INNER JOIN pesanan ON dapur_batal.id_pesanan = pesanan.id_pesanan
    INNER JOIN pesanan_detail ON dapur_order_detail.id_pesanan_detail = pesanan_detail.id_pesanan_detail
    INNER JOIN produk_sell ON pesanan_detail.id_produk_sell = produk_sell.id_produk_sell
    WHERE
        DATE(dapur_batal.waktu) BETWEEN ? AND ?
";

$total_stmt = $conn->prepare($total_sql);
$total_stmt->bind_param("ss", $tanggal_mulai, $tanggal_selesai);
$total_stmt->execute();
$total_result = $total_stmt->get_result();
$total_amount = $total_result->fetch_assoc()['total_keseluruhan'] ?? 0;
?>

<!doctype html>
<html lang="en">
  <head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Pembatalan - Admin Dashboard</title>
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
                <div class="collapse show" id="penjualanMenu">
                  <ul class="nav flex-column ms-3">
                    <li class="nav-item"><a class="nav-link" href="shift_kasir.php"><i class="bi bi-clock"></i> Shift Kasir</a></li>
                    <li class="nav-item"><a class="nav-link" href="promo.php"><i class="bi bi-percent"></i> Promo</a></li>
                    <li class="nav-item"><a class="nav-link" href="biaya_lain.php"><i class="bi bi-receipt"></i> Biaya Lain</a></li>
                    <li class="nav-item"><a class="nav-link" href="harga_pokok_penjualan.php"><i class="bi bi-calculator"></i> Harga Pokok Penjualan</a></li>
                    <li class="nav-item"><a class="nav-link" href="harga_rilis.php"><i class="bi bi-tag"></i> Harga Rilis</a></li>
                    <li class="nav-item"><a class="nav-link active" href="pembatalan.php"><i class="bi bi-x-circle"></i> Pembatalan</a></li>
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
                <div class="collapse" id="laporanMenu">
                  <ul class="nav flex-column ms-3">
                    <li class="nav-item"><a class="nav-link" href="laporan_transaksi.php"><i class="bi bi-list-ul"></i> Transaksi</a></li>
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

        <!-- Mobile Offcanvas Sidebar -->
        <div class="offcanvas offcanvas-start" tabindex="-1" id="sidebarOffcanvas">
          <div class="offcanvas-header">
            <h5 class="offcanvas-title">Menu</h5>
            <button type="button" class="btn-close" data-bs-dismiss="offcanvas"></button>
          </div>
          <div class="offcanvas-body">
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
              <li class="nav-item">
                <a class="nav-link" data-bs-toggle="collapse" href="#masterMenuMobile" role="button">
                  <i class="bi bi-folder"></i> Master
                </a>
                <div class="collapse" id="masterMenuMobile">
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
              <li class="nav-item">
                <a class="nav-link" data-bs-toggle="collapse" href="#produkMenuMobile" role="button">
                  <i class="bi bi-box"></i> Produk
                </a>
                <div class="collapse" id="produkMenuMobile">
                  <ul class="nav flex-column ms-3">
                    <li class="nav-item"><a class="nav-link" href="kategori_menu.php"><i class="bi bi-tags"></i> Kategori Menu</a></li>
                    <li class="nav-item"><a class="nav-link" href="menu.php"><i class="bi bi-list"></i> Menu</a></li>
                    <li class="nav-item"><a class="nav-link" href="kategori_bahan.php"><i class="bi bi-tags"></i> Kategori Bahan</a></li>
                    <li class="nav-item"><a class="nav-link" href="bahan.php"><i class="bi bi-egg"></i> Bahan</a></li>
                    <li class="nav-item"><a class="nav-link" href="resep.php"><i class="bi bi-book"></i> Resep</a></li>
                  </ul>
                </div>
              </li>
              
              <li class="nav-item">
                <a class="nav-link" data-bs-toggle="collapse" href="#pembelianMenuMobile" role="button">
                  <i class="bi bi-cart"></i> Pembelian
                </a>
                <div class="collapse" id="pembelianMenuMobile">
                  <ul class="nav flex-column ms-3">
                    <li class="nav-item"><a class="nav-link" href="pembelian.php"><i class="bi bi-cart-plus"></i> Pesanan Pembelian</a></li>
                    <li class="nav-item"><a class="nav-link" href="pembayaran_pembelian.php"><i class="bi bi-credit-card"></i> Pembayaran</a></li>
                  </ul>
                </div>
              </li>
              <?php endif; ?>
              
              <?php if($_SESSION["jabatan"] == "Admin" || $_SESSION["jabatan"] == "Kasir"): ?>
              <li class="nav-item">
                <a class="nav-link" data-bs-toggle="collapse" href="#penjualanMenuMobile" role="button">
                  <i class="bi bi-cash-stack"></i> Penjualan
                </a>
                <div class="collapse show" id="penjualanMenuMobile">
                  <ul class="nav flex-column ms-3">
                    <li class="nav-item"><a class="nav-link" href="shift_kasir.php"><i class="bi bi-clock"></i> Shift Kasir</a></li>
                    <li class="nav-item"><a class="nav-link" href="promo.php"><i class="bi bi-percent"></i> Promo</a></li>
                    <li class="nav-item"><a class="nav-link" href="biaya_lain.php"><i class="bi bi-receipt"></i> Biaya Lain</a></li>
                    <li class="nav-item"><a class="nav-link" href="harga_pokok_penjualan.php"><i class="bi bi-calculator"></i> Harga Pokok Penjualan</a></li>
                    <li class="nav-item"><a class="nav-link" href="harga_rilis.php"><i class="bi bi-tag"></i> Harga Rilis</a></li>
                    <li class="nav-item"><a class="nav-link active" href="pembatalan.php"><i class="bi bi-x-circle"></i> Pembatalan</a></li>
                  </ul>
                </div>
              </li>
              <?php endif; ?>
              
              <?php if($_SESSION["jabatan"] == "Admin" || $_SESSION["jabatan"] == "Dapur"): ?>
              <li class="nav-item">
                <a class="nav-link" data-bs-toggle="collapse" href="#inventoryMenuMobile" role="button">
                  <i class="bi bi-boxes"></i> Inventory
                </a>
                <div class="collapse" id="inventoryMenuMobile">
                  <ul class="nav flex-column ms-3">
                    <li class="nav-item"><a class="nav-link" href="inventory.php"><i class="bi bi-box-seam"></i> Inventory</a></li>
                    <li class="nav-item"><a class="nav-link" href="transaksi_inventory.php"><i class="bi bi-arrow-left-right"></i> Transaksi</a></li>
                  </ul>
                </div>
              </li>
              <?php endif; ?>
              
              <li class="nav-item">
                <a class="nav-link" data-bs-toggle="collapse" href="#laporanMenuMobile" role="button">
                  <i class="bi bi-graph-up"></i> Laporan
                </a>
                <div class="collapse" id="laporanMenuMobile">
                  <ul class="nav flex-column ms-3">
                    <li class="nav-item"><a class="nav-link" href="laporan_transaksi.php"><i class="bi bi-list-ul"></i> Transaksi</a></li>
                    <li class="nav-item"><a class="nav-link" href="laporan_pengeluaran.php"><i class="bi bi-bar-chart"></i> Pengeluaran vs Penjualan</a></li>
                    <li class="nav-item"><a class="nav-link" href="laporan_kuantitas.php"><i class="bi bi-pie-chart"></i> Kuantitas</a></li>
                  </ul>
                </div>
              </li>
              
              <li class="nav-item">
                <a class="nav-link" href="pengaturan.php">
                  <i class="bi bi-gear"></i> Pengaturan
                </a>
              </li>
            </ul>
          </div>
        </div>

        <!-- Main content -->
        <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
          <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
            <h1 class="h2">Pembatalan</h1>
          </div>

          <!-- Alert Messages -->
          <?php if (!empty($message)): ?>
          <div class="alert alert-success alert-dismissible fade show" role="alert">
            <?php echo htmlspecialchars($message); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
          </div>
          <?php endif; ?>

          <?php if (!empty($error)): ?>
          <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <?php echo htmlspecialchars($error); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
          </div>
          <?php endif; ?>

          <!-- Date Range Filter -->
          <div class="mb-4">
           
              <form method="GET" class="row g-3">
                <div class="col-md-5">
                  <label for="tanggal_mulai" class="form-label">Tanggal Mulai</label>
                  <input type="date" class="form-control" id="tanggal_mulai" name="tanggal_mulai" 
                         value="<?php echo htmlspecialchars($tanggal_mulai); ?>" required>
                </div>
                <div class="col-md-5">
                  <label for="tanggal_selesai" class="form-label">Tanggal Selesai</label>
                  <input type="date" class="form-control" id="tanggal_selesai" name="tanggal_selesai" 
                         value="<?php echo htmlspecialchars($tanggal_selesai); ?>" required>
                </div>
                <div class="col-md-2">
                  <label class="form-label">&nbsp;</label>
                  <button type="submit" class="btn btn-primary w-100">
                    <i class="bi bi-search"></i> Cari
                  </button>
                </div>
              </form>
           
          </div>

          <!-- Cancellation Table -->
          <div class="table-responsive shadow-sm">
            <table class="table table-hover align-middle">
              <thead class="table-dark">
                <tr>
                  <th class="text-center">No</th>
                  <th>Waktu</th>
                  <th>ID Tagihan</th>
                  <th class="text-center">No. Meja</th>
                  <th>Pegawai</th>            
                  <th class="text-center">Total Item</th>
                  <th class="text-end">Total Harga</th>
                </tr>
              </thead>
              <tbody>
                <?php if (empty($pembatalan_data)): ?>
                <tr>
                  <td colspan="7" class="text-center text-muted py-5">
                    <i class="bi bi-inbox display-1 text-muted d-block mb-3"></i>
                    <span class="fs-5">Tidak ada data pembatalan untuk periode <?php echo date('d F Y', strtotime($tanggal_mulai)); ?> - <?php echo date('d F Y', strtotime($tanggal_selesai)); ?></span>
                  </td>
                </tr>
                <?php else: ?>
                <?php 
                $no = ($page - 1) * $limit + 1;
                foreach ($pembatalan_data as $row): 
                ?>
                <tr>
                  <td class="text-center fw-bold"><?php echo $no++; ?></td>
                  <td><?php echo date('d/m/Y H:i', strtotime($row['waktu'])); ?></td>
                  <td>
                    <a href="#" class="text-decoration-none" onclick="showDetail('<?php echo htmlspecialchars($row['id_tagihan']); ?>', '<?php echo htmlspecialchars($tanggal_mulai); ?>', '<?php echo htmlspecialchars($tanggal_selesai); ?>')" data-bs-toggle="modal" data-bs-target="#detailModal">
                      <span class="badge bg-info fs-6 px-3 py-2" style="cursor: pointer;"><?php echo htmlspecialchars($row['id_tagihan']); ?></span>
                    </a>
                  </td>
                  <td class="text-center"><?php echo htmlspecialchars($row['nomor_meja']); ?></td>
                  <td class="fw-semibold"><?php echo htmlspecialchars($row['nama_lengkap']); ?></td>
                 
                  <td class="text-center">
                    <span class="badge bg-secondary fs-6 px-3 py-2"><?php echo $row['total_item']; ?> item</span>
                  </td>
                   <td class="text-end fw-semibold text-danger">Rp <?php echo number_format($row['total_harga_jual'], 0, ',', '.'); ?></td>
                </tr>
                <?php endforeach; ?>
                <?php endif; ?>
              </tbody>
              
            </table>
          </div>
          
          <!-- Grand Total Summary -->
          <?php if (!empty($pembatalan_data)): ?>
            <div class="mt-3 p-3 bg-light rounded border">
              <div class="row align-items-center">
                <div class="col-md-8">
                  <span class="text-muted">Total Pembatalan Periode (<?php echo count($pembatalan_data); ?> transaksi)</span>
                </div>
                <div class="col-md-4 text-end">
                  <span class="fw-bold fs-4 text-danger">Rp <?php echo number_format($total_amount, 0, ',', '.'); ?></span>
                </div>
              </div>
            </div>
          <?php endif; ?>

          <!-- Pagination -->
          <?php if ($total_pages > 1): ?>
          <nav aria-label="Page navigation">
            <ul class="pagination justify-content-center">
              <?php if ($page > 1): ?>
              <li class="page-item">
                <a class="page-link" href="?page=<?php echo $page - 1; ?>&tanggal_mulai=<?php echo urlencode($tanggal_mulai); ?>&tanggal_selesai=<?php echo urlencode($tanggal_selesai); ?>">
                  <i class="bi bi-chevron-left"></i>
                </a>
              </li>
              <?php endif; ?>
              
              <?php for ($i = max(1, $page - 2); $i <= min($total_pages, $page + 2); $i++): ?>
              <li class="page-item <?php echo $i == $page ? 'active' : ''; ?>">
                <a class="page-link" href="?page=<?php echo $i; ?>&tanggal_mulai=<?php echo urlencode($tanggal_mulai); ?>&tanggal_selesai=<?php echo urlencode($tanggal_selesai); ?>">
                  <?php echo $i; ?>
                </a>
              </li>
              <?php endfor; ?>
              
              <?php if ($page < $total_pages): ?>
              <li class="page-item">
                <a class="page-link" href="?page=<?php echo $page + 1; ?>&tanggal_mulai=<?php echo urlencode($tanggal_mulai); ?>&tanggal_selesai=<?php echo urlencode($tanggal_selesai); ?>">
                  <i class="bi bi-chevron-right"></i>
                </a>
              </li>
              <?php endif; ?>
            </ul>
          </nav>
          <?php endif; ?>
        </main>
      </div>
    </div>

    <!-- Detail Modal -->
    <div class="modal fade" id="detailModal" tabindex="-1">
      <div class="modal-dialog modal-lg">
        <div class="modal-content">
          <div class="modal-header">
            <h5 class="modal-title">Detail Pembatalan - <span id="modal-id-tagihan"></span></h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
          </div>
          <div class="modal-body">
            <div id="loading-spinner" class="text-center py-5" style="display: none;">
              <div class="spinner-border text-primary" role="status">
                <span class="visually-hidden">Loading...</span>
              </div>
            </div>
            <div id="detail-content">
              <!-- Content will be loaded here via AJAX -->
            </div>
          </div>
         
        </div>
      </div>
    </div>

    <script src="../js/bootstrap.bundle.min.js"></script>
    <!-- Flatpickr JS for Date Picker -->
    <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
    <script>
      // Initialize date pickers with Flatpickr for better date selection
      flatpickr("#tanggal_mulai", {
        dateFormat: "Y-m-d",
        maxDate: "today",
        locale: {
          firstDayOfWeek: 1
        }
      });
      
      flatpickr("#tanggal_selesai", {
        dateFormat: "Y-m-d",
        maxDate: "today",
        locale: {
          firstDayOfWeek: 1
        }
      });

      // Auto-submit form when date changes
      document.getElementById('tanggal_mulai').addEventListener('change', function() {
        if (document.getElementById('tanggal_selesai').value) {
          // Validate date range
          if (this.value > document.getElementById('tanggal_selesai').value) {
            alert('Tanggal mulai tidak boleh lebih besar dari tanggal selesai!');
            this.value = document.getElementById('tanggal_selesai').value;
          }
        }
      });

      document.getElementById('tanggal_selesai').addEventListener('change', function() {
        if (document.getElementById('tanggal_mulai').value) {
          // Validate date range
          if (this.value < document.getElementById('tanggal_mulai').value) {
            alert('Tanggal selesai tidak boleh lebih kecil dari tanggal mulai!');
            this.value = document.getElementById('tanggal_mulai').value;
          }
        }
      });

      // Function to show detail modal
      function showDetail(idTagihan, tanggalMulai, tanggalSelesai) {
        document.getElementById('modal-id-tagihan').textContent = idTagihan;
        document.getElementById('loading-spinner').style.display = 'block';
        document.getElementById('detail-content').innerHTML = '';
        
        // Create AJAX request to get detail data
        const xhr = new XMLHttpRequest();
        xhr.open('GET', 'get_pembatalan_detail.php?id_tagihan=' + encodeURIComponent(idTagihan) + '&tanggal_mulai=' + encodeURIComponent(tanggalMulai) + '&tanggal_selesai=' + encodeURIComponent(tanggalSelesai), true);
        
        xhr.onload = function() {
          document.getElementById('loading-spinner').style.display = 'none';
          if (xhr.status === 200) {
            document.getElementById('detail-content').innerHTML = xhr.responseText;
          } else {
            document.getElementById('detail-content').innerHTML = '<div class="alert alert-danger">Error loading data</div>';
          }
        };
        
        xhr.onerror = function() {
          document.getElementById('loading-spinner').style.display = 'none';
          document.getElementById('detail-content').innerHTML = '<div class="alert alert-danger">Network error</div>';
        };
        
        xhr.send();
      }
    </script>
  </body>
</html>