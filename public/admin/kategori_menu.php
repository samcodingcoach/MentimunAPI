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
                $nama_kategori = trim($_POST['nama_kategori']);
                $aktif = isset($_POST['aktif']) ? '1' : '0';
                
                if (!empty($nama_kategori)) {
                    $stmt = $conn->prepare("SELECT id_kategori FROM kategori_menu WHERE nama_kategori = ?");
                    $stmt->bind_param("s", $nama_kategori);
                    $stmt->execute();
                    $result = $stmt->get_result();
                    if ($result->fetch_assoc()) {
                        $error = 'Nama kategori sudah ada!';
                    } else {
                        $stmt = $conn->prepare("INSERT INTO kategori_menu (nama_kategori, aktif) VALUES (?, ?)");
                        $stmt->bind_param("ss", $nama_kategori, $aktif);
                        if ($stmt->execute()) {
                            $message = 'Kategori menu berhasil ditambahkan!';
                        } else {
                            $error = 'Error: ' . $conn->error;
                        }
                    }
                } else {
                    $error = 'Nama kategori wajib diisi!';
                }
                break;
                
            case 'update':
                $id_kategori = $_POST['id_kategori'];
                $nama_kategori = trim($_POST['nama_kategori']);
                $aktif = isset($_POST['aktif']) ? '1' : '0';
                
                if (!empty($nama_kategori)) {
                    $stmt = $conn->prepare("SELECT id_kategori FROM kategori_menu WHERE nama_kategori = ? AND id_kategori != ?");
                    $stmt->bind_param("si", $nama_kategori, $id_kategori);
                    $stmt->execute();
                    $result = $stmt->get_result();
                    if ($result->fetch_assoc()) {
                        $error = 'Nama kategori sudah digunakan!';
                    } else {
                        $stmt = $conn->prepare("UPDATE kategori_menu SET nama_kategori = ?, aktif = ? WHERE id_kategori = ?");
                        $stmt->bind_param("ssi", $nama_kategori, $aktif, $id_kategori);
                        if ($stmt->execute()) {
                            $message = 'Kategori menu berhasil diperbarui!';
                        } else {
                            $error = 'Error: ' . $conn->error;
                        }
                    }
                } else {
                    $error = 'Nama kategori wajib diisi!';
                }
                break;
        }
    }
}

// Pagination and Search
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 10;
$offset = ($page - 1) * $limit;
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$filter = isset($_GET['filter']) ? trim($_GET['filter']) : '';

$where_conditions = [];
$params = [];
$types = '';

if (!empty($search)) {
    $where_conditions[] = "nama_kategori LIKE ?";
    $search_param = "%$search%";
    $params[] = $search_param;
    $types .= 's';
}

if (!empty($filter) && in_array($filter, ['1', '0'])) {
    $where_conditions[] = "aktif = ?";
    $params[] = $filter;
    $types .= 's';
}

$where_clause = !empty($where_conditions) ? 'WHERE ' . implode(' AND ', $where_conditions) : '';

$count_sql = "SELECT COUNT(*) as total FROM kategori_menu $where_clause";
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

$sql = "SELECT * FROM kategori_menu $where_clause ORDER BY id_kategori DESC LIMIT $limit OFFSET $offset";
if (!empty($params)) {
    $stmt = $conn->prepare($sql);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $result = $stmt->get_result();
    $categories = $result->fetch_all(MYSQLI_ASSOC);
} else {
    $result = $conn->query($sql);
    $categories = $result ? $result->fetch_all(MYSQLI_ASSOC) : [];
}

