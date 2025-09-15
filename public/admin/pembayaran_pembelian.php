<?php
session_start();
include '../../config/koneksi.php';

if(!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true){
    header("location: login.php");
    exit;
}

$search_result = [];
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $kode_request = $_POST['kode_request'];

    $sql = "SELECT
                br.kode_request,
                b.nama_bahan,
                v.kode_vendor,
                v.nama_vendor,
                v.nomor_rekening1,
                v.nomor_rekening2,
                brd.isDone,
                brd.isInvoice,
                brd.nomor_bukti_transaksi,
                brd.jumlah_request,
                brd.harga_est,
                brd.subtotal
            FROM bahan_request br
            INNER JOIN bahan_request_detail brd ON br.id_request = brd.id_request
            INNER JOIN bahan b ON brd.id_bahan = b.id_bahan
            INNER JOIN vendor v ON brd.id_vendor = v.id_vendor
            WHERE br.kode_request = ?";

    if($stmt = $conn->prepare($sql)){
        $stmt->bind_param("s", $kode_request);
        $stmt->execute();
        $result = $stmt->get_result();
        $search_result = $result->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
    }
}
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Pembayaran Pembelian</title>
    <link href="../css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css" rel="stylesheet">
    <link href="../css/admin.css" rel="stylesheet">
</head>
<body>
    <?php include '_header.php'; ?>

    <div class="container-fluid">
        <div class="row">
            <?php include '_sidebar.php'; ?>

            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="h2">Pembayaran Pembelian</h1>
                </div>

                <div class="card">
                    <div class="card-header">
                        Pencarian Kode Request / No PO
                    </div>
                    <div class="card-body">
                        <form action="pembayaran_pembelian.php" method="post">
                            <div class="mb-3">
                                <label for="kode_request" class="form-label">Kode Request / No PO</label>
                                <input type="text" class="form-control" id="kode_request" name="kode_request" required>
                            </div>
                            <button type="submit" class="btn btn-primary">Cari</button>
                        </form>
                    </div>
                </div>

                <?php if (!empty($search_result)): ?>
                <div class="card mt-4">
                    <div class="card-header">
                        Hasil Pencarian
                    </div>
                    <div class="card-body">
                        <table class="table table-striped">
                            <thead>
                                <tr>
                                    <th>No</th>
                                    <th>Kode Request</th>
                                    <th>Nama Bahan</th>
                                    <th>Vendor</th>
                                    <th>No. Rekening 1</th>
                                    <th>No. Rekening 2</th>
                                    <th>Status</th>
                                    <th>Tipe</th>
                                    <th>No. Bukti</th>
                                    <th>Jumlah</th>
                                    <th>Harga Est</th>
                                    <th>Subtotal</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($search_result as $index => $row): ?>
                                <tr>
                                    <td><?php echo $index + 1; ?></td>
                                    <td><?php echo htmlspecialchars($row['kode_request']); ?></td>
                                    <td><?php echo htmlspecialchars($row['nama_bahan']); ?></td>
                                    <td><?php echo htmlspecialchars($row['kode_vendor'] . ' - ' . $row['nama_vendor']); ?></td>
                                    <td><?php echo htmlspecialchars($row['nomor_rekening1']); ?></td>
                                    <td><?php echo htmlspecialchars($row['nomor_rekening2']); ?></td>
                                    <td><?php echo $row['isDone'] == 0 ? 'UNPAID' : 'PAID'; ?></td>
                                    <td><?php echo $row['isInvoice'] == 0 ? 'INV' : 'BAYAR LANGSUNG'; ?></td>
                                    <td><?php echo htmlspecialchars($row['nomor_bukti_transaksi']); ?></td>
                                    <td><?php echo htmlspecialchars($row['jumlah_request']); ?></td>
                                    <td><?php echo htmlspecialchars($row['harga_est']); ?></td>
                                    <td><?php echo htmlspecialchars($row['subtotal']); ?></td>
                                    <td>
                                        <button class="btn btn-success btn-sm">Bayar</button>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
                <?php elseif ($_SERVER["REQUEST_METHOD"] == "POST"): ?>
                <div class="alert alert-warning mt-4" role="alert">
                    Data tidak ditemukan.
                </div>
                <?php endif; ?>

            </main>
        </div>
    </div>

    <script src="../js/bootstrap.bundle.min.js"></script>
</body>
</html>
