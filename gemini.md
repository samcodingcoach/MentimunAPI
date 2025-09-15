pada index.php buat dashboard untuk menampilkan hal berikut
Informasi Terbaru

berikut querynya

SELECT
id_info,
judul,
isi,
divisi,
gambar,
link,
pegawai.nama_lengkap,
created_time,
CASE
WHEN TIMESTAMPDIFF(HOUR, created_time, NOW()) < 8 THEN
CASE
WHEN TIMESTAMPDIFF(HOUR, created_time, NOW()) < 1 THEN CONCAT(TIMESTAMPDIFF(MINUTE, created_time, NOW()), ' menit yang lalu')
WHEN TIMESTAMPDIFF(HOUR, created_time, NOW()) = 1 THEN '1 jam yang lalu'
ELSE CONCAT(TIMESTAMPDIFF(HOUR, created_time, NOW()), ' jam yang lalu')
END
ELSE
DATE_FORMAT(created_time,'%d %M %Y %H:%i')
END AS waktu_tampil

FROM
informasi
INNER JOIN
pegawai
ON
id_users = pegawai.id_user ORDER BY created_time desc

tampilkan dalam bentuk bukan table tetapi seperti post artikel yang modern

gambar posisi /public/images/info/
