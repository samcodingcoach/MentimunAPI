<?php
session_start();
require_once '../../config/koneksi.php';

// Check if user is logged in
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    header('Location: login.php');
    exit();
}

$message = '';
$error = '';
$edit_meja = null;

// Function to generate meja number
function generateMejaNumber($conn) {
    $stmt = $conn->prepare("SELECT MAX(nomor_meja) as max_nomor FROM meja");
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    
    return ($row['max_nomor'] ?? 0) + 1;
}

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $action = $_POST['action'] ?? '';
    
    switch ($action) {
        case 'create':
            $nomor_meja = $_POST['nomor_meja'] ?? generateMejaNumber($conn);
            $aktif = $_POST['aktif'] ?? '1';
            $in_used = $_POST['in_used'] ?? '0';
            $pos_x = $_POST['pos_x'] ?? 0;
            $pos_y = $_POST['pos_y'] ?? 0;
            
            // Check if nomor_meja already exists
            $check_stmt = $conn->prepare("SELECT id_meja FROM meja WHERE nomor_meja = ?");
            $check_stmt->bind_param("i", $nomor_meja);
            $check_stmt->execute();
            $check_result = $check_stmt->get_result();
            
            if ($check_result->num_rows > 0) {
                $error = 'Nomor meja sudah ada!';
            } else {
                $stmt = $conn->prepare("INSERT INTO meja (nomor_meja, aktif, in_used, pos_x, pos_y) VALUES (?, ?, ?, ?, ?)");
                $stmt->bind_param("issdd", $nomor_meja, $aktif, $in_used, $pos_x, $pos_y);
                
                if ($stmt->execute()) {
                    $message = 'Data meja berhasil ditambahkan!';
                } else {
                    $error = 'Error: ' . $conn->error;
                }
            }
            break;
            
        case 'update':
            $id_meja = $_POST['id_meja'];
            $nomor_meja = $_POST['nomor_meja'];
            $aktif = $_POST['aktif'];
            $in_used = $_POST['in_used'];
            $pos_x = $_POST['pos_x'];
            $pos_y = $_POST['pos_y'];
            
            // Check if nomor_meja already exists for other records
            $check_stmt = $conn->prepare("SELECT id_meja FROM meja WHERE nomor_meja = ? AND id_meja != ?");
            $check_stmt->bind_param("ii", $nomor_meja, $id_meja);
            $check_stmt->execute();
            $check_result = $check_stmt->get_result();
            
            if ($check_result->num_rows > 0) {
                $error = 'Nomor meja sudah ada!';
            } else {
                $stmt = $conn->prepare("UPDATE meja SET nomor_meja = ?, aktif = ?, in_used = ?, pos_x = ?, pos_y = ? WHERE id_meja = ?");
                $stmt->bind_param("issddi", $nomor_meja, $aktif, $in_used, $pos_x, $pos_y, $id_meja);
                
                if ($stmt->execute()) {
                    $message = 'Data meja berhasil diperbarui!';
                } else {
                    $error = 'Error: ' . $conn->error;
                }
            }
            break;
    }
}

