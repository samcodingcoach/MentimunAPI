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
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'add':
                $nama_konsumen = $_POST['nama_konsumen'] ?? '';
                $no_hp = $_POST['no_hp'] ?? '';
                $alamat = $_POST['alamat'] ?? '';
                $email = $_POST['email'] ?? '';
                $aktif = $_POST['aktif'] ?? '1';
                
                if (!empty($nama_konsumen)) {
                    $stmt = $conn->prepare("INSERT INTO konsumen (nama_konsumen, no_hp, alamat, email, aktif) VALUES (?, ?, ?, ?, ?)");
                    $stmt->bind_param("sssss", $nama_konsumen, $no_hp, $alamat, $email, $aktif);
                    
                    if ($stmt->execute()) {
                        $message = 'Konsumen berhasil ditambahkan!';
                    } else {
                        $error = 'Error: ' . $stmt->error;
                    }
                    $stmt->close();
                } else {
                    $error = 'Nama konsumen harus diisi!';
                }
                break;
                
            case 'edit':
                $id_konsumen = $_POST['id_konsumen'] ?? '';
                $nama_konsumen = $_POST['nama_konsumen'] ?? '';
                $no_hp = $_POST['no_hp'] ?? '';
                $alamat = $_POST['alamat'] ?? '';
                $email = $_POST['email'] ?? '';
                $aktif = $_POST['aktif'] ?? '1';
                
                if (!empty($id_konsumen) && !empty($nama_konsumen)) {
                    $stmt = $conn->prepare("UPDATE konsumen SET nama_konsumen = ?, no_hp = ?, alamat = ?, email = ?, aktif = ? WHERE id_konsumen = ?");
                    $stmt->bind_param("sssssi", $nama_konsumen, $no_hp, $alamat, $email, $aktif, $id_konsumen);
                    
                    if ($stmt->execute()) {
                        $message = 'Konsumen berhasil diupdate!';
                    } else {
                        $error = 'Error: ' . $stmt->error;
                    }
                    $stmt->close();
                } else {
                    $error = 'Data tidak valid!';
                }
                break;
                
            case 'delete':
                $id_konsumen = $_POST['id_konsumen'] ?? '';
                
                if (!empty($id_konsumen)) {
                    $stmt = $conn->prepare("DELETE FROM konsumen WHERE id_konsumen = ?");
                    $stmt->bind_param("i", $id_konsumen);
                    
                    if ($stmt->execute()) {
                        $message = 'Konsumen berhasil dihapus!';
                    } else {
                        $error = 'Error: ' . $stmt->error;
                    }
                    $stmt->close();
                } else {
                    $error = 'ID konsumen tidak valid!';
                }
                break;
        }
    }
}

// Fetch data using the specified query
$sql = "
    SELECT
        konsumen.id_konsumen,
        konsumen.nama_konsumen,
        konsumen.no_hp,
        konsumen.alamat,
        konsumen.email,
        konsumen.aktif,
        COALESCE(SUM(pesanan.total_cart), 0) as total_cart
    FROM
        konsumen
    LEFT JOIN
        pesanan ON konsumen.id_konsumen = pesanan.id_konsumen
        AND pesanan.status_checkout = 1
    GROUP BY 
        konsumen.id_konsumen
    ORDER BY nama_konsumen ASC
";

$result = $conn->query($sql);
$konsumen_data = $result->fetch_all(MYSQLI_ASSOC);
?>

