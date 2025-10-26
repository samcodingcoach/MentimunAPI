<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
header('Content-Type: application/json');
include "../../config/koneksi.php";

$data = json_decode(file_get_contents('php://input'), true);


$nomor_hp = $data['nomor_hp'] ?? null;
$email = $data['email'] ?? null;


if (empty($nomor_hp) || empty($email)) {
    $response = [
        'status' => 'error',
        'message' => 'Semua field harus diisi.'
    ];
} 
else {

    //qwen task 1
    //mencari data pegawai (hanya ambil password )berdasarkan nomor hp dan email
    $stmt = $conn->prepare("SELECT password FROM pegawai WHERE nomor_hp = ? AND email = ?");
    $stmt->bind_param("ss", $nomor_hp, $email);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $pegawai = $result->fetch_assoc();
        $password = $pegawai['password'];
        
        //qwen task 2
        //insert ke table reset_password (email,nomor_hp,token) . token isinya dari qwen task 1 password.
        $token = $password; // Using the existing password as token as per requirement
        
        $insert_stmt = $conn->prepare("INSERT INTO reset_password (email, nomor_hp, token) VALUES (?, ?, ?)");
        $insert_stmt->bind_param("sss", $email, $nomor_hp, $token);
        
        if ($insert_stmt->execute()) {
            $response = [
                'status' => 'success',
                'message' => 'Password reset token has been generated successfully.'
            ];
        } else {
            $response = [
                'status' => 'error',
                'message' => 'Failed to generate password reset token.'
            ];
        }
        $insert_stmt->close();
    } else {
        $response = [
            'status' => 'error',
            'message' => 'Nomor HP and email combination not found in our records.'
        ];
    }
    $stmt->close();
    
}

echo json_encode($response);
$conn->close();
?>
