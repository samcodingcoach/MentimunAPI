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
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'add_harga') {
        $id_produk = (int)$_POST['id_produk'];
        $tgl = $_POST['tgl'];
        $harga_pokok_resep = (float)$_POST['harga_pokok_resep'];
        $biaya_produksi = (float)$_POST['biaya_produksi'];
        $margin = (float)$_POST['margin'];
        $nominal = (float)$_POST['nominal'];
        $id_user = $_SESSION['id_user'];
        
        // Get id_resep from produk_menu
        $resep_stmt = $conn->prepare("SELECT id_resep FROM produk_menu WHERE id_produk = ?");
        $resep_stmt->bind_param("i", $id_produk);
        $resep_stmt->execute();
        $resep_result = $resep_stmt->get_result();
        
        if ($resep_result->num_rows > 0) {
            $resep_data = $resep_result->fetch_assoc();
            $id_resep = $resep_data['id_resep'];
            
            $insert_stmt = $conn->prepare("INSERT INTO harga_menu (id_produk, id_resep, tgl, harga_pokok_resep, biaya_produksi, margin, nominal, id_user) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
            $insert_stmt->bind_param("iisddddi", $id_produk, $id_resep, $tgl, $harga_pokok_resep, $biaya_produksi, $margin, $nominal, $id_user);
            
            if ($insert_stmt->execute()) {
                $message = 'Harga berhasil ditambahkan!';
            } else {
                $error = 'Gagal menambahkan harga: ' . $conn->error;
            }
        } else {
            $error = 'Produk tidak memiliki resep yang terkait!';
        }
    }
    
    if ($action === 'update_harga') {
        $id_harga = (int)$_POST['id_harga'];
        $biaya_produksi = (float)$_POST['biaya_produksi'];
        $margin = (float)$_POST['margin'];
        $nominal = (float)$_POST['nominal'];
        
        $update_stmt = $conn->prepare("UPDATE harga_menu SET nominal = ?, biaya_produksi = ?, margin = ? WHERE id_harga = ?");
        $update_stmt->bind_param("dddi", $nominal, $biaya_produksi, $margin, $id_harga);
        
        if ($update_stmt->execute()) {
            $message = 'Harga berhasil diperbarui!';
        } else {
            $error = 'Gagal memperbarui harga: ' . $conn->error;
        }
    }
}

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

