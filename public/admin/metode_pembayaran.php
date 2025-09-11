<?php
session_start();
require_once '../../config/koneksi.php';
require_once '../../config/encryption.php';

// Check if user is logged in
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    header('Location: login.php');
    exit();
}

// Initialize variables
$message = '';
$error = '';
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$filter = isset($_GET['filter']) ? $_GET['filter'] : '';
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 10;
$offset = ($page - 1) * $limit;

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        if ($_POST['action'] === 'update') {
            $id_bayar = (int)$_POST['id_bayar'];
            $kategori = trim($_POST['kategori']);
            $no_rek = trim($_POST['no_rek']);
            $biaya_admin = (float)$_POST['biaya_admin'];
            $keterangan = trim($_POST['keterangan']);
            $pramusaji = $_POST['pramusaji'];
            $aktif = $_POST['aktif'];
            
            if (!empty($kategori)) {
                $stmt = $conn->prepare("UPDATE metode_pembayaran SET kategori = ?, no_rek = ?, biaya_admin = ?, keterangan = ?, pramusaji = ?, aktif = ? WHERE id_bayar = ?");
                if ($stmt) {
                    $stmt->bind_param("ssdssii", $kategori, $no_rek, $biaya_admin, $keterangan, $pramusaji, $aktif, $id_bayar);
                    if ($stmt->execute()) {
                        $message = "Metode pembayaran berhasil diperbarui!";
                    } else {
                        $error = "Gagal memperbarui metode pembayaran!";
                    }
                    $stmt->close();
                } else {
                    $error = "Error: " . $conn->error;
                }
            } else {
                $error = "Kategori tidak boleh kosong!";
            }
        }
    }
}

