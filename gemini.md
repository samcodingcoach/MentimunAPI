pada pembayaran_pembelian.php, kita akan lakukan Update bukti Pembayaran
step 1: buatkan ui untuk searching kode_request / no PO

berikut querynya:

SELECT
bahan_request.kode_request,
bahan.nama_bahan,
vendor.kode_vendor,
vendor.nama_vendor,
vendor.nomor_rekening1,
vendor.nomor_rekening2,
bahan_request_detail.isDone, //0 = UNPAID, 1 = PAID//
bahan_request_detail.isInvoice, //0 = INV, 1 = BAYAR LANGSUNG
bahan_request_detail.nomor_bukti_transaksi,
bahan_request_detail.jumlah_request,
bahan_request_detail.harga_est,
bahan_request_detail.subtotal
FROM
bahan_request
INNER JOIN
bahan_request_detail
ON
bahan_request.id_request = bahan_request_detail.id_request
INNER JOIN
bahan
ON
bahan_request_detail.id_bahan = bahan.id_bahan
INNER JOIN
vendor
ON
bahan_request_detail.id_vendor = vendor.id_vendor

        jika ada maka munculkan table diatas

No,....., Action di isikan Bayar

Cukup sampai sini dulu.
jangan melebihi prompt untuk menjawab
