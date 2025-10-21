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

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action']) && $_POST['action'] == 'rilis_batch') {
    $id_user = isset($_SESSION['id_user']) ? (int)$_SESSION['id_user'] : 0;
    $id_produk_list = isset($_POST['id_produk']) && is_array($_POST['id_produk']) ? $_POST['id_produk'] : [];
    $stok_list = isset($_POST['stok']) && is_array($_POST['stok']) ? $_POST['stok'] : [];
    $harga_list = isset($_POST['harga_jual']) && is_array($_POST['harga_jual']) ? $_POST['harga_jual'] : [];

    if (empty($id_produk_list) || count($id_produk_list) !== count($stok_list) || count($id_produk_list) !== count($harga_list)) {
        $error = 'Data rilis batch tidak valid!';
    } else {
        $success_count = 0;
        $check_stmt = $conn->prepare("SELECT id_produk_sell FROM produk_sell WHERE id_produk = ? AND DATE(tgl_release) = CURDATE() LIMIT 1");
        $insert_stmt = $conn->prepare("INSERT INTO produk_sell (id_produk, stok_awal, harga_jual, id_user, stok) VALUES (?, ?, ?, ?, ?)");

        if (!$check_stmt || !$insert_stmt) {
            $error = 'Gagal mempersiapkan query rilis batch: ' . $conn->error;
        } else {
            foreach ($id_produk_list as $index => $id_produk_value) {
                $id_produk = (int)$id_produk_value;
                $stok_awal = isset($stok_list[$index]) ? max(0, (int)$stok_list[$index]) : 0;
                $harga_jual = isset($harga_list[$index]) ? (float)$harga_list[$index] : 0.0;

                if ($id_produk <= 0) {
                    continue;
                }

                $check_stmt->bind_param('i', $id_produk);
                $check_stmt->execute();
                $check_result = $check_stmt->get_result();
                if ($check_result && $check_result->num_rows > 0) {
                    continue;
                }

                $insert_stmt->bind_param('iidii', $id_produk, $stok_awal, $harga_jual, $id_user, $stok_awal);
                if ($insert_stmt->execute()) {
                    $success_count++;
                }
            }

            $check_stmt->close();
            $insert_stmt->close();
        }

        if ($success_count > 0) {
            $message = $success_count . ' produk berhasil dirilis!';
        } else {
            if (empty($error)) {
                $error = 'Tidak ada produk yang dirilis. Data mungkin sudah dirilis hari ini.';
            }
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action']) && $_POST['action'] == 'rilis') {
    $id_produk = (int)$_POST['id_produk'];
    $stok = (int)$_POST['stok'];
    $harga_jual = (float)$_POST['harga_jual'];
    $aktif = isset($_POST['aktif']) ? '1' : '0';
    $id_user = $_SESSION['id_user'];
    $tgl_hari_ini = date('Y-m-d');

    if ($id_produk > 0 && $stok >= 0) {
        $cek_sql = "SELECT id_produk_sell FROM produk_sell WHERE id_produk = ? AND DATE(tgl_release) = ?";
        $cek_stmt = $conn->prepare($cek_sql);
        $cek_stmt->bind_param("is", $id_produk, $tgl_hari_ini);
        $cek_stmt->execute();
        $cek_result = $cek_stmt->get_result();

        if ($cek_result->num_rows > 0) {
            $error = 'Data sudah ada untuk tanggal hari ini!';
        } else {
            $sql = "INSERT INTO produk_sell(id_produk, stok, harga_jual, id_user, aktif, stok_awal, tgl_release) VALUES (?, ?, ?, ?, ?, ?, NOW())";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("iidisi", $id_produk, $stok, $harga_jual, $id_user, $aktif, $stok);

            if ($stmt->execute()) {
                $message = 'Produk berhasil dirilis!';
            } else {
                $error = 'Gagal merilis produk: ' . $conn->error;
            }
        }
    } else {
        $error = 'Data tidak valid!';
    }
}

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action']) && $_POST['action'] == 'toggle_aktif') {
    $id_produk = (int)$_POST['id_produk'];
    $current_status = $_POST['current_status'];
    $new_status = ($current_status == '1') ? '0' : '1';
    $tgl_release = $_POST['tgl_release'];

    if ($id_produk > 0) {
        $sql = "UPDATE produk_sell SET aktif = ? WHERE id_produk = ? AND DATE(tgl_release) = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("sis", $new_status, $id_produk, $tgl_release);

        if ($stmt->execute()) {
            $status_text = ($new_status == '1') ? 'diaktifkan' : 'dinonaktifkan';
            $message = "Status produk berhasil {$status_text}!";
        } else {
            $error = 'Gagal mengubah status produk: ' . $conn->error;
        }
    } else {
        $error = 'Data tidak valid!';
    }
}

