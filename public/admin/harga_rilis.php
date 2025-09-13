<?php
session_start();
require_once __DIR__ . '/../../config/koneksi.php';
require_once __DIR__ . '/../../config/encryption.php';

// Check if user is logged in
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    header('Location: login.php');
    exit();
}

$message = '';
$error = '';

// Handle rilis produk action
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action']) && $_POST['action'] == 'rilis') {
    $id_produk = (int)$_POST['id_produk'];
    $stok = (int)$_POST['stok'];
    $harga_jual = (float)$_POST['harga_jual'];
    $aktif = isset($_POST['aktif']) ? '1' : '0';
    $id_user = $_SESSION['id_user'];
    $tgl_hari_ini = date('Y-m-d');
    
    if ($id_produk > 0 && $stok >= 0) {
        // Cek apakah sudah ada data untuk hari ini
        $cek_sql = "SELECT id_produk_sell FROM produk_sell 
                    WHERE id_produk = ? 
                    AND DATE(tgl_release) = ?";
        $cek_stmt = $conn->prepare($cek_sql);
        $cek_stmt->bind_param("is", $id_produk, $tgl_hari_ini);
        $cek_stmt->execute();
        $cek_result = $cek_stmt->get_result();
        
        if ($cek_result->num_rows > 0) {
            $error = 'Data sudah ada untuk tanggal hari ini!';
        } else {
            // Insert data baru
            $sql = "INSERT INTO produk_sell(id_produk, stok, harga_jual, id_user, aktif, stok_awal, tgl_release) 
                    VALUES (?, ?, ?, ?, ?, ?, NOW())";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("iidisi", $id_produk, $stok, $harga_jual, $id_user, $aktif, $stok);
            
            if ($stmt->execute()) {
                $message = 'Produk berhasil dirilis!';
            } else {
                $error = 'Gagal merilis produk: ' . $conn->error;
            }
        }
    } else {
        $error = 'Data tidak valid!';
    }
}

// Handle toggle aktif jual action
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action']) && $_POST['action'] == 'toggle_aktif') {
    $id_produk = (int)$_POST['id_produk'];
    $current_status = $_POST['current_status'];
    $new_status = ($current_status == '1') ? '0' : '1';
    $tgl_release = $_POST['tgl_release'];
    
    if ($id_produk > 0) {
        $sql = "UPDATE produk_sell SET aktif = ? WHERE id_produk = ? AND DATE(tgl_release) = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("sis", $new_status, $id_produk, $tgl_release);
        
        if ($stmt->execute()) {
            $status_text = ($new_status == '1') ? 'diaktifkan' : 'dinonaktifkan';
            $message = "Status produk berhasil {$status_text}!";
        } else {
            $error = 'Gagal mengubah status produk: ' . $conn->error;
        }
    } else {
        $error = 'Data tidak valid!';
    }
}



// Get parameters
$selected_date = isset($_GET['date']) ? $_GET['date'] : date('Y-m-d');
$search_nama = isset($_GET['search_nama']) ? trim($_GET['search_nama']) : '';
$filter_kategori = isset($_GET['filter_kategori']) ? (int)$_GET['filter_kategori'] : 0;

// Validate date format
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $selected_date)) {
    $selected_date = date('Y-m-d');
}

// Get categories for dropdown
$categories = [];
try {
    $cat_result = $conn->query("SELECT id_kategori, nama_kategori FROM kategori_menu ORDER BY nama_kategori ASC");
    $categories = $cat_result->fetch_all(MYSQLI_ASSOC);
} catch (Exception $e) {
    $error = 'Terjadi kesalahan saat mengambil kategori: ' . $e->getMessage();
}

