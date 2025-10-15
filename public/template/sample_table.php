<!DOCTYPE html>
<html lang="id" data-bs-theme="light">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - Resto</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">

    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">

    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    <link href="https://cdn.jsdelivr.net/npm/select2-bootstrap-5-theme@1.3.0/dist/select2-bootstrap-5-theme.min.css"
        rel="stylesheet" />

    <link href="css/newadmin.css?v=3" rel="stylesheet" />
</head>

<body>
    <nav class="navbar navbar-custom fixed-top">
        <div class="container-fluid">
            <div class="d-flex align-items-center">
                <button class="btn btn-toggle me-3" id="toggleSidebar">
                    <i class="bi bi-list fs-4"></i>
                </button>
                <a class="navbar-brand" href="#">
                    <i class="bi bi-shop me-2"></i>RESTO ADMIN
                </a>
            </div>

            <div class="d-flex align-items-center gap-3">
                <button class="btn btn-link text-white position-relative">
                    <i class="bi bi-bell fs-5"></i>
                    <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger">
                        3
                    </span>
                </button>

                <button class="dark-mode-toggle" id="darkModeToggle">
                    <i class="bi bi-moon-stars-fill" id="darkModeIcon"></i>
                </button>

                <div class="dropdown user-dropdown">
                    <button class="btn dropdown-toggle" type="button" data-bs-toggle="dropdown">
                        <i class="bi bi-person-circle me-2"></i>
                        <span class="d-none d-md-inline">Admin</span>
                    </button>
                    <ul class="dropdown-menu dropdown-menu-end shadow">
                        <li>
                            <a class="dropdown-item" href="#">
                                <i class="bi bi-person me-2"></i> Profil Saya
                            </a>
                        </li>
                        <li>
                            <a class="dropdown-item" href="#">
                                <i class="bi bi-gear me-2"></i> Pengaturan
                            </a>
                        </li>
                        <li>
                            <hr class="dropdown-divider">
                        </li>
                        <li>
                            <a class="dropdown-item text-danger-custom" href="#">
                                <i class="bi bi-box-arrow-right me-2"></i> Logout
                            </a>
                        </li>
                    </ul>
                </div>
            </div>
        </div>
    </nav>

    <div class="sidebar-overlay" id="sidebarOverlay"></div>

    <aside class="sidebar" id="sidebar">
        <a href="#" class="menu-item active">
            <i class="bi bi-house-door"></i>
            <span class="menu-text">Beranda</span>
        </a>

        <a href="#" class="menu-item">
            <i class="bi bi-info-circle"></i>
            <span class="menu-text">Informasi</span>
        </a>

        <div class="menu-item" data-bs-toggle="collapse" data-bs-target="#masterMenu">
            <i class="bi bi-diagram-3"></i>
            <span class="menu-text">Master</span>
            <i class="bi bi-chevron-right menu-arrow"></i>
        </div>
        <div class="collapse submenu" id="masterMenu">
            <a href="#" class="menu-item"><i class="bi bi-shop"></i><span class="menu-text">Resto</span></a>
            <a href="#" class="menu-item"><i class="bi bi-people"></i><span class="menu-text">Pegawai</span></a>
            <a href="#" class="menu-item"><i class="bi bi-table"></i><span class="menu-text">Meja</span></a>
            <a href="#" class="menu-item"><i class="bi bi-building"></i><span class="menu-text">Vendor</span></a>
            <a href="#" class="menu-item"><i class="bi bi-credit-card"></i><span class="menu-text">Metode
                    Pembayaran</span></a>
        </div>

        <div class="menu-item" data-bs-toggle="collapse" data-bs-target="#produkMenu">
            <i class="bi bi-box-seam"></i>
            <span class="menu-text">Produk</span>
            <i class="bi bi-chevron-right menu-arrow"></i>
        </div>
        <div class="collapse submenu" id="produkMenu">
            <a href="#" class="menu-item"><i class="bi bi-tag"></i><span class="menu-text">Kategori Bahan</span></a>
            <a href="#" class="menu-item"><i class="bi bi-basket"></i><span class="menu-text">Bahan</span></a>
            <a href="#" class="menu-item"><i class="bi bi-tags"></i><span class="menu-text">Kategori Menu</span></a>
            <a href="#" class="menu-item"><i class="bi bi-cup-straw"></i><span class="menu-text">Produk Menu</span></a>
            <a href="#" class="menu-item"><i class="bi bi-journal-text"></i><span class="menu-text">Resep</span></a>
        </div>

        <div class="menu-item" data-bs-toggle="collapse" data-bs-target="#pembelianMenu">
            <i class="bi bi-cart-plus"></i>
            <span class="menu-text">Pembelian</span>
            <i class="bi bi-chevron-right menu-arrow"></i>
        </div>
        <div class="collapse submenu" id="pembelianMenu">
            <a href="#" class="menu-item"><i class="bi bi-receipt"></i><span class="menu-text">Pesanan
                    Pembelian</span></a>
            <a href="#" class="menu-item"><i class="bi bi-cash-coin"></i><span class="menu-text">Pembayaran</span></a>
        </div>

        <div class="menu-item" data-bs-toggle="collapse" data-bs-target="#penjualanMenu">
            <i class="bi bi-graph-up-arrow"></i>
            <span class="menu-text">Penjualan</span>
            <i class="bi bi-chevron-right menu-arrow"></i>
        </div>
        <div class="collapse submenu" id="penjualanMenu">
            <a href="#" class="menu-item"><i class="bi bi-percent"></i><span class="menu-text">Promo</span></a>
            <a href="#" class="menu-item"><i class="bi bi-cash-stack"></i><span class="menu-text">Biaya
                    Lainnya</span></a>
            <a href="#" class="menu-item"><i class="bi bi-clock-history"></i><span class="menu-text">Shift
                    Kasir</span></a>
            <a href="#" class="menu-item"><i class="bi bi-currency-dollar"></i><span class="menu-text">Harga
                    Rilis</span></a>
        </div>

        <div class="menu-item" data-bs-toggle="collapse" data-bs-target="#inventoriMenu">
            <i class="bi bi-boxes"></i>
            <span class="menu-text">Inventori</span>
            <i class="bi bi-chevron-right menu-arrow"></i>
        </div>
        <div class="collapse submenu" id="inventoriMenu">
            <a href="#" class="menu-item"><i class="bi bi-box"></i><span class="menu-text">Inventori</span></a>
            <a href="#" class="menu-item"><i class="bi bi-arrow-left-right"></i><span
                    class="menu-text">Transaksi</span></a>
        </div>

        <div class="menu-item" data-bs-toggle="collapse" data-bs-target="#laporanMenu">
            <i class="bi bi-file-earmark-bar-graph"></i>
            <span class="menu-text">Laporan</span>
            <i class="bi bi-chevron-right menu-arrow"></i>
        </div>
        <div class="collapse submenu" id="laporanMenu">
            <a href="#" class="menu-item"><i class="bi bi-receipt-cutoff"></i><span
                    class="menu-text">Transaksi</span></a>
            <a href="#" class="menu-item"><i class="bi bi-graph-up"></i><span class="menu-text">Pengeluaran vs
                    Penjualan</span></a>
            <a href="#" class="menu-item"><i class="bi bi-calculator"></i><span class="menu-text">Kuantitas</span></a>
        </div>

        <a href="#" class="menu-item">
            <i class="bi bi-gear"></i>
            <span class="menu-text">Pengaturan</span>
        </a>
    </aside>

    <main class="main-content" id="mainContent">
        <div class="container-fluid">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h2 class="mb-1">Dashboard</h2>
                    <p class="text-muted mb-0">Selamat datang kembali, Admin!</p>
                </div>
                <button class="btn btn-primary">
                    <i class="bi bi-plus-lg me-2"></i>Tambah Pesanan
                </button>
            </div>
            <div class="row">
                <div class="col-12">
                    <div class="card-modern">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <div class="d-flex align-items-center">
                                <i class="bi bi-table me-2"></i>
                                <span>Daftar Pesanan</span>
                            </div>
                            <div class="d-flex gap-2">
                                <div class="input-group" style="width: 250px;">
                                    <span class="input-group-text bg-white border-end-0">
                                        <i class="bi bi-search"></i>
                                    </span>
                                    <input type="text" class="form-control border-start-0 ps-0" placeholder="Cari pesanan...">
                                </div>
                                <button class="btn btn-outline-light btn-sm">
                                    <i class="bi bi-filter me-1"></i>Filter
                                </button>
                                <button class="btn btn-outline-light btn-sm">
                                    <i class="bi bi-arrow-down-up me-1"></i>Sort
                                </button>
                                <button class="btn btn-outline-light btn-sm">
                                    <i class="bi bi-download me-1"></i>Export
                                </button>
                            </div>
                        </div>
                        <div class="card-body p-0">
                            <div class="table-responsive">
                                <table class="table table-hover mb-0">
                                    <thead>
                                        <tr>
                                            <th>No. Pesanan</th>
                                            <th>Pelanggan</th>
                                            <th>Meja</th>
                                            <th>Item</th>
                                            <th>Total</th>
                                            <th>Status</th>
                                            <th>Aksi</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <tr>
                                            <td>#001234</td>
                                            <td>
                                                <div class="d-flex align-items-center">
                                                    <div class="bg-primary text-white rounded-circle d-flex align-items-center justify-content-center"
                                                        style="width: 35px; height: 35px;">
                                                        <strong>AB</strong>
                                                    </div>
                                                    <div class="ms-2">
                                                        <div class="fw-bold">Ahmad Budi</div>
                                                        <small class="text-muted">10:35 AM</small>
                                                    </div>
                                                </div>
                                            </td>
                                            <td><span class="badge bg-secondary">Meja 5</span></td>
                                            <td>Nasi Goreng, Es Teh</td>
                                            <td><strong>Rp 35.000</strong></td>
                                            <td><span class="badge bg-success">Selesai</span></td>
                                            <td>
                                                <div class="table-actions">
                                                    <button class="btn btn-sm btn-outline-primary">
                                                        <i class="bi bi-eye"></i>
                                                    </button>
                                                    <button class="btn btn-sm btn-outline-success">
                                                        <i class="bi bi-printer"></i>
                                                    </button>
                                                </div>
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                            <div class="card-footer bg-light border-top py-3 px-4">
                                <div class="d-flex justify-content-between align-items-center">
                                    <small class="text-muted">Menampilkan 1-5 dari 50 pesanan</small>
                                    <nav>
                                        <ul class="pagination pagination-sm mb-0">
                                            <li class="page-item disabled">
                                                <a class="page-link" href="#">Previous</a>
                                            </li>
                                            <li class="page-item active"><a class="page-link" href="#">1</a></li>
                                            <li class="page-item"><a class="page-link" href="#">2</a></li>
                                            <li class="page-item"><a class="page-link" href="#">3</a></li>
                                            <li class="page-item">
                                                <a class="page-link" href="#">Next</a>
                                            </li>
                                        </ul>
                                    </nav>
                                </div>
                            </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>

    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>

    <script>
        // Initialize Select2
        $(document).ready(function () {
            // Single Select with Search
            $('.select2-search').select2({
                theme: 'bootstrap-5',
                width: '100%',
                placeholder: '-- Pilih --',
                allowClear: true
            });

            // Multiple Select with Search
            $('.select2-multiple').select2({
                theme: 'bootstrap-5',
                width: '100%',
                placeholder: '-- Pilih Menu --',
                allowClear: true
            });
        });

        // Sidebar Toggle
        const sidebar = document.getElementById('sidebar');
        const mainContent = document.getElementById('mainContent');
        const toggleBtn = document.getElementById('toggleSidebar');
        const sidebarOverlay = document.getElementById('sidebarOverlay');

        toggleBtn.addEventListener('click', function () {
            if (window.innerWidth <= 768) {
                sidebar.classList.toggle('show-mobile');
                sidebarOverlay.classList.toggle('show');
            } else {
                sidebar.classList.toggle('collapsed');
                mainContent.classList.toggle('expanded');
                localStorage.setItem('sidebarCollapsed', sidebar.classList.contains('collapsed'));
            }
        });

        sidebarOverlay.addEventListener('click', function () {
            sidebar.classList.remove('show-mobile');
            sidebarOverlay.classList.remove('show');
        });

        // Rotate arrow on menu collapse
        document.querySelectorAll('[data-bs-toggle="collapse"]').forEach(function (element) {
            element.addEventListener('click', function () {
                const arrow = this.querySelector('.menu-arrow');
                if (arrow) {
                    arrow.classList.toggle('rotated');
                }
            });
        });

        // Dark Mode Toggle
        const darkModeToggle = document.getElementById('darkModeToggle');
        const darkModeIcon = document.getElementById('darkModeIcon');
        const htmlElement = document.documentElement;

        const currentTheme = localStorage.getItem('bsTheme') || 'light';
        htmlElement.setAttribute('data-bs-theme', currentTheme);

        if (currentTheme === 'dark') {
            darkModeIcon.classList.remove('bi-moon-stars-fill');
            darkModeIcon.classList.add('bi-sun-fill');
        }

        darkModeToggle.addEventListener('click', function () {
            const currentTheme = htmlElement.getAttribute('data-bs-theme');

            if (currentTheme === 'light') {
                htmlElement.setAttribute('data-bs-theme', 'dark');
                localStorage.setItem('bsTheme', 'dark');
                darkModeIcon.classList.remove('bi-moon-stars-fill');
                darkModeIcon.classList.add('bi-sun-fill');
            } else {
                htmlElement.setAttribute('data-bs-theme', 'light');
                localStorage.setItem('bsTheme', 'light');
                darkModeIcon.classList.remove('bi-sun-fill');
                darkModeIcon.classList.add('bi-moon-stars-fill');
            }
        });

        // Load sidebar state
        if (localStorage.getItem('sidebarCollapsed') === 'true' && window.innerWidth > 768) {
            sidebar.classList.add('collapsed');
            mainContent.classList.add('expanded');
        }

        // Handle window resize
        window.addEventListener('resize', function () {
            if (window.innerWidth > 768) {
                sidebar.classList.remove('show-mobile');
                sidebarOverlay.classList.remove('show');
            }
        });
    </script>
</body>

</html>