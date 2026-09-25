<?php

/*
|--------------------------------------------------------------------------
| GHOST DRIVE - HARDENED LOGIN
|--------------------------------------------------------------------------
*/


/* =========================================================
   DETEKSI HTTPS
========================================================= */

$isHttps =
    isset($_SERVER['HTTPS']) &&
    $_SERVER['HTTPS'] !== '' &&
    strtolower($_SERVER['HTTPS']) !== 'off';


/* =========================================================
   SESSION COOKIE SECURITY
========================================================= */

if (session_status() === PHP_SESSION_NONE) {

    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'secure' => $isHttps,
        'httponly' => true,
        'samesite' => 'Lax'
    ]);

}


/* =========================================================
   LOAD CONFIG
========================================================= */

require 'config/db.php';
require 'config/auth.php';


/* =========================================================
   SESSION START
========================================================= */

if (session_status() === PHP_SESSION_NONE) {

    session_start();

}


/* =========================================================
   CSP NONCE
========================================================= */

$cspNonce =
    base64_encode(
        random_bytes(32)
    );


/* =========================================================
   SECURITY HEADERS
========================================================= */

header('X-Frame-Options: SAMEORIGIN');

header('X-Content-Type-Options: nosniff');

header(
    'Referrer-Policy: strict-origin-when-cross-origin'
);

header(
    'Permissions-Policy: camera=(), microphone=(), geolocation=()'
);


/*
 * CSP:
 * - Hanya izinkan resource dari sumber yang diperlukan.
 * - Inline CSS/JS harus menggunakan nonce.
 */

header(
    "Content-Security-Policy: "
    . "default-src 'self'; "
    . "base-uri 'self'; "
    . "form-action 'self'; "
    . "frame-ancestors 'self'; "
    . "object-src 'none'; "
    . "script-src 'self' 'nonce-{$cspNonce}' https://cdn.jsdelivr.net https://cdnjs.cloudflare.com; "
    . "style-src 'self' 'nonce-{$cspNonce}' https://cdn.jsdelivr.net https://cdnjs.cloudflare.com; "
    . "font-src 'self' https://cdnjs.cloudflare.com data:; "
    . "img-src 'self' data:; "
    . "connect-src 'self';"
);


/*
 * HSTS hanya dikirim ketika benar-benar HTTPS.
 */

if ($isHttps) {

    header(
        'Strict-Transport-Security: max-age=31536000; includeSubDomains'
    );

}


/* =========================================================
   CSRF TOKEN
========================================================= */

if (
    !isset($_SESSION['login_csrf']) ||
    !is_string($_SESSION['login_csrf']) ||
    strlen($_SESSION['login_csrf']) < 32
) {

    $_SESSION['login_csrf'] =
        bin2hex(
            random_bytes(32)
        );

}


$csrfToken =
    $_SESSION['login_csrf'];


/* =========================================================
   LOGIN STATE
========================================================= */

$error = '';

$loginSuccess = false;


/* =========================================================
   LOGIN ATTEMPTS
========================================================= */

if (
    !isset($_SESSION['login_attempts']) ||
    !is_array($_SESSION['login_attempts'])
) {

    $_SESSION['login_attempts'] = [];

}


/* =========================================================
   HAPUS ATTEMPT LAMA
========================================================= */

$nowTimestamp = time();


foreach (
    $_SESSION['login_attempts']
    as $key => $attemptTime
) {

    if (
        !is_int($attemptTime) ||
        ($nowTimestamp - $attemptTime) > 900
    ) {

        unset(
            $_SESSION['login_attempts'][$key]
        );

    }

}


/* =========================================================
   BATASI LOGIN
========================================================= */

$attemptCount =
    count(
        $_SESSION['login_attempts']
    );


$loginBlocked =
    $attemptCount >= 5;


/* =========================================================
   PROSES LOGIN
========================================================= */

