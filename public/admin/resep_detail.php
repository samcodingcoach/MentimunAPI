<?php
session_start();
require_once '../../config/koneksi.php';

// Check if user is logged in
if (!isset($_SESSION["id_user"])) {
    header("Location: login.php");
    exit();
}

// Handle AJAX requests
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    // Tidak perlu AJAX search, langsung load semua bahan
    
    if ($action === 'publish_resep') {
        $id_resep = $_POST['id_resep'] ?? '';
        
        if (empty($id_resep)) {
            header('Content-Type: application/json');
            echo json_encode(['status' => 'error', 'message' => 'ID Resep tidak valid']);
            exit;
        }
        
        $stmt = $conn->prepare("UPDATE resep SET publish_menu = 1 WHERE id_resep = ?");
        $stmt->bind_param('i', $id_resep);
        
        if ($stmt->execute()) {
            header('Content-Type: application/json');
            echo json_encode(['status' => 'success', 'message' => 'Resep berhasil dipublish']);
        } else {
            header('Content-Type: application/json');
            echo json_encode(['status' => 'error', 'message' => 'Gagal mempublish resep']);
        }
        exit;
    }
    
    if ($action === 'delete_detail') {
        $id_resep_detail = $_POST['id_resep_detail'] ?? '';
        
        if (empty($id_resep_detail)) {
            header('Content-Type: application/json');
            echo json_encode(['status' => 'error', 'message' => 'ID Resep Detail tidak valid']);
            exit;
        }
        
        $stmt = $conn->prepare("DELETE FROM resep_detail WHERE id_resep_detail = ?");
        $stmt->bind_param('i', $id_resep_detail);
        
        if ($stmt->execute()) {
            header('Content-Type: application/json');
            echo json_encode(['status' => 'success', 'message' => 'Detail resep berhasil dihapus']);
        } else {
            header('Content-Type: application/json');
            echo json_encode(['status' => 'error', 'message' => 'Gagal menghapus detail resep']);
        }
        exit;
    }
    
    if ($action === 'add_detail') {
        $id_resep = $_POST['id_resep'] ?? '';
        $id_bahan_biaya = $_POST['id_bahan_biaya'] ?? '';
        $id_bahan = $_POST['id_bahan'] ?? '';
        $satuan_pemakaian = $_POST['satuan_pemakaian'] ?? '';
        $jumlah_pemakaian = $_POST['jumlah_pemakaian'] ?? 0;
        $nilai_ekpetasi = $_POST['nilai_ekpetasi'] ?? 0;
        
        // Validasi input dengan pesan spesifik
        $errors = [];
        
        if (empty($id_resep)) {
            $errors[] = 'ID Resep tidak valid';
        }
        if (empty($id_bahan)) {
            $errors[] = 'Bahan belum dipilih';
        }
        if (empty($id_bahan_biaya)) {
            $errors[] = 'ID Bahan Biaya tidak valid';
        }
        if (empty($satuan_pemakaian)) {
            $errors[] = 'Satuan pemakaian belum diisi';
        }
        if ($jumlah_pemakaian <= 0) {
            $errors[] = 'Jumlah pemakaian harus lebih dari 0';
        }
        if ($nilai_ekpetasi <= 0) {
            $errors[] = 'Perkiraan biaya harus lebih dari 0';
        }
        
        if (!empty($errors)) {
            header('Content-Type: application/json');
            echo json_encode(['status' => 'error', 'message' => 'Error: ' . implode(', ', $errors)]);
            exit;
        }
        
        try {
            // Cek duplikasi
            $checkStmt = $conn->prepare("SELECT COUNT(*) AS count FROM resep_detail INNER JOIN resep ON resep_detail.id_resep = resep.id_resep WHERE resep.publish_menu = 0 and resep_detail.id_resep = ? AND id_bahan_biaya = ?");
            $checkStmt->bind_param('ii', $id_resep, $id_bahan_biaya);
            $checkStmt->execute();
            $checkResult = $checkStmt->get_result();
            $count = $checkResult->fetch_assoc()['count'];
            
            if ($count > 0) {
                header('Content-Type: application/json');
                echo json_encode(['status' => 'error', 'message' => 'Detail resep dengan bahan yang sama sudah ada']);
                exit;
            }
            
            // Insert data
            $insertStmt = $conn->prepare("
                INSERT INTO resep_detail (id_resep, id_bahan, id_bahan_biaya, satuan_pemakaian, jumlah_pemakaian, nilai_ekpetasi)
                VALUES (?, ?, ?, ?, ?, ?)
            ");
            
            $insertStmt->bind_param('iiisdd', $id_resep, $id_bahan, $id_bahan_biaya, $satuan_pemakaian, $jumlah_pemakaian, $nilai_ekpetasi);
            
            if ($insertStmt->execute()) {
                header('Content-Type: application/json');
                echo json_encode(['status' => 'success', 'message' => 'Detail resep berhasil ditambahkan']);
            } else {
                header('Content-Type: application/json');
                echo json_encode(['status' => 'error', 'message' => 'Gagal menyimpan data']);
            }
            exit;
            
        } catch (Exception $e) {
            header('Content-Type: application/json');
            echo json_encode(['status' => 'error', 'message' => 'Gagal menyimpan data: ' . $e->getMessage()]);
            exit;
        }
    }
}

