<?php
session_start();
require_once '../../config/koneksi.php';
require_once '../../config/encryption.php';

// Check if user is logged in
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    header('Location: login.php');
    exit();
}

// Initialize variables
$message = '';
$error = '';
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$filter = isset($_GET['filter']) ? $_GET['filter'] : '';
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 20;
$offset = ($page - 1) * $limit;

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        if ($_POST['action'] === 'update') {
            $id_bayar = (int)$_POST['id_bayar'];
            $kategori = trim($_POST['kategori']);
            $no_rek = trim($_POST['no_rek']);
            $biaya_admin = (float)$_POST['biaya_admin'];
            $keterangan = trim($_POST['keterangan']);
            $pramusaji = $_POST['pramusaji'];
            $aktif = $_POST['aktif'];
            
            if (!empty($kategori)) {
                $stmt = $conn->prepare("UPDATE metode_pembayaran SET kategori = ?, no_rek = ?, biaya_admin = ?, keterangan = ?, pramusaji = ?, aktif = ? WHERE id_bayar = ?");
                if ($stmt) {
                    $stmt->bind_param("ssdssii", $kategori, $no_rek, $biaya_admin, $keterangan, $pramusaji, $aktif, $id_bayar);
                    if ($stmt->execute()) {
                        $message = "Metode pembayaran berhasil diperbarui!";
                    } else {
                        $error = "Gagal memperbarui metode pembayaran!";
                    }
                    $stmt->close();
                } else {
                    $error = "Error: " . $conn->error;
                }
            } else {
                $error = "Kategori tidak boleh kosong!";
            }
        }
    }
}

