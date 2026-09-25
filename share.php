<?php

require 'config/db.php';


/* =========================================
   AMBIL TOKEN
========================================= */

$token = isset($_GET['token'])
    ? trim($_GET['token'])
    : '';


/* =========================================
   VALIDASI TOKEN
========================================= */

if ($token === '') {

    http_response_code(404);

    die('Link file tidak valid.');

}


/* =========================================
   CARI FILE
========================================= */

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


/* =========================================
   FILE TIDAK DITEMUKAN
========================================= */

if (!$file) {

    http_response_code(404);

    die('File tidak ditemukan atau link sudah tidak berlaku.');

}


$filename = $file['filename'];

$ext = strtolower(
    pathinfo(
        $filename,
        PATHINFO_EXTENSION
    )
);


/* =========================================
   PATH FILE
========================================= */

$filePath = '';

if (isset($file['filepath'])) {

    $filePath = $file['filepath'];

}


/*
   Jika filepath ternyata hanya menyimpan
   relative path, sesuaikan bagian ini.
*/

if (!file_exists($filePath)) {

    /*
       Coba beberapa kemungkinan lokasi
    */

    $possiblePaths = [

        $filePath,

        __DIR__ . '/' . $filePath,

        __DIR__ . '/uploads/' . basename($filePath),

        __DIR__ . '/uploads/' . $filename

    ];


    foreach ($possiblePaths as $path) {

        if (
            !empty($path) &&
            file_exists($path)
        ) {

            $filePath = $path;

            break;

        }

    }

}


/* =========================================
   CEK FILE FISIK
========================================= */

if (
    empty($filePath) ||
    !file_exists($filePath)
) {

    http_response_code(404);

    die('File fisik tidak ditemukan di server.');

}


/* =========================================
   MIME TYPE
========================================= */

$mime = 'application/octet-stream';


if (function_exists('mime_content_type')) {

    $mime =
        mime_content_type($filePath);

}


?>

<!DOCTYPE html>

<html lang="id">

<head>

<meta charset="UTF-8">

<meta
    name="viewport"
    content="width=device-width, initial-scale=1"
>

<title>
    <?= htmlspecialchars($filename) ?> - GosDrive
</title>


<link
    href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css"
    rel="stylesheet"
>


<style>

body{

    background:#f4f6f9;

    font-family:
        'Segoe UI',
        sans-serif;

}


.preview-box{

    max-width:1200px;

    margin:40px auto;

    background:white;

    border-radius:18px;

    padding:25px;

    box-shadow:
        0 5px 25px
        rgba(0,0,0,.08);

}


.file-title{

    font-weight:600;

    word-break:break-word;

}


.preview-area{

    margin-top:20px;

    min-height:500px;

    background:#f8f9fa;

    border-radius:12px;

    display:flex;

    justify-content:center;

    align-items:center;

    overflow:hidden;

}


.preview-area iframe{

    width:100%;

    height:700px;

    border:0;

}


.preview-area img{

    max-width:100%;

    max-height:700px;

    object-fit:contain;

}


</style>

</head>


<body>


<div class="preview-box">


    <div
        class="
            d-flex
            justify-content-between
            align-items-center
            gap-3
            flex-wrap
        "
    >


        <div>

            <div class="text-muted small">
                GosDrive
            </div>

            <div class="file-title">

                <?= htmlspecialchars(
                    $filename
                ) ?>

            </div>

        </div>


        <a
            href="download_public.php?token=<?= urlencode($token) ?>"
            class="btn btn-primary"
        >

            <i class="fa fa-download"></i>

            Download

        </a>


    </div>


    <div class="preview-area">


        <?php if ($ext === 'pdf'): ?>


            <iframe
                src="file_public.php?token=<?= urlencode($token) ?>"
            ></iframe>


        <?php elseif (
            in_array(
                $ext,
                [
                    'jpg',
                    'jpeg',
                    'png',
                    'gif',
                    'webp'
                ]
            )
        ): ?>


            <img
                src="file_public.php?token=<?= urlencode($token) ?>"
                alt="<?= htmlspecialchars($filename) ?>"
            >


        <?php elseif (
            in_array(
                $ext,
                [
                    'mp4',
                    'webm',
                    'ogg'
                ]
            )
        ): ?>


            <video
                controls
                style="
                    max-width:100%;
                    max-height:700px;
                "
            >

                <source
                    src="file_public.php?token=<?= urlencode($token) ?>"
                    type="<?= htmlspecialchars($mime) ?>"
                >

            </video>


        <?php else: ?>


            <div class="text-center">

                <i
                    class="
                        fa
                        fa-file
                        fa-4x
                        text-secondary
                        mb-3
                    "
                ></i>

                <p>
                    File ini tidak dapat dipreview.
                </p>

                <a
                    href="download_public.php?token=<?= urlencode($token) ?>"
                    class="btn btn-primary"
                >

                    Download File

                </a>

            </div>


        <?php endif; ?>


    </div>


</div>


</body>

</html>