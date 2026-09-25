<?php

require 'config/db.php';

if(!isset($_SESSION['user'])){

    header("Location: login.php");
    exit;

}


$user_id =
    (int)$_SESSION['user']['id'];


$id =
    isset($_GET['id'])
        ? (int)$_GET['id']
        : 0;


if($id <= 0){

    exit("ID file tidak valid.");

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

    exit("File tidak ditemukan.");

}


/* =========================================
   PATH SMB
========================================= */

$filePath =
    $file['filepath'] ?? '';


if(
    empty($filePath) ||
    !file_exists($filePath)
){

    exit(
        "File fisik tidak ditemukan di server SMB."
    );

}


$filename =
    $file['filename'];


$ext =
    strtolower(
        pathinfo(
            $filename,
            PATHINFO_EXTENSION
        )
    );


/* =========================================
   MIME
========================================= */

$mime =
    'application/octet-stream';


if(function_exists('mime_content_type')){

    $detectedMime =
        mime_content_type(
            $filePath
        );

    if($detectedMime){

        $mime =
            $detectedMime;

    }

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
<?= htmlspecialchars($filename) ?>
</title>

<style>

*{
    box-sizing:border-box;
}

body{

    margin:0;

    background:#111;

    color:white;

    font-family:
        'Segoe UI',
        sans-serif;

}

.header{

    padding:15px 20px;

    background:#1b1b1b;

    display:flex;

    justify-content:space-between;

    align-items:center;

    gap:15px;

}

.filename{

    font-weight:600;

    overflow:hidden;

    text-overflow:ellipsis;

    white-space:nowrap;

}

.btn{

    background:#0d6efd;

    color:white;

    text-decoration:none;

    padding:8px 14px;

    border-radius:8px;

}

.preview{

    width:100%;

    height:calc(100vh - 70px);

    display:flex;

    justify-content:center;

    align-items:center;

    padding:15px;

}

.preview img{

    max-width:100%;

    max-height:100%;

    object-fit:contain;

}

.preview iframe{

    width:100%;

    height:100%;

    border:0;

    background:white;

}

.preview video{

    max-width:100%;

    max-height:100%;

}

.preview audio{

    width:80%;

}

.message{

    text-align:center;

    color:#ddd;

}

</style>

</head>

<body>


<div class="header">

    <div class="filename">

        <?= htmlspecialchars($filename) ?>

    </div>

    <a
        class="btn"
        href="download.php?id=<?= $id ?>"
    >
        Download
    </a>

</div>


<div class="preview">


<?php

/* ============================
   GAMBAR
============================ */

if(
    in_array(
        $ext,
        [
            'jpg',
            'jpeg',
            'jfif',
            'png',
            'gif',
            'webp'
        ]
    )
){

?>

    <img
        src="file_stream.php?id=<?= $id ?>"
        alt="<?= htmlspecialchars($filename) ?>"
    >

<?php


/* ============================
   PDF
============================ */

}elseif($ext === 'pdf'){

?>

    <iframe
        src="file_stream.php?id=<?= $id ?>"
    ></iframe>

<?php


/* ============================
   VIDEO
============================ */

}elseif(
    in_array(
        $ext,
        [
            'mp4',
            'webm',
            'ogg'
        ]
    )
){

?>

    <video
        controls
        src="file_stream.php?id=<?= $id ?>"
    ></video>

<?php


/* ============================
   AUDIO
============================ */

}elseif(
    in_array(
        $ext,
        [
            'mp3',
            'wav',
            'ogg'
        ]
    )
){

?>

    <audio
        controls
        src="file_stream.php?id=<?= $id ?>"
    ></audio>

<?php


}else{

?>

    <div class="message">

        <h3>
            Preview tidak tersedia
        </h3>

        <p>
            File ini belum mendukung preview.
        </p>

        <a
            class="btn"
            href="download.php?id=<?= $id ?>"
        >
            Download File
        </a>

    </div>

<?php

}

?>

</div>

</body>

</html>