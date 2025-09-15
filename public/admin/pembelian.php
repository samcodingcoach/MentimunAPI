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

// Generate kode request PO-YYMMDD-001
function generateKodeRequest($conn) {
    $today = date('ymd'); // YYMMDD format
    $prefix = 'PO-' . $today . '-';
    
    // Get the last request number for today
    $sql = "SELECT kode_request FROM bahan_request WHERE kode_request LIKE '$prefix%' ORDER BY kode_request DESC LIMIT 1";
    $result = mysqli_query($conn, $sql);
    
    if ($result && mysqli_num_rows($result) > 0) {
        $row = mysqli_fetch_assoc($result);
        $lastCode = $row['kode_request'];
        // Extract the number part
        $lastNum = intval(substr($lastCode, -3));
        $newNum = $lastNum + 1;
    } else {
        $newNum = 1;
    }
    
    return $prefix . sprintf('%03d', $newNum);
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        // 1. Membuat Request diawal sehingga ada id_request
        if ($_POST['action'] === 'create_request') {
            $kode_request = $_POST['kode_request'];
            $id_user = isset($_SESSION['id_user']) ? $_SESSION['id_user'] : 1;
            
            $sql = "INSERT INTO bahan_request (kode_request, grand_total, id_user, status) VALUES (?, 0, ?, '0')";
            $stmt = mysqli_prepare($conn, $sql);
            mysqli_stmt_bind_param($stmt, "si", $kode_request, $id_user);
            
            if (mysqli_stmt_execute($stmt)) {
                $id_request = mysqli_insert_id($conn);
                echo json_encode(['success' => true, 'id_request' => $id_request]);
            } else {
                echo json_encode(['success' => false, 'message' => 'Gagal membuat request']);
            }
            exit();
        }
        
        // 2. Setiap tambah ke daftar = insert ke bahan_request_detail
        if ($_POST['action'] === 'add_item') {
            $id_request = intval($_POST['id_request']);
            $id_bahan = intval($_POST['id_bahan']);
            $id_vendor = intval($_POST['id_vendor']);
            $jumlah_request = intval($_POST['jumlah_request']);
            $harga_est = floatval($_POST['harga_est']);
            $subtotal = floatval($_POST['subtotal']);
            $isInvoice = $_POST['tipe_pembayaran'] === 'invoice' ? '1' : '0';
            $nomor_bukti = $_POST['nomor_bukti_transaksi'] ?? '';
            $id_bahan_biaya = intval($_POST['id_bahan_biaya']) ?? null;
            
            $sql = "INSERT INTO bahan_request_detail (id_request, id_bahan, id_vendor, jumlah_request, harga_est, subtotal, isDone, isInvoice, nomor_bukti_transaksi, stok_status, id_bahan_biaya, perubahan_biaya) VALUES (?, ?, ?, ?, ?, ?, '0', ?, ?, '0', ?, '0')";
            $stmt = mysqli_prepare($conn, $sql);
            mysqli_stmt_bind_param($stmt, "iiiiddssi", $id_request, $id_bahan, $id_vendor, $jumlah_request, $harga_est, $subtotal, $isInvoice, $nomor_bukti, $id_bahan_biaya);
            
            if (mysqli_stmt_execute($stmt)) {
                $detail_id = mysqli_insert_id($conn);
                echo json_encode(['success' => true, 'detail_id' => $detail_id]);
            } else {
                echo json_encode(['success' => false, 'message' => 'Gagal menambah item']);
            }
            exit();
        }
        
        // 3. Simpan transaksi = update status jadi 1
        if ($_POST['action'] === 'save_request') {
            $id_request = intval($_POST['id_request']);
            $grand_total = floatval($_POST['grand_total']);
            
            $sql = "UPDATE bahan_request SET grand_total = ?, status = '1' WHERE id_request = ?";
            $stmt = mysqli_prepare($conn, $sql);
            mysqli_stmt_bind_param($stmt, "di", $grand_total, $id_request);
            
            if (mysqli_stmt_execute($stmt)) {
                $message = "Transaksi pembelian berhasil disimpan!";
            } else {
                $error = "Gagal menyimpan transaksi.";
            }
        }
        
        // Generate new kode request
        if ($_POST['action'] === 'get_new_kode_request') {
            $newKodeRequest = generateKodeRequest($conn);
            echo json_encode(['success' => true, 'kode_request' => $newKodeRequest]);
            exit();
        }
        
        // Delete cancelled request
        if ($_POST['action'] === 'delete_request') {
            $id_request = intval($_POST['id_request']);
            
            // Delete detail items first
            $sql_delete_details = "DELETE FROM bahan_request_detail WHERE id_request = ?";
            $stmt_details = mysqli_prepare($conn, $sql_delete_details);
            mysqli_stmt_bind_param($stmt_details, "i", $id_request);
            mysqli_stmt_execute($stmt_details);
            
            // Then delete request
            $sql_delete_request = "DELETE FROM bahan_request WHERE id_request = ? AND status = '0'";
            $stmt_request = mysqli_prepare($conn, $sql_delete_request);
            mysqli_stmt_bind_param($stmt_request, "i", $id_request);
            
            if (mysqli_stmt_execute($stmt_request)) {
                echo json_encode(['success' => true]);
            } else {
                echo json_encode(['success' => false, 'message' => 'Gagal menghapus request']);
            }
            exit();
        }
        
        // Get filtered request data
        if ($_POST['action'] === 'get_requests') {
            $filter_tanggal = isset($_POST['tanggal']) ? $_POST['tanggal'] : '';
            
            $sql_request = "SELECT
                id_request,
                bahan_request.kode_request,
                bahan_request.tanggal_request,
                CONCAT(pegawai.jabatan,'-',pegawai.nama_lengkap) as nama_lengkap,
                grand_total as grand_total
                FROM
                bahan_request
                INNER JOIN
                pegawai
                ON
                bahan_request.id_user = pegawai.id_user 
                WHERE status=1";
                
            // Add date filter if specified
            if (!empty($filter_tanggal)) {
                $sql_request .= " AND DATE(tanggal_request) = '$filter_tanggal'";
            }
            
            $sql_request .= " ORDER BY id_request DESC";
            $result_request = mysqli_query($conn, $sql_request);
            
            $requests = [];
            if ($result_request && mysqli_num_rows($result_request) > 0) {
                while ($row = mysqli_fetch_assoc($result_request)) {
                    $requests[] = [
                        'id_request' => $row['id_request'],
                        'kode_request' => $row['kode_request'],
                        'tanggal_request' => date('d M Y', strtotime($row['tanggal_request'])),
                        'nama_lengkap' => $row['nama_lengkap'],
                        'grand_total' => 'Rp ' . number_format($row['grand_total'], 0, ',', '.')
                    ];
                }
            }
            
            echo json_encode(['success' => true, 'data' => $requests]);
            exit();
        }
        
        // Hapus item dari detail
        if ($_POST['action'] === 'remove_item') {
            $detail_id = intval($_POST['detail_id']);
            
            $sql = "DELETE FROM bahan_request_detail WHERE id_detail_request = ?";
            $stmt = mysqli_prepare($conn, $sql);
            mysqli_stmt_bind_param($stmt, "i", $detail_id);
            
            if (mysqli_stmt_execute($stmt)) {
                echo json_encode(['success' => true]);
            } else {
                echo json_encode(['success' => false, 'message' => 'Gagal menghapus item']);
            }
            exit();
        }
    }
}

