<?php

require 'config/db.php';

$error = '';
$success = '';


if(isset($_POST['reset_password'])){

    $username = trim($_POST['username']);
    $newPassword = $_POST['new_password'];
    $confirmPassword = $_POST['confirm_password'];


    /* =========================================
       VALIDASI
    ========================================= */

    if($username === ''){

        $error = "Username wajib diisi.";

    }

    elseif(strlen($newPassword) < 6){

        $error =
            "Password baru minimal 6 karakter.";

    }

    elseif($newPassword !== $confirmPassword){

        $error =
            "Konfirmasi password tidak sama.";

    }

    else{

        /* =========================================
           CEK USER
        ========================================= */

        $stmt = $pdo->prepare("
            SELECT id, username
            FROM users
            WHERE username = ?
            LIMIT 1
        ");

        $stmt->execute([
            $username
        ]);

        $user = $stmt->fetch();


        if(!$user){

            $error =
                "Username tidak ditemukan.";

        }

        else{

            /* =====================================
               HASH PASSWORD BARU
            ===================================== */

            $hashedPassword =
                password_hash(
                    $newPassword,
                    PASSWORD_DEFAULT
                );


            /* =====================================
               UPDATE PASSWORD
            ===================================== */

            $stmt = $pdo->prepare("
                UPDATE users
                SET password = ?
                WHERE id = ?
            ");

            $stmt->execute([
                $hashedPassword,
                $user['id']
            ]);


            if($stmt->rowCount() >= 0){

                $success =
                    "Password berhasil diganti.";

            }

        }

    }

}

?>

<!DOCTYPE html>

<html lang="id">

<head>

<meta charset="UTF-8">

<meta
    name="viewport"
    content="width=device-width, initial-scale=1"
>

<title>
    Lupa Password - Ghost Drive
</title>


<link
    href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css"
    rel="stylesheet"
>


<style>

body{

    height:100vh;

    background:
        linear-gradient(
            -45deg,
            #667eea,
            #764ba2,
            #6dd5fa,
            #2980b9
        );

    background-size:400% 400%;

    animation:
        gradientMove 12s ease infinite;

    display:flex;

    justify-content:center;

    align-items:center;

    font-family:
        'Segoe UI',
        sans-serif;

}


@keyframes gradientMove{

    0%{
        background-position:0% 50%;
    }

    50%{
        background-position:100% 50%;
    }

    100%{
        background-position:0% 50%;
    }

}


.glass-card{

    width:400px;

    padding:40px;

    border-radius:20px;

    backdrop-filter:
        blur(15px);

    background:
        rgba(255,255,255,0.15);

    box-shadow:
        0 8px 32px
        rgba(0,0,0,0.2);

    color:white;

}


.glass-card h2{

    text-align:center;

    font-weight:600;

    margin-bottom:10px;

}


.subtitle{

    text-align:center;

    font-size:14px;

    color:#eee;

    margin-bottom:25px;

}


.form-control{

    border-radius:12px;

    background:
        rgba(255,255,255,0.2);

    border:none;

    color:white;

    padding:11px;

}


.form-control::placeholder{

    color:#eee;

}


.form-control:focus{

    box-shadow:none;

    background:
        rgba(255,255,255,0.3);

    color:white;

}


.btn-reset{

    border-radius:12px;

    padding:10px;

    font-weight:500;

    background:white;

    color:#333;

    transition:0.3s;

}


.btn-reset:hover{

    transform:
        translateY(-2px);

    box-shadow:
        0 5px 15px
        rgba(0,0,0,0.3);

}


.footer-link{

    text-align:center;

    margin-top:20px;

}


.footer-link a{

    color:#fff;

    text-decoration:none;

    font-size:14px;

}


.footer-link a:hover{

    text-decoration:underline;

}


.alert{

    border-radius:12px;

}

</style>

</head>


<body>


<div class="glass-card">


    <h2>Ghost Drive</h2>


    <div class="subtitle">

        Reset Password

    </div>


    <?php if($error !== ''): ?>

        <div
            class="
                alert
                alert-danger
                text-dark
            "
        >

            <?= htmlspecialchars($error) ?>

        </div>

    <?php endif; ?>


    <?php if($success !== ''): ?>

        <div
            class="
                alert
                alert-success
                text-dark
            "
        >

            <?= htmlspecialchars($success) ?>

        </div>


        <script>

            alert(
                "Password berhasil diganti!\n\n" +
                "Silakan login menggunakan password baru."
            );

        </script>

    <?php endif; ?>


    <form
        method="POST"
        onsubmit="return confirmReset();"
    >


        <label class="mb-1">
            Username
        </label>

        <input
            type="text"
            name="username"
            class="form-control mb-3"
            placeholder="Masukkan username"
            required
        >


        <label class="mb-1">
            Password Baru
        </label>

        <input
            type="password"
            name="new_password"
            class="form-control mb-3"
            placeholder="Minimal 6 karakter"
            minlength="6"
            required
        >


        <label class="mb-1">
            Konfirmasi Password
        </label>

        <input
            type="password"
            name="confirm_password"
            class="form-control mb-3"
            placeholder="Ulangi password baru"
            minlength="6"
            required
        >


        <button
            type="submit"
            name="reset_password"
            class="btn btn-reset w-100"
        >

            🔐 Ganti Password

        </button>


    </form>


    <div class="footer-link">

        <a href="login.php">

            ← Kembali ke Login

        </a>

    </div>


</div>


<script>

/* =========================================
   KONFIRMASI SEBELUM RESET
========================================= */

function confirmReset(){

    const username =
        document.querySelector(
            '[name="username"]'
        ).value;

    const newPassword =
        document.querySelector(
            '[name="new_password"]'
        ).value;

    const confirmPassword =
        document.querySelector(
            '[name="confirm_password"]'
        ).value;


    if(newPassword !== confirmPassword){

        alert(
            "Password baru dan konfirmasi password tidak sama!"
        );

        return false;

    }


    return confirm(

        "⚠️ KONFIRMASI GANTI PASSWORD\n\n" +

        "Username: " +
        username +
        "\n\n" +

        "Password akun ini akan diganti " +
        "dengan password baru.\n\n" +

        "Apakah Anda yakin ingin melanjutkan?"

    );

}

</script>


</body>

</html>