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
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'add_harga') {
        $id_produk = (int)$_POST['id_produk'];
        $tgl = $_POST['tgl'];
        $harga_pokok_resep = (float)$_POST['harga_pokok_resep'];
        $biaya_produksi = (float)$_POST['biaya_produksi'];
        $margin = (float)$_POST['margin'];
        $nominal = (float)$_POST['nominal'];
        $id_user = $_SESSION['id_user'];
        
        // Get id_resep from produk_menu
        $resep_stmt = $conn->prepare("SELECT id_resep FROM produk_menu WHERE id_produk = ?");
        $resep_stmt->bind_param("i", $id_produk);
        $resep_stmt->execute();
        $resep_result = $resep_stmt->get_result();
        
        if ($resep_result->num_rows > 0) {
            $resep_data = $resep_result->fetch_assoc();
            $id_resep = $resep_data['id_resep'];
            
            $insert_stmt = $conn->prepare("INSERT INTO harga_menu (id_produk, id_resep, tgl, harga_pokok_resep, biaya_produksi, margin, nominal, id_user) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
            $insert_stmt->bind_param("iisddddi", $id_produk, $id_resep, $tgl, $harga_pokok_resep, $biaya_produksi, $margin, $nominal, $id_user);
            
            if ($insert_stmt->execute()) {
                $message = 'Harga berhasil ditambahkan!';
            } else {
                $error = 'Gagal menambahkan harga: ' . $conn->error;
            }
        } else {
            $error = 'Produk tidak memiliki resep yang terkait!';
        }
    }
    
    if ($action === 'update_harga') {
        $id_harga = (int)$_POST['id_harga'];
        $biaya_produksi = (float)$_POST['biaya_produksi'];
        $margin = (float)$_POST['margin'];
        $nominal = (float)$_POST['nominal'];
        
        $update_stmt = $conn->prepare("UPDATE harga_menu SET nominal = ?, biaya_produksi = ?, margin = ? WHERE id_harga = ?");
        $update_stmt->bind_param("dddi", $nominal, $biaya_produksi, $margin, $id_harga);
        
        if ($update_stmt->execute()) {
            $message = 'Harga berhasil diperbarui!';
        } else {
            $error = 'Gagal memperbarui harga: ' . $conn->error;
        }
    }
}

// Get id_produk from URL
$id_produk = isset($_GET['id_produk']) ? (int)$_GET['id_produk'] : 0;

if ($id_produk <= 0) {
    header('Location: menu.php');
    exit();
}

