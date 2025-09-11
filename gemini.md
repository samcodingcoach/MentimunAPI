perintah 4 MEMBUAT TEMPLATE UI
dalam aplikasi ini terdapat 3 role administrator,kasir atau pramusaji, dapur

untuk admin dapat membuka semua
daput dapat membuka Produk,Pembelian,Inventori,Laporan,Pengaturan
kasir dapat membuka Laporan,Pengaturan

Beranda
Informasi
Master->
-Resto 
-Pegawai
-Vendor
-Meja
-Metode Pembayaran
-Bayar
Produk->
-Kategori Menu
-Menu
-Kategori Bahan
-Bahan
-Resep
Pembelian->
-Pesanan Pembelian
-Pembayaran
Penjualan->
-Shift Kasir
-Biaya Lain
-Harga Pokok Penjualan
-Harga Rilis
-Pembatalan
Inventory->
-Inventory
-Transaksi
Laporan->
-Transaksi
-Pengeluaran vs Penjualan
-Kuantitas
Pengaturan->
-Ubah Password
Logout


perintah 5 CRUD Resto
table: perusahaan
id_app	int(11)	NO	PRI		auto_increment
nama_aplikasi	varchar(50)	YES			
alamat	text	YES			
no_hp	varchar(15)	YES			
update_time	datetime	YES		current_timestamp()	on update current_timestamp()
serverkeymidtrans	varchar(100)	YES		

#beri nama halaman resto.php

perintah 6 CRUD Pegawai
table pegawai
id_user	int(11)	NO	PRI		auto_increment
nama_lengkap	varchar(30)	YES			
jabatan	enum('Admin','Kasir','Pramusaji','Dapur')	YES			
nomor_hp	varchar(15)	YES			
email	varchar(30)	YES			
password	varchar(255)	YES			
aktif	tinyint(4)	YES		1	

#berinama halaman pegawai.php