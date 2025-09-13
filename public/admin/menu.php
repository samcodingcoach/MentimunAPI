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

// Function to generate product code
function generateProductCode($conn) {
    $year = date('y'); // 2 digit year
    $prefix = "PM{$year}-";
    
    // Get the last product code for this year
    $stmt = $conn->prepare("SELECT kode_produk FROM produk_menu WHERE kode_produk LIKE ? ORDER BY kode_produk DESC LIMIT 1");
    $like_pattern = $prefix . '%';
    $stmt->bind_param("s", $like_pattern);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($row = $result->fetch_assoc()) {
        // Extract number from last code and increment
        $last_code = $row['kode_produk'];
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
                $kode_produk = trim($_POST['kode_produk']);
                $nama_produk = trim($_POST['nama_produk']);
                $id_kategori = (int)$_POST['id_kategori'];
                $aktif = isset($_POST['aktif']) ? '1' : '0';
                
                if (!empty($nama_produk) && $id_kategori > 0) {
                    // Check if product code already exists
                    if (!empty($kode_produk)) {
                        $stmt = $conn->prepare("SELECT id_produk FROM produk_menu WHERE kode_produk = ?");
                        $stmt->bind_param("s", $kode_produk);
                        $stmt->execute();
                        $result = $stmt->get_result();
                        if ($result->fetch_assoc()) {
                            $error = 'Kode produk sudah digunakan!';
                            break;
                        }
                    } else {
                        $kode_produk = generateProductCode($conn);
                    }
                    
                    // Handle image upload
                    $image_uploaded = false;
                    if (isset($_FILES['gambar']) && $_FILES['gambar']['error'] == 0) {
                        $upload_dir = __DIR__ . '/../images/';
                        $file_tmp = $_FILES['gambar']['tmp_name'];
                        $file_size = $_FILES['gambar']['size'];
                        $file_type = $_FILES['gambar']['type'];
                        
                        // Validate file type
                        $allowed_types = ['image/jpeg', 'image/jpg', 'image/png'];
                        if (!in_array($file_type, $allowed_types)) {
                            $error = 'Format gambar harus JPG, JPEG, atau PNG!';
                            break;
                        }
                        
                        // Validate file size (max 500KB)
                        if ($file_size > 500 * 1024) {
                            $error = 'Ukuran gambar maksimal 500KB!';
                            break;
                        }
                        
                        // Get image dimensions
                        $image_info = getimagesize($file_tmp);
                        if ($image_info === false) {
                            $error = 'File bukan gambar yang valid!';
                            break;
                        }
                        
                        $width = $image_info[0];
                        $height = $image_info[1];
                        
                        // Validate dimensions (300x300px)
                        if ($width != 300 || $height != 300) {
                            $error = 'Dimensi gambar harus 300x300 pixel!';
                            break;
                        }
                        
                        // Create upload directory if not exists
                        if (!is_dir($upload_dir)) {
                            mkdir($upload_dir, 0755, true);
                        }
                        
                        // Set filename as kode_produk
                        $file_extension = pathinfo($_FILES['gambar']['name'], PATHINFO_EXTENSION);
                        $new_filename = $kode_produk . '.' . $file_extension;
                        $upload_path = $upload_dir . $new_filename;
                        
                        if (move_uploaded_file($file_tmp, $upload_path)) {
                            $image_uploaded = true;
                        } else {
                            $error = 'Gagal mengupload gambar!';
                            break;
                        }
                    }
                    
                    $stmt = $conn->prepare("INSERT INTO produk_menu (kode_produk, nama_produk, id_kategori, aktif) VALUES (?, ?, ?, ?)");
                    $stmt->bind_param("ssis", $kode_produk, $nama_produk, $id_kategori, $aktif);
                    if ($stmt->execute()) {
                        $message = 'Data produk menu berhasil ditambahkan!' . ($image_uploaded ? ' Gambar berhasil diupload.' : '');
                    } else {
                        $error = 'Error: ' . $conn->error;
                        // Delete uploaded image if database insert failed
                        if ($image_uploaded && isset($upload_path) && file_exists($upload_path)) {
                            unlink($upload_path);
                        }
                    }
                } else {
                    $error = 'Nama produk dan kategori wajib diisi!';
                }
                break;
                
            case 'update':
                $id_produk = (int)$_POST['id_produk'];
                $kode_produk = trim($_POST['kode_produk']);
                $nama_produk = trim($_POST['nama_produk']);
                $id_kategori = (int)$_POST['id_kategori'];
                $aktif = isset($_POST['aktif']) ? '1' : '0';
                
                if (!empty($nama_produk) && $id_kategori > 0) {
                    // Get old product code for image handling
                    $stmt = $conn->prepare("SELECT kode_produk FROM produk_menu WHERE id_produk = ?");
                    $stmt->bind_param("i", $id_produk);
                    $stmt->execute();
                    $result = $stmt->get_result();
                    $old_product = $result->fetch_assoc();
                    $old_kode_produk = $old_product['kode_produk'];
                    
                    // Check if product code already exists for other products
                    if (!empty($kode_produk)) {
                        $stmt = $conn->prepare("SELECT id_produk FROM produk_menu WHERE kode_produk = ? AND id_produk != ?");
                        $stmt->bind_param("si", $kode_produk, $id_produk);
                        $stmt->execute();
                        $result = $stmt->get_result();
                        if ($result->fetch_assoc()) {
                            $error = 'Kode produk sudah digunakan oleh produk lain!';
                            break;
                        }
                    }
                    
                    // Handle image upload
                    $image_uploaded = false;
                    $new_image_path = null;
                    if (isset($_FILES['gambar']) && $_FILES['gambar']['error'] == 0) {
                        $upload_dir = __DIR__ . '/../images/';
                        $file_tmp = $_FILES['gambar']['tmp_name'];
                        $file_size = $_FILES['gambar']['size'];
                        $file_type = $_FILES['gambar']['type'];
                        
                        // Validate file type
                        $allowed_types = ['image/jpeg', 'image/jpg', 'image/png'];
                        if (!in_array($file_type, $allowed_types)) {
                            $error = 'Format gambar harus JPG, JPEG, atau PNG!';
                            break;
                        }
                        
                        // Validate file size (max 500KB)
                        if ($file_size > 500 * 1024) {
                            $error = 'Ukuran gambar maksimal 500KB!';
                            break;
                        }
                        
                        // Get image dimensions
                        $image_info = getimagesize($file_tmp);
                        if ($image_info === false) {
                            $error = 'File bukan gambar yang valid!';
                            break;
                        }
                        
                        $width = $image_info[0];
                        $height = $image_info[1];
                        
                        // Validate dimensions (300x300px)
                        if ($width != 300 || $height != 300) {
                            $error = 'Dimensi gambar harus 300x300 pixel!';
                            break;
                        }
                        
                        // Create upload directory if not exists
                        if (!is_dir($upload_dir)) {
                            mkdir($upload_dir, 0755, true);
                        }
                        
                        // Set filename as kode_produk
                        $file_extension = pathinfo($_FILES['gambar']['name'], PATHINFO_EXTENSION);
                        $new_filename = $kode_produk . '.' . $file_extension;
                        $new_image_path = $upload_dir . $new_filename;
                        
                        if (move_uploaded_file($file_tmp, $new_image_path)) {
                            $image_uploaded = true;
                            
                            // Delete old image if kode_produk changed
                            if ($old_kode_produk != $kode_produk) {
                                $old_extensions = ['jpg', 'jpeg', 'png'];
                                foreach ($old_extensions as $ext) {
                                    $old_image_path = $upload_dir . $old_kode_produk . '.' . $ext;
                                    if (file_exists($old_image_path)) {
                                        unlink($old_image_path);
                                    }
                                }
                            }
                        } else {
                            $error = 'Gagal mengupload gambar!';
                            break;
                        }
                    }
                    
                    $stmt = $conn->prepare("UPDATE produk_menu SET kode_produk = ?, nama_produk = ?, id_kategori = ?, aktif = ? WHERE id_produk = ?");
                    $stmt->bind_param("ssisi", $kode_produk, $nama_produk, $id_kategori, $aktif, $id_produk);
                    if ($stmt->execute()) {
                        $message = 'Data produk menu berhasil diperbarui!' . ($image_uploaded ? ' Gambar berhasil diupload.' : '');
                    } else {
                        $error = 'Error: ' . $conn->error;
                        // Delete uploaded image if database update failed
                        if ($image_uploaded && $new_image_path && file_exists($new_image_path)) {
                            unlink($new_image_path);
                        }
                    }
                } else {
                    $error = 'Nama produk dan kategori harus diisi!';
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
    $where_conditions[] = "(pm.nama_produk LIKE ? OR pm.kode_produk LIKE ? OR km.nama_kategori LIKE ?)";
    $search_param = "%$search%";
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
    $types .= 'sss';
}

if (!empty($filter) && in_array($filter, ['1', '0'])) {
    $where_conditions[] = "pm.aktif = ?";
    $params[] = $filter;
    $types .= 's';
}

$where_clause = !empty($where_conditions) ? 'WHERE ' . implode(' AND ', $where_conditions) : '';

// Complex query with UNION as specified in gemini.md
$base_query = "
    SELECT
        pm.id_produk, 
        pm.kode_produk, 
        CONCAT('[',km.nama_kategori,'] ', pm.nama_produk) as nama_produk,
        COALESCE(CONCAT('Rp ',FORMAT(hm.nominal,0)), 'Not Set') as harga, 
        DATE_FORMAT(hm.tgl,'%d %M %Y') as tgl, 
        CONCAT(pg.nama_lengkap,' [',pg.jabatan,']') as pegawai,
        pm.aktif
    FROM produk_menu pm
    INNER JOIN kategori_menu km ON pm.id_kategori = km.id_kategori
    LEFT JOIN (
        SELECT id_produk, MAX(tgl) AS max_tgl
        FROM harga_menu
        GROUP BY id_produk
    ) AS lh ON pm.id_produk = lh.id_produk
    LEFT JOIN harga_menu hm ON pm.id_produk = hm.id_produk AND hm.tgl = lh.max_tgl
    LEFT JOIN pegawai pg ON hm.id_user = pg.id_user
";

// Get total count for pagination
$count_sql = "SELECT COUNT(DISTINCT pm.id_produk) as total FROM produk_menu pm INNER JOIN kategori_menu km ON pm.id_kategori = km.id_kategori $where_clause";
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

// Get product data with complex UNION query
$union_query = "
(
    $base_query
    $where_clause
    ORDER BY pm.id_produk DESC
    LIMIT 1
)
UNION ALL
(
    $base_query
    " . (!empty($where_conditions) ? $where_clause . " AND " : "WHERE ") . "pm.id_produk NOT IN (
        SELECT id_produk FROM (
            SELECT id_produk 
            FROM produk_menu 
            ORDER BY id_produk DESC 
            LIMIT 1
        ) AS sub
    )
    ORDER BY COALESCE(hm.tgl, '1970-01-01') DESC, pm.nama_produk ASC
    LIMIT $limit OFFSET $offset
)
";

if (!empty($params)) {
    // For UNION query, we need to duplicate parameters
    $union_params = array_merge($params, $params);
    $union_types = $types . $types;
    $stmt = $conn->prepare($union_query);
    $stmt->bind_param($union_types, ...$union_params);
    $stmt->execute();
    $result = $stmt->get_result();
    $products = $result->fetch_all(MYSQLI_ASSOC);
} else {
    $result = $conn->query($union_query);
    if ($result) {
        $products = $result->fetch_all(MYSQLI_ASSOC);
    } else {
        $error = 'Error fetching data: ' . $conn->error;
        $products = [];
    }
}

// Get single product for editing
$edit_product = null;
if (isset($_GET['edit'])) {
    $stmt = $conn->prepare("SELECT * FROM produk_menu WHERE id_produk = ?");
    $stmt->bind_param("i", $_GET['edit']);
    $stmt->execute();
    $result = $stmt->get_result();
    $edit_product = $result->fetch_assoc();
}

// Get categories for dropdown
$categories_result = $conn->query("SELECT id_kategori, nama_kategori FROM kategori_menu ORDER BY nama_kategori ASC");
$categories = $categories_result->fetch_all(MYSQLI_ASSOC);
?>

<!doctype html>
<html lang="en">
  <head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Produk Menu - Admin Dashboard</title>
    <link href="../css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css" rel="stylesheet">
    <link href="../css/admin.css" rel="stylesheet">
    <style>
    .searchable-select {
        position: relative;
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
        padding: 8px 12px;
        border-bottom: 1px solid #dee2e6;
    }
    .searchable-select .dropdown-item {
        cursor: pointer;
    }
    .searchable-select .dropdown-item:hover {
        background-color: #f8f9fa;
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
            <h1 class="h2">Produk Menu</h1>
            <div class="btn-toolbar mb-2 mb-md-0">
              <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#productModal">
                <i class="bi bi-plus"></i> Tambah Produk Menu
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
                <input type="text" class="form-control me-2" name="search" placeholder="Cari produk menu..." value="<?php echo htmlspecialchars($search); ?>">
                <button class="btn btn-outline-secondary" type="submit">
                  <i class="bi bi-search"></i>
                </button>
                <?php if (!empty($search)): ?>
                <a href="menu.php" class="btn btn-outline-danger ms-2">
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

          <!-- Products Table -->
          <div class="table-responsive">
            <table class="table table-striped table-hover">
              <thead class="table-dark">
                <tr>
                  <th>No</th>
                  <th>Kode Produk</th>
                  <th>Nama Produk</th>
                  <th>Harga</th>
                  <th>Tanggal</th>
                  <th>Pegawai</th>
                  <th>Status</th>
                  <th>Aksi</th>
                </tr>
              </thead>
              <tbody>
                <?php if (empty($products)): ?>
                <tr>
                  <td colspan="8" class="text-center">Tidak ada data produk menu</td>
                </tr>
                <?php else: ?>
                <?php 
                $no = ($page - 1) * $limit + 1;
                foreach ($products as $product): 
                ?>
                <tr>
                  <td><?php echo $no++; ?></td>
                  <td>
                    <a href="#" class="text-decoration-none" onclick="showProductImage('<?php echo htmlspecialchars($product['kode_produk']); ?>', '<?php echo htmlspecialchars($product['nama_produk']); ?>')" data-bs-toggle="modal" data-bs-target="#imageModal">
                      <?php echo htmlspecialchars($product['kode_produk']); ?>
                    </a>
                  </td>
                  <td><?php echo htmlspecialchars($product['nama_produk']); ?></td>
                  <td><?php echo htmlspecialchars($product['harga']); ?></td>
                  <td><?php echo htmlspecialchars($product['tgl'] ?? '-'); ?></td>
                  <td><?php echo htmlspecialchars($product['pegawai'] ?? '-'); ?></td>
                  <td>
                    <?php if ($product['aktif'] == '1'): ?>
                    <span class="badge bg-success">Aktif</span>
                    <?php else: ?>
                    <span class="badge bg-secondary">Tidak Aktif</span>
                    <?php endif; ?>
                  </td>
                  <td>
                    <button type="button" class="btn btn-sm btn-warning" onclick="editProduct(<?php echo $product['id_produk']; ?>)">
                      <i class="bi bi-pencil"></i> Edit
                    </button>
                    <a href="harga.php?id_produk=<?php echo $product['id_produk']; ?>" class="btn btn-sm btn-info ms-1">
                      <i class="bi bi-currency-dollar"></i> Set Harga
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

    <!-- Product Modal -->
    <div class="modal fade" id="productModal" tabindex="-1">
      <div class="modal-dialog">
        <div class="modal-content">
          <div class="modal-header">
            <h5 class="modal-title" id="productModalTitle">Tambah Produk Menu</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
          </div>
          <form id="productForm" method="POST" enctype="multipart/form-data">
            <div class="modal-body">
              <input type="hidden" name="action" id="formAction" value="create">
              <input type="hidden" name="id_produk" id="productId">
              
              <div class="mb-3">
                <label for="kode_produk" class="form-label">Kode Produk</label>
                <input type="text" class="form-control" id="kode_produk" name="kode_produk" maxlength="30" placeholder="Kosongkan untuk auto generate">
                <div class="form-text">Kosongkan untuk generate otomatis</div>
              </div>
              
              <div class="mb-3">
                <label for="nama_produk" class="form-label">Nama Produk <span class="text-danger">*</span></label>
                <input type="text" class="form-control" id="nama_produk" name="nama_produk" maxlength="30" required>
              </div>
              
              <div class="mb-3">
                <label for="id_kategori" class="form-label">Kategori <span class="text-danger">*</span></label>
                <div class="searchable-select">
                  <input type="text" class="form-control" id="kategori_display" placeholder="Pilih kategori..." readonly onclick="toggleDropdown()" style="padding-right: 2.5rem;">
                  <input type="hidden" name="id_kategori" id="id_kategori" required>
                  <div class="dropdown-menu" id="kategori_dropdown" style="display: none; width: 100%; max-height: 200px; overflow-y: auto; border: 1px solid #ced4da; border-radius: 0.375rem; background: white; box-shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.075);">
                    <input type="text" class="search-input form-control" id="kategori_search" placeholder="Cari kategori..." onkeyup="filterKategori()" style="margin: 0.5rem; width: calc(100% - 1rem);">
                    <?php foreach ($categories as $category): ?>
                    <div class="dropdown-item" data-value="<?php echo $category['id_kategori']; ?>" data-text="<?php echo htmlspecialchars($category['nama_kategori']); ?>" onclick="selectKategori(this)" style="padding: 0.5rem 1rem; cursor: pointer; border-bottom: 1px solid #f8f9fa;">
                      <?php echo htmlspecialchars($category['nama_kategori']); ?>
                    </div>
                    <?php endforeach; ?>
                  </div>
                </div>
              </div>
              
              <div class="mb-3">
                <label for="gambar" class="form-label">Gambar Produk</label>
                <input type="file" class="form-control" id="gambar" name="gambar" accept="image/jpeg,image/jpg,image/png">
                <div class="form-text">
                  <small class="text-muted">
                    Format: JPG, JPEG, PNG | Ukuran: 300x300 px | Maksimal: 500KB
                  </small>
                </div>
                <div id="imagePreview" class="mt-2" style="display: none;">
                  <img id="previewImg" src="" alt="Preview" class="img-thumbnail" style="max-width: 150px; max-height: 150px;">
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

    <!-- Image Modal -->
    <div class="modal fade" id="imageModal" tabindex="-1" aria-labelledby="imageModalLabel" aria-hidden="true">
      <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
          <div class="modal-header">
            <h5 class="modal-title" id="imageModalLabel">Gambar Produk</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
          </div>
          <div class="modal-body text-center">
            <div id="imageContainer">
              <img id="productImage" src="" alt="" class="img-fluid" style="max-width: 100%; max-height: 400px;">
            </div>
            <div id="noImageMessage" class="text-muted" style="display: none;">
              <i class="fas fa-image fa-3x mb-3"></i>
              <p>Tidak ada gambar untuk produk ini</p>
            </div>
          </div>
        </div>
      </div>
    </div>

    <script src="../js/bootstrap.bundle.min.js"></script>
    <script>
    // Searchable dropdown functionality
    function toggleDropdown() {
        const dropdown = document.getElementById('kategori_dropdown');
        const isVisible = dropdown.style.display === 'block';
        dropdown.style.display = isVisible ? 'none' : 'block';
        
        if (!isVisible) {
            document.getElementById('kategori_search').value = '';
            filterKategori();
            document.getElementById('kategori_search').focus();
        }
    }
    
    function selectKategori(element) {
        const value = element.getAttribute('data-value');
        const text = element.getAttribute('data-text');
        
        document.getElementById('id_kategori').value = value;
        document.getElementById('kategori_display').value = text;
        document.getElementById('kategori_dropdown').style.display = 'none';
    }
    
    function filterKategori() {
        const searchValue = document.getElementById('kategori_search').value.toLowerCase();
        const items = document.querySelectorAll('#kategori_dropdown .dropdown-item');
        
        items.forEach(item => {
            const text = item.getAttribute('data-text').toLowerCase();
            if (text.includes(searchValue)) {
                item.style.display = 'block';
            } else {
                item.style.display = 'none';
            }
        });
    }
    
    // Close dropdown when clicking outside
    document.addEventListener('click', function(event) {
        const dropdown = document.getElementById('kategori_dropdown');
        const displayInput = document.getElementById('kategori_display');
        const searchInput = document.getElementById('kategori_search');
        
        if (!dropdown.contains(event.target) && event.target !== displayInput && event.target !== searchInput) {
            dropdown.style.display = 'none';
        }
    });
    
    // Edit product function
    function editProduct(id) {
        fetch(`menu.php?edit=${id}`)
            .then(response => response.text())
            .then(data => {
                // Parse the response to get product data
                const parser = new DOMParser();
                const doc = parser.parseFromString(data, 'text/html');
                
                // Extract product data from the response
                const productData = <?php echo json_encode($edit_product); ?>;
                
                if (productData) {
                    document.getElementById('productModalTitle').textContent = 'Edit Produk Menu';
                    document.getElementById('formAction').value = 'update';
                    document.getElementById('productId').value = productData.id_produk;
                    document.getElementById('kode_produk').value = productData.kode_produk || '';
                    document.getElementById('nama_produk').value = productData.nama_produk || '';
                    document.getElementById('id_kategori').value = productData.id_kategori || '';
                    document.getElementById('aktif').checked = productData.aktif === '1';
                    
                    // Set kategori display text
                    const kategoriOption = document.querySelector(`[data-value="${productData.id_kategori}"]`);
                    if (kategoriOption) {
                        document.getElementById('kategori_display').value = kategoriOption.getAttribute('data-text');
                    }
                    
                    const modal = new bootstrap.Modal(document.getElementById('productModal'));
                    modal.show();
                } else {
                    // Redirect to edit page
                    window.location.href = `menu.php?edit=${id}`;
                }
            })
            .catch(error => {
                console.error('Error:', error);
                window.location.href = `menu.php?edit=${id}`;
            });
    }
    
    // Reset form when modal is hidden
    document.getElementById('productModal').addEventListener('hidden.bs.modal', function () {
        document.getElementById('productModalTitle').textContent = 'Tambah Produk Menu';
        document.getElementById('formAction').value = 'create';
        document.getElementById('productId').value = '';
        document.getElementById('productForm').reset();
        document.getElementById('kategori_display').value = '';
        document.getElementById('id_kategori').value = '';
        document.getElementById('aktif').checked = true;
    });
    
    // Image preview functionality
    document.getElementById('gambar').addEventListener('change', function(e) {
        const file = e.target.files[0];
        const preview = document.getElementById('imagePreview');
        const previewImg = document.getElementById('previewImg');
        
        if (file) {
            const reader = new FileReader();
            reader.onload = function(e) {
                previewImg.src = e.target.result;
                preview.style.display = 'block';
            };
            reader.readAsDataURL(file);
        } else {
            preview.style.display = 'none';
        }
    });
    
    // Auto-hide alerts after 5 seconds
    setTimeout(function() {
        const alerts = document.querySelectorAll('.alert');
        alerts.forEach(function(alert) {
            const bsAlert = new bootstrap.Alert(alert);
            bsAlert.close();
        });
    }, 5000);
    
    // Function to show product image in modal
    function showProductImage(kode_produk, nama_produk) {
        const imageContainer = document.getElementById('imageContainer');
        const noImageMessage = document.getElementById('noImageMessage');
        const productImage = document.getElementById('productImage');
        const modalTitle = document.getElementById('imageModalLabel');
        
        modalTitle.textContent = 'Gambar Produk - ' + nama_produk;
        
        // Try to find image with different extensions
        const extensions = ['jpg', 'jpeg', 'png'];
        let imageFound = false;
        
        function tryNextExtension(index) {
            if (index >= extensions.length) {
                // No image found
                imageContainer.style.display = 'none';
                noImageMessage.style.display = 'block';
                return;
            }
            
            const img = new Image();
            const imagePath = '../images/' + kode_produk + '.' + extensions[index];
            
            img.onload = function() {
                productImage.src = imagePath;
                productImage.alt = nama_produk;
                imageContainer.style.display = 'block';
                noImageMessage.style.display = 'none';
                imageFound = true;
            };
            
            img.onerror = function() {
                tryNextExtension(index + 1);
            };
            
            img.src = imagePath;
        }
        
        tryNextExtension(0);
    }
    </script>
  </body>
</html>