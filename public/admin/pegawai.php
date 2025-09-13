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

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'create':
                $nama_lengkap = trim($_POST['nama_lengkap']);
                $jabatan = $_POST['jabatan'];
                $nomor_hp = trim($_POST['nomor_hp']);
                $email = trim($_POST['email']);
                $password = $_POST['password'];
                $aktif = isset($_POST['aktif']) ? 1 : 0;
                
                if (!empty($nama_lengkap) && !empty($jabatan) && !empty($nomor_hp) && !empty($email) && !empty($password)) {
                    // Check if email already exists
                    $stmt = $conn->prepare("SELECT id_user FROM pegawai WHERE email = ?");
                    $stmt->bind_param("s", $email);
                    $stmt->execute();
                    $result = $stmt->get_result();
                    if ($result->fetch_assoc()) {
                        $error = 'Email sudah digunakan!';
                    } else {
                        // Encrypt password using nomor_hp as key
                        $encrypted_password = encryptPassword($password, $nomor_hp);
                        $stmt = $conn->prepare("INSERT INTO pegawai (nama_lengkap, jabatan, nomor_hp, email, password, aktif) VALUES (?, ?, ?, ?, ?, ?)");
                        $stmt->bind_param("sssssi", $nama_lengkap, $jabatan, $nomor_hp, $email, $encrypted_password, $aktif);
                        if ($stmt->execute()) {
                            $message = 'Data pegawai berhasil ditambahkan!';
                        } else {
                            $error = 'Error: ' . $conn->error;
                        }
                    }
                } else {
                    $error = 'Semua field wajib diisi!';
                }
                break;
                
            case 'update':
                $id_user = $_POST['id_user'];
                $nama_lengkap = trim($_POST['nama_lengkap']);
                $jabatan = $_POST['jabatan'];
                $nomor_hp = trim($_POST['nomor_hp']);
                $email = trim($_POST['email']);
                $password = $_POST['password'];
                $aktif = isset($_POST['aktif']) ? 1 : 0;
                
                if (!empty($nama_lengkap) && !empty($jabatan) && !empty($nomor_hp) && !empty($email)) {
                    // Check if email already exists for other users
                    $stmt = $conn->prepare("SELECT id_user FROM pegawai WHERE email = ? AND id_user != ?");
                    $stmt->bind_param("si", $email, $id_user);
                    $stmt->execute();
                    $result = $stmt->get_result();
                    if ($result->fetch_assoc()) {
                        $error = 'Email sudah digunakan oleh pegawai lain!';
                    } else {
                        if (!empty($password)) {
                            // Encrypt password using nomor_hp as key
                            $encrypted_password = encryptPassword($password, $nomor_hp);
                            $stmt = $conn->prepare("UPDATE pegawai SET nama_lengkap = ?, jabatan = ?, nomor_hp = ?, email = ?, password = ?, aktif = ? WHERE id_user = ?");
                            $stmt->bind_param("sssssii", $nama_lengkap, $jabatan, $nomor_hp, $email, $encrypted_password, $aktif, $id_user);
                        } else {
                            $stmt = $conn->prepare("UPDATE pegawai SET nama_lengkap = ?, jabatan = ?, nomor_hp = ?, email = ?, aktif = ? WHERE id_user = ?");
                            $stmt->bind_param("ssssii", $nama_lengkap, $jabatan, $nomor_hp, $email, $aktif, $id_user);
                        }
                        if ($stmt->execute()) {
                            $message = 'Data pegawai berhasil diperbarui!';
                        } else {
                            $error = 'Error: ' . $conn->error;
                        }
                    }
                } else {
                    $error = 'Nama lengkap, jabatan, nomor HP, dan email harus diisi!';
                }
                break;
                
            case 'delete':
                $id_user = $_POST['id_user'];
                $stmt = $conn->prepare("DELETE FROM pegawai WHERE id_user = ?");
                $stmt->bind_param("i", $id_user);
                if ($stmt->execute()) {
                    $message = 'Data pegawai berhasil dihapus!';
                } else {
                    $error = 'Error: ' . $conn->error;
                }
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
    $where_conditions[] = "(nama_lengkap LIKE ? OR email LIKE ? OR nomor_hp LIKE ?)";
    $search_param = "%$search%";
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
    $types .= 'sss';
}

