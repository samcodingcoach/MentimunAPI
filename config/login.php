<?php
error_reporting(E_ALL & ~E_NOTICE);
include "koneksi.php";
$data = json_decode(file_get_contents('php://input'), true);

$email = $data['email'];
$password = $data['password'];

function encryptPassword($password, $key) {
    $cipher = "AES-256-CBC"; // Algoritma enkripsi
    $iv = substr(hash('sha256', $key), 0, 16); // IV dari hash key

    $encrypted = openssl_encrypt($password, $cipher, $key, 0, $iv);
    return $encrypted;
}

function decryptPassword($encryptedPassword, $key) 
{
    $cipher = "AES-256-CBC";
    $iv = substr(hash('sha256', $key), 0, 16);

    $decrypted = openssl_decrypt($encryptedPassword, $cipher, $key, 0, $iv);
    return $decrypted;
}
      
    $sql2 = "SELECT state_open_closing.id_open, state_open_closing.id_user, pegawai.nama_lengkap, pegawai.email, pegawai.nomor_hp,pegawai.`password` FROM state_open_closing INNER JOIN pegawai ON state_open_closing.id_user = pegawai.id_user WHERE state_open_closing.`status` = 1 and DATE(state_open_closing.tanggal_open) = CURDATE() and email='$email'";
    $result_sql2 = mysqli_query($conn, $sql2);
        
    if ($result_sql2) 
    {
            if (mysqli_num_rows($result_sql2) > 0) 
            {
                $row = mysqli_fetch_assoc($result_sql2);
                $id_user = $row['id_user'];
                $nomor_hp = $row['nomor_hp'];
               
                $storedPassword = $row['password'];
                $encinput = encryptPassword($password, $nomor_hp);

                if ($storedPassword === $encinput) 
                {
                    $response = ['status' => 'success', 
                    'message' => 'Login Berhasil',
                    'id_user' => $id_user,
                    'nama_lengkap' => $row['nama_lengkap'],
                    'id_open' => $row['id_open']];
                } 
                else 
                {
                    $response = ['status' => 'failed','message' => '1Password atau Email Salah'];
                }

            }
            else
            {
                {$response = ['status' => 'failed','message' => '2Password atau Email Salah'];}     
            }      
          
    } 
    else 
    {
        $response = ['status' => 'error',  'message' => 'Gagal menyimpan data: ' . mysqli_error($conn)];
    }

header('Content-Type: application/json');
echo json_encode($response);
mysqli_close($conn);
?>
