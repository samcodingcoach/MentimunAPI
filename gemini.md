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

selanjutnya untuk tab transfer
querynya
SELECT
proses_edc.id_tx_bank,
proses_edc.kode_payment,
CASE
WHEN proses_edc.transfer_or_edc = 0 THEN CONCAT('TRANSFER - ', nama_bank, ' A.N ', COALESCE(nama_pengirim, 'NONE'))
WHEN proses_edc.transfer_or_edc = 1 THEN CONCAT('EDC - ', nama_bank, ' A.N ', COALESCE(nama_pengirim, 'NONE'))
END AS bank,
nominal_transfer,
proses_edc.tanggal_transfer,
proses_edc.no_referensi,
proses_edc.img_ss,
proses_edc.status_pemeriksaan,
tgl_pemeriksaan
FROM
proses_edc
WHERE
DATE(tanggal_transfer) = ?

filterisasi berdasarkan tanggal menggunakan datetime picker, default tanggal hari ini format YYYY-MM-DD
untuk status pemeriksaan
1 = DITERIMA
2 = PALSU
selain itu BELUM DITERIMA

img_ss adalah nama file gambar , urlnya struk/images/nama_file

perbaiki tampilan table
No, Kode Payment, BANK/METODE, TANGGAL TRF, STATUS, NOMINAL

Ketika diklik status, muncul modal yang isinya tanggal_transfer,no_referensi,img_ss,status_pemeriksaan,tgl_pemeriksaan
