<?php
session_start();
if (isset($_SESSION['user_id'])) {
    header("Location: ../dashboard.php");
    exit;
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_once '../includes/db.php';

    $email    = trim($_POST['email']);
    $password = $_POST['password'];

    if (empty($email) || empty($password)) {
        $error = "Please enter email/username and password.";
    } else {
        $stmt = $conn->prepare("SELECT id, name, email, password, role FROM users WHERE email = ?");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($row = $result->fetch_assoc()) {
            if ($password === $row['password']) {
                $_SESSION['user_id']    = $row['id'];
                $_SESSION['email']      = $row['email'];
                $_SESSION['full_name']  = $row['name'];
                $_SESSION['role']       = $row['role'];
                header("Location: ../dashboard.php");
                exit;
            }
        }
        $error = "Invalid email/username or password.";
        $stmt->close();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <title>Login - Diesel Trading</title>
    <link href="../assets/sb-admin2/vendor/fontawesome-free/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css?family=Nunito:200,200i,300,300i,400,400i,600,600i,700,700i,800,800i,900,900i" rel="stylesheet">
    <link href="../assets/sb-admin2/css/sb-admin-2.min.css" rel="stylesheet">
    <style>
        :root {
            --navy: #2C3E50;
            --navy-dark: #1A252F;
            --amber: #F39C12;
            --amber-dark: #D68910;
        }
        .bg-gradient-primary {
            background: linear-gradient(135deg, var(--navy) 0%, var(--navy-dark) 100%) !important;
        }
        .btn-primary {
            background-color: var(--amber) !important;
            border-color: var(--amber) !important;
        }
        .btn-primary:hover, .btn-primary:focus {
            background-color: var(--amber-dark) !important;
            border-color: var(--amber-dark) !important;
        }
        .text-primary {
            color: var(--amber) !important;
        }
        a {
            color: var(--amber-dark);
        }
        a:hover {
            color: #B87A0E;
        }
    </style>
</head>
<body class="bg-gradient-primary">
    <div class="container">
        <div class="row justify-content-center align-items-center" style="min-height: 100vh;">
            <div class="col-xl-6 col-lg-8 col-md-9">
                <div class="card o-hidden border-0 shadow-lg my-5">
                    <div class="card-body p-0">
                        <div class="row">
                            <div class="col-lg-12">
                                <div class="p-5">
                                    <div class="text-center">
                                        <div class="mb-3">
                                            <i class="fas fa-fuel-pump fa-3x text-primary"></i>
                                        </div>
                                        <h1 class="h4 text-gray-900 mb-1">Diesel Trading</h1>
                                        <p class="mb-4 text-muted">Purchase Management System</p>
                                    </div>

                                    <?php if ($error): ?>
                                        <div class="alert alert-danger" role="alert">
                                            <i class="fas fa-exclamation-triangle"></i> <?= htmlspecialchars($error) ?>
                                        </div>
                                    <?php endif; ?>

                                    <form method="POST" class="user">
                                        <div class="form-group">
                                            <input type="text" name="email" class="form-control form-control-user"
                                                   placeholder="Enter Email / Username" required
                                                   value="<?= isset($_POST['email']) ? htmlspecialchars($_POST['email']) : '' ?>">
                                        </div>
                                        <div class="form-group">
                                            <input type="password" name="password" class="form-control form-control-user"
                                                   placeholder="Password" required>
                                        </div>
                                        <button type="submit" class="btn btn-primary btn-user btn-block">
                                            <i class="fas fa-sign-in-alt"></i> Login
                                        </button>
                                    </form>
                                    <hr>
                                    <div class="text-center small text-muted">
                                        &copy; <?= date('Y') ?> Diesel Trading System
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <script src="../assets/sb-admin2/vendor/jquery/jquery.min.js"></script>
    <script src="../assets/sb-admin2/vendor/bootstrap/js/bootstrap.bundle.min.js"></script>
    <script src="../assets/sb-admin2/vendor/jquery-easing/jquery.easing.min.js"></script>
    <script src="../assets/sb-admin2/js/sb-admin-2.min.js"></script>
</body>
</html>
