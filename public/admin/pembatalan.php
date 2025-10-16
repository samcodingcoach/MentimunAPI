<?php
session_start();
require_once __DIR__ . '/../../config/koneksi.php';

if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    header('Location: login.php');
    exit();
}

if ($_SESSION['jabatan'] !== 'Admin' && $_SESSION['jabatan'] !== 'Kasir') {
    header('location: index.php');
    exit;
}

$message = '';
$error = '';

$tanggal_mulai = isset($_GET['tanggal_mulai']) ? $_GET['tanggal_mulai'] : date('Y-m-d');
$tanggal_selesai = isset($_GET['tanggal_selesai']) ? $_GET['tanggal_selesai'] : date('Y-m-d');

$page = isset($_GET['page']) ? (int) $_GET['page'] : 1;
$limit = 20;
$offset = ($page - 1) * $limit;

$count_sql = "
    SELECT COUNT(DISTINCT pesanan.id_tagihan) AS total
    FROM dapur_batal
    INNER JOIN dapur_order_detail ON dapur_batal.id_order_detail = dapur_order_detail.id_order_detail
    INNER JOIN pesanan ON dapur_batal.id_pesanan = pesanan.id_pesanan
    INNER JOIN pesanan_detail ON dapur_order_detail.id_pesanan_detail = pesanan_detail.id_pesanan_detail
    INNER JOIN produk_sell ON pesanan_detail.id_produk_sell = produk_sell.id_produk_sell
    INNER JOIN meja ON pesanan.id_meja = meja.id_meja
    INNER JOIN view_produk ON produk_sell.id_produk = view_produk.id_produk
    INNER JOIN pegawai ON dapur_batal.id_user = pegawai.id_user
    WHERE DATE(dapur_batal.waktu) BETWEEN ? AND ?
";

$count_stmt = $conn->prepare($count_sql);
$count_stmt->bind_param('ss', $tanggal_mulai, $tanggal_selesai);
$count_stmt->execute();
$count_result = $count_stmt->get_result();
$total_records = $count_result->fetch_assoc()['total'];
$total_pages = (int) ceil($total_records / $limit);

$sql = "
    SELECT
        MAX(dapur_batal.waktu) AS waktu,
        pesanan.id_tagihan,
        meja.nomor_meja,
        pegawai.nama_lengkap,
        SUM(produk_sell.harga_jual) AS total_harga_jual,
        SUM(dapur_batal.qty) AS total_item
    FROM dapur_batal
    INNER JOIN dapur_order_detail ON dapur_batal.id_order_detail = dapur_order_detail.id_order_detail
    INNER JOIN pesanan ON dapur_batal.id_pesanan = pesanan.id_pesanan
    INNER JOIN pesanan_detail ON dapur_order_detail.id_pesanan_detail = pesanan_detail.id_pesanan_detail
    INNER JOIN produk_sell ON pesanan_detail.id_produk_sell = produk_sell.id_produk_sell
    INNER JOIN meja ON pesanan.id_meja = meja.id_meja
    INNER JOIN view_produk ON produk_sell.id_produk = view_produk.id_produk
    INNER JOIN pegawai ON dapur_batal.id_user = pegawai.id_user
    WHERE DATE(dapur_batal.waktu) BETWEEN ? AND ?
    GROUP BY pesanan.id_tagihan, meja.nomor_meja, pegawai.nama_lengkap
    ORDER BY pesanan.id_tagihan DESC
    LIMIT ? OFFSET ?
";

$stmt = $conn->prepare($sql);
$stmt->bind_param('ssii', $tanggal_mulai, $tanggal_selesai, $limit, $offset);
$stmt->execute();
$result = $stmt->get_result();
$pembatalan_data = $result->fetch_all(MYSQLI_ASSOC);

$total_sql = "
    SELECT SUM(produk_sell.harga_jual * dapur_batal.qty) AS total_keseluruhan
    FROM dapur_batal
    INNER JOIN dapur_order_detail ON dapur_batal.id_order_detail = dapur_order_detail.id_order_detail
    INNER JOIN pesanan ON dapur_batal.id_pesanan = pesanan.id_pesanan
    INNER JOIN pesanan_detail ON dapur_order_detail.id_pesanan_detail = pesanan_detail.id_pesanan_detail
    INNER JOIN produk_sell ON pesanan_detail.id_produk_sell = produk_sell.id_produk_sell
    WHERE DATE(dapur_batal.waktu) BETWEEN ? AND ?
";

$total_stmt = $conn->prepare($total_sql);
$total_stmt->bind_param('ss', $tanggal_mulai, $tanggal_selesai);
$total_stmt->execute();
$total_result = $total_stmt->get_result();
$total_amount = $total_result->fetch_assoc()['total_keseluruhan'] ?? 0;

$start_record = $total_records > 0 ? $offset + 1 : 0;
$end_record = $total_records > 0 ? min($offset + count($pembatalan_data), $total_records) : 0;
?>

<!doctype html>
<html lang="id" data-bs-theme="light">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Pembatalan - Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
    <link href="../css/newadmin.css?v=3" rel="stylesheet">
