<?php

require 'config/db.php';

header('Content-Type: application/json; charset=utf-8');


/* =========================================
   CEK LOGIN
========================================= */

if(!isset($_SESSION['user'])){

    http_response_code(401);

    echo json_encode([
        'success' => false,
        'message' => 'Anda belum login.'
    ]);

    exit;
}


$user_id =
    (int)$_SESSION['user']['id'];


/* =========================================
   DATA UPLOAD
========================================= */

$uploadId =
    $_POST['upload_id'] ?? '';


$chunkIndex =
    (int)($_POST['chunk_index'] ?? -1);


/*
|--------------------------------------------------------------------------
| folder_id
|--------------------------------------------------------------------------
| Untuk tahap chunk sebenarnya tidak wajib.
| Folder tujuan akan diproses pada upload_complete.php.
*/

$folderId =
    isset($_POST['folder_id']) &&
    $_POST['folder_id'] !== ''
        ? (int)$_POST['folder_id']
        : null;


if(
    $uploadId === '' ||
    $chunkIndex < 0
){

    http_response_code(400);

    echo json_encode([
        'success' => false,
        'message' =>
            'Data chunk tidak lengkap.'
    ]);

    exit;
}


/* =========================================
   CEK CHUNK
========================================= */

if(
    !isset($_FILES['chunk']) ||
    $_FILES['chunk']['error'] !== UPLOAD_ERR_OK
){

    http_response_code(400);

    echo json_encode([
        'success' => false,
        'message' =>
            'Chunk tidak diterima oleh PHP.'
    ]);

    exit;
}


$chunk =
    $_FILES['chunk'];


/* =========================================
   VALIDASI UKURAN CHUNK
========================================= */

if(
    !isset($chunk['size']) ||
    $chunk['size'] <= 0
){

    http_response_code(400);

    echo json_encode([
        'success' => false,
        'message' =>
            'Ukuran chunk tidak valid.'
    ]);

    exit;
}


/* =========================================
   SMB
========================================= */

$uploadDir =
    '\\\\10.201.6.43\\agg\\MROS\\SERVERKU\\uploads\\';


/* =========================================
   AMANKAN UPLOAD ID
========================================= */

$safeUploadId =
    preg_replace(
        '/[^a-zA-Z0-9_-]/',
        '',
        $uploadId
    );


if($safeUploadId === ''){

    http_response_code(400);

    echo json_encode([
        'success' => false,
        'message' =>
            'Upload ID tidak valid.'
    ]);

    exit;
}


/* =========================================
   TEMP FOLDER
========================================= */

$tempDir =
    $uploadDir .
    '.chunks_' .
    $safeUploadId .
    '\\';


/* =========================================
   CEK TEMP FOLDER
========================================= */

if(!is_dir($tempDir)){

    http_response_code(404);

    echo json_encode([
        'success' => false,
        'message' =>
            'Session upload tidak ditemukan.'
    ]);

    exit;
}


/* =========================================
   CEK KEPEMILIKAN SESSION
========================================= */

/*
|--------------------------------------------------------------------------
| File metadata dibuat oleh upload_init.php.
| Kita gunakan metadata tersebut untuk memastikan
| session upload milik user yang sedang login.
|--------------------------------------------------------------------------
*/

$metaFile =
    $tempDir .
    'metadata.json';


if(!file_exists($metaFile)){

    http_response_code(404);

    echo json_encode([
        'success' => false,
        'message' =>
            'Metadata upload tidak ditemukan.'
    ]);

    exit;
}


$metaContent =
    file_get_contents(
        $metaFile
    );


$meta =
    json_decode(
        $metaContent,
        true
    );


if(
    !is_array($meta) ||
    !isset($meta['user_id'])
){

    http_response_code(400);

    echo json_encode([
        'success' => false,
        'message' =>
            'Metadata upload tidak valid.'
    ]);

    exit;
}


if(
    (int)$meta['user_id']
    !==
    $user_id
){

    http_response_code(403);

    echo json_encode([
        'success' => false,
        'message' =>
            'Session upload bukan milik user ini.'
    ]);

    exit;
}


/* =========================================
   NAMA CHUNK
========================================= */

$chunkFile =
    $tempDir .
    'chunk_' .
    $chunkIndex .
    '.part';


/* =========================================
   PINDAHKAN CHUNK
========================================= */

if(
    !move_uploaded_file(
        $chunk['tmp_name'],
        $chunkFile
    )
){

    http_response_code(500);

    echo json_encode([
        'success' => false,
        'message' =>
            'Gagal menyimpan chunk ke SMB.'
    ]);

    exit;
}


/* =========================================
   CEK FILE
========================================= */

if(!file_exists($chunkFile)){

    http_response_code(500);

    echo json_encode([
        'success' => false,
        'message' =>
            'Chunk tidak ditemukan setelah upload.'
    ]);

    exit;
}


/* =========================================
   HASIL
========================================= */

echo json_encode([

    'success' => true,

    'message' =>
        'Chunk berhasil diterima.',

    'upload_id' =>
        $uploadId,

    'chunk_index' =>
        $chunkIndex,

    'size' =>
        filesize($chunkFile),

    'folder_id' =>
        $folderId

]);

exit;