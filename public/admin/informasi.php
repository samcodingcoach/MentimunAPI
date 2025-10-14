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

// Handle file upload
function uploadImage($file) {
    $target_dir = "../images/";
    $imageFileType = strtolower(pathinfo($file["name"], PATHINFO_EXTENSION));
    $new_filename = uniqid() . '.' . $imageFileType;
    $target_file = $target_dir . $new_filename;
    
    // Check if image file is actual image
    $check = getimagesize($file["tmp_name"]);
    if($check === false) {
        return [false, "File bukan gambar."];
    }
    
    // Check file size (max 500KB)
    if ($file["size"] > 500000) {
        return [false, "File terlalu besar. Maksimal 500KB."];
    }
    
    // Allow certain file formats
    if($imageFileType != "jpg" && $imageFileType != "png" && $imageFileType != "jpeg" && $imageFileType != "gif" ) {
        return [false, "Hanya file JPG, JPEG, PNG & GIF yang diizinkan."];
    }
    
    // Check image dimensions (minimum 1280x720)
    list($width, $height) = getimagesize($file["tmp_name"]);
    if($width < 1280 || $height < 720) {
        return [false, "Resolusi gambar minimal 1280x720 pixel."];
    }
    
    if (move_uploaded_file($file["tmp_name"], $target_file)) {
        return [true, $new_filename];
    } else {
        return [false, "Terjadi kesalahan saat upload file."];
    }
}

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'create':
                $judul = trim($_POST['judul']);
                $isi = trim($_POST['isi']);
                $divisi = trim($_POST['divisi']);
                 $link = trim($_POST['link']);
                 $id_users = $_SESSION['id_user'];
                 $gambar = '';
                 
                 // Handle image upload
                 $gambar = null;
                 if (isset($_FILES['gambar']) && $_FILES['gambar']['error'] == 0) {
                     $upload_dir = dirname(__DIR__) . '/images/';
                     $file_extension = strtolower(pathinfo($_FILES['gambar']['name'], PATHINFO_EXTENSION));
                     $allowed_extensions = ['jpg', 'jpeg', 'png', 'gif'];
                     
                     if (in_array($file_extension, $allowed_extensions)) {
                         $filename = uniqid() . '.' . $file_extension;
                         $upload_path = $upload_dir . $filename;
                         
                         if (move_uploaded_file($_FILES['gambar']['tmp_name'], $upload_path)) {
                            $gambar = $filename;
                        } else {
                            // Fallback: coba copy jika move_uploaded_file gagal
                            if (copy($_FILES['gambar']['tmp_name'], $upload_path)) {
                                $gambar = $filename;
                                unlink($_FILES['gambar']['tmp_name']); // hapus file temporary
                            } else {
                                $error = 'Gagal mengupload gambar. Path: ' . $upload_path . ' | Tmp: ' . $_FILES['gambar']['tmp_name'] . ' | Error: ' . error_get_last()['message'];
                            }
                        }
                     } else {
                         $error = 'Format gambar tidak didukung. Gunakan JPG, JPEG, PNG, atau GIF.';
                     }
                 }

                
                if (!empty($judul) && !empty($isi) && !empty($divisi)) {
                    $stmt = $conn->prepare("INSERT INTO informasi (judul, isi, divisi, gambar, link, id_users, created_time) VALUES (?, ?, ?, ?, ?, ?, NOW())");
                     $stmt->bind_param("sssssi", $judul, $isi, $divisi, $gambar, $link, $id_users);
                    if ($stmt->execute()) {
                        $message = 'Data informasi berhasil ditambahkan!';
                    } else {
                        $error = 'Error: ' . $conn->error;
                    }
                } else {
                    $error = 'Judul, isi, dan divisi wajib diisi!';
                }
                break;
                
            case 'update':
                $id_info = $_POST['id_info'];
                $judul = trim($_POST['judul']);
                $isi = trim($_POST['isi']);
                 $divisi = trim($_POST['divisi']);
                 $link = trim($_POST['link']);
                 $gambar_lama = $_POST['gambar_lama'];
                 $gambar = $gambar_lama;
                 
                 // Handle image upload
                 if (isset($_FILES['gambar']) && $_FILES['gambar']['error'] == 0) {
                     $upload_dir = dirname(__DIR__) . '/images/';
                     $file_extension = strtolower(pathinfo($_FILES['gambar']['name'], PATHINFO_EXTENSION));
                     $allowed_extensions = ['jpg', 'jpeg', 'png', 'gif'];
                     
                     if (in_array($file_extension, $allowed_extensions)) {
                         $filename = uniqid() . '.' . $file_extension;
                         $upload_path = $upload_dir . $filename;
                         
                         if (move_uploaded_file($_FILES['gambar']['tmp_name'], $upload_path)) {
                             // Delete old image if exists
                             if (!empty($gambar_lama) && file_exists($upload_dir . $gambar_lama)) {
                                 unlink($upload_dir . $gambar_lama);
                             }
                             $gambar = $filename;
                         } else {
                             // Fallback: coba copy jika move_uploaded_file gagal
                             if (copy($_FILES['gambar']['tmp_name'], $upload_path)) {
                                 // Delete old image if exists
                                 if (!empty($gambar_lama) && file_exists($upload_dir . $gambar_lama)) {
                                     unlink($upload_dir . $gambar_lama);
                                 }
                                 $gambar = $filename;
                                 unlink($_FILES['gambar']['tmp_name']); // hapus file temporary
                             } else {
                                 $error = 'Gagal mengupload gambar. Path: ' . $upload_path . ' | Tmp: ' . $_FILES['gambar']['tmp_name'] . ' | Error: ' . error_get_last()['message'];
                             }
                         }
                     } else {
                         $error = 'Format gambar tidak didukung. Gunakan JPG, JPEG, PNG, atau GIF.';
                     }
                 }
                 
                 if (!empty($judul) && !empty($isi) && !empty($divisi)) {
                     $stmt = $conn->prepare("UPDATE informasi SET judul = ?, isi = ?, divisi = ?, gambar = ?, link = ?, id_users = ?, created_time = NOW() WHERE id_info = ?");
                     $stmt->bind_param("sssssii", $judul, $isi, $divisi, $gambar, $link, $id_users, $id_info);
                    if ($stmt->execute()) {
                        $message = 'Data informasi berhasil diperbarui!';
                    } else {
                        $error = 'Error: ' . $conn->error;
                    }
                } else {
                    $error = 'Judul, isi, dan divisi harus diisi!';
                }
                break;
                
            case 'delete':
                $id_info = $_POST['id_info'];
                
                $stmt = $conn->prepare("DELETE FROM informasi WHERE id_info = ?");
                $stmt->bind_param("i", $id_info);
                if ($stmt->execute()) {
                    $message = 'Data informasi berhasil dihapus!';
                } else {
                    $error = 'Error: ' . $conn->error;
                }
                break;
        }
    }
}