</head>
<body>
    <?php include '_header_new.php'; ?>
    <?php include '_sidebar_new.php'; ?>

    <main class="main-content" id="mainContent">
        <div class="container-fluid">
            <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-3">
                <div>
                    <h2 class="mb-1"><i class="bi bi-x-circle me-2"></i>Pembatalan</h2>
                    <p class="text-muted mb-0">Pantau transaksi yang dibatalkan lengkap dengan rangkuman periode.</p>
                </div>
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

            <div class="card-modern mb-4">
                <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-3 px-4">
                    <div class="d-flex align-items-center">
                        <i class="bi bi-calendar-range me-2"></i>
                        <span>Filter Periode</span>
                    </div>
                </div>
                <div class="card-body px-4 pb-4">
                    <form method="GET" class="row g-3">
                        <div class="col-md-5">
                            <label for="tanggal_mulai" class="form-label">Tanggal Mulai</label>
                            <input type="date" class="form-control" id="tanggal_mulai" name="tanggal_mulai" value="<?php echo htmlspecialchars($tanggal_mulai); ?>" required>
                        </div>
                        <div class="col-md-5">
                            <label for="tanggal_selesai" class="form-label">Tanggal Selesai</label>
                            <input type="date" class="form-control" id="tanggal_selesai" name="tanggal_selesai" value="<?php echo htmlspecialchars($tanggal_selesai); ?>" required>
                        </div>
                        <div class="col-md-2 d-flex align-items-end">
                            <button type="submit" class="btn btn-primary w-100">
                                <i class="bi bi-search me-1"></i>Cari
                            </button>
                        </div>
                    </form>

                    <div class="bg-body-tertiary rounded-3 p-3 mt-4 d-flex flex-wrap align-items-center gap-3">
                        <div>
                            <small class="text-uppercase text-muted">Periode</small>
                            <div class="fw-semibold text-dark"><?php echo date('d F Y', strtotime($tanggal_mulai)); ?> - <?php echo date('d F Y', strtotime($tanggal_selesai)); ?></div>
                        </div>
                        <div class="vr d-none d-md-block"></div>
                        <div>
                            <small class="text-uppercase text-muted">Total Pembatalan</small>
                            <div class="fw-semibold text-dark"><?php echo number_format($total_records); ?> transaksi</div>
                        </div>
                        <div class="vr d-none d-md-block"></div>
                        <div>
                            <small class="text-uppercase text-muted">Nilai Kerugian</small>
                            <div class="fw-bold text-danger fs-5">Rp <?php echo number_format($total_amount, 0, ',', '.'); ?></div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card-modern">
                <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-3 px-4">
                    <div class="d-flex align-items-center">
                        <i class="bi bi-table me-2"></i>
                        <span>Daftar Pembatalan</span>
                    </div>
                </div>
                <div class="table-responsive px-0">
                    <table class="table table-hover mb-0">
                        <thead>
                            <tr>
                                <th class="text-start" style="width:5%">No</th>
                                <th style="width: 10%;">Waktu</th>
                                <th class="fw-semibold text-start" style="width: auto;">ID Tagihan</th>
                                <th class="text-center" style="width: 8%;">No. Meja</th>
                                <th class="text-start">Pegawai</th>
                                <th class="text-center" style="width: 10%;">Total Item</th>
                                <th class="text-end" style="width: 15%;">Total Harga</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($pembatalan_data)): ?>
                            <tr>
                                <td colspan="7" class="text-center py-5">
                                    <i class="bi bi-inbox fs-1 d-block mb-2 text-muted"></i>
                                    <span class="text-muted">Tidak ada data pembatalan untuk periode <?php echo date('d F Y', strtotime($tanggal_mulai)); ?> - <?php echo date('d F Y', strtotime($tanggal_selesai)); ?></span>
                                </td>
                            </tr>
                            <?php else: ?>
                                <?php $no = $start_record; ?>
                                <?php foreach ($pembatalan_data as $row): ?>
                                <tr>
                                    <td class="text-start fw-semibold"><?php echo $no++; ?></td>
                                    <td class="fw-medium text-nowrap"><?php echo date('d/m/Y H:i', strtotime($row['waktu'])); ?></td>
                                    <td class="text-start">  
                                        <button type="button" class="btn btn-link p-0 fw-semibold text-decoration-none" data-bs-toggle="modal" data-bs-target="#detailModal" onclick="showDetail('<?php echo htmlspecialchars($row['id_tagihan']); ?>', '<?php echo htmlspecialchars($tanggal_mulai); ?>', '<?php echo htmlspecialchars($tanggal_selesai); ?>')">
                                            <span class="badge bg-info-subtle text-info-emphasis border border-info-subtle px-3 py-2">#<?php echo htmlspecialchars($row['id_tagihan']); ?></span>
                                        </button>
                                    </td>
                                    <td class="text-center text-uppercase fw-semibold"><?php echo htmlspecialchars($row['nomor_meja']); ?></td>
                                    <td class="fw-semibold text-dark text-start"><?php echo htmlspecialchars($row['nama_lengkap']); ?></td>
                                    <td class="text-center">
                                        <span class="badge bg-secondary-subtle text-secondary-emphasis px-3 py-2"><?php echo (int) $row['total_item']; ?> item</span>
                                    </td>
                                    <td class="text-end fw-bold text-danger">Rp <?php echo number_format($row['total_harga_jual'], 0, ',', '.'); ?></td>
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
                                Menampilkan <?php echo number_format($start_record); ?> - <?php echo number_format($end_record); ?> dari <?php echo number_format($total_records); ?> pembatalan
                            <?php else: ?>
                                Tidak ada data pembatalan
                            <?php endif; ?>
                        </small>
                        <nav>
                            <ul class="pagination pagination-sm mb-0">
                                <?php if ($total_pages > 1): ?>
                                    <li class="page-item <?php echo $page <= 1 ? 'disabled' : ''; ?>">
                                        <a class="page-link" href="?page=<?php echo $page - 1; ?>&tanggal_mulai=<?php echo urlencode($tanggal_mulai); ?>&tanggal_selesai=<?php echo urlencode($tanggal_selesai); ?>">
                                            <i class="bi bi-chevron-left"></i>
                                        </a>
                                    </li>
                                    <?php for ($i = max(1, $page - 2); $i <= min($total_pages, $page + 2); $i++): ?>
                                        <li class="page-item <?php echo $i === $page ? 'active' : ''; ?>">
                                            <a class="page-link" href="?page=<?php echo $i; ?>&tanggal_mulai=<?php echo urlencode($tanggal_mulai); ?>&tanggal_selesai=<?php echo urlencode($tanggal_selesai); ?>"><?php echo $i; ?></a>
                                        </li>
                                    <?php endfor; ?>
                                    <li class="page-item <?php echo $page >= $total_pages ? 'disabled' : ''; ?>">
                                        <a class="page-link" href="?page=<?php echo $page + 1; ?>&tanggal_mulai=<?php echo urlencode($tanggal_mulai); ?>&tanggal_selesai=<?php echo urlencode($tanggal_selesai); ?>">
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
    </main>

    <div class="modal fade" id="detailModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Detail Pembatalan - <span id="modal-id-tagihan"></span></h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div id="loading-spinner" class="text-center py-5" style="display: none;">
                        <div class="spinner-border text-primary" role="status">
                            <span class="visually-hidden">Loading...</span>
                        </div>
                    </div>
                    <div id="detail-content"></div>
                </div>
            </div>
        </div>
    </div>

    <?php include '_scripts_new.php'; ?>
    <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
    <script>
        const startDateInput = document.getElementById('tanggal_mulai');
        const endDateInput = document.getElementById('tanggal_selesai');

        if (typeof flatpickr === 'function') {
            flatpickr('#tanggal_mulai', {
                dateFormat: 'Y-m-d',
                maxDate: 'today',
                locale: { firstDayOfWeek: 1 }
            });

            flatpickr('#tanggal_selesai', {
                dateFormat: 'Y-m-d',
                maxDate: 'today',
                locale: { firstDayOfWeek: 1 }
            });
        }

        if (startDateInput) {
            startDateInput.addEventListener('change', function () {
                if (endDateInput && endDateInput.value && this.value > endDateInput.value) {
                    alert('Tanggal mulaim tidak boleh lebih besar dari tanggal selesai!');
                    this.value = endDateInput.value;
                }
            });
        }

        if (endDateInput) {
            endDateInput.addEventListener('change', function () {
                if (startDateInput && startDateInput.value && this.value < startDateInput.value) {
                    alert('Tanggal selesai tidak boleh lebih kecil dari tanggal mulai!');
                    this.value = startDateInput.value;
                }
            });
        }

        function showDetail(idTagihan, tanggalMulai, tanggalSelesai) {
            document.getElementById('modal-id-tagihan').textContent = idTagihan;
            document.getElementById('loading-spinner').style.display = 'block';
            document.getElementById('detail-content').innerHTML = '';

            const url = 'get_pembatalan_detail.php?id_tagihan=' + encodeURIComponent(idTagihan) +
                '&tanggal_mulai=' + encodeURIComponent(tanggalMulai) +
                '&tanggal_selesai=' + encodeURIComponent(tanggalSelesai);

            const xhr = new XMLHttpRequest();
            xhr.open('GET', url, true);
            xhr.onload = function () {
                document.getElementById('loading-spinner').style.display = 'none';
                if (xhr.status === 200) {
                    document.getElementById('detail-content').innerHTML = xhr.responseText;
                } else {
                    document.getElementById('detail-content').innerHTML = '<div class="alert alert-danger">Error loading data</div>';
                }
            };
            xhr.onerror = function () {
                document.getElementById('loading-spinner').style.display = 'none';
                document.getElementById('detail-content').innerHTML = '<div class="alert alert-danger">Network error</div>';
            };
            xhr.send();
        }
    </script>
</body>
</html>
