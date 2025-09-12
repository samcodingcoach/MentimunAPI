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

#perintah 15#
buatkan CRUD Resep (resep.php)
table resep
id_resep	int(11)	NO	PRI		auto_increment
kode_resep	varchar(50)	YES			
id_produk	int(11)	YES			
harga_pokok_resep	double	YES		0	
id_user	int(11)	YES			
tanggal_release	datetime	YES		current_timestamp()	on update current_timestamp()

mengikuti pattern ui 100% dan backend vendor.php pastikan koneksiny mysqli bukan pdo. 
pada kolom aksi hanya ada edit dan jangan sampe ada masalah-masalah lain seperti beda ui css, error 500, modal tidak tertutup, tampilan mobile berantakan dan tidak responsive, 

PENTING:
1. inputannya hanya kode_resep,id_produk,id_user
   id_produk berarti ambil dropdownlist searchable data dari table produk muncul nama_produk
2. id_user ambil dr session
3. tampilah tablenya ada dibawah ini

SELECT
	resep.id_resep, 
	resep.kode_resep, 
	resep.id_produk, 
  CONCAT(produk_menu.nama_produk,' - ',kategori_menu.nama_kategori, ' [',produk_menu.kode_produk,']') as nama_produk,
	resep.id_user, 
  CONCAT(pegawai.nama_lengkap,' [',pegawai.jabatan,']') as pembuat_resep,

  DATE_FORMAT(resep.tanggal_release,'%d %M %Y %H:%i') as tanggal_release,
	resep.pin,
  COUNT(resep_detail.id_bahan) as qty_bahan,
 CONCAT('Rp ', FORMAT(COALESCE(SUM(resep_detail.nilai_ekpetasi), 0), 0)) AS nilai,resep.publish_menu
FROM
	resep
	INNER JOIN
	produk_menu
	ON 
		resep.id_produk = produk_menu.id_produk
	INNER JOIN
	kategori_menu
	ON 
		produk_menu.id_kategori = kategori_menu.id_kategori
	INNER JOIN
	pegawai
	ON 
		resep.id_user = pegawai.id_user
	left JOIN
	resep_detail
	ON 
		resep.id_resep = resep_detail.id_resep
    
   GROUP BY resep.id_resep
   ORDER BY resep.tanggal_release DESC


#perintah 16#
Buat CRUD untuk detail resep (resep_detail.php)
table resep_detail
id_resep_detail	int(11)	NO	PRI		auto_increment
id_resep	int(11)	YES			
id_bahan	int(11)	YES			
id_bahan_biaya	int(11)	YES			
satuan_pemakaian	varchar(15)	YES			
jumlah_pemakaian	double	YES			
nilai_ekpetasi	double	YES		

untuk tampilan table querynya sebagai berikut

$id = $_GET['id_resep'];
$sql = "SELECT
	resep_detail.id_resep_detail, 
	resep_detail.id_resep, 
  CONCAT(bahan.nama_bahan, ' [', kategori_bahan.nama_kategori,']',' | ',bahan.kode_bahan) as nama_bahan,
	resep_detail.id_bahan, 
  CONCAT('Rp ',FORMAT(bahan_biaya.harga_satuan,0),'/',bahan_biaya.satuan) as harga_satuan,
	resep_detail.id_bahan_biaya, 
  CONCAT('Rp ',FORMAT(resep_detail.nilai_ekpetasi,0)) as nilai_ekpetasi,
  CONCAT(resep_detail.jumlah_pemakaian,' ',resep_detail.satuan_pemakaian) satuan_pemakaian
FROM
	resep_detail
	INNER JOIN
	bahan
	ON 
		resep_detail.id_bahan = bahan.id_bahan
	INNER JOIN
	kategori_bahan
	ON 
		bahan.id_kategori = kategori_bahan.id_kategori
	INNER JOIN
	bahan_biaya
	ON 
		resep_detail.id_bahan_biaya = bahan_biaya.id_bahan_biaya
    
    where resep_detail.id_resep = '$id'

    untuk oprasi tambah, skip dulu 

    perintah 17

    buatkan tombol new detail pada resep_detail.php?id=X dan simpan ke table resep_detail
    lalu modal inputnya sebagai berikut

    1. Cari Bahan -> Pakai Dropdownlist searchable 
    isinya dengan query dibawah ini
    SELECT
    bahan_biaya.id_bahan_biaya,
    bahan_biaya.id_bahan,
    bahan.nama_bahan,
    kategori_bahan.nama_kategori,
    CONCAT(FORMAT(bahan_biaya.harga_satuan,0),'/',bahan_biaya.satuan) as harga_satuan,
    bahan_biaya.tanggal
FROM
    bahan_biaya
