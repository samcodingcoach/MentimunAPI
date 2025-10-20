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

// Generate kode request PO-YYMMDD-001
function generateKodeRequest($conn) {
    $today = date('ymd'); // YYMMDD format
    $prefix = 'PO-' . $today . '-';
    
    // Get the last request number for today
    $sql = "SELECT kode_request FROM bahan_request WHERE kode_request LIKE '$prefix%' ORDER BY kode_request DESC LIMIT 1";
    $result = mysqli_query($conn, $sql);
    
    if ($result && mysqli_num_rows($result) > 0) {
        $row = mysqli_fetch_assoc($result);
        $lastCode = $row['kode_request'];
        // Extract the number part
        $lastNum = intval(substr($lastCode, -3));
        $newNum = $lastNum + 1;
    } else {
        $newNum = 1;
    }
    
    return $prefix . sprintf('%03d', $newNum);
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        // 1. Membuat Request diawal sehingga ada id_request
        if ($_POST['action'] === 'create_request') {
            $kode_request = $_POST['kode_request'];
            $id_user = isset($_SESSION['id_user']) ? $_SESSION['id_user'] : 1;
            
            $sql = "INSERT INTO bahan_request (kode_request, grand_total, id_user, status) VALUES (?, 0, ?, '0')";
            $stmt = mysqli_prepare($conn, $sql);
            mysqli_stmt_bind_param($stmt, "si", $kode_request, $id_user);
            
            if (mysqli_stmt_execute($stmt)) {
                $id_request = mysqli_insert_id($conn);
                echo json_encode(['success' => true, 'id_request' => $id_request]);
            } else {
                echo json_encode(['success' => false, 'message' => 'Gagal membuat request']);
            }
            exit();
        }
        
        // 2. Setiap tambah ke daftar = insert ke bahan_request_detail
        if ($_POST['action'] === 'add_item') {
            $id_request = intval($_POST['id_request']);
            $id_bahan = intval($_POST['id_bahan']);
            $id_vendor = intval($_POST['id_vendor']);
            $jumlah_request = intval($_POST['jumlah_request']);
            $harga_est = floatval($_POST['harga_est']);
            $subtotal = floatval($_POST['subtotal']);
            $isInvoice = $_POST['tipe_pembayaran'] === 'invoice' ? '1' : '0';
            $nomor_bukti = $_POST['nomor_bukti_transaksi'] ?? '';
            $id_bahan_biaya = intval($_POST['id_bahan_biaya']) ?? null;
            
            $sql = "INSERT INTO bahan_request_detail (id_request, id_bahan, id_vendor, jumlah_request, harga_est, subtotal, isDone, isInvoice, nomor_bukti_transaksi, stok_status, id_bahan_biaya, perubahan_biaya) VALUES (?, ?, ?, ?, ?, ?, '0', ?, ?, '0', ?, '0')";
            $stmt = mysqli_prepare($conn, $sql);
            mysqli_stmt_bind_param($stmt, "iiiiddssi", $id_request, $id_bahan, $id_vendor, $jumlah_request, $harga_est, $subtotal, $isInvoice, $nomor_bukti, $id_bahan_biaya);
            
            if (mysqli_stmt_execute($stmt)) {
                $detail_id = mysqli_insert_id($conn);
                echo json_encode(['success' => true, 'detail_id' => $detail_id]);
            } else {
                echo json_encode(['success' => false, 'message' => 'Gagal menambah item']);
            }
            exit();
        }
        
        // 3. Simpan transaksi = update status jadi 1
        if ($_POST['action'] === 'save_request') {
            $id_request = intval($_POST['id_request']);
            $grand_total = floatval($_POST['grand_total']);
            
            $sql = "UPDATE bahan_request SET grand_total = ?, status = '1' WHERE id_request = ?";
            $stmt = mysqli_prepare($conn, $sql);
            mysqli_stmt_bind_param($stmt, "di", $grand_total, $id_request);
            
            if (mysqli_stmt_execute($stmt)) {
                $message = "Transaksi pembelian berhasil disimpan!";
            } else {
                $error = "Gagal menyimpan transaksi.";
            }
        }
        
        // Generate new kode request
        if ($_POST['action'] === 'get_new_kode_request') {
            $newKodeRequest = generateKodeRequest($conn);
            echo json_encode(['success' => true, 'kode_request' => $newKodeRequest]);
            exit();
        }
        
        // Delete cancelled request
        if ($_POST['action'] === 'delete_request') {
            $id_request = intval($_POST['id_request']);
            
            // Delete detail items first
            $sql_delete_details = "DELETE FROM bahan_request_detail WHERE id_request = ?";
            $stmt_details = mysqli_prepare($conn, $sql_delete_details);
            mysqli_stmt_bind_param($stmt_details, "i", $id_request);
            mysqli_stmt_execute($stmt_details);
            
            // Then delete request
            $sql_delete_request = "DELETE FROM bahan_request WHERE id_request = ? AND status = '0'";
            $stmt_request = mysqli_prepare($conn, $sql_delete_request);
            mysqli_stmt_bind_param($stmt_request, "i", $id_request);
            
            if (mysqli_stmt_execute($stmt_request)) {
                echo json_encode(['success' => true]);
            } else {
                echo json_encode(['success' => false, 'message' => 'Gagal menghapus request']);
            }
            exit();
        }
        
        // Get filtered request data
        if ($_POST['action'] === 'get_requests') {
            $filter_tanggal = $_POST['tanggal'] ?? '';
            $filter_kode = $_POST['kode'] ?? '';

            $sql_request = "SELECT
                id_request,
                bahan_request.kode_request,
                bahan_request.tanggal_request,
                CONCAT(pegawai.jabatan,'-',pegawai.nama_lengkap) as nama_lengkap,
                grand_total
                FROM
                bahan_request
                INNER JOIN
                pegawai
                ON
                bahan_request.id_user = pegawai.id_user 
                WHERE status=1";
            
            $params = [];
            $types = '';

            if (!empty($filter_tanggal)) {
                $sql_request .= " AND DATE(tanggal_request) = ?";
                $params[] = $filter_tanggal;
                $types .= 's';
            }

            if (!empty($filter_kode)) {
                $sql_request .= " AND bahan_request.kode_request LIKE ?";
                $kode_param = "%" . $filter_kode . "%";
                $params[] = $kode_param;
                $types .= 's';
            }

            $sql_request .= " ORDER BY id_request DESC";
            
            $stmt = mysqli_prepare($conn, $sql_request);

            if (!empty($types)) {
                mysqli_stmt_bind_param($stmt, $types, ...$params);
            }
            
            mysqli_stmt_execute($stmt);
            $result_request = mysqli_stmt_get_result($stmt);
            
            $requests = [];
            $grand_total_sum = 0;
            if ($result_request && mysqli_num_rows($result_request) > 0) {
                while ($row = mysqli_fetch_assoc($result_request)) {
                    $requests[] = [
                        'id_request' => $row['id_request'],
                        'kode_request' => $row['kode_request'],
                        'tanggal_request' => date('d M Y', strtotime($row['tanggal_request'])),
                        'nama_lengkap' => $row['nama_lengkap'],
                        'grand_total' => 'Rp ' . number_format($row['grand_total'], 0, ',', '.')
                    ];
                    $grand_total_sum += $row['grand_total'];
                }
            }
            
            echo json_encode([
                'success' => true, 
                'data' => $requests,
                'grand_total_sum_formatted' => 'Rp ' . number_format($grand_total_sum, 0, ',', '.')
            ]);
            exit();
        }
        
        // Get request detail data
        if ($_POST['action'] === 'get_request_detail') {
            $id_request = intval($_POST['id_request']);
            
            $sql_detail = "SELECT
                bahan_request_detail.id_detail_request,
                bahan_request.kode_request,
                bahan_request.tanggal_request,
                CONCAT('[',kategori_bahan.nama_kategori,'] ', bahan.nama_bahan) as nama_bahan,
                bahan_request_detail.nomor_bukti_transaksi,
                bahan_request_detail.file_bukti,
                case
                when bahan_request_detail.isInvoice = '1' then 'INV'
                when bahan_request_detail.isInvoice = '0' then 'CASH'
                end as payment,
                CASE
                when bahan_request_detail.isDone = 0 then 'UNPAID'
                when bahan_request_detail.isDone = 1 then 'PAID'
                END as IsDone,
                vendor.nama_vendor,
                bahan_request_detail.jumlah_request,
                bahan_request_detail.harga_est as jumlah_harga,
                FORMAT(bahan_request_detail.subtotal,0) as subtotal
                FROM
                bahan_request_detail
                INNER JOIN
                bahan
                ON
                bahan_request_detail.id_bahan = bahan.id_bahan
                INNER JOIN
                vendor
                ON
                bahan_request_detail.id_vendor = vendor.id_vendor
                INNER JOIN
                kategori_bahan
                ON
                bahan.id_kategori = kategori_bahan.id_kategori
                INNER JOIN
                bahan_request
                ON
                bahan_request_detail.id_request = bahan_request.id_request
                WHERE
                bahan_request_detail.id_request = ?
                ORDER BY
                id_detail_request DESC";
                
            $stmt_detail = mysqli_prepare($conn, $sql_detail);
            mysqli_stmt_bind_param($stmt_detail, "i", $id_request);
            mysqli_stmt_execute($stmt_detail);
            $result_detail = mysqli_stmt_get_result($stmt_detail);
            
            $details = [];
            $request_info = null;
            
            if ($result_detail && mysqli_num_rows($result_detail) > 0) {
                while ($row = mysqli_fetch_assoc($result_detail)) {
                    if (!$request_info) {
                        $request_info = [
                            'kode_request' => $row['kode_request'],
                            'tanggal_request' => date('d M Y', strtotime($row['tanggal_request']))
                        ];
                    }
                    
                    $details[] = [
                        'id_detail_request' => $row['id_detail_request'],
                        'nama_bahan' => $row['nama_bahan'],
                        'nomor_bukti_transaksi' => $row['nomor_bukti_transaksi'] ?: '-',
                        'file_bukti' => $row['file_bukti'],
                        'payment' => $row['payment'],
                        'isDone' => $row['IsDone'],
                        'nama_vendor' => $row['nama_vendor'],
                        'jumlah_request' => $row['jumlah_request'],
                        'jumlah_harga' => number_format($row['jumlah_harga'], 0, ',', '.'),
                        'subtotal' => $row['subtotal']
                    ];
                }
            }
            
            echo json_encode(['success' => true, 'request_info' => $request_info, 'details' => $details]);
            exit();
        }
        
        // Hapus item dari detail
        if ($_POST['action'] === 'remove_item') {
            $detail_id = intval($_POST['detail_id']);
            
            $sql = "DELETE FROM bahan_request_detail WHERE id_detail_request = ?";
            $stmt = mysqli_prepare($conn, $sql);
            mysqli_stmt_bind_param($stmt, "i", $detail_id);
            
            if (mysqli_stmt_execute($stmt)) {
                echo json_encode(['success' => true]);
            } else {
                echo json_encode(['success' => false, 'message' => 'Gagal menghapus item']);
            }
            exit();
        }
    }
}

