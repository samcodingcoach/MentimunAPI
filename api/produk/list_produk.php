<?php
header('Content-Type: application/json');
error_reporting(E_ALL & ~E_NOTICE);

include "../../config/koneksi.php";

$sql = "
SELECT
    ps.id_produk_sell,
    ps.id_produk, 
    vp.kode_produk, 
    vp.nama_produk, 
    ps.stok, 
    ps.harga_jual
FROM
    produk_sell ps
INNER JOIN
    view_produk vp ON ps.id_produk = vp.id_produk
WHERE 
    ps.tgl_release = CURDATE()";

$params = array();
$types = "";

if (isset($_GET['id_kategori']) && !empty($_GET['id_kategori'])) {
    $sql .= " AND vp.id_kategori = ?";
    $params[] = intval($_GET['id_kategori']);
    $types .= "i";
}

$sql .= " ORDER BY vp.nama_kategori ASC, vp.nama_produk ASC";

// Menyiapkan statement
$stmt = mysqli_prepare($conn, $sql);

if ($stmt) {
    // Mengikat parameter jika ada
    if (!empty($params)) {
        mysqli_stmt_bind_param($stmt, $types, ...$params);
    }

    // Menjalankan statement
    mysqli_stmt_execute($stmt);

    // Mengambil hasil
    $result = mysqli_stmt_get_result($stmt);

    // Membuat array untuk menyimpan hasil query
    $data = array();

    // Mengambil hasil query dan memasukkannya ke dalam array
    if (mysqli_num_rows($result) > 0) {
        while($row = mysqli_fetch_assoc($result)) {
            $data[] = $row;
        }
    }

    // Mengembalikan data dalam format JSON
    echo json_encode($data);

    // Menutup statement
    mysqli_stmt_close($stmt);
} else {
    // Penanganan error jika statement gagal disiapkan
    echo json_encode(array("error" => "Gagal menyiapkan statement SQL: " . mysqli_error($conn)));
}

// Menutup koneksi
mysqli_close($conn);

?>