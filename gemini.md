kerjakan pada laporan_pengeluaran.php
dibawah ini ada file yang koding sbg berikut

<?php
header('Content-Type: application/json');
error_reporting(E_ALL & ~E_NOTICE);
require_once __DIR__ . '/tcpdf.php';
include "../config/koneksi.php";
$data = json_decode(file_get_contents('php://input'), true);

$response = ["status" => "error", "message" => "export gagal", "pdf_link" => ""];

$tgl_start = $data['tgl_s']; 
$tgl_end = $data['tgl_e'];


//sql1

$sql_po = "SELECT
  'PENGELUARAN PEMBELIAN BAHAN (PO)' as nama,
	bahan_request.tanggal_request as tanggal,
 FORMAT(sum(case when bahan_request_detail.isInvoice = 0 and bahan_request_detail.isDone = 1 THEN subtotal else 0 end),0) as cash_done,
  FORMAT(sum(case when bahan_request_detail.isInvoice = 0 and bahan_request_detail.isDone = 0 THEN subtotal else 0 end),0) as cash_hutang,
  
  FORMAT(sum(case when bahan_request_detail.isInvoice = 1 and bahan_request_detail.isDone = 1 THEN subtotal else 0 end),0) as invoice_done,
  FORMAT(sum(case when bahan_request_detail.isInvoice = 1 and bahan_request_detail.isDone = 0 THEN subtotal else 0 end),0) as invoice_hutang,
  
  FORMAT(SUM(CASE WHEN bahan_request_detail.isInvoice = 0 AND bahan_request_detail.isDone = 1 THEN subtotal ELSE 0 END) 
    + SUM(CASE WHEN bahan_request_detail.isInvoice = 1 AND bahan_request_detail.isDone = 1 THEN subtotal ELSE 0 END),0) AS total
  
FROM
	bahan_request_detail
	INNER JOIN
	bahan_request
	ON 
		bahan_request_detail.id_request = bahan_request.id_request
    where
     
    DATE(tanggal_request) BETWEEN '$tgl_start' AND '$tgl_end'
    
    GROUP BY tanggal_request
    ";



$result_po = mysqli_query($conn, $sql_po);
$rincianpo = [];
while ($rowpo = mysqli_fetch_assoc($result_po)) {
    $rincianpo[] = $rowpo;
}

//sql2

$sql_bahan = "SELECT
  'PENGELUARAN PRODUK (AKUMULASI BAHAN BAKU TERPAKAI)' as nama,
  produk_sell.tgl_release,
  
  FORMAT(sum((harga_menu.nominal - (harga_menu.biaya_produksi + harga_menu.margin)) * (produk_sell.stok_awal - produk_sell.stok)),0) as profit
FROM
	produk_sell
  INNER JOIN
	resep
	ON 
		produk_sell.id_produk = resep.id_produk
	INNER JOIN
	harga_menu
	ON 
		resep.id_resep = harga_menu.id_resep
  WHERE DATE(tgl_release) BETWEEN '$tgl_start' AND '$tgl_end'
  GROUP BY date(tgl_release)";

$result_bahan = mysqli_query($conn, $sql_bahan);
$rincianbahan = [];
while ($rowbahan = mysqli_fetch_assoc($result_bahan)) {
    $rincianbahan[] = $rowbahan;
}

//sql3
$sql_penjualanb="SELECT 'PENJUALAN BRUTO (TERMASUK BIAYA PENAMBAHAN)' as nama, DATE(tanggal_payment) as tanggal_payment, FORMAT(sum(jumlah_uang),0) as total
FROM proses_pembayaran where DATE(tanggal_payment) BETWEEN '$tgl_start' AND '$tgl_end' GROUP BY date(tanggal_payment)";

//sql4

$result_penjualanb = mysqli_query($conn, $sql_penjualanb);
$rincianpenjualanb = [];
while ($rowpenjualanb = mysqli_fetch_assoc($result_penjualanb)) {
    $rincianpenjualanb[] = $rowpenjualanb;
}

