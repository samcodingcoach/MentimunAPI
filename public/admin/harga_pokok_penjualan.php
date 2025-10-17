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

$nama_produk = isset($_GET['nama_produk']) ? trim($_GET['nama_produk']) : '';
$tanggal_mulai = isset($_GET['tanggal_mulai']) ? $_GET['tanggal_mulai'] : date('Y-m-01');
$tanggal_selesai = isset($_GET['tanggal_selesai']) ? $_GET['tanggal_selesai'] : date('Y-m-d');

$page = isset($_GET['page']) ? (int) $_GET['page'] : 1;
$limit = 20;

$conditions = [];
$types = '';
$params = [];

if ($nama_produk !== '') {
    $conditions[] = 'view_produk.nama_produk LIKE ?';
    $types .= 's';
    $params[] = '%' . $nama_produk . '%';
}

if (!empty($tanggal_mulai)) {
    $conditions[] = 'DATE(resep.tanggal_release) >= ?';
    $types .= 's';
    $params[] = $tanggal_mulai;
}

if (!empty($tanggal_selesai)) {
    $conditions[] = 'DATE(resep.tanggal_release) <= ?';
    $types .= 's';
    $params[] = $tanggal_selesai;
}

$whereClause = !empty($conditions) ? 'WHERE ' . implode(' AND ', $conditions) : '';

$baseQuery = '
    FROM view_produk
    INNER JOIN resep ON view_produk.id_produk = resep.id_produk
    INNER JOIN harga_menu ON resep.id_produk = harga_menu.id_produk
';

$count_sql = 'SELECT COUNT(*) AS total ' . $baseQuery . ' ' . $whereClause;
$count_stmt = $conn->prepare($count_sql);
if ($types !== '') {
    $count_stmt->bind_param($types, ...$params);
}
$count_stmt->execute();
$count_result = $count_stmt->get_result();
$total_records = (int) ($count_result->fetch_assoc()['total'] ?? 0);
$count_stmt->close();

$total_pages = max(1, (int) ceil($total_records / $limit));
if ($page < 1) {
    $page = 1;
}
if ($page > $total_pages) {
    $page = $total_pages;
}

$offset = ($page - 1) * $limit;

$data_sql = '
    SELECT
        view_produk.kode_produk,
        view_produk.nama_produk,
        view_produk.nama_kategori,
        resep.tanggal_release AS tanggal_rilis,
        resep.harga_pokok_resep,
        harga_menu.biaya_produksi,
        harga_menu.margin,
        harga_menu.nominal
    ' . $baseQuery . ' ' . $whereClause . '
    ORDER BY resep.tanggal_release DESC, view_produk.nama_produk ASC
    LIMIT ? OFFSET ?
';

$data_stmt = $conn->prepare($data_sql);
$params_with_pagination = $params;
$types_with_pagination = $types . 'ii';
$params_with_pagination[] = $limit;
$params_with_pagination[] = $offset;
$data_stmt->bind_param($types_with_pagination, ...$params_with_pagination);
$data_stmt->execute();
$data_result = $data_stmt->get_result();
$hpp_data = $data_result->fetch_all(MYSQLI_ASSOC);
$data_stmt->close();

$start_record = $total_records > 0 ? $offset + 1 : 0;
$end_record = $total_records > 0 ? min($offset + count($hpp_data), $total_records) : 0;
?>

