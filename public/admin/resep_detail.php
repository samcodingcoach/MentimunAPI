<?php
session_start();
require_once '../../config/koneksi.php';

// Check if user is logged in
if (!isset($_SESSION["id_user"])) {
    header("Location: login.php");
    exit();
}

// Handle AJAX requests
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    // Tidak perlu AJAX search, langsung load semua bahan
    
    if ($action === 'publish_resep') {
        $id_resep = $_POST['id_resep'] ?? '';
        
        if (empty($id_resep)) {
            header('Content-Type: application/json');
            echo json_encode(['status' => 'error', 'message' => 'ID Resep tidak valid']);
            exit;
        }
        
        $stmt = $conn->prepare("UPDATE resep SET publish_menu = 1 WHERE id_resep = ?");
        $stmt->bind_param('i', $id_resep);
        
        if ($stmt->execute()) {
            header('Content-Type: application/json');
            echo json_encode(['status' => 'success', 'message' => 'Resep berhasil dipublish']);
        } else {
            header('Content-Type: application/json');
            echo json_encode(['status' => 'error', 'message' => 'Gagal mempublish resep']);
        }
        exit;
    }
    
    if ($action === 'delete_detail') {
        $id_resep_detail = $_POST['id_resep_detail'] ?? '';
        
        if (empty($id_resep_detail)) {
            header('Content-Type: application/json');
            echo json_encode(['status' => 'error', 'message' => 'ID Resep Detail tidak valid']);
            exit;
        }
        
        $stmt = $conn->prepare("DELETE FROM resep_detail WHERE id_resep_detail = ?");
        $stmt->bind_param('i', $id_resep_detail);
        
        if ($stmt->execute()) {
            header('Content-Type: application/json');
            echo json_encode(['status' => 'success', 'message' => 'Detail resep berhasil dihapus']);
        } else {
            header('Content-Type: application/json');
            echo json_encode(['status' => 'error', 'message' => 'Gagal menghapus detail resep']);
        }
        exit;
    }
    
    if ($action === 'add_detail') {
        $id_resep = $_POST['id_resep'] ?? '';
        $id_bahan_biaya = $_POST['id_bahan_biaya'] ?? '';
        $id_bahan = $_POST['id_bahan'] ?? '';
        $satuan_pemakaian = $_POST['satuan_pemakaian'] ?? '';
        $jumlah_pemakaian = $_POST['jumlah_pemakaian'] ?? 0;
        $nilai_ekpetasi = $_POST['nilai_ekpetasi'] ?? 0;
        
        // Validasi input dengan pesan spesifik
        $errors = [];
        
        if (empty($id_resep)) {
            $errors[] = 'ID Resep tidak valid';
        }
        if (empty($id_bahan)) {
            $errors[] = 'Bahan belum dipilih';
        }
        if (empty($id_bahan_biaya)) {
            $errors[] = 'ID Bahan Biaya tidak valid';
        }
        if (empty($satuan_pemakaian)) {
            $errors[] = 'Satuan pemakaian belum diisi';
        }
        if ($jumlah_pemakaian <= 0) {
            $errors[] = 'Jumlah pemakaian harus lebih dari 0';
        }
        if ($nilai_ekpetasi <= 0) {
            $errors[] = 'Perkiraan biaya harus lebih dari 0';
        }
        
        if (!empty($errors)) {
            header('Content-Type: application/json');
            echo json_encode(['status' => 'error', 'message' => 'Error: ' . implode(', ', $errors)]);
            exit;
        }
        
        try {
            // Cek duplikasi
            $checkStmt = $conn->prepare("SELECT COUNT(*) AS count FROM resep_detail INNER JOIN resep ON resep_detail.id_resep = resep.id_resep WHERE resep.publish_menu = 0 and resep_detail.id_resep = ? AND id_bahan_biaya = ?");
            $checkStmt->bind_param('ii', $id_resep, $id_bahan_biaya);
            $checkStmt->execute();
            $checkResult = $checkStmt->get_result();
            $count = $checkResult->fetch_assoc()['count'];
            
            if ($count > 0) {
                header('Content-Type: application/json');
                echo json_encode(['status' => 'error', 'message' => 'Detail resep dengan bahan yang sama sudah ada']);
                exit;
            }
            
            // Insert data
            $insertStmt = $conn->prepare("
                INSERT INTO resep_detail (id_resep, id_bahan, id_bahan_biaya, satuan_pemakaian, jumlah_pemakaian, nilai_ekpetasi)
                VALUES (?, ?, ?, ?, ?, ?)
            ");
            
            $insertStmt->bind_param('iiisdd', $id_resep, $id_bahan, $id_bahan_biaya, $satuan_pemakaian, $jumlah_pemakaian, $nilai_ekpetasi);
            
            if ($insertStmt->execute()) {
                header('Content-Type: application/json');
                echo json_encode(['status' => 'success', 'message' => 'Detail resep berhasil ditambahkan']);
            } else {
                header('Content-Type: application/json');
                echo json_encode(['status' => 'error', 'message' => 'Gagal menyimpan data']);
            }
            exit;
            
        } catch (Exception $e) {
            header('Content-Type: application/json');
            echo json_encode(['status' => 'error', 'message' => 'Gagal menyimpan data: ' . $e->getMessage()]);
            exit;
        }
    }
}

