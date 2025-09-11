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
              <form id="loginForm">
                <div class="form-floating mb-3">
                  <input class="form-control" id="inputEmail" type="email" placeholder="name@example.com" required />
                  <label for="inputEmail">Email address</label>
                </div>
                <div class="form-floating mb-3">
                  <input class="form-control" id="inputPassword" type="password" placeholder="Password" required />
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
    <script>
      document.getElementById('loginForm').addEventListener('submit', function(event) {
        var email = document.getElementById('inputEmail').value;
        var password = document.getElementById('inputPassword').value;

        if (email.trim() === '' || password.trim() === '') {
          alert('Email and password cannot be empty');
          event.preventDefault();
        }
      });
    </script>
  </body>
</html>