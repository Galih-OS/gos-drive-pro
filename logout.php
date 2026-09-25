<?php

require 'config/db.php';
require 'config/auth.php';


/*
|--------------------------------------------------------------------------
| SIMPAN USER ID SEBELUM SESSION DIHAPUS
|--------------------------------------------------------------------------
*/

$user_id = 0;


if (
    isset($_SESSION['user']['id'])
) {

    $user_id =
        (int) $_SESSION['user']['id'];

}


/*
|--------------------------------------------------------------------------
| CATAT LAST LOGOUT
|--------------------------------------------------------------------------
*/

if ($user_id > 0) {

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
         * Jika pencatatan gagal,
         * logout tetap harus dilakukan.
         */

    }

}


/*
|--------------------------------------------------------------------------
| HANCURKAN SESSION
|--------------------------------------------------------------------------
*/

gosdrive_logout();


/*
|--------------------------------------------------------------------------
| REDIRECT LOGIN
|--------------------------------------------------------------------------
*/

header(
    'Location: login.php?logout=1',
    true,
    303
);

exit;