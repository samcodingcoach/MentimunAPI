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

// Handle file upload
function uploadImage($file) {
    $target_dir = "../images/";
    $imageFileType = strtolower(pathinfo($file["name"], PATHINFO_EXTENSION));
    $new_filename = uniqid() . '.' . $imageFileType;
    $target_file = $target_dir . $new_filename;
    
    // Check if image file is actual image
    $check = getimagesize($file["tmp_name"]);
    if($check === false) {
        return [false, "File bukan gambar."];
    }
    
    // Check file size (max 500KB)
    if ($file["size"] > 500000) {
        return [false, "File terlalu besar. Maksimal 500KB."];
    }
    
    // Allow certain file formats
    if($imageFileType != "jpg" && $imageFileType != "png" && $imageFileType != "jpeg" && $imageFileType != "gif" ) {
        return [false, "Hanya file JPG, JPEG, PNG & GIF yang diizinkan."];
    }
    
    // Check image dimensions (minimum 1280x720)
    list($width, $height) = getimagesize($file["tmp_name"]);
    if($width < 1280 || $height < 720) {
        return [false, "Resolusi gambar minimal 1280x720 pixel."];
    }
    
    if (move_uploaded_file($file["tmp_name"], $target_file)) {
        return [true, $new_filename];
    } else {
        return [false, "Terjadi kesalahan saat upload file."];
    }
}

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'create':
                $judul = trim($_POST['judul']);
                $isi = trim($_POST['isi']);
                $divisi = trim($_POST['divisi']);
                 $link = trim($_POST['link']);
                 $id_users = $_SESSION['id_user'];
                 $gambar = '';
                 
                 // Handle image upload
                 $gambar = null;
                 if (isset($_FILES['gambar']) && $_FILES['gambar']['error'] == 0) {
                     $upload_dir = dirname(__DIR__) . '/images/';
                     $file_extension = strtolower(pathinfo($_FILES['gambar']['name'], PATHINFO_EXTENSION));
                     $allowed_extensions = ['jpg', 'jpeg', 'png', 'gif'];
                     
                     if (in_array($file_extension, $allowed_extensions)) {
                         $filename = uniqid() . '.' . $file_extension;
                         $upload_path = $upload_dir . $filename;
                         
                         if (move_uploaded_file($_FILES['gambar']['tmp_name'], $upload_path)) {
                            $gambar = $filename;
                        } else {
                            // Fallback: coba copy jika move_uploaded_file gagal
                            if (copy($_FILES['gambar']['tmp_name'], $upload_path)) {
                                $gambar = $filename;
                                unlink($_FILES['gambar']['tmp_name']); // hapus file temporary
                            } else {
                                $error = 'Gagal mengupload gambar. Path: ' . $upload_path . ' | Tmp: ' . $_FILES['gambar']['tmp_name'] . ' | Error: ' . error_get_last()['message'];
                            }
                        }
                     } else {
                         $error = 'Format gambar tidak didukung. Gunakan JPG, JPEG, PNG, atau GIF.';
                     }
                 }

                
                if (!empty($judul) && !empty($isi) && !empty($divisi)) {
                    $stmt = $conn->prepare("INSERT INTO informasi (judul, isi, divisi, gambar, link, id_users, created_time) VALUES (?, ?, ?, ?, ?, ?, NOW())");
                     $stmt->bind_param("sssssi", $judul, $isi, $divisi, $gambar, $link, $id_users);
                    if ($stmt->execute()) {
                        $message = 'Data informasi berhasil ditambahkan!';
                    } else {
                        $error = 'Error: ' . $conn->error;
                    }
                } else {
                    $error = 'Judul, isi, dan divisi wajib diisi!';
                }
                break;
                
            case 'update':
                $id_info = $_POST['id_info'];
                $judul = trim($_POST['judul']);
                $isi = trim($_POST['isi']);
                 $divisi = trim($_POST['divisi']);
                 $link = trim($_POST['link']);
                 $gambar_lama = $_POST['gambar_lama'];
                 $gambar = $gambar_lama;
                 
                 // Handle image upload
                 if (isset($_FILES['gambar']) && $_FILES['gambar']['error'] == 0) {
                     $upload_dir = dirname(__DIR__) . '/images/';
                     $file_extension = strtolower(pathinfo($_FILES['gambar']['name'], PATHINFO_EXTENSION));
                     $allowed_extensions = ['jpg', 'jpeg', 'png', 'gif'];
                     
                     if (in_array($file_extension, $allowed_extensions)) {
                         $filename = uniqid() . '.' . $file_extension;
                         $upload_path = $upload_dir . $filename;
                         
                         if (move_uploaded_file($_FILES['gambar']['tmp_name'], $upload_path)) {
                             // Delete old image if exists
                             if (!empty($gambar_lama) && file_exists($upload_dir . $gambar_lama)) {
                                 unlink($upload_dir . $gambar_lama);
                             }
                             $gambar = $filename;
                         } else {
                             // Fallback: coba copy jika move_uploaded_file gagal
                             if (copy($_FILES['gambar']['tmp_name'], $upload_path)) {
                                 // Delete old image if exists
                                 if (!empty($gambar_lama) && file_exists($upload_dir . $gambar_lama)) {
                                     unlink($upload_dir . $gambar_lama);
                                 }
                                 $gambar = $filename;
                                 unlink($_FILES['gambar']['tmp_name']); // hapus file temporary
                             } else {
                                 $error = 'Gagal mengupload gambar. Path: ' . $upload_path . ' | Tmp: ' . $_FILES['gambar']['tmp_name'] . ' | Error: ' . error_get_last()['message'];
                             }
                         }
                     } else {
                         $error = 'Format gambar tidak didukung. Gunakan JPG, JPEG, PNG, atau GIF.';
                     }
                 }
                 
                 if (!empty($judul) && !empty($isi) && !empty($divisi)) {
                     $stmt = $conn->prepare("UPDATE informasi SET judul = ?, isi = ?, divisi = ?, gambar = ?, link = ?, id_users = ?, created_time = NOW() WHERE id_info = ?");
                     $stmt->bind_param("sssssii", $judul, $isi, $divisi, $gambar, $link, $id_users, $id_info);
                    if ($stmt->execute()) {
                        $message = 'Data informasi berhasil diperbarui!';
                    } else {
                        $error = 'Error: ' . $conn->error;
                    }
                } else {
                    $error = 'Judul, isi, dan divisi harus diisi!';
                }
                break;
                
            case 'delete':
                $id_info = $_POST['id_info'];
                
                $stmt = $conn->prepare("DELETE FROM informasi WHERE id_info = ?");
                $stmt->bind_param("i", $id_info);
                if ($stmt->execute()) {
                    $message = 'Data informasi berhasil dihapus!';
                } else {
                    $error = 'Error: ' . $conn->error;
                }
                break;
        }
    }
}