// Build query with search and filter conditions
$data = [];
try {
    $where_conditions = [];
    $params = [];
    $param_types = "";
    
    // Base WHERE conditions
    $where_conditions[] = "harga_menu.nominal > 0";
    $where_conditions[] = "harga_menu.id_resep IS NOT NULL";
    
    // Add search condition
    if (!empty($search_nama)) {
        $where_conditions[] = "produk_menu.nama_produk LIKE ?";
        $params[] = "%" . $search_nama . "%";
        $param_types .= "s";
    }
    
    // Add category filter
    if ($filter_kategori > 0) {
        $where_conditions[] = "produk_menu.id_kategori = ?";
        $params[] = $filter_kategori;
        $param_types .= "i";
    }
    
    // Add date parameter
    $params[] = $selected_date;
    $param_types .= "s";
    
    $where_clause = implode(" AND ", $where_conditions);
    
    $sql = "
        SELECT
            harga_menu.id_harga, 
            harga_menu.id_produk, 
            produk_menu.kode_produk, 
            produk_menu.nama_produk, 
            nama_kategori, 
            nominal,
            harga_menu.id_user,
            COALESCE(FORMAT(produk_sell.harga_jual, 0), 0) AS harga_jual,
            COALESCE(produk_sell.stok, 0) AS stok,
            COALESCE(produk_sell.id_produk_sell, '-') AS id_produk_sell,
            COALESCE(produk_sell.aktif, '-') AS aktif_jual
        FROM
            harga_menu
        INNER JOIN (
            SELECT id_produk, MAX(tgl) AS tgl_terbaru
            FROM harga_menu
            WHERE nominal > 0 AND id_resep IS NOT NULL
            GROUP BY id_produk
        ) AS subquery
            ON harga_menu.id_produk = subquery.id_produk 
            AND harga_menu.tgl = subquery.tgl_terbaru
        INNER JOIN produk_menu
            ON harga_menu.id_produk = produk_menu.id_produk
        INNER JOIN kategori_menu
            ON produk_menu.id_kategori = kategori_menu.id_kategori
        LEFT JOIN produk_sell
            ON harga_menu.id_produk = produk_sell.id_produk
            AND DATE(produk_sell.tgl_release) = ?
        WHERE " . $where_clause . "
        ORDER BY produk_menu.nama_produk ASC
    ";
    
    $stmt = $conn->prepare($sql);
    if (!empty($params)) {
        $stmt->bind_param($param_types, ...$params);
    }
    $stmt->execute();
    $result = $stmt->get_result();
    $data = $result->fetch_all(MYSQLI_ASSOC);
} catch (Exception $e) {
    $error = 'Terjadi kesalahan saat mengambil data: ' . $e->getMessage();
}
?>

