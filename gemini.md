table state_open_closing

id_open	int(11)	NO	PRI		auto_increment
tanggal_open	datetime	YES		current_timestamp()	
first_cash_drawer	double	YES		0	
status	varchar(1)	YES		1	
total_qris	double	YES		0	
manual_total_bank	double	YES		0	
manual_total_cash	double	YES		0	
id_user	int(11)	YES			
id_user2	int(11)	YES			

modal yang dibuat (lihat patterin ui modal di menu.php bagian tambah produk)
1. dropdownlist searchable untuk memilih kasir
   query = SELECT id_user,nama_lengkap,jabatan from pegawai where aktif = '1' and jabatan = 'kasir' or jabatan = 'pramusaji' order by nama_lengkap asc
   tampikan nama lalu pilih valuenya id_user

2. inputan nominal cash awal
3. tanggal hari ini

4. tombol simpan

sebelum simpan cek dulu duplikasi
SELECT id_open FROM state_open_closing where date (tanggal_open) = '? and id_user = ?
jika ada data tidak bisa insert
jika tidak ada data bisa insert
query insert
$jam_sekarang = date('H:i:s');
$tanggal_open_full = $tanggal_open . ' ' . $jam_sekarang;


$sql = "INSERT INTO state_open_closing (tanggal_open,first_cash_drawer,id_user,id_user2) 
VALUES('$tanggal_open_full','$first_cash_drawer','$id_user','$id_user2')";


id_user untuk id_user dropdownlist seacrhable kasir
id_user2 untuk id_user2 = id_user session login
