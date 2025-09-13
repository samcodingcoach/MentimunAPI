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

// Get current user data
$stmt = $conn->prepare("SELECT * FROM pegawai WHERE id_user = ?");
$stmt->bind_param("i", $_SESSION['id_user']);
$stmt->execute();
$result = $stmt->get_result();
$current_user = $result->fetch_assoc();

if (!$current_user) {
    header('Location: logout.php');
    exit();
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $nama_lengkap = trim($_POST['nama_lengkap']);
    $nomor_hp = trim($_POST['nomor_hp']);
    $email = trim($_POST['email']);
    $nomor_hp_baru = trim($_POST['nomor_hp_baru']);
    $password_baru = $_POST['password_baru'];
    $konfirmasi_password = $_POST['konfirmasi_password'];
    
    if (!empty($nama_lengkap) && !empty($nomor_hp) && !empty($email) && !empty($nomor_hp_baru)) {
        // Validate password if provided
        if (!empty($password_baru)) {
            if ($password_baru !== $konfirmasi_password) {
                $error = 'Password baru dan konfirmasi password tidak sama!';
            } elseif (strlen($password_baru) < 6) {
                $error = 'Password baru minimal 6 karakter!';
            }
        }
        
        if (empty($error)) {
            // Check if email already exists for other users
            $stmt = $conn->prepare("SELECT id_user FROM pegawai WHERE email = ? AND id_user != ?");
            $stmt->bind_param("si", $email, $_SESSION['id_user']);
            $stmt->execute();
            $result = $stmt->get_result();
            if ($result->fetch_assoc()) {
                $error = 'Email sudah digunakan oleh pegawai lain!';
            } else {
                // Update user data
                if (!empty($password_baru)) {
                    // Encrypt password using new nomor_hp as key
                    $encrypted_password = encryptPassword($password_baru, $nomor_hp_baru);
                    $stmt = $conn->prepare("UPDATE pegawai SET nama_lengkap = ?, nomor_hp = ?, email = ?, password = ? WHERE id_user = ?");
                    $stmt->bind_param("ssssi", $nama_lengkap, $nomor_hp_baru, $email, $encrypted_password, $_SESSION['id_user']);
                } else {
                    // If nomor_hp changed but no new password, re-encrypt existing password with new nomor_hp
                    if ($nomor_hp_baru !== $current_user['nomor_hp']) {
                        // Decrypt with old nomor_hp and encrypt with new nomor_hp
                        $old_password = decryptPassword($current_user['password'], $current_user['nomor_hp']);
                        $encrypted_password = encryptPassword($old_password, $nomor_hp_baru);
                        $stmt = $conn->prepare("UPDATE pegawai SET nama_lengkap = ?, nomor_hp = ?, email = ?, password = ? WHERE id_user = ?");
                        $stmt->bind_param("ssssi", $nama_lengkap, $nomor_hp_baru, $email, $encrypted_password, $_SESSION['id_user']);
                    } else {
                        $stmt = $conn->prepare("UPDATE pegawai SET nama_lengkap = ?, nomor_hp = ?, email = ? WHERE id_user = ?");
                        $stmt->bind_param("sssi", $nama_lengkap, $nomor_hp_baru, $email, $_SESSION['id_user']);
                    }
                }
                
                if ($stmt->execute()) {
                    $message = 'Profil berhasil diperbarui!';
                    // Update session data
                    $_SESSION['nama_lengkap'] = $nama_lengkap;
                    $_SESSION['nomor_hp'] = $nomor_hp_baru;
                    $_SESSION['email'] = $email;
                    
                    // Redirect to login after successful update
                    echo "<script>
                        alert('Profil berhasil diperbarui! Silakan login kembali.');
                        window.location.href = 'logout.php';
                    </script>";
                    exit();
                } else {
                    $error = 'Error: ' . $conn->error;
                }
            }
        }
    } else {
        $error = 'Nama lengkap, nomor HP, email, dan nomor HP baru harus diisi!';
    }
}
?>

