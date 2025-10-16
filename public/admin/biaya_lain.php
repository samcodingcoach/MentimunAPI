<?php
session_start();
require_once __DIR__ . '/../../config/koneksi.php';
require_once __DIR__ . '/../../config/encryption.php';

if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    header('Location: login.php');
    exit();
}

$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] == 'add_ppn') {
        $nilai_ppn = (float)$_POST['nilai_ppn'];
        $keterangan = trim($_POST['keterangan']);

        if ($nilai_ppn > 0) {
            $conn->query("UPDATE ppn SET aktif = 0 WHERE aktif = 1");

            $sql = "INSERT INTO ppn (nilai_ppn, keterangan, rilis, aktif) VALUES (?, ?, CURDATE(), 1)";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("ds", $nilai_ppn, $keterangan);

            if ($stmt->execute()) {
                $message = 'PPN berhasil ditambahkan!';
            } else {
                $error = 'Gagal menambahkan PPN: ' . $conn->error;
            }
        } else {
            $error = 'Nilai PPN harus lebih dari 0!';
        }
    }

    if ($_POST['action'] == 'update_ppn') {
        $id_ppn = (int)$_POST['id_ppn'];
        $nilai_ppn = (float)$_POST['nilai_ppn'];
        $keterangan = trim($_POST['keterangan']);

        if ($id_ppn > 0 && $nilai_ppn > 0) {
            $sql = "UPDATE ppn SET nilai_ppn = ?, keterangan = ? WHERE id_ppn = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("dsi", $nilai_ppn, $keterangan, $id_ppn);

            if ($stmt->execute()) {
                $message = 'PPN berhasil diupdate!';
            } else {
                $error = 'Gagal mengupdate PPN: ' . $conn->error;
            }
        } else {
            $error = 'Data tidak valid!';
        }
    }

    if ($_POST['action'] == 'delete_ppn') {
        $id_ppn = (int)$_POST['id_ppn'];

        if ($id_ppn > 0) {
            $sql = "DELETE FROM ppn WHERE id_ppn = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("i", $id_ppn);

            if ($stmt->execute()) {
                $message = 'PPN berhasil dihapus!';
            } else {
                $error = 'Gagal menghapus PPN: ' . $conn->error;
            }
        }
    }

    if ($_POST['action'] == 'toggle_ppn') {
        $id_ppn = (int)$_POST['id_ppn'];
        $current_status = (int)$_POST['current_status'];
        $new_status = ($current_status == 1) ? 0 : 1;

        if ($id_ppn > 0) {
            if ($new_status == 1) {
                $conn->query("UPDATE ppn SET aktif = 0 WHERE aktif = 1");
            }

            $sql = "UPDATE ppn SET aktif = ? WHERE id_ppn = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("ii", $new_status, $id_ppn);

            if ($stmt->execute()) {
                $status_text = ($new_status == 1) ? 'diaktifkan' : 'dinonaktifkan';
                $message = "PPN berhasil {$status_text}!";
            } else {
                $error = 'Gagal mengubah status PPN: ' . $conn->error;
            }
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] == 'add_takeaway') {
        $biaya_per_item = (float)$_POST['biaya_per_item'];
        $id_user = $_SESSION['id_user'];

        if ($biaya_per_item >= 0) {
            $sql = "INSERT INTO takeaway_charge (tanggal_rilis, id_user, biaya_per_item) VALUES (CURDATE(), ?, ?)";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("id", $id_user, $biaya_per_item);

            if ($stmt->execute()) {
                $message = 'Biaya takeaway berhasil ditambahkan!';
            } else {
                $error = 'Gagal menambahkan biaya takeaway: ' . $conn->error;
            }
        } else {
            $error = 'Biaya per item tidak valid!';
        }
    }

    if ($_POST['action'] == 'update_takeaway') {
        $id_ta = (int)$_POST['id_ta'];
        $biaya_per_item = (float)$_POST['biaya_per_item'];

        if ($id_ta > 0 && $biaya_per_item >= 0) {
            $sql = "UPDATE takeaway_charge SET biaya_per_item = ? WHERE id_ta = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("di", $biaya_per_item, $id_ta);

            if ($stmt->execute()) {
                $message = 'Biaya takeaway berhasil diupdate!';
            } else {
                $error = 'Gagal mengupdate biaya takeaway: ' . $conn->error;
            }
        } else {
            $error = 'Data tidak valid!';
        }
    }

    if ($_POST['action'] == 'delete_takeaway') {
        $id_ta = (int)$_POST['id_ta'];

        if ($id_ta > 0) {
            $sql = "DELETE FROM takeaway_charge WHERE id_ta = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("i", $id_ta);

            if ($stmt->execute()) {
                $message = 'Biaya takeaway berhasil dihapus!';
            } else {
                $error = 'Gagal menghapus biaya takeaway: ' . $conn->error;
            }
        }
    }
}

