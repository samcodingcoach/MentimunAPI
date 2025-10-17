<?php
$current_page = basename($_SERVER['PHP_SELF']);

// Default active state for Beranda
$beranda_active = ($current_page == 'index.php') ? 'active' : '';
$informasi_active = ($current_page == 'informasi.php') ? 'active' : '';

// Master Menu
$master_menu_pages = ['resto.php', 'pegawai.php', 'konsumen.php', 'vendor.php', 'meja.php', 'metode_pembayaran.php'];
$master_menu_show = in_array($current_page, $master_menu_pages) ? 'show' : '';
$resto_active = ($current_page == 'resto.php') ? 'active' : '';
$pegawai_active = ($current_page == 'pegawai.php') ? 'active' : '';
$konsumen_active = ($current_page == 'konsumen.php') ? 'active' : '';
$vendor_active = ($current_page == 'vendor.php') ? 'active' : '';
$meja_active = ($current_page == 'meja.php') ? 'active' : '';
$metode_pembayaran_active = ($current_page == 'metode_pembayaran.php') ? 'active' : '';

// Produk Menu
$produk_menu_pages = ['kategori_menu.php', 'menu.php', 'kategori_bahan.php', 'bahan.php', 'resep.php', 'resep_detail.php', 'harga.php'];
$produk_menu_show = in_array($current_page, $produk_menu_pages) ? 'show' : '';
$kategori_menu_active = ($current_page == 'kategori_menu.php') ? 'active' : '';
$menu_active = ($current_page == 'menu.php' || $current_page == 'harga.php') ? 'active' : '';
$kategori_bahan_active = ($current_page == 'kategori_bahan.php') ? 'active' : '';
$bahan_active = ($current_page == 'bahan.php') ? 'active' : '';
$resep_active = ($current_page == 'resep.php' || $current_page == 'resep_detail.php') ? 'active' : '';

// Pembelian Menu
$pembelian_menu_pages = ['pembelian.php', 'pembayaran_pembelian.php'];
$pembelian_menu_show = in_array($current_page, $pembelian_menu_pages) ? 'show' : '';
$pembelian_active = ($current_page == 'pembelian.php') ? 'active' : '';
$pembayaran_pembelian_active = ($current_page == 'pembayaran_pembelian.php') ? 'active' : '';

// Penjualan Menu
$penjualan_menu_pages = ['shift_kasir.php', 'promo.php', 'biaya_lain.php', 'harga_pokok_penjualan.php', 'harga_rilis.php', 'pembatalan.php'];
$penjualan_menu_show = in_array($current_page, $penjualan_menu_pages) ? 'show' : '';
$shift_kasir_active = ($current_page == 'shift_kasir.php') ? 'active' : '';
$promo_active = ($current_page == 'promo.php') ? 'active' : '';
$biaya_lain_active = ($current_page == 'biaya_lain.php') ? 'active' : '';
$harga_pokok_penjualan_active = ($current_page == 'harga_pokok_penjualan.php') ? 'active' : '';
$harga_rilis_active = ($current_page == 'harga_rilis.php') ? 'active' : '';
$pembatalan_active = ($current_page == 'pembatalan.php') ? 'active' : '';

// Inventory Menu
$inventory_menu_pages = ['inventory.php', 'transaksi_inventory.php'];
$inventory_menu_show = in_array($current_page, $inventory_menu_pages) ? 'show' : '';
$inventory_active = ($current_page == 'inventory.php') ? 'active' : '';
$transaksi_inventory_active = ($current_page == 'transaksi_inventory.php') ? 'active' : '';

