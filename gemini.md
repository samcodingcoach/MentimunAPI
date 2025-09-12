perintah 20
harga.php , cek lagi sidebarmenu banyak yang kurang menunya berbeda dengan vendor.php

kemudian pada table harga.php, tambahkan kolom action dan tombol 'Harga' dan membuka modal
yang isinya update ke table harga_menu
berikut querynya

UPDATE harga_menu SET nominal = ?, biaya_produksi = ?, margin = ? WHERE id_harga = ?'

design modalnya
-Harga Pokok  (Readonly) ambil dari harga_pokok_resep di table harga.php?id=x
-Biaya Produksi
-Margin , berupa persen atau nominal, berikut contoh algoritma
         biaya produksi 20000
         Harga pokok = 10000
         Persen = 10%
         Nominal = 20000+10000 kemudian 30000 diambil 10% = 3000
-Total = 33000
-Button Update

agar lebih lengkapnya berikut table harga_menu

id_harga	int(11)	NO	PRI		auto_increment
id_produk	int(11)	YES			
biaya_produksi	double	YES		0	
nominal	double	YES		0	
tgl	datetime	YES		current_timestamp()	on update current_timestamp()
id_user	int(11)	YES			
id_resep	int(11)	YES			
margin	double	YES		0	
