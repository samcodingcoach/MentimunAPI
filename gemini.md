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
