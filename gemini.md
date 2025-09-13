pada pembatalan.php
buatkan table menampilkan data dengan query dibawah ini
SELECT
id_batal,
waktu,
pesanan.id_tagihan,
meja.nomor_meja,
pegawai.nama_lengkap,
SUM(produk_sell.harga_jual) AS total_harga_jual,
sum(dapur_batal.qty) as total_item
FROM
dapur_batal
INNER JOIN dapur_order_detail ON dapur_batal.id_order_detail = dapur_order_detail.id_order_detail
INNER JOIN pesanan ON dapur_batal.id_pesanan = pesanan.id_pesanan
INNER JOIN pesanan_detail ON dapur_order_detail.id_pesanan_detail = pesanan_detail.id_pesanan_detail
INNER JOIN produk_sell ON pesanan_detail.id_produk_sell = produk_sell.id_produk_sell
INNER JOIN meja ON pesanan.id_meja = meja.id_meja
INNER JOIN view_produk ON produk_sell.id_produk = view_produk.id_produk
INNER JOIN pegawai ON dapur_batal.id_user = pegawai.id_user
WHERE
DATE(dapur_batal.waktu) BETWEEN '?' AND '?'
GROUP BY
pesanan.id_tagihan, meja.nomor_meja
ORDER BY
pesanan.id_tagihan DESC

pada where dan between anda harus datetimepicker pencarian antara tanggal mulai dan tanggal selesai
pastikan desain pattern ui harus mengikuti yang sudah ada yaitu menu.php

hapus summary card pada line 473
hapus fitur cetak
ikuti css untuk table dan responsive di shift_kasir.php
