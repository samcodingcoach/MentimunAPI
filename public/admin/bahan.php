<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
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
                $id_kategori = trim($_POST['id_kategori']);
                $nama_bahan = trim($_POST['nama_bahan']);
                $kode_bahan = trim($_POST['kode_bahan']);
                
                if (!empty($id_kategori) && !empty($nama_bahan) && !empty($kode_bahan)) {
                    // Validate kode_bahan max 6 characters
                    if (strlen($kode_bahan) > 6) {
                        $error = 'Kode bahan maksimal 6 karakter!';
                    } else {
                        // Check if kode_bahan already exists
                        $stmt = $conn->prepare("SELECT id_bahan FROM bahan WHERE kode_bahan = ?");
                        $stmt->bind_param("s", $kode_bahan);
                        $stmt->execute();
                        $result = $stmt->get_result();
                        if ($result->fetch_assoc()) {
                            $error = 'Kode bahan sudah digunakan!';
                        } else {
                            $stmt = $conn->prepare("INSERT INTO bahan (id_kategori, nama_bahan, kode_bahan) VALUES (?, ?, ?)");
                            $stmt->bind_param("iss", $id_kategori, $nama_bahan, $kode_bahan);
                            if ($stmt->execute()) {
                                $message = 'Data bahan berhasil ditambahkan!';
                            } else {
                                $error = 'Error: ' . $conn->error;
                            }
                        }
                    }
                } else {
                    $error = 'Semua field wajib diisi!';
                }
                break;
                
            case 'update':
                $id_bahan = $_POST['id_bahan'];
                $id_kategori = trim($_POST['id_kategori']);
                $nama_bahan = trim($_POST['nama_bahan']);
                $kode_bahan = trim($_POST['kode_bahan']);
                
                if (!empty($id_kategori) && !empty($nama_bahan) && !empty($kode_bahan)) {
                    // Validate kode_bahan max 6 characters
                    if (strlen($kode_bahan) > 6) {
                        $error = 'Kode bahan maksimal 6 karakter!';
                    } else {
                        // Check if kode_bahan already exists for other items
                        $stmt = $conn->prepare("SELECT id_bahan FROM bahan WHERE kode_bahan = ? AND id_bahan != ?");
                        $stmt->bind_param("si", $kode_bahan, $id_bahan);
                        $stmt->execute();
                        $result = $stmt->get_result();
                        if ($result->fetch_assoc()) {
                            $error = 'Kode bahan sudah digunakan oleh bahan lain!';
                        } else {
                            $stmt = $conn->prepare("UPDATE bahan SET id_kategori = ?, nama_bahan = ?, kode_bahan = ? WHERE id_bahan = ?");
                            $stmt->bind_param("issi", $id_kategori, $nama_bahan, $kode_bahan, $id_bahan);
                            if ($stmt->execute()) {
                                $message = 'Data bahan berhasil diperbarui!';
                            } else {
                                $error = 'Error: ' . $conn->error;
                            }
                        }
                    }
                } else {
                    $error = 'Semua field wajib diisi!';
                }
                break;
                
            case 'update_biaya':
                $id_bahan = $_POST['id_bahan_biaya']; // This refers to the ingredient ID
                // Remove dots (thousand separators) to get the actual numeric value
                $harga_formatted = trim($_POST['harga_formatted']);
                $harga = str_replace('.', '', $harga_formatted); // Remove dots (thousand separators) to get actual value
                
                // Validate that the cleaned value contains only numbers
                if (!is_numeric($harga)) {
                    $error = 'Harga harus berupa angka yang valid!';
                } else {
                    $harga = (double)$harga; // Convert to double
                    $satuan = trim($_POST['satuan']);
                    
                    if (!empty($id_bahan) && !empty($harga) && !empty($satuan)) {
                        // Check if harga is numeric and positive
                        if ($harga < 0) {
                            $error = 'Harga harus berupa angka positif!';
                        } else {
                            // Check if bahan_biaya table exists
                            $table_check = $conn->query("SHOW TABLES LIKE 'bahan_biaya'");
                            if ($table_check->num_rows == 0) {
                                // Create the table if it doesn't exist with your specified structure
                                $create_table_sql = "CREATE TABLE bahan_biaya (
                                    id_bahan_biaya INT(11) NOT NULL AUTO_INCREMENT,
                                    id_bahan INT(11) DEFAULT 0,
                                    satuan VARCHAR(15) DEFAULT NULL,
                                    harga_satuan DOUBLE DEFAULT 0,
                                    tanggal DATE DEFAULT (CURRENT_DATE),
                                    id_user INT(11) DEFAULT 0,
                                    PRIMARY KEY (id_bahan_biaya)
                                )";
                                $conn->query($create_table_sql);
                            }
                            
                            // Insert new record with id_user from session
                            $id_user = isset($_SESSION['id_user']) ? $_SESSION['id_user'] : 0; // Get user ID from session or default to 0
                            
                            $stmt = $conn->prepare("INSERT INTO bahan_biaya (id_bahan, satuan, harga_satuan, id_user) VALUES (?, ?, ?, ?)");
                            $stmt->bind_param("isdi", $id_bahan, $satuan, $harga, $id_user);
                            
                            if ($stmt->execute()) {
                                $message = 'Data biaya bahan berhasil disimpan!';
                            } else {
                                $error = 'Error: ' . $conn->error;
                            }
                        }
                    } else {
                        $error = 'Semua field wajib diisi!';
                    }
                }
                break;
        }
    }
}

