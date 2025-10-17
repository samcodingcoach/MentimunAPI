<?php
session_start();
require_once __DIR__ . '/../../config/koneksi.php';

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
                $nama_aplikasi = trim($_POST['nama_aplikasi']);
                $alamat = trim($_POST['alamat']);
                $no_hp = trim($_POST['no_hp']);
                $serverkeymidtrans = trim($_POST['serverkeymidtrans']);
                
                if (!empty($nama_aplikasi) && !empty($alamat) && !empty($no_hp)) {
                    $stmt = $conn->prepare("INSERT INTO perusahaan (nama_aplikasi, alamat, no_hp, serverkeymidtrans, update_time) VALUES (?, ?, ?, ?, NOW())");
                    if ($stmt->execute([$nama_aplikasi, $alamat, $no_hp, $serverkeymidtrans])) {
                        $message = 'Data resto berhasil ditambahkan!';
                    } else {
                        $error = 'Error: ' . $conn->error;
                    }
                } else {
                    $error = 'Nama aplikasi, alamat, dan nomor HP harus diisi!';
                }
                break;
                
            case 'update':
                $id_app = $_POST['id_app'];
                $nama_aplikasi = trim($_POST['nama_aplikasi']);
                $alamat = trim($_POST['alamat']);
                $no_hp = trim($_POST['no_hp']);
                $serverkeymidtrans = trim($_POST['serverkeymidtrans']);
                
                if (!empty($nama_aplikasi) && !empty($alamat) && !empty($no_hp)) {
                    $stmt = $conn->prepare("UPDATE perusahaan SET nama_aplikasi = ?, alamat = ?, no_hp = ?, serverkeymidtrans = ?, update_time = NOW() WHERE id_app = ?");
                    if ($stmt->execute([$nama_aplikasi, $alamat, $no_hp, $serverkeymidtrans, $id_app])) {
                        $message = 'Data resto berhasil diperbarui!';
                    } else {
                        $error = 'Error: ' . $conn->error;
                    }
                } else {
                    $error = 'Nama aplikasi, alamat, dan nomor HP harus diisi!';
                }
                break;
                
            case 'delete':
                $id_app = $_POST['id_app'];
                $stmt = $conn->prepare("DELETE FROM perusahaan WHERE id_app = ?");
                if ($stmt->execute([$id_app])) {
                    $message = 'Data resto berhasil dihapus!';
                } else {
                    $error = 'Error: ' . $conn->error;
                }
                break;
        }
    }
}

// Pagination and Search parameters
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 20;
$offset = ($page - 1) * $limit;
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$filter = isset($_GET['filter']) ? trim($_GET['filter']) : '';

// Build WHERE clause for search and filter
$where_conditions = [];
$params = [];
$types = '';

if (!empty($search)) {
    $where_conditions[] = "(nama_aplikasi LIKE ? OR alamat LIKE ? OR no_hp LIKE ?)";
    $search_param = "%$search%";
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
    $types .= 'sss';
}

if (!empty($filter)) {
    // Add filter logic here if needed
}

$where_clause = !empty($where_conditions) ? 'WHERE ' . implode(' AND ', $where_conditions) : '';

// Get total count for pagination
$count_sql = "SELECT COUNT(*) as total FROM perusahaan $where_clause";
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

// Get resto data with pagination
$sql = "SELECT * FROM perusahaan $where_clause ORDER BY id_app DESC LIMIT $limit OFFSET $offset";
if (!empty($params)) {
    $stmt = $conn->prepare($sql);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $result = $stmt->get_result();
    $restos = $result->fetch_all(MYSQLI_ASSOC);
} else {
    $result = $conn->query($sql);
    if ($result) {
        $restos = $result->fetch_all(MYSQLI_ASSOC);
    } else {
        $error = 'Error fetching data: ' . $conn->error;
        $restos = [];
    }
}

