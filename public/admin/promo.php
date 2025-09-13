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

// Function to generate promo code
function generatePromoCode($conn) {
    $year = date('y'); // 2 digit year
    $prefix = "PR{$year}-";
    
    // Get the last promo code for this year
    $stmt = $conn->prepare("SELECT kode_promo FROM promo WHERE kode_promo LIKE ? ORDER BY kode_promo DESC LIMIT 1");
    $like_pattern = $prefix . '%';
    $stmt->bind_param("s", $like_pattern);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($row = $result->fetch_assoc()) {
        // Extract number from last code and increment
        $last_code = $row['kode_promo'];
        $number = (int)substr($last_code, -4) + 1;
    } else {
        $number = 1;
    }
    
    return $prefix . str_pad($number, 4, '0', STR_PAD_LEFT);
}

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'create':
                $kode_promo = trim($_POST['kode_promo']);
                $nama_promo = trim($_POST['nama_promo']);
                $deskripsi = trim($_POST['deskripsi']);
                $tanggalmulai_promo = $_POST['tanggalmulai_promo'];
                $tanggalselesai_promo = $_POST['tanggalselesai_promo'];
                $pilihan_promo = $_POST['pilihan_promo'];
                $nominal = floatval($_POST['nominal']);
                $persen = floatval($_POST['persen']);
                $kuota = intval($_POST['kuota']);
                $min_pembelian = floatval($_POST['min_pembelian']);
                $aktif = isset($_POST['aktif']) ? '1' : '0';
                $id_user = $_SESSION['id_user'];
                
                if (!empty($nama_promo) && !empty($tanggalmulai_promo) && !empty($tanggalselesai_promo)) {
                    // Generate promo code if empty
                    if (empty($kode_promo)) {
                        $kode_promo = generatePromoCode($conn);
                    }
                    
                    // Check if promo code already exists
                    $stmt = $conn->prepare("SELECT id_promo FROM promo WHERE kode_promo = ?");
                    $stmt->bind_param("s", $kode_promo);
                    $stmt->execute();
                    $result = $stmt->get_result();
                    if ($result->fetch_assoc()) {
                        $error = 'Kode promo "' . $kode_promo . '" sudah digunakan! Silakan gunakan kode lain.';
                        break;
                    }
                    
                    // Validate dates
                    if ($tanggalselesai_promo < $tanggalmulai_promo) {
                        $error = 'Tanggal selesai harus sama atau lebih besar dari tanggal mulai!';
                        break;
                    }
                    
                    // Set nilai based on pilihan_promo
                    if ($pilihan_promo == 'nominal') {
                        $persen = 0;
                    } else {
                        $nominal = 0;
                    }
                    
                    $stmt = $conn->prepare("INSERT INTO promo (kode_promo, nama_promo, deskripsi, id_user, tanggalmulai_promo, tanggalselesai_promo, nominal, persen, pilihan_promo, aktif, kuota, min_pembelian) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                    $stmt->bind_param("sssissddsiii", $kode_promo, $nama_promo, $deskripsi, $id_user, $tanggalmulai_promo, $tanggalselesai_promo, $nominal, $persen, $pilihan_promo, $aktif, $kuota, $min_pembelian);
                    if ($stmt->execute()) {
                        $message = 'Data promo berhasil ditambahkan!';
                    } else {
                        $error = 'Error: ' . $conn->error;
                    }
                } else {
                    $error = 'Nama promo, tanggal mulai, dan tanggal selesai harus diisi!';
                }
                break;
                

            case 'toggle_status':
                $id_promo = intval($_POST['id_promo']);
                $aktif = $_POST['aktif'] == '1' ? '0' : '1';
                
                $stmt = $conn->prepare("UPDATE promo SET aktif = ? WHERE id_promo = ?");
                $stmt->bind_param("ii", $aktif, $id_promo);
                if ($stmt->execute()) {
                    $message = $aktif == '1' ? 'Promo berhasil diaktifkan!' : 'Promo berhasil dinonaktifkan!';
                } else {
                    $error = 'Error: ' . $conn->error;
                }
                break;
                
            case 'check_promo_code':
                $kode_promo = $_POST['kode_promo'];
                
                $stmt = $conn->prepare("SELECT id_promo FROM promo WHERE kode_promo = ?");
                $stmt->bind_param("s", $kode_promo);
                $stmt->execute();
                $result = $stmt->get_result();
                
                echo json_encode(['exists' => $result->num_rows > 0]);
                exit;
                break;
        }
    }
}

