pada konsumen.php buat CRUD untuk Konsumen
berikut query untuk menampilkan table
SELECT
konsumen.id_konsumen,
konsumen.nama_konsumen,
konsumen.no_hp,
konsumen.alamat,
konsumen.email,
konsumen.aktif,
sum(pesanan.total_cart) as total_cart
FROM
konsumen
INNER JOIN
pesanan
ON
konsumen.id_konsumen = pesanan.id_konsumen
where pesanan.status_checkout = 1
GROUP BY pesanan.id_konsumen,pesanan.status_checkout

    ORDER BY nama_konsumen ASC

tampilkan
No, Nama Konsumen, HP, Status, Action
1,XXX,08x,1 = Aktif 0 Nonaktif, Edit
ketika nama konsumen di klik muncul modal untuk tampilan lengkap datanya
ditambah Alamat, dan Email.

dan untuk insert dan edit
berikut describenya
id_konsumen int(11) NO PRI auto_increment
nama_konsumen varchar(50) YES
no_hp varchar(15) YES
alamat text YES
email varchar(50) YES
aktif varchar(1) YES 1
