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

// Get selected date
$selected_date = isset($_GET['tanggal']) ? $_GET['tanggal'] : date('Y-m-d');

// Get date range for Per Kasir tab
$tanggal_awal = isset($_GET['tanggal_awal']) ? $_GET['tanggal_awal'] : date('Y-m-d');
$tanggal_akhir = isset($_GET['tanggal_akhir']) ? $_GET['tanggal_akhir'] : date('Y-m-d');
$selected_kasir = isset($_GET['kasir']) ? $_GET['kasir'] : '';

// Get parameters for Semua tab
$semua_tanggal1 = isset($_GET['tanggal1']) ? $_GET['tanggal1'] : date('Y-m-d');
$semua_tanggal2 = isset($_GET['tanggal2']) ? $_GET['tanggal2'] : date('Y-m-d');
$semua_kategori = isset($_GET['kategori']) ? $_GET['kategori'] : 'Semua';
$semua_page = isset($_GET['semua_page']) ? (int)$_GET['semua_page'] : 1;
$semua_limit = 15;
$semua_offset = ($semua_page - 1) * $semua_limit;

// Get active tab
$active_tab = isset($_GET['tab']) ? $_GET['tab'] : 'tunai';

// Query for Tunai (Cash) transactions
$tunai_sql = "
    SELECT
        proses_pembayaran.kode_payment,
        DATE_FORMAT(proses_pembayaran.tanggal_payment,'%H:%i') AS jam,
        DATE_FORMAT(proses_pembayaran.update_status,'%d %M %Y %H:%i') AS waktu_dibayar,
        pegawai.nama_lengkap as kasir,
        FORMAT(proses_pembayaran.jumlah_uang, 0) AS nominal,
        CASE
            WHEN proses_pembayaran.`status` = 1 THEN 'DIBAYAR'
            WHEN proses_pembayaran.`status` = 0 THEN 'BELUM DIBAYAR'
        END AS status_bayar
    FROM
        proses_pembayaran
    INNER JOIN pegawai ON proses_pembayaran.id_user = pegawai.id_user
    INNER JOIN metode_pembayaran ON proses_pembayaran.id_bayar = metode_pembayaran.id_bayar
    WHERE
        metode_pembayaran.kategori = 'Tunai' AND
        DATE(tanggal_payment) = ?
    ORDER BY proses_pembayaran.tanggal_payment DESC
";

$tunai_stmt = $conn->prepare($tunai_sql);
$tunai_stmt->bind_param("s", $selected_date);
$tunai_stmt->execute();
$tunai_result = $tunai_stmt->get_result();
$tunai_data = $tunai_result->fetch_all(MYSQLI_ASSOC);

// Calculate total for Tunai
$total_tunai = 0;
foreach ($tunai_data as $row) {
    $amount = str_replace(',', '', $row['nominal']);
    $total_tunai += (int)$amount;
}

// Query for Transfer transactions
$transfer_sql = "
    SELECT
        proses_edc.id_tx_bank,
        proses_edc.kode_payment,
        CASE
            WHEN proses_edc.transfer_or_edc = 0 THEN CONCAT('TRANSFER - ', nama_bank, ' A.N ', COALESCE(nama_pengirim, 'NONE'))
            WHEN proses_edc.transfer_or_edc = 1 THEN CONCAT('EDC - ', nama_bank, ' A.N ', COALESCE(nama_pengirim, 'NONE'))
        END AS bank,
        nominal_transfer,
        proses_edc.tanggal_transfer,
        proses_edc.no_referensi,
        proses_edc.img_ss,
        proses_edc.status_pemeriksaan,
        tgl_pemeriksaan
    FROM
        proses_edc
    WHERE
        DATE(tanggal_transfer) = ?
    ORDER BY proses_edc.tanggal_transfer DESC
";

$transfer_stmt = $conn->prepare($transfer_sql);
$transfer_stmt->bind_param("s", $selected_date);
$transfer_stmt->execute();
$transfer_result = $transfer_stmt->get_result();
$transfer_data = $transfer_result->fetch_all(MYSQLI_ASSOC);

// Calculate total for Transfer
$total_transfer = 0;
foreach ($transfer_data as $row) {
    $total_transfer += $row['nominal_transfer'];
}

// Query for QRIS transactions
$qris_sql = "
    SELECT
        proses_pembayaran.kode_payment,
        DATE_FORMAT(proses_pembayaran.tanggal_payment,'%H:%i') AS jam,
        CASE
            WHEN proses_pembayaran.`status` = 1 THEN 'SETTLEMENT'
            WHEN proses_pembayaran.`status` = 0 THEN 'PENDING'
        END AS status_bayar,
        pegawai.nama_lengkap as kasir,
        FORMAT(proses_pembayaran.jumlah_uang, 0) AS nominal,
        DATE_FORMAT(proses_pembayaran.update_status,'%d %M %Y %H:%i') AS waktu_dibayar
    FROM
        proses_pembayaran
    INNER JOIN pegawai ON proses_pembayaran.id_user = pegawai.id_user
    INNER JOIN metode_pembayaran ON proses_pembayaran.id_bayar = metode_pembayaran.id_bayar
    WHERE
        metode_pembayaran.kategori = 'QRIS' AND
        DATE(tanggal_payment) = ?
    ORDER BY proses_pembayaran.tanggal_payment DESC
";

$qris_stmt = $conn->prepare($qris_sql);
$qris_stmt->bind_param("s", $selected_date);
$qris_stmt->execute();
$qris_result = $qris_stmt->get_result();
$qris_data = $qris_result->fetch_all(MYSQLI_ASSOC);

// Calculate total for QRIS
$total_qris = 0;
foreach ($qris_data as $row) {
    $amount = str_replace(',', '', $row['nominal']);
    $total_qris += (int)$amount;
}

// Query to get list of all active cashiers - simplified as per gemini.md line 44
$kasir_list_sql = "
    SELECT
        pegawai.id_user,
        pegawai.nama_lengkap
    FROM
        pegawai
    WHERE 
        jabatan = 'Kasir' AND 
        aktif = 1 
    ORDER BY 
        nama_lengkap ASC
";

$kasir_list_stmt = $conn->prepare($kasir_list_sql);
$kasir_list_stmt->execute();
$kasir_list_result = $kasir_list_stmt->get_result();
$kasir_list = $kasir_list_result->fetch_all(MYSQLI_ASSOC);

$selected_kasir_name = '';
if (!empty($selected_kasir)) {
    foreach ($kasir_list as $kasir_option) {
        if ($kasir_option['id_user'] == $selected_kasir) {
            $selected_kasir_name = $kasir_option['nama_lengkap'];
            break;
        }
    }
}

