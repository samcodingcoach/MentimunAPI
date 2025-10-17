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

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'create':
                $nama_lengkap = trim($_POST['nama_lengkap']);
                $jabatan = $_POST['jabatan'];
                $nomor_hp = trim($_POST['nomor_hp']);
                $email = trim($_POST['email']);
                $password = $_POST['password'];
                $aktif = isset($_POST['aktif']) ? 1 : 0;
                
                if (!empty($nama_lengkap) && !empty($jabatan) && !empty($nomor_hp) && !empty($email) && !empty($password)) {
                    // Check if email already exists
                    $stmt = $conn->prepare("SELECT id_user FROM pegawai WHERE email = ?");
                    $stmt->bind_param("s", $email);
                    $stmt->execute();
                    $result = $stmt->get_result();
                    if ($result->fetch_assoc()) {
                        $error = 'Email sudah digunakan!';
                    } else {
                        // Encrypt password using nomor_hp as key
                        $encrypted_password = encryptPassword($password, $nomor_hp);
                        $stmt = $conn->prepare("INSERT INTO pegawai (nama_lengkap, jabatan, nomor_hp, email, password, aktif) VALUES (?, ?, ?, ?, ?, ?)");
                        $stmt->bind_param("sssssi", $nama_lengkap, $jabatan, $nomor_hp, $email, $encrypted_password, $aktif);
                        if ($stmt->execute()) {
                            $message = 'Data pegawai berhasil ditambahkan!';
                        } else {
                            $error = 'Error: ' . $conn->error;
                        }
                    }
                } else {
                    $error = 'Semua field wajib diisi!';
                }
                break;
                
            case 'update':
                $id_user = $_POST['id_user'];
                $nama_lengkap = trim($_POST['nama_lengkap']);
                $jabatan = $_POST['jabatan'];
                $nomor_hp = trim($_POST['nomor_hp']);
                $email = trim($_POST['email']);
                $password = $_POST['password'];
                $aktif = isset($_POST['aktif']) ? 1 : 0;
                
                if (!empty($nama_lengkap) && !empty($jabatan) && !empty($nomor_hp) && !empty($email)) {
                    // Check if email already exists for other users
                    $stmt = $conn->prepare("SELECT id_user FROM pegawai WHERE email = ? AND id_user != ?");
                    $stmt->bind_param("si", $email, $id_user);
                    $stmt->execute();
                    $result = $stmt->get_result();
                    if ($result->fetch_assoc()) {
                        $error = 'Email sudah digunakan oleh pegawai lain!';
                    } else {
                        if (!empty($password)) {
                            // Encrypt password using nomor_hp as key
                            $encrypted_password = encryptPassword($password, $nomor_hp);
                            $stmt = $conn->prepare("UPDATE pegawai SET nama_lengkap = ?, jabatan = ?, nomor_hp = ?, email = ?, password = ?, aktif = ? WHERE id_user = ?");
                            $stmt->bind_param("sssssii", $nama_lengkap, $jabatan, $nomor_hp, $email, $encrypted_password, $aktif, $id_user);
                        } else {
                            $stmt = $conn->prepare("UPDATE pegawai SET nama_lengkap = ?, jabatan = ?, nomor_hp = ?, email = ?, aktif = ? WHERE id_user = ?");
                            $stmt->bind_param("ssssii", $nama_lengkap, $jabatan, $nomor_hp, $email, $aktif, $id_user);
                        }
                        if ($stmt->execute()) {
                            $message = 'Data pegawai berhasil diperbarui!';
                        } else {
                            $error = 'Error: ' . $conn->error;
                        }
                    }
                } else {
                    $error = 'Nama lengkap, jabatan, nomor HP, dan email harus diisi!';
                }
                break;
                
            case 'delete':
                $id_user = $_POST['id_user'];
                $stmt = $conn->prepare("DELETE FROM pegawai WHERE id_user = ?");
                $stmt->bind_param("i", $id_user);
                if ($stmt->execute()) {
                    $message = 'Data pegawai berhasil dihapus!';
                } else {
                    $error = 'Error: ' . $conn->error;
                }
                break;
        }
    }
}

// Pagination and Search parameters
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 10;
$offset = ($page - 1) * $limit;
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$filter = isset($_GET['filter']) ? trim($_GET['filter']) : '';

// Build WHERE clause for search and filter
$where_conditions = [];
$params = [];
$types = '';

if (!empty($search)) {
    $where_conditions[] = "(nama_lengkap LIKE ? OR email LIKE ? OR nomor_hp LIKE ?)";
    $search_param = "%$search%";
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
    $types .= 'sss';
}

