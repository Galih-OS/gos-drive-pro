<?php

require 'config/db.php';


header(
    'Content-Type: application/json; charset=utf-8'
);


/* =========================================
   CEK LOGIN PEMILIK
========================================= */

if(!isset($_SESSION['user'])){

    echo json_encode([
        'success' => false,
        'message' => 'Anda belum login.'
    ]);

    exit;

}


$user_id =
    (int)$_SESSION['user']['id'];


$id =
    isset($_POST['id'])
        ? (int)$_POST['id']
        : 0;


if($id <= 0){

    echo json_encode([
        'success' => false,
        'message' => 'ID file tidak valid.'
    ]);

    exit;

}


/* =========================================
   CEK FILE MILIK USER
========================================= */

$stmt = $pdo->prepare("
    SELECT *
    FROM files
    WHERE id = ?
    AND user_id = ?
    LIMIT 1
");

$stmt->execute([
    $id,
    $user_id
]);


$file =
    $stmt->fetch(PDO::FETCH_ASSOC);


if(!$file){

    echo json_encode([
        'success' => false,
        'message' => 'File tidak ditemukan.'
    ]);

    exit;

}


/* =========================================
   CEK TOKEN LAMA
========================================= */

if(
    !empty($file['share_token'])
){

    $token =
        $file['share_token'];

}

else {


    /* ===============================
       BUAT TOKEN RANDOM
    =============================== */

    $token =
        bin2hex(
            random_bytes(32)
        );


    $stmt = $pdo->prepare("
        UPDATE files
        SET share_token = ?
        WHERE id = ?
        AND user_id = ?
    ");


    $stmt->execute([
        $token,
        $id,
        $user_id
    ]);

}


/* =========================================
   BUAT URL
========================================= */

$baseUrl =
    rtrim(
        dirname(
            $_SERVER['SCRIPT_NAME']
        ),
        '/'
    );


$url =
    (
        isset($_SERVER['HTTPS']) &&
        $_SERVER['HTTPS'] !== 'off'
    )
    ? 'https://'
    : 'http://';


$url .=
    $_SERVER['HTTP_HOST'];


$url .=
    $baseUrl;


$url .=
    '/share.php?token=' .
    urlencode($token);


/* =========================================
   RESPONSE
========================================= */

echo json_encode([

    'success' => true,

    'url' => $url

]);

exit;