<?php

require 'config/db.php';

/*
|--------------------------------------------------------------------------
| CEK LOGIN
|--------------------------------------------------------------------------
*/

if (!isset($_SESSION['user'])) {
    http_response_code(401);
    exit('Anda belum login.');
}

$user_id = (int) $_SESSION['user']['id'];


/*
|--------------------------------------------------------------------------
| CEK ID
|--------------------------------------------------------------------------
*/

$id = isset($_GET['id'])
    ? (int) $_GET['id']
    : 0;

if ($id <= 0) {
    http_response_code(400);
    exit('ID file tidak valid.');
}


/*
|--------------------------------------------------------------------------
| AMBIL DATA FILE
|--------------------------------------------------------------------------
*/

$stmt = $pdo->prepare("
    SELECT
        id,
        user_id,
        filename,
        filepath
    FROM files
    WHERE id = ?
      AND user_id = ?
    LIMIT 1
");

$stmt->execute([
    $id,
    $user_id
]);

$file = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$file) {
    http_response_code(404);
    exit('File tidak ditemukan.');
}


/*
|--------------------------------------------------------------------------
| PATH FILE FISIK
|--------------------------------------------------------------------------
*/

$filePath = trim($file['filepath'] ?? '');

if ($filePath === '') {
    http_response_code(404);
    exit('Path file kosong.');
}

if (!is_file($filePath)) {
    http_response_code(404);
    exit('File fisik tidak ditemukan di server SMB.');
}


/*
|--------------------------------------------------------------------------
| INFORMASI FILE
|--------------------------------------------------------------------------
*/

$filename = basename($file['filename'] ?? 'download');

if ($filename === '') {
    $filename = 'download';
}

$fileSize = filesize($filePath);

if ($fileSize === false) {
    http_response_code(500);
    exit('Ukuran file tidak dapat dibaca.');
}


/*
|--------------------------------------------------------------------------
| DETEKSI MIME DARI ISI FILE
|--------------------------------------------------------------------------
*/

$mime = 'application/octet-stream';

if (class_exists('finfo')) {

    $finfo = new finfo(FILEINFO_MIME_TYPE);

    $detectedMime = $finfo->file($filePath);

    if ($detectedMime !== false && $detectedMime !== '') {
        $mime = $detectedMime;
    }

} elseif (function_exists('mime_content_type')) {

    $detectedMime = mime_content_type($filePath);

    if ($detectedMime) {
        $mime = $detectedMime;
    }
}


/*
|--------------------------------------------------------------------------
| BERSIHKAN OUTPUT BUFFER
|--------------------------------------------------------------------------
|
| Penting:
| Jangan sampai ada HTML / whitespace / warning PHP
| yang ikut masuk ke dalam file hasil download.
|
*/

while (ob_get_level() > 0) {
    ob_end_clean();
}


/*
|--------------------------------------------------------------------------
| HEADER DOWNLOAD
|--------------------------------------------------------------------------
*/

header('Content-Description: File Transfer');

header('Content-Type: ' . $mime);

header(
    'Content-Disposition: attachment; filename="' .
    addcslashes($filename, '"\\') .
    '"'
);

header('Content-Length: ' . $fileSize);

header('Content-Transfer-Encoding: binary');

header('Cache-Control: private, no-cache, no-store, must-revalidate');

header('Pragma: public');

header('Expires: 0');


/*
|--------------------------------------------------------------------------
| KIRIM FILE
|--------------------------------------------------------------------------
*/

$handle = fopen($filePath, 'rb');

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