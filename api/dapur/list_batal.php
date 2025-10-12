<?php
header('Content-Type: application/json');
error_reporting(E_ALL & ~E_NOTICE);

include "../../config/koneksi.php";

$sql = "SELECT
	dapur_batal.id_batal, 
	DATE_FORMAT(dapur_batal.waktu, '%H:%i') as waktu, 
	dapur_batal.alasan, 
	dapur_batal.qty, 
	dapur_batal.status_dapur, 
	pesanan_detail.ta_dinein, 
	(produk_sell.harga_jual * dapur_batal.qty) as harga_jual, 
	produk_menu.kode_produk, 
	produk_menu.nama_produk, 
	kategori_menu.nama_kategori, 
	pesanan.id_meja, 
	pesanan.id_tagihan, 
	proses_pembayaran.kode_payment
FROM
	dapur_batal
	INNER JOIN
	dapur_order_detail
	ON 
		dapur_batal.id_order_detail = dapur_order_detail.id_order_detail
	INNER JOIN
	pesanan_detail
	ON 
		dapur_order_detail.id_pesanan_detail = pesanan_detail.id_pesanan_detail
	INNER JOIN
	produk_sell
	ON 
		pesanan_detail.id_produk_sell = produk_sell.id_produk_sell
	INNER JOIN
	produk_menu
	ON 
		produk_sell.id_produk = produk_menu.id_produk
	INNER JOIN
	kategori_menu
	ON 
		produk_menu.id_kategori = kategori_menu.id_kategori
	INNER JOIN
	pesanan
	ON 
		dapur_batal.id_pesanan = pesanan.id_pesanan
	INNER JOIN
	proses_pembayaran
	ON 
		pesanan.id_pesanan = proses_pembayaran.id_pesanan
    
    WHERE date(waktu) = CURDATE()";



$result = mysqli_query($conn, $sql);

// Membuat array untuk menyimpan hasil query
$data = array();

// Mengambil hasil query dan memasukkannya ke dalam array
if (mysqli_num_rows($result) > 0) {
    while($row = mysqli_fetch_assoc($result)) 
    {
        
        $data[] = $row;
    }
}

// Mengembalikan data dalam format JSON
echo json_encode($data);

mysqli_close($conn);

?>