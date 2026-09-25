<?php

declare(strict_types=1);

/* =========================================================
   GOSDRIVE - SHARE FOLDER
   =========================================================
   POST :
       Membuat / mengambil token share folder

   GET :
       Download folder yang dibagikan sebagai ZIP

   ========================================================= */


/* =========================================================
   MATIKAN OUTPUT ERROR KE BROWSER
   ========================================================= */

ini_set('display_errors', '0');
ini_set('display_startup_errors', '0');

error_reporting(E_ALL);

set_time_limit(0);


/* =========================================================
   START OUTPUT BUFFER
   =========================================================
   Tujuannya untuk mencegah BOM / whitespace / output
   dari file lain ikut masuk ke binary ZIP.
========================================================= */

if (ob_get_level() === 0) {
    ob_start();
}


/* =========================================================
   DATABASE
========================================================= */

require 'config/db.php';


/* =========================================================
   SESSION
========================================================= */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}


/* =========================================================
   NO CACHE
========================================================= */

header(
    'Cache-Control: no-store, no-cache, must-revalidate, max-age=0'
);

header(
    'Pragma: no-cache'
);

header(
    'Expires: 0'
);


/* =========================================================
   FUNGSI BERSIHKAN OUTPUT BUFFER
========================================================= */

function clearOutputBuffers(): void
{
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
}


/* =========================================================
   FUNGSI JSON RESPONSE
========================================================= */

function jsonResponse(
    bool $success,
    string $message = '',
    array $extra = [],
    int $statusCode = 200
): never {

    clearOutputBuffers();

    http_response_code($statusCode);

    header(
        'Content-Type: application/json; charset=utf-8'
    );

    echo json_encode(
        array_merge(
            [
                'success' => $success,
                'message' => $message
            ],
            $extra
        ),
        JSON_UNESCAPED_UNICODE |
        JSON_UNESCAPED_SLASHES
    );

    exit;
}


/* =========================================================
   FUNGSI ROOT URL
========================================================= */

function getBaseUrl(): string
{
    $https =
        (
            isset($_SERVER['HTTPS']) &&
            $_SERVER['HTTPS'] !== 'off'
        )
        ||
        (
            isset($_SERVER['HTTP_X_FORWARDED_PROTO']) &&
            strtolower(
                (string)$_SERVER['HTTP_X_FORWARDED_PROTO']
            ) === 'https'
        );


    $scheme =
        $https
        ? 'https'
        : 'http';


    $host =
        $_SERVER['HTTP_HOST']
        ?? 'localhost';


    $script =
        $_SERVER['SCRIPT_NAME']
        ?? '/share_folder.php';


    $directory =
        str_replace(
            '\\',
            '/',
            dirname($script)
        );


    if (
        $directory === '/' ||
        $directory === '.' ||
        $directory === '\\'
    ) {

        $directory = '';

    }


    return
        $scheme .
        '://' .
        $host .
        rtrim(
            $directory,
            '/'
        );
}


/* =========================================================
   FUNGSI NAMA FILE AMAN
========================================================= */

function safeFileName(
    string $name
): string {

    $name = basename($name);


    $name =
        preg_replace(
            '/[<>:"\/\\\\|?*\x00-\x1F]/u',
            '_',
            $name
        );


    $name =
        trim(
            (string)$name,
            ". "
        );


    if ($name === '') {
        $name = 'folder';
    }


    return $name;
}


/* =========================================================
   TOKEN
========================================================= */

$token =
    trim(
        (string)(
            $_GET['token']
            ?? ''
        )
    );


/* =========================================================
   =========================================================
   POST
   MEMBUAT LINK SHARE
   =========================================================
   ========================================================= */