// Handle edit request
$edit_data = null;
if (isset($_GET['edit'])) {
    $edit_id = (int)$_GET['edit'];
    $stmt = $conn->prepare("SELECT * FROM metode_pembayaran WHERE id_bayar = ?");
    if ($stmt) {
        $stmt->bind_param("i", $edit_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $edit_data = $result->fetch_assoc();
        $stmt->close();
    } else {
        $error = "Error: " . $conn->error;
    }
}

// Build query for fetching metode pembayaran
$where_conditions = [];
$params = [];

if (!empty($search)) {
    $where_conditions[] = "(kategori LIKE ? OR no_rek LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

if (!empty($filter)) {
    if ($filter === 'aktif') {
        $where_conditions[] = "aktif = '1'";
    } elseif ($filter === 'nonaktif') {
        $where_conditions[] = "aktif = '0'";
    } elseif ($filter === 'pramusaji') {
        $where_conditions[] = "pramusaji = '1'";
    }
}

$where_clause = !empty($where_conditions) ? 'WHERE ' . implode(' AND ', $where_conditions) : '';

// Get total count
$count_sql = "SELECT COUNT(*) FROM metode_pembayaran $where_clause";
if (!empty($params)) {
    $count_stmt = $conn->prepare($count_sql);
    if ($count_stmt) {
        $types = str_repeat('s', count($params));
        $count_stmt->bind_param($types, ...$params);
        $count_stmt->execute();
        $result = $count_stmt->get_result();
        $total_records = $result->fetch_row()[0];
        $count_stmt->close();
    } else {
        $error = "Error: " . $conn->error;
        $total_records = 0;
    }
} else {
    $result = $conn->query($count_sql);
    if ($result) {
        $total_records = $result->fetch_row()[0];
    } else {
        $error = "Error: " . $conn->error;
        $total_records = 0;
    }
}
$total_pages = ceil($total_records / $limit);

// Get metode pembayaran data
$sql = "SELECT * FROM metode_pembayaran $where_clause ORDER BY id_bayar ASC LIMIT $limit OFFSET $offset";
if (!empty($params)) {
    $stmt = $conn->prepare($sql);
    if ($stmt) {
        $types = str_repeat('s', count($params));
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $result = $stmt->get_result();
        $metode_list = $result->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
    } else {
        $error = "Error: " . $conn->error;
        $metode_list = [];
    }
} else {
    $result = $conn->query($sql);
    if ($result) {
        $metode_list = $result->fetch_all(MYSQLI_ASSOC);
    } else {
        $error = "Error: " . $conn->error;
        $metode_list = [];
    }
}
?>
<!doctype html>
<html lang="id" data-bs-theme="light">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Metode Pembayaran - Admin</title>
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
                    <h2 class="mb-1"><i class="bi bi-credit-card me-2"></i>Metode Pembayaran</h2>
                    <p class="text-muted mb-0">Kelola metode pembayaran yang tersedia</p>
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

            <div class="card-modern">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <div class="d-flex align-items-center">
                        <i class="bi bi-table me-2"></i>
                        <span>Daftar Metode Pembayaran</span>
                    </div>
                    <div class="d-flex gap-2">
                        <form class="d-flex" method="GET" action="">
                            <div class="input-group" style="width: 250px;">
                                <span class="input-group-text bg-white border-end-0">
                                    <i class="bi bi-search"></i>
                                </span>
                                <input type="text" name="search" class="form-control border-start-0 ps-0" placeholder="Cari metode..." value="<?php echo htmlspecialchars($search); ?>">
                            </div>
                        </form>
                        <select class="form-select" style="width: 150px;" onchange="window.location.href='?filter='+this.value+'&search=<?php echo urlencode($search); ?>'">
                            <option value="">Semua Status</option>
                            <option value="aktif" <?php echo $filter === 'aktif' ? 'selected' : ''; ?>>Aktif</option>
                            <option value="nonaktif" <?php echo $filter === 'nonaktif' ? 'selected' : ''; ?>>Non-aktif</option>
                            <option value="pramusaji" <?php echo $filter === 'pramusaji' ? 'selected' : ''; ?>>Untuk Pramusaji</option>
                        </select>
                    </div>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead>
                                <tr>
                                    <th style="width: 50px;">No</th>
                                    <th>Kategori</th>
                                    <th style="width: 200px;">No. Rekening</th>
                                    <th style="width: 150px;">Biaya Admin</th>
                                    <th style="width: 120px;">Pramusaji</th>
                                    <th style="width: 100px;">Status</th>
                                    <th style="width: 100px;">Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                            <?php if (!empty($metode_list)): ?>
                                <?php $no = $offset + 1; foreach ($metode_list as $metode): ?>
                                <tr>
                                    <td class="text-center"><?php echo $no++; ?></td>
                                    <td><strong><?php echo htmlspecialchars($metode['kategori']); ?></strong></td>
                                    <td><?php echo htmlspecialchars($metode['no_rek']); ?></td>
                                    <td>
                                        <strong class="text-success">Rp <?php echo number_format((float)$metode['biaya_admin'], 0, ',', '.'); ?></strong>
                                    </td>
                                    <td>
                                        <?php if ($metode['pramusaji'] == '1'): ?>
                                            <span class="badge bg-success">Ya</span>
                                        <?php else: ?>
                                            <span class="badge bg-secondary">Tidak</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($metode['aktif'] == '1'): ?>
                                            <span class="badge bg-success">Aktif</span>
                                        <?php else: ?>
                                            <span class="badge bg-secondary">Non-aktif</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <button class="btn btn-sm btn-outline-warning" onclick="editMetode(<?php echo htmlspecialchars(json_encode($metode)); ?>)" title="Edit">
                                            <i class="bi bi-pencil"></i>
                                        </button>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="7" class="text-center py-4">
                                        <i class="bi bi-inbox fs-1 d-block mb-2 text-muted"></i>
                                        <p class="text-muted mb-0">Tidak ada data metode pembayaran</p>
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
                        <small class="text-muted">Menampilkan <?php echo $offset + 1; ?>-<?php echo min($offset + $limit, $total_records); ?> dari <?php echo $total_records; ?> metode pembayaran</small>
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

    <!-- Edit Modal -->
    <div class="modal fade" id="editModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="bi bi-pencil-square me-2"></i>Edit Metode Pembayaran</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST" class="form-modern">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="update">
                        <input type="hidden" name="id_bayar" id="edit_id_bayar">
                        
                        <div class="row mb-3">
                            <label class="col-sm-3 col-form-label">Kategori <span class="text-danger">*</span></label>
                            <div class="col-sm-9">
                                <input type="text" name="kategori" id="edit_kategori" class="form-control" required>
                            </div>
                        </div>
                        
                        <div class="row mb-3">
                            <label class="col-sm-3 col-form-label">Nomor Rekening</label>
                            <div class="col-sm-9">
                                <input type="text" name="no_rek" id="edit_no_rek" class="form-control" placeholder="Nomor rekening atau kode">
                            </div>
                        </div>
                        
                        <div class="row mb-3">
                            <label class="col-sm-3 col-form-label">Biaya Admin</label>
                            <div class="col-sm-9">
                                <div class="input-group">
                                    <span class="input-group-text">Rp</span>
                                    <input type="number" step="0.01" name="biaya_admin" id="edit_biaya_admin" class="form-control" placeholder="0">
                                </div>
                            </div>
                        </div>
                        
                        <div class="row mb-3">
                            <label class="col-sm-3 col-form-label">Keterangan</label>
                            <div class="col-sm-9">
                                <textarea name="keterangan" id="edit_keterangan" class="form-control" rows="3" placeholder="Keterangan tambahan"></textarea>
                            </div>
                        </div>
                        
                        <div class="row mb-3">
                            <label class="col-sm-3 col-form-label">Untuk Pramusaji</label>
                            <div class="col-sm-9">
                                <select name="pramusaji" id="edit_pramusaji" class="form-select" required>
                                    <option value="0">Tidak</option>
                                    <option value="1">Ya</option>
                                </select>
                                <small class="text-muted">Apakah metode ini dapat digunakan oleh pramusaji</small>
                            </div>
                        </div>
                        
                        <div class="row mb-3">
                            <label class="col-sm-3 col-form-label">Status</label>
                            <div class="col-sm-9">
                                <select name="aktif" id="edit_aktif" class="form-select" required>
                                    <option value="1">Aktif</option>
                                    <option value="0">Non-aktif</option>
                                </select>
                                <small class="text-muted">Status ketersediaan metode pembayaran</small>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                        <button type="submit" class="btn btn-primary"><i class="bi bi-save me-2"></i>Simpan Perubahan</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <?php include '_scripts_new.php'; ?>
    
    <script>
        function editMetode(metode) {
            document.getElementById('edit_id_bayar').value = metode.id_bayar;
            document.getElementById('edit_kategori').value = metode.kategori;
            document.getElementById('edit_no_rek').value = metode.no_rek || '';
            document.getElementById('edit_biaya_admin').value = metode.biaya_admin;
            document.getElementById('edit_keterangan').value = metode.keterangan || '';
            document.getElementById('edit_pramusaji').value = metode.pramusaji;
            document.getElementById('edit_aktif').value = metode.aktif;
            
            var editModal = new bootstrap.Modal(document.getElementById('editModal'));
            editModal.show();
        }
        
        <?php if ($edit_data): ?>
        document.addEventListener('DOMContentLoaded', function() {
            editMetode(<?php echo json_encode($edit_data); ?>);
        });
        <?php endif; ?>
    </script>
</body>
</html>
