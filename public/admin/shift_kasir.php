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
$selected_date = date('Y-m-d'); // Default to today
$shift_data = [];

// Handle POST actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'create') {
        $id_user = $_POST['id_pegawai'] ?? '';
        $first_cash_drawer = $_POST['cash_awal'] ?? 0;
        $tanggal_open = $_POST['tanggal'] ?? date('Y-m-d');
        $id_user2 = $_SESSION['user_id'] ?? 1; // ID user yang login
        
        // Check for duplicate
        $check_query = "SELECT id_open FROM state_open_closing WHERE DATE(tanggal_open) = ? AND id_user = ?";
        $check_stmt = $conn->prepare($check_query);
        $check_stmt->bind_param("si", $tanggal_open, $id_user);
        $check_stmt->execute();
        $result = $check_stmt->get_result();
        $exists = $result->num_rows > 0;
        
        if ($exists) {
            $_SESSION['error'] = 'Kasir sudah memiliki shift pada tanggal tersebut!';
        } else {
            // Create full datetime
            $jam_sekarang = date('H:i:s');
            $tanggal_open_full = $tanggal_open . ' ' . $jam_sekarang;
            
            $insert_query = "INSERT INTO state_open_closing (tanggal_open, first_cash_drawer, id_user, id_user2) VALUES (?, ?, ?, ?)";
            $insert_stmt = $conn->prepare($insert_query);
            $insert_stmt->bind_param("sdii", $tanggal_open_full, $first_cash_drawer, $id_user, $id_user2);
            
            if ($insert_stmt->execute()) {
                $_SESSION['success'] = 'Shift berhasil ditambahkan!';
            } else {
                $_SESSION['error'] = 'Gagal menambahkan shift: ' . $conn->error;
            }
        }
        
        header('Location: shift_kasir.php');
        exit();
    }
}

// Get kasir list for dropdown
$kasir_query = "SELECT id_user, nama_lengkap, jabatan FROM pegawai WHERE aktif = '1' AND (jabatan = 'kasir' OR jabatan = 'pramusaji') ORDER BY nama_lengkap ASC";
$kasir_result = $conn->query($kasir_query);
$kasir_list = $kasir_result->fetch_all(MYSQLI_ASSOC);

// Handle date filter
if (isset($_GET['tanggal']) && !empty($_GET['tanggal'])) {
    $selected_date = $_GET['tanggal'];
}

// Get shift data based on selected date
try {
    $sql = "SELECT
        state_open_closing.id_open, 
        DATE_FORMAT(state_open_closing.tanggal_open,'%d %M %Y %H:%i') as tanggal_open,
        FORMAT((state_open_closing.first_cash_drawer - state_open_closing.manual_total_cash ),0) as cash_awal,
        FORMAT(state_open_closing.total_qris,0) as qris, 
        FORMAT(state_open_closing.manual_total_bank,0) as transfer, 
        FORMAT(state_open_closing.manual_total_cash,0) as cash, 
        FORMAT(
            (state_open_closing.total_qris + 
             state_open_closing.manual_total_bank + 
             state_open_closing.manual_total_cash + 
             (state_open_closing.first_cash_drawer - state_open_closing.manual_total_cash)), 0
        ) AS grand_total,
        state_open_closing.id_user, 
        CONCAT('[',pegawai.jabatan,'] ',pegawai.nama_lengkap) as kasir,
        state_open_closing.status,
        state_open_closing.total_qris as qris_raw,
        state_open_closing.manual_total_bank as transfer_raw,
        state_open_closing.manual_total_cash as cash_raw
    FROM
        state_open_closing
        INNER JOIN
        pegawai
        ON 
            state_open_closing.id_user = pegawai.id_user 
    WHERE DATE(tanggal_open) = ?
    ORDER BY state_open_closing.tanggal_open DESC";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $selected_date);
    $stmt->execute();
    $result = $stmt->get_result();
    $shift_data = $result->fetch_all(MYSQLI_ASSOC);
} catch (Exception $e) {
    $error = 'Error mengambil data: ' . $e->getMessage();
}
?>

<!DOCTYPE html>

