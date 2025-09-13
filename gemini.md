pada login.php
load query berikut
SELECT nama_aplikasi FROM `perusahaan` limit 1
kemudian simpan dalam variable session

kemudian pada index.php
bagian navbar  <a class="navbar-brand" href="#">Resto007 Admin</a>
ubah Resto007 Admin Sesuai dengan variable session
