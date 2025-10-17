<?php
session_start();
require_once '../../config/koneksi.php';

// Check if user is logged in
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    header('Location: login.php');
    exit();
}

$message = '';
$error = '';
$edit_meja = null;

// Function to generate meja number
function generateMejaNumber($conn) {
    $stmt = $conn->prepare("SELECT MAX(nomor_meja) as max_nomor FROM meja");
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    
    return ($row['max_nomor'] ?? 0) + 1;
}

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $action = $_POST['action'] ?? '';
    
    switch ($action) {
        case 'create':
            $nomor_meja = $_POST['nomor_meja'] ?? generateMejaNumber($conn);
            $aktif = $_POST['aktif'] ?? '1';
            $in_used = $_POST['in_used'] ?? '0';
            $pos_x = $_POST['pos_x'] ?? 0;
            $pos_y = $_POST['pos_y'] ?? 0;
            
            // Check if nomor_meja already exists
            $check_stmt = $conn->prepare("SELECT id_meja FROM meja WHERE nomor_meja = ?");
            $check_stmt->bind_param("i", $nomor_meja);
            $check_stmt->execute();
            $check_result = $check_stmt->get_result();
            
            if ($check_result->num_rows > 0) {
                $error = 'Nomor meja sudah ada!';
            } else {
                $stmt = $conn->prepare("INSERT INTO meja (nomor_meja, aktif, in_used, pos_x, pos_y) VALUES (?, ?, ?, ?, ?)");
                $stmt->bind_param("issdd", $nomor_meja, $aktif, $in_used, $pos_x, $pos_y);
                
                if ($stmt->execute()) {
                    $message = 'Data meja berhasil ditambahkan!';
                } else {
                    $error = 'Error: ' . $conn->error;
                }
            }
            break;
            
        case 'update':
            $id_meja = $_POST['id_meja'];
            $nomor_meja = $_POST['nomor_meja'];
            $aktif = $_POST['aktif'];
            $in_used = $_POST['in_used'];
            $pos_x = $_POST['pos_x'];
            $pos_y = $_POST['pos_y'];
            
            // Check if nomor_meja already exists for other records
            $check_stmt = $conn->prepare("SELECT id_meja FROM meja WHERE nomor_meja = ? AND id_meja != ?");
            $check_stmt->bind_param("ii", $nomor_meja, $id_meja);
            $check_stmt->execute();
            $check_result = $check_stmt->get_result();
            
            if ($check_result->num_rows > 0) {
                $error = 'Nomor meja sudah ada!';
            } else {
                $stmt = $conn->prepare("UPDATE meja SET nomor_meja = ?, aktif = ?, in_used = ?, pos_x = ?, pos_y = ? WHERE id_meja = ?");
                $stmt->bind_param("issddi", $nomor_meja, $aktif, $in_used, $pos_x, $pos_y, $id_meja);
                
                if ($stmt->execute()) {
                    $message = 'Data meja berhasil diperbarui!';
                } else {
                    $error = 'Error: ' . $conn->error;
                }
            }
            break;
            
        case 'delete':
            $id_meja = $_POST['id_meja'];
            $stmt = $conn->prepare("DELETE FROM meja WHERE id_meja = ?");
            $stmt->bind_param("i", $id_meja);
            if ($stmt->execute()) {
                $message = 'Data meja berhasil dihapus!';
            } else {
                $error = 'Error: ' . $conn->error;
            }
            break;
    }
}

