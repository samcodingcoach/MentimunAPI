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

// Function to generate vendor code
function generateVendorCode($conn) {
    $year = date('y'); // 2 digit year
    $prefix = "VD{$year}-";
    
    // Get the last vendor code for this year
    $stmt = $conn->prepare("SELECT kode_vendor FROM vendor WHERE kode_vendor LIKE ? ORDER BY kode_vendor DESC LIMIT 1");
    $like_pattern = $prefix . '%';
    $stmt->bind_param("s", $like_pattern);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($row = $result->fetch_assoc()) {
        // Extract number from last code and increment
        $last_code = $row['kode_vendor'];
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
                $nama_vendor = trim($_POST['nama_vendor']);
                $alamat = trim($_POST['alamat']);
                $kota = trim($_POST['kota']);
                $hp = trim($_POST['hp']);
                $nomor_rekening1 = trim($_POST['nomor_rekening1']);
                $nomor_rekening2 = trim($_POST['nomor_rekening2']);
                $person = trim($_POST['person']);
                $email = trim($_POST['email']);
                $status = isset($_POST['status']) ? '1' : '0';
                $keterangan = trim($_POST['keterangan']);
                
                if (!empty($nama_vendor) && !empty($alamat) && !empty($kota) && !empty($hp) && !empty($email)) {
                    // Check if email already exists
                    $stmt = $conn->prepare("SELECT id_vendor FROM vendor WHERE email = ?");
                    $stmt->bind_param("s", $email);
                    $stmt->execute();
                    $result = $stmt->get_result();
                    if ($result->fetch_assoc()) {
                        $error = 'Email sudah digunakan!';
                    } else {
                        $kode_vendor = generateVendorCode($conn);
                        $stmt = $conn->prepare("INSERT INTO vendor (nama_vendor, alamat, kota, hp, nomor_rekening1, nomor_rekening2, person, email, status, keterangan, kode_vendor) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                        $stmt->bind_param("sssssssssss", $nama_vendor, $alamat, $kota, $hp, $nomor_rekening1, $nomor_rekening2, $person, $email, $status, $keterangan, $kode_vendor);
                        if ($stmt->execute()) {
                            $message = 'Data vendor berhasil ditambahkan!';
                        } else {
                            $error = 'Error: ' . $conn->error;
                        }
                    }
                } else {
                    $error = 'Nama vendor, alamat, kota, HP, dan email wajib diisi!';
                }
                break;
                
            case 'update':
                $id_vendor = $_POST['id_vendor'];
                $nama_vendor = trim($_POST['nama_vendor']);
                $alamat = trim($_POST['alamat']);
                $kota = trim($_POST['kota']);
                $hp = trim($_POST['hp']);
                $nomor_rekening1 = trim($_POST['nomor_rekening1']);
                $nomor_rekening2 = trim($_POST['nomor_rekening2']);
                $person = trim($_POST['person']);
                $email = trim($_POST['email']);
                $status = isset($_POST['status']) ? '1' : '0';
                $keterangan = trim($_POST['keterangan']);
                
                if (!empty($nama_vendor) && !empty($alamat) && !empty($kota) && !empty($hp) && !empty($email)) {
                    // Check if email already exists for other vendors
                    $stmt = $conn->prepare("SELECT id_vendor FROM vendor WHERE email = ? AND id_vendor != ?");
                    $stmt->bind_param("si", $email, $id_vendor);
                    $stmt->execute();
                    $result = $stmt->get_result();
                    if ($result->fetch_assoc()) {
                        $error = 'Email sudah digunakan oleh vendor lain!';
                    } else {
                        $stmt = $conn->prepare("UPDATE vendor SET nama_vendor = ?, alamat = ?, kota = ?, hp = ?, nomor_rekening1 = ?, nomor_rekening2 = ?, person = ?, email = ?, status = ?, keterangan = ? WHERE id_vendor = ?");
                        $stmt->bind_param("ssssssssssi", $nama_vendor, $alamat, $kota, $hp, $nomor_rekening1, $nomor_rekening2, $person, $email, $status, $keterangan, $id_vendor);
                        if ($stmt->execute()) {
                            $message = 'Data vendor berhasil diperbarui!';
                        } else {
                            $error = 'Error: ' . $conn->error;
                        }
                    }
                } else {
                    $error = 'Nama vendor, alamat, kota, HP, dan email harus diisi!';
                }
                break;
                
            case 'delete':
                $id_vendor = $_POST['id_vendor'];
                $stmt = $conn->prepare("DELETE FROM vendor WHERE id_vendor = ?");
                $stmt->bind_param("i", $id_vendor);
                if ($stmt->execute()) {
                    $message = 'Data vendor berhasil dihapus!';
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
    $where_conditions[] = "(nama_vendor LIKE ? OR email LIKE ? OR hp LIKE ? OR kota LIKE ? OR kode_vendor LIKE ?)";
    $search_param = "%$search%";
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
    $types .= 'sssss';
}

if (!empty($filter) && in_array($filter, ['1', '0'])) {
    $where_conditions[] = "status = ?";
    $params[] = $filter;
    $types .= 's';
}

$where_clause = !empty($where_conditions) ? 'WHERE ' . implode(' AND ', $where_conditions) : '';

// Get total count for pagination
$count_sql = "SELECT COUNT(*) as total FROM vendor $where_clause";
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

// Get vendor data with pagination
$sql = "SELECT * FROM vendor $where_clause ORDER BY id_vendor DESC LIMIT $limit OFFSET $offset";
if (!empty($params)) {
    $stmt = $conn->prepare($sql);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $result = $stmt->get_result();
    $vendors = $result->fetch_all(MYSQLI_ASSOC);
} else {
    $result = $conn->query($sql);
    if ($result) {
        $vendors = $result->fetch_all(MYSQLI_ASSOC);
    } else {
        $error = 'Error fetching data: ' . $conn->error;
        $vendors = [];
    }
}

// Get single vendor for editing
$edit_vendor = null;
if (isset($_GET['edit'])) {
    $stmt = $conn->prepare("SELECT * FROM vendor WHERE id_vendor = ?");
    $stmt->bind_param("i", $_GET['edit']);
    $stmt->execute();
    $result = $stmt->get_result();
    $edit_vendor = $result->fetch_assoc();
}
?>

<!doctype html>
<html lang="en">
  <head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Vendor - Admin Dashboard</title>
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
                <a class="nav-link" href="#">
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
                    <li class="nav-item"><a class="nav-link active" href="vendor.php"><i class="bi bi-truck"></i> Vendor</a></li>
                    <li class="nav-item"><a class="nav-link" href="#"><i class="bi bi-table"></i> Meja</a></li>
                    <li class="nav-item"><a class="nav-link" href="#"><i class="bi bi-credit-card"></i> Metode Pembayaran</a></li>
                    <li class="nav-item"><a class="nav-link" href="#"><i class="bi bi-cash"></i> Bayar</a></li>
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
                    <li class="nav-item"><a class="nav-link" href="#"><i class="bi bi-tags"></i> Kategori Menu</a></li>
                    <li class="nav-item"><a class="nav-link" href="#"><i class="bi bi-list"></i> Menu</a></li>
                    <li class="nav-item"><a class="nav-link" href="#"><i class="bi bi-tags"></i> Kategori Bahan</a></li>
                    <li class="nav-item"><a class="nav-link" href="#"><i class="bi bi-egg"></i> Bahan</a></li>
                    <li class="nav-item"><a class="nav-link" href="#"><i class="bi bi-book"></i> Resep</a></li>
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
                    <li class="nav-item"><a class="nav-link" href="#"><i class="bi bi-cart-plus"></i> Pesanan Pembelian</a></li>
                    <li class="nav-item"><a class="nav-link" href="#"><i class="bi bi-credit-card"></i> Pembayaran</a></li>
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
                <div class="collapse" id="penjualanMenu">
                  <ul class="nav flex-column ms-3">
                    <li class="nav-item"><a class="nav-link" href="#"><i class="bi bi-clock"></i> Shift Kasir</a></li>
                    <li class="nav-item"><a class="nav-link" href="#"><i class="bi bi-receipt"></i> Biaya Lain</a></li>
                    <li class="nav-item"><a class="nav-link" href="#"><i class="bi bi-calculator"></i> Harga Pokok Penjualan</a></li>
                    <li class="nav-item"><a class="nav-link" href="#"><i class="bi bi-tag"></i> Harga Rilis</a></li>
                    <li class="nav-item"><a class="nav-link" href="#"><i class="bi bi-x-circle"></i> Pembatalan</a></li>
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
                    <li class="nav-item"><a class="nav-link" href="#"><i class="bi bi-box-seam"></i> Inventory</a></li>
                    <li class="nav-item"><a class="nav-link" href="#"><i class="bi bi-arrow-left-right"></i> Transaksi</a></li>
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
                    <li class="nav-item"><a class="nav-link" href="#"><i class="bi bi-list-ul"></i> Transaksi</a></li>
                    <li class="nav-item"><a class="nav-link" href="#"><i class="bi bi-bar-chart"></i> Pengeluaran vs Penjualan</a></li>
                    <li class="nav-item"><a class="nav-link" href="#"><i class="bi bi-pie-chart"></i> Kuantitas</a></li>
                  </ul>
                </div>
              </li>
              
              <li class="nav-item">
                <a class="nav-link" href="#">
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
                  <i class="bi bi-house"></i>
                  <span>Beranda</span>
                </a>
              </li>
              
              <li class="nav-item">
                <a class="nav-link" href="#">
                  <i class="bi bi-info-circle"></i>
                  <span>Informasi</span>
                </a>
              </li>
              
              <?php if($_SESSION["jabatan"] == "Admin"): ?>
              <li class="nav-item">
                <a class="nav-link" data-bs-toggle="collapse" href="#masterMenuMobile" role="button">
                  <i class="bi bi-folder"></i>
                  <span>Master</span>
                  <i class="bi bi-chevron-down ms-auto"></i>
                </a>
                <div class="collapse" id="masterMenuMobile">
                  <ul class="nav flex-column ms-3">
                    <li class="nav-item"><a class="nav-link" href="resto.php"><i class="bi bi-building"></i> Resto</a></li>
                    <li class="nav-item"><a class="nav-link" href="pegawai.php"><i class="bi bi-people"></i> Pegawai</a></li>
                    <li class="nav-item"><a class="nav-link" href="vendor.php"><i class="bi bi-truck"></i> Vendor</a></li>
                    <li class="nav-item"><a class="nav-link" href="#"><i class="bi bi-table"></i> Meja</a></li>
                    <li class="nav-item"><a class="nav-link" href="#"><i class="bi bi-credit-card"></i> Metode Pembayaran</a></li>
                    <li class="nav-item"><a class="nav-link" href="#"><i class="bi bi-cash"></i> Bayar</a></li>
                  </ul>
                </div>
              </li>
              <?php endif; ?>
              
              <?php if($_SESSION["jabatan"] == "Admin" || $_SESSION["jabatan"] == "Dapur"): ?>
              <li class="nav-item">
                <a class="nav-link" data-bs-toggle="collapse" href="#produkMenuMobile" role="button">
                  <i class="bi bi-box"></i>
                  <span>Produk</span>
                  <i class="bi bi-chevron-down ms-auto"></i>
                </a>
                <div class="collapse" id="produkMenuMobile">
                  <ul class="nav flex-column ms-3">
                    <li class="nav-item"><a class="nav-link" href="#"><i class="bi bi-tags"></i> Kategori Menu</a></li>
                    <li class="nav-item"><a class="nav-link" href="#"><i class="bi bi-list"></i> Menu</a></li>
                    <li class="nav-item"><a class="nav-link" href="#"><i class="bi bi-tags"></i> Kategori Bahan</a></li>
                    <li class="nav-item"><a class="nav-link" href="#"><i class="bi bi-egg"></i> Bahan</a></li>
                    <li class="nav-item"><a class="nav-link" href="#"><i class="bi bi-book"></i> Resep</a></li>
                  </ul>
                </div>
              </li>
              
              <li class="nav-item">
                <a class="nav-link" data-bs-toggle="collapse" href="#pembelianMenuMobile" role="button">
                  <i class="bi bi-cart"></i>
                  <span>Pembelian</span>
                  <i class="bi bi-chevron-down ms-auto"></i>
                </a>
                <div class="collapse" id="pembelianMenuMobile">
                  <ul class="nav flex-column ms-3">
                    <li class="nav-item"><a class="nav-link" href="#"><i class="bi bi-cart-plus"></i> Pesanan Pembelian</a></li>
                    <li class="nav-item"><a class="nav-link" href="#"><i class="bi bi-credit-card"></i> Pembayaran</a></li>
                  </ul>
                </div>
              </li>
              <?php endif; ?>
              
              <?php if($_SESSION["jabatan"] == "Admin" || $_SESSION["jabatan"] == "Kasir"): ?>
              <li class="nav-item">
                <a class="nav-link" data-bs-toggle="collapse" href="#penjualanMenuMobile" role="button">
                  <i class="bi bi-cash-stack"></i>
                  <span>Penjualan</span>
                  <i class="bi bi-chevron-down ms-auto"></i>
                </a>
                <div class="collapse" id="penjualanMenuMobile">
                  <ul class="nav flex-column ms-3">
                    <li class="nav-item"><a class="nav-link" href="#"><i class="bi bi-clock"></i> Shift Kasir</a></li>
                    <li class="nav-item"><a class="nav-link" href="#"><i class="bi bi-receipt"></i> Biaya Lain</a></li>
                    <li class="nav-item"><a class="nav-link" href="#"><i class="bi bi-calculator"></i> Harga Pokok Penjualan</a></li>
                    <li class="nav-item"><a class="nav-link" href="#"><i class="bi bi-tag"></i> Harga Rilis</a></li>
                    <li class="nav-item"><a class="nav-link" href="#"><i class="bi bi-x-circle"></i> Pembatalan</a></li>
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
                    <li class="nav-item"><a class="nav-link" href="#"><i class="bi bi-box-seam"></i> Inventory</a></li>
                    <li class="nav-item"><a class="nav-link" href="#"><i class="bi bi-arrow-left-right"></i> Transaksi</a></li>
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
                    <li class="nav-item"><a class="nav-link" href="#"><i class="bi bi-list-ul"></i> Transaksi</a></li>
                    <li class="nav-item"><a class="nav-link" href="#"><i class="bi bi-bar-chart"></i> Pengeluaran vs Penjualan</a></li>
                    <li class="nav-item"><a class="nav-link" href="#"><i class="bi bi-pie-chart"></i> Kuantitas</a></li>
                  </ul>
                </div>
              </li>
              
              <li class="nav-item">
                <a class="nav-link" href="#">
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
            <h1 class="h2">Manajemen Vendor</h1>
            <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#vendorModal">
              <i class="bi bi-plus-circle"></i> Tambah Vendor
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
                <input type="text" class="form-control me-2" name="search" placeholder="Cari nama, email, HP, kota, atau kode..." value="<?php echo htmlspecialchars($search); ?>">
                <button class="btn btn-outline-secondary" type="submit">
                  <i class="bi bi-search"></i>
                </button>
              </form>
            </div>
            <div class="col-md-3">
              <form method="GET" class="d-flex">
                <select class="form-select me-2" name="filter" onchange="this.form.submit()">
                  <option value="">Semua Status</option>
                  <option value="1" <?php echo $filter === '1' ? 'selected' : ''; ?>>Aktif</option>
                  <option value="0" <?php echo $filter === '0' ? 'selected' : ''; ?>>Tidak Aktif</option>
                </select>
                <?php if (!empty($search)): ?>
                  <input type="hidden" name="search" value="<?php echo htmlspecialchars($search); ?>">
                <?php endif; ?>
              </form>
            </div>
            <div class="col-md-5 text-end">
              <small class="text-muted">
                Showing <?php echo $offset + 1; ?> to <?php echo min($offset + $limit, $total_records); ?> of <?php echo $total_records; ?> entries
              </small>
            </div>
          </div>

          <!-- Data Table -->
          <div class="table-responsive">
            <table class="table table-striped table-hover">
              <thead class="table-dark">
                <tr>
                  <th>No</th>
                  <th>Kode Vendor</th>
                  <th>Nama Vendor</th>
                  <th class="d-none d-md-table-cell">Kota</th>
                  <th class="d-none d-lg-table-cell">HP</th>
                  <th class="d-none d-lg-table-cell">Email</th>
                  <th class="d-none d-md-table-cell">Status</th>
                  <th>Aksi</th>
                </tr>
              </thead>
              <tbody>
                <?php if (!empty($vendors)): ?>
                  <?php foreach ($vendors as $index => $vendor): ?>
                    <tr>
                      <td><?php echo $offset + $index + 1; ?></td>
                      <td><?php echo htmlspecialchars($vendor['kode_vendor']); ?></td>
                      <td><?php echo htmlspecialchars($vendor['nama_vendor']); ?></td>
                      <td class="d-none d-md-table-cell"><?php echo htmlspecialchars($vendor['kota']); ?></td>
                      <td class="d-none d-lg-table-cell"><?php echo htmlspecialchars($vendor['hp']); ?></td>
                      <td class="d-none d-lg-table-cell"><?php echo htmlspecialchars($vendor['email']); ?></td>
                      <td class="d-none d-md-table-cell">
                        <span class="badge bg-<?php echo $vendor['status'] == '1' ? 'success' : 'secondary'; ?>">
                          <?php echo $vendor['status'] == '1' ? 'Aktif' : 'Tidak Aktif'; ?>
                        </span>
                      </td>
                      <td>
                        <div class="btn-group" role="group">
                          <a href="?edit=<?php echo $vendor['id_vendor']; ?><?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?><?php echo !empty($filter) ? '&filter=' . urlencode($filter) : ''; ?>&page=<?php echo $page; ?>" class="btn btn-sm btn-warning">
                            <i class="bi bi-pencil"></i>
                          </a>
                          <form method="POST" style="display: inline;" onsubmit="return confirm('Yakin ingin menghapus data ini?')">
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="id_vendor" value="<?php echo $vendor['id_vendor']; ?>">
                            <button type="submit" class="btn btn-sm btn-danger">
                              <i class="bi bi-trash"></i>
                            </button>
                          </form>
                        </div>
                      </td>
                    </tr>
                  <?php endforeach; ?>
                <?php else: ?>
                  <tr>
                    <td colspan="8" class="text-center">Tidak ada data vendor</td>
                  </tr>
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

     <!-- Modal for Add/Edit Vendor -->
    <div class="modal fade" id="vendorModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><?php echo $edit_vendor ? 'Edit Vendor' : 'Tambah Vendor'; ?></h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="<?php echo $edit_vendor ? 'update' : 'create'; ?>">
                        <?php if ($edit_vendor): ?>
                            <input type="hidden" name="id_vendor" value="<?php echo $edit_vendor['id_vendor']; ?>">
                        <?php endif; ?>
                        
                        <!-- Tab Navigation -->
                        <ul class="nav nav-tabs" id="vendorTabs" role="tablist">
                            <li class="nav-item" role="presentation">
                                <button class="nav-link active" id="basic-tab" data-bs-toggle="tab" data-bs-target="#basic" type="button" role="tab">
                                    <i class="bi bi-person"></i> Data Dasar
                                </button>
                            </li>
                            <li class="nav-item" role="presentation">
                                <button class="nav-link" id="contact-tab" data-bs-toggle="tab" data-bs-target="#contact" type="button" role="tab">
                                    <i class="bi bi-telephone"></i> Kontak
                                </button>
                            </li>
                            <li class="nav-item" role="presentation">
                                <button class="nav-link" id="payment-tab" data-bs-toggle="tab" data-bs-target="#payment" type="button" role="tab">
                                    <i class="bi bi-credit-card"></i> Pembayaran
                                </button>
                            </li>
                            <li class="nav-item" role="presentation">
                                <button class="nav-link" id="other-tab" data-bs-toggle="tab" data-bs-target="#other" type="button" role="tab">
                                    <i class="bi bi-gear"></i> Lainnya
                                </button>
                            </li>
                        </ul>
                        
                        <!-- Tab Content -->
                        <div class="tab-content" id="vendorTabsContent">
                            <!-- Basic Info Tab -->
                            <div class="tab-pane fade show active" id="basic" role="tabpanel">
                                <div class="mt-3">
                                    <div class="row">
                                        <div class="col-md-6">
                                            <div class="mb-3">
                                                <label for="nama_vendor" class="form-label">Nama Vendor *</label>
                                                <input type="text" class="form-control" id="nama_vendor" name="nama_vendor" 
                                                       value="<?php echo $edit_vendor ? htmlspecialchars($edit_vendor['nama_vendor']) : ''; ?>" required>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="mb-3">
                                                <label for="kota" class="form-label">Kota *</label>
                                                <input type="text" class="form-control" id="kota" name="kota" 
                                                       value="<?php echo $edit_vendor ? htmlspecialchars($edit_vendor['kota']) : ''; ?>" required>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label for="alamat" class="form-label">Alamat *</label>
                                        <textarea class="form-control" id="alamat" name="alamat" rows="3" required><?php echo $edit_vendor ? htmlspecialchars($edit_vendor['alamat']) : ''; ?></textarea>
                                    </div>
                                    
                                    <div class="mb-3 form-check">
                                        <input type="checkbox" class="form-check-input" id="status" name="status" 
                                               <?php echo ($edit_vendor && $edit_vendor['status'] == '1') || !$edit_vendor ? 'checked' : ''; ?>>
                                        <label class="form-check-label" for="status">
                                            Status Aktif
                                        </label>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Contact Info Tab -->
                            <div class="tab-pane fade" id="contact" role="tabpanel">
                                <div class="mt-3">
                                    <div class="row">
                                        <div class="col-md-6">
                                            <div class="mb-3">
                                                <label for="hp" class="form-label">Nomor HP *</label>
                                                <input type="text" class="form-control" id="hp" name="hp" 
                                                       value="<?php echo $edit_vendor ? htmlspecialchars($edit_vendor['hp']) : ''; ?>" required>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="mb-3">
                                                <label for="email" class="form-label">Email *</label>
                                                <input type="email" class="form-control" id="email" name="email" 
                                                       value="<?php echo $edit_vendor ? htmlspecialchars($edit_vendor['email']) : ''; ?>" required>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label for="person" class="form-label">Contact Person</label>
                                        <input type="text" class="form-control" id="person" name="person" 
                                               value="<?php echo $edit_vendor ? htmlspecialchars($edit_vendor['person']) : ''; ?>">
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Payment Info Tab -->
                            <div class="tab-pane fade" id="payment" role="tabpanel">
                                <div class="mt-3">
                                    <div class="row">
                                        <div class="col-md-6">
                                            <div class="mb-3">
                                                <label for="nomor_rekening1" class="form-label">Nomor Rekening 1</label>
                                                <input type="text" class="form-control" id="nomor_rekening1" name="nomor_rekening1" 
                                                       value="<?php echo $edit_vendor ? htmlspecialchars($edit_vendor['nomor_rekening1']) : ''; ?>">
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="mb-3">
                                                <label for="nomor_rekening2" class="form-label">Nomor Rekening 2</label>
                                                <input type="text" class="form-control" id="nomor_rekening2" name="nomor_rekening2" 
                                                       value="<?php echo $edit_vendor ? htmlspecialchars($edit_vendor['nomor_rekening2']) : ''; ?>">
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Other Info Tab -->
                            <div class="tab-pane fade" id="other" role="tabpanel">
                                <div class="mt-3">
                                    <div class="mb-3">
                                        <label for="keterangan" class="form-label">Keterangan</label>
                                        <textarea class="form-control" id="keterangan" name="keterangan" rows="4"><?php echo $edit_vendor ? htmlspecialchars($edit_vendor['keterangan']) : ''; ?></textarea>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                        <button type="submit" class="btn btn-primary">
                            <?php echo $edit_vendor ? 'Update' : 'Simpan'; ?>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="../js/bootstrap.bundle.min.js"></script>
    
    <!-- Auto show modal if editing -->
    <?php if ($edit_vendor): ?>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            var vendorModal = new bootstrap.Modal(document.getElementById('vendorModal'));
            vendorModal.show();
        });
    </script>
    <?php endif; ?>
  </body>
</html>