<!doctype html>
<html lang="id" data-bs-theme="light">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Shift Kasir - Admin</title>
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
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h2 class="mb-1"><i class="bi bi-clock-history me-2"></i>Shift Kasir</h2>
                    <p class="text-muted mb-0">Kelola shift kasir dan pantau transaksi harian</p>
                </div>
                <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#shiftModal">
                    <i class="bi bi-plus-lg me-2"></i>Tambah Shift
                </button>
            </div>

            <?php if (isset($_SESSION['success']) && !empty($_SESSION['success'])): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <i class="bi bi-check-circle me-2"></i><?php echo $_SESSION['success']; unset($_SESSION['success']); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
            <?php endif; ?>

            <?php if (isset($_SESSION['error']) && !empty($_SESSION['error'])): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <i class="bi bi-exclamation-triangle me-2"></i><?php echo $_SESSION['error']; unset($_SESSION['error']); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
            <?php endif; ?>

             <div class="row">
                <div class="col-12">
                        <div class="card-modern">
                            <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-3 px-4">
                                <div class="d-flex align-items-center">
                                    <i class="bi bi-table me-2"></i>
                                    <span>Daftar Shift Kasir</span>
                                </div>
                                <form method="GET" class="d-flex align-items-center gap-2 flex-wrap">
                                    <div class="input-group" style="width: 180px;">
                                        <span class="input-group-text bg-white border-end-0">
                                           
                                            <i class="bi bi-search"></i>
                                        </span>
                                        <input type="date" class="form-control border-start-0 ps-0" name="tanggal" value="<?php echo $selected_date; ?>">
                                    </div>
                                </form>
                            </div>
                           
                            <div class="table-responsive px-0">
                                <table class="table table-hover mb-0">
                                        <thead>
                                            <tr>
                                                <th style="width: 5%; text-align: start">No</th>
                                                <th style="width: auto; text-align: left">Kasir</th>
                                                <th style="width: 5%; text-align: center">Status</th>
                                                <th style="width: 15%; text-align: right">Tunai Awal</th>
                                                <th style="width: 10%; text-align: right">QRIS</th>
                                                <th style="width: 10%; text-align: right">Transfer</th>
                                                <th style="width: 15%; text-align: right">Grand Total</th>
                                                <th style="width: 10%; text-align: center">Aksi</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php if (empty($shift_data)): ?>
                                                <tr>
                                                    <td colspan="8" class="text-center ">
                                                        <i class="bi bi-inbox fs-1 d-block mb-2 text-muted"></i>
                                                        <p class="text-muted mb-0">Tidak ada data shift untuk tanggal <?php echo date('d F Y', strtotime($selected_date)); ?></p>
                                                    </td>
                                                </tr>
                                            <?php else: ?>
                                                <?php foreach ($shift_data as $index => $shift): ?>
                                                    <tr class="align-middle">
                                                        <td class="fw-semibold text-start"><?php echo $index + 1; ?></td>
                                                        <td>
                                                            <div class="fw-bold mb-1"><?php echo htmlspecialchars($shift['kasir'], ENT_QUOTES, 'UTF-8'); ?></div>
                                                            <small class="text-muted"><?php echo htmlspecialchars($shift['tanggal_open'], ENT_QUOTES, 'UTF-8'); ?></small>
                                                        </td>
                                                        <td class="text-center">
                                                            <?php if ($shift['status'] == '1'): ?>
                                                                <span class="badge bg-success">Open</span>
                                                            <?php else: ?>
                                                                <span class="badge bg-secondary">Closed</span>
                                                            <?php endif; ?>
                                                        </td>
                                                        <td class="text-end">
                                                            <span class="text-primary fw-medium">Rp <?php echo $shift['cash_awal']; ?></span>
                                                        </td>
                                                        <td class="text-end">
                                                            <span class="fw-medium">Rp <?php echo $shift['qris']; ?></span>
                                                        </td>
                                                        <td class="text-end">
                                                            <span class="fw-medium">Rp <?php echo $shift['transfer']; ?></span>
                                                        </td>
                                                        <td class="text-end">
                                                            <span class="text-success fw-bold">Rp <?php echo $shift['grand_total']; ?></span>
                                                        </td>
                                                        <td class="text-center">
                                                            <div class="table-actions d-inline-flex justify-content-center align-items-center">
                                                                <button type="button" class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#detailModal"
                                                                    data-shift-id="<?php echo (int)$shift['id_open']; ?>"
                                                                    data-shift-kasir="<?php echo htmlspecialchars($shift['kasir'], ENT_QUOTES, 'UTF-8'); ?>"
                                                                    data-shift-cash="<?php echo htmlspecialchars($shift['cash'], ENT_QUOTES, 'UTF-8'); ?>"
                                                                    data-shift-qris="<?php echo htmlspecialchars($shift['qris'], ENT_QUOTES, 'UTF-8'); ?>"
                                                                    data-shift-transfer="<?php echo htmlspecialchars($shift['transfer'], ENT_QUOTES, 'UTF-8'); ?>">
                                                                    <i class="bi bi-eye"></i>
                                                                </button>
                                                            </div>
                                                        </td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            <?php endif; ?>
                                        </tbody>
                                    </table>
                            </div>
                            
                            <?php if (!empty($shift_data)): ?>
                            <div class="card-footer bg-light border-top py-3 px-4">
                                <div class="d-flex flex-wrap justify-content-between align-items-center gap-3">
                                    <?php 
                                        $total_cash_awal = 0;
                                        $total_grand = 0;
                                        foreach ($shift_data as $shift) {
                                            $total_cash_awal += (int)str_replace(',', '', $shift['cash_awal']);
                                            $total_grand += (int)str_replace(',', '', $shift['grand_total']);
                                        }
                                    ?>
                                    <small class="text-muted">Menampilkan <?php echo count($shift_data); ?> shift pada tanggal <?php echo date('d F Y', strtotime($selected_date)); ?></small>
                                    <div class="d-flex align-items-center gap-4">
                                        <div>
                                            <small class="text-muted d-block">Total Tunai Awal</small>
                                            <strong class="text-primary">Rp <?php echo number_format($total_cash_awal, 0, ',', '.'); ?></strong>
                                        </div>
                                        <div>
                                            <small class="text-muted d-block">Total Grand Total</small>
                                            <strong class="text-success">Rp <?php echo number_format($total_grand, 0, ',', '.'); ?></strong>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <?php endif; ?>
                        </div>
                 </div>
             </div>
            
        </main>
      </div>
    </div>

    <!-- Detail Modal -->
    <div class="modal fade" id="detailModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="bi bi-info-circle me-2"></i>Detail Shift Kasir</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <!-- Kasir Info -->
                    <div class="mb-4 p-3 bg-light rounded">
                        <div class="d-flex align-items-center">
                            <i class="bi bi-person-circle text-primary fs-3 me-3"></i>
                            <div>
                                <small class="text-muted">Nama Kasir</small>
                                <div class="fw-bold" id="detail-kasir"></div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Payment Details -->
                    <div class="row g-3">
                        <div class="col-md-4">
                            <div class="text-center p-4 border rounded">
                                <i class="bi bi-cash-stack text-success fs-2 mb-2"></i>
                                <div class="text-muted small mb-2">Tunai</div>
                                <div class="fw-bold text-success" id="detail-tunai"></div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="text-center p-4 border rounded">
                                <i class="bi bi-qr-code text-info fs-2 mb-2"></i>
                                <div class="text-muted small mb-2">QRIS</div>
                                <div class="fw-bold text-info" id="detail-qris"></div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="text-center p-4 border rounded">
                                <i class="bi bi-credit-card text-warning fs-2 mb-2"></i>
                                <div class="text-muted small mb-2">Transfer</div>
                                <div class="fw-bold text-warning" id="detail-transfer"></div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                        <i class="bi bi-x-lg me-1"></i>Tutup
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Add Shift Modal -->
    <div class="modal fade" id="shiftModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="bi bi-plus-circle me-2"></i>Tambah Shift Kasir</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST" class="form-modern">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="create">
                        <div class="row mb-3">
                            <label class="col-sm-3 col-form-label">Kasir <span class="text-danger">*</span></label>
                            <div class="col-sm-9">
                                <select name="id_pegawai" class="form-select" required>
                                    <option value="">-- Pilih Kasir --</option>
                                    <?php foreach ($kasir_list as $kasir): ?>
                                    <option value="<?php echo $kasir['id_user']; ?>">
                                        <?php echo htmlspecialchars($kasir['nama_lengkap']); ?> (<?php echo htmlspecialchars($kasir['jabatan']); ?>)
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        <div class="row mb-3">
                            <label class="col-sm-3 col-form-label">Modal Awal (Rp) <span class="text-danger">*</span></label>
                            <div class="col-sm-9">
                                <input type="number" class="form-control" name="cash_awal" min="0" step="1000" required placeholder="0">
                            </div>
                        </div>
                        <div class="row mb-3">
                            <label class="col-sm-3 col-form-label">Tanggal <span class="text-danger">*</span></label>
                            <div class="col-sm-9">
                                <input type="date" class="form-control" name="tanggal" value="<?php echo date('Y-m-d'); ?>" required>
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
    </main>
    </div>

    <?php include '_scripts_new.php'; ?>
    
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const detailModal = document.getElementById('detailModal');
            if (detailModal) {
                detailModal.addEventListener('show.bs.modal', function (event) {
                    const button = event.relatedTarget;
                    if (!button) {
                        return;
                    }

                    const kasir = button.getAttribute('data-shift-kasir') || '';
                    const tunai = button.getAttribute('data-shift-cash') || '0';
                    const qris = button.getAttribute('data-shift-qris') || '0';
                    const transfer = button.getAttribute('data-shift-transfer') || '0';

                    document.getElementById('detail-kasir').textContent = kasir;
                    document.getElementById('detail-tunai').textContent = 'Rp ' + tunai;
                    document.getElementById('detail-qris').textContent = 'Rp ' + qris;
                    document.getElementById('detail-transfer').textContent = 'Rp ' + transfer;
                });
            }

            const dateInputs = document.querySelectorAll('input[name="tanggal"]');
            dateInputs.forEach(input => {
                input.addEventListener('change', function() {
                    if (this.form) {
                        this.form.submit();
                    }
                });
            });
            
            // Set date picker limits (yesterday to 2 days ahead)
            dateInputs.forEach(input => {
                const today = new Date();
                
                const yesterday = new Date(today);
                yesterday.setDate(today.getDate() - 1);
                
                const twoDaysAhead = new Date(today);
                twoDaysAhead.setDate(today.getDate() + 2);
                
                const formatDate = (date) => {
                    return date.getFullYear() + '-' + 
                           String(date.getMonth() + 1).padStart(2, '0') + '-' + 
                           String(date.getDate()).padStart(2, '0');
                };
                
                input.min = formatDate(yesterday);
                input.max = formatDate(twoDaysAhead);
            });
        });
    </script>
</body>
</html>