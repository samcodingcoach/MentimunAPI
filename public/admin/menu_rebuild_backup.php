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

// Function to generate product code
function generateProductCode($conn) {
    $year = date('y'); // 2 digit year
    $prefix = "PM{$year}-";
    
    // Get the last product code for this year
    $stmt = $conn->prepare("SELECT kode_produk FROM produk_menu WHERE kode_produk LIKE ? ORDER BY kode_produk DESC LIMIT 1");
    $like_pattern = $prefix . '%';
    $stmt->bind_param("s", $like_pattern);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($row = $result->fetch_assoc()) {
        // Extract number from last code and increment
        $last_code = $row['kode_produk'];
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
                $kode_produk = trim($_POST['kode_produk']);
                $nama_produk = trim($_POST['nama_produk']);
                $id_kategori = (int)$_POST['id_kategori'];
                $aktif = isset($_POST['aktif']) ? '1' : '0';
                
                if (!empty($nama_produk) && $id_kategori > 0) {
                    // Check if product code already exists
                    if (!empty($kode_produk)) {
                        $stmt = $conn->prepare("SELECT id_produk FROM produk_menu WHERE kode_produk = ?");
                        $stmt->bind_param("s", $kode_produk);
                        $stmt->execute();
                        $result = $stmt->get_result();
                        if ($result->fetch_assoc()) {
                            $error = 'Kode produk sudah digunakan!';
                            break;
                        }
                    } else {
                        $kode_produk = generateProductCode($conn);
                    }
                    
                    // Handle image upload
                    $image_uploaded = false;
                    if (isset($_FILES['gambar']) && $_FILES['gambar']['error'] == 0) {
                        $upload_dir = __DIR__ . '/../images/';
                        $file_tmp = $_FILES['gambar']['tmp_name'];
                        $file_size = $_FILES['gambar']['size'];
                        $file_type = $_FILES['gambar']['type'];
                        
                        // Validate file type
                        $allowed_types = ['image/jpeg', 'image/jpg', 'image/png'];
                        if (!in_array($file_type, $allowed_types)) {
                            $error = 'Format gambar harus JPG, JPEG, atau PNG!';
                            break;
                        }
                        
                        // Validate file size (max 500KB)
                        if ($file_size > 500 * 1024) {
                            $error = 'Ukuran gambar maksimal 500KB!';
                            break;
                        }
                        
                        // Get image dimensions
                        $image_info = getimagesize($file_tmp);
                        if ($image_info === false) {
                            $error = 'File bukan gambar yang valid!';
                            break;
                        }
                        
                        $width = $image_info[0];
                        $height = $image_info[1];
                        
                        // Validate dimensions (300x300px)
                        if ($width != 300 || $height != 300) {
                            $error = 'Dimensi gambar harus 300x300 pixel!';
                            break;
                        }
                        
                        // Create upload directory if not exists
                        if (!is_dir($upload_dir)) {
                            mkdir($upload_dir, 0755, true);
                        }
                        
                        // Set filename as kode_produk
                        $file_extension = pathinfo($_FILES['gambar']['name'], PATHINFO_EXTENSION);
                        $new_filename = $kode_produk . '.' . $file_extension;
                        $upload_path = $upload_dir . $new_filename;
                        
                        if (move_uploaded_file($file_tmp, $upload_path)) {
                            $image_uploaded = true;
                        } else {
                            $error = 'Gagal mengupload gambar!';
                            break;
                        }
                    }
                    
                    $stmt = $conn->prepare("INSERT INTO produk_menu (kode_produk, nama_produk, id_kategori, aktif) VALUES (?, ?, ?, ?)");
                    $stmt->bind_param("ssis", $kode_produk, $nama_produk, $id_kategori, $aktif);
                    if ($stmt->execute()) {
                        $message = 'Data produk menu berhasil ditambahkan!' . ($image_uploaded ? ' Gambar berhasil diupload.' : '');
                    } else {
                        $error = 'Error: ' . $conn->error;
                        // Delete uploaded image if database insert failed
                        if ($image_uploaded && isset($upload_path) && file_exists($upload_path)) {
                            unlink($upload_path);
                        }
                    }
                } else {
                    $error = 'Nama produk dan kategori wajib diisi!';
                }
                break;
                
            case 'update':
                $id_produk = (int)$_POST['id_produk'];
                $kode_produk = trim($_POST['kode_produk']);
                $nama_produk = trim($_POST['nama_produk']);
                $id_kategori = (int)$_POST['id_kategori'];
                $aktif = isset($_POST['aktif']) ? '1' : '0';
                
                if (!empty($nama_produk) && $id_kategori > 0) {
                    // Get old product code for image handling
                    $stmt = $conn->prepare("SELECT kode_produk FROM produk_menu WHERE id_produk = ?");
                    $stmt->bind_param("i", $id_produk);
                    $stmt->execute();
                    $result = $stmt->get_result();
                    $old_product = $result->fetch_assoc();
                    $old_kode_produk = $old_product['kode_produk'];
                    
                    // Check if product code already exists for other products
                    if (!empty($kode_produk)) {
                        $stmt = $conn->prepare("SELECT id_produk FROM produk_menu WHERE kode_produk = ? AND id_produk != ?");
                        $stmt->bind_param("si", $kode_produk, $id_produk);
                        $stmt->execute();
                        $result = $stmt->get_result();
                        if ($result->fetch_assoc()) {
                            $error = 'Kode produk sudah digunakan oleh produk lain!';
                            break;
                        }
                    }
                    
                    // Handle image upload
                    $image_uploaded = false;
                    $new_image_path = null;
                    if (isset($_FILES['gambar']) && $_FILES['gambar']['error'] == 0) {
                        $upload_dir = __DIR__ . '/../images/';
                        $file_tmp = $_FILES['gambar']['tmp_name'];
                        $file_size = $_FILES['gambar']['size'];
                        $file_type = $_FILES['gambar']['type'];
                        
                        // Validate file type
                        $allowed_types = ['image/jpeg', 'image/jpg', 'image/png'];
                        if (!in_array($file_type, $allowed_types)) {
                            $error = 'Format gambar harus JPG, JPEG, atau PNG!';
                            break;
                        }
                        
                        // Validate file size (max 500KB)
                        if ($file_size > 500 * 1024) {
                            $error = 'Ukuran gambar maksimal 500KB!';
                            break;
                        }
                        
                        // Get image dimensions
                        $image_info = getimagesize($file_tmp);
                        if ($image_info === false) {
                            $error = 'File bukan gambar yang valid!';
                            break;
                        }
                        
                        $width = $image_info[0];
                        $height = $image_info[1];
                        
                        // Validate dimensions (300x300px)
                        if ($width != 300 || $height != 300) {
                            $error = 'Dimensi gambar harus 300x300 pixel!';
                            break;
                        }
                        
                        // Create upload directory if not exists
                        if (!is_dir($upload_dir)) {
                            mkdir($upload_dir, 0755, true);
                        }
                        
                        // Set filename as kode_produk
                        $file_extension = pathinfo($_FILES['gambar']['name'], PATHINFO_EXTENSION);
                        $new_filename = $kode_produk . '.' . $file_extension;
                        $new_image_path = $upload_dir . $new_filename;
                        
                        if (move_uploaded_file($file_tmp, $new_image_path)) {
                            $image_uploaded = true;
                            
                            // Delete old image if kode_produk changed
                            if ($old_kode_produk != $kode_produk) {
                                $old_extensions = ['jpg', 'jpeg', 'png'];
                                foreach ($old_extensions as $ext) {
                                    $old_image_path = $upload_dir . $old_kode_produk . '.' . $ext;
                                    if (file_exists($old_image_path)) {
                                        unlink($old_image_path);
                                    }
                                }
                            }
                        } else {
                            $error = 'Gagal mengupload gambar!';
                            break;
                        }
                    }
                    
                    $stmt = $conn->prepare("UPDATE produk_menu SET kode_produk = ?, nama_produk = ?, id_kategori = ?, aktif = ? WHERE id_produk = ?");
                    $stmt->bind_param("ssisi", $kode_produk, $nama_produk, $id_kategori, $aktif, $id_produk);
                    if ($stmt->execute()) {
                        $message = 'Data produk menu berhasil diperbarui!' . ($image_uploaded ? ' Gambar berhasil diupload.' : '');
                    } else {
                        $error = 'Error: ' . $conn->error;
                        // Delete uploaded image if database update failed
                        if ($image_uploaded && $new_image_path && file_exists($new_image_path)) {
                            unlink($new_image_path);
                        }
                    }
                } else {
                    $error = 'Nama produk dan kategori harus diisi!';
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
    $where_conditions[] = "(pm.nama_produk LIKE ? OR pm.kode_produk LIKE ? OR km.nama_kategori LIKE ?)";
    $search_param = "%$search%";
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
    $types .= 'sss';
}

if (!empty($filter) && in_array($filter, ['1', '0'])) {
    $where_conditions[] = "pm.aktif = ?";
    $params[] = $filter;
    $types .= 's';
}

$where_clause = !empty($where_conditions) ? 'WHERE ' . implode(' AND ', $where_conditions) : '';

// Complex query with UNION as specified in gemini.md
$base_query = "
    SELECT
        pm.id_produk, 
        pm.kode_produk, 
        CONCAT('[',km.nama_kategori,'] ', pm.nama_produk) as nama_produk,
        COALESCE(CONCAT('Rp ',FORMAT(hm.nominal,0)), 'Not Set') as harga, 
        DATE_FORMAT(hm.tgl,'%d %M %Y') as tgl, 
        CONCAT(pg.nama_lengkap,' [',pg.jabatan,']') as pegawai,
        pm.aktif
    FROM produk_menu pm
    INNER JOIN kategori_menu km ON pm.id_kategori = km.id_kategori
    LEFT JOIN (
        SELECT id_produk, MAX(tgl) AS max_tgl
        FROM harga_menu
        GROUP BY id_produk
    ) AS lh ON pm.id_produk = lh.id_produk
    LEFT JOIN harga_menu hm ON pm.id_produk = hm.id_produk AND hm.tgl = lh.max_tgl
    LEFT JOIN pegawai pg ON hm.id_user = pg.id_user
";

// Get total count for pagination
$count_sql = "SELECT COUNT(DISTINCT pm.id_produk) as total FROM produk_menu pm INNER JOIN kategori_menu km ON pm.id_kategori = km.id_kategori $where_clause";
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

// Get product data with complex UNION query
$union_query = "
(
    $base_query
    $where_clause
    ORDER BY pm.id_produk DESC
    LIMIT 1
)
UNION ALL
(
    $base_query
    " . (!empty($where_conditions) ? $where_clause . " AND " : "WHERE ") . "pm.id_produk NOT IN (
        SELECT id_produk FROM (
            SELECT id_produk 
            FROM produk_menu 
            ORDER BY id_produk DESC 
            LIMIT 1
        ) AS sub
    )
    ORDER BY COALESCE(hm.tgl, '1970-01-01') DESC, pm.nama_produk ASC
    LIMIT $limit OFFSET $offset
)
";

if (!empty($params)) {
    // For UNION query, we need to duplicate parameters
    $union_params = array_merge($params, $params);
    $union_types = $types . $types;
    $stmt = $conn->prepare($union_query);
    $stmt->bind_param($union_types, ...$union_params);
    $stmt->execute();
    $result = $stmt->get_result();
    $products = $result->fetch_all(MYSQLI_ASSOC);
} else {
    $result = $conn->query($union_query);
    if ($result) {
        $products = $result->fetch_all(MYSQLI_ASSOC);
    } else {
        $error = 'Error fetching data: ' . $conn->error;
        $products = [];
    }
}

// Get single product for editing
$edit_product = null;
if (isset($_GET['edit'])) {
    $stmt = $conn->prepare("SELECT * FROM produk_menu WHERE id_produk = ?");
    $stmt->bind_param("i", $_GET['edit']);
    $stmt->execute();
    $result = $stmt->get_result();
    $edit_product = $result->fetch_assoc();
}

// Get categories for dropdown
$categories_result = $conn->query("SELECT id_kategori, nama_kategori FROM kategori_menu ORDER BY nama_kategori ASC");
<!doctype html>
<html lang="id" data-bs-theme="light">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Produk Menu - Admin</title>
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
                    <h2 class="mb-1"><i class="bi bi-cup-straw me-2"></i>Produk Menu</h2>
                    <p class="text-muted mb-0">Kelola produk menu dan harga</p>
                </div>
                <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addModal">
                    <i class="bi bi-plus-lg me-2"></i>Tambah Menu
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
                        <span>Daftar Menu</span>
                    </div>
                    <div class="d-flex gap-2">
                        <form class="d-flex" method="GET" action="">
                            <div class="input-group" style="width: 250px;">
                                <span class="input-group-text bg-white border-end-0">
                                    <i class="bi bi-search"></i>
                                </span>
                                <input type="text" name="search" class="form-control border-start-0 ps-0" placeholder="Cari menu..." value="<?php echo htmlspecialchars($search); ?>">
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
                                    <th style="width: 50px;">No</th>
                                    <th style="width: 120px;">Kode</th>
                                    <th>Nama Produk</th>
                                    <th style="width: 150px;">Harga</th>
                                    <th style="width: 130px;">Tanggal</th>
                                    <th style="width: 150px;">Pegawai</th>
                                    <th style="width: 100px;">Status</th>
                                    <th style="width: 180px;">Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                            <?php if (!empty($products)): ?>
                                <?php 
                                $no = $offset + 1;
                                foreach ($products as $product): 
                                ?>
                                <tr>
                                    <td class="text-center"><?php echo $no++; ?></td>
                                    <td>
                                        <a href="#" class="text-decoration-none fw-semibold" onclick="showProductImage('<?php echo htmlspecialchars($product['kode_produk']); ?>', '<?php echo htmlspecialchars($product['nama_produk']); ?>'); return false;" data-bs-toggle="modal" data-bs-target="#imageModal">
                                            <?php echo htmlspecialchars($product['kode_produk']); ?>
                                        </a>
                                    </td>
                                    <td><?php echo htmlspecialchars($product['nama_produk']); ?></td>
                                    <td><strong class="text-success"><?php echo htmlspecialchars($product['harga']); ?></strong></td>
                                    <td><?php echo htmlspecialchars($product['tgl'] ?? '-'); ?></td>
                                    <td><small><?php echo htmlspecialchars($product['pegawai'] ?? '-'); ?></small></td>
                                    <td>
                                        <?php if ($product['aktif'] == '1'): ?>
                                            <span class="badge bg-success">Aktif</span>
                                        <?php else: ?>
                                            <span class="badge bg-secondary">Nonaktif</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <div class="table-actions">
                                            <button class="btn btn-sm btn-outline-warning" onclick="editProduct(<?php echo $product['id_produk']; ?>)" title="Edit">
                                                <i class="bi bi-pencil"></i>
                                            </button>
                                            <a href="harga.php?id_produk=<?php echo $product['id_produk']; ?>" class="btn btn-sm btn-outline-info" title="Set Harga">
                                                <i class="bi bi-currency-dollar"></i>
                                            </a>
                                        </div>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="8" class="text-center py-4">
                                        <i class="bi bi-inbox fs-1 d-block mb-2 text-muted"></i>
                                        <p class="text-muted mb-0">Tidak ada data produk menu</p>
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
                        <small class="text-muted">Menampilkan <?php echo $offset + 1; ?>-<?php echo min($offset + $limit, $total_records); ?> dari <?php echo $total_records; ?> menu</small>
                        <nav>
                            <ul class="pagination pagination-sm mb-0">
                                <li class="page-item <?php echo $page <= 1 ? 'disabled' : ''; ?>">
                                    <a class="page-link" href="?page=<?php echo $page - 1; ?>&search=<?php echo urlencode($search); ?>&filter=<?php echo urlencode($filter); ?>">Previous</a>
                                </li>
                                <?php for ($i = max(1, $page - 2); $i <= min($total_pages, $page + 2); $i++): ?>
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
                    <h5 class="modal-title"><i class="bi bi-plus-circle me-2"></i>Tambah Produk Menu</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST" enctype="multipart/form-data" class="form-modern">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="create">
                        
                        <div class="row mb-3">
                            <label class="col-sm-3 col-form-label">Kode Produk</label>
                            <div class="col-sm-9">
                                <input type="text" name="kode_produk" class="form-control" maxlength="30" placeholder="Kosongkan untuk auto generate">
                                <small class="text-muted">Kosongkan untuk generate otomatis (PM{tahun}-{nomor})</small>
                            </div>
                        </div>
                        
                        <div class="row mb-3">
                            <label class="col-sm-3 col-form-label">Nama Produk <span class="text-danger">*</span></label>
                            <div class="col-sm-9">
                                <input type="text" name="nama_produk" class="form-control" maxlength="30" required>
                            </div>
                        </div>
                        
                        <div class="row mb-3">
                            <label class="col-sm-3 col-form-label">Kategori <span class="text-danger">*</span></label>
                            <div class="col-sm-9">
                                <select name="id_kategori" class="form-select select2-search" required>
                                    <option value="">-- Pilih Kategori --</option>
                                    <?php foreach ($categories as $cat): ?>
                                    <option value="<?php echo $cat['id_kategori']; ?>"><?php echo htmlspecialchars($cat['nama_kategori']); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        
                        <div class="row mb-3">
                            <label class="col-sm-3 col-form-label">Gambar Produk</label>
                            <div class="col-sm-9">
                                <input type="file" name="gambar" class="form-control" accept="image/jpeg,image/jpg,image/png" id="add_gambar">
                                <small class="text-muted">Format: JPG, JPEG, PNG | Ukuran: 300x300 px | Maksimal: 500KB</small>
                                <div id="addImagePreview" class="mt-2" style="display: none;">
                                    <img id="addPreviewImg" src="" alt="Preview" class="img-thumbnail" style="max-width: 150px; max-height: 150px;">
                                </div>
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
                    <h5 class="modal-title"><i class="bi bi-pencil-square me-2"></i>Edit Produk Menu</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST" enctype="multipart/form-data" class="form-modern" id="editForm">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="update">
                        <input type="hidden" name="id_produk" id="edit_id_produk">
                        
                        <div class="row mb-3">
                            <label class="col-sm-3 col-form-label">Kode Produk <span class="text-danger">*</span></label>
                            <div class="col-sm-9">
                                <input type="text" name="kode_produk" id="edit_kode_produk" class="form-control" maxlength="30" required>
                            </div>
                        </div>
                        
                        <div class="row mb-3">
                            <label class="col-sm-3 col-form-label">Nama Produk <span class="text-danger">*</span></label>
                            <div class="col-sm-9">
                                <input type="text" name="nama_produk" id="edit_nama_produk" class="form-control" maxlength="30" required>
                            </div>
                        </div>
                        
                        <div class="row mb-3">
                            <label class="col-sm-3 col-form-label">Kategori <span class="text-danger">*</span></label>
                            <div class="col-sm-9">
                                <select name="id_kategori" id="edit_id_kategori" class="form-select select2-search" required>
                                    <option value="">-- Pilih Kategori --</option>
                                    <?php foreach ($categories as $cat): ?>
                                    <option value="<?php echo $cat['id_kategori']; ?>"><?php echo htmlspecialchars($cat['nama_kategori']); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        
                        <div class="row mb-3">
                            <label class="col-sm-3 col-form-label">Gambar Produk</label>
                            <div class="col-sm-9">
                                <input type="file" name="gambar" class="form-control" accept="image/jpeg,image/jpg,image/png" id="edit_gambar">
                                <small class="text-muted">Format: JPG, JPEG, PNG | Ukuran: 300x300 px | Maksimal: 500KB | Kosongkan jika tidak ingin mengubah</small>
                                <div id="editImagePreview" class="mt-2" style="display: none;">
                                    <img id="editPreviewImg" src="" alt="Preview" class="img-thumbnail" style="max-width: 150px; max-height: 150px;">
                                </div>
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

    <!-- Image Modal -->
    <div class="modal fade" id="imageModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="imageModalLabel">Gambar Produk</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body text-center">
                    <div id="imageContainer">
                        <img id="productImage" src="" alt="" class="img-fluid" style="max-width: 100%; max-height: 400px;">
                    </div>
                    <div id="noImageMessage" class="text-muted" style="display: none;">
                        <i class="bi bi-image fs-1 d-block mb-3"></i>
                        <p>Tidak ada gambar untuk produk ini</p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <?php include '_scripts_new.php'; ?>
    
    <script>
    // Image preview for add
    document.getElementById('add_gambar').addEventListener('change', function(e) {
        const file = e.target.files[0];
        if (file) {
            const reader = new FileReader();
            reader.onload = function(e) {
                document.getElementById('addPreviewImg').src = e.target.result;
                document.getElementById('addImagePreview').style.display = 'block';
            };
            reader.readAsDataURL(file);
        } else {
            document.getElementById('addImagePreview').style.display = 'none';
        }
    });
    
    // Image preview for edit
    document.getElementById('edit_gambar').addEventListener('change', function(e) {
        const file = e.target.files[0];
        if (file) {
            const reader = new FileReader();
            reader.onload = function(e) {
                document.getElementById('editPreviewImg').src = e.target.result;
                document.getElementById('editImagePreview').style.display = 'block';
            };
            reader.readAsDataURL(file);
        } else {
            document.getElementById('editImagePreview').style.display = 'none';
        }
    });
    
    // Edit product function
    function editProduct(id) {
        // Redirect to page with edit parameter to get data
        window.location.href = '?edit=' + id;
    }
    
    <?php if ($edit_product): ?>
    // Auto-open edit modal if edit parameter present
    document.addEventListener('DOMContentLoaded', function() {
        document.getElementById('edit_id_produk').value = <?php echo $edit_product['id_produk']; ?>;
        document.getElementById('edit_kode_produk').value = '<?php echo htmlspecialchars($edit_product['kode_produk']); ?>';
        document.getElementById('edit_nama_produk').value = '<?php echo htmlspecialchars($edit_product['nama_produk']); ?>';
        document.getElementById('edit_id_kategori').value = <?php echo $edit_product['id_kategori']; ?>;
        document.getElementById('edit_aktif').checked = <?php echo $edit_product['aktif'] == '1' ? 'true' : 'false'; ?>;
        
        const editModal = new bootstrap.Modal(document.getElementById('editModal'));
        editModal.show();
        
        // Clear edit param from URL after modal shown
        editModal._element.addEventListener('hidden.bs.modal', function () {
            const url = new URL(window.location);
            url.searchParams.delete('edit');
            window.history.replaceState({}, '', url);
        });
    });
    <?php endif; ?>
    
    // Show product image in modal
    function showProductImage(kode_produk, nama_produk) {
        const imageContainer = document.getElementById('imageContainer');
        const noImageMessage = document.getElementById('noImageMessage');
        const productImage = document.getElementById('productImage');
        const modalTitle = document.getElementById('imageModalLabel');
        
        modalTitle.textContent = 'Gambar Produk - ' + nama_produk;
        
        // Try to find image with different extensions
        const extensions = ['jpg', 'jpeg', 'png'];
        let imageFound = false;
        
        function tryNextExtension(index) {
            if (index >= extensions.length) {
                imageContainer.style.display = 'none';
                noImageMessage.style.display = 'block';
                return;
            }
            
            const img = new Image();
            const imagePath = '../images/' + kode_produk + '.' + extensions[index];
            
            img.onload = function() {
                productImage.src = imagePath;
                productImage.alt = nama_produk;
                imageContainer.style.display = 'block';
                noImageMessage.style.display = 'none';
            };
            
            img.onerror = function() {
                tryNextExtension(index + 1);
            };
            
            img.src = imagePath;
        }
        
        tryNextExtension(0);
    }
    </script>
</body>
</html>
