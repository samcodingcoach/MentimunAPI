<?php
session_start();
require_once __DIR__ . '/../../config/koneksi.php';

// Check if user is logged in
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    header('Location: login.php');
    exit();
}

$message = '';
$error = '';

// Get selected date
$selected_date = isset($_GET['tanggal']) ? $_GET['tanggal'] : date('Y-m-d');

// Get date range for Per Kasir tab
$tanggal_awal = isset($_GET['tanggal_awal']) ? $_GET['tanggal_awal'] : date('Y-m-d');
$tanggal_akhir = isset($_GET['tanggal_akhir']) ? $_GET['tanggal_akhir'] : date('Y-m-d');
$selected_kasir = isset($_GET['kasir']) ? $_GET['kasir'] : '';
$kasir_export_query = '';
if (!empty($selected_kasir)) {
    $kasir_export_query = http_build_query([
        'tab' => 'kasir',
        'tanggal_awal' => $tanggal_awal,
        'tanggal_akhir' => $tanggal_akhir,
        'kasir' => $selected_kasir
    ]);
}

// Get parameters for Semua tab
$semua_tanggal1 = isset($_GET['tanggal1']) ? $_GET['tanggal1'] : date('Y-m-d');
$semua_tanggal2 = isset($_GET['tanggal2']) ? $_GET['tanggal2'] : date('Y-m-d');
$semua_kategori = isset($_GET['kategori']) ? $_GET['kategori'] : 'Semua';
$semua_page = isset($_GET['semua_page']) ? (int)$_GET['semua_page'] : 1;
$semua_limit = 15;
$semua_offset = ($semua_page - 1) * $semua_limit;
$semua_export_query = http_build_query([
    'tab' => 'semua',
    'tanggal1' => $semua_tanggal1,
    'tanggal2' => $semua_tanggal2,
    'kategori' => $semua_kategori
]);

// Get active tab
$active_tab = isset($_GET['tab']) ? $_GET['tab'] : 'tunai';

// Query for Tunai (Cash) transactions
$tunai_sql = "
    SELECT
        proses_pembayaran.kode_payment,
        DATE_FORMAT(proses_pembayaran.tanggal_payment,'%H:%i') AS jam,
        DATE_FORMAT(proses_pembayaran.update_status,'%d %M %Y %H:%i') AS waktu_dibayar,
        pegawai.nama_lengkap as kasir,
        FORMAT(proses_pembayaran.jumlah_dibayarkan, 0) AS nominal,
        CASE
            WHEN proses_pembayaran.`status` = 1 THEN 'DIBAYAR'
            WHEN proses_pembayaran.`status` = 0 THEN 'BELUM DIBAYAR'
        END AS status_bayar
    FROM
        proses_pembayaran
    INNER JOIN pegawai ON proses_pembayaran.id_user = pegawai.id_user
    INNER JOIN metode_pembayaran ON proses_pembayaran.id_bayar = metode_pembayaran.id_bayar
    WHERE
        metode_pembayaran.kategori = 'Tunai' AND
        DATE(tanggal_payment) = ?
    ORDER BY proses_pembayaran.tanggal_payment DESC
";

$tunai_stmt = $conn->prepare($tunai_sql);
$tunai_stmt->bind_param("s", $selected_date);
$tunai_stmt->execute();
$tunai_result = $tunai_stmt->get_result();
$tunai_data = $tunai_result->fetch_all(MYSQLI_ASSOC);

// Calculate total for Tunai
$total_tunai = 0;
foreach ($tunai_data as $row) {
    $amount = str_replace(',', '', $row['nominal']);
    $total_tunai += (int)$amount;
}

// Query for Transfer transactions
$transfer_sql = "
    SELECT
        proses_edc.id_tx_bank,
        proses_edc.kode_payment,
        CASE
            WHEN proses_edc.transfer_or_edc = 0 THEN CONCAT('TRANSFER - ', nama_bank, ' A.N ', COALESCE(nama_pengirim, 'NONE'))
            WHEN proses_edc.transfer_or_edc = 1 THEN CONCAT('EDC - ', nama_bank, ' A.N ', COALESCE(nama_pengirim, 'NONE'))
        END AS bank,
        nominal_transfer,
        proses_edc.tanggal_transfer,
        proses_edc.no_referensi,
        proses_edc.img_ss,
        proses_edc.status_pemeriksaan,
        tgl_pemeriksaan
    FROM
        proses_edc
    WHERE
        DATE(tanggal_transfer) = ?
    ORDER BY proses_edc.tanggal_transfer DESC
";

$transfer_stmt = $conn->prepare($transfer_sql);
$transfer_stmt->bind_param("s", $selected_date);
$transfer_stmt->execute();
$transfer_result = $transfer_stmt->get_result();
$transfer_data = $transfer_result->fetch_all(MYSQLI_ASSOC);

// Calculate total for Transfer
$total_transfer = 0;
foreach ($transfer_data as $row) {
    $total_transfer += $row['nominal_transfer'];
}

// Query for QRIS transactions
$qris_sql = "
    SELECT
        proses_pembayaran.kode_payment,
        DATE_FORMAT(proses_pembayaran.tanggal_payment,'%H:%i') AS jam,
        CASE
            WHEN proses_pembayaran.`status` = 1 THEN 'SETTLEMENT'
            WHEN proses_pembayaran.`status` = 0 THEN 'PENDING'
        END AS status_bayar,
        pegawai.nama_lengkap as kasir,
        FORMAT(proses_pembayaran.jumlah_dibayarkan, 0) AS nominal,
        DATE_FORMAT(proses_pembayaran.update_status,'%d %M %Y %H:%i') AS waktu_dibayar
    FROM
        proses_pembayaran
    INNER JOIN pegawai ON proses_pembayaran.id_user = pegawai.id_user
    INNER JOIN metode_pembayaran ON proses_pembayaran.id_bayar = metode_pembayaran.id_bayar
    WHERE
        metode_pembayaran.kategori = 'QRIS' AND
        DATE(tanggal_payment) = ?
    ORDER BY proses_pembayaran.tanggal_payment DESC
";

$qris_stmt = $conn->prepare($qris_sql);
$qris_stmt->bind_param("s", $selected_date);
$qris_stmt->execute();
$qris_result = $qris_stmt->get_result();
$qris_data = $qris_result->fetch_all(MYSQLI_ASSOC);

// Calculate total for QRIS
$total_qris = 0;
foreach ($qris_data as $row) {
    $amount = str_replace(',', '', $row['nominal']);
    $total_qris += (int)$amount;
}

// Query to get list of all active cashiers - simplified as per gemini.md line 44
$kasir_list_sql = "
    SELECT
        pegawai.id_user,
        pegawai.nama_lengkap
    FROM
        pegawai
    WHERE 
        jabatan = 'Kasir' AND 
        aktif = 1 
    ORDER BY 
        nama_lengkap ASC
";

$kasir_list_stmt = $conn->prepare($kasir_list_sql);
$kasir_list_stmt->execute();
$kasir_list_result = $kasir_list_stmt->get_result();
$kasir_list = $kasir_list_result->fetch_all(MYSQLI_ASSOC);

$selected_kasir_name = '';
if (!empty($selected_kasir)) {
    foreach ($kasir_list as $kasir_option) {
        if ($kasir_option['id_user'] == $selected_kasir) {
            $selected_kasir_name = $kasir_option['nama_lengkap'];
            break;
        }
    }
}

// Query for Per Kasir data
$kasir_sql = "
    SELECT 
        state_open_closing.id_open, 
        DATE_FORMAT(tanggal_open, '%d/%m/%Y') as tanggal_open,
        (state_open_closing.first_cash_drawer - state_open_closing.manual_total_cash) AS cash_awal,
        state_open_closing.total_qris AS qris,
        state_open_closing.manual_total_bank AS transfer,
        state_open_closing.manual_total_cash AS cash,
        (state_open_closing.total_qris + state_open_closing.manual_total_bank + state_open_closing.manual_total_cash) + 
        (state_open_closing.first_cash_drawer - state_open_closing.manual_total_cash) AS grand_total,
        DATE(state_open_closing.tanggal_open) AS tanggal_open_raw,
        COALESCE(SUM(CASE WHEN proses_pembayaran.`status` = 1 THEN proses_pembayaran.total_diskon ELSE 0 END), 0) AS total_diskon,
        state_open_closing.id_user,
        pegawai.nama_lengkap AS kasir
    FROM
        state_open_closing
    LEFT JOIN proses_pembayaran ON state_open_closing.id_user = proses_pembayaran.id_user
        AND DATE(proses_pembayaran.tanggal_payment) = DATE(state_open_closing.tanggal_open)
    INNER JOIN pegawai ON state_open_closing.id_user = pegawai.id_user
    WHERE
        DATE(tanggal_open) BETWEEN ? AND ?
        AND state_open_closing.id_user = ?
    GROUP BY state_open_closing.id_open
    ORDER BY tanggal_open DESC
";

$kasir_data = [];
$total_kasir = 0;
if (!empty($selected_kasir)) {
    $kasir_stmt = $conn->prepare($kasir_sql);
    $kasir_stmt->bind_param("sss", $tanggal_awal, $tanggal_akhir, $selected_kasir);
    $kasir_stmt->execute();
    $kasir_result = $kasir_stmt->get_result();
    $kasir_data = $kasir_result->fetch_all(MYSQLI_ASSOC);
    
// Calculate total for Per Kasir
    foreach ($kasir_data as $row) {
        $total_kasir += $row['grand_total'];
    }
}