// Get product information
$product_info = null;
$stmt = $conn->prepare("SELECT pm.nama_produk, pm.kode_produk, km.nama_kategori 
                       FROM produk_menu pm 
                       INNER JOIN kategori_menu km ON pm.id_kategori = km.id_kategori 
                       WHERE pm.id_produk = ?");
$stmt->bind_param("i", $id_produk);
$stmt->execute();
$result = $stmt->get_result();
if ($result->num_rows > 0) {
    $product_info = $result->fetch_assoc();
} else {
    header('Location: menu.php');
    exit();
}

// Pagination settings
$limit = 10;
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$offset = ($page - 1) * $limit;

// Get total count
$count_stmt = $conn->prepare("SELECT COUNT(*) as total FROM harga_menu hm
                              INNER JOIN produk_menu pm ON hm.id_produk = pm.id_produk
                              INNER JOIN resep r ON hm.id_resep = r.id_resep
                              INNER JOIN pegawai pg ON hm.id_user = pg.id_user
                              WHERE pm.id_produk = ?");
$count_stmt->bind_param("i", $id_produk);
$count_stmt->execute();
$count_result = $count_stmt->get_result();
$total_records = $count_result->fetch_assoc()['total'];
$total_pages = ceil($total_records / $limit);

// Get harga data with pagination
$harga_data = [];
$stmt = $conn->prepare("SELECT
                        DATE_FORMAT(harga_menu.tgl,'%d %M %Y %H:%i') AS tgl, 
                        harga_menu.id_harga, 
                        harga_menu.id_resep,
                        CONCAT(pg1.nama_lengkap,' - ',pg1.jabatan) as user_harga,
                        CONCAT(pg2.nama_lengkap,' - ',pg2.jabatan) as user_resep, 
                        resep.harga_pokok_resep, 
                        harga_menu.biaya_produksi, 
                        harga_menu.margin as margin, 
                        harga_menu.nominal
                       FROM harga_menu
                       INNER JOIN produk_menu ON harga_menu.id_produk = produk_menu.id_produk
                       INNER JOIN resep ON harga_menu.id_resep = resep.id_resep
                       INNER JOIN pegawai pg1 ON harga_menu.id_user = pg1.id_user
                       INNER JOIN pegawai pg2 ON resep.id_user = pg2.id_user
                       WHERE produk_menu.id_produk = ?
                       ORDER BY DATE(harga_menu.tgl) desc, harga_menu.nominal ASC
                       LIMIT ? OFFSET ?");
$stmt->bind_param("iii", $id_produk, $limit, $offset);
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $harga_data[] = $row;
}

// Get available resep for this product
$resep_data = [];
$resep_stmt = $conn->prepare("SELECT 
                            resep.id_resep,
                            resep.kode_resep,
                            COALESCE(SUM(resep_detail.nilai_ekpetasi), 0) as harga_pokok_resep
                            FROM resep 
                            LEFT JOIN resep_detail ON resep.id_resep = resep_detail.id_resep
                            WHERE resep.id_produk = ? AND resep.publish_menu = 1
                            GROUP BY resep.id_resep
                            ORDER BY resep.tanggal_release DESC");
$resep_stmt->bind_param("i", $id_produk);
$resep_stmt->execute();
$resep_result = $resep_stmt->get_result();
while ($resep_row = $resep_result->fetch_assoc()) {
    $resep_data[] = $resep_row;
}
?>

<!doctype html>
<html lang="id" data-bs-theme="light">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Set Harga Menu - Admin</title>
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
                    <h2 class="mb-1"><i class="bi bi-tag me-2"></i>Set Harga Menu</h2>
                    <p class="text-muted mb-0"><?php echo htmlspecialchars($product_info['nama_produk']); ?> (<?php echo htmlspecialchars($product_info['kode_produk']); ?>)</p>
                </div>
                <a href="menu.php" class="btn btn-outline-secondary">
                    <i class="bi bi-arrow-left me-2"></i> Kembali ke Menu
                </a>
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

            <!-- Harga Table -->
            <div class="card-modern">
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table mb-0">
                            <thead>
                                <tr>
                                    <th width="50">No</th>
                                    <th>Tanggal</th>
                                    <th style="width: 15%;" class="text-end">Harga Pokok</th>
                                    <th style="width: 14%;" class="text-end">Biaya Produksi</th>
                                    <th style="width: 15%;" class="text-end">Margin</th>
                                    <th style="width: 15%;"  class="text-end">Nominal</th>
                                    <th width="100">Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($harga_data)): ?>
                                <tr>
                                    <td colspan="7" class="text-center py-4">
                                        <i class="bi bi-inbox fs-1 text-muted d-block mb-2"></i>
                                        <small class="text-muted">Tidak ada data harga</small>
                                    </td>
                                </tr>
                                <?php else: ?>
                                <?php 
                                $no = ($page - 1) * $limit + 1;
                                foreach ($harga_data as $harga): 
                                ?>
                                <tr>
                                    <td><?php echo $no++; ?></td>
                                    <td><?php echo htmlspecialchars($harga['tgl']); ?></td>
                                    <td class="text-end">Rp <?php echo number_format($harga['harga_pokok_resep'], 0, ',', '.'); ?></td>
                                    <td class="text-end">Rp <?php echo number_format($harga['biaya_produksi'], 0, ',', '.'); ?></td>
                                    <td class="text-end">Rp <?php echo number_format($harga['margin'], 0, ',', '.'); ?></td>
                                    <td class="text-end">
                                        <strong>Rp <?php echo number_format($harga['nominal'], 0, ',', '.'); ?></strong>
                                    </td>
                                    <td>
                                        <button type="button" class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#hargaModal" 
                                                onclick="editHarga(<?php echo $harga['id_harga']; ?>, <?php echo $harga['harga_pokok_resep']; ?>, <?php echo $harga['biaya_produksi']; ?>, <?php echo $harga['margin']; ?>, <?php echo $harga['nominal']; ?>)">
                                            <i class="bi bi-pencil"></i>
                                        </button>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
                <?php if ($total_pages > 1): ?>
                <div class="card-footer">
                    <nav aria-label="Page navigation">
                        <ul class="pagination pagination-sm justify-content-center mb-0">
                            <?php if ($page > 1): ?>
                            <li class="page-item">
                                <a class="page-link" href="?id_produk=<?php echo $id_produk; ?>&page=<?php echo $page - 1; ?>">
                                    <i class="bi bi-chevron-left"></i>
                                </a>
                            </li>
                            <?php endif; ?>
                            
                            <?php for ($i = max(1, $page - 2); $i <= min($total_pages, $page + 2); $i++): ?>
                            <li class="page-item <?php echo $i == $page ? 'active' : ''; ?>">
                                <a class="page-link" href="?id_produk=<?php echo $id_produk; ?>&page=<?php echo $i; ?>">
                                    <?php echo $i; ?>
                                </a>
                            </li>
                            <?php endfor; ?>
                            
                            <?php if ($page < $total_pages): ?>
                            <li class="page-item">
                                <a class="page-link" href="?id_produk=<?php echo $id_produk; ?>&page=<?php echo $page + 1; ?>">
                                    <i class="bi bi-chevron-right"></i>
                                </a>
                            </li>
                            <?php endif; ?>
                        </ul>
                    </nav>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </main>

    <!-- Modal Harga -->
    <div class="modal fade" id="hargaModal" tabindex="-1" aria-labelledby="hargaModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <form id="hargaForm" method="POST">
                    <div class="modal-header">
                        <h5 class="modal-title" id="hargaModalLabel">Set Harga Menu</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <input type="hidden" name="action" value="add_harga">
                        <input type="hidden" name="id_harga" id="id_harga">
                        <input type="hidden" name="id_produk" value="<?php echo $id_produk; ?>">
                        <input type="hidden" name="id_resep" id="id_resep">
                        <input type="hidden" name="tgl" id="tgl" value="<?php echo date('Y-m-d'); ?>">
                        
                        <div class="mb-3">
                            <label for="harga_pokok_resep" class="form-label">Harga Pokok Resep</label>
                            <input type="number" class="form-control" name="harga_pokok_resep" id="harga_pokok_resep" step="0.01" readonly>
                        </div>
                        
                        <div class="mb-3">
                            <label for="biaya_produksi" class="form-label">Biaya Produksi <span class="text-danger">*</span></label>
                            <input type="number" class="form-control" name="biaya_produksi" id="biaya_produksi" step="0.01" required>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="margin_type" class="form-label">Tipe Margin <span class="text-danger">*</span></label>
                                    <select class="form-select" name="margin_type" id="margin_type" onchange="calculateMargin()" required>
                                        <option value="">Pilih Tipe Margin</option>
                                        <option value="persen">Persen (%)</option>
                                        <option value="nominal">Nominal (Rp)</option>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="margin_value" class="form-label">Nilai Margin <span class="text-danger">*</span></label>
                                    <input type="number" class="form-control" name="margin_value" id="margin_value" step="0.01" onchange="calculateMargin()" required>
                                </div>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="margin" class="form-label">Margin (Rp)</label>
                                    <input type="number" class="form-control" name="margin" id="margin" step="0.01" readonly>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="nominal" class="form-label">Harga Jual (Nominal)</label>
                                    <input type="number" class="form-control" name="nominal" id="nominal" step="0.01" readonly>
                                    <div class="form-text">Harga Pokok + Biaya Produksi + Margin</div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-save me-2"></i>Simpan Harga
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <?php include '_scripts_new.php'; ?>
    
    <script>
        function addHarga() {
            document.getElementById('hargaModalLabel').textContent = 'Tambah Harga Menu';
            document.querySelector('input[name="action"]').value = 'add_harga';
            document.getElementById('id_harga').value = '';
            document.getElementById('biaya_produksi').value = '';
            document.getElementById('margin_type').value = '';
            document.getElementById('margin_value').value = '';
            document.getElementById('margin').value = '';
            document.getElementById('nominal').value = '';
            
            // Set harga pokok resep from available resep
            <?php if (!empty($resep_data)): ?>
            document.getElementById('harga_pokok_resep').value = <?php echo $resep_data[0]['harga_pokok_resep']; ?>;
            document.getElementById('id_resep').value = <?php echo $resep_data[0]['id_resep']; ?>;
            <?php endif; ?>
        }
        
        function editHarga(id_harga, harga_pokok, biaya_produksi, margin, nominal) {
            document.getElementById('hargaModalLabel').textContent = 'Edit Harga Menu';
            document.querySelector('input[name="action"]').value = 'update_harga';
            document.getElementById('id_harga').value = id_harga;
            document.getElementById('harga_pokok_resep').value = harga_pokok;
            document.getElementById('biaya_produksi').value = biaya_produksi;
            document.getElementById('margin').value = margin;
            document.getElementById('nominal').value = nominal;
        }
        
        function calculateMargin() {
            const hargaPokok = parseFloat(document.getElementById('harga_pokok_resep').value) || 0;
            const biayaProduksi = parseFloat(document.getElementById('biaya_produksi').value) || 0;
            const marginType = document.getElementById('margin_type').value;
            const marginValue = parseFloat(document.getElementById('margin_value').value) || 0;
            
            let margin = 0;
            const totalCost = hargaPokok + biayaProduksi;
            
            if (marginType === 'persen') {
                margin = totalCost * (marginValue / 100);
            } else if (marginType === 'nominal') {
                margin = marginValue;
            }
            
            const nominal = totalCost + margin;
            
            document.getElementById('margin').value = margin.toFixed(2);
            document.getElementById('nominal').value = nominal.toFixed(2);
        }
        
        // Auto calculate when harga pokok or biaya produksi changes
        document.getElementById('harga_pokok_resep').addEventListener('input', calculateMargin);
        document.getElementById('biaya_produksi').addEventListener('input', calculateMargin);
    </script>
</body>
</html>