// Laporan Menu
$laporan_menu_pages = ['laporan_transaksi.php', 'laporan_pengeluaran.php', 'laporan_kuantitas.php'];
$laporan_menu_show = in_array($current_page, $laporan_menu_pages) ? 'show' : '';
$laporan_transaksi_active = ($current_page == 'laporan_transaksi.php') ? 'active' : '';
$laporan_pengeluaran_active = ($current_page == 'laporan_pengeluaran.php') ? 'active' : '';
$laporan_kuantitas_active = ($current_page == 'laporan_kuantitas.php') ? 'active' : '';
?>
<aside class="sidebar" id="sidebar">
    <a href="index.php" class="menu-item <?php echo $beranda_active; ?>">
        <i class="bi bi-house-door"></i>
        <span class="menu-text">Beranda</span>
    </a>

    <a href="informasi.php" class="menu-item <?php echo $informasi_active; ?>">
        <i class="bi bi-info-circle"></i>
        <span class="menu-text">Informasi</span>
    </a>

    <?php if($_SESSION["jabatan"] == "Admin"): ?>
    <!-- Master Menu - Admin Only -->
    <div class="menu-item" data-bs-toggle="collapse" data-bs-target="#masterMenu">
        <i class="bi bi-diagram-3"></i>
        <span class="menu-text">Master</span>
        <i class="bi bi-chevron-right menu-arrow <?php echo $master_menu_show ? 'rotated' : ''; ?>"></i>
    </div>
    <div class="collapse submenu <?php echo $master_menu_show; ?>" id="masterMenu">
        <a href="resto.php" class="menu-item <?php echo $resto_active; ?>">
            <i class="bi bi-shop"></i>
            <span class="menu-text">Resto</span>
        </a>
        <a href="pegawai.php" class="menu-item <?php echo $pegawai_active; ?>">
            <i class="bi bi-people"></i>
            <span class="menu-text">Pegawai</span>
        </a>
        <a href="konsumen.php" class="menu-item <?php echo $konsumen_active; ?>">
            <i class="bi bi-person-check"></i>
            <span class="menu-text">Konsumen</span>
        </a>
        <a href="vendor.php" class="menu-item <?php echo $vendor_active; ?>">
            <i class="bi bi-truck"></i>
            <span class="menu-text">Vendor</span>
        </a>
        <a href="meja.php" class="menu-item <?php echo $meja_active; ?>">
            <i class="bi bi-table"></i>
            <span class="menu-text">Meja</span>
        </a>
        <a href="metode_pembayaran.php" class="menu-item <?php echo $metode_pembayaran_active; ?>">
            <i class="bi bi-credit-card"></i>
            <span class="menu-text">Metode Bayar</span>
        </a>
    </div>
    <?php endif; ?>

    <?php if($_SESSION["jabatan"] == "Admin" || $_SESSION["jabatan"] == "Dapur"): ?>
    <!-- Produk Menu -->
    <div class="menu-item" data-bs-toggle="collapse" data-bs-target="#produkMenu">
        <i class="bi bi-box-seam"></i>
        <span class="menu-text">Produk</span>
        <i class="bi bi-chevron-right menu-arrow <?php echo $produk_menu_show ? 'rotated' : ''; ?>"></i>
    </div>
    <div class="collapse submenu <?php echo $produk_menu_show; ?>" id="produkMenu">
        <a href="kategori_menu.php" class="menu-item <?php echo $kategori_menu_active; ?>">
            <i class="bi bi-tags"></i>
            <span class="menu-text">Kategori Menu</span>
        </a>
        <a href="menu.php" class="menu-item <?php echo $menu_active; ?>">
            <i class="bi bi-cup-straw"></i>
            <span class="menu-text">Menu</span>
        </a>
        <a href="kategori_bahan.php" class="menu-item <?php echo $kategori_bahan_active; ?>">
            <i class="bi bi-tag"></i>
            <span class="menu-text">Kategori Bahan</span>
        </a>
        <a href="bahan.php" class="menu-item <?php echo $bahan_active; ?>">
            <i class="bi bi-basket"></i>
            <span class="menu-text">Bahan</span>
        </a>
        <a href="resep.php" class="menu-item <?php echo $resep_active; ?>">
            <i class="bi bi-journal-text"></i>
            <span class="menu-text">Resep</span>
        </a>
    </div>

    <!-- Pembelian Menu -->
    <div class="menu-item" data-bs-toggle="collapse" data-bs-target="#pembelianMenu">
        <i class="bi bi-cart-plus"></i>
        <span class="menu-text">Pembelian</span>
        <i class="bi bi-chevron-right menu-arrow <?php echo $pembelian_menu_show ? 'rotated' : ''; ?>"></i>
    </div>
    <div class="collapse submenu <?php echo $pembelian_menu_show; ?>" id="pembelianMenu">
        <a href="pembelian.php" class="menu-item <?php echo $pembelian_active; ?>">
            <i class="bi bi-receipt"></i>
            <span class="menu-text">Pesanan Pembelian</span>
        </a>
        <a href="pembayaran_pembelian.php" class="menu-item <?php echo $pembayaran_pembelian_active; ?>">
            <i class="bi bi-cash-coin"></i>
            <span class="menu-text">Pembayaran</span>
        </a>
    </div>
    <?php endif; ?>

    <?php if($_SESSION["jabatan"] == "Admin" || $_SESSION["jabatan"] == "Kasir"): ?>
    <!-- Penjualan Menu -->
    <div class="menu-item" data-bs-toggle="collapse" data-bs-target="#penjualanMenu">
        <i class="bi bi-graph-up-arrow"></i>
        <span class="menu-text">Penjualan</span>
        <i class="bi bi-chevron-right menu-arrow <?php echo $penjualan_menu_show ? 'rotated' : ''; ?>"></i>
    </div>
    <div class="collapse submenu <?php echo $penjualan_menu_show; ?>" id="penjualanMenu">
        <a href="shift_kasir.php" class="menu-item <?php echo $shift_kasir_active; ?>">
            <i class="bi bi-clock-history"></i>
            <span class="menu-text">Shift Kasir</span>
        </a>
        <a href="promo.php" class="menu-item <?php echo $promo_active; ?>">
            <i class="bi bi-percent"></i>
            <span class="menu-text">Promo</span>
        </a>
        <a href="biaya_lain.php" class="menu-item <?php echo $biaya_lain_active; ?>">
            <i class="bi bi-cash-stack"></i>
            <span class="menu-text">Biaya Lain</span>
        </a>
        <a href="harga_pokok_penjualan.php" class="menu-item <?php echo $harga_pokok_penjualan_active; ?>">
            <i class="bi bi-calculator"></i>
            <span class="menu-text">Harga Pokok</span>
        </a>
        <a href="harga_rilis.php" class="menu-item <?php echo $harga_rilis_active; ?>">
            <i class="bi bi-currency-dollar"></i>
            <span class="menu-text">Harga Rilis</span>
        </a>
        <a href="pembatalan.php" class="menu-item <?php echo $pembatalan_active; ?>">
            <i class="bi bi-x-circle"></i>
            <span class="menu-text">Pembatalan</span>
        </a>
    </div>
    <?php endif; ?>

    <?php if($_SESSION["jabatan"] == "Admin" || $_SESSION["jabatan"] == "Dapur"): ?>
    <!-- Inventory Menu -->
    <div class="menu-item" data-bs-toggle="collapse" data-bs-target="#inventoriMenu">
        <i class="bi bi-boxes"></i>
        <span class="menu-text">Inventori</span>
        <i class="bi bi-chevron-right menu-arrow <?php echo $inventory_menu_show ? 'rotated' : ''; ?>"></i>
    </div>
    <div class="collapse submenu <?php echo $inventory_menu_show; ?>" id="inventoriMenu">
        <a href="inventory.php" class="menu-item <?php echo $inventory_active; ?>">
            <i class="bi bi-box"></i>
            <span class="menu-text">Inventory</span>
        </a>
        <a href="transaksi_inventory.php" class="menu-item <?php echo $transaksi_inventory_active; ?>">
            <i class="bi bi-arrow-left-right"></i>
            <span class="menu-text">Transaksi</span>
        </a>
    </div>
    <?php endif; ?>

    <!-- Laporan Menu - All Roles -->
    <div class="menu-item" data-bs-toggle="collapse" data-bs-target="#laporanMenu">
        <i class="bi bi-file-earmark-bar-graph"></i>
        <span class="menu-text">Laporan</span>
        <i class="bi bi-chevron-right menu-arrow <?php echo $laporan_menu_show ? 'rotated' : ''; ?>"></i>
    </div>
    <div class="collapse submenu <?php echo $laporan_menu_show; ?>" id="laporanMenu">
        <a href="laporan_transaksi.php" class="menu-item <?php echo $laporan_transaksi_active; ?>">
            <i class="bi bi-receipt-cutoff"></i>
            <span class="menu-text">Transaksi</span>
        </a>
        <a href="laporan_pengeluaran.php" class="menu-item <?php echo $laporan_pengeluaran_active; ?>">
            <i class="bi bi-graph-up"></i>
            <span class="menu-text">Pengeluaran vs Penjualan</span>
        </a>
        <a href="laporan_kuantitas.php" class="menu-item <?php echo $laporan_kuantitas_active; ?>">
            <i class="bi bi-calculator"></i>
            <span class="menu-text">Kuantitas</span>
        </a>
    </div>

    <a href="pengaturan.php" class="menu-item">
        <i class="bi bi-gear"></i>
        <span class="menu-text">Pengaturan</span>
    </a>
</aside>
