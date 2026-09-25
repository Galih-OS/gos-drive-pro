<?php

require '../config/db.php';


/* =========================================================
   SESSION
========================================================= */

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
    'Cache-Control: post-check=0, pre-check=0',
    false
);

header(
    'Pragma: no-cache'
);

header(
    'Expires: 0'
);


/* =========================================================
   CATAT LOGOUT ADMIN
========================================================= */

if (
    isset($_SESSION['user']) &&
    is_array($_SESSION['user']) &&
    isset($_SESSION['user']['id'])
) {

    $user_id =
        (int) $_SESSION['user']['id'];


    try {

        $stmt =
            $pdo->prepare("
                UPDATE users
                SET last_logout = ?
                WHERE id = ?
            ");


        $stmt->execute([

            date('Y-m-d H:i:s'),

            $user_id

        ]);

    }

    catch (Throwable $e) {

        /*
         * Jangan menghentikan proses logout
         * hanya karena pencatatan logout gagal.
         */

    }

}


/* =========================================================
   HAPUS SELURUH DATA SESSION
========================================================= */

$_SESSION = [];


/* =========================================================
   HAPUS COOKIE SESSION
========================================================= */

if (
    ini_get('session.use_cookies')
) {

    $params =
        session_get_cookie_params();


    setcookie(

        session_name(),

        '',

        time() - 42000,

        $params['path'] ?? '/',

        $params['domain'] ?? '',

        $params['secure'] ?? false,

        $params['httponly'] ?? true

    );

}


/* =========================================================
   HANCURKAN SESSION
========================================================= */

session_destroy();


/* =========================================================
   BUAT SESSION BARU UNTUK HALAMAN LOGOUT
========================================================= */

/*
 * Session lama sudah dihancurkan.
 *
 * Kita buat session baru hanya untuk menampilkan
 * halaman animasi logout.
 */

session_start();


session_regenerate_id(true);


/* =========================================================
   STATUS LOGOUT
========================================================= */

$_SESSION['logout_success'] =
    true;

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

<title>Logout - Ghost Drive</title>


<link
    href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css"
    rel="stylesheet"
>


<link
    href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css"
    rel="stylesheet"
>


<style>

/* =========================================================
   RESET
========================================================= */

*{

    box-sizing:border-box;

}


html,
body{

    width:100%;

    min-height:100%;

    margin:0;

}


body{

    min-height:100vh;

    display:flex;

    align-items:center;

    justify-content:center;

    overflow:hidden;

    font-family:
        'Segoe UI',
        sans-serif;

    color:white;

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
        backgroundMove
        12s ease infinite;

}


@keyframes backgroundMove{

    0%{

        background-position:
            0% 50%;

    }

    50%{

        background-position:
            100% 50%;

    }

    100%{

        background-position:
            0% 50%;

    }

}


/* =========================================================
   CARD
========================================================= */

.logout-card{

    width:400px;

    max-width:
        calc(100% - 30px);

    padding:42px 35px;

    text-align:center;

    border-radius:24px;

    background:
        rgba(
            255,
            255,
            255,
            .10
        );

    backdrop-filter:
        blur(18px);

    -webkit-backdrop-filter:
        blur(18px);

    border:
        1px solid
        rgba(
            255,
            255,
            255,
            .12
        );

    box-shadow:
        0 25px 60px
        rgba(
            0,
            0,
            0,
            .4
        );

    animation:
        cardAppear
        .7s
        ease
        both;

}


@keyframes cardAppear{

    from{

        opacity:0;

        transform:
            translateY(25px)
            scale(.95);

    }

    to{

        opacity:1;

        transform:
            translateY(0)
            scale(1);

    }

}


/* =========================================================
   LOGOUT ICON
========================================================= */

.logout-icon-wrapper{

    position:relative;

    width:120px;

    height:120px;

    margin:
        0 auto 25px;

    display:flex;

    align-items:center;

    justify-content:center;

}


.logout-ring{

    position:absolute;

    width:115px;

    height:115px;

    border-radius:50%;

    border:
        2px solid
        rgba(
            255,
            255,
            255,
            .20
        );

    animation:
        logoutRing
        2s
        ease-in-out
        infinite;

}


@keyframes logoutRing{

    0%{

        transform:
            scale(.80);

        opacity:.25;

    }

    50%{

        transform:
            scale(1.10);

        opacity:.8;

    }

    100%{

        transform:
            scale(.80);

        opacity:.25;

    }

}


.logout-icon{

    width:78px;

    height:78px;

    border-radius:50%;

    display:flex;

    align-items:center;

    justify-content:center;

    font-size:31px;

    background:
        rgba(
            255,
            255,
            255,
            .12
        );

    box-shadow:
        0 0 30px
        rgba(
            255,
            255,
            255,
            .10
        );

    animation:
        iconLogout
        .8s
        ease
        both;

}


@keyframes iconLogout{

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

.logout-check{

    position:absolute;

    right:4px;

    bottom:8px;

    width:31px;

    height:31px;

    border-radius:50%;

    display:flex;

    align-items:center;

    justify-content:center;

    background:#22c55e;

    box-shadow:
        0 5px 18px
        rgba(
            34,
            197,
            94,
            .45
        );

    animation:
        checkAppear
        .5s
        ease
        .9s
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

.logout-title{

    font-size:25px;

    font-weight:700;

    margin-bottom:7px;

    animation:
        textAppear
        .6s
        ease
        .3s
        both;

}


.logout-subtitle{

    font-size:14px;

    opacity:.7;

    margin-bottom:27px;

    animation:
        textAppear
        .6s
        ease
        .5s
        both;

}


@keyframes textAppear{

    from{

        opacity:0;

        transform:
            translateY(10px);

    }

    to{

        opacity:1;

        transform:
            translateY(0);

    }

}


/* =========================================================
   STATUS
========================================================= */

.logout-status{

    text-align:left;

    max-width:300px;

    margin:
        0 auto 22px;

}


.status-row{

    display:flex;

    align-items:center;

    gap:10px;

    font-size:13px;

    margin-bottom:11px;

    opacity:0;

    transform:
        translateX(-12px);

    animation:
        statusAppear
        .5s
        ease
        forwards;

}


.status-row:nth-child(1){

    animation-delay:
        .8s;

}


.status-row:nth-child(2){

    animation-delay:
        1.1s;

}


.status-row:nth-child(3){

    animation-delay:
        1.4s;

}


.status-circle{

    width:23px;

    height:23px;

    min-width:23px;

    border-radius:50%;

    display:flex;

    align-items:center;

    justify-content:center;

    background:
        rgba(
            34,
            197,
            94,
            .22
        );

    font-size:10px;

}


@keyframes statusAppear{

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

    overflow:hidden;

    border-radius:20px;

    background:
        rgba(
            255,
            255,
            255,
            .12
        );

}


.progress-bar{

    width:0%;

    height:100%;

    border-radius:20px;

    background:white;

    box-shadow:
        0 0 12px
        rgba(
            255,
            255,
            255,
            .7
        );

    transition:
        width
        .6s
        cubic-bezier(
            .4,
            0,
            .2,
            1
        );

}


.logout-message{

    margin-top:15px;

    font-size:13px;

    opacity:.7;

}


/* =========================================================
   DOTS
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

    animation-delay:
        .15s;

}


.loading-dots span:nth-child(3){

    animation-delay:
        .30s;

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


<div class="logout-card">


    <!-- =================================================
         ICON
    ================================================== -->

    <div class="logout-icon-wrapper">

        <div class="logout-ring"></div>


        <div class="logout-icon">

            <i
                class="fa-solid fa-right-from-bracket"
            ></i>

        </div>


        <div class="logout-check">

            <i
                class="fa-solid fa-check"
            ></i>

        </div>

    </div>


    <!-- =================================================
         TITLE
    ================================================== -->

    <div class="logout-title">

        Logout Berhasil

    </div>


    <div class="logout-subtitle">

        Sesi administrator telah ditutup dengan aman.

    </div>


    <!-- =================================================
         STATUS
    ================================================== -->

    <div class="logout-status">


        <div class="status-row">

            <span class="status-circle">

                <i
                    class="fa-solid fa-check"
                ></i>

            </span>

            <span>

                Sesi administrator dihapus

            </span>

        </div>


        <div class="status-row">

            <span class="status-circle">

                <i
                    class="fa-solid fa-check"
                ></i>

            </span>

            <span>

                Cookie sesi dihapus

            </span>

        </div>


        <div class="status-row">

            <span class="status-circle">

                <i
                    class="fa-solid fa-check"
                ></i>

            </span>

            <span>

                Keamanan sesi diperbarui

            </span>

        </div>


    </div>


    <!-- =================================================
         PROGRESS
    ================================================== -->

    <div class="progress-container">

        <div
            class="progress-bar"
            id="logoutProgress"
        ></div>

    </div>


    <div
        class="logout-message"
        id="logoutMessage"
    >

        Mengamankan sesi...

    </div>


    <div class="loading-dots">

        <span></span>

        <span></span>

        <span></span>

    </div>


</div>


<script>

document.addEventListener(
    'DOMContentLoaded',
    function(){

        const progress =
            document.getElementById(
                'logoutProgress'
            );


        const message =
            document.getElementById(
                'logoutMessage'
            );


        /* =============================================
           PROGRESS 25%
        ============================================= */

        setTimeout(
            function(){

                progress.style.width =
                    '25%';

                message.innerHTML =
                    'Menghapus sesi administrator...';

            },
            350
        );


        /* =============================================
           PROGRESS 55%
        ============================================= */

        setTimeout(
            function(){

                progress.style.width =
                    '55%';

                message.innerHTML =
                    'Membersihkan data sesi...';

            },
            900
        );


        /* =============================================
           PROGRESS 80%
        ============================================= */

        setTimeout(
            function(){

                progress.style.width =
                    '80%';

                message.innerHTML =
                    'Memastikan sesi telah ditutup...';

            },
            1500
        );


        /* =============================================
           PROGRESS 100%
        ============================================= */

        setTimeout(
            function(){

                progress.style.width =
                    '100%';

                message.innerHTML =
                    '<i class="fa-solid fa-check"></i> ' +
                    'Logout aman. Mengalihkan...';

            },
            2100
        );


        /* =============================================
           REDIRECT
        ============================================= */

        setTimeout(
            function(){

                window.location.replace(
                    'admin_login.php'
                );

            },
            2700
        );

    }
);

</script>


</body>

</html>