if ($active_tab === 'kasir' && isset($_GET['export'])) {
    $export_type = $_GET['export'];

    if (empty($selected_kasir)) {
        http_response_code(400);
        exit('Silakan pilih kasir terlebih dahulu.');
    }

    $kasir_export_stmt = $conn->prepare($kasir_sql);
    if (!$kasir_export_stmt) {
        error_log('Kasir export prepare failed: ' . $conn->error);
        http_response_code(500);
        exit('Terjadi kesalahan saat mempersiapkan data.');
    }

    $kasir_export_stmt->bind_param("sss", $tanggal_awal, $tanggal_akhir, $selected_kasir);
    if (!$kasir_export_stmt->execute()) {
        error_log('Kasir export execute failed: ' . $kasir_export_stmt->error);
        http_response_code(500);
        exit('Terjadi kesalahan saat mengambil data.');
    }

    $kasir_export_result = $kasir_export_stmt->get_result();
    $kasir_export_data = $kasir_export_result->fetch_all(MYSQLI_ASSOC);

    if ($export_type === 'pdf') {
        require_once __DIR__ . '/../libs/fpdf/fpdf.php';

        $convertText = static function ($text) {
            $text = (string)$text;
            if (function_exists('iconv')) {
                $converted = @iconv('UTF-8', 'ISO-8859-1//TRANSLIT', $text);
                if ($converted !== false) {
                    return $converted;
                }
            }
            return preg_replace('/[^\x00-\xFF]/', '', $text);
        };

        $formatCurrency = static function ($value) use ($convertText) {
            $amount = is_numeric($value) ? (float)$value : 0.0;
            return $convertText('Rp ' . number_format($amount, 0, ',', '.'));
        };

        while (ob_get_level() > 0) {
            ob_end_clean();
        }

        try {
            $pdf = new FPDF('L', 'mm', 'A4');
            $pdf->AddPage();
            $pdf->SetFont('Arial', 'B', 16);
            $pdf->Cell(0, 10, $convertText('Laporan Per Kasir'), 0, 1, 'C');
            $pdf->SetFont('Arial', '', 11);
            $pdf->Cell(0, 7, $convertText('Periode: ' . $tanggal_awal . ' s/d ' . $tanggal_akhir), 0, 1, 'C');
            $pdf->Cell(0, 7, $convertText('Kasir: ' . ($selected_kasir_name ?: '-')), 0, 1, 'C');
            $pdf->Cell(0, 7, $convertText('Diunduh: ' . date('d M Y H:i')), 0, 1, 'C');
            $pdf->Ln(4);

            $headers = ['No', 'Tanggal', 'Kasir', 'Cash Awal', 'QRIS', 'Transfer', 'Cash', 'Diskon', 'Grand Total'];
            $widths = [12, 32, 40, 30, 30, 30, 30, 30, 38];
            $alignments = ['C', 'L', 'L', 'R', 'R', 'R', 'R', 'R', 'R'];

            $pdf->SetFillColor(230, 230, 230);
            $pdf->SetFont('Arial', 'B', 11);
            foreach ($headers as $idx => $header) {
                $pdf->Cell($widths[$idx], 10, $convertText($header), 1, 0, 'C', true);
            }
            $pdf->Ln();

            $pdf->SetFont('Arial', '', 10);
            $summaryTotals = [
                'cash_awal' => 0.0,
                'qris' => 0.0,
                'transfer' => 0.0,
                'cash' => 0.0,
                'total_diskon' => 0.0,
                'grand_total' => 0.0,
            ];

            if (!empty($kasir_export_data)) {
                foreach ($kasir_export_data as $i => $row) {
                    $cash_awal_value = is_numeric($row['cash_awal']) ? (float)$row['cash_awal'] : 0.0;
                    $qris_value = is_numeric($row['qris']) ? (float)$row['qris'] : 0.0;
                    $transfer_value = is_numeric($row['transfer']) ? (float)$row['transfer'] : 0.0;
                    $cash_value = is_numeric($row['cash']) ? (float)$row['cash'] : 0.0;
                    $diskon_value = is_numeric($row['total_diskon']) ? (float)$row['total_diskon'] : 0.0;
                    $grand_total_value = is_numeric($row['grand_total']) ? (float)$row['grand_total'] : 0.0;

                    $summaryTotals['cash_awal'] += $cash_awal_value;
                    $summaryTotals['qris'] += $qris_value;
                    $summaryTotals['transfer'] += $transfer_value;
                    $summaryTotals['cash'] += $cash_value;
                    $summaryTotals['total_diskon'] += $diskon_value;
                    $summaryTotals['grand_total'] += $grand_total_value;

                    $cells = [
                        $i + 1,
                        $row['tanggal_open'],
                        $row['kasir'],
                        $formatCurrency($cash_awal_value),
                        $formatCurrency($qris_value),
                        $formatCurrency($transfer_value),
                        $formatCurrency($cash_value),
                        $formatCurrency($diskon_value),
                        $formatCurrency($grand_total_value),
                    ];

                    foreach ($cells as $idx => $cell) {
                        $pdf->Cell($widths[$idx], 8, $convertText($cell), 1, 0, $alignments[$idx]);
                    }
                    $pdf->Ln();
                }

                $pdf->SetFont('Arial', 'B', 10);
                $labelWidth = $widths[0] + $widths[1] + $widths[2];
                $pdf->Cell($labelWidth, 8, $convertText('Total'), 1, 0, 'R');

                $totalColumns = [
                    $summaryTotals['cash_awal'],
                    $summaryTotals['qris'],
                    $summaryTotals['transfer'],
                    $summaryTotals['cash'],
                    $summaryTotals['total_diskon'],
                    $summaryTotals['grand_total'],
                ];

                for ($idx = 3; $idx < count($widths); $idx++) {
                    $valueIndex = $idx - 3;
                    $pdf->Cell(
                        $widths[$idx],
                        8,
                        $convertText($formatCurrency($totalColumns[$valueIndex])),
                        1,
                        $idx === count($widths) - 1 ? 1 : 0,
                        'R'
                    );
                }
            } else {
                $pdf->Cell(array_sum($widths), 8, $convertText('Tidak ada data untuk filter ini'), 1, 1, 'C');
            }

            $filename = 'laporan_kasir_' . date('Ymd_His') . '.pdf';
            $pdf->Output('D', $filename);
        } catch (Throwable $e) {
            error_log('Kasir PDF export failed: ' . $e->getMessage());
            http_response_code(500);
            exit('Terjadi kesalahan saat membuat PDF.');
        }
        exit;
    }

    if ($export_type === 'excel') {
        header('Content-Type: application/vnd.ms-excel; charset=utf-8');
        header('Content-Disposition: attachment; filename="laporan_kasir_' . date('Ymd_His') . '.xls"');
        header('Pragma: no-cache');
        header('Expires: 0');

        echo "<table border='1'>";
        echo '<thead><tr>';
        echo '<th>No</th><th>Tanggal</th><th>Kasir</th><th>Cash Awal</th><th>QRIS</th><th>Transfer</th><th>Cash</th><th>Diskon</th><th>Grand Total</th>';
        echo '</tr></thead><tbody>';

        $summaryTotals = [
            'cash_awal' => 0.0,
            'qris' => 0.0,
            'transfer' => 0.0,
            'cash' => 0.0,
            'total_diskon' => 0.0,
            'grand_total' => 0.0,
        ];

        if (!empty($kasir_export_data)) {
            foreach ($kasir_export_data as $i => $row) {
                $cash_awal_value = is_numeric($row['cash_awal']) ? (float)$row['cash_awal'] : 0.0;
                $qris_value = is_numeric($row['qris']) ? (float)$row['qris'] : 0.0;
                $transfer_value = is_numeric($row['transfer']) ? (float)$row['transfer'] : 0.0;
                $cash_value = is_numeric($row['cash']) ? (float)$row['cash'] : 0.0;
                $diskon_value = is_numeric($row['total_diskon']) ? (float)$row['total_diskon'] : 0.0;
                $grand_total_value = is_numeric($row['grand_total']) ? (float)$row['grand_total'] : 0.0;

                $summaryTotals['cash_awal'] += $cash_awal_value;
                $summaryTotals['qris'] += $qris_value;
                $summaryTotals['transfer'] += $transfer_value;
                $summaryTotals['cash'] += $cash_value;
                $summaryTotals['total_diskon'] += $diskon_value;
                $summaryTotals['grand_total'] += $grand_total_value;

                echo '<tr>';
                echo '<td>' . ($i + 1) . '</td>';
                echo '<td>' . htmlspecialchars($row['tanggal_open'], ENT_QUOTES, 'UTF-8') . '</td>';
                echo '<td>' . htmlspecialchars($row['kasir'], ENT_QUOTES, 'UTF-8') . '</td>';
                echo '<td>' . $cash_awal_value . '</td>';
                echo '<td>' . $qris_value . '</td>';
                echo '<td>' . $transfer_value . '</td>';
                echo '<td>' . $cash_value . '</td>';
                echo '<td>' . $diskon_value . '</td>';
                echo '<td>' . $grand_total_value . '</td>';
                echo '</tr>';
            }

            echo '<tr>';
            echo '<td colspan="3"><strong>Total</strong></td>';
            echo '<td><strong>' . $summaryTotals['cash_awal'] . '</strong></td>';
            echo '<td><strong>' . $summaryTotals['qris'] . '</strong></td>';
            echo '<td><strong>' . $summaryTotals['transfer'] . '</strong></td>';
            echo '<td><strong>' . $summaryTotals['cash'] . '</strong></td>';
            echo '<td><strong>' . $summaryTotals['total_diskon'] . '</strong></td>';
            echo '<td><strong>' . $summaryTotals['grand_total'] . '</strong></td>';
            echo '</tr>';
        } else {
            echo "<tr><td colspan='9'>Tidak ada data untuk filter ini</td></tr>";
        }

        echo '</tbody></table>';
        exit;
    }
}

// Query for Semua tab
$semua_where = "DATE(proses_pembayaran.tanggal_payment) BETWEEN ? AND ?";
$semua_params = [$semua_tanggal1, $semua_tanggal2];
$semua_types = "ss";

if ($semua_kategori !== 'Semua') {
    $semua_where .= " AND metode_pembayaran.kategori = ?";
    $semua_params[] = $semua_kategori;
    $semua_types .= "s";
}

$semua_export_params = $semua_params;
$semua_export_types = $semua_types;