// Get resep ID from URL (support both 'id_resep' and 'id' parameters)
$id_resep = 0;
if (isset($_GET['id_resep'])) {
    $id_resep = (int)$_GET['id_resep'];
} elseif (isset($_GET['id'])) {
    $id_resep = (int)$_GET['id'];
}

if (!$id_resep) {
    header("Location: resep.php");
    exit();
}

// Get resep info first
$resep_query = "SELECT r.kode_resep, CONCAT(pm.nama_produk,' - ',km.nama_kategori, ' [',pm.kode_produk,']') as nama_produk FROM resep r INNER JOIN produk_menu pm ON r.id_produk = pm.id_produk INNER JOIN kategori_menu km ON pm.id_kategori = km.id_kategori WHERE r.id_resep = ?";
$resep_stmt = $conn->prepare($resep_query);
$resep_stmt->bind_param("i", $id_resep);
$resep_stmt->execute();
$resep_result = $resep_stmt->get_result();
$resep_info = $resep_result->fetch_assoc();

if (!$resep_info) {
    header("Location: resep.php");
    exit();
}

// Check if resep is already published
$publish_query = "SELECT resep.publish_menu FROM resep WHERE id_resep = ? AND publish_menu = 1";
$publish_stmt = $conn->prepare($publish_query);
$publish_stmt->bind_param("i", $id_resep);
$publish_stmt->execute();
$publish_result = $publish_stmt->get_result();
$is_published = $publish_result->num_rows > 0;

// Pagination
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 10;
$offset = ($page - 1) * $limit;

// Search functionality
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$search_condition = '';
$search_params = [];
$param_types = 'i';

if (!empty($search)) {
    $search_condition = " AND (bahan.nama_bahan LIKE ? OR kategori_bahan.nama_kategori LIKE ? OR bahan.kode_bahan LIKE ?)";
    $search_like = "%$search%";
    $search_params = [$search_like, $search_like, $search_like];
    $param_types = 'isss';
}

// Get total records for pagination
$count_query = "SELECT COUNT(*) as total FROM resep_detail rd INNER JOIN bahan b ON rd.id_bahan = b.id_bahan INNER JOIN kategori_bahan kb ON b.id_kategori = kb.id_kategori INNER JOIN bahan_biaya bb ON rd.id_bahan_biaya = bb.id_bahan_biaya WHERE rd.id_resep = ?" . $search_condition;
$count_stmt = $conn->prepare($count_query);
if (!empty($search)) {
    $count_stmt->bind_param($param_types, $id_resep, ...$search_params);
} else {
    $count_stmt->bind_param("i", $id_resep);
}
$count_stmt->execute();
$count_result = $count_stmt->get_result();
$total_records = $count_result->fetch_assoc()['total'];
$total_pages = ceil($total_records / $limit);