if (!empty($filter) && in_array($filter, ['Admin', 'Kasir', 'Koki', 'Pelayan'])) {
    $where_conditions[] = "jabatan = ?";
    $params[] = $filter;
    $types .= 's';
}

$where_clause = !empty($where_conditions) ? 'WHERE ' . implode(' AND ', $where_conditions) : '';

// Get total count for pagination
$count_sql = "SELECT COUNT(*) as total FROM pegawai $where_clause";
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

// Get pegawai data with pagination
$sql = "SELECT * FROM pegawai $where_clause ORDER BY id_user DESC LIMIT $limit OFFSET $offset";
if (!empty($params)) {
    $stmt = $conn->prepare($sql);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $result = $stmt->get_result();
    $pegawais = $result->fetch_all(MYSQLI_ASSOC);
} else {
    $result = $conn->query($sql);
    if ($result) {
        $pegawais = $result->fetch_all(MYSQLI_ASSOC);
    } else {
        $error = 'Error fetching data: ' . $conn->error;
        $pegawais = [];
    }
}

// Get single pegawai for editing
$edit_pegawai = null;
if (isset($_GET['edit'])) {
    $stmt = $conn->prepare("SELECT * FROM pegawai WHERE id_user = ?");
    $stmt->bind_param("i", $_GET['edit']);
    $stmt->execute();
    $result = $stmt->get_result();
    $edit_pegawai = $result->fetch_assoc();
}

// Jabatan options
$jabatan_options = ['admin', 'kasir', 'koki', 'pelayan'];
?>