// Query for Per Kasir data
$kasir_data = [];
$total_kasir = 0;
if (!empty($selected_kasir)) {
    $kasir_sql = "
        SELECT 
            state_open_closing.id_open, 
            DATE_FORMAT(tanggal_open, '%d/%m/%Y') as tanggal_open,
            (state_open_closing.first_cash_drawer - state_open_closing.manual_total_cash) AS cash_awal,
            state_open_closing.total_qris AS qris,
            state_open_closing.manual_total_bank AS transfer,
            state_open_closing.manual_total_cash AS cash,
            (state_open_closing.total_qris + state_open_closing.manual_total_bank + state_open_closing.manual_total_cash) + 
            (state_open_closing.first_cash_drawer - state_open_closing.manual_total_cash) AS grand_total,
            COALESCE(SUM(proses_pembayaran.total_diskon), 0) AS total_diskon,
            state_open_closing.id_user,
            pegawai.nama_lengkap AS kasir
        FROM
            state_open_closing
        LEFT JOIN proses_pembayaran ON state_open_closing.id_user = proses_pembayaran.id_user
            AND DATE(tanggal_open) BETWEEN ? AND ?
        INNER JOIN pegawai ON state_open_closing.id_user = pegawai.id_user
        WHERE
            DATE(tanggal_open) BETWEEN ? AND ?
            AND state_open_closing.id_user = ?
        GROUP BY state_open_closing.id_open
        ORDER BY tanggal_open DESC
    ";
    
    $kasir_stmt = $conn->prepare($kasir_sql);
    $kasir_stmt->bind_param("sssss", $tanggal_awal, $tanggal_akhir, $tanggal_awal, $tanggal_akhir, $selected_kasir);
    $kasir_stmt->execute();
    $kasir_result = $kasir_stmt->get_result();
    $kasir_data = $kasir_result->fetch_all(MYSQLI_ASSOC);
    
// Calculate total for Per Kasir
    foreach ($kasir_data as $row) {
        $total_kasir += $row['grand_total'];
    }
}

// Query for Semua tab
$semua_where = "DATE(proses_pembayaran.tanggal_payment) BETWEEN ? AND ?";
$semua_params = [$semua_tanggal1, $semua_tanggal2];
$semua_types = "ss";

if ($semua_kategori !== 'Semua') {
    $semua_where .= " AND metode_pembayaran.kategori = ?";
    $semua_params[] = $semua_kategori;
    $semua_types .= "s";
}

// Main query for Semua tab
$semua_sql = "
    SELECT
        proses_pembayaran.kode_payment AS kode,
        proses_pembayaran.id_tagihan,
        proses_pembayaran.jumlah_uang AS total,
        metode_pembayaran.kategori
    FROM
        proses_pembayaran
    INNER JOIN
        metode_pembayaran ON proses_pembayaran.id_bayar = metode_pembayaran.id_bayar
    WHERE
        $semua_where
    ORDER BY proses_pembayaran.tanggal_payment DESC
    LIMIT ? OFFSET ?
";

$semua_params[] = $semua_limit;
$semua_params[] = $semua_offset;
$semua_types .= "ii";

$semua_stmt = $conn->prepare($semua_sql);
$semua_stmt->bind_param($semua_types, ...$semua_params);
$semua_stmt->execute();
$semua_result = $semua_stmt->get_result();
$semua_data = $semua_result->fetch_all(MYSQLI_ASSOC);

// Count query for pagination
$semua_count_sql = "
    SELECT COUNT(*) as total
    FROM
        proses_pembayaran
    INNER JOIN
        metode_pembayaran ON proses_pembayaran.id_bayar = metode_pembayaran.id_bayar
    WHERE
        $semua_where
";

$count_params = array_slice($semua_params, 0, -2); // Remove limit and offset
$count_types = substr($semua_types, 0, -2);

$semua_count_stmt = $conn->prepare($semua_count_sql);
if (!empty($count_params)) {
    $semua_count_stmt->bind_param($count_types, ...$count_params);
}
$semua_count_stmt->execute();
$semua_count_result = $semua_count_stmt->get_result();
$semua_total_records = $semua_count_result->fetch_assoc()['total'];
$semua_total_pages = ceil($semua_total_records / $semua_limit);

// Calculate total amount for Semua
$total_semua = 0;
foreach ($semua_data as $row) {
    $total_semua += $row['total'];
}

$total_pembayaran_harian = $total_tunai + $total_transfer + $total_qris;
$total_jenis_transaksi = count($tunai_data) + count($transfer_data) + count($qris_data);
?>

<!doctype html>
<html lang="id" data-bs-theme="light">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Laporan Transaksi - Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">
    <link href="../css/newadmin.css?v=3" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
