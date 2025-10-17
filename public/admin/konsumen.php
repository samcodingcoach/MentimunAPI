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
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'add':
                $nama_konsumen = $_POST['nama_konsumen'] ?? '';
                $no_hp = $_POST['no_hp'] ?? '';
                $alamat = $_POST['alamat'] ?? '';
                $email = $_POST['email'] ?? '';
                $aktif = $_POST['aktif'] ?? '1';
                
                if (!empty($nama_konsumen)) {
                    $stmt = $conn->prepare("INSERT INTO konsumen (nama_konsumen, no_hp, alamat, email, aktif) VALUES (?, ?, ?, ?, ?)");
                    $stmt->bind_param("sssss", $nama_konsumen, $no_hp, $alamat, $email, $aktif);
                    
                    if ($stmt->execute()) {
                        $message = 'Konsumen berhasil ditambahkan!';
                    } else {
                        $error = 'Error: ' . $stmt->error;
                    }
                    $stmt->close();
                } else {
                    $error = 'Nama konsumen harus diisi!';
                }
                break;
                
            case 'edit':
                $id_konsumen = $_POST['id_konsumen'] ?? '';
                $nama_konsumen = $_POST['nama_konsumen'] ?? '';
                $no_hp = $_POST['no_hp'] ?? '';
                $alamat = $_POST['alamat'] ?? '';
                $email = $_POST['email'] ?? '';
                $aktif = $_POST['aktif'] ?? '1';
                
                if (!empty($id_konsumen) && !empty($nama_konsumen)) {
                    $stmt = $conn->prepare("UPDATE konsumen SET nama_konsumen = ?, no_hp = ?, alamat = ?, email = ?, aktif = ? WHERE id_konsumen = ?");
                    $stmt->bind_param("sssssi", $nama_konsumen, $no_hp, $alamat, $email, $aktif, $id_konsumen);
                    
                    if ($stmt->execute()) {
                        $message = 'Konsumen berhasil diupdate!';
                    } else {
                        $error = 'Error: ' . $stmt->error;
                    }
                    $stmt->close();
                } else {
                    $error = 'Data tidak valid!';
                }
                break;
                
            case 'delete':
                $id_konsumen = $_POST['id_konsumen'] ?? '';
                
                if (!empty($id_konsumen)) {
                    $stmt = $conn->prepare("DELETE FROM konsumen WHERE id_konsumen = ?");
                    $stmt->bind_param("i", $id_konsumen);
                    
                    if ($stmt->execute()) {
                        $message = 'Konsumen berhasil dihapus!';
                    } else {
                        $error = 'Error: ' . $stmt->error;
                    }
                    $stmt->close();
                } else {
                    $error = 'ID konsumen tidak valid!';
                }
                break;
        }
    }
}

// Pagination parameters
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
    $where_conditions[] = "(nama_konsumen LIKE ? OR no_hp LIKE ? OR email LIKE ?)";
    $search_param = "%$search%";
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
    $types .= 'sss';
}

if ($filter !== '' && in_array($filter, ['0', '1'])) {
    $where_conditions[] = "aktif = ?";
    $params[] = $filter;
    $types .= 's';
}

$where_clause = !empty($where_conditions) ? 'WHERE ' . implode(' AND ', $where_conditions) : '';

// Get total count for pagination
$count_sql = "SELECT COUNT(*) as total FROM konsumen $where_clause";
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

// Fetch data using the specified query with pagination
$sql = "
    SELECT
        konsumen.id_konsumen,
        konsumen.nama_konsumen,
        konsumen.no_hp,
        konsumen.alamat,
        konsumen.email,
        konsumen.aktif,
        COALESCE(SUM(pesanan.total_cart), 0) as total_cart
    FROM
        konsumen
    LEFT JOIN
        pesanan ON konsumen.id_konsumen = pesanan.id_konsumen
        AND pesanan.status_checkout = 1
    $where_clause
    GROUP BY 
        konsumen.id_konsumen
    ORDER BY nama_konsumen ASC
    LIMIT $limit OFFSET $offset
";

