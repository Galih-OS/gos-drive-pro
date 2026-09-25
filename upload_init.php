<?php

require 'config/db.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

header('Content-Type: application/json; charset=utf-8');


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
   DATA UPLOAD
========================================================= */

$uploadId =
    $_POST['upload_id'] ?? '';


$filename =
    basename(
        $_POST['filename'] ?? ''
    );


$fileSize =
    (int) (
        $_POST['filesize'] ?? 0
    );


/*
|--------------------------------------------------------------------------
| FOLDER ID
|--------------------------------------------------------------------------
|
| 0 = Root / File Saya
| >0 = Folder tertentu
|
*/

$folder_id =
    (int) (
        $_POST['folder_id'] ?? 0
    );


/* =========================================================
   VALIDASI DATA
========================================================= */

if (
    $uploadId === '' ||
    $filename === '' ||
    $fileSize <= 0
) {

    http_response_code(400);

    echo json_encode([
        'success' => false,
        'message' => 'Data upload tidak lengkap.'
    ]);

    exit;
}


/* =========================================================
   BERSIHKAN UPLOAD ID
========================================================= */

$safeUploadId =
    preg_replace(
        '/[^a-zA-Z0-9_-]/',
        '',
        $uploadId
    );


if ($safeUploadId === '') {

    http_response_code(400);

    echo json_encode([
        'success' => false,
        'message' => 'Upload ID tidak valid.'
    ]);

    exit;
}


/* =========================================================
   VALIDASI FOLDER
========================================================= */

if ($folder_id > 0) {

    $stmtFolder =
        $pdo->prepare("
            SELECT
                id,
                folder_name,
                parent_id
            FROM folders
            WHERE id = ?
            AND user_id = ?
            LIMIT 1
        ");

    $stmtFolder->execute([
        $folder_id,
        $user_id
    ]);


    $folderData =
        $stmtFolder->fetch(
            PDO::FETCH_ASSOC
        );


    if (!$folderData) {

        http_response_code(404);

        echo json_encode([
            'success' => false,
            'message' =>
                'Folder tidak ditemukan atau bukan milik Anda.'
        ]);

        exit;
    }

}
else {

    $folder_id = 0;

}


/* =========================================================
   CEK USER
========================================================= */

$stmt =
    $pdo->prepare("
        SELECT storage_quota
        FROM users
        WHERE id = ?
        LIMIT 1
    ");

$stmt->execute([
    $user_id
]);


$userData =
    $stmt->fetch(
        PDO::FETCH_ASSOC
    );


if (!$userData) {

    http_response_code(404);

    echo json_encode([
        'success' => false,
        'message' =>
            'Data user tidak ditemukan.'
    ]);

    exit;
}


$storageQuota =
    (int) $userData['storage_quota'];


/* =========================================================
   STORAGE TERPAKAI
========================================================= */

$stmt =
    $pdo->prepare("
        SELECT COALESCE(
            SUM(file_size),
            0
        )
        FROM files
        WHERE user_id = ?
    ");

$stmt->execute([
    $user_id
]);


$storageUsed =
    (int) $stmt->fetchColumn();


/* =========================================================
   CEK QUOTA
========================================================= */

if ($storageQuota > 0) {

    $remaining =
        $storageQuota -
        $storageUsed;


    if ($fileSize > $remaining) {

        http_response_code(413);

        echo json_encode([

            'success' => false,

            'message' =>
                'Storage tidak mencukupi.',

            'storage_used' =>
                $storageUsed,

            'storage_quota' =>
                $storageQuota,

            'storage_remaining' =>
                max(
                    0,
                    $remaining
                )

        ]);

        exit;
    }

}


/* =========================================================
   SMB
========================================================= */

$uploadDir =
    '\\\\10.201.6.43\\agg\\MROS\\SERVERKU\\uploads\\';


if (!is_dir($uploadDir)) {

    http_response_code(500);

    echo json_encode([
        'success' => false,
        'message' =>
            'Folder SMB tidak dapat diakses.'
    ]);

    exit;
}


if (!is_writable($uploadDir)) {

    http_response_code(500);

    echo json_encode([
        'success' => false,
        'message' =>
            'Folder SMB tidak memiliki izin tulis.'
    ]);

    exit;
}


/* =========================================================
   TEMP FOLDER
========================================================= */

$tempDir =
    $uploadDir .
    '.chunks_' .
    $safeUploadId .
    '\\';


if (!is_dir($tempDir)) {

    if (!mkdir(
        $tempDir,
        0777,
        true
    )) {

        http_response_code(500);

        echo json_encode([
            'success' => false,
            'message' =>
                'Gagal membuat folder temporary upload.'
        ]);

        exit;
    }

}


/* =========================================================
   SIMPAN METADATA
========================================================= */

$metadata = [

    'user_id' =>
        $user_id,

    'folder_id' =>
        $folder_id,

    'filename' =>
        $filename,

    'filesize' =>
        $fileSize,

    'upload_id' =>
        $uploadId,

    'created_at' =>
        date('Y-m-d H:i:s')

];


$metadataFile =
    $tempDir .
    'metadata.json';


$metadataSaved =
    file_put_contents(

        $metadataFile,

        json_encode(
            $metadata,
            JSON_PRETTY_PRINT |
            JSON_UNESCAPED_UNICODE
        )

    );


if ($metadataSaved === false) {

    http_response_code(500);

    echo json_encode([
        'success' => false,
        'message' =>
            'Gagal menyimpan metadata upload.'
    ]);

    exit;
}


/* =========================================================
   RESPONSE
========================================================= */

echo json_encode([

    'success' =>
        true,

    'message' =>
        'Upload berhasil dimulai.',

    'upload_id' =>
        $uploadId,

    'filename' =>
        $filename,

    'filesize' =>
        $fileSize,

    'folder_id' =>
        $folder_id

]);

exit;