// Get data for edit mode
$edit_data = null;
if (isset($_GET['edit'])) {
    $id_bahan = $_GET['edit'];
    $stmt = $conn->prepare("SELECT b.*, kb.nama_kategori FROM bahan b LEFT JOIN kategori_bahan kb ON b.id_kategori = kb.id_kategori WHERE b.id_bahan = ?");
    $stmt->bind_param("i", $id_bahan);
    $stmt->execute();
    $result = $stmt->get_result();
    $edit_data = $result->fetch_assoc();
}

// Get categories for dropdown
$categories = [];
$result = $conn->query("SELECT id_kategori, nama_kategori FROM kategori_bahan ORDER BY nama_kategori");
while ($row = $result->fetch_assoc()) {
    $categories[] = $row;
}

// Pagination
$limit = 20;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($page - 1) * $limit;

// Search functionality
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$where_clause = '';
$params = [];
$types = '';

if (!empty($search)) {
    $where_clause = "WHERE (b.nama_bahan LIKE ? OR b.kode_bahan LIKE ? OR kb.nama_kategori LIKE ?)";
    $search_param = "%{$search}%";
    $params = [$search_param, $search_param, $search_param];
    $types = 'sss';
}

// Get total count
$count_sql = "SELECT COUNT(*) as total FROM bahan b LEFT JOIN kategori_bahan kb ON b.id_kategori = kb.id_kategori {$where_clause}";
if (!empty($params)) {
    $count_stmt = $conn->prepare($count_sql);
    $count_stmt->bind_param($types, ...$params);
    $count_stmt->execute();
    $total_result = $count_stmt->get_result();
} else {
    $total_result = $conn->query($count_sql);
}
$total_rows = $total_result->fetch_assoc()['total'];
$total_pages = ceil($total_rows / $limit);

// Get data
$sql = "SELECT b.*, kb.nama_kategori FROM bahan b LEFT JOIN kategori_bahan kb ON b.id_kategori = kb.id_kategori {$where_clause} ORDER BY b.id_bahan DESC LIMIT {$limit} OFFSET {$offset}";
if (!empty($params)) {
    $stmt = $conn->prepare($sql);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $result = $stmt->get_result();
} else {
    $result = $conn->query($sql);
}
?>

