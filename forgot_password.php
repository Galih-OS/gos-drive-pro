<?php

declare(strict_types=1);
require 'config/db.php';


/*
|--------------------------------------------------------------------------
| PASTIKAN SESSION AKTIF
|--------------------------------------------------------------------------
*/

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}


/*
|--------------------------------------------------------------------------
| CEK HTTPS
|--------------------------------------------------------------------------
*/

$secureCookie = (
    isset($_SERVER['HTTPS']) &&
    $_SERVER['HTTPS'] !== 'off'
);


/*
|--------------------------------------------------------------------------
| SECURITY HEADERS
|--------------------------------------------------------------------------
*/

header('X-Frame-Options: SAMEORIGIN');

header('X-Content-Type-Options: nosniff');

header(
    'Referrer-Policy: strict-origin-when-cross-origin'
);

header(
    'Permissions-Policy: camera=(), microphone=(), geolocation=()'
);

if ($secureCookie) {

    header(
        'Strict-Transport-Security: '
        . 'max-age=31536000; includeSubDomains'
    );
}


/*
|--------------------------------------------------------------------------
| CSP NONCE
|--------------------------------------------------------------------------
*/

$cspNonce = base64_encode(
    random_bytes(16)
);

header(
    "Content-Security-Policy: "
    . "default-src 'self'; "
    . "style-src 'self' https://cdn.jsdelivr.net 'unsafe-inline'; "
    . "script-src 'self' 'nonce-{$cspNonce}'; "
    . "font-src 'self' https://cdn.jsdelivr.net; "
    . "img-src 'self' data:; "
    . "object-src 'none'; "
    . "base-uri 'self'; "
    . "form-action 'self'; "
    . "frame-ancestors 'self';"
);


/*
|--------------------------------------------------------------------------
| VARIABLES
|--------------------------------------------------------------------------
*/

$error   = '';
$success = '';


/*
|--------------------------------------------------------------------------
| CSRF TOKEN
|--------------------------------------------------------------------------
*/

if (
    empty($_SESSION['reset_password_csrf']) ||
    !is_string($_SESSION['reset_password_csrf'])
) {

    $_SESSION['reset_password_csrf'] =
        bin2hex(random_bytes(32));
}


/*
|--------------------------------------------------------------------------
| RATE LIMIT
|--------------------------------------------------------------------------
| Maksimal 5 percobaan dalam 15 menit per session.
|--------------------------------------------------------------------------
*/

$maxAttempts = 5;

$window = 15 * 60;


if (
    !isset($_SESSION['reset_attempts']) ||
    !is_array($_SESSION['reset_attempts'])
) {

    $_SESSION['reset_attempts'] = [];
}


/*
|--------------------------------------------------------------------------
| HAPUS ATTEMPT LAMA
|--------------------------------------------------------------------------
*/

$now = time();

$_SESSION['reset_attempts'] = array_values(

    array_filter(

        $_SESSION['reset_attempts'],

        static function ($timestamp) use ($now, $window) {

            return is_int($timestamp)
                && ($now - $timestamp) < $window;
        }
    )
);


