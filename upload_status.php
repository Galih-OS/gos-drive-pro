<?php

$tempRoot =
    __DIR__ . DIRECTORY_SEPARATOR . 'temp_uploads';


$uploadId =
    $_POST['upload_id'] ?? '';


if ($uploadId === '') {

    http_response_code(400);

    echo json_encode([

        'success' => false,

        'message' =>
            'Upload ID tidak ditemukan.'

    ]);

    exit;
}


$uploadFolder =
    $tempRoot .
    DIRECTORY_SEPARATOR .
    $uploadId;


$metaFile =
    $uploadFolder .
    DIRECTORY_SEPARATOR .
    'meta.json';


if (
    !is_dir($uploadFolder) ||
    !file_exists($metaFile)
) {

    http_response_code(404);

    echo json_encode([

        'success' => false,

        'message' =>
            'Upload session tidak ditemukan.'

    ]);

    exit;
}


$meta =
    json_decode(
        file_get_contents($metaFile),
        true
    );


/* =========================================
   VALIDASI USER
========================================= */

if (
    (int)$meta['user_id'] !==
    (int)$_SESSION['user']['id']
) {

    http_response_code(403);

    echo json_encode([

        'success' => false,

        'message' =>
            'Akses ditolak.'

    ]);

    exit;
}


/* =========================================
   CHUNK SELESAI
========================================= */

$completedChunks = [];


$totalChunks =
    (int)$meta['total_chunks'];


for (
    $i = 0;
    $i < $totalChunks;
    $i++
) {

    $chunkFile =
        $uploadFolder .
        DIRECTORY_SEPARATOR .
        'chunk_' .
        sprintf(
            '%08d',
            $i
        );


    if (file_exists($chunkFile)) {

        $completedChunks[] =
            $i;
    }
}


/* =========================================
   RESPONSE
========================================= */

echo json_encode([

    'success' => true,

    'upload_id' =>
        $uploadId,

    'filename' =>
        $meta['filename'],

    'filesize' =>
        $meta['filesize'],

    'chunk_size' =>
        $meta['chunk_size'],

    'total_chunks' =>
        $totalChunks,

    'completed_chunks' =>
        $completedChunks,

    'completed_count' =>
        count($completedChunks)

]);

exit;