$selected_date = isset($_GET['date']) ? $_GET['date'] : date('Y-m-d');

if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $selected_date)) {
    $selected_date = date('Y-m-d');
}

$data = [];
try {
    $sql = "
        SELECT
            harga_menu.id_harga,
            harga_menu.id_produk,
            produk_menu.kode_produk,
            produk_menu.nama_produk,
            nama_kategori,
            nominal,
            harga_menu.id_user,
            COALESCE(FORMAT(produk_sell.harga_jual, 0), 0) AS harga_jual,
            COALESCE(produk_sell.stok, 0) AS stok,
            COALESCE(produk_sell.id_produk_sell, '-') AS id_produk_sell,
            COALESCE(produk_sell.aktif, '-') AS aktif_jual
        FROM
            harga_menu
        INNER JOIN (
            SELECT id_produk, MAX(tgl) AS tgl_terbaru
            FROM harga_menu
            WHERE nominal > 0 AND id_resep IS NOT NULL
            GROUP BY id_produk
        ) AS subquery
            ON harga_menu.id_produk = subquery.id_produk
            AND harga_menu.tgl = subquery.tgl_terbaru
        INNER JOIN produk_menu
            ON harga_menu.id_produk = produk_menu.id_produk
        INNER JOIN kategori_menu
            ON produk_menu.id_kategori = kategori_menu.id_kategori
        LEFT JOIN produk_sell
            ON harga_menu.id_produk = produk_sell.id_produk
            AND DATE(produk_sell.tgl_release) = ?
        WHERE harga_menu.nominal > 0
          AND harga_menu.id_resep IS NOT NULL
        ORDER BY produk_menu.nama_produk ASC
    "
    ;

    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $selected_date);
    $stmt->execute();
    $result = $stmt->get_result();
    $data = $result->fetch_all(MYSQLI_ASSOC);
} catch (Exception $e) {
    $error = 'Terjadi kesalahan saat mengambil data: ' . $e->getMessage();
}

$produk_rilis_terbaru = [];
try {
    $sql_rilis = "
        SELECT
            ps.id_produk_sell,
            ps.id_produk,
            vp.kode_produk,
            vp.nama_produk,
            vp.nama_kategori,
            ps.harga_jual,
            ps.stok
        FROM
            produk_sell ps
        INNER JOIN view_produk vp ON ps.id_produk = vp.id_produk
        WHERE
            DATE(ps.tgl_release) = (
                SELECT MAX(DATE(tgl_release)) FROM produk_sell
            )
            AND ps.id_produk NOT IN (
                SELECT id_produk FROM produk_sell WHERE DATE(tgl_release) = CURDATE()
            )
        ORDER BY ps.harga_jual ASC
    ";

    $result_rilis = $conn->query($sql_rilis);
    if ($result_rilis) {
        $produk_rilis_terbaru = $result_rilis->fetch_all(MYSQLI_ASSOC);
    }
} catch (Exception $e) {
    $error = 'Terjadi kesalahan saat mengambil data rilis terbaru: ' . $e->getMessage();
}
?>

<!doctype html>
<html lang="id" data-bs-theme="light">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Harga Rilis - Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    <link href="https://cdn.jsdelivr.net/npm/select2-bootstrap-5-theme@1.3.0/dist/select2-bootstrap-5-theme.min.css" rel="stylesheet" />
    <link href="../css/newadmin.css?v=3" rel="stylesheet">
    <style>
        #produkTerbaruModal .table td,
        #produkTerbaruModal .table th {
            vertical-align: middle;
        }
        #produkTerbaruModal .stok-input {
            max-width: 110px;
            margin-inline: auto;
        }
        #produkTerbaruModal .avatar-wrapper {
            width: 48px;
            height: 48px;
            border-radius: 50%;
            overflow: hidden;
            background-color: var(--bs-body-tertiary);
        }
        #produkTerbaruModal .avatar-wrapper img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
    </style>
