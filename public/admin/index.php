<?php
session_start();

if(!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true){
    header("location: login.php");
    exit;
    
}
?>
<!doctype html>
<html lang="en">
  <head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Admin Dashboard</title>
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
                <a class="nav-link active" href="index.php">
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
              
              <?php if($_SESSION["jabatan"] == "Admin"): ?>
              <!-- Penjualan Menu - Admin Only -->
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

        <!-- Mobile Offcanvas Sidebar -->
        <div class="offcanvas offcanvas-start" tabindex="-1" id="sidebarOffcanvas">
          <div class="offcanvas-header">
            <h5 class="offcanvas-title">Menu</h5>
            <button type="button" class="btn-close" data-bs-dismiss="offcanvas"></button>
          </div>
          <div class="offcanvas-body">
            <ul class="nav flex-column">
              <li class="nav-item">
                <a class="nav-link active" href="index.php">
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
              <li class="nav-item">
                <a class="nav-link" data-bs-toggle="collapse" href="#masterMenuMobile" role="button">
                  <i class="bi bi-gear-fill"></i>
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
                    <li class="nav-item"><a class="nav-link" href="bayar.php"><i class="bi bi-wallet2"></i> Bayar</a></li>
                  </ul>
                </div>
              </li>
              <?php endif; ?>
              
              <?php if($_SESSION["jabatan"] == "Admin" || $_SESSION["jabatan"] == "Dapur"): ?>
              <li class="nav-item">
                <a class="nav-link" data-bs-toggle="collapse" href="#produkMenuMobile" role="button">
                  <i class="bi bi-box-seam"></i>
                  <span>Produk</span>
                  <i class="bi bi-chevron-down ms-auto"></i>
                </a>
                <div class="collapse" id="produkMenuMobile">
                  <ul class="nav flex-column ms-3">
                    <li class="nav-item"><a class="nav-link" href="kategori_menu.php"><i class="bi bi-tags"></i> Kategori Menu</a></li>
                    <li class="nav-item"><a class="nav-link" href="menu.php"><i class="bi bi-card-list"></i> Menu</a></li>
                    <li class="nav-item"><a class="nav-link" href="kategori_bahan.php"><i class="bi bi-collection"></i> Kategori Bahan</a></li>
                    <li class="nav-item"><a class="nav-link" href="bahan.php"><i class="bi bi-basket"></i> Bahan</a></li>
                    <li class="nav-item"><a class="nav-link" href="resep.php"><i class="bi bi-journal-text"></i> Resep</a></li>
                  </ul>
                </div>
              </li>
              
              <li class="nav-item">
                <a class="nav-link" data-bs-toggle="collapse" href="#pembelianMenuMobile" role="button">
                  <i class="bi bi-cart-plus"></i>
                  <span>Pembelian</span>
                  <i class="bi bi-chevron-down ms-auto"></i>
                </a>
                <div class="collapse" id="pembelianMenuMobile">
                  <ul class="nav flex-column ms-3">
                    <li class="nav-item"><a class="nav-link" href="pembelian.php"><i class="bi bi-clipboard-check"></i> Pesanan Pembelian</a></li>
                    <li class="nav-item"><a class="nav-link" href="pembayaran_pembelian.php"><i class="bi bi-currency-dollar"></i> Pembayaran</a></li>
                  </ul>
                </div>
              </li>
              <?php endif; ?>
              
              <?php if($_SESSION["jabatan"] == "Admin"): ?>
              <li class="nav-item">
                <a class="nav-link" data-bs-toggle="collapse" href="#penjualanMenuMobile" role="button">
                  <i class="bi bi-cash-coin"></i>
                  <span>Penjualan</span>
                  <i class="bi bi-chevron-down ms-auto"></i>
                </a>
                <div class="collapse" id="penjualanMenuMobile">
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
              <li class="nav-item">
                <a class="nav-link" data-bs-toggle="collapse" href="#inventoryMenuMobile" role="button">
                  <i class="bi bi-boxes"></i>
                  <span>Inventory</span>
                  <i class="bi bi-chevron-down ms-auto"></i>
                </a>
                <div class="collapse" id="inventoryMenuMobile">
                  <ul class="nav flex-column ms-3">
                    <li class="nav-item"><a class="nav-link" href="#"><i class="bi bi-box-seam"></i> Inventory</a></li>
                    <li class="nav-item"><a class="nav-link" href="#"><i class="bi bi-arrow-left-right"></i> Transaksi</a></li>
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
                    <li class="nav-item"><a class="nav-link" href="#"><i class="bi bi-list-ul"></i> Transaksi</a></li>
                    <li class="nav-item"><a class="nav-link" href="#"><i class="bi bi-bar-chart"></i> Pengeluaran vs Penjualan</a></li>
                    <li class="nav-item"><a class="nav-link" href="#"><i class="bi bi-pie-chart"></i> Kuantitas</a></li>
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
            <h1 class="h2">Dashboard</h1>
          </div>
          
          <div class="row">
            <div class="col-12">
              <h5 class="card-title">Selamat Datang, <?php echo htmlspecialchars($_SESSION["nama_lengkap"]); ?>!</h5>
            </div>
          </div>

          <div class="row mt-4">
            <div class="col-12">
              <h4>Informasi Terbaru</h4>
            </div>
          </div>

          <div class="row gy-4">
            <?php
            require_once '../../config/koneksi.php';
            $sql = "SELECT id_info, judul, isi, divisi, gambar, link, pegawai.nama_lengkap, created_time, CASE WHEN TIMESTAMPDIFF(HOUR, created_time, NOW()) < 8 THEN CASE WHEN TIMESTAMPDIFF(HOUR, created_time, NOW()) < 1 THEN CONCAT(TIMESTAMPDIFF(MINUTE, created_time, NOW()), ' menit yang lalu') WHEN TIMESTAMPDIFF(HOUR, created_time, NOW()) = 1 THEN '1 jam yang lalu' ELSE CONCAT(TIMESTAMPDIFF(HOUR, created_time, NOW()), ' jam yang lalu') END ELSE DATE_FORMAT(created_time,'%d %M %Y %H:%i') END AS waktu_tampil FROM informasi INNER JOIN pegawai ON id_users = pegawai.id_user ORDER BY created_time desc LIMIT 3";
            $result = $conn->query($sql);
            if ($result->num_rows > 0) {
                while($row = $result->fetch_assoc()) {
            ?>
            <div class="col-lg-4 col-md-6">
              <div class="card h-100">
                <?php if (!empty($row["gambar"])) { ?>
                <img src="../images/info/<?php echo htmlspecialchars($row["gambar"]); ?>" class="card-img-top img-fluid rounded" alt="..." style="height: 200px; object-fit: cover;">
                <?php } ?>
                <div class="card-body">
                  <h5 class="card-title"><?php echo htmlspecialchars($row["judul"]); ?></h5>
                  <p class="card-text"><?php echo nl2br(htmlspecialchars($row["isi"])); ?></p>
                  <?php if (!empty($row["link"])) { ?>
                  <a href="<?php echo htmlspecialchars($row["link"]); ?>" class="btn btn-primary">Go somewhere</a>
                  <?php } ?>
                </div>
                <div class="card-footer">
                  <small class="text-muted">Oleh <?php echo htmlspecialchars($row["nama_lengkap"]); ?> - <?php echo htmlspecialchars($row["waktu_tampil"]); ?></small>
                </div>
              </div>
            </div>
            <?php
                }
            } else {
            ?>
            <div class="col-12">
              <p class="text-center">Tidak ada informasi terbaru.</p>
            </div>
            <?php
            }
            ?>
          </div>

          <div class="row mt-4">
            <div class="col-12">
              <h4>Ringkasan Hari Ini</h4>
            </div>
          </div>

          <div class="row gy-4">
            <?php
            $sql_invoice = "SELECT SUM(vi.total_dengan_ppn) AS total_invoice FROM pesanan INNER JOIN pegawai ON pegawai.id_user = pesanan.id_user INNER JOIN view_invoice vi ON vi.id_pesanan = pesanan.id_pesanan WHERE status_checkout = '0' AND tgl_cart = CURDATE()";
            $result_invoice = $conn->query($sql_invoice);
            $total_invoice = 0;
            if ($result_invoice && $result_invoice->num_rows > 0) {
                $row_invoice = $result_invoice->fetch_assoc();
                if ($row_invoice['total_invoice']) {
                    $total_invoice = $row_invoice['total_invoice'];
                }
            }

            $sql_transaksi = "SELECT sum(jumlah_uang) AS total_transaksi FROM proses_pembayaran WHERE DATE(tanggal_payment) = CURDATE()";
            $result_transaksi = $conn->query($sql_transaksi);
            $total_transaksi = 0;
            if ($result_transaksi && $result_transaksi->num_rows > 0) {
                $row_transaksi = $result_transaksi->fetch_assoc();
                if ($row_transaksi['total_transaksi']) {
                    $total_transaksi = $row_transaksi['total_transaksi'];
                }
            }

            $sql_batal = "SELECT COALESCE(SUM(produk_sell.harga_jual),0) AS total_nilai_batal, COUNT(*) AS jumlah_batal FROM dapur_batal INNER JOIN dapur_order_detail ON dapur_batal.id_order_detail = dapur_order_detail.id_order_detail INNER JOIN pesanan ON dapur_batal.id_pesanan = pesanan.id_pesanan INNER JOIN pesanan_detail ON dapur_order_detail.id_pesanan_detail = pesanan_detail.id_pesanan_detail INNER JOIN produk_sell ON pesanan_detail.id_produk_sell = produk_sell.id_produk_sell INNER JOIN meja ON pesanan.id_meja = meja.id_meja INNER JOIN view_produk ON produk_sell.id_produk = view_produk.id_produk INNER JOIN pegawai ON dapur_batal.id_user = pegawai.id_user WHERE DATE(waktu)=CURDATE()";
            $result_batal = $conn->query($sql_batal);
            $total_nilai_batal = 0;
            $jumlah_batal = 0;
            if ($result_batal && $result_batal->num_rows > 0) {
                $row_batal = $result_batal->fetch_assoc();
                if ($row_batal['total_nilai_batal']) {
                    $total_nilai_batal = $row_batal['total_nilai_batal'];
                }
                if ($row_batal['jumlah_batal']) {
                    $jumlah_batal = $row_batal['jumlah_batal'];
                }
            }
            ?>
            <div class="col-lg-4 col-md-6">
                <div class="card text-center">
                    <div class="card-body">
                        <i class="bi bi-receipt fs-1 text-primary"></i>
                        <h6 class="card-title mt-2">Invoice Belum Checkout</h6>
                        <p class="card-text fs-5 mb-0">Rp<?php echo number_format($total_invoice, 0, ',', '.'); ?></p>
                    </div>
                </div>
            </div>
            <div class="col-lg-4 col-md-6">
                <div class="card text-center">
                    <div class="card-body">
                        <i class="bi bi-cash-coin fs-1 text-success"></i>
                        <h6 class="card-title mt-2">Total Transaksi Hari Ini</h6>
                        <p class="card-text fs-5 mb-0">Rp<?php echo number_format($total_transaksi, 0, ',', '.'); ?></p>
                    </div>
                </div>
            </div>
            <div class="col-lg-4 col-md-6">
                <div class="card text-center">
                    <div class="card-body">
                        <i class="bi bi-x-circle fs-1 text-danger"></i>
                        <h6 class="card-title mt-2">Total Batal Hari Ini</h6>
                        <p class="card-text fs-5 mb-0">Rp<?php echo number_format($total_nilai_batal, 0, ',', '.'); ?></p>
                        <small class="text-muted"><?php echo $jumlah_batal; ?> item</small>
                    </div>
                </div>
            </div>
          </div>

          <div class="row mt-4">
            <div class="col-12">
              <h4>Aktifitas Terkini</h4>
            </div>
          </div>

          <div class="row gy-4">
            <div class="col-lg-4 col-md-6">
                <h5>Makanan</h5>
                <?php
                $sql_makanan = "SELECT DATE_FORMAT(TIME(dapur_order_detail.tgl_update),'%H:%i') as waktu, produk_menu.nama_produk, produk_menu.kode_produk, pesanan_detail.ta_dinein, pesanan_detail.qty, produk_sell.harga_jual, kategori_menu.nama_kategori FROM dapur_order_detail INNER JOIN pesanan_detail ON dapur_order_detail.id_pesanan_detail = pesanan_detail.id_pesanan_detail INNER JOIN produk_sell ON pesanan_detail.id_produk_sell = produk_sell.id_produk_sell INNER JOIN produk_menu ON produk_sell.id_produk = produk_menu.id_produk INNER JOIN kategori_menu ON produk_menu.id_kategori = kategori_menu.id_kategori WHERE dapur_order_detail.ready >=0 and DATE(tgl_update) = CURDATE() and dapur_order_detail.ready >=2 and kategori_menu.nama_kategori = 'Makanan' ORDER BY id_order_detail desc limit 15";
                $result_makanan = $conn->query($sql_makanan);
                if ($result_makanan && $result_makanan->num_rows > 0) {
                    while($row = $result_makanan->fetch_assoc()) {
                ?>
                <div class="card mb-3">
                    <div class="row g-0">
                        <div class="col-md-4">
                            <img src="../images/<?php echo htmlspecialchars($row['kode_produk']); ?>.jpg" class="img-fluid rounded-start" alt="...">
                        </div>
                        <div class="col-md-8">
                            <div class="card-body py-1">
                                <h6 class="card-title mb-0"><?php echo htmlspecialchars($row['nama_produk']); ?></h6>
                                <small class="text-muted"><?php echo htmlspecialchars($row['waktu']); ?></small>
                                <p class="card-text mb-0">Qty: <?php echo htmlspecialchars($row['qty']); ?> - <?php echo htmlspecialchars($row['ta_dinein']); ?></p>
                            </div>
                        </div>
                    </div>
                </div>
                <?php
                    }
                } else {
                    echo "<p>Tidak ada data makanan.</p>";
                }
                ?>
            </div>
            <div class="col-lg-4 col-md-6">
                <h5>Minuman</h5>
                <?php
                $sql_minuman = "SELECT DATE_FORMAT(TIME(dapur_order_detail.tgl_update),'%H:%i') as waktu, produk_menu.nama_produk, produk_menu.kode_produk, pesanan_detail.ta_dinein, pesanan_detail.qty, produk_sell.harga_jual, kategori_menu.nama_kategori FROM dapur_order_detail INNER JOIN pesanan_detail ON dapur_order_detail.id_pesanan_detail = pesanan_detail.id_pesanan_detail INNER JOIN produk_sell ON pesanan_detail.id_produk_sell = produk_sell.id_produk_sell INNER JOIN produk_menu ON produk_sell.id_produk = produk_menu.id_produk INNER JOIN kategori_menu ON produk_menu.id_kategori = kategori_menu.id_kategori WHERE dapur_order_detail.ready >=0 and DATE(tgl_update) = CURDATE() and dapur_order_detail.ready >=2 and kategori_menu.nama_kategori = 'Minuman' ORDER BY id_order_detail desc limit 15";
                $result_minuman = $conn->query($sql_minuman);
                if ($result_minuman && $result_minuman->num_rows > 0) {
                    while($row = $result_minuman->fetch_assoc()) {
                ?>
                <div class="card mb-3">
                    <div class="row g-0">
                        <div class="col-md-4">
                            <img src="../images/<?php echo htmlspecialchars($row['kode_produk']); ?>.jpg" class="img-fluid rounded-start" alt="...">
                        </div>
                        <div class="col-md-8">
                            <div class="card-body py-1">
                                <h6 class="card-title mb-0"><?php echo htmlspecialchars($row['nama_produk']); ?></h6>
                                <small class="text-muted"><?php echo htmlspecialchars($row['waktu']); ?></small>
                                <p class="card-text mb-0">Qty: <?php echo htmlspecialchars($row['qty']); ?> - <?php echo htmlspecialchars($row['ta_dinein']); ?></p>
                            </div>
                        </div>
                    </div>
                </div>
                <?php
                    }
                } else {
                    echo "<p>Tidak ada data minuman.</p>";
                }
                ?>
            </div>
            <div class="col-lg-4 col-md-6">
                <h5>Pembatalan</h5>
                <?php
                $sql_batal_list = "SELECT DATE_FORMAT(TIME(dapur_batal.waktu),'%H:%i') as waktu, produk_menu.nama_produk, produk_menu.kode_produk, pesanan_detail.ta_dinein, pesanan_detail.qty, produk_sell.harga_jual, dapur_batal.alasan FROM dapur_batal INNER JOIN dapur_order_detail ON dapur_batal.id_order_detail = dapur_order_detail.id_order_detail INNER JOIN pesanan_detail ON dapur_order_detail.id_pesanan_detail = pesanan_detail.id_pesanan_detail INNER JOIN produk_sell ON pesanan_detail.id_produk_sell = produk_sell.id_produk_sell INNER JOIN produk_menu ON produk_sell.id_produk = produk_menu.id_produk WHERE DATE(dapur_batal.waktu) = CURDATE() ORDER BY dapur_batal.id_batal desc limit 15";
                $result_batal_list = $conn->query($sql_batal_list);
                if ($result_batal_list && $result_batal_list->num_rows > 0) {
                    while($row = $result_batal_list->fetch_assoc()) {
                ?>
                <div class="card mb-3">
                    <div class="row g-0">
                        <div class="col-md-4">
                            <img src="../images/<?php echo htmlspecialchars($row['kode_produk']); ?>.jpg" class="img-fluid rounded-start" alt="...">
                        </div>
                        <div class="col-md-8">
                            <div class="card-body py-1">
                                <h6 class="card-title mb-0"><?php echo htmlspecialchars($row['nama_produk']); ?></h6>
                                <small class="text-muted"><?php echo htmlspecialchars($row['waktu']); ?></small>
                                <p class="card-text mb-0">Alasan: <?php echo htmlspecialchars($row['alasan']); ?></p>
                            </div>
                        </div>
                    </div>
                </div>
                <?php
                    }
                } else {
                    echo "<p>Tidak ada data pembatalan.</p>";
                }
                $conn->close();
                ?>
            </div>
          </div>

        </main>
      </div>
    </div>

    <script src="../js/bootstrap.bundle.min.js"></script>
  </body>
</html>