<!doctype html>
<html lang="en">
  <head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Harga Rilis - Admin Dashboard</title>
    <link href="../css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css" rel="stylesheet">
    <link href="../css/admin.css" rel="stylesheet">
  </head>
  <body>
    <!-- Top Navbar -->
    <nav class="navbar navbar-expand-lg navbar-dark bg-primary">
      <div class="container-fluid">
        <button class="navbar-toggler" type="button" data-bs-toggle="offcanvas" data-bs-target="#sidebarOffcanvas" aria-controls="sidebarOffcanvas">
          <span class="navbar-toggler-icon"></span>
        </button>
        <a class="navbar-brand" href="#">Resto007 Admin</a>
        <div class="navbar-nav ms-auto">
          <div class="nav-item dropdown">
            <a class="nav-link dropdown-toggle text-white" href="#" role="button" data-bs-toggle="dropdown">
              <?php echo htmlspecialchars($_SESSION["nama_lengkap"]); ?> (<?php echo htmlspecialchars($_SESSION["jabatan"]); ?>)
            </a>
            <ul class="dropdown-menu">
              <li><a class="dropdown-item" href="#">Ubah Password</a></li>
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
                  <i class="bi bi-house"></i>
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
              <!-- Master Menu -->
              <li class="nav-item">
                <a class="nav-link" data-bs-toggle="collapse" href="#masterMenu" role="button">
                  <i class="bi bi-folder"></i>
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
                  <i class="bi bi-box"></i>
                  <span>Produk</span>
                  <i class="bi bi-chevron-down ms-auto"></i>
                </a>
                <div class="collapse" id="produkMenu">
                  <ul class="nav flex-column ms-3">
                    <li class="nav-item"><a class="nav-link" href="kategori_menu.php"><i class="bi bi-tags"></i> Kategori Menu</a></li>
                    <li class="nav-item"><a class="nav-link" href="menu.php"><i class="bi bi-list"></i> Menu</a></li>
                    <li class="nav-item"><a class="nav-link" href="kategori_bahan.php"><i class="bi bi-tags"></i> Kategori Bahan</a></li>
                    <li class="nav-item"><a class="nav-link" href="bahan.php"><i class="bi bi-egg"></i> Bahan</a></li>
                    <li class="nav-item"><a class="nav-link" href="resep.php"><i class="bi bi-book"></i> Resep</a></li>
                  </ul>
                </div>
              </li>
              
              <!-- Pembelian Menu -->
              <li class="nav-item">
                <a class="nav-link" data-bs-toggle="collapse" href="#pembelianMenu" role="button">
                  <i class="bi bi-cart"></i>
                  <span>Pembelian</span>
                  <i class="bi bi-chevron-down ms-auto"></i>
                </a>
                <div class="collapse" id="pembelianMenu">
                  <ul class="nav flex-column ms-3">
                    <li class="nav-item"><a class="nav-link" href="pembelian.php"><i class="bi bi-cart-plus"></i> Pesanan Pembelian</a></li>
                    <li class="nav-item"><a class="nav-link" href="pembayaran_pembelian.php"><i class="bi bi-credit-card"></i> Pembayaran</a></li>
                  </ul>
                </div>
              </li>
              <?php endif; ?>
              
              <?php if($_SESSION["jabatan"] == "Admin" || $_SESSION["jabatan"] == "Kasir"): ?>
              <!-- Penjualan Menu -->
              <li class="nav-item">
                <a class="nav-link" data-bs-toggle="collapse" href="#penjualanMenu" role="button">
                  <i class="bi bi-cash-stack"></i>
                  <span>Penjualan</span>
                  <i class="bi bi-chevron-down ms-auto"></i>
                </a>
                <div class="collapse show" id="penjualanMenu">
                  <ul class="nav flex-column ms-3">
                    <li class="nav-item"><a class="nav-link" href="shift_kasir.php"><i class="bi bi-clock"></i> Shift Kasir</a></li>
                    <li class="nav-item"><a class="nav-link" href="promo.php"><i class="bi bi-percent"></i> Promo</a></li>
                    <li class="nav-item"><a class="nav-link" href="biaya_lain.php"><i class="bi bi-receipt"></i> Biaya Lain</a></li>
                    <li class="nav-item"><a class="nav-link" href="harga_pokok_penjualan.php"><i class="bi bi-calculator"></i> Harga Pokok Penjualan</a></li>
                    <li class="nav-item"><a class="nav-link active" href="harga_rilis.php"><i class="bi bi-tag"></i> Harga Rilis</a></li>
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
              
              <li class="nav-item">
                <a class="nav-link" href="pengaturan.php">
                  <i class="bi bi-gear"></i>
                  <span>Pengaturan</span>
                </a>
              </li>
            </ul>
          </div>
        </div>

        <!-- Mobile Sidebar -->
        <div class="offcanvas offcanvas-start" tabindex="-1" id="sidebarOffcanvas">
          <div class="offcanvas-header">
            <h5 class="offcanvas-title">Menu</h5>
            <button type="button" class="btn-close" data-bs-dismiss="offcanvas"></button>
          </div>
          <div class="offcanvas-body">
            <ul class="nav flex-column">
              <li class="nav-item">
                <a class="nav-link" href="index.php">
                  <i class="bi bi-house"></i> Beranda
                </a>
              </li>
              
              <li class="nav-item">
                <a class="nav-link" href="informasi.php">
                  <i class="bi bi-info-circle"></i> Informasi
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
                    <li class="nav-item"><a class="nav-link active" href="harga_rilis.php"><i class="bi bi-tag"></i> Harga Rilis</a></li>
                    <li class="nav-item"><a class="nav-link" href="pembatalan.php"><i class="bi bi-x-circle"></i> Pembatalan</a></li>
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

        <!-- Main Content -->
        <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
          <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
            <h1 class="h2">Harga Rilis</h1>
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

          <!-- Search and Filter -->
          <div class="row mb-3">
            <div class="col-md-12">
              <form method="GET" class="row g-3">
                <div class="col-md-3">
                  <label for="date" class="form-label">Tanggal</label>
                  <input type="date" class="form-control" id="date" name="date" value="<?php echo htmlspecialchars($selected_date); ?>">
                </div>
                <div class="col-md-3">
                  <label for="search_nama" class="form-label">Cari Nama Produk</label>
                  <input type="text" class="form-control" id="search_nama" name="search_nama" placeholder="Masukkan nama produk..." value="<?php echo htmlspecialchars($search_nama); ?>">
                </div>
                <div class="col-md-3">
                  <label for="filter_kategori" class="form-label">Kategori</label>
                  <select class="form-select" id="filter_kategori" name="filter_kategori">
                    <option value="0">Semua Kategori</option>
                    <?php foreach ($categories as $category): ?>
                    <option value="<?php echo $category['id_kategori']; ?>" <?php echo ($filter_kategori == $category['id_kategori']) ? 'selected' : ''; ?>>
                      <?php echo htmlspecialchars($category['nama_kategori']); ?>
                    </option>
                    <?php endforeach; ?>
                  </select>
                </div>
                <div class="col-md-3">
                  <label class="form-label">&nbsp;</label>
                  <div class="d-grid">
                    <button type="submit" class="btn btn-primary">Filter</button>
                  </div>
                </div>
              </form>
            </div>
          </div>

          <!-- Data Table -->
          <div class="table-responsive">
            <table class="table table-striped table-hover">
              <thead class="table-dark">
                <tr>
                  <th>No</th>
                  <th>Kode Produk</th>
                  <th>Nama Produk</th>
                  <th>Kategori</th>
                  <th>Harga Pokok</th>
                
                  <th>Stok</th>
                  <th>Aktif Jual</th>
                </tr>
              </thead>
              <tbody>
                <?php if (empty($data)): ?>
                 <tr>
                   <td colspan="8" class="text-center">
                     <?php if (!empty($search_nama) || $filter_kategori > 0): ?>
                       Tidak ada data yang sesuai dengan kriteria pencarian
                     <?php else: ?>
                       Tidak ada data untuk tanggal <?php echo htmlspecialchars($selected_date); ?>
                     <?php endif; ?>
                   </td>
                 </tr>
                <?php else: ?>
                <?php foreach ($data as $index => $row): ?>
                <tr>
                  <td><?php echo $index + 1; ?></td>
                  <td><?php echo htmlspecialchars($row['kode_produk']); ?></td>
                  <td><?php echo htmlspecialchars($row['nama_produk']); ?></td>
                  <td><?php echo htmlspecialchars($row['nama_kategori']); ?></td>
                  <td>Rp <?php echo number_format($row['nominal'], 0, ',', '.'); ?></td>
                 
                  <td><?php echo htmlspecialchars($row['stok']); ?></td>
                  <td>
                    <?php if ($row['aktif_jual'] === '1'): ?>
                      <button class="btn btn-success btn-sm" data-bs-toggle="modal" data-bs-target="#toggleModal" 
                              onclick="setToggleData(<?php echo $row['id_produk']; ?>, '<?php echo addslashes($row['nama_produk']); ?>', '1', '<?php echo $selected_date; ?>')">
                        <i class="bi bi-check-circle"></i> Aktif
                      </button>
                    <?php elseif ($row['aktif_jual'] === '0'): ?>
                      <button class="btn btn-warning btn-sm" data-bs-toggle="modal" data-bs-target="#toggleModal" 
                              onclick="setToggleData(<?php echo $row['id_produk']; ?>, '<?php echo addslashes($row['nama_produk']); ?>', '0', '<?php echo $selected_date; ?>')">
                        <i class="bi bi-pause-circle"></i> Nonaktif
                      </button>
                    <?php else: ?>
                      <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#rilisModal" 
                              onclick="setRilisData(<?php echo $row['id_produk']; ?>, '<?php echo addslashes($row['nama_produk']); ?>', <?php echo $row['nominal']; ?>)">
                        <i class="bi bi-upload"></i> Belum Rilis
                      </button>
                    <?php endif; ?>
                  </td>
                </tr>
                <?php endforeach; ?>
                <?php endif; ?>
              </tbody>
            </table>
          </div>

          <!-- Summary -->
          <?php if (!empty($data)): ?>
          <div class="row mt-3">
            <div class="col-md-12">
              <p class="text-muted">
                <strong>Total: <?php echo count($data); ?> produk</strong> | 
                Tanggal: <?php echo date('d/m/Y', strtotime($selected_date)); ?>
                <?php if (!empty($search_nama)): ?>
                  | Pencarian: "<?php echo htmlspecialchars($search_nama); ?>"
                <?php endif; ?>
                <?php if ($filter_kategori > 0): ?>
                  <?php 
                    $selected_category = array_filter($categories, function($cat) use ($filter_kategori) {
                      return $cat['id_kategori'] == $filter_kategori;
                    });
                    $selected_category = reset($selected_category);
                  ?>
                  | Kategori: <?php echo htmlspecialchars($selected_category['nama_kategori']); ?>
                <?php endif; ?>
              </p>
            </div>
          </div>
          <?php endif; ?>
        </main>
      </div>
    </div>

    <!-- Modal Rilis Produk -->
    <div class="modal fade" id="rilisModal" tabindex="-1" aria-labelledby="rilisModalLabel" aria-hidden="true">
      <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg">
          <div class="modal-header bg-primary text-white border-0">
            <h5 class="modal-title fw-bold" id="rilisModalLabel">
              <i class="bi bi-upload me-2"></i>Rilis Produk
            </h5>
            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
          </div>
          <form method="POST">
            <div class="modal-body p-4">
              <input type="hidden" name="action" value="rilis">
              <input type="hidden" name="id_produk" id="modal_id_produk">
              <input type="hidden" name="harga_jual" id="modal_harga_jual">
              
              <div class="mb-4">
                <label class="form-label fw-semibold text-muted">Nama Produk</label>
                <p id="modal_nama_produk" class="form-control-plaintext fw-bold fs-5"></p>
              </div>
              
              <div class="mb-4">
                <label class="form-label fw-semibold text-muted">Tanggal</label>
                <p class="form-control-plaintext"><?php echo date('d/m/Y'); ?></p>
              </div>
              
              <div class="mb-4">
                <label class="form-label fw-semibold text-muted">Harga Jual</label>
                <p id="modal_harga_display" class="form-control-plaintext fw-bold text-success fs-5"></p>
              </div>
              
              <div class="mb-4">
                <label for="modal_stok" class="form-label fw-semibold text-muted">Stok</label>
                <div class="input-group">
                  <span class="input-group-text bg-light border-0"><i class="bi bi-box"></i></span>
                  <input type="number" class="form-control border-0 bg-light" id="modal_stok" name="stok" min="0" required>
                </div>
              </div>
              
              <div class="form-check p-3 bg-light rounded">
                <input type="checkbox" class="form-check-input" id="modal_aktif" name="aktif" checked>
                <label class="form-check-label fw-semibold" for="modal_aktif">
                  <i class="bi bi-check-circle text-success me-2"></i>Aktif Jual
                </label>
              </div>
            </div>
            <div class="modal-footer border-0 p-4">
              <button type="button" class="btn btn-light px-4" data-bs-dismiss="modal">
                <i class="bi bi-x-circle me-2"></i>Batal
              </button>
              <button type="submit" class="btn btn-primary px-4 fw-bold">
                <i class="bi bi-upload me-2"></i>RILIS PRODUK
              </button>
            </div>
          </form>
        </div>
      </div>
    </div>

    <!-- Modal Toggle Status Aktif -->
    <div class="modal fade" id="toggleModal" tabindex="-1" aria-labelledby="toggleModalLabel" aria-hidden="true">
      <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg">
          <div class="modal-header bg-warning text-dark border-0">
            <h5 class="modal-title fw-bold" id="toggleModalLabel">
              <i class="bi bi-toggle-on me-2"></i>Ubah Status Aktif Jual
            </h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
          </div>
          <form method="POST">
            <div class="modal-body p-4 text-center">
              <input type="hidden" name="action" value="toggle_aktif">
              <input type="hidden" name="id_produk" id="toggle_id_produk">
              <input type="hidden" name="current_status" id="toggle_current_status">
              <input type="hidden" name="tgl_release" id="toggle_tgl_release">
              
              <div class="mb-4">
                <i class="bi bi-question-circle-fill text-warning" style="font-size: 3rem;"></i>
              </div>
              
              <h6 class="fw-bold mb-3" id="toggle_nama_produk"></h6>
              
              <p class="text-muted mb-4" id="toggle_message"></p>
            </div>
            <div class="modal-footer border-0 p-4 justify-content-center">
              <button type="button" class="btn btn-light px-4 me-2" data-bs-dismiss="modal">
                <i class="bi bi-x-circle me-2"></i>Batal
              </button>
              <button type="submit" class="btn px-4 fw-bold" id="toggle_submit_btn">
                <i class="bi bi-toggle-on me-2"></i><span id="toggle_action_text"></span>
              </button>
            </div>
          </form>
        </div>
      </div>
    </div>

    <script src="../js/bootstrap.bundle.min.js"></script>
    <script>
    function setRilisData(idProduk, namaProduk, hargaPokok) {
        document.getElementById('modal_id_produk').value = idProduk;
        document.getElementById('modal_nama_produk').textContent = namaProduk;
        document.getElementById('modal_harga_jual').value = hargaPokok;
        document.getElementById('modal_harga_display').textContent = 'Rp ' + new Intl.NumberFormat('id-ID').format(hargaPokok);
        document.getElementById('modal_stok').value = '';
        document.getElementById('modal_aktif').checked = true;
    }
    
    function setToggleData(idProduk, namaProduk, currentStatus, tglRelease) {
        document.getElementById('toggle_id_produk').value = idProduk;
        document.getElementById('toggle_nama_produk').textContent = namaProduk;
        document.getElementById('toggle_current_status').value = currentStatus;
        document.getElementById('toggle_tgl_release').value = tglRelease;
        
        const toggleMessage = document.getElementById('toggle_message');
        const toggleSubmitBtn = document.getElementById('toggle_submit_btn');
        const toggleActionText = document.getElementById('toggle_action_text');
        
        if (currentStatus === '1') {
            toggleMessage.textContent = 'Apakah Anda yakin ingin menonaktifkan produk ini dari penjualan?';
            toggleSubmitBtn.className = 'btn btn-warning px-4 fw-bold';
            toggleActionText.textContent = 'NONAKTIFKAN';
        } else {
            toggleMessage.textContent = 'Apakah Anda yakin ingin mengaktifkan produk ini untuk penjualan?';
            toggleSubmitBtn.className = 'btn btn-success px-4 fw-bold';
            toggleActionText.textContent = 'AKTIFKAN';
        }
    }
    </script>
  </body>
</html>