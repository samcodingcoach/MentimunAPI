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
<html lang="id" data-bs-theme="light">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Profil Saya - Admin</title>
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
                    <h2 class="mb-1"><i class="bi bi-person-circle me-2"></i>Profil Saya</h2>
                    <p class="text-muted mb-0">Kelola informasi profil dan keamanan akun Anda</p>
                </div>
            </div>

            <?php if ($message): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <i class="bi bi-check-circle me-2"></i><?php echo $message; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
            <?php endif; ?>

            <?php if ($error): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <i class="bi bi-exclamation-triangle me-2"></i><?php echo $error; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
            <?php endif; ?>

            <div class="row">
                <div class="col-lg-8 mx-auto">
                    <div class="card-modern">
                        <div class="card-header">
                            <i class="bi bi-pencil-square me-2"></i>Informasi Profil
                        </div>
                        <div class="card-body">
                            <form method="POST" class="form-modern">
                                <div class="row mb-3">
                                    <label class="col-sm-3 col-form-label">Nama Lengkap <span class="text-danger">*</span></label>
                                    <div class="col-sm-9">
                                        <input type="text" name="nama_lengkap" class="form-control" value="<?php echo htmlspecialchars($current_user['nama_lengkap']); ?>" required>
                                    </div>
                                </div>

                                <div class="row mb-3">
                                    <label class="col-sm-3 col-form-label">Jabatan</label>
                                    <div class="col-sm-9">
                                        <input type="text" class="form-control" value="<?php echo htmlspecialchars($current_user['jabatan']); ?>" disabled readonly>
                                    </div>
                                </div>

                                <div class="row mb-3">
                                    <label class="col-sm-3 col-form-label">Nomor HP Saat Ini <span class="text-danger">*</span></label>
                                    <div class="col-sm-9">
                                        <input type="text" name="nomor_hp" class="form-control" value="<?php echo htmlspecialchars($current_user['nomor_hp']); ?>" disabled readonly>
                                        <small class="text-muted">Nomor HP digunakan sebagai username</small>
                                    </div>
                                </div>

                                <div class="row mb-3">
                                    <label class="col-sm-3 col-form-label">Nomor HP Baru <span class="text-danger">*</span></label>
                                    <div class="col-sm-9">
                                        <input type="text" name="nomor_hp_baru" class="form-control" value="<?php echo htmlspecialchars($current_user['nomor_hp']); ?>" required>
                                        <small class="text-muted">Ubah nomor HP jika diperlukan</small>
                                    </div>
                                </div>

                                <div class="row mb-3">
                                    <label class="col-sm-3 col-form-label">Email <span class="text-danger">*</span></label>
                                    <div class="col-sm-9">
                                        <input type="email" name="email" class="form-control" value="<?php echo htmlspecialchars($current_user['email']); ?>" required>
                                    </div>
                                </div>

                                <hr class="my-4">

                                <h6 class="mb-3"><i class="bi bi-shield-lock me-2"></i>Ubah Password (Opsional)</h6>
                                <p class="text-muted small">Kosongkan jika tidak ingin mengubah password</p>

                                <div class="row mb-3">
                                    <label class="col-sm-3 col-form-label">Password Baru</label>
                                    <div class="col-sm-9">
                                        <input type="password" name="password_baru" id="password_baru" class="form-control" placeholder="Minimal 6 karakter">
                                    </div>
                                </div>

                                <div class="row mb-3">
                                    <label class="col-sm-3 col-form-label">Konfirmasi Password</label>
                                    <div class="col-sm-9">
                                        <input type="password" name="konfirmasi_password" id="konfirmasi_password" class="form-control" placeholder="Ulangi password baru">
                                        <div id="password-match-message" class="mt-2"></div>
                                    </div>
                                </div>

                                <div class="row">
                                    <div class="col-sm-9 offset-sm-3">
                                        <button type="submit" class="btn btn-primary">
                                            <i class="bi bi-save me-2"></i>Simpan Perubahan
                                        </button>
                                        <a href="index.php" class="btn btn-outline-secondary">
                                            <i class="bi bi-x-circle me-2"></i>Batal
                                        </a>
                                    </div>
                                </div>
                            </form>
                        </div>
                    </div>

                    <div class="card-modern mt-4">
                        <div class="card-header bg-danger text-white">
                            <i class="bi bi-exclamation-triangle me-2"></i>Informasi Penting
                        </div>
                        <div class="card-body">
                            <ul class="mb-0">
                                <li>Setelah mengubah profil, Anda akan diminta login kembali</li>
                                <li>Password minimal 6 karakter</li>
                                <li>Jika mengubah nomor HP, password lama akan di-enkripsi ulang</li>
                                <li>Email harus unik dan belum digunakan pegawai lain</li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <?php include '_scripts_new.php'; ?>
    
    <script>
    // Password match validation
    document.getElementById('konfirmasi_password').addEventListener('input', function() {
        const password = document.getElementById('password_baru').value;
        const confirm = this.value;
        const message = document.getElementById('password-match-message');
        
        if (confirm === '') {
            message.innerHTML = '';
            return;
        }
        
        if (password === confirm) {
            message.innerHTML = '<small class="text-success"><i class="bi bi-check-circle"></i> Password cocok</small>';
            this.setCustomValidity('');
        } else {
            message.innerHTML = '<small class="text-danger"><i class="bi bi-x-circle"></i> Password tidak cocok</small>';
            this.setCustomValidity('Password tidak cocok');
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
