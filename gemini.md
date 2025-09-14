DESCRIBE vendor;
id_vendor int(11) NO PRI auto_increment
nama_vendor varchar(50) YES
status varchar(1) YES 1
keterangan text YES
kode_vendor varchar(15) YES

DESCRIBE kategori_bahan;
id_kategori int(11) NO PRI auto_increment
nama_kategori varchar(30) NO UNI

DESCRIBE bahan;
id_bahan int(11) NO PRI auto_increment
id_kategori int(11) NO
nama_bahan varchar(30) YES
kode_bahan varchar(10) YES

DESCRIBE bahan_biaya
id_bahan_biaya int(11) NO PRI auto_increment
id_bahan int(11) YES 0
satuan varchar(15) YES
harga_satuan double YES 0
tanggal date YES current_timestamp()
id_user int(11) YES

DESCRIBE bahan_request;
id_request int(11) NO PRI auto_increment
kode_request varchar(50) YES UNI
tanggal_request date YES current_timestamp()
grand_total double YES 0
id_user int(11) YES
status varchar(1) YES 0

DESCRIBE bahan_request_detail;
id_detail_request int(11) NO PRI auto_increment
id_request int(11) YES
id_bahan int(11) YES
id_vendor int(11) YES
jumlah_request int(11) YES
harga_est double YES 0
subtotal double YES
isDone varchar(1) YES 0
isInvoice varchar(1) YES 1
nomor_bukti_transaksi varchar(50) YES
stok_status varchar(1) YES 0
id_bahan_biaya int(11) YES
perubahan_biaya varchar(1) YES 0

pada daftar table diatas saya ingin dibuatkan transaksi pembelian/request (pembelian.php)
kode_request pakai generate number PO-YYMMDD-001
flow aplikasi pilih bahan dengan dropdownlist searchable,
pilih vendor dengan dropdownlist searchable, pada vendor dropdownlist menampilkan juga Kode Vendor, Nama Vendor dan Keterangan.
muncul pilihan satuan yang isinya diambil dari bahan_biaya berdasarkan id_bahan , muncul secara distinct
harga_est diambil dari table bahan_biaya.harga_satuan berdasarkan id_bahan dan satuan.
input jumlah_request
subtotal mengkalikan harga_est \* jumlah_request
ada pilihan Invoice / Bayar Langsung, jika Invoice isDone 0, jika Bayar Langsung isDone 1
isDone = 0, (default, tidak perlu di ubah)
nomor_bukti_transaksi optional isi nanti.
stok_status tidak ditampilkan

Error: Field 'id_user' doesn't have a default value
satuan juga pake searchable dropdownlist
Subtotal 100000.00 hilangkan .00
buat layout inputan tambah item lebih kosisten dan rapi

Error: Field 'id_user' doesn't have a default value, masih dengan ini. pastikan saat menyimpan pastikan sesuai dengan table bahan_request dan dan bahan_request_detail
id_user didapatkan session yang dipakai saat login.php

error
This page isn’t working right now
192.168.77.8 can't currently handle this request.
seperti anda salah paham, pada pembelian.php tidak perlu insert ke bahan_biaya

saya akan berikan teknik insert yang benar
request_bahan
id_request - auto
kode_request - kode_request
tanggal_request date YES current_timestamp() - auto
grand_total double YES 0 - subtotal
id_user int(11) YES - $\_SESSION['id_user']
status varchar(1) YES 0 biarkan default

kemudian request_bahan_detail
id_detail_request int(11) NO PRI auto_increment - auto
id_request int(11) YES - id_request diatas
id_bahan int(11) YES - $row['id_bahan']
id_vendor int(11) YES - $row['id_vendor'];
jumlah_request int(11) YES - jumlah_request
harga_est double YES 0 - harga_est
subtotal double YES - subtotal_display
isDone varchar(1) YES 0 - biarkan default 0
isInvoice varchar(1) YES 1 - Bayar Langsung = 0 , 1 = Invoice
nomor_bukti_transaksi varchar(50) YES - nomor_bukti
stok_status varchar(1) YES 0 biarkan 0
id_bahan_biaya int(11) YES - saya tidak temukan di pembelian.php yg jelas itu dari saat pilih satuan muncul harga estimasi yang mana itu dari table bahan biaya
perubahan_biaya varchar(1) YES 0 biarkan 0

1. membuat Request sehingga diawal request melakukan insert sehingga ada data id_request.
2. setiap melakukan tambah ke daftar maka itu insert data ke bahan_request_detail
3. simpan transaksi itu update bahan_request.status = 1

oke sepertinya saya berhasil melakukan pembelian. tetapi diawal halaman pembelian.php terdapat daftar request / po, quernya seperti berikut.
SELECT
id_request,
bahan_request.kode_request,
bahan_request.tanggal_request,
CONCAT(pegawai.jabatan,'-',pegawai.nama_lengkap) as nama_lengkap,
grand_total as grand_total
FROM
bahan_request
INNER JOIN
pegawai
ON
bahan_request.id_user = pegawai.id_user WHERE DATE(tanggal_request) = '2025-09-14' and status=1
ORDER BY id_request DESC

ada tombol tambah request, kemudian muncul modal inputan tadi sebelumnya
