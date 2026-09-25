<?php

require 'config/db.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

header('Content-Type: application/json; charset=utf-8');


/* =========================================================
   CEK LOGIN
========================================================= */

if (!isset($_SESSION['user'])) {

    http_response_code(401);

    echo json_encode([
        'success' => false,
        'message' => 'Anda belum login.'
    ]);

    exit;
}


$user_id =
    (int) $_SESSION['user']['id'];


/* =========================================================
   DATA UPLOAD
========================================================= */

$uploadId =
    $_POST['upload_id'] ?? '';


$originalName =
    basename(
        $_POST['filename'] ?? ''
    );


$fileSize =
    (int) (
        $_POST['filesize'] ?? 0
    );


$totalChunks =
    (int) (
        $_POST['total_chunks'] ?? 0
    );


/* =========================================================
   VALIDASI
========================================================= */

if (
    $uploadId === '' ||
    $originalName === '' ||
    $fileSize <= 0 ||
    $totalChunks <= 0
) {

    http_response_code(400);

    echo json_encode([
        'success' => false,
        'message' =>
            'Data complete upload tidak lengkap.'
    ]);

    exit;
}


/* =========================================================
   BERSIHKAN NAMA FILE
========================================================= */

$originalName =
    preg_replace(
        '/[^\w\s\.\-\(\)\[\]]/u',
        '_',
        $originalName
    );


if (!$originalName) {

    $originalName =
        'file_upload';

}


/* =========================================================
   SMB
========================================================= */

$uploadDir =
    '\\\\10.201.6.43\\agg\\MROS\\SERVERKU\\uploads\\';


/* =========================================================
   AMANKAN UPLOAD ID
========================================================= */

$safeUploadId =
    preg_replace(
        '/[^a-zA-Z0-9_-]/',
        '',
        $uploadId
    );


if ($safeUploadId === '') {

    http_response_code(400);

    echo json_encode([
        'success' => false,
        'message' =>
            'Upload ID tidak valid.'
    ]);

    exit;
}


/* =========================================================
   TEMP DIRECTORY
========================================================= */

$tempDir =
    $uploadDir .
    '.chunks_' .
    $safeUploadId .
    '\\';


if (!is_dir($tempDir)) {

    http_response_code(404);

    echo json_encode([
        'success' => false,
        'message' =>
            'Folder temporary upload tidak ditemukan.'
    ]);

    exit;
}


/* =========================================================
   BACA METADATA
========================================================= */

$metadataFile =
    $tempDir .
    'metadata.json';


if (!file_exists($metadataFile)) {

    http_response_code(404);

    echo json_encode([
        'success' => false,
        'message' =>
            'Metadata upload tidak ditemukan.'
    ]);

    exit;
}


$metadataContent =
    file_get_contents(
        $metadataFile
    );


$metadata =
    json_decode(
        $metadataContent,
        true
    );


if (
    !is_array($metadata)
) {

    http_response_code(400);

    echo json_encode([
        'success' => false,
        'message' =>
            'Metadata upload tidak valid.'
    ]);

    exit;
}


/* =========================================================
   VALIDASI METADATA USER
========================================================= */

$metadataUserId =
    isset($metadata['user_id'])
        ? (int)$metadata['user_id']
        : 0;


if (
    $metadataUserId !==
    $user_id
) {

    http_response_code(403);

    echo json_encode([
        'success' => false,
        'message' =>
            'Upload bukan milik user yang sedang login.'
    ]);

    exit;
}


/* =========================================================
   AMBIL FOLDER ID
========================================================= */

/*
|--------------------------------------------------------------------------
| 0 = ROOT / FILE SAYA
| >0 = FOLDER
|--------------------------------------------------------------------------
*/

$folder_id =
    isset($metadata['folder_id'])
        ? (int)$metadata['folder_id']
        : 0;


/* =========================================================
   VALIDASI FOLDER
========================================================= */

