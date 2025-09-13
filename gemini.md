CRUD PROMO (promo.php)
table promo
id_promo	int(11)	NO	PRI		auto_increment
created_promo	datetime	YES		current_timestamp()	
nama_promo	varchar(30)	NO			
kode_promo	varchar(10)	YES			
deskripsi	text	YES			
id_user	int(11)	YES			
tanggalmulai_promo	date	YES			
tanggalselesai_promo	date	YES			
nominal	double	YES		0	
persen	double	YES		0	
pilihan_promo	enum('nominal','persen')	YES			
aktif	tinyint(1)	YES		0	
kuota	int(11)	YES		5	
min_pembelian	double	YES		0	

ikuti pattern menu.php

buatkan halaman crud,
untuk data yang di tampilkan dengan query sebagai berikut
 select id_promo,
	nama_promo, 
  kode_promo,
  tanggalmulai_promo,
  tanggalselesai_promo,
	CONCAT(pegawai.jabatan,' - ',pegawai.nama_lengkap) as insert_by,
	promo.kuota, 
	min_pembelian, 
  promo.pilihan_promo,
	case
    when pilihan_promo = 'nominal' then CONCAT('Rp',FORMAT(promo.nominal,0))
    when pilihan_promo = 'persen' then CONCAT(promo.persen,'%')
  
  end as nilai_promo,
  deskripsi,
  promo.aktif
  
FROM
	promo
	INNER JOIN
	pegawai
	ON 
		promo.id_user = pegawai.id_user

        yang ditampilkan sbg berikut

No, Kode Promo, Nama Promo, Kuota,Nilai Promo,Status,Aksi (Nonaktifkan)
dan saat di klik kode promo menampikan dalam bentuk modal
Tanggal Mulai Promo, Tanggal Selesai Promo, Di Insert Oleh, Minimal Pembelian, Pilihan Promo

Detail Promo modalnya terlalu sederhana, buat lebih baik namun tetap minimalis
tidak ada aksi edit. 