// Handle delete via GET parameter
if (isset($_GET['delete'])) {
    $id_info = (int)$_GET['delete'];
    
    // Get image filename before deleting
    $stmt = $conn->prepare("SELECT gambar FROM informasi WHERE id_info = ?");
    $stmt->bind_param("i", $id_info);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    
    if ($row && !empty($row['gambar'])) {
        $image_path = dirname(__DIR__) . '/images/' . $row['gambar'];
        if (file_exists($image_path)) {
            unlink($image_path);
        }
    }
    
    $stmt = $conn->prepare("DELETE FROM informasi WHERE id_info = ?");
    $stmt->bind_param("i", $id_info);
    if ($stmt->execute()) {
        $message = 'Data informasi berhasil dihapus!';
    } else {
        $error = 'Error: ' . $conn->error;
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
    $where_conditions[] = "(judul LIKE ? OR isi LIKE ?)";
    $search_param = "%$search%";
    $params[] = $search_param;
    $params[] = $search_param;
    $types .= 'ss';
}

if (!empty($filter) && in_array($filter, ['All', 'Admin', 'Kasir', 'Pramusaji', 'Dapur'])) {
    $where_conditions[] = "divisi = ?";
    $params[] = $filter;
    $types .= 's';
}

$where_clause = !empty($where_conditions) ? 'WHERE ' . implode(' AND ', $where_conditions) : '';

// Get total count for pagination
$count_sql = "SELECT COUNT(*) as total FROM informasi $where_clause";
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

// Get informasi data with user info
$sql = "SELECT i.*, p.nama_lengkap FROM informasi i LEFT JOIN pegawai p ON i.id_users = p.id_user $where_clause ORDER BY i.created_time DESC LIMIT $limit OFFSET $offset";
if (!empty($params)) {
    $stmt = $conn->prepare($sql);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $result = $stmt->get_result();
} else {
    $result = $conn->query($sql);
}

// Fetch all results into an array
$informasi = [];
if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $informasi[] = $row;
    }
}
?>

