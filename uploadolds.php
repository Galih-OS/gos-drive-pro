<?php
if($_SERVER['REQUEST_METHOD'] === 'POST' 
   && empty($_FILES) 
   && $_SERVER['CONTENT_LENGTH'] > 0){

    http_response_code(400);
    exit("Ukuran file melebihi batas maksimal server.");
}


require 'config/db.php';

header("Content-Type: text/plain");

if(!isset($_SESSION['user'])){
    http_response_code(403);
    exit("Unauthorized: Silakan login.");
}

$user_id = $_SESSION['user']['id'];

if(!isset($_FILES['file'])){
    http_response_code(400);
    exit("Tidak ada file yang dikirim.");
}

$file = $_FILES['file'];

/* =========================
   CEK ERROR UPLOAD PHP
========================= */
if($file['error'] !== UPLOAD_ERR_OK){

    $errors = [
        UPLOAD_ERR_INI_SIZE   => "File melebihi batas upload_max_filesize di php.ini.",
        UPLOAD_ERR_FORM_SIZE  => "File melebihi batas MAX_FILE_SIZE dari form.",
        UPLOAD_ERR_PARTIAL    => "File hanya terupload sebagian.",
        UPLOAD_ERR_NO_FILE    => "Tidak ada file yang dipilih.",
        UPLOAD_ERR_NO_TMP_DIR => "Folder temporary tidak ditemukan.",
        UPLOAD_ERR_CANT_WRITE => "Gagal menulis file ke server.",
        UPLOAD_ERR_EXTENSION  => "Upload dihentikan oleh ekstensi PHP."
    ];

    $message = $errors[$file['error']] ?? "Unknown upload error.";

    http_response_code(400);
    exit($message);
}

/* =========================
   BATAS 20GB PER USER
========================= */
$MAX_STORAGE = 20 * 1024 * 1024 * 1024;

$stmt = $pdo->prepare("SELECT COALESCE(SUM(file_size),0) 
                       FROM files WHERE user_id=?");
$stmt->execute([$user_id]);
$currentUsage = $stmt->fetchColumn();

$fileSize = $file['size'];

if(($currentUsage + $fileSize) > $MAX_STORAGE){
    http_response_code(400);
    exit("Storage penuh! Maksimal 20GB per user.");
}

/* =========================
   UPLOAD PROCESS
========================= */

$uploadDir = "uploads/";

if(!is_dir($uploadDir)){
    if(!mkdir($uploadDir, 0777, true)){
        http_response_code(500);
        exit("Gagal membuat folder upload.");
    }
}

/* Amankan nama */
$originalName = basename($file['name']);
$originalName = preg_replace("/[^a-zA-Z0-9._-]/", "_", $originalName);

if(empty($originalName)){
    http_response_code(400);
    exit("Nama file tidak valid.");
}

/* Generate nama unik */
$ext = pathinfo($originalName, PATHINFO_EXTENSION);
$newName = uniqid("file_", true);
if(!empty($ext)){
    $newName .= "." . $ext;
}

$targetPath = $uploadDir . $newName;

/* Cek apakah file valid upload */
if(!is_uploaded_file($file['tmp_name'])){
    http_response_code(400);
    exit("File tidak valid.");
}

/* Pindahkan file */
if(!move_uploaded_file($file['tmp_name'], $targetPath)){
    http_response_code(500);
    exit("Gagal memindahkan file ke folder tujuan.");
}

/* Simpan ke database */
try{

    $shareToken = bin2hex(random_bytes(16));

    $stmtInsert = $pdo->prepare("INSERT INTO files 
        (user_id, filename, filepath, share_token, file_size, created_at)
        VALUES (?, ?, ?, ?, ?, NOW())");

    $stmtInsert->execute([
        $user_id,
        $originalName,
        $targetPath,
        $shareToken,
        $fileSize
    ]);

}catch(Exception $e){

    unlink($targetPath); // rollback file
    http_response_code(500);
    exit("Gagal menyimpan data ke database.");
}

echo "success";