if (!empty($params)) {
    $stmt = $conn->prepare($sql);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $result = $stmt->get_result();
    $konsumen_data = $result->fetch_all(MYSQLI_ASSOC);
} else {
    $result = $conn->query($sql);
    $konsumen_data = $result->fetch_all(MYSQLI_ASSOC);
}
?>
<!doctype html>
<html lang="id" data-bs-theme="light">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Data Konsumen - Admin</title>
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
                    <h2 class="mb-1"><i class="bi bi-person-check me-2"></i>Data Konsumen</h2>
                    <p class="text-muted mb-0">Kelola data konsumen dan riwayat transaksi</p>
                </div>
                <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addModal">
                    <i class="bi bi-plus-lg me-2"></i>Tambah Konsumen
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
                        <span>Daftar Konsumen</span>
                    </div>
                    <div class="d-flex gap-2">
                        <form class="d-flex" method="GET" action="">
                            <div class="input-group" style="width: 250px;">
                                <span class="input-group-text bg-white border-end-0">
                                    <i class="bi bi-search"></i>
                                </span>
                                <input type="text" name="search" class="form-control border-start-0 ps-0" placeholder="Cari konsumen..." value="<?php echo htmlspecialchars($search); ?>">
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
                                    <th style="width: auto; text-align:left;">Nama</th>
                                    <th style="width: 10%; text-align:center;">No. HP</th>
                                    <th style="width: 15%; text-align:center">Email</th>
                                    <th style="width: 20%; text-align:right">Total Pembelian</th>
                                    <th style="width: 7%; text-align:center">Status</th>
                                    <th style="width: 15%; text-align:center">Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                            <?php if (!empty($konsumen_data)): ?>
                                <?php foreach ($konsumen_data as $index => $row): ?>
                                <tr>
                                    <td class="text-start"><?php echo $offset + $index + 1; ?></td>
                                    <td class="text-start">
                                        <a href="#" class="text-decoration-none fw-semibold" onclick="showDetailModal(
                                            '<?php echo htmlspecialchars($row['id_konsumen']); ?>',
                                            '<?php echo htmlspecialchars(addslashes($row['nama_konsumen'])); ?>',
                                            '<?php echo htmlspecialchars($row['no_hp']); ?>',
                                           
                                            '<?php echo htmlspecialchars($row['email']); ?>',
                                            '<?php echo $row['aktif']; ?>',
                                            '<?php echo number_format($row['total_cart'], 0, ',', '.'); ?>'
                                        ); return false;">
                                            <?php echo htmlspecialchars($row['nama_konsumen']); ?>
                                        </a>
                                    </td>
                                    <td class="text-center"><?php echo htmlspecialchars($row['no_hp'] ?: '-'); ?></td>
                                    <td class="text-center"><?php echo htmlspecialchars($row['email'] ?: '-'); ?></td>
                                    <td class="text-end"><strong class="text-success">Rp <?php echo number_format($row['total_cart'], 0, ',', '.'); ?></strong></td>
                                    <td class="text-center">
                                        <?php if ($row['aktif'] == '1'): ?>
                                            <span class="badge bg-success">Aktif</span>
                                        <?php else: ?>
                                            <span class="badge bg-secondary">Nonaktif</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-center">
                                        <div class="table-actions">
                                            <button class="btn btn-sm btn-outline-warning" onclick="editKonsumen(<?php echo htmlspecialchars(json_encode($row)); ?>)" title="Edit">
                                                <i class="bi bi-pencil"></i>
                                            </button>
                                            <button class="btn btn-sm btn-outline-danger" onclick="confirmDelete(<?php echo $row['id_konsumen']; ?>, '<?php echo htmlspecialchars(addslashes($row['nama_konsumen'])); ?>')" title="Hapus">
                                                <i class="bi bi-trash"></i>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="7" class="text-center py-4">
                                        <i class="bi bi-inbox fs-1 d-block mb-2 text-muted"></i>
                                        <p class="text-muted mb-0">Tidak ada data konsumen</p>
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
                        <small class="text-muted">Menampilkan <?php echo $offset + 1; ?>-<?php echo min($offset + $limit, $total_records); ?> dari <?php echo $total_records; ?> konsumen</small>
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
                    <h5 class="modal-title"><i class="bi bi-plus-circle me-2"></i>Tambah Konsumen</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST" class="form-modern">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="add">
                        <div class="row mb-3">
                            <label class="col-sm-3 col-form-label">Nama Konsumen <span class="text-danger">*</span></label>
                            <div class="col-sm-9">
                                <input type="text" name="nama_konsumen" class="form-control" required placeholder="Masukkan nama konsumen">
                            </div>
                        </div>
                        <div class="row mb-3">
                            <label class="col-sm-3 col-form-label">No. HP</label>
                            <div class="col-sm-9">
                                <input type="text" name="no_hp" class="form-control" placeholder="Nomor HP konsumen">
                            </div>
                        </div>
                        <div class="row mb-3">
                            <label class="col-sm-3 col-form-label">Email</label>
                            <div class="col-sm-9">
                                <input type="email" name="email" class="form-control" placeholder="email@example.com">
                            </div>
                        </div>
                        <div class="row mb-3">
                            <label class="col-sm-3 col-form-label">Alamat</label>
                            <div class="col-sm-9">
                                <textarea name="alamat" class="form-control" rows="3" placeholder="Alamat konsumen"></textarea>
                            </div>
                        </div>
                        <div class="row mb-3">
                            <label class="col-sm-3 col-form-label">Status</label>
                            <div class="col-sm-9">
                                <select name="aktif" class="form-select">
                                    <option value="1">Aktif</option>
                                    <option value="0">Nonaktif</option>
                                </select>
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
                    <h5 class="modal-title"><i class="bi bi-pencil-square me-2"></i>Edit Konsumen</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST" class="form-modern">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="edit">
                        <input type="hidden" name="id_konsumen" id="edit_id_konsumen">
                        <div class="row mb-3">
                            <label class="col-sm-3 col-form-label">Nama Konsumen <span class="text-danger">*</span></label>
                            <div class="col-sm-9">
                                <input type="text" name="nama_konsumen" id="edit_nama_konsumen" class="form-control" required>
                            </div>
                        </div>
                        <div class="row mb-3">
                            <label class="col-sm-3 col-form-label">No. HP</label>
                            <div class="col-sm-9">
                                <input type="text" name="no_hp" id="edit_no_hp" class="form-control">
                            </div>
                        </div>
                        <div class="row mb-3">
                            <label class="col-sm-3 col-form-label">Email</label>
                            <div class="col-sm-9">
                                <input type="email" name="email" id="edit_email" class="form-control">
                            </div>
                        </div>
                        <div class="row mb-3">
                            <label class="col-sm-3 col-form-label">Alamat</label>
                            <div class="col-sm-9">
                                <textarea name="alamat" id="edit_alamat" class="form-control" rows="3"></textarea>
                            </div>
                        </div>
                        <div class="row mb-3">
                            <label class="col-sm-3 col-form-label">Status</label>
                            <div class="col-sm-9">
                                <select name="aktif" id="edit_aktif" class="form-select">
                                    <option value="1">Aktif</option>
                                    <option value="0">Nonaktif</option>
                                </select>
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

    <!-- Detail Modal -->
    <div class="modal fade" id="detailModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="bi bi-info-circle me-2"></i>Detail Konsumen</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label text-muted">ID Konsumen</label>
                            <p class="fw-semibold" id="detail-id">-</p>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label text-muted">Status</label>
                            <p id="detail-status">-</p>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label text-muted">Nama Konsumen</label>
                            <p class="fw-semibold" id="detail-nama">-</p>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label text-muted">No. HP</label>
                            <p id="detail-hp">-</p>
                        </div>
                        <div class="col-12">
                            <label class="form-label text-muted">Alamat</label>
                            <p id="detail-alamat">-</p>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label text-muted">Email</label>
                            <p id="detail-email">-</p>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label text-muted">Total Pembelian</label>
                            <p class="fw-bold text-success fs-5" id="detail-total">-</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <?php include '_scripts_new.php'; ?>
    
    <script>
        function editKonsumen(konsumen) {
            document.getElementById('edit_id_konsumen').value = konsumen.id_konsumen;
            document.getElementById('edit_nama_konsumen').value = konsumen.nama_konsumen;
            document.getElementById('edit_no_hp').value = konsumen.no_hp || '';
            document.getElementById('edit_alamat').value = konsumen.alamat || '';
            document.getElementById('edit_email').value = konsumen.email || '';
            document.getElementById('edit_aktif').value = konsumen.aktif;
            
            var editModal = new bootstrap.Modal(document.getElementById('editModal'));
            editModal.show();
        }
        
        function showDetailModal(id, nama, hp, alamat, email, aktif, total) {
            document.getElementById('detail-id').textContent = id;
            document.getElementById('detail-nama').textContent = nama;
            document.getElementById('detail-hp').textContent = hp || '-';
            document.getElementById('detail-alamat').textContent = alamat || '-';
            document.getElementById('detail-email').textContent = email || '-';
            document.getElementById('detail-total').textContent = 'Rp ' + total;
            
            const statusElement = document.getElementById('detail-status');
            if (aktif == '1') {
                statusElement.innerHTML = '<span class="badge bg-success">Aktif</span>';
            } else {
                statusElement.innerHTML = '<span class="badge bg-secondary">Nonaktif</span>';
            }
            
            var detailModal = new bootstrap.Modal(document.getElementById('detailModal'));
            detailModal.show();
        }
        
        function confirmDelete(id, nama) {
            if (confirm('Apakah Anda yakin ingin menghapus konsumen "' + nama + '"?')) {
                var form = document.createElement('form');
                form.method = 'POST';
                form.innerHTML = '<input type="hidden" name="action" value="delete"><input type="hidden" name="id_konsumen" value="' + id + '">';
                document.body.appendChild(form);
                form.submit();
            }
        }
    </script>
</body>
</html>
