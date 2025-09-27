<?php
// Mengatur agar laporan error ditampilkan, kecuali notice
error_reporting(E_ALL & ~E_NOTICE);
// Mengatur header output sebagai JSON
header('Content-Type: application/json');

// Menyertakan file koneksi database
include "../../config/koneksi.php";

// Memeriksa apakah parameter id_meja ada di URL
if (!isset($_GET['id_meja'])) {
    echo json_encode(['error' => 'Parameter id_meja tidak ditemukan']);
    exit;
}

// Mengamankan input id_meja
$id_meja = mysqli_real_escape_string($conn, $_GET['id_meja']);

// Array utama untuk menampung semua data
$response = [];

// 1. Mengambil data meja
$sql_meja = "SELECT id_meja, nomor_meja, in_used FROM meja WHERE in_used=1 AND id_meja=$id_meja";
$result_meja = mysqli_query($conn, $sql_meja);

if (mysqli_num_rows($result_meja) > 0) {
    $response['meja'] = mysqli_fetch_assoc($result_meja);

    // 2. Mengambil data pesanan utama berdasarkan id_meja
    $sql_pesanan = "SELECT
                        p.id_pesanan, 
                        k.nama_konsumen, 
                        p.tgl_cart, 
                        p.total_cart, 
                        p.id_meja, 
                        p.id_tagihan, 
                        p.nomor_antri
                    FROM
                        pesanan p
                        INNER JOIN konsumen k ON p.id_konsumen = k.id_konsumen 
                    WHERE p.status_checkout=1 AND p.id_meja = $id_meja AND DATE(p.tgl_cart) = CURDATE() ORDER BY p.tgl_cart DESC LIMIT 1";
    
    $result_pesanan = mysqli_query($conn, $sql_pesanan);

    if (mysqli_num_rows($result_pesanan) > 0) {
        $pesanan_data = mysqli_fetch_assoc($result_pesanan);
        $id_pesanan = $pesanan_data['id_pesanan'];

        // 3. Mengambil detail pesanan (item-item) berdasarkan id_pesanan
        $sql_detail = "SELECT
	pd.id_pesanan, 
	pd.id_produk_sell, 
	pm.kode_produk, 
	pm.nama_produk, 
	ps.harga_jual, 
	pd.qty, 
	pd.ta_dinein, 
	pd.ket, 
	kategori_menu.nama_kategori
FROM
	pesanan_detail AS pd
	INNER JOIN
	produk_sell AS ps
	ON 
		pd.id_produk_sell = ps.id_produk_sell
	INNER JOIN
	produk_menu AS pm
	ON 
		ps.id_produk = pm.id_produk
	INNER JOIN
	kategori_menu
	ON 
		pm.id_kategori = kategori_menu.id_kategori
                        WHERE pd.id_pesanan = $id_pesanan";

        $result_detail = mysqli_query($conn, $sql_detail);
        $pesanan_detail_list = [];
        if (mysqli_num_rows($result_detail) > 0) {
            while($row = mysqli_fetch_assoc($result_detail)) {
                $pesanan_detail_list[] = $row;
            }
        }
        // Menambahkan detail pesanan ke data pesanan
        $pesanan_data['pesanan_detail'] = $pesanan_detail_list;

        // 4. Mengambil data pembayaran berdasarkan id_pesanan
        $sql_pembayaran = "SELECT
                                pp.kode_payment, 
                                pp.tanggal_payment, 
                                pp.id_pesanan, 
                                mp.kategori, 
                                pp.status, 
                                pp.jumlah_uang, 
                                pp.jumlah_dibayarkan, 
                                pp.kembalian, 
                                pp.model_diskon, 
                                pp.nilai_persen, 
                                pp.nilai_nominal, 
                                pp.total_diskon
                            FROM
                                proses_pembayaran pp
                                INNER JOIN metode_pembayaran mp ON pp.id_bayar = mp.id_bayar 
                            WHERE pp.id_pesanan = $id_pesanan";
        
        $result_pembayaran = mysqli_query($conn, $sql_pembayaran);
        $pembayaran_list = [];
        if (mysqli_num_rows($result_pembayaran) > 0) {
            while($row = mysqli_fetch_assoc($result_pembayaran)) {
                $pembayaran_list[] = $row;
            }
        }
        // Menambahkan detail pembayaran ke data pesanan
        $pesanan_data['pembayaran'] = $pembayaran_list;
        
        // Menambahkan data pesanan yang sudah lengkap (dengan detail dan pembayaran) ke response utama
        $response['pesanan'] = $pesanan_data;
    }
}

// Mengembalikan data dalam format JSON
echo json_encode($response, JSON_PRETTY_PRINT);

// Menutup koneksi
mysqli_close($conn);
?>
