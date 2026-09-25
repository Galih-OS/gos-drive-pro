<?php

require 'config/db.php';
require 'config/auth.php';

gosdrive_require_login();

header(
    'Content-Type: application/json; charset=utf-8'
);


/* =========================================================
   SESSION
========================================================= */

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

if (!isset($_SESSION['user'])) {

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
   REQUEST
========================================================= */

$folder_id =
    isset($_POST['folder_id'])
        ? (int) $_POST['folder_id']
        : 0;


$delete_contents =
    isset($_POST['delete_contents'])
        ? (int) $_POST['delete_contents']
        : 0;


/* =========================================================
   VALIDASI
========================================================= */

if ($folder_id <= 0) {

    http_response_code(400);

    echo json_encode([
        'success' => false,
        'message' => 'Folder ID tidak valid.'
    ]);

    exit;
}


if ($delete_contents !== 1) {

    http_response_code(400);

    echo json_encode([
        'success' => false,
        'message' =>
            'Konfirmasi penghapusan isi folder tidak diterima.'
    ]);

    exit;
}


/* =========================================================
   CEK FOLDER UTAMA
========================================================= */

$stmt =
    $pdo->prepare("
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


$rootFolder =
    $stmt->fetch(
        PDO::FETCH_ASSOC
    );


if (!$rootFolder) {

    http_response_code(404);

    echo json_encode([
        'success' => false,
        'message' =>
            'Folder tidak ditemukan atau bukan milik Anda.'
    ]);

    exit;
}


/* =========================================================
   KUMPULKAN SEMUA FOLDER
========================================================= */

$folderIds = [
    $folder_id
];


$processed = [];


$index = 0;


while (
    isset(
        $folderIds[$index]
    )
) {

    $currentId =
        (int) $folderIds[$index];


    $index++;


    if (
        isset(
            $processed[$currentId]
        )
    ) {

        continue;

    }


    $processed[$currentId] = true;


    /* =====================================================
       CARI SUBFOLDER
    ===================================================== */

    $stmtChildren =
        $pdo->prepare("
            SELECT id
            FROM folders
            WHERE user_id = ?
            AND parent_id = ?
        ");


    $stmtChildren->execute([
        $user_id,
        $currentId
    ]);


    $children =
        $stmtChildren->fetchAll(
            PDO::FETCH_COLUMN
        );


    foreach (
        $children
        as $childId
    ) {

        $childId =
            (int) $childId;


        if (
            !isset(
                $processed[$childId]
            )
        ) {

            $folderIds[] =
                $childId;

        }

    }

}


/* =========================================================
   JUMLAH FOLDER
========================================================= */

$deletedFolders =
    count($folderIds);


/* =========================================================
   PLACEHOLDER
========================================================= */

$placeholders =
    implode(
        ',',
        array_fill(
            0,
            count($folderIds),
            '?'
        )
    );


$params =
    array_merge(
        [$user_id],
        $folderIds
    );


/* =========================================================
   AMBIL FILE
========================================================= */

$stmtFiles =
    $pdo->prepare("
        SELECT
            id,
            filename,
            filepath,
            file_size
        FROM files
        WHERE user_id = ?
        AND folder_id IN ($placeholders)
    ");


$stmtFiles->execute(
    $params
);


$files =
    $stmtFiles->fetchAll(
        PDO::FETCH_ASSOC
    );


$deletedFiles =
    count($files);


/* =========================================================
   TOTAL SIZE
========================================================= */

$deletedBytes = 0;


foreach (
    $files
    as $file
) {

    $deletedBytes +=
        (int) (
            $file['file_size'] ?? 0
        );

}


/* =========================================================
   TRANSACTION
========================================================= */

try {

    $pdo->beginTransaction();


    /* =====================================================
       HAPUS FILE FISIK
    ===================================================== */

    foreach (
        $files
        as $file
    ) {

        $filepath =
            trim(
                $file['filepath'] ?? ''
            );


        /*
         * Jika path kosong:
         * jangan melakukan apa-apa.
         */

        if ($filepath === '') {

            continue;

        }


        /*
         * File ada -> hapus.
         */

        if (file_exists($filepath)) {

            if (!unlink($filepath)) {

                throw new Exception(

                    'Gagal menghapus file fisik: '
                    .
                    $file['filename']

                );

            }

        }

    }


    /* =====================================================
       HAPUS FILE DATABASE
    ===================================================== */

    if ($deletedFiles > 0) {

        $stmtDeleteFiles =
            $pdo->prepare("
                DELETE FROM files
                WHERE user_id = ?
                AND folder_id IN ($placeholders)
            ");


        $stmtDeleteFiles->execute(
            $params
        );

    }


    /* =====================================================
       HAPUS FOLDER
    ===================================================== */

    $stmtDeleteFolders =
        $pdo->prepare("
            DELETE FROM folders
            WHERE user_id = ?
            AND id IN ($placeholders)
        ");


    $stmtDeleteFolders->execute(
        $params
    );


    /* =====================================================
       COMMIT
    ===================================================== */

    $pdo->commit();


}

catch (Throwable $e) {

    if (
        $pdo->inTransaction()
    ) {

        $pdo->rollBack();

    }


    http_response_code(500);


    echo json_encode([

        'success' => false,

        'message' =>
            'Gagal menghapus folder.',

        'error' =>
            $e->getMessage()

    ], JSON_UNESCAPED_UNICODE);


    exit;

}


/* =========================================================
   RESPONSE BERHASIL
========================================================= */

echo json_encode([

    'success' =>
        true,

    'message' =>
        'Folder dan seluruh isinya berhasil dihapus.',

    'folder_id' =>
        $folder_id,

    'folder_name' =>
        $rootFolder['folder_name'],

    'deleted_folders' =>
        $deletedFolders,

    'deleted_files' =>
        $deletedFiles,

    'deleted_bytes' =>
        $deletedBytes

], JSON_UNESCAPED_UNICODE);


exit;