if (
    $_SERVER['REQUEST_METHOD'] === 'POST'
) {

    $requestedWith =
        $_SERVER['HTTP_X_REQUESTED_WITH']
        ?? '';


    if (
        strtolower($requestedWith)
        !==
        'xmlhttprequest'
    ) {

        jsonResponse(
            false,
            'Permintaan tidak valid.',
            [],
            400
        );

    }


    if (
        !isset($_SESSION['user']) ||
        !is_array($_SESSION['user']) ||
        !isset($_SESSION['user']['id'])
    ) {

        jsonResponse(
            false,
            'Anda belum login.',
            [],
            401
        );

    }


    $userId =
        (int)$_SESSION['user']['id'];


    $folderId =
        isset($_POST['folder_id'])
        ? (int)$_POST['folder_id']
        : 0;


    if ($folderId <= 0) {

        jsonResponse(
            false,
            'ID folder tidak valid.',
            [],
            400
        );

    }


    $stmt =
        $pdo->prepare("
            SELECT
                id,
                user_id,
                parent_id,
                folder_name,
                share_token
            FROM folders
            WHERE id = ?
            AND user_id = ?
            LIMIT 1
        ");


    $stmt->execute([
        $folderId,
        $userId
    ]);


    $folder =
        $stmt->fetch(
            PDO::FETCH_ASSOC
        );


    if (!$folder) {

        jsonResponse(
            false,
            'Folder tidak ditemukan atau bukan milik Anda.',
            [],
            404
        );

    }


    $shareToken =
        trim(
            (string)(
                $folder['share_token']
                ?? ''
            )
        );


    if ($shareToken === '') {

        $shareToken =
            bin2hex(
                random_bytes(32)
            );


        $stmtToken =
            $pdo->prepare("
                UPDATE folders
                SET share_token = ?
                WHERE id = ?
                AND user_id = ?
            ");


        $stmtToken->execute([
            $shareToken,
            $folderId,
            $userId
        ]);

    }


    $baseUrl =
        getBaseUrl();


    $shareUrl =
        $baseUrl .
        '/share_folder.php?token=' .
        rawurlencode(
            $shareToken
        );


    jsonResponse(
        true,
        'Link share folder berhasil dibuat.',
        [
            'url' =>
                $shareUrl,

            'share_url' =>
                $shareUrl,

            'folder_id' =>
                $folderId,

            'folder_name' =>
                $folder['folder_name']
        ]
    );
}


/* =========================================================
   HANYA GET
========================================================= */

if (
    $_SERVER['REQUEST_METHOD'] !== 'GET'
) {

    jsonResponse(
        false,
        'Method request tidak diperbolehkan.',
        [],
        405
    );

}


/* =========================================================
   TOKEN KOSONG
========================================================= */

if ($token === '') {

    clearOutputBuffers();

    http_response_code(400);

    header(
        'Content-Type: text/html; charset=utf-8'
    );

    ?>

<!DOCTYPE html>
<html lang="id">

<head>

<meta charset="UTF-8">

<meta
    name="viewport"
    content="width=device-width, initial-scale=1.0"
>

<title>Link Tidak Valid - GosDrive</title>

<style>

* {
    box-sizing: border-box;
}

body {

    margin: 0;

    min-height: 100vh;

    display: flex;

    align-items: center;

    justify-content: center;

    font-family: Arial, sans-serif;

    background:
        linear-gradient(
            135deg,
            #111827,
            #312e81
        );

    color: white;
}

.box {

    width: 400px;

    max-width:
        calc(100% - 30px);

    padding: 40px;

    text-align: center;

    border-radius: 20px;

    background:
        rgba(
            255,
            255,
            255,
            .10
        );

    backdrop-filter:
        blur(15px);
}

.icon {

    font-size: 55px;

    margin-bottom: 20px;
}

h2 {
    margin-bottom: 10px;
}

p {

    opacity: .75;

    line-height: 1.6;
}

</style>

</head>

<body>

<div class="box">

    <div class="icon">
        🔗
    </div>

    <h2>
        Link Tidak Valid
    </h2>

    <p>
        Link share folder tidak ditemukan
        atau tidak lengkap.
    </p>

</div>

</body>

</html>

<?php

    exit;
}


/* =========================================================
   CARI ROOT FOLDER
========================================================= */

$stmt =
    $pdo->prepare("
        SELECT
            id,
            user_id,
            parent_id,
            folder_name,
            share_token
        FROM folders
        WHERE share_token = ?
        LIMIT 1
    ");


$stmt->execute([
    $token
]);


$rootFolder =
    $stmt->fetch(
        PDO::FETCH_ASSOC
    );


if (!$rootFolder) {

    clearOutputBuffers();

    http_response_code(404);

    header(
        'Content-Type: text/html; charset=utf-8'
    );

    ?>

<!DOCTYPE html>
<html lang="id">

<head>

<meta charset="UTF-8">

<meta
    name="viewport"
    content="width=device-width, initial-scale=1.0"
>

<title>Folder Tidak Ditemukan - GosDrive</title>

<style>

* {
    box-sizing: border-box;
}

body {

    margin: 0;

    min-height: 100vh;

    display: flex;

    align-items: center;

    justify-content: center;

    font-family: Arial, sans-serif;

    background:
        linear-gradient(
            135deg,
            #111827,
            #312e81
        );

    color: white;
}

.box {

    width: 400px;

    max-width:
        calc(100% - 30px);

    padding: 40px;

    text-align: center;

    border-radius: 20px;

    background:
        rgba(
            255,
            255,
            255,
            .10
        );

    backdrop-filter:
        blur(15px);
}

.icon {

    font-size: 55px;

    margin-bottom: 20px;
}

h2 {
    margin-bottom: 10px;
}

p {

    opacity: .75;

    line-height: 1.6;
}

</style>

</head>

<body>

<div class="box">

    <div class="icon">
        📁
    </div>

    <h2>
        Folder Tidak Ditemukan
    </h2>

    <p>
        Link share mungkin sudah tidak
        berlaku atau folder telah dihapus.
    </p>

</div>

</body>

</html>

<?php

    exit;
}


/* =========================================================
   CEK ZIP
========================================================= */

if (
    !class_exists('ZipArchive')
) {

    clearOutputBuffers();

    http_response_code(500);

    header(
        'Content-Type: text/html; charset=utf-8'
    );

    echo '
        <h2>Fitur ZIP tidak tersedia.</h2>
        <p>Extension PHP ZipArchive belum aktif di XAMPP.</p>
    ';

    exit;
}


/* =========================================================
   ROOT ID
========================================================= */

$rootFolderId =
    (int)$rootFolder['id'];

$rootUserId =
    (int)$rootFolder['user_id'];


/* =========================================================
   KUMPULKAN SEMUA FOLDER
========================================================= */

$folderIds = [
    $rootFolderId
];


$processed = [];


$index = 0;


while (
    isset($folderIds[$index])
) {

    $currentFolderId =
        (int)$folderIds[$index];


    $index++;


    if (
        isset(
            $processed[$currentFolderId]
        )
    ) {

        continue;
    }


    $processed[$currentFolderId] =
        true;


    $stmtChildren =
        $pdo->prepare("
            SELECT
                id
            FROM folders
            WHERE user_id = ?
            AND parent_id = ?
            ORDER BY folder_name ASC
        ");


    $stmtChildren->execute([
        $rootUserId,
        $currentFolderId
    ]);


    $children =
        $stmtChildren->fetchAll(
            PDO::FETCH_COLUMN
        );


    foreach (
        $children as $childId
    ) {

        $childId =
            (int)$childId;


        if (
            !isset(
                $processed[$childId]
            )
            &&
            !in_array(
                $childId,
                $folderIds,
                true
            )
        ) {

            $folderIds[] =
                $childId;
        }
    }
}


/* =========================================================
   AMBIL FILE
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
        [
            $rootUserId
        ],
        $folderIds
    );


$stmtFiles =
    $pdo->prepare("
        SELECT
            id,
            filename,
            filepath,
            folder_id
        FROM files
        WHERE user_id = ?
        AND folder_id IN ($placeholders)
        ORDER BY filename ASC
    ");


$stmtFiles->execute(
    $params
);


$files =
    $stmtFiles->fetchAll(
        PDO::FETCH_ASSOC
    );


/* =========================================================
   TEMP DIRECTORY
========================================================= */

$tempDirectory =
    sys_get_temp_dir();


if (
    !is_dir($tempDirectory) ||
    !is_writable($tempDirectory)
) {

    clearOutputBuffers();

    http_response_code(500);

    header(
        'Content-Type: text/plain; charset=utf-8'
    );

    exit(
        'Folder temporary PHP tidak dapat ditulis.'
    );
}


/* =========================================================
   TEMP ZIP
========================================================= */

$zipFile =
    tempnam(
        $tempDirectory,
        'gosdrive_'
    );


if ($zipFile === false) {

    clearOutputBuffers();

    http_response_code(500);

    exit(
        'Tidak dapat membuat file sementara.'
    );
}


/* =========================================================
   BUAT ZIP
========================================================= */

$zip =
    new ZipArchive();


$openResult =
    $zip->open(
        $zipFile,
        ZipArchive::CREATE |
        ZipArchive::OVERWRITE
    );


if (
    $openResult !== true
) {

    @unlink($zipFile);

    clearOutputBuffers();

    http_response_code(500);

    exit(
        'Tidak dapat membuka file ZIP. Error code: ' .
        (string)$openResult
    );
}


/* =========================================================
   ROOT FOLDER
========================================================= */

$rootFolderName =
    safeFileName(
        (string)$rootFolder['folder_name']
    );


/* =========================================================
   CACHE PATH FOLDER
========================================================= */

$folderPathCache = [
    $rootFolderId =>
        $rootFolderName
];


/* =========================================================
   MASUKKAN FILE KE ZIP
========================================================= */

$addedFiles = 0;

$failedFiles = [];


foreach (
    $files as $file
) {

    $filepath =
        trim(
            (string)(
                $file['filepath']
                ?? ''
            )
        );


    if ($filepath === '') {

        $failedFiles[] =
            'Path kosong: ' .
            (string)$file['filename'];

        continue;
    }


    /*
     * Gunakan path asli terlebih dahulu.
     * Ini penting untuk SMB/network path.
     */

    $realPath =
        $filepath;


    /*
     * Jika path asli tidak ditemukan,
     * coba normalisasi Windows.
     */

    if (
        !is_file($realPath)
    ) {

        $alternativePath =
            str_replace(
                '/',
                '\\',
                $filepath
            );


        if (
            is_file($alternativePath)
        ) {

            $realPath =
                $alternativePath;

        }

    }


    /*
     * File harus tersedia dan readable.
     */

    if (
        !is_file($realPath) ||
        !is_readable($realPath)
    ) {

        $failedFiles[] =
            (string)$file['filename'];

        continue;
    }


    $fileName =
        safeFileName(
            (string)$file['filename']
        );


    $folderId =
        (int)$file['folder_id'];


    /* =====================================================
       CARI PATH FOLDER
    ===================================================== */

    if (
        isset(
            $folderPathCache[$folderId]
        )
    ) {

        $relativeFolder =
            $folderPathCache[$folderId];

    } else {

        $chain = [];

        $currentId =
            $folderId;

        $guard = 0;


        while (
            $currentId !== $rootFolderId &&
            $currentId > 0 &&
            $guard < 100
        ) {

            $guard++;


            $stmtParent =
                $pdo->prepare("
                    SELECT
                        id,
                        parent_id,
                        folder_name
                    FROM folders
                    WHERE id = ?
                    AND user_id = ?
                    LIMIT 1
                ");


            $stmtParent->execute([
                $currentId,
                $rootUserId
            ]);


            $parentFolder =
                $stmtParent->fetch(
                    PDO::FETCH_ASSOC
                );


            if (!$parentFolder) {
                break;
            }


            $chain[] =
                safeFileName(
                    (string)$parentFolder['folder_name']
                );


            $currentId =
                (int)(
                    $parentFolder['parent_id']
                    ?? 0
                );
        }


        /*
         * Pastikan benar-benar kembali
         * ke root folder yang dibagikan.
         */

        if (
            $currentId === $rootFolderId
        ) {

            $chain[] =
                $rootFolderName;


            $chain =
                array_reverse(
                    $chain
                );


            $relativeFolder =
                implode(
                    '/',
                    $chain
                );

        } else {

            /*
             * Jika struktur folder tidak valid,
             * masukkan ke root ZIP.
             */

            $relativeFolder =
                $rootFolderName;
        }


        $folderPathCache[$folderId] =
            $relativeFolder;
    }


    /* =====================================================
       PATH FILE DI ZIP
    ===================================================== */

    $zipPath =
        trim(
            $relativeFolder .
            '/' .
            $fileName,
            '/'
        );


    /* =====================================================
       TAMBAHKAN FILE
    ===================================================== */

    $added =
        $zip->addFile(
            $realPath,
            $zipPath
        );


    if (!$added) {

        $failedFiles[] =
            (string)$file['filename'];

        continue;
    }


    $addedFiles++;
}


/* =========================================================
   TUTUP ZIP DAN PERIKSA
========================================================= */

$closeResult =
    $zip->close();


if (!$closeResult) {

    @unlink($zipFile);

    clearOutputBuffers();

    http_response_code(500);

    header(
        'Content-Type: text/plain; charset=utf-8'
    );

    exit(
        'Gagal menyelesaikan file ZIP.'
    );
}


/* =========================================================
   PERIKSA FILE ZIP
========================================================= */

if (
    !is_file($zipFile)
) {

    clearOutputBuffers();

    http_response_code(500);

    exit(
        'File ZIP tidak berhasil dibuat.'
    );
}


$zipSize =
    filesize($zipFile);


if (
    $zipSize === false ||
    $zipSize < 22
) {

    @unlink($zipFile);

    clearOutputBuffers();

    http_response_code(500);

    exit(
        'File ZIP kosong atau rusak.'
    );
}


/* =========================================================
   NAMA DOWNLOAD
========================================================= */

$downloadName =
    safeFileName(
        (string)$rootFolder['folder_name']
    );


$downloadName .= '.zip';


/* =========================================================
   BERSIHKAN SEMUA OUTPUT
   =========================================================
   INI SANGAT PENTING.

   Jika sebelumnya ada:
   - warning PHP
   - whitespace
   - BOM
   - output dari config/db.php
   - notice

   semuanya dibuang sebelum ZIP dikirim.
========================================================= */

clearOutputBuffers();


/* =========================================================
   HEADER ZIP
========================================================= */

http_response_code(200);


header(
    'Content-Type: application/zip'
);


header(
    'Content-Disposition: attachment; filename="' .
    $downloadName .
    '"'
);


header(
    'Content-Length: ' .
    (string)$zipSize
);


header(
    'Content-Transfer-Encoding: binary'
);


header(
    'Accept-Ranges: bytes'
);


header(
    'X-Content-Type-Options: nosniff'
);


header(
    'Cache-Control: no-store, no-cache, must-revalidate'
);


header(
    'Pragma: no-cache'
);


/* =========================================================
   KIRIM FILE ZIP
========================================================= */

$handle =
    fopen(
        $zipFile,
        'rb'
    );


if ($handle === false) {

    @unlink($zipFile);

    exit;
}


/*
 * Stream ZIP sebagai binary.
 */

while (!feof($handle)) {

    $buffer =
        fread(
            $handle,
            1024 * 1024
        );


    if ($buffer === false) {
        break;
    }


    echo $buffer;


    /*
     * Pastikan data keluar.
     */

    flush();
}


fclose($handle);


/* =========================================================
   HAPUS TEMP ZIP
========================================================= */

@unlink($zipFile);


exit;