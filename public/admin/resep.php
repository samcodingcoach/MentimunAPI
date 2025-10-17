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
                $kode_resep = trim($_POST['kode_resep']);
                $id_produk = $_POST['id_produk'];
                $id_user = $_SESSION['id_user'];
                
                if (!empty($kode_resep) && !empty($id_produk)) {
                    $stmt = $conn->prepare("INSERT INTO resep (kode_resep, id_produk, id_user) VALUES (?, ?, ?)");
                    $stmt->bind_param("sii", $kode_resep, $id_produk, $id_user);
                    if ($stmt->execute()) {
                        $message = 'Data resep berhasil ditambahkan!';
                    } else {
                        $error = 'Error: ' . $conn->error;
                    }
                } else {
                    $error = 'Kode resep dan produk wajib diisi!';
                }
                break;
                
            case 'update':
                $id_resep = $_POST['id_resep'];
                $kode_resep = trim($_POST['kode_resep']);
                $id_produk = $_POST['id_produk'];
                $id_user = $_SESSION['id_user'];
                
                if (!empty($kode_resep) && !empty($id_produk)) {
                    $stmt = $conn->prepare("UPDATE resep SET kode_resep = ?, id_produk = ?, id_user = ? WHERE id_resep = ?");
                    $stmt->bind_param("siii", $kode_resep, $id_produk, $id_user, $id_resep);
                    if ($stmt->execute()) {
                        $message = 'Data resep berhasil diperbarui!';
                    } else {
                        $error = 'Error: ' . $conn->error;
                    }
                } else {
                    $error = 'Kode resep dan produk harus diisi!';
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

// Build WHERE clause for search
$where_conditions = [];
$params = [];
$types = '';

if (!empty($search)) {
    $where_conditions[] = "(resep.kode_resep LIKE ? OR produk_menu.nama_produk LIKE ? OR kategori_menu.nama_kategori LIKE ? OR pegawai.nama_lengkap LIKE ?)";
    $search_param = "%$search%";
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
    $types .= 'ssss';
}

$where_clause = !empty($where_conditions) ? 'WHERE ' . implode(' AND ', $where_conditions) : '';

// Get total count for pagination
$count_sql = "SELECT COUNT(*) as total FROM resep 
              INNER JOIN produk_menu ON resep.id_produk = produk_menu.id_produk
              INNER JOIN kategori_menu ON produk_menu.id_kategori = kategori_menu.id_kategori
              INNER JOIN pegawai ON resep.id_user = pegawai.id_user
              $where_clause";
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

// Get resep data with pagination using the specified query
$sql = "SELECT
    resep.id_resep, 
    resep.kode_resep, 
    resep.id_produk, 
    CONCAT(produk_menu.nama_produk,' - ',kategori_menu.nama_kategori, ' [',produk_menu.kode_produk,']') as nama_produk,
    resep.id_user, 
    CONCAT(pegawai.nama_lengkap,' [',pegawai.jabatan,']') as pembuat_resep,
    DATE_FORMAT(resep.tanggal_release,'%d %M %Y %H:%i') as tanggal_release,
    COUNT(resep_detail.id_bahan) as qty_bahan,
    CONCAT('Rp ', FORMAT(COALESCE(SUM(resep_detail.nilai_ekpetasi), 0), 0)) AS nilai
FROM resep
INNER JOIN produk_menu ON resep.id_produk = produk_menu.id_produk
INNER JOIN kategori_menu ON produk_menu.id_kategori = kategori_menu.id_kategori
INNER JOIN pegawai ON resep.id_user = pegawai.id_user
LEFT JOIN resep_detail ON resep.id_resep = resep_detail.id_resep
$where_clause
GROUP BY resep.id_resep
ORDER BY resep.tanggal_release DESC
LIMIT $limit OFFSET $offset";

if (!empty($params)) {
    $stmt = $conn->prepare($sql);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $result = $stmt->get_result();
    $reseps = $result->fetch_all(MYSQLI_ASSOC);
} else {
    $result = $conn->query($sql);
    if ($result) {
        $reseps = $result->fetch_all(MYSQLI_ASSOC);
    } else {
        $error = 'Error fetching data: ' . $conn->error;
        $reseps = [];
    }
}

// Get products for dropdown
$products_sql = "SELECT pm.id_produk, CONCAT(pm.nama_produk, ' - ', km.nama_kategori, ' [', pm.kode_produk, ']') as display_name 
                 FROM produk_menu pm 
                 INNER JOIN kategori_menu km ON pm.id_kategori = km.id_kategori 
                 WHERE pm.aktif = '1' 
                 ORDER BY pm.nama_produk";
$products_result = $conn->query($products_sql);
$products = $products_result->fetch_all(MYSQLI_ASSOC);

// Get single resep for editing
$edit_resep = null;
if (isset($_GET['edit'])) {
    $stmt = $conn->prepare("SELECT * FROM resep WHERE id_resep = ?");
    $stmt->bind_param("i", $_GET['edit']);
    $stmt->execute();
    $result = $stmt->get_result();
    $edit_resep = $result->fetch_assoc();
}
?>

<!doctype html>
<html lang="id" data-bs-theme="light">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Resep - Admin</title>
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
                    <h2 class="mb-1"><i class="bi bi-journal-text me-2"></i>Manajemen Resep</h2>
                    <p class="text-muted mb-0">Kelola resep produk dan komposisi bahan</p>
                </div>
                <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#resepModal">
                    <i class="bi bi-plus-lg me-2"></i>Tambah Resep
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
                <i class="bi bi-x-circle me-2"></i><?php echo $error; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
            <?php endif; ?>

            <!-- Data Table -->
            <div class="card-modern">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <div class="d-flex align-items-center">
                        <i class="bi bi-journal-text me-2"></i>
                        <span>Daftar Resep</span>
                    </div>
                    <div class="d-flex gap-2">
                        <form class="d-flex" method="GET" action="">
                            <div class="input-group" style="width: 250px;">
                                <span class="input-group-text bg-white border-end-0">
                                    <i class="bi bi-search"></i>
                                </span>
                                <input type="text" name="search" class="form-control border-start-0 ps-0" placeholder="Cari kode resep, produk, kategori, atau pembuat..." value="<?php echo htmlspecialchars($search); ?>">
                            </div>
                        </form>
                    </div>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead>
                                <tr>
                                    <th class="text-start" style="width: 5%;">No</th>
                                    <th class="text-center" style="width: 10%;">Kode Resep</th>
                                    <th style="width: auto;" class="text-start">Produk</th>
                                   
                                    <th style="width: 15%;" class="text-center">Tanggal</th>
                                    <th style="width: 5%;" class="text-center">Qty</th>
                                    <th style="width: 10%;" class="text-end">Nilai</th>
                                    <th style="width: 10%;"  class="text-center">Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (!empty($reseps)): ?>
                                    <?php foreach ($reseps as $index => $resep): ?>
                                        <tr>
                                            <td class="text-start"><?php echo $offset + $index + 1; ?></td>
                                            <td class="text-center"><span class="badge bg-secondary"><?php echo htmlspecialchars($resep['kode_resep']); ?></span></td>
                                            <td style="text-align: left;"><?php echo htmlspecialchars($resep['nama_produk']); ?></td>
                                           
                                            <td class="text-center"><?php echo htmlspecialchars($resep['tanggal_release']); ?></td>
                                            <td class="text-center">
                                               <?php echo $resep['qty_bahan']; ?>
                                            </td>
                                            <td class="text-end">
                                                <strong><?php echo htmlspecialchars($resep['nilai']); ?></strong>
                                            </td>
                                            <td class="text-center">
                                                <a href="resep_detail.php?id=<?php echo $resep['id_resep']; ?>" class="btn btn-sm btn-outline-info">
                                                    <i class="bi bi-eye"></i>
                                                </a>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="8" class="text-center py-4">
                                            <i class="bi bi-journal-text fs-1 text-muted d-block mb-2"></i>
                                            <p class="text-muted mb-0">Tidak ada data resep</p>
                                        </td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
                <div class="card-footer bg-light border-top py-3 px-4">
                    <div class="d-flex justify-content-between align-items-center">
                        <small class="text-muted">Menampilkan <?php echo $offset + 1; ?>-<?php echo min($offset + $limit, $total_records); ?> dari <?php echo $total_records; ?> resep</small>
                        <?php if ($total_pages > 1): ?>
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
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <!-- Modal -->
    <div class="modal fade" id="resepModal" tabindex="-1" aria-labelledby="resepModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-md">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="resepModalLabel">
                        <?php echo $edit_resep ? 'Edit Resep' : 'Tambah Resep'; ?>
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form method="POST">
                    <input type="hidden" name="action" value="<?php echo $edit_resep ? 'update' : 'create'; ?>">
                    <?php if ($edit_resep): ?>
                        <input type="hidden" name="id_resep" value="<?php echo $edit_resep['id_resep']; ?>">
                    <?php endif; ?>
                    
                    <div class="modal-body">
                        <div class="mb-3">
                            <label for="kode_resep" class="form-label">Kode Resep <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="kode_resep" name="kode_resep" 
                                   value="<?php echo $edit_resep ? htmlspecialchars($edit_resep['kode_resep']) : ''; ?>" 
                                   maxlength="50" required>
                        </div>
                        
                        <div class="mb-3">
                            <label for="id_produk" class="form-label">Produk <span class="text-danger">*</span></label>
                            <select class="form-select select2-search" name="id_produk" id="id_produk" required>
                                <option value="">-- Pilih Produk --</option>
                                <?php foreach ($products as $product): ?>
                                    <option value="<?php echo $product['id_produk']; ?>" <?php echo ($edit_resep && $edit_resep['id_produk'] == $product['id_produk']) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($product['display_name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-save me-2"></i><?php echo $edit_resep ? 'Update' : 'Simpan'; ?>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <?php include '_scripts_new.php'; ?>
    
    <!-- Auto show modal if editing -->
    <?php if ($edit_resep): ?>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            var resepModal = new bootstrap.Modal(document.getElementById('resepModal'));
            resepModal.show();
        });
    </script>
    <?php endif; ?>
    
    <!-- Auto close modal after successful update -->
    <?php if ($message && !$error): ?>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            var resepModal = bootstrap.Modal.getInstance(document.getElementById('resepModal'));
            if (resepModal) {
                resepModal.hide();
            }
            // Remove edit parameter from URL
            if (window.location.search.includes('edit=')) {
                const url = new URL(window.location);
                url.searchParams.delete('edit');
                window.history.replaceState({}, document.title, url.pathname + url.search);
            }
        });
    </script>
    <?php endif; ?>
</body>
</html>
