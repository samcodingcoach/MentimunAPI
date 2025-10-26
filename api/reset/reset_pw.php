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
            
            require_once '../../config/PHPMailer/src/Exception.php';
            require_once '../../config/PHPMailer/src/PHPMailer.php';
            require_once '../../config/PHPMailer/src/SMTP.php';

            $stmt_smtp = $conn->prepare("SELECT email, password FROM smtp LIMIT 1");
            $stmt_smtp->execute();
            $smtp_result = $stmt_smtp->get_result();

            if ($smtp_result->num_rows > 0) {
                $smtp_data = $smtp_result->fetch_assoc();
                $smtp_email = $smtp_data['email'];
                $smtp_password = $smtp_data['password'];

                $mail = new PHPMailer\PHPMailer\PHPMailer(true);

                try {
                    //Server settings
                    $mail->isSMTP();
                    $mail->Host       = 'smtp.gmail.com';
                    $mail->SMTPAuth   = true;
                    $mail->Username   = $smtp_email;
                    $mail->Password   = $smtp_password;
                    $mail->SMTPSecure = PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
                    $mail->Port       = 587;

                    //Recipients
                    $mail->setFrom($smtp_email, 'Password Reset');
                    $mail->addAddress($email);

                    // Content
                    $mail->isHTML(true);
                    $mail->Subject = 'Password Reset Token';
                    $mail->Body    = 'Your password reset token is: ' . $token;

                    $mail->send();
                    $response = [
                        'status' => 'success',
                        'message' => 'Password reset token has been sent to your email.'
                    ];
                } catch (Exception $e) {
                    $response = [
                        'status' => 'error',
                        'message' => "Message could not be sent. Mailer Error: {$mail->ErrorInfo}"
                    ];
                }
            } else {
                $response = [
                    'status' => 'error',
                    'message' => 'SMTP settings not found.'
                ];
            }
            $stmt_smtp->close();
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