$edit_category = null;
if (isset($_GET['edit'])) {
    $stmt = $conn->prepare("SELECT * FROM kategori_menu WHERE id_kategori = ?");
    $stmt->bind_param("i", $_GET['edit']);
    $stmt->execute();
    $result = $stmt->get_result();
    $edit_category = $result->fetch_assoc();
}
?>
<!doctype html>
<html lang="id" data-bs-theme="light">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Kategori Menu - Admin</title>
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
                    <h2 class="mb-1">Kategori Menu</h2>
                    <p class="text-muted mb-0">Kelola kategori menu restoran</p>
                </div>
                <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addModal">
                    <i class="bi bi-plus-lg me-2"></i>Tambah Kategori
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
                        <span>Daftar Kategori Menu</span>
                    </div>
                    <div class="d-flex gap-2">
                        <form class="d-flex" method="GET" action="">
                            <div class="input-group" style="width: 250px;">
                                <span class="input-group-text bg-white border-end-0">
                                    <i class="bi bi-search"></i>
                                </span>
                                <input type="text" name="search" class="form-control border-start-0 ps-0" placeholder="Cari kategori..." value="<?php echo htmlspecialchars($search); ?>">
                            </div>
                        </form>
                        <select class="form-select" style="width: 150px;" onchange="window.location.href='?filter='+this.value+'&search=<?php echo urlencode($search); ?>'">
                            <option value="">Semua Status</option>
                            <option value="1" <?php echo $filter === '1' ? 'selected' : ''; ?>>Aktif</option>
                            <option value="0" <?php echo $filter === '0' ? 'selected' : ''; ?>>Nonaktif</option>
                        </select>
                    </div>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead>
                            <tr>
                                <th style="width: 5%; text-align:left;">No</th>
                                <th style="width: auto; text-align:left;">Nama Kategori</th>
                                <th style="width: 10%; text-align:center;">Status</th>
                                <th style="width: 12%; text-align:center;">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php if (!empty($categories)): ?>
                            <?php foreach ($categories as $index => $cat): ?>
                            <tr>
                                <td class="text-start"><?php echo $offset + $index + 1; ?></td>
                                <td class="text-start"><strong><?php echo htmlspecialchars($cat['nama_kategori']); ?></strong></td>
                                <td class="text-center">
                                    <?php if ($cat['aktif'] == '1'): ?>
                                        <span class="badge bg-success">Aktif</span>
                                    <?php else: ?>
                                        <span class="badge bg-secondary">Nonaktif</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-center">
                                    <div class="table-actions">
                                        <button class="btn btn-sm btn-outline-warning" onclick="editCategory(<?php echo htmlspecialchars(json_encode($cat)); ?>)">
                                            <i class="bi bi-pencil"></i>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="4" class="text-center py-4">
                                    <i class="bi bi-inbox fs-1 d-block mb-2 text-muted"></i>
                                    <p class="text-muted mb-0">Tidak ada data kategori</p>
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
                                Menampilkan <?php echo $offset + 1; ?>-<?php echo min($offset + $limit, $total_records); ?> dari <?php echo $total_records; ?> kategori
                            <?php else: ?>
                                Tidak ada data kategori
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
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="bi bi-plus-circle me-2"></i>Tambah Kategori Menu</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST" class="form-modern">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="create">
                        <div class="mb-3">
                            <label class="form-label">Nama Kategori <span class="text-danger">*</span></label>
                            <input type="text" name="nama_kategori" class="form-control" required placeholder="Masukkan nama kategori">
                        </div>
                        <div class="form-check">
                            <input type="checkbox" name="aktif" class="form-check-input" id="aktifAdd" checked>
                            <label class="form-check-label" for="aktifAdd">Aktif</label>
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
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="bi bi-pencil-square me-2"></i>Edit Kategori Menu</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST" class="form-modern">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="update">
                        <input type="hidden" name="id_kategori" id="edit_id_kategori">
                        <div class="mb-3">
                            <label class="form-label">Nama Kategori <span class="text-danger">*</span></label>
                            <input type="text" name="nama_kategori" id="edit_nama_kategori" class="form-control" required>
                        </div>
                        <div class="form-check">
                            <input type="checkbox" name="aktif" class="form-check-input" id="edit_aktif">
                            <label class="form-check-label" for="edit_aktif">Aktif</label>
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
        function editCategory(category) {
            document.getElementById('edit_id_kategori').value = category.id_kategori;
            document.getElementById('edit_nama_kategori').value = category.nama_kategori;
            document.getElementById('edit_aktif').checked = category.aktif == '1';
            
            var editModal = new bootstrap.Modal(document.getElementById('editModal'));
            editModal.show();
        }
        
        <?php if ($edit_category): ?>
        document.addEventListener('DOMContentLoaded', function() {
            editCategory(<?php echo json_encode($edit_category); ?>);
        });
        <?php endif; ?>
    </script>
</body>
</html>
