<?php

require 'config/db.php';

header(
    'Content-Type: text/plain; charset=utf-8'
);


if(!isset($_SESSION['user'])){

    echo "Anda belum login.";

    exit;
}


$user_id =
    (int)$_SESSION['user']['id'];


$id =
    isset($_POST['id'])
    ? (int)$_POST['id']
    : 0;


$newName =
    isset($_POST['new_name'])
    ? trim($_POST['new_name'])
    : '';


/* =========================
   VALIDASI
========================= */

if($id <= 0){

    echo "ID file tidak valid.";

    exit;
}


if($newName === ''){

    echo "Nama file tidak boleh kosong.";

    exit;
}


/* =========================
   AMBIL FILE
========================= */

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

    echo "File tidak ditemukan.";

    exit;
}


/* =========================
   NAMA LAMA
========================= */

$oldName =
    $file['filename'];


/* =========================
   EXTENSION
========================= */

$oldExt =
    pathinfo(
        $oldName,
        PATHINFO_EXTENSION
    );


$newExt =
    pathinfo(
        $newName,
        PATHINFO_EXTENSION
    );


if(
    $oldExt !== '' &&
    $newExt === ''
){

    $newName .= '.' . $oldExt;

}


/* =========================
   CEK KARAKTER
========================= */

$invalidChars = [
    '/',
    '\\',
    ':',
    '*',
    '?',
    '"',
    '<',
    '>',
    '|'
];


foreach(
    $invalidChars
    as $char
){

    if(
        strpos(
            $newName,
            $char
        ) !== false
    ){

        echo
            "Nama file mengandung karakter yang tidak diperbolehkan.";

        exit;
    }

}


/* =========================
   PATH SMB LAMA
========================= */

$oldPath =
    $file['filepath'];


if(
    empty($oldPath) ||
    !file_exists($oldPath)
){

    echo
        "File fisik tidak ditemukan di SMB.";

    exit;
}


/* =========================
   BUAT PATH BARU
========================= */

$directory =
    dirname($oldPath);


$newPath =
    $directory .
    DIRECTORY_SEPARATOR .
    $newName;


/* =========================
   CEK FILE SUDAH ADA
========================= */

if(
    file_exists($newPath)
){

    echo
        "Nama file tersebut sudah digunakan.";

    exit;
}


/* =========================
   RENAME FILE SMB
========================= */

if(
    !rename(
        $oldPath,
        $newPath
    )
){

    echo
        "Gagal mengubah nama file di SMB.";

    exit;
}


/* =========================
   UPDATE DATABASE
========================= */

$stmt = $pdo->prepare("
    UPDATE files

    SET
        filename = ?,
        filepath = ?

    WHERE id = ?
    AND user_id = ?
");


$success =
    $stmt->execute([

        $newName,

        $newPath,

        $id,

        $user_id

    ]);


if($success){

    echo "success";

}

else{

    /* Rollback file fisik
       jika database gagal */

    @rename(
        $newPath,
        $oldPath
    );


    echo
        "Gagal memperbarui database.";

}