$ppn_data = [];
try {
    $ppn_sql = "SELECT ppn.id_ppn, ppn.nilai_ppn, ppn.keterangan, DATE_FORMAT(ppn.rilis,'%d %M %Y') as rilis, ppn.aktif FROM ppn ORDER BY id_ppn DESC";
    $ppn_result = $conn->query($ppn_sql);
    $ppn_data = $ppn_result->fetch_all(MYSQLI_ASSOC);
} catch (Exception $e) {
    $error = 'Terjadi kesalahan saat mengambil data PPN: ' . $e->getMessage();
}
$ppn_total = count($ppn_data);

$takeaway_data = [];
try {
    $takeaway_sql = "SELECT id_ta, DATE_FORMAT(tanggal_rilis,'%d %M %Y') as tanggal_rilis, biaya_per_item FROM takeaway_charge ORDER BY id_ta DESC";
    $takeaway_result = $conn->query($takeaway_sql);
    $takeaway_data = $takeaway_result->fetch_all(MYSQLI_ASSOC);
} catch (Exception $e) {
    $error = 'Terjadi kesalahan saat mengambil data takeaway: ' . $e->getMessage();
}
$takeaway_total = count($takeaway_data);

$current_takeaway = 0;
try {
    $current_sql = "SELECT biaya_per_item FROM takeaway_charge ORDER BY DATE(tanggal_rilis) DESC LIMIT 1";
    $current_result = $conn->query($current_sql);
    if ($current_result->num_rows > 0) {
        $current_row = $current_result->fetch_assoc();
        $current_takeaway = $current_row['biaya_per_item'];
    }
} catch (Exception $e) {
    // silent
}
?>