// Get resep detail data with the provided query
$sql = "SELECT
    resep_detail.id_resep_detail, 
    resep_detail.id_resep, 
    CONCAT(bahan.nama_bahan, ' [', kategori_bahan.nama_kategori,']',' | ',bahan.kode_bahan) as nama_bahan,
    resep_detail.id_bahan, 
    CONCAT('Rp ',FORMAT(bahan_biaya.harga_satuan,0),'/',bahan_biaya.satuan) as harga_satuan,
    resep_detail.id_bahan_biaya, 
    CONCAT('Rp ',FORMAT(resep_detail.nilai_ekpetasi,0)) as nilai_ekpetasi,
    CONCAT(resep_detail.jumlah_pemakaian,' ',resep_detail.satuan_pemakaian) as satuan_pemakaian
FROM
    resep_detail
    INNER JOIN
    bahan
    ON 
        resep_detail.id_bahan = bahan.id_bahan
    INNER JOIN
    kategori_bahan
    ON 
        bahan.id_kategori = kategori_bahan.id_kategori
    INNER JOIN
    bahan_biaya
    ON 
        resep_detail.id_bahan_biaya = bahan_biaya.id_bahan_biaya
WHERE resep_detail.id_resep = ?" . $search_condition . " ORDER BY bahan.nama_bahan LIMIT ? OFFSET ?";

$stmt = $conn->prepare($sql);
if (!empty($search)) {
    $all_params = array_merge([$id_resep], $search_params, [$limit, $offset]);
    $stmt->bind_param($param_types . 'ii', ...$all_params);
} else {
    $stmt->bind_param("iii", $id_resep, $limit, $offset);
}
$stmt->execute();
$result = $stmt->get_result();
$resep_details = $result->fetch_all(MYSQLI_ASSOC);
?>

<!doctype html>
<html lang="id" data-bs-theme="light">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Detail Resep - Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    <link href="https://cdn.jsdelivr.net/npm/select2-bootstrap-5-theme@1.3.0/dist/select2-bootstrap-5-theme.min.css" rel="stylesheet" />
    <link href="../css/newadmin.css" rel="stylesheet">
