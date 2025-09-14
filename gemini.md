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