<!doctype html>
<html lang="id" data-bs-theme="light">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Bahan - Admin</title>
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
                    <h2 class="mb-1">Bahan</h2>
                    <p class="text-muted mb-0">Kelola bahan baku restoran</p>
                </div>
                <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#bahanModal">
                    <i class="bi bi-plus-lg me-2"></i>Tambah Bahan
                </button>
            </div>

            <?php if ($message): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <i class="bi bi-check-circle me-2"></i><?php echo htmlspecialchars($message); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
            <?php endif; ?>

            <?php if ($error): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <i class="bi bi-exclamation-triangle me-2"></i><?php echo htmlspecialchars($error); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
            <?php endif; ?>

            <div class="card-modern">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <div class="d-flex align-items-center">
                        <i class="bi bi-table me-2"></i>
                        <span>Daftar Bahan</span>
                    </div>
                    <form method="GET" class="d-flex">
                        <div class="input-group" style="width: 250px;">
                            <span class="input-group-text bg-white border-end-0">
                                <i class="bi bi-search"></i>
                            </span>
                            <input type="text" name="search" class="form-control border-start-0 ps-0" placeholder="Cari nama bahan..." value="<?php echo htmlspecialchars($search); ?>">
                            <?php if (!empty($search)): ?>
                            <a href="bahan.php" class="btn btn-outline-danger" type="button">
                                <i class="bi bi-x"></i>
                            </a>
                            <?php endif; ?>
                        </div>
                    </form>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">

                    <table class="table table-hover mb-0">
                        <thead>
                            <tr>
                                <th class="text-start" style="width: 5%;">No</th>
                                <th class="text-center" style="width: 10%;">Kode Bahan</th>
                                <th class="text-start" style="width: auto;">Nama Bahan</th>
                                <th class="text-center" style="width: 10%;">Kategori</th>
                                <th class="text-center" style="width: 12%;">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php if ($result->num_rows > 0): ?>
                            <?php $no = $offset + 1; ?>
                            <?php while ($row = $result->fetch_assoc()): ?>
                            <tr>
                                <td class="text-start"><?php echo $no++; ?></td>
                                <td class="text-center"><?php echo htmlspecialchars($row['kode_bahan']); ?></td>
                                <td class="text-start"><strong><?php echo htmlspecialchars($row['nama_bahan']); ?></strong></td>
                                <td class="text-center"><?php echo htmlspecialchars($row['nama_kategori'] ?? '-') ?></td>
                                <td class="text-center">
                                    <div class="table-actions">
                                        <button type="button" class="btn btn-sm btn-outline-warning btn-edit" 
                                                data-id="<?php echo $row['id_bahan']; ?>" 
                                                data-nama="<?php echo addslashes(htmlspecialchars($row['nama_bahan'])); ?>" 
                                                data-kode="<?php echo addslashes(htmlspecialchars($row['kode_bahan'])); ?>" 
                                                data-kategori="<?php echo $row['id_kategori']; ?>">
                                            <i class="bi bi-pencil"></i>
                                        </button>
                                        <button type="button" class="btn btn-sm btn-outline-info btn-biaya" 
                                                data-id="<?php echo $row['id_bahan']; ?>" 
                                                data-nama="<?php echo addslashes(htmlspecialchars($row['nama_bahan'])); ?>">
                                            <i class="bi bi-cash-coin"></i>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="5" class="text-center py-4">
                                    <i class="bi bi-inbox fs-1 d-block mb-2 text-muted"></i>
                                    <p class="text-muted mb-0">Tidak ada data bahan</p>
                                </td>
                            </tr>
                        <?php endif; ?>
                        </tbody>
                    </table>
                    </div>
                </div>

                <div class="card-footer bg-light border-top py-3 px-4">
                    <div class="d-flex justify-content-between align-items-center">
                        <small class="text-muted">Menampilkan <?php echo $offset + 1; ?>-<?php echo min($offset + $limit, $total_rows); ?> dari <?php echo $total_rows; ?> data</small>
                        <nav>
                            <ul class="pagination pagination-sm mb-0">
                                <?php if ($total_pages > 1): ?>
                                <li class="page-item <?php echo $page <= 1 ? 'disabled' : ''; ?>">
                                    <a class="page-link" href="?page=<?php echo ($page - 1); ?><?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?>">
                                        <i class="bi bi-chevron-left"></i>
                                    </a>
                                </li>
                                <?php for ($i = max(1, $page - 2); $i <= min($total_pages, $page + 2); $i++): ?>
                                    <li class="page-item <?php echo ($i == $page) ? 'active' : ''; ?>">
                                        <a class="page-link" href="?page=<?php echo $i; ?><?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?>">
                                            <?php echo $i; ?>
                                        </a>
                                    </li>
                                <?php endfor; ?>
                                <li class="page-item <?php echo $page >= $total_pages ? 'disabled' : ''; ?>">
                                    <a class="page-link" href="?page=<?php echo ($page + 1); ?><?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?>">
                                        <i class="bi bi-chevron-right"></i>
                                    </a>
                                </li>
                                <?php else: ?>
                                <li class="page-item active">
                                    <span class="page-link">1</span>
                                </li>
                                <?php endif; ?>
                            </ul>
                        </nav>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <!-- Add/Edit Modal -->
    <div class="modal fade" id="bahanModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="bi bi-plus-circle me-2"></i><span id="modalTitle">Tambah Bahan</span></h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST" class="form-modern">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="create" id="modalAction">
                        <input type="hidden" name="id_bahan" id="modalIdBahan">
                        
                        <div class="mb-3">
                            <label class="form-label">Kategori Bahan <span class="text-danger">*</span></label>
                            <select class="form-select select2-search" name="id_kategori" id="modalIdKategori" required>
                                <option value="">-- Pilih Kategori --</option>
                                <?php foreach ($categories as $category): ?>
                                    <option value="<?php echo $category['id_kategori']; ?>"><?php echo htmlspecialchars($category['nama_kategori']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Nama Bahan <span class="text-danger">*</span></label>
                            <input type="text" name="nama_bahan" id="modalNamaBahan" class="form-control" required placeholder="Masukkan nama bahan">
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Kode Bahan <span class="text-danger">*</span></label>
                            <input type="text" name="kode_bahan" id="modalKodeBahan" class="form-control" maxlength="6" required placeholder="Maksimal 6 karakter">
                            <div class="form-text">Maksimal 6 karakter</div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                        <button type="submit" class="btn btn-primary" id="modalSubmitBtn"><i class="bi bi-save me-2"></i>Simpan</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Biaya Modal -->
    <div class="modal fade" id="biayaModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="bi bi-cash-coin me-2"></i>Biaya Bahan</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST" class="form-modern">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="update_biaya">
                        <input type="hidden" name="id_bahan_biaya" id="id_bahan_biaya" value="">
                        
                        <div class="mb-3">
                            <label class="form-label">Nama Bahan</label>
                            <input type="text" class="form-control" id="nama_bahan_biaya" readonly>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Harga <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="harga" name="harga_formatted" placeholder="0" required>
                            <input type="hidden" id="harga_hidden" name="harga">
                            <div class="form-text">Contoh: 100.000 / 20.000 / 2000</div>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Satuan <span class="text-danger">*</span></label>
                            <select class="form-select select2-search" name="satuan" id="satuan" required>
                                <option value="">-- Pilih Satuan --</option>
                                <option value="buah">buah</option>
                                <option value="pcs">pcs</option>
                                <option value="kg">kg</option>
                                <option value="g">g</option>
                                <option value="mg">mg</option>
                                <option value="l">l</option>
                                <option value="ml">ml</option>
                                <option value="ons">ons</option>
                                <option value="pon">pon</option>
                                <option value="lt">lt</option>
                                <option value="gr">gr</option>
                                <option value="bh">bh</option>
                                <option value="bhg">bhg</option>
                                <option value="btr">btr</option>
                                <option value="btg">btg</option>
                                <option value="bks">bks</option>
                                <option value="ltr">ltr</option>
                                <option value="bwk">bwk</option>
                                <option value="dus">dus</option>
                                <option value="pack">pack</option>
                            </select>
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

    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
    <script>
        function editBahan(id, nama, kode, kategori) {
            document.getElementById('modalAction').value = 'update';
            document.getElementById('modalIdBahan').value = id;
            document.getElementById('modalIdKategori').value = kategori;
            document.getElementById('modalNamaBahan').value = nama;
            document.getElementById('modalKodeBahan').value = kode;
            document.getElementById('modalTitle').textContent = 'Edit Bahan';
            document.getElementById('modalSubmitBtn').innerHTML = '<i class="bi bi-save me-2"></i>Perbarui';
            
            $('#modalIdKategori').trigger('change');
            
            var modal = new bootstrap.Modal(document.getElementById('bahanModal'));
            modal.show();
        }
        
        function biayaBahan(id, nama) {
            document.getElementById('id_bahan_biaya').value = id;
            document.getElementById('nama_bahan_biaya').value = nama;
            document.getElementById('harga').value = '';
            document.getElementById('harga_hidden').value = '';
            document.getElementById('satuan').value = '';
            
            $('#satuan').trigger('change');
            
            var modal = new bootstrap.Modal(document.getElementById('biayaModal'));
            modal.show();
        }
        
        document.addEventListener('DOMContentLoaded', function() {
            // Modal reset event
            document.getElementById('bahanModal').addEventListener('hidden.bs.modal', function () {
                const modalForm = document.querySelector('#bahanModal form');
                modalForm.reset();
                document.getElementById('modalAction').value = 'create';
                document.getElementById('modalIdBahan').value = '';
                document.getElementById('modalTitle').textContent = 'Tambah Bahan';
                document.getElementById('modalSubmitBtn').innerHTML = '<i class="bi bi-save me-2"></i>Simpan';
                $('#modalIdKategori').val(null).trigger('change');
            });
            
            <?php if ($edit_data): ?>
            editBahan(<?php echo $edit_data['id_bahan']; ?>, '<?php echo htmlspecialchars($edit_data['nama_bahan']); ?>', '<?php echo htmlspecialchars($edit_data['kode_bahan']); ?>', <?php echo $edit_data['id_kategori']; ?>);
            <?php endif; ?>
            
            // Edit button click
            document.addEventListener('click', function(e) {
                if (e.target.closest('.btn-edit')) {
                    e.preventDefault();
                    const btn = e.target.closest('.btn-edit');
                    const id = btn.getAttribute('data-id');
                    const nama = btn.getAttribute('data-nama');
                    const kode = btn.getAttribute('data-kode');
                    const kategori = btn.getAttribute('data-kategori');
                    
                    console.log('Edit button clicked:', {id, nama, kode, kategori});
                    editBahan(parseInt(id), nama, kode, parseInt(kategori));
                }
                
                if (e.target.closest('.btn-biaya')) {
                    const btn = e.target.closest('.btn-biaya');
                    const id = btn.getAttribute('data-id');
                    const nama = btn.getAttribute('data-nama');
                    biayaBahan(parseInt(id), nama);
                }
            });
        });

        $(document).ready(function () {
            $('.select2-search').select2({
                theme: 'bootstrap-5',
                width: '100%',
                placeholder: '-- Pilih --',
                allowClear: true
            });
        });
    </script>
</body>
</html>