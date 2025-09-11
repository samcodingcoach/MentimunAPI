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

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'create':
                $nama_aplikasi = trim($_POST['nama_aplikasi']);
                $alamat = trim($_POST['alamat']);
                $no_hp = trim($_POST['no_hp']);
                $serverkeymidtrans = trim($_POST['serverkeymidtrans']);
                
                if (!empty($nama_aplikasi) && !empty($alamat) && !empty($no_hp)) {
                    $stmt = $conn->prepare("INSERT INTO perusahaan (nama_aplikasi, alamat, no_hp, serverkeymidtrans, update_time) VALUES (?, ?, ?, ?, NOW())");
                    if ($stmt->execute([$nama_aplikasi, $alamat, $no_hp, $serverkeymidtrans])) {
                        $message = 'Data resto berhasil ditambahkan!';
                    } else {
                        $error = 'Error: ' . $conn->error;
                    }
                } else {
                    $error = 'Nama aplikasi, alamat, dan nomor HP harus diisi!';
                }
                break;
                
            case 'update':
                $id_app = $_POST['id_app'];
                $nama_aplikasi = trim($_POST['nama_aplikasi']);
                $alamat = trim($_POST['alamat']);
                $no_hp = trim($_POST['no_hp']);
                $serverkeymidtrans = trim($_POST['serverkeymidtrans']);
                
                if (!empty($nama_aplikasi) && !empty($alamat) && !empty($no_hp)) {
                    $stmt = $conn->prepare("UPDATE perusahaan SET nama_aplikasi = ?, alamat = ?, no_hp = ?, serverkeymidtrans = ?, update_time = NOW() WHERE id_app = ?");
                    if ($stmt->execute([$nama_aplikasi, $alamat, $no_hp, $serverkeymidtrans, $id_app])) {
                        $message = 'Data resto berhasil diperbarui!';
                    } else {
                        $error = 'Error: ' . $conn->error;
                    }
                } else {
                    $error = 'Nama aplikasi, alamat, dan nomor HP harus diisi!';
                }
                break;
                
            case 'delete':
                $id_app = $_POST['id_app'];
                $stmt = $conn->prepare("DELETE FROM perusahaan WHERE id_app = ?");
                if ($stmt->execute([$id_app])) {
                    $message = 'Data resto berhasil dihapus!';
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
    $where_conditions[] = "(nama_aplikasi LIKE ? OR alamat LIKE ? OR no_hp LIKE ?)";
    $search_param = "%$search%";
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
    $types .= 'sss';
}

if (!empty($filter)) {
    // Add filter logic here if needed
}

$where_clause = !empty($where_conditions) ? 'WHERE ' . implode(' AND ', $where_conditions) : '';

// Get total count for pagination
$count_sql = "SELECT COUNT(*) as total FROM perusahaan $where_clause";
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

// Get resto data with pagination
$sql = "SELECT * FROM perusahaan $where_clause ORDER BY id_app DESC LIMIT $limit OFFSET $offset";
if (!empty($params)) {
    $stmt = $conn->prepare($sql);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $result = $stmt->get_result();
    $restos = $result->fetch_all(MYSQLI_ASSOC);
} else {
    $result = $conn->query($sql);
    if ($result) {
        $restos = $result->fetch_all(MYSQLI_ASSOC);
    } else {
        $error = 'Error fetching data: ' . $conn->error;
        $restos = [];
    }
}

// Get single resto for editing
$edit_resto = null;
if (isset($_GET['edit'])) {
    $stmt = $conn->prepare("SELECT * FROM perusahaan WHERE id_app = ?");
    $stmt->bind_param("i", $_GET['edit']);
    $stmt->execute();
    $result = $stmt->get_result();
    $edit_resto = $result->fetch_assoc();
}
?>