</head>
<body>
    <?php include '_header_new.php'; ?>
    <?php include '_sidebar_new.php'; ?>

    <main class="main-content" id="mainContent">
        <div class="container-fluid">
            <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-3">
                <div>
                    <h2 class="mb-1"><i class="bi bi-list-ul me-2"></i>Laporan Transaksi</h2>
                    <p class="text-muted mb-0">Pantau transaksi harian Anda dengan tampilan modern</p>
                </div>
                <div class="d-flex gap-2">
                    <span class="badge bg-secondary-subtle text-secondary px-3 py-2">
                        <i class="bi bi-calendar-week me-1"></i><?php echo date('d M Y', strtotime($selected_date)); ?>
                    </span>
                    <span class="badge bg-secondary-subtle text-secondary px-3 py-2">
                        <i class="bi bi-person-lines-fill me-1"></i><?php echo $selected_kasir_name ? 'Kasir: ' . htmlspecialchars($selected_kasir_name) : 'Semua Kasir'; ?>
                    </span>
                </div>
            </div>

            <div class="card-modern mb-4">
                <div class="card-header px-4 py-3">
                    <div class="d-flex align-items-center">
                        <i class="bi bi-funnel me-2"></i>
                        <span>Filter Harian</span>
                    </div>
                </div>
                <div class="card-body">
                    <form method="GET" class="row g-3 align-items-end">
                        <div class="col-md-4">
                           
                            <input type="text" class="form-control" id="tanggal" name="tanggal" value="<?php echo htmlspecialchars($selected_date); ?>" required>
                        </div>
                        <div class="col-md-2">
                            <button type="submit" class="btn btn-primary">
                                <i class="bi bi-search me-2"></i>Tampilkan
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <div class="card-modern">
                <div class="card-header px-4 py-3">
                    <div class="d-flex flex-wrap justify-content-between align-items-center gap-3">
                        <div class="d-flex align-items-center">
                            <i class="bi bi-diagram-3 me-2"></i>
                            <span>Rincian Transaksi</span>
                        </div>
                        <ul class="nav nav-pills gap-2" id="transactionTabs" role="tablist">
                            <li class="nav-item" role="presentation">
                                <button class="nav-link active" id="tunai-tab" data-bs-toggle="tab" data-bs-target="#tunai" type="button" role="tab">
                                    <i class="bi bi-cash me-1"></i>Tunai
                                </button>
                            </li>
                            <li class="nav-item" role="presentation">
                                <button class="nav-link" id="transfer-tab" data-bs-toggle="tab" data-bs-target="#transfer" type="button" role="tab">
                                    <i class="bi bi-bank me-1"></i>Transfer
                                </button>
                            </li>
                            <li class="nav-item" role="presentation">
                                <button class="nav-link" id="qris-tab" data-bs-toggle="tab" data-bs-target="#qris" type="button" role="tab">
                                    <i class="bi bi-qr-code me-1"></i>QRIS
                                </button>
                            </li>
                            <li class="nav-item" role="presentation">
                                <button class="nav-link" id="kasir-tab" data-bs-toggle="tab" data-bs-target="#kasir" type="button" role="tab">
                                    <i class="bi bi-person-badge me-1"></i>Per Kasir
                                </button>
                            </li>
                            <li class="nav-item" role="presentation">
                                <button class="nav-link" id="semua-tab" data-bs-toggle="tab" data-bs-target="#semua" type="button" role="tab">
                                    <i class="bi bi-list-ul me-1"></i>Semua
                                </button>
                            </li>
                            <li class="nav-item" role="presentation">
                                <button class="nav-link" id="summary-tab" data-bs-toggle="tab" data-bs-target="#summary" type="button" role="tab">
                                    <i class="bi bi-journal-check me-1"></i>Ringkasan
                                </button>
                            </li>
                        </ul>
                    </div>
                </div>
                <div class="card-body p-0">
                    <div class="tab-content" id="transactionTabsContent">

          <!-- Tab Content -->
          <div class="tab-content" id="transactionTabsContent">
            <!-- Tunai Tab -->
            <div class="tab-pane fade show active" id="tunai" role="tabpanel" aria-labelledby="tunai-tab">
                <div class="px-0">
                    
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-light text-center">
                                <tr>
                                    <th style="width:5%;">No</th>
                                    <th style="width:auto; text-align:left;">Kode Payment</th>
                                    <th class="text-center" style="width:10%;">Jam</th>
                                    <th style="width:10%; text-align:left;">Kasir</th>
                                    <th class="text-center" style="width:10%;">Status</th>
                                    <th class="text-end" style="width:15%;">Nominal</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($tunai_data)): ?>
                                <tr>
                                    <td colspan="6" class="text-center py-5">
                                        <i class="bi bi-inbox fs-1 d-block mb-2 text-muted"></i>
                                        <span class="text-muted">Tidak ada transaksi tunai untuk tanggal <?php echo date('d F Y', strtotime($selected_date)); ?></span>
                                    </td>
                                </tr>
                                <?php else: ?>
                                    <?php $no = 1; foreach ($tunai_data as $row): ?>
                                    <tr>
                                        <td class="text-center fw-semibold"><?php echo $no++; ?></td>
                                        <td class="fw-semibold">&num;<?php echo htmlspecialchars($row['kode_payment']); ?></td>
                                        <td class="text-center text-nowrap"><?php echo htmlspecialchars($row['jam']); ?></td>
                                        <td><?php echo htmlspecialchars($row['kasir']); ?></td>
                                        <td class="text-center">
                                            <?php if ($row['status_bayar'] == 'DIBAYAR'): ?>
                                                <span class="badge bg-success-subtle text-success px-3 py-2">
                                                    <i class="bi bi-check-circle me-1"></i>DIBAYAR
                                                </span>
                                            <?php else: ?>
                                                <span class="badge bg-warning-subtle text-warning px-3 py-2">
                                                    <i class="bi bi-clock me-1"></i>BELUM DIBAYAR
                                                </span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="text-end fw-semibold">Rp <?php echo htmlspecialchars($row['nominal']); ?></td>
                                    </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                    <?php if (!empty($tunai_data)): ?>
                    <div class="d-flex flex-wrap justify-content-between gap-2 mt-3 px-4 py-2 align-items-center">
                        <div class="badge bg-secondary-subtle text-secondary px-3 py-2">
                            <i class="bi bi-123 me-1"></i><?php echo count($tunai_data); ?> transaksi
                        </div>
                        <div class="badge bg-success-subtle text-success px-3 py-2">
                            <i class="bi bi-calculator me-1"></i>Total Tunai: Rp <?php echo number_format($total_tunai, 0, ',', '.'); ?>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Transfer Tab -->
            <div class="tab-pane fade" id="transfer" role="tabpanel" aria-labelledby="transfer-tab">
                <div class="px-0">
                    
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-light text-center">
                                <tr>
                                    <th style="width:5%; text-align:left;" >No</th>
                                    <th style="width:auto; text-align:left;">Kode Payment</th>
                                    <th style="width:15%; text-align:left;">Bank / Metode</th>
                                    <th class="text-center" style="width:10%;">Tanggal</th>
                                    <th class="text-center" style="width:10%;">Status</th>
                                    <th class="text-end" style="width:15%;">Nominal</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($transfer_data)): ?>
                                <tr>
                                    <td colspan="6" class="text-center py-5">
                                        <i class="bi bi-inbox fs-1 d-block mb-2 text-muted"></i>
                                        <span class="text-muted">Tidak ada transaksi transfer untuk tanggal <?php echo date('d F Y', strtotime($selected_date)); ?></span>
                                    </td>
                                </tr>
                                <?php else: ?>
                                    <?php $no = 1; foreach ($transfer_data as $row): ?>
                                    <tr>
                                        <td class="text-left fw-semibold"><?php echo $no++; ?></td>
                                        <td class="fw-semibold">&num;<?php echo htmlspecialchars($row['kode_payment']); ?></td>
                                        <td><?php echo htmlspecialchars($row['bank']); ?></td>
                                        <td class="text-center text-nowrap"><?php echo date('d/m/Y H:i', strtotime($row['tanggal_transfer'])); ?></td>
                                        <td class="text-center">
                                            <a href="#" class="text-decoration-none" onclick="showTransferDetail(
                                                '<?php echo addslashes($row['tanggal_transfer']); ?>',
                                                '<?php echo addslashes($row['no_referensi'] ?? '-'); ?>',
                                                '<?php echo addslashes($row['img_ss'] ?? ''); ?>',
                                                '<?php echo $row['status_pemeriksaan']; ?>',
                                                '<?php echo addslashes($row['tgl_pemeriksaan'] ?? ''); ?>'
                                            )" data-bs-toggle="modal" data-bs-target="#transferDetailModal">
                                                <?php if ($row['status_pemeriksaan'] == 1): ?>
                                                    <span class="badge bg-success-subtle text-success px-3 py-2">
                                                        <i class="bi bi-check-circle me-1"></i>DITERIMA
                                                    </span>
                                                <?php elseif ($row['status_pemeriksaan'] == 2): ?>
                                                    <span class="badge bg-danger-subtle text-danger px-3 py-2">
                                                        <i class="bi bi-x-circle me-1"></i>PALSU
                                                    </span>
                                                <?php else: ?>
                                                    <span class="badge bg-warning-subtle text-warning px-3 py-2">
                                                        <i class="bi bi-clock me-1"></i>BELUM DITERIMA
                                                    </span>
                                                <?php endif; ?>
                                            </a>
                                        </td>
                                        <td class="text-end fw-semibold text-primary">Rp <?php echo number_format($row['nominal_transfer'], 0, ',', '.'); ?></td>
                                    </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                    <?php if (!empty($transfer_data)): ?>
                    <div class="d-flex justify-content-end mt-3">
                        <div class="badge bg-primary-subtle text-primary px-3 py-2">
                            <i class="bi bi-calculator me-1"></i>Total Transfer: Rp <?php echo number_format($total_transfer, 0, ',', '.'); ?>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- QRIS Tab -->
            <div class="tab-pane fade" id="qris" role="tabpanel" aria-labelledby="qris-tab">
                <div class="px-0">
                    
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-light text-center">
                                <tr>
                                    <th style="width:5%; text-align:left;">No</th>
                                    <th style="width:auto; text-align:left;">Kode Payment</th>
                                    <th class="text-center" style="width:10%;">Jam</th>
                                    <th style="width:15%; text-align:left;">Kasir</th>
                                    <th class="text-center" style="width:15%;">Status Bayar</th>
                                    <th class="text-end" style="width:15%;">Nominal</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($qris_data)): ?>
                                <tr>
                                    <td colspan="6" class="text-center py-5">
                                        <i class="bi bi-inbox fs-1 d-block mb-2 text-muted"></i>
                                        <span class="text-muted">Tidak ada transaksi QRIS untuk tanggal <?php echo date('d F Y', strtotime($selected_date)); ?></span>
                                    </td>
                                </tr>
                                <?php else: ?>
                                    <?php $no = 1; foreach ($qris_data as $row): ?>
                                    <tr>
                                        <td class="text-start fw-semibold"><?php echo $no++; ?></td>
                                        <td class="fw-semibold">&num;<?php echo htmlspecialchars($row['kode_payment']); ?></td>
                                        <td class="text-center text-nowrap"><?php echo htmlspecialchars($row['jam']); ?></td>
                                        <td class="text-start"><?php echo htmlspecialchars($row['kasir']); ?></td>
                                        <td class="text-center">
                                            <?php if ($row['status_bayar'] == 'SETTLEMENT'): ?>
                                                <span class="badge bg-success-subtle text-success px-3 py-2">
                                                    <i class="bi bi-check-circle me-1"></i>SETTLEMENT
                                                </span>
                                            <?php else: ?>
                                                <span class="badge bg-warning-subtle text-warning px-3 py-2">
                                                    <i class="bi bi-clock me-1"></i>PENDING
                                                </span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="text-end fw-semibold">Rp <?php echo htmlspecialchars($row['nominal']); ?></td>
                                    </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                    <?php if (!empty($qris_data)): ?>
                    <div class="d-flex justify-content-end mt-3">
                        <div class="badge bg-warning-subtle text-warning px-3 py-2">
                            <i class="bi bi-calculator me-1"></i>Total QRIS: Rp <?php echo number_format($total_qris, 0, ',', '.'); ?>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Per Kasir Tab -->
                        <div class="tab-pane fade" id="kasir" role="tabpanel" aria-labelledby="kasir-tab">
                            <div class="px-0">
                                
                                <div class="card-modern border-0 shadow-none">
                                    <div class="card-body px-0">
                                        <form method="GET" class="row g-3 align-items-end px-4 py-2"  id="kasirFilterForm">
                                            <input type="hidden" name="tab" value="kasir">
                                            <div class="col-md-3">
                                                <label for="tanggal_awal" class="form-label">Tanggal Awal</label>
                                                <input type="text" class="form-control" id="tanggal_awal" name="tanggal_awal" value="<?php echo htmlspecialchars($tanggal_awal); ?>">
                                            </div>
                                            <div class="col-md-3">
                                                <label for="tanggal_akhir" class="form-label">Tanggal Akhir</label>
                                                <input type="text" class="form-control" id="tanggal_akhir" name="tanggal_akhir" value="<?php echo htmlspecialchars($tanggal_akhir); ?>">
                                            </div>
                                            <div class="col-md-4">
                                                <label for="kasir" class="form-label">Pilih Kasir</label>
                                                <select class="form-select" id="kasir" name="kasir">
                                                    <option value="">-- Pilih Kasir --</option>
                                                    <?php foreach ($kasir_list as $kasir): ?>
                                                        <option value="<?php echo htmlspecialchars($kasir['id_user']); ?>" <?php echo ($selected_kasir == $kasir['id_user']) ? 'selected' : ''; ?>>
                                                            <?php echo htmlspecialchars($kasir['nama_lengkap']); ?>
                                                        </option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </div>
                                            <div class="col-md-2">
                                                <button type="submit" class="btn btn-primary w-100">
                                                    <i class="bi bi-search me-2"></i>Tampilkan
                                                </button>
                                            </div>
                                        </form>
                                    </div>
                                </div>

                                <?php if (!empty($selected_kasir)): ?>
                                <div class="table-responsive">
                                    <table class="table table-hover align-middle mb-0">
                                        <thead class="table-light text-center">
                                            <tr>
                                                <th style="width:5%; text-align:left;">No</th>
                                                <th style="width:12%; text-align:center;">Tanggal</th>
                                                <th style="width:15%; text-align:left;">Kasir</th>
                                                <th class="text-end" style="width:12%;">Cash Awal</th>
                                                <th class="text-end" style="width:12%;">QRIS</th>
                                                <th class="text-end" style="width:12%;">Transfer</th>
                                                <th class="text-end" style="width:12%;">Cash</th>
                                                <th class="text-end" style="width:12%;">Total Diskon</th>
                                                <th class="text-end" style="width:12%;">Grand Total</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php if (empty($kasir_data)): ?>
                                            <tr>
                                                <td colspan="9" class="text-center py-5">
                                                    <i class="bi bi-inbox fs-1 d-block mb-2 text-muted"></i>
                                                    <span class="text-muted">Tidak ada data untuk kasir yang dipilih</span>
                                                </td>
                                            </tr>
                                            <?php else: ?>
                                                <?php $no = 1; foreach ($kasir_data as $row): ?>
                                                <tr>
                                                    <td class="text-left fw-semibold"><?php echo $no++; ?></td>
                                                    <td class="text-center text-nowrap">
                                                        <a href="#" class="text-decoration-none" onclick="showKasirDetail('<?php echo $tanggal_awal; ?>', '<?php echo $tanggal_akhir; ?>', '<?php echo $row['id_user']; ?>', '<?php echo htmlspecialchars($row['kasir']); ?>')" data-bs-toggle="modal" data-bs-target="#kasirDetailModal">
                                                            <span class="text-primary">
                                                                <i class="bi bi-calendar3 me-1"></i><?php echo htmlspecialchars($row['tanggal_open']); ?>
                                                            </span>
                                                        </a>
                                                    </td>
                                                    <td><?php echo htmlspecialchars($row['kasir']); ?></td>
                                                    <td class="text-end">Rp <?php echo number_format($row['cash_awal'], 0, ',', '.'); ?></td>
                                                    <td class="text-end">Rp <?php echo number_format($row['qris'], 0, ',', '.'); ?></td>
                                                    <td class="text-end">Rp <?php echo number_format($row['transfer'], 0, ',', '.'); ?></td>
                                                    <td class="text-end">Rp <?php echo number_format($row['cash'], 0, ',', '.'); ?></td>
                                                    <td class="text-end text-danger">Rp <?php echo number_format($row['total_diskon'], 0, ',', '.'); ?></td>
                                                    <td class="text-end fw-semibold text-primary">Rp <?php echo number_format($row['grand_total'], 0, ',', '.'); ?></td>
                                                </tr>
                                                <?php endforeach; ?>
                                            <?php endif; ?>
                                        </tbody>
                                    </table>
                                </div>
                                <?php if (!empty($kasir_data)): ?>
                                <div class="d-flex justify-content-end mt-3 px-4 py-2">
                                    <div class="badge bg-primary-subtle text-primary px-3 py-2">
                                        <i class="bi bi-calculator me-1"></i>Total Kasir: Rp <?php echo number_format($total_kasir, 0, ',', '.'); ?>
                                    </div>
                                </div>
                                <?php endif; ?>
                                <?php else: ?>
                                <div class="text-center py-5">
                                    <i class="bi bi-person-badge fs-1 text-muted"></i>
                                    <p class="mt-3 text-muted">Silakan pilih kasir dan periode untuk melihat data</p>
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>

            <!-- Semua Tab -->
                        <div class="tab-pane fade" id="semua" role="tabpanel" aria-labelledby="semua-tab">
                            <div class="px-0">
                                
                                <form method="GET" class="row g-3 align-items-end mb-4 px-4 py-4">
                                    <input type="hidden" name="tab" value="semua">
                                    <input type="hidden" name="semua_page" value="1">
                                    <div class="col-md-3">
                                        <label for="tanggal1" class="form-label">Tanggal Awal</label>
                                        <input type="text" class="form-control" id="tanggal1" name="tanggal1" value="<?php echo htmlspecialchars($semua_tanggal1); ?>">
                                    </div>
                                    <div class="col-md-3">
                                        <label for="tanggal2" class="form-label">Tanggal Akhir</label>
                                        <input type="text" class="form-control" id="tanggal2" name="tanggal2" value="<?php echo htmlspecialchars($semua_tanggal2); ?>">
                                    </div>
                                    <div class="col-md-3">
                                        <label for="kategori" class="form-label">Kategori</label>
                                        <select class="form-select" id="kategori" name="kategori">
                                            <option value="Semua" <?php echo $semua_kategori == 'Semua' ? 'selected' : ''; ?>>Semua</option>
                                            <option value="Tunai" <?php echo $semua_kategori == 'Tunai' ? 'selected' : ''; ?>>Tunai</option>
                                            <option value="Transfer" <?php echo $semua_kategori == 'Transfer' ? 'selected' : ''; ?>>Transfer</option>
                                            <option value="QRIS" <?php echo $semua_kategori == 'QRIS' ? 'selected' : ''; ?>>QRIS</option>
                                        </select>
                                    </div>
                                    <div class="col-md-3">
                                        <button type="submit" class="btn btn-primary w-100">
                                            <i class="bi bi-search me-2"></i>Tampilkan
                                        </button>
                                    </div>
                                </form>

                                <div class="table-responsive">
                                    <table class="table table-hover align-middle mb-0">
                                        <thead class="table-light text-center">
                                            <tr>
                                                <th style="width:5%; text-align:left;">No</th>
                                                <th style="width:auto; text-align:left;">Kode</th>
                                                <th style="width:20%; text-align:left;">ID</th>
                                                <th style="width:8%; text-align:center;">Kategori</th>
                                                <th class="text-end" style="width:15%;">Total</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php if (empty($semua_data)): ?>
                                            <tr>
                                                <td colspan="5" class="text-center py-5">
                                                    <i class="bi bi-inbox fs-1 d-block mb-2 text-muted"></i>
                                                    <span class="text-muted">Tidak ada data untuk filter yang dipilih</span>
                                                </td>
                                            </tr>
                                            <?php else: ?>
                                                <?php $no = $semua_offset + 1; foreach ($semua_data as $row): ?>
                                                <tr>
                                                    <td class="text-start fw-semibold"><?php echo $no++; ?></td>
                                                    <td class="fw-semibold"><?php echo htmlspecialchars($row['kode']); ?></td>
                                                    <td class="fw-semibold text-start" ><?php echo htmlspecialchars($row['id_tagihan']); ?></td>
                                                    <td>
                                                        <?php
                                                        $badge_class = 'bg-secondary';
                                                        if ($row['kategori'] === 'Tunai') {
                                                            $badge_class = 'bg-success';
                                                        } elseif ($row['kategori'] === 'Transfer') {
                                                            $badge_class = 'bg-info';
                                                        } elseif ($row['kategori'] === 'QRIS') {
                                                            $badge_class = 'bg-warning text-dark';
                                                        }
                                                        ?>
                                                        <span class="badge <?php echo $badge_class; ?>"><?php echo htmlspecialchars($row['kategori']); ?></span>
                                                    </td>
                                                    <td class="text-end fw-semibold">Rp <?php echo number_format($row['total'], 0, ',', '.'); ?></td>
                                                </tr>
                                                <?php endforeach; ?>
                                            <?php endif; ?>
                                        </tbody>
                                    </table>
                                </div>

                                <?php if ($semua_total_pages > 1): ?>
                                <nav aria-label="Page navigation" class="mt-3">
                                    <ul class="pagination justify-content-center mb-0">
                                        <li class="page-item <?php echo $semua_page <= 1 ? 'disabled' : ''; ?>">
                                            <a class="page-link" href="?tab=semua&tanggal1=<?php echo $semua_tanggal1; ?>&tanggal2=<?php echo $semua_tanggal2; ?>&kategori=<?php echo $semua_kategori; ?>&semua_page=<?php echo $semua_page - 1; ?>">Previous</a>
                                        </li>
                                        <?php
                                        $start_page = max(1, $semua_page - 2);
                                        $end_page = min($semua_total_pages, $semua_page + 2);
                                        if ($start_page > 1): ?>
                                        <li class="page-item"><a class="page-link" href="?tab=semua&tanggal1=<?php echo $semua_tanggal1; ?>&tanggal2=<?php echo $semua_tanggal2; ?>&kategori=<?php echo $semua_kategori; ?>&semua_page=1">1</a></li>
                                        <?php if ($start_page > 2): ?><li class="page-item disabled"><span class="page-link">...</span></li><?php endif; ?>
                                        <?php endif; ?>
                                        <?php for ($i = $start_page; $i <= $end_page; $i++): ?>
                                        <li class="page-item <?php echo $i == $semua_page ? 'active' : ''; ?>">
                                            <a class="page-link" href="?tab=semua&tanggal1=<?php echo $semua_tanggal1; ?>&tanggal2=<?php echo $semua_tanggal2; ?>&kategori=<?php echo $semua_kategori; ?>&semua_page=<?php echo $i; ?>"><?php echo $i; ?></a>
                                        </li>
                                        <?php endfor; ?>
                                        <?php if ($end_page < $semua_total_pages): ?>
                                        <?php if ($end_page < $semua_total_pages - 1): ?><li class="page-item disabled"><span class="page-link">...</span></li><?php endif; ?>
                                        <li class="page-item"><a class="page-link" href="?tab=semua&tanggal1=<?php echo $semua_tanggal1; ?>&tanggal2=<?php echo $semua_tanggal2; ?>&kategori=<?php echo $semua_kategori; ?>&semua_page=<?php echo $semua_total_pages; ?>"><?php echo $semua_total_pages; ?></a></li>
                                        <?php endif; ?>
                                        <li class="page-item <?php echo $semua_page >= $semua_total_pages ? 'disabled' : ''; ?>">
                                            <a class="page-link" href="?tab=semua&tanggal1=<?php echo $semua_tanggal1; ?>&tanggal2=<?php echo $semua_tanggal2; ?>&kategori=<?php echo $semua_kategori; ?>&semua_page=<?php echo $semua_page + 1; ?>">Next</a>
                                        </li>
                                    </ul>
                                </nav>
                                <?php endif; ?>

                                <?php if (!empty($semua_data)): ?>
                                <div class="d-flex justify-content-between align-items-center mt-3 px-4 py-2">
                                    <span class="text-muted">Menampilkan <?php echo $semua_offset + 1; ?> - <?php echo min($semua_offset + $semua_limit, $semua_total_records); ?> dari <?php echo $semua_total_records; ?> transaksi <?php echo $semua_kategori !== 'Semua' ? '(Filter: ' . htmlspecialchars($semua_kategori) . ')' : ''; ?></span>
                                    <div class="badge bg-success-subtle text-success px-3 py-2">
                                        <i class="bi bi-calculator me-1"></i>Total Halaman: Rp <?php echo number_format($total_semua, 0, ',', '.'); ?>
                                    </div>
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div class="tab-pane fade" id="summary" role="tabpanel" aria-labelledby="summary-tab">
                            <div class="px-4 py-4">
                                <div class="row g-4">
                                    <div class="col-lg-4">
                                        <div class="summary-card bg-success-subtle">
                                            <h6 class="text-success mb-3"><i class="bi bi-cash-stack me-2"></i>Tunai</h6>
                                            <div class="d-flex justify-content-between mb-2">
                                                <span>Total Nominal</span>
                                                <strong>Rp <?php echo number_format($total_tunai, 0, ',', '.'); ?></strong>
                                            </div>
                                            <div class="d-flex justify-content-between mb-2">
                                                <span>Jumlah Transaksi</span>
                                                <strong><?php echo count($tunai_data); ?></strong>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-lg-4">
                                        <div class="summary-card bg-primary-subtle">
                                            <h6 class="text-primary mb-3"><i class="bi bi-bank me-2"></i>Transfer / EDC</h6>
                                            <div class="d-flex justify-content-between mb-2">
                                                <span>Total Nominal</span>
                                                <strong>Rp <?php echo number_format($total_transfer, 0, ',', '.'); ?></strong>
                                            </div>
                                            <div class="d-flex justify-content-between mb-2">
                                                <span>Jumlah Transaksi</span>
                                                <strong><?php echo count($transfer_data); ?></strong>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-lg-4">
                                        <div class="summary-card bg-warning-subtle">
                                            <h6 class="text-warning mb-3"><i class="bi bi-qr-code-scan me-2"></i>QRIS</h6>
                                            <div class="d-flex justify-content-between mb-2">
                                                <span>Total Nominal</span>
                                                <strong>Rp <?php echo number_format($total_qris, 0, ',', '.'); ?></strong>
                                            </div>
                                            <div class="d-flex justify-content-between mb-2">
                                                <span>Jumlah Transaksi</span>
                                                <strong><?php echo count($qris_data); ?></strong>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <div class="row g-4 mt-3">
                                    <div class="col-lg-6">
                                        <div class="summary-card bg-secondary-subtle">
                                            <h6 class="text-secondary mb-3"><i class="bi bi-people me-2"></i>Per Kasir</h6>
                                            <div class="d-flex justify-content-between mb-2">
                                                <span>Total Grand Total</span>
                                                <strong>Rp <?php echo number_format($total_kasir, 0, ',', '.'); ?></strong>
                                            </div>
                                            <div class="d-flex justify-content-between mb-2">
                                                <span>Periode Dipilih</span>
                                                <strong><?php echo htmlspecialchars($tanggal_awal); ?> s/d <?php echo htmlspecialchars($tanggal_akhir); ?></strong>
                                            </div>
                                            <div class="d-flex justify-content-between">
                                                <span>Kasir Dipilih</span>
                                                <strong><?php echo $selected_kasir_name ? htmlspecialchars($selected_kasir_name) : 'Semua'; ?></strong>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-lg-6">
                                        <div class="summary-card bg-info-subtle">
                                            <h6 class="text-info mb-3"><i class="bi bi-list-check me-2"></i>Semua Transaksi</h6>
                                            <div class="d-flex justify-content-between mb-2">
                                                <span>Total Halaman Saat Ini</span>
                                                <strong>Rp <?php echo number_format($total_semua, 0, ',', '.'); ?></strong>
                                            </div>
                                            <div class="d-flex justify-content-between mb-2">
                                                <span>Rentang Tanggal</span>
                                                <strong><?php echo htmlspecialchars($semua_tanggal1); ?> s/d <?php echo htmlspecialchars($semua_tanggal2); ?></strong>
                                            </div>
                                            <div class="d-flex justify-content-between">
                                                <span>Kategori</span>
                                                <strong><?php echo htmlspecialchars($semua_kategori); ?></strong>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <div class="mt-4 text-center">
                                    <div class="badge bg-secondary-subtle text-secondary px-4 py-3 fs-5">
                                        <i class="bi bi-calculator me-2"></i>Total Kombinasi: Rp <?php echo number_format($total_tunai + $total_transfer + $total_qris, 0, ',', '.'); ?>
                                    </div>
                                </div>
                            </div>
                        </div>
            </div>
        </div>
    </main>

    <div class="position-fixed bottom-0 end-0 p-3" style="z-index: 1080">
        <div id="kasirToast" class="toast align-items-center text-bg-warning border-0" role="alert" aria-live="assertive" aria-atomic="true" data-bs-delay="3000">
            <div class="d-flex">
                <div class="toast-body">
                    Silakan pilih kasir terlebih dahulu.
                </div>
                <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button>
            </div>
        </div>
    </div>

    <!-- Kasir Detail Modal -->
    <div class="modal fade" id="kasirDetailModal" tabindex="-1">
      <div class="modal-dialog modal-xl">
        <div class="modal-content">
          <div class="modal-header">
            <h5 class="modal-title">Detail Transaksi Kasir - <span id="kasir-name"></span></h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
          </div>
          <div class="modal-body">
            <div class="table-responsive">
              <table class="table table-hover table-sm">
                <thead class="table-dark">
                  <tr>
                    <th>No</th>
                    <th>Kode Payment</th>
                    <th>Tanggal</th>
                    <th>Jam</th>
                    <th>Kategori</th>
                    <th>Diskon</th>
                    <th class="text-end">Total Diskon</th>
                    <th class="text-end">Total Kotor</th>
                    <th class="text-end">Total Bersih</th>
                  </tr>
                </thead>
                <tbody id="kasir-detail-tbody">
                  <tr>
                    <td colspan="9" class="text-center">
                      <div class="spinner-border text-primary" role="status">
                        <span class="visually-hidden">Loading...</span>
                      </div>
                    </td>
                  </tr>
                </tbody>
              </table>
            </div>
            
            <!-- Pagination -->
            <nav aria-label="Page navigation" class="mt-3">
              <ul class="pagination justify-content-center" id="kasir-detail-pagination">
              </ul>
            </nav>
          </div>
        </div>
      </div>
    </div>
    
    <!-- Transfer Detail Modal -->
    <div class="modal fade" id="transferDetailModal" tabindex="-1">
      <div class="modal-dialog modal-lg">
        <div class="modal-content">
          <div class="modal-header">
            <h5 class="modal-title">Detail Transfer</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
          </div>
          <div class="modal-body">
            <div class="row g-3">
              <div class="col-md-6">
                <label class="form-label text-muted">Tanggal Transfer</label>
                <p class="fw-semibold" id="modal-tanggal-transfer">-</p>
              </div>
              <div class="col-md-6">
                <label class="form-label text-muted">Tanggal Pemeriksaan</label>
                <p class="fw-semibold" id="modal-tgl-pemeriksaan">-</p>
              </div>
              <div class="col-md-6">
                <label class="form-label text-muted">No. Referensi</label>
                <p class="fw-semibold" id="modal-no-referensi">-</p>
              </div>
              <div class="col-md-6">
                <label class="form-label text-muted">Status Pemeriksaan</label>
                <p id="modal-status-pemeriksaan">-</p>
              </div>
              
              <div class="col-12">
                <label class="form-label text-muted">Bukti Transfer</label>
                <div id="modal-img-container" class="text-center">
                  <p class="text-muted">Tidak ada bukti</p>
                </div>
              </div>
            </div>
          </div>
         
        </div>
      </div>
    </div>

    <?php include '_scripts_new.php'; ?>
    <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
    <script>
      document.addEventListener('DOMContentLoaded', function() {
        // Initialize date picker with Flatpickr for main date filter
        if (document.getElementById('tanggal')) {
          flatpickr("#tanggal", {
            dateFormat: "Y-m-d",
            maxDate: "today",
            defaultDate: "<?php echo $selected_date; ?>",
            locale: {
              firstDayOfWeek: 1
            }
          });
        }
        
        // Initialize date pickers for Per Kasir tab - simplified without onChange events
        if (document.getElementById('tanggal_awal')) {
          flatpickr("#tanggal_awal", {
            dateFormat: "Y-m-d",
            maxDate: "today",
            defaultDate: "<?php echo $tanggal_awal; ?>",
            locale: {
              firstDayOfWeek: 1
            }
          });
        }
        
        if (document.getElementById('tanggal_akhir')) {
          flatpickr("#tanggal_akhir", {
            dateFormat: "Y-m-d",
            maxDate: "today",
            defaultDate: "<?php echo $tanggal_akhir; ?>",
            locale: {
              firstDayOfWeek: 1
            }
          });
        }
        
        // Initialize date pickers for Semua tab
        if (document.getElementById('tanggal1')) {
          flatpickr("#tanggal1", {
            dateFormat: "Y-m-d",
            maxDate: "today",
            defaultDate: "<?php echo $semua_tanggal1; ?>",
            locale: {
              firstDayOfWeek: 1
            }
          });
        }
        
        if (document.getElementById('tanggal2')) {
          flatpickr("#tanggal2", {
            dateFormat: "Y-m-d",
            maxDate: "today",
            defaultDate: "<?php echo $semua_tanggal2; ?>",
            locale: {
              firstDayOfWeek: 1
            }
          });
        }
      
        // Activate the correct tab based on URL parameter
        const urlParams = new URLSearchParams(window.location.search);
        const activeTab = urlParams.get('tab');
        
        // Use setTimeout to ensure Bootstrap is fully loaded
        setTimeout(function() {
          if (activeTab === 'kasir') {
            const kasirTabEl = document.getElementById('kasir-tab');
            if (kasirTabEl) {
              const kasirTab = new bootstrap.Tab(kasirTabEl);
              kasirTab.show();
            }
          } else if (activeTab === 'transfer') {
            const transferTabEl = document.getElementById('transfer-tab');
            if (transferTabEl) {
              const transferTab = new bootstrap.Tab(transferTabEl);
              transferTab.show();
            }
          } else if (activeTab === 'qris') {
            const qrisTabEl = document.getElementById('qris-tab');
            if (qrisTabEl) {
              const qrisTab = new bootstrap.Tab(qrisTabEl);
              qrisTab.show();
            }
          } else if (activeTab === 'semua') {
            const semuaTabEl = document.getElementById('semua-tab');
            if (semuaTabEl) {
              const semuaTab = new bootstrap.Tab(semuaTabEl);
              semuaTab.show();
            }
          }
        }, 100);
      
        // Prevent form submission on Enter key except for button
        const kasirForm = document.getElementById('kasirFilterForm');
        if (kasirForm) {
          kasirForm.addEventListener('keypress', function(e) {
            if (e.key === 'Enter' && e.target.tagName !== 'BUTTON') {
              e.preventDefault();
              return false;
            }
          });
          kasirForm.addEventListener('submit', function(e) {
            const kasirSelect = document.getElementById('kasir');
            if (kasirSelect && !kasirSelect.value) {
              e.preventDefault();
              const toast = document.getElementById('kasirToast');
              if (toast) {
                const toastInstance = bootstrap.Toast.getOrCreateInstance(toast);
                toastInstance.show();
              }
            }
          });
        }
        
        // Handle tab clicks to maintain state
        document.querySelectorAll('[data-bs-toggle="tab"]').forEach(tab => {
          tab.addEventListener('shown.bs.tab', function (e) {
            const tabId = e.target.id.replace('-tab', '');
            const url = new URL(window.location);
            
            // Update URL parameter for active tab
            if (tabId === 'tunai') {
              url.searchParams.delete('tab');
            } else {
              url.searchParams.set('tab', tabId);
            }
            
            // For non-kasir tabs, remove kasir-specific parameters
            if (tabId !== 'kasir') {
              url.searchParams.delete('tanggal_awal');
              url.searchParams.delete('tanggal_akhir');
              url.searchParams.delete('kasir');
            }
            
            // Update URL without page reload
            window.history.replaceState({}, '', url);
          });
        });
      }); // End of DOMContentLoaded

      // Global variables for kasir detail modal
      let currentKasirPage = 1;
      let currentKasirData = {
        tanggal_awal: '',
        tanggal_akhir: '',
        id_user: '',
        kasir_name: ''
      };
      
      // Function to show kasir detail modal
      function showKasirDetail(tanggalAwal, tanggalAkhir, idUser, kasirName) {
        currentKasirData = {
          tanggal_awal: tanggalAwal,
          tanggal_akhir: tanggalAkhir,
          id_user: idUser,
          kasir_name: kasirName
        };
        currentKasirPage = 1;
        
        // Set kasir name in modal title
        document.getElementById('kasir-name').textContent = kasirName;
        
        // Load data
        loadKasirDetailData();
      }
      
      // Function to load kasir detail data
      function loadKasirDetailData() {
        const tbody = document.getElementById('kasir-detail-tbody');
        const pagination = document.getElementById('kasir-detail-pagination');
        
        // Show loading
        tbody.innerHTML = `
          <tr>
            <td colspan="9" class="text-center">
              <div class="spinner-border text-primary" role="status">
                <span class="visually-hidden">Loading...</span>
              </div>
            </td>
          </tr>
        `;
        
        // Fetch data
        fetch(`get_kasir_detail.php?tanggal_awal=${currentKasirData.tanggal_awal}&tanggal_akhir=${currentKasirData.tanggal_akhir}&id_user=${currentKasirData.id_user}&page=${currentKasirPage}`)
          .then(response => {
            if (!response.ok) {
              throw new Error('Network response was not ok');
            }
            return response.json();
          })
          .then(result => {
            // Clear tbody
            tbody.innerHTML = '';
            
            if (result.data && result.data.length > 0) {
              // Populate table
              result.data.forEach((item, index) => {
                const row = document.createElement('tr');
                row.innerHTML = `
                  <td class="text-center">${((currentKasirPage - 1) * 10) + index + 1}</td>
                  <td>${item.kode_payment}</td>
                  <td>${item.tanggal}</td>
                  <td class="text-center">${item.jam}</td>
                  <td>${item.kategori}</td>
                  <td class="text-center">${item.diskon || '-'}</td>
                  <td class="text-end">Rp ${item.total_diskon_formatted}</td>
                  <td class="text-end">Rp ${item.total_kotor_formatted}</td>
                  <td class="text-end fw-bold">Rp ${item.total_bersih_formatted}</td>
                `;
                tbody.appendChild(row);
              });
              
              // Update pagination
              updateKasirPagination(result.pagination);
            } else {
              tbody.innerHTML = `
                <tr>
                  <td colspan="9" class="text-center text-muted py-4">
                    <i class="bi bi-inbox display-4 d-block mb-2"></i>
                    Tidak ada data transaksi
                  </td>
                </tr>
              `;
              pagination.innerHTML = '';
            }
          })
          .catch(error => {
            console.error('Error loading kasir detail:', error);
            tbody.innerHTML = `
              <tr>
                <td colspan="9" class="text-center text-danger">
                  <i class="bi bi-exclamation-triangle display-4 d-block mb-2"></i>
                  Error loading data: ${error.message}
                </td>
              </tr>
            `;
          });
      }
      
      // Function to update pagination
      function updateKasirPagination(paginationData) {
        const pagination = document.getElementById('kasir-detail-pagination');
        pagination.innerHTML = '';
        
        if (paginationData.total_pages <= 1) {
          return;
        }
        
        // Previous button
        const prevLi = document.createElement('li');
        prevLi.className = `page-item ${paginationData.current_page === 1 ? 'disabled' : ''}`;
        prevLi.innerHTML = `<a class="page-link" href="#" onclick="goToKasirPage(${paginationData.current_page - 1}); return false;">Previous</a>`;
        pagination.appendChild(prevLi);
        
        // Page numbers
        const maxVisiblePages = 5;
        let startPage = Math.max(1, paginationData.current_page - Math.floor(maxVisiblePages / 2));
        let endPage = Math.min(paginationData.total_pages, startPage + maxVisiblePages - 1);
        
        if (endPage - startPage < maxVisiblePages - 1) {
          startPage = Math.max(1, endPage - maxVisiblePages + 1);
        }
        
        if (startPage > 1) {
          const firstLi = document.createElement('li');
          firstLi.className = 'page-item';
          firstLi.innerHTML = `<a class="page-link" href="#" onclick="goToKasirPage(1); return false;">1</a>`;
          pagination.appendChild(firstLi);
          
          if (startPage > 2) {
            const dotsLi = document.createElement('li');
            dotsLi.className = 'page-item disabled';
            dotsLi.innerHTML = `<span class="page-link">...</span>`;
            pagination.appendChild(dotsLi);
          }
        }
        
        for (let i = startPage; i <= endPage; i++) {
          const pageLi = document.createElement('li');
          pageLi.className = `page-item ${i === paginationData.current_page ? 'active' : ''}`;
          pageLi.innerHTML = `<a class="page-link" href="#" onclick="goToKasirPage(${i}); return false;">${i}</a>`;
          pagination.appendChild(pageLi);
        }
        
        if (endPage < paginationData.total_pages) {
          if (endPage < paginationData.total_pages - 1) {
            const dotsLi = document.createElement('li');
            dotsLi.className = 'page-item disabled';
            dotsLi.innerHTML = `<span class="page-link">...</span>`;
            pagination.appendChild(dotsLi);
          }
          
          const lastLi = document.createElement('li');
          lastLi.className = 'page-item';
          lastLi.innerHTML = `<a class="page-link" href="#" onclick="goToKasirPage(${paginationData.total_pages}); return false;">${paginationData.total_pages}</a>`;
          pagination.appendChild(lastLi);
        }
        
        // Next button
        const nextLi = document.createElement('li');
        nextLi.className = `page-item ${paginationData.current_page === paginationData.total_pages ? 'disabled' : ''}`;
        nextLi.innerHTML = `<a class="page-link" href="#" onclick="goToKasirPage(${paginationData.current_page + 1}); return false;">Next</a>`;
        pagination.appendChild(nextLi);
      }
      
      // Function to go to specific page
      function goToKasirPage(page) {
        if (page < 1) return;
        currentKasirPage = page;
        loadKasirDetailData();
      }
      
      // Function to show transfer detail modal
      function showTransferDetail(tanggalTransfer, noReferensi, imgSs, statusPemeriksaan, tglPemeriksaan) {
        // Format tanggal transfer
        if (tanggalTransfer) {
          const date = new Date(tanggalTransfer);
          const options = { 
            year: 'numeric', 
            month: 'long', 
            day: 'numeric', 
            hour: '2-digit', 
            minute: '2-digit' 
          };
          document.getElementById('modal-tanggal-transfer').textContent = date.toLocaleDateString('id-ID', options);
        } else {
          document.getElementById('modal-tanggal-transfer').textContent = '-';
        }
        
        // Set no referensi
        document.getElementById('modal-no-referensi').textContent = noReferensi || '-';
        
        // Set status pemeriksaan with badge
        let statusHtml = '';
        if (statusPemeriksaan == '1') {
          statusHtml = '<span class="badge bg-success fs-6 px-3 py-2"><i class="bi bi-check-circle me-1"></i>DITERIMA</span>';
        } else if (statusPemeriksaan == '2') {
          statusHtml = '<span class="badge bg-danger fs-6 px-3 py-2"><i class="bi bi-x-circle me-1"></i>PALSU</span>';
        } else {
          statusHtml = '<span class="badge bg-warning fs-6 px-3 py-2"><i class="bi bi-clock me-1"></i>BELUM DITERIMA</span>';
        }
        document.getElementById('modal-status-pemeriksaan').innerHTML = statusHtml;
        
        // Set tanggal pemeriksaan
        if (tglPemeriksaan && tglPemeriksaan !== '') {
          const datePeriksa = new Date(tglPemeriksaan);
          const options = { 
            year: 'numeric', 
            month: 'long', 
            day: 'numeric', 
            hour: '2-digit', 
            minute: '2-digit' 
          };
          document.getElementById('modal-tgl-pemeriksaan').textContent = datePeriksa.toLocaleDateString('id-ID', options);
        } else {
          document.getElementById('modal-tgl-pemeriksaan').textContent = 'Belum diperiksa';
        }
        
        // Set image
        // Set image
const imgContainer = document.getElementById('modal-img-container');
if (imgSs && imgSs !== '') {
  imgContainer.innerHTML = `
    <a href="../images/bukti_tf/${imgSs}" target="_blank" class="d-block">
      <img src="../images/bukti_tf/${imgSs}" class="img-fluid rounded border" style="max-height: 400px;" alt="Bukti Transfer">
    </a>
    <p class="mt-2 text-muted small">Klik gambar untuk melihat ukuran penuh</p>
  `;
} else {
  imgContainer.innerHTML = '<p class="text-muted">Tidak ada bukti transfer</p>';
}
      }
    </script>
  </body>
</html>