// Handle delete via GET parameter
if (isset($_GET['delete'])) {
    $id_info = (int)$_GET['delete'];
    
    // Get image filename before deleting
    $stmt = $conn->prepare("SELECT gambar FROM informasi WHERE id_info = ?");
    $stmt->bind_param("i", $id_info);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    
    if ($row && !empty($row['gambar'])) {
        $image_path = dirname(__DIR__) . '/images/' . $row['gambar'];
        if (file_exists($image_path)) {
            unlink($image_path);
        }
    }
    
    $stmt = $conn->prepare("DELETE FROM informasi WHERE id_info = ?");
    $stmt->bind_param("i", $id_info);
    if ($stmt->execute()) {
        $message = 'Data informasi berhasil dihapus!';
    } else {
        $error = 'Error: ' . $conn->error;
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
    $where_conditions[] = "(judul LIKE ? OR isi LIKE ?)";
    $search_param = "%$search%";
    $params[] = $search_param;
    $params[] = $search_param;
    $types .= 'ss';
}

if (!empty($filter) && in_array($filter, ['All', 'Admin', 'Kasir', 'Pramusaji', 'Dapur'])) {
    $where_conditions[] = "divisi = ?";
    $params[] = $filter;
    $types .= 's';
}

$where_clause = !empty($where_conditions) ? 'WHERE ' . implode(' AND ', $where_conditions) : '';

// Get total count for pagination
$count_sql = "SELECT COUNT(*) as total FROM informasi $where_clause";
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

// Get informasi data with user info
$sql = "SELECT i.*, p.nama_lengkap FROM informasi i LEFT JOIN pegawai p ON i.id_users = p.id_user $where_clause ORDER BY i.created_time DESC LIMIT $limit OFFSET $offset";
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
    <title>Informasi - Resto007 Admin</title>
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
                <a class="nav-link active" href="informasi.php">
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
                <div class="collapse" id="produkMenu">
                  <ul class="nav flex-column ms-3">
                    <li class="nav-item"><a class="nav-link" href="kategori_menu.php"><i class="bi bi-tags"></i> Kategori Menu</a></li>
                    <li class="nav-item"><a class="nav-link" href="menu.php"><i class="bi bi-list"></i> Menu</a></li>
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
                  <i class="bi bi-cash-coin"></i>
                  <span>Penjualan</span>
                  <i class="bi bi-chevron-down ms-auto"></i>
                </a>
                <div class="collapse" id="penjualanMenu">
                  <ul class="nav flex-column ms-3">
                    <li class="nav-item"><a class="nav-link" href="shift_kasir.php"><i class="bi bi-clock"></i> Shift Kasir</a></li>
                    <li class="nav-item"><a class="nav-link" href="biaya_lain.php"><i class="bi bi-receipt"></i> Biaya Lain</a></li>
                    <li class="nav-item"><a class="nav-link" href="transaksi.php"><i class="bi bi-cart-check"></i> Transaksi</a></li>
                    <li class="nav-item"><a class="nav-link" href="pembayaran.php"><i class="bi bi-credit-card"></i> Pembayaran</a></li>
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
                    <li class="nav-item"><a class="nav-link" href="inventory.php"><i class="bi bi-box"></i> Inventory</a></li>
                    <li class="nav-item"><a class="nav-link" href="transaksi_inventory.php"><i class="bi bi-arrow-left-right"></i> Transaksi</a></li>
                  </ul>
                </div>
              </li>
              <?php endif; ?>
              
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
                    <li class="nav-item"><a class="nav-link" href="laporan_pengeluaran.php"><i class="bi bi-graph-up"></i> Pengeluaran vs Penjualan</a></li>
                    <li class="nav-item"><a class="nav-link" href="laporan_kuantitas.php"><i class="bi bi-bar-chart"></i> Kuantitas</a></li>
                  </ul>
                </div>
              </li>
              
              <!-- Pengaturan Menu -->
              <li class="nav-item">
                <a class="nav-link" href="pengaturan.php">
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
            <h1 class="h2">Informasi</h1>
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

          <!-- Data Count Info -->
          <div class="mb-3">
            <small class="text-muted">
              <?php 
                $start = ($page - 1) * $limit + 1;
                $end = min($page * $limit, $total_records);
                if ($total_records > 0) {
                  echo "Showing $start to $end of $total_records entries";
                } else {
                  echo "Showing 0 to 0 of 0 entries";
                }
              ?>
            </small>
          </div>

          <!-- Search and Filter -->
          <div class="row mb-3">
            <div class="col-md-6">
              <form method="GET" class="d-flex">
                <input type="text" class="form-control me-2" name="search" placeholder="Cari judul atau isi..." value="<?php echo htmlspecialchars($search); ?>">
                <button class="btn btn-outline-primary" type="submit">Cari</button>
                <?php if (!empty($search) || !empty($filter)): ?>
                <a href="informasi.php" class="btn btn-outline-secondary ms-2">Reset</a>
                <?php endif; ?>
              </form>
            </div>
            <div class="col-md-3">
              <form method="GET">
                <?php if (!empty($search)): ?>
                <input type="hidden" name="search" value="<?php echo htmlspecialchars($search); ?>">
                <?php endif; ?>
                <select class="form-select" name="filter" onchange="this.form.submit()">
                  <option value="">Semua Divisi</option>
                  <option value="All" <?php echo $filter === 'All' ? 'selected' : ''; ?>>All</option>
                  <option value="Admin" <?php echo $filter === 'Admin' ? 'selected' : ''; ?>>Admin</option>
                  <option value="Kasir" <?php echo $filter === 'Kasir' ? 'selected' : ''; ?>>Kasir</option>
                  <option value="Pramusaji" <?php echo $filter === 'Pramusaji' ? 'selected' : ''; ?>>Pramusaji</option>
                  <option value="Dapur" <?php echo $filter === 'Dapur' ? 'selected' : ''; ?>>Dapur</option>
                </select>
              </form>
            </div>
            <div class="col-md-3 text-end">
              <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addModal">
                <i class="bi bi-plus"></i> Tambah Informasi
              </button>
            </div>
          </div>

          <!-- Informasi Table -->
          <div class="table-responsive">
            <table class="table table-striped table-hover">
              <thead class="table-dark">
                <tr>
                  <th>No</th>
                  <th>Judul</th>
                  <th>Isi</th>
                  <th class="d-none">Gambar</th>
                  <th class="d-none">Link</th>
                  <th>Divisi</th>
                  <th>Dibuat Oleh</th>
                  <th>Waktu</th>
                  <th class="action-column">Aksi</th>
                </tr>
              </thead>
              <tbody>
                <?php if ($result && $result->num_rows > 0): ?>
                  <?php $no = $offset + 1; ?>
                  <?php while ($row = $result->fetch_assoc()): ?>
                  <tr>
                    <td><?php echo $no++; ?></td>
                    <td><?php echo htmlspecialchars($row['judul']); ?></td>
                    <td><?php echo htmlspecialchars(substr($row['isi'], 0, 50)) . (strlen($row['isi']) > 50 ? '...' : ''); ?></td>
                    <td class="d-none"><?php echo htmlspecialchars($row['gambar'] ?? ''); ?></td>
                    <td class="d-none"><?php echo htmlspecialchars($row['link'] ?? ''); ?></td>
                    <td>
                      <span class="badge bg-<?php echo $row['divisi'] === 'All' ? 'primary' : ($row['divisi'] === 'Admin' ? 'danger' : ($row['divisi'] === 'Kasir' ? 'success' : ($row['divisi'] === 'Pramusaji' ? 'warning' : 'info'))); ?>">
                        <?php echo htmlspecialchars($row['divisi']); ?>
                      </span>
                    </td>
                    <td><?php echo htmlspecialchars($row['nama_lengkap'] ?? 'Unknown'); ?></td>
                    <td><?php echo date('d/m/Y H:i', strtotime($row['created_time'])); ?></td>
                    <td>
                      <button type="button" class="btn btn-sm btn-warning" 
                                onclick="editInformasi(<?php echo $row['id_info']; ?>, '<?php echo addslashes($row['judul']); ?>', '<?php echo addslashes($row['isi']); ?>', '<?php echo $row['divisi']; ?>', '<?php echo addslashes($row['gambar'] ?? ''); ?>', '<?php echo addslashes($row['link'] ?? ''); ?>')">
                          <i class="bi bi-pencil"></i> Edit
                        </button>
                        <a href="?delete=<?php echo $row['id_info']; ?>" class="btn btn-sm btn-danger" onclick="return confirm('Apakah Anda yakin ingin menghapus data ini?')">
                            <i class="bi bi-trash"></i> Delete
                        </a>
                    </td>
                  </tr>
                  <?php endwhile; ?>
                <?php else: ?>
                  <tr>
                    <td colspan="9" class="text-center">Tidak ada data informasi</td>
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
                <a class="page-link" href="?page=<?php echo $page - 1; ?><?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?><?php echo !empty($filter) ? '&filter=' . urlencode($filter) : ''; ?>">
                  Previous
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
                  Next
                </a>
              </li>
              <?php endif; ?>
            </ul>
          </nav>
          <?php endif; ?>
        </main>
      </div>
    </div>

    <!-- Mobile Sidebar Offcanvas -->
    <div class="offcanvas offcanvas-start" tabindex="-1" id="sidebarOffcanvas">
      <div class="offcanvas-header">
        <h5 class="offcanvas-title">Menu</h5>
        <button type="button" class="btn-close" data-bs-dismiss="offcanvas"></button>
      </div>
      <div class="offcanvas-body p-0">
        <ul class="nav flex-column">
          <li class="nav-item">
            <a class="nav-link" href="index.php">
              <i class="bi bi-house"></i>
              <span>Beranda</span>
            </a>
          </li>
          
          <li class="nav-item">
            <a class="nav-link active" href="informasi.php">
              <i class="bi bi-info-circle"></i>
              <span>Informasi</span>
            </a>
          </li>
          
          <?php if($_SESSION["jabatan"] == "Admin"): ?>
          <!-- Master Menu Mobile -->
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
          <!-- Produk Menu Mobile -->
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
          
          <!-- Pembelian Menu Mobile -->
          <li class="nav-item">
            <a class="nav-link" data-bs-toggle="collapse" href="#pembelianMenuMobile" role="button">
              <i class="bi bi-cart"></i>
              <span>Pembelian</span>
              <i class="bi bi-chevron-down ms-auto"></i>
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
          <!-- Penjualan Menu Mobile -->
          <li class="nav-item">
            <a class="nav-link" data-bs-toggle="collapse" href="#penjualanMenuMobile" role="button">
              <i class="bi bi-cash-coin"></i>
              <span>Penjualan</span>
              <i class="bi bi-chevron-down ms-auto"></i>
            </a>
            <div class="collapse" id="penjualanMenuMobile">
              <ul class="nav flex-column ms-3">
                <li class="nav-item"><a class="nav-link" href="shift_kasir.php"><i class="bi bi-clock"></i> Shift Kasir</a></li>
                <li class="nav-item"><a class="nav-link" href="biaya_lain.php"><i class="bi bi-receipt"></i> Biaya Lain</a></li>
                <li class="nav-item"><a class="nav-link" href="transaksi.php"><i class="bi bi-cart-check"></i> Transaksi</a></li>
                <li class="nav-item"><a class="nav-link" href="pembayaran.php"><i class="bi bi-credit-card"></i> Pembayaran</a></li>
              </ul>
            </div>
          </li>
          <?php endif; ?>
          
          <?php if($_SESSION["jabatan"] == "Admin" || $_SESSION["jabatan"] == "Dapur"): ?>
          <!-- Inventory Menu Mobile -->
          <li class="nav-item">
            <a class="nav-link" data-bs-toggle="collapse" href="#inventoryMenuMobile" role="button">
              <i class="bi bi-boxes"></i>
              <span>Inventory</span>
              <i class="bi bi-chevron-down ms-auto"></i>
            </a>
            <div class="collapse" id="inventoryMenuMobile">
              <ul class="nav flex-column ms-3">
                <li class="nav-item"><a class="nav-link" href="inventory.php"><i class="bi bi-box"></i> Inventory</a></li>
                <li class="nav-item"><a class="nav-link" href="transaksi_inventory.php"><i class="bi bi-arrow-left-right"></i> Transaksi</a></li>
              </ul>
            </div>
          </li>
          <?php endif; ?>
          
          <!-- Laporan Menu Mobile -->
          <li class="nav-item">
            <a class="nav-link" data-bs-toggle="collapse" href="#laporanMenuMobile" role="button">
              <i class="bi bi-file-earmark-text"></i>
              <span>Laporan</span>
              <i class="bi bi-chevron-down ms-auto"></i>
            </a>
            <div class="collapse" id="laporanMenuMobile">
              <ul class="nav flex-column ms-3">
                <li class="nav-item"><a class="nav-link" href="laporan_transaksi.php"><i class="bi bi-receipt"></i> Transaksi</a></li>
                <li class="nav-item"><a class="nav-link" href="laporan_pengeluaran.php"><i class="bi bi-graph-up"></i> Pengeluaran vs Penjualan</a></li>
                <li class="nav-item"><a class="nav-link" href="laporan_kuantitas.php"><i class="bi bi-bar-chart"></i> Kuantitas</a></li>
              </ul>
            </div>
          </li>
          
          <!-- Pengaturan Menu Mobile -->
          <li class="nav-item">
            <a class="nav-link" href="pengaturan.php">
              <i class="bi bi-gear"></i>
              <span>Pengaturan</span>
            </a>
          </li>
        </ul>
      </div>
    </div>

    <!-- Add Modal -->
    <div class="modal fade" id="addModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Tambah Informasi</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST" enctype="multipart/form-data">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="create">
                        <div class="mb-3">
                            <label for="judul" class="form-label">Judul <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="judul" name="judul" required maxlength="50">
                        </div>
                        <div class="mb-3">
                            <label for="isi" class="form-label">Isi <span class="text-danger">*</span></label>
                            <textarea class="form-control" id="isi" name="isi" rows="5" required></textarea>
                        </div>
                        <div class="mb-3">
                            <label for="divisi" class="form-label">Divisi <span class="text-danger">*</span></label>
                            <select class="form-select" id="divisi" name="divisi" required>
                                <option value="">Pilih Divisi</option>
                                <option value="All">All</option>
                                <option value="Admin">Admin</option>
                                <option value="Kasir">Kasir</option>
                                <option value="Pramusaji">Pramusaji</option>
                                <option value="Dapur">Dapur</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label for="gambar" class="form-label">Gambar</label>
                            <input type="file" class="form-control" id="gambar" name="gambar" accept="image/*">
                        </div>
                        <div class="mb-3">
                            <label for="link" class="form-label">Link</label>
                            <input type="url" class="form-control" id="link" name="link">
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

    <!-- Edit Modal -->
    <div class="modal fade" id="editModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Edit Informasi</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST" enctype="multipart/form-data">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="update">
                        <input type="hidden" name="id_info" id="edit_id_info">

                        <!-- Nav tabs -->
                        <ul class="nav nav-tabs" id="editTabs" role="tablist">
                            <li class="nav-item" role="presentation">
                                <button class="nav-link active" id="basic-tab" data-bs-toggle="tab" data-bs-target="#basic" type="button" role="tab">Informasi Dasar</button>
                            </li>
                            <li class="nav-item" role="presentation">
                                <button class="nav-link" id="media-tab" data-bs-toggle="tab" data-bs-target="#media" type="button" role="tab">Gambar & Link</button>
                            </li>
                        </ul>

                        <!-- Tab content -->
                        <div class="tab-content" id="editTabContent">
                            <div class="tab-pane fade show active" id="basic" role="tabpanel">
                                <div class="mt-3">
                                    <div class="mb-3">
                                        <label for="edit_judul" class="form-label">Judul <span class="text-danger">*</span></label>
                                        <input type="text" class="form-control" id="edit_judul" name="judul" required maxlength="50">
                                    </div>
                                    <div class="mb-3">
                                        <label for="edit_isi" class="form-label">Isi <span class="text-danger">*</span></label>
                                        <textarea class="form-control" id="edit_isi" name="isi" rows="4" required></textarea>
                                    </div>
                                    <div class="mb-3">
                                        <label for="edit_divisi" class="form-label">Divisi <span class="text-danger">*</span></label>
                                        <select class="form-select" id="edit_divisi" name="divisi" required>
                                            <option value="">Pilih Divisi</option>
                                            <option value="All">All</option>
                                            <option value="Admin">Admin</option>
                                            <option value="Kasir">Kasir</option>
                                            <option value="Pramusaji">Pramusaji</option>
                                            <option value="Dapur">Dapur</option>
                                        </select>
                                    </div>
                                </div>
                            </div>
                            <div class="tab-pane fade" id="media" role="tabpanel">
                                <div class="mt-3">
                                    <div class="mb-3">
                                        <label for="edit_gambar" class="form-label">Gambar</label>
                                        <input type="file" class="form-control" id="edit_gambar" name="gambar" accept="image/*">
                                        <input type="hidden" name="gambar_lama" id="edit_gambar_lama">
                                        <div id="edit_current_image" class="mt-2" style="display: none;">
                                            <small class="text-muted">Gambar saat ini:</small><br>
                                            <img id="edit_image_preview" src="" alt="Current Image" class="img-thumbnail" style="max-width: 150px; max-height: 100px;">
                                            <div class="mt-1">
                                                <small class="text-info">Pilih file baru untuk mengganti gambar.</small>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="mb-3">
                                        <label for="edit_link" class="form-label">Link</label>
                                        <input type="url" class="form-control" id="edit_link" name="link">
                                    </div>
                                </div>
                            </div>
                        </div>

                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                        <button type="submit" class="btn btn-primary">Update</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Delete Modal -->
    <div class="modal fade" id="deleteModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Konfirmasi Hapus</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p>Apakah Anda yakin ingin menghapus informasi <strong id="delete_judul"></strong>?</p>
                    <p class="text-danger">Tindakan ini tidak dapat dibatalkan!</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                    <form method="POST" style="display: inline;">
                        <input type="hidden" name="action" value="delete">
                        <input type="hidden" name="id_info" id="delete_id_info">
                        <button type="submit" class="btn btn-danger">Hapus</button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script src="../js/bootstrap.bundle.min.js"></script>
    <script>
        function editInformasi(id, judul, isi, divisi, gambar, link) {
            document.getElementById('edit_id_info').value = id;
            document.getElementById('edit_judul').value = judul;
            document.getElementById('edit_isi').value = isi;
            document.getElementById('edit_divisi').value = divisi;
            document.getElementById('edit_gambar_lama').value = gambar || '';
            document.getElementById('edit_link').value = link || '';
            
            // Show/hide current image preview
            const currentImageDiv = document.getElementById('edit_current_image');
            const imagePreview = document.getElementById('edit_image_preview');
            
            if (gambar && gambar.trim() !== '') {
                imagePreview.src = '../images/' + gambar;
                currentImageDiv.style.display = 'block';
            } else {
                currentImageDiv.style.display = 'none';
            }
            
            // Reset file input
            document.getElementById('edit_gambar').value = '';
            
            var editModal = new bootstrap.Modal(document.getElementById('editModal'));
            editModal.show();
        }
        
        function deleteInformasi(id, judul) {
            document.getElementById('delete_id_info').value = id;
            document.getElementById('delete_judul').textContent = judul;
            
            var deleteModal = new bootstrap.Modal(document.getElementById('deleteModal'));
            deleteModal.show();
        }
    </script>
</body>
</html>