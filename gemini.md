selanjutnya untuk tab QRIS
querynya
SELECT
proses_pembayaran.kode_payment,
DATE_FORMAT(proses_pembayaran.tanggal_payment,'%H:%i') AS jam,
CASE
WHEN proses_pembayaran.`status` = 1 THEN 'SETTLEMENT'
WHEN proses_pembayaran.`status` = 0 THEN 'PENDING'
END AS status_bayar,
pegawai.nama_lengkap as kasir,
FORMAT(proses_pembayaran.jumlah_uang, 0) AS nominal,
DATE_FORMAT(proses_pembayaran.update_status,'%d %M %Y %H:%i') AS waktu_dibayar
FROM
proses_pembayaran
INNER JOIN pegawai ON proses_pembayaran.id_user = pegawai.id_user
INNER JOIN metode_pembayaran ON proses_pembayaran.id_bayar = metode_pembayaran.id_bayar
WHERE
metode_pembayaran.kategori = 'QRIS' AND
DATE(tanggal_payment) = ? <- format YYYY-MM-DD