if ($active_tab === 'semua' && isset($_GET['export'])) {
    $export_type = $_GET['export'];
    $semua_export_sql = "
        SELECT
            proses_pembayaran.kode_payment AS kode,
            proses_pembayaran.id_tagihan,
            proses_pembayaran.jumlah_dibayarkan AS total,
            metode_pembayaran.kategori
        FROM
            proses_pembayaran
        INNER JOIN
            metode_pembayaran ON proses_pembayaran.id_bayar = metode_pembayaran.id_bayar
        WHERE
            $semua_where
        ORDER BY proses_pembayaran.tanggal_payment DESC
    ";

    if (!empty($semua_export_params)) {
        $semua_export_stmt = $conn->prepare($semua_export_sql);
        if (!$semua_export_stmt) {
            error_log('Semua export prepare failed: ' . $conn->error);
            http_response_code(500);
            exit('Terjadi kesalahan saat mempersiapkan data.');
        }
        $semua_export_stmt->bind_param($semua_export_types, ...$semua_export_params);
        if (!$semua_export_stmt->execute()) {
            error_log('Semua export execute failed: ' . $semua_export_stmt->error);
            http_response_code(500);
            exit('Terjadi kesalahan saat mengambil data.');
        }
        $semua_export_result = $semua_export_stmt->get_result();
    } else {
        $semua_export_result = $conn->query($semua_export_sql);
        if (!$semua_export_result) {
            error_log('Semua export query failed: ' . $conn->error);
            http_response_code(500);
            exit('Terjadi kesalahan saat mengambil data.');
        }
    }

    $semua_export_data = $semua_export_result->fetch_all(MYSQLI_ASSOC);

    if ($export_type === 'pdf') {
        require_once __DIR__ . '/../libs/fpdf/fpdf.php';

        $convertText = static function ($text) {
            $text = (string)$text;
            if (function_exists('iconv')) {
                $converted = @iconv('UTF-8', 'ISO-8859-1//TRANSLIT', $text);
                if ($converted !== false) {
                    return $converted;
                }
            }
            return preg_replace('/[^\x00-\xFF]/', '', $text);
        };

        $formatCurrency = static function ($value) use ($convertText) {
            $amount = is_numeric($value) ? (float)$value : 0.0;
            return $convertText('Rp ' . number_format($amount, 0, ',', '.'));
        };

        while (ob_get_level() > 0) {
            ob_end_clean();
        }

        try {
            $pdf = new FPDF('L', 'mm', 'A4');
            $pdf->AddPage();
            $pdf->SetFont('Arial', 'B', 16);
            $pdf->Cell(0, 10, $convertText('Laporan Semua Transaksi'), 0, 1, 'C');
            $pdf->SetFont('Arial', '', 11);
            $subtitle = 'Periode: ' . $semua_tanggal1 . ' s/d ' . $semua_tanggal2;
            if ($semua_kategori !== 'Semua') {
                $subtitle .= ' | Kategori: ' . $semua_kategori;
            }
            $pdf->Cell(0, 7, $convertText($subtitle), 0, 1, 'C');
            $pdf->Cell(0, 7, $convertText('Diunduh: ' . date('d M Y H:i')), 0, 1, 'C');
            $pdf->Ln(4);

            $headers = ['No', 'Kode', 'ID Tagihan', 'Kategori', 'Total'];
            $widths = [15, 80, 80, 40, 50];
            $alignments = ['C', 'L', 'L', 'C', 'R'];

            $pdf->SetFillColor(230, 230, 230);
            $pdf->SetFont('Arial', 'B', 11);
            foreach ($headers as $idx => $header) {
                $pdf->Cell($widths[$idx], 10, $convertText($header), 1, 0, 'C', true);
            }
            $pdf->Ln();

            $pdf->SetFont('Arial', '', 10);
            $grand_total_export = 0.0;
            if (!empty($semua_export_data)) {
                foreach ($semua_export_data as $i => $row) {
                    $numeric_total = is_numeric($row['total']) ? (float)$row['total'] : 0.0;
                    $grand_total_export += $numeric_total;
                    $cells = [
                        $i + 1,
                        $row['kode'],
                        $row['id_tagihan'],
                        $row['kategori'],
                        $formatCurrency($numeric_total) . ' '
                    ];

                    foreach ($cells as $idx => $cell) {
                        $pdf->Cell($widths[$idx], 8, $convertText($cell), 1, 0, $alignments[$idx]);
                    }
                    $pdf->Ln();
                }

                $pdf->SetFont('Arial', 'B', 10);
                $label_width = $widths[0] + $widths[1] + $widths[2] + $widths[3];
                $pdf->Cell($label_width, 8, $convertText('Grand Total'), 1, 0, 'R');
                $pdf->Cell($widths[4], 8, $convertText($formatCurrency($grand_total_export) . ' '), 1, 1, 'R');
            } else {
                $pdf->Cell(array_sum($widths), 8, $convertText('Tidak ada data untuk filter ini'), 1, 1, 'C');
            }

            $filename = 'laporan_semua_transaksi_' . date('Ymd_His') . '.pdf';
            $pdf->Output('D', $filename);
        } catch (Throwable $e) {
            error_log('Semua PDF export failed: ' . $e->getMessage());
            http_response_code(500);
            exit('Terjadi kesalahan saat membuat PDF.');
        }
        exit;
    }

    if ($export_type === 'excel') {
        header('Content-Type: application/vnd.ms-excel; charset=utf-8');
        header('Content-Disposition: attachment; filename="laporan_semua_transaksi_' . date('Ymd_His') . '.xls"');
        header('Pragma: no-cache');
        header('Expires: 0');

        echo "<table border='1'>";
        echo '<thead><tr>';
        echo '<th>No</th><th>Kode</th><th>ID Tagihan</th><th>Kategori</th><th>Total</th>';
        echo '</tr></thead><tbody>';

        $grand_total_export = 0.0;
        if (!empty($semua_export_data)) {
            foreach ($semua_export_data as $i => $row) {
                $total_value = is_numeric($row['total']) ? (float)$row['total'] : 0;
                $grand_total_export += $total_value;
                echo '<tr>';
                echo '<td>' . ($i + 1) . '</td>';
                echo '<td>' . htmlspecialchars($row['kode'], ENT_QUOTES, 'UTF-8') . '</td>';
                echo '<td>' . htmlspecialchars($row['id_tagihan'], ENT_QUOTES, 'UTF-8') . '</td>';
                echo '<td>' . htmlspecialchars($row['kategori'], ENT_QUOTES, 'UTF-8') . '</td>';
                echo '<td>' . $total_value . '</td>';
                echo '</tr>';
            }
            echo '<tr>';
            echo '<td colspan="4"><strong>Grand Total</strong></td>';
            echo '<td><strong>' . $grand_total_export . '</strong></td>';
            echo '</tr>';
        } else {
            echo "<tr><td colspan='5'>Tidak ada data untuk filter ini</td></tr>";
        }

        echo '</tbody></table>';
        exit;
    }
}

// Main query for Semua tab
$semua_sql = "
    SELECT
        proses_pembayaran.kode_payment AS kode,
        proses_pembayaran.id_tagihan,
        proses_pembayaran.jumlah_dibayarkan AS total,
        metode_pembayaran.kategori
    FROM
        proses_pembayaran
    INNER JOIN
        metode_pembayaran ON proses_pembayaran.id_bayar = metode_pembayaran.id_bayar
    WHERE
        $semua_where
    ORDER BY proses_pembayaran.tanggal_payment DESC
    LIMIT ? OFFSET ?
";

$semua_params[] = $semua_limit;
$semua_params[] = $semua_offset;
$semua_types .= "ii";

$semua_stmt = $conn->prepare($semua_sql);
$semua_stmt->bind_param($semua_types, ...$semua_params);
$semua_stmt->execute();
$semua_result = $semua_stmt->get_result();
$semua_data = $semua_result->fetch_all(MYSQLI_ASSOC);

// Count query for pagination
$semua_count_sql = "
    SELECT COUNT(*) as total
    FROM
        proses_pembayaran
    INNER JOIN
        metode_pembayaran ON proses_pembayaran.id_bayar = metode_pembayaran.id_bayar
    WHERE
        $semua_where
";

$count_params = array_slice($semua_params, 0, -2); // Remove limit and offset
$count_types = substr($semua_types, 0, -2);

$semua_count_stmt = $conn->prepare($semua_count_sql);
if (!empty($count_params)) {
    $semua_count_stmt->bind_param($count_types, ...$count_params);
}
$semua_count_stmt->execute();
$semua_count_result = $semua_count_stmt->get_result();
$semua_total_records = $semua_count_result->fetch_assoc()['total'];
$semua_total_pages = ceil($semua_total_records / $semua_limit);

// Calculate total amount for Semua
$total_semua = 0;
foreach ($semua_data as $row) {
    $total_semua += $row['total'];
}

$total_pembayaran_harian = $total_tunai + $total_transfer + $total_qris;
$total_jenis_transaksi = count($tunai_data) + count($transfer_data) + count($qris_data);
?>