<!doctype html>
<html lang="id" data-bs-theme="light">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Harga Pokok Penjualan - Admin</title>
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
                    <h2 class="mb-1"><i class="bi bi-journal-text me-2"></i>Harga Pokok Penjualan</h2>
                    <p class="text-muted mb-0">Monitoring rilis produk beserta margin dan status produksi.</p>
                </div>
            </div>

            <div class="card-modern mb-4">
                <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-3 px-4">
                    <div class="d-flex align-items-center">
                        <i class="bi bi-funnel me-2"></i>
                        <span>Filter Data</span>
                    </div>
                </div>
                <div class="card-body px-4 pb-4">
                    <form method="GET" class="row g-3">
                        <div class="col-md-4">
                            <label for="nama_produk" class="form-label">Nama Produk</label>
                            <input type="text" class="form-control" id="nama_produk" name="nama_produk" value="<?php echo htmlspecialchars($nama_produk); ?>" placeholder="Cari berdasarkan nama produk">
                        </div>
                        <div class="col-md-3">
                            <label for="tanggal_mulai" class="form-label">Tanggal Rilis Mulai</label>
                            <input type="date" class="form-control" id="tanggal_mulai" name="tanggal_mulai" value="<?php echo htmlspecialchars($tanggal_mulai); ?>" required>
                        </div>
                        <div class="col-md-3">
                            <label for="tanggal_selesai" class="form-label">Tanggal Rilis Selesai</label>
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
                    <small class="text-uppercase text-muted">Nama Produk</small>
                    <div class="fw-semibold text-dark"><?php echo $nama_produk !== '' ? htmlspecialchars($nama_produk) : 'Semua Produk'; ?></div>
                </div>
                <div class="vr d-none d-md-block"></div>
                <div>
                    <small class="text-uppercase text-muted">Periode Rilis</small>
                    <div class="fw-semibold text-dark"><?php echo date('d F Y', strtotime($tanggal_mulai)); ?> - <?php echo date('d F Y', strtotime($tanggal_selesai)); ?></div>
                </div>
                <div class="vr d-none d-md-block"></div>
                <div>
                    <small class="text-uppercase text-muted">Total Data</small>
                    <div class="fw-bold fs-5"><?php echo number_format($total_records); ?></div>
                </div>
            </div>
                </div>
            </div>

            <div class="card-modern">
                <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-3 px-4">
                    <div class="d-flex align-items-center">
                        <i class="bi bi-table me-2"></i>
                        <span>Daftar Harga Pokok Penjualan</span>
                    </div>
                </div>
                <div class="table-responsive px-0">
                    <table class="table table-hover mb-0">
                        <thead>
                            <tr>
                                <th class="text-start" style="width:5%">No</th>
                                <th style="width:10%">Kode</th>
                                <th style="width:20%">Nama Produk</th>
                                <th style="width:15%">Kategori</th>
                                <th style="width:12%">Tanggal Rilis</th>
                                <th style="width:10%">Resep</th>
                                <th style="width:10%">Produksi</th>
                                <th class="text-end" style="width:10%">Margin</th>
                                <th class="text-center" style="width:8%">Rilis</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($hpp_data)): ?>
                                <tr>
                                    <td colspan="9" class="text-center py-5">
                                        <i class="bi bi-inbox fs-1 d-block mb-2 text-muted"></i>
                                        <span class="text-muted">Belum ada data harga pokok penjualan untuk kriteria yang dipilih.</span>
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($hpp_data as $index => $row): ?>
                                    <tr>
                                        <td class="text-start fw-semibold"><?php echo $offset + $index + 1; ?></td>
                                        <td class="text-uppercase fw-semibold"><?php echo htmlspecialchars($row['kode_produk'] ?? '-'); ?></td>
                                        <td class="fw-semibold text-dark"><?php echo htmlspecialchars($row['nama_produk'] ?? '-'); ?></td>
                                        <td><?php echo htmlspecialchars($row['nama_kategori'] ?? '-'); ?></td>
                                        <td class="text-nowrap">
                                            <?php echo !empty($row['tanggal_rilis']) ? date('d/m/Y', strtotime($row['tanggal_rilis'])) : '-'; ?>
                                        </td>
                                        <td class="text-end fw-semibold">
                                            <?php echo isset($row['harga_pokok_resep']) ? number_format((float) $row['harga_pokok_resep'], 0, ',', '.') : '-'; ?>
                                        </td>
                                        <td class="text-end fw-semibold">
                                            <?php echo isset($row['biaya_produksi']) ? number_format((float) $row['biaya_produksi'], 0, ',', '.') : '-'; ?>
                                        </td>
                                        <td class="text-end fw-semibold">
                                            <?php echo isset($row['margin']) ? number_format((float) $row['margin'], 0, ',', '.') : '-'; ?>
                                        </td>
                                        <td class="text-center">
                                            <?php
                                                $isReleased = isset($row['nominal']) ? (float) $row['nominal'] > 0 : false;
                                                $badgeClass = $isReleased ? 'bg-success-subtle text-success-emphasis' : 'bg-secondary-subtle text-secondary-emphasis';
                                                $badgeText = $isReleased ? 'Ya' : 'Tidak';
                                            ?>
                                            <span class="badge <?php echo $badgeClass; ?> px-3 py-2"><?php echo $badgeText; ?></span>
                                        </td>
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
                                Menampilkan <?php echo number_format($start_record); ?> - <?php echo number_format($end_record); ?> dari <?php echo number_format($total_records); ?> data
                            <?php else: ?>
                                Tidak ada data harga pokok penjualan
                            <?php endif; ?>
                        </small>
                        <nav>
                            <ul class="pagination pagination-sm mb-0">
                                <?php if ($total_pages > 1): ?>
                                    <li class="page-item <?php echo $page <= 1 ? 'disabled' : ''; ?>">
                                        <a class="page-link" href="?page=<?php echo $page - 1; ?>&nama_produk=<?php echo urlencode($nama_produk); ?>&tanggal_mulai=<?php echo urlencode($tanggal_mulai); ?>&tanggal_selesai=<?php echo urlencode($tanggal_selesai); ?>">
                                            <i class="bi bi-chevron-left"></i>
                                        </a>
                                    </li>
                                    <?php for ($i = max(1, $page - 2); $i <= min($total_pages, $page + 2); $i++): ?>
                                        <li class="page-item <?php echo $i === $page ? 'active' : ''; ?>">
                                            <a class="page-link" href="?page=<?php echo $i; ?>&nama_produk=<?php echo urlencode($nama_produk); ?>&tanggal_mulai=<?php echo urlencode($tanggal_mulai); ?>&tanggal_selesai=<?php echo urlencode($tanggal_selesai); ?>"><?php echo $i; ?></a>
                                        </li>
                                    <?php endfor; ?>
                                    <li class="page-item <?php echo $page >= $total_pages ? 'disabled' : ''; ?>">
                                        <a class="page-link" href="?page=<?php echo $page + 1; ?>&nama_produk=<?php echo urlencode($nama_produk); ?>&tanggal_mulai=<?php echo urlencode($tanggal_mulai); ?>&tanggal_selesai=<?php echo urlencode($tanggal_selesai); ?>">
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

    <?php include '_scripts_new.php'; ?>
    <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
    <script>
        const startDateInput = document.getElementById('tanggal_mulai');
        const endDateInput = document.getElementById('tanggal_selesai');

        if (typeof flatpickr === 'function') {
            flatpickr('#tanggal_mulai', {
                dateFormat: 'Y-m-d',
                locale: { firstDayOfWeek: 1 }
            });

            flatpickr('#tanggal_selesai', {
                dateFormat: 'Y-m-d',
                locale: { firstDayOfWeek: 1 }
            });
        }

        if (startDateInput) {
            startDateInput.addEventListener('change', function () {
                if (endDateInput && endDateInput.value && this.value > endDateInput.value) {
                    alert('Tanggal mulai tidak boleh lebih besar dari tanggal selesai!');
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
    </script>
</body>
</html>
