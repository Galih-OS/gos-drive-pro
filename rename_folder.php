<?php

require 'config/db.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

header('Content-Type: application/json; charset=utf-8');


/* =========================================================
   CEK LOGIN
========================================================= */

if (!isset($_SESSION['user']['id'])) {

    http_response_code(401);

    echo json_encode([
        'success' => false,
        'message' => 'Anda belum login.'
    ]);

    exit;
}


$user_id = (int) $_SESSION['user']['id'];


/* =========================================================
   REQUEST
========================================================= */

$folder_id = isset($_POST['folder_id'])
    ? (int) $_POST['folder_id']
    : 0;

$folder_name = isset($_POST['folder_name'])
    ? trim($_POST['folder_name'])
    : '';


/* =========================================================
   VALIDASI FOLDER ID
========================================================= */

if ($folder_id <= 0) {

    http_response_code(400);

    echo json_encode([
        'success' => false,
        'message' => 'Folder ID tidak valid.'
    ]);

    exit;
}


/* =========================================================
   VALIDASI NAMA FOLDER
========================================================= */

if ($folder_name === '') {

    http_response_code(400);

    echo json_encode([
        'success' => false,
        'message' => 'Nama folder tidak boleh kosong.'
    ]);

    exit;
}


/* =========================================================
   BATASI PANJANG NAMA
========================================================= */

if (mb_strlen($folder_name, 'UTF-8') > 255) {

    http_response_code(400);

    echo json_encode([
        'success' => false,
        'message' => 'Nama folder maksimal 255 karakter.'
    ]);

    exit;
}


/* =========================================================
   CEK KARAKTER TERLARANG
========================================================= */

$invalidChars = [
    '/',
    '\\',
    ':',
    '*',
    '?',
    '"',
    '<',
    '>',
    '|'
];


foreach ($invalidChars as $char) {

    if (strpos($folder_name, $char) !== false) {

        http_response_code(400);

        echo json_encode([
            'success' => false,
            'message' =>
                'Nama folder mengandung karakter yang tidak diperbolehkan: '
                . $char
        ]);

        exit;
    }
}


/* =========================================================
   CEK FOLDER
========================================================= */

$stmt = $pdo->prepare("
    SELECT
        id,
        user_id,
        parent_id,
        folder_name
    FROM folders
    WHERE id = ?
    AND user_id = ?
    LIMIT 1
");

$stmt->execute([
    $folder_id,
    $user_id
]);

$folder = $stmt->fetch(PDO::FETCH_ASSOC);


if (!$folder) {

    http_response_code(404);

    echo json_encode([
        'success' => false,
        'message' => 'Folder tidak ditemukan atau bukan milik Anda.'
    ]);

    exit;
}


/* =========================================================
   CEK NAMA SAMA
========================================================= */

if ($folder['folder_name'] === $folder_name) {

    echo json_encode([
        'success' => true,
        'message' => 'Nama folder tidak berubah.',
        'folder_id' => $folder_id,
        'folder_name' => $folder_name
    ], JSON_UNESCAPED_UNICODE);

    exit;
}


/* =========================================================
   CEK DUPLIKAT DALAM FOLDER YANG SAMA
========================================================= */

$stmtCheck = $pdo->prepare("
    SELECT id
    FROM folders
    WHERE user_id = ?
    AND parent_id <=> ?
    AND folder_name = ?
    AND id != ?
    LIMIT 1
");

$stmtCheck->execute([
    $user_id,
    $folder['parent_id'],
    $folder_name,
    $folder_id
]);


$duplicate = $stmtCheck->fetch(PDO::FETCH_ASSOC);


if ($duplicate) {

    http_response_code(409);

    echo json_encode([
        'success' => false,
        'message' => 'Nama folder tersebut sudah digunakan.'
    ], JSON_UNESCAPED_UNICODE);

    exit;
}


/* =========================================================
   RENAME
========================================================= */

try {

    $stmtUpdate = $pdo->prepare("
        UPDATE folders
        SET folder_name = ?
        WHERE id = ?
        AND user_id = ?
        LIMIT 1
    ");

    $stmtUpdate->execute([
        $folder_name,
        $folder_id,
        $user_id
    ]);


    echo json_encode([

        'success' => true,

        'message' =>
            'Folder berhasil diubah namanya.',

        'folder_id' =>
            $folder_id,

        'old_name' =>
            $folder['folder_name'],

        'folder_name' =>
            $folder_name

    ], JSON_UNESCAPED_UNICODE);

    exit;

} catch (Throwable $e) {

    http_response_code(500);

    echo json_encode([

        'success' => false,

        'message' =>
            'Gagal mengubah nama folder.',

        'error' =>
            $e->getMessage()

    ], JSON_UNESCAPED_UNICODE);

    exit;
}