<!DOCTYPE html>

<!doctype html>
<html lang="id" data-bs-theme="light">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Informasi - Admin</title>
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
                    <h2 class="mb-1"><i class="bi bi-info-circle me-2"></i>Informasi</h2>
                    <p class="text-muted mb-0">Kelola informasi dan pengumuman untuk semua divisi</p>
                </div>
                <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addModal">
                    <i class="bi bi-plus-lg me-2"></i>Tambah Informasi
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
                        <span>Daftar Informasi</span>
                    </div>
                    <div class="d-flex gap-2">
                        <form class="d-flex" method="GET" action="">
                            <div class="input-group" style="width: 250px;">
                                <span class="input-group-text bg-white border-end-0">
                                    <i class="bi bi-search"></i>
                                </span>
                                <input type="text" name="search" class="form-control border-start-0 ps-0" placeholder="Cari informasi..." value="<?php echo htmlspecialchars($search); ?>">
                            </div>
                        </form>
                        <select class="form-select" style="width: 150px;" onchange="window.location.href='?filter='+this.value+'&search=<?php echo urlencode($search); ?>'">
                            <option value="">Semua Divisi</option>
                            <option value="Semua" <?php echo $filter === 'Semua' ? 'selected' : ''; ?>>Semua</option>
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
                                <th style="width: 5%;">No</th>
                                <th>Judul</th>
                                <th style="width: 10%;">Gambar</th>
                                <th style="width: 5%;">Divisi</th>
                                <th style="width: 15%;">Pembuat</th>
                                <th style="width: 10%;">Tanggal</th>
                                <th style="width: 15%;">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php if (!empty($informasi)): ?>
                            <?php foreach ($informasi as $index => $info): ?>
                            <tr>
                                <td class="text-center"><?php echo $offset + $index + 1; ?></td>
                                <td>
                                    <strong><?php echo htmlspecialchars($info['judul']); ?></strong>
                                    <?php if (!empty($info['link'])): ?>
                                    <br><a href="<?php echo htmlspecialchars($info['link']); ?>" target="_blank" class="text-primary small">
                                        <i class="bi bi-link-45deg"></i> Link
                                    </a>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if (!empty($info['gambar']) && file_exists('../images/' . $info['gambar'])): ?>
                                        <img src="../images/<?php echo htmlspecialchars($info['gambar']); ?>" alt="Gambar" class="img-thumbnail" style="max-width: 150px; max-height: 100px; object-fit: cover;">
                                    <?php else: ?>
                                        <span class="text-muted">-</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="badge bg-primary"><?php echo htmlspecialchars($info['divisi']); ?></span>
                                </td>
                                <td><?php echo htmlspecialchars($info['nama_lengkap']); ?></td>
                                <td><?php echo date('d/m/Y H:i', strtotime($info['created_time'])); ?></td>
                                <td>
                                    <div class="table-actions">
                                        <button class="btn btn-sm btn-outline-primary" onclick="viewInfo(<?php echo htmlspecialchars(json_encode($info)); ?>)" title="Lihat Detail">
                                            <i class="bi bi-eye"></i>
                                        </button>
                                        <button class="btn btn-sm btn-outline-warning" onclick="editInfo(<?php echo htmlspecialchars(json_encode($info)); ?>)" title="Edit">
                                            <i class="bi bi-pencil"></i>
                                        </button>
                                        <button class="btn btn-sm btn-outline-danger" onclick="confirmDelete(<?php echo $info['id_info']; ?>, '<?php echo htmlspecialchars(addslashes($info['judul'])); ?>')" title="Hapus">
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
                                    <p class="text-muted mb-0">Tidak ada data informasi</p>
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
                        <small class="text-muted">Menampilkan <?php echo $offset + 1; ?>-<?php echo min($offset + $limit, $total_records); ?> dari <?php echo $total_records; ?> informasi</small>
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
                    <h5 class="modal-title"><i class="bi bi-plus-circle me-2"></i>Tambah Informasi</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST" enctype="multipart/form-data" class="form-modern">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="create">
                        <div class="row mb-3">
                            <label class="col-sm-3 col-form-label">Judul <span class="text-danger">*</span></label>
                            <div class="col-sm-9">
                                <input type="text" name="judul" class="form-control" required placeholder="Masukkan judul informasi">
                            </div>
                        </div>
                        <div class="row mb-3">
                            <label class="col-sm-3 col-form-label">Isi <span class="text-danger">*</span></label>
                            <div class="col-sm-9">
                                <textarea name="isi" class="form-control" rows="5" required placeholder="Masukkan isi informasi"></textarea>
                            </div>
                        </div>
                        <div class="row mb-3">
                            <label class="col-sm-3 col-form-label">Divisi <span class="text-danger">*</span></label>
                            <div class="col-sm-9">
                                <select name="divisi" class="form-select select2-search" required>
                                    <option value="">-- Pilih Divisi --</option>
                                    <option value="Semua">Semua</option>
                                    <option value="Kasir">Kasir</option>
                                    <option value="Dapur">Dapur</option>
                                </select>
                            </div>
                        </div>
                        <div class="row mb-3">
                            <label class="col-sm-3 col-form-label">Link (Opsional)</label>
                            <div class="col-sm-9">
                                <input type="url" name="link" class="form-control" placeholder="https://example.com">
                                <small class="text-muted">URL eksternal terkait informasi ini</small>
                            </div>
                        </div>
                        <div class="row mb-3">
                            <label class="col-sm-3 col-form-label">Gambar (Opsional)</label>
                            <div class="col-sm-9">
                                <input type="file" name="gambar" class="form-control" accept="image/jpeg,image/jpg,image/png,image/gif">
                                <small class="text-muted">Format: JPG, JPEG, PNG, GIF. Max 500KB. Min 1280x720px</small>
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
                    <h5 class="modal-title"><i class="bi bi-pencil-square me-2"></i>Edit Informasi</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST" enctype="multipart/form-data" class="form-modern">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="update">
                        <input type="hidden" name="id_info" id="edit_id_info">
                        <input type="hidden" name="gambar_lama" id="edit_gambar_lama">
                        <div class="row mb-3">
                            <label class="col-sm-3 col-form-label">Judul <span class="text-danger">*</span></label>
                            <div class="col-sm-9">
                                <input type="text" name="judul" id="edit_judul" class="form-control" required>
                            </div>
                        </div>
                        <div class="row mb-3">
                            <label class="col-sm-3 col-form-label">Isi <span class="text-danger">*</span></label>
                            <div class="col-sm-9">
                                <textarea name="isi" id="edit_isi" class="form-control" rows="5" required></textarea>
                            </div>
                        </div>
                        <div class="row mb-3">
                            <label class="col-sm-3 col-form-label">Divisi <span class="text-danger">*</span></label>
                            <div class="col-sm-9">
                                <select name="divisi" id="edit_divisi" class="form-select" required>
                                    <option value="">-- Pilih Divisi --</option>
                                    <option value="Semua">Semua</option>
                                    <option value="Kasir">Kasir</option>
                                    <option value="Dapur">Dapur</option>
                                </select>
                            </div>
                        </div>
                        <div class="row mb-3">
                            <label class="col-sm-3 col-form-label">Link (Opsional)</label>
                            <div class="col-sm-9">
                                <input type="url" name="link" id="edit_link" class="form-control">
                            </div>
                        </div>
                        <div class="row mb-3">
                            <label class="col-sm-3 col-form-label">Gambar Saat Ini</label>
                            <div class="col-sm-9">
                                <div id="edit_current_image"></div>
                            </div>
                        </div>
                        <div class="row mb-3">
                            <label class="col-sm-3 col-form-label">Gambar Baru (Opsional)</label>
                            <div class="col-sm-9">
                                <input type="file" name="gambar" class="form-control" accept="image/jpeg,image/jpg,image/png,image/gif">
                                <small class="text-muted">Kosongkan jika tidak ingin mengubah gambar</small>
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

    <!-- View Modal -->
    <div class="modal fade" id="viewModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="bi bi-eye me-2"></i>Detail Informasi</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div id="view_content"></div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Tutup</button>
                </div>
            </div>
        </div>
    </div>

    <?php include '_scripts_new.php'; ?>
    
    <script>
        function editInfo(info) {
            document.getElementById('edit_id_info').value = info.id_info;
            document.getElementById('edit_judul').value = info.judul;
            document.getElementById('edit_isi').value = info.isi;
            document.getElementById('edit_divisi').value = info.divisi;
            document.getElementById('edit_link').value = info.link || '';
            document.getElementById('edit_gambar_lama').value = info.gambar || '';
            
            // Show current image
            const imgContainer = document.getElementById('edit_current_image');
            if (info.gambar) {
                imgContainer.innerHTML = '<img src="../images/' + info.gambar + '" class="img-thumbnail" style="max-width: 200px;">';
            } else {
                imgContainer.innerHTML = '<span class="text-muted">Tidak ada gambar</span>';
            }
            
            var editModal = new bootstrap.Modal(document.getElementById('editModal'));
            editModal.show();
        }
        
        function viewInfo(info) {
            let html = '<div class="row">';
            html += '<div class="col-md-12 mb-3">';
            html += '<h4>' + info.judul + '</h4>';
            html += '<hr>';
            html += '</div>';
            
            if (info.gambar) {
                html += '<div class="col-md-12 mb-3">';
                html += '<img src="../images/' + info.gambar + '" class="img-fluid rounded" style="max-width: 100%;">';
                html += '</div>';
            }
            
            html += '<div class="col-md-12 mb-3">';
            html += '<h6>Isi:</h6>';
            html += '<p style="white-space: pre-wrap;">' + info.isi + '</p>';
            html += '</div>';
            
            html += '<div class="col-md-6 mb-2">';
            html += '<strong>Divisi:</strong> <span class="badge bg-primary">' + info.divisi + '</span>';
            html += '</div>';
            
            html += '<div class="col-md-6 mb-2">';
            html += '<strong>Pembuat:</strong> ' + info.nama_lengkap;
            html += '</div>';
            
            html += '<div class="col-md-6 mb-2">';
            html += '<strong>Tanggal:</strong> ' + new Date(info.created_time).toLocaleString('id-ID');
            html += '</div>';
            
            if (info.link) {
                html += '<div class="col-md-6 mb-2">';
                html += '<strong>Link:</strong> <a href="' + info.link + '" target="_blank">' + info.link + '</a>';
                html += '</div>';
            }
            
            html += '</div>';
            
            document.getElementById('view_content').innerHTML = html;
            var viewModal = new bootstrap.Modal(document.getElementById('viewModal'));
            viewModal.show();
        }
        
        function confirmDelete(id, judul) {
            if (confirm('Apakah Anda yakin ingin menghapus informasi "' + judul + '"?')) {
                window.location.href = '?delete=' + id;
            }
        }
    </script>
</body>
</html>