// Get available resep for this product
$resep_data = [];
$resep_stmt = $conn->prepare("SELECT 
                            resep.id_resep,
                            resep.kode_resep,
                            COALESCE(SUM(resep_detail.nilai_ekpetasi), 0) as harga_pokok_resep
                            FROM resep 
                            LEFT JOIN resep_detail ON resep.id_resep = resep_detail.id_resep
                            WHERE resep.id_produk = ? AND resep.publish_menu = 1
                            GROUP BY resep.id_resep
                            ORDER BY resep.tanggal_release DESC");
$resep_stmt->bind_param("i", $id_produk);
$resep_stmt->execute();
$resep_result = $resep_stmt->get_result();
while ($resep_row = $resep_result->fetch_assoc()) {
    $resep_data[] = $resep_row;
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
                    <h1 class="h2">Set Harga Menu: <?php echo htmlspecialchars($product_info['nama_produk']); ?> (<?php echo htmlspecialchars($product_info['kode_produk']); ?>)</h1>
                    <div class="btn-toolbar mb-2 mb-md-0">
                        <a href="menu.php" class="btn btn-outline-secondary">
                            <i class="bi bi-arrow-left"></i> Kembali ke Menu
                        </a>
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
                <div class="table-responsive">
                            <table class="table table-striped table-hover">
                                <thead class="table-dark">
                                    <tr>
                                        <th>No</th>
                                        <th>Tanggal</th>
                                        <th>Harga Pokok</th>
                                        <th>Biaya Produksi</th>
                                        <th>Margin</th>
                                        <th>Nominal</th>
                                        <th>Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($harga_data)): ?>
                                    <tr>
                                        <td colspan="7" class="text-center">Tidak ada data harga</td>
                                    </tr>
                                    <?php else: ?>
                                    <?php 
                                    $no = ($page - 1) * $limit + 1;
                                    foreach ($harga_data as $harga): 
                                    ?>
                                    <tr>
                                        <td><?php echo $no++; ?></td>
                                        <td><?php echo htmlspecialchars($harga['tgl']); ?></td>
                                        
                                        <td>Rp <?php echo number_format($harga['harga_pokok_resep'], 0, ',', '.'); ?></td>
                                        <td>Rp <?php echo number_format($harga['biaya_produksi'], 0, ',', '.'); ?></td>
                                        <td>Rp <?php echo number_format($harga['margin'], 0, ',', '.'); ?></td>
                                        <td>Rp <?php echo number_format($harga['nominal'], 0, ',', '.'); ?></td>
                                        <td>
                                            <button type="button" class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#hargaModal" 
                                                    onclick="editHarga(<?php echo $harga['id_harga']; ?>, <?php echo $harga['harga_pokok_resep']; ?>, <?php echo $harga['biaya_produksi']; ?>, <?php echo $harga['margin']; ?>, <?php echo $harga['nominal']; ?>)">
                                                <i class="bi bi-pencil"></i> Harga
                                            </button>
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
                <!-- Penjualan Menu -->
                <li class="nav-item">
                    <a class="nav-link" data-bs-toggle="collapse" href="#penjualanMenuMobile" role="button">
                        <i class="bi bi-cash-stack"></i>
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

    <!-- Modal Harga -->
    <div class="modal fade" id="hargaModal" tabindex="-1" aria-labelledby="hargaModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <form id="hargaForm" method="POST">
                    <div class="modal-header">
                        <h5 class="modal-title" id="hargaModalLabel">Set Harga Menu</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <input type="hidden" name="action" value="add_harga">
                        <input type="hidden" name="id_harga" id="id_harga">
                        <input type="hidden" name="id_produk" value="<?php echo $id_produk; ?>">
                        <input type="hidden" name="id_resep" id="id_resep">
                        <input type="hidden" name="tgl" id="tgl" value="<?php echo date('Y-m-d'); ?>">
                        
                        <div class="mb-3">
                            <label for="harga_pokok_resep" class="form-label">Harga Pokok Resep</label>
                            <input type="number" class="form-control" name="harga_pokok_resep" id="harga_pokok_resep" step="0.01" readonly>
                        </div>
                        
                        <div class="mb-3">
                            <label for="biaya_produksi" class="form-label">Biaya Produksi <span class="text-danger">*</span></label>
                            <input type="number" class="form-control" name="biaya_produksi" id="biaya_produksi" step="0.01" required>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="margin_type" class="form-label">Tipe Margin <span class="text-danger">*</span></label>
                                    <select class="form-select" name="margin_type" id="margin_type" onchange="calculateMargin()" required>
                                        <option value="">Pilih Tipe Margin</option>
                                        <option value="persen">Persen (%)</option>
                                        <option value="nominal">Nominal (Rp)</option>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="margin_value" class="form-label">Nilai Margin <span class="text-danger">*</span></label>
                                    <input type="number" class="form-control" name="margin_value" id="margin_value" step="0.01" onchange="calculateMargin()" required>
                                </div>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="margin" class="form-label">Margin (Rp)</label>
                                    <input type="number" class="form-control" name="margin" id="margin" step="0.01" readonly>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="nominal" class="form-label">Harga Jual (Nominal)</label>
                                    <input type="number" class="form-control" name="nominal" id="nominal" step="0.01" readonly>
                                    <div class="form-text">Harga Pokok + Biaya Produksi + Margin</div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                        <button type="submit" class="btn btn-primary">Simpan Harga</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="../js/bootstrap.bundle.min.js"></script>
    <script>
        function addHarga() {
            document.getElementById('hargaModalLabel').textContent = 'Tambah Harga Menu';
            document.querySelector('input[name="action"]').value = 'add_harga';
            document.getElementById('id_harga').value = '';
            document.getElementById('biaya_produksi').value = '';
            document.getElementById('margin_type').value = '';
            document.getElementById('margin_value').value = '';
            document.getElementById('margin').value = '';
            document.getElementById('nominal').value = '';
        }
        
        function editHarga(id_harga, harga_pokok, biaya_produksi, margin, nominal) {
            document.getElementById('hargaModalLabel').textContent = 'Edit Harga Menu';
            document.querySelector('input[name="action"]').value = 'update_harga';
            document.getElementById('id_harga').value = id_harga;
            document.getElementById('harga_pokok_resep').value = harga_pokok;
            document.getElementById('biaya_produksi').value = biaya_produksi;
            document.getElementById('margin').value = margin;
            document.getElementById('nominal').value = nominal;
        }
        

        
        function calculateMargin() {
            const hargaPokok = parseFloat(document.getElementById('harga_pokok_resep').value) || 0;
            const biayaProduksi = parseFloat(document.getElementById('biaya_produksi').value) || 0;
            const marginType = document.getElementById('margin_type').value;
            const marginValue = parseFloat(document.getElementById('margin_value').value) || 0;
            
            let margin = 0;
            const totalCost = hargaPokok + biayaProduksi;
            
            if (marginType === 'persen') {
                margin = totalCost * (marginValue / 100);
            } else if (marginType === 'nominal') {
                margin = marginValue;
            }
            
            const nominal = totalCost + margin;
            
            document.getElementById('margin').value = margin.toFixed(2);
            document.getElementById('nominal').value = nominal.toFixed(2);
        }
        
        // Auto calculate when harga pokok or biaya produksi changes
        document.getElementById('harga_pokok_resep').addEventListener('input', calculateMargin);
        document.getElementById('biaya_produksi').addEventListener('input', calculateMargin);
    </script>
</body>
</html>