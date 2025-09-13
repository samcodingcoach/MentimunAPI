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

// Handle PPN actions
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] == 'add_ppn') {
        $nilai_ppn = (float)$_POST['nilai_ppn'];
        $keterangan = trim($_POST['keterangan']);
        
        if ($nilai_ppn > 0) {
            // Deaktifkan semua PPN yang aktif
            $deactivate_sql = "UPDATE ppn SET aktif = 0 WHERE aktif = 1";
            $conn->query($deactivate_sql);
            
            // Insert PPN baru
            $sql = "INSERT INTO ppn (nilai_ppn, keterangan, rilis, aktif) VALUES (?, ?, CURDATE(), 1)";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("ds", $nilai_ppn, $keterangan);
            
            if ($stmt->execute()) {
                $message = 'PPN berhasil ditambahkan!';
            } else {
                $error = 'Gagal menambahkan PPN: ' . $conn->error;
            }
        } else {
            $error = 'Nilai PPN harus lebih dari 0!';
        }
    }
    
    if ($_POST['action'] == 'update_ppn') {
        $id_ppn = (int)$_POST['id_ppn'];
        $nilai_ppn = (float)$_POST['nilai_ppn'];
        $keterangan = trim($_POST['keterangan']);
        
        if ($id_ppn > 0 && $nilai_ppn > 0) {
            $sql = "UPDATE ppn SET nilai_ppn = ?, keterangan = ? WHERE id_ppn = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("dsi", $nilai_ppn, $keterangan, $id_ppn);
            
            if ($stmt->execute()) {
                $message = 'PPN berhasil diupdate!';
            } else {
                $error = 'Gagal mengupdate PPN: ' . $conn->error;
            }
        } else {
            $error = 'Data tidak valid!';
        }
    }
    
    if ($_POST['action'] == 'delete_ppn') {
        $id_ppn = (int)$_POST['id_ppn'];
        
        if ($id_ppn > 0) {
            $sql = "DELETE FROM ppn WHERE id_ppn = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("i", $id_ppn);
            
            if ($stmt->execute()) {
                $message = 'PPN berhasil dihapus!';
            } else {
                $error = 'Gagal menghapus PPN: ' . $conn->error;
            }
        }
    }
    
    if ($_POST['action'] == 'toggle_ppn') {
        $id_ppn = (int)$_POST['id_ppn'];
        $current_status = (int)$_POST['current_status'];
        $new_status = ($current_status == 1) ? 0 : 1;
        
        if ($id_ppn > 0) {
            if ($new_status == 1) {
                // Deaktifkan semua PPN yang aktif
                $deactivate_sql = "UPDATE ppn SET aktif = 0 WHERE aktif = 1";
                $conn->query($deactivate_sql);
            }
            
            $sql = "UPDATE ppn SET aktif = ? WHERE id_ppn = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("ii", $new_status, $id_ppn);
            
            if ($stmt->execute()) {
                $status_text = ($new_status == 1) ? 'diaktifkan' : 'dinonaktifkan';
                $message = "PPN berhasil {$status_text}!";
            } else {
                $error = 'Gagal mengubah status PPN: ' . $conn->error;
            }
        }
    }
}

// Handle Takeaway actions
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] == 'add_takeaway') {
        $biaya_per_item = (float)$_POST['biaya_per_item'];
        $id_user = $_SESSION['id_user'];
        
        if ($biaya_per_item >= 0) {
            $sql = "INSERT INTO takeaway_charge (tanggal_rilis, id_user, biaya_per_item) VALUES (CURDATE(), ?, ?)";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("id", $id_user, $biaya_per_item);
            
            if ($stmt->execute()) {
                $message = 'Biaya takeaway berhasil ditambahkan!';
            } else {
                $error = 'Gagal menambahkan biaya takeaway: ' . $conn->error;
            }
        } else {
            $error = 'Biaya per item tidak valid!';
        }
    }
    
    if ($_POST['action'] == 'update_takeaway') {
        $id_ta = (int)$_POST['id_ta'];
        $biaya_per_item = (float)$_POST['biaya_per_item'];
        
        if ($id_ta > 0 && $biaya_per_item >= 0) {
            $sql = "UPDATE takeaway_charge SET biaya_per_item = ? WHERE id_ta = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("di", $biaya_per_item, $id_ta);
            
            if ($stmt->execute()) {
                $message = 'Biaya takeaway berhasil diupdate!';
            } else {
                $error = 'Gagal mengupdate biaya takeaway: ' . $conn->error;
            }
        } else {
            $error = 'Data tidak valid!';
        }
    }
    
    if ($_POST['action'] == 'delete_takeaway') {
        $id_ta = (int)$_POST['id_ta'];
        
        if ($id_ta > 0) {
            $sql = "DELETE FROM takeaway_charge WHERE id_ta = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("i", $id_ta);
            
            if ($stmt->execute()) {
                $message = 'Biaya takeaway berhasil dihapus!';
            } else {
                $error = 'Gagal menghapus biaya takeaway: ' . $conn->error;
            }
        }
    }
}

