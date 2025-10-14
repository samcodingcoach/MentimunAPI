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
                $kode_resep = trim($_POST['kode_resep']);
                $id_produk = $_POST['id_produk'];
                $id_user = $_SESSION['id_user'];
                
                if (!empty($kode_resep) && !empty($id_produk)) {
                    $stmt = $conn->prepare("INSERT INTO resep (kode_resep, id_produk, id_user) VALUES (?, ?, ?)");
                    $stmt->bind_param("sii", $kode_resep, $id_produk, $id_user);
                    if ($stmt->execute()) {
                        $message = 'Data resep berhasil ditambahkan!';
                    } else {
                        $error = 'Error: ' . $conn->error;
                    }
                } else {
                    $error = 'Kode resep dan produk wajib diisi!';
                }
                break;
                
            case 'update':
                $id_resep = $_POST['id_resep'];
                $kode_resep = trim($_POST['kode_resep']);
                $id_produk = $_POST['id_produk'];
                $id_user = $_SESSION['id_user'];
                
                if (!empty($kode_resep) && !empty($id_produk)) {
                    $stmt = $conn->prepare("UPDATE resep SET kode_resep = ?, id_produk = ?, id_user = ? WHERE id_resep = ?");
                    $stmt->bind_param("siii", $kode_resep, $id_produk, $id_user, $id_resep);
                    if ($stmt->execute()) {
                        $message = 'Data resep berhasil diperbarui!';
                    } else {
                        $error = 'Error: ' . $conn->error;
                    }
                } else {
                    $error = 'Kode resep dan produk harus diisi!';
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
    $where_conditions[] = "(resep.kode_resep LIKE ? OR produk_menu.nama_produk LIKE ? OR kategori_menu.nama_kategori LIKE ? OR pegawai.nama_lengkap LIKE ?)";
    $search_param = "%$search%";
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
    $types .= 'ssss';
}

$where_clause = !empty($where_conditions) ? 'WHERE ' . implode(' AND ', $where_conditions) : '';

// Get total count for pagination
$count_sql = "SELECT COUNT(*) as total FROM resep 
              INNER JOIN produk_menu ON resep.id_produk = produk_menu.id_produk
              INNER JOIN kategori_menu ON produk_menu.id_kategori = kategori_menu.id_kategori
              INNER JOIN pegawai ON resep.id_user = pegawai.id_user
              $where_clause";
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

// Get resep data with pagination using the specified query
$sql = "SELECT
    resep.id_resep, 
    resep.kode_resep, 
    resep.id_produk, 
    CONCAT(produk_menu.nama_produk,' - ',kategori_menu.nama_kategori, ' [',produk_menu.kode_produk,']') as nama_produk,
    resep.id_user, 
    CONCAT(pegawai.nama_lengkap,' [',pegawai.jabatan,']') as pembuat_resep,
    DATE_FORMAT(resep.tanggal_release,'%d %M %Y %H:%i') as tanggal_release,
    COUNT(resep_detail.id_bahan) as qty_bahan,
    CONCAT('Rp ', FORMAT(COALESCE(SUM(resep_detail.nilai_ekpetasi), 0), 0)) AS nilai
FROM resep
INNER JOIN produk_menu ON resep.id_produk = produk_menu.id_produk
INNER JOIN kategori_menu ON produk_menu.id_kategori = kategori_menu.id_kategori
INNER JOIN pegawai ON resep.id_user = pegawai.id_user
LEFT JOIN resep_detail ON resep.id_resep = resep_detail.id_resep
$where_clause
GROUP BY resep.id_resep
ORDER BY resep.tanggal_release DESC
LIMIT $limit OFFSET $offset";

if (!empty($params)) {
    $stmt = $conn->prepare($sql);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $result = $stmt->get_result();
    $reseps = $result->fetch_all(MYSQLI_ASSOC);
} else {
    $result = $conn->query($sql);
    if ($result) {
        $reseps = $result->fetch_all(MYSQLI_ASSOC);
    } else {
        $error = 'Error fetching data: ' . $conn->error;
        $reseps = [];
    }
}