/*
|--------------------------------------------------------------------------
| PROSES RESET PASSWORD
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD'] === 'POST') {


    /*
    |--------------------------------------------------------------------------
    | RATE LIMIT
    |--------------------------------------------------------------------------
    */

    if (
        count($_SESSION['reset_attempts']) >=
        $maxAttempts
    ) {

        $error =
            'Terlalu banyak percobaan. '
            . 'Silakan coba lagi beberapa menit kemudian.';

    } else {


        /*
        |--------------------------------------------------------------------------
        | CATAT PERCOBAAN
        |--------------------------------------------------------------------------
        */

        $_SESSION['reset_attempts'][] = time();


        /*
        |--------------------------------------------------------------------------
        | CEK CSRF
        |--------------------------------------------------------------------------
        */

        $csrfToken =
            $_POST['csrf_token'] ?? '';


        if (
            !is_string($csrfToken) ||
            !hash_equals(
                $_SESSION['reset_password_csrf'],
                $csrfToken
            )
        ) {

            $error =
                'Permintaan tidak valid. '
                . 'Silakan muat ulang halaman dan coba lagi.';

        } else {


            /*
            |--------------------------------------------------------------------------
            | AMBIL INPUT
            |--------------------------------------------------------------------------
            */

            $username =
                trim(
                    (string)(
                        $_POST['username'] ?? ''
                    )
                );

            $newPassword =
                (string)(
                    $_POST['new_password'] ?? ''
                );

            $confirmPassword =
                (string)(
                    $_POST['confirm_password'] ?? ''
                );


            /*
            |--------------------------------------------------------------------------
            | VALIDASI USERNAME
            |--------------------------------------------------------------------------
            */

            if ($username === '') {

                $error =
                    'Username wajib diisi.';

            } elseif (strlen($username) > 50) {

                $error =
                    'Username tidak valid.';

            } elseif (
                !preg_match(
                    '/^[A-Za-z0-9._-]+$/',
                    $username
                )
            ) {

                $error =
                    'Username mengandung karakter '
                    . 'yang tidak valid.';
            }


            /*
            |--------------------------------------------------------------------------
            | VALIDASI PASSWORD
            |--------------------------------------------------------------------------
            */

            elseif (strlen($newPassword) < 8) {

                $error =
                    'Password baru minimal 8 karakter.';

            } elseif (strlen($newPassword) > 4096) {

                $error =
                    'Password terlalu panjang.';

            } elseif (
                $newPassword !== $confirmPassword
            ) {

                $error =
                    'Konfirmasi password tidak sama.';
            }


            /*
            |--------------------------------------------------------------------------
            | PROSES DATABASE
            |--------------------------------------------------------------------------
            */

            else {

                try {


                    /*
                    |--------------------------------------------------------------------------
                    | CARI USER
                    |--------------------------------------------------------------------------
                    */

                    $stmt = $pdo->prepare("
                        SELECT
                            id,
                            username,
                            status
                        FROM users
                        WHERE username = ?
                        LIMIT 1
                    ");

                    $stmt->execute([
                        $username
                    ]);

                    $user = $stmt->fetch(
                        PDO::FETCH_ASSOC
                    );


                    /*
                    |--------------------------------------------------------------------------
                    | USER TIDAK DITEMUKAN
                    |--------------------------------------------------------------------------
                    */

                    if (!$user) {

                        $error =
                            'Username atau proses reset '
                            . 'tidak dapat diproses.';


                    /*
                    |--------------------------------------------------------------------------
                    | CEK SUSPENDED
                    |--------------------------------------------------------------------------
                    */

                    } elseif (
                        isset($user['status']) &&
                        $user['status'] === 'suspended'
                    ) {

                        $error =
                            'Akun tidak dapat melakukan '
                            . 'reset password.';


                    } else {


                        /*
                        |--------------------------------------------------------------------------
                        | HASH PASSWORD
                        |--------------------------------------------------------------------------
                        */

                        $hashedPassword =
                            password_hash(
                                $newPassword,
                                PASSWORD_DEFAULT
                            );


                        if (
                            $hashedPassword === false
                        ) {

                            throw new RuntimeException(
                                'Password hashing gagal.'
                            );
                        }


                        /*
                        |--------------------------------------------------------------------------
                        | UPDATE PASSWORD
                        |--------------------------------------------------------------------------
                        */

                        $update = $pdo->prepare("
                            UPDATE users
                            SET password = ?
                            WHERE id = ?
                            LIMIT 1
                        ");

                        $update->execute([
                            $hashedPassword,
                            (int)$user['id']
                        ]);


                        /*
                        |--------------------------------------------------------------------------
                        | CEK UPDATE
                        |--------------------------------------------------------------------------
                        */

                        if (
                            $update->rowCount() !== 1
                        ) {

                            throw new RuntimeException(
                                'Password gagal diperbarui.'
                            );
                        }


                        /*
                        |--------------------------------------------------------------------------
                        | BERSIHKAN SESSION
                        |--------------------------------------------------------------------------
                        |
                        | Tidak menggunakan:
                        | session_destroy();
                        | session_start();
                        |
                        | Cukup kosongkan session lalu
                        | regenerasi session ID.
                        |--------------------------------------------------------------------------
                        */

                        $_SESSION = [];

                        session_regenerate_id(true);


                        /*
                        |--------------------------------------------------------------------------
                        | CSRF BARU
                        |--------------------------------------------------------------------------
                        */

                        $_SESSION[
                            'reset_password_csrf'
                        ] =
                            bin2hex(
                                random_bytes(32)
                            );


                        /*
                        |--------------------------------------------------------------------------
                        | RESET RATE LIMIT
                        |--------------------------------------------------------------------------
                        */

                        $_SESSION[
                            'reset_attempts'
                        ] = [];


                        /*
                        |--------------------------------------------------------------------------
                        | PESAN SUKSES
                        |--------------------------------------------------------------------------
                        */

                        $success =
                            'Password berhasil diganti. '
                            . 'Silakan login menggunakan '
                            . 'password baru.';
                    }


                } catch (
                    PDOException |
                    RuntimeException |
                    Throwable $e
                ) {


                    /*
                    |--------------------------------------------------------------------------
                    | JANGAN TAMPILKAN ERROR INTERNAL
                    |--------------------------------------------------------------------------
                    */

                    error_log(
                        'Ghost Drive reset password error: '
                        . $e->getMessage()
                    );


                    $error =
                        'Terjadi kesalahan saat memproses '
                        . 'permintaan. Silakan coba lagi.';
                }
            }
        }
    }
}


/*
|--------------------------------------------------------------------------
| ESCAPE OUTPUT
|--------------------------------------------------------------------------
*/

function e(string $value): string
{
    return htmlspecialchars(
        $value,
        ENT_QUOTES | ENT_SUBSTITUTE,
        'UTF-8'
    );
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

<meta
    name="robots"
    content="noindex,nofollow,noarchive"
>

<title>
    Reset Password - Ghost Drive
</title>


<link
    href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css"
    rel="stylesheet"
>


<style>

body{

    min-height:100vh;

    margin:0;

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

    padding:20px;

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

    width:100%;

    max-width:400px;

    padding:40px;

    border-radius:20px;

    backdrop-filter:
        blur(15px);

    -webkit-backdrop-filter:
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

    border:
        1px solid
        rgba(255,255,255,0.15);

    color:white;

    padding:11px;

}


.form-control::placeholder{

    color:#eee;

}


.form-control:focus{

    box-shadow:
        0 0 0 3px
        rgba(255,255,255,0.18);

    background:
        rgba(255,255,255,0.3);

    color:white;

}


.btn-reset{

    border-radius:12px;

    padding:10px;

    font-weight:600;

    background:white;

    color:#333;

    border:none;

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


    <h2>
        Ghost Drive
    </h2>


    <div class="subtitle">

        Reset Password

    </div>


    <?php if($error !== ''): ?>

        <div
            class="alert alert-danger text-dark"
            role="alert"
        >

            <?= e($error) ?>

        </div>

    <?php endif; ?>


    <?php if($success !== ''): ?>

        <div
            class="alert alert-success text-dark"
            role="alert"
        >

            <?= e($success) ?>

        </div>

    <?php endif; ?>


    <?php if($success === ''): ?>

    <form
        method="POST"
        id="resetForm"
        autocomplete="off"
    >

        <input
            type="hidden"
            name="csrf_token"
            value="<?= e(
                $_SESSION['reset_password_csrf']
            ) ?>"
        >


        <label
            class="mb-1"
            for="username"
        >
            Username
        </label>


        <input
            type="text"
            id="username"
            name="username"
            class="form-control mb-3"
            placeholder="Masukkan username"
            maxlength="50"
            pattern="[A-Za-z0-9._-]+"
            autocomplete="username"
            required
        >


        <label
            class="mb-1"
            for="new_password"
        >
            Password Baru
        </label>


        <input
            type="password"
            id="new_password"
            name="new_password"
            class="form-control mb-3"
            placeholder="Minimal 8 karakter"
            minlength="8"
            maxlength="4096"
            autocomplete="new-password"
            required
        >


        <label
            class="mb-1"
            for="confirm_password"
        >
            Konfirmasi Password
        </label>


        <input
            type="password"
            id="confirm_password"
            name="confirm_password"
            class="form-control mb-3"
            placeholder="Ulangi password baru"
            minlength="8"
            maxlength="4096"
            autocomplete="new-password"
            required
        >


        <button
            type="submit"
            id="resetButton"
            name="reset_password"
            class="btn btn-reset w-100"
        >

            🔐 Ganti Password

        </button>


    </form>

    <?php endif; ?>


    <div class="footer-link">

        <a href="login.php">

            ← Kembali ke Login

        </a>

    </div>


</div>


<script nonce="<?= e($cspNonce) ?>">

document
    .getElementById('resetForm')
    ?.addEventListener(
        'submit',
        function(event){

            const newPassword =
                document.getElementById(
                    'new_password'
                ).value;


            const confirmPassword =
                document.getElementById(
                    'confirm_password'
                ).value;


            if(
                newPassword !==
                confirmPassword
            ){

                event.preventDefault();

                alert(
                    'Password baru dan konfirmasi password tidak sama.'
                );

                return;
            }


            const confirmed =
                confirm(

                    'KONFIRMASI GANTI PASSWORD\n\n' +

                    'Password akun akan diganti ' +
                    'dengan password baru.\n\n' +

                    'Pastikan Anda memang memiliki ' +
                    'hak untuk melakukan perubahan ini.\n\n' +

                    'Lanjutkan?'
                );


            if(!confirmed){

                event.preventDefault();

                return;
            }


            const button =
                document.getElementById(
                    'resetButton'
                );


            if(button){

                button.disabled = true;

                button.innerText =
                    'Memproses...';
            }

        }
    );

</script>


</body>

</html>
