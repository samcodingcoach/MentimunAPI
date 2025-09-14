kerjakan pada laporan_kuantitas.php
dibawah ini ada file yang koding sbg berikut

<?php
// Ambil tanggal dari parameter GET
$tgl = isset($_GET['tgl']) ? $_GET['tgl'] : date('Y-m-d');
include "../config/koneksi.php";

// Query 1: Ringkasan kuantitas (8 hari)
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

// Query 2: Pertumbuhan penjualan
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

mysqli_close($conn);
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Laporan Penjualan</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
       body {
            font-size: 16px;
        }
        table th, table td {
            vertical-align: middle !important;
            font-size: 15px;
        }
        .table thead th {
            background-color:rgb(196, 223, 255);
            text-align: center;
        }
    </style>
</head>
<body>
    <h3 class="text-center">RINGKASAN KUANTITAS</h3>
    <h5 class="text-center mb-4"><?php echo date("d F Y", strtotime($tgl)); ?></h5>
    <div class="table-responsive">
        <table class="table table-bordered table-sm text-center">
            <thead class="table-light">
                <tr>
                    <th rowspan="2">No</th>
                    <th rowspan="2">Nama Produk</th>
                    <?php for ($i = 7; $i >= 0; $i--): ?>
                        <th colspan="2"><?php echo date('d-m', strtotime("$tgl -$i day")); ?></th>
                    <?php endfor; ?>
                </tr>
                <tr>
                    <?php for ($i = 0; $i < 8; $i++): ?>
                        <th>A</th>
                        <th>B</th>
                    <?php endfor; ?>
                </tr>
            </thead>
            <tbody>
                <?php $no = 1; foreach ($data1 as $row): ?>
                    <tr>
                        <td><?php echo $no++; ?></td>
                        <td><?php echo $row['nama_produk']; ?></td>
                        <?php for ($i = 1; $i <= 8; $i++): ?>
                            <td><?php echo $row['stok_'.$i]; ?></td>
                            <td><?php echo $row['terjual_'.$i]; ?></td>
                        <?php endfor; ?>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <small><i>A = Stok Awal, B = Terjual</i></small>
    </div>

    <h4 class="text-center mt-5">PERTUMBUHAN PENJUALAN</h4>
    <div class="table-responsive">
        <table class="table table-bordered table-sm text-center">
            <thead class="table-light">
                <tr>
                    <th>No</th>
                    <th>Kode</th>
                    <th>Nama Produk</th>
                    <th>Total: <?php echo date("d M", strtotime("$tgl -7 day")); ?> – <?php echo date("d M", strtotime("$tgl -5 day")); ?></th>
                    <th>Total: <?php echo date("d M", strtotime("$tgl -4 day")); ?> – <?php echo date("d M", strtotime($tgl)); ?></th>
                    <th>Sell Growth</th>
                    <th>Tren</th>
                </tr>
            </thead>
            <tbody>
                <?php $no = 1; foreach ($data2 as $row): ?>
                    <tr>
                        <td><?php echo $no++; ?></td>
                        <td><?php echo $row['kode_produk']; ?></td>
                        <td class="text-start"><?php echo $row['nama_produk']; ?></td>
                        <td><?php echo $row['total_1_3']; ?></td>
                        <td><?php echo $row['total_4_7']; ?></td>
                        <td><?php echo $row['pertumbuhan_persen'] === null ? '-' : $row['pertumbuhan_persen'] . '%'; ?></td>
                        <td><?php echo $row['tren']; ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <div class="text-end"><small>Export time: <?php echo date('d F Y H:i'); ?></small></div>
    </div>

</body>
</html>

fokus hanya ke seluruh query saja. saya ingin di laporan_kuantitas.php dapat mencari output laporan seperti yang diatas terdapat tanggal start
tidak perlu sampai export hanya sampai menampilkan di .php
