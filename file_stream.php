<?php

require 'config/db.php';

/* =========================================
   CEK LOGIN
========================================= */
if(!isset($_SESSION['user'])){
    http_response_code(401);
    exit('Unauthorized.');

}


$user_id =
    (int)$_SESSION['user']['id'];

$id =
    isset($_GET['id'])
        ? (int)$_GET['id']
        : 0;

if($id <= 0){
    http_response_code(400);
    exit('ID tidak valid.');
}


/* =========================================
   AMBIL FILE
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
    http_response_code(404);
    exit('File tidak ditemukan.');
}


/* =========================================
   PATH FILE
========================================= */

$filePath =
    $file['filepath'] ?? '';

if(
    empty($filePath) ||
    !is_file($filePath)
){
    http_response_code(404);
    exit('File tidak ditemukan di SMB.');
}


/* =========================================
   NAMA FILE
========================================= */

$filename =
    $file['filename'] ?? basename($filePath);

$ext =
    strtolower(
        pathinfo(
            $filename,
            PATHINFO_EXTENSION
        )
    );


/* =========================================
   MIME TYPE
========================================= */

$mimeTypes = [

    /* GAMBAR */
    'jpg'  => 'image/jpeg',
    'jpeg' => 'image/jpeg',
    'jfif' => 'image/jpeg',
    'png'  => 'image/png',
    'gif'  => 'image/gif',
    'webp' => 'image/webp',
    'bmp'  => 'image/bmp',
    'svg'  => 'image/svg+xml',
    'ico'  => 'image/x-icon',

    /* PDF */
    'pdf'  => 'application/pdf',

    /* VIDEO */
    'mp4'  => 'video/mp4',
    'webm' => 'video/webm',
    'ogg'  => 'video/ogg',
    'ogv'  => 'video/ogg',

    /* AUDIO */
    'mp3'  => 'audio/mpeg',
    'wav'  => 'audio/wav',
    'm4a'  => 'audio/mp4',
    'aac'  => 'audio/aac',
    'flac' => 'audio/flac',

];


$mime =
    $mimeTypes[$ext]
    ?? 'application/octet-stream';


/* =========================================
   UKURAN FILE
========================================= */

$fileSize =
    filesize($filePath);


if($fileSize === false){

    http_response_code(500);

    exit('Gagal membaca ukuran file.');

}


/* =========================================
   BERSIHKAN OUTPUT BUFFER
========================================= */

while(ob_get_level()){

    ob_end_clean();

}


/* =========================================
   HEADER
========================================= */

header(
    'Content-Type: ' . $mime
);

header(
    'Content-Length: ' . $fileSize
);

header(
    'Content-Disposition: inline; filename="' .
    addslashes(
        basename($filename)
    ) .
    '"'
);

header(
    'X-Content-Type-Options: nosniff'
);

header(
    'Cache-Control: private, max-age=3600'
);


/* =========================================
   STREAM FILE
========================================= */

$handle =
    fopen(
        $filePath,
        'rb'
    );


if($handle === false){
    http_response_code(500);
    exit('Gagal membuka file.');
}


while(!feof($handle)){
    echo fread(
        $handle,
        8192
    );
    flush();
}

fclose($handle);

exit;