if (!empty($filter) && in_array($filter, ['Admin', 'Kasir', 'Koki', 'Pelayan'])) {
    $where_conditions[] = "jabatan = ?";
    $params[] = $filter;
    $types .= 's';
}

$where_clause = !empty($where_conditions) ? 'WHERE ' . implode(' AND ', $where_conditions) : '';

// Get total count for pagination
$count_sql = "SELECT COUNT(*) as total FROM pegawai $where_clause";
if (!empty($params)) {
    $count_stmt = $conn->prepare($count_sql);
    $count_stmt->bind_param($types, ...$params);
    $count_stmt->execute();
    $count_result = $count_stmt->get_result();
} else {
    $count_result = $conn->query($count_sql);
}
$total_records = $count_result->fetch_assoc()['total'];
$total_pages = ceil($total_records / $limit);

// Get pegawai data with pagination
$sql = "SELECT * FROM pegawai $where_clause ORDER BY id_user DESC LIMIT $limit OFFSET $offset";
if (!empty($params)) {
    $stmt = $conn->prepare($sql);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $result = $stmt->get_result();
    $pegawais = $result->fetch_all(MYSQLI_ASSOC);
} else {
    $result = $conn->query($sql);
    if ($result) {
        $pegawais = $result->fetch_all(MYSQLI_ASSOC);
    } else {
        $error = 'Error fetching data: ' . $conn->error;
        $pegawais = [];
    }
}

// Get single pegawai for editing
$edit_pegawai = null;
if (isset($_GET['edit'])) {
    $stmt = $conn->prepare("SELECT * FROM pegawai WHERE id_user = ?");
    $stmt->bind_param("i", $_GET['edit']);
    $stmt->execute();
    $result = $stmt->get_result();
    $edit_pegawai = $result->fetch_assoc();
}

// Jabatan options
$jabatan_options = ['admin', 'kasir', 'koki', 'pelayan'];
?>


