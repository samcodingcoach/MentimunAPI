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

perintah 7 CRUD Vendor untuk halaman vendor
table vendor
id_vendor	int(11)	NO	PRI		auto_increment
nama_vendor	varchar(50)	YES			
alamat	text	YES			
kota	varchar(30)	YES			
hp	varchar(20)	YES			
nomor_rekening1	varchar(100)	YES			
nomor_rekening2	varchar(100)	YES			
person	varchar(30)	YES			
email	varchar(30)	YES	UNI		
status	varchar(1)	YES		1	
keterangan	text	YES			
kode_vendor	varchar(15)	YES			Buat Kode Auto VD25-0001  atau VD25-0002 dst , 25 adalah 2 digit akhir tahun

mengikuti pattern ui dan backend pegawai.php