</head>
<body>
    <?php include '_header_new.php'; ?>
    <?php include '_sidebar_new.php'; ?>

    <main class="main-content" id="mainContent">
        <div class="container-fluid">
            <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-3">
                <div>
                    <h2 class="mb-1"><i class="bi bi-tag me-2"></i>Harga Rilis</h2>
                    <p class="text-muted mb-0">Kelola harga jual harian, stok, dan status penjualan produk.</p>
                </div>

                <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#produkTerbaruModal">
                    <i class="bi bi-plus-lg me-2"></i>Tambah Rilis
                </button>
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
                <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-3 px-4">
                    <div class="d-flex align-items-center">
                        <i class="bi bi-table me-2"></i>
                        <span>Daftar Produk</span>
                    </div>
                    <form method="GET" class="d-flex align-items-center" onchange="this.submit()">
                        <input type="date" class="form-control" name="date" value="<?php echo htmlspecialchars($selected_date); ?>">
                    </form>
                </div>
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead class="table-light">
                            <tr>
                                <th style="width: 5%;" class="text-start">No</th>
                                <th style="width: 10%;">Kode</th>
                                <th class="text-start" style="width: auto;">Nama Produk</th>
                                <th class="text-center" style="width: 12%;">Kategori</th>
                                <th style="width: 16%;" class="text-end">Harga Pokok</th>
                                <th style="width: 12%;" class="text-center">Stok</th>
                                <th style="width: 16%;" class="text-center">Status Penjualan</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($data)): ?>
                            <tr>
                                <td colspan="7" class="text-center py-5">
                                    <i class="bi bi-inbox fs-1 d-block mb-2 text-muted"></i>
                                    <span class="text-muted">Tidak ada data untuk tanggal <?php echo htmlspecialchars($selected_date); ?></span>
                                </td>
                            </tr>
                            <?php else: ?>
                                <?php foreach ($data as $index => $row): ?>
                                <tr>
                                    <td class="text-start fw-semibold"><?php echo $index + 1; ?></td>
                                    <td class="fw-medium text-uppercase"><?php echo htmlspecialchars($row['kode_produk']); ?></td>
                                    <td class="fw-semibold text-start" ><?php echo htmlspecialchars($row['nama_produk']); ?></td>
                                    <td class="text-center"><?php echo htmlspecialchars($row['nama_kategori']); ?></td>
                                    <td class="text-end">Rp <?php echo number_format($row['nominal'], 0, ',', '.'); ?></td>
                                    <td class="text-center fw-semibold"><?php echo htmlspecialchars($row['stok']); ?></td>
                                    <td class="text-center">
                                        <?php if ($row['aktif_jual'] === '1'): ?>
                                            <button type="button" class="btn btn-sm btn-success d-inline-flex align-items-center gap-2" data-bs-toggle="modal" data-bs-target="#toggleModal"
                                                onclick="setToggleData(<?php echo $row['id_produk']; ?>, '<?php echo addslashes($row['nama_produk']); ?>', '1', '<?php echo $selected_date; ?>')">
                                                <i class="bi bi-check-circle"></i><span>Aktif</span>
                                            </button>
                                        <?php elseif ($row['aktif_jual'] === '0'): ?>
                                            <button type="button" class="btn btn-sm btn-warning d-inline-flex align-items-center gap-2" data-bs-toggle="modal" data-bs-target="#toggleModal"
                                                onclick="setToggleData(<?php echo $row['id_produk']; ?>, '<?php echo addslashes($row['nama_produk']); ?>', '0', '<?php echo $selected_date; ?>')">
                                                <i class="bi bi-pause-circle"></i><span>Nonaktif</span>
                                            </button>
                                        <?php else: ?>
                                            <button type="button" class="btn btn-sm btn-outline-primary d-inline-flex align-items-center gap-2" data-bs-toggle="modal" data-bs-target="#rilisModal"
                                                onclick="setRilisData(<?php echo $row['id_produk']; ?>, '<?php echo addslashes($row['nama_produk']); ?>', <?php echo (float)$row['nominal']; ?>)">
                                                <i class="bi bi-upload"></i><span>Belum Rilis</span>
                                            </button>
                                        <?php endif; ?>
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
                            <?php if (!empty($data)): ?>
                                Menampilkan <?php echo count($data); ?> produk | Tanggal: <?php echo date('d/m/Y', strtotime($selected_date)); ?>
                            <?php else: ?>
                                Tidak ada data ditampilkan
                            <?php endif; ?>
                        </small>
                        <small class="text-muted">
                            <i class="bi bi-info-circle me-1"></i>Harga rilis berlaku per tanggal terpilih
                        </small>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <div class="modal fade" id="produkTerbaruModal" tabindex="-1" aria-labelledby="produkTerbaruModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-xl modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="produkTerbaruModalLabel"><i class="bi bi-clock-history me-2"></i>Produk Rilis Terakhir</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form method="POST" class="form-modern">
                    <input type="hidden" name="action" value="rilis_batch">
                    <div class="modal-body">
                        <div class="table-responsive">
                            <table class="table table-hover mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th style="width: 5%;" class="text-start">No</th>
                                    <th style="width: 8%;" class="text-start">Gambar</th>
                                    <th style="width: 12%;" class="text-start">Kode Produk</th>
                                    <th class="text-start" style="width: auto;">Nama Produk</th>
                                    <th style="width: 18%;" class="text-center">Nama Kategori</th>
                                    <th style="width: 15%;" class="text-end">Harga</th>
                                    <th style="width: 12%;" class="text-center">Stok</th>
                                </tr>
                            </thead>
                                <tbody>
                                    <?php if (!empty($produk_rilis_terbaru)): ?>
                                        <?php foreach ($produk_rilis_terbaru as $index => $produk): ?>
                                        <?php
                                            $kodeProdukRaw = $produk['kode_produk'];
                                            $kodeProdukSafe = htmlspecialchars($kodeProdukRaw);
        								$localImagePath = __DIR__ . '/../images/' . $kodeProdukRaw . '.jpg';
                                            $gambarPath = file_exists($localImagePath)
                                                ? '../images/' . $kodeProdukRaw . '.jpg'
                                                : '../images/default.jpg';
                                            $idProdukValue = isset($produk['id_produk']) ? (int)$produk['id_produk'] : 0;
                                            $hargaJualValue = isset($produk['harga_jual']) ? (float)$produk['harga_jual'] : 0.0;
                                            $stokValue = isset($produk['stok']) ? max(0, (int)$produk['stok']) : 0;
                                        ?>
                                        <tr>
                                            <input type="hidden" name="id_produk[]" value="<?php echo $idProdukValue; ?>">
                                            <input type="hidden" name="harga_jual[]" value="<?php echo $hargaJualValue; ?>">
                                            <td class="text-start fw-semibold"><?php echo $index + 1; ?></td>
                                            <td class="text-center">
                                                <div class="avatar-wrapper d-inline-flex align-items-center justify-content-center">
                                                    <img src="<?php echo $gambarPath; ?>"
                                                         alt="<?php echo $kodeProdukSafe; ?>"
                                                         loading="lazy">
                                                </div>
                                            </td>
                                            <td class="text-uppercase fw-medium text-start"><?php echo $kodeProdukSafe; ?></td>
                                            <td class="text-start fw-semibold"><?php echo htmlspecialchars($produk['nama_produk']); ?></td>
                                            <td class="text-center"><?php echo htmlspecialchars($produk['nama_kategori']); ?></td>
                                            <td class="text-end">Rp <?php echo number_format($hargaJualValue, 0, ',', '.'); ?></td>
                                            <td class="text-center">
                                                <input type="number" class="form-control text-center stok-input" name="stok[]" min="0" value="<?php echo $stokValue; ?>">
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <tr>
                                            <td colspan="7" class="text-center py-5">
                                                <i class="bi bi-inbox fs-1 d-block mb-3 text-muted"></i>
                                                <span class="text-muted">Tidak ada data rilis sebelumnya yang dapat ditampilkan.</span>
                                            </td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                            <i class="bi bi-x-lg me-1"></i>Tutup
                        </button>
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-upload me-1"></i>Rilis
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="modal fade" id="rilisModal" tabindex="-1" aria-labelledby="rilisModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="rilisModalLabel"><i class="bi bi-upload me-2"></i>Rilis Produk</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form method="POST" class="form-modern">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="rilis">
                        <input type="hidden" name="id_produk" id="modal_id_produk">
                        <input type="hidden" name="harga_jual" id="modal_harga_jual">

        				<div class="bg-body-tertiary rounded-3 p-3 mb-4">
                            <div class="row g-3 align-items-start">
                                <div class="col-12 col-md-6">
                                    <span class="text-uppercase text-muted small fw-semibold d-block">Nama Produk</span>
                                    <div id="modal_nama_produk" class="fw-semibold text-dark"></div>
                                </div>
                                <div class="col-6 col-md-3">
                                    <span class="text-uppercase text-muted small fw-semibold d-block">Tanggal</span>
                                    <div class="fw-medium text-dark"><?php echo date('d/m/Y'); ?></div>
                                </div>
                                <div class="col-6 col-md-3 text-md-end">
                                    <span class="text-uppercase text-muted small fw-semibold d-block">Harga Jual</span>
                                    <div id="modal_harga_display" class="fw-bold text-success"></div>
                                </div>
                            </div>
                        </div>

                        <div class="mb-4">
                            <label for="modal_stok" class="form-label fw-semibold">Stok Tersedia</label>
                            <div class="row g-2 align-items-center">
                                <div class="col-12 col-sm">
                                    <div class="input-group">
                                        <span class="input-group-text bg-white"><i class="bi bi-box"></i></span>
                                        <input type="number" class="form-control" id="modal_stok" name="stok" min="0" required>
                                    </div>
                                </div>
                                <div class="col-12 col-sm-auto">
                                    
                                </div>
                            </div>
                        </div>

                        <div class="form-check form-switch">
                            <input type="checkbox" class="form-check-input" id="modal_aktif" name="aktif" checked>
                            <label class="form-check-label fw-medium" for="modal_aktif">Aktifkan produk untuk penjualan</label>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                            <i class="bi bi-x-lg me-1"></i>Batal
                        </button>
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-upload me-1"></i>Rilis Produk
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="modal fade" id="toggleModal" tabindex="-1" aria-labelledby="toggleModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="toggleModalLabel"><i class="bi bi-toggle-on me-2"></i>Ubah Status Penjualan</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form method="POST" class="form-modern">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="toggle_aktif">
                        <input type="hidden" name="id_produk" id="toggle_id_produk">
                        <input type="hidden" name="current_status" id="toggle_current_status">
                        <input type="hidden" name="tgl_release" id="toggle_tgl_release">

                        <div class="text-center mb-4">
                            <i class="bi bi-question-circle text-warning" style="font-size: 3rem;"></i>
                        </div>
                        <h6 class="fw-semibold text-center mb-3" id="toggle_nama_produk"></h6>
                        <p class="text-muted text-center mb-0" id="toggle_message"></p>
                    </div>
                    <div class="modal-footer justify-content-between">
                        <button type="button" class="btn btn-light" data-bs-dismiss="modal">
                            <i class="bi bi-x-lg me-1"></i>Batal
                        </button>
                        <button type="submit" class="btn" id="toggle_submit_btn">
                            <i class="bi bi-toggle-on me-1"></i><span id="toggle_action_text"></span>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <?php include '_scripts_new.php'; ?>
    <script>
        function setRilisData(idProduk, namaProduk, hargaPokok) {
            document.getElementById('modal_id_produk').value = idProduk;
            document.getElementById('modal_nama_produk').textContent = namaProduk;
            document.getElementById('modal_harga_jual').value = hargaPokok;
            document.getElementById('modal_harga_display').textContent = new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', minimumFractionDigits: 0 }).format(hargaPokok);
            document.getElementById('modal_stok').value = '';
            document.getElementById('modal_aktif').checked = true;
        }

        function setToggleData(idProduk, namaProduk, currentStatus, tglRelease) {
            document.getElementById('toggle_id_produk').value = idProduk;
            document.getElementById('toggle_nama_produk').textContent = namaProduk;
            document.getElementById('toggle_current_status').value = currentStatus;
            document.getElementById('toggle_tgl_release').value = tglRelease;

            const toggleMessage = document.getElementById('toggle_message');
            const toggleSubmitBtn = document.getElementById('toggle_submit_btn');
            const toggleActionText = document.getElementById('toggle_action_text');

            if (currentStatus === '1') {
                toggleMessage.textContent = 'Nonaktifkan produk ini dari penjualan?';
                toggleSubmitBtn.className = 'btn btn-warning';
                toggleActionText.textContent = 'Nonaktifkan';
            } else {
                toggleMessage.textContent = 'Aktifkan produk ini untuk penjualan?';
                toggleSubmitBtn.className = 'btn btn-success';
                toggleActionText.textContent = 'Aktifkan';
            }
        }
    </script>
</body>
</html>
