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
                $nama_kategori = trim($_POST['nama_kategori']);
                
                if (!empty($nama_kategori)) {
                    // Check if category name already exists
                    $stmt = $conn->prepare("SELECT id_kategori FROM kategori_bahan WHERE nama_kategori = ?");
                    $stmt->bind_param("s", $nama_kategori);
                    $stmt->execute();
                    $result = $stmt->get_result();
                    if ($result->fetch_assoc()) {
                        $error = 'Nama kategori sudah ada!';
                    } else {
                        $stmt = $conn->prepare("INSERT INTO kategori_bahan (nama_kategori) VALUES (?)");
                        $stmt->bind_param("s", $nama_kategori);
                        if ($stmt->execute()) {
                            $message = 'Kategori bahan berhasil ditambahkan!';
                        } else {
                            $error = 'Error: ' . $conn->error;
                        }
                    }
                } else {
                    $error = 'Nama kategori wajib diisi!';
                }
                break;
                
            case 'update':
                $id_kategori = $_POST['id_kategori'];
                $nama_kategori = trim($_POST['nama_kategori']);
                
                if (!empty($nama_kategori)) {
                    // Check if category name already exists for other categories
                    $stmt = $conn->prepare("SELECT id_kategori FROM kategori_bahan WHERE nama_kategori = ? AND id_kategori != ?");
                    $stmt->bind_param("si", $nama_kategori, $id_kategori);
                    $stmt->execute();
                    $result = $stmt->get_result();
                    if ($result->fetch_assoc()) {
                        $error = 'Nama kategori sudah digunakan oleh kategori lain!';
                    } else {
                        $stmt = $conn->prepare("UPDATE kategori_bahan SET nama_kategori = ? WHERE id_kategori = ?");
                        $stmt->bind_param("si", $nama_kategori, $id_kategori);
                        if ($stmt->execute()) {
                            $message = 'Kategori bahan berhasil diperbarui!';
                        } else {
                            $error = 'Error: ' . $conn->error;
                        }
                    }
                } else {
                    $error = 'Nama kategori wajib diisi!';
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

// Build WHERE clause for search
$where_conditions = [];
$params = [];
$types = '';

if (!empty($search)) {
    $where_conditions[] = "nama_kategori LIKE ?";
    $search_param = "%$search%";
    $params[] = $search_param;
    $types .= 's';
}

$where_clause = !empty($where_conditions) ? 'WHERE ' . implode(' AND ', $where_conditions) : '';

// Get total count for pagination
$count_sql = "SELECT COUNT(*) as total FROM kategori_bahan $where_clause";
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

// Get category data with pagination
$sql = "SELECT * FROM kategori_bahan $where_clause ORDER BY id_kategori DESC LIMIT $limit OFFSET $offset";
if (!empty($params)) {
    $stmt = $conn->prepare($sql);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $result = $stmt->get_result();
    $categories = $result->fetch_all(MYSQLI_ASSOC);
} else {
    $result = $conn->query($sql);
    if ($result) {
        $categories = $result->fetch_all(MYSQLI_ASSOC);
    } else {
        $error = 'Error fetching data: ' . $conn->error;
        $categories = [];
    }
}

// Get single category for editing
$edit_category = null;
if (isset($_GET['edit'])) {
    $stmt = $conn->prepare("SELECT * FROM kategori_bahan WHERE id_kategori = ?");
    $stmt->bind_param("i", $_GET['edit']);
    $stmt->execute();
    $result = $stmt->get_result();
    $edit_category = $result->fetch_assoc();
}
?>

<!doctype html>
<html lang="en">
  <head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Kategori Bahan - Admin Dashboard</title>
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
                 <div class="collapse show" id="produkMenu">
                   <ul class="nav flex-column ms-3">
                     <li class="nav-item"><a class="nav-link" href="kategori_menu.php"><i class="bi bi-tags"></i> Kategori Menu</a></li>
                     <li class="nav-item"><a class="nav-link" href="#"><i class="bi bi-list"></i> Menu</a></li>
                     <li class="nav-item"><a class="nav-link active" href="kategori_bahan.php"><i class="bi bi-tags"></i> Kategori Bahan</a></li>
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
                 <a class="nav-link" href="informasi.php">
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
                     <li class="nav-item"><a class="nav-link active" href="kategori_bahan.php"><i class="bi bi-tags"></i> Kategori Bahan</a></li>
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
             <h1 class="h2">Kategori Bahan</h1>
             <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#categoryModal">
               <i class="bi bi-plus-circle"></i> Tambah Kategori
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

           <!-- Search -->
           <div class="row mb-3">
             <div class="col-md-4">
               <form method="GET" class="d-flex">
                 <input type="text" class="form-control me-2" name="search" placeholder="Cari nama kategori..." value="<?php echo htmlspecialchars($search); ?>">
                 <button class="btn btn-outline-secondary" type="submit">
                   <i class="bi bi-search"></i>
                 </button>
               </form>
             </div>
             <div class="col-md-8 text-end">
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
                   <th>Nama Kategori</th>
                   <th>Aksi</th>
                 </tr>
               </thead>
               <tbody>
                 <?php if (!empty($categories)): ?>
                   <?php foreach ($categories as $index => $category): ?>
                     <tr>
                       <td><?php echo $offset + $index + 1; ?></td>
                       <td><?php echo htmlspecialchars($category['nama_kategori']); ?></td>
                       <td>
                         <a href="?edit=<?php echo $category['id_kategori']; ?><?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?>&page=<?php echo $page; ?>" class="btn btn-sm btn-warning">
                           <i class="bi bi-pencil"></i> Edit
                         </a>
                       </td>
                     </tr>
                   <?php endforeach; ?>
                 <?php else: ?>
                   <tr>
                     <td colspan="3" class="text-center">Tidak ada data kategori bahan</td>
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
                     <a class="page-link" href="?page=<?php echo $page - 1; ?><?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?>">
                       <i class="bi bi-chevron-left"></i>
                     </a>
                   </li>
                 <?php endif; ?>

                 <!-- Page Numbers -->
                 <?php
                 $start_page = max(1, $page - 2);
                 $end_page = min($total_pages, $page + 2);
                 for ($i = $start_page; $i <= $end_page; $i++):
                 ?>
                   <li class="page-item <?php echo $i == $page ? 'active' : ''; ?>">
                     <a class="page-link" href="?page=<?php echo $i; ?><?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?>">
                       <?php echo $i; ?>
                     </a>
                   </li>
                 <?php endfor; ?>

                 <!-- Next Page -->
                 <?php if ($page < $total_pages): ?>
                   <li class="page-item">
                     <a class="page-link" href="?page=<?php echo $page + 1; ?><?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?>">
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

     <!-- Add/Edit Modal -->
     <div class="modal fade" id="categoryModal" tabindex="-1">
       <div class="modal-dialog">
         <div class="modal-content">
           <div class="modal-header">
             <h5 class="modal-title"><?php echo $edit_category ? 'Edit' : 'Tambah'; ?> Kategori Bahan</h5>
             <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
           </div>
           <form method="POST">
             <div class="modal-body">
               <input type="hidden" name="action" value="<?php echo $edit_category ? 'update' : 'create'; ?>">
               <?php if ($edit_category): ?>
                 <input type="hidden" name="id_kategori" value="<?php echo $edit_category['id_kategori']; ?>">
               <?php endif; ?>
               
               <div class="mb-3">
                 <label for="nama_kategori" class="form-label">Nama Kategori <span class="text-danger">*</span></label>
                 <input type="text" class="form-control" id="nama_kategori" name="nama_kategori" required maxlength="30" value="<?php echo $edit_category ? htmlspecialchars($edit_category['nama_kategori']) : ''; ?>">
               </div>
             </div>
             <div class="modal-footer">
               <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
               <button type="submit" class="btn btn-primary"><?php echo $edit_category ? 'Update' : 'Simpan'; ?></button>
             </div>
           </form>
         </div>
       </div>
     </div>

     <script src="../js/bootstrap.bundle.min.js"></script>
     <script>
       // Auto show modal if editing
       <?php if ($edit_category): ?>
         var categoryModal = new bootstrap.Modal(document.getElementById('categoryModal'));
         categoryModal.show();
       <?php endif; ?>
       
       // Close modal after successful submission
       <?php if ($message && !$edit_category): ?>
         // If there's a success message and we're not in edit mode, close any open modal
         var modal = bootstrap.Modal.getInstance(document.getElementById('categoryModal'));
         if (modal) {
           modal.hide();
         }
       <?php endif; ?>
       
       // Handle form submission success
       document.addEventListener('DOMContentLoaded', function() {
         <?php if ($message): ?>
           // Close modal after successful operation
           setTimeout(function() {
             var modal = bootstrap.Modal.getInstance(document.getElementById('categoryModal'));
             if (modal) {
               modal.hide();
             }
             // Remove edit parameter from URL after successful update
             if (window.location.search.includes('edit=')) {
               window.history.replaceState({}, document.title, window.location.pathname);
             }
           }, 100);
         <?php endif; ?>
         
         // Handle "Tambah Kategori" button click
         document.querySelector('[data-bs-target="#categoryModal"]').addEventListener('click', function() {
           // Reset form for add mode
           var form = document.querySelector('#categoryModal form');
           var actionInput = form.querySelector('input[name="action"]');
           var idInput = form.querySelector('input[name="id_kategori"]');
           var namaInput = form.querySelector('input[name="nama_kategori"]');
           var modalTitle = document.querySelector('#categoryModal .modal-title');
           var submitBtn = document.querySelector('#categoryModal button[type="submit"]');
           
           // Reset to add mode
           actionInput.value = 'create';
           if (idInput) idInput.remove();
           namaInput.value = '';
           modalTitle.textContent = 'Tambah Kategori Bahan';
           submitBtn.textContent = 'Simpan';
           
           // Remove edit parameter from URL
           if (window.location.search.includes('edit=')) {
             window.history.replaceState({}, document.title, window.location.pathname);
           }
         });
       });
     </script>
   </body>
 </html>