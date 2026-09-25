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


/* =========================
   AMBIL ID
========================= */

$ids = [];


if(isset($_POST['id'])){

    $ids[] =
        (int)$_POST['id'];

}


if(
    isset($_POST['ids']) &&
    is_array($_POST['ids'])
){

    foreach(
        $_POST['ids']
        as $id
    ){

        $id =
            (int)$id;


        if($id > 0){

            $ids[] =
                $id;

        }

    }

}


$ids =
    array_unique($ids);


if(empty($ids)){

    echo
        "Tidak ada file yang dipilih.";

    exit;
}


/* =========================
   DELETE
========================= */

try{

    $pdo->beginTransaction();

    $deleted = 0;


    foreach(
        $ids
        as $id
    ){

        /* =====================
           AMBIL DATA
        ===================== */

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
            $stmt->fetch(
                PDO::FETCH_ASSOC
            );


        if(!$file){

            continue;

        }


        /* =====================
           PATH SMB
        ===================== */

        $filePath =
            $file['filepath'];


        if(
            empty($filePath)
        ){

            continue;

        }


        /* =====================
           HAPUS FILE SMB
        ===================== */

        if(
            file_exists($filePath)
        ){

            if(
                !unlink($filePath)
            ){

                throw new Exception(
                    "Gagal menghapus file SMB: " .
                    $file['filename']
                );

            }

        }


        /* =====================
           HAPUS DATABASE
        ===================== */

        $stmt =
            $pdo->prepare("
                DELETE FROM files
                WHERE id = ?
                AND user_id = ?
            ");


        $stmt->execute([
            $id,
            $user_id
        ]);


        $deleted++;

    }


    $pdo->commit();


    if($deleted > 0){

        echo "success";

    }

    else{

        echo
            "Tidak ada file yang berhasil dihapus.";

    }

}

catch(Exception $e){

    if(
        $pdo->inTransaction()
    ){

        $pdo->rollBack();

    }


    echo
        "Gagal: " .
        $e->getMessage();

}