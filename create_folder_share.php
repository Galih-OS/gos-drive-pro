<?php

require 'config/db.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}


/* =========================================================
   JSON HEADER
========================================================= */

header(
    'Content-Type: application/json; charset=utf-8'
);


/* =========================================================
   CEK LOGIN
========================================================= */

if (
    !isset($_SESSION['user']) ||
    !isset($_SESSION['user']['id'])
) {

    http_response_code(401);

    echo json_encode([
        'success' => false,
        'message' => 'Anda belum login.'
    ]);

    exit;
}


$user_id =
    (int) $_SESSION['user']['id'];


/* =========================================================
   CEK REQUEST
========================================================= */

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {

    http_response_code(405);

    echo json_encode([
        'success' => false,
        'message' => 'Metode request tidak diperbolehkan.'
    ]);

    exit;
}


/* =========================================================
   AMBIL FOLDER ID
========================================================= */

$folder_id =
    isset($_POST['folder_id'])
        ? (int) $_POST['folder_id']
        : 0;


if ($folder_id <= 0) {

    http_response_code(400);

    echo json_encode([
        'success' => false,
        'message' => 'Folder ID tidak valid.'
    ]);

    exit;
}


/* =========================================================
   CEK FOLDER MILIK USER
========================================================= */

$stmt = $pdo->prepare("
    SELECT
        id,
        user_id,
        folder_name,
        share_token
    FROM folders
    WHERE id = ?
    AND user_id = ?
    LIMIT 1
");


$stmt->execute([
    $folder_id,
    $user_id
]);


$folder =
    $stmt->fetch(PDO::FETCH_ASSOC);


if (!$folder) {

    http_response_code(404);

    echo json_encode([
        'success' => false,
        'message' => 'Folder tidak ditemukan atau bukan milik Anda.'
    ]);

    exit;
}


/* =========================================================
   CEK TOKEN LAMA
========================================================= */

if (
    !empty($folder['share_token'])
) {

    $token =
        $folder['share_token'];

}


/* =========================================================
   BUAT TOKEN BARU
========================================================= */

else {

    try {

        $token =
            bin2hex(
                random_bytes(32)
            );

    }

    catch (Throwable $e) {

        http_response_code(500);

        echo json_encode([
            'success' => false,
            'message' => 'Gagal membuat token share.'
        ]);

        exit;
    }


    $stmtToken =
        $pdo->prepare("
            UPDATE folders
            SET share_token = ?
            WHERE id = ?
            AND user_id = ?
        ");


    $stmtToken->execute([
        $token,
        $folder_id,
        $user_id
    ]);

}


/* =========================================================
   BUAT URL SHARE
========================================================= */

$scheme =
    (
        isset($_SERVER['HTTPS']) &&
        $_SERVER['HTTPS'] !== 'off'
    )
    ? 'https'
    : 'http';


$host =
    $_SERVER['HTTP_HOST'];


/*
 * Folder tempat file PHP berada.
 */

$basePath =
    rtrim(
        dirname($_SERVER['SCRIPT_NAME']),
        '/'
    );


$url =
    $scheme .
    '://' .
    $host .
    $basePath .
    '/share_folder.php?token=' .
    urlencode($token);


/* =========================================================
   RESPONSE
========================================================= */

echo json_encode([

    'success' =>
        true,

    'message' =>
        'Link folder berhasil dibuat.',

    'folder_id' =>
        $folder_id,

    'folder_name' =>
        $folder['folder_name'],

    'url' =>
        $url

], JSON_UNESCAPED_UNICODE);


exit;