<?php
session_start();
require_once __DIR__ . '/../../config/koneksi.php';

// Check if user is logged in
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    header('Location: login.php');
    exit();
}

// Ambil tanggal dari parameter GET
$tgl = isset($_GET['tgl']) ? $_GET['tgl'] : date('Y-m-d');

// Query 1: Ringkasan kuantitas (8 hari)
$sql1 = "SELECT
    view_produk.kode_produk,
    view_produk.nama_produk,
    COALESCE(SUM(CASE WHEN DATE(produk_sell.tgl_release) = '$tgl' - INTERVAL 7 DAY THEN produk_sell.stok_awal END), 0) AS stok_1,
    COALESCE(SUM(CASE WHEN DATE(produk_sell.tgl_release) = '$tgl' - INTERVAL 7 DAY THEN pesanan_detail.qty END), 0) AS terjual_1,
    COALESCE(SUM(CASE WHEN DATE(produk_sell.tgl_release) = '$tgl' - INTERVAL 6 DAY THEN produk_sell.stok_awal END), 0) AS stok_2,
    COALESCE(SUM(CASE WHEN DATE(produk_sell.tgl_release) = '$tgl' - INTERVAL 6 DAY THEN pesanan_detail.qty END), 0) AS terjual_2,
    COALESCE(SUM(CASE WHEN DATE(produk_sell.tgl_release) = '$tgl' - INTERVAL 5 DAY THEN produk_sell.stok_awal END), 0) AS stok_3,
    COALESCE(SUM(CASE WHEN DATE(produk_sell.tgl_release) = '$tgl' - INTERVAL 5 DAY THEN pesanan_detail.qty END), 0) AS terjual_3,
    COALESCE(SUM(CASE WHEN DATE(produk_sell.tgl_release) = '$tgl' - INTERVAL 4 DAY THEN produk_sell.stok_awal END), 0) AS stok_4,
    COALESCE(SUM(CASE WHEN DATE(produk_sell.tgl_release) = '$tgl' - INTERVAL 4 DAY THEN pesanan_detail.qty END), 0) AS terjual_4,
    COALESCE(SUM(CASE WHEN DATE(produk_sell.tgl_release) = '$tgl' - INTERVAL 3 DAY THEN produk_sell.stok_awal END), 0) AS stok_5,
    COALESCE(SUM(CASE WHEN DATE(produk_sell.tgl_release) = '$tgl' - INTERVAL 3 DAY THEN pesanan_detail.qty END), 0) AS terjual_5,
    COALESCE(SUM(CASE WHEN DATE(produk_sell.tgl_release) = '$tgl' - INTERVAL 2 DAY THEN produk_sell.stok_awal END), 0) AS stok_6,
    COALESCE(SUM(CASE WHEN DATE(produk_sell.tgl_release) = '$tgl' - INTERVAL 2 DAY THEN pesanan_detail.qty END), 0) AS terjual_6,
    COALESCE(SUM(CASE WHEN DATE(produk_sell.tgl_release) = '$tgl' - INTERVAL 1 DAY THEN produk_sell.stok_awal END), 0) AS stok_7,
    COALESCE(SUM(CASE WHEN DATE(produk_sell.tgl_release) = '$tgl' - INTERVAL 1 DAY THEN pesanan_detail.qty END), 0) AS terjual_7,
    COALESCE(SUM(CASE WHEN DATE(produk_sell.tgl_release) = '$tgl' THEN produk_sell.stok_awal END), 0) AS stok_8,
    COALESCE(SUM(CASE WHEN DATE(produk_sell.tgl_release) = '$tgl' THEN pesanan_detail.qty END), 0) AS terjual_8
FROM
    view_produk
LEFT JOIN produk_sell ON produk_sell.id_produk = view_produk.id_produk
LEFT JOIN pesanan_detail ON pesanan_detail.id_produk_sell = produk_sell.id_produk_sell
WHERE
    DATE(produk_sell.tgl_release) BETWEEN '$tgl' - INTERVAL 7 DAY AND '$tgl'
GROUP BY
    view_produk.kode_produk, view_produk.nama_produk
ORDER BY
    view_produk.nama_produk";

$result1 = mysqli_query($conn, $sql1);
$data1 = mysqli_fetch_all($result1, MYSQLI_ASSOC);

