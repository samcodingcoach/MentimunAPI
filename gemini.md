selanjutnya untuk tab Per Kasir
tampilkan table datanya dengan query berikut
SELECT state_open_closing.id_open, tanggal_open, (state_open_closing.first_cash_drawer - state_open_closing.manual_total_cash
) AS cash_awal,
state_open_closing.total_qris AS cris,
state_open_closing.manual_total_bank AS transfer,
state_open_closing.manual_total_cash AS cash,
(state_open_closing.total_qris + state_open_closing.manual_total_bank + state_open_closing.manual_total_cash) + ( state_open_closing.first_cash_drawer - state_open_closing.manual_total_cash )
AS grand_total,
SUM( proses_pembayaran.total_diskon ) AS total_diskon,
state_open_closing.id_user,
pegawai.nama_lengkap AS kasir
FROM
state_open_closing
LEFT JOIN proses_pembayaran ON state_open_closing.id_user = proses_pembayaran.id_user
AND DATE ( tanggal_open ) BETWEEN '?' <- tanggal awal
AND '?' <- tanggal akhir
INNER JOIN pegawai ON state_open_closing.id_user = pegawai.id_user
WHERE
DATE (tanggal_open) BETWEEN '?' <- tanggal awal
AND '?' <- tanggal akhir
AND state_open_closing.id_user = '?';

pada halaman ini pake filter datepicker tanggal awal dan tanggal akhir kemudian pilih nama kasir menggunakan nama pegawai / kasir berikut quernya
SELECT DISTINCT
proses_pembayaran.id_user,
pegawai.nama_lengkap
FROM
proses_pembayaran
INNER JOIN pegawai ON proses_pembayaran.id_user = pegawai.id_user
WHERE
proses_pembayaran.`status` = 1 AND
DATE(tanggal_payment) BETWEEN '?' <- tanggal awal format YYYY-MM-DD
AND '?' <- tanggal akhir format YYYY-MM-DD
ORDER BY
proses_pembayaran.id_checkout ASC

dropdownlist pilih kasir tidak ada value, lihat lagi perintah di line 24. dimana ada valuenya ketika perubahan tanggal awal dan tanggal akhir
masalah ketika memilih tanggal halaman tab kasir menjadi begini sj
-- Pilih Kasir --
Brian
bukan ui dropdownlist dan datetimepicker awal dan akhir hilang.

masalah masih sama, ganti sj algoritma nya, pada dropdownlist pilih kasir pakai query dibawah ini
SELECT
pegawai.id_user,
pegawai.nama_lengkap
FROM
pegawai

WHERE jabatan ='Kasir' and aktif = 1 ORDER BY nama_lengkap asc

jadi hilangkan event after select datepicker awal dan akhir

Silakan lengkapi semua field sebelum menampilkan laporan, timbul ini ketika melakukan filter tampilkan
coba perhatikan query
SELECT state_open_closing.id_open, tanggal_open, (state_open_closing.first_cash_drawer - state_open_closing.manual_total_cash
) AS cash_awal,
state_open_closing.total_qris AS cris,
state_open_closing.manual_total_bank AS transfer,
state_open_closing.manual_total_cash AS cash,
(state_open_closing.total_qris + state_open_closing.manual_total_bank + state_open_closing.manual_total_cash) + ( state_open_closing.first_cash_drawer - state_open_closing.manual_total_cash )
AS grand_total,
SUM( proses_pembayaran.total_diskon ) AS total_diskon,
state_open_closing.id_user,
pegawai.nama_lengkap AS kasir
FROM
state_open_closing
LEFT JOIN proses_pembayaran ON state_open_closing.id_user = proses_pembayaran.id_user
AND DATE ( tanggal_open ) BETWEEN '?' <- tanggal awal
AND '?' <- tanggal akhir
INNER JOIN pegawai ON state_open_closing.id_user = pegawai.id_user
WHERE
DATE (tanggal_open) BETWEEN '?' <- tanggal awal
AND '?' <- tanggal akhir
AND state_open_closing.id_user = '?';

pastikan dropdownlist pilih kasir valuenya id_user bukana nama_lengkap