// Get data for dropdowns
$sql_bahan = "SELECT b.id_bahan, b.nama_bahan, b.kode_bahan, kb.nama_kategori 
              FROM bahan b 
              LEFT JOIN kategori_bahan kb ON b.id_kategori = kb.id_kategori 
              ORDER BY kb.nama_kategori, b.nama_bahan";
$result_bahan = mysqli_query($conn, $sql_bahan);
$bahan_data = [];
while ($row = mysqli_fetch_assoc($result_bahan)) {
    $bahan_data[] = $row;
}

$sql_vendor = "SELECT id_vendor, kode_vendor, nama_vendor, keterangan 
               FROM vendor 
               WHERE status = '1' 
               ORDER BY nama_vendor";
$result_vendor = mysqli_query($conn, $sql_vendor);
$vendor_data = [];
while ($row = mysqli_fetch_assoc($result_vendor)) {
    $vendor_data[] = $row;
}

// Kode request now generated only when modal opens
?>

<!doctype html>
<html lang="id" data-bs-theme="light">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Transaksi Pembelian - Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    <link href="https://cdn.jsdelivr.net/npm/select2-bootstrap-5-theme@1.3.0/dist/select2-bootstrap-5-theme.min.css" rel="stylesheet" />
    <link href="../css/newadmin.css?v=3" rel="stylesheet">
    <style>
        .card-header .btn-link {
            color: inherit;
        }
        .card-header .btn-link:hover {
            color: #0d6efd;
            text-decoration: none;
        }
        .table th, .table td {
            vertical-align: middle;
        }
        .table .text-center, .table .text-end, .table .text-start {
            text-align: center;
        }
    </style>