INNER JOIN (
    SELECT 
        id_bahan, 
        satuan, 
        MAX(tanggal) AS max_tanggal
    FROM 
        bahan_biaya
    GROUP BY 
        id_bahan, satuan
) latest
ON bahan_biaya.id_bahan = latest.id_bahan 
   AND bahan_biaya.satuan = latest.satuan 
   AND bahan_biaya.tanggal = latest.max_tanggal
INNER JOIN bahan 
    ON bahan_biaya.id_bahan = bahan.id_bahan
INNER JOIN kategori_bahan 
    ON bahan.id_kategori = kategori_bahan.id_kategori  where nama_bahan like '$nama_produk%'

    2. lalu ada satuan dan sub satuan, dengan dropdownlist
    dengan schema berikut yang saya ambil dari c#, bisa anda terapkan diphp, yang tersimpan di table adalah subsatuannya

    private Dictionary<string, Dictionary<string, string>> satuanData = new Dictionary<string, Dictionary<string, string>>()
  {
      { "Berat", new Dictionary<string, string>
          {
              { "Miligram (mg)", "mg" },
              { "Gram (g)", "g" },
              { "Ons (ons)", "ons" },
              { "Kilogram (kg)", "kg" },
              { "Kuintal (kwintal)", "kwintal" },
              { "Ton (ton)", "ton" }
          }
      },
      { "Volume", new Dictionary<string, string>
          {
              { "Mililiter (ml)", "ml" },
              { "Centiliter (cl)", "cl" },
              { "Desiliter (dl)", "dl" },
              { "Liter (l)", "l" },
              { "Galon (galon)", "galon" }
          }
      },
      { "Jumlah", new Dictionary<string, string>
          {
              { "Butir", "butir" },
              { "Siung", "siung" },
              { "Batang", "batang" },
              { "Ikat", "ikat" },
              { "Buah", "buah" },
              { "Pack", "pack" }
          }
      },
      { "Rumah Tangga", new Dictionary<string, string>
          {
              { "Sejumput", "sejumput" },
              { "Sendok Teh (sdt)", "sdt" },
              { "Sendok Makan (sdm)", "sdm" },
              { "Cangkir / Cup", "cup" },
              { "Gelas", "gelas" }
          }
      }
  };

  3. jumlah_pemakaian dan 4. perkiraan biaya
  contoh insertnya dr project sebelumnya
  // Cek apakah bahan sudah ada di resep ini
$check = mysqli_query($conn, "SELECT id_resep, id_bahan FROM resep_detail WHERE id_bahan = '$id_bahan' AND id_resep = '$id_resep'");

if (mysqli_num_rows($check) > 0) 
{
    
    $response = [
        'status' => 'duplikat',
        'message' => 'Gagal Simpan',
      
    ];
} 
else {
    // Insert data ke resep_detail
    $sql = "INSERT INTO resep_detail (id_resep, id_bahan, id_bahan_biaya, satuan_pemakaian, jumlah_pemakaian, nilai_ekpetasi) 
            VALUES ('$id_resep', '$id_bahan', '$id_bahan_biaya', '$satuan_pemakaian', '$jumlah_pemakaian', '$nilai_ekpetasi')";

    if (mysqli_query($conn, $sql)) {
        $response = [
            'status' => 'success',
            'message' => 'Simpan sukses'
        ];
    } 
    else {
        $response['message'] = 'Gagal menyimpan data: ' . mysqli_error($conn);
    }
}

perintah 15
pada menu.php, tambahkan perintah berikut
1. pada kolom aksi tambahkan tombol / link Set Harga
   buat file baru harga.php dengan parameter id_produk=X
   dalam harga.php 
   menampilkan data dari dari query berikut

   SELECT
  DATE_FORMAT( harga_menu.tgl ,'%d %M %Y %H:%i') AS tgl, 
	harga_menu.id_harga, 
	harga_menu.id_resep,
  CONCAT(pegawai.nama_lengkap,' - ',pegawai.jabatan) as user_harga,
  CONCAT(pegawai.nama_lengkap,' - ',pegawai.jabatan) as user_resep, 
	resep.harga_pokok_resep, 
  biaya_produksi, 
	harga_menu.margin as margin, 
	nominal
FROM
	harga_menu
	INNER JOIN
	produk_menu
	ON 
		harga_menu.id_produk = produk_menu.id_produk
	INNER JOIN
	resep
	ON 
		harga_menu.id_resep = resep.id_resep
	INNER JOIN
	pegawai
	ON 
		harga_menu.id_user = pegawai.id_user AND
		resep.id_user = pegawai.id_user 
    
    WHERE produk_menu.id_produk = 22
    ORDER BY DATE(harga_menu.tgl) desc, harga_menu.nominal ASC

    
