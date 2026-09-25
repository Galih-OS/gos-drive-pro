<?php

require '../config/db.php';
require '../config/auth.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}


/* =========================================================
   NO CACHE
========================================================= */

header(
    'Cache-Control: no-store, no-cache, must-revalidate, max-age=0'
);

header(
    'Pragma: no-cache'
);

header(
    'Expires: 0'
);


/* =========================================================
   VARIABEL
========================================================= */

$error = null;

$loginSuccess = false;


/* =========================================================
   JIKA SUDAH LOGIN ADMIN
========================================================= */

if (
    $_SERVER['REQUEST_METHOD'] !== 'POST' &&
    isset($_SESSION['user']) &&
    is_array($_SESSION['user']) &&
    isset($_SESSION['user']['id']) &&
    isset($_SESSION['user']['role']) &&
    $_SESSION['user']['role'] === 'admin'
) {

    header(
        'Location: admin.php',
        true,
        303
    );

    exit;
}


/* =========================================================
   VARIABEL
========================================================= */

$error = null;
$loginSuccess = false;


/* =========================================================
   PROSES LOGIN ADMIN
========================================================= */

if (
    $_SERVER['REQUEST_METHOD'] === 'POST' &&
    isset($_POST['login'])
) {

    $username =
        trim(
            $_POST['username'] ?? ''
        );


    $password =
        $_POST['password'] ?? '';


    /* =====================================================
       VALIDASI
    ===================================================== */

    if (
        $username === '' ||
        $password === ''
    ) {

        $error =
            'Username dan password wajib diisi.';

    }

    else {

        /* =================================================
           CARI ADMIN
        ================================================= */

        $stmt =
            $pdo->prepare("
                SELECT
                    id,
                    username,
                    password,
                    role,
                    status,
                    last_login
                FROM users
                WHERE username = ?
                AND role = 'admin'
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
           VERIFIKASI PASSWORD
        ================================================= */

        if (
            !$user ||
            !isset($user['password']) ||
            !password_verify(
                $password,
                $user['password']
            )
        ) {

            /*
             * Jangan memberi tahu apakah
             * username atau password yang salah.
             */

            $error =
                'Username atau password administrator salah.';

        }

        /* =================================================
           CEK STATUS
        ================================================= */

        elseif (
            !isset($user['status']) ||
            $user['status'] !== 'active'
        ) {

            $error =
                'Akun administrator sedang tidak aktif.';

        }

        else {

            /* =================================================
               LOGIN BERHASIL
            ================================================= */

            /*
             * Regenerasi session ID setelah autentikasi.
             *
             * Ini membantu mencegah session fixation.
             */

            session_regenerate_id(true);


            /* =================================================
               LAST LOGIN
            ================================================= */

            $now =
                date(
                    'Y-m-d H:i:s'
                );


            $stmtLogin =
                $pdo->prepare("
                    UPDATE users
                    SET last_login = ?
                    WHERE id = ?
                ");


            $stmtLogin->execute([

                $now,

                (int) $user['id']

            ]);


            /* =================================================
               SESSION ADMIN
            ================================================= */

            /*
             * JANGAN memasukkan $user secara utuh
             * karena $user masih memiliki password hash.
             */

            $_SESSION['user'] = [

                'id' =>
                    (int) $user['id'],

                'username' =>
                    $user['username'],

                'role' =>
                    'admin',

                'status' =>
                    'active',

                'last_login' =>
                    $now

            ];


            /* =================================================
               SESSION SECURITY
            ================================================= */

            $_SESSION['login_time'] =
                time();


            $_SESSION['last_activity'] =
                time();


            $_SESSION['last_regeneration'] =
                time();


            /* =================================================
               REDIRECT ADMIN
            ================================================= */

            $loginSuccess = true;

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

<title>Admin - Ghost Drive</title>


<link
    href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css"
    rel="stylesheet"
>


<link
    href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css"
    rel="stylesheet"
>


<style>

*{
    box-sizing:border-box;
}


body{

    min-height:100vh;

    margin:0;

    display:flex;

    justify-content:center;

    align-items:center;

    font-family:
        'Segoe UI',
        sans-serif;

    background:
        linear-gradient(
            -45deg,
            #111827,
            #1e293b,
            #312e81,
            #111827
        );

    background-size:400% 400%;

    animation:
        gradientMove 12s ease infinite;

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


.admin-card{

    width:400px;

    max-width:
        calc(100% - 30px);

    padding:40px;

    border-radius:20px;

    background:
        rgba(
            255,
            255,
            255,
            0.10
        );

    backdrop-filter:
        blur(15px);

    -webkit-backdrop-filter:
        blur(15px);

    box-shadow:
        0 20px 50px
        rgba(
            0,
            0,
            0,
            .35
        );

    color:white;

}


.admin-icon{

    width:80px;

    height:80px;

    margin:
        0 auto 20px;

    border-radius:50%;

    display:flex;

    align-items:center;

    justify-content:center;

    background:
        rgba(
            255,
            255,
            255,
            .12
        );

    font-size:35px;

}


h2{

    text-align:center;

    margin-bottom:8px;

}


.subtitle{

    text-align:center;

    opacity:.7;

    margin-bottom:30px;

}


.form-control{

    height:48px;

    border-radius:12px;

    border:none;

    background:
        rgba(
            255,
            255,
            255,
            .12
        );

    color:white;

}


.form-control::placeholder{

    color:
        rgba(
            255,
            255,
            255,
            .6
        );

}


.form-control:focus{

    background:
        rgba(
            255,
            255,
            255,
            .18
        );

    color:white;

    box-shadow:
        0 0 0 2px
        rgba(
            255,
            255,
            255,
            .15
        );

}


.form-control:-webkit-autofill{

    -webkit-text-fill-color:white;

    transition:
        background-color
        9999s
        ease-in-out
        0s;

}


.password-wrapper{

    position:relative;

}


.password-wrapper .form-control{

    padding-right:52px;

}


.password-toggle{

    position:absolute;

    right:8px;

    top:50%;

    transform:
        translateY(-50%);

    width:38px;

    height:38px;

    border:none;

    border-radius:9px;

    background:
        transparent;

    color:
        rgba(
            255,
            255,
            255,
            .75
        );

    display:flex;

    align-items:center;

    justify-content:center;

    cursor:pointer;

    transition:.2s;

}


.password-toggle:hover{

    color:white;

    background:
        rgba(
            255,
            255,
            255,
            .12
        );

}


.password-toggle:focus{

    outline:none;

    box-shadow:
        0 0 0 2px
        rgba(
            255,
            255,
            255,
            .3
        );

}


.btn-admin{

    height:48px;

    border:none;

    border-radius:12px;

    font-weight:600;

    transition:.2s;

}


.btn-admin:hover{

    transform:
        translateY(-1px);

}


.btn-admin:active{

    transform:
        translateY(0);

}


.footer{

    text-align:center;

    margin-top:20px;

}


.footer a{

    color:white;

    text-decoration:none;

    opacity:.8;

}


.footer a:hover{

    opacity:1;

}


.alert{

    border-radius:12px;

}

/* =========================================================
   LOGIN SUCCESS ANIMATION
========================================================= */

.login-success{

    text-align:center;

    padding:
        5px 0 0;

    animation:
        successFadeIn .7s ease both;

}


@keyframes successFadeIn{

    from{

        opacity:0;

        transform:
            translateY(20px)
            scale(.96);

    }

    to{

        opacity:1;

        transform:
            translateY(0)
            scale(1);

    }

}


/* =========================================================
   ICON
========================================================= */

.success-icon-wrapper{

    width:110px;

    height:110px;

    margin:
        0 auto 25px;

    position:relative;

    display:flex;

    justify-content:center;

    align-items:center;

}


.success-ring{

    position:absolute;

    width:100px;

    height:100px;

    border-radius:50%;

    border:
        2px solid
        rgba(255,255,255,.25);

    animation:
        ringPulse 2s ease-in-out infinite;

}


@keyframes ringPulse{

    0%{

        transform:scale(.85);

        opacity:.3;

    }

    50%{

        transform:scale(1.12);

        opacity:.8;

    }

    100%{

        transform:scale(.85);

        opacity:.3;

    }

}


.success-icon{

    width:75px;

    height:75px;

    border-radius:50%;

    display:flex;

    justify-content:center;

    align-items:center;

    background:
        rgba(255,255,255,.14);

    box-shadow:
        0 0 30px
        rgba(255,255,255,.15);

    font-size:30px;

    animation:
        iconAppear .8s ease both;

}


@keyframes iconAppear{

    0%{

        opacity:0;

        transform:
            scale(.3)
            rotate(-20deg);

    }

    70%{

        transform:
            scale(1.12)
            rotate(5deg);

    }

    100%{

        opacity:1;

        transform:
            scale(1)
            rotate(0);

    }

}


/* =========================================================
   CHECK
========================================================= */

.success-check{

    position:absolute;

    right:3px;

    bottom:5px;

    width:30px;

    height:30px;

    border-radius:50%;

    background:#22c55e;

    display:flex;

    align-items:center;

    justify-content:center;

    font-size:14px;

    box-shadow:
        0 5px 15px
        rgba(34,197,94,.4);

    animation:
        checkAppear .5s
        ease
        1s
        both;

}


@keyframes checkAppear{

    from{

        opacity:0;

        transform:
            scale(0);

    }

    to{

        opacity:1;

        transform:
            scale(1);

    }

}


/* =========================================================
   TITLE
========================================================= */

.success-title{

    font-size:25px;

    font-weight:700;

    margin-bottom:7px;

}


.success-subtitle{

    font-size:14px;

    opacity:.7;

    margin-bottom:25px;

}


/* =========================================================
   STATUS
========================================================= */

.security-status{

    text-align:left;

    margin:
        0 auto 22px;

    max-width:300px;

}


.status-item{

    display:flex;

    align-items:center;

    gap:10px;

    font-size:13px;

    margin-bottom:11px;

    opacity:.45;

    transform:
        translateX(-10px);

    animation:
        statusSlide .5s
        ease
        forwards;

}


.status-item:nth-child(1){

    animation-delay:.5s;

}


.status-item:nth-child(2){

    animation-delay:.9s;

}


.status-item:nth-child(3){

    animation-delay:1.3s;

}


.status-item.active{

    opacity:1;

}


.status-icon{

    width:23px;

    height:23px;

    border-radius:50%;

    display:flex;

    align-items:center;

    justify-content:center;

    background:
        rgba(255,255,255,.12);

    font-size:11px;

}


.status-item.completed
.status-icon{

    background:
        rgba(34,197,94,.25);

}


@keyframes statusSlide{

    to{

        opacity:1;

        transform:
            translateX(0);

    }

}


/* =========================================================
   PROGRESS
========================================================= */

.progress-container{

    width:100%;

    height:5px;

    border-radius:20px;

    overflow:hidden;

    background:
        rgba(255,255,255,.12);

    margin-top:18px;

}


.progress-bar-login{

    width:0%;

    height:100%;

    border-radius:20px;

    background:white;

    transition:
        width
        .7s
        cubic-bezier(
            .4,
            0,
            .2,
            1
        );

    box-shadow:
        0 0 12px
        rgba(255,255,255,.7);

}


/* =========================================================
   REDIRECT TEXT
========================================================= */

.redirect-text{

    margin-top:15px;

    font-size:13px;

    opacity:.7;

}


.typing-dots{

    animation:
        dots 1.2s
        infinite;

}


@keyframes dots{

    0%{
        opacity:.2;
    }

    50%{
        opacity:1;
    }

    100%{
        opacity:.2;
    }

}


/* =========================================================
   LOADING DOTS
========================================================= */

.loading-dots{

    margin-top:15px;

    display:flex;

    justify-content:center;

    gap:5px;

}


.loading-dots span{

    width:6px;

    height:6px;

    border-radius:50%;

    background:white;

    opacity:.3;

    animation:
        loadingDot
        1.2s
        infinite;

}


.loading-dots span:nth-child(2){

    animation-delay:.15s;

}


.loading-dots span:nth-child(3){

    animation-delay:.3s;

}


@keyframes loadingDot{

    0%,
    100%{

        opacity:.25;

        transform:
            translateY(0);

    }

    50%{

        opacity:1;

        transform:
            translateY(-4px);

    }

}

</style>

</head>


<body>


<div class="admin-card">

<?php if ($loginSuccess): ?>

    <!-- =========================================
         LOGIN BERHASIL
    ========================================== -->

    <div class="login-success">

        <div class="success-icon-wrapper">

            <div class="success-ring"></div>

            <div class="success-icon">

                <i class="fa-solid fa-shield-halved"></i>

            </div>

            <div class="success-check">

                <i class="fa-solid fa-check"></i>

            </div>

        </div>


        <div class="success-title">

            Login Berhasil

        </div>


        <div class="success-subtitle">

            Administrator berhasil diverifikasi

        </div>


        <div class="security-status">

            <div class="status-item active">

                <span class="status-icon">

                    <i class="fa-solid fa-check"></i>

                </span>

                <span>

                    Username terverifikasi

                </span>

            </div>


            <div class="status-item active">

                <span class="status-icon">

                    <i class="fa-solid fa-check"></i>

                </span>

                <span>

                    Password terverifikasi

                </span>

            </div>


            <div
                class="status-item"
                id="sessionStatus"
            >

                <span class="status-icon">

                    <i class="fa-solid fa-spinner fa-spin"></i>

                </span>

                <span>

                    Menyiapkan sesi aman...

                </span>

            </div>

        </div>


        <div class="progress-container">

            <div
                class="progress-bar-login"
                id="loginProgress"
            ></div>

        </div>


        <div
            class="redirect-text"
            id="redirectText"
        >

            Membuka Administrator...

        </div>


        <div class="loading-dots">

            <span></span>
            <span></span>
            <span></span>

        </div>

    </div>


    <script>

    document.addEventListener(
        "DOMContentLoaded",
        function(){

            const progress =
                document.getElementById(
                    "loginProgress"
                );


            const sessionStatus =
                document.getElementById(
                    "sessionStatus"
                );


            const redirectText =
                document.getElementById(
                    "redirectText"
                );


            /*
             * Progress 0 → 100%
             * sekitar 2,8 detik
             */

            setTimeout(
                function(){

                    progress.style.width =
                        "35%";

                },
                300
            );


            setTimeout(
                function(){

                    progress.style.width =
                        "65%";


                    sessionStatus.classList.add(
                        "completed"
                    );


                    sessionStatus.innerHTML =

                        '<span class="status-icon">' +

                            '<i class="fa-solid fa-check"></i>' +

                        '</span>' +

                        '<span>' +

                            'Sesi aman berhasil dibuat' +

                        '</span>';

                },
                1100
            );


            setTimeout(
                function(){

                    progress.style.width =
                        "85%";


                    redirectText.innerHTML =

                        'Menyiapkan Administrator <span class="typing-dots">...</span>';

                },
                1800
            );


            setTimeout(
                function(){

                    progress.style.width =
                        "100%";


                    redirectText.innerHTML =

                        '<i class="fa-solid fa-arrow-right"></i> ' +

                        'Membuka Administrator...';

                },
                2400
            );


            /*
             * Masuk admin setelah animasi selesai
             */

            setTimeout(
                function(){

                    window.location.href =
                        "admin.php";

                },
                3000
            );

        }
    );

    </script>


<?php else: ?>


    <!-- =========================================
         LOGIN FORM
    ========================================== -->

    <div class="admin-icon">

        <i class="fa fa-user-shield"></i>

    </div>


    <h2>

        Admin Ghost Drive

    </h2>


    <div class="subtitle">

        Administrator Login

    </div>


    <?php if ($error !== null): ?>

        <div
            class="alert alert-danger"
            role="alert"
        >

            <i
                class="fa fa-circle-exclamation me-1"
            ></i>

            <?= htmlspecialchars(
                $error,
                ENT_QUOTES,
                'UTF-8'
            ) ?>

        </div>

    <?php endif; ?>


    <form
        method="POST"
        autocomplete="on"
        id="adminLoginForm"
    >

        <div class="mb-3">

            <input
                type="text"
                name="username"
                class="form-control"
                placeholder="Username Administrator"
                autocomplete="username"
                autocapitalize="none"
                spellcheck="false"
                maxlength="100"
                required
            >

        </div>


        <div class="password-wrapper mb-3">

            <input
                type="password"
                name="password"
                id="adminPassword"
                class="form-control"
                placeholder="Password Administrator"
                autocomplete="current-password"
                maxlength="255"
                required
            >


            <button
                type="button"
                class="password-toggle"
                id="toggleAdminPassword"
                aria-label="Tampilkan password"
                title="Tampilkan password"
            >

                <i
                    class="fa-solid fa-eye"
                    id="passwordIcon"
                ></i>

            </button>

        </div>


        <button
            type="submit"
            name="login"
            id="loginButton"
            class="btn btn-light btn-admin w-100"
        >

            <i
                class="fa fa-right-to-bracket me-1"
            ></i>

            Masuk Administrator

        </button>

    </form>


    <div class="footer">

        <a href="../login.php">

            <i
                class="fa fa-arrow-left me-1"
            ></i>

            Kembali ke Login User

        </a>

    </div>


<?php endif; ?>

</div>


<script>

/* =========================================================
   TOGGLE PASSWORD
========================================================= */

(function(){

    const passwordInput =
        document.getElementById(
            'adminPassword'
        );


    const toggleButton =
        document.getElementById(
            'toggleAdminPassword'
        );


    const passwordIcon =
        document.getElementById(
            'passwordIcon'
        );


    if (
        !passwordInput ||
        !toggleButton ||
        !passwordIcon
    ){

        return;

    }


    toggleButton.addEventListener(
        'click',
        function(){

            const isPassword =
                passwordInput.type === 'password';


            if (isPassword) {

                passwordInput.type =
                    'text';


                passwordIcon.classList.remove(
                    'fa-eye'
                );


                passwordIcon.classList.add(
                    'fa-eye-slash'
                );


                toggleButton.setAttribute(
                    'aria-label',
                    'Sembunyikan password'
                );


                toggleButton.setAttribute(
                    'title',
                    'Sembunyikan password'
                );

            }

            else {

                passwordInput.type =
                    'password';


                passwordIcon.classList.remove(
                    'fa-eye-slash'
                );


                passwordIcon.classList.add(
                    'fa-eye'
                );


                toggleButton.setAttribute(
                    'aria-label',
                    'Tampilkan password'
                );


                toggleButton.setAttribute(
                    'title',
                    'Tampilkan password'
                );

            }

        }
    );

})();


/* =========================================================
   CEGAH DOUBLE SUBMIT
========================================================= */

(function(){

    const form =
        document.getElementById(
            'adminLoginForm'
        );


    const button =
        document.getElementById(
            'loginButton'
        );


    if (
        !form ||
        !button
    ){

        return;

    }


    form.addEventListener(
        'submit',
        function(){

            /*
             * Jangan disable terlalu awal
             * sebelum browser mengirim POST.
             */

            setTimeout(
                function(){

                    button.disabled =
                        true;


                    button.innerHTML =
                        '<i class="fa fa-spinner fa-spin me-1"></i>' +
                        ' Memverifikasi...';

                },
                10
            );

        }
    );

})();

</script>


</body>

</html>