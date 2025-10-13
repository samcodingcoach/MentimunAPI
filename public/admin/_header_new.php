<?php
// Ambil nama aplikasi dari sesi
$nama_aplikasi = isset($_SESSION['nama_aplikasi']) ? htmlspecialchars($_SESSION['nama_aplikasi']) : 'RESTO ADMIN';
$nama_lengkap = htmlspecialchars($_SESSION["nama_lengkap"]);
$jabatan = htmlspecialchars($_SESSION["jabatan"]);
?>
<nav class="navbar navbar-custom fixed-top">
    <div class="container-fluid">
        <div class="d-flex align-items-center">
            <button class="btn btn-toggle me-3" id="toggleSidebar">
                <i class="bi bi-list fs-4"></i>
            </button>
            <a class="navbar-brand" href="index.php">
                <i class="bi bi-shop me-2"></i><?php echo $nama_aplikasi; ?>
            </a>
        </div>

        <div class="d-flex align-items-center gap-3">
            <button class="btn btn-link text-white position-relative" title="Notifikasi">
                <i class="bi bi-bell fs-5"></i>
                <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger">
                    3
                </span>
            </button>

            <button class="dark-mode-toggle" id="darkModeToggle" title="Toggle Dark Mode">
                <i class="bi bi-moon-stars-fill" id="darkModeIcon"></i>
            </button>

            <div class="dropdown user-dropdown">
                <button class="btn dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                    <i class="bi bi-person-circle me-2"></i>
                    <span class="d-none d-md-inline"><?php echo $nama_lengkap; ?> (<?php echo $jabatan; ?>)</span>
                </button>
                <ul class="dropdown-menu dropdown-menu-end shadow">
                    <li>
                        <a class="dropdown-item" href="profile.php">
                            <i class="bi bi-person me-2"></i> Profil Saya
                        </a>
                    </li>
                    <li>
                        <a class="dropdown-item" href="resto.php">
                            <i class="bi bi-gear me-2"></i> Pengaturan
                        </a>
                    </li>
                    <li>
                        <hr class="dropdown-divider">
                    </li>
                    <li>
                        <a class="dropdown-item text-danger-custom" href="logout.php">
                            <i class="bi bi-box-arrow-right me-2"></i> Logout
                        </a>
                    </li>
                </ul>
            </div>
        </div>
    </div>
</nav>

<div class="sidebar-overlay" id="sidebarOverlay"></div>