<!doctype html>
<html lang="en">
  <head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Data Konsumen - Admin Dashboard</title>
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
        <a class="navbar-brand" href="#"><?php echo isset($_SESSION['nama_aplikasi']) ? htmlspecialchars($_SESSION['nama_aplikasi']) : 'Admin'; ?></a>
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
                  <i class="bi bi-house-door"></i>
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
              <!-- Master Menu - Admin Only -->
              <li class="nav-item">
                <a class="nav-link" data-bs-toggle="collapse" href="#masterMenu" role="button">
                  <i class="bi bi-gear-fill"></i>
                  <span>Master</span>
                  <i class="bi bi-chevron-down ms-auto"></i>
                </a>
                <div class="collapse show" id="masterMenu">
                  <ul class="nav flex-column ms-3">
                    <li class="nav-item"><a class="nav-link" href="resto.php"><i class="bi bi-building"></i> Resto</a></li>
                    <li class="nav-item"><a class="nav-link" href="pegawai.php"><i class="bi bi-people"></i> Pegawai</a></li>
                    <li class="nav-item"><a class="nav-link active" href="konsumen.php"><i class="bi bi-person-check"></i> Konsumen</a></li>
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
                  <i class="bi bi-box-seam"></i>
                  <span>Produk</span>
                  <i class="bi bi-chevron-down ms-auto"></i>
                </a>
                <div class="collapse" id="produkMenu">
                  <ul class="nav flex-column ms-3">
                    <li class="nav-item"><a class="nav-link" href="kategori_menu.php"><i class="bi bi-tags"></i> Kategori Menu</a></li>
                    <li class="nav-item"><a class="nav-link" href="menu.php"><i class="bi bi-card-list"></i> Menu</a></li>
                    <li class="nav-item"><a class="nav-link" href="kategori_bahan.php"><i class="bi bi-collection"></i> Kategori Bahan</a></li>
                    <li class="nav-item"><a class="nav-link" href="bahan.php"><i class="bi bi-basket"></i> Bahan</a></li>
                    <li class="nav-item"><a class="nav-link" href="resep.php"><i class="bi bi-journal-text"></i> Resep</a></li>
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
                    <li class="nav-item"><a class="nav-link" href="pembelian.php"><i class="bi bi-clipboard-check"></i> Pesanan Pembelian</a></li>
                    <li class="nav-item"><a class="nav-link" href="pembayaran_pembelian.php"><i class="bi bi-currency-dollar"></i> Pembayaran</a></li>
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
              
              <!-- Laporan Menu - All Roles -->
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
              
              <!-- Pengaturan Menu - All Roles -->
              <li class="nav-item">
                <a class="nav-link" href="pengaturan.php">
                  <i class="bi bi-gear"></i>
                  <span>Pengaturan</span>
                </a>
              </li>
            </ul>
          </div>
        </div>

        <!-- Main content -->
        <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
          <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
            <h1 class="h2">Data Konsumen</h1>
            <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addModal">
              <i class="bi bi-plus-circle"></i> Tambah Konsumen
            </button>
          </div>

          <?php if ($message): ?>
          <div class="alert alert-success alert-dismissible fade show" role="alert">
            <?php echo $message; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
          </div>
          <?php endif; ?>

          <?php if ($error): ?>
          <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <?php echo $error; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
          </div>
          <?php endif; ?>

          <!-- Data Table -->
          <div class="table-responsive shadow-sm">
            <table class="table table-hover align-middle">
              <thead class="table-dark">
                <tr>
                  <th class="text-center">No</th>
                  <th>Nama Konsumen</th>
                  <th>HP</th>
                  <th class="text-center">Status</th>
                  <th class="text-center">Action</th>
                </tr>
              </thead>
              <tbody>
                <?php if (empty($konsumen_data)): ?>
                <tr>
                  <td colspan="5" class="text-center text-muted py-5">
                    <i class="bi bi-inbox display-1 text-muted d-block mb-3"></i>
                    <span class="fs-5">Belum ada data konsumen</span>
                  </td>
                </tr>
                <?php else: ?>
                <?php 
                $no = 1;
                foreach ($konsumen_data as $row): 
                ?>
                <tr>
                  <td class="text-center fw-bold"><?php echo $no++; ?></td>
                  <td>
                    <a href="#" class="text-decoration-none fw-semibold" onclick="showDetailModal(
                      '<?php echo htmlspecialchars($row['id_konsumen']); ?>',
                      '<?php echo htmlspecialchars($row['nama_konsumen']); ?>',
                      '<?php echo htmlspecialchars($row['no_hp']); ?>',
                      '<?php echo htmlspecialchars($row['alamat']); ?>',
                      '<?php echo htmlspecialchars($row['email']); ?>',
                      '<?php echo $row['aktif']; ?>',
                      '<?php echo number_format($row['total_cart'], 0, ',', '.'); ?>'
                    )" data-bs-toggle="modal" data-bs-target="#detailModal">
                      <?php echo htmlspecialchars($row['nama_konsumen']); ?>
                    </a>
                  </td>
                  <td><?php echo htmlspecialchars($row['no_hp'] ?: '-'); ?></td>
                  <td class="text-center">
                    <?php if ($row['aktif'] == '1'): ?>
                      <span class="badge bg-success">Aktif</span>
                    <?php else: ?>
                      <span class="badge bg-danger">Nonaktif</span>
                    <?php endif; ?>
                  </td>
                  <td class="text-center">
                    <button type="button" class="btn btn-sm btn-outline-primary" onclick="editKonsumen(
                      '<?php echo $row['id_konsumen']; ?>',
                      '<?php echo htmlspecialchars($row['nama_konsumen']); ?>',
                      '<?php echo htmlspecialchars($row['no_hp']); ?>',
                      '<?php echo htmlspecialchars($row['alamat']); ?>',
                      '<?php echo htmlspecialchars($row['email']); ?>',
                      '<?php echo $row['aktif']; ?>'
                    )" data-bs-toggle="modal" data-bs-target="#editModal">
                      <i class="bi bi-pencil"></i> Edit
                    </button>
                  </td>
                </tr>
                <?php endforeach; ?>
                <?php endif; ?>
              </tbody>
            </table>
          </div>
        </main>
      </div>
    </div>

    <!-- Add Modal -->
    <div class="modal fade" id="addModal" tabindex="-1">
      <div class="modal-dialog">
        <div class="modal-content">
          <div class="modal-header">
            <h5 class="modal-title">Tambah Konsumen</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
          </div>
          <form method="POST">
            <div class="modal-body">
              <input type="hidden" name="action" value="add">
              <div class="mb-3">
                <label for="add_nama_konsumen" class="form-label">Nama Konsumen *</label>
                <input type="text" class="form-control" id="add_nama_konsumen" name="nama_konsumen" required>
              </div>
              <div class="mb-3">
                <label for="add_no_hp" class="form-label">No. HP</label>
                <input type="text" class="form-control" id="add_no_hp" name="no_hp">
              </div>
              <div class="mb-3">
                <label for="add_alamat" class="form-label">Alamat</label>
                <textarea class="form-control" id="add_alamat" name="alamat" rows="3"></textarea>
              </div>
              <div class="mb-3">
                <label for="add_email" class="form-label">Email</label>
                <input type="email" class="form-control" id="add_email" name="email">
              </div>
              <div class="mb-3">
                <label for="add_aktif" class="form-label">Status</label>
                <select class="form-select" id="add_aktif" name="aktif">
                  <option value="1">Aktif</option>
                  <option value="0">Nonaktif</option>
                </select>
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
            <h5 class="modal-title">Edit Konsumen</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
          </div>
          <form method="POST">
            <div class="modal-body">
              <input type="hidden" name="action" value="edit">
              <input type="hidden" name="id_konsumen" id="edit_id_konsumen">
              <div class="mb-3">
                <label for="edit_nama_konsumen" class="form-label">Nama Konsumen *</label>
                <input type="text" class="form-control" id="edit_nama_konsumen" name="nama_konsumen" required>
              </div>
              <div class="mb-3">
                <label for="edit_no_hp" class="form-label">No. HP</label>
                <input type="text" class="form-control" id="edit_no_hp" name="no_hp">
              </div>
              <div class="mb-3">
                <label for="edit_alamat" class="form-label">Alamat</label>
                <textarea class="form-control" id="edit_alamat" name="alamat" rows="3"></textarea>
              </div>
              <div class="mb-3">
                <label for="edit_email" class="form-label">Email</label>
                <input type="email" class="form-control" id="edit_email" name="email">
              </div>
              <div class="mb-3">
                <label for="edit_aktif" class="form-label">Status</label>
                <select class="form-select" id="edit_aktif" name="aktif">
                  <option value="1">Aktif</option>
                  <option value="0">Nonaktif</option>
                </select>
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

    <!-- Detail Modal -->
    <div class="modal fade" id="detailModal" tabindex="-1">
      <div class="modal-dialog modal-lg">
        <div class="modal-content">
          <div class="modal-header">
            <h5 class="modal-title">Detail Konsumen</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
          </div>
          <div class="modal-body">
            <div class="row g-3">
              <div class="col-md-6">
                <label class="form-label text-muted">ID Konsumen</label>
                <p class="fw-semibold" id="detail-id">-</p>
              </div>
              <div class="col-md-6">
                <label class="form-label text-muted">Status</label>
                <p id="detail-status">-</p>
              </div>
              <div class="col-md-6">
                <label class="form-label text-muted">Nama Konsumen</label>
                <p class="fw-semibold" id="detail-nama">-</p>
              </div>
              <div class="col-md-6">
                <label class="form-label text-muted">No. HP</label>
                <p id="detail-hp">-</p>
              </div>
              <div class="col-12">
                <label class="form-label text-muted">Alamat</label>
                <p id="detail-alamat">-</p>
              </div>
              <div class="col-md-6">
                <label class="form-label text-muted">Email</label>
                <p id="detail-email">-</p>
              </div>
              <div class="col-md-6">
                <label class="form-label text-muted">Total Pembelian</label>
                <p class="fw-bold text-success" id="detail-total">-</p>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>

    <script src="../js/bootstrap.bundle.min.js"></script>
    <script>
      function editKonsumen(id, nama, hp, alamat, email, aktif) {
        document.getElementById('edit_id_konsumen').value = id;
        document.getElementById('edit_nama_konsumen').value = nama;
        document.getElementById('edit_no_hp').value = hp;
        document.getElementById('edit_alamat').value = alamat;
        document.getElementById('edit_email').value = email;
        document.getElementById('edit_aktif').value = aktif;
      }
      
      function showDetailModal(id, nama, hp, alamat, email, aktif, total) {
        document.getElementById('detail-id').textContent = id;
        document.getElementById('detail-nama').textContent = nama;
        document.getElementById('detail-hp').textContent = hp || '-';
        document.getElementById('detail-alamat').textContent = alamat || '-';
        document.getElementById('detail-email').textContent = email || '-';
        document.getElementById('detail-total').textContent = 'Rp ' + total;
        
        // Set status badge
        const statusElement = document.getElementById('detail-status');
        if (aktif == '1') {
          statusElement.innerHTML = '<span class="badge bg-success">Aktif</span>';
        } else {
          statusElement.innerHTML = '<span class="badge bg-danger">Nonaktif</span>';
        }
      }
    </script>
  </body>
</html>