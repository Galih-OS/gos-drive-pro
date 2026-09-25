<?php

/*
|--------------------------------------------------------------------------
| GHOST DRIVE - SECURE REGISTER
|--------------------------------------------------------------------------
*/


/* =========================================================
   HTTPS DETECTION
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
   LOAD DATABASE
========================================================= */

require 'config/db.php';


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

header(
    'X-Frame-Options: SAMEORIGIN'
);

header(
    'X-Content-Type-Options: nosniff'
);

header(
    'Referrer-Policy: strict-origin-when-cross-origin'
);

header(
    'Permissions-Policy: camera=(), microphone=(), geolocation=()'
);


/* =========================================================
   CONTENT SECURITY POLICY
========================================================= */

header(
    "Content-Security-Policy: "
    . "default-src 'self'; "
    . "base-uri 'self'; "
    . "form-action 'self'; "
    . "frame-ancestors 'self'; "
    . "object-src 'none'; "
    . "script-src 'self' 'nonce-{$cspNonce}' https://cdn.jsdelivr.net; "
    . "style-src 'self' 'nonce-{$cspNonce}' https://cdn.jsdelivr.net; "
    . "font-src 'self' https://cdn.jsdelivr.net data:; "
    . "img-src 'self' data:; "
    . "connect-src 'self';"
);


/* =========================================================
   HSTS
========================================================= */

if ($isHttps) {

    header(
        'Strict-Transport-Security: max-age=31536000; includeSubDomains'
    );

}


/* =========================================================
   CSRF TOKEN
========================================================= */

if (
    !isset($_SESSION['register_csrf']) ||
    !is_string($_SESSION['register_csrf']) ||
    strlen($_SESSION['register_csrf']) < 32
) {

    $_SESSION['register_csrf'] =
        bin2hex(
            random_bytes(32)
        );

}


$csrfToken =
    $_SESSION['register_csrf'];


/* =========================================================
   REGISTER STATE
========================================================= */

$error = '';

$success = false;


/* =========================================================
   REGISTRATION THROTTLING
========================================================= */

if (
    !isset($_SESSION['register_attempts']) ||
    !is_array($_SESSION['register_attempts'])
) {

    $_SESSION['register_attempts'] = [];

}


/* =========================================================
   BERSIHKAN ATTEMPT LAMA
========================================================= */

$nowTimestamp = time();


foreach (
    $_SESSION['register_attempts']
    as $key => $attemptTime
) {

    if (
        !is_int($attemptTime) ||
        ($nowTimestamp - $attemptTime) > 900
    ) {

        unset(
            $_SESSION['register_attempts'][$key]
        );

    }

}


/* =========================================================
   MAKSIMAL REGISTRASI
   5 percobaan / 15 menit
========================================================= */

$registerAttemptCount =
    count(
        $_SESSION['register_attempts']
    );


$registerBlocked =
    $registerAttemptCount >= 5;


/* =========================================================
   PROSES REGISTER
========================================================= */

