buat list table pada laporan_transaksi.php
ada beberapa tab
Tunai, Transfer, QRIS, Kasir, Semua
selesaikan dulu Tab Tunai
pada tab tunai ada filterisasi berdasarkan tanggal menggunakan datetime picker, default tanggal hari ini format YYYY-MM-DD
berikut querynya
SELECT
proses_pembayaran.kode_payment,
DATE_FORMAT(proses_pembayaran.tanggal_payment,'%H:%i') AS jam,
DATE_FORMAT(proses_pembayaran.update_status,'%d %M %Y %H:%i') AS waktu_dibayar,
pegawai.nama_lengkap as kasir,
FORMAT(proses_pembayaran.jumlah_uang, 0) AS nominal,
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
DATE(tanggal_payment) = CURDATE()

gunakan pattern yang sudah anda pelajari di project ini.
