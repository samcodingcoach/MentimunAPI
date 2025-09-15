<?php
session_start();
include '../../config/koneksi.php';

if(!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true){
    header("location: login.php");
    exit;
}

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['update_pembayaran'])) {
    $id_request = $_POST['id_request'];
    $id_vendor = $_POST['id_vendor'];
    $nomor_bukti_transaksi = $_POST['nomor_bukti_transaksi'];
    $kode_request = $_POST['kode_request'];
    
    $file_bukti_name = '';
    $upload_error = '';

    if(isset($_FILES['file_bukti']) && $_FILES['file_bukti']['error'] == 0){
        $target_dir = "../images/bukti_tf/";
        if (!file_exists($target_dir)) {
            mkdir($target_dir, 0777, true);
        }

        $file = $_FILES['file_bukti'];
        $file_size = $file['size'];
        $file_ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

        if ($file_size > 1024 * 1024) {
            $upload_error = "Ukuran file tidak boleh lebih dari 1 MB.";
        }

        if ($file_ext !== 'jpg') {
            $upload_error = "File harus berupa JPG.";
        }

        if (empty($upload_error)) {
            $file_bukti_name = date('YmdHis') . '_' . uniqid() . '.jpg';
            $target_file = $target_dir . $file_bukti_name;
            if (!move_uploaded_file($file["tmp_name"], $target_file)) {
                $upload_error = "Gagal mengunggah file.";
            }
        }
    }

    if (empty($upload_error)) {
        $sql_update = "UPDATE bahan_request_detail SET nomor_bukti_transaksi = ?, file_bukti = ?, isDone=1 WHERE id_request = ? and id_vendor = ?";
        if($stmt_update = $conn->prepare($sql_update)){
            $stmt_update->bind_param("ssii", $nomor_bukti_transaksi, $file_bukti_name, $id_request, $id_vendor);
            if ($stmt_update->execute()) {
                $_SESSION['success_message'] = "Pembayaran berhasil disimpan.";
            }
            $stmt_update->close();
        }
        header("location: pembayaran_pembelian.php?kode_request=" . urlencode($kode_request));
        exit;
    } else {
        $_SESSION['error_message'] = $upload_error;
        header("location: pembayaran_pembelian.php?kode_request=" . urlencode($kode_request));
        exit;
    }
}

