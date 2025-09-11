<?php
session_start();
include '../../config/koneksi.php';

function decryptPassword($encryptedPassword, $key) {
    $cipher = "AES-256-CBC";
    $iv = substr(hash('sha256', $key), 0, 16);
    $decrypted = openssl_decrypt($encryptedPassword, $cipher, $key, 0, $iv);
    return $decrypted;
}

$error = '';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $email = $_POST['email'];
    $password = $_POST['password'];

    if (empty($email) || empty($password)) {
        $error = "Email and password are required.";
    } else {
        $sql = "SELECT * FROM pegawai WHERE email = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows == 1) {
            $row = $result->fetch_assoc();
            $decrypted_password = decryptPassword($row['password'], $row['nomor_hp']);

            if ($password === $decrypted_password) {
                $_SESSION['loggedin'] = true;
                $_SESSION['id_user'] = $row['id_user'];
                $_SESSION['nama_lengkap'] = $row['nama_lengkap'];
                $_SESSION['jabatan'] = $row['jabatan'];
                header("location: index.php");
                exit;
            } else {
                $error = "Invalid password.";
            }
        } else {
            $error = "No account found with that email.";
        }
    }
}
?>

<!doctype html>
<html lang="en">
  <head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Login</title>
    <link href="../css/bootstrap.min.css" rel="stylesheet">
    <link href="../css/custom.css" rel="stylesheet">
  </head>
  <body class="bg-animated vh-100 d-flex align-items-center justify-content-center">
    <div class="container">
      <div class="row justify-content-center">
        <div class="col-lg-5">
          <div class="card shadow-lg border-0 rounded-lg">
            <div class="card-header"><h3 class="text-center font-weight-light my-4">Login</h3></div>
            <div class="card-body">
              <?php 
              if(!empty($error)){
                  echo '<div class="alert alert-danger">' . $error . '</div>';
              }
              ?>
              <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post">
                <div class="form-floating mb-3">
                  <input class="form-control" id="inputEmail" name="email" type="email" placeholder="name@example.com" required />
                  <label for="inputEmail">Email address</label>
                </div>
                <div class="form-floating mb-3">
                  <input class="form-control" id="inputPassword" name="password" type="password" placeholder="Password" required />
                  <label for="inputPassword">Password</label>
                </div>
                <div class="d-flex align-items-center justify-content-end mt-4 mb-0">
                  <button class="btn btn-primary" type="submit">Login</button>
                </div>
              </form>
            </div>
          </div>
        </div>
      </div>
    </div>
    <footer class="fixed-bottom text-center py-3">
      <div class="container">
        <span class="text-muted">Copyright &copy; ITDEV 2025</span>
      </div>
    </footer>
    <script src="../js/bootstrap.bundle.min.js"></script>
  </body>
</html>