//sql5

$sql_penjualan2="SELECT 'PENJUALAN NETO (NON BIAYA PENAMBAHAN)' as nama, DATE(tanggal_payment) as tanggal_payment, 
FORMAT(sum(jumlah_no_pajak_qris) ,0)
as total
FROM proses_pembayaran where DATE(tanggal_payment) BETWEEN '$tgl_start' AND '$tgl_end' GROUP BY date(tanggal_payment)";


$result_penjualan2 = mysqli_query($conn, $sql_penjualan2);
$rincianpenjualan2 = [];
while ($rowpenjualan2 = mysqli_fetch_assoc($result_penjualan2)) {
    $rincianpenjualan2[] = $rowpenjualan2;
}


// Buat objek PDF
$pdf = new TCPDF();
//$pdf = new TCPDF('P', 'mm', 'A4',false, 'UTF-8', false);
$pdf->setPrintHeader(false);
$pdf->SetCreator(PDF_CREATOR);
$pdf->SetAuthor('Mentimun');
$pdf->SetTitle('Data Transaksi Penjualan');
$pdf->SetMargins(10, 10, 10);

$pdf->AddPage('P', 'A4');

$pdf->SetFont('helvetica', '', 11);


$pdf->setPrintFooter(false);

$today = date("d F Y H:i");

$no = 1;
$no_bahan = 1; 
$no_penjualanb = 1;
$no_penjualan2 = 1;
$sum_po = 0;
$sum_bahan = 0;
$sum_penjaualanb = 0;
$sum_penjualan2 = 0;