<!doctype html>
<html lang="en">
  <head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Resto - Admin Dashboard</title>
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
                  <i class="bi bi-house-door"></i>
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
              <!-- Master Menu - Admin Only -->
              <li class="nav-item">
                <a class="nav-link" data-bs-toggle="collapse" href="#masterMenu" role="button">
                  <i class="bi bi-gear-fill"></i>
                  <span>Master</span>
                  <i class="bi bi-chevron-down ms-auto"></i>
                </a>
                <div class="collapse show" id="masterMenu">
                  <ul class="nav flex-column ms-3">
                    <li class="nav-item"><a class="nav-link active" href="resto.php"><i class="bi bi-building"></i> Resto</a></li>
                    <li class="nav-item"><a class="nav-link" href="pegawai.php"><i class="bi bi-people"></i> Pegawai</a></li>
                    <li class="nav-item"><a class="nav-link" href="#"><i class="bi bi-truck"></i> Vendor</a></li>
                    <li class="nav-item"><a class="nav-link" href="#"><i class="bi bi-table"></i> Meja</a></li>
                    <li class="nav-item"><a class="nav-link" href="#"><i class="bi bi-credit-card"></i> Metode Pembayaran</a></li>
                    <li class="nav-item"><a class="nav-link" href="#"><i class="bi bi-wallet2"></i> Bayar</a></li>
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
                    <li class="nav-item"><a class="nav-link" href="#"><i class="bi bi-tags"></i> Kategori Menu</a></li>
                    <li class="nav-item"><a class="nav-link" href="#"><i class="bi bi-card-list"></i> Menu</a></li>
                    <li class="nav-item"><a class="nav-link" href="#"><i class="bi bi-collection"></i> Kategori Bahan</a></li>
                    <li class="nav-item"><a class="nav-link" href="#"><i class="bi bi-basket"></i> Bahan</a></li>
                    <li class="nav-item"><a class="nav-link" href="#"><i class="bi bi-journal-text"></i> Resep</a></li>
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
                    <li class="nav-item"><a class="nav-link" href="#"><i class="bi bi-clipboard-check"></i> Pesanan Pembelian</a></li>
                    <li class="nav-item"><a class="nav-link" href="#"><i class="bi bi-currency-dollar"></i> Pembayaran</a></li>
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
              
              <!-- Laporan Menu - All Roles -->
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
              
              <!-- Pengaturan Menu - All Roles -->
              <li class="nav-item">
                <a class="nav-link" href="#">
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
                <a class="nav-link" href="#">
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
                    <li class="nav-item"><a class="nav-link active" href="resto.php"><i class="bi bi-building"></i> Resto</a></li>
                    <li class="nav-item"><a class="nav-link" href="pegawai.php"><i class="bi bi-people"></i> Pegawai</a></li>
                    <li class="nav-item"><a class="nav-link" href="#"><i class="bi bi-truck"></i> Vendor</a></li>
                    <li class="nav-item"><a class="nav-link" href="#"><i class="bi bi-table"></i> Meja</a></li>
                    <li class="nav-item"><a class="nav-link" href="#"><i class="bi bi-credit-card"></i> Metode Pembayaran</a></li>
                    <li class="nav-item"><a class="nav-link" href="#"><i class="bi bi-wallet2"></i> Bayar</a></li>
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
                    <li class="nav-item"><a class="nav-link" href="#"><i class="bi bi-tags"></i> Kategori Menu</a></li>
                    <li class="nav-item"><a class="nav-link" href="#"><i class="bi bi-card-list"></i> Menu</a></li>
                    <li class="nav-item"><a class="nav-link" href="#"><i class="bi bi-collection"></i> Kategori Bahan</a></li>
                    <li class="nav-item"><a class="nav-link" href="#"><i class="bi bi-basket"></i> Bahan</a></li>
                    <li class="nav-item"><a class="nav-link" href="#"><i class="bi bi-journal-text"></i> Resep</a></li>
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
                    <li class="nav-item"><a class="nav-link" href="#"><i class="bi bi-clipboard-check"></i> Pesanan Pembelian</a></li>
                    <li class="nav-item"><a class="nav-link" href="#"><i class="bi bi-currency-dollar"></i> Pembayaran</a></li>
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
            <h1 class="h2">Manajemen Resto</h1>
            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#restoModal">
              <i class="bi bi-plus-circle"></i> Tambah Resto
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
                <input type="text" class="form-control me-2" name="search" placeholder="Cari nama, alamat, atau no HP..." value="<?php echo htmlspecialchars($search); ?>">
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
            <div class="col-md-6 text-end">
              <small class="text-muted">
                Menampilkan <?php echo count($restos); ?> dari <?php echo $total_records; ?> data
              </small>
            </div>
          </div>

          <!-- Data Table -->
          <div class="table-responsive">
            <table class="table table-striped table-hover">
              <thead class="table-dark">
                <tr>
                  <th>No</th>
                  <th>Nama Aplikasi</th>
                  <th class="d-none d-md-table-cell">Alamat</th>
                  <th>No HP</th>
                  <th class="d-none d-lg-table-cell">Server Key Midtrans</th>
                  <th class="d-none d-md-table-cell">Update Time</th>
                  <th>Aksi</th>
                </tr>
              </thead>
              <tbody>
                <?php if (empty($restos)): ?>
                  <tr>
                    <td colspan="7" class="text-center">Tidak ada data resto</td>
                  </tr>
                <?php else: ?>
                  <?php 
                  $no = $offset + 1;
                  foreach ($restos as $resto): 
                  ?>
                    <tr>
                      <td><?php echo $no++; ?></td>
                      <td><?php echo htmlspecialchars($resto['nama_aplikasi']); ?></td>
                      <td class="d-none d-md-table-cell"><?php echo htmlspecialchars($resto['alamat']); ?></td>
                      <td><?php echo htmlspecialchars($resto['no_hp']); ?></td>
                      <td class="d-none d-lg-table-cell"><?php echo htmlspecialchars(substr($resto['serverkeymidtrans'], 0, 20) . '...'); ?></td>
                      <td class="d-none d-md-table-cell"><?php echo htmlspecialchars($resto['update_time']); ?></td>
                      <td>
                        <div class="btn-group" role="group">
                          <a href="?edit=<?php echo $resto['id_app']; ?><?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?>&page=<?php echo $page; ?>" class="btn btn-sm btn-warning">
                            <i class="bi bi-pencil"></i>
                          </a>
                          <form method="POST" style="display: inline;" onsubmit="return confirm('Yakin ingin menghapus data ini?')">
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="id_app" value="<?php echo $resto['id_app']; ?>">
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
                    <a class="page-link" href="?page=<?php echo ($page - 1); ?><?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?>">
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
                    <a class="page-link" href="?page=1<?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?>">1</a>
                  </li>
                  <?php if ($start_page > 2): ?>
                    <li class="page-item disabled"><span class="page-link">...</span></li>
                  <?php endif; ?>
                <?php endif; ?>

                <?php for ($i = $start_page; $i <= $end_page; $i++): ?>
                  <li class="page-item <?php echo ($i == $page) ? 'active' : ''; ?>">
                    <a class="page-link" href="?page=<?php echo $i; ?><?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?>"><?php echo $i; ?></a>
                  </li>
                <?php endfor; ?>

                <?php if ($end_page < $total_pages): ?>
                  <?php if ($end_page < $total_pages - 1): ?>
                    <li class="page-item disabled"><span class="page-link">...</span></li>
                  <?php endif; ?>
                  <li class="page-item">
                    <a class="page-link" href="?page=<?php echo $total_pages; ?><?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?>"><?php echo $total_pages; ?></a>
                  </li>
                <?php endif; ?>

                <!-- Next Page -->
                <?php if ($page < $total_pages): ?>
                  <li class="page-item">
                    <a class="page-link" href="?page=<?php echo ($page + 1); ?><?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?>">
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

    <!-- Modal for Add/Edit Resto -->
    <div class="modal fade" id="restoModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><?php echo $edit_resto ? 'Edit Resto' : 'Tambah Resto'; ?></h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="<?php echo $edit_resto ? 'update' : 'create'; ?>">
                        <?php if ($edit_resto): ?>
                            <input type="hidden" name="id_app" value="<?php echo $edit_resto['id_app']; ?>">
                        <?php endif; ?>
                        
                        <div class="mb-3">
                            <label for="nama_aplikasi" class="form-label">Nama Aplikasi *</label>
                            <input type="text" class="form-control" id="nama_aplikasi" name="nama_aplikasi" 
                                   value="<?php echo $edit_resto ? htmlspecialchars($edit_resto['nama_aplikasi']) : ''; ?>" required>
                        </div>
                        
                        <div class="mb-3">
                            <label for="alamat" class="form-label">Alamat *</label>
                            <textarea class="form-control" id="alamat" name="alamat" rows="3" required><?php echo $edit_resto ? htmlspecialchars($edit_resto['alamat']) : ''; ?></textarea>
                        </div>
                        
                        <div class="mb-3">
                            <label for="no_hp" class="form-label">No HP *</label>
                            <input type="text" class="form-control" id="no_hp" name="no_hp" 
                                   value="<?php echo $edit_resto ? htmlspecialchars($edit_resto['no_hp']) : ''; ?>" required>
                        </div>
                        
                        <div class="mb-3">
                            <label for="serverkeymidtrans" class="form-label">Server Key Midtrans</label>
                            <input type="text" class="form-control" id="serverkeymidtrans" name="serverkeymidtrans" 
                                   value="<?php echo $edit_resto ? htmlspecialchars($edit_resto['serverkeymidtrans']) : ''; ?>">
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                        <button type="submit" class="btn btn-primary"><?php echo $edit_resto ? 'Update' : 'Simpan'; ?></button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="../js/bootstrap.bundle.min.js"></script>
    <script>
        // Auto show modal if editing
        <?php if ($edit_resto): ?>
            var modal = new bootstrap.Modal(document.getElementById('restoModal'));
            modal.show();
        <?php endif; ?>
    </script>
  </body>
</html>