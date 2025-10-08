<?php
error_reporting(E_ALL & ~E_NOTICE);
header('Content-Type: application/json');

include "koneksi.php";
$data = json_decode(file_get_contents('php://input'), true);

$id = $data['id_user'];
$sql2 = "UPDATE state_open_closing set status = 0 where 
         id_user='$id' and date(tanggal_open) = curdate() and status = 1";

    if (mysqli_query($conn, $sql2)) 
        {
            
           
                $response = [
                    'status' => 'success',
                    'message' => 'Closing Transaksi Berhasil'
                ];
          
        } 
        else 
        {
            $response = [
                'status' => 'error', 
                'message' => 'Gagal menyimpan data: ' . mysqli_error($conn)
            ];
        }        
   



echo json_encode($response);
mysqli_close($conn);
?>
