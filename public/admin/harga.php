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

// Get id_produk from URL
$id_produk = isset($_GET['id_produk']) ? (int)$_GET['id_produk'] : 0;

if ($id_produk <= 0) {
    header('Location: menu.php');
    exit();
}

// Get product information
$product_info = null;
$stmt = $conn->prepare("SELECT pm.nama_produk, pm.kode_produk, km.nama_kategori 
                       FROM produk_menu pm 
                       INNER JOIN kategori_menu km ON pm.id_kategori = km.id_kategori 
                       WHERE pm.id_produk = ?");
$stmt->bind_param("i", $id_produk);
$stmt->execute();
$result = $stmt->get_result();
if ($result->num_rows > 0) {
    $product_info = $result->fetch_assoc();
} else {
    header('Location: menu.php');
    exit();
}

// Pagination settings
$limit = 10;
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$offset = ($page - 1) * $limit;

// Get total count
$count_stmt = $conn->prepare("SELECT COUNT(*) as total FROM harga_menu hm
                              INNER JOIN produk_menu pm ON hm.id_produk = pm.id_produk
                              INNER JOIN resep r ON hm.id_resep = r.id_resep
                              INNER JOIN pegawai pg ON hm.id_user = pg.id_user
                              WHERE pm.id_produk = ?");
$count_stmt->bind_param("i", $id_produk);
$count_stmt->execute();
$count_result = $count_stmt->get_result();
$total_records = $count_result->fetch_assoc()['total'];
$total_pages = ceil($total_records / $limit);

// Get harga data with pagination
$harga_data = [];
$stmt = $conn->prepare("SELECT
                        DATE_FORMAT(harga_menu.tgl,'%d %M %Y %H:%i') AS tgl, 
                        harga_menu.id_harga, 
                        harga_menu.id_resep,
                        CONCAT(pg1.nama_lengkap,' - ',pg1.jabatan) as user_harga,
                        CONCAT(pg2.nama_lengkap,' - ',pg2.jabatan) as user_resep, 
                        resep.harga_pokok_resep, 
                        harga_menu.biaya_produksi, 
                        harga_menu.margin as margin, 
                        harga_menu.nominal
                       FROM harga_menu
                       INNER JOIN produk_menu ON harga_menu.id_produk = produk_menu.id_produk
                       INNER JOIN resep ON harga_menu.id_resep = resep.id_resep
                       INNER JOIN pegawai pg1 ON harga_menu.id_user = pg1.id_user
                       INNER JOIN pegawai pg2 ON resep.id_user = pg2.id_user
                       WHERE produk_menu.id_produk = ?
                       ORDER BY DATE(harga_menu.tgl) desc, harga_menu.nominal ASC
                       LIMIT ? OFFSET ?");
$stmt->bind_param("iii", $id_produk, $limit, $offset);
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $harga_data[] = $row;
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Set Harga - Resto007</title>
    <link href="../css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.7.2/font/bootstrap-icons.css" rel="stylesheet">
    <link href="../css/admin.css" rel="stylesheet">
    <style>
        .sidebar {
            min-height: 100vh;
            background-color: #f8f9fa;
        }
        .sidebar .nav-link {
            color: #333;
            padding: 0.75rem 1rem;
            border-radius: 0.375rem;
            margin-bottom: 0.25rem;
        }
        .sidebar .nav-link:hover {
            background-color: #e9ecef;
            color: #0d6efd;
        }
        .sidebar .nav-link.active {
            background-color: #0d6efd;
            color: white;
        }
        .sidebar .nav-link i {
            margin-right: 0.5rem;
            width: 1rem;
        }
        .main-content {
            padding: 2rem;
        }
        @media (max-width: 768px) {
            .sidebar {
                position: fixed;
                top: 0;
                left: -250px;
                width: 250px;
                height: 100vh;
                z-index: 1000;
                transition: left 0.3s ease;
                background-color: #f8f9fa;
                border-right: 1px solid #dee2e6;
            }
            .sidebar.show {
                left: 0;
            }
            .main-content {
                margin-left: 0;
                padding: 1rem;
            }
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
                            <div class="collapse show" id="produkMenu">
                                <ul class="nav flex-column ms-3">
                                    <li class="nav-item"><a class="nav-link" href="kategori_menu.php"><i class="bi bi-tags"></i> Kategori Menu</a></li>
                                    <li class="nav-item"><a class="nav-link active" href="menu.php"><i class="bi bi-list"></i> Menu</a></li>
                                    <li class="nav-item"><a class="nav-link" href="kategori_bahan.php"><i class="bi bi-tags"></i> Kategori Bahan</a></li>
                                    <li class="nav-item"><a class="nav-link" href="bahan.php"><i class="bi bi-egg"></i> Bahan</a></li>
                                    <li class="nav-item"><a class="nav-link" href="resep.php"><i class="bi bi-book"></i> Resep</a></li>
                                </ul>
                            </div>
                        </li>
                        <?php endif; ?>
                        
                        <?php if($_SESSION["jabatan"] == "Admin" || $_SESSION["jabatan"] == "Kasir"): ?>
                        <!-- Laporan Menu -->
                        <li class="nav-item">
                            <a class="nav-link" data-bs-toggle="collapse" href="#laporanMenu" role="button">
                                <i class="bi bi-file-earmark-text"></i>
                                <span>Laporan</span>
                                <i class="bi bi-chevron-down ms-auto"></i>
                            </a>
                            <div class="collapse" id="laporanMenu">
                                <ul class="nav flex-column ms-3">
                                    <li class="nav-item"><a class="nav-link" href="laporan_transaksi.php"><i class="bi bi-receipt"></i> Transaksi</a></li>
                                    <li class="nav-item"><a class="nav-link" href="laporan_pengeluaran.php"><i class="bi bi-graph-down"></i> Pengeluaran vs Penjualan</a></li>
                                    <li class="nav-item"><a class="nav-link" href="laporan_kuantitas.php"><i class="bi bi-bar-chart"></i> Kuantitas</a></li>
                                </ul>
                            </div>
                        </li>
                        <?php endif; ?>
                        
                        <!-- Pengaturan Menu -->
                        <li class="nav-item">
                            <a class="nav-link" data-bs-toggle="collapse" href="#pengaturanMenu" role="button">
                                <i class="bi bi-gear"></i>
                                <span>Pengaturan</span>
                                <i class="bi bi-chevron-down ms-auto"></i>
                            </a>
                            <div class="collapse" id="pengaturanMenu">
                                <ul class="nav flex-column ms-3">
                                    <li class="nav-item"><a class="nav-link" href="ubah_password.php"><i class="bi bi-key"></i> Ubah Password</a></li>
                                </ul>
                            </div>
                        </li>
                        
                        <li class="nav-item">
                            <a class="nav-link" href="logout.php">
                                <i class="bi bi-box-arrow-right"></i>
                                <span>Logout</span>
                            </a>
                        </li>
                    </ul>
                </div>
            </div>

            <!-- Main Content -->
            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4 main-content">
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="h2">Set Harga Menu</h1>
                    <div class="btn-toolbar mb-2 mb-md-0">
                        <a href="menu.php" class="btn btn-outline-secondary">
                            <i class="bi bi-arrow-left"></i> Kembali ke Menu
                        </a>
                    </div>
                </div>

                <!-- Product Info -->
                <div class="card mb-4">
                    <div class="card-body">
                        <h5 class="card-title"><?php echo htmlspecialchars($product_info['nama_produk']); ?></h5>
                        <p class="card-text">
                            <strong>Kode Produk:</strong> <?php echo htmlspecialchars($product_info['kode_produk']); ?><br>
                            <strong>Kategori:</strong> <?php echo htmlspecialchars($product_info['nama_kategori']); ?>
                        </p>
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

                <!-- Harga Table -->
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">Riwayat Harga</h5>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-striped table-hover">
                                <thead class="table-dark">
                                    <tr>
                                        <th>No</th>
                                        <th>Tanggal</th>
                                        <th>User Harga</th>
                                        <th>User Resep</th>
                                        <th>Harga Pokok</th>
                                        <th>Biaya Produksi</th>
                                        <th>Margin</th>
                                        <th>Nominal</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($harga_data)): ?>
                                    <tr>
                                        <td colspan="8" class="text-center">Tidak ada data harga</td>
                                    </tr>
                                    <?php else: ?>
                                    <?php 
                                    $no = ($page - 1) * $limit + 1;
                                    foreach ($harga_data as $harga): 
                                    ?>
                                    <tr>
                                        <td><?php echo $no++; ?></td>
                                        <td><?php echo htmlspecialchars($harga['tgl']); ?></td>
                                        <td><?php echo htmlspecialchars($harga['user_harga']); ?></td>
                                        <td><?php echo htmlspecialchars($harga['user_resep']); ?></td>
                                        <td>Rp <?php echo number_format($harga['harga_pokok_resep'], 0, ',', '.'); ?></td>
                                        <td>Rp <?php echo number_format($harga['biaya_produksi'], 0, ',', '.'); ?></td>
                                        <td><?php echo number_format($harga['margin'], 2, ',', '.'); ?>%</td>
                                        <td>Rp <?php echo number_format($harga['nominal'], 0, ',', '.'); ?></td>
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
                                    <a class="page-link" href="?id_produk=<?php echo $id_produk; ?>&page=<?php echo $page - 1; ?>">
                                        <i class="bi bi-chevron-left"></i>
                                    </a>
                                </li>
                                <?php endif; ?>
                                
                                <?php for ($i = max(1, $page - 2); $i <= min($total_pages, $page + 2); $i++): ?>
                                <li class="page-item <?php echo $i == $page ? 'active' : ''; ?>">
                                    <a class="page-link" href="?id_produk=<?php echo $id_produk; ?>&page=<?php echo $i; ?>">
                                        <?php echo $i; ?>
                                    </a>
                                </li>
                                <?php endfor; ?>
                                
                                <?php if ($page < $total_pages): ?>
                                <li class="page-item">
                                    <a class="page-link" href="?id_produk=<?php echo $id_produk; ?>&page=<?php echo $page + 1; ?>">
                                        <i class="bi bi-chevron-right"></i>
                                    </a>
                                </li>
                                <?php endif; ?>
                            </ul>
                        </nav>
                        <?php endif; ?>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <!-- Sidebar Offcanvas for Mobile -->
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
                            <li class="nav-item"><a class="nav-link" href="metode_pembayaran.php"><i class="bi bi-credit-card"></i> Metode Pembayaran</a></li>
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
                    <div class="collapse show" id="produkMenuMobile">
                        <ul class="nav flex-column ms-3">
                            <li class="nav-item"><a class="nav-link" href="kategori_menu.php"><i class="bi bi-tags"></i> Kategori Menu</a></li>
                            <li class="nav-item"><a class="nav-link active" href="menu.php"><i class="bi bi-list"></i> Menu</a></li>
                            <li class="nav-item"><a class="nav-link" href="kategori_bahan.php"><i class="bi bi-tags"></i> Kategori Bahan</a></li>
                            <li class="nav-item"><a class="nav-link" href="bahan.php"><i class="bi bi-egg"></i> Bahan</a></li>
                            <li class="nav-item"><a class="nav-link" href="resep.php"><i class="bi bi-book"></i> Resep</a></li>
                        </ul>
                    </div>
                </li>
                <?php endif; ?>
                
                <?php if($_SESSION["jabatan"] == "Admin" || $_SESSION["jabatan"] == "Kasir"): ?>
                <!-- Laporan Menu -->
                <li class="nav-item">
                    <a class="nav-link" data-bs-toggle="collapse" href="#laporanMenuMobile" role="button">
                        <i class="bi bi-file-earmark-text"></i>
                        <span>Laporan</span>
                        <i class="bi bi-chevron-down ms-auto"></i>
                    </a>
                    <div class="collapse" id="laporanMenuMobile">
                        <ul class="nav flex-column ms-3">
                            <li class="nav-item"><a class="nav-link" href="laporan_transaksi.php"><i class="bi bi-receipt"></i> Transaksi</a></li>
                            <li class="nav-item"><a class="nav-link" href="laporan_pengeluaran.php"><i class="bi bi-graph-down"></i> Pengeluaran vs Penjualan</a></li>
                            <li class="nav-item"><a class="nav-link" href="laporan_kuantitas.php"><i class="bi bi-bar-chart"></i> Kuantitas</a></li>
                        </ul>
                    </div>
                </li>
                <?php endif; ?>
                
                <!-- Pengaturan Menu -->
                <li class="nav-item">
                    <a class="nav-link" data-bs-toggle="collapse" href="#pengaturanMenuMobile" role="button">
                        <i class="bi bi-gear"></i>
                        <span>Pengaturan</span>
                        <i class="bi bi-chevron-down ms-auto"></i>
                    </a>
                    <div class="collapse" id="pengaturanMenuMobile">
                        <ul class="nav flex-column ms-3">
                            <li class="nav-item"><a class="nav-link" href="ubah_password.php"><i class="bi bi-key"></i> Ubah Password</a></li>
                        </ul>
                    </div>
                </li>
                
                <li class="nav-item">
                    <a class="nav-link" href="logout.php">
                        <i class="bi bi-box-arrow-right"></i>
                        <span>Logout</span>
                    </a>
                </li>
            </ul>
        </div>
    </div>

    <script src="../js/bootstrap.bundle.min.js"></script>
</body>
</html>