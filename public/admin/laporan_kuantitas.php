<?php
session_start();
require_once __DIR__ . '/../../config/koneksi.php';

if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    header('Location: login.php');
    exit();
}

$tgl = isset($_GET['tgl']) ? $_GET['tgl'] : date('Y-m-d');

$sql1 = "SELECT
    view_produk.kode_produk,
    view_produk.nama_produk,
    COALESCE(SUM(CASE WHEN DATE(produk_sell.tgl_release) = '$tgl' - INTERVAL 7 DAY THEN produk_sell.stok_awal END), 0) AS stok_1,
    COALESCE(SUM(CASE WHEN DATE(produk_sell.tgl_release) = '$tgl' - INTERVAL 7 DAY THEN pesanan_detail.qty END), 0) AS terjual_1,
    COALESCE(SUM(CASE WHEN DATE(produk_sell.tgl_release) = '$tgl' - INTERVAL 6 DAY THEN produk_sell.stok_awal END), 0) AS stok_2,
    COALESCE(SUM(CASE WHEN DATE(produk_sell.tgl_release) = '$tgl' - INTERVAL 6 DAY THEN pesanan_detail.qty END), 0) AS terjual_2,
    COALESCE(SUM(CASE WHEN DATE(produk_sell.tgl_release) = '$tgl' - INTERVAL 5 DAY THEN produk_sell.stok_awal END), 0) AS stok_3,
    COALESCE(SUM(CASE WHEN DATE(produk_sell.tgl_release) = '$tgl' - INTERVAL 5 DAY THEN pesanan_detail.qty END), 0) AS terjual_3,
    COALESCE(SUM(CASE WHEN DATE(produk_sell.tgl_release) = '$tgl' - INTERVAL 4 DAY THEN produk_sell.stok_awal END), 0) AS stok_4,
    COALESCE(SUM(CASE WHEN DATE(produk_sell.tgl_release) = '$tgl' - INTERVAL 4 DAY THEN pesanan_detail.qty END), 0) AS terjual_4,
    COALESCE(SUM(CASE WHEN DATE(produk_sell.tgl_release) = '$tgl' - INTERVAL 3 DAY THEN produk_sell.stok_awal END), 0) AS stok_5,
    COALESCE(SUM(CASE WHEN DATE(produk_sell.tgl_release) = '$tgl' - INTERVAL 3 DAY THEN pesanan_detail.qty END), 0) AS terjual_5,
    COALESCE(SUM(CASE WHEN DATE(produk_sell.tgl_release) = '$tgl' - INTERVAL 2 DAY THEN produk_sell.stok_awal END), 0) AS stok_6,
    COALESCE(SUM(CASE WHEN DATE(produk_sell.tgl_release) = '$tgl' - INTERVAL 2 DAY THEN pesanan_detail.qty END), 0) AS terjual_6,
    COALESCE(SUM(CASE WHEN DATE(produk_sell.tgl_release) = '$tgl' - INTERVAL 1 DAY THEN produk_sell.stok_awal END), 0) AS stok_7,
    COALESCE(SUM(CASE WHEN DATE(produk_sell.tgl_release) = '$tgl' - INTERVAL 1 DAY THEN pesanan_detail.qty END), 0) AS terjual_7,
    COALESCE(SUM(CASE WHEN DATE(produk_sell.tgl_release) = '$tgl' THEN produk_sell.stok_awal END), 0) AS stok_8,
    COALESCE(SUM(CASE WHEN DATE(produk_sell.tgl_release) = '$tgl' THEN pesanan_detail.qty END), 0) AS terjual_8
FROM
    view_produk
LEFT JOIN produk_sell ON produk_sell.id_produk = view_produk.id_produk
LEFT JOIN pesanan_detail ON pesanan_detail.id_produk_sell = produk_sell.id_produk_sell
WHERE
    DATE(produk_sell.tgl_release) BETWEEN '$tgl' - INTERVAL 7 DAY AND '$tgl'
GROUP BY
    view_produk.kode_produk, view_produk.nama_produk
ORDER BY
    view_produk.nama_produk";

$result1 = mysqli_query($conn, $sql1);
$data1 = mysqli_fetch_all($result1, MYSQLI_ASSOC);

