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

