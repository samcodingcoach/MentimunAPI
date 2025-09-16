dibawah dashboard ringkasan hari ini

buatkan row dibagi 3 kolom (menampilkan daftar menu yang terakhir di pesan, 1. Makanan, Minuman,Pembatalan)
fokus ke kolom pertama yaitu makanan,
berikut querynya

SELECT
DATE_FORMAT(TIME(dapur_order_detail.tgl_update),'%H:%i') as waktu,
produk_menu.nama_produk,
produk_menu.kode_produk,
pesanan_detail.ta_dinein,
pesanan_detail.qty,
produk_sell.harga_jual,
kategori_menu.nama_kategori
FROM
dapur_order_detail
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
WHERE dapur_order_detail.ready >=0 and DATE(tgl_update) = CURDATE() and dapur_order_detail.ready >=2 ORDER BY id_order_detail desc limit 15

tamplikan dalam bentuk card, untuk munculkan gambar terdapat di ../images/{kode_produk}.jpg
tampilkan nama_kategori = 'Makanan' di kolom 1 dan kolom 2 'Minuman'
