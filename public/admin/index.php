<?php
session_start();

if(!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true){
    header("location: login.php");
    exit;
}

require_once '../../config/koneksi.php';
?>
<!doctype html>
<html lang="id" data-bs-theme="light">
  <head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Dashboard - Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    <link href="https://cdn.jsdelivr.net/npm/select2-bootstrap-5-theme@1.3.0/dist/select2-bootstrap-5-theme.min.css" rel="stylesheet" />
    <link href="../css/newadmin.css" rel="stylesheet">
  </head>
  <body>
    <?php include '_header_new.php'; ?>
    
    <?php include '_sidebar_new.php'; ?>

    <main class="main-content" id="mainContent">
        <div class="container-fluid">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h2 class="mb-1">Dashboard</h2>
                    <p class="text-muted mb-0">Selamat datang kembali, <?php echo htmlspecialchars($_SESSION["nama_lengkap"]); ?>!</p>
                </div>
            </div>

            <!-- Stats Cards -->
            <div class="row g-4 mb-4">
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
                    <div class="card-stat">
                        <div class="d-flex align-items-center">
                            <div class="stat-icon" style="background: linear-gradient(135deg, #28a745 0%, #20c997 100%);">
                                <i class="bi bi-receipt"></i>
                            </div>
                            <div class="ms-3 flex-grow-1">
                                <h6 class="text-muted mb-1">Invoice Belum Checkout</h6>
                                <h3 class="text-success">Rp <?php echo number_format($total_invoice / 1000, 1); ?>rb</h3>
                                <small class="text-muted">Hari ini</small>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-lg-4 col-md-6">
                    <div class="card-stat">
                        <div class="d-flex align-items-center">
                            <div class="stat-icon" style="background: linear-gradient(135deg, #007bff 0%, #0056b3 100%);">
                                <i class="bi bi-cash-coin"></i>
                            </div>
                            <div class="ms-3 flex-grow-1">
                                <h6 class="text-muted mb-1">Total Transaksi</h6>
                                <h3 class="text-primary">Rp <?php echo number_format($total_transaksi / 1000, 1); ?>rb</h3>
                                <small class="text-success">
                                    <i class="bi bi-arrow-up"></i> Hari ini
                                </small>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-lg-4 col-md-6">
                    <div class="card-stat">
                        <div class="d-flex align-items-center">
                            <div class="stat-icon" style="background: linear-gradient(135deg, var(--danger-color) 0%, #dc3545 100%);">
                                <i class="bi bi-x-circle"></i>
                            </div>
                            <div class="ms-3 flex-grow-1">
                                <h6 class="text-muted mb-1">Total Pembatalan</h6>
                                <h3 class="text-danger-custom">Rp <?php echo number_format($total_nilai_batal / 1000, 1); ?>rb</h3>
                                <small class="text-danger-custom">
                                    <i class="bi bi-exclamation-circle"></i> <?php echo $jumlah_batal; ?> item
                                </small>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Informasi Terbaru -->
            <div class="row mb-4">
                <div class="col-12">
                    <h4 class="mb-3">Informasi Terbaru</h4>
                </div>
            </div>

            <div class="row g-4 mb-4">
                <?php
                $sql = "SELECT id_info, judul, isi, divisi, gambar, link, pegawai.nama_lengkap, created_time, CASE WHEN TIMESTAMPDIFF(HOUR, created_time, NOW()) < 8 THEN CASE WHEN TIMESTAMPDIFF(HOUR, created_time, NOW()) < 1 THEN CONCAT(TIMESTAMPDIFF(MINUTE, created_time, NOW()), ' menit yang lalu') WHEN TIMESTAMPDIFF(HOUR, created_time, NOW()) = 1 THEN '1 jam yang lalu' ELSE CONCAT(TIMESTAMPDIFF(HOUR, created_time, NOW()), ' jam yang lalu') END ELSE DATE_FORMAT(created_time,'%d %M %Y %H:%i') END AS waktu_tampil FROM informasi INNER JOIN pegawai ON id_users = pegawai.id_user ORDER BY created_time desc LIMIT 3";
                $result = $conn->query($sql);
                if ($result->num_rows > 0) {
                    while($row = $result->fetch_assoc()) {
                ?>
                <div class="col-lg-4 col-md-6">
                    <div class="card-modern h-100">
                        <?php if (!empty($row["gambar"])) { ?>
                        <img src="../images/info/<?php echo htmlspecialchars($row["gambar"]); ?>" class="card-img-top img-fluid" alt="..." style="height: 200px; object-fit: cover;">
                        <?php } ?>
                        <div class="card-body">
                            <h5 class="card-title"><?php echo htmlspecialchars($row["judul"]); ?></h5>
                            <p class="card-text"><?php echo nl2br(htmlspecialchars(substr($row["isi"], 0, 150))); ?>...</p>
                            <?php if (!empty($row["link"])) { ?>
                            <a href="<?php echo htmlspecialchars($row["link"]); ?>" class="btn btn-primary btn-sm">Selengkapnya</a>
                            <?php } ?>
                        </div>
                        <div class="card-footer bg-light">
                            <small class="text-muted">Oleh <?php echo htmlspecialchars($row["nama_lengkap"]); ?> - <?php echo htmlspecialchars($row["waktu_tampil"]); ?></small>
                        </div>
                    </div>
                </div>
                <?php
                    }
                } else {
                ?>
                <div class="col-12">
                    <p class="text-center text-muted">Tidak ada informasi terbaru.</p>
                </div>
                <?php
                }
                ?>
            </div>

            <!-- Aktivitas Terkini -->
            <div class="row mb-4">
                <div class="col-12">
                    <h4 class="mb-3">Aktivitas Terkini</h4>
                </div>
            </div>

            <div class="row g-4">
                <!-- Makanan -->
                <div class="col-lg-4 col-md-6">
                    <div class="card-modern">
                        <div class="card-header">
                            <i class="bi bi-cup-hot me-2"></i>Makanan
                        </div>
                        <div class="card-body p-0">
                            <?php
                            $sql_makanan = "SELECT DATE_FORMAT(TIME(dapur_order_detail.tgl_update),'%H:%i') as waktu, produk_menu.nama_produk, produk_menu.kode_produk, pesanan_detail.ta_dinein, pesanan_detail.qty, produk_sell.harga_jual, kategori_menu.nama_kategori FROM dapur_order_detail INNER JOIN pesanan_detail ON dapur_order_detail.id_pesanan_detail = pesanan_detail.id_pesanan_detail INNER JOIN produk_sell ON pesanan_detail.id_produk_sell = produk_sell.id_produk_sell INNER JOIN produk_menu ON produk_sell.id_produk = produk_menu.id_produk INNER JOIN kategori_menu ON produk_menu.id_kategori = kategori_menu.id_kategori WHERE dapur_order_detail.ready >=0 and DATE(tgl_update) = CURDATE() and dapur_order_detail.ready >=2 and kategori_menu.nama_kategori = 'Makanan' ORDER BY id_order_detail desc limit 5";
                            $result_makanan = $conn->query($sql_makanan);
                            if ($result_makanan && $result_makanan->num_rows > 0) {
                                while($row = $result_makanan->fetch_assoc()) {
                            ?>
                            <div class="d-flex align-items-center p-3 border-bottom">
                                <div style="width: 50px; height: 50px; border-radius: 8px; overflow: hidden; flex-shrink: 0;">
                                    <?php
                                    $image_path = '../images/' . htmlspecialchars($row['kode_produk']) . '.jpg';
                                    if (file_exists($image_path)) {
                                        echo '<img src="' . $image_path . '" class="img-fluid" alt="..." style="width: 100%; height: 100%; object-fit: cover;">';
                                    } else {
                                        echo '<div class="bg-secondary d-flex align-items-center justify-content-center h-100"><i class="bi bi-image text-white"></i></div>';
                                    }
                                    ?>
                                </div>
                                <div class="ms-3 flex-grow-1">
                                    <h6 class="mb-0"><?php echo htmlspecialchars($row['nama_produk']); ?></h6>
                                    <small class="text-muted"><?php echo htmlspecialchars($row['waktu']); ?> - Qty: <?php echo htmlspecialchars($row['qty']); ?></small>
                                </div>
                            </div>
                            <?php
                                }
                            } else {
                                echo '<p class="text-center text-muted p-3">Tidak ada data makanan.</p>';
                            }
                            ?>
                        </div>
                    </div>
                </div>

                <!-- Minuman -->
                <div class="col-lg-4 col-md-6">
                    <div class="card-modern">
                        <div class="card-header">
                            <i class="bi bi-cup-straw me-2"></i>Minuman
                        </div>
                        <div class="card-body p-0">
                            <?php
                            $sql_minuman = "SELECT DATE_FORMAT(TIME(dapur_order_detail.tgl_update),'%H:%i') as waktu, produk_menu.nama_produk, produk_menu.kode_produk, pesanan_detail.ta_dinein, pesanan_detail.qty, produk_sell.harga_jual, kategori_menu.nama_kategori FROM dapur_order_detail INNER JOIN pesanan_detail ON dapur_order_detail.id_pesanan_detail = pesanan_detail.id_pesanan_detail INNER JOIN produk_sell ON pesanan_detail.id_produk_sell = produk_sell.id_produk_sell INNER JOIN produk_menu ON produk_sell.id_produk = produk_menu.id_produk INNER JOIN kategori_menu ON produk_menu.id_kategori = kategori_menu.id_kategori WHERE dapur_order_detail.ready >=0 and DATE(tgl_update) = CURDATE() and dapur_order_detail.ready >=2 and kategori_menu.nama_kategori = 'Minuman' ORDER BY id_order_detail desc limit 5";
                            $result_minuman = $conn->query($sql_minuman);
                            if ($result_minuman && $result_minuman->num_rows > 0) {
                                while($row = $result_minuman->fetch_assoc()) {
                            ?>
                            <div class="d-flex align-items-center p-3 border-bottom">
                                <div style="width: 50px; height: 50px; border-radius: 8px; overflow: hidden; flex-shrink: 0;">
                                    <?php
                                    $image_path = '../images/' . htmlspecialchars($row['kode_produk']) . '.jpg';
                                    if (file_exists($image_path)) {
                                        echo '<img src="' . $image_path . '" class="img-fluid" alt="..." style="width: 100%; height: 100%; object-fit: cover;">';
                                    } else {
                                        echo '<div class="bg-secondary d-flex align-items-center justify-content-center h-100"><i class="bi bi-image text-white"></i></div>';
                                    }
                                    ?>
                                </div>
                                <div class="ms-3 flex-grow-1">
                                    <h6 class="mb-0"><?php echo htmlspecialchars($row['nama_produk']); ?></h6>
                                    <small class="text-muted"><?php echo htmlspecialchars($row['waktu']); ?> - Qty: <?php echo htmlspecialchars($row['qty']); ?></small>
                                </div>
                            </div>
                            <?php
                                }
                            } else {
                                echo '<p class="text-center text-muted p-3">Tidak ada data minuman.</p>';
                            }
                            ?>
                        </div>
                    </div>
                </div>

                <!-- Pembatalan -->
                <div class="col-lg-4 col-md-6">
                    <div class="card-modern">
                        <div class="card-header">
                            <i class="bi bi-x-circle me-2"></i>Pembatalan
                        </div>
                        <div class="card-body p-0">
                            <?php
                            $sql_batal_list = "SELECT DATE_FORMAT( TIME ( dapur_order_detail.tgl_update ), '%H:%i' ) AS waktu, produk_menu.nama_produk, produk_menu.kode_produk, pesanan_detail.ta_dinein, produk_sell.harga_jual, kategori_menu.nama_kategori FROM dapur_order_detail INNER JOIN pesanan_detail ON dapur_order_detail.id_pesanan_detail = pesanan_detail.id_pesanan_detail INNER JOIN produk_sell ON pesanan_detail.id_produk_sell = produk_sell.id_produk_sell INNER JOIN produk_menu ON produk_sell.id_produk = produk_menu.id_produk INNER JOIN kategori_menu ON produk_menu.id_kategori = kategori_menu.id_kategori WHERE DATE ( tgl_update ) = CURDATE() AND dapur_order_detail.ready = 3 ORDER BY id_order_detail DESC limit 5";
                            $result_batal_list = $conn->query($sql_batal_list);
                            if ($result_batal_list && $result_batal_list->num_rows > 0) {
                                while($row = $result_batal_list->fetch_assoc()) {
                            ?>
                            <div class="d-flex align-items-center p-3 border-bottom">
                                <div style="width: 50px; height: 50px; border-radius: 8px; overflow: hidden; flex-shrink: 0;">
                                    <?php
                                    $image_path = '../images/' . htmlspecialchars($row['kode_produk']) . '.jpg';
                                    if (file_exists($image_path)) {
                                        echo '<img src="' . $image_path . '" class="img-fluid" alt="..." style="width: 100%; height: 100%; object-fit: cover;">';
                                    } else {
                                        echo '<div class="bg-secondary d-flex align-items-center justify-content-center h-100"><i class="bi bi-image text-white"></i></div>';
                                    }
                                    ?>
                                </div>
                                <div class="ms-3 flex-grow-1">
                                    <h6 class="mb-0"><?php echo htmlspecialchars($row['nama_produk']); ?></h6>
                                    <small class="text-muted"><?php echo htmlspecialchars($row['waktu']); ?> - Rp <?php echo number_format($row['harga_jual'], 0, ',', '.'); ?></small>
                                </div>
                            </div>
                            <?php
                                }
                            } else {
                                echo '<p class="text-center text-muted p-3">Tidak ada data pembatalan.</p>';
                            }
                            $conn->close();
                            ?>
                        </div>
                    </div>
                </div>
            </div>

        </div>
    </main>

    <?php include '_scripts_new.php'; ?>
  </body>
</html>