// Get resep ID from URL (support both 'id_resep' and 'id' parameters)
$id_resep = 0;
if (isset($_GET['id_resep'])) {
    $id_resep = (int)$_GET['id_resep'];
} elseif (isset($_GET['id'])) {
    $id_resep = (int)$_GET['id'];
}

if (!$id_resep) {
    header("Location: resep.php");
    exit();
}

// Get resep info first
$resep_query = "SELECT r.kode_resep, CONCAT(pm.nama_produk,' - ',km.nama_kategori, ' [',pm.kode_produk,']') as nama_produk FROM resep r INNER JOIN produk_menu pm ON r.id_produk = pm.id_produk INNER JOIN kategori_menu km ON pm.id_kategori = km.id_kategori WHERE r.id_resep = ?";
$resep_stmt = $conn->prepare($resep_query);
$resep_stmt->bind_param("i", $id_resep);
$resep_stmt->execute();
$resep_result = $resep_stmt->get_result();
$resep_info = $resep_result->fetch_assoc();

if (!$resep_info) {
    header("Location: resep.php");
    exit();
}

// Check if resep is already published
$publish_query = "SELECT resep.publish_menu FROM resep WHERE id_resep = ? AND publish_menu = 1";
$publish_stmt = $conn->prepare($publish_query);
$publish_stmt->bind_param("i", $id_resep);
$publish_stmt->execute();
$publish_result = $publish_stmt->get_result();
$is_published = $publish_result->num_rows > 0;

// Pagination
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 10;
$offset = ($page - 1) * $limit;

// Search functionality
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$search_condition = '';
$search_params = [];
$param_types = 'i';

if (!empty($search)) {
    $search_condition = " AND (bahan.nama_bahan LIKE ? OR kategori_bahan.nama_kategori LIKE ? OR bahan.kode_bahan LIKE ?)";
    $search_like = "%$search%";
    $search_params = [$search_like, $search_like, $search_like];
    $param_types = 'isss';
}

// Get total records for pagination
$count_query = "SELECT COUNT(*) as total FROM resep_detail rd INNER JOIN bahan b ON rd.id_bahan = b.id_bahan INNER JOIN kategori_bahan kb ON b.id_kategori = kb.id_kategori INNER JOIN bahan_biaya bb ON rd.id_bahan_biaya = bb.id_bahan_biaya WHERE rd.id_resep = ?" . $search_condition;
$count_stmt = $conn->prepare($count_query);
if (!empty($search)) {
    $count_stmt->bind_param($param_types, $id_resep, ...$search_params);
} else {
    $count_stmt->bind_param("i", $id_resep);
}
$count_stmt->execute();
$count_result = $count_stmt->get_result();
$total_records = $count_result->fetch_assoc()['total'];
$total_pages = ceil($total_records / $limit);

