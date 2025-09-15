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
                                    <th>Nama Bahan</th>
                                    <th>Vendor</th>
                                    <th>Status</th>
                                    <th>Tipe</th>
                                    <th>Subtotal</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($search_result as $index => $row): ?>
                                <tr>
                                    <td><?php echo $index + 1; ?></td>
                                    <td><?php echo htmlspecialchars($row['nama_bahan']); ?></td>
                                    <td><?php echo htmlspecialchars($row['nama_vendor']); ?></td>
                                    <td><?php echo $row['isDone'] == 0 ? 'UNPAID' : 'PAID'; ?></td>
                                    <td><?php echo $row['isInvoice'] == 0 ? 'INV' : 'BAYAR LANGSUNG'; ?></td>
                                    <td><?php echo htmlspecialchars($row['subtotal']); ?></td>
                                    <td>
                                        <button type="button" class="btn btn-success btn-sm" data-bs-toggle="modal" data-bs-target="#bayarModal"
                                            data-nama-bahan="<?php echo htmlspecialchars($row['nama_bahan']); ?>"
                                            data-vendor="<?php echo htmlspecialchars($row['nama_vendor']); ?>"
                                            data-status="<?php echo $row['isDone'] == 0 ? 'UNPAID' : 'PAID'; ?>"
                                            data-tipe="<?php echo $row['isInvoice'] == 0 ? 'INV' : 'BAYAR LANGSUNG'; ?>"
                                            data-rekening1="<?php echo htmlspecialchars($row['nomor_rekening1']); ?>"
                                            data-rekening2="<?php echo htmlspecialchars($row['nomor_rekening2']); ?>"
                                            data-jumlah="<?php echo htmlspecialchars($row['jumlah_request']); ?>"
                                            data-harga="<?php echo htmlspecialchars($row['harga_est']); ?>"
                                            data-subtotal="<?php echo htmlspecialchars($row['subtotal']); ?>">
                                            Bayar
                                        </button>
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

                <!-- Modal -->
                <div class="modal fade" id="bayarModal" tabindex="-1" aria-labelledby="bayarModalLabel" aria-hidden="true">
                    <div class="modal-dialog modal-lg">
                        <div class="modal-content">
                            <div class="modal-header">
                                <h5 class="modal-title" id="bayarModalLabel">Form Pembayaran</h5>
                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                            </div>
                            <div class="modal-body">
                                <form>
                                    <div class="row">
                                        <div class="col-md-6">
                                            <h5>Informasi</h5>
                                            <div class="mb-3">
                                                <label for="modal-nama-bahan" class="form-label">Nama Bahan</label>
                                                <input type="text" class="form-control" id="modal-nama-bahan" readonly>
                                            </div>
                                            <div class="mb-3">
                                                <label for="modal-vendor" class="form-label">Vendor</label>
                                                <input type="text" class="form-control" id="modal-vendor" readonly>
                                            </div>
                                            <div class="mb-3">
                                                <label for="modal-status" class="form-label">Status</label>
                                                <input type="text" class="form-control" id="modal-status" readonly>
                                            </div>
                                            <div class="mb-3">
                                                <label for="modal-tipe" class="form-label">Tipe</label>
                                                <input type="text" class="form-control" id="modal-tipe" readonly>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <h5>Rekening</h5>
                                            <div class="mb-3">
                                                <label for="modal-rekening1" class="form-label">Nomor Rek 1</label>
                                                <input type="text" class="form-control" id="modal-rekening1" readonly>
                                            </div>
                                            <div class="mb-3">
                                                <label for="modal-rekening2" class="form-label">Nomor Rekening 2</label>
                                                <input type="text" class="form-control" id="modal-rekening2" readonly>
                                            </div>
                                        </div>
                                    </div>
                                    <hr>
                                    <div class="row">
                                        <div class="col-md-12">
                                            <h5>Nominal</h5>
                                            <div class="row">
                                                <div class="col-md-4">
                                                    <div class="mb-3">
                                                        <label for="modal-jumlah" class="form-label">Jumlah</label>
                                                        <input type="text" class="form-control" id="modal-jumlah" readonly>
                                                    </div>
                                                </div>
                                                <div class="col-md-4">
                                                    <div class="mb-3">
                                                        <label for="modal-harga" class="form-label">Harga</label>
                                                        <input type="text" class="form-control" id="modal-harga" readonly>
                                                    </div>
                                                </div>
                                                <div class="col-md-4">
                                                    <div class="mb-3">
                                                        <label for="modal-subtotal" class="form-label">Subtotal</label>
                                                        <input type="text" class="form-control" id="modal-subtotal" readonly>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    <hr>
                                    <div class="row">
                                        <div class="col-md-12">
                                            <h5>Bayar</h5>
                                            <div class="mb-3">
                                                <label for="modal-nomor-bukti" class="form-label">Input Nomor Bukti Transaksi</label>
                                                <input type="text" class="form-control" id="modal-nomor-bukti">
                                            </div>
                                            <div class="mb-3">
                                                <label for="modal-bukti-transaksi" class="form-label">Input Bukti Transaksi</label>
                                                <input type="file" class="form-control" id="modal-bukti-transaksi">
                                            </div>
                                        </div>
                                    </div>
                                </form>
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                                <button type="button" class="btn btn-primary">Save changes</button>
                            </div>
                        </div>
                    </div>
                </div>

            </main>
        </div>
    </div>

    <script src="../js/bootstrap.bundle.min.js"></script>
    <script>
        var bayarModal = document.getElementById('bayarModal');
        bayarModal.addEventListener('show.bs.modal', function (event) {
            var button = event.relatedTarget;
            var namaBahan = button.getAttribute('data-nama-bahan');
            var vendor = button.getAttribute('data-vendor');
            var status = button.getAttribute('data-status');
            var tipe = button.getAttribute('data-tipe');
            var rekening1 = button.getAttribute('data-rekening1');
            var rekening2 = button.getAttribute('data-rekening2');
            var jumlah = button.getAttribute('data-jumlah');
            var harga = button.getAttribute('data-harga');
            var subtotal = button.getAttribute('data-subtotal');

            var modalNamaBahan = bayarModal.querySelector('#modal-nama-bahan');
            var modalVendor = bayarModal.querySelector('#modal-vendor');
            var modalStatus = bayarModal.querySelector('#modal-status');
            var modalTipe = bayarModal.querySelector('#modal-tipe');
            var modalRekening1 = bayarModal.querySelector('#modal-rekening1');
            var modalRekening2 = bayarModal.querySelector('#modal-rekening2');
            var modalJumlah = bayarModal.querySelector('#modal-jumlah');
            var modalHarga = bayarModal.querySelector('#modal-harga');
            var modalSubtotal = bayarModal.querySelector('#modal-subtotal');

            modalNamaBahan.value = namaBahan;
            modalVendor.value = vendor;
            modalStatus.value = status;
            modalTipe.value = tipe;
            modalRekening1.value = rekening1;
            modalRekening2.value = rekening2;
            modalJumlah.value = jumlah;
            modalHarga.value = harga;
            modalSubtotal.value = subtotal;
        });
    </script>
</body>
</html>
