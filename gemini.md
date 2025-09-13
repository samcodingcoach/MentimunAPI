buatkan di biaya_lain.php untuk crud ppn dan takeaway
dihalaman ini tetap pakai pattern ui halaman sebelumnya namun di main nya ada 2 tab
1. tab Pajak ,
2. tab Takeaway
berikut quernya ppn
SELECT
	ppn.id_ppn, 
	ppn.nilai_ppn, <-persen ada koma karna di table pakai type double
	ppn.keterangan, 
	DATE_FORMAT(ppn.rilis,'%d %M %Y') as rilis, 
	ppn.aktif
FROM
	ppn
  ORDER BY id_ppn DESC

query takeaway
SELECT biaya_per_item FROM `takeaway_charge` ORDER BY DATE(tanggal_rilis) desc limit 1 

setiap tab ada crudnya dan pada ppn bisa aktif dan tidak aktif jadi ketika di ada inputan baru maka row dibawahnya atau sebelumnya menjadi tidak Aktif
saya tampilkan struktur ppn 
id_ppn	int(11)	NO	PRI		auto_increment
nilai_ppn	double	YES			
keterangan	text	YES			
rilis	date	YES		current_timestamp()	
aktif	tinyint(4)	YES		0	

dan takeaway_charge
id_ta	int(11)	NO	PRI		auto_increment
tanggal_rilis	date	YES		current_timestamp()	
id_user	int(11)	YES			
biaya_per_item	double	YES		0	
