buatkan pada halaman ini(profile.php) untuk mengedit user yang login

Pada halaman terdapat form dan inputan sbg berikut
Nama Lengkap
Nomor Handphone
Email

Dibawahnya ada 
Nomor Handpone Baru (default ambil dari Nomor Handphone Lama)
Password Baru
Konfirmasi Password Baru
Update


berikut table pegawai 
id_user	int(11)	NO	PRI		auto_increment
nama_lengkap	varchar(30)	YES			
jabatan	enum('Admin','Kasir','Pramusaji','Dapur')	YES			
nomor_hp	varchar(15)	YES			
email	varchar(30)	YES			
password	varchar(255)	YES			
aktif	tinyint(4)	YES		1	

silahkan anda lihat mekanisme enkripsi/deskrip yang ada di pegawai.php
jika sudah berhasil update. kembali ke halaman login.php hapus session yang ada sebelumnya.