<!doctype html>
<html lang="id" data-bs-theme="light">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Laporan Transaksi - Admin</title>
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
                    <h2 class="mb-1"><i class="bi bi-list-ul me-2"></i>Laporan Transaksi</h2>
                    <p class="text-muted mb-0">Pantau transaksi harian Anda dengan tampilan modern</p>
                </div>
                <div class="d-flex gap-2">
                    <span class="badge bg-secondary-subtle text-secondary px-3 py-2">
                        <i class="bi bi-calendar-week me-1"></i><?php echo date('d M Y', strtotime($selected_date)); ?>
                    </span>
                    <span class="badge bg-secondary-subtle text-secondary px-3 py-2">
                        <i class="bi bi-person-lines-fill me-1"></i><?php echo $selected_kasir_name ? 'Kasir: ' . htmlspecialchars($selected_kasir_name) : 'Semua Kasir'; ?>
                    </span>
                </div>
            </div>

            <div class="card-modern mb-4">
                <div class="card-header px-4 py-3">
                    <div class="d-flex align-items-center">
                        <i class="bi bi-funnel me-2"></i>
                        <span>Filter Harian</span>
                    </div>
                </div>
                <div class="card-body">
                    <form method="GET" class="row g-3 align-items-end">
                        <div class="col-md-4">
                           
                            <input type="text" class="form-control" id="tanggal" name="tanggal" value="<?php echo htmlspecialchars($selected_date); ?>" required>
                        </div>
                        <div class="col-md-2">
                            <button type="submit" class="btn btn-primary">
                                <i class="bi bi-search me-2"></i>Tampilkan
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <div class="card-modern">
                <div class="card-header px-4 py-3">
                    <div class="d-flex flex-wrap justify-content-between align-items-center gap-3">
                        <div class="d-flex align-items-center">
                            <i class="bi bi-diagram-3 me-2"></i>
                            <span>Rincian Transaksi</span>
                        </div>
                        <ul class="nav nav-pills gap-2" id="transactionTabs" role="tablist">
                            <li class="nav-item" role="presentation">
                                <button class="nav-link active" id="tunai-tab" data-bs-toggle="tab" data-bs-target="#tunai" type="button" role="tab">
                                    <i class="bi bi-cash me-1"></i>Tunai
                                </button>
                            </li>
                            <li class="nav-item" role="presentation">
                                <button class="nav-link" id="transfer-tab" data-bs-toggle="tab" data-bs-target="#transfer" type="button" role="tab">
                                    <i class="bi bi-bank me-1"></i>Transfer
                                </button>
                            </li>
                            <li class="nav-item" role="presentation">
                                <button class="nav-link" id="qris-tab" data-bs-toggle="tab" data-bs-target="#qris" type="button" role="tab">
                                    <i class="bi bi-qr-code me-1"></i>QRIS
                                </button>
                            </li>
                            <li class="nav-item" role="presentation">
                                <button class="nav-link" id="kasir-tab" data-bs-toggle="tab" data-bs-target="#kasir" type="button" role="tab">
                                    <i class="bi bi-person-badge me-1"></i>Per Kasir
                                </button>
                            </li>
                            <li class="nav-item" role="presentation">
                                <button class="nav-link" id="semua-tab" data-bs-toggle="tab" data-bs-target="#semua" type="button" role="tab">
                                    <i class="bi bi-list-ul me-1"></i>Semua
                                </button>
                            </li>
                            <li class="nav-item" role="presentation">
                                <button class="nav-link" id="summary-tab" data-bs-toggle="tab" data-bs-target="#summary" type="button" role="tab">
                                    <i class="bi bi-journal-check me-1"></i>Ringkasan
                                </button>
                            </li>
                        </ul>
                    </div>
                </div>
                <div class="card-body p-0">
                    <div class="tab-content" id="transactionTabsContent">

          <!-- Tab Content -->
          <div class="tab-content" id="transactionTabsContent">
            <!-- Tunai Tab -->
            <div class="tab-pane fade show active" id="tunai" role="tabpanel" aria-labelledby="tunai-tab">
                <div class="px-0">
                    
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-light text-center">
                                <tr>
                                    <th style="width:5%;">No</th>
                                    <th style="width:auto; text-align:left;">Kode Payment</th>
                                    <th class="text-center" style="width:10%;">Jam</th>
                                    <th style="width:10%; text-align:left;">Kasir</th>
                                    <th class="text-center" style="width:10%;">Status</th>
                                    <th class="text-end" style="width:15%;">Nominal</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($tunai_data)): ?>
                                <tr>
                                    <td colspan="6" class="text-center py-5">
                                        <i class="bi bi-inbox fs-1 d-block mb-2 text-muted"></i>
                                        <span class="text-muted">Tidak ada transaksi tunai untuk tanggal <?php echo date('d F Y', strtotime($selected_date)); ?></span>
                                    </td>
                                </tr>
                                <?php else: ?>
                                    <?php $no = 1; foreach ($tunai_data as $row): ?>
                                    <tr>
                                        <td class="text-center fw-semibold"><?php echo $no++; ?></td>
                                        <td class="fw-semibold">&num;<?php echo htmlspecialchars($row['kode_payment']); ?></td>
                                        <td class="text-center text-nowrap"><?php echo htmlspecialchars($row['jam']); ?></td>
                                        <td><?php echo htmlspecialchars($row['kasir']); ?></td>
                                        <td class="text-center">
                                            <?php if ($row['status_bayar'] == 'DIBAYAR'): ?>
                                                <span class="badge bg-success-subtle text-success px-3 py-2">
                                                    <i class="bi bi-check-circle me-1"></i>DIBAYAR
                                                </span>
                                            <?php else: ?>
                                                <span class="badge bg-warning-subtle text-warning px-3 py-2">
                                                    <i class="bi bi-clock me-1"></i>BELUM DIBAYAR
                                                </span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="text-end fw-semibold">Rp <?php echo htmlspecialchars($row['nominal']); ?></td>
                                    </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                    <?php if (!empty($tunai_data)): ?>
                    <div class="d-flex flex-wrap justify-content-between gap-2 mt-3 px-4 py-2 align-items-center">
                        <div class="badge bg-secondary-subtle text-secondary px-3 py-2">
                            <i class="bi bi-123 me-1"></i><?php echo count($tunai_data); ?> transaksi
                        </div>
                        <div class="badge bg-success-subtle text-success px-3 py-2">
                            <i class="bi bi-calculator me-1"></i>Total Tunai: Rp <?php echo number_format($total_tunai, 0, ',', '.'); ?>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Transfer Tab -->
            <div class="tab-pane fade" id="transfer" role="tabpanel" aria-labelledby="transfer-tab">
                <div class="px-0">
                    
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-light text-center">
                                <tr>
                                    <th style="width:5%; text-align:left;" >No</th>
                                    <th style="width:auto; text-align:left;">Kode Payment</th>
                                    <th style="width:15%; text-align:left;">Bank / Metode</th>
                                    <th class="text-center" style="width:10%;">Tanggal</th>
                                    <th class="text-center" style="width:10%;">Status</th>
                                    <th class="text-end" style="width:15%;">Nominal</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($transfer_data)): ?>
                                <tr>
                                    <td colspan="6" class="text-center py-5">
                                        <i class="bi bi-inbox fs-1 d-block mb-2 text-muted"></i>
                                        <span class="text-muted">Tidak ada transaksi transfer untuk tanggal <?php echo date('d F Y', strtotime($selected_date)); ?></span>
                                    </td>
                                </tr>
                                <?php else: ?>
                                    <?php $no = 1; foreach ($transfer_data as $row): ?>
                                    <tr>
                                        <td class="text-left fw-semibold"><?php echo $no++; ?></td>
                                        <td class="fw-semibold">&num;<?php echo htmlspecialchars($row['kode_payment']); ?></td>
                                        <td><?php echo htmlspecialchars($row['bank']); ?></td>
                                        <td class="text-center text-nowrap"><?php echo date('d/m/Y H:i', strtotime($row['tanggal_transfer'])); ?></td>
                                        <td class="text-center">
                                            <a href="#" class="text-decoration-none" onclick="showTransferDetail(
                                                '<?php echo addslashes($row['tanggal_transfer']); ?>',
                                                '<?php echo addslashes($row['no_referensi'] ?? '-'); ?>',
                                                '<?php echo addslashes($row['img_ss'] ?? ''); ?>',
                                                '<?php echo $row['status_pemeriksaan']; ?>',
                                                '<?php echo addslashes($row['tgl_pemeriksaan'] ?? ''); ?>'
                                            )" data-bs-toggle="modal" data-bs-target="#transferDetailModal">
                                                <?php if ($row['status_pemeriksaan'] == 1): ?>
                                                    <span class="badge bg-success-subtle text-success px-3 py-2">
                                                        <i class="bi bi-check-circle me-1"></i>DITERIMA
                                                    </span>
                                                <?php elseif ($row['status_pemeriksaan'] == 2): ?>
                                                    <span class="badge bg-danger-subtle text-danger px-3 py-2">
                                                        <i class="bi bi-x-circle me-1"></i>PALSU
                                                    </span>
                                                <?php else: ?>
                                                    <span class="badge bg-warning-subtle text-warning px-3 py-2">
                                                        <i class="bi bi-clock me-1"></i>BELUM DITERIMA
                                                    </span>
                                                <?php endif; ?>
                                            </a>
                                        </td>
                                        <td class="text-end fw-semibold text-primary">Rp <?php echo number_format($row['nominal_transfer'], 0, ',', '.'); ?></td>
                                    </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                    <?php if (!empty($transfer_data)): ?>
                    <div class="d-flex justify-content-end mt-3 px-4 py-2">
                        <div class="badge bg-primary-subtle text-primary px-4 py-2">
                            <i class="bi bi-calculator me-1"></i>Total Transfer: Rp <?php echo number_format($total_transfer, 0, ',', '.'); ?>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- QRIS Tab -->
            <div class="tab-pane fade" id="qris" role="tabpanel" aria-labelledby="qris-tab">
                <div class="px-0">
                    
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-light text-center">
                                <tr>
                                    <th style="width:5%; text-align:left;">No</th>
                                    <th style="width:auto; text-align:left;">Kode Payment</th>
                                    <th class="text-center" style="width:10%;">Jam</th>
                                    <th style="width:15%; text-align:left;">Kasir</th>
                                    <th class="text-center" style="width:15%;">Status Bayar</th>
                                    <th class="text-end" style="width:15%;">Nominal</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($qris_data)): ?>
                                <tr>
                                    <td colspan="6" class="text-center py-5">
                                        <i class="bi bi-inbox fs-1 d-block mb-2 text-muted"></i>
                                        <span class="text-muted">Tidak ada transaksi QRIS untuk tanggal <?php echo date('d F Y', strtotime($selected_date)); ?></span>
                                    </td>
                                </tr>
                                <?php else: ?>
                                    <?php $no = 1; foreach ($qris_data as $row): ?>
                                    <tr>
                                        <td class="text-start fw-semibold"><?php echo $no++; ?></td>
                                        <td class="fw-semibold">&num;<?php echo htmlspecialchars($row['kode_payment']); ?></td>
                                        <td class="text-center text-nowrap"><?php echo htmlspecialchars($row['jam']); ?></td>
                                        <td class="text-start"><?php echo htmlspecialchars($row['kasir']); ?></td>
                                        <td class="text-center">
                                            <?php if ($row['status_bayar'] == 'SETTLEMENT'): ?>
                                                <span class="badge bg-success-subtle text-success px-3 py-2">
                                                    <i class="bi bi-check-circle me-1"></i>SETTLEMENT
                                                </span>
                                            <?php else: ?>
                                                <span class="badge bg-warning-subtle text-warning px-3 py-2">
                                                    <i class="bi bi-clock me-1"></i>PENDING
                                                </span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="text-end fw-semibold">Rp <?php echo htmlspecialchars($row['nominal']); ?></td>
                                    </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                    <?php if (!empty($qris_data)): ?>
                    <div class="d-flex justify-content-end mt-3 px-4 py-2">
                        <div class="badge bg-warning-subtle text-warning px-3 py-2">
                            <i class="bi bi-calculator me-1"></i>Total QRIS: Rp <?php echo number_format($total_qris, 0, ',', '.'); ?>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Per Kasir Tab -->

                        <div class="tab-pane fade" id="kasir" role="tabpanel" aria-labelledby="kasir-tab">
                            <div class="px-0">
                                
                                <div class="card-modern border-0 shadow-none">
                                    <div class="card-body px-0">
                                        <form method="GET" class="row g-3 align-items-end px-4 py-2"  id="kasirFilterForm">
                                            <input type="hidden" name="tab" value="kasir">
                                            <div class="col-12 col-md-3 col-lg-2">
                                                <label for="tanggal_awal" class="form-label">Tanggal Awal</label>
                                                <input type="text" class="form-control" id="tanggal_awal" name="tanggal_awal" value="<?php echo htmlspecialchars($tanggal_awal); ?>">
                                            </div>
                                            <div class="col-12 col-md-3 col-lg-2">
                                                <label for="tanggal_akhir" class="form-label">Tanggal Akhir</label>
                                                <input type="text" class="form-control" id="tanggal_akhir" name="tanggal_akhir" value="<?php echo htmlspecialchars($tanggal_akhir); ?>">
                                            </div>
                                            <div class="col-12 col-md-3 col-lg-3">
                                                <label for="kasir_select" class="form-label">Pilih Kasir</label>
                                                <select class="form-select" id="kasir_select" name="kasir">
                                                    <option value="">-- Pilih Kasir --</option>
                                                    <?php foreach ($kasir_list as $kasir): ?>
                                                        <option value="<?php echo htmlspecialchars($kasir['id_user']); ?>" <?php echo ($selected_kasir == $kasir['id_user']) ? 'selected' : ''; ?>>
                                                            <?php echo htmlspecialchars($kasir['nama_lengkap']); ?>
                                                        </option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </div>
                                            <div class="col-12 col-md-3 col-lg-2">
                                                <button type="submit" class="btn btn-primary w-100">
                                                    <i class="bi bi-search me-2"></i>Tampilkan
                                                </button>
                                            </div>
                                            <?php if (!empty($selected_kasir) && !empty($kasir_data)): ?>
                                            <div class="col-12 col-md-6 col-lg-3">
                                                <div class="d-flex flex-wrap gap-2">
                                                    <a href="?export=pdf&amp;<?php echo htmlspecialchars($kasir_export_query, ENT_QUOTES, 'UTF-8'); ?>" class="btn btn-sm border flex-fill d-inline-flex align-items-center justify-content-center gap-2 px-3" style="background:#fff;color:#ff4d8d;font-size:1rem; min-width:120px;">
                                                        <i class="bi bi-file-earmark-pdf fs-5"></i>
                                                        <span class="fw-semibold">PDF</span>
                                                    </a>
                                                    <a href="?export=excel&amp;<?php echo htmlspecialchars($kasir_export_query, ENT_QUOTES, 'UTF-8'); ?>" class="btn btn-sm border flex-fill d-inline-flex align-items-center justify-content-center gap-2 px-3" style="background:#fff;color:#28a745;font-size:1rem; min-width:120px;">
                                                        <i class="bi bi-file-earmark-excel fs-5"></i>
                                                        <span class="fw-semibold">Excel</span>
                                                    </a>
                                                </div>
                                            </div>
                                            <?php endif; ?>
                                        </form>
                                    </div>
                                </div>

                                <?php if (!empty($selected_kasir)): ?>
                                <div class="table-responsive">
                                    <table class="table table-hover align-middle mb-0">
                                        <thead class="table-light text-center">
                                            <tr>
                                                <th style="width:5%; text-align:left;">No</th>
                                                <th style="width:10%; text-align:center;">Tanggal</th>
                                                <th style="width:10%; text-align:left;">Kasir</th>
                                                <th class="text-end" style="width:12%;">Cash Awal</th>
                                                <th class="text-end" style="width:12%;">QRIS</th>
                                                <th class="text-end" style="width:12%;">Transfer</th>
                                                <th class="text-end" style="width:12%;">Cash</th>
                                                <th class="text-end" style="width:12%;">Total Diskon</th>
                                                <th class="text-end" style="width:auto;">Grand Total</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php if (empty($kasir_data)): ?>
                                            <tr>
                                                <td colspan="9" class="text-center py-5">
                                                    <i class="bi bi-inbox fs-1 d-block mb-2 text-muted"></i>
                                                    <span class="text-muted">Tidak ada data untuk kasir yang dipilih</span>
                                                </td>
                                            </tr>
                                            <?php else: ?>
                                                <?php $no = 1; foreach ($kasir_data as $row): ?>
                                                <tr>
                                                    <td class="text-left fw-semibold"><?php echo $no++; ?></td>
                                                    <td class="text-center text-nowrap">
                                                        <a href="#" class="text-decoration-none" onclick="showKasirDetail('<?php echo htmlspecialchars($row['tanggal_open_raw']); ?>', '<?php echo $row['id_user']; ?>', '<?php echo htmlspecialchars($row['kasir']); ?>')" data-bs-toggle="modal" data-bs-target="#kasirDetailModal">
                                                            <span class="text-primary">
                                                                <i class="bi bi-calendar3 me-1"></i><?php echo htmlspecialchars($row['tanggal_open']); ?>
                                                            </span>
                                                        </a>
                                                    </td>
                                                    <td class="text-start"><?php echo htmlspecialchars($row['kasir']); ?></td>
                                                    <td class="text-end">Rp <?php echo number_format($row['cash_awal'], 0, ',', '.'); ?></td>
                                                    <td class="text-end">Rp <?php echo number_format($row['qris'], 0, ',', '.'); ?></td>
                                                    <td class="text-end">Rp <?php echo number_format($row['transfer'], 0, ',', '.'); ?></td>
                                                    <td class="text-end">Rp <?php echo number_format($row['cash'], 0, ',', '.'); ?></td>
                                                    <td class="text-end text-danger">Rp <?php echo number_format($row['total_diskon'], 0, ',', '.'); ?></td>
                                                    <td class="text-end fw-semibold text-primary">Rp <?php echo number_format($row['grand_total'], 0, ',', '.'); ?></td>
                                                </tr>
                                                <?php endforeach; ?>
                                            <?php endif; ?>
                                        </tbody>
                                    </table>
                                </div>
                                <?php if (!empty($kasir_data)): ?>
                                <div class="d-flex justify-content-end mt-3 px-4 py-2">
                                    <div class="badge bg-primary-subtle text-primary px-3 py-2">
                                        <i class="bi bi-calculator me-1"></i>Total Kasir: Rp <?php echo number_format($total_kasir, 0, ',', '.'); ?>
                                    </div>
                                </div>
                                <?php endif; ?>
                                <?php else: ?>
                                <div class="text-center py-5">
                                    <i class="bi bi-person-badge fs-1 text-muted"></i>
                                    <p class="mt-3 text-muted">Silakan pilih kasir dan periode untuk melihat data</p>
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>

            <!-- Semua Tab -->
                        <div class="tab-pane fade" id="semua" role="tabpanel" aria-labelledby="semua-tab">
                            <div class="px-0">
                                
                                <form method="GET" class="row g-3 align-items-end mb-4 px-4 py-4">
                                    <input type="hidden" name="tab" value="semua">
                                    <input type="hidden" name="semua_page" value="1">
                                    <div class="col-12 col-md-3 col-lg-2">
                                        <label for="tanggal1" class="form-label">Tanggal Awal</label>
                                        <input type="text" class="form-control" id="tanggal1" name="tanggal1" value="<?php echo htmlspecialchars($semua_tanggal1); ?>">
                                    </div>
                                    <div class="col-12 col-md-3 col-lg-2">
                                        <label for="tanggal2" class="form-label">Tanggal Akhir</label>
                                        <input type="text" class="form-control" id="tanggal2" name="tanggal2" value="<?php echo htmlspecialchars($semua_tanggal2); ?>">
                                    </div>
                                    <div class="col-12 col-md-3 col-lg-2">
                                        <label for="kategori" class="form-label">Kategori</label>
                                        <select class="form-select" id="kategori" name="kategori">
                                            <option value="Semua" <?php echo $semua_kategori == 'Semua' ? 'selected' : ''; ?>>Semua</option>
                                            <option value="Tunai" <?php echo $semua_kategori == 'Tunai' ? 'selected' : ''; ?>>Tunai</option>
                                            <option value="Transfer" <?php echo $semua_kategori == 'Transfer' ? 'selected' : ''; ?>>Transfer</option>
                                            <option value="QRIS" <?php echo $semua_kategori == 'QRIS' ? 'selected' : ''; ?>>QRIS</option>
                                        </select>
                                    </div>
                                    <div class="col-12 col-md-3 col-lg-6">
                                        <div class="d-flex flex-wrap justify-content-end gap-2">
                                            <button type="submit" class="btn btn-primary d-inline-flex align-items-center gap-2">
                                                <i class="bi bi-search"></i>
                                                <span>Tampilkan</span>
                                            </button>
                                            <?php if (!empty($semua_data)): ?>
                                            <a href="?export=pdf&amp;<?php echo htmlspecialchars($semua_export_query . '&semua_page=' . $semua_page, ENT_QUOTES, 'UTF-8'); ?>" class="btn btn-sm border d-inline-flex align-items-center justify-content-center gap-2 px-3" style="background:#fff;color:#ff4d8d;font-size:1rem;">
                                                <i class="bi bi-file-earmark-pdf fs-5"></i>
                                                <span class="fw-semibold">PDF</span>
                                            </a>
                                            <a href="?export=excel&amp;<?php echo htmlspecialchars($semua_export_query . '&semua_page=' . $semua_page, ENT_QUOTES, 'UTF-8'); ?>" class="btn btn-sm border d-inline-flex align-items-center justify-content-center gap-2 px-3" style="background:#fff;color:#28a745;font-size:1rem;">
                                                <i class="bi bi-file-earmark-excel fs-5"></i>
                                                <span class="fw-semibold">Excel</span>
                                            </a>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </form>

                                <div class="table-responsive">
                                    <table class="table table-hover align-middle mb-0">
                                        <thead class="table-light text-center">
                                            <tr>
                                                <th style="width:5%; text-align:left;">No</th>
                                                <th style="width:auto; text-align:left;">Kode</th>
                                                <th style="width:20%; text-align:left;">ID</th>
                                                <th style="width:8%; text-align:center;">Kategori</th>
                                                <th class="text-end" style="width:15%;">Total</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php if (empty($semua_data)): ?>
                                            <tr>
                                                <td colspan="5" class="text-center py-5">
                                                    <i class="bi bi-inbox fs-1 d-block mb-2 text-muted"></i>
                                                    <span class="text-muted">Tidak ada data untuk filter yang dipilih</span>
                                                </td>
                                            </tr>
                                            <?php else: ?>
                                                <?php $no = $semua_offset + 1; foreach ($semua_data as $row): ?>
                                                <tr>
                                                    <td class="text-start fw-semibold"><?php echo $no++; ?></td>
                                                    <td class="fw-semibold"><?php echo htmlspecialchars($row['kode']); ?></td>
                                                    <td class="fw-semibold text-start" ><?php echo htmlspecialchars($row['id_tagihan']); ?></td>
                                                    <td>
                                                        <?php
                                                        $badge_class = 'bg-secondary';
                                                        if ($row['kategori'] === 'Tunai') {
                                                            $badge_class = 'bg-success';
                                                        } elseif ($row['kategori'] === 'Transfer') {
                                                            $badge_class = 'bg-info';
                                                        } elseif ($row['kategori'] === 'QRIS') {
                                                            $badge_class = 'bg-warning text-dark';
                                                        }
                                                        ?>
                                                        <span class="badge <?php echo $badge_class; ?>"><?php echo htmlspecialchars($row['kategori']); ?></span>
                                                    </td>
                                                    <td class="text-end fw-semibold">Rp <?php echo number_format($row['total'], 0, ',', '.'); ?></td>
                                                </tr>
                                                <?php endforeach; ?>
                                            <?php endif; ?>
                                        </tbody>
                                    </table>
                                </div>

                                <?php if ($semua_total_pages > 1): ?>
                                <nav aria-label="Page navigation" class="mt-3">
                                    <ul class="pagination justify-content-center mb-0">
                                        <li class="page-item <?php echo $semua_page <= 1 ? 'disabled' : ''; ?>">
                                            <a class="page-link" href="?tab=semua&tanggal1=<?php echo $semua_tanggal1; ?>&tanggal2=<?php echo $semua_tanggal2; ?>&kategori=<?php echo $semua_kategori; ?>&semua_page=<?php echo $semua_page - 1; ?>">Previous</a>
                                        </li>
                                        <?php
                                        $start_page = max(1, $semua_page - 2);
                                        $end_page = min($semua_total_pages, $semua_page + 2);
                                        if ($start_page > 1): ?>
                                        <li class="page-item"><a class="page-link" href="?tab=semua&tanggal1=<?php echo $semua_tanggal1; ?>&tanggal2=<?php echo $semua_tanggal2; ?>&kategori=<?php echo $semua_kategori; ?>&semua_page=1">1</a></li>
                                        <?php if ($start_page > 2): ?><li class="page-item disabled"><span class="page-link">...</span></li><?php endif; ?>
                                        <?php endif; ?>
                                        <?php for ($i = $start_page; $i <= $end_page; $i++): ?>
                                        <li class="page-item <?php echo $i == $semua_page ? 'active' : ''; ?>">
                                            <a class="page-link" href="?tab=semua&tanggal1=<?php echo $semua_tanggal1; ?>&tanggal2=<?php echo $semua_tanggal2; ?>&kategori=<?php echo $semua_kategori; ?>&semua_page=<?php echo $i; ?>"><?php echo $i; ?></a>
                                        </li>
                                        <?php endfor; ?>
                                        <?php if ($end_page < $semua_total_pages): ?>
                                        <?php if ($end_page < $semua_total_pages - 1): ?><li class="page-item disabled"><span class="page-link">...</span></li><?php endif; ?>
                                        <li class="page-item"><a class="page-link" href="?tab=semua&tanggal1=<?php echo $semua_tanggal1; ?>&tanggal2=<?php echo $semua_tanggal2; ?>&kategori=<?php echo $semua_kategori; ?>&semua_page=<?php echo $semua_total_pages; ?>"><?php echo $semua_total_pages; ?></a></li>
                                        <?php endif; ?>
                                        <li class="page-item <?php echo $semua_page >= $semua_total_pages ? 'disabled' : ''; ?>">
                                            <a class="page-link" href="?tab=semua&tanggal1=<?php echo $semua_tanggal1; ?>&tanggal2=<?php echo $semua_tanggal2; ?>&kategori=<?php echo $semua_kategori; ?>&semua_page=<?php echo $semua_page + 1; ?>">Next</a>
                                        </li>
                                    </ul>
                                </nav>
                                <?php endif; ?>

                                <?php if (!empty($semua_data)): ?>
                                <div class="d-flex justify-content-between align-items-center mt-3 px-4 py-2">
                                    <span class="text-muted">Menampilkan <?php echo $semua_offset + 1; ?> - <?php echo min($semua_offset + $semua_limit, $semua_total_records); ?> dari <?php echo $semua_total_records; ?> transaksi <?php echo $semua_kategori !== 'Semua' ? '(Filter: ' . htmlspecialchars($semua_kategori) . ')' : ''; ?></span>
                                    <div class="badge bg-success-subtle text-success px-3 py-2">
                                        <i class="bi bi-calculator me-1"></i>Total Halaman: Rp <?php echo number_format($total_semua, 0, ',', '.'); ?>
                                    </div>
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div class="tab-pane fade" id="summary" role="tabpanel" aria-labelledby="summary-tab">
                            <div class="px-4 py-4">
                                <div class="row g-4">
                                    <div class="col-lg-4">
                                        <div class="summary-card bg-success-subtle px-4 py-4">
                                            <h6 class="text-success mb-3"><i class="bi bi-cash-stack me-2"></i>Tunai</h6>
                                            <div class="d-flex justify-content-between mb-2">
                                                <span>Total Nominal</span>
                                                <strong>Rp <?php echo number_format($total_tunai, 0, ',', '.'); ?></strong>
                                            </div>
                                            <div class="d-flex justify-content-between mb-2">
                                                <span>Jumlah Transaksi</span>
                                                <strong><?php echo count($tunai_data); ?></strong>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-lg-4">
                                        <div class="summary-card bg-primary-subtle px-4 py-4">
                                            <h6 class="text-primary mb-3"><i class="bi bi-bank me-2"></i>Transfer / EDC</h6>
                                            <div class="d-flex justify-content-between mb-2">
                                                <span>Total Nominal</span>
                                                <strong>Rp <?php echo number_format($total_transfer, 0, ',', '.'); ?></strong>
                                            </div>
                                            <div class="d-flex justify-content-between mb-2">
                                                <span>Jumlah Transaksi</span>
                                                <strong><?php echo count($transfer_data); ?></strong>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-lg-4">
                                        <div class="summary-card bg-warning-subtle px-4 py-4">
                                            <h6 class="text-warning mb-3"><i class="bi bi-qr-code-scan me-2"></i>QRIS</h6>
                                            <div class="d-flex justify-content-between mb-2">
                                                <span>Total Nominal</span>
                                                <strong>Rp <?php echo number_format($total_qris, 0, ',', '.'); ?></strong>
                                            </div>
                                            <div class="d-flex justify-content-between mb-2">
                                                <span>Jumlah Transaksi</span>
                                                <strong><?php echo count($qris_data); ?></strong>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <div class="row g-4 mt-3">
                                    <div class="col-lg-6">
                                        <div class="summary-card bg-secondary-subtle px-4 py-4">
                                            <h6 class="text-secondary mb-3"><i class="bi bi-people me-2"></i>Per Kasir</h6>
                                            <div class="d-flex justify-content-between mb-2">
                                                <span>Total Grand Total</span>
                                                <strong>Rp <?php echo number_format($total_kasir, 0, ',', '.'); ?></strong>
                                            </div>
                                            <div class="d-flex justify-content-between mb-2">
                                                <span>Periode Dipilih</span>
                                                <strong><?php echo htmlspecialchars($tanggal_awal); ?> s/d <?php echo htmlspecialchars($tanggal_akhir); ?></strong>
                                            </div>
                                            <div class="d-flex justify-content-between">
                                                <span>Kasir Dipilih</span>
                                                <strong><?php echo $selected_kasir_name ? htmlspecialchars($selected_kasir_name) : 'Semua'; ?></strong>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-lg-6">
                                        <div class="summary-card bg-info-subtle px-4 py-4">
                                            <h6 class="text-info mb-3"><i class="bi bi-list-check me-2"></i>Semua Transaksi</h6>
                                            <div class="d-flex justify-content-between mb-2">
                                                <span>Total Halaman Saat Ini</span>
                                                <strong>Rp <?php echo number_format($total_semua, 0, ',', '.'); ?></strong>
                                            </div>
                                            <div class="d-flex justify-content-between mb-2">
                                                <span>Rentang Tanggal</span>
                                                <strong><?php echo htmlspecialchars($semua_tanggal1); ?> s/d <?php echo htmlspecialchars($semua_tanggal2); ?></strong>
                                            </div>
                                            <div class="d-flex justify-content-between">
                                                <span>Kategori</span>
                                                <strong><?php echo htmlspecialchars($semua_kategori); ?></strong>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <div class="mt-4 text-center">
                                    <div class="badge bg-secondary-subtle text-secondary px-4 py-3 fs-5">
                                        <i class="bi bi-calculator me-2"></i>Total Kombinasi: Rp <?php echo number_format($total_tunai + $total_transfer + $total_qris, 0, ',', '.'); ?>
                                    </div>
                                </div>
                            </div>
                        </div>
            </div>
        </div>
    </main>

    <div class="position-fixed bottom-0 end-0 p-3" style="z-index: 1080">
        <div id="kasirToast" class="toast align-items-center text-bg-warning border-0" role="alert" aria-live="assertive" aria-atomic="true" data-bs-delay="3000">
            <div class="d-flex">
                <div class="toast-body">
                    Silakan pilih kasir terlebih dahulu.
                </div>
                <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button>
            </div>
        </div>
    </div>

    <!-- Kasir Detail Modal -->
    <div class="modal fade" id="kasirDetailModal" tabindex="-1">
      <div class="modal-dialog modal-xl">
        <div class="modal-content">
          <div class="modal-header">
            <h5 class="modal-title">Detail Transaksi Kasir - <span id="kasir-name"></span></h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
          </div>
          <div class="modal-body">
            <div class="mb-3">
              <div class="row g-3 align-items-end">
                <div class="col-md-6">
                  <label for="kasir-detail-search" class="form-label">Cari Kode Payment</label>
                  <div class="input-group">
                    <span class="input-group-text bg-body-secondary"><i class="bi bi-search"></i></span>
                    <input type="text" class="form-control" id="kasir-detail-search" placeholder="Masukkan kode payment">
                  </div>
                </div>
                <div class="col-md-4">
                  <label for="kasir-detail-kategori" class="form-label">Kategori</label>
                  <select class="form-select" id="kasir-detail-kategori">
                    <option value="Semua">Semua</option>
                  </select>
                </div>
                <div class="col-md-2">
                  <label class="form-label d-none d-md-block">&nbsp;</label>
                  <button type="button" class="btn btn-outline-secondary w-100" id="kasir-detail-reset">
                    <i class="bi bi-arrow-counterclockwise me-1"></i>Reset
                  </button>
                </div>
              </div>
            </div>

            <div class="table-responsive rounded-3 border">
              <table class="table table-striped table-hover align-middle mb-0" id="kasir-detail-table">
                <thead class="table-light">
                  <tr class="text-center text-uppercase small">
                    <th style="width:6%;">No</th>
                    <th style="width:20%;" class="text-start">Kode Payment</th>
                    <th style="width:10%;">Jam</th>
                    <th style="width:18%;" class="text-start">Kategori</th>
                    <th style="width:12%;" class="text-start">Diskon</th>
                    <th style="width:12%;" class="text-end">Total Diskon</th>
                    <th style="width:12%;" class="text-end">Total Kotor</th>
                    <th style="width:12%;" class="text-end">Total Bersih</th>
                  </tr>
                </thead>
                <tbody id="kasir-detail-tbody">
                  <tr>
                    <td colspan="8" class="text-center py-4">
                      <div class="spinner-border text-primary" role="status">
                        <span class="visually-hidden">Loading...</span>
                      </div>
                    </td>
                  </tr>
                </tbody>
              </table>
            </div>

            <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mt-3" id="kasir-detail-summary" style="display:none;">
              <span class="text-muted" id="kasir-detail-count"></span>
              <div class="badge bg-success-subtle text-success px-3 py-2" id="kasir-detail-total"></div>
            </div>

            <nav aria-label="Page navigation" class="mt-3">
              <ul class="pagination justify-content-center" id="kasir-detail-pagination"></ul>
            </nav>
          </div>
        </div>
      </div>
    </div>
    
    <!-- Transfer Detail Modal -->
    <div class="modal fade" id="transferDetailModal" tabindex="-1">
      <div class="modal-dialog modal-lg">
        <div class="modal-content">
          <div class="modal-header">
            <h5 class="modal-title">Detail Transfer</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
          </div>
          <div class="modal-body">
            <div class="row g-3">
              <div class="col-md-6">
                <label class="form-label text-muted">Tanggal Transfer</label>
                <p class="fw-semibold" id="modal-tanggal-transfer">-</p>
              </div>
              <div class="col-md-6">
                <label class="form-label text-muted">Tanggal Pemeriksaan</label>
                <p class="fw-semibold" id="modal-tgl-pemeriksaan">-</p>
              </div>
              <div class="col-md-6">
                <label class="form-label text-muted">No. Referensi</label>
                <p class="fw-semibold" id="modal-no-referensi">-</p>
              </div>
              <div class="col-md-6">
                <label class="form-label text-muted">Status Pemeriksaan</label>
                <p id="modal-status-pemeriksaan">-</p>
              </div>
              
              <div class="col-12">
                <label class="form-label text-muted">Bukti Transfer</label>
                <div id="modal-img-container" class="text-center">
                  <p class="text-muted">Tidak ada bukti</p>
                </div>
              </div>
            </div>
          </div>
         
        </div>
      </div>
    </div>

    <?php include '_scripts_new.php'; ?>
    <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
    <script>
      document.addEventListener('DOMContentLoaded', function() {
        // Initialize date picker with Flatpickr for main date filter
        if (document.getElementById('tanggal')) {
          flatpickr("#tanggal", {
            dateFormat: "Y-m-d",
            maxDate: "today",
            defaultDate: "<?php echo $selected_date; ?>",
            locale: {
              firstDayOfWeek: 1
            }
          });
        }
        
        // Initialize date pickers for Per Kasir tab - simplified without onChange events
        if (document.getElementById('tanggal_awal')) {
          flatpickr("#tanggal_awal", {
            dateFormat: "Y-m-d",
            maxDate: "today",
            defaultDate: "<?php echo $tanggal_awal; ?>",
            locale: {
              firstDayOfWeek: 1
            }
          });
        }
        
        if (document.getElementById('tanggal_akhir')) {
          flatpickr("#tanggal_akhir", {
            dateFormat: "Y-m-d",
            maxDate: "today",
            defaultDate: "<?php echo $tanggal_akhir; ?>",
            locale: {
              firstDayOfWeek: 1
            }
          });
        }
        
        // Initialize date pickers for Semua tab
        if (document.getElementById('tanggal1')) {
          flatpickr("#tanggal1", {
            dateFormat: "Y-m-d",
            maxDate: "today",
            defaultDate: "<?php echo $semua_tanggal1; ?>",
            locale: {
              firstDayOfWeek: 1
            }
          });
        }
        
        if (document.getElementById('tanggal2')) {
          flatpickr("#tanggal2", {
            dateFormat: "Y-m-d",
            maxDate: "today",
            defaultDate: "<?php echo $semua_tanggal2; ?>",
            locale: {
              firstDayOfWeek: 1
            }
          });
        }
      
        // Activate the correct tab based on URL parameter
        const urlParams = new URLSearchParams(window.location.search);
        const activeTab = urlParams.get('tab');
        
        // Use setTimeout to ensure Bootstrap is fully loaded
        setTimeout(function() {
          if (activeTab === 'kasir') {
            const kasirTabEl = document.getElementById('kasir-tab');
            if (kasirTabEl) {
              const kasirTab = new bootstrap.Tab(kasirTabEl);
              kasirTab.show();
            }
          } else if (activeTab === 'transfer') {
            const transferTabEl = document.getElementById('transfer-tab');
            if (transferTabEl) {
              const transferTab = new bootstrap.Tab(transferTabEl);
              transferTab.show();
            }
          } else if (activeTab === 'qris') {
            const qrisTabEl = document.getElementById('qris-tab');
            if (qrisTabEl) {
              const qrisTab = new bootstrap.Tab(qrisTabEl);
              qrisTab.show();
            }
          } else if (activeTab === 'semua') {
            const semuaTabEl = document.getElementById('semua-tab');
            if (semuaTabEl) {
              const semuaTab = new bootstrap.Tab(semuaTabEl);
              semuaTab.show();
            }
          }
        }, 100);
      
        // Prevent form submission on Enter key except for button
        const kasirForm = document.getElementById('kasirFilterForm');
        if (kasirForm) {
          kasirForm.addEventListener('keypress', function(e) {
            if (e.key === 'Enter' && e.target.tagName !== 'BUTTON') {
              e.preventDefault();
              return false;
            }
          });
          kasirForm.addEventListener('submit', function(e) {
            const kasirSelect = document.getElementById('kasir_select');
            if (kasirSelect && !kasirSelect.value) {
              e.preventDefault();
              const toast = document.getElementById('kasirToast');
              if (toast) {
                const toastInstance = bootstrap.Toast.getOrCreateInstance(toast);
                toastInstance.show();
              }
            }
          });
        }
        
        // Handle tab clicks to maintain state
        document.querySelectorAll('[data-bs-toggle="tab"]').forEach(tab => {
          tab.addEventListener('shown.bs.tab', function (e) {
            const tabId = e.target.id.replace('-tab', '');
            const url = new URL(window.location);
            
            // Update URL parameter for active tab
            if (tabId === 'tunai') {
              url.searchParams.delete('tab');
            } else {
              url.searchParams.set('tab', tabId);
            }
            
            // For non-kasir tabs, remove kasir-specific parameters
            if (tabId !== 'kasir') {
              url.searchParams.delete('tanggal_awal');
              url.searchParams.delete('tanggal_akhir');
              url.searchParams.delete('kasir');
            }
            
            // Update URL without page reload
            window.history.replaceState({}, '', url);
          });
        });
      }); // End of DOMContentLoaded

      // Utility helpers
      const kasirDetailElements = {
        tbody: document.getElementById('kasir-detail-tbody'),
        pagination: document.getElementById('kasir-detail-pagination'),
        searchInput: document.getElementById('kasir-detail-search'),
        kategoriSelect: document.getElementById('kasir-detail-kategori'),
        resetButton: document.getElementById('kasir-detail-reset'),
        summaryWrap: document.getElementById('kasir-detail-summary'),
        summaryCount: document.getElementById('kasir-detail-count'),
        summaryTotal: document.getElementById('kasir-detail-total')
      };

      const KASIR_DETAIL_PAGE_SIZE = 10;
      let kasirSearchDebounce;

      function escapeHtml(value) {
        if (value === null || value === undefined) return '';
        return String(value).replace(/[&<>"']/g, function(match) {
          const escape = { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;' };
          return escape[match] || match;
        });
      }

      function getKategoriBadgeClass(kategori) {
        if (!kategori) return 'bg-secondary-subtle text-secondary';
        const normalized = kategori.toLowerCase();
        if (normalized.includes('tunai')) {
          return 'bg-success-subtle text-success';
        }
        if (normalized.includes('transfer') || normalized.includes('edc')) {
          return 'bg-primary-subtle text-primary';
        }
        if (normalized.includes('qris')) {
          return 'bg-warning-subtle text-warning';
        }
        return 'bg-secondary-subtle text-secondary';
      }

      let currentKasirPage = 1;
      let currentKasirData = {
        tanggal: '',
        id_user: '',
        kasir_name: '',
        search: '',
        kategori: 'Semua'
      };

      if (kasirDetailElements.searchInput) {
        kasirDetailElements.searchInput.addEventListener('input', function (event) {
          clearTimeout(kasirSearchDebounce);
          kasirSearchDebounce = setTimeout(() => {
            currentKasirData.search = event.target.value.trim();
            currentKasirPage = 1;
            loadKasirDetailData();
          }, 300);
        });
      }

      if (kasirDetailElements.kategoriSelect) {
        kasirDetailElements.kategoriSelect.addEventListener('change', function (event) {
          currentKasirData.kategori = event.target.value;
          currentKasirPage = 1;
          loadKasirDetailData();
        });
      }

      if (kasirDetailElements.resetButton) {
        kasirDetailElements.resetButton.addEventListener('click', function () {
          if (kasirDetailElements.searchInput) {
            kasirDetailElements.searchInput.value = '';
          }
          if (kasirDetailElements.kategoriSelect) {
            kasirDetailElements.kategoriSelect.value = 'Semua';
          }
          currentKasirData.search = '';
          currentKasirData.kategori = 'Semua';
          currentKasirPage = 1;
          loadKasirDetailData();
        });
      }

      function showKasirDetail(tanggal, idUser, kasirName) {
        currentKasirData = {
          tanggal: tanggal,
          id_user: idUser,
          kasir_name: kasirName,
          search: '',
          kategori: 'Semua'
        };
        currentKasirPage = 1;

        if (kasirDetailElements.searchInput) {
          kasirDetailElements.searchInput.value = '';
        }
        if (kasirDetailElements.kategoriSelect) {
          kasirDetailElements.kategoriSelect.value = 'Semua';
        }

        const kasirNameTarget = document.getElementById('kasir-name');
        if (kasirNameTarget) {
          kasirNameTarget.textContent = kasirName;
        }

        loadKasirDetailData();
      }

      function buildKasirDetailParams() {
        const params = new URLSearchParams({
          tanggal: currentKasirData.tanggal,
          id_user: currentKasirData.id_user,
          page: currentKasirPage
        });

        if (currentKasirData.search) {
          params.append('search', currentKasirData.search);
        }

        if (currentKasirData.kategori && currentKasirData.kategori !== 'Semua') {
          params.append('kategori', currentKasirData.kategori);
        }

        return params.toString();
      }

      function updateKasirFilters(filterData) {
        if (!filterData || !Array.isArray(filterData.categories) || !kasirDetailElements.kategoriSelect) {
          return;
        }

        const select = kasirDetailElements.kategoriSelect;
        const previousValue = currentKasirData.kategori || 'Semua';
        const uniqueCategories = Array.from(new Set(filterData.categories.filter(Boolean)));

        select.innerHTML = '<option value="Semua">Semua</option>';
        uniqueCategories.forEach(category => {
          const option = document.createElement('option');
          option.value = category;
          option.textContent = category;
          select.appendChild(option);
        });

        if (uniqueCategories.includes(previousValue)) {
          select.value = previousValue;
        } else {
          select.value = 'Semua';
          currentKasirData.kategori = 'Semua';
        }
      }

      function updateKasirSummary(summaryData) {
        if (!kasirDetailElements.summaryWrap || !kasirDetailElements.summaryCount || !kasirDetailElements.summaryTotal) {
          return;
        }

        if (!summaryData) {
          kasirDetailElements.summaryWrap.style.display = 'none';
          kasirDetailElements.summaryCount.textContent = '';
          kasirDetailElements.summaryTotal.textContent = '';
          return;
        }

        const totalRows = summaryData.total_rows ?? 0;
        const totalBersihFormatted = summaryData.total_bersih_formatted ?? '0';

        kasirDetailElements.summaryWrap.style.display = 'flex';
        kasirDetailElements.summaryCount.textContent = `Total transaksi: ${totalRows}`;
        kasirDetailElements.summaryTotal.innerHTML = `<i class="bi bi-wallet2 me-1"></i>Grand Total Bersih: Rp ${totalBersihFormatted}`;
      }

      function loadKasirDetailData() {
        const tbody = kasirDetailElements.tbody;
        const pagination = kasirDetailElements.pagination;

        if (!tbody || !pagination) {
          return;
        }

        tbody.innerHTML = `
          <tr>
            <td colspan="8" class="text-center py-4">
              <div class="spinner-border text-primary" role="status">
                <span class="visually-hidden">Loading...</span>
              </div>
            </td>
          </tr>
        `;
        pagination.innerHTML = '';
        updateKasirSummary(null);

        fetch(`get_kasir_detail.php?${buildKasirDetailParams()}`)
          .then(response => {
            if (!response.ok) {
              throw new Error('Gagal memuat data');
            }
            return response.json();
          })
          .then(result => {
            tbody.innerHTML = '';

            updateKasirFilters(result.filters);
            updateKasirSummary(result.summary);

            if (result.data && result.data.length > 0) {
              result.data.forEach((item, index) => {
                const row = document.createElement('tr');
                const baseIndex = (currentKasirPage - 1) * KASIR_DETAIL_PAGE_SIZE;

                const cells = [
                  { text: baseIndex + index + 1, className: 'text-center fw-semibold' },
                  { text: item.kode_payment || '-', className: 'text-start fw-semibold' },
                  { text: item.jam || '-', className: 'text-center text-nowrap' },
                  { category: item.kategori || '-', className: 'text-start' },
                  { text: item.diskon || '-', className: 'text-start' },
                  { text: `Rp ${item.total_diskon_formatted || '0'}`, className: 'text-end' },
                  { text: `Rp ${item.total_kotor_formatted || '0'}`, className: 'text-end' },
                  { text: `Rp ${item.total_bersih_formatted || '0'}`, className: 'text-end fw-bold text-primary' }
                ];

                cells.forEach(cellData => {
                  const td = document.createElement('td');
                  td.className = cellData.className;
                  if (cellData.category !== undefined) {
                    td.innerHTML = `<span class="badge ${getKategoriBadgeClass(cellData.category)} px-3 py-2">${escapeHtml(cellData.category)}</span>`;
                  } else {
                    td.textContent = cellData.text;
                  }
                  row.appendChild(td);
                });

                tbody.appendChild(row);
              });

              updateKasirPagination(result.pagination);
            } else {
              tbody.innerHTML = `
                <tr>
                  <td colspan="8" class="text-center text-muted py-5">
                    <i class="bi bi-inbox display-4 d-block mb-2"></i>
                    Tidak ada data transaksi
                  </td>
                </tr>
              `;
            }
          })
          .catch(error => {
            console.error('Error loading kasir detail:', error);
            updateKasirSummary(null);
            tbody.innerHTML = `
              <tr>
                <td colspan="8" class="text-center text-danger py-5">
                  <i class="bi bi-exclamation-triangle display-4 d-block mb-2"></i>
                  ${escapeHtml(error.message)}
                </td>
              </tr>
            `;
          });
      }
      
      // Function to update pagination
      function updateKasirPagination(paginationData) {
        const pagination = document.getElementById('kasir-detail-pagination');
        if (!pagination) {
          return;
        }

        pagination.innerHTML = '';

        if (!paginationData || !paginationData.total_pages || paginationData.total_pages <= 1) {
          return;
        }
        
        // Previous button
        const prevLi = document.createElement('li');
        prevLi.className = `page-item ${paginationData.current_page === 1 ? 'disabled' : ''}`;
        prevLi.innerHTML = `<a class="page-link" href="#" onclick="goToKasirPage(${paginationData.current_page - 1}); return false;">Previous</a>`;
        pagination.appendChild(prevLi);
        
        // Page numbers
        const maxVisiblePages = 5;
        let startPage = Math.max(1, paginationData.current_page - Math.floor(maxVisiblePages / 2));
        let endPage = Math.min(paginationData.total_pages, startPage + maxVisiblePages - 1);
        
        if (endPage - startPage < maxVisiblePages - 1) {
          startPage = Math.max(1, endPage - maxVisiblePages + 1);
        }
        
        if (startPage > 1) {
          const firstLi = document.createElement('li');
          firstLi.className = 'page-item';
          firstLi.innerHTML = `<a class="page-link" href="#" onclick="goToKasirPage(1); return false;">1</a>`;
          pagination.appendChild(firstLi);
          
          if (startPage > 2) {
            const dotsLi = document.createElement('li');
            dotsLi.className = 'page-item disabled';
            dotsLi.innerHTML = `<span class="page-link">...</span>`;
            pagination.appendChild(dotsLi);
          }
        }
        
        for (let i = startPage; i <= endPage; i++) {
          const pageLi = document.createElement('li');
          pageLi.className = `page-item ${i === paginationData.current_page ? 'active' : ''}`;
          pageLi.innerHTML = `<a class="page-link" href="#" onclick="goToKasirPage(${i}); return false;">${i}</a>`;
          pagination.appendChild(pageLi);
        }
        
        if (endPage < paginationData.total_pages) {
          if (endPage < paginationData.total_pages - 1) {
            const dotsLi = document.createElement('li');
            dotsLi.className = 'page-item disabled';
            dotsLi.innerHTML = `<span class="page-link">...</span>`;
            pagination.appendChild(dotsLi);
          }
          
          const lastLi = document.createElement('li');
          lastLi.className = 'page-item';
          lastLi.innerHTML = `<a class="page-link" href="#" onclick="goToKasirPage(${paginationData.total_pages}); return false;">${paginationData.total_pages}</a>`;
          pagination.appendChild(lastLi);
        }
        
        // Next button
        const nextLi = document.createElement('li');
        nextLi.className = `page-item ${paginationData.current_page === paginationData.total_pages ? 'disabled' : ''}`;
        nextLi.innerHTML = `<a class="page-link" href="#" onclick="goToKasirPage(${paginationData.current_page + 1}); return false;">Next</a>`;
        pagination.appendChild(nextLi);
      }
      
      // Function to go to specific page
      function goToKasirPage(page) {
        if (page < 1) return;
        currentKasirPage = page;
        loadKasirDetailData();
      }
      
      // Function to show transfer detail modal
      function showTransferDetail(tanggalTransfer, noReferensi, imgSs, statusPemeriksaan, tglPemeriksaan) {
        // Format tanggal transfer
        if (tanggalTransfer) {
          const date = new Date(tanggalTransfer);
          const options = { 
            year: 'numeric', 
            month: 'long', 
            day: 'numeric', 
            hour: '2-digit', 
            minute: '2-digit' 
          };
          document.getElementById('modal-tanggal-transfer').textContent = date.toLocaleDateString('id-ID', options);
        } else {
          document.getElementById('modal-tanggal-transfer').textContent = '-';
        }
        
        // Set no referensi
        document.getElementById('modal-no-referensi').textContent = noReferensi || '-';
        
        // Set status pemeriksaan with badge
        let statusHtml = '';
        if (statusPemeriksaan == '1') {
          statusHtml = '<span class="badge bg-success fs-6 px-3 py-2"><i class="bi bi-check-circle me-1"></i>DITERIMA</span>';
        } else if (statusPemeriksaan == '2') {
          statusHtml = '<span class="badge bg-danger fs-6 px-3 py-2"><i class="bi bi-x-circle me-1"></i>PALSU</span>';
        } else {
          statusHtml = '<span class="badge bg-warning fs-6 px-3 py-2"><i class="bi bi-clock me-1"></i>BELUM DITERIMA</span>';
        }
        document.getElementById('modal-status-pemeriksaan').innerHTML = statusHtml;
        
        // Set tanggal pemeriksaan
        if (tglPemeriksaan && tglPemeriksaan !== '') {
          const datePeriksa = new Date(tglPemeriksaan);
          const options = { 
            year: 'numeric', 
            month: 'long', 
            day: 'numeric', 
            hour: '2-digit', 
            minute: '2-digit' 
          };
          document.getElementById('modal-tgl-pemeriksaan').textContent = datePeriksa.toLocaleDateString('id-ID', options);
        } else {
          document.getElementById('modal-tgl-pemeriksaan').textContent = 'Belum diperiksa';
        }
        
        // Set image
        // Set image
const imgContainer = document.getElementById('modal-img-container');
if (imgSs && imgSs !== '') {
  imgContainer.innerHTML = `
    <a href="../images/bukti_tf/${imgSs}" target="_blank" class="d-block">
      <img src="../images/bukti_tf/${imgSs}" class="img-fluid rounded border" style="max-height: 400px;" alt="Bukti Transfer">
    </a>
    <p class="mt-2 text-muted small">Klik gambar untuk melihat ukuran penuh</p>
  `;
} else {
  imgContainer.innerHTML = '<p class="text-muted">Tidak ada bukti transfer</p>';
}
      }
    </script>
  </body>
</html>