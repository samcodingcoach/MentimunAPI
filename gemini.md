pada kolom aktif jual di harga_rilis, jika statusnya -, maka tampilkan tombol Belum Rilis, 
jika statusnya , maka tampilkan tombol Sudah Rilis

jika tombol Belum Rilis di klik, keluar modal Simpan
berikut konten modalnya
1. Label Nama Produk dan
2. Label tanggal hari ini
3. Label harga jual ambil dari value harga pokok
   tampilan kolom harga jual karena tidak dipake
4. inputan stok
5. checkbox aktif jual (default true)

tombol RILIS

query simpannya adalah
cek dulu 
$cek_sql = "SELECT id_produk_sell FROM produk_sell 
            WHERE id_produk = '$id_produk' 
            AND DATE(tgl_release) = '$tgl_hari_ini'";

jika row > 0 , tidak insert atau pesan data sudah ada
jika row = 0, insert data

$sql = "INSERT INTO produk_sell(id_produk, stok, harga_jual, id_user, aktif,stok_awal) 
            VALUES ('$id_produk', '$stok', '$harga_jual', '$id_user', '$aktif', '$stok')";

values silahkan di sesuaikan

hal lain pada tombol action-> hapus, jika di kolom stok = 0 dan aktif jual = -,
maka bisa dihapus, jika stok > 0 dan aktif jual = -, maka tidak bisa dihapus