</head>
<body>
    <?php include '_header_new.php'; ?>
    <?php include '_sidebar_new.php'; ?>

    <main class="main-content" id="mainContent">
        <div class="container-fluid">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h2 class="mb-1"><i class="bi bi-journal-text me-2"></i>Detail Resep</h2>
                    <p class="text-muted mb-0">
                        <strong>Kode:</strong> <?php echo htmlspecialchars($resep_info['kode_resep']); ?> | 
                        <strong>Produk:</strong> <?php echo htmlspecialchars($resep_info['nama_produk']); ?>
                    </p>
                </div>
                <div>
                    <?php if (!$is_published): ?>
                    <button type="button" class="btn btn-primary me-2" data-bs-toggle="modal" data-bs-target="#detailModal">
                        <i class="bi bi-plus-lg me-2"></i>Tambah Detail
                    </button>
                    <button type="button" class="btn btn-success me-2" id="publishBtn" onclick="publishResep()">
                        <i class="bi bi-check-lg me-2"></i>Publish
                    </button>
                    <?php endif; ?>
                    <a href="resep.php" class="btn btn-secondary">
                        <i class="bi bi-arrow-left me-2"></i>Kembali
                    </a>
                </div>
            </div>

          <!-- Info Cards -->
          <div class="row mb-4">
            <!-- Total Details Card -->
            <div class="col-xl-3 col-md-6 mb-4">
              <div class="card border-left-primary shadow h-100 py-2" style="border-left: 4px solid #4e73df !important;">
                <div class="card-body">
                  <div class="row no-gutters align-items-center">
                    <div class="col mr-2">
                      <div class="text-xs font-weight-bold text-primary text-uppercase mb-1" style="color: #4e73df !important; font-size: 0.8rem; font-weight: 600;">
                        Total Detail
                      </div>
                      <div class="h5 mb-0 font-weight-bold text-gray-800" style="color: #5a5c69 !important;">
                        <?php echo $total_records; ?>
                      </div>
                    </div>
                    <div class="col-auto">
                      <i class="bi bi-list-ul" style="font-size: 2rem; color: #4e73df;"></i>
                    </div>
                  </div>
                </div>
              </div>
            </div>

            <!-- Status Card -->
            <div class="col-xl-3 col-md-6 mb-4">
              <div class="card border-left-<?php echo $is_published ? 'success' : 'warning'; ?> shadow h-100 py-2" style="border-left: 4px solid <?php echo $is_published ? '#1cc88a' : '#f6c23e'; ?> !important;">
                <div class="card-body">
                  <div class="row no-gutters align-items-center">
                    <div class="col mr-2">
                      <div class="text-xs font-weight-bold text-<?php echo $is_published ? 'success' : 'warning'; ?> text-uppercase mb-1" style="color: <?php echo $is_published ? '#1cc88a' : '#f6c23e'; ?> !important; font-size: 0.8rem; font-weight: 600;">
                        Status
                      </div>
                      <div class="h5 mb-0 font-weight-bold text-gray-800" style="color: #5a5c69 !important;">
                        <?php echo $is_published ? 'Published' : 'Draft'; ?>
                      </div>
                    </div>
                    <div class="col-auto">
                      <i class="bi bi-<?php echo $is_published ? 'check-circle-fill' : 'clock-fill'; ?>" style="font-size: 2rem; color: <?php echo $is_published ? '#1cc88a' : '#f6c23e'; ?>;"></i>
                    </div>
                  </div>
                </div>
              </div>
            </div>

            <!-- Current Page Card -->
            <div class="col-xl-3 col-md-6 mb-4">
              <div class="card border-left-info shadow h-100 py-2" style="border-left: 4px solid #36b9cc !important;">
                <div class="card-body">
                  <div class="row no-gutters align-items-center">
                    <div class="col mr-2">
                      <div class="text-xs font-weight-bold text-info text-uppercase mb-1" style="color: #36b9cc !important; font-size: 0.8rem; font-weight: 600;">
                        Halaman
                      </div>
                      <div class="h5 mb-0 font-weight-bold text-gray-800" style="color: #5a5c69 !important;">
                        <?php echo $page; ?> / <?php echo $total_pages; ?>
                      </div>
                    </div>
                    <div class="col-auto">
                      <i class="bi bi-file-text" style="font-size: 2rem; color: #36b9cc;"></i>
                    </div>
                  </div>
                </div>
              </div>
            </div>

            <!-- Kode Resep Card -->
            <div class="col-xl-3 col-md-6 mb-4">
              <div class="card border-left-secondary shadow h-100 py-2" style="border-left: 4px solid #858796 !important;">
                <div class="card-body">
                  <div class="row no-gutters align-items-center">
                    <div class="col mr-2">
                      <div class="text-xs font-weight-bold text-secondary text-uppercase mb-1" style="color: #858796 !important; font-size: 0.8rem; font-weight: 600;">
                        Kode Resep
                      </div>
                      <div class="h5 mb-0 font-weight-bold text-gray-800" style="color: #5a5c69 !important;">
                        <?php echo htmlspecialchars($resep_info['kode_resep']); ?>
                      </div>
                    </div>
                    <div class="col-auto">
                      <i class="bi bi-upc-scan" style="font-size: 2rem; color: #858796;"></i>
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </div>

          <!-- Search -->
          <div class="row mb-4">
            <div class="col-md-4">
              <form method="GET" class="d-flex">
                <input type="hidden" name="id_resep" value="<?php echo $id_resep; ?>">
                <input type="text" class="form-control me-2" name="search" placeholder="Cari bahan, kategori, atau kode..." value="<?php echo htmlspecialchars($search); ?>">
                <button class="btn btn-outline-secondary" type="submit">
                  <i class="bi bi-search"></i>
                </button>
              </form>
            </div>
            <div class="col-md-8 text-end">
              <small class="text-muted">
                Showing <?php echo $offset + 1; ?> to <?php echo min($offset + $limit, $total_records); ?> of <?php echo $total_records; ?> entries
              </small>
            </div>
          </div>

          <!-- Data Table -->
          <div class="card shadow" style="border-radius: 15px; border: none;">
            <div class="card-body p-0">
              <div class="table-responsive">
                <table class="table table-hover mb-0" style="border-radius: 15px; overflow: hidden;">
                  <thead style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white;">
                    <tr>
                      <th class="border-0" style="padding: 1rem 1.5rem;">No</th>
                      <th class="border-0" style="padding: 1rem 1.5rem;">Nama Bahan</th>
                      <th class="border-0 d-none d-md-table-cell" style="padding: 1rem 1.5rem;">Harga Satuan</th>
                      <th class="border-0 d-none d-lg-table-cell" style="padding: 1rem 1.5rem;">Pemakaian</th>
                      <th class="border-0" style="padding: 1rem 1.5rem;">Nilai Ekspetasi</th>
                      <?php if (!$is_published): ?>
                      <th class="border-0 text-center" style="padding: 1rem 1.5rem;">Aksi</th>
                      <?php endif; ?>
                    </tr>
                  </thead>
                  <tbody>
                    <?php if (!empty($resep_details)): ?>
                      <?php foreach ($resep_details as $index => $detail): ?>
                        <tr style="transition: all 0.3s ease; cursor: pointer;">
                          <td class="align-middle" style="padding: 1rem 1.5rem;"><?php echo $offset + $index + 1; ?></td>
                          <td class="align-middle" style="padding: 1rem 1.5rem;">
                            <div>
                              <strong><?php echo htmlspecialchars($detail['nama_bahan']); ?></strong>
                            </div>
                          </td>
                          <td class="align-middle d-none d-md-table-cell" style="padding: 1rem 1.5rem;">
                            <span class="badge" style="background: linear-gradient(135deg, #36b9cc 0%, #258391 100%); color: white; padding: 0.5rem 1rem; font-size: 0.85rem;">
                              <i class="bi bi-tag-fill me-1"></i>
                              <?php echo htmlspecialchars($detail['harga_satuan']); ?>
                            </span>
                          </td>
                          <td class="align-middle d-none d-lg-table-cell" style="padding: 1rem 1.5rem;">
                            <span class="text-muted">
                              <i class="bi bi-speedometer2 me-1"></i>
                              <?php echo htmlspecialchars($detail['satuan_pemakaian']); ?>
                            </span>
                          </td>
                          <td class="align-middle" style="padding: 1rem 1.5rem;">
                            <span class="badge" style="background: linear-gradient(135deg, #1cc88a 0%, #13855c 100%); color: white; padding: 0.5rem 1rem; font-size: 0.85rem;">
                              <i class="bi bi-currency-exchange me-1"></i>
                              <?php echo htmlspecialchars($detail['nilai_ekpetasi']); ?>
                            </span>
                          </td>
                          <?php if (!$is_published): ?>
                          <td class="align-middle text-center" style="padding: 1rem 1.5rem;">
                            <button type="button" class="btn btn-danger btn-sm" style="border-radius: 20px; padding: 0.25rem 0.75rem; background: linear-gradient(135deg, #e74a3b 0%, #c0392b 100%); border: none;" onclick="deleteDetail(<?php echo $detail['id_resep_detail']; ?>)">
                              <i class="bi bi-trash-fill"></i>
                            </button>
                          </td>
                          <?php endif; ?>
                        </tr>
                      <?php endforeach; ?>
                    <?php else: ?>
                      <tr>
                        <td colspan="<?php echo $is_published ? '5' : '6'; ?>" class="text-center" style="padding: 3rem;">
                          <div style="opacity: 0.5;">
                            <i class="bi bi-inbox" style="font-size: 3rem;"></i>
                            <p class="mt-2 mb-0">Tidak ada data detail resep</p>
                          </div>
                        </td>
                      </tr>
                    <?php endif; ?>
                  </tbody>
                </table>
                    </div>
                </div>
                <div class="card-footer bg-light border-top py-3 px-4">
                    <div class="d-flex justify-content-between align-items-center">
                        <small class="text-muted">Menampilkan <?php echo $offset + 1; ?>-<?php echo min($offset + $limit, $total_records); ?> dari <?php echo $total_records; ?> detail</small>
                        <?php if ($total_pages > 1): ?>
                        <nav>
                            <ul class="pagination pagination-sm mb-0">
                                <li class="page-item <?php echo $page <= 1 ? 'disabled' : ''; ?>">
                                    <a class="page-link" href="?id_resep=<?php echo $id_resep; ?>&page=<?php echo $page - 1; ?><?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?>">Previous</a>
                                </li>
                                <?php for ($i = 1; $i <= min($total_pages, 5); $i++): ?>
                                <li class="page-item <?php echo $i == $page ? 'active' : ''; ?>">
                                    <a class="page-link" href="?id_resep=<?php echo $id_resep; ?>&page=<?php echo $i; ?><?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?>"><?php echo $i; ?></a>
                                </li>
                                <?php endfor; ?>
                                <li class="page-item <?php echo $page >= $total_pages ? 'disabled' : ''; ?>">
                                    <a class="page-link" href="?id_resep=<?php echo $id_resep; ?>&page=<?php echo $page + 1; ?><?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?>">Next</a>
                                </li>
                            </ul>
                        </nav>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <!-- Modal New Detail -->
    <div class="modal fade" id="detailModal" tabindex="-1" aria-labelledby="detailModalLabel" aria-hidden="true">
      <div class="modal-dialog modal-lg">
        <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="detailModalLabel">
                        <i class="bi bi-plus-lg me-2"></i>Tambah Detail Resep
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
          <form id="detailForm" method="POST">
            <div class="modal-body">
              <input type="hidden" name="id_resep" value="<?php echo $id_resep; ?>">
              <input type="hidden" name="action" value="add_detail">
              
              <!-- Pilih Bahan -->
              <div class="mb-3">
                <label for="id_bahan" class="form-label">Pilih Bahan <span class="text-danger">*</span></label>
                <select class="form-select" id="id_bahan" name="id_bahan" required>
                  <option value="">Pilih Bahan...</option>
                  <?php
                  $stmt = $conn->prepare("
                      SELECT bb.id_bahan_biaya, bb.id_bahan, b.nama_bahan, kb.nama_kategori, 
                             CONCAT('Rp ', FORMAT(bb.harga_satuan, 0), ' / ', bb.satuan) as harga_satuan
                      FROM bahan_biaya bb
                      JOIN bahan b ON bb.id_bahan = b.id_bahan
                      JOIN kategori_bahan kb ON b.id_kategori = kb.id_kategori
                      ORDER BY b.nama_bahan
                  ");
                  $stmt->execute();
                  $result = $stmt->get_result();
                  while ($row = $result->fetch_assoc()) {
                      echo '<option value="' . $row['id_bahan'] . '" data-biaya="' . $row['id_bahan_biaya'] . '" data-harga="' . $row['harga_satuan'] . '">';
                      echo $row['nama_bahan'] . ' [' . $row['nama_kategori'] . '] - ' . $row['harga_satuan'];
                      echo '</option>';
                  }
                  ?>
                </select>
                <input type="hidden" id="id_bahan_biaya" name="id_bahan_biaya">
              </div>
              
              <!-- Satuan Pemakaian -->
              <div class="mb-3">
                <label for="satuan_pemakaian" class="form-label">Satuan Pemakaian <span class="text-danger">*</span></label>
                <input type="text" class="form-control" id="satuan_pemakaian" name="satuan_pemakaian" placeholder="Contoh: kg, liter, buah, sdm, dll" required>
                <div class="form-text">Masukkan satuan pemakaian (kg, liter, buah, sdm, dll)</div>
              </div>
              
              <!-- Jumlah dan Biaya -->
              <div class="row">
                <div class="col-md-6">
                  <div class="mb-3">
                    <label for="jumlah_pemakaian" class="form-label">Jumlah Pemakaian <span class="text-danger">*</span></label>
                    <input type="number" class="form-control" id="jumlah_pemakaian" name="jumlah_pemakaian" step="0.01" min="0" required>
                  </div>
                </div>
                <div class="col-md-6">
                  <div class="mb-3">
                    <label for="nilai_ekpetasi" class="form-label">Perkiraan Biaya <span class="text-danger">*</span></label>
                    <input type="number" class="form-control" id="nilai_ekpetasi" name="nilai_ekpetasi" step="0.01" min="0" required>
                  </div>
                </div>
              </div>
              
              <!-- Info Harga -->
              <div class="alert alert-info" id="harga_info" style="display: none;">
                <strong>Harga Satuan:</strong> <span id="harga_satuan_text"></span>
              </div>
            </div>
            <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-primary"><i class="bi bi-save me-2"></i>Simpan</button>
                </div>
            </form>
        </div>
    </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
    // Handle bahan selection
    document.getElementById('id_bahan').addEventListener('change', function() {
        const selectedOption = this.options[this.selectedIndex];
        const idBahanBiaya = selectedOption.getAttribute('data-biaya');
        const hargaSatuan = selectedOption.getAttribute('data-harga');
        
        // Set id_bahan_biaya
        document.getElementById('id_bahan_biaya').value = idBahanBiaya || '';
        
        // Show/hide harga info
        if (hargaSatuan) {
            document.getElementById('harga_satuan_text').textContent = hargaSatuan;
            document.getElementById('harga_info').style.display = 'block';
        } else {
            document.getElementById('harga_info').style.display = 'none';
        }
    });
    
    // Handle form submission
    document.getElementById('detailForm').addEventListener('submit', function(e) {
        e.preventDefault();
        
        const formData = new FormData(this);
        
        fetch('resep_detail.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.status === 'success') {
                alert(data.message);
                location.reload();
            } else {
                alert(data.message);
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('Terjadi kesalahan saat menyimpan data');
        });
    });
    
    // Reset form when modal is hidden
    document.getElementById('detailModal').addEventListener('hidden.bs.modal', function() {
        document.getElementById('detailForm').reset();
        document.getElementById('harga_info').style.display = 'none';
        document.getElementById('id_bahan_biaya').value = '';
    });
    
    // Publish resep function
    function publishResep() 
    {
        if (confirm('Yakin, Setelah publish tidak bisa tambah resep lagi?')) {
            const formData = new FormData();
            formData.append('action', 'publish_resep');
            formData.append('id_resep', '<?php echo $id_resep; ?>');
            
            fetch('resep_detail.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.status === 'success') {
                    alert(data.message);
                    window.location.href = 'resep.php';
                } else {
                    alert(data.message);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Terjadi kesalahan saat publish resep');
            });
        }
    }
    
    // Delete detail function
    function deleteDetail(id_resep_detail) {
        if (confirm('Yakin ingin menghapus detail resep ini?')) {
            const formData = new FormData();
            formData.append('action', 'delete_detail');
            formData.append('id_resep_detail', id_resep_detail);
            
            fetch('resep_detail.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.status === 'success') {
                    alert(data.message);
                    location.reload();
                } else {
                    alert(data.message);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Terjadi kesalahan saat menghapus data');
            });
        }
    }
    </script>
</body>
</html>