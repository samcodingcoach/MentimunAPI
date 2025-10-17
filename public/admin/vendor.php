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

// Function to generate vendor code
function generateVendorCode($conn) {
    $year = date('y'); // 2 digit year
    $prefix = "VD{$year}-";
    
    // Get the last vendor code for this year
    $stmt = $conn->prepare("SELECT kode_vendor FROM vendor WHERE kode_vendor LIKE ? ORDER BY kode_vendor DESC LIMIT 1");
    $like_pattern = $prefix . '%';
    $stmt->bind_param("s", $like_pattern);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($row = $result->fetch_assoc()) {
        // Extract number from last code and increment
        $last_code = $row['kode_vendor'];
        $number = (int)substr($last_code, -4) + 1;
    } else {
        $number = 1;
    }
    
    return $prefix . str_pad($number, 4, '0', STR_PAD_LEFT);
}

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'create':
                $nama_vendor = trim($_POST['nama_vendor']);
                $alamat = trim($_POST['alamat']);
                $kota = trim($_POST['kota']);
                $hp = trim($_POST['hp']);
                $nomor_rekening1 = trim($_POST['nomor_rekening1']);
                $nomor_rekening2 = trim($_POST['nomor_rekening2']);
                $person = trim($_POST['person']);
                $email = trim($_POST['email']);
                $status = isset($_POST['status']) ? '1' : '0';
                $keterangan = trim($_POST['keterangan']);
                
                if (!empty($nama_vendor) && !empty($alamat) && !empty($kota) && !empty($hp) && !empty($email)) {
                    // Check if email already exists
                    $stmt = $conn->prepare("SELECT id_vendor FROM vendor WHERE email = ?");
                    $stmt->bind_param("s", $email);
                    $stmt->execute();
                    $result = $stmt->get_result();
                    if ($result->fetch_assoc()) {
                        $error = 'Email sudah digunakan!';
                    } else {
                        $kode_vendor = generateVendorCode($conn);
                        $stmt = $conn->prepare("INSERT INTO vendor (nama_vendor, alamat, kota, hp, nomor_rekening1, nomor_rekening2, person, email, status, keterangan, kode_vendor) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                        $stmt->bind_param("sssssssssss", $nama_vendor, $alamat, $kota, $hp, $nomor_rekening1, $nomor_rekening2, $person, $email, $status, $keterangan, $kode_vendor);
                        if ($stmt->execute()) {
                            $message = 'Data vendor berhasil ditambahkan!';
                        } else {
                            $error = 'Error: ' . $conn->error;
                        }
                    }
                } else {
                    $error = 'Nama vendor, alamat, kota, HP, dan email wajib diisi!';
                }
                break;
                
            case 'update':
                $id_vendor = $_POST['id_vendor'];
                $nama_vendor = trim($_POST['nama_vendor']);
                $alamat = trim($_POST['alamat']);
                $kota = trim($_POST['kota']);
                $hp = trim($_POST['hp']);
                $nomor_rekening1 = trim($_POST['nomor_rekening1']);
                $nomor_rekening2 = trim($_POST['nomor_rekening2']);
                $person = trim($_POST['person']);
                $email = trim($_POST['email']);
                $status = isset($_POST['status']) ? '1' : '0';
                $keterangan = trim($_POST['keterangan']);
                
                if (!empty($nama_vendor) && !empty($alamat) && !empty($kota) && !empty($hp) && !empty($email)) {
                    // Check if email already exists for other vendors
                    $stmt = $conn->prepare("SELECT id_vendor FROM vendor WHERE email = ? AND id_vendor != ?");
                    $stmt->bind_param("si", $email, $id_vendor);
                    $stmt->execute();
                    $result = $stmt->get_result();
                    if ($result->fetch_assoc()) {
                        $error = 'Email sudah digunakan oleh vendor lain!';
                    } else {
                        $stmt = $conn->prepare("UPDATE vendor SET nama_vendor = ?, alamat = ?, kota = ?, hp = ?, nomor_rekening1 = ?, nomor_rekening2 = ?, person = ?, email = ?, status = ?, keterangan = ? WHERE id_vendor = ?");
                        $stmt->bind_param("ssssssssssi", $nama_vendor, $alamat, $kota, $hp, $nomor_rekening1, $nomor_rekening2, $person, $email, $status, $keterangan, $id_vendor);
                        if ($stmt->execute()) {
                            $message = 'Data vendor berhasil diperbarui!';
                        } else {
                            $error = 'Error: ' . $conn->error;
                        }
                    }
                } else {
                    $error = 'Nama vendor, alamat, kota, HP, dan email harus diisi!';
                }
                break;
                
            case 'delete':
                $id_vendor = $_POST['id_vendor'];
                $stmt = $conn->prepare("DELETE FROM vendor WHERE id_vendor = ?");
                $stmt->bind_param("i", $id_vendor);
                if ($stmt->execute()) {
                    $message = 'Data vendor berhasil dihapus!';
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
    $where_conditions[] = "(nama_vendor LIKE ? OR email LIKE ? OR hp LIKE ? OR kota LIKE ? OR kode_vendor LIKE ?)";
    $search_param = "%$search%";
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
    $types .= 'sssss';
}

if (!empty($filter) && in_array($filter, ['1', '0'])) {
    $where_conditions[] = "status = ?";
    $params[] = $filter;
    $types .= 's';
}

$where_clause = !empty($where_conditions) ? 'WHERE ' . implode(' AND ', $where_conditions) : '';

// Get total count for pagination
$count_sql = "SELECT COUNT(*) as total FROM vendor $where_clause";
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

// Get vendor data with pagination
$sql = "SELECT * FROM vendor $where_clause ORDER BY id_vendor DESC LIMIT $limit OFFSET $offset";
if (!empty($params)) {
    $stmt = $conn->prepare($sql);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $result = $stmt->get_result();
    $vendors = $result->fetch_all(MYSQLI_ASSOC);
} else {
    $result = $conn->query($sql);
    if ($result) {
        $vendors = $result->fetch_all(MYSQLI_ASSOC);
    } else {
        $error = 'Error fetching data: ' . $conn->error;
        $vendors = [];
    }
}

// Get single vendor for editing
$edit_vendor = null;
if (isset($_GET['edit'])) {
    $stmt = $conn->prepare("SELECT * FROM vendor WHERE id_vendor = ?");
    $stmt->bind_param("i", $_GET['edit']);
    $stmt->execute();
    $result = $stmt->get_result();
    $edit_vendor = $result->fetch_assoc();
}
?>
<!doctype html>
<html lang="id" data-bs-theme="light">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Data Vendor - Admin</title>
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
                    <h2 class="mb-1"><i class="bi bi-truck me-2"></i>Data Vendor</h2>
                    <p class="text-muted mb-0">Kelola data vendor dan supplier</p>
                </div>
                <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addModal">
                    <i class="bi bi-plus-lg me-2"></i>Tambah Vendor
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
                        <span>Daftar Vendor</span>
                    </div>
                    <div class="d-flex gap-2">
                        <form class="d-flex" method="GET" action="">
                            <div class="input-group" style="width: 250px;">
                                <span class="input-group-text bg-white border-end-0">
                                    <i class="bi bi-search"></i>
                                </span>
                                <input type="text" name="search" class="form-control border-start-0 ps-0" placeholder="Cari vendor..." value="<?php echo htmlspecialchars($search); ?>">
                            </div>
                        </form>
                        <select class="form-select" style="width: 150px;" onchange="window.location.href='?filter='+this.value+'&search=<?php echo urlencode($search); ?>'">
                            <option value="">Semua Status</option>
                            <option value="1" <?php echo $filter === '1' ? 'selected' : ''; ?>>Aktif</option>
                            <option value="0" <?php echo $filter === '0' ? 'selected' : ''; ?>>Tidak Aktif</option>
                        </select>
                    </div>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead>
                                <tr>
                                    <th style="width: 5%; text-align:left;">No</th>
                                    <th style="width: 10%; text-align:center;">Kode</th>
                                    <th style="width: auto; text-align:left;">Nama</th>
                                    <th style="width: 15%; text-align:center;">Kota</th>
                                    <th style="width: 10%; text-align:center;">HP</th>
                                    <th style="width: 15%; text-align:center;">Email</th>
                                    <th style="width: 5%; text-align:center;">Status</th>
                                    <th style="width: 10%; text-align:center;">Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                            <?php if (!empty($vendors)): ?>
                                <?php foreach ($vendors as $index => $vendor): ?>
                                <tr>
                                    <td class="text-start"><?php echo $offset + $index + 1; ?></td>
                                    <td class="text-center"><span class="badge bg-info"><?php echo htmlspecialchars($vendor['kode_vendor']); ?></span></td>
                                    <td class="text-start"><strong><?php echo htmlspecialchars($vendor['nama_vendor']); ?></strong></td>
                                    <td class="text-center"><?php echo htmlspecialchars($vendor['kota']); ?></td>
                                    <td class="text-center"><?php echo htmlspecialchars($vendor['hp']); ?></td>
                                    <td class="text-center"><?php echo htmlspecialchars($vendor['email']); ?></td>
                                    <td class="text-center">
                                        <span class="badge bg-<?php echo $vendor['status'] == '1' ? 'success' : 'secondary'; ?>">
                                            <?php echo $vendor['status'] == '1' ? 'Aktif' : 'Tidak Aktif'; ?>
                                        </span>
                                    </td>
                                    <td class="text-center">
                                        <div class="table-actions">
                                            <button class="btn btn-sm btn-outline-warning" onclick="editVendor(<?php echo htmlspecialchars(json_encode($vendor)); ?>)" title="Edit">
                                                <i class="bi bi-pencil"></i>
                                            </button>
                                            <button class="btn btn-sm btn-outline-danger" onclick="confirmDelete(<?php echo $vendor['id_vendor']; ?>, '<?php echo htmlspecialchars(addslashes($vendor['nama_vendor'])); ?>')" title="Hapus">
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
                                        <p class="text-muted mb-0">Tidak ada data vendor</p>
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
                                Menampilkan <?php echo $offset + 1; ?>-<?php echo min($offset + $limit, $total_records); ?> dari <?php echo $total_records; ?> vendor
                            <?php else: ?>
                                Tidak ada data vendor
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
                    <h5 class="modal-title"><i class="bi bi-plus-circle me-2"></i>Tambah Vendor</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST" class="form-modern">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="create">
                        
                        <!-- Tab Navigation -->
                        <ul class="nav nav-tabs mb-3" id="addTabs" role="tablist">
                            <li class="nav-item" role="presentation">
                                <button class="nav-link active" id="add-basic-tab" data-bs-toggle="tab" data-bs-target="#add-basic" type="button" role="tab">
                                    <i class="bi bi-person"></i> Data Dasar
                                </button>
                            </li>
                            <li class="nav-item" role="presentation">
                                <button class="nav-link" id="add-contact-tab" data-bs-toggle="tab" data-bs-target="#add-contact" type="button" role="tab">
                                    <i class="bi bi-telephone"></i> Kontak
                                </button>
                            </li>
                            <li class="nav-item" role="presentation">
                                <button class="nav-link" id="add-payment-tab" data-bs-toggle="tab" data-bs-target="#add-payment" type="button" role="tab">
                                    <i class="bi bi-credit-card"></i> Pembayaran
                                </button>
                            </li>
                        </ul>
                        
                        <!-- Tab Content -->
                        <div class="tab-content">
                            <!-- Basic Info Tab -->
                            <div class="tab-pane fade show active" id="add-basic" role="tabpanel">
                                <div class="row mb-3">
                                    <label class="col-sm-3 col-form-label">Nama Vendor <span class="text-danger">*</span></label>
                                    <div class="col-sm-9">
                                        <input type="text" name="nama_vendor" class="form-control" required placeholder="Nama vendor">
                                    </div>
                                </div>
                                <div class="row mb-3">
                                    <label class="col-sm-3 col-form-label">Kota <span class="text-danger">*</span></label>
                                    <div class="col-sm-9">
                                        <input type="text" name="kota" class="form-control" required placeholder="Kota vendor">
                                    </div>
                                </div>
                                <div class="row mb-3">
                                    <label class="col-sm-3 col-form-label">Alamat <span class="text-danger">*</span></label>
                                    <div class="col-sm-9">
                                        <textarea name="alamat" class="form-control" rows="3" required placeholder="Alamat lengkap"></textarea>
                                    </div>
                                </div>
                                <div class="row mb-3">
                                    <label class="col-sm-3 col-form-label">Keterangan</label>
                                    <div class="col-sm-9">
                                        <textarea name="keterangan" class="form-control" rows="2" placeholder="Keterangan tambahan"></textarea>
                                    </div>
                                </div>
                                <div class="row mb-3">
                                    <div class="col-sm-9 offset-sm-3">
                                        <div class="form-check">
                                            <input type="checkbox" name="status" class="form-check-input" id="statusAdd" checked>
                                            <label class="form-check-label" for="statusAdd">Aktif</label>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Contact Info Tab -->
                            <div class="tab-pane fade" id="add-contact" role="tabpanel">
                                <div class="row mb-3">
                                    <label class="col-sm-3 col-form-label">Nomor HP <span class="text-danger">*</span></label>
                                    <div class="col-sm-9">
                                        <input type="text" name="hp" class="form-control" required placeholder="Nomor HP vendor">
                                    </div>
                                </div>
                                <div class="row mb-3">
                                    <label class="col-sm-3 col-form-label">Email <span class="text-danger">*</span></label>
                                    <div class="col-sm-9">
                                        <input type="email" name="email" class="form-control" required placeholder="email@example.com">
                                    </div>
                                </div>
                                <div class="row mb-3">
                                    <label class="col-sm-3 col-form-label">Contact Person</label>
                                    <div class="col-sm-9">
                                        <input type="text" name="person" class="form-control" placeholder="Nama contact person">
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Payment Info Tab -->
                            <div class="tab-pane fade" id="add-payment" role="tabpanel">
                                <div class="row mb-3">
                                    <label class="col-sm-3 col-form-label">Nomor Rekening 1</label>
                                    <div class="col-sm-9">
                                        <input type="text" name="nomor_rekening1" class="form-control" placeholder="Nomor rekening bank 1">
                                    </div>
                                </div>
                                <div class="row mb-3">
                                    <label class="col-sm-3 col-form-label">Nomor Rekening 2</label>
                                    <div class="col-sm-9">
                                        <input type="text" name="nomor_rekening2" class="form-control" placeholder="Nomor rekening bank 2">
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
                    <h5 class="modal-title"><i class="bi bi-pencil-square me-2"></i>Edit Vendor</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST" class="form-modern">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="update">
                        <input type="hidden" name="id_vendor" id="edit_id_vendor">
                        
                        <!-- Tab Navigation -->
                        <ul class="nav nav-tabs mb-3" id="editTabs" role="tablist">
                            <li class="nav-item" role="presentation">
                                <button class="nav-link active" id="edit-basic-tab" data-bs-toggle="tab" data-bs-target="#edit-basic" type="button" role="tab">
                                    <i class="bi bi-person"></i> Data Dasar
                                </button>
                            </li>
                            <li class="nav-item" role="presentation">
                                <button class="nav-link" id="edit-contact-tab" data-bs-toggle="tab" data-bs-target="#edit-contact" type="button" role="tab">
                                    <i class="bi bi-telephone"></i> Kontak
                                </button>
                            </li>
                            <li class="nav-item" role="presentation">
                                <button class="nav-link" id="edit-payment-tab" data-bs-toggle="tab" data-bs-target="#edit-payment" type="button" role="tab">
                                    <i class="bi bi-credit-card"></i> Pembayaran
                                </button>
                            </li>
                        </ul>
                        
                        <!-- Tab Content -->
                        <div class="tab-content">
                            <!-- Basic Info Tab -->
                            <div class="tab-pane fade show active" id="edit-basic" role="tabpanel">
                                <div class="row mb-3">
                                    <label class="col-sm-3 col-form-label">Nama Vendor <span class="text-danger">*</span></label>
                                    <div class="col-sm-9">
                                        <input type="text" name="nama_vendor" id="edit_nama_vendor" class="form-control" required>
                                    </div>
                                </div>
                                <div class="row mb-3">
                                    <label class="col-sm-3 col-form-label">Kota <span class="text-danger">*</span></label>
                                    <div class="col-sm-9">
                                        <input type="text" name="kota" id="edit_kota" class="form-control" required>
                                    </div>
                                </div>
                                <div class="row mb-3">
                                    <label class="col-sm-3 col-form-label">Alamat <span class="text-danger">*</span></label>
                                    <div class="col-sm-9">
                                        <textarea name="alamat" id="edit_alamat" class="form-control" rows="3" required></textarea>
                                    </div>
                                </div>
                                <div class="row mb-3">
                                    <label class="col-sm-3 col-form-label">Keterangan</label>
                                    <div class="col-sm-9">
                                        <textarea name="keterangan" id="edit_keterangan" class="form-control" rows="2"></textarea>
                                    </div>
                                </div>
                                <div class="row mb-3">
                                    <div class="col-sm-9 offset-sm-3">
                                        <div class="form-check">
                                            <input type="checkbox" name="status" class="form-check-input" id="edit_status">
                                            <label class="form-check-label" for="edit_status">Aktif</label>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Contact Info Tab -->
                            <div class="tab-pane fade" id="edit-contact" role="tabpanel">
                                <div class="row mb-3">
                                    <label class="col-sm-3 col-form-label">Nomor HP <span class="text-danger">*</span></label>
                                    <div class="col-sm-9">
                                        <input type="text" name="hp" id="edit_hp" class="form-control" required>
                                    </div>
                                </div>
                                <div class="row mb-3">
                                    <label class="col-sm-3 col-form-label">Email <span class="text-danger">*</span></label>
                                    <div class="col-sm-9">
                                        <input type="email" name="email" id="edit_email" class="form-control" required>
                                    </div>
                                </div>
                                <div class="row mb-3">
                                    <label class="col-sm-3 col-form-label">Contact Person</label>
                                    <div class="col-sm-9">
                                        <input type="text" name="person" id="edit_person" class="form-control">
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Payment Info Tab -->
                            <div class="tab-pane fade" id="edit-payment" role="tabpanel">
                                <div class="row mb-3">
                                    <label class="col-sm-3 col-form-label">Nomor Rekening 1</label>
                                    <div class="col-sm-9">
                                        <input type="text" name="nomor_rekening1" id="edit_nomor_rekening1" class="form-control">
                                    </div>
                                </div>
                                <div class="row mb-3">
                                    <label class="col-sm-3 col-form-label">Nomor Rekening 2</label>
                                    <div class="col-sm-9">
                                        <input type="text" name="nomor_rekening2" id="edit_nomor_rekening2" class="form-control">
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
        function editVendor(vendor) {
            document.getElementById('edit_id_vendor').value = vendor.id_vendor;
            document.getElementById('edit_nama_vendor').value = vendor.nama_vendor;
            document.getElementById('edit_kota').value = vendor.kota;
            document.getElementById('edit_alamat').value = vendor.alamat;
            document.getElementById('edit_hp').value = vendor.hp;
            document.getElementById('edit_email').value = vendor.email;
            document.getElementById('edit_person').value = vendor.person || '';
            document.getElementById('edit_nomor_rekening1').value = vendor.nomor_rekening1 || '';
            document.getElementById('edit_nomor_rekening2').value = vendor.nomor_rekening2 || '';
            document.getElementById('edit_keterangan').value = vendor.keterangan || '';
            document.getElementById('edit_status').checked = vendor.status == '1';
            
            var editModal = new bootstrap.Modal(document.getElementById('editModal'));
            editModal.show();
        }
        
        function confirmDelete(id, nama) {
            if (confirm('Apakah Anda yakin ingin menghapus vendor "' + nama + '"?')) {
                var form = document.createElement('form');
                form.method = 'POST';
                form.innerHTML = '<input type="hidden" name="action" value="delete"><input type="hidden" name="id_vendor" value="' + id + '">';
                document.body.appendChild(form);
                form.submit();
            }
        }
        
        <?php if ($edit_vendor): ?>
        document.addEventListener('DOMContentLoaded', function() {
            editVendor(<?php echo json_encode($edit_vendor); ?>);
        });
        <?php endif; ?>
    </script>
</body>
</html>