$html = '
<style>
    
    table { border-collapse: collapse; width: 100%; }
    th, td { border: 1px solid #4c4c4c; padding: 5px; font-size: 9pt; }
    .judul { font-size: 14pt; font-weight: bold; text-align: center; margin-bottom: 10px; }
    
</style>
<h3 align="center">RINGKASAN PENGELUARAN VS PENJUALAN</h3>
<h4 align="center">' . date("d F Y", strtotime($tgl_start)) . ' s.d ' . date("d F Y", strtotime($tgl_end)) . '</h4><br>';

if (count($rincianpo) > 0) {
    $nama1 = $rincianpo[0]['nama'];
} else {
    $nama1 = "Tidak ada data pengeluaran pembelian bahan";
}
$html .= '<h4> ' . $nama1 . ' </h4><br>';


$html .= '
<table cellpadding="3" cellspacing="0" width="100%" border="1">
     <thead>
        <tr>
            <th style="background-color:rgb(230, 230, 230); font-weight: bold; padding : 15px;" width="5%" align="center">NO</th>
            <th style="background-color:rgb(230, 230, 230); font-weight: bold; padding : 15px;" width="15%" align="center">TANGGAL</th>
            <th style="background-color:rgb(230, 230, 230); font-weight: bold; padding : 15px;" width="15%" align="center">CASH PAID</th>
            <th style="background-color:rgb(230, 230, 230); font-weight: bold; padding : 15px;" width="15%" align="center">CASH UNPAID</th>
            <th style="background-color:rgb(230, 230, 230); font-weight: bold; padding : 15px;" width="15%" align="center">INVOICE PAID</th>
            <th style="background-color:rgb(230, 230, 230); font-weight: bold; padding : 15px;" width="20%" align="center">INVOICE UNPAID</th>
            <th style="background-color:rgb(230, 230, 230); font-weight: bold; padding : 15px;" width="15%" align="center">TOTAL</th>
        </tr>
    </thead>
    <tbody>';




foreach ($rincianpo as $rowpo) {
    $html .= '
        <tr>
            <td width="5%" align="center">' . $no++ . '</td>
            <td width="15%" align="center">' . $rowpo['tanggal'] . '</td>
            <td width="15%" align="right">' . $rowpo['cash_done'] . '</td>
            <td width="15%" align="right">' . $rowpo['cash_hutang'] . '</td>
            <td width="15%" align="right">' . $rowpo['invoice_done'] . '</td>
            <td width="20%" align="right">' . $rowpo['invoice_hutang'] . '</td>
            <td width="15%" align="right">' . $rowpo['total'] . '</td>
        </tr>';

        $sum_po += str_replace(',', '', $rowpo['total']);
}



$html .= '
    </tbody>
</table>';

$html .= '
    <table cellpadding="3" cellspacing="0" border="1" width="100%" style="margin-top:20px; padding-left: 0px; padding-right: 0px">
        <tbody>
        <tr>
            <td width="85%" colspan="6"><b>GRAND TOTAL:</b></td>
            
            <td width="15%" align="right"><b>Rp ' . number_format($sum_po, 0) . '</b></td>
        </tr>
        </tbody>
    </table>
    <br><br>';




    $html .= '<div style="border-top: 1px dashed lightgray; margin: 10px 0;"></div>';

    
    if (count($rincianbahan) > 0) {
        $nama2 = $rincianbahan[0]['nama'];
    } else {
        $nama2 = "Tidak ada data rincian pembelian bahan";
    }
  
    

   

    $html .= '<h4> ' . $nama2 . ' </h4><br>';

    $html .= '
    <table cellpadding="3" cellspacing="0" width="100%" border="1">
        <thead>
            <tr>
                <th style="background-color:rgb(230, 230, 230); font-weight: bold; padding : 15px;" width="5%" align="center">NO</th>
                <th style="background-color:rgb(230, 230, 230); font-weight: bold; padding : 15px;" width="80%" align="center">TANGGAL</th>
                <th style="background-color:rgb(230, 230, 230); font-weight: bold; padding : 15px;" width="15%" align="center">TOTAL</th>
            </tr>
        </thead>
    <tbody>';

    foreach ($rincianbahan as $rowbahan) {
        $html .= '
            <tr>
                <td width="5%" align="center">' . $no_bahan++ . '</td>
                <td width="80%" align="center">' . $rowbahan['tgl_release'] . '</td>
                <td width="15%" align="right">' . $rowbahan['profit'] . '</td>
            </tr>';
    
            $sum_bahan += str_replace(',', '', $rowbahan['profit']);
    }
    
    $html .= '
        </tbody>
    </table>';

    $html .= '
    <table cellpadding="3" cellspacing="0" border="1" width="100%" style="margin-top:20px; padding-left: 0px; padding-right: 0px">
        <tbody>
        <tr>
            <td width="85%" colspan="2"><b>GRAND TOTAL:</b></td>
            
            <td width="15%" align="right"><b>Rp ' . number_format($sum_bahan, 0) . '</b></td>
        </tr>
        </tbody>
    </table>
    <br><br>';

    $html .= '<div style="border-top: 1px dashed lightgray; margin: 10px 0;"></div>';


    //penjualanb

    if (count($rincianpenjualanb) > 0) {
        $nama3 = $rincianpenjualanb[0]['nama'];
    } else {
        $nama3 = "Tidak ada data penjualan";
    }
   
    $html .= '<h4> ' . $nama3 . ' </h4><br>';

    $html .= '
    <table cellpadding="3" cellspacing="0" width="100%" border="1">
        <thead>
            <tr>
                <th style="background-color:rgb(230, 230, 230); font-weight: bold; padding : 15px;" width="5%" align="center">NO</th>
                <th style="background-color:rgb(230, 230, 230); font-weight: bold; padding : 15px;" width="80%" align="center">TANGGAL</th>
                <th style="background-color:rgb(230, 230, 230); font-weight: bold; padding : 15px;" width="15%" align="center">TOTAL</th>
            </tr>
        </thead>
    <tbody>';

    //rincianpenjualanb rowpenjualanb
    foreach ($rincianpenjualanb as $rowpenjualanb) {
        $html .= '
            <tr>
                <td width="5%" align="center">' . $no_penjualanb++ . '</td>
                <td width="80%" align="center">' . $rowpenjualanb['tanggal_payment'] . '</td>
                <td width="15%" align="right">' . $rowpenjualanb['total'] . '</td>
            </tr>';
    
            $sum_penjaualanb += str_replace(',', '', $rowpenjualanb['total']);
    }
    
    $html .= '
        </tbody>
    </table>';

    $html .= '
    <table cellpadding="3" cellspacing="0" border="1" width="100%" style="margin-top:20px; padding-left: 0px; padding-right: 0px">
        <tbody>
        <tr>
            <td width="85%" colspan="2"><b>GRAND TOTAL:</b></td>
            
            <td width="15%" align="right"><b>Rp ' . number_format($sum_penjaualanb, 0) . '</b></td>
        </tr>
        </tbody>
    </table>
    <br><br>';
    $html .= '<div style="border-top: 1px dashed lightgray; margin: 10px 0;"></div>';

    //penjualan 2


    if (count($rincianpenjualan2) > 0) {
        $nama4 = $rincianpenjualan2[0]['nama'];
    } else {
        $nama4 = "Tidak ada data penjualan";
    }

    $html .= '<h4> ' . $nama4 . ' </h4><br>';

    $html .= '
    <table cellpadding="3" cellspacing="0" width="100%" border="1">
        <thead>
            <tr>
                <th style="background-color:rgb(230, 230, 230); font-weight: bold; padding : 15px;" width="5%" align="center">NO</th>
                <th style="background-color:rgb(230, 230, 230); font-weight: bold; padding : 15px;" width="80%" align="center">TANGGAL</th>
                <th style="background-color:rgb(230, 230, 230); font-weight: bold; padding : 15px;" width="15%" align="center">TOTAL</th>
            </tr>
        </thead>
    <tbody>';

    //rincianpenjualan2 rowpenjualan2
    foreach ($rincianpenjualan2 as $rowpenjualan2) {
        $html .= '
            <tr>
                <td width="5%" align="center">' . $no_penjualan2++ . '</td>
                <td width="80%" align="center">' . $rowpenjualan2['tanggal_payment'] . '</td>
                <td width="15%" align="right">' . $rowpenjualan2['total'] . '</td>
            </tr>';
    
            $sum_penjualan2 += str_replace(',', '', $rowpenjualan2['total']);
    }
    
    $html .= '
        </tbody>
    </table>';

    $html .= '
    <table cellpadding="3" cellspacing="0" border="1" width="100%" style="margin-top:20px; padding-left: 0px; padding-right: 0px">
        <tbody>
        <tr>
            <td width="85%" colspan="2"><b>GRAND TOTAL:</b></td>
            
            <td width="15%" align="right"><b>Rp ' . number_format($sum_penjualan2, 0) . '</b></td>
        </tr>
        </tbody>
    </table>
    <br><br>';

    $html .= '<small>Export time : ' . $today . '</small>';
    




// Masukkan HTML ke PDF
$pdf->writeHTML($html, true, false, true, false, '');

// Path penyimpanan PDF
$folder = __DIR__ . '/../transaksi/pdf/';
$file_name = 'summary2_' . $tgl_start . '_' . $tgl_end . '.pdf';
$file_path = $folder . $file_name;
$file_url = 'transaksi/pdf/' . $file_name; // Hanya path relatif dari root

// Pastikan folder ada
if (!is_dir($folder)) {
    mkdir($folder, 0777, true);
}

// Simpan file PDF
$pdf->Output($file_path, 'F');

$response["status"] = "success";
$response["message"] = "PDF berhasil dibuat!";
$response["pdf_link"] = $file_url;
 
echo json_encode($response);
$conn->close();
?>

fokus hanya ke seluruh query saja. saya ingin di laporan_pengeluaran.php dapat mencari output laporan seperti yang diatas terdapat tanggal start dan tgl end
tidak perlu sampai export hanya sampai menampilkan di .php