if (
    $_SERVER['REQUEST_METHOD'] === 'POST' &&
    isset($_POST['login'])
) {


    /* =====================================================
       CEK CSRF
    ===================================================== */

    $submittedCsrf =
        isset($_POST['csrf_token'])
            ? (string)$_POST['csrf_token']
            : '';


    if (
        $submittedCsrf === '' ||
        !hash_equals(
            $_SESSION['login_csrf'],
            $submittedCsrf
        )
    ) {

        $error =
            "Permintaan login tidak valid. "
            . "Silakan refresh halaman dan coba kembali.";

    }


    /* =====================================================
       CEK THROTTLING
    ===================================================== */

    elseif ($loginBlocked) {

        $error =
            "Terlalu banyak percobaan login. "
            . "Silakan tunggu beberapa menit sebelum mencoba lagi.";

    }


    else {


        /* =================================================
           INPUT
        ================================================= */

        $username =
            trim(
                (string)(
                    $_POST['username'] ?? ''
                )
            );


        $password =
            (string)(
                $_POST['password'] ?? ''
            );


        /* =================================================
           VALIDASI
        ================================================= */

        if (
            $username === '' ||
            $password === ''
        ) {

            $error =
                "Username dan password wajib diisi.";

        }

        elseif (
            mb_strlen(
                $username,
                'UTF-8'
            ) > 100
        ) {

            $error =
                "Username atau password salah.";

        }

        elseif (
            strlen($password) > 4096
        ) {

            $error =
                "Username atau password salah.";

        }

        else {


            /* =================================================
               CARI USER
            ================================================= */

            $stmt =
                $pdo->prepare("
                    SELECT
                        id,
                        username,
                        password,
                        role,
                        status
                    FROM users
                    WHERE username = ?
                    LIMIT 1
                ");


            $stmt->execute([
                $username
            ]);


            $user =
                $stmt->fetch(
                    PDO::FETCH_ASSOC
                );


            /* =================================================
               PASSWORD
            ================================================= */

            $passwordValid = false;


            if ($user) {

                $passwordValid =
                    password_verify(
                        $password,
                        $user['password']
                    );

            }


            /* =================================================
               LOGIN GAGAL
            ================================================= */

            if (
                !$user ||
                !$passwordValid
            ) {

                $_SESSION['login_attempts'][] =
                    time();


                $error =
                    "Username atau password salah.";

            }


            /* =================================================
               AKUN SUSPENDED
            ================================================= */

            elseif (
                isset($user['status']) &&
                $user['status'] === 'suspended'
            ) {

                $error =
                    "Akun tidak dapat digunakan. "
                    . "Silakan hubungi administrator GosDrive.";

            }


            /* =================================================
               LOGIN BERHASIL
            ================================================= */

            else {


                /* =============================================
                   SESSION FIXATION PROTECTION
                ============================================= */

                session_regenerate_id(true);


                /* =============================================
                   RESET LOGIN ATTEMPTS
                ============================================= */

                $_SESSION['login_attempts'] = [];


                /* =============================================
                   WAKTU LOGIN
                ============================================= */

                $now =
                    date('Y-m-d H:i:s');


                /* =============================================
                   UPDATE LAST LOGIN
                ============================================= */

                $stmtLogin =
                    $pdo->prepare("
                        UPDATE users
                        SET last_login = ?
                        WHERE id = ?
                    ");


                $stmtLogin->execute([

                    $now,

                    (int)$user['id']

                ]);


                /* =============================================
                   ROTASI CSRF
                ============================================= */

                $_SESSION['login_csrf'] =
                    bin2hex(
                        random_bytes(32)
                    );


                /* =============================================
                   DATA SESSION
                ============================================= */

                $_SESSION['user'] = [

                    'id' =>
                        (int)$user['id'],

                    'username' =>
                        $user['username'],

                    'role' =>
                        $user['role'] ?? 'user',

                    'last_login' =>
                        $now

                ];


                /* =============================================
                   SESSION SECURITY
                ============================================= */

                $_SESSION['login_time'] =
                    time();


                $_SESSION['last_activity'] =
                    time();


                $_SESSION['last_regeneration'] =
                    time();


                /* =============================================
                   SUCCESS
                ============================================= */

                $loginSuccess = true;

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
    content="width=device-width, initial-scale=1.0"
>

<meta
    name="robots"
    content="noindex,nofollow,noarchive"
>

<title>Ghost Drive</title>


<link
    href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css"
    rel="stylesheet"
>


<link
    rel="shortcut icon"
    href="ghostdrive.png"
>


<link
    href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css"
    rel="stylesheet"
>


<style nonce="<?= htmlspecialchars(
    $cspNonce,
    ENT_QUOTES,
    'UTF-8'
) ?>">

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

    font-family:'Segoe UI',sans-serif;

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

    width:380px;

    padding:40px;

    border-radius:20px;

    backdrop-filter:blur(15px);

    background:
        rgba(
            255,
            255,
            255,
            0.15
        );

    box-shadow:
        0 8px 32px
        rgba(0,0,0,0.2);

    color:white;

}


.glass-card h2{

    text-align:center;

    font-weight:600;

    margin-bottom:30px;

}


.form-control{

    border-radius:12px;

    background:
        rgba(
            255,
            255,
            255,
            0.2
        );

    border:none;

    color:white;

}


.form-control::placeholder{

    color:#eee;

}


.form-control:focus{

    box-shadow:none;

    background:
        rgba(
            255,
            255,
            255,
            0.3
        );

    color:white;

}


.btn-login{

    border-radius:12px;

    padding:10px;

    font-weight:500;

    background:white;

    color:#333;

    transition:0.3s;

}


.btn-login:hover{

    transform:translateY(-2px);

    box-shadow:
        0 5px 15px
        rgba(0,0,0,0.3);

}


.footer-link{

    text-align:center;

    margin-top:15px;

}


.footer-link a{

    color:#fff;

    text-decoration:none;

    font-size:14px;

}


.footer-link a:hover{

    text-decoration:underline;

}


.login-success{

    text-align:center;

}


.login-success .icon{

    font-size:50px;

    margin-bottom:15px;

}


.login-success .title{

    font-size:22px;

    font-weight:600;

}


.login-success .message{

    margin-top:8px;

    font-size:14px;

}


.password-toggle{

    position:absolute;

    right:10px;

    top:50%;

    transform:translateY(-50%);

    border:none;

    background:transparent;

    color:white;

    font-size:18px;

    cursor:pointer;

    padding:5px 8px;

    opacity:0.8;

}


.password-toggle:hover{

    opacity:1;

}


.telegram-link{

    color:#fff;

    text-decoration:none;

    font-size:14px;

    display:inline-flex;

    align-items:center;

    gap:7px;

    transition:0.3s;

}


.telegram-link i{

    font-size:20px;

}


.telegram-link:hover{

    color:#dff6ff;

    transform:translateY(-2px);

}

</style>

</head>


<body>


<div class="glass-card">


<?php if ($loginSuccess): ?>


    <div class="login-success">

        <div class="icon">
            ✅
        </div>

        <div class="title">
            Login Berhasil
        </div>

        <div class="message">
            Berhasil, masuk menuju halaman selanjutnya...
        </div>

        <div class="mt-3">

            <div
                class="spinner-border text-light"
                role="status"
            ></div>

        </div>

    </div>


    <script nonce="<?= htmlspecialchars(
        $cspNonce,
        ENT_QUOTES,
        'UTF-8'
    ) ?>">

    setTimeout(function(){

        window.location.href =
            "dashboard.php";

    }, 2000);

    </script>


<?php else: ?>


    <h2>
        Ghost Drive
    </h2>


    <?php if ($error !== ''): ?>

        <div
            class="alert alert-danger text-dark"
            role="alert"
        >

            <?= htmlspecialchars(
                $error,
                ENT_QUOTES,
                'UTF-8'
            ) ?>

        </div>

    <?php endif; ?>


    <?php if (isset($_GET['success'])): ?>

        <div
            class="alert alert-success text-dark"
            role="alert"
        >

            Registrasi berhasil!
            Silakan login.

        </div>

    <?php endif; ?>


    <?php if (isset($_GET['logout'])): ?>

        <div
            class="alert alert-success text-dark"
            role="alert"
        >

            Anda berhasil logout.

        </div>

    <?php endif; ?>


    <?php if (isset($_GET['timeout'])): ?>

        <div
            class="alert alert-warning text-dark"
            role="alert"
        >

            Sesi Anda telah berakhir karena tidak ada aktivitas.
            Silakan login kembali.

        </div>

    <?php endif; ?>


    <?php if (isset($_GET['expired'])): ?>

        <div
            class="alert alert-warning text-dark"
            role="alert"
        >

            Sesi Anda telah berakhir.
            Silakan login kembali.

        </div>

    <?php endif; ?>


    <form
        method="POST"
        autocomplete="on"
        novalidate
    >

        <input
            type="hidden"
            name="csrf_token"
            value="<?= htmlspecialchars(
                $csrfToken,
                ENT_QUOTES,
                'UTF-8'
            ) ?>"
        >


        <input
            type="text"
            name="username"
            class="form-control mb-3"
            placeholder="Username"
            autocomplete="username"
            maxlength="100"
            required
        >


        <div class="position-relative mb-3">

            <input
                type="password"
                name="password"
                id="password"
                class="form-control pe-5"
                placeholder="Password"
                autocomplete="current-password"
                maxlength="4096"
                required
            >


            <button
                type="button"
                onclick="togglePassword()"
                class="password-toggle"
                aria-label="Tampilkan password"
            >

                👁️

            </button>

        </div>


        <button
            type="submit"
            name="login"
            class="btn btn-login w-100"
        >

            Login

        </button>

    </form>


    <div class="footer-link">

        <p class="mt-3">

            <a href="forgot_password.php">
                🔑 Lupa Password?
            </a>

        </p>


        <p>

            Belum punya akun?

            <a href="register.php">
                Daftar
            </a>

        </p>


        <p>

            <a
                href="https://t.me/galihos"
                target="_blank"
                rel="noopener noreferrer"
                class="telegram-link"
            >

                <i class="fa-brands fa-telegram"></i>

                Hubungi Ghost Drive

            </a>

        </p>

    </div>


<?php endif; ?>


</div>


<script nonce="<?= htmlspecialchars(
    $cspNonce,
    ENT_QUOTES,
    'UTF-8'
) ?>">

function togglePassword(){

    const password =
        document.getElementById(
            "password"
        );


    const button =
        document.querySelector(
            ".password-toggle"
        );


    if (
        !password ||
        !button
    ) {

        return;

    }


    if (
        password.type === "password"
    ){

        password.type =
            "text";


        button.innerHTML =
            "🙈";


        button.setAttribute(
            "aria-label",
            "Sembunyikan password"
        );

    }

    else{

        password.type =
            "password";


        button.innerHTML =
            "👁️";


        button.setAttribute(
            "aria-label",
            "Tampilkan password"
        );

    }

}

</script>


</body>

</html>