if (
    $_SERVER['REQUEST_METHOD'] === 'POST' &&
    isset($_POST['register'])
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
            $_SESSION['register_csrf'],
            $submittedCsrf
        )
    ) {

        $error =
            "Permintaan tidak valid. "
            . "Silakan refresh halaman dan coba kembali.";

    }


    /* =====================================================
       CEK THROTTLING
    ===================================================== */

    elseif ($registerBlocked) {

        $error =
            "Terlalu banyak percobaan pendaftaran. "
            . "Silakan tunggu beberapa menit.";

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


        $plainPassword =
            (string)(
                $_POST['password'] ?? ''
            );


        /* =================================================
           CATAT ATTEMPT
        ================================================= */

        $_SESSION['register_attempts'][] =
            time();


        /* =================================================
           VALIDASI USERNAME
        ================================================= */

        if ($username === '') {

            $error =
                "Username wajib diisi.";

        }

        elseif (
            mb_strlen(
                $username,
                'UTF-8'
            ) < 3
        ) {

            $error =
                "Username minimal 3 karakter.";

        }

        elseif (
            mb_strlen(
                $username,
                'UTF-8'
            ) > 50
        ) {

            $error =
                "Username maksimal 50 karakter.";

        }

        /*
         * Hanya izinkan:
         * huruf
         * angka
         * underscore
         * titik
         * tanda minus
         */

        elseif (
            !preg_match(
                '/^[A-Za-z0-9._-]+$/',
                $username
            )
        ) {

            $error =
                "Username hanya boleh menggunakan huruf, "
                . "angka, titik, underscore, dan tanda minus.";

        }


        /* =================================================
           VALIDASI PASSWORD
        ================================================= */

        elseif ($plainPassword === '') {

            $error =
                "Password wajib diisi.";

        }

        elseif (
            strlen($plainPassword) < 8
        ) {

            $error =
                "Password minimal 8 karakter.";

        }

        elseif (
            strlen($plainPassword) > 4096
        ) {

            $error =
                "Password terlalu panjang.";

        }


        /* =================================================
           PROSES DATABASE
        ================================================= */

        else {

            try {


                /* =============================================
                   CEK USERNAME
                ============================================= */

                $check =
                    $pdo->prepare("
                        SELECT id
                        FROM users
                        WHERE username = ?
                        LIMIT 1
                    ");


                $check->execute([
                    $username
                ]);


                if ($check->fetch()) {

                    /*
                     * Jangan berikan informasi database.
                     */

                    $error =
                        "Username sudah digunakan.";

                }


                /* =============================================
                   INSERT USER
                ============================================= */

                else {


                    /* =========================================
                       HASH PASSWORD
                    ========================================= */

                    $password =
                        password_hash(
                            $plainPassword,
                            PASSWORD_DEFAULT
                        );


                    if (
                        $password === false
                    ) {

                        throw new RuntimeException(
                            'Password hashing failed.'
                        );

                    }


                    /* =========================================
                       DEFAULT QUOTA

                       0 = TANPA BATAS
                    ========================================= */

                    $storageQuota = 0;


                    /* =========================================
                       INSERT
                    ========================================= */

                    $stmt =
                        $pdo->prepare("
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
                       ROTASI CSRF
                    ========================================= */

                    $_SESSION['register_csrf'] =
                        bin2hex(
                            random_bytes(32)
                        );


                    /* =========================================
                       RESET ATTEMPTS
                    ========================================= */

                    $_SESSION['register_attempts'] = [];


                    /* =========================================
                       SUCCESS
                    ========================================= */

                    $success = true;

                }

            }

            catch (
                PDOException $e
            ) {

                /*
                 * Jangan tampilkan detail SQL/database
                 * kepada pengguna.
                 */

                /*
                 * Jika username memiliki UNIQUE INDEX,
                 * kemungkinan duplicate tetap ditangani
                 * sebagai pesan umum.
                 */

                $error =
                    "Pendaftaran tidak dapat diproses. "
                    . "Silakan coba kembali.";

            }

            catch (
                Throwable $e
            ) {

                $error =
                    "Pendaftaran tidak dapat diproses. "
                    . "Silakan coba kembali.";

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

<title>Daftar - Ghost Drive</title>


<link
    href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css"
    rel="stylesheet"
>


<style nonce="<?= htmlspecialchars(
    $cspNonce,
    ENT_QUOTES,
    'UTF-8'
) ?>">

body{

    min-height:100vh;

    background:
        linear-gradient(
            135deg,
            #e0f7fa,
            #e3f2fd
        );

    font-family:
        'Segoe UI',
        sans-serif;

}


.card{

    border-radius:20px;

    border:none;

}


.register-title{

    font-weight:600;

}


.form-control{

    border-radius:12px;

}


.btn-register{

    border-radius:12px;

    padding:10px;

    font-weight:500;

}


.security-note{

    font-size:12px;

    color:#6c757d;

    text-align:center;

    margin-top:15px;

}

</style>

</head>


<body>


<div class="container mt-5">


<div class="row justify-content-center">


<div class="col-md-4">


<div class="card p-4 shadow">


<h3 class="mb-3 text-center register-title">

    Daftar Akun

</h3>


<?php if ($error !== ''): ?>

<div
    class="alert alert-danger"
    role="alert"
>

    <?= htmlspecialchars(
        $error,
        ENT_QUOTES,
        'UTF-8'
    ) ?>

</div>

<?php endif; ?>


<?php if ($success): ?>

<div
    class="alert alert-success"
    role="alert"
>

    Registrasi berhasil.
    Silakan login.

</div>


<a
    href="login.php"
    class="btn btn-primary w-100 btn-register"
>

    Login

</a>


<?php else: ?>


<form
    method="POST"
    autocomplete="off"
    novalidate
>


<!-- =====================================================
     CSRF
====================================================== -->

<input
    type="hidden"
    name="csrf_token"
    value="<?= htmlspecialchars(
        $csrfToken,
        ENT_QUOTES,
        'UTF-8'
    ) ?>"
>


<!-- =====================================================
     USERNAME
====================================================== -->

<input
    type="text"
    name="username"
    class="form-control mb-3"
    placeholder="Username"
    autocomplete="username"
    minlength="3"
    maxlength="50"
    pattern="[A-Za-z0-9._-]+"
    required
>


<!-- =====================================================
     PASSWORD
====================================================== -->

<input
    type="password"
    name="password"
    class="form-control mb-3"
    placeholder="Password minimal 8 karakter"
    autocomplete="new-password"
    minlength="8"
    maxlength="4096"
    required
>


<button
    type="submit"
    name="register"
    value="1"
    class="btn btn-primary w-100 btn-register"
>

    Daftar

</button>


</form>


<div class="security-note">

    Gunakan password yang kuat dan unik.

</div>


<hr>


<div class="text-center">

    Sudah punya akun?

    <a href="login.php">

        Login

    </a>

</div>


<?php endif; ?>


</div>


</div>


</div>


</div>


</body>

</html>