if ($folder_id > 0) {

    $stmtFolder =
        $pdo->prepare("
            SELECT
                id,
                folder_name,
                parent_id
            FROM folders
            WHERE id = ?
            AND user_id = ?
            LIMIT 1
        ");

    $stmtFolder->execute([
        $folder_id,
        $user_id
    ]);


    $folderData =
        $stmtFolder->fetch(
            PDO::FETCH_ASSOC
        );


    if (!$folderData) {

        http_response_code(404);

        echo json_encode([
            'success' => false,
            'message' =>
                'Folder tujuan tidak ditemukan atau bukan milik Anda.'
        ]);

        exit;
    }

}
else {

    $folder_id = 0;

}


/* =========================================================
   CEK SEMUA CHUNK
========================================================= */

for (
    $i = 0;
    $i < $totalChunks;
    $i++
) {

    $chunkFile =
        $tempDir .
        'chunk_' .
        $i .
        '.part';


    if (!file_exists($chunkFile)) {

        http_response_code(400);

        echo json_encode([

            'success' => false,

            'message' =>
                'Chunk belum lengkap.',

            'missing_chunk' =>
                $i

        ]);

        exit;
    }

}


/* =========================================================
   NAMA FILE SERVER
========================================================= */

$extension =
    pathinfo(
        $originalName,
        PATHINFO_EXTENSION
    );


$baseName =
    pathinfo(
        $originalName,
        PATHINFO_FILENAME
    );


$baseName =
    preg_replace(
        '/[^\w\s\-]/u',
        '_',
        $baseName
    );


if ($baseName === '') {

    $baseName =
        'file';

}


$storedName =
    $baseName .
    '_' .
    date('Ymd_His') .
    '_' .
    bin2hex(
        random_bytes(5)
    );


if ($extension !== '') {

    $storedName .=
        '.' .
        $extension;

}


/* =========================================================
   DESTINATION
========================================================= */

$destination =
    $uploadDir .
    $storedName;


/* =========================================================
   BUAT FILE FINAL
========================================================= */

$destinationHandle =
    fopen(
        $destination,
        'wb'
    );


if (!$destinationHandle) {

    http_response_code(500);

    echo json_encode([
        'success' => false,
        'message' =>
            'Gagal membuat file final di SMB.'
    ]);

    exit;
}


/* =========================================================
   GABUNG CHUNK
========================================================= */

$totalWritten = 0;


try {

    for (
        $i = 0;
        $i < $totalChunks;
        $i++
    ) {

        $chunkFile =
            $tempDir .
            'chunk_' .
            $i .
            '.part';


        $chunkHandle =
            fopen(
                $chunkFile,
                'rb'
            );


        if (!$chunkHandle) {

            throw new Exception(
                'Gagal membaca chunk ' .
                $i
            );

        }


        while (
            !feof(
                $chunkHandle
            )
        ) {

            $buffer =
                fread(
                    $chunkHandle,
                    1024 * 1024
                );


            if ($buffer === false) {

                fclose(
                    $chunkHandle
                );

                throw new Exception(
                    'Gagal membaca data chunk.'
                );

            }


            if ($buffer !== '') {

                $written =
                    fwrite(
                        $destinationHandle,
                        $buffer
                    );


                if ($written === false) {

                    fclose(
                        $chunkHandle
                    );

                    throw new Exception(
                        'Gagal menulis file final.'
                    );

                }


                $totalWritten +=
                    $written;

            }

        }


        fclose(
            $chunkHandle
        );

    }


    fclose(
        $destinationHandle
    );

}

catch (Exception $e) {

    @fclose(
        $destinationHandle
    );


    if (file_exists($destination)) {

        @unlink(
            $destination
        );

    }


    http_response_code(500);

    echo json_encode([

        'success' => false,

        'message' =>
            'Gagal menggabungkan file.',

        'error' =>
            $e->getMessage()

    ]);

    exit;
}


/* =========================================================
   CEK UKURAN FINAL
========================================================= */

$finalSize =
    filesize(
        $destination
    );


if (
    $finalSize === false ||
    (int)$finalSize !==
    (int)$fileSize
) {

    @unlink(
        $destination
    );


    http_response_code(500);

    echo json_encode([

        'success' => false,

        'message' =>
            'Ukuran file hasil tidak sesuai.',

        'expected' =>
            $fileSize,

        'actual' =>
            $finalSize

    ]);

    exit;
}


/* =========================================================
   TOKEN SHARE
========================================================= */

$shareToken =
    bin2hex(
        random_bytes(32)
    );


/* =========================================================
   SIMPAN DATABASE
========================================================= */

try {

    $pdo->beginTransaction();


    $stmt =
        $pdo->prepare("
            INSERT INTO files
            (
                user_id,
                folder_id,
                filename,
                filepath,
                size,
                share_token,
                file_size
            )
            VALUES
            (
                ?,
                ?,
                ?,
                ?,
                ?,
                ?,
                ?
            )
        ");


    $stmt->execute([

        $user_id,

        $folder_id > 0
            ? $folder_id
            : null,

        $originalName,

        $destination,

        $fileSize,

        $shareToken,

        $fileSize

    ]);


    $fileId =
        (int)$pdo->lastInsertId();


    $pdo->commit();

}

catch (Exception $e) {

    if (
        $pdo->inTransaction()
    ) {

        $pdo->rollBack();

    }


    if (
        file_exists(
            $destination
        )
    ) {

        @unlink(
            $destination
        );

    }


    http_response_code(500);

    echo json_encode([

        'success' => false,

        'message' =>
            'File sudah tersimpan tetapi gagal disimpan ke database.',

        'error' =>
            $e->getMessage()

    ]);

    exit;
}


/* =========================================================
   HAPUS CHUNK
========================================================= */

for (
    $i = 0;
    $i < $totalChunks;
    $i++
) {

    $chunkFile =
        $tempDir .
        'chunk_' .
        $i .
        '.part';


    if (
        file_exists(
            $chunkFile
        )
    ) {

        @unlink(
            $chunkFile
        );

    }

}


/* =========================================================
   HAPUS METADATA
========================================================= */

if (
    file_exists(
        $metadataFile
    )
) {

    @unlink(
        $metadataFile
    );

}


/* =========================================================
   HAPUS TEMP FOLDER
========================================================= */

@rmdir(
    $tempDir
);


/* =========================================================
   STORAGE TERPAKAI
========================================================= */

$stmt =
    $pdo->prepare("
        SELECT COALESCE(
            SUM(file_size),
            0
        )
        FROM files
        WHERE user_id = ?
    ");

$stmt->execute([
    $user_id
]);


$newStorageUsed =
    (int)$stmt->fetchColumn();


/* =========================================================
   STORAGE QUOTA
========================================================= */

$stmt =
    $pdo->prepare("
        SELECT storage_quota
        FROM users
        WHERE id = ?
        LIMIT 1
    ");

$stmt->execute([
    $user_id
]);


$storageQuota =
    (int)$stmt->fetchColumn();


if (
    $storageQuota > 0
) {

    $storageRemaining =
        max(
            0,
            $storageQuota -
            $newStorageUsed
        );

}
else {

    $storageRemaining =
        null;

}


/* =========================================================
   NAMA FOLDER
========================================================= */

$folderName = null;


if ($folder_id > 0) {

    $stmtFolderName =
        $pdo->prepare("
            SELECT folder_name
            FROM folders
            WHERE id = ?
            AND user_id = ?
            LIMIT 1
        ");

    $stmtFolderName->execute([
        $folder_id,
        $user_id
    ]);


    $folderName =
        $stmtFolderName->fetchColumn();

}


/* =========================================================
   RESPONSE
========================================================= */

echo json_encode([

    'success' =>
        true,

    'message' =>
        'File berhasil diupload.',

    'file_id' =>
        $fileId,

    'filename' =>
        $originalName,

    'size' =>
        $fileSize,

    'share_token' =>
        $shareToken,

    'folder_id' =>
        $folder_id,

    'folder_name' =>
        $folderName,

    'storage_used' =>
        $newStorageUsed,

    'storage_quota' =>
        $storageQuota,

    'storage_remaining' =>
        $storageRemaining

]);

exit;