<?php

/*
|--------------------------------------------------------------------------
| GOSDRIVE - PUBLIC FILE DOWNLOAD
|--------------------------------------------------------------------------
| Download file berdasarkan share_token
|--------------------------------------------------------------------------
*/

ini_set('display_errors', '0');
ini_set('log_errors', '1');

require 'config/db.php';


/* =========================================================
   BERSIHKAN OUTPUT BUFFER
   Agar tidak ada output/warning yang merusak file
========================================================= */

while (ob_get_level() > 0) {
    ob_end_clean();
}


/* =========================================================
   TOKEN
========================================================= */

$token = isset($_GET['token'])
    ? trim((string)$_GET['token'])
    : '';


if ($token === '') {

    http_response_code(404);

    exit('File tidak ditemukan.');

}


/* =========================================================
   CARI FILE
========================================================= */

$stmt = $pdo->prepare("
    SELECT *
    FROM files
    WHERE share_token = ?
    LIMIT 1
");

$stmt->execute([
    $token
]);

$file = $stmt->fetch(PDO::FETCH_ASSOC);


if (!$file) {

    http_response_code(404);

    exit('File tidak ditemukan.');

}


/* =========================================================
   NAMA FILE
========================================================= */

$filename = isset($file['filename'])
    ? (string)$file['filename']
    : 'file';


$filename = basename($filename);


/* =========================================================
   PATH FILE
========================================================= */

$filePath = isset($file['filepath'])
    ? trim((string)$file['filepath'])
    : '';


$possiblePaths = [

    $filePath,

    __DIR__ . '/' . $filePath,

    __DIR__ . '/uploads/' . basename($filePath),

    __DIR__ . '/uploads/' . $filename

];


$realPath = null;


foreach ($possiblePaths as $path) {

    if (
        !empty($path) &&
        is_file($path) &&
        is_readable($path)
    ) {

        $realPath = $path;

        break;

    }

}


if ($realPath === null) {

    http_response_code(404);

    exit('File fisik tidak ditemukan.');

}


/* =========================================================
   UKURAN FILE
========================================================= */

$fileSize = filesize($realPath);


if ($fileSize === false) {

    http_response_code(500);

    exit('Ukuran file tidak dapat dibaca.');

}


/* =========================================================
   EXTENSION
========================================================= */

$extension = strtolower(
    pathinfo($filename, PATHINFO_EXTENSION)
);


/* =========================================================
   MIME TYPE
========================================================= */

$mimeMap = [

    /* IMAGE */
    'jpg'  => 'image/jpeg',
    'jpeg' => 'image/jpeg',
    'jfif' => 'image/jpeg',
    'jpe'  => 'image/jpeg',

    'png'  => 'image/png',
    'gif'  => 'image/gif',
    'webp' => 'image/webp',
    'bmp'  => 'image/bmp',
    'svg'  => 'image/svg+xml',
    'ico'  => 'image/x-icon',
    'tif'  => 'image/tiff',
    'tiff' => 'image/tiff',

    /* PDF */
    'pdf'  => 'application/pdf',

    /* VIDEO */
    'mp4'  => 'video/mp4',
    'webm' => 'video/webm',
    'ogv'  => 'video/ogg',
    'ogg'  => 'video/ogg',
    'mov'  => 'video/quicktime',
    'avi'  => 'video/x-msvideo',
    'mkv'  => 'video/x-matroska',

    /* AUDIO */
    'mp3'  => 'audio/mpeg',
    'wav'  => 'audio/wav',
    'm4a'  => 'audio/mp4',
    'aac'  => 'audio/aac',
    'flac' => 'audio/flac',

    /* TEXT */
    'txt'  => 'text/plain; charset=UTF-8',
    'csv'  => 'text/csv; charset=UTF-8',

    /* OFFICE */
    'doc'  => 'application/msword',

    'docx' =>
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',

    'xls' =>
        'application/vnd.ms-excel',

    'xlsx' =>
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',

    'ppt' =>
        'application/vnd.ms-powerpoint',

    'pptx' =>
        'application/vnd.openxmlformats-officedocument.presentationml.presentation',

    /* ARCHIVE */
    'zip' => 'application/zip',

    'rar' =>
        'application/vnd.rar',

    '7z' =>
        'application/x-7z-compressed'

];


$mime = $mimeMap[$extension] ?? 'application/octet-stream';


/* =========================================================
   FALLBACK MIME
========================================================= */

if (
    $mime === 'application/octet-stream' &&
    function_exists('mime_content_type')
) {

    $detectedMime = @mime_content_type($realPath);

    if (
        !empty($detectedMime) &&
        is_string($detectedMime)
    ) {

        $mime = $detectedMime;

    }

}


/* =========================================================
   BERSIHKAN NAMA FILE UNTUK HEADER
========================================================= */

$safeFilename = str_replace(
    ['"', "\r", "\n"],
    '',
    $filename
);


/* =========================================================
   HEADER DOWNLOAD
========================================================= */

header('Content-Type: ' . $mime);

header(
    'Content-Length: ' . $fileSize
);

header(
    'Content-Disposition: attachment; filename="' .
    $safeFilename .
    '"'
);

header('Content-Transfer-Encoding: binary');

header('X-Content-Type-Options: nosniff');

header('Content-Description: File Transfer');

header('Cache-Control: no-cache, no-store, must-revalidate');

header('Pragma: no-cache');

header('Expires: 0');


/* =========================================================
   STREAM FILE
========================================================= */

$handle = @fopen($realPath, 'rb');


if ($handle === false) {

    http_response_code(500);

    exit('File tidak dapat dibuka.');

}


while (!feof($handle)) {

    $buffer = fread(
        $handle,
        1024 * 1024
    );

    if ($buffer === false) {

        break;

    }

    echo $buffer;

    flush();

}


fclose($handle);

exit;