<!doctype html>
<html lang="en">
  <head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Pegawai - Admin Dashboard</title>
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
                <div class="collapse show" id="masterMenu">
                  <ul class="nav flex-column ms-3">
                    <li class="nav-item"><a class="nav-link" href="resto.php"><i class="bi bi-building"></i> Resto</a></li>
                    <li class="nav-item"><a class="nav-link active" href="pegawai.php"><i class="bi bi-people"></i> Pegawai</a></li>
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
              
              <?php if($_SESSION["jabatan"] == "Admin"): ?>
              <!-- Penjualan Menu - Admin Only -->
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
                  <i class="bi bi-gear-fill"></i>
                  <span>Master</span>
                  <i class="bi bi-chevron-down ms-auto"></i>
                </a>
                <div class="collapse show" id="masterMenuMobile">
                  <ul class="nav flex-column ms-3">
                    <li class="nav-item"><a class="nav-link" href="resto.php"><i class="bi bi-building"></i> Resto</a></li>
                    <li class="nav-item"><a class="nav-link active" href="pegawai.php"><i class="bi bi-people"></i> Pegawai</a></li>
                    <li class="nav-item"><a class="nav-link" href="vendor.php"><i class="bi bi-truck"></i> Vendor</a></li>
                    <li class="nav-item"><a class="nav-link" href="meja.php"><i class="bi bi-table"></i> Meja</a></li>
                    <li class="nav-item"><a class="nav-link" href="metode_pembayaran.php"><i class="bi bi-credit-card"></i> Metode Pembayaran</a></li>
                    <li class="nav-item"><a class="nav-link" href="bayar.php"><i class="bi bi-wallet2"></i> Bayar</a></li>
                  </ul>
                </div>
              </li>
              <?php endif; ?>
              
              <?php if($_SESSION["jabatan"] == "Admin" || $_SESSION["jabatan"] == "Dapur"): ?>
              <li class="nav-item">
                <a class="nav-link" data-bs-toggle="collapse" href="#produkMenuMobile" role="button">
                  <i class="bi bi-box-seam"></i>
                  <span>Produk</span>
                  <i class="bi bi-chevron-down ms-auto"></i>
                </a>
                <div class="collapse" id="produkMenuMobile">
                  <ul class="nav flex-column ms-3">
                    <li class="nav-item"><a class="nav-link" href="kategori_menu.php"><i class="bi bi-tags"></i> Kategori Menu</a></li>
                    <li class="nav-item"><a class="nav-link" href="menu.php"><i class="bi bi-card-list"></i> Menu</a></li>
                    <li class="nav-item"><a class="nav-link" href="kategori_bahan.php"><i class="bi bi-collection"></i> Kategori Bahan</a></li>
                    <li class="nav-item"><a class="nav-link" href="bahan.php"><i class="bi bi-basket"></i> Bahan</a></li>
                    <li class="nav-item"><a class="nav-link" href="resep.php"><i class="bi bi-journal-text"></i> Resep</a></li>
                  </ul>
                </div>
              </li>
              
              <li class="nav-item">
                <a class="nav-link" data-bs-toggle="collapse" href="#pembelianMenuMobile" role="button">
                  <i class="bi bi-cart-plus"></i>
                  <span>Pembelian</span>
                  <i class="bi bi-chevron-down ms-auto"></i>
                </a>
                <div class="collapse" id="pembelianMenuMobile">
                  <ul class="nav flex-column ms-3">
                    <li class="nav-item"><a class="nav-link" href="pembelian.php"><i class="bi bi-clipboard-check"></i> Pesanan Pembelian</a></li>
                    <li class="nav-item"><a class="nav-link" href="pembayaran_pembelian.php"><i class="bi bi-currency-dollar"></i> Pembayaran</a></li>
                  </ul>
                </div>
              </li>
              <?php endif; ?>
              
              <?php if($_SESSION["jabatan"] == "Admin"): ?>
              <li class="nav-item">
                <a class="nav-link" data-bs-toggle="collapse" href="#penjualanMenuMobile" role="button">
                  <i class="bi bi-cash-coin"></i>
                  <span>Penjualan</span>
                  <i class="bi bi-chevron-down ms-auto"></i>
                </a>
                <div class="collapse" id="penjualanMenuMobile">
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
              <li class="nav-item">
                <a class="nav-link" data-bs-toggle="collapse" href="#inventoryMenuMobile" role="button">
                  <i class="bi bi-boxes"></i>
                  <span>Inventory</span>
                  <i class="bi bi-chevron-down ms-auto"></i>
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
                  <i class="bi bi-graph-up"></i>
                  <span>Laporan</span>
                  <i class="bi bi-chevron-down ms-auto"></i>
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
                  <i class="bi bi-gear"></i>
                  <span>Pengaturan</span>
                </a>
              </li>
            </ul>
          </div>
        </div>

        <!-- Main Content -->
        <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
          <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
            <h1 class="h2">Manajemen Pegawai</h1>
            <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#pegawaiModal">
              <i class="bi bi-plus-circle"></i> Tambah Pegawai
            </button>
          </div>

          <!-- Alert Messages -->
          <?php if ($message): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
              <?php echo htmlspecialchars($message); ?>
              <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
          <?php endif; ?>

          <?php if ($error): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
              <?php echo htmlspecialchars($error); ?>
              <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
          <?php endif; ?>

          <!-- Search and Filter -->
          <div class="row mb-3">
            <div class="col-md-4">
              <form method="GET" class="d-flex">
                <input type="text" class="form-control me-2" name="search" placeholder="Cari nama, email, atau HP..." value="<?php echo htmlspecialchars($search); ?>">
                <button type="submit" class="btn btn-outline-primary">
                  <i class="bi bi-search"></i>
                </button>
                <?php if (!empty($search)): ?>
                  <a href="?" class="btn btn-outline-secondary ms-2">
                    <i class="bi bi-x-circle"></i>
                  </a>
                <?php endif; ?>
              </form>
            </div>
            <div class="col-md-4">
              <form method="GET" class="d-flex">
                <?php if (!empty($search)): ?>
                  <input type="hidden" name="search" value="<?php echo htmlspecialchars($search); ?>">
                <?php endif; ?>
                <select class="form-select me-2" name="filter" onchange="this.form.submit()">
                  <option value="">Semua Jabatan</option>
                  <option value="Admin" <?php echo $filter == 'Admin' ? 'selected' : ''; ?>>Admin</option>
                  <option value="Kasir" <?php echo $filter == 'Kasir' ? 'selected' : ''; ?>>Kasir</option>
                  <option value="Koki" <?php echo $filter == 'Koki' ? 'selected' : ''; ?>>Koki</option>
                  <option value="Pelayan" <?php echo $filter == 'Pelayan' ? 'selected' : ''; ?>>Pelayan</option>
                </select>
                <?php if (!empty($filter)): ?>
                  <a href="?<?php echo !empty($search) ? 'search=' . urlencode($search) : ''; ?>" class="btn btn-outline-secondary">
                    <i class="bi bi-x-circle"></i>
                  </a>
                <?php endif; ?>
              </form>
            </div>
            <div class="col-md-4 text-end">
              <small class="text-muted">
                Menampilkan <?php echo count($pegawais); ?> dari <?php echo $total_records; ?> data
              </small>
            </div>
          </div>

          <!-- Data Table -->
          <div class="table-responsive">
            <table class="table table-striped table-hover">
              <thead class="table-dark">
                <tr>
                  <th>No</th>
                  <th>Nama Lengkap</th>
                  <th>Jabatan</th>
                  <th class="d-none d-md-table-cell">Nomor HP</th>
                  <th class="d-none d-lg-table-cell">Email</th>
                  <th class="d-none d-md-table-cell">Status</th>
                  <th>Aksi</th>
                </tr>
              </thead>
              <tbody>
                <?php if (empty($pegawais)): ?>
                  <tr>
                    <td colspan="7" class="text-center">Tidak ada data pegawai</td>
                  </tr>
                <?php else: ?>
                  <?php 
                  $no = $offset + 1;
                  foreach ($pegawais as $pegawai): 
                  ?>
                    <tr>
                      <td><?php echo $no++; ?></td>
                      <td><?php echo htmlspecialchars($pegawai['nama_lengkap']); ?></td>
                      <td>
                        <span class="badge bg-<?php echo $pegawai['jabatan'] == 'admin' ? 'danger' : ($pegawai['jabatan'] == 'kasir' ? 'primary' : ($pegawai['jabatan'] == 'koki' ? 'warning' : 'info')); ?>">
                          <?php echo ucfirst(htmlspecialchars($pegawai['jabatan'])); ?>
                        </span>
                      </td>
                      <td class="d-none d-md-table-cell"><?php echo htmlspecialchars($pegawai['nomor_hp']); ?></td>
                      <td class="d-none d-lg-table-cell"><?php echo htmlspecialchars($pegawai['email']); ?></td>
                      <td class="d-none d-md-table-cell">
                        <span class="badge bg-<?php echo $pegawai['aktif'] ? 'success' : 'secondary'; ?>">
                          <?php echo $pegawai['aktif'] ? 'Aktif' : 'Tidak Aktif'; ?>
                        </span>
                      </td>
                      <td>
                        <div class="btn-group" role="group">
                          <a href="?edit=<?php echo $pegawai['id_user']; ?><?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?><?php echo !empty($filter) ? '&filter=' . urlencode($filter) : ''; ?>&page=<?php echo $page; ?>" class="btn btn-sm btn-warning">
                            <i class="bi bi-pencil"></i>
                          </a>
                          <form method="POST" style="display: inline;" onsubmit="return confirm('Yakin ingin menghapus data ini?')">
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="id_user" value="<?php echo $pegawai['id_user']; ?>">
                            <button type="submit" class="btn btn-sm btn-danger">
                              <i class="bi bi-trash"></i>
                            </button>
                          </form>
                        </div>
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
                <!-- Previous Page -->
                <?php if ($page > 1): ?>
                  <li class="page-item">
                    <a class="page-link" href="?page=<?php echo ($page - 1); ?><?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?><?php echo !empty($filter) ? '&filter=' . urlencode($filter) : ''; ?>">
                      <i class="bi bi-chevron-left"></i> Previous
                    </a>
                  </li>
                <?php else: ?>
                  <li class="page-item disabled">
                    <span class="page-link"><i class="bi bi-chevron-left"></i> Previous</span>
                  </li>
                <?php endif; ?>

                <!-- Page Numbers -->
                <?php
                $start_page = max(1, $page - 2);
                $end_page = min($total_pages, $page + 2);
                
                if ($start_page > 1): ?>
                  <li class="page-item">
                    <a class="page-link" href="?page=1<?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?><?php echo !empty($filter) ? '&filter=' . urlencode($filter) : ''; ?>">1</a>
                  </li>
                  <?php if ($start_page > 2): ?>
                    <li class="page-item disabled"><span class="page-link">...</span></li>
                  <?php endif; ?>
                <?php endif; ?>

                <?php for ($i = $start_page; $i <= $end_page; $i++): ?>
                  <li class="page-item <?php echo ($i == $page) ? 'active' : ''; ?>">
                    <a class="page-link" href="?page=<?php echo $i; ?><?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?><?php echo !empty($filter) ? '&filter=' . urlencode($filter) : ''; ?>"><?php echo $i; ?></a>
                  </li>
                <?php endfor; ?>

                <?php if ($end_page < $total_pages): ?>
                  <?php if ($end_page < $total_pages - 1): ?>
                    <li class="page-item disabled"><span class="page-link">...</span></li>
                  <?php endif; ?>
                  <li class="page-item">
                    <a class="page-link" href="?page=<?php echo $total_pages; ?><?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?><?php echo !empty($filter) ? '&filter=' . urlencode($filter) : ''; ?>"><?php echo $total_pages; ?></a>
                  </li>
                <?php endif; ?>

                <!-- Next Page -->
                <?php if ($page < $total_pages): ?>
                  <li class="page-item">
                    <a class="page-link" href="?page=<?php echo ($page + 1); ?><?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?><?php echo !empty($filter) ? '&filter=' . urlencode($filter) : ''; ?>">
                      Next <i class="bi bi-chevron-right"></i>
                    </a>
                  </li>
                <?php else: ?>
                  <li class="page-item disabled">
                    <span class="page-link">Next <i class="bi bi-chevron-right"></i></span>
                  </li>
                <?php endif; ?>
              </ul>
            </nav>
          <?php endif; ?>
        </main>
       </div>
     </div>

     <!-- Modal for Add/Edit Pegawai -->
    <div class="modal fade" id="pegawaiModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><?php echo $edit_pegawai ? 'Edit Pegawai' : 'Tambah Pegawai'; ?></h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="<?php echo $edit_pegawai ? 'update' : 'create'; ?>">
                        <?php if ($edit_pegawai): ?>
                            <input type="hidden" name="id_user" value="<?php echo $edit_pegawai['id_user']; ?>">
                        <?php endif; ?>
                        
                        <div class="mb-3">
                            <label for="nama_lengkap" class="form-label">Nama Lengkap *</label>
                            <input type="text" class="form-control" id="nama_lengkap" name="nama_lengkap" 
                                   value="<?php echo $edit_pegawai ? htmlspecialchars($edit_pegawai['nama_lengkap']) : ''; ?>" required>
                        </div>
                        
                        <div class="mb-3">
                            <label for="jabatan" class="form-label">Jabatan *</label>
                            <select class="form-select" id="jabatan" name="jabatan" required>
                                <option value="">Pilih Jabatan</option>
                                <?php foreach ($jabatan_options as $option): ?>
                                    <option value="<?php echo $option; ?>" <?php echo ($edit_pegawai && $edit_pegawai['jabatan'] == $option) ? 'selected' : ''; ?>>
                                        <?php echo ucfirst($option); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="mb-3">
                            <label for="nomor_hp" class="form-label">Nomor HP *</label>
                            <input type="text" class="form-control" id="nomor_hp" name="nomor_hp" 
                                   value="<?php echo $edit_pegawai ? htmlspecialchars($edit_pegawai['nomor_hp']) : ''; ?>" required>
                        </div>
                        
                        <div class="mb-3">
                            <label for="email" class="form-label">Email *</label>
                            <input type="email" class="form-control" id="email" name="email" 
                                   value="<?php echo $edit_pegawai ? htmlspecialchars($edit_pegawai['email']) : ''; ?>" required>
                        </div>
                        
                        <div class="mb-3">
                            <label for="password" class="form-label">Password <?php echo $edit_pegawai ? '(kosongkan jika tidak ingin mengubah)' : '*'; ?></label>
                            <input type="password" class="form-control" id="password" name="password" <?php echo !$edit_pegawai ? 'required' : ''; ?>>
                        </div>
                        
                        <div class="mb-3 form-check">
                            <input type="checkbox" class="form-check-input" id="aktif" name="aktif" 
                                   <?php echo ($edit_pegawai && $edit_pegawai['aktif']) || !$edit_pegawai ? 'checked' : ''; ?>>
                            <label class="form-check-label" for="aktif">
                                Aktif
                            </label>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                        <button type="submit" class="btn btn-primary"><?php echo $edit_pegawai ? 'Update' : 'Simpan'; ?></button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="../js/bootstrap.bundle.min.js"></script>
    <script>
        // Auto show modal if editing
        <?php if ($edit_pegawai): ?>
            var modal = new bootstrap.Modal(document.getElementById('pegawaiModal'));
            modal.show();
        <?php endif; ?>
    </script>
</body>
</html>