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
                $id_kategori = trim($_POST['id_kategori']);
                $nama_bahan = trim($_POST['nama_bahan']);
                $kode_bahan = trim($_POST['kode_bahan']);
                
                if (!empty($id_kategori) && !empty($nama_bahan) && !empty($kode_bahan)) {
                    // Validate kode_bahan max 6 characters
                    if (strlen($kode_bahan) > 6) {
                        $error = 'Kode bahan maksimal 6 karakter!';
                    } else {
                        // Check if kode_bahan already exists
                        $stmt = $conn->prepare("SELECT id_bahan FROM bahan WHERE kode_bahan = ?");
                        $stmt->bind_param("s", $kode_bahan);
                        $stmt->execute();
                        $result = $stmt->get_result();
                        if ($result->fetch_assoc()) {
                            $error = 'Kode bahan sudah digunakan!';
                        } else {
                            $stmt = $conn->prepare("INSERT INTO bahan (id_kategori, nama_bahan, kode_bahan) VALUES (?, ?, ?)");
                            $stmt->bind_param("iss", $id_kategori, $nama_bahan, $kode_bahan);
                            if ($stmt->execute()) {
                                $message = 'Data bahan berhasil ditambahkan!';
                            } else {
                                $error = 'Error: ' . $conn->error;
                            }
                        }
                    }
                } else {
                    $error = 'Semua field wajib diisi!';
                }
                break;
                
            case 'update':
                $id_bahan = $_POST['id_bahan'];
                $id_kategori = trim($_POST['id_kategori']);
                $nama_bahan = trim($_POST['nama_bahan']);
                $kode_bahan = trim($_POST['kode_bahan']);
                
                if (!empty($id_kategori) && !empty($nama_bahan) && !empty($kode_bahan)) {
                    // Validate kode_bahan max 6 characters
                    if (strlen($kode_bahan) > 6) {
                        $error = 'Kode bahan maksimal 6 karakter!';
                    } else {
                        // Check if kode_bahan already exists for other items
                        $stmt = $conn->prepare("SELECT id_bahan FROM bahan WHERE kode_bahan = ? AND id_bahan != ?");
                        $stmt->bind_param("si", $kode_bahan, $id_bahan);
                        $stmt->execute();
                        $result = $stmt->get_result();
                        if ($result->fetch_assoc()) {
                            $error = 'Kode bahan sudah digunakan oleh bahan lain!';
                        } else {
                            $stmt = $conn->prepare("UPDATE bahan SET id_kategori = ?, nama_bahan = ?, kode_bahan = ? WHERE id_bahan = ?");
                            $stmt->bind_param("issi", $id_kategori, $nama_bahan, $kode_bahan, $id_bahan);
                            if ($stmt->execute()) {
                                $message = 'Data bahan berhasil diperbarui!';
                            } else {
                                $error = 'Error: ' . $conn->error;
                            }
                        }
                    }
                } else {
                    $error = 'Semua field wajib diisi!';
                }
                break;
        }
    }
}

// Get data for edit mode
$edit_data = null;
if (isset($_GET['edit'])) {
    $id_bahan = $_GET['edit'];
    $stmt = $conn->prepare("SELECT b.*, kb.nama_kategori FROM bahan b LEFT JOIN kategori_bahan kb ON b.id_kategori = kb.id_kategori WHERE b.id_bahan = ?");
    $stmt->bind_param("i", $id_bahan);
    $stmt->execute();
    $result = $stmt->get_result();
    $edit_data = $result->fetch_assoc();
}

// Get categories for dropdown
$categories = [];
$result = $conn->query("SELECT id_kategori, nama_kategori FROM kategori_bahan ORDER BY nama_kategori");
while ($row = $result->fetch_assoc()) {
    $categories[] = $row;
}

// Pagination
$limit = 10;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($page - 1) * $limit;

// Search functionality
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$where_clause = '';
$params = [];
$types = '';

if (!empty($search)) {
    $where_clause = "WHERE (b.nama_bahan LIKE ? OR b.kode_bahan LIKE ? OR kb.nama_kategori LIKE ?)";
    $search_param = "%{$search}%";
    $params = [$search_param, $search_param, $search_param];
    $types = 'sss';
}

