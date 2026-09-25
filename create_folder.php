<?php

require 'config/db.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/* =========================================================
   CEK LOGIN
========================================================= */

if (!isset($_SESSION['user'])) {

    http_response_code(401);

    echo json_encode([
        'success' => false,
        'message' => 'Anda belum login.'
    ]);

    exit;
}


/* =========================================================
   HEADER JSON
========================================================= */

header('Content-Type: application/json; charset=utf-8');


/* =========================================================
   USER LOGIN
========================================================= */

$user_id = (int) $_SESSION['user']['id'];


/* =========================================================
   AMBIL DATA
========================================================= */

$folder_name = isset($_POST['folder_name'])
    ? trim($_POST['folder_name'])
    : '';

$parent_id = isset($_POST['parent_id'])
    ? (int) $_POST['parent_id']
    : 0;


/* =========================================================
   VALIDASI NAMA FOLDER
========================================================= */

if ($folder_name === '') {

    echo json_encode([
        'success' => false,
        'message' => 'Nama folder tidak boleh kosong.'
    ]);

    exit;
}


/* =========================================================
   BATASI PANJANG NAMA
========================================================= */

if (mb_strlen($folder_name) > 255) {

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

        echo json_encode([
            'success' => false,
            'message' => 'Nama folder mengandung karakter yang tidak diperbolehkan.'
        ]);

        exit;
    }

}


/* =========================================================
   NORMALISASI PARENT ID
========================================================= */

if ($parent_id <= 0) {

    $parent_id = null;

}


/* =========================================================
   CEK PARENT FOLDER
========================================================= */

if ($parent_id !== null) {

    $stmtParent = $pdo->prepare("
        SELECT id
        FROM folders
        WHERE id = ?
        AND user_id = ?
        LIMIT 1
    ");

    $stmtParent->execute([
        $parent_id,
        $user_id
    ]);

    $parentExists = $stmtParent->fetchColumn();


    if (!$parentExists) {

        echo json_encode([
            'success' => false,
            'message' => 'Folder induk tidak ditemukan.'
        ]);

        exit;
    }

}


/* =========================================================
   CEK NAMA FOLDER DUPLIKAT
========================================================= */

if ($parent_id === null) {

    $stmtCheck = $pdo->prepare("
        SELECT id
        FROM folders
        WHERE user_id = ?
        AND parent_id IS NULL
        AND folder_name = ?
        LIMIT 1
    ");

    $stmtCheck->execute([
        $user_id,
        $folder_name
    ]);

}
else {

    $stmtCheck = $pdo->prepare("
        SELECT id
        FROM folders
        WHERE user_id = ?
        AND parent_id = ?
        AND folder_name = ?
        LIMIT 1
    ");

    $stmtCheck->execute([
        $user_id,
        $parent_id,
        $folder_name
    ]);

}


$duplicate = $stmtCheck->fetchColumn();


if ($duplicate) {

    echo json_encode([
        'success' => false,
        'message' => 'Folder dengan nama tersebut sudah ada.'
    ]);

    exit;
}


/* =========================================================
   BUAT FOLDER
========================================================= */

try {

    $stmtInsert = $pdo->prepare("
        INSERT INTO folders
        (
            user_id,
            parent_id,
            folder_name,
            created_at
        )
        VALUES
        (
            ?,
            ?,
            ?,
            NOW()
        )
    ");


    $stmtInsert->execute([
        $user_id,
        $parent_id,
        $folder_name
    ]);


    $folder_id = (int) $pdo->lastInsertId();


    /* =====================================================
       RESPONSE
    ===================================================== */

    echo json_encode([
        'success' => true,
        'message' => 'Folder berhasil dibuat.',
        'folder_id' => $folder_id,
        'folder_name' => $folder_name
    ]);

    exit;

}
catch (PDOException $e) {

    http_response_code(500);

    echo json_encode([
        'success' => false,
        'message' => 'Gagal membuat folder.',
        'error' => $e->getMessage()
    ]);

    exit;

}