<!doctype html>
<html lang="en">
  <head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Profile - Admin Dashboard</title>
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
              <li><a class="dropdown-item" href="profile.php">Profile</a></li>
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
                    <?php if($_SESSION["jabatan"] == "Admin"): ?>
                    <li class="nav-item"><a class="nav-link" href="promo.php"><i class="bi bi-percent"></i> Promo</a></li>
                    <li class="nav-item"><a class="nav-link" href="biaya_lain.php"><i class="bi bi-receipt"></i> Biaya Lain</a></li>
                    <li class="nav-item"><a class="nav-link" href="harga_pokok_penjualan.php"><i class="bi bi-calculator"></i> Harga Pokok Penjualan</a></li>
                    <li class="nav-item"><a class="nav-link" href="harga_rilis.php"><i class="bi bi-tag"></i> Harga Rilis</a></li>
                    <li class="nav-item"><a class="nav-link" href="pembatalan.php"><i class="bi bi-x-circle"></i> Pembatalan</a></li>
                    <?php endif; ?>
                  </ul>
                </div>
              </li>
              <?php endif; ?>
              
              <?php if($_SESSION["jabatan"] == "Admin"): ?>
              <!-- Laporan Menu - Admin Only -->
              <li class="nav-item">
                <a class="nav-link" data-bs-toggle="collapse" href="#laporanMenu" role="button">
                  <i class="bi bi-file-earmark-text"></i>
                  <span>Laporan</span>
                  <i class="bi bi-chevron-down ms-auto"></i>
                </a>
                <div class="collapse" id="laporanMenu">
                  <ul class="nav flex-column ms-3">
                    <li class="nav-item"><a class="nav-link" href="laporan_penjualan.php"><i class="bi bi-graph-up"></i> Penjualan</a></li>
                    <li class="nav-item"><a class="nav-link" href="laporan_pembelian.php"><i class="bi bi-cart-check"></i> Pembelian</a></li>
                    <li class="nav-item"><a class="nav-link" href="laporan_stok.php"><i class="bi bi-boxes"></i> Stok</a></li>
                  </ul>
                </div>
              </li>
              
              <!-- Pengaturan Menu - Admin Only -->
              <li class="nav-item">
                <a class="nav-link" data-bs-toggle="collapse" href="#pengaturanMenu" role="button">
                  <i class="bi bi-gear"></i>
                  <span>Pengaturan</span>
                  <i class="bi bi-chevron-down ms-auto"></i>
                </a>
                <div class="collapse" id="pengaturanMenu">
                  <ul class="nav flex-column ms-3">
                    <li class="nav-item"><a class="nav-link" href="backup.php"><i class="bi bi-download"></i> Backup Data</a></li>
                    <li class="nav-item"><a class="nav-link" href="restore.php"><i class="bi bi-upload"></i> Restore Data</a></li>
                  </ul>
                </div>
              </li>
              <?php endif; ?>
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
                  <i class="bi bi-house-door"></i> Beranda
                </a>
              </li>
              <li class="nav-item">
                <a class="nav-link" href="informasi.php">
                  <i class="bi bi-info-circle"></i> Informasi
                </a>
              </li>
              
              <?php if($_SESSION["jabatan"] == "Admin"): ?>
              <li class="nav-item">
                <a class="nav-link" data-bs-toggle="collapse" href="#masterMenuMobile" role="button">
                  <i class="bi bi-gear-fill"></i> Master
                </a>
                <div class="collapse" id="masterMenuMobile">
                  <ul class="nav flex-column ms-3">
                    <li class="nav-item"><a class="nav-link" href="resto.php">Resto</a></li>
                    <li class="nav-item"><a class="nav-link" href="pegawai.php">Pegawai</a></li>
                    <li class="nav-item"><a class="nav-link" href="vendor.php">Vendor</a></li>
                    <li class="nav-item"><a class="nav-link" href="meja.php">Meja</a></li>
                    <li class="nav-item"><a class="nav-link" href="metode_pembayaran.php">Metode Pembayaran</a></li>
                  </ul>
                </div>
              </li>
              <?php endif; ?>
              
              <?php if($_SESSION["jabatan"] == "Admin" || $_SESSION["jabatan"] == "Dapur"): ?>
              <li class="nav-item">
                <a class="nav-link" data-bs-toggle="collapse" href="#produkMenuMobile" role="button">
                  <i class="bi bi-box-seam"></i> Produk
                </a>
                <div class="collapse" id="produkMenuMobile">
                  <ul class="nav flex-column ms-3">
                    <li class="nav-item"><a class="nav-link" href="kategori_menu.php">Kategori Menu</a></li>
                    <li class="nav-item"><a class="nav-link" href="menu.php">Menu</a></li>
                    <li class="nav-item"><a class="nav-link" href="kategori_bahan.php">Kategori Bahan</a></li>
                    <li class="nav-item"><a class="nav-link" href="bahan.php">Bahan</a></li>
                    <li class="nav-item"><a class="nav-link" href="resep.php">Resep</a></li>
                  </ul>
                </div>
              </li>
              
              <li class="nav-item">
                <a class="nav-link" data-bs-toggle="collapse" href="#pembelianMenuMobile" role="button">
                  <i class="bi bi-cart-plus"></i> Pembelian
                </a>
                <div class="collapse" id="pembelianMenuMobile">
                  <ul class="nav flex-column ms-3">
                    <li class="nav-item"><a class="nav-link" href="pembelian.php">Pesanan Pembelian</a></li>
                    <li class="nav-item"><a class="nav-link" href="pembayaran_pembelian.php">Pembayaran</a></li>
                  </ul>
                </div>
              </li>
              <?php endif; ?>
              
              <?php if($_SESSION["jabatan"] == "Admin" || $_SESSION["jabatan"] == "Kasir"): ?>
              <li class="nav-item">
                <a class="nav-link" data-bs-toggle="collapse" href="#penjualanMenuMobile" role="button">
                  <i class="bi bi-cash-coin"></i> Penjualan
                </a>
                <div class="collapse" id="penjualanMenuMobile">
                  <ul class="nav flex-column ms-3">
                    <li class="nav-item"><a class="nav-link" href="shift_kasir.php">Shift Kasir</a></li>
                    <?php if($_SESSION["jabatan"] == "Admin"): ?>
                    <li class="nav-item"><a class="nav-link" href="promo.php">Promo</a></li>
                    <li class="nav-item"><a class="nav-link" href="biaya_lain.php">Biaya Lain</a></li>
                    <li class="nav-item"><a class="nav-link" href="harga_pokok_penjualan.php">Harga Pokok Penjualan</a></li>
                    <li class="nav-item"><a class="nav-link" href="harga_rilis.php">Harga Rilis</a></li>
                    <li class="nav-item"><a class="nav-link" href="pembatalan.php">Pembatalan</a></li>
                    <?php endif; ?>
                  </ul>
                </div>
              </li>
              <?php endif; ?>
            </ul>
          </div>
        </div>

        <!-- Main Content -->
        <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
          <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
            <h1 class="h2">Profile</h1>
          </div>

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

          <div class="row">
            <div class="col-md-12">
              <div class="card">
                <div class="card-header">
                  <h5 class="card-title mb-0">Edit Profile</h5>
                </div>
                <div class="card-body">
                  <form method="POST">
                    <div class="mb-3">
                      <label for="nama_lengkap" class="form-label">Nama Lengkap <span class="text-danger">*</span></label>
                      <input type="text" class="form-control" id="nama_lengkap" name="nama_lengkap" value="<?php echo htmlspecialchars($current_user['nama_lengkap']); ?>" required>
                    </div>
                    
                    <div class="mb-3">
                      <label for="nomor_hp" class="form-label">Nomor Handphone <span class="text-danger">*</span></label>
                      <input type="text" class="form-control" id="nomor_hp" name="nomor_hp" value="<?php echo htmlspecialchars($current_user['nomor_hp']); ?>" required>
                    </div>
                    
                    <div class="mb-3">
                      <label for="email" class="form-label">Email <span class="text-danger">*</span></label>
                      <input type="email" class="form-control" id="email" name="email" value="<?php echo htmlspecialchars($current_user['email']); ?>" required>
                    </div>
                    
                    <hr>
                    
                    <div class="mb-3">
                      <label for="nomor_hp_baru" class="form-label">Nomor Handphone Baru <span class="text-danger">*</span></label>
                      <input type="text" class="form-control" id="nomor_hp_baru" name="nomor_hp_baru" value="<?php echo htmlspecialchars($current_user['nomor_hp']); ?>" required>
                    </div>
                    
                    <div class="mb-3">
                      <label for="password_baru" class="form-label">Password Baru</label>
                      <input type="password" class="form-control" id="password_baru" name="password_baru" placeholder="Kosongkan jika tidak ingin mengubah password">
                      <div class="form-text">Minimal 6 karakter</div>
                    </div>
                    
                    <div class="mb-3">
                      <label for="konfirmasi_password" class="form-label">Konfirmasi Password Baru</label>
                      <input type="password" class="form-control" id="konfirmasi_password" name="konfirmasi_password" placeholder="Ulangi password baru">
                    </div>
                    
                    <div class="d-grid">
                      <button type="submit" class="btn btn-primary">Update</button>
                    </div>
                  </form>
                </div>
              </div>
            </div>
            
           
          </div>
        </main>
      </div>
    </div>

    <script src="../js/bootstrap.bundle.min.js"></script>
    <script>
      // Validate password confirmation
      document.getElementById('konfirmasi_password').addEventListener('input', function() {
        const password = document.getElementById('password_baru').value;
        const confirm = this.value;
        
        if (password !== '' && confirm !== '' && password !== confirm) {
          this.setCustomValidity('Password tidak sama');
        } else {
          this.setCustomValidity('');
        }
      });
      
      document.getElementById('password_baru').addEventListener('input', function() {
        const confirm = document.getElementById('konfirmasi_password');
        if (confirm.value !== '') {
          confirm.dispatchEvent(new Event('input'));
        }
      });
    </script>
  </body>
</html>