$sql2 = "SELECT
    view_produk.kode_produk,
    view_produk.nama_produk,
    COALESCE(SUM(CASE WHEN DATE(produk_sell.tgl_release) BETWEEN '$tgl' - INTERVAL 7 DAY AND '$tgl' - INTERVAL 5 DAY THEN pesanan_detail.qty END), 0) AS total_1_3,
    COALESCE(SUM(CASE WHEN DATE(produk_sell.tgl_release) BETWEEN '$tgl' - INTERVAL 4 DAY AND '$tgl' THEN pesanan_detail.qty END), 0) AS total_4_7,
    ROUND(CASE 
        WHEN COALESCE(SUM(CASE WHEN DATE(produk_sell.tgl_release) BETWEEN '$tgl' - INTERVAL 7 DAY AND '$tgl' - INTERVAL 5 DAY THEN pesanan_detail.qty END), 0) = 0 THEN NULL
        ELSE (
            (COALESCE(SUM(CASE WHEN DATE(produk_sell.tgl_release) BETWEEN '$tgl' - INTERVAL 4 DAY AND '$tgl' THEN pesanan_detail.qty END), 0) -
            COALESCE(SUM(CASE WHEN DATE(produk_sell.tgl_release) BETWEEN '$tgl' - INTERVAL 7 DAY AND '$tgl' - INTERVAL 5 DAY THEN pesanan_detail.qty END), 0)) * 100.0
        ) / NULLIF(COALESCE(SUM(CASE WHEN DATE(produk_sell.tgl_release) BETWEEN '$tgl' - INTERVAL 7 DAY AND '$tgl' - INTERVAL 5 DAY THEN pesanan_detail.qty END), 0), 0)
    END, 2) AS pertumbuhan_persen,
    CASE
        WHEN SUM(CASE WHEN DATE(produk_sell.tgl_release) BETWEEN '$tgl' - INTERVAL 4 DAY AND '$tgl' THEN pesanan_detail.qty END) >
             SUM(CASE WHEN DATE(produk_sell.tgl_release) BETWEEN '$tgl' - INTERVAL 7 DAY AND '$tgl' - INTERVAL 5 DAY THEN pesanan_detail.qty END)
        THEN 'Naik'
        WHEN SUM(CASE WHEN DATE(produk_sell.tgl_release) BETWEEN '$tgl' - INTERVAL 4 DAY AND '$tgl' THEN pesanan_detail.qty END) <
             SUM(CASE WHEN DATE(produk_sell.tgl_release) BETWEEN '$tgl' - INTERVAL 7 DAY AND '$tgl' - INTERVAL 5 DAY THEN pesanan_detail.qty END)
        THEN 'Turun'
        ELSE 'Stabil'
    END AS tren
FROM
    view_produk
LEFT JOIN produk_sell ON produk_sell.id_produk = view_produk.id_produk
LEFT JOIN pesanan_detail ON pesanan_detail.id_produk_sell = produk_sell.id_produk_sell
WHERE
    produk_sell.tgl_release BETWEEN '$tgl' - INTERVAL 7 DAY AND '$tgl'
GROUP BY
    view_produk.kode_produk, view_produk.nama_produk
ORDER BY
    view_produk.nama_produk";

$result2 = mysqli_query($conn, $sql2);
$data2 = mysqli_fetch_all($result2, MYSQLI_ASSOC);
?>

