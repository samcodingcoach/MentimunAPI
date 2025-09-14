pada tab Semua
tampilkan data berikut dari query
SELECT
proses_pembayaran.kode_payment AS kode,
proses_pembayaran.id_tagihan,
proses_pembayaran.jumlah_uang AS total,
metode_pembayaran.kategori
FROM
proses_pembayaran
INNER JOIN
metode_pembayaran ON proses_pembayaran.id_bayar = metode_pembayaran.id_bayar
WHERE
DATE(proses_pembayaran.tanggal_payment) BETWEEN '?1' AND '?2'

kolomnya
No, Kode, ID, Kategori, Total

?1=datetimepicker1 dan ?2=datetimepicker2
YYYY-MM-DD

bisa di filter Semua, Tunai, Transfer,QRIS
paging per 15