// Pagination and Search parameters
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 20;
$offset = ($page - 1) * $limit;
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$filter = isset($_GET['filter']) ? trim($_GET['filter']) : '';

// Build WHERE clause for search and filter
$where_conditions = [];
$params = [];
$types = '';

if (!empty($search)) {
    $where_conditions[] = "(promo.nama_promo LIKE ? OR promo.kode_promo LIKE ? OR promo.deskripsi LIKE ?)";
    $search_param = "%$search%";
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
    $types .= 'sss';
}

if (!empty($filter) && in_array($filter, ['1', '0'])) {
    $where_conditions[] = "promo.aktif = ?";
    $params[] = $filter;
    $types .= 's';
}

$where_clause = !empty($where_conditions) ? 'WHERE ' . implode(' AND ', $where_conditions) : '';

// Query as specified in gemini.md
$base_query = "
    SELECT 
        promo.id_promo,
        promo.nama_promo, 
        promo.kode_promo,
        promo.tanggalmulai_promo,
        promo.tanggalselesai_promo,
        CONCAT(pegawai.jabatan,' - ',pegawai.nama_lengkap) as insert_by,
        promo.kuota, 
        promo.min_pembelian, 
        promo.pilihan_promo,
        CASE
            WHEN promo.pilihan_promo = 'nominal' THEN CONCAT('Rp',FORMAT(promo.nominal,0))
            WHEN promo.pilihan_promo = 'persen' THEN CONCAT(promo.persen,'%')
        END as nilai_promo,
        promo.deskripsi,
        promo.aktif
    FROM promo
    INNER JOIN pegawai ON promo.id_user = pegawai.id_user
";

// Get total count for pagination
$count_sql = "SELECT COUNT(*) as total FROM promo INNER JOIN pegawai ON promo.id_user = pegawai.id_user $where_clause";
if (!empty($params)) {
    $count_stmt = $conn->prepare($count_sql);
    $count_stmt->bind_param($types, ...$params);
    $count_stmt->execute();
    $count_result = $count_stmt->get_result();
} else {
    $count_result = $conn->query($count_sql);
}
$total_records = $count_result->fetch_assoc()['total'];
$total_pages = ceil($total_records / $limit);

// Get promo data
$data_query = "$base_query $where_clause ORDER BY promo.id_promo DESC LIMIT $limit OFFSET $offset";

if (!empty($params)) {
    $stmt = $conn->prepare($data_query);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $result = $stmt->get_result();
    $promos = $result->fetch_all(MYSQLI_ASSOC);
} else {
    $result = $conn->query($data_query);
    if ($result) {
        $promos = $result->fetch_all(MYSQLI_ASSOC);
    } else {
        $error = 'Error fetching data: ' . $conn->error;
        $promos = [];
    }
}


?>

