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
$produk_menu_pages = ['kategori_menu.php', 'menu.php', 'kategori_bahan.php', 'bahan.php', 'resep.php'];
$produk_menu_show = in_array($current_page, $produk_menu_pages) ? 'show' : '';
$kategori_menu_active = ($current_page == 'kategori_menu.php') ? 'active' : '';
$menu_active = ($current_page == 'menu.php') ? 'active' : '';
$kategori_bahan_active = ($current_page == 'kategori_bahan.php') ? 'active' : '';
$bahan_active = ($current_page == 'bahan.php') ? 'active' : '';
$resep_active = ($current_page == 'resep.php') ? 'active' : '';

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
        <div class="col-md-3 col-lg-2 d-md-block sidebar collapse" id="sidebarMenu">
          <div class="position-sticky pt-3">
            <ul class="nav flex-column">
              <li class="nav-item">
                <a class="nav-link <?php echo $beranda_active; ?>" href="index.php">
                  <i class="bi bi-house-door"></i>
                  <span>Beranda</span>
                </a>
              </li>
              <li class="nav-item">
                <a class="nav-link <?php echo $informasi_active; ?>" href="informasi.php">
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
                <div class="collapse <?php echo $master_menu_show; ?>" id="masterMenu">
                  <ul class="nav flex-column ms-3">
                    <li class="nav-item"><a class="nav-link <?php echo $resto_active; ?>" href="resto.php"><i class="bi bi-building"></i> Resto</a></li>
                    <li class="nav-item"><a class="nav-link <?php echo $pegawai_active; ?>" href="pegawai.php"><i class="bi bi-people"></i> Pegawai</a></li>
                    <li class="nav-item"><a class="nav-link <?php echo $konsumen_active; ?>" href="konsumen.php"><i class="bi bi-person-check"></i> Konsumen</a></li>
                    <li class="nav-item"><a class="nav-link <?php echo $vendor_active; ?>" href="vendor.php"><i class="bi bi-truck"></i> Vendor</a></li>
                    <li class="nav-item"><a class="nav-link <?php echo $meja_active; ?>" href="meja.php"><i class="bi bi-table"></i> Meja</a></li>
                    <li class="nav-item"><a class="nav-link <?php echo $metode_pembayaran_active; ?>" href="metode_pembayaran.php"><i class="bi bi-credit-card"></i> Metode Pembayaran</a></li>
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
                <div class="collapse <?php echo $produk_menu_show; ?>" id="produkMenu">
                  <ul class="nav flex-column ms-3">
                    <li class="nav-item"><a class="nav-link <?php echo $kategori_menu_active; ?>" href="kategori_menu.php"><i class="bi bi-tags"></i> Kategori Menu</a></li>
                    <li class="nav-item"><a class="nav-link <?php echo $menu_active; ?>" href="menu.php"><i class="bi bi-card-list"></i> Menu</a></li>
                    <li class="nav-item"><a class="nav-link <?php echo $kategori_bahan_active; ?>" href="kategori_bahan.php"><i class="bi bi-collection"></i> Kategori Bahan</a></li>
                    <li class="nav-item"><a class="nav-link <?php echo $bahan_active; ?>" href="bahan.php"><i class="bi bi-basket"></i> Bahan</a></li>
                    <li class="nav-item"><a class="nav-link <?php echo $resep_active; ?>" href="resep.php"><i class="bi bi-journal-text"></i> Resep</a></li>
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
                <div class="collapse <?php echo $pembelian_menu_show; ?>" id="pembelianMenu">
                  <ul class="nav flex-column ms-3">
                    <li class="nav-item"><a class="nav-link <?php echo $pembelian_active; ?>" href="pembelian.php"><i class="bi bi-clipboard-check"></i> Pesanan Pembelian</a></li>
                    <li class="nav-item"><a class="nav-link <?php echo $pembayaran_pembelian_active; ?>" href="pembayaran_pembelian.php"><i class="bi bi-currency-dollar"></i> Pembayaran</a></li>
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
                <div class="collapse <?php echo $penjualan_menu_show; ?>" id="penjualanMenu">
                  <ul class="nav flex-column ms-3">
                    <li class="nav-item"><a class="nav-link <?php echo $shift_kasir_active; ?>" href="shift_kasir.php"><i class="bi bi-clock"></i> Shift Kasir</a></li>
                    <li class="nav-item"><a class="nav-link <?php echo $promo_active; ?>" href="promo.php"><i class="bi bi-percent"></i> Promo</a></li>
                    <li class="nav-item"><a class="nav-link <?php echo $biaya_lain_active; ?>" href="biaya_lain.php"><i class="bi bi-receipt"></i> Biaya Lain</a></li>
                    <li class="nav-item"><a class="nav-link <?php echo $harga_pokok_penjualan_active; ?>" href="harga_pokok_penjualan.php"><i class="bi bi-calculator"></i> Harga Pokok Penjualan</a></li>
                    <li class="nav-item"><a class="nav-link <?php echo $harga_rilis_active; ?>" href="harga_rilis.php"><i class="bi bi-tag"></i> Harga Rilis</a></li>
                    <li class="nav-item"><a class="nav-link <?php echo $pembatalan_active; ?>" href="pembatalan.php"><i class="bi bi-x-circle"></i> Pembatalan</a></li>
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
                <div class="collapse <?php echo $inventory_menu_show; ?>" id="inventoryMenu">
                  <ul class="nav flex-column ms-3">
                    <li class="nav-item"><a class="nav-link <?php echo $inventory_active; ?>" href="inventory.php"><i class="bi bi-box-seam"></i> Inventory</a></li>
                    <li class="nav-item"><a class="nav-link <?php echo $transaksi_inventory_active; ?>" href="transaksi_inventory.php"><i class="bi bi-arrow-left-right"></i> Transaksi</a></li>
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
                <div class="collapse <?php echo $laporan_menu_show; ?>" id="laporanMenu">
                  <ul class="nav flex-column ms-3">
                    <li class="nav-item"><a class="nav-link <?php echo $laporan_transaksi_active; ?>" href="laporan_transaksi.php"><i class="bi bi-list-ul"></i> Transaksi</a></li>
                    <li class="nav-item"><a class="nav-link <?php echo $laporan_pengeluaran_active; ?>" href="laporan_pengeluaran.php"><i class="bi bi-bar-chart"></i> Pengeluaran vs Penjualan</a></li>
                    <li class="nav-item"><a class="nav-link <?php echo $laporan_kuantitas_active; ?>" href="laporan_kuantitas.php"><i class="bi bi-pie-chart"></i> Kuantitas</a></li>
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
