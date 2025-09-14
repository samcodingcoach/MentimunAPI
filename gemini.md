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

pada kolom tanggal ketika di klik membuka modal untuk detail per kasir
dengan data query sbg berikut
SELECT
proses_pembayaran.kode_payment,
DATE_FORMAT(proses_pembayaran.tanggal_payment, '%d-%m-%Y') AS tanggal,
DATE_FORMAT(proses_pembayaran.tanggal_payment, '%H:%i') AS jam,
CASE
WHEN metode_pembayaran.kategori = 'Transfer' THEN
CASE
WHEN proses_edc.transfer_or_edc = 0 THEN 'Transfer via Bank'
WHEN proses_edc.transfer_or_edc = 1 THEN 'Transfer via EDC'
ELSE 'Transfer'
END
ELSE metode_pembayaran.kategori
END AS kategori,
CASE
when proses_pembayaran.model_diskon = 'PERSENTASE' THEN CONCAT('-',proses_pembayaran.nilai_persen,'%')
when proses_pembayaran.model_diskon = 'NOMINAL' THEN CONCAT('-Rp ',FORMAT(proses_pembayaran.nilai_nominal,0))
END as diskon,
COALESCE(total_diskon,0) as total_diskon,
jumlah_uang AS total_bersih,
jumlah_uang + COALESCE(proses_pembayaran.total_diskon,0) as total_kotor

FROM
proses_pembayaran
INNER JOIN
metode_pembayaran ON proses_pembayaran.id_bayar = metode_pembayaran.id_bayar
LEFT JOIN
proses_edc ON proses_pembayaran.kode_payment = proses_edc.kode_payment

    WHERE proses_pembayaran.`status` = 1 and DATE(tanggal_payment) BETWEEN '?1' AND '?2' and id_user = '?3'

ORDER BY
proses_pembayaran.id_checkout ASC

keterangan ?1 tanggal awal, ?2 tanggal akhir ?3 id_user
format tanggal YYYY-MM-DD

buat modal minimalis , responsive, paging sesuai dengan pattern yang ada
