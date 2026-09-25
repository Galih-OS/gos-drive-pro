<?php

require 'config/db.php';


/* =========================================
   REGISTER
========================================= */

if (isset($_POST['register'])) {

    $username = trim($_POST['username'] ?? '');
    $plainPassword = $_POST['password'] ?? '';


    /* =========================================
       VALIDASI
    ========================================= */

    if ($username === '') {

        $error = "Username wajib diisi.";

    } elseif ($plainPassword === '') {

        $error = "Password wajib diisi.";

    } else {

        /* =========================================
           CEK USERNAME
        ========================================= */

        $check = $pdo->prepare("
            SELECT id
            FROM users
            WHERE username = ?
            LIMIT 1
        ");

        $check->execute([
            $username
        ]);


        if ($check->fetch()) {

            $error = "Username sudah digunakan.";

        } else {

            /* =========================================
               HASH PASSWORD
            ========================================= */

            $password = password_hash(
                $plainPassword,
                PASSWORD_DEFAULT
            );


            /* =========================================
               DEFAULT QUOTA
               
               0 = TANPA BATAS
            ========================================= */

            $storageQuota = 0;


            /* =========================================
               INSERT USER
            ========================================= */

            $stmt = $pdo->prepare("
                INSERT INTO users
                (
                    username,
                    password,
                    storage_quota
                )
                VALUES
                (
                    ?,
                    ?,
                    ?
                )
            ");

            $stmt->execute([
                $username,
                $password,
                $storageQuota
            ]);


            /* =========================================
               REDIRECT LOGIN
            ========================================= */

            header(
                "Location: login.php?success=1"
            );

            exit;
        }
    }
}

?>

<!DOCTYPE html>
<html>
<head>
<title>Daftar - Mini Drive PRO</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
<style>
body{
    background: linear-gradient(135deg,#e0f7fa,#e3f2fd);
    font-family: 'Segoe UI', sans-serif;
}
.card{
    border-radius:20px;
}
</style>
</head>
<body>

<div class="container mt-5">
<div class="row justify-content-center">
<div class="col-md-4">

<div class="card p-4 shadow">
<h3 class="mb-3 text-center">Daftar Akun</h3>

<?php if(isset($error)): ?>
<div class="alert alert-danger">
    <?= htmlspecialchars($error) ?>
</div>
<?php endif; ?>

<form method="POST">
    <input type="text" name="username" class="form-control mb-3" placeholder="Username" required>
    <input type="password" name="password" class="form-control mb-3" placeholder="Password" required>
    <button name="register" class="btn btn-primary w-100">Daftar</button>
</form>

<hr>
<div class="text-center">
    Sudah punya akun? <a href="login.php">Login</a>
</div>

</div>
</div>
</div>
</div>

</body>
</html>
