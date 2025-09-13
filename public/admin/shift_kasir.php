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
$selected_date = date('Y-m-d'); // Default to today
$shift_data = [];

// Handle POST actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'create') {
        $id_user = $_POST['id_pegawai'] ?? '';
        $first_cash_drawer = $_POST['cash_awal'] ?? 0;
        $tanggal_open = $_POST['tanggal'] ?? date('Y-m-d');
        $id_user2 = $_SESSION['user_id'] ?? 1; // ID user yang login
        
        // Check for duplicate
        $check_query = "SELECT id_open FROM state_open_closing WHERE DATE(tanggal_open) = ? AND id_user = ?";
        $check_stmt = $conn->prepare($check_query);
        $check_stmt->bind_param("si", $tanggal_open, $id_user);
        $check_stmt->execute();
        $result = $check_stmt->get_result();
        $exists = $result->num_rows > 0;
        
        if ($exists) {
            $_SESSION['error'] = 'Kasir sudah memiliki shift pada tanggal tersebut!';
        } else {
            // Create full datetime
            $jam_sekarang = date('H:i:s');
            $tanggal_open_full = $tanggal_open . ' ' . $jam_sekarang;
            
            $insert_query = "INSERT INTO state_open_closing (tanggal_open, first_cash_drawer, id_user, id_user2) VALUES (?, ?, ?, ?)";
            $insert_stmt = $conn->prepare($insert_query);
            $insert_stmt->bind_param("sdii", $tanggal_open_full, $first_cash_drawer, $id_user, $id_user2);
            
            if ($insert_stmt->execute()) {
                $_SESSION['success'] = 'Shift berhasil ditambahkan!';
            } else {
                $_SESSION['error'] = 'Gagal menambahkan shift: ' . $conn->error;
            }
        }
        
        header('Location: shift_kasir.php');
        exit();
    }
}

// Get kasir list for dropdown
$kasir_query = "SELECT id_user, nama_lengkap, jabatan FROM pegawai WHERE aktif = '1' AND (jabatan = 'kasir' OR jabatan = 'pramusaji') ORDER BY nama_lengkap ASC";
$kasir_result = $conn->query($kasir_query);
$kasir_list = $kasir_result->fetch_all(MYSQLI_ASSOC);

// Handle date filter
if (isset($_GET['tanggal']) && !empty($_GET['tanggal'])) {
    $selected_date = $_GET['tanggal'];
}

// Get shift data based on selected date
try {
    $sql = "SELECT
        state_open_closing.id_open, 
        DATE_FORMAT(state_open_closing.tanggal_open,'%d %M %Y %H:%i') as tanggal_open,
        FORMAT((state_open_closing.first_cash_drawer - state_open_closing.manual_total_cash ),0) as cash_awal,
        FORMAT(state_open_closing.total_qris,0) as qris, 
        FORMAT(state_open_closing.manual_total_bank,0) as transfer, 
        FORMAT(state_open_closing.manual_total_cash,0) as cash, 
        FORMAT(
            (state_open_closing.total_qris + 
             state_open_closing.manual_total_bank + 
             state_open_closing.manual_total_cash + 
             (state_open_closing.first_cash_drawer - state_open_closing.manual_total_cash)), 0
        ) AS grand_total,
        state_open_closing.id_user, 
        CONCAT('[',pegawai.jabatan,'] ',pegawai.nama_lengkap) as kasir,
        state_open_closing.status,
        state_open_closing.total_qris as qris_raw,
        state_open_closing.manual_total_bank as transfer_raw,
        state_open_closing.manual_total_cash as cash_raw
    FROM
        state_open_closing
        INNER JOIN
        pegawai
        ON 
            state_open_closing.id_user = pegawai.id_user 
    WHERE DATE(tanggal_open) = ?
    ORDER BY state_open_closing.tanggal_open DESC";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $selected_date);
    $stmt->execute();
    $result = $stmt->get_result();
    $shift_data = $result->fetch_all(MYSQLI_ASSOC);
} catch (Exception $e) {
    $error = 'Error mengambil data: ' . $e->getMessage();
}
?>

