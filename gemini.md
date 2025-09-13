buatkan pada harga_pokok_penjualan.php
tampilan dari query berikut

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
WHERE 
	harga_menu.nominal > 0 
	AND harga_menu.id_resep IS NOT NULL

    ? = berupa variable dari date picker (contoh: 2025-09-13)
     
     ketika halaman pertama di load memakai tanggal hari ini
     dan ketika di klik date picker memakai tanggal yang dipilih

     tampilkan di table design
     No, Kode Produk, Nama Produk,Harga Jual, Stok,Aktif Jual,Aksi
     untuk kolom aksi tambahkan button delete