// Get PPN data
$ppn_data = [];
try {
    $ppn_sql = "SELECT ppn.id_ppn, ppn.nilai_ppn, ppn.keterangan, DATE_FORMAT(ppn.rilis,'%d %M %Y') as rilis, ppn.aktif FROM ppn ORDER BY id_ppn DESC";
    $ppn_result = $conn->query($ppn_sql);
    $ppn_data = $ppn_result->fetch_all(MYSQLI_ASSOC);
} catch (Exception $e) {
    $error = 'Terjadi kesalahan saat mengambil data PPN: ' . $e->getMessage();
}

// Get Takeaway data
$takeaway_data = [];
try {
    $takeaway_sql = "SELECT id_ta, DATE_FORMAT(tanggal_rilis,'%d %M %Y') as tanggal_rilis, biaya_per_item FROM takeaway_charge ORDER BY DATE(tanggal_rilis) DESC";
    $takeaway_result = $conn->query($takeaway_sql);
    $takeaway_data = $takeaway_result->fetch_all(MYSQLI_ASSOC);
} catch (Exception $e) {
    $error = 'Terjadi kesalahan saat mengambil data takeaway: ' . $e->getMessage();
}

// Get current takeaway charge
$current_takeaway = 0;
try {
    $current_sql = "SELECT biaya_per_item FROM takeaway_charge ORDER BY DATE(tanggal_rilis) DESC LIMIT 1";
    $current_result = $conn->query($current_sql);
    if ($current_result->num_rows > 0) {
        $current_row = $current_result->fetch_assoc();
        $current_takeaway = $current_row['biaya_per_item'];
    }
} catch (Exception $e) {
    // Silent error for current takeaway
}
?>