// Get resep detail data with the provided query
$sql = "SELECT
    resep_detail.id_resep_detail, 
    resep_detail.id_resep, 
    CONCAT(bahan.nama_bahan, ' [', kategori_bahan.nama_kategori,']',' | ',bahan.kode_bahan) as nama_bahan,
    resep_detail.id_bahan, 
    CONCAT('Rp ',FORMAT(bahan_biaya.harga_satuan,0),'/',bahan_biaya.satuan) as harga_satuan,
    resep_detail.id_bahan_biaya, 
    CONCAT('Rp ',FORMAT(resep_detail.nilai_ekpetasi,0)) as nilai_ekpetasi,
    CONCAT(resep_detail.jumlah_pemakaian,' ',resep_detail.satuan_pemakaian) as satuan_pemakaian
FROM
    resep_detail
    INNER JOIN
    bahan
    ON 
        resep_detail.id_bahan = bahan.id_bahan
    INNER JOIN
    kategori_bahan
    ON 
        bahan.id_kategori = kategori_bahan.id_kategori
    INNER JOIN
    bahan_biaya
    ON 
        resep_detail.id_bahan_biaya = bahan_biaya.id_bahan_biaya
WHERE resep_detail.id_resep = ?" . $search_condition . " ORDER BY bahan.nama_bahan LIMIT ? OFFSET ?";

