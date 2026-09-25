<?php

/*
|--------------------------------------------------------------------------
| GosDrive - Secure Authentication / Session
|--------------------------------------------------------------------------
*/

if (session_status() === PHP_SESSION_NONE) {

    /*
     * =========================================================
     * SESSION HARDENING
     * =========================================================
     */

    ini_set('session.use_cookies', '1');

    ini_set('session.use_only_cookies', '1');

    ini_set('session.use_strict_mode', '1');

    ini_set('session.use_trans_sid', '0');

    ini_set('session.cookie_httponly', '1');

    /*
     * Jangan gunakan session.cookie_secure = 1 secara paksa
     * karena GosDrive Anda masih digunakan melalui localhost HTTP.
     *
     * Kita aktifkan otomatis jika HTTPS.
     */

    $isHttps =
        (
            isset($_SERVER['HTTPS']) &&
            $_SERVER['HTTPS'] !== 'off'
        );


    /*
     * =========================================================
     * SESSION COOKIE
     * =========================================================
     */

    session_set_cookie_params([

        'lifetime' => 0,

        'path' => '/',

        'secure' => $isHttps,

        'httponly' => true,

        'samesite' => 'Lax'

    ]);


    /*
     * =========================================================
     * START SESSION
     * =========================================================
     */

    session_start();

}


/*
|--------------------------------------------------------------------------
| NO CACHE
|--------------------------------------------------------------------------
*/

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


/*
|--------------------------------------------------------------------------
| KONFIGURASI SESSION
|--------------------------------------------------------------------------
*/

/*
 * 30 menit tidak ada aktivitas.
 */

const GOSDRIVE_IDLE_TIMEOUT = 1800;


/*
 * Maksimal umur login 8 jam.
 */

const GOSDRIVE_MAX_SESSION_AGE = 28800;


/*
|--------------------------------------------------------------------------
| FUNGSI LOGOUT
|--------------------------------------------------------------------------
*/

function gosdrive_logout()
{

    /*
     * Ambil parameter cookie sebelum session dihancurkan.
     */

    $cookieParams =
        session_get_cookie_params();


    /*
     * Hapus semua data session.
     */

    $_SESSION = [];


    /*
     * Hapus cookie session.
     */

    if (
        ini_get('session.use_cookies')
    ) {

        setcookie(

            session_name(),

            '',

            [
                'expires' => time() - 42000,

                'path' =>
                    $cookieParams['path']
                    ?? '/',

                'domain' =>
                    $cookieParams['domain']
                    ?? '',

                'secure' =>
                    $cookieParams['secure']
                    ?? false,

                'httponly' =>
                    $cookieParams['httponly']
                    ?? true,

                'samesite' =>
                    $cookieParams['samesite']
                    ?? 'Lax'
            ]

        );

    }


    /*
     * Hancurkan session di server.
     */

    if (
        session_status() === PHP_SESSION_ACTIVE
    ) {

        session_destroy();

    }

}


/*
|--------------------------------------------------------------------------
| CEK LOGIN
|--------------------------------------------------------------------------
*/

function gosdrive_require_login()
{

    /*
     * Tidak ada user session.
     */

    if (
        !isset($_SESSION['user']) ||
        !is_array($_SESSION['user']) ||
        empty($_SESSION['user']['id'])
    ) {

        gosdrive_logout();

        header(
            'Location: login.php',
            true,
            303
        );

        exit;

    }


    /*
     * =========================================================
     * CEK ID USER
     * =========================================================
     */

    $userId =
        (int) $_SESSION['user']['id'];


    if ($userId <= 0) {

        gosdrive_logout();

        header(
            'Location: login.php',
            true,
            303
        );

        exit;

    }


    /*
     * =========================================================
     * CEK WAKTU LOGIN
     * =========================================================
     */

    $now =
        time();


    /*
     * Jika tidak ada login_time,
     * session dianggap tidak valid.
     */

    if (
        empty($_SESSION['login_time'])
    ) {

        gosdrive_logout();

        header(
            'Location: login.php',
            true,
            303
        );

        exit;

    }


    $loginTime =
        (int) $_SESSION['login_time'];


    /*
     * =========================================================
     * MAXIMUM SESSION AGE
     * =========================================================
     */

    if (
        ($now - $loginTime)
        >
        GOSDRIVE_MAX_SESSION_AGE
    ) {

        gosdrive_logout();

        header(
            'Location: login.php?expired=1',
            true,
            303
        );

        exit;

    }


    /*
     * =========================================================
     * IDLE TIMEOUT
     * =========================================================
     */

    $lastActivity =
        isset($_SESSION['last_activity'])
            ? (int) $_SESSION['last_activity']
            : 0;


    if (
        $lastActivity <= 0
    ) {

        gosdrive_logout();

        header(
            'Location: login.php?expired=1',
            true,
            303
        );

        exit;

    }


    if (
        ($now - $lastActivity)
        >
        GOSDRIVE_IDLE_TIMEOUT
    ) {

        gosdrive_logout();

        header(
            'Location: login.php?timeout=1',
            true,
            303
        );

        exit;

    }


    /*
     * =========================================================
     * UPDATE LAST ACTIVITY
     * =========================================================
     */

    $_SESSION['last_activity'] =
        $now;


    /*
     * =========================================================
     * SESSION ID ROTATION
     * =========================================================
     *
     * Regenerasi setiap 15 menit.
     */

    $lastRegeneration =
        isset(
            $_SESSION['last_regeneration']
        )
            ? (int)
                $_SESSION['last_regeneration']
            : 0;


    if (
        $lastRegeneration <= 0 ||
        ($now - $lastRegeneration) >= 900
    ) {

        /*
         * Jangan gunakan true secara rutin
         * karena dapat menyebabkan race condition
         * pada request bersamaan.
         */

        session_regenerate_id(false);

        $_SESSION['last_regeneration'] =
            $now;

    }

}


/*
|--------------------------------------------------------------------------
| CEK ROLE
|--------------------------------------------------------------------------
*/

function gosdrive_require_role(
    string $role
)
{

    gosdrive_require_login();


    if (
        !isset($_SESSION['user']['role']) ||
        $_SESSION['user']['role'] !== $role
    ) {

        http_response_code(403);

        exit(
            'Akses ditolak.'
        );

    }

}