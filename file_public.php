<?php

/*
|--------------------------------------------------------------------------
| GOSDRIVE - PUBLIC FILE STREAM
|--------------------------------------------------------------------------
| Digunakan untuk:
| - Preview gambar
| - Preview PDF
| - Preview video
| - Preview audio
| - File publik berdasarkan share_token
|--------------------------------------------------------------------------
*/

ini_set('display_errors', '0');
ini_set('log_errors', '1');

require 'config/db.php';


/* =========================================================
   MATIKAN OUTPUT BUFFER AGAR BINARY TIDAK RUSAK
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
   CARI FILE BERDASARKAN SHARE TOKEN
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
   FILE SIZE
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
    'html' => 'text/html; charset=UTF-8',
    'htm'  => 'text/html; charset=UTF-8',
    'css'  => 'text/css; charset=UTF-8',
    'js'   => 'application/javascript',

    /* OFFICE */
    'doc'  => 'application/msword',
    'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',

    'xls'  => 'application/vnd.ms-excel',
    'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',

    'ppt'  => 'application/vnd.ms-powerpoint',
    'pptx' => 'application/vnd.openxmlformats-officedocument.presentationml.presentation',

    /* ARCHIVE */
    'zip' => 'application/zip',
    'rar' => 'application/vnd.rar',
    '7z'  => 'application/x-7z-compressed'

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
   HEADER
========================================================= */

header('Content-Type: ' . $mime);

header(
    'Content-Length: ' . $fileSize
);

header(
    'Content-Disposition: inline; filename="' .
    str_replace(
        ['"', "\r", "\n"],
        '',
        $filename
    ) .
    '"'
);

header('X-Content-Type-Options: nosniff');

header('Accept-Ranges: bytes');

header('Cache-Control: public, max-age=3600');

header('Pragma: public');


/* =========================================================
   STREAM FILE
========================================================= */

$handle = @fopen($realPath, 'rb');


if ($handle === false) {

    http_response_code(500);

    exit('File tidak dapat dibuka.');

}


while (!feof($handle)) {

    $buffer = fread($handle, 1024 * 1024);

    if ($buffer === false) {
        break;
    }

    echo $buffer;

    flush();

}


fclose($handle);

exit;