// Get products for dropdown
$products_sql = "SELECT pm.id_produk, CONCAT(pm.nama_produk, ' - ', km.nama_kategori, ' [', pm.kode_produk, ']') as display_name 
                 FROM produk_menu pm 
                 INNER JOIN kategori_menu km ON pm.id_kategori = km.id_kategori 
                 WHERE pm.aktif = '1' 
                 ORDER BY pm.nama_produk";
$products_result = $conn->query($products_sql);
$products = $products_result->fetch_all(MYSQLI_ASSOC);

// Get single resep for editing
$edit_resep = null;
if (isset($_GET['edit'])) {
    $stmt = $conn->prepare("SELECT * FROM resep WHERE id_resep = ?");
    $stmt->bind_param("i", $_GET['edit']);
    $stmt->execute();
    $result = $stmt->get_result();
    $edit_resep = $result->fetch_assoc();
}
?>

<!doctype html>
<html lang="en">
  <head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Resep - Admin Dashboard</title>
    <link href="../css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css" rel="stylesheet">
    <link href="../css/admin.css" rel="stylesheet">
    <style>
    .searchable-select {
        position: relative;
    }
    .searchable-select .dropdown-menu {
        position: absolute;
        top: 100%;
        left: 0;
        z-index: 1000;
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

        <!-- Main Content -->
        <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
          <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
            <h1 class="h2">Manajemen Resep</h1>
            <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#resepModal">
              <i class="bi bi-plus-circle"></i> Tambah Resep
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
                <input type="text" class="form-control me-2" name="search" placeholder="Cari kode resep, produk, kategori, atau pembuat..." value="<?php echo htmlspecialchars($search); ?>">
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
                  <th>Kode Resep</th>
                  <th>Produk</th>
                  <th class="d-none d-md-table-cell">Pembuat</th>
                  <th class="d-none d-lg-table-cell">Tanggal</th>
                  <th class="d-none d-lg-table-cell">Qty Bahan</th>
                  <th class="d-none d-lg-table-cell">Nilai</th>
                  <th>Aksi</th>
                </tr>
              </thead>
              <tbody>
                <?php if (!empty($reseps)): ?>
                  <?php foreach ($reseps as $index => $resep): ?>
                    <tr>
                      <td><?php echo $offset + $index + 1; ?></td>
                      <td><?php echo htmlspecialchars($resep['kode_resep']); ?></td>
                      <td><?php echo htmlspecialchars($resep['nama_produk']); ?></td>
                      <td class="d-none d-md-table-cell"><?php echo htmlspecialchars($resep['pembuat_resep']); ?></td>
                      <td class="d-none d-lg-table-cell"><?php echo htmlspecialchars($resep['tanggal_release']); ?></td>
                      <td class="d-none d-lg-table-cell"><?php echo $resep['qty_bahan']; ?></td>
                      <td class="d-none d-lg-table-cell"><?php echo htmlspecialchars($resep['nilai']); ?></td>
                      <td>
                        <a href="resep_detail.php?id=<?php echo $resep['id_resep']; ?>" class="btn btn-sm btn-info">
                          <i class="bi bi-eye"></i> Detail
                        </a>
                      </td>
                    </tr>
                  <?php endforeach; ?>
                <?php else: ?>
                  <tr>
                    <td colspan="8" class="text-center">Tidak ada data resep</td>
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

    <!-- Modal -->
    <div class="modal fade" id="resepModal" tabindex="-1" aria-labelledby="resepModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-md">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="resepModalLabel">
                        <?php echo $edit_resep ? 'Edit Resep' : 'Tambah Resep'; ?>
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form method="POST">
                    <input type="hidden" name="action" value="<?php echo $edit_resep ? 'update' : 'create'; ?>">
                    <?php if ($edit_resep): ?>
                        <input type="hidden" name="id_resep" value="<?php echo $edit_resep['id_resep']; ?>">
                    <?php endif; ?>
                    
                    <div class="modal-body">
                        <div class="mb-3">
                            <label for="kode_resep" class="form-label">Kode Resep *</label>
                            <input type="text" class="form-control" id="kode_resep" name="kode_resep" 
                                   value="<?php echo $edit_resep ? htmlspecialchars($edit_resep['kode_resep']) : ''; ?>" 
                                   maxlength="50" required>
                        </div>
                        
                        <div class="mb-3">
                            <label for="id_produk" class="form-label">Produk *</label>
                            <div class="searchable-select">
                                <input type="text" class="form-control" id="produk_display" placeholder="Pilih produk..." readonly onclick="toggleDropdown()" style="padding-right: 2.5rem;">
                                <input type="hidden" name="id_produk" id="id_produk" required>
                                <div class="dropdown-menu" id="produk_dropdown" style="display: none; width: 100%; max-height: 200px; overflow-y: auto; border: 1px solid #ced4da; border-radius: 0.375rem; background: white; box-shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.075);">
                                    <input type="text" class="search-input form-control" id="produk_search" placeholder="Cari produk..." onkeyup="filterProduk()" style="margin: 0.5rem; width: calc(100% - 1rem);">
                                    <?php foreach ($products as $product): ?>
                                    <div class="dropdown-item" data-value="<?php echo $product['id_produk']; ?>" data-text="<?php echo htmlspecialchars($product['display_name']); ?>" onclick="selectProduk(this)" style="padding: 0.5rem 1rem; cursor: pointer; border-bottom: 1px solid #f8f9fa;">
                                        <?php echo htmlspecialchars($product['display_name']); ?>
                                    </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                        <button type="submit" class="btn btn-primary">
                            <?php echo $edit_resep ? 'Update' : 'Simpan'; ?>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="../js/bootstrap.bundle.min.js"></script>
    
    <script>
    // Searchable dropdown functionality
    function toggleDropdown() {
        const dropdown = document.getElementById('produk_dropdown');
        const isVisible = dropdown.style.display === 'block';
        dropdown.style.display = isVisible ? 'none' : 'block';
        
        if (!isVisible) {
            document.getElementById('produk_search').value = '';
            filterProduk();
            document.getElementById('produk_search').focus();
        }
    }
    
    function selectProduk(element) {
        const value = element.getAttribute('data-value');
        const text = element.getAttribute('data-text');
        
        document.getElementById('id_produk').value = value;
        document.getElementById('produk_display').value = text;
        document.getElementById('produk_dropdown').style.display = 'none';
    }
    
    function filterProduk() {
        const searchValue = document.getElementById('produk_search').value.toLowerCase();
        const items = document.querySelectorAll('#produk_dropdown .dropdown-item');
        
        items.forEach(item => {
            const text = item.textContent.toLowerCase();
            if (text.includes(searchValue)) {
                item.style.display = 'block';
            } else {
                item.style.display = 'none';
            }
        });
    }
    
    // Close dropdown when clicking outside
    document.addEventListener('click', function(event) {
        const dropdown = document.getElementById('produk_dropdown');
        const display = document.getElementById('produk_display');
        
        if (!dropdown.contains(event.target) && event.target !== display) {
            dropdown.style.display = 'none';
        }
    });
    </script>
    
    <!-- Auto show modal if editing -->
    <?php if ($edit_resep): ?>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            var resepModal = new bootstrap.Modal(document.getElementById('resepModal'));
            resepModal.show();
            
            // Set selected product for editing
            <?php if ($edit_resep): ?>
            const editProdukId = '<?php echo $edit_resep['id_produk']; ?>';
            const produkItems = document.querySelectorAll('#produk_dropdown .dropdown-item');
            produkItems.forEach(item => {
                if (item.getAttribute('data-value') === editProdukId) {
                    selectProduk(item);
                }
            });
            <?php endif; ?>
        });
    </script>
    <?php endif; ?>
    
    <!-- Auto close modal after successful update -->
    <?php if ($message && !$error): ?>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            var resepModal = bootstrap.Modal.getInstance(document.getElementById('resepModal'));
            if (resepModal) {
                resepModal.hide();
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