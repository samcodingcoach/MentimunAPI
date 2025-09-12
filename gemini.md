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

perintah 8 CRUD Meja untuk halaman meja
table meja
id_meja	int(11)	NO	PRI		auto_increment
nomor_meja	int(11)	YES			
aktif	varchar(1)	YES		1	
in_used	varchar(1)	YES		0	
pos_x	float	YES			
pos_y	float	YES			
update_at	datetime	YES		current_timestamp()	on update current_timestamp()

mengikuti pattern ui dan backend vendor.php


perintah 9 CRUD Metode Pembayaran untuk halaman metode pembayaran
table metode_pembayaran
id_bayar	int(11)	NO	PRI		auto_increment
kategori	varchar(20)	YES			
no_rek	varchar(25)	YES			
biaya_admin	double	YES			
keterangan	text	YES			
pramusaji	varchar(1)	YES		0	0=Tidak, 1=Ya
aktif	varchar(1)	YES		0	

catatan= tidak ada hapus dan create new
mengikuti pattern ui dan backend vendor.php

perintah 10 CRUD informasi untuk halaman informasi
table informasi
id_info	int(11)	NO	PRI		auto_increment
judul	varchar(50)	YES			
isi	text	YES			
divisi	varchar(20)	YES			    All,Admin,Kasir,Pramusaji,Dapur
gambar	varchar(255)	YES			ini pakai browser upload gambar minimal 1280x720 dan max 500kb dan uploadke public/images
link	varchar(255)	YES			ini pakai link copy paste
id_users	int(11)	YES			
created_time	datetime	YES		current_timestamp()	on update current_timestamp()

mengikuti pattern ui dan backend vendor.php pastikan koneksiny mysqli bukan pdo. 

perintah 11 CRUD Kategori Menu 
table kategori_menu
id_kategori	int(11)	NO	PRI		auto_increment
nama_kategori	varchar(25)	NO			
aktif	varchar(1)	NO		1	

mengikuti pattern ui dan backend vendor.php pastikan koneksiny mysqli bukan pdo. 
pada kolom aksi hanya ada edit

perintah 12 CRUD Kategori Bahan
table kategori_bahan
id_kategori	int(11)	NO	PRI		auto_increment
nama_kategori	varchar(30)	NO	UNI		

mengikuti pattern ui 100% dan backend vendor.php pastikan koneksiny mysqli bukan pdo. 
pada kolom aksi hanya ada edit dan jangan sampe ada masalah-masalah lain seperti beda ui css, error 500, modal tidak tertutup

perintah 13 CRUD Bahan (bahan.php)
table bahan
id_bahan	int(11)	NO	PRI		auto_increment
id_kategori	int(11)	NO			
nama_bahan	varchar(30)	YES			
kode_bahan	varchar(10)	YES					

mengikuti pattern ui 100% dan backend vendor.php pastikan koneksiny mysqli bukan pdo. 
pada kolom aksi hanya ada edit dan jangan sampe ada masalah-masalah lain seperti beda ui css, error 500, modal tidak tertutup, tampilan mobile berantakan dan tidak responsive, 

id_kategori diambil dari table kategori_bahan.id_kategori tampilkan saja inputan pakai dropdownlist
kode_bahan manual max 6 karakter

perintah 14 CRUD Produk Menu (menu.php)
table produk_menu
id_produk	int(11)	NO	PRI		auto_increment
kode_produk	varchar(30)	YES			
nama_produk	varchar(30)	YES			
id_kategori	int(11)	YES			
id_harga	int(11)	YES			
aktif	varchar(1)	YES		1	

mengikuti pattern ui 100% dan backend vendor.php pastikan koneksiny mysqli bukan pdo. 
pada kolom aksi hanya ada edit dan jangan sampe ada masalah-masalah lain seperti beda ui css, error 500, modal tidak tertutup, tampilan mobile berantakan dan tidak responsive, 

id_kategori diambil dari table kategori_menu.id_kategori tampilkan saja inputan pakai dropdownlist seacrhable
dan untuk tampilan ditable adalah seperti query dibawah ini
(
    SELECT
        pm.id_produk, 
        pm.kode_produk, 
        CONCAT('[',km.nama_kategori,'] ', pm.nama_produk) as nama_produk,
        COALESCE(CONCAT('Rp ',FORMAT(hm.nominal,0)), 'Not Set') as harga, 
        DATE_FORMAT(hm.tgl,'%d %M %Y') as tgl, 
        CONCAT(pg.nama_lengkap,' [',pg.jabatan,']') as pegawai
    FROM produk_menu pm
    INNER JOIN kategori_menu km ON pm.id_kategori = km.id_kategori
    LEFT JOIN (
        SELECT id_produk, MAX(tgl) AS max_tgl
        FROM harga_menu
        GROUP BY id_produk
    ) AS lh ON pm.id_produk = lh.id_produk
    LEFT JOIN harga_menu hm ON pm.id_produk = hm.id_produk AND hm.tgl = lh.max_tgl
    LEFT JOIN pegawai pg ON hm.id_user = pg.id_user
    ORDER BY pm.id_produk DESC
    LIMIT 1
)

UNION ALL

-- Ambil semua selain produk terbaru
(
    SELECT
        pm.id_produk, 
        pm.kode_produk, 
        CONCAT('[',km.nama_kategori,'] ', pm.nama_produk) as nama_produk,
        COALESCE(CONCAT('Rp ',FORMAT(hm.nominal,0)), 'Not Set') as harga, 
        DATE_FORMAT(hm.tgl,'%d %M %Y') as tgl, 
        CONCAT(pg.nama_lengkap,' [',pg.jabatan,']') as pegawai
    FROM produk_menu pm
    INNER JOIN kategori_menu km ON pm.id_kategori = km.id_kategori
    LEFT JOIN (
        SELECT id_produk, MAX(tgl) AS max_tgl
        FROM harga_menu
        GROUP BY id_produk
    ) AS lh ON pm.id_produk = lh.id_produk
    LEFT JOIN harga_menu hm ON pm.id_produk = hm.id_produk AND hm.tgl = lh.max_tgl
    LEFT JOIN pegawai pg ON hm.id_user = pg.id_user
    WHERE pm.id_produk NOT IN (
        SELECT id_produk FROM (
            SELECT id_produk 
            FROM produk_menu 
            ORDER BY id_produk DESC 
            LIMIT 1
        ) AS sub
    )
    ORDER BY hm.tgl DESC, pm.nama_produk ASC