<!doctype html>
<html lang="id" data-bs-theme="light">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Data Pegawai - Admin</title>
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
                    <h2 class="mb-1"><i class="bi bi-people me-2"></i>Data Pegawai</h2>
                    <p class="text-muted mb-0">Kelola data pegawai dan hak akses</p>
                </div>
                <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addModal">
                    <i class="bi bi-plus-lg me-2"></i>Tambah Pegawai
                </button>
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

            <div class="card-modern">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <div class="d-flex align-items-center">
                        <i class="bi bi-table me-2"></i>
                        <span>Daftar Pegawai</span>
                    </div>
                    <div class="d-flex gap-2">
                        <form class="d-flex" method="GET" action="">
                            <div class="input-group" style="width: 250px;">
                                <span class="input-group-text bg-white border-end-0">
                                    <i class="bi bi-search"></i>
                                </span>
                                <input type="text" name="search" class="form-control border-start-0 ps-0" placeholder="Cari pegawai..." value="<?php echo htmlspecialchars($search); ?>">
                            </div>
                        </form>
                        <select class="form-select" style="width: 160px;" onchange="window.location.href='?filter='+this.value+'&search=<?php echo urlencode($search); ?>'">
                            <option value="">Semua Jabatan</option>
                            <option value="Admin" <?php echo $filter === 'Admin' ? 'selected' : ''; ?>>Admin</option>
                            <option value="Kasir" <?php echo $filter === 'Kasir' ? 'selected' : ''; ?>>Kasir</option>
                            <option value="Dapur" <?php echo $filter === 'Dapur' ? 'selected' : ''; ?>>Dapur</option>
                        </select>
                    </div>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead>
                                <tr>
                                    <th style="width: 5%; text-align:left">No</th>
                                    <th style="width: auto; text-align:left">Nama Lengkap</th>
                                    <th style="width: 10%; text-align:center">Jabatan</th>
                                    <th style="width: 10%; text-align:center">Nomor HP</th>
                                    <th style="width: 15%; text-align:center">Email</th>
                                    <th style="width: 15%; text-align:center">Status</th>
                                    <th style="width: 15%; text-align:center">Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                            <?php if (!empty($pegawais)): ?>
                                <?php foreach ($pegawais as $index => $pegawai): ?>
                                <tr>
                                    <td class="text-start"><?php echo $offset + $index + 1; ?></td>
                                    <td class="text-start"><strong><?php echo htmlspecialchars($pegawai['nama_lengkap']); ?></strong></td>
                                    <td class="text-center">
                                        <span class="badge <?php 
                                            echo $pegawai['jabatan'] == 'Admin' ? 'bg-danger' : 
                                                ($pegawai['jabatan'] == 'Kasir' ? 'bg-primary' : 'bg-success'); 
                                        ?>">
                                            <?php echo htmlspecialchars($pegawai['jabatan']); ?>
                                        </span>
                                    </td>
                                    <td class="text-center"> <?php echo htmlspecialchars($pegawai['nomor_hp']); ?></td>
                                    <td class="text-center"><?php echo htmlspecialchars($pegawai['email']); ?></td>
                                    <td class="text-center">
                                        <?php if ($pegawai['aktif'] == 1): ?>
                                            <span class="badge bg-success">Aktif</span>
                                        <?php else: ?>
                                            <span class="badge bg-secondary">Nonaktif</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <div class="table-actions">
                                            <button class="btn btn-sm btn-outline-warning" onclick="editPegawai(<?php echo htmlspecialchars(json_encode($pegawai)); ?>)" title="Edit">
                                                <i class="bi bi-pencil"></i>
                                            </button>
                                            <?php if ($pegawai['id_user'] != $_SESSION['id_user']): ?>
                                            <button class="btn btn-sm btn-outline-danger" onclick="confirmDelete(<?php echo $pegawai['id_user']; ?>, '<?php echo htmlspecialchars(addslashes($pegawai['nama_lengkap'])); ?>')" title="Hapus">
                                                <i class="bi bi-trash"></i>
                                            </button>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="7" class="text-center py-4">
                                        <i class="bi bi-inbox fs-1 d-block mb-2 text-muted"></i>
                                        <p class="text-muted mb-0">Tidak ada data pegawai</p>
                                    </td>
                                </tr>
                            <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
                <div class="card-footer bg-light border-top py-3 px-4">
                    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2">
                        <small class="text-muted mb-0">
                            <?php if ($total_records > 0): ?>
                                Menampilkan <?php echo $offset + 1; ?>-<?php echo min($offset + $limit, $total_records); ?> dari <?php echo $total_records; ?> pegawai
                            <?php else: ?>
                                Tidak ada data pegawai
                            <?php endif; ?>
                        </small>
                        <?php if ($total_pages > 1): ?>
                        <nav>
                            <ul class="pagination pagination-sm mb-0">
                                <li class="page-item <?php echo $page <= 1 ? 'disabled' : ''; ?>">
                                    <a class="page-link" href="?page=<?php echo $page - 1; ?>&search=<?php echo urlencode($search); ?>&filter=<?php echo urlencode($filter); ?>">Previous</a>
                                </li>
                                <?php
                                    $maxPagesToShow = 5;
                                    $startPage = max(1, $page - floor($maxPagesToShow / 2));
                                    $endPage = min($total_pages, $startPage + $maxPagesToShow - 1);
                                    if ($endPage - $startPage + 1 < $maxPagesToShow) {
                                        $startPage = max(1, $endPage - $maxPagesToShow + 1);
                                    }
                                ?>
                                <?php for ($i = $startPage; $i <= $endPage; $i++): ?>
                                <li class="page-item <?php echo $i == $page ? 'active' : ''; ?>">
                                    <a class="page-link" href="?page=<?php echo $i; ?>&search=<?php echo urlencode($search); ?>&filter=<?php echo urlencode($filter); ?>"><?php echo $i; ?></a>
                                </li>
                                <?php endfor; ?>
                                <li class="page-item <?php echo $page >= $total_pages ? 'disabled' : ''; ?>">
                                    <a class="page-link" href="?page=<?php echo $page + 1; ?>&search=<?php echo urlencode($search); ?>&filter=<?php echo urlencode($filter); ?>">Next</a>
                                </li>
                            </ul>
                        </nav>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <!-- Add Modal -->
    <div class="modal fade" id="addModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="bi bi-plus-circle me-2"></i>Tambah Pegawai</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST" class="form-modern">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="create">
                        <div class="row mb-3">
                            <label class="col-sm-3 col-form-label">Nama Lengkap <span class="text-danger">*</span></label>
                            <div class="col-sm-9">
                                <input type="text" name="nama_lengkap" class="form-control" required placeholder="Masukkan nama lengkap">
                            </div>
                        </div>
                        <div class="row mb-3">
                            <label class="col-sm-3 col-form-label">Jabatan <span class="text-danger">*</span></label>
                            <div class="col-sm-9">
                                <select name="jabatan" class="form-select" required>
                                    <option value="">-- Pilih Jabatan --</option>
                                    <option value="Admin">Admin</option>
                                    <option value="Kasir">Kasir</option>
                                    <option value="Dapur">Dapur</option>
                                </select>
                            </div>
                        </div>
                        <div class="row mb-3">
                            <label class="col-sm-3 col-form-label">Nomor HP <span class="text-danger">*</span></label>
                            <div class="col-sm-9">
                                <input type="text" name="nomor_hp" class="form-control" required placeholder="Nomor HP (untuk login)">
                                <small class="text-muted">Nomor HP digunakan sebagai username</small>
                            </div>
                        </div>
                        <div class="row mb-3">
                            <label class="col-sm-3 col-form-label">Email <span class="text-danger">*</span></label>
                            <div class="col-sm-9">
                                <input type="email" name="email" class="form-control" required placeholder="email@example.com">
                            </div>
                        </div>
                        <div class="row mb-3">
                            <label class="col-sm-3 col-form-label">Password <span class="text-danger">*</span></label>
                            <div class="col-sm-9">
                                <input type="password" name="password" class="form-control" required placeholder="Minimal 6 karakter">
                            </div>
                        </div>
                        <div class="row mb-3">
                            <div class="col-sm-9 offset-sm-3">
                                <div class="form-check">
                                    <input type="checkbox" name="aktif" class="form-check-input" id="aktifAdd" checked>
                                    <label class="form-check-label" for="aktifAdd">Aktif</label>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                        <button type="submit" class="btn btn-primary"><i class="bi bi-save me-2"></i>Simpan</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Edit Modal -->
    <div class="modal fade" id="editModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="bi bi-pencil-square me-2"></i>Edit Pegawai</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST" class="form-modern">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="update">
                        <input type="hidden" name="id_user" id="edit_id_user">
                        <div class="row mb-3">
                            <label class="col-sm-3 col-form-label">Nama Lengkap <span class="text-danger">*</span></label>
                            <div class="col-sm-9">
                                <input type="text" name="nama_lengkap" id="edit_nama_lengkap" class="form-control" required>
                            </div>
                        </div>
                        <div class="row mb-3">
                            <label class="col-sm-3 col-form-label">Jabatan <span class="text-danger">*</span></label>
                            <div class="col-sm-9">
                                <select name="jabatan" id="edit_jabatan" class="form-select" required>
                                    <option value="">-- Pilih Jabatan --</option>
                                    <option value="Admin">Admin</option>
                                    <option value="Kasir">Kasir</option>
                                    <option value="Dapur">Dapur</option>
                                </select>
                            </div>
                        </div>
                        <div class="row mb-3">
                            <label class="col-sm-3 col-form-label">Nomor HP <span class="text-danger">*</span></label>
                            <div class="col-sm-9">
                                <input type="text" name="nomor_hp" id="edit_nomor_hp" class="form-control" required>
                            </div>
                        </div>
                        <div class="row mb-3">
                            <label class="col-sm-3 col-form-label">Email <span class="text-danger">*</span></label>
                            <div class="col-sm-9">
                                <input type="email" name="email" id="edit_email" class="form-control" required>
                            </div>
                        </div>
                        <div class="row mb-3">
                            <label class="col-sm-3 col-form-label">Password Baru</label>
                            <div class="col-sm-9">
                                <input type="password" name="password" id="edit_password" class="form-control" placeholder="Kosongkan jika tidak ingin mengubah">
                                <small class="text-muted">Isi hanya jika ingin mengubah password</small>
                            </div>
                        </div>
                        <div class="row mb-3">
                            <div class="col-sm-9 offset-sm-3">
                                <div class="form-check">
                                    <input type="checkbox" name="aktif" class="form-check-input" id="edit_aktif">
                                    <label class="form-check-label" for="edit_aktif">Aktif</label>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                        <button type="submit" class="btn btn-primary"><i class="bi bi-save me-2"></i>Update</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <?php include '_scripts_new.php'; ?>
    
    <script>
        function editPegawai(pegawai) {
            document.getElementById('edit_id_user').value = pegawai.id_user;
            document.getElementById('edit_nama_lengkap').value = pegawai.nama_lengkap;
            document.getElementById('edit_jabatan').value = pegawai.jabatan;
            document.getElementById('edit_nomor_hp').value = pegawai.nomor_hp;
            document.getElementById('edit_email').value = pegawai.email;
            document.getElementById('edit_password').value = '';
            document.getElementById('edit_aktif').checked = pegawai.aktif == 1;
            
            var editModal = new bootstrap.Modal(document.getElementById('editModal'));
            editModal.show();
        }
        
        function confirmDelete(id, nama) {
            if (confirm('Apakah Anda yakin ingin menghapus pegawai "' + nama + '"?')) {
                var form = document.createElement('form');
                form.method = 'POST';
                form.innerHTML = '<input type="hidden" name="action" value="delete"><input type="hidden" name="id_user" value="' + id + '">';
                document.body.appendChild(form);
                form.submit();
            }
        }
        
        <?php if ($edit_pegawai): ?>
        document.addEventListener('DOMContentLoaded', function() {
            editPegawai(<?php echo json_encode($edit_pegawai); ?>);
        });
        <?php endif; ?>
    </script>
</body>
</html>