<!doctype html>
<html lang="en">
  <head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Shift Kasir - Admin Dashboard</title>
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
                    <li class="nav-item"><a class="nav-link active" href="shift_kasir.php"><i class="bi bi-clock"></i> Shift Kasir</a></li>
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
              <li class="nav-item">
                <a class="nav-link" data-bs-toggle="collapse" href="#penjualanMenuMobile" role="button">
                  <i class="bi bi-cash-stack"></i>
                  <span>Penjualan</span>
                  <i class="bi bi-chevron-down ms-auto"></i>
                </a>
                <div class="collapse show" id="penjualanMenuMobile">
                  <ul class="nav flex-column ms-3">
                    <li class="nav-item"><a class="nav-link active" href="shift_kasir.php"><i class="bi bi-clock"></i> Shift Kasir</a></li>
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
                  <i class="bi bi-boxes"></i>
                  <span>Inventory</span>
                  <i class="bi bi-chevron-down ms-auto"></i>
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
                  <i class="bi bi-graph-up"></i>
                  <span>Laporan</span>
                  <i class="bi bi-chevron-down ms-auto"></i>
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
            <h1 class="h2">Shift Kasir</h1>
            <div class="btn-toolbar mb-2 mb-md-0">
              <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#shiftModal">
                <i class="bi bi-plus-circle me-2"></i>Tambah Shift
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
            <div class="col-md-4">
              <form method="GET" class="d-flex">
                <input type="date" class="form-control me-2" name="tanggal" value="<?php echo $selected_date; ?>">
                <button type="submit" class="btn btn-outline-primary">
                  <i class="bi bi-search"></i>
                </button>
              
              </form>
            </div>
           
          </div>

          <!-- Shift Data Table -->
          
          <div class="table-responsive shadow-sm">
            <table class="table table-hover align-middle">
              <thead class="table-dark">
                <tr>
                  <th class="text-center">No</th>
                  <th>Nama Kasir</th>
                  <th class="text-center">Status</th>
                  <th class="text-end">Tunai Awal</th>
                  <th class="text-end">Grand Total</th>
                </tr>
              </thead>
              <tbody>
                <?php if (empty($shift_data)): ?>
                  <tr>
                    <td colspan="5" class="text-center text-muted py-5">
                      <i class="bi bi-inbox display-1 text-muted d-block mb-3"></i>
                      <span class="fs-5">Tidak ada data shift untuk tanggal <?php echo date('d F Y', strtotime($selected_date)); ?></span>
                    </td>
                  </tr>
                <?php else: ?>
                  <?php foreach ($shift_data as $index => $shift): ?>
                    <tr onclick="showDetail(<?php echo $shift['id_open']; ?>, '<?php echo addslashes($shift['kasir']); ?>', '<?php echo $shift['cash']; ?>', '<?php echo $shift['qris']; ?>', '<?php echo $shift['transfer']; ?>')" data-bs-toggle="modal" data-bs-target="#detailModal" style="cursor: pointer;">
                      <td class="text-center fw-bold"><?php echo $index + 1; ?></td>
                      <td class="fw-semibold"><?php echo htmlspecialchars($shift['kasir']); ?></td>
                      <td class="text-center">
                        <?php if ($shift['status'] == '1'): ?>
                          <span class="badge bg-success fs-6 px-3 py-2">
                            <i class="bi bi-check-circle me-1"></i>Open
                          </span>
                        <?php else: ?>
                          <span class="badge bg-secondary fs-6 px-3 py-2">
                            <i class="bi bi-lock me-1"></i>Closed
                          </span>
                        <?php endif; ?>
                      </td>
                      <td class="text-end fw-semibold text-primary">Rp <?php echo $shift['cash_awal']; ?></td>
                      <td class="text-end fw-bold text-success fs-5">Rp <?php echo $shift['grand_total']; ?></td>
                    </tr>
                  <?php endforeach; ?>
                <?php endif; ?>
              </tbody>
            </table>
          </div>
          
          <!-- Grand Total Summary -->
          <?php if (!empty($shift_data)): ?>
            <?php 
              $total_grand = 0;
              foreach ($shift_data as $shift) {
                $total_grand += (int)str_replace(',', '', $shift['grand_total']);
              }
            ?>
            <div class="mt-3 p-3 bg-light rounded border">
              <div class="row align-items-center">
                <div class="col-md-8">
                  <span class="text-muted">Total Grand Total (<?php echo count($shift_data); ?> shift)</span>
                </div>
                <div class="col-md-4 text-end">
                  <span class="fw-bold fs-4 text-success">Rp <?php echo number_format($total_grand, 0, ',', '.'); ?></span>
                </div>
              </div>
            </div>
          <?php endif; ?>
        </main>
      </div>
    </div>

    <!-- Detail Modal -->
    <div class="modal fade" id="detailModal" tabindex="-1">
        <div class="modal-dialog modal-md">
            <div class="modal-content border-0 shadow-lg">
                <div class="modal-header border-0 pb-2">
                    <h5 class="modal-title fw-bold text-dark d-flex align-items-center">
                        <i class="bi bi-info-circle-fill text-primary me-2"></i>
                        Detail Shift Kasir
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body px-4 py-3">
                    <!-- Kasir Info -->
                    <div class="mb-4 p-3 bg-primary bg-opacity-10 rounded-3">
                        <div class="d-flex align-items-center">
                            <div class="bg-primary bg-opacity-20 rounded-circle p-2 me-3">
                                <i class="bi bi-person-fill text-primary fs-5"></i>
                            </div>
                            <div>
                                <small class="text-muted d-block mb-1">Nama Kasir</small>
                                <span class="fw-bold text-dark" id="detail-kasir"></span>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Payment Details -->
                    <div class="row g-3">
                        <div class="col-4">
                            <div class="text-center p-3 bg-success bg-opacity-10 rounded-3 border border-success border-opacity-25">
                                <i class="bi bi-cash-stack text-success fs-3 mb-2 d-block"></i>
                                <small class="text-muted d-block mb-1 fw-medium">Tunai</small>
                                <div class="fw-bold text-success fs-6" id="detail-tunai"></div>
                            </div>
                        </div>
                        <div class="col-4">
                            <div class="text-center p-3 bg-info bg-opacity-10 rounded-3 border border-info border-opacity-25">
                                <i class="bi bi-qr-code text-info fs-3 mb-2 d-block"></i>
                                <small class="text-muted d-block mb-1 fw-medium">QRIS</small>
                                <div class="fw-bold text-info fs-6" id="detail-qris"></div>
                            </div>
                        </div>
                        <div class="col-4">
                            <div class="text-center p-3 bg-warning bg-opacity-10 rounded-3 border border-warning border-opacity-25">
                                <i class="bi bi-credit-card text-warning fs-3 mb-2 d-block"></i>
                                <small class="text-muted d-block mb-1 fw-medium">Transfer</small>
                                <div class="fw-bold text-warning fs-6" id="detail-transfer"></div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer border-0 pt-2 pb-3">
                    <button type="button" class="btn btn-outline-secondary px-4" data-bs-dismiss="modal">
                        <i class="bi bi-x-circle me-1"></i>
                        Tutup
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Add Shift Modal -->
    <div class="modal fade" id="shiftModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title"><i class="bi bi-plus-circle me-2"></i>Tambah Shift Kasir</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="create">
                        <div class="mb-3">
                             <label for="id_pegawai" class="form-label">Kasir <span class="text-danger">*</span></label>
                             <div class="searchable-select" style="position: relative;">
                                 <input type="text" class="form-control" id="kasir_display" placeholder="Pilih kasir..." readonly onclick="toggleKasirDropdown()" style="padding-right: 2.5rem;">
                                 <input type="hidden" name="id_pegawai" id="id_pegawai" required>
                                 <div class="dropdown-menu" id="kasir_dropdown" style="display: none; position: absolute; width: 100%; max-height: 200px; overflow-y: auto; border: 1px solid #ced4da; border-radius: 0.375rem; background: white; box-shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.075); z-index: 1060; top: 100%;">
                                     <input type="text" class="search-input form-control" id="kasir_search" placeholder="Cari kasir..." onkeyup="filterKasir()" style="margin: 0.5rem; width: calc(100% - 1rem);">
                                     <?php foreach ($kasir_list as $kasir): ?>
                                     <div class="dropdown-item" data-value="<?php echo $kasir['id_user']; ?>" data-text="<?php echo htmlspecialchars($kasir['nama_lengkap']); ?>" onclick="selectKasir(this)" style="padding: 0.5rem 1rem; cursor: pointer; border-bottom: 1px solid #f8f9fa;">
                                         <?php echo htmlspecialchars($kasir['nama_lengkap']); ?>
                                     </div>
                                     <?php endforeach; ?>
                                 </div>
                             </div>
                         </div>
                         <div class="mb-3">
                             <label for="cash_awal" class="form-label">Modal Awal (Rp) <span class="text-danger">*</span></label>
                             <input type="number" class="form-control" id="cash_awal" name="cash_awal" min="0" step="1000" required>
                         </div>
                         <div class="mb-3">
                             <label for="tanggal" class="form-label">Tanggal <span class="text-danger">*</span></label>
                             <input type="date" class="form-control" id="tanggal" name="tanggal" value="<?php echo date('Y-m-d'); ?>" required>
                         </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                            <i class="bi bi-x-circle me-2"></i>Batal
                        </button>
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-check-circle me-2"></i>Simpan
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Bootstrap JS -->
    <script src="../js/bootstrap.bundle.min.js"></script>
    
    <!-- JavaScript Functions -->
    <script>
        function showDetail(id, kasir, tunai, qris, transfer) {
            document.getElementById('detail-kasir').textContent = kasir;
            document.getElementById('detail-tunai').textContent = 'Rp ' + tunai;
            document.getElementById('detail-qris').textContent = 'Rp ' + qris;
            document.getElementById('detail-transfer').textContent = 'Rp ' + transfer;
        }
        
        // Searchable dropdown functionality for kasir
        function toggleKasirDropdown() {
            const dropdown = document.getElementById('kasir_dropdown');
            const isVisible = dropdown.style.display === 'block';
            dropdown.style.display = isVisible ? 'none' : 'block';
            
            if (!isVisible) {
                document.getElementById('kasir_search').value = '';
                filterKasir();
                document.getElementById('kasir_search').focus();
            }
        }
        
        function selectKasir(element) {
            const value = element.getAttribute('data-value');
            const text = element.getAttribute('data-text');
            
            document.getElementById('id_pegawai').value = value;
            document.getElementById('kasir_display').value = text;
            document.getElementById('kasir_dropdown').style.display = 'none';
        }
        
        function filterKasir() {
            const searchValue = document.getElementById('kasir_search').value.toLowerCase();
            const items = document.querySelectorAll('#kasir_dropdown .dropdown-item');
            
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
            const dropdown = document.getElementById('kasir_dropdown');
            const displayInput = document.getElementById('kasir_display');
            const searchInput = document.getElementById('kasir_search');
            
            if (dropdown && !dropdown.contains(event.target) && event.target !== displayInput && event.target !== searchInput) {
                dropdown.style.display = 'none';
            }
        });
        
        // Auto-submit form when date changes
        document.getElementById('tanggal').addEventListener('change', function() {
            this.form.submit();
        });
        
        // Set date picker limits (yesterday to 2 days ahead)
        document.addEventListener('DOMContentLoaded', function() {
            const dateInput = document.getElementById('tanggal');
            const today = new Date();
            
            // Yesterday
            const yesterday = new Date(today);
            yesterday.setDate(today.getDate() - 1);
            
            // 2 days ahead
            const twoDaysAhead = new Date(today);
            twoDaysAhead.setDate(today.getDate() + 2);
            
            // Format dates to YYYY-MM-DD
            const formatDate = (date) => {
                return date.getFullYear() + '-' + 
                       String(date.getMonth() + 1).padStart(2, '0') + '-' + 
                       String(date.getDate()).padStart(2, '0');
            };
            
            dateInput.min = formatDate(yesterday);
            dateInput.max = formatDate(twoDaysAhead);
        });
    </script>
</body>
</html>