// Get total count
$count_sql = "SELECT COUNT(*) as total FROM bahan b LEFT JOIN kategori_bahan kb ON b.id_kategori = kb.id_kategori {$where_clause}";
if (!empty($params)) {
    $count_stmt = $conn->prepare($count_sql);
    $count_stmt->bind_param($types, ...$params);
    $count_stmt->execute();
    $total_result = $count_stmt->get_result();
} else {
    $total_result = $conn->query($count_sql);
}
$total_rows = $total_result->fetch_assoc()['total'];
$total_pages = ceil($total_rows / $limit);

// Get data
$sql = "SELECT b.*, kb.nama_kategori FROM bahan b LEFT JOIN kategori_bahan kb ON b.id_kategori = kb.id_kategori {$where_clause} ORDER BY b.id_bahan DESC LIMIT {$limit} OFFSET {$offset}";
if (!empty($params)) {
    $stmt = $conn->prepare($sql);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $result = $stmt->get_result();
} else {
    $result = $conn->query($sql);
}
?>

<!DOCTYPE html>
<html lang="id">
  <head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Bahan - Resto007 Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    <link href="../css/admin.css" rel="stylesheet">
    <style>
      .sidebar {
        min-height: 100vh;
        background-color: #f8f9fa;
      }
      .nav-link {
        color: #333;
        padding: 0.75rem 1rem;
        border-radius: 0.375rem;
        margin-bottom: 0.25rem;
      }
      .nav-link:hover {
        background-color: #e9ecef;
        color: #0d6efd;
      }
      .nav-link.active {
        background-color: #0d6efd;
        color: white;
      }
      .nav-link i {
        margin-right: 0.5rem;
      }
      .table th {
        background-color: #f8f9fa;
        font-weight: 600;
      }
      .btn-sm {
        padding: 0.25rem 0.5rem;
        font-size: 0.875rem;
      }
      .alert {
        border-radius: 0.5rem;
      }
      .modal-header {
        background-color: #f8f9fa;
        border-bottom: 1px solid #dee2e6;
      }
      .form-label {
        font-weight: 500;
      }
      .pagination {
        margin-top: 1rem;
      }
      .search-box {
        max-width: 300px;
      }
      .searchable-select {
        position: relative;
      }
      .searchable-select .form-control {
        cursor: pointer;
      }
      .searchable-select .dropdown-menu {
        width: 100%;
        max-height: 200px;
        overflow-y: auto;
      }
      .searchable-select .search-input {
        border: none;
        outline: none;
        width: 100%;
        padding: 0.375rem 0.75rem;
        border-bottom: 1px solid #dee2e6;
      }
      .searchable-select .dropdown-item {
        cursor: pointer;
      }
      .searchable-select .dropdown-item:hover {
        background-color: #f8f9fa;
      }
      .searchable-select .dropdown-item.active {
        background-color: #0d6efd;
        color: white;
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
                <div class="collapse show" id="produkMenu">
                  <ul class="nav flex-column ms-3">
                    <li class="nav-item"><a class="nav-link" href="kategori_menu.php"><i class="bi bi-tags"></i> Kategori Menu</a></li>
                    <li class="nav-item"><a class="nav-link" href="#"><i class="bi bi-list"></i> Menu</a></li>
                    <li class="nav-item"><a class="nav-link" href="kategori_bahan.php"><i class="bi bi-tags"></i> Kategori Bahan</a></li>
                    <li class="nav-item"><a class="nav-link active" href="bahan.php"><i class="bi bi-egg"></i> Bahan</a></li>
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
       <div class="offcanvas offcanvas-start" tabindex="-1" id="sidebarOffcanvas" aria-labelledby="sidebarOffcanvasLabel">
         <div class="offcanvas-header">
           <h5 class="offcanvas-title" id="sidebarOffcanvasLabel">Menu</h5>
           <button type="button" class="btn-close" data-bs-dismiss="offcanvas" aria-label="Close"></button>
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
                   <li class="nav-item"><a class="nav-link" href="metode_pembayaran.php"><i class="bi bi-credit-card"></i> Metode Pembayaran</a></li>
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
               <div class="collapse show" id="produkMenuMobile">
                 <ul class="nav flex-column ms-3">
                   <li class="nav-item"><a class="nav-link" href="kategori_menu.php"><i class="bi bi-tags"></i> Kategori Menu</a></li>
                   <li class="nav-item"><a class="nav-link" href="#"><i class="bi bi-list"></i> Menu</a></li>
                   <li class="nav-item"><a class="nav-link" href="kategori_bahan.php"><i class="bi bi-tags"></i> Kategori Bahan</a></li>
                   <li class="nav-item"><a class="nav-link active" href="bahan.php"><i class="bi bi-egg"></i> Bahan</a></li>
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
           <h1 class="h2">Bahan</h1>
           <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#bahanModal">
             <i class="bi bi-plus-circle"></i> Tambah Bahan
           </button>
         </div>

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
               <input type="text" class="form-control search-box" name="search" placeholder="Cari nama bahan, kode, atau kategori..." value="<?php echo htmlspecialchars($search); ?>">
               <button class="btn btn-outline-secondary ms-2" type="submit">
                 <i class="bi bi-search"></i>
               </button>
               <?php if (!empty($search)): ?>
                 <a href="bahan.php" class="btn btn-outline-danger ms-2">
                   <i class="bi bi-x"></i>
                 </a>
               <?php endif; ?>
             </form>
           </div>
         </div>

         <!-- Data Table -->
         <div class="table-responsive">
           <table class="table table-striped table-hover">
             <thead>
               <tr>
                 <th>No</th>
                 <th>Kode Bahan</th>
                 <th>Nama Bahan</th>
                 <th>Kategori</th>
                 <th>Aksi</th>
               </tr>
             </thead>
             <tbody>
               <?php if ($result->num_rows > 0): ?>
                 <?php $no = $offset + 1; ?>
                 <?php while ($row = $result->fetch_assoc()): ?>
                   <tr>
                     <td><?php echo $no++; ?></td>
                     <td><?php echo htmlspecialchars($row['kode_bahan']); ?></td>
                     <td><?php echo htmlspecialchars($row['nama_bahan']); ?></td>
                     <td><?php echo htmlspecialchars($row['nama_kategori'] ?? 'Tidak ada kategori'); ?></td>
                     <td>
                       <button type="button" class="btn btn-warning btn-sm" onclick="editBahan(<?php echo $row['id_bahan']; ?>, '<?php echo htmlspecialchars($row['nama_bahan']); ?>', '<?php echo htmlspecialchars($row['kode_bahan']); ?>', <?php echo $row['id_kategori']; ?>)">
                         <i class="bi bi-pencil"></i> Edit
                       </button>
                     </td>
                   </tr>
                 <?php endwhile; ?>
               <?php else: ?>
                 <tr>
                   <td colspan="5" class="text-center">Tidak ada data bahan</td>
                 </tr>
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
                   <a class="page-link" href="?page=<?php echo ($page - 1); ?><?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?>">
                     <i class="bi bi-chevron-left"></i>
                   </a>
                 </li>
               <?php endif; ?>

               <?php for ($i = max(1, $page - 2); $i <= min($total_pages, $page + 2); $i++): ?>
                 <li class="page-item <?php echo ($i == $page) ? 'active' : ''; ?>">
                   <a class="page-link" href="?page=<?php echo $i; ?><?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?>">
                     <?php echo $i; ?>
                   </a>
                 </li>
               <?php endfor; ?>

               <?php if ($page < $total_pages): ?>
                 <li class="page-item">
                   <a class="page-link" href="?page=<?php echo ($page + 1); ?><?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?>">
                     <i class="bi bi-chevron-right"></i>
                   </a>
                 </li>
               <?php endif; ?>
             </ul>
           </nav>

           <div class="text-center text-muted">
             Showing <?php echo $offset + 1; ?> to <?php echo min($offset + $limit, $total_rows); ?> of <?php echo $total_rows; ?> entries
           </div>
         <?php endif; ?>
       </main>
     </div>
   </div>

   <!-- Add/Edit Modal -->
   <div class="modal fade" id="bahanModal" tabindex="-1" aria-labelledby="bahanModalLabel" aria-hidden="true">
     <div class="modal-dialog">
       <div class="modal-content">
         <div class="modal-header">
           <h5 class="modal-title" id="bahanModalLabel"><?php echo $edit_data ? 'Edit Bahan' : 'Tambah Bahan'; ?></h5>
           <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
         </div>
         <form method="POST">
           <div class="modal-body">
             <input type="hidden" name="action" value="<?php echo $edit_data ? 'update' : 'create'; ?>">
             <?php if ($edit_data): ?>
               <input type="hidden" name="id_bahan" value="<?php echo $edit_data['id_bahan']; ?>">
             <?php endif; ?>
             
             <div class="mb-3">
               <label for="id_kategori" class="form-label">Kategori Bahan <span class="text-danger">*</span></label>
               <div class="searchable-select">
                 <input type="text" class="form-control" id="kategori_display" placeholder="Pilih atau ketik untuk mencari kategori..." readonly>
                 <input type="hidden" id="id_kategori" name="id_kategori" required>
                 <div class="dropdown-menu" id="kategori_dropdown">
                   <input type="text" class="search-input" id="kategori_search" placeholder="Cari kategori...">
                   <div id="kategori_options">
                     <?php foreach ($categories as $category): ?>
                       <div class="dropdown-item" data-value="<?php echo $category['id_kategori']; ?>" data-text="<?php echo htmlspecialchars($category['nama_kategori']); ?>">
                         <?php echo htmlspecialchars($category['nama_kategori']); ?>
                       </div>
                     <?php endforeach; ?>
                   </div>
                 </div>
               </div>
             </div>
             
             <div class="mb-3">
               <label for="nama_bahan" class="form-label">Nama Bahan <span class="text-danger">*</span></label>
               <input type="text" class="form-control" id="nama_bahan" name="nama_bahan" value="<?php echo $edit_data ? htmlspecialchars($edit_data['nama_bahan']) : ''; ?>" required>
             </div>
             
             <div class="mb-3">
               <label for="kode_bahan" class="form-label">Kode Bahan <span class="text-danger">*</span></label>
               <input type="text" class="form-control" id="kode_bahan" name="kode_bahan" value="<?php echo $edit_data ? htmlspecialchars($edit_data['kode_bahan']) : ''; ?>" maxlength="6" required>
               <div class="form-text">Maksimal 6 karakter</div>
             </div>
           </div>
           <div class="modal-footer">
             <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
             <button type="submit" class="btn btn-primary"><?php echo $edit_data ? 'Perbarui' : 'Simpan'; ?></button>
           </div>
         </form>
       </div>
     </div>
   </div>

   <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
   <script>
     <?php if ($edit_data): ?>
       // Show modal for edit mode
       document.addEventListener('DOMContentLoaded', function() {
         var modal = new bootstrap.Modal(document.getElementById('bahanModal'));
         modal.show();
       });
     <?php endif; ?>
     
     // Initialize searchable dropdown
     document.addEventListener('DOMContentLoaded', function() {
       const displayInput = document.getElementById('kategori_display');
       const hiddenInput = document.getElementById('id_kategori');
       const dropdown = document.getElementById('kategori_dropdown');
       const searchInput = document.getElementById('kategori_search');
       const optionsContainer = document.getElementById('kategori_options');
       const allOptions = optionsContainer.querySelectorAll('.dropdown-item');
       
       // Show dropdown when clicking display input
       displayInput.addEventListener('click', function() {
         dropdown.style.display = 'block';
         searchInput.focus();
       });
       
       // Hide dropdown when clicking outside
       document.addEventListener('click', function(e) {
         if (!e.target.closest('.searchable-select')) {
           dropdown.style.display = 'none';
         }
       });
       
       // Search functionality
       searchInput.addEventListener('input', function() {
         const searchTerm = this.value.toLowerCase();
         allOptions.forEach(option => {
           const text = option.textContent.toLowerCase();
           if (text.includes(searchTerm)) {
             option.style.display = 'block';
           } else {
             option.style.display = 'none';
           }
         });
       });
       
       // Select option
       allOptions.forEach(option => {
         option.addEventListener('click', function() {
           const value = this.getAttribute('data-value');
           const text = this.getAttribute('data-text');
           
           hiddenInput.value = value;
           displayInput.value = text;
           dropdown.style.display = 'none';
           searchInput.value = '';
           
           // Show all options again
           allOptions.forEach(opt => opt.style.display = 'block');
         });
       });
       
       // Clear search when dropdown is shown
       displayInput.addEventListener('focus', function() {
         searchInput.value = '';
         allOptions.forEach(opt => opt.style.display = 'block');
       });
     });
     
     // Handle form submission success
     document.addEventListener('DOMContentLoaded', function() {
       <?php if ($message): ?>
         // Close modal after successful operation
         setTimeout(function() {
           var modal = bootstrap.Modal.getInstance(document.getElementById('bahanModal'));
           if (modal) {
             modal.hide();
           }
           // Remove edit parameter from URL after successful update
           if (window.location.search.includes('edit=')) {
             window.history.replaceState({}, document.title, window.location.pathname);
           }
         }, 100);
       <?php endif; ?>
       
       // Handle "Tambah Bahan" button click
       document.querySelector('[data-bs-target="#bahanModal"]').addEventListener('click', function() {
         // Reset form for add mode
         var form = document.querySelector('#bahanModal form');
         var actionInput = form.querySelector('input[name="action"]');
         var idInput = form.querySelector('input[name="id_bahan"]');
         var kategoriSelect = form.querySelector('select[name="id_kategori"]');
         var namaInput = form.querySelector('input[name="nama_bahan"]');
         var kodeInput = form.querySelector('input[name="kode_bahan"]');
         var modalTitle = document.querySelector('#bahanModal .modal-title');
         var submitBtn = document.querySelector('#bahanModal button[type="submit"]');
         
         // Reset to add mode
         actionInput.value = 'create';
         if (idInput) idInput.remove();
         document.getElementById('id_kategori').value = '';
         document.getElementById('kategori_display').value = '';
         namaInput.value = '';
         kodeInput.value = '';
         modalTitle.textContent = 'Tambah Bahan';
         submitBtn.textContent = 'Simpan';
         
         // Remove edit parameter from URL
         if (window.location.search.includes('edit=')) {
           window.history.replaceState({}, document.title, window.location.pathname);
         }
       });
     });
     
     function editBahan(id, nama, kode, kategori) {
       // Set form to edit mode
       var form = document.querySelector('#bahanModal form');
       var actionInput = form.querySelector('input[name="action"]');
       var idInput = form.querySelector('input[name="id_bahan"]');
       var kategoriSelect = form.querySelector('select[name="id_kategori"]');
       var namaInput = form.querySelector('input[name="nama_bahan"]');
       var kodeInput = form.querySelector('input[name="kode_bahan"]');
       var modalTitle = document.querySelector('#bahanModal .modal-title');
       var submitBtn = document.querySelector('#bahanModal button[type="submit"]');
       
       // Set edit mode
       actionInput.value = 'update';
       
       // Add or update id input
       if (!idInput) {
         idInput = document.createElement('input');
         idInput.type = 'hidden';
         idInput.name = 'id_bahan';
         form.appendChild(idInput);
       }
       idInput.value = id;
       
       // Set form values
       document.getElementById('id_kategori').value = kategori;
       // Find the category name for display
       const categoryOption = document.querySelector(`[data-value="${kategori}"]`);
       if (categoryOption) {
         document.getElementById('kategori_display').value = categoryOption.getAttribute('data-text');
       }
       namaInput.value = nama;
       kodeInput.value = kode;
       modalTitle.textContent = 'Edit Bahan';
       submitBtn.textContent = 'Perbarui';
       
       // Show modal
       var modal = new bootstrap.Modal(document.getElementById('bahanModal'));
       modal.show();
     }
   </script>
 </body>
</html>