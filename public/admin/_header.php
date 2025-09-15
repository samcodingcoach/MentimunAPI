<?php
// Ambil nama aplikasi dari sesi
$nama_aplikasi = isset($_SESSION['nama_aplikasi']) ? htmlspecialchars($_SESSION['nama_aplikasi']) : 'Admin';
$nama_lengkap = htmlspecialchars($_SESSION["nama_lengkap"]);
$jabatan = htmlspecialchars($_SESSION["jabatan"]);
?>
<nav class="navbar navbar-expand-lg navbar-dark bg-primary">
    <div class="container-fluid">
        <button class="navbar-toggler" type="button" data-bs-toggle="offcanvas" data-bs-target="#sidebarOffcanvas" aria-controls="sidebarOffcanvas">
            <span class="navbar-toggler-icon"></span>
        </button>
        <a class="navbar-brand" href="#"><?php echo $nama_aplikasi; ?></a>
        <div class="navbar-nav ms-auto">
            <div class="nav-item dropdown">
                <a class="nav-link dropdown-toggle text-white" href="#" role="button" data-bs-toggle="dropdown">
                    <?php echo "$nama_lengkap ($jabatan)"; ?>
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