// Get meja for editing
if (isset($_GET['edit'])) {
    $edit_id = $_GET['edit'];
    $stmt = $conn->prepare("SELECT * FROM meja WHERE id_meja = ?");
    $stmt->bind_param("i", $edit_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $edit_meja = $result->fetch_assoc();
}

// Pagination and search
$search = $_GET['search'] ?? '';
$filter = $_GET['filter'] ?? '';
$page = max(1, $_GET['page'] ?? 1);
$limit = 20;
$offset = ($page - 1) * $limit;

// Build query
$where_conditions = [];
$params = [];
$param_types = '';

if (!empty($search)) {
    $where_conditions[] = "nomor_meja LIKE ?";
    $params[] = "%$search%";
    $param_types .= 's';
}

if (!empty($filter)) {
    if ($filter === 'aktif') {
        $where_conditions[] = "aktif = '1'";
    } elseif ($filter === 'nonaktif') {
        $where_conditions[] = "aktif = '0'";
    } elseif ($filter === 'terpakai') {
        $where_conditions[] = "in_used = '1'";
    } elseif ($filter === 'kosong') {
        $where_conditions[] = "in_used = '0'";
    }
}

$where_clause = !empty($where_conditions) ? 'WHERE ' . implode(' AND ', $where_conditions) : '';

// Get total records
$count_query = "SELECT COUNT(*) as total FROM meja $where_clause";
if (!empty($params)) {
    $count_stmt = $conn->prepare($count_query);
    $count_stmt->bind_param($param_types, ...$params);
    $count_stmt->execute();
    $count_result = $count_stmt->get_result();
} else {
    $count_result = $conn->query($count_query);
}
$total_records = $count_result->fetch_assoc()['total'];
$total_pages = ceil($total_records / $limit);

// Get meja data
$query = "SELECT * FROM meja $where_clause ORDER BY nomor_meja ASC LIMIT $limit OFFSET $offset";
if (!empty($params)) {
    $stmt = $conn->prepare($query);
    $stmt->bind_param($param_types, ...$params);
    $stmt->execute();
    $result = $stmt->get_result();
} else {
    $result = $conn->query($query);
}

$meja_list = [];
while ($row = $result->fetch_assoc()) {
    $meja_list[] = $row;
}
?>
<!doctype html>
<html lang="id" data-bs-theme="light">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Data Meja - Admin</title>
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
                    <h2 class="mb-1"><i class="bi bi-table me-2"></i>Data Meja</h2>
                    <p class="text-muted mb-0">Kelola data meja dan tata letak</p>
                </div>
                <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addModal">
                    <i class="bi bi-plus-lg me-2"></i>Tambah Meja
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
                        <span>Daftar Meja</span>
                    </div>
                    <div class="d-flex gap-2">
                        <form class="d-flex" method="GET" action="">
                            <div class="input-group" style="width: 250px;">
                                <span class="input-group-text bg-white border-end-0">
                                    <i class="bi bi-search"></i>
                                </span>
                                <input type="text" name="search" class="form-control border-start-0 ps-0" placeholder="Cari nomor meja..." value="<?php echo htmlspecialchars($search); ?>">
                            </div>
                        </form>
                        <select class="form-select" style="width: 150px;" onchange="window.location.href='?filter='+this.value+'&search=<?php echo urlencode($search); ?>'">
                            <option value="">Semua Status</option>
                            <option value="aktif" <?php echo $filter === 'aktif' ? 'selected' : ''; ?>>Aktif</option>
                            <option value="nonaktif" <?php echo $filter === 'nonaktif' ? 'selected' : ''; ?>>Non-aktif</option>
                            <option value="terpakai" <?php echo $filter === 'terpakai' ? 'selected' : ''; ?>>Terpakai</option>
                            <option value="kosong" <?php echo $filter === 'kosong' ? 'selected' : ''; ?>>Kosong</option>
                        </select>
                    </div>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead>
                                <tr>
                                    <th style="width: 5%; text-align:left;">ID</th>
                                    <th style="width: 12%; text-align:left;">No. Meja</th>
                                    <th style="width: 12%; text-align:center;">Status</th>
                                    <th style="width: 12%; text-align:center;">Kondisi</th>
                                    <th style="width: 12%; text-align:center;">Posisi X</th>
                                    <th style="width: 12%; text-align:center;">Posisi Y</th>
                                    <th style="width: auto; text-align:center;">Update Terakhir</th>
                                    <th style="width: 15%; text-align:center;">Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                            <?php if (!empty($meja_list)): ?>
                                <?php foreach ($meja_list as $meja): ?>
                                <tr>
                                    <td class="text-start"><?php echo htmlspecialchars($meja['id_meja']); ?></td>
                                    <td class="text-start"><strong>Meja <?php echo htmlspecialchars($meja['nomor_meja']); ?></strong></td>
                                    <td class="text-center">
                                        <?php if ($meja['aktif'] == '1'): ?>
                                            <span class="badge bg-success">Aktif</span>
                                        <?php else: ?>
                                            <span class="badge bg-secondary">Non-aktif</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-center">
                                        <?php if ($meja['in_used'] == '1'): ?>
                                            <span class="badge bg-warning text-dark">Terpakai</span>
                                        <?php else: ?>
                                            <span class="badge bg-success">Kosong</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-center"><?php echo htmlspecialchars($meja['pos_x']); ?></td>
                                    <td class="text-center"><?php echo htmlspecialchars($meja['pos_y']); ?></td>
                                    <td class="text-center">
                                        <?php 
                                        if ($meja['update_at']) {
                                            echo date('d/m/Y H:i', strtotime($meja['update_at']));
                                        } else {
                                            echo '-';
                                        }
                                        ?>
                                    </td>
                                    <td class="text-center">
                                        <div class="table-actions">
                                            <button class="btn btn-sm btn-outline-warning" onclick="editMeja(<?php echo htmlspecialchars(json_encode($meja)); ?>)" title="Edit">
                                                <i class="bi bi-pencil"></i>
                                            </button>
                                            <button class="btn btn-sm btn-outline-danger" onclick="confirmDelete(<?php echo $meja['id_meja']; ?>, <?php echo $meja['nomor_meja']; ?>)" title="Hapus">
                                                <i class="bi bi-trash"></i>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="8" class="text-center py-4">
                                        <i class="bi bi-inbox fs-1 d-block mb-2 text-muted"></i>
                                        <p class="text-muted mb-0">Tidak ada data meja</p>
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
                        <small class="text-muted">Menampilkan <?php echo $offset + 1; ?>-<?php echo min($offset + $limit, $total_records); ?> dari <?php echo $total_records; ?> meja</small>
                        <nav>
                            <ul class="pagination pagination-sm mb-0">
                                <li class="page-item <?php echo $page <= 1 ? 'disabled' : ''; ?>">
                                    <a class="page-link" href="?page=<?php echo $page - 1; ?>&search=<?php echo urlencode($search); ?>&filter=<?php echo urlencode($filter); ?>">Previous</a>
                                </li>
                                <?php for ($i = 1; $i <= min($total_pages, 5); $i++): ?>
                                <li class="page-item <?php echo $i == $page ? 'active' : ''; ?>">
                                    <a class="page-link" href="?page=<?php echo $i; ?>&search=<?php echo urlencode($search); ?>&filter=<?php echo urlencode($filter); ?>"><?php echo $i; ?></a>
                                </li>
                                <?php endfor; ?>
                                <li class="page-item <?php echo $page >= $total_pages ? 'disabled' : ''; ?>">
                                    <a class="page-link" href="?page=<?php echo $page + 1; ?>&search=<?php echo urlencode($search); ?>&filter=<?php echo urlencode($filter); ?>">Next</a>
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
                    <h5 class="modal-title"><i class="bi bi-plus-circle me-2"></i>Tambah Meja</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST" class="form-modern">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="create">
                        
                        <!-- Tab Navigation -->
                        <ul class="nav nav-tabs mb-3" id="addTabs" role="tablist">
                            <li class="nav-item" role="presentation">
                                <button class="nav-link active" id="add-basic-tab" data-bs-toggle="tab" data-bs-target="#add-basic" type="button" role="tab">
                                    <i class="bi bi-info-circle"></i> Data Dasar
                                </button>
                            </li>
                            <li class="nav-item" role="presentation">
                                <button class="nav-link" id="add-position-tab" data-bs-toggle="tab" data-bs-target="#add-position" type="button" role="tab">
                                    <i class="bi bi-geo-alt"></i> Posisi
                                </button>
                            </li>
                            <li class="nav-item" role="presentation">
                                <button class="nav-link" id="add-status-tab" data-bs-toggle="tab" data-bs-target="#add-status" type="button" role="tab">
                                    <i class="bi bi-toggle-on"></i> Status
                                </button>
                            </li>
                        </ul>
                        
                        <!-- Tab Content -->
                        <div class="tab-content">
                            <!-- Basic Data Tab -->
                            <div class="tab-pane fade show active" id="add-basic" role="tabpanel">
                                <div class="row mb-3">
                                    <label class="col-sm-3 col-form-label">Nomor Meja <span class="text-danger">*</span></label>
                                    <div class="col-sm-9">
                                        <input type="number" name="nomor_meja" class="form-control" value="<?php echo generateMejaNumber($conn); ?>" min="1" required>
                                        <small class="text-muted">Nomor unik untuk identifikasi meja</small>
                                    </div>
                                </div>
                            </div>

                            <!-- Position Tab -->
                            <div class="tab-pane fade" id="add-position" role="tabpanel">
                                <div class="row mb-3">
                                    <label class="col-sm-3 col-form-label">Posisi X</label>
                                    <div class="col-sm-9">
                                        <input type="number" step="0.01" name="pos_x" class="form-control" value="0">
                                        <small class="text-muted">Koordinat X untuk layout meja</small>
                                    </div>
                                </div>
                                <div class="row mb-3">
                                    <label class="col-sm-3 col-form-label">Posisi Y</label>
                                    <div class="col-sm-9">
                                        <input type="number" step="0.01" name="pos_y" class="form-control" value="0">
                                        <small class="text-muted">Koordinat Y untuk layout meja</small>
                                    </div>
                                </div>
                            </div>

                            <!-- Status Tab -->
                            <div class="tab-pane fade" id="add-status" role="tabpanel">
                                <div class="row mb-3">
                                    <label class="col-sm-3 col-form-label">Status Aktif</label>
                                    <div class="col-sm-9">
                                        <select name="aktif" class="form-select" required>
                                            <option value="1" selected>Aktif</option>
                                            <option value="0">Non-aktif</option>
                                        </select>
                                        <small class="text-muted">Status ketersediaan meja</small>
                                    </div>
                                </div>
                                <div class="row mb-3">
                                    <label class="col-sm-3 col-form-label">Kondisi Meja</label>
                                    <div class="col-sm-9">
                                        <select name="in_used" class="form-select" required>
                                            <option value="0" selected>Kosong</option>
                                            <option value="1">Terpakai</option>
                                        </select>
                                        <small class="text-muted">Status penggunaan meja saat ini</small>
                                    </div>
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
                    <h5 class="modal-title"><i class="bi bi-pencil-square me-2"></i>Edit Meja</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST" class="form-modern">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="update">
                        <input type="hidden" name="id_meja" id="edit_id_meja">
                        
                        <!-- Tab Navigation -->
                        <ul class="nav nav-tabs mb-3" id="editTabs" role="tablist">
                            <li class="nav-item" role="presentation">
                                <button class="nav-link active" id="edit-basic-tab" data-bs-toggle="tab" data-bs-target="#edit-basic" type="button" role="tab">
                                    <i class="bi bi-info-circle"></i> Data Dasar
                                </button>
                            </li>
                            <li class="nav-item" role="presentation">
                                <button class="nav-link" id="edit-position-tab" data-bs-toggle="tab" data-bs-target="#edit-position" type="button" role="tab">
                                    <i class="bi bi-geo-alt"></i> Posisi
                                </button>
                            </li>
                            <li class="nav-item" role="presentation">
                                <button class="nav-link" id="edit-status-tab" data-bs-toggle="tab" data-bs-target="#edit-status" type="button" role="tab">
                                    <i class="bi bi-toggle-on"></i> Status
                                </button>
                            </li>
                        </ul>
                        
                        <!-- Tab Content -->
                        <div class="tab-content">
                            <!-- Basic Data Tab -->
                            <div class="tab-pane fade show active" id="edit-basic" role="tabpanel">
                                <div class="row mb-3">
                                    <label class="col-sm-3 col-form-label">Nomor Meja <span class="text-danger">*</span></label>
                                    <div class="col-sm-9">
                                        <input type="number" name="nomor_meja" id="edit_nomor_meja" class="form-control" min="1" required>
                                        <small class="text-muted">Nomor unik untuk identifikasi meja</small>
                                    </div>
                                </div>
                            </div>

                            <!-- Position Tab -->
                            <div class="tab-pane fade" id="edit-position" role="tabpanel">
                                <div class="row mb-3">
                                    <label class="col-sm-3 col-form-label">Posisi X</label>
                                    <div class="col-sm-9">
                                        <input type="number" step="0.01" name="pos_x" id="edit_pos_x" class="form-control">
                                        <small class="text-muted">Koordinat X untuk layout meja</small>
                                    </div>
                                </div>
                                <div class="row mb-3">
                                    <label class="col-sm-3 col-form-label">Posisi Y</label>
                                    <div class="col-sm-9">
                                        <input type="number" step="0.01" name="pos_y" id="edit_pos_y" class="form-control">
                                        <small class="text-muted">Koordinat Y untuk layout meja</small>
                                    </div>
                                </div>
                            </div>

                            <!-- Status Tab -->
                            <div class="tab-pane fade" id="edit-status" role="tabpanel">
                                <div class="row mb-3">
                                    <label class="col-sm-3 col-form-label">Status Aktif</label>
                                    <div class="col-sm-9">
                                        <select name="aktif" id="edit_aktif" class="form-select" required>
                                            <option value="1">Aktif</option>
                                            <option value="0">Non-aktif</option>
                                        </select>
                                        <small class="text-muted">Status ketersediaan meja</small>
                                    </div>
                                </div>
                                <div class="row mb-3">
                                    <label class="col-sm-3 col-form-label">Kondisi Meja</label>
                                    <div class="col-sm-9">
                                        <select name="in_used" id="edit_in_used" class="form-select" required>
                                            <option value="0">Kosong</option>
                                            <option value="1">Terpakai</option>
                                        </select>
                                        <small class="text-muted">Status penggunaan meja saat ini</small>
                                    </div>
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
        function editMeja(meja) {
            document.getElementById('edit_id_meja').value = meja.id_meja;
            document.getElementById('edit_nomor_meja').value = meja.nomor_meja;
            document.getElementById('edit_pos_x').value = meja.pos_x;
            document.getElementById('edit_pos_y').value = meja.pos_y;
            document.getElementById('edit_aktif').value = meja.aktif;
            document.getElementById('edit_in_used').value = meja.in_used;
            
            var editModal = new bootstrap.Modal(document.getElementById('editModal'));
            editModal.show();
        }
        
        function confirmDelete(id, nomor) {
            if (confirm('Apakah Anda yakin ingin menghapus Meja ' + nomor + '?')) {
                var form = document.createElement('form');
                form.method = 'POST';
                form.innerHTML = '<input type="hidden" name="action" value="delete"><input type="hidden" name="id_meja" value="' + id + '">';
                document.body.appendChild(form);
                form.submit();
            }
        }
        
        <?php if ($edit_meja): ?>
        document.addEventListener('DOMContentLoaded', function() {
            editMeja(<?php echo json_encode($edit_meja); ?>);
        });
        <?php endif; ?>
    </script>
</body>
</html>
