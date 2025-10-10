buatkan satu tombol lagi di kolom aksi setelah Edit, yaitu Tombol 'Biaya', buat tombol saja dulu tidak lebih
revisi width semua kolom table yang tidak beraturan
ketentuan
No: Fit dengan isi
Kode Bahan: Fit
Nama Bahan \*
Kategori fit
Aksi Fit

Ketika tombol biaya di klik muncul modal,
design modal yang sudah ada ,ikuti pattern di halaman ini.
tambahkan input harga dan satuan
tambahkan tombol update

Saya berharap satuan ketika diketik langsung bisa format ribuan contoh
100.000 / 20.000 / 2000

dan satuan berupa dropdownlist inputable. bisa milih dan mengetik
isi secara manual : buah,pcs,kg,mg satuan yang sifatnya sering dipergunakan di dapur dan urutkan berdasarkan ASC

pada modal biaya saya ingin melakukan simpan ke table bahan_biaya
id_bahan_biaya int(11) NO PRI auto_increment
id_bahan int(11) YES 0
satuan varchar(15) YES
harga_satuan double YES 0
tanggal date YES current_timestamp()
id_user int(11) YES

insert kolom id_bahan,satuan,harga_satuan, id_user
id_user diambil dari $\_SESSION['id_user'] = $row['id_user'];
kemudian ketika selesai beri info alert

berhasil melakukan insert , masalahnya untuk harga ketik 5.000 yang tersimpan malah 5 , harusnya anda jadikan double langsung tersimpan 5000