// Query 2: Pertumbuhan penjualan
$sql2 = "SELECT
    view_produk.kode_produk,
    view_produk.nama_produk,
    COALESCE(SUM(CASE WHEN DATE(produk_sell.tgl_release) BETWEEN '$tgl' - INTERVAL 7 DAY AND '$tgl' - INTERVAL 5 DAY THEN pesanan_detail.qty END), 0) AS total_1_3,
    COALESCE(SUM(CASE WHEN DATE(produk_sell.tgl_release) BETWEEN '$tgl' - INTERVAL 4 DAY AND '$tgl' THEN pesanan_detail.qty END), 0) AS total_4_7,
    ROUND(CASE 
        WHEN COALESCE(SUM(CASE WHEN DATE(produk_sell.tgl_release) BETWEEN '$tgl' - INTERVAL 7 DAY AND '$tgl' - INTERVAL 5 DAY THEN pesanan_detail.qty END), 0) = 0 THEN NULL
        ELSE (
            (COALESCE(SUM(CASE WHEN DATE(produk_sell.tgl_release) BETWEEN '$tgl' - INTERVAL 4 DAY AND '$tgl' THEN pesanan_detail.qty END), 0) -
            COALESCE(SUM(CASE WHEN DATE(produk_sell.tgl_release) BETWEEN '$tgl' - INTERVAL 7 DAY AND '$tgl' - INTERVAL 5 DAY THEN pesanan_detail.qty END), 0)) * 100.0
        ) / NULLIF(COALESCE(SUM(CASE WHEN DATE(produk_sell.tgl_release) BETWEEN '$tgl' - INTERVAL 7 DAY AND '$tgl' - INTERVAL 5 DAY THEN pesanan_detail.qty END), 0), 0)
    END, 2) AS pertumbuhan_persen,
    CASE
        WHEN SUM(CASE WHEN DATE(produk_sell.tgl_release) BETWEEN '$tgl' - INTERVAL 4 DAY AND '$tgl' THEN pesanan_detail.qty END) >
             SUM(CASE WHEN DATE(produk_sell.tgl_release) BETWEEN '$tgl' - INTERVAL 7 DAY AND '$tgl' - INTERVAL 5 DAY THEN pesanan_detail.qty END)
        THEN 'Naik'
        WHEN SUM(CASE WHEN DATE(produk_sell.tgl_release) BETWEEN '$tgl' - INTERVAL 4 DAY AND '$tgl' THEN pesanan_detail.qty END) <
             SUM(CASE WHEN DATE(produk_sell.tgl_release) BETWEEN '$tgl' - INTERVAL 7 DAY AND '$tgl' - INTERVAL 5 DAY THEN pesanan_detail.qty END)
        THEN 'Turun'
        ELSE 'Stabil'
    END AS tren
FROM
    view_produk
LEFT JOIN produk_sell ON produk_sell.id_produk = view_produk.id_produk
LEFT JOIN pesanan_detail ON pesanan_detail.id_produk_sell = produk_sell.id_produk_sell
WHERE
    produk_sell.tgl_release BETWEEN '$tgl' - INTERVAL 7 DAY AND '$tgl'
GROUP BY
    view_produk.kode_produk, view_produk.nama_produk
ORDER BY
    view_produk.nama_produk";

$result2 = mysqli_query($conn, $sql2);
$data2 = mysqli_fetch_all($result2, MYSQLI_ASSOC);
?>

