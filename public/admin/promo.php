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

// Function to generate promo code
function generatePromoCode($conn) {
    $year = date('y'); // 2 digit year
    $prefix = "PR{$year}-";
    
    // Get the last promo code for this year
    $stmt = $conn->prepare("SELECT kode_promo FROM promo WHERE kode_promo LIKE ? ORDER BY kode_promo DESC LIMIT 1");
    $like_pattern = $prefix . '%';
    $stmt->bind_param("s", $like_pattern);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($row = $result->fetch_assoc()) {
        // Extract number from last code and increment
        $last_code = $row['kode_promo'];
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
                $kode_promo = trim($_POST['kode_promo']);
                $nama_promo = trim($_POST['nama_promo']);
                $deskripsi = trim($_POST['deskripsi']);
                $tanggalmulai_promo = $_POST['tanggalmulai_promo'];
                $tanggalselesai_promo = $_POST['tanggalselesai_promo'];
                $pilihan_promo = $_POST['pilihan_promo'];
                $nominal = floatval($_POST['nominal']);
                $persen = floatval($_POST['persen']);
                $kuota = intval($_POST['kuota']);
                $min_pembelian = floatval($_POST['min_pembelian']);
                $aktif = isset($_POST['aktif']) ? '1' : '0';
                $id_user = $_SESSION['id_user'];
                
                if (!empty($nama_promo) && !empty($tanggalmulai_promo) && !empty($tanggalselesai_promo)) {
                    // Generate promo code if empty
                    if (empty($kode_promo)) {
                        $kode_promo = generatePromoCode($conn);
                    }
                    
                    // Check if promo code already exists
                    $stmt = $conn->prepare("SELECT id_promo FROM promo WHERE kode_promo = ?");
                    $stmt->bind_param("s", $kode_promo);
                    $stmt->execute();
                    $result = $stmt->get_result();
                    if ($result->fetch_assoc()) {
                        $error = 'Kode promo "' . $kode_promo . '" sudah digunakan! Silakan gunakan kode lain.';
                        break;
                    }
                    
                    // Validate dates
                    if ($tanggalselesai_promo < $tanggalmulai_promo) {
                        $error = 'Tanggal selesai harus sama atau lebih besar dari tanggal mulai!';
                        break;
                    }
                    
                    // Set nilai based on pilihan_promo
                    if ($pilihan_promo == 'nominal') {
                        $persen = 0;
                    } else {
                        $nominal = 0;
                    }
                    
                    $stmt = $conn->prepare("INSERT INTO promo (kode_promo, nama_promo, deskripsi, id_user, tanggalmulai_promo, tanggalselesai_promo, nominal, persen, pilihan_promo, aktif, kuota, min_pembelian) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                    $stmt->bind_param("sssissddsiii", $kode_promo, $nama_promo, $deskripsi, $id_user, $tanggalmulai_promo, $tanggalselesai_promo, $nominal, $persen, $pilihan_promo, $aktif, $kuota, $min_pembelian);
                    if ($stmt->execute()) {
                        $message = 'Data promo berhasil ditambahkan!';
                    } else {
                        $error = 'Error: ' . $conn->error;
                    }
                } else {
                    $error = 'Nama promo, tanggal mulai, dan tanggal selesai harus diisi!';
                }
                break;
                

            case 'toggle_status':
                $id_promo = intval($_POST['id_promo']);
                $aktif = $_POST['aktif'] == '1' ? '0' : '1';
                
                $stmt = $conn->prepare("UPDATE promo SET aktif = ? WHERE id_promo = ?");
                $stmt->bind_param("ii", $aktif, $id_promo);
                if ($stmt->execute()) {
                    $message = $aktif == '1' ? 'Promo berhasil diaktifkan!' : 'Promo berhasil dinonaktifkan!';
                } else {
                    $error = 'Error: ' . $conn->error;
                }
                break;
                
            case 'check_promo_code':
                $kode_promo = $_POST['kode_promo'];
                
                $stmt = $conn->prepare("SELECT id_promo FROM promo WHERE kode_promo = ?");
                $stmt->bind_param("s", $kode_promo);
                $stmt->execute();
                $result = $stmt->get_result();
                
                echo json_encode(['exists' => $result->num_rows > 0]);
                exit;
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
    $where_conditions[] = "(promo.nama_promo LIKE ? OR promo.kode_promo LIKE ? OR promo.deskripsi LIKE ?)";
    $search_param = "%$search%";
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
    $types .= 'sss';
}

if (!empty($filter) && in_array($filter, ['1', '0'])) {
    $where_conditions[] = "promo.aktif = ?";
    $params[] = $filter;
    $types .= 's';
}

$where_clause = !empty($where_conditions) ? 'WHERE ' . implode(' AND ', $where_conditions) : '';

// Query as specified in gemini.md
$base_query = "
    SELECT 
        promo.id_promo,
        promo.nama_promo, 
        promo.kode_promo,
        promo.tanggalmulai_promo,
        promo.tanggalselesai_promo,
        CONCAT(pegawai.jabatan,' - ',pegawai.nama_lengkap) as insert_by,
        promo.kuota, 
        promo.min_pembelian, 
        promo.pilihan_promo,
        CASE
            WHEN promo.pilihan_promo = 'nominal' THEN CONCAT('Rp',FORMAT(promo.nominal,0))
            WHEN promo.pilihan_promo = 'persen' THEN CONCAT(promo.persen,'%')
        END as nilai_promo,
        promo.deskripsi,
        promo.aktif
    FROM promo
    INNER JOIN pegawai ON promo.id_user = pegawai.id_user
";

// Get total count for pagination
$count_sql = "SELECT COUNT(*) as total FROM promo INNER JOIN pegawai ON promo.id_user = pegawai.id_user $where_clause";
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

// Get promo data
$data_query = "$base_query $where_clause ORDER BY promo.id_promo DESC LIMIT $limit OFFSET $offset";

if (!empty($params)) {
    $stmt = $conn->prepare($data_query);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $result = $stmt->get_result();
    $promos = $result->fetch_all(MYSQLI_ASSOC);
} else {
    $result = $conn->query($data_query);
    if ($result) {
        $promos = $result->fetch_all(MYSQLI_ASSOC);
    } else {
        $error = 'Error fetching data: ' . $conn->error;
        $promos = [];
    }
}

$start_record = $total_records > 0 ? $offset + 1 : 0;
$end_record = $total_records > 0 ? min($offset + count($promos), $total_records) : 0;

?>

<!doctype html>
<html lang="id" data-bs-theme="light">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Promo - Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    <link href="https://cdn.jsdelivr.net/npm/select2-bootstrap-5-theme@1.3.0/dist/select2-bootstrap-5-theme.min.css" rel="stylesheet" />
    <link href="../css/newadmin.css?v=3" rel="stylesheet">
</head>
<body>
    <?php include '_header_new.php'; ?>
    
    <?php include '_sidebar_new.php'; ?>

    <main class="main-content" id="mainContent">
        <div class="container-fluid">
            <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-3">
                <div>
                    <h2 class="mb-1"><i class="bi bi-percent me-2"></i>Promo</h2>
                    <p class="text-muted mb-0">Kelola promo dan pantau performa penawaran</p>
                </div>
                <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#promoModal">
                    <i class="bi bi-plus-lg me-2"></i>Tambah Promo
                </button>
            </div>

            <?php if (!empty($message)): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <i class="bi bi-check-circle me-2"></i><?php echo htmlspecialchars($message); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
            <?php endif; ?>

            <?php if (!empty($error)): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <i class="bi bi-exclamation-triangle me-2"></i><?php echo htmlspecialchars($error); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
            <?php endif; ?>

            <div class="row">
                <div class="col-12">
                    <div class="card-modern">
                        <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-3 px-4">
                            <div class="d-flex align-items-center">
                                <i class="bi bi-table me-2"></i>
                                <span>Daftar Promo</span>
                            </div>
                            <div class="d-flex flex-wrap align-items-center gap-2 justify-content-end">
                                <form method="GET" class="d-flex flex-wrap align-items-center gap-2">
                                    <?php if (!empty($filter)): ?>
                                    <input type="hidden" name="filter" value="<?php echo htmlspecialchars($filter); ?>">
                                    <?php endif; ?>
                                    <div class="input-group" style="width: 240px;">
                                        <span class="input-group-text bg-white border-end-0">
                                            <i class="bi bi-search"></i>
                                        </span>
                                        <input type="text" class="form-control border-start-0 ps-0" name="search" placeholder="Cari promo..." value="<?php echo htmlspecialchars($search); ?>">
                                    </div>
                                    <?php if (!empty($search)): ?>
                                    <a href="promo.php" class="btn btn-outline-danger">
                                        <i class="bi bi-x-lg"></i>
                                    </a>
                                    <?php endif; ?>
                                </form>
                                <form method="GET" class="d-flex align-items-center" style="min-width: 180px;">
                                    <?php if (!empty($search)): ?>
                                    <input type="hidden" name="search" value="<?php echo htmlspecialchars($search); ?>">
                                    <?php endif; ?>
                                    <select name="filter" class="form-select" onchange="this.form.submit()">
                                        <option value="">Semua Status</option>
                                        <option value="1" <?php echo $filter === '1' ? 'selected' : ''; ?>>Aktif</option>
                                        <option value="0" <?php echo $filter === '0' ? 'selected' : ''; ?>>Tidak Aktif</option>
                                    </select>
                                </form>
                            </div>
                        </div>

                        <div class="table-responsive px-0">
                            <table class="table table-hover mb-0">
                                <thead> 
                                    <tr>
                                        <th style="width: 5%;" class="text-start">No</th>
                                        <th style="width: 10%; text-align: left">Kode</th>
                                        <th style="text-align: left">Nama Promo</th>
                                        <th style="width: 10%; text-align: center">Kuota</th>
                                        <th style="width: 15%; text-align: right">Nilai Promo</th>
                                        <th style="width: 10%; text-align: center">Status</th>
                                        <th style="width: 12%; text-align: center">Aksi</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($promos)): ?>
                                    <tr>
                                        <td colspan="7" class="text-center py-5">
                                            <i class="bi bi-inbox fs-1 d-block mb-2 text-muted"></i>
                                            <p class="text-muted mb-0">Tidak ada data promo</p>
                                        </td>
                                    </tr>
                                    <?php else: ?>
                                        <?php $no = ($page - 1) * $limit + 1; ?>
                                        <?php foreach ($promos as $promo): ?>
                                        <tr class="align-middle">
                                            <td class="fw-semibold text-start"><?php echo $no++; ?></td>
                                            <td class="fw-medium">
                                                <button type="button" class="btn btn-link p-0 fw-bold text-decoration-none" data-bs-toggle="modal" data-bs-target="#detailModal" data-promo='<?php echo htmlspecialchars(json_encode($promo), ENT_QUOTES, 'UTF-8'); ?>'>
                                                    <?php echo htmlspecialchars($promo['kode_promo']); ?>
                                                </button>
                                            </td>
                                            <td class="text-start">
                                                <div class="fw-bold mb-1"><?php echo htmlspecialchars($promo['nama_promo']); ?></div>
                                                <small class="text-muted"><?php echo htmlspecialchars($promo['insert_by']); ?></small>
                                            </td>
                                            <td class="text-center"><?php echo htmlspecialchars($promo['kuota']); ?></td>
                                            <td class="text-end">
                                                <span class="fw-medium text-primary"><?php echo htmlspecialchars($promo['nilai_promo']); ?></span>
                                            </td>
                                            <td class="text-center">
                                                <?php if ($promo['aktif'] == '1'): ?>
                                                    <span class="badge bg-success">Aktif</span>
                                                <?php else: ?>
                                                    <span class="badge bg-secondary">Tidak Aktif</span>
                                                <?php endif; ?>
                                            </td>
                                            <td class="text-center">
                                                <div class="table-actions d-inline-flex justify-content-center align-items-center gap-2">
                                                    <button type="button" class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#detailModal" data-promo='<?php echo htmlspecialchars(json_encode($promo), ENT_QUOTES, 'UTF-8'); ?>'>
                                                        <i class="bi bi-eye"></i>
                                                    </button>
                                                    <form method="POST" class="d-inline" onsubmit="return confirm('Yakin ingin mengubah status promo ini?')">
                                                        <input type="hidden" name="action" value="toggle_status">
                                                        <input type="hidden" name="id_promo" value="<?php echo $promo['id_promo']; ?>">
                                                        <input type="hidden" name="aktif" value="<?php echo $promo['aktif']; ?>">
                                                        <button type="submit" class="btn btn-sm <?php echo $promo['aktif'] == '1' ? 'btn-outline-danger' : 'btn-outline-success'; ?>">
                                                            <i class="bi bi-<?php echo $promo['aktif'] == '1' ? 'x-circle' : 'check-circle'; ?>"></i>
                                                        </button>
                                                    </form>
                                                </div>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                        <div class="card-footer bg-light border-top py-3 px-4">
                            <div class="d-flex flex-wrap justify-content-between align-items-center gap-2">
                                <small class="text-muted mb-0">
                                    <?php if ($total_records > 0): ?>
                                        Menampilkan <?php echo number_format($start_record); ?> - <?php echo number_format($end_record); ?> dari <?php echo number_format($total_records); ?> promo
                                    <?php else: ?>
                                        Tidak ada data promo
                                    <?php endif; ?>
                                </small>
                                <nav>
                                    <ul class="pagination pagination-sm mb-0">
                                        <?php if ($total_pages > 1): ?>
                                            <li class="page-item <?php echo $page <= 1 ? 'disabled' : ''; ?>">
                                                <a class="page-link" href="?page=<?php echo $page - 1; ?><?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?><?php echo !empty($filter) ? '&filter=' . urlencode($filter) : ''; ?>">
                                                    <i class="bi bi-chevron-left"></i>
                                                </a>
                                            </li>
                                            <?php for ($i = max(1, $page - 2); $i <= min($total_pages, $page + 2); $i++): ?>
                                                <li class="page-item <?php echo $i == $page ? 'active' : ''; ?>">
                                                    <a class="page-link" href="?page=<?php echo $i; ?><?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?><?php echo !empty($filter) ? '&filter=' . urlencode($filter) : ''; ?>">
                                                        <?php echo $i; ?>
                                                    </a>
                                                </li>
                                            <?php endfor; ?>
                                            <li class="page-item <?php echo $page >= $total_pages ? 'disabled' : ''; ?>">
                                                <a class="page-link" href="?page=<?php echo $page + 1; ?><?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?><?php echo !empty($filter) ? '&filter=' . urlencode($filter) : ''; ?>">
                                                    <i class="bi bi-chevron-right"></i>
                                                </a>
                                            </li>
                                        <?php else: ?>
                                            <li class="page-item active"><span class="page-link">1</span></li>
                                        <?php endif; ?>
                                    </ul>
                                </nav>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <!-- Promo Modal -->
    <div class="modal fade" id="promoModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="promoModalTitle"><i class="bi bi-plus-circle me-2"></i>Tambah Promo</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST" id="promoForm" class="form-modern">
                    <div class="modal-body">
                        <input type="hidden" name="action" id="promoAction" value="create">
                        <input type="hidden" name="id_promo" id="promoId">

                        <div class="row mb-3">
                            <label class="col-sm-3 col-form-label">Kode Promo</label>
                            <div class="col-sm-9">
                                <input type="text" class="form-control" id="kode_promo" name="kode_promo" placeholder="Kosongkan untuk auto generate" onblur="checkPromoCode()">
                                <div id="kode_promo_feedback" class="form-text"></div>
                            </div>
                        </div>
                        <div class="row mb-3">
                            <label class="col-sm-3 col-form-label">Nama Promo <span class="text-danger">*</span></label>
                            <div class="col-sm-9">
                                <input type="text" class="form-control" id="nama_promo" name="nama_promo" required maxlength="30">
                            </div>
                        </div>
                        <div class="row mb-3">
                            <label class="col-sm-3 col-form-label">Deskripsi</label>
                            <div class="col-sm-9">
                                <textarea class="form-control" id="deskripsi" name="deskripsi" rows="3"></textarea>
                            </div>
                        </div>
                        <div class="row mb-3">
                            <label class="col-sm-3 col-form-label">Tanggal Mulai <span class="text-danger">*</span></label>
                            <div class="col-sm-9">
                                <input type="date" class="form-control" id="tanggalmulai_promo" name="tanggalmulai_promo" required onchange="updateMinEndDate()">
                            </div>
                        </div>
                        <div class="row mb-3">
                            <label class="col-sm-3 col-form-label">Tanggal Selesai <span class="text-danger">*</span></label>
                            <div class="col-sm-9">
                                <input type="date" class="form-control" id="tanggalselesai_promo" name="tanggalselesai_promo" required>
                            </div>
                        </div>
                        <div class="row mb-3">
                            <label class="col-sm-3 col-form-label">Jenis Promo <span class="text-danger">*</span></label>
                            <div class="col-sm-9">
                                <select class="form-select" id="pilihan_promo" name="pilihan_promo" required onchange="togglePromoValue()">
                                    <option value="">Pilih Jenis Promo</option>
                                    <option value="nominal">Nominal (Rp)</option>
                                    <option value="persen">Persentase (%)</option>
                                </select>
                            </div>
                        </div>
                        <div class="row mb-3">
                            <label class="col-sm-3 col-form-label">Nilai Promo</label>
                            <div class="col-sm-9">
                                <div class="row g-2">
                                    <div class="col-md-6" id="nominalGroup" style="display: none;">
                                        <input type="number" class="form-control" id="nominal" name="nominal" min="0" step="0.01" placeholder="Nominal">
                                    </div>
                                    <div class="col-md-6" id="persenGroup" style="display: none;">
                                        <input type="number" class="form-control" id="persen" name="persen" min="0" max="100" step="0.01" placeholder="Persentase">
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="row mb-3">
                            <label class="col-sm-3 col-form-label">Kuota</label>
                            <div class="col-sm-9">
                                <input type="number" class="form-control" id="kuota" name="kuota" min="1" value="5">
                            </div>
                        </div>
                        <div class="row mb-3">
                            <label class="col-sm-3 col-form-label">Minimal Pembelian (Rp)</label>
                            <div class="col-sm-9">
                                <input type="number" class="form-control" id="min_pembelian" name="min_pembelian" min="0" step="0.01" value="0">
                            </div>
                        </div>
                        <div class="row mb-3">
                            <label class="col-sm-3 col-form-label">Status</label>
                            <div class="col-sm-9 d-flex align-items-center">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="aktif" name="aktif" checked>
                                    <label class="form-check-label" for="aktif">Aktif</label>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                            <i class="bi bi-x-lg me-1"></i>Batal
                        </button>
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-check-lg me-1"></i>Simpan
                        </button>
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
                    <h5 class="modal-title"><i class="bi bi-info-circle me-2"></i>Detail Promo</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body" id="detailContent"></div>
            </div>
        </div>
    </div>

    <?php include '_scripts_new.php'; ?>

    <script>
        function togglePromoValue() {
            const pilihan = document.getElementById('pilihan_promo').value;
            const nominalGroup = document.getElementById('nominalGroup');
            const persenGroup = document.getElementById('persenGroup');
            const nominalInput = document.getElementById('nominal');
            const persenInput = document.getElementById('persen');

            if (pilihan === 'nominal') {
                nominalGroup.style.display = 'block';
                persenGroup.style.display = 'none';
                nominalInput.required = true;
                persenInput.required = false;
                persenInput.value = '';
            } else if (pilihan === 'persen') {
                nominalGroup.style.display = 'none';
                persenGroup.style.display = 'block';
                nominalInput.required = false;
                persenInput.required = true;
                nominalInput.value = '';
            } else {
                nominalGroup.style.display = 'none';
                persenGroup.style.display = 'none';
                nominalInput.required = false;
                persenInput.required = false;
            }
        }

        function updateMinEndDate() {
            const startDate = document.getElementById('tanggalmulai_promo').value;
            const endDateInput = document.getElementById('tanggalselesai_promo');

            if (startDate) {
                endDateInput.min = startDate;
                if (endDateInput.value && endDateInput.value < startDate) {
                    endDateInput.value = '';
                }
            }
        }

        function checkPromoCode() {
            const kodePromo = document.getElementById('kode_promo').value.trim();
            const feedback = document.getElementById('kode_promo_feedback');

            if (kodePromo === '') {
                feedback.textContent = '';
                feedback.className = 'form-text';
                return;
            }

            const xhr = new XMLHttpRequest();
            xhr.open('POST', '', true);
            xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
            xhr.onreadystatechange = function() {
                if (xhr.readyState === 4 && xhr.status === 200) {
                    const response = JSON.parse(xhr.responseText);
                    if (response.exists) {
                        feedback.innerHTML = '<i class="bi bi-x-circle text-danger me-1"></i>Kode promo sudah digunakan!';
                        feedback.className = 'form-text text-danger';
                    } else {
                        feedback.innerHTML = '<i class="bi bi-check-circle text-success me-1"></i>Kode promo tersedia';
                        feedback.className = 'form-text text-success';
                    }
                }
            };
            xhr.send('action=check_promo_code&kode_promo=' + encodeURIComponent(kodePromo));
        }

        document.addEventListener('DOMContentLoaded', function() {
            const today = new Date().toISOString().split('T')[0];
            const startDateInput = document.getElementById('tanggalmulai_promo');
            const endDateInput = document.getElementById('tanggalselesai_promo');

            startDateInput.min = today;
            endDateInput.min = today;

            const promoModal = document.getElementById('promoModal');
            if (promoModal) {
                promoModal.addEventListener('hidden.bs.modal', function() {
                    if (document.getElementById('promoAction').value === 'create') {
                        document.getElementById('promoForm').reset();
                        document.getElementById('promoModalTitle').innerHTML = '<i class="bi bi-plus-circle me-2"></i>Tambah Promo';
                        document.getElementById('aktif').checked = true;
                        togglePromoValue();
                        startDateInput.min = today;
                        endDateInput.min = today;
                    }
                });
            }

            togglePromoValue();

            const detailModal = document.getElementById('detailModal');
            if (detailModal) {
                detailModal.addEventListener('show.bs.modal', function(event) {
                    const button = event.relatedTarget;
                    if (!button) {
                        return;
                    }

                    const promoData = button.getAttribute('data-promo');
                    if (!promoData) {
                        return;
                    }

                    try {
                        const promo = JSON.parse(promoData);
                        const formatter = new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', minimumFractionDigits: 0 });
                        const statusBadge = promo.aktif == '1'
                            ? '<span class="badge bg-success"><i class="bi bi-check-circle me-1"></i>Aktif</span>'
                            : '<span class="badge bg-danger"><i class="bi bi-x-circle me-1"></i>Tidak Aktif</span>';

                        const nilaiPromo = promo.nilai_promo ? promo.nilai_promo : '-';
                        const deskripsi = promo.deskripsi ? promo.deskripsi : '<em class="text-muted">Tidak ada deskripsi</em>';

                        document.getElementById('detailContent').innerHTML = `
                            <div class="card border-0">
                                <div class="card-body p-0">
                                    <div class="row g-3">
                                        <div class="col-md-6">
                                            <div class="border rounded p-3 h-100">
                                                <h6 class="text-primary mb-3"><i class="bi bi-tag me-2"></i>Informasi Promo</h6>
                                                <div class="mb-2">
                                                    <small class="text-muted">Kode Promo</small>
                                                    <div class="fw-bold">${promo.kode_promo}</div>
                                                </div>
                                                <div class="mb-2">
                                                    <small class="text-muted">Nama Promo</small>
                                                    <div class="fw-bold">${promo.nama_promo}</div>
                                                </div>
                                                <div class="mb-2">
                                                    <small class="text-muted">Status</small>
                                                    <div>${statusBadge}</div>
                                                </div>
                                                <div class="mb-2">
                                                    <small class="text-muted">Deskripsi</small>
                                                    <div>${deskripsi}</div>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="border rounded p-3 h-100">
                                                <h6 class="text-success mb-3"><i class="bi bi-percent me-2"></i>Detail Promo</h6>
                                                <div class="mb-2">
                                                    <small class="text-muted">Jenis Promo</small>
                                                    <div class="fw-bold">${promo.pilihan_promo === 'nominal' ? 'Nominal (Rp)' : 'Persentase (%)'}</div>
                                                </div>
                                                <div class="mb-2">
                                                    <small class="text-muted">Nilai Promo</small>
                                                    <div class="fw-bold text-success fs-5">${nilaiPromo}</div>
                                                </div>
                                                <div class="mb-2">
                                                    <small class="text-muted">Minimal Pembelian</small>
                                                    <div class="fw-bold">${formatter.format(promo.min_pembelian || 0)}</div>
                                                </div>
                                                <div class="mb-2">
                                                    <small class="text-muted">Kuota</small>
                                                    <div class="fw-bold">${promo.kuota} kali</div>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="col-12">
                                            <div class="border rounded p-3">
                                                <h6 class="text-info mb-3"><i class="bi bi-calendar me-2"></i>Periode & Tracking</h6>
                                                <div class="row">
                                                    <div class="col-md-4">
                                                        <small class="text-muted">Tanggal Mulai</small>
                                                        <div class="fw-bold">${new Date(promo.tanggalmulai_promo).toLocaleDateString('id-ID', { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' })}</div>
                                                    </div>
                                                    <div class="col-md-4">
                                                        <small class="text-muted">Tanggal Selesai</small>
                                                        <div class="fw-bold">${new Date(promo.tanggalselesai_promo).toLocaleDateString('id-ID', { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' })}</div>
                                                    </div>
                                                    <div class="col-md-4">
                                                        <small class="text-muted">Dibuat Oleh</small>
                                                        <div class="fw-bold">${promo.insert_by}</div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        `;
                    } catch (err) {
                        console.error('Gagal memuat detail promo:', err);
                        document.getElementById('detailContent').innerHTML = '<p class="text-danger mb-0">Gagal memuat detail promo.</p>';
                    }
                });
            }
        });
    </script>
</body>
</html>