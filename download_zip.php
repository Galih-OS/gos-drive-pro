<?php

require 'config/db.php';


/* =========================================================
   CEK LOGIN
========================================================= */

if (!isset($_SESSION['user'])) {

    http_response_code(401);

    exit('Anda belum login.');

}


$user_id = (int) $_SESSION['user']['id'];


/* =========================================================
   CEK DATA ID FILE
========================================================= */

if (
    !isset($_POST['ids']) ||
    !is_array($_POST['ids']) ||
    count($_POST['ids']) === 0
) {

    http_response_code(400);

    exit('Tidak ada file yang dipilih.');

}


/* =========================================================
   BERSIHKAN ID
========================================================= */

$ids = array_map(
    'intval',
    $_POST['ids']
);


/* Hapus ID 0 / tidak valid */

$ids = array_filter(
    $ids,
    function ($id) {

        return $id > 0;

    }
);


$ids = array_values(
    array_unique($ids)
);


if (count($ids) === 0) {

    http_response_code(400);

    exit('ID file tidak valid.');

}


/* =========================================================
   AMBIL DATA FILE
========================================================= */

$placeholders =
    implode(
        ',',
        array_fill(
            0,
            count($ids),
            '?'
        )
    );


$sql = "
    SELECT
        id,
        filename,
        filepath
    FROM files
    WHERE
        user_id = ?
        AND id IN ($placeholders)
";


$params =
    array_merge(
        [$user_id],
        $ids
    );


$stmt =
    $pdo->prepare($sql);


$stmt->execute(
    $params
);


$files =
    $stmt->fetchAll(
        PDO::FETCH_ASSOC
    );


if (!$files) {

    http_response_code(404);

    exit('File tidak ditemukan.');

}


/* =========================================================
   CEK ZIPARCHIVE
========================================================= */

if (!class_exists('ZipArchive')) {

    http_response_code(500);

    exit(
        'Fitur ZIP belum tersedia pada server PHP.'
    );

}


/* =========================================================
   NAMA FILE ZIP
========================================================= */

$zipName =
    'GosDrive_Files_' .
    date('Y-m-d_H-i-s') .
    '.zip';


/* =========================================================
   FILE ZIP TEMPORARY
========================================================= */

$tmpZip =
    tempnam(
        sys_get_temp_dir(),
        'gosdrive_zip_'
    );


if ($tmpZip === false) {

    http_response_code(500);

    exit(
        'Tidak dapat membuat file ZIP sementara.'
    );

}


/* =========================================================
   BUAT ZIP
========================================================= */

$zip =
    new ZipArchive();


$result =
    $zip->open(
        $tmpZip,
        ZipArchive::CREATE |
        ZipArchive::OVERWRITE
    );


if ($result !== true) {

    @unlink($tmpZip);

    http_response_code(500);

    exit(
        'Gagal membuat file ZIP.'
    );

}


/* =========================================================
   MASUKKAN FILE KE ZIP
========================================================= */

$addedCount = 0;

$usedNames = [];


foreach ($files as $file) {

    $filePath =
        $file['filepath'] ?? '';

    $originalName =
        $file['filename'] ?? '';


    /* File fisik tidak ada */

    if (
        empty($filePath) ||
        !file_exists($filePath) ||
        !is_file($filePath)
    ) {

        continue;

    }


    /*
     * Gunakan nama file asli.
     * Jika ada nama yang sama, beri nomor.
     */

    $zipFileName =
        basename($originalName);


    if ($zipFileName === '') {

        $zipFileName =
            'file_' .
            $file['id'];

    }


    $baseName =
        pathinfo(
            $zipFileName,
            PATHINFO_FILENAME
        );


    $extension =
        pathinfo(
            $zipFileName,
            PATHINFO_EXTENSION
        );


    $nameKey =
        strtolower(
            $zipFileName
        );


    $counter = 1;


    while (
        isset(
            $usedNames[$nameKey]
        )
    ) {

        if ($extension !== '') {

            $zipFileName =
                $baseName .
                ' (' .
                $counter .
                ').' .
                $extension;

        } else {

            $zipFileName =
                $baseName .
                ' (' .
                $counter .
                ')';

        }


        $nameKey =
            strtolower(
                $zipFileName
            );


        $counter++;

    }


    $usedNames[$nameKey] = true;


    /*
     * Tambahkan file ke ZIP
     */

    if (
        $zip->addFile(
            $filePath,
            $zipFileName
        )
    ) {

        $addedCount++;

    }

}


/* =========================================================
   TUTUP ZIP
========================================================= */

$zip->close();


/* =========================================================
   CEK APAKAH ADA FILE YANG BERHASIL DIMASUKKAN
========================================================= */

if ($addedCount === 0) {

    @unlink($tmpZip);

    http_response_code(404);

    exit(
        'Tidak ada file yang dapat dimasukkan ke ZIP.'
    );

}


/* =========================================================
   KIRIM ZIP KE BROWSER
========================================================= */

if (!file_exists($tmpZip)) {

    http_response_code(500);

    exit(
        'File ZIP tidak berhasil dibuat.'
    );

}


$zipSize =
    filesize(
        $tmpZip
    );


/*
 * Bersihkan output buffer agar ZIP tidak rusak
 */

while (
    ob_get_level() > 0
) {

    ob_end_clean();

}


header(
    'Content-Type: application/zip'
);


header(
    'Content-Disposition: attachment; filename="' .
    $zipName .
    '"'
);


header(
    'Content-Length: ' .
    $zipSize
);


header(
    'Content-Transfer-Encoding: binary'
);


header(
    'Cache-Control: no-cache, no-store, must-revalidate'
);


header(
    'Pragma: no-cache'
);


header(
    'Expires: 0'
);


/* =========================================================
   KIRIM FILE
========================================================= */

readfile(
    $tmpZip
);


/* =========================================================
   HAPUS FILE ZIP SEMENTARA
========================================================= */

@unlink(
    $tmpZip
);


exit;