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


perintah 21
cek semua .* php di public/admin, kecuali login dan logout
edit semua menu side bar href nya
 <li class="nav-item"><a class="nav-link" href="resto.php"><i class="bi bi-building"></i> Resto</a></li>
                    <li class="nav-item"><a class="nav-link" href="pegawai.php"><i class="bi bi-people"></i> Pegawai</a></li>
                    <li class="nav-item"><a class="nav-link" href="vendor.php"><i class="bi bi-truck"></i> Vendor</a></li>
                    <li class="nav-item"><a class="nav-link" href="meja.php"><i class="bi bi-table"></i> Meja</a></li>
                    <li class="nav-item"><a class="nav-link" href="metode_pembayaran.php"><i class="bi bi-credit-card"></i> Metode Pembayaran</a></li>
<li class="nav-item"><a class="nav-link" href="kategori_menu.php"><i class="bi bi-tags"></i> Kategori Menu</a></li>
                    <li class="nav-item"><a class="nav-link" href="menu.php"><i class="bi bi-card-list"></i> Menu</a></li>
                    <li class="nav-item"><a class="nav-link" href="kategori_bahan.php"><i class="bi bi-collection"></i> Kategori Bahan</a></li>
                    <li class="nav-item"><a class="nav-link" href="bahan.php"><i class="bi bi-basket"></i> Bahan</a></li>
                    <li class="nav-item"><a class="nav-link" href="resep.php"><i class="bi bi-journal-text"></i> Resep</a></li>