</head>
<body>
    <?php include '_header_new.php'; ?>
    <?php include '_sidebar_new.php'; ?>

    <main class="main-content" id="mainContent">
        <div class="container-fluid">
            <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-3">
                <div>
                    <h2 class="mb-1"><i class="bi bi-cart-plus me-2"></i>Transaksi Pembelian</h2>
                    <p class="text-muted mb-0">Kelola permintaan pembelian bahan dan pantau statusnya</p>
                </div>
                <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#modalAddRequest">
                    <i class="bi bi-plus-circle me-2"></i>Tambah Request
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
                <div class="card-header px-4 py-3">
                    <div class="d-flex align-items-center">
                        <i class="bi bi-table me-2"></i>
                        <span>Daftar Request Pembelian</span>
                    </div>
                </div>
                <div class="card-body">
                    <form class="row g-3 align-items-end" id="filterForm">
                        <div class="col-md-4">
                            <label for="filter_tanggal" class="form-label">Filter Tanggal</label>
                            <input type="date" class="form-control" id="filter_tanggal" value="<?php echo date('Y-m-d'); ?>">
                        </div>
                        <div class="col-md-4">
                            <label for="filter_kode" class="form-label">Cari Kode Request / No. PO</label>
                            <input type="text" class="form-control" id="filter_kode" placeholder="Masukkan kode request...">
                        </div>
                        <div class="col-md-4">
                            <div class="d-flex gap-2">
                                <button class="btn btn-primary flex-fill" type="button" id="btn_filter">
                                    <i class="bi bi-search me-2"></i>Filter
                                </button>
                                <button class="btn btn-secondary flex-fill" type="button" id="btn_reset_filter">
                                    <i class="bi bi-arrow-clockwise me-2"></i>Reset
                                </button>
                            </div>
                        </div>
                    </form>
                </div>
                <div class="table-responsive px-0">
                    <table class="table table-hover align-middle mb-0" id="table_requests">
                        <thead class="table-light">
                            <tr>
                                <th style="width:7%;">No</th>
                                <th>Kode Request</th>
                                <th style="width:18%;">Tanggal</th>
                                <th style="width:28%;">Pegawai</th>
                                <th style="width:18%;" class="text-end">Grand Total</th>
                                <th style="width:12%;" class="text-center">Aksi</th>
                            </tr>
                        </thead>
                        <tbody id="tbody_requests">
                            <!-- Data akan dimuat via AJAX -->
                        </tbody>
                    </table>
                </div>
                <div class="table-footer-info d-flex flex-wrap align-items-center justify-content-between gap-3 px-3 px-md-4 py-3 mt-3" id="table_summary">
                    <span class="text-muted mb-0" id="summary_rows">Total Item: 0</span>
                    <div class="d-flex flex-wrap align-items-center gap-2 justify-content-end">
                        <span class="fw-semibold mb-0">Grand Total:</span>
                        <span class="badge bg-primary-subtle text-primary px-3 py-2" id="summary_total">Rp 0</span>
                        <nav aria-label="Table pagination" class="mb-0 ms-2">
                            <ul class="pagination pagination-sm align-items-center mb-0" id="requests-pagination"></ul>
                        </nav>
                    </div>
                </div>
            </div>
        </div>
    </main>
    <!-- Modal Tambah Request -->
    <div class="modal fade" id="modalAddRequest" tabindex="-1" aria-labelledby="modalAddRequestLabel" aria-hidden="true">
      <div class="modal-dialog modal-xl">
        <div class="modal-content">
          <div class="modal-header">
            <h5 class="modal-title" id="modalAddRequestLabel">Tambah Request Pembelian Bahan</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
          </div>
          <div class="modal-body">
            <div class="card border-0 shadow-sm mb-4">
              <div class="card-header bg-light border-0 py-3">
                <div class="d-flex align-items-center">
                  <i class="bi bi-receipt-cutoff text-primary me-2"></i>
                  <span class="fw-semibold">Informasi Request</span>
                  <button class="btn btn-link text-decoration-none ms-auto p-0" type="button" data-bs-toggle="collapse" data-bs-target="#informasiRequestForm" aria-expanded="true" aria-controls="informasiRequestForm">
                    <i class="bi bi-chevron-down"></i>
                  </button>
                </div>
              </div>
              <div class="collapse show" id="informasiRequestForm">
                <div class="card-body">
                  <div class="row g-3">
                    <div class="col-md-6">
                      <label class="form-label text-uppercase small text-muted">Kode Request</label>
                      <div class="input-group">
                        <span class="input-group-text bg-primary text-white"><i class="bi bi-upc"></i></span>
                        <input type="text" class="form-control" id="modal_kode_request" value="" readonly>
                      </div>
                    </div>
                    <div class="col-md-6">
                      <label class="form-label text-uppercase small text-muted">Tanggal</label>
                      <div class="input-group">
                        <span class="input-group-text bg-secondary text-white"><i class="bi bi-calendar3"></i></span>
                        <input type="text" class="form-control" value="<?php echo date('d F Y'); ?>" readonly>
                      </div>
                    </div>
                  </div>
                </div>
              </div>
            </div>

            <div class="card border-0 shadow-sm mb-4">
              <div class="card-header bg-light border-0 py-3">
                <div class="d-flex align-items-center">
                  <i class="bi bi-basket text-primary me-2"></i>
                  <span class="fw-semibold">Detail Item Request</span>
                  <button class="btn btn-link text-decoration-none ms-auto p-0" type="button" data-bs-toggle="collapse" data-bs-target="#detailItemRequestForm" aria-expanded="true" aria-controls="detailItemRequestForm">
                    <i class="bi bi-chevron-down"></i>
                  </button>
                </div>
              </div>
              <div class="collapse show" id="detailItemRequestForm">
                <div class="card-body">
                  <div class="row g-3">
                    <div class="col-md-6">
                      <label class="form-label fw-semibold">Bahan <span class="text-danger">*</span></label>
                      <select class="form-select" id="modal_select_bahan" required>
                        <option value="">Pilih Bahan</option>
                        <?php foreach ($bahan_data as $row): ?>
                        <option value="<?php echo $row['id_bahan']; ?>" 
                                data-kode="<?php echo $row['kode_bahan']; ?>"
                                data-kategori="<?php echo $row['nama_kategori']; ?>">
                          <?php echo $row['kode_bahan'] . ' - ' . $row['nama_bahan'] . ' (' . $row['nama_kategori'] . ')'; ?>
                        </option>
                        <?php endforeach; ?>
                      </select>
                    </div>
                    <div class="col-md-6">
                      <label class="form-label fw-semibold">Vendor <span class="text-danger">*</span></label>
                      <select class="form-select" id="modal_select_vendor" required>
                        <option value="">Pilih Vendor</option>
                        <?php foreach ($vendor_data as $row): ?>
                        <option value="<?php echo $row['id_vendor']; ?>" 
                                data-kode="<?php echo $row['kode_vendor']; ?>"
                                data-keterangan="<?php echo $row['keterangan']; ?>">
                          <?php echo $row['kode_vendor'] . ' - ' . $row['nama_vendor'] . ' (' . $row['keterangan'] . ')'; ?>
                        </option>
                        <?php endforeach; ?>
                      </select>
                    </div>
                    <div class="col-md-4">
                      <label class="form-label fw-semibold">Satuan <span class="text-danger">*</span></label>
                      <select class="form-select" id="modal_select_satuan" required disabled>
                        <option value="">Pilih Satuan</option>
                      </select>
                    </div>
                    <div class="col-md-4">
                      <label class="form-label fw-semibold">Harga Estimasi</label>
                      <div class="input-group">
                        <span class="input-group-text">Rp</span>
                        <input type="number" class="form-control" id="modal_harga_est" step="0.01" readonly>
                      </div>
                    </div>
                    <div class="col-md-4">
                      <label class="form-label fw-semibold">Jumlah <span class="text-danger">*</span></label>
                      <div class="input-group">
                        <span class="input-group-text"><i class="bi bi-hash"></i></span>
                        <input type="number" class="form-control" id="modal_jumlah_request" min="1" placeholder="0" required>
                      </div>
                    </div>
                    <div class="col-md-4">
                      <label class="form-label fw-semibold">Subtotal</label>
                      <div class="input-group">
                        <span class="input-group-text">Rp</span>
                        <input type="text" class="form-control" id="modal_subtotal_display" readonly>
                      </div>
                    </div>
                    <div class="col-md-4">
                      <label class="form-label fw-semibold">Tipe Pembayaran <span class="text-danger">*</span></label>
                      <select class="form-select" id="modal_tipe_pembayaran">
                        <option value="invoice">Invoice</option>
                        <option value="bayar_langsung">Bayar Langsung</option>
                      </select>
                    </div>
                    <div class="col-md-4">
                      <label class="form-label fw-semibold">Nomor Bukti Transaksi</label>
                      <input type="text" class="form-control" id="modal_nomor_bukti" placeholder="Opsional">
                      <small class="text-muted">Isi setelah transaksi jika diperlukan</small>
                    </div>
                    <div class="col-12 d-flex justify-content-end">
                      <button type="button" class="btn btn-primary btn-lg px-4 mt-2" id="modal_btn_add_item">
                        <i class="bi bi-plus-circle me-2"></i>Tambah ke Daftar
                      </button>
                    </div>
                  </div>
                </div>
              </div>
            </div>

            <!-- Tabel Detail Modal -->
            <div class="card border-0 shadow-sm mb-4">
              <div class="card-header bg-light border-0 py-3">
                <div class="d-flex align-items-center">
                  <i class="bi bi-list-check text-primary me-2"></i>
                  <span class="fw-semibold">Daftar Item Request</span>
                  <button class="btn btn-link text-decoration-none ms-auto p-0" type="button" data-bs-toggle="collapse" data-bs-target="#detailItemTable" aria-expanded="true" aria-controls="detailItemTable">
                    <i class="bi bi-chevron-down"></i>
                  </button>
                </div>
              </div>
              <div class="collapse show" id="detailItemTable">
                <div class="card-body p-0">
                  <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0" id="modal_table_detail">
                      <thead class="table-light border-bottom">
                        <tr class="text-uppercase small">
                          <th style="width:5%;" class="ps-4">No</th>
                          <th style="width:20%;">Bahan</th>
                          <th style="width:20%;">Vendor</th>
                          <th style="width:10%;" class="text-center">Satuan</th>
                          <th style="width:10%;" class="text-end">Harga Est.</th>
                          <th style="width:8%;" class="text-center">Qty</th>
                          <th style="width:12%;" class="text-end">Subtotal</th>
                          <th style="width:10%;" class="text-center">Status</th>
                          <th style="width:5%;" class="text-center pe-4">Aksi</th>
                        </tr>
                      </thead>
                      <tbody>
                        <tr>
                          <td colspan="9" class="text-center text-muted py-4">
                            Belum ada item yang ditambahkan
                          </td>
                        </tr>
                      </tbody>
                    </table>
                  </div>
                </div>
              </div>
            </div>

            <!-- Grand Total Modal -->
            <div class="row">
              <div class="col-md-8">
                <!-- Empty space -->
              </div>
              <div class="col-md-4">
                <div class="card bg-light">
                  <div class="card-body">
                    <div class="d-flex justify-content-between">
                      <strong>Grand Total:</strong>
                      <strong class="text-primary" id="modal_grand_total_display">Rp 0</strong>
                    </div>
                  </div>
                </div>
                
                <!-- Hidden fields -->
                <input type="hidden" id="modal_subtotal" value="0">
              </div>
            </div>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
              <i class="bi bi-x-circle"></i> Tutup
            </button>
            <button type="button" class="btn btn-success" id="modal_btn_save" disabled>
              <i class="bi bi-save"></i> Simpan Transaksi
            </button>
          </div>
        </div>
      </div>
    </div>

    <!-- Modal Detail Request -->
    <div class="modal fade" id="modalDetailRequest" tabindex="-1" aria-labelledby="modalDetailRequestLabel" aria-hidden="true">
      <div class="modal-dialog modal-xl">
        <div class="modal-content">
          <div class="modal-header">
            <h5 class="modal-title" id="modalDetailRequestLabel">Detail Request Pembelian</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
          </div>
          <div class="modal-body">
            <!-- Request Info -->
            <div class="row mb-4">
              <div class="col-md-6">
                <div class="mb-3">
                  <label class="form-label"><strong>Kode Request:</strong></label>
                  <input type="text" class="form-control" id="detail_kode_request" readonly>
                </div>
              </div>
              <div class="col-md-6">
                <div class="mb-3">
                  <label class="form-label"><strong>Tanggal Request:</strong></label>
                  <input type="text" class="form-control" id="detail_tanggal_request" readonly>
                </div>
              </div>
            </div>
            
            <div class="card border-0 shadow-sm">
              <div class="card-header bg-light border-0 py-3">
                <div class="d-flex align-items-center">
                  <i class="bi bi-list-check me-2 text-primary"></i>
                  <span class="fw-semibold">Daftar Item Request</span>
                </div>
              </div>
              <div class="card-body p-0">
                <div class="table-responsive">
                  <table class="table table-hover align-middle mb-0" id="table_detail_items">
                    <thead class="table-light border-bottom">
                      <tr class="text-uppercase small text-muted">
                        <th style="width:6%;" class="ps-4">No</th>
                        <th style="width:28%;">Bahan</th>
                        <th style="width:18%;">Vendor</th>
                        <th style="width:8%;" class="text-center">Qty</th>
                        <th style="width:12%;" class="text-end">Harga</th>
                        <th style="width:12%;" class="text-end">Subtotal</th>
                        <th style="width:8%;" class="text-center">Payment</th>
                        <th style="width:8%;" class="text-center">Status</th>
                        <th style="width:10%;" class="text-center pe-4">No. Bukti</th>
                      </tr>
                    </thead>
                    <tbody id="tbody_detail_items">
                      <tr>
                        <td colspan="9" class="text-center text-muted py-4">
                          Tidak ada item pada request ini
                        </td>
                      </tr>
                    </tbody>
                  </table>
                </div>
              </div>
            </div>
          </div>
          
        </div>
      </div>
    </div>

    <!-- Modal Bukti Pembayaran -->
    <div class="modal fade" id="modalBuktiPembayaran" tabindex="-1" aria-labelledby="modalBuktiPembayaranLabel" aria-hidden="true">
      <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
          <div class="modal-body position-relative">
            <button type="button" class="btn-close-overlay" data-bs-dismiss="modal" aria-label="Close">
              <i class="bi bi-x-circle-fill"></i>
            </button>
            <img src="" id="bukti_pembayaran_image" class="img-fluid rounded" alt="Bukti Pembayaran">
          </div>
        </div>
      </div>
    </div>

    <?php include '_scripts_new.php'; ?>
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
    
    <script>
    $(document).ready(function() {
        const PAGE_SIZE = 10;
        let currentPage = 1;
        let totalPages = 1;
        let currentRequests = [];
        let currentGrandTotalFormatted = 'Rp 0';

        let modalDetailItems = [];
        let modalGrandTotal = 0;
        let modalCurrentRequestId = null;
        
        // Format rupiah function to remove .00 for whole numbers
        function formatRupiah(amount) {
            const num = parseFloat(amount);
            if (num % 1 === 0) {
                // Whole number, format without decimals
                return num.toLocaleString('id-ID');
            } else {
                // Has decimals, format with 2 decimal places
                return num.toLocaleString('id-ID', {minimumFractionDigits: 2, maximumFractionDigits: 2});
            }
        }
        
        // Toggle chevron icons for collapsible elements
        $('.collapse').on('show.bs.collapse', function () {
            const icon = $(this).closest('.card').find('.bi-chevron-down');
            icon.removeClass('bi-chevron-down').addClass('bi-chevron-up');
        });
        
        $('.collapse').on('hide.bs.collapse', function () {
            const icon = $(this).closest('.card').find('.bi-chevron-up');
            icon.removeClass('bi-chevron-up').addClass('bi-chevron-down');
        });
        
        // === REQUEST LIST FUNCTIONALITY ===
        
        // Load requests data
        function loadRequests(tanggal = '', kode = '') {
            $.ajax({
                url: window.location.href,
                type: 'POST',
                data: {
                    action: 'get_requests',
                    tanggal: tanggal,
                    kode: kode
                },
                dataType: 'json',
                success: function(response) {
                    if (response.success) {
                        currentRequests = response.data || [];
                        currentGrandTotalFormatted = response.grand_total_sum_formatted || 'Rp 0';
                        totalPages = Math.max(1, Math.ceil(currentRequests.length / PAGE_SIZE));
                        if (currentPage > totalPages) {
                            currentPage = totalPages;
                        }
                        renderRequestsTablePage();
                    } else {
                        $('#tbody_requests').html('<tr><td colspan="6" class="text-center text-muted">Tidak ada data request pembelian</td></tr>');
                        updateSummary(0, 0, 0, 'Rp 0');
                        renderPagination(0);
                    }
                },
                error: function() {
                    $('#tbody_requests').html('<tr><td colspan="6" class="text-center text-muted">Error loading data</td></tr>');
                    updateSummary(0, 0, 0, 'Rp 0');
                    renderPagination(0);
                }
            });
        }

        function renderRequestsTablePage() {
            const tbody = $('#tbody_requests');
            tbody.empty();

            const totalRecords = currentRequests.length;
            if (totalRecords === 0) {
                tbody.append('<tr><td colspan="6" class="text-center text-muted">Tidak ada data request pembelian</td></tr>');
                updateSummary(0, 0, 0, currentGrandTotalFormatted);
                renderPagination(0);
                return;
            }

            const startIndex = (currentPage - 1) * PAGE_SIZE;
            const paginatedData = currentRequests.slice(startIndex, startIndex + PAGE_SIZE);

            paginatedData.forEach(function(request, index) {
                tbody.append(`
                    <tr>
                        <td class="fw-semibold">${startIndex + index + 1}</td>
                        <td>${request.kode_request}</td>
                        <td>${request.tanggal_request}</td>
                        <td>${request.nama_lengkap}</td>
                        <td class="text-end">${request.grand_total}</td>
                        <td class="text-center">
                            <button type="button" class="btn btn-sm btn-outline-primary" onclick="showDetailModal(${request.id_request})">
                                <i class="bi bi-eye"></i>
                            </button>
                        </td>
                    </tr>
                `);
            });

            const endIndex = startIndex + paginatedData.length;
            updateSummary(startIndex + 1, endIndex, totalRecords, currentGrandTotalFormatted);

            renderPagination(totalRecords);
        }

        function updateSummary(start, end, totalRecords, totalFormatted) {
            $('#summary_rows').text(`Total Item: ${totalRecords}`);
            $('#summary_total').text(totalFormatted);
        }

        function renderPagination(totalRecords) {
            const pagination = $('#requests-pagination');
            pagination.empty();

            if (totalRecords <= PAGE_SIZE) {
                return;
            }

            totalPages = Math.max(1, Math.ceil(totalRecords / PAGE_SIZE));

            const createPageItem = (page, label, disabled = false, active = false) => {
                const liClass = `page-item ${disabled ? 'disabled' : ''} ${active ? 'active' : ''}`;
                return `
                    <li class="${liClass}">
                        <button type="button" class="page-link" data-page="${page}">${label}</button>
                    </li>
                `;
            };

            pagination.append(createPageItem(currentPage - 1, 'Previous', currentPage === 1));

            const maxVisible = 5;
            let startPage = Math.max(1, currentPage - Math.floor(maxVisible / 2));
            let endPage = Math.min(totalPages, startPage + maxVisible - 1);
            if (endPage - startPage + 1 < maxVisible) {
                startPage = Math.max(1, endPage - maxVisible + 1);
            }

            if (startPage > 1) {
                pagination.append(createPageItem(1, '1', false, currentPage === 1));
                if (startPage > 2) {
                    pagination.append('<li class="page-item disabled"><span class="page-link">...</span></li>');
                }
            }

            for (let page = startPage; page <= endPage; page++) {
                pagination.append(createPageItem(page, page, false, currentPage === page));
            }

            if (endPage < totalPages) {
                if (endPage < totalPages - 1) {
                    pagination.append('<li class="page-item disabled"><span class="page-link">...</span></li>');
                }
                pagination.append(createPageItem(totalPages, totalPages, false, currentPage === totalPages));
            }

            pagination.append(createPageItem(currentPage + 1, 'Next', currentPage === totalPages));
        }

        $('#requests-pagination').on('click', '.page-link', function() {
            const targetPage = Number($(this).data('page'));
            if (!targetPage || targetPage === currentPage || targetPage < 1 || targetPage > totalPages) {
                return;
            }
            currentPage = targetPage;
            renderRequestsTablePage();
        });
        
        // Filter button click
        $('#btn_filter').on('click', function() {
            const tanggal = $('#filter_tanggal').val();
            const kode = $('#filter_kode').val();
            currentPage = 1;
            loadRequests(tanggal, kode);
        });
        
        // Reset filter button click
        $('#btn_reset_filter').on('click', function() {
            $('#filter_tanggal').val('');
            $('#filter_kode').val('');
            currentPage = 1;
            loadRequests('', '');
        });
        
        // Filter on enter key
        $('#filter_tanggal, #filter_kode').on('keypress', function(e) {
            if (e.which === 13) {
                const tanggal = $('#filter_tanggal').val();
                const kode = $('#filter_kode').val();
                currentPage = 1;
                loadRequests(tanggal, kode);
            }
        });
        
        loadRequests($('#filter_tanggal').val(), $('#filter_kode').val());
        
        // === DETAIL MODAL FUNCTIONALITY ===
        
        // Show detail modal function (global)
        window.showDetailModal = function(idRequest) {
            $.ajax({
                url: window.location.href,
                type: 'POST',
                data: {
                    action: 'get_request_detail',
                    id_request: idRequest
                },
                dataType: 'json',
                success: function(response) {
                    if (response.success && response.request_info && response.details.length > 0) {
                        // Fill request info
                        $('#detail_kode_request').val(response.request_info.kode_request);
                        $('#detail_tanggal_request').val(response.request_info.tanggal_request);
                        
                        // Render detail table
                        renderDetailTable(response.details);
                        
                        // Show modal
                        $('#modalDetailRequest').modal('show');
                    } else {
                        alert('Data detail tidak ditemukan atau request kosong.');
                    }
                },
                error: function() {
                    alert('Error loading detail data.');
                }
            });
        };
        
        // Render detail table
        function renderDetailTable(details) {
            const tbody = $('#tbody_detail_items');
            tbody.empty();
            
            if (details.length === 0) {
                tbody.append('<tr><td colspan="9" class="text-center text-muted">Tidak ada detail item</td></tr>');
                return;
            }
            
            details.forEach(function(detail, index) {
                // Status badge class
                let statusClass = detail.isDone === 'PAID' ? 'bg-success' : 'bg-warning';
                let paymentClass = detail.payment === 'INV' ? 'bg-info' : 'bg-primary';
                
                let nomorBuktiHtml = `<small>${detail.nomor_bukti_transaksi}</small>`;
                if (detail.isDone === 'PAID' && detail.file_bukti) {
                    nomorBuktiHtml = `
                        <a href="#" class="text-primary" onclick="showBuktiModal('${detail.file_bukti}')">
                            <small>${detail.nomor_bukti_transaksi}</small>
                        </a>`;
                }

                tbody.append(`
                    <tr id="detail_row_${detail.id_detail_request}">
                        <td>${index + 1}</td>
                        <td><small>${detail.nama_bahan}</small></td>
                        <td><small>${detail.nama_vendor}</small></td>
                        <td class="text-center">${detail.jumlah_request}</td>
                        <td class="text-end">Rp ${detail.jumlah_harga}</td>
                        <td class="text-end">Rp ${detail.subtotal}</td>
                        <td class="text-center">
                            <span class="badge ${paymentClass}">${detail.payment}</span>
                        </td>
                        <td class="text-center">
                            <span class="badge ${statusClass}">${detail.isDone}</span>
                        </td>
                        <td class="text-center">
                            ${nomorBuktiHtml}
                        </td>
                    </tr>
                `);
            });
        }

        // Show bukti pembayaran modal
        window.showBuktiModal = function(fileBukti) {
            const imageUrl = `../struk/images/${fileBukti}`;
            $('#bukti_pembayaran_image').attr('src', imageUrl);
            $('#modalBuktiPembayaran').modal('show');
        };
        
        // === MODAL FUNCTIONALITY ===
        
        // Initialize modal Select2 when modal opens
        $('#modalAddRequest').on('shown.bs.modal', function() {
            // Initialize Select2 for modal elements
            $('#modal_select_bahan, #modal_select_vendor').select2({
                theme: 'bootstrap-5',
                width: '100%',
                dropdownParent: $('#modalAddRequest')
            });
            
            // Generate new request code for modal
            $.ajax({
                url: window.location.href,
                type: 'POST', 
                data: {
                    action: 'get_new_kode_request'
                },
                dataType: 'json',
                success: function(response) {
                    if (response.success) {
                        $('#modal_kode_request').val(response.kode_request);
                    }
                }
            });
            
            // Create new request for modal
            createModalRequest();
        });
        
        // Clear modal on close
        $('#modalAddRequest').on('hidden.bs.modal', function() {
            // Delete incomplete request if it exists
            if (modalCurrentRequestId && modalDetailItems.length === 0) {
                $.ajax({
                    url: window.location.href,
                    type: 'POST',
                    data: {
                        action: 'delete_request',
                        id_request: modalCurrentRequestId
                    },
                    dataType: 'json',
                    success: function(response) {
                        console.log('Empty request deleted:', modalCurrentRequestId);
                    },
                    error: function() {
                        console.log('Failed to delete empty request');
                    }
                });
            }
            
            clearModalForm();
            modalDetailItems = [];
            modalCurrentRequestId = null;
            renderModalTable();
            updateModalGrandTotal();
        });
        
        function createModalRequest() {
            $.ajax({
                url: window.location.href,
                type: 'POST',
                data: {
                    action: 'create_request',
                    kode_request: $('#modal_kode_request').val()
                },
                dataType: 'json',
                success: function(response) {
                    if (response.success) {
                        modalCurrentRequestId = response.id_request;
                        console.log('Modal Request created with ID:', modalCurrentRequestId);
                    } else {
                        alert('Gagal membuat request: ' + response.message);
                    }
                },
                error: function() {
                    alert('Error membuat request');
                }
            });
        }
        
        // Modal bahan change handler
        $('#modal_select_bahan').on('change', function() {
            const idBahan = $(this).val();
            $('#modal_select_satuan').prop('disabled', true).html('<option value="">Loading...</option>');
            $('#modal_harga_est').val('');
            
            if (idBahan) {
                $.ajax({
                    url: 'ajax/get_satuan_bahan.php',
                    type: 'POST',
                    data: {id_bahan: idBahan},
                    dataType: 'json',
                    success: function(response) {
                        $('#modal_select_satuan').prop('disabled', false).html('<option value="">Pilih Satuan</option>');
                        if (response.success && response.data.length > 0) {
                            $.each(response.data, function(index, item) {
                                $('#modal_select_satuan').append(`<option value="${item.satuan}">${item.satuan}</option>`);
                            });
                            initializeModalSatuanSelect2();
                        }
                    },
                    error: function() {
                        $('#modal_select_satuan').prop('disabled', false).html('<option value="">Error loading data</option>');
                    }
                });
            } else {
                $('#modal_select_satuan').prop('disabled', true).html('<option value="">Pilih Satuan</option>');
            }
        });
        
        function initializeModalSatuanSelect2() {
            if (!$('#modal_select_satuan').hasClass('select2-hidden-accessible')) {
                $('#modal_select_satuan').select2({
                    theme: 'bootstrap-5',
                    width: '100%',
                    dropdownParent: $('#modalAddRequest')
                });
            }
        }
        
        // Modal satuan change handler
        $('#modal_select_satuan').on('change', function() {
            const idBahan = $('#modal_select_bahan').val();
            const satuan = $(this).val();
            
            if (idBahan && satuan) {
                $.ajax({
                    url: 'ajax/get_harga_bahan.php',
                    type: 'POST', 
                    data: {id_bahan: idBahan, satuan: satuan},
                    dataType: 'json',
                    success: function(response) {
                        if (response.success) {
                            $('#modal_harga_est').val(response.harga_satuan);
                            $('#modal_harga_est').data('id_bahan_biaya', response.id_bahan_biaya);
                            calculateModalSubtotal();
                        } else {
                            $('#modal_harga_est').val(0);
                            $('#modal_harga_est').data('id_bahan_biaya', null);
                        }
                    },
                    error: function() {
                        $('#modal_harga_est').val(0);
                    }
                });
            }
        });
        
        // Modal quantity change handler
        $('#modal_jumlah_request').on('input', function() {
            calculateModalSubtotal();
        });
        
        function calculateModalSubtotal() {
            const harga = parseFloat($('#modal_harga_est').val()) || 0;
            const qty = parseInt($('#modal_jumlah_request').val()) || 0;
            const subtotal = harga * qty;
            $('#modal_subtotal').val(subtotal.toFixed(2));
            $('#modal_subtotal_display').val(formatRupiah(subtotal));
        }
        
        // Modal add item handler
        $('#modal_btn_add_item').on('click', function() {
            const idBahan = $('#modal_select_bahan').val();
            const idVendor = $('#modal_select_vendor').val();
            const satuan = $('#modal_select_satuan').val();
            const hargaEst = parseFloat($('#modal_harga_est').val()) || 0;
            const jumlah = parseInt($('#modal_jumlah_request').val()) || 0;
            const subtotal = parseFloat($('#modal_subtotal').val()) || 0;
            const tipePembayaran = $('#modal_tipe_pembayaran').val();
            const nomorBukti = $('#modal_nomor_bukti').val();
            const idBahanBiaya = $('#modal_harga_est').data('id_bahan_biaya');
            
            // Validation
            if (!idBahan || !idVendor || !satuan || jumlah <= 0) {
                alert('Mohon lengkapi semua field yang wajib diisi!');
                return;
            }
            
            if (!modalCurrentRequestId) {
                alert('Request belum dibuat. Silakan tutup dan buka modal kembali.');
                return;
            }
            
            // Insert item ke database
            $.ajax({
                url: window.location.href,
                type: 'POST',
                data: {
                    action: 'add_item',
                    id_request: modalCurrentRequestId,
                    id_bahan: idBahan,
                    id_vendor: idVendor,
                    jumlah_request: jumlah,
                    harga_est: hargaEst,
                    subtotal: subtotal,
                    tipe_pembayaran: tipePembayaran,
                    nomor_bukti_transaksi: nomorBukti,
                    id_bahan_biaya: idBahanBiaya
                },
                dataType: 'json',
                success: function(response) {
                    if (response.success) {
                        // Get display text
                        const namaBahan = $('#modal_select_bahan option:selected').text();
                        const namaVendor = $('#modal_select_vendor option:selected').text();
                        const isDone = tipePembayaran === 'bayar_langsung' ? '1' : '0';
                        const statusText = tipePembayaran === 'bayar_langsung' ? 'Bayar Langsung' : 'Invoice';
                        
                        // Add to array untuk display
                        const item = {
                            detail_id: response.detail_id,
                            id_bahan: idBahan,
                            id_vendor: idVendor,
                            satuan: satuan,
                            harga_est: hargaEst,
                            jumlah_request: jumlah,
                            subtotal: subtotal,
                            isDone: isDone,
                            isInvoice: '1',
                            nomor_bukti_transaksi: nomorBukti,
                            nama_bahan: namaBahan,
                            nama_vendor: namaVendor,
                            status_text: statusText
                        };
                        
                        modalDetailItems.push(item);
                        renderModalTable();
                        clearModalForm();
                        updateModalGrandTotal();
                    } else {
                        alert('Gagal menambah item: ' + response.message);
                    }
                },
                error: function() {
                    alert('Error menambah item');
                }
            });
        });
        
        function renderModalTable() {
            const tbody = $('#modal_table_detail tbody');
            tbody.empty();
            
            if (modalDetailItems.length === 0) {
                tbody.append(`
                    <tr>
                        <td colspan="9" class="text-center text-muted py-4">
                            Belum ada item yang ditambahkan
                        </td>
                    </tr>
                `);
                return;
            }
            
            $.each(modalDetailItems, function(index, item) {
                tbody.append(`
                    <tr>
                        <td>${index + 1}</td>
                        <td>${item.nama_bahan}</td>
                        <td>${item.nama_vendor}</td>
                        <td>${item.satuan}</td>
                        <td>Rp ${formatRupiah(parseFloat(item.harga_est))}</td>
                        <td>${item.jumlah_request}</td>
                        <td>Rp ${formatRupiah(parseFloat(item.subtotal))}</td>
                        <td><span class="badge ${item.isDone === '1' ? 'bg-success' : 'bg-warning'}">${item.status_text}</span></td>
                        <td>
                            <button type="button" class="btn btn-sm btn-danger" onclick="removeModalItem(${index})">
                                <i class="bi bi-trash"></i>
                            </button>
                        </td>
                    </tr>
                `);
            });
        }
        
        function clearModalForm() {
            $('#modal_select_bahan').val('').trigger('change');
            $('#modal_select_vendor').val('').trigger('change');
            $('#modal_select_satuan').prop('disabled', true).html('<option value="">Pilih Satuan</option>');
            $('#modal_harga_est').val('');
            $('#modal_jumlah_request').val('');
            $('#modal_subtotal').val('');
            $('#modal_subtotal_display').val('');
            $('#modal_nomor_bukti').val('');
        }
        
        function updateModalGrandTotal() {
            modalGrandTotal = modalDetailItems.reduce((sum, item) => sum + parseFloat(item.subtotal), 0);
            $('#modal_grand_total_display').text('Rp ' + formatRupiah(modalGrandTotal));
            $('#modal_btn_save').prop('disabled', modalDetailItems.length === 0);
        }
        
        // Remove modal item function (global)
        window.removeModalItem = function(index) {
            if (confirm('Yakin ingin menghapus item ini?')) {
                const item = modalDetailItems[index];
                
                $.ajax({
                    url: window.location.href,
                    type: 'POST',
                    data: {
                        action: 'remove_item',
                        detail_id: item.detail_id
                    },
                    dataType: 'json',
                    success: function(response) {
                        if (response.success) {
                            modalDetailItems.splice(index, 1);
                            renderModalTable();
                            updateModalGrandTotal();
                        } else {
                            alert('Gagal menghapus item: ' + response.message);
                        }
                    },
                    error: function() {
                        alert('Error menghapus item');
                    }
                });
            }
        };
        
        // Modal save transaction handler
        $('#modal_btn_save').on('click', function() {
            if (modalDetailItems.length === 0) {
                alert('Tidak ada item untuk disimpan!');
                return;
            }
            
            if (!modalCurrentRequestId) {
                alert('Request belum dibuat.');
                return;
            }
            
            if (confirm('Simpan transaksi pembelian ini?')) {
                $.ajax({
                    url: window.location.href,
                    type: 'POST',
                    data: {
                        action: 'save_request',
                        id_request: modalCurrentRequestId,
                        grand_total: modalGrandTotal
                    },
                    success: function(response) {
                        $('#modalAddRequest').modal('hide');
                        // Refresh table instead of whole page
                        loadRequests($('#filter_tanggal').val());
                        alert('Transaksi pembelian berhasil disimpan!');
                    },
                    error: function() {
                        alert('Error menyimpan transaksi');
                    }
                });
            }
        });
    });
    </script>
  </body>
</html>