dibawah dashboard informasi terbaru

selanjutnya dashboard untuk menampilkan invoice hari ini
berikut querynya

SELECT
SUM(vi.total_dengan_ppn) AS total_invoice
FROM
pesanan
INNER JOIN pegawai ON pegawai.id_user = pesanan.id_user
INNER JOIN view_invoice vi ON vi.id_pesanan = pesanan.id_pesanan
WHERE
status_checkout = '0'
AND tgl_cart = CURDATE()

kemudian di row yang sama Ringkasan hari ini tambakan setelah card Invoice
SELECT
sum(jumlah_uang) AS total_transaksi
FROM
proses_pembayaran
WHERE
DATE(tanggal_payment) = CURDATE()

kemudian di row yang sama Ringkasan hari ini tambahkan setelah card Total Transaksi hari ini
SELECT
COALESCE(SUM(produk_sell.harga_jual),0) AS total_nilai_batal,
COUNT(\*) AS jumlah_batal
FROM
dapur_batal
INNER JOIN dapur_order_detail
ON dapur_batal.id_order_detail = dapur_order_detail.id_order_detail
INNER JOIN pesanan
ON dapur_batal.id_pesanan = pesanan.id_pesanan
INNER JOIN pesanan_detail
ON dapur_order_detail.id_pesanan_detail = pesanan_detail.id_pesanan_detail
INNER JOIN produk_sell
ON pesanan_detail.id_produk_sell = produk_sell.id_produk_sell
INNER JOIN meja
ON pesanan.id_meja = meja.id_meja
INNER JOIN view_produk
ON produk_sell.id_produk = view_produk.id_produk
INNER JOIN pegawai
ON dapur_batal.id_user = pegawai.id_user
WHERE
DATE(waktu)=CURDATE()