<!doctype html>
<html lang="en">
  <head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Laporan Kuantitas - Admin Dashboard</title>
    <link href="../css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css" rel="stylesheet">
    <link href="../css/admin.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
    <style>
        table th, table td {
            vertical-align: middle !important;
            font-size: 14px;
        }
        .table thead th {
            background-color: rgb(196, 223, 255);
            text-align: center;
        }
        .table-sm th, .table-sm td {
            padding: 0.25rem;
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
                <div class="collapse show" id="laporanMenu">
                  <ul class="nav flex-column ms-3">
                    <li class="nav-item"><a class="nav-link" href="laporan_transaksi.php"><i class="bi bi-list-ul"></i> Transaksi</a></li>
                    <li class="nav-item"><a class="nav-link" href="laporan_pengeluaran.php"><i class="bi bi-bar-chart"></i> Pengeluaran vs Penjualan</a></li>
                    <li class="nav-item"><a class="nav-link active" href="laporan_kuantitas.php"><i class="bi bi-pie-chart"></i> Kuantitas</a></li>
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
            <h1 class="h2">Laporan Kuantitas</h1>
          </div>

          <!-- Date Filter -->
          <div class="mb-4">
            <form method="GET" class="row g-3 align-items-end">
              <div class="col-md-4">
                <label for="tgl" class="form-label">Pilih Tanggal</label>
                <input type="date" class="form-control" id="tgl" name="tgl" 
                       value="<?php echo htmlspecialchars($tgl); ?>" required>
                
              </div>
              <div class="col-md-4">
                <button type="submit" class="btn btn-primary">
                  <i class="bi bi-search"></i> Tampilkan
                </button>
              </div>
            </form>
          </div>

          <!-- Ringkasan Kuantitas -->
          <div class="mb-5">
            <div>
               <h3 class="text-left mb-0">RINGKASAN KUANTITAS</h3>
            </div>
            <div>
              <div>
                <table class="table table-bordered table-sm text-center">
                  <thead class="table-light">
                    <tr>
                      <th rowspan="2" class="align-middle">No</th>
                      <th rowspan="2" class="align-middle">Nama Produk</th>
                      <?php for ($i = 7; $i >= 0; $i--): ?>
                        <th colspan="2"><?php echo date('d-m', strtotime("$tgl -$i day")); ?></th>
                      <?php endfor; ?>
                    </tr>
                    <tr>
                      <?php for ($i = 0; $i < 8; $i++): ?>
                        <th>A</th>
                        <th>B</th>
                      <?php endfor; ?>
                    </tr>
                  </thead>
                  <tbody>
                    <?php if (empty($data1)): ?>
                    <tr>
                      <td colspan="18" class="text-center text-muted py-3">Tidak ada data untuk periode ini</td>
                    </tr>
                    <?php else: ?>
                    <?php $no = 1; foreach ($data1 as $row): ?>
                      <tr>
                        <td><?php echo $no++; ?></td>
                        <td class="text-start"><?php echo htmlspecialchars($row['nama_produk']); ?></td>
                        <?php for ($i = 1; $i <= 8; $i++): ?>
                          <td><?php echo $row['stok_'.$i]; ?></td>
                          <td><strong><?php echo $row['terjual_'.$i]; ?></strong></td>
                        <?php endfor; ?>
                      </tr>
                    <?php endforeach; ?>
                    <?php endif; ?>
                  </tbody>
                </table>
                <small class="text-muted">A = Stok Awal, B = Terjual</small>
              </div>
            </div>
          </div>

          <!-- Pertumbuhan Penjualan -->
          <div>
            <div>
              <h4 class="text-left mb-0">PERTUMBUHAN PENJUALAN</h4>
            </div>
            <div>
              <div>
                <table class="table table-bordered table-sm text-center">
                  <thead class="table-light">
                    <tr>
                      <th>No</th>
                      <th>Kode</th>
                      <th>Nama Produk</th>
                      <th>Total: <?php echo date("d M", strtotime("$tgl -7 day")); ?> – <?php echo date("d M", strtotime("$tgl -5 day")); ?></th>
                      <th>Total: <?php echo date("d M", strtotime("$tgl -4 day")); ?> – <?php echo date("d M", strtotime($tgl)); ?></th>
                      <th>Sell Growth</th>
                      <th>Tren</th>
                    </tr>
                  </thead>
                  <tbody>
                    <?php if (empty($data2)): ?>
                    <tr>
                      <td colspan="7" class="text-center text-muted py-3">Tidak ada data untuk periode ini</td>
                    </tr>
                    <?php else: ?>
                    <?php $no = 1; foreach ($data2 as $row): ?>
                      <tr>
                        <td><?php echo $no++; ?></td>
                        <td><?php echo htmlspecialchars($row['kode_produk']); ?></td>
                        <td class="text-start"><?php echo htmlspecialchars($row['nama_produk']); ?></td>
                        <td><?php echo $row['total_1_3']; ?></td>
                        <td><?php echo $row['total_4_7']; ?></td>
                        <td>
                          <?php 
                          if ($row['pertumbuhan_persen'] === null) {
                            echo '<span class="text-muted">-</span>';
                          } else {
                            $persen = $row['pertumbuhan_persen'];
                            $color = $persen > 0 ? 'text-success' : ($persen < 0 ? 'text-danger' : 'text-muted');
                            echo '<span class="' . $color . '">' . $persen . '%</span>';
                          }
                          ?>
                        </td>
                        <td>
                          <?php 
                          $tren = $row['tren'];
                          $badge = '';
                          if ($tren === 'Naik') {
                            $badge = 'bg-success';
                          } elseif ($tren === 'Turun') {
                            $badge = 'bg-danger';
                          } else {
                            $badge = 'bg-secondary';
                          }
                          ?>
                          <span class="badge <?php echo $badge; ?>"><?php echo $tren; ?></span>
                        </td>
                      </tr>
                    <?php endforeach; ?>
                    <?php endif; ?>
                  </tbody>
                </table>
                <div class="text-end mt-3">
                  <small class="text-muted">Export time: <?php echo date('d F Y H:i'); ?></small>
                </div>
              </div>
            </div>
          </div>

        </main>
      </div>
    </div>

    <script src="../js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
    <script>
      // Initialize date picker
      flatpickr("#tgl", {
        dateFormat: "Y-m-d",
        maxDate: "today",
        defaultDate: "<?php echo $tgl; ?>",
        locale: {
          firstDayOfWeek: 1
        }
      });
    </script>
  </body>
</html>