<!doctype html>
<html lang="id" data-bs-theme="light">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Biaya Lain - Admin</title>
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
                    <h2 class="mb-1"><i class="bi bi-receipt me-2"></i>Biaya Lain</h2>
                    <p class="text-muted mb-0">Kelola pajak PPN dan biaya takeaway</p>
                </div>
                <div class="d-flex flex-wrap gap-2">
                    <button class="btn btn-outline-primary" data-bs-toggle="modal" data-bs-target="#ppnModal">
                        <i class="bi bi-percent me-2"></i>Tambah PPN
                    </button>
                    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#takeawayModal">
                        <i class="bi bi-bag-plus me-2"></i>Tambah Biaya Takeaway
                    </button>
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

            <div class="card-modern">
                <div class="card-header px-4 py-3">
                    <div class="d-flex flex-wrap justify-content-between align-items-center gap-3">
                        <div class="d-flex align-items-center">
                            <i class="bi bi-sliders me-2"></i>
                            <span>Pengelolaan Biaya</span>
                        </div>
                        <ul class="nav nav-pills gap-2" id="biayaTab" role="tablist">
                            <li class="nav-item" role="presentation">
                                <button class="nav-link active" id="pajak-tab" data-bs-toggle="tab" data-bs-target="#pajak" type="button" role="tab">
                                    <i class="bi bi-percent me-2"></i>Pajak (PPN)
                                </button>
                            </li>
                            <li class="nav-item" role="presentation">
                                <button class="nav-link" id="takeaway-tab" data-bs-toggle="tab" data-bs-target="#takeaway" type="button" role="tab">
                                    <i class="bi bi-bag me-2"></i>Takeaway
                                </button>
                            </li>
                        </ul>
                    </div>
                </div>
                <div class="card-body p-0">
                    <div class="tab-content" id="biayaTabContent">
                        <div class="tab-pane fade show active" id="pajak" role="tabpanel" aria-labelledby="pajak-tab">
                           
                                
                                <div class="table-responsive px-0">
                                    <table class="table table-hover mb-0">
                                        <thead class="table-light">
                                            <tr>
                                                <th class="text-start" style="width: 5%;">No</th>
                                                <th style="width: 10%;" class="text-center">Nilai PPN</th>
                                                <th class="text-start">Keterangan</th>
                                                <th style="width: 15%;" class="text-center">Tanggal Rilis</th>
                                                <th class="text-center" style="width: 10%;">Status</th>
                                                <th class="text-center" style="width: 14%;">Aksi</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php if (empty($ppn_data)): ?>
                                            <tr>
                                                <td colspan="6" class="text-center py-5">
                                                    <i class="bi bi-inbox fs-1 d-block mb-2 text-muted"></i>
                                                    <span class="text-muted">Belum ada data PPN</span>
                                                </td>
                                            </tr>
                                            <?php else: ?>
                                                <?php foreach ($ppn_data as $index => $ppn): ?>
                                                <tr>
                                                    <td class="text-start fw-semibold"><?php echo $index + 1; ?></td>
                                                    <td class="fw-semibold text-primary text-center"><?php echo number_format($ppn['nilai_ppn'], 2); ?>%</td>
                                                    <td class="text-start"><?php echo htmlspecialchars($ppn['keterangan']); ?></td>
                                                    <td class="text-muted text-center"><?php echo $ppn['rilis']; ?></td>
                                                    <td class="text-center">
                                                        <?php if ($ppn['aktif'] == 1): ?>
                                                            <span class="badge bg-success-subtle text-success px-3 py-2">
                                                                <i class="bi bi-check-circle me-1"></i>Aktif
                                                            </span>
                                                        <?php else: ?>
                                                            <span class="badge bg-warning-subtle text-warning px-3 py-2">
                                                                <i class="bi bi-pause-circle me-1"></i>Nonaktif
                                                            </span>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td class="text-center">
                                                        <div class="d-inline-flex gap-2">
                                                            <button type="button" class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#ppnModal" data-ppn='<?php echo htmlspecialchars(json_encode($ppn), ENT_QUOTES, 'UTF-8'); ?>'>
                                                                <i class="bi bi-pencil"></i>
                                                            </button>
                                                            <form method="POST" class="d-inline" onsubmit="return confirm('Yakin ingin mengubah status PPN ini?');">
                                                                <input type="hidden" name="action" value="toggle_ppn">
                                                                <input type="hidden" name="id_ppn" value="<?php echo $ppn['id_ppn']; ?>">
                                                                <input type="hidden" name="current_status" value="<?php echo $ppn['aktif']; ?>">
                                                                <button type="submit" class="btn btn-sm <?php echo $ppn['aktif'] == 1 ? 'btn-outline-danger' : 'btn-outline-success'; ?>">
                                                                    <i class="bi bi-<?php echo $ppn['aktif'] == 1 ? 'x-circle' : 'check-circle'; ?>"></i>
                                                                </button>
                                                            </form>
                                                            <form method="POST" class="d-inline" onsubmit="return confirm('Hapus data PPN ini?');">
                                                                <input type="hidden" name="action" value="delete_ppn">
                                                                <input type="hidden" name="id_ppn" value="<?php echo $ppn['id_ppn']; ?>">
                                                                <button type="submit" class="btn btn-sm btn-outline-secondary">
                                                                    <i class="bi bi-trash"></i>
                                                                </button>
                                                            </form>
                                                        </div>
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
                                            <?php if (!empty($ppn_data)): ?>
                                                Menampilkan 1 - <?php echo number_format($ppn_total); ?> dari <?php echo number_format($ppn_total); ?> data
                                            <?php else: ?>
                                                Tidak ada data PPN
                                            <?php endif; ?>
                                        </small>
                                        <nav>
                                            <ul class="pagination pagination-sm mb-0">
                                                <li class="page-item disabled"><span class="page-link"><i class="bi bi-chevron-left"></i></span></li>
                                                <li class="page-item active"><span class="page-link">1</span></li>
                                                <li class="page-item disabled"><span class="page-link"><i class="bi bi-chevron-right"></i></span></li>
                                            </ul>
                                        </nav>
                                    </div>
                                </div>
                           
                        </div>

                        <div class="tab-pane fade" id="takeaway" role="tabpanel" aria-labelledby="takeaway-tab">
                           
                               
                                <div class="table-responsive px-0">
                                    <table class="table table-hover align-middle mb-0">
                                        <thead class="table-light">
                                            <tr>
                                                <th class="text-center" style="width: 6%;">No</th>
                                                <th style="width: 40%;" class="text-start">Tanggal Rilis</th>
                                                <th style="width: 40%;" class="text-center">Biaya per Item</th>
                                                <th class="text-center" style="width: 14%;">Aksi</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php if (empty($takeaway_data)): ?>
                                            <tr>
                                                <td colspan="4" class="text-center py-5">
                                                    <i class="bi bi-inbox fs-1 d-block mb-2 text-muted"></i>
                                                    <span class="text-muted">Belum ada data biaya takeaway</span>
                                                </td>
                                            </tr>
                                            <?php else: ?>
                                                <?php foreach ($takeaway_data as $index => $takeaway): ?>
                                                <tr>
                                                    <td class="text-center fw-semibold"><?php echo $index + 1; ?></td>
                                                    <td class="text-muted"><?php echo $takeaway['tanggal_rilis']; ?></td>
                                                    <td class="fw-semibold text-success">Rp <?php echo number_format($takeaway['biaya_per_item'], 0, ',', '.'); ?></td>
                                                    <td class="text-center">
                                                        <div class="d-inline-flex gap-2">
                                                            <button type="button" class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#takeawayModal" data-takeaway='<?php echo htmlspecialchars(json_encode($takeaway), ENT_QUOTES, 'UTF-8'); ?>'>
                                                                <i class="bi bi-pencil"></i>
                                                            </button>
                                                            <form method="POST" class="d-inline" onsubmit="return confirm('Hapus biaya takeaway ini?');">
                                                                <input type="hidden" name="action" value="delete_takeaway">
                                                                <input type="hidden" name="id_ta" value="<?php echo $takeaway['id_ta']; ?>">
                                                                <button type="submit" class="btn btn-sm btn-outline-secondary">
                                                                    <i class="bi bi-trash"></i>
                                                                </button>
                                                            </form>
                                                        </div>
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
                                            <?php if (!empty($takeaway_data)): ?>
                                                Menampilkan 1 - <?php echo number_format($takeaway_total); ?> dari <?php echo number_format($takeaway_total); ?> data
                                            <?php else: ?>
                                                Tidak ada data takeaway
                                            <?php endif; ?>
                                        </small>
                                        <nav>
                                            <ul class="pagination pagination-sm mb-0">
                                                <li class="page-item disabled"><span class="page-link"><i class="bi bi-chevron-left"></i></span></li>
                                                <li class="page-item active"><span class="page-link">1</span></li>
                                                <li class="page-item disabled"><span class="page-link"><i class="bi bi-chevron-right"></i></span></li>
                                            </ul>
                                        </nav>
                                    </div>
                                </div>
                        
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <div class="modal fade" id="ppnModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="bi bi-plus-circle me-2"></i>Tambah PPN</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST" class="form-modern">
                    <div class="modal-body">
                        <input type="hidden" name="action" id="ppnAction" value="add_ppn">
                        <input type="hidden" name="id_ppn" id="ppnId">
                        <div class="mb-3">
                            <label for="ppnNilai" class="form-label">Nilai PPN (%)</label>
                            <input type="number" class="form-control" id="ppnNilai" name="nilai_ppn" step="0.01" min="0" max="100" required>
                        </div>
                        <div class="mb-3">
                            <label for="ppnKeterangan" class="form-label">Keterangan</label>
                            <textarea class="form-control" id="ppnKeterangan" name="keterangan" rows="3" required></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                            <i class="bi bi-x-lg me-1"></i>Batal
                        </button>
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-check-lg me-1"></i>Simpan
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="modal fade" id="takeawayModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="bi bi-plus-circle me-2"></i>Tambah Biaya Takeaway</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST" class="form-modern">
                    <div class="modal-body">
                        <input type="hidden" name="action" id="takeawayAction" value="add_takeaway">
                        <input type="hidden" name="id_ta" id="takeawayId">
                        <div class="mb-3">
                            <label for="takeawayBiaya" class="form-label">Biaya per Item (Rp)</label>
                            <input type="number" class="form-control" id="takeawayBiaya" name="biaya_per_item" min="0" required>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                            <i class="bi bi-x-lg me-1"></i>Batal
                        </button>
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-check-lg me-1"></i>Simpan
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <?php include '_scripts_new.php'; ?>
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            document.querySelectorAll('[data-bs-toggle="tab"]').forEach(function (button) {
                button.addEventListener('shown.bs.tab', function (event) {
                    const targetSelector = event.target.getAttribute('data-bs-target');
                    const target = document.querySelector(targetSelector);
                    if (target) {
                        target.style.opacity = '0';
                        setTimeout(function () {
                            target.style.opacity = '1';
                        }, 80);
                    }
                });
            });

            const ppnModal = document.getElementById('ppnModal');
            if (ppnModal) {
                ppnModal.addEventListener('show.bs.modal', function (event) {
                    const button = event.relatedTarget;
                    const form = ppnModal.querySelector('form');
                    const actionInput = form.querySelector('#ppnAction');
                    const idInput = form.querySelector('#ppnId');
                    const nilaiInput = form.querySelector('#ppnNilai');
                    const ketInput = form.querySelector('#ppnKeterangan');
                    const title = ppnModal.querySelector('.modal-title');

                    if (button && button.getAttribute('data-ppn')) {
                        try {
                            const data = JSON.parse(button.getAttribute('data-ppn'));
                            actionInput.value = 'update_ppn';
                            idInput.value = data.id_ppn;
                            nilaiInput.value = data.nilai_ppn;
                            ketInput.value = data.keterangan;
                            title.innerHTML = '<i class="bi bi-pencil-square me-2"></i>Edit PPN';
                        } catch (error) {
                            console.error('Gagal memuat data PPN:', error);
                        }
                    } else {
                        actionInput.value = 'add_ppn';
                        idInput.value = '';
                        nilaiInput.value = '';
                        ketInput.value = '';
                        title.innerHTML = '<i class="bi bi-plus-circle me-2"></i>Tambah PPN';
                    }
                });

                ppnModal.addEventListener('hidden.bs.modal', function () {
                    const form = ppnModal.querySelector('form');
                    form.reset();
                    form.querySelector('#ppnAction').value = 'add_ppn';
                    form.querySelector('#ppnId').value = '';
                    ppnModal.querySelector('.modal-title').innerHTML = '<i class="bi bi-plus-circle me-2"></i>Tambah PPN';
                });
            }

            const takeawayModal = document.getElementById('takeawayModal');
            if (takeawayModal) {
                takeawayModal.addEventListener('show.bs.modal', function (event) {
                    const button = event.relatedTarget;
                    const form = takeawayModal.querySelector('form');
                    const actionInput = form.querySelector('#takeawayAction');
                    const idInput = form.querySelector('#takeawayId');
                    const biayaInput = form.querySelector('#takeawayBiaya');
                    const title = takeawayModal.querySelector('.modal-title');

                    if (button && button.getAttribute('data-takeaway')) {
                        try {
                            const data = JSON.parse(button.getAttribute('data-takeaway'));
                            actionInput.value = 'update_takeaway';
                            idInput.value = data.id_ta;
                            biayaInput.value = data.biaya_per_item;
                            title.innerHTML = '<i class="bi bi-pencil-square me-2"></i>Edit Biaya Takeaway';
                        } catch (error) {
                            console.error('Gagal memuat data takeaway:', error);
                        }
                    } else {
                        actionInput.value = 'add_takeaway';
                        idInput.value = '';
                        biayaInput.value = '';
                        title.innerHTML = '<i class="bi bi-plus-circle me-2"></i>Tambah Biaya Takeaway';
                    }
                });

                takeawayModal.addEventListener('hidden.bs.modal', function () {
                    const form = takeawayModal.querySelector('form');
                    form.reset();
                    form.querySelector('#takeawayAction').value = 'add_takeaway';
                    form.querySelector('#takeawayId').value = '';
                    takeawayModal.querySelector('.modal-title').innerHTML = '<i class="bi bi-plus-circle me-2"></i>Tambah Biaya Takeaway';
                });
            }
        });
    </script>
</body>
</html>
