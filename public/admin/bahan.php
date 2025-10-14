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
$limit = 10;
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
    <style>
        /* Custom table wrapper padding for bahan page */
        .card-modern .table-responsive {
            padding: 0 15px !important;
        }
        
        /* Custom table column widths for bahan page */
        .card-modern .table th:nth-child(1),
        .card-modern .table td:nth-child(1) { /* No - min width */
            width: 50px !important;
            max-width: 50px !important;
            white-space: nowrap !important;
            text-align: center !important;
        }
        
        .card-modern .table th:nth-child(2),
        .card-modern .table td:nth-child(2) { /* Kode Bahan - fit to content */
            width: 100px !important;
            max-width: 100px !important;
            white-space: nowrap !important;
        }
        
        .card-modern .table th:nth-child(3),
        .card-modern .table td:nth-child(3) { /* Nama Bahan - flexible */
            width: auto !important;
            min-width: 200px !important;
        }
        
        .card-modern .table th:nth-child(4),
        .card-modern .table td:nth-child(4) { /* Kategori - fit to content */
            width: 120px !important;
            max-width: 120px !important;
            white-space: nowrap !important;
        }
        
        .card-modern .table th:nth-child(5),
        .card-modern .table td:nth-child(5) { /* Aksi - fit to content */
            width: 130px !important;
            max-width: 130px !important;
            white-space: nowrap !important;
        }
    </style>
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

                    <table class="table table-hover mb-0" style="table-layout: fixed;">
                        <thead>
                            <tr>
                                <th>No</th>
                                <th>Kode Bahan</th>
                                <th>Nama Bahan</th>
                                <th>Kategori</th>
                                <th>Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php if ($result->num_rows > 0): ?>
                            <?php $no = $offset + 1; ?>
                            <?php while ($row = $result->fetch_assoc()): ?>
                            <tr>
                                <td><?php echo $no++; ?></td>
                                <td><?php echo htmlspecialchars($row['kode_bahan']); ?></td>
                                <td><strong><?php echo htmlspecialchars($row['nama_bahan']); ?></strong></td>
                                <td><?php echo htmlspecialchars($row['nama_kategori'] ?? '-') ?></td>
                                <td>
                                    <div class="table-actions">
                                        <button class="btn btn-sm btn-outline-warning" onclick="editBahan(<?php echo $row['id_bahan']; ?>, '<?php echo htmlspecialchars($row['nama_bahan']); ?>', '<?php echo htmlspecialchars($row['kode_bahan']); ?>', <?php echo $row['id_kategori']; ?>)">
                                            <i class="bi bi-pencil"></i>
                                        </button>
                                        <button class="btn btn-sm btn-outline-info" onclick="biayaBahan(<?php echo $row['id_bahan']; ?>, '<?php echo htmlspecialchars($row['nama_bahan']); ?>')">
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

                <?php if ($total_pages > 1): ?>
                <div class="card-footer bg-light border-top py-3 px-4">
                    <div class="d-flex justify-content-between align-items-center">
                        <small class="text-muted">Menampilkan <?php echo $offset + 1; ?>-<?php echo min($offset + $limit, $total_rows); ?> dari <?php echo $total_rows; ?> data</small>
                        <nav>
                            <ul class="pagination pagination-sm mb-0">
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
                            </ul>
                        </nav>
                    </div>
                </div>
                <?php endif; ?>
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

   <script>
        function editBahan(id, nama, kode, kategori) {
            // Set form to edit mode
            document.getElementById('modalAction').value = 'update';
            document.getElementById('modalIdBahan').value = id;
            document.getElementById('modalIdKategori').value = kategori;
            document.getElementById('modalNamaBahan').value = nama;
            document.getElementById('modalKodeBahan').value = kode;
            document.getElementById('modalTitle').textContent = 'Edit Bahan';
            document.getElementById('modalSubmitBtn').innerHTML = '<i class="bi bi-save me-2"></i>Perbarui';
            
            // Trigger change event for select2
            $('#modalIdKategori').trigger('change');
            
            // Show modal
            var modal = new bootstrap.Modal(document.getElementById('bahanModal'));
            modal.show();
        }
        
        function biayaBahan(id, nama) {
            // Set values for the biaya modal
            document.getElementById('id_bahan_biaya').value = id;
            document.getElementById('nama_bahan_biaya').value = nama;
            
            // Clear and reset inputs
            document.getElementById('harga').value = '';
            document.getElementById('harga_hidden').value = '';
            document.getElementById('satuan').value = '';
            
            // Trigger change event for select2
            $('#satuan').trigger('change');
            
            // Show modal
            var modal = new bootstrap.Modal(document.getElementById('biayaModal'));
            modal.show();
        }
        
        // Reset form when opening add modal
        document.addEventListener('DOMContentLoaded', function() {
            const addModal = document.getElementById('bahanModal');
            addModal.addEventListener('show.bs.modal', function() {
                // Reset to add mode
                document.getElementById('modalAction').value = 'create';
                document.getElementById('modalIdBahan').value = '';
                document.getElementById('modalIdKategori').value = '';
                document.getElementById('modalNamaBahan').value = '';
                document.getElementById('modalKodeBahan').value = '';
                document.getElementById('modalTitle').textContent = 'Tambah Bahan';
                document.getElementById('modalSubmitBtn').innerHTML = '<i class="bi bi-save me-2"></i>Simpan';
                
                // Trigger change event for select2
                $('#modalIdKategori').trigger('change');
            });
        });
        
        <?php if ($edit_data): ?>
        // Show modal for edit mode
        document.addEventListener('DOMContentLoaded', function() {
            editBahan(<?php echo $edit_data['id_bahan']; ?>, '<?php echo htmlspecialchars($edit_data['nama_bahan']); ?>', '<?php echo htmlspecialchars($edit_data['kode_bahan']); ?>', <?php echo $edit_data['id_kategori']; ?>);
        });
        <?php endif; ?>
        
        // Handle form submission success
        document.addEventListener('DOMContentLoaded', function() {
            <?php if ($message): ?>
                // Close modals after successful operation
                setTimeout(function() {
                    const bahanModal = bootstrap.Modal.getInstance(document.getElementById('bahanModal'));
                    const biayaModal = bootstrap.Modal.getInstance(document.getElementById('biayaModal'));
                    if (bahanModal) bahanModal.hide();
                    if (biayaModal) biayaModal.hide();
                    
                    // Remove edit parameter from URL if present
                    if (window.location.search.includes('edit=')) {
                        window.history.replaceState({}, document.title, window.location.pathname);
                    }
                }, 100);
            <?php endif; ?>
        });
        
        // Format number with thousand separators
        function formatNumber(num) {
            // Remove any non-numeric characters except decimal point and minus sign
            num = num.toString().replace(/[^0-9]/g, '');
            // Add thousand separators
            return num.replace(/\B(?=(\d{3})+(?!\d))/g, ".");
        }
        
        // Add event listener for harga input
        document.addEventListener('DOMContentLoaded', function() {
            const hargaInput = document.getElementById('harga');
            if (hargaInput) {
                hargaInput.addEventListener('input', function(e) {
                    let value = e.target.value;
                    // Remove any non-numeric characters except decimal point and minus sign
                    value = value.replace(/[^\d]/g, '');
                    // Format the number
                    let formattedValue = formatNumber(value);
                    // Update the display
                    e.target.value = formattedValue;
                    // Update the hidden input with the numeric value
                    document.getElementById('harga_hidden').value = value;
                });
            }
            
            // Form submission validation for biaya modal
            const biayaForm = document.querySelector('#biayaModal form');
            if (biayaForm) {
                biayaForm.addEventListener('submit', function(e) {
                    // Update hidden field if it's empty but the display field has a value
                    const hargaDisplay = document.getElementById('harga').value;
                    const hargaHidden = document.getElementById('harga_hidden');
                    if (hargaHidden.value === '' && hargaDisplay !== '') {
                        // Remove formatting and set the numeric value
                        const numericValue = hargaDisplay.replace(/[^\d]/g, '');
                        hargaHidden.value = numericValue;
                    }
                });
            }
        });
    </script>
   
   <?php include '_scripts_new.php'; ?>