<!doctype html>
<html lang="en">
  <head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Promo - Admin Dashboard</title>
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
                    <li class="nav-item"><a class="nav-link active" href="promo.php"><i class="bi bi-percent"></i> Promo</a></li>
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
                    <li class="nav-item"><a class="nav-link active" href="promo.php"><i class="bi bi-percent"></i> Promo</a></li>
                    <li class="nav-item"><a class="nav-link" href="biaya_lain.php"><i class="bi bi-receipt"></i> Biaya Lain</a></li>
                    <li class="nav-item"><a class="nav-link" href="harga_pokok_penjualan.php"><i class="bi bi-calculator"></i> Harga Pokok Penjualan</a></li>
                    <li class="nav-item"><a class="nav-link" href="harga_rilis.php"><i class="bi bi-tag"></i> Harga Rilis</a></li>
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

        <!-- Main content -->
        <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
          <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
            <h1 class="h2">Promo</h1>
            <div class="btn-toolbar mb-2 mb-md-0">
              <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#promoModal">
                <i class="bi bi-plus"></i> Tambah Promo
              </button>
            </div>
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
            <div class="col-md-6">
              <form method="GET" class="d-flex">
                <input type="text" class="form-control me-2" name="search" placeholder="Cari promo..." value="<?php echo htmlspecialchars($search); ?>">
                <button class="btn btn-outline-secondary" type="submit">
                  <i class="bi bi-search"></i>
                </button>
                <?php if (!empty($search)): ?>
                <a href="promo.php" class="btn btn-outline-danger ms-2">
                  <i class="bi bi-x"></i>
                </a>
                <?php endif; ?>
              </form>
            </div>
            <div class="col-md-6">
              <form method="GET" class="d-flex justify-content-end">
                <?php if (!empty($search)): ?>
                <input type="hidden" name="search" value="<?php echo htmlspecialchars($search); ?>">
                <?php endif; ?>
                <select name="filter" class="form-select me-2" style="width: auto; padding-right: 2.5rem;" onchange="this.form.submit()">
                  <option value="">Semua Status</option>
                  <option value="1" <?php echo $filter === '1' ? 'selected' : ''; ?>>Aktif</option>
                  <option value="0" <?php echo $filter === '0' ? 'selected' : ''; ?>>Tidak Aktif</option>
                </select>
              </form>
            </div>
          </div>

          <!-- Promo Table -->
          <div class="table-responsive">
            <table class="table table-striped table-hover">
              <thead class="table-dark">
                <tr>
                  <th>No</th>
                  <th>Kode Promo</th>
                  <th>Nama Promo</th>
                  <th>Kuota</th>
                  <th>Nilai Promo</th>
                  <th>Status</th>
                  <th>Aksi</th>
                </tr>
              </thead>
              <tbody>
                <?php if (empty($promos)): ?>
                <tr>
                  <td colspan="7" class="text-center">Tidak ada data promo</td>
                </tr>
                <?php else: ?>
                <?php 
                $no = ($page - 1) * $limit + 1;
                foreach ($promos as $promo): 
                ?>
                <tr>
                  <td><?php echo $no++; ?></td>
                  <td>
                    <a href="#" class="text-decoration-none" onclick="showPromoDetail('<?php echo htmlspecialchars(json_encode($promo)); ?>')" data-bs-toggle="modal" data-bs-target="#detailModal">
                      <?php echo htmlspecialchars($promo['kode_promo']); ?>
                    </a>
                  </td>
                  <td><?php echo htmlspecialchars($promo['nama_promo']); ?></td>
                  <td><?php echo htmlspecialchars($promo['kuota']); ?></td>
                  <td><?php echo htmlspecialchars($promo['nilai_promo']); ?></td>
                  <td>
                    <?php if ($promo['aktif'] == '1'): ?>
                    <span class="badge bg-success">Aktif</span>
                    <?php else: ?>
                    <span class="badge bg-secondary">Tidak Aktif</span>
                    <?php endif; ?>
                  </td>
                  <td>
                    <form method="POST" class="d-inline" onsubmit="return confirm('Yakin ingin mengubah status promo ini?')">
                      <input type="hidden" name="action" value="toggle_status">
                      <input type="hidden" name="id_promo" value="<?php echo $promo['id_promo']; ?>">
                      <input type="hidden" name="aktif" value="<?php echo $promo['aktif']; ?>">
                      <button type="submit" class="btn btn-sm <?php echo $promo['aktif'] == '1' ? 'btn-danger' : 'btn-success'; ?> ms-1">
                        <i class="bi bi-<?php echo $promo['aktif'] == '1' ? 'x-circle' : 'check-circle'; ?>"></i> 
                        <?php echo $promo['aktif'] == '1' ? 'Nonaktifkan' : 'Aktifkan'; ?>
                      </button>
                    </form>
                  </td>
                </tr>
                <?php endforeach; ?>
                <?php endif; ?>
              </tbody>
            </table>
          </div>

          <!-- Pagination -->
          <?php if ($total_pages > 1): ?>
          <nav aria-label="Page navigation">
            <ul class="pagination justify-content-center">
              <?php if ($page > 1): ?>
              <li class="page-item">
                <a class="page-link" href="?page=<?php echo $page - 1; ?><?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?><?php echo !empty($filter) ? '&filter=' . urlencode($filter) : ''; ?>">
                  <i class="bi bi-chevron-left"></i>
                </a>
              </li>
              <?php endif; ?>
              
              <?php for ($i = max(1, $page - 2); $i <= min($total_pages, $page + 2); $i++): ?>
              <li class="page-item <?php echo $i == $page ? 'active' : ''; ?>">
                <a class="page-link" href="?page=<?php echo $i; ?><?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?><?php echo !empty($filter) ? '&filter=' . urlencode($filter) : ''; ?>">
                  <?php echo $i; ?>
                </a>
              </li>
              <?php endfor; ?>
              
              <?php if ($page < $total_pages): ?>
              <li class="page-item">
                <a class="page-link" href="?page=<?php echo $page + 1; ?><?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?><?php echo !empty($filter) ? '&filter=' . urlencode($filter) : ''; ?>">
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

    <!-- Promo Modal -->
    <div class="modal fade" id="promoModal" tabindex="-1">
      <div class="modal-dialog modal-lg">
        <div class="modal-content">
          <div class="modal-header">
            <h5 class="modal-title" id="promoModalTitle">Tambah Promo</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
          </div>
          <form method="POST" id="promoForm">
            <div class="modal-body">
              <input type="hidden" name="action" id="promoAction" value="create">
              <input type="hidden" name="id_promo" id="promoId">
              
              <div class="row">
                <div class="col-md-6">
                  <div class="mb-3">
                    <label for="kode_promo" class="form-label">Kode Promo</label>
                    <input type="text" class="form-control" id="kode_promo" name="kode_promo" placeholder="Kosongkan untuk auto generate" onblur="checkPromoCode()">
                    <div id="kode_promo_feedback" class="form-text"></div>
                  </div>
                </div>
                <div class="col-md-6">
                  <div class="mb-3">
                    <label for="nama_promo" class="form-label">Nama Promo <span class="text-danger">*</span></label>
                    <input type="text" class="form-control" id="nama_promo" name="nama_promo" required maxlength="30">
                  </div>
                </div>
              </div>
              
              <div class="mb-3">
                <label for="deskripsi" class="form-label">Deskripsi</label>
                <textarea class="form-control" id="deskripsi" name="deskripsi" rows="3"></textarea>
              </div>
              
              <div class="row">
                <div class="col-md-6">
                  <div class="mb-3">
                    <label for="tanggalmulai_promo" class="form-label">Tanggal Mulai <span class="text-danger">*</span></label>
                    <input type="date" class="form-control" id="tanggalmulai_promo" name="tanggalmulai_promo" required onchange="updateMinEndDate()">
                  </div>
                </div>
                <div class="col-md-6">
                  <div class="mb-3">
                    <label for="tanggalselesai_promo" class="form-label">Tanggal Selesai <span class="text-danger">*</span></label>
                    <input type="date" class="form-control" id="tanggalselesai_promo" name="tanggalselesai_promo" required>
                  </div>
                </div>
              </div>
              
              <div class="row">
                <div class="col-md-6">
                  <div class="mb-3">
                    <label for="pilihan_promo" class="form-label">Jenis Promo <span class="text-danger">*</span></label>
                    <select class="form-select" id="pilihan_promo" name="pilihan_promo" required onchange="togglePromoValue()">
                      <option value="">Pilih Jenis Promo</option>
                      <option value="nominal">Nominal (Rp)</option>
                      <option value="persen">Persentase (%)</option>
                    </select>
                  </div>
                </div>
                <div class="col-md-6">
                  <div class="mb-3" id="nominalGroup" style="display: none;">
                    <label for="nominal" class="form-label">Nilai Nominal (Rp)</label>
                    <input type="number" class="form-control" id="nominal" name="nominal" min="0" step="0.01">
                  </div>
                  <div class="mb-3" id="persenGroup" style="display: none;">
                    <label for="persen" class="form-label">Nilai Persentase (%)</label>
                    <input type="number" class="form-control" id="persen" name="persen" min="0" max="100" step="0.01">
                  </div>
                </div>
              </div>
              
              <div class="row">
                <div class="col-md-6">
                  <div class="mb-3">
                    <label for="kuota" class="form-label">Kuota</label>
                    <input type="number" class="form-control" id="kuota" name="kuota" min="1" value="5">
                  </div>
                </div>
                <div class="col-md-6">
                  <div class="mb-3">
                    <label for="min_pembelian" class="form-label">Minimal Pembelian (Rp)</label>
                    <input type="number" class="form-control" id="min_pembelian" name="min_pembelian" min="0" step="0.01" value="0">
                  </div>
                </div>
              </div>
              
              <div class="mb-3">
                <div class="form-check">
                  <input class="form-check-input" type="checkbox" id="aktif" name="aktif" checked>
                  <label class="form-check-label" for="aktif">
                    Aktif
                  </label>
                </div>
              </div>
            </div>
            <div class="modal-footer">
              <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
              <button type="submit" class="btn btn-primary">Simpan</button>
            </div>
          </form>
        </div>
      </div>
    </div>

    <!-- Detail Modal -->
    <div class="modal fade" id="detailModal" tabindex="-1">
      <div class="modal-dialog modal-lg">
        <div class="modal-content">
          <div class="modal-header bg-primary text-white">
            <h5 class="modal-title"><i class="bi bi-info-circle me-2"></i>Detail Promo</h5>
            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
          </div>
          <div class="modal-body" id="detailContent">
            <!-- Content will be populated by JavaScript -->
          </div>
         
        </div>
      </div>
    </div>

    <script src="../js/bootstrap.bundle.min.js"></script>
    <script>
    function togglePromoValue() {
        const pilihan = document.getElementById('pilihan_promo').value;
        const nominalGroup = document.getElementById('nominalGroup');
        const persenGroup = document.getElementById('persenGroup');
        
        if (pilihan === 'nominal') {
            nominalGroup.style.display = 'block';
            persenGroup.style.display = 'none';
            document.getElementById('nominal').required = true;
            document.getElementById('persen').required = false;
            document.getElementById('persen').value = '';
        } else if (pilihan === 'persen') {
            nominalGroup.style.display = 'none';
            persenGroup.style.display = 'block';
            document.getElementById('persen').required = true;
            document.getElementById('nominal').required = false;
            document.getElementById('nominal').value = '';
        } else {
            nominalGroup.style.display = 'none';
            persenGroup.style.display = 'none';
            document.getElementById('nominal').required = false;
            document.getElementById('persen').required = false;
        }
    }
    
    function updateMinEndDate() {
        const startDate = document.getElementById('tanggalmulai_promo').value;
        const endDateInput = document.getElementById('tanggalselesai_promo');
        
        if (startDate) {
            endDateInput.min = startDate;
            
            // If end date is already set and is before start date, clear it
            if (endDateInput.value && endDateInput.value < startDate) {
                endDateInput.value = '';
            }
        }
    }
    
    // Check if promo code already exists
    function checkPromoCode() {
        const kodePromo = document.getElementById('kode_promo').value.trim();
        const feedback = document.getElementById('kode_promo_feedback');
        
        if (kodePromo === '') {
            feedback.innerHTML = '';
            feedback.className = 'form-text';
            return;
        }
        
        // Create AJAX request
        const xhr = new XMLHttpRequest();
        xhr.open('POST', '', true);
        xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
        
        xhr.onreadystatechange = function() {
            if (xhr.readyState === 4 && xhr.status === 200) {
                const response = JSON.parse(xhr.responseText);
                if (response.exists) {
                    feedback.innerHTML = '<i class="fas fa-times-circle text-danger"></i> Kode promo sudah digunakan!';
                    feedback.className = 'form-text text-danger';
                } else {
                    feedback.innerHTML = '<i class="fas fa-check-circle text-success"></i> Kode promo tersedia';
                    feedback.className = 'form-text text-success';
                }
            }
        };
        
        xhr.send('action=check_promo_code&kode_promo=' + encodeURIComponent(kodePromo));
    }
    
    // Set minimum date to today for start date
    document.addEventListener('DOMContentLoaded', function() {
        const today = new Date().toISOString().split('T')[0];
        document.getElementById('tanggalmulai_promo').min = today;
        document.getElementById('tanggalselesai_promo').min = today;
    })
    

    
    let currentPromoId = null;
    
    function showPromoDetail(promoJson) {
        const promo = JSON.parse(promoJson);
        currentPromoId = promo.id_promo;
        
        const statusBadge = promo.aktif == '1' ? 
            '<span class="badge bg-success"><i class="bi bi-check-circle me-1"></i>Aktif</span>' : 
            '<span class="badge bg-danger"><i class="bi bi-x-circle me-1"></i>Tidak Aktif</span>';
            
        const promoValue = promo.pilihan_promo === 'nominal' ? 
             `${promo.nilai_promo || 0}` : 
             `${promo.nilai_promo || 0}`;
            
        const content = `
            <div class="card border-0">
                <div class="card-body p-0">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <div class="border rounded p-3 h-100">
                                <h6 class="text-primary mb-3"><i class="bi bi-tag me-2"></i>Informasi Promo</h6>
                                <div class="mb-2">
                                    <small class="text-muted">Kode Promo</small>
                                    <div class="fw-bold">${promo.kode_promo}</div>
                                </div>
                                <div class="mb-2">
                                    <small class="text-muted">Nama Promo</small>
                                    <div class="fw-bold">${promo.nama_promo}</div>
                                </div>
                                <div class="mb-2">
                                    <small class="text-muted">Status</small>
                                    <div>${statusBadge}</div>
                                </div>
                                <div class="mb-2">
                                    <small class="text-muted">Deskripsi</small>
                                    <div>${promo.deskripsi || '<em class="text-muted">Tidak ada deskripsi</em>'}</div>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="border rounded p-3 h-100">
                                <h6 class="text-success mb-3"><i class="bi bi-percent me-2"></i>Detail Promo</h6>
                                <div class="mb-2">
                                    <small class="text-muted">Jenis Promo</small>
                                    <div class="fw-bold">${promo.pilihan_promo === 'nominal' ? 'Nominal (Rp)' : 'Persentase (%)'}</div>
                                </div>
                                <div class="mb-2">
                                    <small class="text-muted">Nilai Promo</small>
                                    <div class="fw-bold text-success fs-5">${promoValue}</div>
                                </div>
                                <div class="mb-2">
                                    <small class="text-muted">Minimal Pembelian</small>
                                    <div class="fw-bold">Rp ${new Intl.NumberFormat('id-ID').format(promo.min_pembelian)}</div>
                                </div>
                                <div class="mb-2">
                                    <small class="text-muted">Kuota</small>
                                    <div class="fw-bold">${promo.kuota} kali</div>
                                </div>
                            </div>
                        </div>
                        <div class="col-12">
                            <div class="border rounded p-3">
                                <h6 class="text-info mb-3"><i class="bi bi-calendar me-2"></i>Periode & Tracking</h6>
                                <div class="row">
                                    <div class="col-md-4">
                                        <small class="text-muted">Tanggal Mulai</small>
                                        <div class="fw-bold">${new Date(promo.tanggalmulai_promo).toLocaleDateString('id-ID', {weekday: 'long', year: 'numeric', month: 'long', day: 'numeric'})}</div>
                                    </div>
                                    <div class="col-md-4">
                                        <small class="text-muted">Tanggal Selesai</small>
                                        <div class="fw-bold">${new Date(promo.tanggalselesai_promo).toLocaleDateString('id-ID', {weekday: 'long', year: 'numeric', month: 'long', day: 'numeric'})}</div>
                                    </div>
                                    <div class="col-md-4">
                                         <small class="text-muted">Dibuat Oleh</small>
                                         <div class="fw-bold">${promo.insert_by}</div>
                                     </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        `;
        document.getElementById('detailContent').innerHTML = content;
    }
    

    

    
    // Reset form when modal is closed
    document.getElementById('promoModal').addEventListener('hidden.bs.modal', function() {
        if (document.getElementById('promoAction').value === 'create') {
            document.getElementById('promoForm').reset();
            document.getElementById('promoModalTitle').textContent = 'Tambah Promo';
            document.getElementById('aktif').checked = true;
            togglePromoValue();
        }
    });
    </script>
  </body>
</html>