// Handle edit request
$edit_data = null;
if (isset($_GET['edit'])) {
    $edit_id = (int)$_GET['edit'];
    $stmt = $conn->prepare("SELECT * FROM metode_pembayaran WHERE id_bayar = ?");
    if ($stmt) {
        $stmt->bind_param("i", $edit_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $edit_data = $result->fetch_assoc();
        $stmt->close();
    } else {
        $error = "Error: " . $conn->error;
    }
}

// Build query for fetching metode pembayaran
$where_conditions = [];
$params = [];

if (!empty($search)) {
    $where_conditions[] = "(kategori LIKE ? OR no_rek LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

if (!empty($filter)) {
    if ($filter === 'aktif') {
        $where_conditions[] = "aktif = '1'";
    } elseif ($filter === 'nonaktif') {
        $where_conditions[] = "aktif = '0'";
    } elseif ($filter === 'pramusaji') {
        $where_conditions[] = "pramusaji = '1'";
    }
}

$where_clause = !empty($where_conditions) ? 'WHERE ' . implode(' AND ', $where_conditions) : '';

// Get total count
$count_sql = "SELECT COUNT(*) FROM metode_pembayaran $where_clause";
if (!empty($params)) {
    $count_stmt = $conn->prepare($count_sql);
    if ($count_stmt) {
        $types = str_repeat('s', count($params));
        $count_stmt->bind_param($types, ...$params);
        $count_stmt->execute();
        $result = $count_stmt->get_result();
        $total_records = $result->fetch_row()[0];
        $count_stmt->close();
    } else {
        $error = "Error: " . $conn->error;
        $total_records = 0;
    }
} else {
    $result = $conn->query($count_sql);
    if ($result) {
        $total_records = $result->fetch_row()[0];
    } else {
        $error = "Error: " . $conn->error;
        $total_records = 0;
    }
}
$total_pages = ceil($total_records / $limit);

// Get metode pembayaran data
$sql = "SELECT * FROM metode_pembayaran $where_clause ORDER BY id_bayar DESC LIMIT $limit OFFSET $offset";
if (!empty($params)) {
    $stmt = $conn->prepare($sql);
    if ($stmt) {
        $types = str_repeat('s', count($params));
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $result = $stmt->get_result();
        $metode_list = $result->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
    } else {
        $error = "Error: " . $conn->error;
        $metode_list = [];
    }
} else {
    $result = $conn->query($sql);
    if ($result) {
        $metode_list = $result->fetch_all(MYSQLI_ASSOC);
    } else {
        $error = "Error: " . $conn->error;
        $metode_list = [];
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Metode Pembayaran - Admin Panel</title>
    <link href="../css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.7.2/font/bootstrap-icons.css" rel="stylesheet">
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
                    <li class="nav-item"><a class="nav-link" href="vendor.php"><i class="bi bi-truck"></i> Vendor</a></li>
                    <li class="nav-item"><a class="nav-link" href="meja.php"><i class="bi bi-table"></i> Meja</a></li>
                    <li class="nav-item"><a class="nav-link active" href="metode_pembayaran.php"><i class="bi bi-credit-card"></i> Metode Pembayaran</a></li>
                    
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

        <!-- Mobile Sidebar (Offcanvas) -->
        <div class="offcanvas offcanvas-start d-lg-none" tabindex="-1" id="sidebarOffcanvas">
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
              <!-- Master Menu -->
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
                    <li class="nav-item"><a class="nav-link active" href="metode_pembayaran.php"><i class="bi bi-credit-card"></i> Metode Pembayaran</a></li>
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
            <h1 class="h2">Metode Pembayaran</h1>
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
                            <input type="text" class="form-control me-2" name="search" placeholder="Cari kategori atau nomor rekening..." value="<?php echo htmlspecialchars($search); ?>">
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
                                <option value="pramusaji" <?php echo $filter === 'pramusaji' ? 'selected' : ''; ?>>Untuk Pramusaji</option>
                            </select>
                            <input type="hidden" name="search" value="<?php echo htmlspecialchars($search); ?>">
                            <input type="hidden" name="page" value="1">
                        </form>
                    </div>
                </div>

                <!-- Metode Pembayaran Table -->
                <div class="table-responsive mb-4">
                    <table class="table table-striped table-hover">
                        <thead class="table-dark">
                            <tr>
                                <th>NO</th>
                                <th>KATEGORI</th>
                                <th>NO REKENING</th>
                                <th class="d-none d-md-table-cell">BIAYA ADMIN</th>
                                <th class="d-none d-md-table-cell">PRAMUSAJI</th>
                                <th class="d-none d-md-table-cell">STATUS</th>
                                <th>AKSI</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($metode_list)): ?>
                            <tr>
                                <td colspan="7" class="text-center">Tidak ada data metode pembayaran</td>
                            </tr>
                            <?php else: ?>
                            <?php $no = $offset + 1; foreach ($metode_list as $metode): ?>
                            <tr>
                                <td><?php echo $no++; ?></td>
                                <td>
                                    <strong><?php echo htmlspecialchars($metode['kategori']); ?></strong>
                                </td>
                                <td><?php echo htmlspecialchars($metode['no_rek']); ?></td>
                                <td class="d-none d-md-table-cell">
                                    Rp <?php echo number_format((float)$metode['biaya_admin'], 2, ',', '.'); ?>
                                </td>
                                <td class="d-none d-md-table-cell">
                                    <?php if ($metode['pramusaji'] == '1'): ?>
                                        <span class="badge bg-success">Ya</span>
                                    <?php else: ?>
                                        <span class="badge bg-secondary">Tidak</span>
                                    <?php endif; ?>
                                </td>
                                <td class="d-none d-md-table-cell">
                                    <?php if ($metode['aktif'] == '1'): ?>
                                        <span class="badge bg-success">Aktif</span>
                                    <?php else: ?>
                                        <span class="badge bg-secondary">Non-aktif</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <a href="?edit=<?php echo $metode['id_bayar']; ?><?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?><?php echo !empty($filter) ? '&filter=' . urlencode($filter) : ''; ?>&page=<?php echo $page; ?>" class="btn btn-sm btn-warning">
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
                
                <div class="text-center mt-2">
                    <small class="text-muted">
                        Menampilkan <?php echo min($offset + 1, $total_records); ?> dari <?php echo $total_records; ?> data metode pembayaran (halaman <?php echo $page; ?> dari <?php echo $total_pages; ?>)
                    </small>
                </div>
                <?php endif; ?>
            </main>
        </div>
    </div>

    <!-- Edit Modal -->
    <?php if ($edit_data): ?>
    <div class="modal fade show" id="editModal" tabindex="-1" style="display: block;" data-bs-backdrop="static">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Edit Metode Pembayaran</h5>
                    <a href="?<?php echo !empty($search) ? 'search=' . urlencode($search) . '&' : ''; ?><?php echo !empty($filter) ? 'filter=' . urlencode($filter) . '&' : ''; ?>page=<?php echo $page; ?>" class="btn-close"></a>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="update">
                        <input type="hidden" name="id_bayar" value="<?php echo $edit_data['id_bayar']; ?>">
                        
                        <div class="mb-3">
                            <label for="kategori" class="form-label">Kategori *</label>
                            <input type="text" class="form-control" id="kategori" name="kategori" value="<?php echo htmlspecialchars($edit_data['kategori']); ?>" required>
                        </div>
                        
                        <div class="mb-3">
                            <label for="no_rek" class="form-label">Nomor Rekening</label>
                            <input type="text" class="form-control" id="no_rek" name="no_rek" value="<?php echo htmlspecialchars($edit_data['no_rek']); ?>">
                        </div>
                        
                        <div class="mb-3">
                            <label for="biaya_admin" class="form-label">Biaya Admin</label>
                            <input type="number" step="0.01" class="form-control" id="biaya_admin" name="biaya_admin" value="<?php echo $edit_data['biaya_admin']; ?>">
                        </div>
                        
                        <div class="mb-3">
                            <label for="keterangan" class="form-label">Keterangan</label>
                            <textarea class="form-control" id="keterangan" name="keterangan" rows="3"><?php echo htmlspecialchars($edit_data['keterangan']); ?></textarea>
                        </div>
                        
                        <div class="mb-3">
                            <label for="pramusaji" class="form-label">Untuk Pramusaji</label>
                            <select class="form-select" id="pramusaji" name="pramusaji" required>
                                <option value="0" <?php echo $edit_data['pramusaji'] == '0' ? 'selected' : ''; ?>>Tidak</option>
                                <option value="1" <?php echo $edit_data['pramusaji'] == '1' ? 'selected' : ''; ?>>Ya</option>
                            </select>
                        </div>
                        
                        <div class="mb-3">
                            <label for="aktif" class="form-label">Status</label>
                            <select class="form-select" id="aktif" name="aktif" required>
                                <option value="1" <?php echo $edit_data['aktif'] == '1' ? 'selected' : ''; ?>>Aktif</option>
                                <option value="0" <?php echo $edit_data['aktif'] == '0' ? 'selected' : ''; ?>>Non-aktif</option>
                            </select>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <a href="?<?php echo !empty($search) ? 'search=' . urlencode($search) . '&' : ''; ?><?php echo !empty($filter) ? 'filter=' . urlencode($filter) . '&' : ''; ?>page=<?php echo $page; ?>" class="btn btn-secondary">Batal</a>
                        <button type="submit" class="btn btn-primary">Simpan Perubahan</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <div class="modal-backdrop fade show"></div>
    <?php endif; ?>

        </main>
      </div>
    </div>

    <script src="../js/bootstrap.bundle.min.js"></script>
</body>
</html>