</body>
</html>
         </form>
       </div>
     </div>
   </div>

   <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
   <script>
     <?php if ($edit_data): ?>
       // Show modal for edit mode
       document.addEventListener('DOMContentLoaded', function() {
         var modal = new bootstrap.Modal(document.getElementById('bahanModal'));
         modal.show();
       });
     <?php endif; ?>
     
     // Initialize searchable dropdown
     document.addEventListener('DOMContentLoaded', function() {
       const displayInput = document.getElementById('kategori_display');
       const hiddenInput = document.getElementById('id_kategori');
       const dropdown = document.getElementById('kategori_dropdown');
       const searchInput = document.getElementById('kategori_search');
       const optionsContainer = document.getElementById('kategori_options');
       const allOptions = optionsContainer.querySelectorAll('.dropdown-item');
       
       // Show dropdown when clicking display input
       displayInput.addEventListener('click', function() {
         dropdown.style.display = 'block';
         searchInput.focus();
       });
       
       // Hide dropdown when clicking outside
       document.addEventListener('click', function(e) {
         if (!e.target.closest('.searchable-select')) {
           dropdown.style.display = 'none';
         }
       });
       
       // Search functionality
       searchInput.addEventListener('input', function() {
         const searchTerm = this.value.toLowerCase();
         allOptions.forEach(option => {
           const text = option.textContent.toLowerCase();
           if (text.includes(searchTerm)) {
             option.style.display = 'block';
           } else {
             option.style.display = 'none';
           }
         });
       });
       
       // Select option
       allOptions.forEach(option => {
         option.addEventListener('click', function() {
           const value = this.getAttribute('data-value');
           const text = this.getAttribute('data-text');
           
           hiddenInput.value = value;
           displayInput.value = text;
           dropdown.style.display = 'none';
           searchInput.value = '';
           
           // Show all options again
           allOptions.forEach(opt => opt.style.display = 'block');
         });
       });
       
       // Clear search when dropdown is shown
       displayInput.addEventListener('focus', function() {
         searchInput.value = '';
         allOptions.forEach(opt => opt.style.display = 'block');
       });
     });
     
     // Handle form submission success
     document.addEventListener('DOMContentLoaded', function() {
       <?php if ($message): ?>
         // Close modal after successful operation
         setTimeout(function() {
           var modal = bootstrap.Modal.getInstance(document.getElementById('bahanModal'));
           if (modal) {
             modal.hide();
           }
           // Remove edit parameter from URL after successful update
           if (window.location.search.includes('edit=')) {
             window.history.replaceState({}, document.title, window.location.pathname);
           }
         }, 100);
       <?php endif; ?>
       
       // Handle "Tambah Bahan" button click
       document.querySelector('[data-bs-target="#bahanModal"]').addEventListener('click', function() {
         // Reset form for add mode
         var form = document.querySelector('#bahanModal form');
         var actionInput = form.querySelector('input[name="action"]');
         var idInput = form.querySelector('input[name="id_bahan"]');
         var kategoriSelect = form.querySelector('select[name="id_kategori"]');
         var namaInput = form.querySelector('input[name="nama_bahan"]');
         var kodeInput = form.querySelector('input[name="kode_bahan"]');
         var modalTitle = document.querySelector('#bahanModal .modal-title');
         var submitBtn = document.querySelector('#bahanModal button[type="submit"]');
         
         // Reset to add mode
         actionInput.value = 'create';
         if (idInput) idInput.remove();
         document.getElementById('id_kategori').value = '';
         document.getElementById('kategori_display').value = '';
         namaInput.value = '';
         kodeInput.value = '';
         modalTitle.textContent = 'Tambah Bahan';
         submitBtn.textContent = 'Simpan';
         
         // Remove edit parameter from URL
         if (window.location.search.includes('edit=')) {
           window.history.replaceState({}, document.title, window.location.pathname);
         }
       });
     });
     
     function editBahan(id, nama, kode, kategori) {
       // Set form to edit mode
       var form = document.querySelector('#bahanModal form');
       var actionInput = form.querySelector('input[name="action"]');
       var idInput = form.querySelector('input[name="id_bahan"]');
       var kategoriSelect = form.querySelector('select[name="id_kategori"]');
       var namaInput = form.querySelector('input[name="nama_bahan"]');
       var kodeInput = form.querySelector('input[name="kode_bahan"]');
       var modalTitle = document.querySelector('#bahanModal .modal-title');
       var submitBtn = document.querySelector('#bahanModal button[type="submit"]');
       
       // Set edit mode
       actionInput.value = 'update';
       
       // Add or update id input
       if (!idInput) {
         idInput = document.createElement('input');
         idInput.type = 'hidden';
         idInput.name = 'id_bahan';
         form.appendChild(idInput);
       }
       idInput.value = id;
       
       // Set form values
       document.getElementById('id_kategori').value = kategori;
       // Find the category name for display
       const categoryOption = document.querySelector(`[data-value="${kategori}"]`);
       if (categoryOption) {
         document.getElementById('kategori_display').value = categoryOption.getAttribute('data-text');
       }
       namaInput.value = nama;
       kodeInput.value = kode;
       modalTitle.textContent = 'Edit Bahan';
       submitBtn.textContent = 'Perbarui';
       
       // Show modal
       var modal = new bootstrap.Modal(document.getElementById('bahanModal'));
       modal.show();
     }
     
     function biayaBahan(id, nama) {
       // Set values for the biaya modal
       document.getElementById('id_bahan_biaya').value = id;
       document.getElementById('nama_bahan_biaya').value = nama;
       
       // Clear and reset inputs
       document.getElementById('harga').value = '';
       document.getElementById('harga_hidden').value = '';
       document.getElementById('satuan_display').value = '';
       document.getElementById('satuan_hidden').value = '';
       
       // Show modal
       var modal = new bootstrap.Modal(document.getElementById('biayaModal'));
       modal.show();
     }
     
     // Handle form submission success
     document.addEventListener('DOMContentLoaded', function() {
       <?php if ($message): ?>
         // Close biaya modal after successful operation if it's open
         setTimeout(function() {
           var biayaModal = bootstrap.Modal.getInstance(document.getElementById('biayaModal'));
           if (biayaModal) {
             biayaModal.hide();
           }
         }, 100);
       <?php endif; ?>
     });
     
     // Format number with thousand separators
     function formatNumber(num) {
       // Remove any non-numeric characters except decimal point and minus sign
       num = num.toString().replace(/[^0-9]/g, '');
       // Add thousand separators
       return num.replace(/\B(?=(\d{3})+(?!\d))/g, ".");
     }
     
     // Add event listener for harga input
     document.getElementById('harga').addEventListener('input', function(e) {
       let value = e.target.value;
       // Remove any non-numeric characters except decimal point and minus sign
       value = value.replace(/[^\d]/g, '');
       // Format the number
       let formattedValue = formatNumber(value);
       // Update the display
       e.target.value = formattedValue;
       // Update the hidden input with the numeric value
       document.getElementById('harga_hidden').value = value;
     });
     
     // Initialize satuan searchable dropdown
     document.addEventListener('DOMContentLoaded', function() {
       const satuanDisplay = document.getElementById('satuan_display');
       const satuanHidden = document.getElementById('satuan_hidden');
       const satuanDropdown = document.getElementById('satuan_dropdown');
       const satuanSearch = document.getElementById('satuan_search');
       const satuanOptionsContainer = document.getElementById('satuan_options');
       const satuanAllOptions = satuanOptionsContainer.querySelectorAll('.dropdown-item');
       
       // Show dropdown when clicking display input
       satuanDisplay.addEventListener('click', function() {
         satuanDropdown.style.display = 'block';
         satuanSearch.focus();
       });
       
       // Hide dropdown when clicking outside
       document.addEventListener('click', function(e) {
         if (!e.target.closest('.searchable-select')) {
           satuanDropdown.style.display = 'none';
         }
       });
       
       // Search functionality for satuan
       satuanSearch.addEventListener('input', function() {
         const searchTerm = this.value.toLowerCase();
         satuanAllOptions.forEach(option => {
           const text = option.textContent.toLowerCase();
           if (text.includes(searchTerm)) {
             option.style.display = 'block';
           } else {
             option.style.display = 'none';
           }
         });
       });
       
       // Select option from dropdown
       satuanAllOptions.forEach(option => {
         option.addEventListener('click', function() {
           const value = this.getAttribute('data-value');
           
           satuanHidden.value = value;
           satuanDisplay.value = value;
           satuanDropdown.style.display = 'none';
           satuanSearch.value = '';
           
           // Show all options again
           satuanAllOptions.forEach(opt => opt.style.display = 'block');
         });
       });
       
       // Allow manual entry - when user types and then clicks away, use the typed value
       satuanDisplay.addEventListener('blur', function() {
         if (this.value.trim() !== '' && !satuanHidden.value) {
           satuanHidden.value = this.value.trim();
         }
       });
       
       // Also update the hidden field when typing
       satuanDisplay.addEventListener('input', function() {
         // Check if the typed value matches any predefined options
         const typedValue = this.value.toLowerCase();
         let matched = false;
         
         satuanAllOptions.forEach(option => {
           const optionText = option.textContent.toLowerCase();
           if (optionText === typedValue) {
             satuanHidden.value = option.getAttribute('data-value');
             matched = true;
           }
         });
         
         // If no match found, use the typed value
         if (!matched) {
           satuanHidden.value = this.value.trim();
         }
       });
       
       // Clear search when dropdown is shown
       satuanDisplay.addEventListener('focus', function() {
         satuanSearch.value = '';
         satuanAllOptions.forEach(opt => opt.style.display = 'block');
       });
     });
   </script>
 </body>
</html>