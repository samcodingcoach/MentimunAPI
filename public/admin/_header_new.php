<?php
// Ambil nama aplikasi dari sesi
$nama_aplikasi = isset($_SESSION['nama_aplikasi']) ? htmlspecialchars($_SESSION['nama_aplikasi']) : 'RESTO ADMIN';
$nama_lengkap = htmlspecialchars($_SESSION["nama_lengkap"]);
$jabatan = htmlspecialchars($_SESSION["jabatan"]);

$notifications = [];
$notification_count = 0;

if (isset($conn)) {
    $notifSql = "SELECT proses_pembayaran.tanggal_payment, proses_pembayaran.kode_payment, proses_pembayaran.jumlah_dibayarkan, metode_pembayaran.kategori FROM proses_pembayaran INNER JOIN metode_pembayaran ON proses_pembayaran.id_bayar = metode_pembayaran.id_bayar WHERE DATE(proses_pembayaran.tanggal_payment) = CURDATE() AND  proses_pembayaran.status = 1 ORDER BY proses_pembayaran.tanggal_payment DESC LIMIT 10";
    if ($resultNotif = $conn->query($notifSql)) {
        while ($row = $resultNotif->fetch_assoc()) {
            $notifications[] = [
                'time' => $row['tanggal_payment'],
                'code' => $row['kode_payment'],
                'amount' => $row['jumlah_dibayarkan'],
                'category' => $row['kategori']
            ];
        }
        $resultNotif->free();
    }
}

$notification_count = count($notifications);

$methodIconMap = [
    'Tunai' => 'bi-cash-stack',
    'Transfer' => 'bi-credit-card',
    'QRIS' => 'bi-qr-code'
];

$methodColorMap = [
    'Tunai' => 'bg-success-subtle text-success',
    'Transfer' => 'bg-primary-subtle text-primary',
    'QRIS' => 'bg-info-subtle text-info'
];
?>
<nav class="navbar navbar-custom fixed-top">
    <div class="container-fluid">
        <div class="d-flex align-items-center">
            <button class="btn btn-toggle me-3" id="toggleSidebar">
                <i class="bi bi-list fs-4"></i>
            </button>
            <a class="navbar-brand" href="index.php">
                <i class="bi bi-shop me-2"></i><?php echo $nama_aplikasi; ?>
            </a>
        </div>

        <div class="d-flex align-items-center gap-3">
            <div class="dropdown">
                <button class="btn btn-link text-white position-relative" title="Notifikasi" id="notificationDropdown" data-bs-toggle="dropdown" aria-expanded="false">
                    <i class="bi bi-bell fs-5"></i>
                    <?php if ($notification_count > 0): ?>
                    <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger">
                        <?php echo $notification_count; ?>
                    </span>
                    <?php endif; ?>
                </button>
                <div class="dropdown-menu dropdown-menu-end shadow-lg p-0" aria-labelledby="notificationDropdown" style="width: 400px; max-height: 400px; overflow-y: auto;">
                    <div class="px-3 py-3 border-bottom d-flex justify-content-between align-items-center">
                        <span class="fw-semibold">Notifikasi</span>
                        <span class="badge bg-primary-subtle text-primary">Hari Ini</span>
                    </div>
                    <?php if ($notification_count === 0): ?>
                        <div class="px-3 py-4 text-center text-muted small">Belum ada notifikasi pembayaran hari ini.</div>
                    <?php else: ?>
                        <div class="list-group list-group-flush">
                            <?php foreach ($notifications as $notification): 
                                $timeLabel = $notification['time'] ? date('H:i', strtotime($notification['time'])) : '-';
                                $iconClass = $methodIconMap[$notification['category']] ?? 'bi-wallet2';
                                $colorClass = $methodColorMap[$notification['category']] ?? 'bg-secondary-subtle text-secondary';
                                $amountFormatted = 'Rp ' . number_format((float)$notification['amount'], 0, ',', '.');
                                $descriptionHtml = sprintf(
                                    'Berhasil melakukan pembayaran: <strong>%s</strong> dengan metode: <strong>%s</strong>',
                                    htmlspecialchars($notification['code']),
                                    htmlspecialchars($notification['category'])
                                );
                            ?>
                            <div class="list-group-item list-group-item-action py-3">
                                <div class="d-flex align-items-start gap-3">
                                    <div class="d-inline-flex align-items-center justify-content-center flex-shrink-0 <?php echo $colorClass; ?>" style="width: 48px; height: 48px; border-radius: 50%; font-size: 1.25rem;">
                                        <i class="bi <?php echo $iconClass; ?>"></i>
                                    </div>
                                    <div class="flex-grow-1">
                                        <div class="d-flex justify-content-between align-items-start mb-2">
                                            <span class="text-wrap text-break me-3" style="max-width: 260px; color: #333; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden;">
                                                <?php echo $descriptionHtml; ?>
                                            </span>
                                            <small class="text-muted flex-shrink-0 ms-auto"><?php echo $timeLabel; ?></small>
                                        </div>
                                        <div class="d-flex justify-content-between align-items-center">
                                            <span class="badge rounded-pill <?php echo $colorClass; ?> px-3 py-2"><?php echo htmlspecialchars($notification['category']); ?></span>
                                            <span class="fw-semibold text-success"><?php echo $amountFormatted; ?></span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <button class="dark-mode-toggle" id="darkModeToggle" title="Toggle Dark Mode">
                <i class="bi bi-moon-stars-fill" id="darkModeIcon"></i>
            </button>

            <div class="dropdown user-dropdown">
                <button class="btn dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                    <i class="bi bi-person-circle me-2"></i>
                    <span class="d-none d-md-inline"><?php echo $nama_lengkap; ?> (<?php echo $jabatan; ?>)</span>
                </button>
                <ul class="dropdown-menu dropdown-menu-end shadow">
                    <li>
                        <a class="dropdown-item" href="profile.php">
                            <i class="bi bi-person me-2"></i> Profil Saya
                        </a>
                    </li>
                    <li>
                        <a class="dropdown-item" href="resto.php">
                            <i class="bi bi-gear me-2"></i> Pengaturan
                        </a>
                    </li>
                    <li>
                        <hr class="dropdown-divider">
                    </li>
                    <li>
                        <a class="dropdown-item text-danger-custom" href="logout.php">
                            <i class="bi bi-box-arrow-right me-2"></i> Logout
                        </a>
                    </li>
                </ul>
            </div>
        </div>
    </div>
</nav>

<div class="sidebar-overlay" id="sidebarOverlay"></div>