// Get single resto for editing
$edit_resto = null;
if (isset($_GET['edit'])) {
    $stmt = $conn->prepare("SELECT * FROM perusahaan WHERE id_app = ?");
    $stmt->bind_param("i", $_GET['edit']);
    $stmt->execute();
    $result = $stmt->get_result();
    $edit_resto = $result->fetch_assoc();
}
?>


<!doctype html>
<html lang="id" data-bs-theme="light">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Data Resto - Admin</title>
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
                    <h2 class="mb-1"><i class="bi bi-shop me-2"></i>Data Resto</h2>
                    <p class="text-muted mb-0">Kelola informasi restoran/perusahaan</p>
                </div>
                <!-- <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addModal">
                    <i class="bi bi-plus-lg me-2"></i>Tambah Resto
                </button> -->
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
                        <span>Daftar Resto</span>
                    </div>
                    <div class="d-flex gap-2">
                        <form class="d-flex" method="GET" action="">
                            <div class="input-group" style="width: 250px;">
                                <span class="input-group-text bg-white border-end-0">
                                    <i class="bi bi-search"></i>
                                </span>
                                <input type="text" name="search" class="form-control border-start-0 ps-0" placeholder="Cari resto..." value="<?php echo htmlspecialchars($search); ?>">
                            </div>
                        </form>
                    </div>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead>
                                <tr>
                                    <th style="width: 5%; text-align: left">No</th>
                                    <th style="width: auto; text-align: left" >Nama Aplikasi</th>
                                    <th style="width: 25%; text-align: left">Alamat</th>
                                    <th style="width: 15%; text-align: center">No HP</th>
                                    <th style="width: 15%; text-align: center">Server Key Midtrans</th>
                                    <th style="width: 14%; text-align: center">Update Time</th>
                                    <th style="width: 10%; text-align:center">Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                            <?php if (!empty($restos)): ?>
                                <?php foreach ($restos as $index => $resto): ?>
                                <tr>
                                    <td class="text-start"><?php echo $offset + $index + 1; ?></td>
                                    <td class="text-start"><strong><?php echo htmlspecialchars($resto['nama_aplikasi']); ?></strong></td>
                                    <td class="text-start"><?php echo htmlspecialchars($resto['alamat']); ?></td>
                                    <td class="text-center"><?php echo htmlspecialchars($resto['no_hp']); ?></td>
                                    <td class="text-center"><small class="font-monospace"><?php echo htmlspecialchars(substr($resto['serverkeymidtrans'], 0, 20)); ?>...</small></td>
                                    <td class="text-center"><?php echo date('d/m/Y H:i', strtotime($resto['update_time'])); ?></td>
                                    <td>
                                        <div class="table-actions">
                                            <button class="btn btn-sm btn-outline-warning" onclick="editResto(<?php echo htmlspecialchars(json_encode($resto)); ?>)" title="Edit">
                                                <i class="bi bi-pencil"></i>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="7" class="text-center py-4">
                                        <i class="bi bi-inbox fs-1 d-block mb-2 text-muted"></i>
                                        <p class="text-muted mb-0">Tidak ada data resto</p>
                                    </td>
                                </tr>
                            <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
                <?php if ($total_pages > 1): ?>
                <div class="card-footer bg-light border-top py-3 px-4">
                    <div class="d-flex justify-content-between align-items-center">
                        <small class="text-muted">Menampilkan <?php echo $offset + 1; ?>-<?php echo min($offset + $limit, $total_records); ?> dari <?php echo $total_records; ?> resto</small>
                        <nav>
                            <ul class="pagination pagination-sm mb-0">
                                <li class="page-item <?php echo $page <= 1 ? 'disabled' : ''; ?>">
                                    <a class="page-link" href="?page=<?php echo $page - 1; ?>&search=<?php echo urlencode($search); ?>">Previous</a>
                                </li>
                                <?php for ($i = 1; $i <= min($total_pages, 5); $i++): ?>
                                <li class="page-item <?php echo $i == $page ? 'active' : ''; ?>">
                                    <a class="page-link" href="?page=<?php echo $i; ?>&search=<?php echo urlencode($search); ?>"><?php echo $i; ?></a>
                                </li>
                                <?php endfor; ?>
                                <li class="page-item <?php echo $page >= $total_pages ? 'disabled' : ''; ?>">
                                    <a class="page-link" href="?page=<?php echo $page + 1; ?>&search=<?php echo urlencode($search); ?>">Next</a>
                                </li>
                            </ul>
                        </nav>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </main>

    <!-- Add Modal -->
    <div class="modal fade" id="addModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="bi bi-plus-circle me-2"></i>Tambah Data Resto</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST" class="form-modern">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="create">
                        <div class="row mb-3">
                            <label class="col-sm-3 col-form-label">Nama Aplikasi <span class="text-danger">*</span></label>
                            <div class="col-sm-9">
                                <input type="text" name="nama_aplikasi" class="form-control" required placeholder="Nama restoran/aplikasi">
                            </div>
                        </div>
                        <div class="row mb-3">
                            <label class="col-sm-3 col-form-label">Alamat <span class="text-danger">*</span></label>
                            <div class="col-sm-9">
                                <textarea name="alamat" class="form-control" rows="3" required placeholder="Alamat lengkap resto"></textarea>
                            </div>
                        </div>
                        <div class="row mb-3">
                            <label class="col-sm-3 col-form-label">No HP <span class="text-danger">*</span></label>
                            <div class="col-sm-9">
                                <input type="text" name="no_hp" class="form-control" required placeholder="Nomor HP/Telepon">
                            </div>
                        </div>
                        <div class="row mb-3">
                            <label class="col-sm-3 col-form-label">Server Key Midtrans</label>
                            <div class="col-sm-9">
                                <input type="text" name="serverkeymidtrans" class="form-control" placeholder="Server key untuk payment gateway">
                                <small class="text-muted">Opsional - untuk integrasi pembayaran Midtrans</small>
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
                    <h5 class="modal-title"><i class="bi bi-pencil-square me-2"></i>Edit Data Resto</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST" class="form-modern">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="update">
                        <input type="hidden" name="id_app" id="edit_id_app">
                        <div class="row mb-3">
                            <label class="col-sm-3 col-form-label">Nama Aplikasi <span class="text-danger">*</span></label>
                            <div class="col-sm-9">
                                <input type="text" name="nama_aplikasi" id="edit_nama_aplikasi" class="form-control" required>
                            </div>
                        </div>
                        <div class="row mb-3">
                            <label class="col-sm-3 col-form-label">Alamat <span class="text-danger">*</span></label>
                            <div class="col-sm-9">
                                <textarea name="alamat" id="edit_alamat" class="form-control" rows="3" required></textarea>
                            </div>
                        </div>
                        <div class="row mb-3">
                            <label class="col-sm-3 col-form-label">No HP <span class="text-danger">*</span></label>
                            <div class="col-sm-9">
                                <input type="text" name="no_hp" id="edit_no_hp" class="form-control" required>
                            </div>
                        </div>
                        <div class="row mb-3">
                            <label class="col-sm-3 col-form-label">Server Key Midtrans</label>
                            <div class="col-sm-9">
                                <input type="text" name="serverkeymidtrans" id="edit_serverkeymidtrans" class="form-control">
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
        function editResto(resto) {
            document.getElementById('edit_id_app').value = resto.id_app;
            document.getElementById('edit_nama_aplikasi').value = resto.nama_aplikasi;
            document.getElementById('edit_alamat').value = resto.alamat;
            document.getElementById('edit_no_hp').value = resto.no_hp;
            document.getElementById('edit_serverkeymidtrans').value = resto.serverkeymidtrans || '';
            
            var editModal = new bootstrap.Modal(document.getElementById('editModal'));
            editModal.show();
        }
        
        <?php if ($edit_resto): ?>
        document.addEventListener('DOMContentLoaded', function() {
            editResto(<?php echo json_encode($edit_resto); ?>);
        });
        <?php endif; ?>
    </script>
</body>
</html>