$stmt = $conn->prepare($sql);
if (!empty($search)) {
    $all_params = array_merge([$id_resep], $search_params, [$limit, $offset]);
    $stmt->bind_param($param_types . 'ii', ...$all_params);
} else {
    $stmt->bind_param("iii", $id_resep, $limit, $offset);
}
$stmt->execute();
$result = $stmt->get_result();
$resep_details = $result->fetch_all(MYSQLI_ASSOC);
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Detail Resep - Resto007</title>
    <link href="../css/bootstrap.min.css" rel="stylesheet">
    <link href="../css/admin.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.7.2/font/bootstrap-icons.css" rel="stylesheet">
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
                    <li class="nav-item"><a class="nav-link" href="menu.php"><i class="bi bi-list"></i> Menu</a></li>
                    <li class="nav-item"><a class="nav-link" href="kategori_bahan.php"><i class="bi bi-tags"></i> Kategori Bahan</a></li>
                    <li class="nav-item"><a class="nav-link" href="bahan.php"><i class="bi bi-egg"></i> Bahan</a></li>
                    <li class="nav-item"><a class="nav-link active" href="resep.php"><i class="bi bi-book"></i> Resep</a></li>
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
                    <li class="nav-item"><a class="nav-link" href="kategori_menu.php"><i class="bi bi-tags"></i> Kategori Menu</a></li>
                    <li class="nav-item"><a class="nav-link" href="menu.php"><i class="bi bi-list"></i> Menu</a></li>
                    <li class="nav-item"><a class="nav-link" href="kategori_bahan.php"><i class="bi bi-tags"></i> Kategori Bahan</a></li>
                    <li class="nav-item"><a class="nav-link" href="bahan.php"><i class="bi bi-egg"></i> Bahan</a></li>
                    <li class="nav-item"><a class="nav-link" href="resep.php"><i class="bi bi-book"></i> Resep</a></li>
                  </ul>
                </div>
              </li>
              <?php endif; ?>
            </ul>
          </div>
        </div>

        <!-- Main Content -->
        <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
          <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
            <div>
              <h1 class="h2">Detail Resep</h1>
              <p class="text-muted mb-0">
                <strong>Kode:</strong> <?php echo htmlspecialchars($resep_info['kode_resep']); ?> | 
                <strong>Produk:</strong> <?php echo htmlspecialchars($resep_info['nama_produk']); ?>
              </p>
            </div>
            <div>
              <?php if (!$is_published): ?>
              <button type="button" class="btn btn-primary me-2" data-bs-toggle="modal" data-bs-target="#detailModal">
                <i class="bi bi-plus-circle"></i> New Detail
              </button>
              <button type="button" class="btn btn-success me-2" id="publishBtn" onclick="publishResep()">
                <i class="bi bi-check-circle"></i> Publish
              </button>
              <?php endif; ?>
              <a href="resep.php" class="btn btn-secondary">
                <i class="bi bi-arrow-left"></i> Kembali ke Resep
              </a>
            </div>
          </div>

          <!-- Search -->
          <div class="row mb-3">
            <div class="col-md-4">
              <form method="GET" class="d-flex">
                <input type="hidden" name="id_resep" value="<?php echo $id_resep; ?>">
                <input type="text" class="form-control me-2" name="search" placeholder="Cari bahan, kategori, atau kode..." value="<?php echo htmlspecialchars($search); ?>">
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
                  <th>Nama Bahan</th>
                  <th class="d-none d-md-table-cell">Harga Satuan</th>
                  <th class="d-none d-lg-table-cell">Pemakaian</th>
                  <th>Nilai Ekspetasi</th>
                  <?php if (!$is_published): ?>
                  <th>Aksi</th>
                  <?php endif; ?>
                </tr>
              </thead>
              <tbody>
                <?php if (!empty($resep_details)): ?>
                  <?php foreach ($resep_details as $index => $detail): ?>
                    <tr>
                      <td><?php echo $offset + $index + 1; ?></td>
                      <td><?php echo htmlspecialchars($detail['nama_bahan']); ?></td>
                      <td class="d-none d-md-table-cell"><?php echo htmlspecialchars($detail['harga_satuan']); ?></td>
                      <td class="d-none d-lg-table-cell"><?php echo htmlspecialchars($detail['satuan_pemakaian']); ?></td>
                      <td><span class="badge bg-success"><?php echo htmlspecialchars($detail['nilai_ekpetasi']); ?></span></td>
                      <?php if (!$is_published): ?>
                      <td>
                        <button type="button" class="btn btn-danger btn-sm" onclick="deleteDetail(<?php echo $detail['id_resep_detail']; ?>)">
                          <i class="bi bi-trash"></i>
                        </button>
                      </td>
                      <?php endif; ?>
                    </tr>
                  <?php endforeach; ?>
                <?php else: ?>
                  <tr>
                    <td colspan="<?php echo $is_published ? '5' : '6'; ?>" class="text-center">Tidak ada data detail resep</td>
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
                    <a class="page-link" href="?id_resep=<?php echo $id_resep; ?>&page=<?php echo $page - 1; ?><?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?>">
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
                    <a class="page-link" href="?id_resep=<?php echo $id_resep; ?>&page=<?php echo $i; ?><?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?>">
                      <?php echo $i; ?>
                    </a>
                  </li>
                <?php endfor; ?>

                <!-- Next Page -->
                <?php if ($page < $total_pages): ?>
                  <li class="page-item">
                    <a class="page-link" href="?id_resep=<?php echo $id_resep; ?>&page=<?php echo $page + 1; ?><?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?>">
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

    <!-- Modal New Detail -->
    <div class="modal fade" id="detailModal" tabindex="-1" aria-labelledby="detailModalLabel" aria-hidden="true">
      <div class="modal-dialog modal-lg">
        <div class="modal-content">
          <div class="modal-header">
            <h5 class="modal-title" id="detailModalLabel">Tambah Detail Resep</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
          </div>
          <form id="detailForm" method="POST">
            <div class="modal-body">
              <input type="hidden" name="id_resep" value="<?php echo $id_resep; ?>">
              <input type="hidden" name="action" value="add_detail">
              
              <!-- Pilih Bahan -->
              <div class="mb-3">
                <label for="id_bahan" class="form-label">Pilih Bahan <span class="text-danger">*</span></label>
                <select class="form-select" id="id_bahan" name="id_bahan" required>
                  <option value="">Pilih Bahan...</option>
                  <?php
                  $stmt = $conn->prepare("
                      SELECT bb.id_bahan_biaya, bb.id_bahan, b.nama_bahan, kb.nama_kategori, 
                             CONCAT('Rp ', FORMAT(bb.harga_satuan, 0), ' / ', bb.satuan) as harga_satuan
                      FROM bahan_biaya bb
                      JOIN bahan b ON bb.id_bahan = b.id_bahan
                      JOIN kategori_bahan kb ON b.id_kategori = kb.id_kategori
                      ORDER BY b.nama_bahan
                  ");
                  $stmt->execute();
                  $result = $stmt->get_result();
                  while ($row = $result->fetch_assoc()) {
                      echo '<option value="' . $row['id_bahan'] . '" data-biaya="' . $row['id_bahan_biaya'] . '" data-harga="' . $row['harga_satuan'] . '">';
                      echo $row['nama_bahan'] . ' [' . $row['nama_kategori'] . '] - ' . $row['harga_satuan'];
                      echo '</option>';
                  }
                  ?>
                </select>
                <input type="hidden" id="id_bahan_biaya" name="id_bahan_biaya">
              </div>
              
              <!-- Satuan Pemakaian -->
              <div class="mb-3">
                <label for="satuan_pemakaian" class="form-label">Satuan Pemakaian <span class="text-danger">*</span></label>
                <input type="text" class="form-control" id="satuan_pemakaian" name="satuan_pemakaian" placeholder="Contoh: kg, liter, buah, sdm, dll" required>
                <div class="form-text">Masukkan satuan pemakaian (kg, liter, buah, sdm, dll)</div>
              </div>
              
              <!-- Jumlah dan Biaya -->
              <div class="row">
                <div class="col-md-6">
                  <div class="mb-3">
                    <label for="jumlah_pemakaian" class="form-label">Jumlah Pemakaian <span class="text-danger">*</span></label>
                    <input type="number" class="form-control" id="jumlah_pemakaian" name="jumlah_pemakaian" step="0.01" min="0" required>
                  </div>
                </div>
                <div class="col-md-6">
                  <div class="mb-3">
                    <label for="nilai_ekpetasi" class="form-label">Perkiraan Biaya <span class="text-danger">*</span></label>
                    <input type="number" class="form-control" id="nilai_ekpetasi" name="nilai_ekpetasi" step="0.01" min="0" required>
                  </div>
                </div>
              </div>
              
              <!-- Info Harga -->
              <div class="alert alert-info" id="harga_info" style="display: none;">
                <strong>Harga Satuan:</strong> <span id="harga_satuan_text"></span>
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

    <script src="../js/bootstrap.bundle.min.js"></script>
    <script>
    // Handle bahan selection
    document.getElementById('id_bahan').addEventListener('change', function() {
        const selectedOption = this.options[this.selectedIndex];
        const idBahanBiaya = selectedOption.getAttribute('data-biaya');
        const hargaSatuan = selectedOption.getAttribute('data-harga');
        
        // Set id_bahan_biaya
        document.getElementById('id_bahan_biaya').value = idBahanBiaya || '';
        
        // Show/hide harga info
        if (hargaSatuan) {
            document.getElementById('harga_satuan_text').textContent = hargaSatuan;
            document.getElementById('harga_info').style.display = 'block';
        } else {
            document.getElementById('harga_info').style.display = 'none';
        }
    });
    
    // Handle form submission
    document.getElementById('detailForm').addEventListener('submit', function(e) {
        e.preventDefault();
        
        const formData = new FormData(this);
        
        fetch('resep_detail.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.status === 'success') {
                alert(data.message);
                location.reload();
            } else {
                alert(data.message);
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('Terjadi kesalahan saat menyimpan data');
        });
    });
    
    // Reset form when modal is hidden
    document.getElementById('detailModal').addEventListener('hidden.bs.modal', function() {
        document.getElementById('detailForm').reset();
        document.getElementById('harga_info').style.display = 'none';
        document.getElementById('id_bahan_biaya').value = '';
    });
    
    // Publish resep function
    function publishResep() 
    {
        if (confirm('Yakin, Setelah publish tidak bisa tambah resep lagi?')) {
            const formData = new FormData();
            formData.append('action', 'publish_resep');
            formData.append('id_resep', '<?php echo $id_resep; ?>');
            
            fetch('resep_detail.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.status === 'success') {
                    alert(data.message);
                    window.location.href = 'resep.php';
                } else {
                    alert(data.message);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Terjadi kesalahan saat publish resep');
            });
        }
    }
    
    // Delete detail function
    function deleteDetail(id_resep_detail) {
        if (confirm('Yakin ingin menghapus detail resep ini?')) {
            const formData = new FormData();
            formData.append('action', 'delete_detail');
            formData.append('id_resep_detail', id_resep_detail);
            
            fetch('resep_detail.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.status === 'success') {
                    alert(data.message);
                    location.reload();
                } else {
                    alert(data.message);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Terjadi kesalahan saat menghapus data');
            });
        }
    }
    </script>
</body>
</html>