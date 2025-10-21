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
$total_subtotal = 0;
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
        if (!empty($search_result)) {
            foreach ($search_result as $row) {
                $total_subtotal += (float)$row['subtotal'];
            }
        }
        $stmt->close();
    }
}
?>
<!doctype html>
<html lang="id" data-bs-theme="light">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Pembayaran Pembelian - Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">
    <link href="../css/newadmin.css?v=3" rel="stylesheet">
</head>
<body>
    <?php include '_header_new.php'; ?>
    <?php include '_sidebar_new.php'; ?>

    <main class="main-content" id="mainContent">
        <div class="container-fluid">
            <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-3">
                <div>
                    <h2 class="mb-1"><i class="bi bi-credit-card-2-front me-2"></i>Pembayaran Pembelian</h2>
                    <p class="text-muted mb-0">Kelola pembayaran request pembelian dan unggah bukti transaksi</p>
                </div>
            </div>

            <?php if (isset($_SESSION['success_message'])): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <i class="bi bi-check-circle me-2"></i><?php echo htmlspecialchars($_SESSION['success_message']); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
            <?php unset($_SESSION['success_message']); endif; ?>

            <?php if (isset($_SESSION['error_message'])): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <i class="bi bi-exclamation-triangle me-2"></i><?php echo htmlspecialchars($_SESSION['error_message']); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
            <?php unset($_SESSION['error_message']); endif; ?>

            <div class="card-modern mb-4">
                <div class="card-header px-4 py-3">
                    <div class="d-flex align-items-center">
                        <i class="bi bi-search me-2"></i>
                        <span>Cari Request Pembelian</span>
                    </div>
                </div>
                <div class="card-body">
                    <form class="row g-3 align-items-end" action="pembayaran_pembelian.php" method="post">
                        <div class="col-md-8">
                            <label class="form-label fw-semibold">Kode Request / No PO</label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="bi bi-upc"></i></span>
                                <input type="text" class="form-control" id="kode_request" name="kode_request" placeholder="Masukkan kode request" value="<?php echo htmlspecialchars($kode_request_value); ?>" required>
                            </div>
                        </div>
                        <div class="col-md-4 d-flex align-items-end">
                            <button type="submit" class="btn btn-primary w-100">
                                <i class="bi bi-search me-2"></i>Cari Request
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <div class="card-modern">
                <div class="card-header px-4 py-3 d-flex flex-wrap align-items-center justify-content-between gap-2">
                    <div class="d-flex align-items-center">
                        <i class="bi bi-table me-2"></i>
                        <span>Daftar Pembayaran</span>
                    </div>
                    <?php if (!empty($search_result)): ?>
                    <span class="badge bg-primary-subtle text-primary px-3 py-2">
                        Total Tagihan: Rp <?php echo number_format($total_subtotal, 0, ',', '.'); ?>
                    </span>
                    <?php endif; ?>
                </div>
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th style="width:6%;" class="text-start">No</th>
                                <th style="width:20%;">Nama Bahan</th>
                                <th style="width:20%;">Vendor</th>
                                <th style="width:10%;" class="text-center">Status</th>
                                <th style="width:12%;" class="text-center">Tipe</th>
                                <th style="width:16%;" class="text-end">Subtotal</th>
                                <th style="width:12%;" class="text-center">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($search_result)): ?>
                                <?php foreach ($search_result as $index => $row): ?>
                                <?php
                                    $statusPaid = (int)$row['isDone'] === 1;
                                    $statusBadgeClass = $statusPaid ? 'bg-success' : 'bg-warning';
                                    $statusText = $statusPaid ? 'PAID' : 'UNPAID';
                                    $isInvoiceFlag = (int)$row['isInvoice'];
                                    $typeText = $isInvoiceFlag === 0 ? 'INV' : 'BAYAR LANGSUNG';
                                    $typeBadgeClass = $isInvoiceFlag === 0 ? 'bg-info' : 'bg-primary';
                                ?>
                                <tr>
                                    <td class="text-start fw-semibold"><?php echo $index + 1; ?></td>
                                    <td class="fw-medium"><?php echo htmlspecialchars($row['nama_bahan']); ?></td>
                                    <td><?php echo htmlspecialchars($row['nama_vendor']); ?></td>
                                    <td class="text-center">
                                        <span class="badge <?php echo $statusBadgeClass; ?> px-3 py-2"><?php echo $statusText; ?></span>
                                    </td>
                                    <td class="text-center">
                                        <span class="badge <?php echo $typeBadgeClass; ?> px-3 py-2"><?php echo $typeText; ?></span>
                                    </td>
                                    <td class="text-end">Rp <?php echo number_format((float)$row['subtotal'], 0, ',', '.'); ?></td>
                                    <td class="text-center">
                                        <button type="button"
                                            class="btn btn-sm <?php echo $statusPaid ? 'btn-outline-secondary' : 'btn-outline-success'; ?>"
                                            data-bs-toggle="modal"
                                            data-bs-target="#bayarModal"
                                            data-id-request="<?php echo (int)$row['id_request']; ?>"
                                            data-id-vendor="<?php echo (int)$row['id_vendor']; ?>"
                                            data-kode-request="<?php echo htmlspecialchars($row['kode_request']); ?>"
                                            data-nama-bahan="<?php echo htmlspecialchars($row['nama_bahan']); ?>"
                                            data-vendor="<?php echo htmlspecialchars($row['nama_vendor']); ?>"
                                            data-status="<?php echo $statusText; ?>"
                                            data-tipe="<?php echo $typeText; ?>"
                                            data-rekening1="<?php echo htmlspecialchars($row['nomor_rekening1']); ?>"
                                            data-rekening2="<?php echo htmlspecialchars($row['nomor_rekening2']); ?>"
                                            data-jumlah="<?php echo (int)$row['jumlah_request']; ?>"
                                            data-harga="<?php echo (float)$row['harga_est']; ?>"
                                            data-subtotal="<?php echo (float)$row['subtotal']; ?>">
                                            <i class="bi <?php echo $statusPaid ? 'bi-check-circle' : 'bi-credit-card'; ?> me-1"></i>
                                            <?php echo $statusPaid ? 'Terbayar' : 'Bayar'; ?>
                                        </button>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            <?php elseif (!empty($kode_request_value)): ?>
                            <tr>
                                <td colspan="7" class="text-center py-4">
                                    <i class="bi bi-inbox fs-1 d-block mb-2 text-muted"></i>
                                    <span class="text-muted">Data tidak ditemukan untuk kode request tersebut.</span>
                                </td>
                            </tr>
                            <?php else: ?>
                            <tr>
                                <td colspan="7" class="text-center py-4">
                                    <i class="bi bi-search fs-1 d-block mb-2 text-muted"></i>
                                    <span class="text-muted">Silakan cari request pembelian terlebih dahulu.</span>
                                </td>
                            </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </main>

    <div class="modal fade" id="bayarModal" tabindex="-1" aria-labelledby="bayarModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered">
            <div class="modal-content">
                <form id="form-pembayaran" action="pembayaran_pembelian.php" method="post" enctype="multipart/form-data" class="form-modern">
                    <div class="modal-header">
                        <h5 class="modal-title" id="bayarModalLabel"><i class="bi bi-credit-card me-2"></i>Form Pembayaran</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <input type="hidden" name="id_request" id="modal-id-request">
                        <input type="hidden" name="id_vendor" id="modal-id-vendor">
                        <input type="hidden" name="kode_request" id="modal-kode-request">

                        <div class="row g-3 mb-3">
                            <div class="col-md-6">
                                <div class="bg-body-tertiary rounded-3 p-3 h-100">
                                    <h6 class="fw-semibold mb-3"><i class="bi bi-info-circle me-2"></i>Informasi Request</h6>
                                    <dl class="row mb-0 small">
                                        <dt class="col-5 text-muted text-uppercase">Nama Bahan</dt>
                                        <dd class="col-7" id="modal-nama-bahan">-</dd>
                                        <dt class="col-5 text-muted text-uppercase">Vendor</dt>
                                        <dd class="col-7" id="modal-vendor">-</dd>
                                        <dt class="col-5 text-muted text-uppercase">Status</dt>
                                        <dd class="col-7" id="modal-status">-</dd>
                                        <dt class="col-5 text-muted text-uppercase">Tipe</dt>
                                        <dd class="col-7" id="modal-tipe">-</dd>
                                    </dl>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="bg-body-tertiary rounded-3 p-3 h-100">
                                    <h6 class="fw-semibold mb-3"><i class="bi bi-bank me-2"></i>Informasi Rekening</h6>
                                    <dl class="row mb-0 small">
                                        <dt class="col-5 text-muted text-uppercase">Rekening 1</dt>
                                        <dd class="col-7" id="modal-rekening1">-</dd>
                                        <dt class="col-5 text-muted text-uppercase">Rekening 2</dt>
                                        <dd class="col-7" id="modal-rekening2">-</dd>
                                    </dl>
                                </div>
                            </div>
                        </div>

                        <div class="card border-0 shadow-sm mb-3">
                            <div class="card-body">
                                <h6 class="fw-semibold mb-3"><i class="bi bi-cash-stack me-2"></i>Rincian Nominal</h6>
                                <div class="row g-3">
                                    <div class="col-md-4">
                                        <label class="form-label text-uppercase small text-muted">Jumlah</label>
                                        <input type="text" class="form-control" id="modal-jumlah" readonly>
                                    </div>
                                    <div class="col-md-4">
                                        <label class="form-label text-uppercase small text-muted">Harga</label>
                                        <input type="text" class="form-control" id="modal-harga" readonly>
                                    </div>
                                    <div class="col-md-4">
                                        <label class="form-label text-uppercase small text-muted">Subtotal</label>
                                        <input type="text" class="form-control fw-semibold" id="modal-subtotal" readonly>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="card border-0 shadow-sm">
                            <div class="card-body">
                                <h6 class="fw-semibold mb-3"><i class="bi bi-receipt me-2"></i>Konfirmasi Pembayaran</h6>
                                <div class="row g-3">
                                    <div class="col-md-6">
                                        <label class="form-label fw-semibold">Nomor Bukti Transaksi</label>
                                        <input type="text" class="form-control" id="modal-nomor-bukti" name="nomor_bukti_transaksi" placeholder="Masukkan nomor bukti">
                                        <div class="form-text">Kosongkan jika belum tersedia.</div>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label fw-semibold">Upload Bukti (JPG, &le; 1MB)</label>
                                        <input type="file" class="form-control" id="modal-bukti-transaksi" name="file_bukti" accept=".jpg">
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                            <i class="bi bi-x-lg me-1"></i>Tutup
                        </button>
                        <button type="submit" name="update_pembayaran" class="btn btn-primary">
                            <i class="bi bi-save me-1"></i>Simpan Pembayaran
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <?php include '_scripts_new.php'; ?>
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const formatter = new Intl.NumberFormat('id-ID');
            const bayarModalEl = document.getElementById('bayarModal');
            const formPembayaran = document.getElementById('form-pembayaran');

            if (bayarModalEl) {
                bayarModalEl.addEventListener('show.bs.modal', function (event) {
                    const button = event.relatedTarget;
                    if (!button) return;

                    const status = button.getAttribute('data-status');

                    document.getElementById('modal-id-request').value = button.getAttribute('data-id-request') || '';
                    document.getElementById('modal-id-vendor').value = button.getAttribute('data-id-vendor') || '';
                    document.getElementById('modal-kode-request').value = button.getAttribute('data-kode-request') || '';
                    document.getElementById('modal-nama-bahan').textContent = button.getAttribute('data-nama-bahan') || '-';
                    document.getElementById('modal-vendor').textContent = button.getAttribute('data-vendor') || '-';
                    document.getElementById('modal-status').textContent = status || '-';
                    document.getElementById('modal-tipe').textContent = button.getAttribute('data-tipe') || '-';
                    document.getElementById('modal-rekening1').textContent = button.getAttribute('data-rekening1') || '-';
                    document.getElementById('modal-rekening2').textContent = button.getAttribute('data-rekening2') || '-';
                    document.getElementById('modal-jumlah').value = formatter.format(button.getAttribute('data-jumlah') || 0);
                    document.getElementById('modal-harga').value = formatter.format(button.getAttribute('data-harga') || 0);
                    document.getElementById('modal-subtotal').value = formatter.format(button.getAttribute('data-subtotal') || 0);

                    const nomorBuktiInput = document.getElementById('modal-nomor-bukti');
                    const fileInput = document.getElementById('modal-bukti-transaksi');
                    const submitBtn = formPembayaran.querySelector('button[name="update_pembayaran"]');

                    if (status === 'PAID') {
                        submitBtn.classList.add('d-none');
                        nomorBuktiInput.disabled = true;
                        fileInput.disabled = true;
                    } else {
                        submitBtn.classList.remove('d-none');
                        nomorBuktiInput.disabled = false;
                        fileInput.disabled = false;
                        nomorBuktiInput.value = '';
                        fileInput.value = '';
                    }
                });
            }

            if (formPembayaran) {
                formPembayaran.addEventListener('submit', function (event) {
                    const fileInput = document.getElementById('modal-bukti-transaksi');
                    if (fileInput && fileInput.files.length > 0) {
                        const file = fileInput.files[0];
                        if (file.type !== 'image/jpeg') {
                            alert('File harus berupa JPG.');
                            event.preventDefault();
                            return;
                        }
                        if (file.size > 1024 * 1024) {
                            alert('Ukuran file tidak boleh lebih dari 1 MB.');
                            event.preventDefault();
                            return;
                        }
                    }
                });
            }
        });
    </script>
</body>
</html>
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