<!doctype html>
<html lang="en">
  <head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Biaya Lain - Admin Dashboard</title>
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
                  <i class="bi bi-cash-stack"></i>
                  <span>Penjualan</span>
                  <i class="bi bi-chevron-down ms-auto"></i>
                </a>
                <div class="collapse show" id="penjualanMenu">
                  <ul class="nav flex-column ms-3">
                    <li class="nav-item"><a class="nav-link" href="shift_kasir.php"><i class="bi bi-clock"></i> Shift Kasir</a></li>
                    <li class="nav-item"><a class="nav-link active" href="biaya_lain.php"><i class="bi bi-receipt"></i> Biaya Lain</a></li>
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
            <!-- Same menu structure as desktop sidebar -->
          </div>
        </div>

        <!-- Main content -->
        <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
          <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
            <h1 class="h2">Biaya Lain</h1>
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

          <!-- Tab Navigation -->
          <ul class="nav nav-tabs" id="biayaTab" role="tablist">
            <li class="nav-item" role="presentation">
              <button class="nav-link active" id="pajak-tab" data-bs-toggle="tab" data-bs-target="#pajak" type="button" role="tab">
                <i class="bi bi-percent me-2"></i>Pajak (PPN)
              </button>
            </li>
            <li class="nav-item" role="presentation">
              <button class="nav-link" id="takeaway-tab" data-bs-toggle="tab" data-bs-target="#takeaway" type="button" role="tab">
                <i class="bi bi-bag me-2"></i>Takeaway
              </button>
            </li>
          </ul>

          <!-- Tab Content -->
          <div class="tab-content" id="biayaTabContent">
            <!-- PPN Tab -->
            <div class="tab-pane fade show active" id="pajak" role="tabpanel">
              <div class="card mt-3">
                <div class="card-header d-flex justify-content-between align-items-center">
                  <h5 class="mb-0">Data PPN</h5>
                  <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addPpnModal">
                    <i class="bi bi-plus-circle me-2"></i>Tambah PPN
                  </button>
                </div>
                <div class="card-body">
                  <div class="table-responsive">
                    <table class="table table-striped table-hover">
                      <thead class="table-dark">
                        <tr>
                          <th>No</th>
                          <th>Nilai PPN (%)</th>
                          <th>Keterangan</th>
                          <th>Tanggal Rilis</th>
                          <th>Status</th>
                          <th>Aksi</th>
                        </tr>
                      </thead>
                      <tbody>
                        <?php if (empty($ppn_data)): ?>
                          <tr>
                            <td colspan="6" class="text-center text-muted">Belum ada data PPN</td>
                          </tr>
                        <?php else: ?>
                          <?php foreach ($ppn_data as $index => $ppn): ?>
                            <tr>
                              <td><?php echo $index + 1; ?></td>
                              <td><?php echo number_format($ppn['nilai_ppn'], 2); ?>%</td>
                              <td><?php echo htmlspecialchars($ppn['keterangan']); ?></td>
                              <td><?php echo $ppn['rilis']; ?></td>
                              <td>
                                <?php if ($ppn['aktif'] == 1): ?>
                                  <button class="btn btn-success btn-sm" data-bs-toggle="modal" data-bs-target="#togglePpnModal" 
                                          onclick="setTogglePpnData(<?php echo $ppn['id_ppn']; ?>, '<?php echo addslashes($ppn['keterangan']); ?>', <?php echo $ppn['aktif']; ?>)">
                                    <i class="bi bi-check-circle"></i> Aktif
                                  </button>
                                <?php else: ?>
                                  <button class="btn btn-warning btn-sm" data-bs-toggle="modal" data-bs-target="#togglePpnModal" 
                                          onclick="setTogglePpnData(<?php echo $ppn['id_ppn']; ?>, '<?php echo addslashes($ppn['keterangan']); ?>', <?php echo $ppn['aktif']; ?>)">
                                    <i class="bi bi-pause-circle"></i> Nonaktif
                                  </button>
                                <?php endif; ?>
                              </td>
                              <td>
                                <button class="btn btn-sm btn-outline-primary me-1" data-bs-toggle="modal" data-bs-target="#editPpnModal" 
                                        onclick="setEditPpnData(<?php echo $ppn['id_ppn']; ?>, <?php echo $ppn['nilai_ppn']; ?>, '<?php echo addslashes($ppn['keterangan']); ?>')">
                                  <i class="bi bi-pencil"></i>
                                </button>
                                <button class="btn btn-sm btn-outline-danger" data-bs-toggle="modal" data-bs-target="#deletePpnModal" 
                                        onclick="setDeletePpnData(<?php echo $ppn['id_ppn']; ?>, '<?php echo addslashes($ppn['keterangan']); ?>')">
                                  <i class="bi bi-trash"></i>
                                </button>
                              </td>
                            </tr>
                          <?php endforeach; ?>
                        <?php endif; ?>
                      </tbody>
                    </table>
                  </div>
                </div>
              </div>
            </div>

            <!-- Takeaway Tab -->
            <div class="tab-pane fade" id="takeaway" role="tabpanel">
              <div class="card mt-3">
                <div class="card-header d-flex justify-content-between align-items-center">
                  <h5 class="mb-0">Data Biaya Takeaway</h5>
                  <div>
                    <span class="badge bg-info me-2">Biaya Saat Ini: Rp <?php echo number_format($current_takeaway, 0, ',', '.'); ?></span>
                    <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addTakeawayModal">
                      <i class="bi bi-plus-circle me-2"></i>Tambah Biaya
                    </button>
                  </div>
                </div>
                <div class="card-body">
                  <div class="table-responsive">
                    <table class="table table-striped table-hover">
                      <thead class="table-dark">
                        <tr>
                          <th>No</th>
                          <th>Tanggal Rilis</th>
                          <th>Biaya per Item</th>
                          <th>Aksi</th>
                        </tr>
                      </thead>
                      <tbody>
                        <?php if (empty($takeaway_data)): ?>
                          <tr>
                            <td colspan="4" class="text-center text-muted">Belum ada data biaya takeaway</td>
                          </tr>
                        <?php else: ?>
                          <?php foreach ($takeaway_data as $index => $takeaway): ?>
                            <tr>
                              <td><?php echo $index + 1; ?></td>
                              <td><?php echo $takeaway['tanggal_rilis']; ?></td>
                              <td>Rp <?php echo number_format($takeaway['biaya_per_item'], 0, ',', '.'); ?></td>
                              <td>
                                <button class="btn btn-sm btn-outline-primary me-1" data-bs-toggle="modal" data-bs-target="#editTakeawayModal" 
                                        onclick="setEditTakeawayData(<?php echo $takeaway['id_ta']; ?>, <?php echo $takeaway['biaya_per_item']; ?>)">
                                  <i class="bi bi-pencil"></i>
                                </button>
                                <button class="btn btn-sm btn-outline-danger" data-bs-toggle="modal" data-bs-target="#deleteTakeawayModal" 
                                        onclick="setDeleteTakeawayData(<?php echo $takeaway['id_ta']; ?>, <?php echo $takeaway['biaya_per_item']; ?>)">
                                  <i class="bi bi-trash"></i>
                                </button>
              </td>
                            </tr>
                          <?php endforeach; ?>
                        <?php endif; ?>
                      </tbody>
                    </table>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </main>
       </div>
     </div>

    <!-- Modal Add PPN -->
    <div class="modal fade" id="addPpnModal" tabindex="-1">
      <div class="modal-dialog">
        <div class="modal-content">
          <div class="modal-header bg-primary text-white">
            <h5 class="modal-title"><i class="bi bi-plus-circle me-2"></i>Tambah PPN</h5>
            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
          </div>
          <form method="POST">
            <div class="modal-body">
              <input type="hidden" name="action" value="add_ppn">
              <div class="mb-3">
                <label for="nilai_ppn" class="form-label">Nilai PPN (%)</label>
                <input type="number" class="form-control" id="nilai_ppn" name="nilai_ppn" step="0.01" min="0" max="100" required>
              </div>
              <div class="mb-3">
                <label for="keterangan" class="form-label">Keterangan</label>
                <textarea class="form-control" id="keterangan" name="keterangan" rows="3" required></textarea>
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

    <!-- Modal Edit PPN -->
    <div class="modal fade" id="editPpnModal" tabindex="-1">
      <div class="modal-dialog">
        <div class="modal-content">
          <div class="modal-header bg-warning text-dark">
            <h5 class="modal-title"><i class="bi bi-pencil me-2"></i>Edit PPN</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
          </div>
          <form method="POST">
            <div class="modal-body">
              <input type="hidden" name="action" value="update_ppn">
              <input type="hidden" id="edit_id_ppn" name="id_ppn">
              <div class="mb-3">
                <label for="edit_nilai_ppn" class="form-label">Nilai PPN (%)</label>
                <input type="number" class="form-control" id="edit_nilai_ppn" name="nilai_ppn" step="0.01" min="0" max="100" required>
              </div>
              <div class="mb-3">
                <label for="edit_keterangan" class="form-label">Keterangan</label>
                <textarea class="form-control" id="edit_keterangan" name="keterangan" rows="3" required></textarea>
              </div>
            </div>
            <div class="modal-footer">
              <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
              <button type="submit" class="btn btn-warning">Update</button>
            </div>
          </form>
        </div>
      </div>
    </div>

    <!-- Modal Delete PPN -->
    <div class="modal fade" id="deletePpnModal" tabindex="-1">
      <div class="modal-dialog">
        <div class="modal-content">
          <div class="modal-header bg-danger text-white">
            <h5 class="modal-title"><i class="bi bi-trash me-2"></i>Hapus PPN</h5>
            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
          </div>
          <form method="POST">
            <div class="modal-body">
              <input type="hidden" name="action" value="delete_ppn">
              <input type="hidden" id="delete_id_ppn" name="id_ppn">
              <p>Apakah Anda yakin ingin menghapus PPN dengan keterangan:</p>
              <p class="fw-bold text-danger" id="delete_ppn_keterangan"></p>
            </div>
            <div class="modal-footer">
              <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
              <button type="submit" class="btn btn-danger">Hapus</button>
            </div>
          </form>
        </div>
      </div>
    </div>

    <!-- Modal Toggle PPN Status -->
    <div class="modal fade" id="togglePpnModal" tabindex="-1">
      <div class="modal-dialog">
        <div class="modal-content">
          <div class="modal-header bg-info text-white">
            <h5 class="modal-title"><i class="bi bi-arrow-repeat me-2"></i>Ubah Status PPN</h5>
            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
          </div>
          <form method="POST">
            <div class="modal-body">
              <input type="hidden" name="action" value="toggle_ppn">
              <input type="hidden" id="toggle_id_ppn" name="id_ppn">
              <p>Apakah Anda yakin ingin mengubah status PPN:</p>
              <p class="fw-bold" id="toggle_ppn_keterangan"></p>
              <p class="text-muted" id="toggle_ppn_status"></p>
            </div>
            <div class="modal-footer">
              <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
              <button type="submit" class="btn btn-info">Ubah Status</button>
            </div>
          </form>
        </div>
      </div>
    </div>

    <!-- Modal Add Takeaway -->
    <div class="modal fade" id="addTakeawayModal" tabindex="-1">
      <div class="modal-dialog">
        <div class="modal-content">
          <div class="modal-header bg-primary text-white">
            <h5 class="modal-title"><i class="bi bi-plus-circle me-2"></i>Tambah Biaya Takeaway</h5>
            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
          </div>
          <form method="POST">
            <div class="modal-body">
              <input type="hidden" name="action" value="add_takeaway">
              <div class="mb-3">
                <label for="biaya_per_item" class="form-label">Biaya per Item (Rp)</label>
                <input type="number" class="form-control" id="biaya_per_item" name="biaya_per_item" min="0" required>
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

    <!-- Modal Edit Takeaway -->
    <div class="modal fade" id="editTakeawayModal" tabindex="-1">
      <div class="modal-dialog">
        <div class="modal-content">
          <div class="modal-header bg-warning text-dark">
            <h5 class="modal-title"><i class="bi bi-pencil me-2"></i>Edit Biaya Takeaway</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
          </div>
          <form method="POST">
            <div class="modal-body">
              <input type="hidden" name="action" value="update_takeaway">
              <input type="hidden" id="edit_id_ta" name="id_ta">
              <div class="mb-3">
                <label for="edit_biaya_per_item" class="form-label">Biaya per Item (Rp)</label>
                <input type="number" class="form-control" id="edit_biaya_per_item" name="biaya_per_item" min="0" required>
              </div>
            </div>
            <div class="modal-footer">
              <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
              <button type="submit" class="btn btn-warning">Update</button>
            </div>
          </form>
        </div>
      </div>
    </div>

    <!-- Modal Delete Takeaway -->
    <div class="modal fade" id="deleteTakeawayModal" tabindex="-1">
      <div class="modal-dialog">
        <div class="modal-content">
          <div class="modal-header bg-danger text-white">
            <h5 class="modal-title"><i class="bi bi-trash me-2"></i>Hapus Biaya Takeaway</h5>
            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
          </div>
          <form method="POST">
            <div class="modal-body">
              <input type="hidden" name="action" value="delete_takeaway">
              <input type="hidden" id="delete_id_ta" name="id_ta">
              <p>Apakah Anda yakin ingin menghapus biaya takeaway:</p>
              <p class="fw-bold text-danger" id="delete_takeaway_biaya"></p>
            </div>
            <div class="modal-footer">
              <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
              <button type="submit" class="btn btn-danger">Hapus</button>
            </div>
          </form>
        </div>
      </div>
    </div>

    <!-- Bootstrap JS -->
    <script src="../js/bootstrap.bundle.min.js"></script>
    
    <!-- JavaScript Functions -->
    <script>
      // PPN Functions
      function setEditPpnData(id, nilai, keterangan) {
        document.getElementById('edit_id_ppn').value = id;
        document.getElementById('edit_nilai_ppn').value = nilai;
        document.getElementById('edit_keterangan').value = keterangan;
      }
      
      function setDeletePpnData(id, keterangan) {
        document.getElementById('delete_id_ppn').value = id;
        document.getElementById('delete_ppn_keterangan').textContent = keterangan;
      }
      
      function setTogglePpnData(id, keterangan, aktif) {
        document.getElementById('toggle_id_ppn').value = id;
        document.getElementById('toggle_ppn_keterangan').textContent = keterangan;
        
        const statusText = aktif == 1 ? 'Status saat ini: Aktif → akan diubah menjadi Nonaktif' : 'Status saat ini: Nonaktif → akan diubah menjadi Aktif';
        document.getElementById('toggle_ppn_status').textContent = statusText;
      }
      
      // Takeaway Functions
      function setEditTakeawayData(id, biaya) {
        document.getElementById('edit_id_ta').value = id;
        document.getElementById('edit_biaya_per_item').value = biaya;
      }
      
      function setDeleteTakeawayData(id, biaya) {
        document.getElementById('delete_id_ta').value = id;
        document.getElementById('delete_takeaway_biaya').textContent = 'Rp ' + new Intl.NumberFormat('id-ID').format(biaya);
      }
    </script>
  </body>
</html>