// Get meja for editing
if (isset($_GET['edit'])) {
    $edit_id = $_GET['edit'];
    $stmt = $conn->prepare("SELECT * FROM meja WHERE id_meja = ?");
    $stmt->bind_param("i", $edit_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $edit_meja = $result->fetch_assoc();
}

// Pagination and search
$search = $_GET['search'] ?? '';
$filter = $_GET['filter'] ?? '';
$page = max(1, $_GET['page'] ?? 1);
$limit = 10;
$offset = ($page - 1) * $limit;

// Build query
$where_conditions = [];
$params = [];
$param_types = '';

if (!empty($search)) {
    $where_conditions[] = "nomor_meja LIKE ?";
    $params[] = "%$search%";
    $param_types .= 's';
}

if (!empty($filter)) {
    if ($filter === 'aktif') {
        $where_conditions[] = "aktif = '1'";
    } elseif ($filter === 'nonaktif') {
        $where_conditions[] = "aktif = '0'";
    } elseif ($filter === 'terpakai') {
        $where_conditions[] = "in_used = '1'";
    } elseif ($filter === 'kosong') {
        $where_conditions[] = "in_used = '0'";
    }
}

$where_clause = !empty($where_conditions) ? 'WHERE ' . implode(' AND ', $where_conditions) : '';

// Get total records
$count_query = "SELECT COUNT(*) as total FROM meja $where_clause";
if (!empty($params)) {
    $count_stmt = $conn->prepare($count_query);
    $count_stmt->bind_param($param_types, ...$params);
    $count_stmt->execute();
    $count_result = $count_stmt->get_result();
} else {
    $count_result = $conn->query($count_query);
}
$total_records = $count_result->fetch_assoc()['total'];
$total_pages = ceil($total_records / $limit);

// Get meja data
$query = "SELECT * FROM meja $where_clause ORDER BY nomor_meja ASC LIMIT $limit OFFSET $offset";
if (!empty($params)) {
    $stmt = $conn->prepare($query);
    $stmt->bind_param($param_types, ...$params);
    $stmt->execute();
    $result = $stmt->get_result();
} else {
    $result = $conn->query($query);
}

$meja_list = [];
while ($row = $result->fetch_assoc()) {
    $meja_list[] = $row;
}
?>

<!doctype html>
<html lang="en">
  <head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Meja - Admin Dashboard</title>
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
              <?php echo htmlspecialchars($_SESSION["nama_lengkap"]); ?> (<?php echo htmlspecialchars($_SESSION["jabatan"] ?? 'Admin'); ?>)
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
                 <div class="collapse show" id="masterMenu">
                   <ul class="nav flex-column ms-3">
                     <li class="nav-item"><a class="nav-link" href="resto.php"><i class="bi bi-building"></i> Resto</a></li>
                     <li class="nav-item"><a class="nav-link" href="pegawai.php"><i class="bi bi-people"></i> Pegawai</a></li>
                     <li class="nav-item"><a class="nav-link" href="vendor.php"><i class="bi bi-truck"></i> Vendor</a></li>
                     <li class="nav-item"><a class="nav-link active" href="meja.php"><i class="bi bi-table"></i> Meja</a></li>
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
               
               <!-- Laporan Menu -->
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
                    <li class="nav-item"><a class="nav-link" href="meja.php"><i class="bi bi-table"></i> Meja</a></li>
                    <li class="nav-item"><a class="nav-link" href="#"><i class="bi bi-credit-card"></i> Metode Pembayaran</a></li>
                    <li class="nav-item"><a class="nav-link" href="#"><i class="bi bi-cash"></i> Bayar</a></li>
                  </ul>
                </div>
              </li>
              <?php endif; ?>
              
              <?php if($_SESSION["jabatan"] == "Admin" || $_SESSION["jabatan"] == "Dapur"): ?>
              <!-- Produk Menu -->
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
              
              <!-- Pembelian Menu -->
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
              <!-- Penjualan Menu -->
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
              <!-- Inventory Menu -->
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
              
              <!-- Laporan Menu -->
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
            <h1 class="h2">Manajemen Meja</h1>
                    <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#mejaModal">
                        <i class="bi bi-plus-circle"></i> Tambah Meja
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
                    <div class="col-md-6">
                        <form method="GET" class="d-flex">
                            <input type="text" class="form-control me-2" name="search" placeholder="Cari nomor meja..." value="<?php echo htmlspecialchars($search); ?>">
                            <input type="hidden" name="filter" value="<?php echo htmlspecialchars($filter); ?>">
                            <input type="hidden" name="page" value="1">
                            <button class="btn btn-outline-secondary" type="submit">
                                <i class="bi bi-search"></i>
                            </button>
                        </form>
                    </div>
                    <div class="col-md-6">
                        <form method="GET" class="d-flex justify-content-end">
                            <select name="filter" class="form-select w-auto me-2" style="padding-right: 2.5rem;" onchange="this.form.submit()">
                                <option value="">Semua Status</option>
                                <option value="aktif" <?php echo $filter === 'aktif' ? 'selected' : ''; ?>>Aktif</option>
                                <option value="nonaktif" <?php echo $filter === 'nonaktif' ? 'selected' : ''; ?>>Non-aktif</option>
                                <option value="terpakai" <?php echo $filter === 'terpakai' ? 'selected' : ''; ?>>Terpakai</option>
                                <option value="kosong" <?php echo $filter === 'kosong' ? 'selected' : ''; ?>>Kosong</option>
                            </select>
                            <input type="hidden" name="search" value="<?php echo htmlspecialchars($search); ?>">
                            <input type="hidden" name="page" value="1">
                        </form>
                    </div>
                </div>

                <!-- Meja Table -->
                <div class="table-responsive mb-4">
                    <table class="table table-striped table-hover">
                        <thead class="table-dark">
                            <tr>
                                <th>ID</th>
                                <th>Nomor Meja</th>
                                <th>Status</th>
                                <th>Kondisi</th>
                                <th>Posisi X</th>
                                <th>Posisi Y</th>
                                <th>Update Terakhir</th>
                                <th>Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($meja_list)): ?>
                            <tr>
                                <td colspan="8" class="text-center">Tidak ada data meja</td>
                            </tr>
                            <?php else: ?>
                            <?php foreach ($meja_list as $meja): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($meja['id_meja']); ?></td>
                                <td>
                                    <strong>Meja <?php echo htmlspecialchars($meja['nomor_meja']); ?></strong>
                                </td>
                                <td>
                                    <?php if ($meja['aktif'] == '1'): ?>
                                        <span class="badge bg-success">Aktif</span>
                                    <?php else: ?>
                                        <span class="badge bg-secondary">Non-aktif</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($meja['in_used'] == '1'): ?>
                                        <span class="badge bg-warning text-dark">Terpakai</span>
                                    <?php else: ?>
                                        <span class="badge bg-success">Kosong</span>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo htmlspecialchars($meja['pos_x']); ?></td>
                                <td><?php echo htmlspecialchars($meja['pos_y']); ?></td>
                                <td>
                                    <?php 
                                    if ($meja['update_at']) {
                                        echo date('d/m/Y H:i', strtotime($meja['update_at']));
                                    } else {
                                        echo '-';
                                    }
                                    ?>
                                </td>
                                <td>
                                    <a href="?edit=<?php echo $meja['id_meja']; ?><?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?><?php echo !empty($filter) ? '&filter=' . urlencode($filter) : ''; ?>&page=<?php echo $page; ?>" class="btn btn-sm btn-warning">
                                        <i class="bi bi-pencil"></i> Edit
                                    </a>
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

                <!-- Info -->
                <div class="mt-3">
                    <small class="text-muted">
                        Menampilkan <?php echo count($meja_list); ?> dari <?php echo $total_records; ?> data meja
                        (Halaman <?php echo $page; ?> dari <?php echo $total_pages; ?>)
                    </small>
                </div>
        </main>
      </div>
    </div>

    <!-- Meja Modal -->
    <div class="modal fade" id="mejaModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <?php echo $edit_meja ? 'Edit Meja' : 'Tambah Meja Baru'; ?>
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="<?php echo $edit_meja ? 'update' : 'create'; ?>">
                        <?php if ($edit_meja): ?>
                        <input type="hidden" name="id_meja" value="<?php echo $edit_meja['id_meja']; ?>">
                        <?php endif; ?>

                        <!-- Tab Navigation -->
                        <ul class="nav nav-tabs" id="mejaTab" role="tablist">
                            <li class="nav-item" role="presentation">
                                <button class="nav-link active" id="basic-tab" data-bs-toggle="tab" data-bs-target="#basic" type="button" role="tab">
                                    <i class="bi bi-info-circle"></i> Data Dasar
                                </button>
                            </li>
                            <li class="nav-item" role="presentation">
                                <button class="nav-link" id="position-tab" data-bs-toggle="tab" data-bs-target="#position" type="button" role="tab">
                                    <i class="bi bi-geo-alt"></i> Posisi
                                </button>
                            </li>
                            <li class="nav-item" role="presentation">
                                <button class="nav-link" id="status-tab" data-bs-toggle="tab" data-bs-target="#status" type="button" role="tab">
                                    <i class="bi bi-toggle-on"></i> Status
                                </button>
                            </li>
                        </ul>

                        <!-- Tab Content -->
                        <div class="tab-content mt-3" id="mejaTabContent">
                            <!-- Basic Data Tab -->
                            <div class="tab-pane fade show active" id="basic" role="tabpanel">
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label for="nomor_meja" class="form-label">Nomor Meja *</label>
                                            <input type="number" class="form-control" id="nomor_meja" name="nomor_meja" 
                                                   value="<?php echo $edit_meja ? htmlspecialchars($edit_meja['nomor_meja']) : generateMejaNumber($conn); ?>" 
                                                   min="1" required>
                                            <div class="form-text">Nomor unik untuk identifikasi meja</div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Position Tab -->
                            <div class="tab-pane fade" id="position" role="tabpanel">
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label for="pos_x" class="form-label">Posisi X</label>
                                            <input type="number" step="0.01" class="form-control" id="pos_x" name="pos_x" 
                                                   value="<?php echo $edit_meja ? htmlspecialchars($edit_meja['pos_x']) : '0'; ?>">
                                            <div class="form-text">Koordinat X untuk layout meja</div>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label for="pos_y" class="form-label">Posisi Y</label>
                                            <input type="number" step="0.01" class="form-control" id="pos_y" name="pos_y" 
                                                   value="<?php echo $edit_meja ? htmlspecialchars($edit_meja['pos_y']) : '0'; ?>">
                                            <div class="form-text">Koordinat Y untuk layout meja</div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Status Tab -->
                            <div class="tab-pane fade" id="status" role="tabpanel">
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label for="aktif" class="form-label">Status Aktif</label>
                                            <select class="form-select" id="aktif" name="aktif" required>
                                                <option value="1" <?php echo (!$edit_meja || $edit_meja['aktif'] == '1') ? 'selected' : ''; ?>>Aktif</option>
                                                <option value="0" <?php echo ($edit_meja && $edit_meja['aktif'] == '0') ? 'selected' : ''; ?>>Non-aktif</option>
                                            </select>
                                            <div class="form-text">Status ketersediaan meja</div>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label for="in_used" class="form-label">Kondisi Meja</label>
                                            <select class="form-select" id="in_used" name="in_used" required>
                                                <option value="0" <?php echo (!$edit_meja || $edit_meja['in_used'] == '0') ? 'selected' : ''; ?>>Kosong</option>
                                                <option value="1" <?php echo ($edit_meja && $edit_meja['in_used'] == '1') ? 'selected' : ''; ?>>Terpakai</option>
                                            </select>
                                            <div class="form-text">Status penggunaan meja saat ini</div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-check-circle"></i> 
                            <?php echo $edit_meja ? 'Update' : 'Simpan'; ?>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="../js/bootstrap.bundle.min.js"></script>
    
    <!-- Auto show modal if editing -->
    <?php if ($edit_meja): ?>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            var mejaModal = new bootstrap.Modal(document.getElementById('mejaModal'));
            mejaModal.show();
        });
    </script>
    <?php endif; ?>
    
    <!-- Auto close modal after successful update -->
    <?php if ($message && !$error): ?>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            var mejaModal = bootstrap.Modal.getInstance(document.getElementById('mejaModal'));
            if (mejaModal) {
                mejaModal.hide();
            }
            // Remove edit parameter from URL
            if (window.location.search.includes('edit=')) {
                const url = new URL(window.location);
                url.searchParams.delete('edit');
                window.history.replaceState({}, document.title, url.pathname + url.search);
            }
        });
    </script>
    <?php endif; ?>
</body>
</html>