// Get data for dropdowns
$sql_bahan = "SELECT b.id_bahan, b.nama_bahan, b.kode_bahan, kb.nama_kategori 
              FROM bahan b 
              LEFT JOIN kategori_bahan kb ON b.id_kategori = kb.id_kategori 
              ORDER BY kb.nama_kategori, b.nama_bahan";
$result_bahan = mysqli_query($conn, $sql_bahan);
$bahan_data = [];
while ($row = mysqli_fetch_assoc($result_bahan)) {
    $bahan_data[] = $row;
}

$sql_vendor = "SELECT id_vendor, kode_vendor, nama_vendor, keterangan 
               FROM vendor 
               WHERE status = '1' 
               ORDER BY nama_vendor";
$result_vendor = mysqli_query($conn, $sql_vendor);
$vendor_data = [];
while ($row = mysqli_fetch_assoc($result_vendor)) {
    $vendor_data[] = $row;
}

// Kode request now generated only when modal opens
?>

<!doctype html>
<html lang="en">
  <head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Transaksi Pembelian - Admin Dashboard</title>
    <link href="../css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css" rel="stylesheet">
    <link href="../css/admin.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    <link href="https://cdn.jsdelivr.net/npm/select2-bootstrap-5-theme@1.3.0/dist/select2-bootstrap-5-theme.min.css" rel="stylesheet" />
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
                <div class="collapse" id="masterMenu">
                  <ul class="nav flex-column ms-3">
                    <li class="nav-item"><a class="nav-link" href="resto.php"><i class="bi bi-building"></i> Resto</a></li>
                    <li class="nav-item"><a class="nav-link" href="pegawai.php"><i class="bi bi-people"></i> Pegawai</a></li>
                    <li class="nav-item"><a class="nav-link" href="konsumen.php"><i class="bi bi-person-check"></i> Konsumen</a></li>
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
                <div class="collapse show" id="pembelianMenu">
                  <ul class="nav flex-column ms-3">
                    <li class="nav-item"><a class="nav-link active" href="pembelian.php"><i class="bi bi-clipboard-check"></i> Pesanan Pembelian</a></li>
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
            <h1 class="h2">Transaksi Pembelian</h1>
            <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#modalAddRequest">
              <i class="bi bi-plus-circle"></i> Tambah Request
            </button>
          </div>
          <div>
              
            
          </div>

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

          <!-- Daftar Request / PO -->
          <div class="mb-4">
            
            <div>
              <!-- Filter Tanggal -->
              <div class="row mb-4">
                <div class="col-md-12">
                  <label class="form-label fw-semibold">Filter Tanggal:</label>
                  <div class="input-group">
                    <input type="date" class="form-control" id="filter_tanggal" value="<?php echo date('Y-m-d'); ?>">
                    <button class="btn btn-outline-primary" type="button" id="btn_filter">
                      <i class="bi bi-search"></i> Filter
                    </button>
                    <button class="btn btn-outline-secondary" type="button" id="btn_reset_filter">
                      <i class="bi bi-arrow-clockwise"></i> Reset
                    </button>
                  </div>
                </div>
                
              </div>
              
              <div class="table-responsive">
                <table class="table table-hover" id="table_requests">
                  <thead class="table-dark">
                    <tr>
                                            <th>No.</th>
                      <th>Kode Request</th>
                      <th>Tanggal</th>
                      <th>Pegawai</th>
                      <th>Grand Total</th>
                      <th>Aksi</th>
                    </tr>
                  </thead>
                  <tbody id="tbody_requests">
                    <!-- Data akan dimuat via AJAX -->
                  </tbody>
                </table>
              </div>
            </div>
          </div>
          
        </main>
      </div>
    </div>

    <!-- Modal Tambah Request -->
    <div class="modal fade" id="modalAddRequest" tabindex="-1" aria-labelledby="modalAddRequestLabel" aria-hidden="true">
      <div class="modal-dialog modal-xl">
        <div class="modal-content">
          <div class="modal-header">
            <h5 class="modal-title" id="modalAddRequestLabel">Tambah Request Pembelian Bahan</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
          </div>
          <div class="modal-body">
            <!-- Header Info -->
            <div class="row mb-4">
              <div class="col-md-6">
                <div class="mb-3">
                  <label class="form-label"><strong>Kode Request:</strong></label>
                  <input type="text" class="form-control" id="modal_kode_request" value="" readonly>
                </div>
              </div>
              <div class="col-md-6">
                <div class="mb-3">
                  <label class="form-label"><strong>Tanggal:</strong></label>
                  <input type="text" class="form-control" value="<?php echo date('d F Y'); ?>" readonly>
                </div>
              </div>
            </div>

            <!-- Form Add Item -->
            <div class="card mb-4">
              <div class="card-header bg-light">
                <h6 class="mb-0">Tambah Item</h6>
              </div>
              <div class="card-body">
                <!-- Baris 1: Pilihan Utama -->
                <div class="row mb-3">
                  <div class="col-md-4">
                    <label class="form-label fw-semibold">Bahan <span class="text-danger">*</span></label>
                    <select class="form-select" id="modal_select_bahan" required>
                      <option value="">Pilih Bahan</option>
                      <?php foreach ($bahan_data as $row): ?>
                      <option value="<?php echo $row['id_bahan']; ?>" 
                              data-kode="<?php echo $row['kode_bahan']; ?>"
                              data-kategori="<?php echo $row['nama_kategori']; ?>">
                        <?php echo $row['kode_bahan'] . ' - ' . $row['nama_bahan'] . ' (' . $row['nama_kategori'] . ')'; ?>
                      </option>
                      <?php endforeach; ?>
                    </select>
                  </div>
                  <div class="col-md-4">
                    <label class="form-label fw-semibold">Vendor <span class="text-danger">*</span></label>
                    <select class="form-select" id="modal_select_vendor" required>
                      <option value="">Pilih Vendor</option>
                      <?php foreach ($vendor_data as $row): ?>
                      <option value="<?php echo $row['id_vendor']; ?>" 
                              data-kode="<?php echo $row['kode_vendor']; ?>"
                              data-keterangan="<?php echo $row['keterangan']; ?>">
                        <?php echo $row['kode_vendor'] . ' - ' . $row['nama_vendor'] . ' (' . $row['keterangan'] . ')'; ?>
                      </option>
                      <?php endforeach; ?>
                    </select>
                  </div>
                  <div class="col-md-4">
                    <label class="form-label fw-semibold">Satuan <span class="text-danger">*</span></label>
                    <select class="form-select" id="modal_select_satuan" required disabled>
                      <option value="">Pilih Satuan</option>
                    </select>
                  </div>
                </div>
                
                <!-- Baris 2: Harga & Jumlah -->
                <div class="row mb-3">
                  <div class="col-md-3">
                    <label class="form-label fw-semibold">Harga Estimasi</label>
                    <div class="input-group">
                      <span class="input-group-text">Rp</span>
                      <input type="number" class="form-control" id="modal_harga_est" step="0.01" readonly>
                    </div>
                  </div>
                  <div class="col-md-3">
                    <label class="form-label fw-semibold">Jumlah <span class="text-danger">*</span></label>
                    <input type="number" class="form-control" id="modal_jumlah_request" min="1" placeholder="Masukkan jumlah" required>
                  </div>
                  <div class="col-md-3">
                    <label class="form-label fw-semibold">Subtotal</label>
                    <div class="input-group">
                      <span class="input-group-text">Rp</span>
                      <input type="text" class="form-control" id="modal_subtotal_display" readonly>
                    </div>
                  </div>
                  <div class="col-md-3">
                    <label class="form-label fw-semibold">Tipe Pembayaran <span class="text-danger">*</span></label>
                    <select class="form-select" id="modal_tipe_pembayaran">
                      <option value="invoice">Invoice</option>
                      <option value="bayar_langsung">Bayar Langsung</option>
                    </select>
                  </div>
                </div>
                
                <!-- Baris 3: Nomor Bukti & Tombol -->
                <div class="row mb-3">
                  <div class="col-md-6">
                    <label class="form-label fw-semibold">Nomor Bukti Transaksi</label>
                    <input type="text" class="form-control" id="modal_nomor_bukti" placeholder="Opsional - diisi nanti">
                    <small class="text-muted">Dapat diisi setelah transaksi atau dikosongkan</small>
                  </div>
                  <div class="col-md-6 d-flex align-items-end">
                    <button type="button" class="btn btn-primary btn-lg px-4" id="modal_btn_add_item">
                      <i class="bi bi-plus-circle"></i> Tambah ke Daftar
                    </button>
                  </div>
                </div>
              </div>
            </div>

            <!-- Tabel Detail Modal -->
            <div class="table-responsive mb-4">
              <table class="table table-striped table-hover" id="modal_table_detail">
                <thead class="table-dark">
                  <tr>
                    <th width="5%">No</th>
                    <th width="20%">Bahan</th>
                    <th width="20%">Vendor</th>
                    <th width="10%">Satuan</th>
                    <th width="10%">Harga Est.</th>
                    <th width="8%">Qty</th>
                    <th width="12%">Subtotal</th>
                    <th width="10%">Status</th>
                    <th width="5%">Aksi</th>
                  </tr>
                </thead>
                <tbody>
                  <tr>
                    <td colspan="9" class="text-center text-muted py-4">
                      Belum ada item yang ditambahkan
                    </td>
                  </tr>
                </tbody>
              </table>
            </div>

            <!-- Grand Total Modal -->
            <div class="row">
              <div class="col-md-8">
                <!-- Empty space -->
              </div>
              <div class="col-md-4">
                <div class="card bg-light">
                  <div class="card-body">
                    <div class="d-flex justify-content-between">
                      <strong>Grand Total:</strong>
                      <strong class="text-primary" id="modal_grand_total_display">Rp 0</strong>
                    </div>
                  </div>
                </div>
                
                <!-- Hidden fields -->
                <input type="hidden" id="modal_subtotal" value="0">
              </div>
            </div>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
              <i class="bi bi-x-circle"></i> Tutup
            </button>
            <button type="button" class="btn btn-success" id="modal_btn_save" disabled>
              <i class="bi bi-save"></i> Simpan Transaksi
            </button>
          </div>
        </div>
      </div>
    </div>

    <script src="../js/bootstrap.bundle.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
    
    <script>
    $(document).ready(function() {
        let modalDetailItems = [];
        let modalGrandTotal = 0;
        let modalCurrentRequestId = null;
        
        // Format rupiah function to remove .00 for whole numbers
        function formatRupiah(amount) {
            const num = parseFloat(amount);
            if (num % 1 === 0) {
                // Whole number, format without decimals
                return num.toLocaleString('id-ID');
            } else {
                // Has decimals, format with 2 decimal places
                return num.toLocaleString('id-ID', {minimumFractionDigits: 2, maximumFractionDigits: 2});
            }
        }
        
        // === REQUEST LIST FUNCTIONALITY ===
        
        // Load requests data
        function loadRequests(tanggal = '') {
            $.ajax({
                url: window.location.href,
                type: 'POST',
                data: {
                    action: 'get_requests',
                    tanggal: tanggal
                },
                dataType: 'json',
                success: function(response) {
                    if (response.success) {
                        renderRequestsTable(response.data);
                    } else {
                        $('#tbody_requests').html('<tr><td colspan="6" class="text-center text-muted">Error loading data</td></tr>');
                    }
                },
                error: function() {
                    $('#tbody_requests').html('<tr><td colspan="6" class="text-center text-muted">Error loading data</td></tr>');
                }
            });
        }
        
        // Render requests table
        function renderRequestsTable(requests) {
            const tbody = $('#tbody_requests');
            tbody.empty();
            
            if (requests.length === 0) {
                tbody.append('<tr><td colspan="6" class="text-center text-muted">Tidak ada data request pembelian</td></tr>');
                return;
            }
            
            requests.forEach(function(request, index) {
                tbody.append(`
                    <tr>
                        <td>${index + 1}</td>
                        <td>${request.kode_request}</td>
                        <td>${request.tanggal_request}</td>
                        <td>${request.nama_lengkap}</td>
                        <td>${request.grand_total}</td>
                        <td>
                            <a href="pembelian_detail.php?id=${request.id_request}" class="btn btn-sm btn-info">
                                <i class="bi bi-eye"></i> Detail
                            </a>
                        </td>
                    </tr>
                `);
            });
        }
        
        // Filter button click
        $('#btn_filter').on('click', function() {
            const tanggal = $('#filter_tanggal').val();
            loadRequests(tanggal);
        });
        
        // Reset filter button click
        $('#btn_reset_filter').on('click', function() {
            $('#filter_tanggal').val('');
            loadRequests('');
        });
        
        // Filter on enter key
        $('#filter_tanggal').on('keypress', function(e) {
            if (e.which === 13) {
                const tanggal = $(this).val();
                loadRequests(tanggal);
            }
        });
        
        // Load initial data (today)
        loadRequests($('#filter_tanggal').val());
        
        // === MODAL FUNCTIONALITY ===
        
        // Initialize modal Select2 when modal opens
        $('#modalAddRequest').on('shown.bs.modal', function() {
            // Initialize Select2 for modal elements
            $('#modal_select_bahan, #modal_select_vendor').select2({
                theme: 'bootstrap-5',
                width: '100%',
                dropdownParent: $('#modalAddRequest')
            });
            
            // Generate new request code for modal
            $.ajax({
                url: window.location.href,
                type: 'POST', 
                data: {
                    action: 'get_new_kode_request'
                },
                dataType: 'json',
                success: function(response) {
                    if (response.success) {
                        $('#modal_kode_request').val(response.kode_request);
                    }
                }
            });
            
            // Create new request for modal
            createModalRequest();
        });
        
        // Clear modal on close
        $('#modalAddRequest').on('hidden.bs.modal', function() {
            // Delete incomplete request if it exists
            if (modalCurrentRequestId && modalDetailItems.length === 0) {
                $.ajax({
                    url: window.location.href,
                    type: 'POST',
                    data: {
                        action: 'delete_request',
                        id_request: modalCurrentRequestId
                    },
                    dataType: 'json',
                    success: function(response) {
                        console.log('Empty request deleted:', modalCurrentRequestId);
                    },
                    error: function() {
                        console.log('Failed to delete empty request');
                    }
                });
            }
            
            clearModalForm();
            modalDetailItems = [];
            modalCurrentRequestId = null;
            renderModalTable();
            updateModalGrandTotal();
        });
        
        function createModalRequest() {
            $.ajax({
                url: window.location.href,
                type: 'POST',
                data: {
                    action: 'create_request',
                    kode_request: $('#modal_kode_request').val()
                },
                dataType: 'json',
                success: function(response) {
                    if (response.success) {
                        modalCurrentRequestId = response.id_request;
                        console.log('Modal Request created with ID:', modalCurrentRequestId);
                    } else {
                        alert('Gagal membuat request: ' + response.message);
                    }
                },
                error: function() {
                    alert('Error membuat request');
                }
            });
        }
        
        // Modal bahan change handler
        $('#modal_select_bahan').on('change', function() {
            const idBahan = $(this).val();
            $('#modal_select_satuan').prop('disabled', true).html('<option value="">Loading...</option>');
            $('#modal_harga_est').val('');
            
            if (idBahan) {
                $.ajax({
                    url: 'ajax/get_satuan_bahan.php',
                    type: 'POST',
                    data: {id_bahan: idBahan},
                    dataType: 'json',
                    success: function(response) {
                        $('#modal_select_satuan').prop('disabled', false).html('<option value="">Pilih Satuan</option>');
                        if (response.success && response.data.length > 0) {
                            $.each(response.data, function(index, item) {
                                $('#modal_select_satuan').append(`<option value="${item.satuan}">${item.satuan}</option>`);
                            });
                            initializeModalSatuanSelect2();
                        }
                    },
                    error: function() {
                        $('#modal_select_satuan').prop('disabled', false).html('<option value="">Error loading data</option>');
                    }
                });
            } else {
                $('#modal_select_satuan').prop('disabled', true).html('<option value="">Pilih Satuan</option>');
            }
        });
        
        function initializeModalSatuanSelect2() {
            if (!$('#modal_select_satuan').hasClass('select2-hidden-accessible')) {
                $('#modal_select_satuan').select2({
                    theme: 'bootstrap-5',
                    width: '100%',
                    dropdownParent: $('#modalAddRequest')
                });
            }
        }
        
        // Modal satuan change handler
        $('#modal_select_satuan').on('change', function() {
            const idBahan = $('#modal_select_bahan').val();
            const satuan = $(this).val();
            
            if (idBahan && satuan) {
                $.ajax({
                    url: 'ajax/get_harga_bahan.php',
                    type: 'POST', 
                    data: {id_bahan: idBahan, satuan: satuan},
                    dataType: 'json',
                    success: function(response) {
                        if (response.success) {
                            $('#modal_harga_est').val(response.harga_satuan);
                            $('#modal_harga_est').data('id_bahan_biaya', response.id_bahan_biaya);
                            calculateModalSubtotal();
                        } else {
                            $('#modal_harga_est').val(0);
                            $('#modal_harga_est').data('id_bahan_biaya', null);
                        }
                    },
                    error: function() {
                        $('#modal_harga_est').val(0);
                    }
                });
            }
        });
        
        // Modal quantity change handler
        $('#modal_jumlah_request').on('input', function() {
            calculateModalSubtotal();
        });
        
        function calculateModalSubtotal() {
            const harga = parseFloat($('#modal_harga_est').val()) || 0;
            const qty = parseInt($('#modal_jumlah_request').val()) || 0;
            const subtotal = harga * qty;
            $('#modal_subtotal').val(subtotal.toFixed(2));
            $('#modal_subtotal_display').val(formatRupiah(subtotal));
        }
        
        // Modal add item handler
        $('#modal_btn_add_item').on('click', function() {
            const idBahan = $('#modal_select_bahan').val();
            const idVendor = $('#modal_select_vendor').val();
            const satuan = $('#modal_select_satuan').val();
            const hargaEst = parseFloat($('#modal_harga_est').val()) || 0;
            const jumlah = parseInt($('#modal_jumlah_request').val()) || 0;
            const subtotal = parseFloat($('#modal_subtotal').val()) || 0;
            const tipePembayaran = $('#modal_tipe_pembayaran').val();
            const nomorBukti = $('#modal_nomor_bukti').val();
            const idBahanBiaya = $('#modal_harga_est').data('id_bahan_biaya');
            
            // Validation
            if (!idBahan || !idVendor || !satuan || jumlah <= 0) {
                alert('Mohon lengkapi semua field yang wajib diisi!');
                return;
            }
            
            if (!modalCurrentRequestId) {
                alert('Request belum dibuat. Silakan tutup dan buka modal kembali.');
                return;
            }
            
            // Insert item ke database
            $.ajax({
                url: window.location.href,
                type: 'POST',
                data: {
                    action: 'add_item',
                    id_request: modalCurrentRequestId,
                    id_bahan: idBahan,
                    id_vendor: idVendor,
                    jumlah_request: jumlah,
                    harga_est: hargaEst,
                    subtotal: subtotal,
                    tipe_pembayaran: tipePembayaran,
                    nomor_bukti_transaksi: nomorBukti,
                    id_bahan_biaya: idBahanBiaya
                },
                dataType: 'json',
                success: function(response) {
                    if (response.success) {
                        // Get display text
                        const namaBahan = $('#modal_select_bahan option:selected').text();
                        const namaVendor = $('#modal_select_vendor option:selected').text();
                        const isDone = tipePembayaran === 'bayar_langsung' ? '1' : '0';
                        const statusText = tipePembayaran === 'bayar_langsung' ? 'Bayar Langsung' : 'Invoice';
                        
                        // Add to array untuk display
                        const item = {
                            detail_id: response.detail_id,
                            id_bahan: idBahan,
                            id_vendor: idVendor,
                            satuan: satuan,
                            harga_est: hargaEst,
                            jumlah_request: jumlah,
                            subtotal: subtotal,
                            isDone: isDone,
                            isInvoice: '1',
                            nomor_bukti_transaksi: nomorBukti,
                            nama_bahan: namaBahan,
                            nama_vendor: namaVendor,
                            status_text: statusText
                        };
                        
                        modalDetailItems.push(item);
                        renderModalTable();
                        clearModalForm();
                        updateModalGrandTotal();
                    } else {
                        alert('Gagal menambah item: ' + response.message);
                    }
                },
                error: function() {
                    alert('Error menambah item');
                }
            });
        });
        
        function renderModalTable() {
            const tbody = $('#modal_table_detail tbody');
            tbody.empty();
            
            if (modalDetailItems.length === 0) {
                tbody.append(`
                    <tr>
                        <td colspan="9" class="text-center text-muted py-4">
                            Belum ada item yang ditambahkan
                        </td>
                    </tr>
                `);
                return;
            }
            
            $.each(modalDetailItems, function(index, item) {
                tbody.append(`
                    <tr>
                        <td>${index + 1}</td>
                        <td>${item.nama_bahan}</td>
                        <td>${item.nama_vendor}</td>
                        <td>${item.satuan}</td>
                        <td>Rp ${formatRupiah(parseFloat(item.harga_est))}</td>
                        <td>${item.jumlah_request}</td>
                        <td>Rp ${formatRupiah(parseFloat(item.subtotal))}</td>
                        <td><span class="badge ${item.isDone === '1' ? 'bg-success' : 'bg-warning'}">${item.status_text}</span></td>
                        <td>
                            <button type="button" class="btn btn-sm btn-danger" onclick="removeModalItem(${index})">
                                <i class="bi bi-trash"></i>
                            </button>
                        </td>
                    </tr>
                `);
            });
        }
        
        function clearModalForm() {
            $('#modal_select_bahan').val('').trigger('change');
            $('#modal_select_vendor').val('').trigger('change');
            $('#modal_select_satuan').prop('disabled', true).html('<option value="">Pilih Satuan</option>');
            $('#modal_harga_est').val('');
            $('#modal_jumlah_request').val('');
            $('#modal_subtotal').val('');
            $('#modal_subtotal_display').val('');
            $('#modal_nomor_bukti').val('');
        }
        
        function updateModalGrandTotal() {
            modalGrandTotal = modalDetailItems.reduce((sum, item) => sum + parseFloat(item.subtotal), 0);
            $('#modal_grand_total_display').text('Rp ' + formatRupiah(modalGrandTotal));
            $('#modal_btn_save').prop('disabled', modalDetailItems.length === 0);
        }
        
        // Remove modal item function (global)
        window.removeModalItem = function(index) {
            if (confirm('Yakin ingin menghapus item ini?')) {
                const item = modalDetailItems[index];
                
                $.ajax({
                    url: window.location.href,
                    type: 'POST',
                    data: {
                        action: 'remove_item',
                        detail_id: item.detail_id
                    },
                    dataType: 'json',
                    success: function(response) {
                        if (response.success) {
                            modalDetailItems.splice(index, 1);
                            renderModalTable();
                            updateModalGrandTotal();
                        } else {
                            alert('Gagal menghapus item: ' + response.message);
                        }
                    },
                    error: function() {
                        alert('Error menghapus item');
                    }
                });
            }
        };
        
        // Modal save transaction handler
        $('#modal_btn_save').on('click', function() {
            if (modalDetailItems.length === 0) {
                alert('Tidak ada item untuk disimpan!');
                return;
            }
            
            if (!modalCurrentRequestId) {
                alert('Request belum dibuat.');
                return;
            }
            
            if (confirm('Simpan transaksi pembelian ini?')) {
                $.ajax({
                    url: window.location.href,
                    type: 'POST',
                    data: {
                        action: 'save_request',
                        id_request: modalCurrentRequestId,
                        grand_total: modalGrandTotal
                    },
                    success: function(response) {
                        $('#modalAddRequest').modal('hide');
                        // Refresh table instead of whole page
                        loadRequests($('#filter_tanggal').val());
                        alert('Transaksi pembelian berhasil disimpan!');
                    },
                    error: function() {
                        alert('Error menyimpan transaksi');
                    }
                });
            }
        });
    });
    </script>
  </body>
</html>