$search_result = [];
$kode_request_value = '';
if (($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['kode_request'])) || isset($_GET['kode_request'])) {
    $kode_request = isset($_POST['kode_request']) ? $_POST['kode_request'] : $_GET['kode_request'];
    $kode_request_value = $kode_request;

    $sql = "SELECT
                br.id_request,
                br.kode_request,
                b.nama_bahan,
                v.id_vendor,
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

                <?php
                if (isset($_SESSION['success_message'])) {
                    echo '<div class="alert alert-success">' . $_SESSION['success_message'] . '</div>';
                    unset($_SESSION['success_message']);
                }
                if (isset($_SESSION['error_message'])) {
                    echo '<div class="alert alert-danger">' . $_SESSION['error_message'] . '</div>';
                    unset($_SESSION['error_message']);
                }
                ?>

                <div>
                   
                        <form action="pembayaran_pembelian.php" method="post">
                            <div class="mb-3">
                                <label for="kode_request" class="form-label">Kode Request / No PO</label>
                                <div class="d-flex">
                                    <input type="text" class="form-control" id="kode_request" name="kode_request" value="<?php echo htmlspecialchars($kode_request_value); ?>" required>
                                    <button type="submit" class="btn btn-primary ms-2">Cari</button>
                                </div>
                            </div>
                        </form>
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
                                    <td><?php echo htmlspecialchars(number_format($row['subtotal'], 0, ',', '.')); ?></td>
                                    <td>
                                        <?php if($row['isDone'] == 0): ?>
                                            <button type="button" class="btn btn-success btn-sm" data-bs-toggle="modal" data-bs-target="#bayarModal"
                                                data-id-request="<?php echo $row['id_request']; ?>"
                                                data-id-vendor="<?php echo $row['id_vendor']; ?>"
                                                data-kode-request="<?php echo htmlspecialchars($row['kode_request']); ?>"
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
                                        <?php else: ?>
                                            <button type="button" class="btn btn-secondary btn-sm" data-bs-toggle="modal" data-bs-target="#bayarModal"
                                                data-id-request="<?php echo $row['id_request']; ?>"
                                                data-id-vendor="<?php echo $row['id_vendor']; ?>"
                                                data-kode-request="<?php echo htmlspecialchars($row['kode_request']); ?>"
                                                data-nama-bahan="<?php echo htmlspecialchars($row['nama_bahan']); ?>"
                                                data-vendor="<?php echo htmlspecialchars($row['nama_vendor']); ?>"
                                                data-status="<?php echo $row['isDone'] == 0 ? 'UNPAID' : 'PAID'; ?>"
                                                data-tipe="<?php echo $row['isInvoice'] == 0 ? 'INV' : 'BAYAR LANGSUNG'; ?>"
                                                data-rekening1="<?php echo htmlspecialchars($row['nomor_rekening1']); ?>"
                                                data-rekening2="<?php echo htmlspecialchars($row['nomor_rekening2']); ?>"
                                                data-jumlah="<?php echo htmlspecialchars($row['jumlah_request']); ?>"
                                                data-harga="<?php echo htmlspecialchars($row['harga_est']); ?>"
                                                data-subtotal="<?php echo htmlspecialchars($row['subtotal']); ?>">
                                                Terbayar
                                            </button>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
                <?php elseif (($_SERVER["REQUEST_METHOD"] == "POST" || isset($_GET['kode_request'])) && empty($search_result)): ?>
                <div class="alert alert-warning mt-4" role="alert">
                    Data tidak ditemukan.
                </div>
                <?php endif; ?>

                <!-- Modal -->
                <div class="modal fade" id="bayarModal" tabindex="-1" aria-labelledby="bayarModalLabel" aria-hidden="true">
                    <div class="modal-dialog">
                        <div class="modal-content">
                            <form id="form-pembayaran" action="pembayaran_pembelian.php" method="post" enctype="multipart/form-data">
                                <div class="modal-header">
                                    <h5 class="modal-title" id="bayarModalLabel">Form Pembayaran</h5>
                                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                </div>
                                <div class="modal-body">
                                    <input type="hidden" name="id_request" id="modal-id-request">
                                    <input type="hidden" name="id_vendor" id="modal-id-vendor">
                                    <input type="hidden" name="kode_request" id="modal-kode-request">
                                    <ul class="nav nav-tabs" id="myTab" role="tablist">
                                        <li class="nav-item" role="presentation">
                                            <button class="nav-link active" id="informasi-tab" data-bs-toggle="tab" data-bs-target="#informasi" type="button" role="tab" aria-controls="informasi" aria-selected="true">Informasi</button>
                                        </li>
                                        <li class="nav-item" role="presentation">
                                            <button class="nav-link" id="rekening-tab" data-bs-toggle="tab" data-bs-target="#rekening" type="button" role="tab" aria-controls="rekening" aria-selected="false">Rekening</button>
                                        </li>
                                    </ul>
                                    <div class="tab-content" id="myTabContent">
                                        <div class="tab-pane fade show active p-2" id="informasi" role="tabpanel" aria-labelledby="informasi-tab">
                                            <div class="mb-2">
                                                <label for="modal-nama-bahan" class="form-label">Nama Bahan</label>
                                                <input type="text" class="form-control form-control-sm" id="modal-nama-bahan" readonly>
                                            </div>
                                            <div class="mb-2">
                                                <label for="modal-vendor" class="form-label">Vendor</label>
                                                <input type="text" class="form-control form-control-sm" id="modal-vendor" readonly>
                                            </div>
                                            <div class="mb-2">
                                                <label for="modal-status" class="form-label">Status</label>
                                                <input type="text" class="form-control form-control-sm" id="modal-status" readonly>
                                            </div>
                                            <div class="mb-2">
                                                <label for="modal-tipe" class="form-label">Tipe</label>
                                                <input type="text" class="form-control form-control-sm" id="modal-tipe" readonly>
                                            </div>
                                        </div>
                                        <div class="tab-pane fade p-2" id="rekening" role="tabpanel" aria-labelledby="rekening-tab">
                                            <div class="mb-2">
                                                <label for="modal-rekening1" class="form-label">Nomor Rek 1</label>
                                                <input type="text" class="form-control form-control-sm" id="modal-rekening1" readonly>
                                            </div>
                                            <div class="mb-2">
                                                <label for="modal-rekening2" class="form-label">Nomor Rekening 2</label>
                                                <input type="text" class="form-control form-control-sm" id="modal-rekening2" readonly>
                                            </div>
                                        </div>
                                    </div>
                                    <hr class="my-2">
                                    <div class="row">
                                        <div class="col-md-12">
                                            <h5 class="mb-2">Nominal</h5>
                                            <div class="row">
                                                <div class="col-md-4">
                                                    <div class="mb-2">
                                                        <label for="modal-jumlah" class="form-label">Jumlah</label>
                                                        <input type="text" class="form-control form-control-sm" id="modal-jumlah" readonly>
                                                    </div>
                                                </div>
                                                <div class="col-md-4">
                                                    <div class="mb-2">
                                                        <label for="modal-harga" class="form-label">Harga</label>
                                                        <input type="text" class="form-control form-control-sm" id="modal-harga" readonly>
                                                    </div>
                                                </div>
                                                <div class="col-md-4">
                                                    <div class="mb-2">
                                                        <label for="modal-subtotal" class="form-label">Subtotal</label>
                                                        <input type="text" class="form-control form-control-sm" id="modal-subtotal" readonly>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    <hr class="my-2">
                                    <div class="row">
                                        <div class="col-md-12">
                                            <h5 class="mb-2">Bayar</h5>
                                            <div class="mb-2">
                                                <label for="modal-nomor-bukti" class="form-label">Input Nomor Bukti Transaksi</label>
                                                <input type="text" class="form-control form-control-sm" id="modal-nomor-bukti" name="nomor_bukti_transaksi">
                                            </div>
                                            <div class="mb-2">
                                                <label for="modal-bukti-transaksi" class="form-label">Input Bukti Transaksi</label>
                                                <input type="file" class="form-control form-control-sm" id="modal-bukti-transaksi" name="file_bukti" accept=".jpg">
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div class="modal-footer">
                                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                                    <button type="submit" name="update_pembayaran" class="btn btn-primary">Save changes</button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>

            </main>
        </div>
    </div>

    <script src="../js/bootstrap.bundle.min.js"></script>
    <script>
        function formatNumber(number) {
            return new Intl.NumberFormat('id-ID').format(number);
        }

        var bayarModal = document.getElementById('bayarModal');
        bayarModal.addEventListener('show.bs.modal', function (event) {
            var button = event.relatedTarget;
            var id_request = button.getAttribute('data-id-request');
            var id_vendor = button.getAttribute('data-id-vendor');
            var kode_request = button.getAttribute('data-kode-request');
            var namaBahan = button.getAttribute('data-nama-bahan');
            var vendor = button.getAttribute('data-vendor');
            var status = button.getAttribute('data-status');
            var tipe = button.getAttribute('data-tipe');
            var rekening1 = button.getAttribute('data-rekening1');
            var rekening2 = button.getAttribute('data-rekening2');
            var jumlah = button.getAttribute('data-jumlah');
            var harga = button.getAttribute('data-harga');
            var subtotal = button.getAttribute('data-subtotal');

            var modalIdRequest = bayarModal.querySelector('#modal-id-request');
            var modalIdVendor = bayarModal.querySelector('#modal-id-vendor');
            var modalKodeRequest = bayarModal.querySelector('#modal-kode-request');
            var modalNamaBahan = bayarModal.querySelector('#modal-nama-bahan');
            var modalVendor = bayarModal.querySelector('#modal-vendor');
            var modalStatus = bayarModal.querySelector('#modal-status');
            var modalTipe = bayarModal.querySelector('#modal-tipe');
            var modalRekening1 = bayarModal.querySelector('#modal-rekening1');
            var modalRekening2 = bayarModal.querySelector('#modal-rekening2');
            var modalJumlah = bayarModal.querySelector('#modal-jumlah');
            var modalHarga = bayarModal.querySelector('#modal-harga');
            var modalSubtotal = bayarModal.querySelector('#modal-subtotal');
            var saveButton = bayarModal.querySelector('button[name="update_pembayaran"]');
            var nomorBuktiInput = bayarModal.querySelector('#modal-nomor-bukti');
            var fileBuktiInput = bayarModal.querySelector('#modal-bukti-transaksi');

            modalIdRequest.value = id_request;
            modalIdVendor.value = id_vendor;
            modalKodeRequest.value = kode_request;
            modalNamaBahan.value = namaBahan;
            modalVendor.value = vendor;
            modalStatus.value = status;
            modalTipe.value = tipe;
            modalRekening1.value = rekening1;
            modalRekening2.value = rekening2;
            modalJumlah.value = formatNumber(jumlah);
            modalHarga.value = formatNumber(harga);
            modalSubtotal.value = formatNumber(subtotal);

            if (status === 'PAID') {
                saveButton.style.display = 'none';
                nomorBuktiInput.disabled = true;
                fileBuktiInput.disabled = true;
            } else {
                saveButton.style.display = 'block';
                nomorBuktiInput.disabled = false;
                fileBuktiInput.disabled = false;
            }
        });

        var formPembayaran = document.getElementById('form-pembayaran');
        formPembayaran.addEventListener('submit', function(event) {
            var fileInput = document.getElementById('modal-bukti-transaksi');
            if (fileInput.files.length > 0) {
                var file = fileInput.files[0];
                var fileType = file.type;
                var fileSize = file.size;

                if (fileType !== 'image/jpeg') {
                    alert('File harus berupa JPG.');
                    event.preventDefault();
                    return;
                }

                if (fileSize > 1024 * 1024) {
                    alert('Ukuran file tidak boleh lebih dari 1 MB.');
                    event.preventDefault();
                    return;
                }
            }
            
            if (!confirm('Apakah Anda yakin ingin menyimpan pembayaran ini?')) {
                event.preventDefault();
            }
        });
    </script>
</body>
</html>