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