<!doctype html>
<html lang="id" data-bs-theme="light">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Laporan Kuantitas - Admin</title>
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
                    <h2 class="mb-1"><i class="bi bi-graph-up-arrow me-2"></i>Laporan Kuantitas</h2>
                    <p class="text-muted mb-0">Analisis stok dan tren penjualan per produk</p>
                </div>
                <div>
                    <span class="badge bg-primary-subtle text-primary px-3 py-2">
                        <i class="bi bi-calendar-event me-1"></i><?php echo date('d F Y', strtotime($tgl)); ?>
                    </span>
                </div>
            </div>

            <div class="card-modern mb-4">
                <div class="card-header px-4 py-3">
                    <div class="d-flex align-items-center">
                        <i class="bi bi-funnel me-2"></i>
                        <span>Filter Data</span>
                    </div>
                </div>
                <div class="card-body">
                    <form method="GET" class="row g-3 align-items-end">
                        <div class="col-md-4">
                            <label for="tgl" class="form-label">Pilih Tanggal</label>
                            <input type="text" class="form-control" id="tgl" name="tgl" value="<?php echo htmlspecialchars($tgl); ?>" required>
                        </div>
                        <div class="col-md-4">
                            <button type="submit" class="btn btn-primary">
                                <i class="bi bi-search me-2"></i>Tampilkan
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <div class="card-modern mb-4">
                <div class="card-header px-4 py-3">
                    <div class="d-flex flex-wrap justify-content-between align-items-center gap-3">
                        <div class="d-flex align-items-center">
                            <i class="bi bi-stack me-2"></i>
                            <span>Ringkasan Kuantitas (8 Hari)</span>
                        </div>
                        <small class="text-muted">A = Stok Awal, B = Terjual</small>
                    </div>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover table-sm text-center mb-0">
                            <thead class="table-light align-middle">
                                <tr>
                                    <th rowspan="2" class="text-center" style="width: 4%;">No</th>
                                    <th rowspan="2" class="text-start" style="min-width: 180px;">Nama Produk</th>
                                    <?php for ($i = 7; $i >= 0; $i--): ?>
                                        <th colspan="2"><?php echo date('d M', strtotime("$tgl -$i day")); ?></th>
                                    <?php endfor; ?>
                                </tr>
                                <tr>
                                    <?php for ($i = 0; $i < 8; $i++): ?>
                                        <th class="bg-body-secondary">A</th>
                                        <th class="bg-body-secondary">B</th>
                                    <?php endfor; ?>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($data1)): ?>
                                <tr>
                                    <td colspan="18" class="py-5">
                                        <i class="bi bi-inbox fs-1 d-block mb-2 text-muted"></i>
                                        <span class="text-muted">Tidak ada data untuk periode ini</span>
                                    </td>
                                </tr>
                                <?php else: ?>
                                    <?php $no = 1; foreach ($data1 as $row): ?>
                                    <tr>
                                        <td class="fw-semibold"><?php echo $no++; ?></td>
                                        <td class="text-start fw-semibold text-nowrap"><?php echo htmlspecialchars($row['nama_produk']); ?></td>
                                        <?php for ($i = 1; $i <= 8; $i++): ?>
                                            <td><?php echo $row['stok_'.$i]; ?></td>
                                            <td class="fw-semibold text-primary"><?php echo $row['terjual_'.$i]; ?></td>
                                        <?php endfor; ?>
                                    </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
                <div class="card-footer bg-light border-top py-3 px-4">
                    <small class="text-muted">
                        <?php if (!empty($data1)): ?>
                            Menampilkan <?php echo count($data1); ?> produk
                        <?php else: ?>
                            Tidak ada data ringkasan
                        <?php endif; ?>
                    </small>
                </div>
            </div>

            <div class="card-modern">
                <div class="card-header px-4 py-3">
                    <div class="d-flex flex-wrap justify-content-between align-items-center gap-3">
                        <div class="d-flex align-items-center">
                            <i class="bi bi-activity me-2"></i>
                            <span>Pertumbuhan Penjualan</span>
                        </div>
                        <small class="text-muted">
                            <?php echo date("d M", strtotime("$tgl -7 day")); ?> – <?php echo date("d M", strtotime($tgl)); ?>
                        </small>
                    </div>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover table-sm text-center mb-0">
                            <thead class="table-light align-middle">
                                <tr>
                                    <th style="width: 4%;">No</th>
                                    <th style="width: 10%;">Kode</th>
                                    <th class="text-start" style="min-width: 180px;">Nama Produk</th>
                                    <th>Total <?php echo date("d M", strtotime("$tgl -7 day")); ?> – <?php echo date("d M", strtotime("$tgl -5 day")); ?></th>
                                    <th>Total <?php echo date("d M", strtotime("$tgl -4 day")); ?> – <?php echo date("d M", strtotime($tgl)); ?></th>
                                    <th>Sell Growth</th>
                                    <th>Tren</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($data2)): ?>
                                <tr>
                                    <td colspan="7" class="py-5">
                                        <i class="bi bi-inbox fs-1 d-block mb-2 text-muted"></i>
                                        <span class="text-muted">Tidak ada data untuk periode ini</span>
                                    </td>
                                </tr>
                                <?php else: ?>
                                    <?php $no = 1; foreach ($data2 as $row): ?>
                                    <tr>
                                        <td class="fw-semibold"><?php echo $no++; ?></td>
                                        <td><?php echo htmlspecialchars($row['kode_produk']); ?></td>
                                        <td class="text-start fw-semibold text-nowrap"><?php echo htmlspecialchars($row['nama_produk']); ?></td>
                                        <td><?php echo $row['total_1_3']; ?></td>
                                        <td><?php echo $row['total_4_7']; ?></td>
                                        <td>
                                            <?php
                                            if ($row['pertumbuhan_persen'] === null) {
                                                echo '<span class="text-muted">-</span>';
                                            } else {
                                                $persen = $row['pertumbuhan_persen'];
                                                $color = $persen > 0 ? 'text-success' : ($persen < 0 ? 'text-danger' : 'text-muted');
                                                echo '<span class="' . $color . '">' . $persen . '%</span>';
                                            }
                                            ?>
                                        </td>
                                        <td>
                                            <?php
                                            $tren = $row['tren'];
                                            $class = $tren === 'Naik' ? 'bg-success-subtle text-success' : ($tren === 'Turun' ? 'bg-danger-subtle text-danger' : 'bg-secondary-subtle text-secondary');
                                            ?>
                                            <span class="badge <?php echo $class; ?> px-3 py-2"><?php echo $tren; ?></span>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
                <div class="card-footer bg-light border-top py-3 px-4 d-flex justify-content-between align-items-center">
                    <small class="text-muted">
                        <?php if (!empty($data2)): ?>
                            Menampilkan <?php echo count($data2); ?> produk
                        <?php else: ?>
                            Tidak ada data pertumbuhan
                        <?php endif; ?>
                    </small>
                    <small class="text-muted">
                        <i class="bi bi-clock-history me-1"></i>Export time: <?php echo date('d F Y H:i'); ?>
                    </small>
                </div>
            </div>
        </div>
    </main>

    <?php include '_scripts_new.php'; ?>
    <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
    <script>
        flatpickr('#tgl', {
            dateFormat: 'Y-m-d',
            maxDate: 'today',
            defaultDate: '<?php echo $tgl; ?>',
            locale: {
                firstDayOfWeek: 1
            }
        });
    </script>
</body>
</html>