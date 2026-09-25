<?php

require 'config/db.php';
require 'config/auth.php';

gosdrive_require_login();

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}


/* =========================================================
   CEK LOGIN
========================================================= */

if (!isset($_SESSION['user'])) {

    header("Location: login.php");
    exit;

}


$user_id = (int) $_SESSION['user']['id'];


/* =========================================================
   PARAMETER FOLDER
========================================================= */

$currentFolderId = isset($_GET['folder'])
    ? (int) $_GET['folder']
    : 0;


/* =========================================================
   PARAMETER PENCARIAN
========================================================= */

$search = isset($_GET['q'])
    ? trim($_GET['q'])
    : '';


/* =========================================================
   PAGINATION
========================================================= */

$limit = 12;

$page = isset($_GET['page'])
    ? (int) $_GET['page']
    : 1;

if ($page < 1) {
    $page = 1;
}


/* =========================================================
   FUNGSI FORMAT FILE
========================================================= */

function formatFileSize($bytes)
{

    $bytes = (int) $bytes;

    if ($bytes <= 0) {
        return '0 B';
    }

    $units = [
        'B',
        'KB',
        'MB',
        'GB',
        'TB'
    ];

    $i = floor(
        log($bytes, 1024)
    );

    $i = min(
        $i,
        count($units) - 1
    );

    return round(
        $bytes / pow(1024, $i),
        2
    ) . ' ' . $units[$i];

}


/* =========================================================
   FUNGSI FORMAT TANGGAL
========================================================= */

function formatUploadDate($date)
{

    if (empty($date)) {
        return '-';
    }

    $timestamp = strtotime($date);

    if (!$timestamp) {
        return '-';
    }

    return date(
        'd-m-Y H:i',
        $timestamp
    );

}


/* =========================================================
   FUNGSI ICON FILE
========================================================= */

function getFileIcon($filename)
{

    $ext = strtolower(
        pathinfo(
            $filename,
            PATHINFO_EXTENSION
        )
    );


    if (
        in_array(
            $ext,
            [
                'jpg',
                'jpeg',
                'png',
                'gif',
                'webp',
                'bmp',
                'svg'
            ]
        )
    ) {

        return [
            'fa-image',
            'text-success'
        ];

    }


    if ($ext === 'pdf') {

        return [
            'fa-file-pdf',
            'text-danger'
        ];

    }


    if (
        in_array(
            $ext,
            [
                'mp3',
                'wav',
                'ogg',
                'flac',
                'm4a'
            ]
        )
    ) {

        return [
            'fa-file-audio',
            'text-warning'
        ];

    }


    if (
        in_array(
            $ext,
            [
                'mp4',
                'avi',
                'mkv',
                'mov',
                'webm'
            ]
        )
    ) {

        return [
            'fa-file-video',
            'text-primary'
        ];

    }


    if (
        in_array(
            $ext,
            [
                'doc',
                'docx'
            ]
        )
    ) {

        return [
            'fa-file-word',
            'text-primary'
        ];

    }


    if (
        in_array(
            $ext,
            [
                'xls',
                'xlsx',
                'csv'
            ]
        )
    ) {

        return [
            'fa-file-excel',
            'text-success'
        ];

    }


    if (
        in_array(
            $ext,
            [
                'ppt',
                'pptx'
            ]
        )
    ) {

        return [
            'fa-file-powerpoint',
            'text-danger'
        ];

    }


    if (
        in_array(
            $ext,
            [
                'zip',
                'rar',
                '7z',
                'tar',
                'gz'
            ]
        )
    ) {

        return [
            'fa-file-zipper',
            'text-warning'
        ];

    }


    if (
        in_array(
            $ext,
            [
                'txt',
                'log'
            ]
        )
    ) {

        return [
            'fa-file-lines',
            'text-secondary'
        ];

    }


    return [
        'fa-file',
        'text-secondary'
    ];

}


/* =========================================================
   FOLDER AKTIF
========================================================= */

$currentFolder = null;

if ($currentFolderId > 0) {

    $stmtFolder = $pdo->prepare("
        SELECT
            id,
            user_id,
            parent_id,
            folder_name,
            created_at
        FROM folders
        WHERE id = ?
        AND user_id = ?
        LIMIT 1
    ");

    $stmtFolder->execute([
        $currentFolderId,
        $user_id
    ]);

    $currentFolder = $stmtFolder->fetch(
        PDO::FETCH_ASSOC
    );


    /*
     * Jika folder tidak ditemukan
     * atau bukan milik user
     */

    if (!$currentFolder) {

        $currentFolderId = 0;

    }

}


/* =========================================================
   BREADCRUMB FOLDER
========================================================= */

$breadcrumbs = [];


if ($currentFolder) {

    $folderWalk = $currentFolder;


    while ($folderWalk) {

        array_unshift(
            $breadcrumbs,
            $folderWalk
        );


        $parentId = !empty(
            $folderWalk['parent_id']
        )
            ? (int) $folderWalk['parent_id']
            : 0;


        if ($parentId <= 0) {
            break;
        }


        $stmtParent = $pdo->prepare("
            SELECT
                id,
                user_id,
                parent_id,
                folder_name,
                created_at
            FROM folders
            WHERE id = ?
            AND user_id = ?
            LIMIT 1
        ");

        $stmtParent->execute([
            $parentId,
            $user_id
        ]);


        $folderWalk =
            $stmtParent->fetch(
                PDO::FETCH_ASSOC
            );


        if (!$folderWalk) {
            break;
        }

    }

}


/* =========================================================
   STORAGE TOTAL
========================================================= */

$stmtStorage = $pdo->prepare("
    SELECT COALESCE(
        SUM(file_size),
        0
    )
    FROM files
    WHERE user_id = ?
");

$stmtStorage->execute([
    $user_id
]);


$storageUsed =
    (int) $stmtStorage->fetchColumn();


/* =========================================================
   STORAGE QUOTA
========================================================= */

$stmtUser = $pdo->prepare("
    SELECT storage_quota
    FROM users
    WHERE id = ?
    LIMIT 1
");

$stmtUser->execute([
    $user_id
]);


$userData =
    $stmtUser->fetch(
        PDO::FETCH_ASSOC
    );


$storageQuota =
    isset($userData['storage_quota'])
        ? (int) $userData['storage_quota']
        : 0;


$percent = 0;


if ($storageQuota > 0) {

    $percent =
        (
            $storageUsed /
            $storageQuota
        ) * 100;

}


$percent = min(
    $percent,
    100
);


/* =========================================================
   FOLDER LIST
========================================================= */

$folders = [];


/*
 * Folder ditampilkan jika:
 *
 * 1. Tidak sedang mencari
 * 2. Folder berada pada parent yang aktif
 */

if ($search === '') {

    $stmtFolders = $pdo->prepare("
        SELECT
            id,
            user_id,
            parent_id,
            folder_name,
            created_at
        FROM folders
        WHERE user_id = ?
        AND (
            parent_id = ?
            OR (
                ? = 0
                AND parent_id IS NULL
            )
        )
        ORDER BY folder_name ASC
    ");

    $stmtFolders->execute([
        $user_id,
        $currentFolderId,
        $currentFolderId
    ]);


    $folders =
        $stmtFolders->fetchAll(
            PDO::FETCH_ASSOC
        );

}


/* =========================================================
   TOTAL FILE
========================================================= */

if ($search !== '') {

    /*
     * PENCARIAN GLOBAL
     *
     * Mencari semua file milik user
     */

    $stmtTotal = $pdo->prepare("
        SELECT COUNT(*)
        FROM files
        WHERE user_id = ?
        AND filename LIKE ?
    ");

    $stmtTotal->execute([
        $user_id,
        '%' . $search . '%'
    ]);

} else {

    /*
     * TANPA PENCARIAN
     *
     * Hanya file pada folder aktif
     */

    if ($currentFolderId > 0) {

        $stmtTotal = $pdo->prepare("
            SELECT COUNT(*)
            FROM files
            WHERE user_id = ?
            AND folder_id = ?
        ");

        $stmtTotal->execute([
            $user_id,
            $currentFolderId
        ]);

    } else {

        $stmtTotal = $pdo->prepare("
            SELECT COUNT(*)
            FROM files
            WHERE user_id = ?
            AND folder_id IS NULL
        ");

        $stmtTotal->execute([
            $user_id
        ]);

    }

}


$totalFiles =
    (int) $stmtTotal->fetchColumn();


$totalPages =
    $totalFiles > 0
        ? (int) ceil(
            $totalFiles / $limit
        )
        : 1;


if ($page > $totalPages) {

    $page = $totalPages;

}


$offset =
    ($page - 1) * $limit;


/* =========================================================
   AMBIL FILE
========================================================= */

if ($search !== '') {

    /*
     * SEARCH GLOBAL
     */

    $stmt = $pdo->prepare("
        SELECT *
        FROM files
        WHERE user_id = ?
        AND filename LIKE ?
        ORDER BY created_at DESC
        LIMIT $limit
        OFFSET $offset
    ");

    $stmt->execute([
        $user_id,
        '%' . $search . '%'
    ]);

} else {

    if ($currentFolderId > 0) {

        /*
         * FILE DI DALAM FOLDER
         */

        $stmt = $pdo->prepare("
            SELECT *
            FROM files
            WHERE user_id = ?
            AND folder_id = ?
            ORDER BY created_at DESC
            LIMIT $limit
            OFFSET $offset
        ");

        $stmt->execute([
            $user_id,
            $currentFolderId
        ]);

    } else {

        /*
         * FILE DI ROOT
         */

        $stmt = $pdo->prepare("
            SELECT *
            FROM files
            WHERE user_id = ?
            AND folder_id IS NULL
            ORDER BY created_at DESC
            LIMIT $limit
            OFFSET $offset
        ");

        $stmt->execute([
            $user_id
        ]);

    }

}


$files =
    $stmt->fetchAll(
        PDO::FETCH_ASSOC
    );


/* =========================================================
   CURRENT FOLDER UNTUK JAVASCRIPT
========================================================= */

$currentFolderForJS =
    $currentFolderId > 0
        ? $currentFolderId
        : null;

?>

<!DOCTYPE html>

<html lang="id">

<head>

<meta charset="UTF-8">

<meta
    name="viewport"
    content="width=device-width, initial-scale=1.0"
>

<link
    rel="shortcut icon"
    href="ghostdrive.png"
>

<title>GosDrive</title>


<!-- =====================================================
     BOOTSTRAP
====================================================== -->

<link
    href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css"
    rel="stylesheet"
>


<!-- =====================================================
     FONT AWESOME
====================================================== -->

<link
    href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css"
    rel="stylesheet"
>


<style>

/* =========================================================
   RENAME FOLDER MODAL
========================================================= */

.rename-folder-overlay {

    position: fixed;

    inset: 0;

    z-index: 99999;

    display: flex;

    align-items: center;

    justify-content: center;

    padding: 20px;

    background:
        rgba(0, 0, 0, .45);

    backdrop-filter:
        blur(2px);

}


/* =========================================================
   MODAL
========================================================= */

.rename-folder-modal {

    width: 100%;

    max-width: 460px;

    background: #fff;

    border-radius: 14px;

    box-shadow:
        0 20px 60px rgba(0, 0, 0, .25);

    overflow: hidden;

    animation:
        renameModalShow .18s ease-out;

}


@keyframes renameModalShow {

    from {

        opacity: 0;

        transform:
            translateY(-10px)
            scale(.98);

    }

    to {

        opacity: 1;

        transform:
            translateY(0)
            scale(1);

    }

}


/* =========================================================
   HEADER
========================================================= */

.rename-folder-header {

    display: flex;

    align-items: center;

    justify-content: space-between;

    padding:
        18px 20px;

    border-bottom:
        1px solid #e5e7eb;

}


.rename-folder-title {

    font-size: 18px;

    font-weight: 600;

    color: #202124;

}


.rename-folder-subtitle {

    margin-top: 3px;

    font-size: 13px;

    color: #6b7280;

}


.rename-folder-close {

    width: 34px;

    height: 34px;

    border: none;

    background: transparent;

    border-radius: 50%;

    font-size: 25px;

    line-height: 1;

    color: #6b7280;

    cursor: pointer;

    display: flex;

    align-items: center;

    justify-content: center;

}


.rename-folder-close:hover {

    background: #f3f4f6;

    color: #111827;

}


/* =========================================================
   BODY
========================================================= */

.rename-folder-body {

    padding:
        20px;

}


.rename-folder-label {

    display: block;

    margin-bottom: 7px;

    font-size: 13px;

    font-weight: 600;

    color: #374151;

}


.rename-folder-input {

    width: 100%;

    height: 44px;

    box-sizing: border-box;

    border:
        1px solid #c7cbd1;

    border-radius: 7px;

    padding:
        0 12px;

    font-size: 15px;

    color: #202124;

    background: #fff;

    outline: none;

    transition:
        border-color .15s,
        box-shadow .15s;

}


.rename-folder-input:focus {

    border-color: #4f46e5;

    box-shadow:
        0 0 0 3px rgba(79, 70, 229, .12);

}


.rename-folder-input.error {

    border-color: #dc3545;

}


.rename-folder-error {

    margin-top: 7px;

    font-size: 13px;

    color: #dc3545;

}


/* =========================================================
   FOOTER
========================================================= */

.rename-folder-footer {

    display: flex;

    align-items: center;

    justify-content: flex-end;

    gap: 8px;

    padding:
        14px 20px;

    border-top:
        1px solid #e5e7eb;

    background: #fafafa;

}


.rename-folder-btn {

    height: 38px;

    padding:
        0 16px;

    border-radius: 7px;

    font-size: 14px;

    font-weight: 500;

    cursor: pointer;

    transition: .15s;

}


.rename-folder-btn:disabled {

    opacity: .65;

    cursor: wait;

}


.rename-folder-btn-cancel {

    border:
        1px solid #d1d5db;

    background: #fff;

    color: #374151;

}


.rename-folder-btn-cancel:hover {

    background: #f3f4f6;

}


.rename-folder-btn-save {

    border:
        1px solid #4f46e5;

    background: #4f46e5;

    color: #fff;

}


.rename-folder-btn-save:hover {

    background: #4338ca;

    border-color: #4338ca;

}


/* =========================================================
   MOBILE
========================================================= */

@media (max-width: 576px) {

    .rename-folder-overlay {

        padding: 12px;

    }


    .rename-folder-modal {

        max-width: 100%;

        border-radius: 12px;

    }

}


/* =========================================================
   GLOBAL
========================================================= */

* {
    box-sizing: border-box;
}


body {

    margin: 0;

    background: #f4f6f9;

    font-family:
        'Segoe UI',
        Arial,
        sans-serif;

    color: #202124;

}


/* =========================================================
   SIDEBAR
========================================================= */

.sidebar {

    height: 100vh;

    background: #ffffff;

    padding: 20px 16px;

    box-shadow:
        2px 0 10px rgba(0,0,0,.05);

    position: fixed;

    top: 0;

    left: 0;

    width: 230px;

    z-index: 1000;

    overflow-y: auto;

}


.sidebar h4 {

    font-weight: 600;

    margin-bottom: 30px;

}


.sidebar a {

    display: block;

    padding: 10px;

    color: #555;

    text-decoration: none;

    border-radius: 10px;

    margin-bottom: 5px;

}


.sidebar a:hover {

    background: #eef2ff;

    color: #4f46e5;

}


.sidebar .storage-title {

    font-size: 30px;

    font-weight: 600;

}


.sidebar .storage-value {

    font-size: 25px;

    color: #555;

}


/* =========================================================
   MAIN
========================================================= */

.main {

    margin-left: 250px;

    padding: 30px;

    min-height: 100vh;

}


/* =========================================================
   SEARCH BOX
========================================================= */

.search-box {

    background: white;

    padding: 14px;

    border-radius: 15px;

    box-shadow:
        0 3px 10px rgba(0,0,0,.05);

    margin-bottom: 18px;

}


.search-box form {

    display: flex;

    gap: 10px;

}


.search-input-wrap {

    position: relative;

    flex: 1;

}


.search-input-wrap i {

    position: absolute;

    left: 14px;

    top: 50%;

    transform: translateY(-50%);

    color: #dc3545;

}


.search-input {

    width: 100%;

    height: 40px;

    border: 1px solid #d9dee5;

    border-radius: 9px;

    padding:
        0 15px 0 38px;

    outline: none;

}


.search-input:focus {

    border-color: #6366f1;

    box-shadow:
        0 0 0 3px rgba(99,102,241,.1);

}


.search-button {

    height: 40px;

    min-width: 80px;

}


/* =========================================================
   UPLOAD BOX
========================================================= */

.upload-box {

    background: white;

    padding: 20px;

    border-radius: 15px;

    box-shadow:
        0 3px 10px rgba(0,0,0,.05);

    margin-bottom: 20px;

}


/* =========================================================
   UPLOAD HEADER
========================================================= */

.upload-header {

    display: flex;

    align-items: center;

    justify-content: space-between;

    gap: 10px;

    margin-bottom: 15px;

}


.upload-header h5 {

    margin: 0;

}


.upload-header-actions {

    display: flex;

    gap: 8px;

}


/* =========================================================
   DROP ZONE
========================================================= */

.drop-zone {

    border: 2px dashed #cbd5e1;

    border-radius: 15px;

    padding: 38px 20px;

    text-align: center;

    cursor: pointer;

    background: #f8fafc;

    transition:
        all .25s ease;

    user-select: none;

}


.drop-zone:hover {

    border-color: #4f46e5;

    background: #f5f3ff;

}


.drop-zone.drag-over {

    border-color: #4f46e5;

    background: #eef2ff;

    transform: scale(1.01);

}


.drop-zone-icon {

    font-size: 50px;

    color: #4f46e5;

    margin-bottom: 10px;

}


.drop-zone-title {

    font-size: 19px;

    font-weight: 600;

    color: #333;

}


.drop-zone-subtitle {

    color: #6c757d;

    margin-top: 5px;

}


.drop-zone-info {

    font-size: 13px;

    color: #6c757d;

    margin-top: 12px;

}


/* =========================================================
   SELECTED FILES
========================================================= */

.selected-files {

    margin-top: 15px;

    max-height: 250px;

    overflow-y: auto;

}


.selected-files-header {

    display: flex;

    align-items: center;

    justify-content: space-between;

    background: #f8f9fa;

    border: 1px solid #e5e7eb;

    border-radius: 8px 8px 0 0;

    padding: 8px 12px;

    font-size: 13px;

}


.selected-file-item {

    display: flex;

    align-items: center;

    gap: 10px;

    background: #fff;

    border: 1px solid #e5e7eb;

    border-top: none;

    padding: 9px 12px;

}


.selected-file-item:last-child {

    border-radius: 0 0 8px 8px;

}


.selected-file-icon {

    width: 30px;

    min-width: 30px;

    text-align: center;

    font-size: 20px;

    color: #4f46e5;

}


.selected-file-name {

    flex: 1;

    min-width: 0;

    overflow: hidden;

    white-space: nowrap;

    text-overflow: ellipsis;

    font-size: 14px;

}


.selected-file-size {

    color: #6c757d;

    font-size: 12px;

    white-space: nowrap;

}


.selected-file-remove {

    border: none;

    background: none;

    color: #dc3545;

    cursor: pointer;

    padding: 3px 7px;

}


/* =========================================================
   UPLOAD BUTTON ROW
========================================================= */

.upload-button-row {

    display: flex;

    align-items: center;

    gap: 10px;

    margin-top: 15px;

}


/* =========================================================
   BREADCRUMB
========================================================= */

.breadcrumb-box {

    background: #fff;

    border-radius: 12px;

    padding: 12px 15px;

    margin-bottom: 18px;

    box-shadow:
        0 2px 8px rgba(0,0,0,.04);

}


.breadcrumb-box .breadcrumb {

    margin: 0;

}


.breadcrumb-box a {

    text-decoration: none;

    color: #4f46e5;

}


.breadcrumb-box a:hover {

    text-decoration: underline;

}


/* =========================================================
   FOLDER SECTION
========================================================= */

.folder-section {

    margin-bottom: 25px;

}


.folder-section-title {

    display: flex;

    align-items: center;

    justify-content: space-between;

    margin-bottom: 15px;

}


/* =========================================================
   FOLDER GRID
========================================================= */

.folder-grid {

    display: grid;

    grid-template-columns:
        repeat(
            auto-fill,
            minmax(210px, 1fr)
        );

    gap: 14px;

}


/* =========================================================
   FOLDER CARD
========================================================= */

.folder-card {
    position: relative;
    background: #ffffff;
    border: 1px solid #e5e7eb;
    border-radius: 14px;
    padding: 14px;
    min-height: 125px;
    display: flex;
    flex-direction: column;
    text-decoration: none;
    color: #333;
    transition:
        transform .2s ease,
        box-shadow .2s ease,
        border-color .2s ease;
    box-shadow:
        0 2px 8px rgba(0,0,0,.04);
}


/* =========================================================
   FOLDER SELECTED
========================================================= */

.folder-card.folder-selected {
    border-color: #0d6efd;
    box-shadow: 0 0 0 2px rgba(13,110,253,.12);
    background: #f8fbff;
}

.folder-card.folder-selected .folder-icon {
    color: #0d6efd;
}


/* =========================================================
   FOLDER BULK BAR
========================================================= */

#folderBulkActionBar {
    display: none;
}

#folderBulkActionBar.show {
    display: flex !important;
}


/* =========================================================
   FOLDER CARD HOVER
========================================================= */

.folder-card:hover {
    color: #333;
    transform:
        translateY(-2px);
    border-color: #d5d9e0;
    box-shadow:
        0 6px 18px rgba(0,0,0,.08);
}


/* =========================================================
   BARIS ATAS FOLDER
========================================================= */

.folder-top {
    display: flex;
    align-items: center;
    gap: 10px;
    width: 100%;
    min-width: 0;
}


/* =========================================================
   NOMOR FOLDER
========================================================= */

.folder-number {
    width: 24px;
    min-width: 24px;
    height: 24px;
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 7px;
    background: #f3f4f6;
    color: #555;
    font-size: 12px;
    font-weight: 700;
}


/* =========================================================
   ICON FOLDER
========================================================= */

.folder-icon {
    width: 38px;
    min-width: 38px;
    font-size: 36px;
    line-height: 1;
    color: #f4b400;

}


/* =========================================================
   INFORMASI FOLDER
========================================================= */

.folder-info {
    flex: 1;
    min-width: 0;
}


/* =========================================================
   NAMA FOLDER
========================================================= */

.folder-name {
    display: block;
    width: 100%;
    font-size: 15px;
    font-weight: 600;
    color: #202124;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    line-height: 20px;
}


/* =========================================================
   TANGGAL FOLDER
========================================================= */

.folder-date {
    margin-top: 5px;
    font-size: 11px;
    line-height: 16px;
    color: #6c757d;
    white-space: normal;

}


/* =========================================================
   BARIS TOMBOL
========================================================= */
.folder-actions {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 7px;
    width: 100%;
    margin-top: auto;
    padding-top: 12px;
}


/* =========================================================
   BUTTON FOLDER
========================================================= */

.folder-actions .btn {

    width: 36px;

    height: 32px;

    padding: 0;

    display: inline-flex;

    align-items: center;

    justify-content: center;

    border-radius: 6px;

    font-size: 14px;

}


/* =========================================================
   TOMBOL DELETE
========================================================= */

.folder-actions .btn-outline-danger {

    color: #dc3545;

    border-color: #dc3545;

    background: #fff;

}


.folder-actions .btn-outline-danger:hover {

    color: #fff;

    background: #dc3545;

}


/* =========================================================
   TOMBOL BUKA FOLDER
========================================================= */

.folder-actions .btn-outline-primary {

    color: #0d6efd;

    border-color: #0d6efd;

    background: #fff;

}


.folder-actions .btn-outline-primary:hover {

    color: #fff;

    background: #0d6efd;

}


/* =========================================================
   TOMBOL RENAME
========================================================= */

.folder-actions .btn-outline-secondary {

    color: #495057;

    border-color: #495057;

    background: #fff;

}


.folder-actions .btn-outline-secondary:hover {

    color: #fff;

    background: #495057;

}


/* =========================================================
   MOBILE / TABLET
========================================================= */

@media(max-width: 768px) {

    .folder-grid {

        grid-template-columns:
            repeat(
                auto-fill,
                minmax(180px, 1fr)
            );

        gap: 10px;

    }


    .folder-card {

        min-height: 120px;

        padding: 12px;

    }


    .folder-name {

        font-size: 14px;

    }

}


/* =========================================================
   MOBILE KECIL
========================================================= */

@media(max-width: 576px) {

    .folder-grid {

        grid-template-columns:
            repeat(2, minmax(0, 1fr));

        gap: 10px;

    }


    .folder-card {

        min-height: 120px;

        padding: 11px;

    }


    .folder-top {

        gap: 7px;

    }


    .folder-number {

        width: 22px;

        min-width: 22px;

        height: 22px;

        font-size: 11px;

    }


    .folder-icon {

        width: 32px;

        min-width: 32px;

        font-size: 30px;

    }


    .folder-name {

        font-size: 13px;

    }


    .folder-date {

        font-size: 10px;

    }


    .folder-actions {

        gap: 5px;

        padding-top: 9px;

    }


    .folder-actions .btn {

        width: 32px;

        height: 29px;

        font-size: 12px;

    }

}


/* =========================================================
   FILE HEADER
========================================================= */

.file-header {

    display: flex;

    align-items: center;

    justify-content: space-between;

    margin-bottom: 12px;

    gap: 15px;

}


.file-header h5 {

    margin: 0;

}


/* =========================================================
   VIEW TOGGLE
========================================================= */

.view-toggle {

    flex-shrink: 0;

}


.view-toggle button {

    width: 45px;

    height: 38px;

}


.view-toggle button.active {

    background: #4f46e5;

    border-color: #4f46e5;

    color: white;

}


/* =========================================================
   FILE CONTAINER
========================================================= */

#fileContainer {

    width: 100%;

}


#fileContainer.grid-view {

    display: flex;

    flex-wrap: wrap;

}


#fileContainer.grid-view .file-item {

    display: block;

}


#fileContainer.grid-view .file-card {

    text-align: center;

}


/* =========================================================
   FILE CARD
========================================================= */

.file-card {

    border-radius: 15px;

    padding: 15px;

    background: white;

    transition: .3s;

    box-shadow:
        0 3px 10px rgba(0,0,0,.05);

    box-sizing: border-box;

}


.file-card:hover {

    transform: translateY(-3px);

    box-shadow:
        0 8px 20px rgba(0,0,0,.10);

}


/* =========================================================
   FILE ICON
========================================================= */

.file-icon {

    font-size: 40px;

    margin-bottom: 10px;

}


/* =========================================================
   FILE NUMBER
========================================================= */

.file-number {

    color: #555;

    font-weight: 600;

}


/* =========================================================
   FILE NAME
========================================================= */

.file-name {

    display: -webkit-box;

    -webkit-line-clamp: 2;

    -webkit-box-orient: vertical;

    overflow: hidden;

    text-overflow: ellipsis;

    min-height: 40px;

    word-break: break-word;

}


/* =========================================================
   FILE SIZE
========================================================= */

.file-size {

    color: #6c757d;

    font-size: 13px;

    white-space: nowrap;

}


/* =========================================================
   FILE DATE
========================================================= */

.file-date {

    color: #6c757d;

    font-size: 12px;

    white-space: nowrap;

}


.file-date i {

    margin-right: 3px;

}


/* =========================================================
   FILE ACTIONS
========================================================= */

.file-actions {

    display: flex;

    align-items: center;

    justify-content: center;

    gap: 5px;

    flex-wrap: wrap;

    margin-top: 10px;

}


.file-check {

    cursor: pointer;

}


/* =========================================================
   LIST VIEW
========================================================= */

#fileContainer.list-view {

    display: block;

    width: 100%;

}


#fileContainer.list-view .file-item {

    display: block;

    width: 100% !important;

    max-width: 100% !important;

    flex: none !important;

    margin-bottom: 10px !important;

}


#fileContainer.list-view .file-card {

    display: flex;

    align-items: center;

    width: 100%;

    min-height: 65px;

    padding: 10px 15px;

    text-align: left !important;

    border-radius: 10px;

}


#fileContainer.list-view .file-check {

    width: 18px;

    height: 18px;

    margin: 0 15px 0 0;

    flex: 0 0 18px;

}


#fileContainer.list-view .file-number {

    display: block;

    width: 40px;

    min-width: 40px;

    margin-right: 10px;

    text-align: left;

}


#fileContainer.list-view .file-icon {

    display: block;

    width: 40px;

    min-width: 40px;

    margin: 0 15px 0 0;

    font-size: 25px;

    text-align: center;

}


#fileContainer.list-view .file-name {

    flex: 1;

    width: auto;

    min-width: 0;

    margin: 0 15px 0 0;

    min-height: auto;

    text-align: left !important;

    white-space: nowrap;

    overflow: hidden;

    text-overflow: ellipsis;

    display: block;

}


#fileContainer.list-view .file-size {

    width: 120px;

    min-width: 120px;

    margin-right: 15px;

    text-align: left;

}


#fileContainer.list-view .file-date {

    width: 150px;

    min-width: 150px;

    margin-right: 15px;

    text-align: left;

}


#fileContainer.list-view .file-actions {

    display: flex !important;

    align-items: center;

    justify-content: flex-end;

    gap: 5px;

    width: auto;

    flex-shrink: 0;

    margin: 0 !important;

}


/* =========================================================
   LIST HEADER
========================================================= */

.file-list-header {

    display: none;

    width: 100%;

    box-sizing: border-box;

}


body.list-mode .file-list-header {

    display: flex;

}


.file-size-header {

    width: 120px;

    min-width: 120px;

}


.file-date-header {

    width: 150px;

    min-width: 150px;

}


.file-actions-header {

    width: 210px;

    min-width: 210px;

}


/* =========================================================
   BULK ACTION
========================================================= */

#bulkActionBar {
    display: none !important;
    align-items: center;
    justify-content: space-between;
    width: 100%;
}

#bulkActionBar.show {
    display: flex !important;
}


/* =========================================================
   PROGRESS
========================================================= */

#progressContainer {

    display: none;

}


#progressBar {

    transition: width .2s ease;

}


/* =========================================================
   MOBILE
========================================================= */

@media(max-width: 768px) {

    .sidebar {

        width: 200px;

    }


    .main {

        margin-left: 215px;

        padding: 15px;

    }


    .folder-grid {

        grid-template-columns:
            repeat(
                auto-fill,
                minmax(160px, 1fr)
            );

    }


    #fileContainer.list-view .file-card {

        padding: 10px;

    }


    #fileContainer.list-view .file-number {

        width: 30px;

        min-width: 30px;

        margin-right: 5px;

    }


    #fileContainer.list-view .file-icon {

        width: 30px;

        min-width: 30px;

        margin-right: 8px;

        font-size: 22px;

    }


    #fileContainer.list-view .file-name {

        margin-right: 8px;

        font-size: 14px;

    }


    #fileContainer.list-view .file-size {

        width: 90px;

        min-width: 90px;

        font-size: 12px;

    }


    #fileContainer.list-view .file-date {

        width: 110px;

        min-width: 110px;

        font-size: 11px;

    }


    .file-actions-header {

        width: 190px;

        min-width: 190px;

    }

}


@media(max-width: 576px) {

    .sidebar {

        position: relative;

        width: 100%;

        height: auto;

    }


    .main {

        margin-left: 0;

        padding: 15px;

    }


    .search-box form {

        flex-direction: column;

    }


    .search-button {

        width: 100%;

    }


    .upload-header {

        align-items: flex-start;

        flex-direction: column;

    }


    .upload-header-actions {

        width: 100%;

    }


    .upload-header-actions button {

        flex: 1;

    }


    .drop-zone {

        padding: 25px 15px;

    }


    .drop-zone-icon {

        font-size: 40px;

    }


    .drop-zone-title {

        font-size: 16px;

    }


    .selected-file-size {

        display: none;

    }


    #fileContainer.list-view .file-card {

        padding: 8px;

    }


    #fileContainer.list-view .file-check {

        margin-right: 7px;

    }


    #fileContainer.list-view .file-number {

        width: 25px;

        min-width: 25px;

    }


    #fileContainer.list-view .file-icon {

        width: 25px;

        min-width: 25px;

        margin-right: 7px;

    }


    #fileContainer.list-view .file-size {

        width: auto;

        min-width: auto;

        margin-right: 5px;

        font-size: 10px;

    }


    #fileContainer.list-view .file-date {

        width: auto;

        min-width: auto;

        margin-right: 5px;

        font-size: 10px;

    }


    #fileContainer.list-view .file-actions .btn {

        padding: 3px 5px;

    }


    .file-size-header,
    .file-date-header {

        display: none;

    }

}

</style>

</head>


<body>


<!-- =====================================================
     SIDEBAR
====================================================== -->

<div class="sidebar">

    <h4>

        <i class="fa fa-cloud"></i>

        GosDrive

    </h4>
    <!-- =================================================
         GREETING
    ================================================== -->

    Halo,

        <?= htmlspecialchars(
            $_SESSION['user']['username'],
            ENT_QUOTES,
            'UTF-8'
        ) ?>

    <a
        href="logout.php"
        class="text-danger"
        onclick="
            return confirm(
                'Apakah Anda yakin ingin logout dari GosDrive?'
            );
        "
    >

        <i class="fa fa-sign-out-alt"></i>

        Logout

    </a>


    <hr>


    <div class="storage-title">

        Storage

    </div>


    <div class="storage-value mt-1">

        <?= formatFileSize($storageUsed) ?>

    </div>


    <?php if ($storageQuota > 0): ?>

    <div
        class="progress mt-3"
        style="height:8px;"
    >

        <div
            class="progress-bar"
            role="progressbar"
            style="width:<?= $percent ?>%;"
        ></div>

    </div>


    <small class="text-muted">

        <?= round($percent, 2) ?>%
        digunakan

    </small>

    <?php endif; ?>

</div>


<!-- =====================================================
     MAIN
====================================================== -->

<div class="main">

    <!-- =================================================
         SEARCH
    ================================================== -->

    <div class="search-box">

        <form
            method="GET"
            action="dashboard.php"
        >

            <div class="search-input-wrap">

                <i class="fa fa-search"></i>

                <input
                    type="text"
                    name="q"
                    class="search-input"
                    placeholder="Cari nama file..."
                    value="<?= htmlspecialchars(
                        $search,
                        ENT_QUOTES,
                        'UTF-8'
                    ) ?>"
                >

            </div>


            <button
                type="submit"
                class="btn btn-danger search-button"
            >

                <i class="fa fa-search"></i>

                Cari

            </button>


            <?php if ($search !== ''): ?>

            <a
                href="dashboard.php"
                class="btn btn-outline-secondary"
                style="height:40px;"
            >

                <i class="fa fa-times"></i>

                Reset

            </a>

            <?php endif; ?>

        </form>

    </div>


    <!-- =================================================
         BREADCRUMB
    ================================================== -->

    <div class="breadcrumb-box">

        <nav aria-label="breadcrumb">

            <ol class="breadcrumb">


                <li class="breadcrumb-item">

                    <a href="dashboard.php">

                        <i class="fa fa-house"></i>

                        GosDrive

                    </a>

                </li>


                <?php foreach (
                    $breadcrumbs
                    as $index => $crumb
                ): ?>

                    <?php

                    $isLast =
                        $index ===
                        count($breadcrumbs) - 1;

                    ?>


                    <li
                        class="
                            breadcrumb-item
                            <?= $isLast
                                ? 'active'
                                : ''
                            ?>
                        "
                    >

                        <?php if (!$isLast): ?>

                        <a
                            href="dashboard.php?folder=<?= (int)$crumb['id'] ?>"
                        >

                            <?= htmlspecialchars(
                                $crumb['folder_name'],
                                ENT_QUOTES,
                                'UTF-8'
                            ) ?>

                        </a>

                        <?php else: ?>

                            <?= htmlspecialchars(
                                $crumb['folder_name'],
                                ENT_QUOTES,
                                'UTF-8'
                            ) ?>

                        <?php endif; ?>

                    </li>


                <?php endforeach; ?>


            </ol>

        </nav>

    </div>


    <!-- =================================================
         UPLOAD BOX
    ================================================== -->

    <div class="upload-box">


        <div class="upload-header">

            <h5>

                <i class="fa fa-upload"></i>

                Upload File

            </h5>
        </div>


        <!-- =============================================
             DROP ZONE
        ============================================== -->

        <div
            id="dropZone"
            class="drop-zone"
        >

            <div class="drop-zone-icon">

                <i class="fa fa-cloud-arrow-up"></i>

            </div>


            <div class="drop-zone-title">

                Tarik & Lepaskan File di Sini

            </div>


            <div class="drop-zone-subtitle">

                atau klik untuk memilih file

            </div>


            <div class="drop-zone-info">

                <i class="fa fa-file"></i>

                Bisa memilih beberapa file sekaligus

            </div>


            <input
                type="file"
                id="fileInput"
                multiple
                hidden
            >

        </div>


        <!-- =============================================
             SELECTED FILES
        ============================================== -->

        <div
            id="selectedFiles"
            class="selected-files"
            style="display:none;"
        ></div>


        <!-- =============================================
             PROGRESS
        ============================================== -->

        <div
            class="progress mt-3"
            id="progressContainer"
            style="height:22px;"
        >

            <div
                id="progressBar"
                class="
                    progress-bar
                    progress-bar-striped
                    progress-bar-animated
                "
                style="width:0%;"
            >

                0%

            </div>

        </div>


        <!-- =============================================
             STATUS
        ============================================== -->

        <div
            id="uploadStatus"
            class="mt-2"
        ></div>


        <!-- =============================================
             BUTTON
        ============================================== -->

        <div class="upload-button-row">

            <button
                type="button"
                class="btn btn-primary"
                onclick="uploadFile()"
                id="uploadButton"
            >

                <i class="fa fa-upload"></i>

                Upload File

            </button>

                            <!-- TAMBAH FOLDER -->

                <button
                    type="button"
                    class="btn btn-warning"
                    onclick="createFolder()"
                >

                    <i class="fa fa-folder-plus"></i>

                    Tambah Folder

                </button>


            <button
                type="button"
                id="clearFilesButton"
                onclick="clearSelectedFiles()"
                class="btn btn-outline-secondary"
                style="display:none;"
            >

                <i class="fa fa-times"></i>

                Bersihkan

            </button>

        </div>

    </div>


    <!-- =================================================
         FOLDER
    ================================================== -->


    <!-- =================================================
         FOLDER BULK ACTION
    ================================================== -->

    <?php if ($search === '' && !empty($folders)): ?>

    <div
        id="folderBulkActionBar"
        class="alert alert-light border align-items-center justify-content-between mb-3"
        style="display:none;"
    >

        <div>
            <label class="mb-0" style="cursor:pointer;">
                <input
                    type="checkbox"
                    id="selectAllFolders"
                    class="form-check-input me-2"
                    onchange="toggleSelectAllFolders(this)"
                >

                <strong>Pilih Semua Folder</strong>
            </label>

            <span
                id="selectedFolderCount"
                class="text-muted ms-2"
            >
                0 folder dipilih
            </span>
        </div>

        <div class="d-flex" style="gap:8px;">

            <button
                type="button"
                id="downloadSelectedFoldersButton"
                class="btn btn-success btn-sm"
                onclick="downloadSelectedFolders()"
            >
                <i class="fa fa-download"></i>
                Download Folder Terpilih
            </button>

            <button
                type="button"
                id="shareSelectedFoldersButton"
                class="btn btn-info btn-sm text-white"
                onclick="shareSelectedFolders()"
            >
                <i class="fa fa-share-alt"></i>
                Share Folder Terpilih
            </button>

        </div>
    </div>

    <?php endif; ?>

<div class="folder-grid">

<?php foreach ($folders as $folder): ?>

<div
    class="folder-card"
    data-folder-id="<?= (int)$folder['id'] ?>"
    style="
        cursor:pointer;
        position:relative;
    "
    onclick="
        window.location.href =
        'dashboard.php?folder=<?= (int)$folder['id'] ?>';
    "
>


    <!-- CHECKBOX FOLDER -->

    <div
        style="
            position:absolute;
            top:10px;
            left:10px;
            z-index:5;
        "
        onclick="event.stopPropagation();"
    >

        <input
            type="checkbox"
            class="form-check-input folder-check"
            value="<?= (int)$folder['id'] ?>"
            data-folder-name="<?= htmlspecialchars(
                $folder['folder_name'],
                ENT_QUOTES,
                'UTF-8'
            ) ?>"
            onchange="updateSelectedFolders()"
            title="Pilih folder"
        >

    </div>


    <!-- ICON FOLDER -->

    <div class="folder-icon">

        <i class="fa fa-folder"></i>

    </div>


    <!-- INFORMASI -->

    <div
        class="folder-info"
        style="
            min-width:0;
            flex:1;
        "
    >

        <div
            class="folder-name"
            title="<?= htmlspecialchars(
                $folder['folder_name'],
                ENT_QUOTES,
                'UTF-8'
            ) ?>"
        >

            <?= htmlspecialchars(
                $folder['folder_name'],
                ENT_QUOTES,
                'UTF-8'
            ) ?>

        </div>


        <div class="folder-date">

            Dibuat:

            <?= formatUploadDate(
                $folder['created_at']
            ) ?>

        </div>

    </div>


    <!-- AKSI FOLDER -->

    <div
        class="folder-actions"
        onclick="event.stopPropagation();"
    >


        <!-- SHARE -->

        <button
            type="button"
            class="
                btn
                btn-sm
                btn-outline-info
            "
            title="Share Folder"
            onclick='shareFolder(
                <?= (int)$folder['id'] ?>,
                <?= json_encode(
                    $folder['folder_name'],
                    JSON_UNESCAPED_UNICODE |
                    JSON_UNESCAPED_SLASHES |
                    JSON_HEX_TAG |
                    JSON_HEX_APOS |
                    JSON_HEX_QUOT |
                    JSON_HEX_AMP
                ) ?>
            )'
        >

            <i class="fa fa-share-alt"></i>

        </button>


        <!-- DOWNLOAD -->

        <button
            type="button"
            class="
                btn
                btn-sm
                btn-outline-success
            "
            title="Download Folder"
            onclick="
                downloadFolder(
                    <?= (int)$folder['id'] ?>
                )
            "
        >

            <i class="fa fa-download"></i>

        </button>


        <!-- DELETE -->

        <button
            type="button"
            class="
                btn
                btn-sm
                btn-outline-danger
            "
            title="Hapus folder"
            onclick='deleteFolder(
                <?= (int)$folder['id'] ?>,
                <?= json_encode(
                    $folder['folder_name'],
                    JSON_UNESCAPED_UNICODE |
                    JSON_UNESCAPED_SLASHES |
                    JSON_HEX_TAG |
                    JSON_HEX_APOS |
                    JSON_HEX_QUOT |
                    JSON_HEX_AMP
                ) ?>
            )'
        >

            <i class="fa fa-trash"></i>

        </button>


        <!-- BUKA -->

        <button
            type="button"
            class="
                btn
                btn-sm
                btn-outline-primary
            "
            title="Buka folder"
            onclick="
                window.location.href =
                'dashboard.php?folder=<?= (int)$folder['id'] ?>';
            "
        >

            <i class="fa fa-folder-open"></i>

        </button>


        <!-- RENAME -->

        <button
            type="button"
            class="
                btn
                btn-sm
                btn-outline-warning
            "
            title="Rename Folder"
            onclick='openRenameFolderModal(
                <?= (int)$folder['id'] ?>,
                <?= json_encode(
                    $folder['folder_name'],
                    JSON_UNESCAPED_UNICODE |
                    JSON_UNESCAPED_SLASHES |
                    JSON_HEX_TAG |
                    JSON_HEX_APOS |
                    JSON_HEX_QUOT |
                    JSON_HEX_AMP
                ) ?>
            )'
        >

            <i class="fa fa-pen"></i>

        </button>


    </div>

</div>

<?php endforeach; ?>

</div>


    <!-- =================================================
         FILE HEADER
    ================================================== -->

    <div class="file-header">

        <div>

            <h5>

                <?php if ($search !== ''): ?>

                    <i class="fa fa-search"></i>

                    Hasil pencarian:

                    <strong>
                        <?= htmlspecialchars(
                            $search,
                            ENT_QUOTES,
                            'UTF-8'
                        ) ?>
                    </strong>

                <?php elseif ($currentFolder): ?>

                    <i class="fa fa-folder-open text-warning"></i>

                    Isi Folder

                <?php else: ?>

                    File Saya

                <?php endif; ?>

            </h5>


            <small class="text-muted">

                <?= $totalFiles ?>

                file

            </small>

        </div>


        <div class="btn-group view-toggle">

            <button
                type="button"
                class="
                    btn
                    btn-outline-secondary
                    active
                "
                onclick="setView('grid')"
                title="Grid View"
            >

                <i class="fa fa-th"></i>

            </button>


            <button
                type="button"
                class="
                    btn
                    btn-outline-secondary
                "
                onclick="setView('list')"
                title="List View"
            >

                <i class="fa fa-bars"></i>

            </button>

        </div>

    </div>


            <!-- =================================================
                BULK ACTION
            ================================================== -->

            <div
                id="bulkActionBar"
                class="
                    alert
                    alert-light
                    border
                    align-items-center
                    justify-content-between
                    mb-3
                "
            >

                <div>

                    <label
                        class="mb-0"
                        style="cursor:pointer;"
                    >

                        <input
                            type="checkbox"
                            id="selectAll"
                            class="form-check-input me-2"
                            onchange="toggleSelectAll(this)"
                        >

                        <strong>
                            Pilih Semua
                        </strong>

                    </label>


                    <span
                        id="selectedCount"
                        class="text-muted ms-2"
                    >

                        0 file dipilih

                    </span>

                </div>


                <div
                    class="d-flex"
                    style="gap:8px;"
                >

                    <!-- DOWNLOAD -->

                    <button
                        type="button"
                        id="downloadSelectedButton"
                        class="btn btn-primary btn-sm"
                        onclick="downloadSelected()"
                    >

                        <i class="fa fa-download"></i>

                        Download Terpilih

                    </button>


                    <!-- DELETE -->

                    <button
                        type="button"
                        id="deleteSelectedButton"
                        class="btn btn-danger btn-sm"
                        onclick="deleteSelected()"
                    >

                        <i class="fa fa-trash"></i>

                        Hapus Terpilih

                    </button>

                </div>

            </div>


    <!-- =================================================
         LIST HEADER
    ================================================== -->

    <div
        id="fileListHeader"
        class="
            file-list-header
            align-items-center
            bg-light
            rounded
            px-3
            py-2
            mb-2
        "
    >

        <div style="width:35px;">

            <input
                type="checkbox"
                class="form-check-input"
                onchange="toggleSelectAll(this)"
            >

        </div>


        <div style="width:50px;">

            <strong>No.</strong>

        </div>


        <div style="width:55px;">

            <strong>File</strong>

        </div>


        <div class="flex-grow-1">

            <strong>Nama File</strong>

        </div>


        <div class="file-size-header">

            <strong>Ukuran</strong>

        </div>


        <div class="file-date-header">

            <strong>Tanggal Upload</strong>

        </div>


        <div class="file-actions-header">

            <strong>Aksi</strong>

        </div>

    </div>


    <!-- =================================================
         FILE CONTAINER
    ================================================== -->

    <div
        id="fileContainer"
        class="row grid-view"
    >


        <?php foreach (
            $files
            as $index => $file
        ): ?>


        <?php

        $nomor =
            (
                ($page - 1) *
                $limit
            )
            + $index
            + 1;


        $fileSize =
            isset($file['file_size'])
                ? (int)$file['file_size']
                : (
                    isset($file['size'])
                        ? (int)$file['size']
                        : 0
                );


        $icon =
            getFileIcon(
                $file['filename']
            );

        ?>


        <div
            class="
                col-md-3
                mb-4
                file-item
            "
        >

            <div class="file-card">


                <!-- CHECKBOX -->

                <input
                    type="checkbox"
                    class="
                        form-check-input
                        file-check
                    "
                    value="<?= (int)$file['id'] ?>"
                    onchange="updateSelectedCount()"
                >


                <!-- NOMOR -->

                <span class="file-number">

                    <?= $nomor ?>

                </span>


                <!-- ICON -->

                <i
                    class="
                        fa
                        <?= $icon[0] ?>
                        file-icon
                        <?= $icon[1] ?>
                    "
                ></i>


                <!-- NAMA FILE -->

                <h6
                    class="file-name"
                    title="<?= htmlspecialchars(
                        $file['filename'],
                        ENT_QUOTES,
                        'UTF-8'
                    ) ?>"
                >

                    <?= htmlspecialchars(
                        $file['filename'],
                        ENT_QUOTES,
                        'UTF-8'
                    ) ?>

                </h6>


                <!-- UKURAN -->

                <div
                    class="file-size"
                    title="Ukuran file"
                >

                    <i class="fa fa-database me-1"></i>

                    <?= formatFileSize(
                        $fileSize
                    ) ?>

                </div>


                <!-- TANGGAL -->

                <div
                    class="file-date mt-1"
                    title="Tanggal upload"
                >

                    <i class="fa fa-calendar"></i>

                    <?= formatUploadDate(
                        $file['created_at']
                    ) ?>

                </div>


                <!-- ACTION -->
                    <div class="file-actions">

                        <!-- PREVIEW -->
                        <a
                            href="preview.php?id=<?= (int)$file['id'] ?>"
                            target="_blank"
                            class="btn btn-sm btn-outline-success"
                            title="Preview"
                        >
                            <i class="fa fa-eye"></i>
                        </a>

                        <!-- SHARE -->
                        <button
                            type="button"
                            class="btn btn-sm btn-outline-info"
                            title="Share Link"
                            onclick='shareFile(
                                <?= (int)$file["id"] ?>,
                                <?= json_encode(
                                    $file["filename"],
                                    JSON_UNESCAPED_UNICODE |
                                    JSON_UNESCAPED_SLASHES |
                                    JSON_HEX_TAG |
                                    JSON_HEX_APOS |
                                    JSON_HEX_QUOT |
                                    JSON_HEX_AMP
                                ) ?>
                            )'
                        >
                            <i class="fa fa-share-alt"></i>
                        </button>

                        <!-- DOWNLOAD -->
                        <a
                            href="download.php?id=<?= (int)$file['id'] ?>"
                            class="btn btn-sm btn-outline-primary"
                            title="Download"
                        >
                            <i class="fa fa-download"></i>
                        </a>

                        <!-- RENAME -->
                        <button
                            type="button"
                            class="btn btn-sm btn-outline-warning"
                            title="Rename"
                            onclick='renameFile(
                                <?= (int)$file["id"] ?>,
                                <?= json_encode(
                                    $file["filename"],
                                    JSON_UNESCAPED_UNICODE |
                                    JSON_UNESCAPED_SLASHES |
                                    JSON_HEX_TAG |
                                    JSON_HEX_APOS |
                                    JSON_HEX_QUOT |
                                    JSON_HEX_AMP
                                ) ?>
                            )'
                        >
                            <i class="fa fa-pen"></i>
                        </button>

                        <!-- DELETE -->
                        <button
                            type="button"
                            class="btn btn-sm btn-outline-danger"
                            title="Hapus"
                            onclick="deleteFile(<?= (int)$file['id'] ?>)"
                        >
                            <i class="fa fa-trash"></i>
                        </button>

</div>

            </div>

        </div>


        <?php endforeach; ?>


    </div>


    <!-- =================================================
         EMPTY
    ================================================== -->

    <?php if (
        empty($files)
        &&
        empty($folders)
    ): ?>

    <div
        class="
            alert
            alert-light
            text-center
            border
        "
    >

        <i
            class="
                fa
                fa-folder-open
                fa-2x
                mb-2
            "
        ></i>

        <br>


        <?php if ($search !== ''): ?>

            Tidak ditemukan file dengan nama:

            <strong>

                <?= htmlspecialchars(
                    $search,
                    ENT_QUOTES,
                    'UTF-8'
                ) ?>

            </strong>

        <?php elseif ($currentFolder): ?>

            Folder ini masih kosong.

        <?php else: ?>

            Belum ada file atau folder.

        <?php endif; ?>


    </div>

    <?php endif; ?>


    <!-- =================================================
         PAGINATION
    ================================================== -->

    <?php if ($totalPages > 1): ?>

    <nav class="mt-3">

        <ul
            class="
                pagination
                justify-content-center
            "
        >


            <?php if ($page > 1): ?>

            <li class="page-item">

                <?php

                $prevParams = [];

                if ($search !== '') {
                    $prevParams['q'] = $search;
                }

                if ($currentFolderId > 0) {
                    $prevParams['folder'] =
                        $currentFolderId;
                }

                $prevParams['page'] =
                    $page - 1;

                ?>


                <a
                    class="page-link"
                    href="dashboard.php?<?= http_build_query(
                        $prevParams
                    ) ?>"
                >

                    Previous

                </a>

            </li>

            <?php endif; ?>


            <?php for (
                $i = 1;
                $i <= $totalPages;
                $i++
            ): ?>


            <?php

            $pageParams = [];

            if ($search !== '') {
                $pageParams['q'] = $search;
            }

            if ($currentFolderId > 0) {
                $pageParams['folder'] =
                    $currentFolderId;
            }

            $pageParams['page'] = $i;

            ?>


            <li
                class="
                    page-item
                    <?= ($i == $page)
                        ? 'active'
                        : ''
                    ?>
                "
            >

                <a
                    class="page-link"
                    href="dashboard.php?<?= http_build_query(
                        $pageParams
                    ) ?>"
                >

                    <?= $i ?>

                </a>

            </li>


            <?php endfor; ?>


            <?php if ($page < $totalPages): ?>

            <li class="page-item">

                <?php

                $nextParams = [];

                if ($search !== '') {
                    $nextParams['q'] = $search;
                }

                if ($currentFolderId > 0) {
                    $nextParams['folder'] =
                        $currentFolderId;
                }

                $nextParams['page'] =
                    $page + 1;

                ?>


                <a
                    class="page-link"
                    href="dashboard.php?<?= http_build_query(
                        $nextParams
                    ) ?>"
                >

                    Next

                </a>

            </li>

            <?php endif; ?>


        </ul>

    </nav>

    <?php endif; ?>


</div>

            <!--modal rename-->
<!-- =========================================================
     MODAL RENAME FOLDER
========================================================= -->

<div
    id="renameFolderModal"
    class="rename-folder-overlay"
    style="display:none;"
>

    <div
        class="rename-folder-modal"
        role="dialog"
        aria-modal="true"
        aria-labelledby="renameFolderTitle"
    >

        <!-- HEADER -->

        <div class="rename-folder-header">

            <div>

                <div
                    id="renameFolderTitle"
                    class="rename-folder-title"
                >

                    Rename Folder

                </div>

                <div class="rename-folder-subtitle">

                    Masukkan nama folder baru

                </div>

            </div>

            <button
                type="button"
                class="rename-folder-close"
                id="renameFolderCloseButton"
                title="Tutup"
            >

                &times;

            </button>

        </div>


        <!-- BODY -->

        <div class="rename-folder-body">

            <label
                for="renameFolderInput"
                class="rename-folder-label"
            >

                Nama folder

            </label>


            <input
                type="text"
                id="renameFolderInput"
                class="rename-folder-input"
                autocomplete="off"
                maxlength="255"
                spellcheck="false"
            >


            <div
                id="renameFolderError"
                class="rename-folder-error"
                style="display:none;"
            ></div>

        </div>


        <!-- FOOTER -->

        <div class="rename-folder-footer">

            <button
                type="button"
                id="renameFolderCancelButton"
                class="rename-folder-btn rename-folder-btn-cancel"
            >

                Batal

            </button>


            <button
                type="button"
                id="renameFolderSaveButton"
                class="rename-folder-btn rename-folder-btn-save"
            >

                <i class="fa fa-check"></i>

                Simpan

            </button>

        </div>

    </div>

</div>
            <!--tutup rename-->


<script>

/* =========================================================
   GOSDRIVE
   DASHBOARD + FOLDER + CHUNK UPLOAD
========================================================= */


/* =========================================================
   CURRENT FOLDER
========================================================= */

const currentFolderId =
    <?= json_encode(
        $currentFolderForJS
    ) ?>;


/* =========================================================
   CHUNK CONFIG
========================================================= */

const CHUNK_SIZE =
    10 * 1024 * 1024;


/* =========================================================
   ELEMENT
========================================================= */

const dropZone =
    document.getElementById(
        "dropZone"
    );


const fileInput =
    document.getElementById(
        "fileInput"
    );


const selectedFilesBox =
    document.getElementById(
        "selectedFiles"
    );


const uploadButton =
    document.getElementById(
        "uploadButton"
    );


const clearFilesButton =
    document.getElementById(
        "clearFilesButton"
    );


let selectedFiles = [];


/* =========================================================
   KLIK DROP ZONE
========================================================= */

dropZone.addEventListener(
    "click",
    function () {

        if (fileInput.disabled) {
            return;
        }

        fileInput.click();

    }
);


/* =========================================================
   PILIH FILE
========================================================= */

fileInput.addEventListener(
    "change",
    function () {

        const files =
            Array.from(
                this.files
            );

        addSelectedFiles(
            files
        );

    }
);


/* =========================================================
   DRAG ENTER
========================================================= */

dropZone.addEventListener(
    "dragenter",
    function (e) {

        e.preventDefault();

        e.stopPropagation();

        dropZone.classList.add(
            "drag-over"
        );

    }
);


/* =========================================================
   DRAG OVER
========================================================= */

dropZone.addEventListener(
    "dragover",
    function (e) {

        e.preventDefault();

        e.stopPropagation();

        dropZone.classList.add(
            "drag-over"
        );

    }
);


/* =========================================================
   DRAG LEAVE
========================================================= */

dropZone.addEventListener(
    "dragleave",
    function (e) {

        e.preventDefault();

        e.stopPropagation();

        dropZone.classList.remove(
            "drag-over"
        );

    }
);


/* =========================================================
   DROP
========================================================= */

dropZone.addEventListener(
    "drop",
    function (e) {

        e.preventDefault();

        e.stopPropagation();

        dropZone.classList.remove(
            "drag-over"
        );


        if (fileInput.disabled) {
            return;
        }


        const files =
            Array.from(
                e.dataTransfer.files
            );


        if (files.length === 0) {
            return;
        }


        addSelectedFiles(
            files
        );

    }
);


/* =========================================================
   TAMBAH FILE
========================================================= */

function addSelectedFiles(files)
{

    files.forEach(
        function (file) {

            const exists =
                selectedFiles.some(
                    function (existing) {

                        return (
                            existing.name ===
                            file.name

                            &&

                            existing.size ===
                            file.size

                            &&

                            existing.lastModified ===
                            file.lastModified
                        );

                    }
                );


            if (!exists) {

                selectedFiles.push(
                    file
                );

            }

        }
    );


    updateFileInput();

    renderSelectedFiles();

}


/* =========================================================
   UPDATE INPUT
========================================================= */

function updateFileInput()
{

    const dataTransfer =
        new DataTransfer();


    selectedFiles.forEach(
        function (file) {

            dataTransfer.items.add(
                file
            );

        }
    );


    fileInput.files =
        dataTransfer.files;

}


/* =========================================================
   TAMPILKAN FILE TERPILIH
========================================================= */

function renderSelectedFiles()
{

    if (
        selectedFiles.length === 0
    ) {

        selectedFilesBox.style.display =
            "none";

        selectedFilesBox.innerHTML =
            "";

        clearFilesButton.style.display =
            "none";

        return;

    }


    selectedFilesBox.style.display =
        "block";


    clearFilesButton.style.display =
        "inline-block";


    let html = `

        <div class="selected-files-header">

            <strong>

                ${selectedFiles.length}
                file dipilih

            </strong>

            <span class="text-muted">

                Siap diupload

            </span>

        </div>

    `;


    selectedFiles.forEach(
        function (file, index) {

            html += `

                <div
                    class="selected-file-item"
                >

                    <div
                        class="selected-file-icon"
                    >

                        <i
                            class="fa fa-file"
                        ></i>

                    </div>


                    <div
                        class="selected-file-name"
                        title="${escapeHtml(
                            file.name
                        )}"
                    >

                        ${escapeHtml(
                            file.name
                        )}

                    </div>


                    <div
                        class="selected-file-size"
                    >

                        ${formatBytesJS(
                            file.size
                        )}

                    </div>


                    <button
                        type="button"
                        class="selected-file-remove"
                        onclick="removeSelectedFile(${index})"
                        title="Hapus"
                    >

                        <i
                            class="fa fa-times"
                        ></i>

                    </button>

                </div>

            `;

        }
    );


    selectedFilesBox.innerHTML =
        html;

}


/* =========================================================
   REMOVE FILE DARI ANTREAN
========================================================= */

function removeSelectedFile(index)
{

    if (fileInput.disabled) {
        return;
    }


    selectedFiles.splice(
        index,
        1
    );


    updateFileInput();

    renderSelectedFiles();

}


/* =========================================================
   BERSIHKAN FILE
========================================================= */

function clearSelectedFiles()
{

    if (fileInput.disabled) {
        return;
    }


    selectedFiles = [];

    fileInput.value = "";

    updateFileInput();

    renderSelectedFiles();

}


/* =========================================================
   UPLOAD FILE
========================================================= */

async function uploadFile()
{

    const files =
        Array.from(
            fileInput.files
        );


    const progressContainer =
        document.getElementById(
            "progressContainer"
        );


    const progressBar =
        document.getElementById(
            "progressBar"
        );


    const uploadStatus =
        document.getElementById(
            "uploadStatus"
        );


    if (files.length === 0) {

        alert(
            "Pilih atau tarik minimal satu file!"
        );

        return;

    }


    fileInput.disabled =
        true;


    dropZone.style.pointerEvents =
        "none";


    uploadButton.disabled =
        true;


    clearFilesButton.disabled =
        true;


    uploadButton.innerHTML =
        '<i class="fa fa-spinner fa-spin"></i> Uploading...';


    progressContainer.style.display =
        "block";


    progressBar.style.width =
        "0%";


    progressBar.innerHTML =
        "0%";


    let berhasil = 0;

    let gagal = 0;


    for (
        let i = 0;
        i < files.length;
        i++
    ) {

        const file =
            files[i];


        uploadStatus.innerHTML =

            `<div class="alert alert-info">

                <i
                    class="fa fa-spinner fa-spin"
                ></i>

                Upload
                ${i + 1}
                dari
                ${files.length}

                :

                <strong>

                    ${escapeHtml(
                        file.name
                    )}

                </strong>

                <br>

                <small>

                    ${formatBytesJS(
                        file.size
                    )}

                </small>

            </div>`;


        try {

            await uploadLargeFile(
                file,
                i,
                files.length,
                progressBar,
                uploadStatus
            );


            berhasil++;

        }

        catch (error) {

            gagal++;


            console.error(
                "Upload gagal:",
                file.name,
                error
            );


            uploadStatus.innerHTML +=

                `<div
                    class="alert alert-danger mt-2"
                >

                    <strong>
                        Gagal:
                    </strong>

                    ${escapeHtml(
                        file.name
                    )}

                    <br>

                    <small>

                        ${escapeHtml(
                            error.message
                        )}

                    </small>

                </div>`;

        }

    }


    progressBar.style.width =
        "100%";


    progressBar.innerHTML =
        "100%";


    if (gagal === 0) {

        uploadStatus.innerHTML =

            `<div
                class="alert alert-success"
            >

                <i
                    class="fa fa-check-circle"
                ></i>

                Semua

                <strong>

                    ${berhasil}

                </strong>

                file berhasil diupload!

            </div>`;

    }

    else {

        uploadStatus.innerHTML =

            `<div
                class="alert alert-warning"
            >

                <i
                    class="fa fa-exclamation-triangle"
                ></i>

                Berhasil:

                <strong>

                    ${berhasil}

                </strong>

                file

                &nbsp;|&nbsp;

                Gagal:

                <strong>

                    ${gagal}

                </strong>

                file

            </div>`;

    }


    fileInput.disabled =
        false;


    dropZone.style.pointerEvents =
        "auto";


    uploadButton.disabled =
        false;


    clearFilesButton.disabled =
        false;


    uploadButton.innerHTML =
        '<i class="fa fa-upload"></i> Upload File';


    selectedFiles = [];

    fileInput.value = "";

    updateFileInput();

    renderSelectedFiles();


    if (berhasil > 0) {

        setTimeout(
            function () {

                location.reload();

            },
            1500
        );

    }

}


/* =========================================================
   UPLOAD LARGE FILE
========================================================= */

async function uploadLargeFile(
    file,
    fileIndex,
    totalFiles,
    progressBar,
    uploadStatus
)
{

    const uploadId =
        generateUploadId();


    /* =====================================================
       INIT
    ===================================================== */

    const initData =
        new FormData();


    initData.append(
        "action",
        "init"
    );


    initData.append(
        "upload_id",
        uploadId
    );


    initData.append(
        "filename",
        file.name
    );


    initData.append(
        "filesize",
        file.size
    );


    /*
     * FOLDER AKTIF
     *
     * NULL = root
     */

    if (currentFolderId !== null) {

        initData.append(
            "folder_id",
            currentFolderId
        );

    }
    else {

        initData.append(
            "folder_id",
            ""
        );

    }


    const initResponse =
        await fetch(
            "upload.php",
            {
                method: "POST",
                body: initData
            }
        );


    const initText =
        await initResponse.text();


    let initResult;


    try {

        initResult =
            JSON.parse(
                initText
            );

    }

    catch (e) {

        throw new Error(
            "Response INIT tidak valid: "
            +
            initText.substring(
                0,
                500
            )
        );

    }


    if (
        !initResponse.ok
        ||
        !initResult.success
    ) {

        throw new Error(
            initResult.message
            ||
            "Gagal memulai upload."
        );

    }


    /* =====================================================
       TOTAL CHUNK
    ===================================================== */

    const totalChunks =
        Math.ceil(
            file.size /
            CHUNK_SIZE
        );


    let uploadedBytes = 0;


    /* =====================================================
       UPLOAD CHUNK
    ===================================================== */

    for (
        let chunkIndex = 0;
        chunkIndex < totalChunks;
        chunkIndex++
    ) {

        const start =
            chunkIndex *
            CHUNK_SIZE;


        const end =
            Math.min(
                start +
                CHUNK_SIZE,
                file.size
            );


        const chunk =
            file.slice(
                start,
                end
            );


        let success =
            false;


        /* =================================================
           RETRY
        ================================================= */

        for (
            let retry = 1;
            retry <= 3;
            retry++
        ) {

            try {

                const chunkData =
                    new FormData();


                chunkData.append(
                    "action",
                    "chunk"
                );


                chunkData.append(
                    "upload_id",
                    uploadId
                );


                chunkData.append(
                    "chunk_index",
                    chunkIndex
                );


                chunkData.append(
                    "total_chunks",
                    totalChunks
                );


                chunkData.append(
                    "filename",
                    file.name
                );


                /*
                 * FOLDER AKTIF
                 */

                if (currentFolderId !== null) {

                    chunkData.append(
                        "folder_id",
                        currentFolderId
                    );

                }
                else {

                    chunkData.append(
                        "folder_id",
                        ""
                    );

                }


                chunkData.append(
                    "chunk",
                    chunk
                );


                const response =
                    await uploadChunkRequest(
                        chunkData,
                        function (loaded) {

                            const currentBytes =
                                uploadedBytes +
                                loaded;


                            const filePercent =
                                (
                                    currentBytes /
                                    file.size
                                ) * 100;


                            const overallPercent =
                                (
                                    (
                                        fileIndex /
                                        totalFiles
                                    ) * 100
                                )
                                +
                                (
                                    filePercent /
                                    totalFiles
                                );


                            const percent =
                                Math.min(
                                    99,
                                    Math.round(
                                        overallPercent
                                    )
                                );


                            progressBar.style.width =
                                percent + "%";


                            progressBar.innerHTML =
                                percent + "%";


                            uploadStatus.innerHTML =

                                `<div
                                    class="alert alert-info"
                                >

                                    <i
                                        class="
                                            fa
                                            fa-spinner
                                            fa-spin
                                        "
                                    ></i>

                                    Upload
                                    ${fileIndex + 1}
                                    dari
                                    ${totalFiles}

                                    <br>

                                    <strong>

                                        ${escapeHtml(
                                            file.name
                                        )}

                                    </strong>

                                    <br>

                                    Chunk
                                    ${chunkIndex + 1}
                                    /
                                    ${totalChunks}

                                    <br>

                                    Progress:
                                    ${percent}%

                                </div>`;

                        }
                    );


                const result =
                    response.result;


                if (
                    !response.ok
                    ||
                    !result.success
                ) {

                    throw new Error(
                        result.message
                        ||
                        "Chunk gagal diupload."
                    );

                }


                uploadedBytes +=
                    chunk.size;


                success =
                    true;


                break;

            }

            catch (error) {

                console.warn(
                    "Chunk gagal. Retry:",
                    retry,
                    error
                );


                if (retry >= 3) {

                    throw new Error(

                        "Chunk "
                        +
                        (chunkIndex + 1)
                        +
                        " gagal setelah 3 percobaan. "
                        +
                        error.message

                    );

                }


                await sleep(
                    1000 * retry
                );

            }

        }


        if (!success) {

            throw new Error(
                "Upload chunk gagal."
            );

        }

    }


    /* =====================================================
       COMPLETE
    ===================================================== */

    const completeData =
        new FormData();


    completeData.append(
        "action",
        "complete"
    );


    completeData.append(
        "upload_id",
        uploadId
    );


    completeData.append(
        "filename",
        file.name
    );


    completeData.append(
        "filesize",
        file.size
    );


    completeData.append(
        "total_chunks",
        totalChunks
    );


    /*
     * FOLDER AKTIF
     */

    if (currentFolderId !== null) {

        completeData.append(
            "folder_id",
            currentFolderId
        );

    }
    else {

        completeData.append(
            "folder_id",
            ""
        );

    }


    const completeResponse =
        await fetch(
            "upload_complete.php",
            {
                method: "POST",
                body: completeData
            }
        );


    const completeText =
        await completeResponse.text();


    let completeResult;


    try {

        completeResult =
            JSON.parse(
                completeText
            );

    }

    catch (e) {

        throw new Error(
            "Response COMPLETE tidak valid: "
            +
            completeText.substring(
                0,
                500
            )
        );

    }


    if (
        !completeResponse.ok
        ||
        !completeResult.success
    ) {

        throw new Error(
            completeResult.message
            ||
            "Gagal menyelesaikan upload."
        );

    }


    /* =====================================================
       PROGRESS FILE SELESAI
    ===================================================== */

    const finalPercent =
        Math.round(
            (
                (
                    fileIndex + 1
                )
                /
                totalFiles
            ) * 100
        );


    progressBar.style.width =
        finalPercent + "%";


    progressBar.innerHTML =
        finalPercent + "%";


    return completeResult;

}


/* =========================================================
   XHR CHUNK REQUEST
========================================================= */

function uploadChunkRequest(
    formData,
    progressCallback
)
{

    return new Promise(
        function (
            resolve,
            reject
        ) {

            const xhr =
                new XMLHttpRequest();


            xhr.open(
                "POST",
                "upload.php",
                true
            );


            xhr.upload.addEventListener(
                "progress",
                function (e) {

                    if (
                        e.lengthComputable
                        &&
                        progressCallback
                    ) {

                        progressCallback(
                            e.loaded
                        );

                    }

                }
            );


            xhr.onload =
                function () {

                    let result;


                    try {

                        result =
                            JSON.parse(
                                xhr.responseText
                            );

                    }

                    catch (e) {

                        reject(
                            new Error(
                                "Response server tidak valid: "
                                +
                                xhr.responseText.substring(
                                    0,
                                    500
                                )
                            )
                        );

                        return;

                    }


                    resolve({

                        ok:
                            xhr.status >= 200
                            &&
                            xhr.status < 300,

                        result:
                            result

                    });

                };


            xhr.onerror =
                function () {

                    reject(
                        new Error(
                            "Network Error"
                        )
                    );

                };


            xhr.ontimeout =
                function () {

                    reject(
                        new Error(
                            "Request timeout."
                        )
                    );

                };


            xhr.timeout =
                300000;


            xhr.send(
                formData
            );

        }
    );

}


/* =========================================================
   GENERATE UPLOAD ID
========================================================= */

function generateUploadId()
{

    return (
        Date.now().toString(36)
        +
        "-"
        +
        Math.random()
            .toString(36)
            .substring(2)
        +
        "-"
        +
        Math.random()
            .toString(36)
            .substring(2)
    );

}


/* =========================================================
   SLEEP
========================================================= */

function sleep(ms)
{

    return new Promise(
        function (resolve) {

            setTimeout(
                resolve,
                ms
            );

        }
    );

}


/* =========================================================
   FORMAT BYTE
========================================================= */

function formatBytesJS(bytes)
{

    if (bytes <= 0) {
        return "0 B";
    }


    const units = [
        "B",
        "KB",
        "MB",
        "GB",
        "TB"
    ];


    const i =
        Math.floor(
            Math.log(bytes) /
            Math.log(1024)
        );


    return (
        bytes /
        Math.pow(
            1024,
            i
        )
    ).toFixed(2)

    + " "

    + units[i];

}


/* =========================================================
   ESCAPE HTML
========================================================= */

function escapeHtml(text)
{

    const div =
        document.createElement(
            "div"
        );


    div.textContent =
        text;


    return div.innerHTML;

}


/* =========================================================
   CREATE FOLDER
========================================================= */

function createFolder()
{

    const folderName =
        prompt(
            "Masukkan nama folder:"
        );


    if (folderName === null) {
        return;
    }


    const name =
        folderName.trim();


    if (name === "") {

        alert(
            "Nama folder tidak boleh kosong."
        );

        return;

    }


    /*
     * ENDPOINT INI AKAN KITA BUAT
     * PADA TAHAP BERIKUTNYA:
     *
     * create_folder.php
     */

    const params =
        new URLSearchParams();


    params.append(
        "folder_name",
        name
    );


    if (currentFolderId !== null) {

        params.append(
            "parent_id",
            currentFolderId
        );

    }
    else {

        params.append(
            "parent_id",
            ""
        );

    }


    fetch(
        "create_folder.php",
        {

            method: "POST",

            headers: {

                "Content-Type":
                    "application/x-www-form-urlencoded"

            },

            body:
                params.toString()

        }
    )

    .then(
        response =>
            response.json()
    )

    .then(
        result => {

            if (result.success) {

                alert(
                    "Folder berhasil dibuat."
                );


                location.reload();

            }

            else {

                alert(
                    result.message
                    ||
                    "Gagal membuat folder."
                );

            }

        }
    )

    .catch(
        error => {

            console.error(
                error
            );


            alert(
                "Terjadi kesalahan saat membuat folder."
            );

        }
    );

}

/* =========================================================
   DELETE FOLDER
========================================================= */

async function deleteFolder(folderId, folderName)
{
    const confirmResult = await Swal.fire({
        icon: 'warning',
        title: 'Hapus Folder?',
        html:
            'Anda yakin ingin menghapus folder<br>' +
            '<strong>“' + escapeHtml(folderName) + '”</strong>?' +
            '<br><br>' +
            '<small style="color:#dc3545;">' +
            'Seluruh file dan subfolder di dalamnya juga akan dihapus.' +
            '</small>',

        showCancelButton: true,

        confirmButtonText:
            '<i class="fa fa-trash"></i> Ya, Hapus',

        cancelButtonText:
            'Batal',

        reverseButtons: true,

        focusCancel: true,

        confirmButtonColor: '#dc3545',

        cancelButtonColor: '#6c757d',

        allowOutsideClick: false,

        customClass: {
            popup: 'gosdrive-delete-popup',
            confirmButton: 'gosdrive-confirm-button',
            cancelButton: 'gosdrive-cancel-button'
        }
    });


    if (!confirmResult.isConfirmed) {
        return;
    }


    /* =========================================
       TAMPILKAN PROSES
    ========================================= */

    Swal.fire({
        title: 'Menghapus folder...',
        html:
            '<div style="margin-top:10px;color:#6c757d;">' +
            'Mohon tunggu sebentar.' +
            '</div>',

        allowOutsideClick: false,
        allowEscapeKey: false,
        showConfirmButton: false,

        didOpen: () => {

            Swal.showLoading();

        }
    });


    try {

        const formData =
            new FormData();

        formData.append(
            'folder_id',
            folderId
        );

        formData.append(
            'delete_contents',
            '1'
        );


        const response =
            await fetch(
                'delete_folder.php',
                {
                    method: 'POST',
                    body: formData,
                    credentials: 'same-origin'
                }
            );


        const text =
            await response.text();


        let result;


        try {

            result =
                JSON.parse(text);

        }
        catch (jsonError) {

            console.error(
                'Response delete_folder.php:',
                text
            );

            throw new Error(
                'Server tidak mengirim JSON yang valid.'
            );

        }


        if (
            !response.ok ||
            !result.success
        ) {

            throw new Error(
                result.message ||
                'Gagal menghapus folder.'
            );

        }


        /* =========================================
           BERHASIL
        ========================================= */

        await Swal.fire({

            icon: 'success',

            title: 'Folder Berhasil Dihapus',

            html:
                '<strong>“' +
                escapeHtml(
                    result.folder_name ||
                    folderName
                ) +
                '”</strong>' +

                '<br><br>' +

                '<span style="color:#6c757d;">' +
                'Folder dan seluruh isinya telah dihapus.' +
                '</span>' +

                '<br><br>' +

                '<small style="color:#999;">' +
                'Kembali ke halaman utama...' +
                '</small>',

            timer: 2500,

            timerProgressBar: true,

            showConfirmButton: false,

            allowOutsideClick: false,

            allowEscapeKey: false

        });


        /* =========================================
           KEMBALI KE DASHBOARD
        ========================================= */

        window.location.href =
            'dashboard.php';

    }
    catch (error) {

        console.error(
            'DELETE FOLDER ERROR:',
            error
        );


        Swal.fire({

            icon: 'error',

            title: 'Penghapusan Gagal',

            html:
                '<span style="color:#6c757d;">' +
                escapeHtml(
                    error.message ||
                    'Terjadi kesalahan.'
                ) +
                '</span>',

            confirmButtonText:
                'Tutup',

            confirmButtonColor:
                '#6c757d'

        });

    }
}

/* =========================================================
   RENAME FILE
========================================================= */


function renameFile(
    id,
    oldName
)
{

    const newName =
        prompt(
            "Masukkan nama file baru:",
            oldName
        );


    if (newName === null) {
        return;
    }


    const name =
        newName.trim();


    if (name === "") {

        alert(
            "Nama file tidak boleh kosong."
        );

        return;

    }


    if (name === oldName) {
        return;
    }


    fetch(
        "rename.php",
        {

            method: "POST",

            headers: {

                "Content-Type":
                    "application/x-www-form-urlencoded"

            },

            body:
                "id="
                +
                encodeURIComponent(id)
                +
                "&new_name="
                +
                encodeURIComponent(name)

        }
    )

    .then(
        response =>
            response.text()
    )

    .then(
        result => {

            if (
                result.trim() ===
                "success"
            ) {

                alert(
                    "File berhasil diubah namanya."
                );


                location.reload();

            }

            else {

                alert(
                    "Gagal mengubah nama file.\n\n"
                    +
                    result
                );

            }

        }
    )

    .catch(
        error => {

            console.error(
                error
            );


            alert(
                "Terjadi kesalahan saat mengubah nama file."
            );

        }
    );

}


/* =========================================================
   DELETE FILE
========================================================= */

function deleteFile(id)
{

    const yakin =
        confirm(
            "Apakah Anda yakin ingin menghapus file ini?"
        );


    if (!yakin) {
        return;
    }


    fetch(
        "delete.php",
        {

            method: "POST",

            headers: {

                "Content-Type":
                    "application/x-www-form-urlencoded"

            },

            body:
                "id="
                +
                encodeURIComponent(id)

        }
    )

    .then(
        response =>
            response.text()
    )

    .then(
        result => {

            if (
                result.trim() ===
                "success"
            ) {

                alert(
                    "File berhasil dihapus."
                );


                location.reload();

            }

            else {

                alert(
                    "Gagal menghapus file.\n\n"
                    +
                    result
                );

            }

        }
    )

    .catch(
        error => {

            console.error(
                error
            );


            alert(
                "Terjadi kesalahan saat menghapus file."
            );

        }
    );

}


/* =========================================================
   UPDATE SELECTED COUNT
========================================================= */

/* =========================================================
   UPDATE SELECTED COUNT
========================================================= */

function updateSelectedCount()
{

    const checkboxes =
        document.querySelectorAll(
            ".file-check"
        );


    const selected =
        document.querySelectorAll(
            ".file-check:checked"
        );


    const count =
        selected.length;


    const countText =
        document.getElementById(
            "selectedCount"
        );


    const bulkBar =
        document.getElementById(
            "bulkActionBar"
        );


    const selectAll =
        document.getElementById(
            "selectAll"
        );


    /* JUMLAH FILE */

    if (countText) {

        countText.innerHTML =
            count +
            " file dipilih";

    }


    /* TAMPILKAN / SEMBUNYIKAN BULK ACTION */

    if (bulkBar) {

        if (count > 0) {

            bulkBar.classList.add(
                "show"
            );

        }

        else {

            bulkBar.classList.remove(
                "show"
            );

        }

    }


    /* STATUS PILIH SEMUA */

    if (selectAll) {

        selectAll.checked =
            checkboxes.length > 0 &&
            count === checkboxes.length;

    }

}


/* =========================================================
   SELECT ALL
========================================================= */

function toggleSelectAll(source)
{

    const checkboxes =
        document.querySelectorAll(
            ".file-check"
        );


    checkboxes.forEach(
        function (checkbox) {

            checkbox.checked =
                source.checked;

        }
    );


    updateSelectedCount();

}

/* =========================================================
   DOWNLOAD SELECTED
========================================================= */

function downloadSelected()
{

    const selected =
        Array.from(
            document.querySelectorAll(
                ".file-check:checked"
            )
        );


    if (selected.length === 0) {

        alert(
            "Silakan pilih minimal satu file."
        );

        return;

    }


    const ids =
        selected.map(
            function (item) {

                return item.value;

            }
        );


    const yakin =
        confirm(
            "Download "
            +
            ids.length
            +
            " file sebagai ZIP?"
        );


    if (!yakin) {

        return;

    }


    const form =
        document.createElement(
            "form"
        );


    form.method =
        "POST";


    form.action =
        "download_zip.php";


    form.style.display =
        "none";


    ids.forEach(
        function (id) {

            const input =
                document.createElement(
                    "input"
                );


            input.type =
                "hidden";


            input.name =
                "ids[]";


            input.value =
                id;


            form.appendChild(
                input
            );

        }
    );


    document.body.appendChild(
        form
    );


    form.submit();


    setTimeout(
        function () {

            form.remove();

        },
        1000
    );

}


/* =========================================================
   DELETE SELECTED
========================================================= */

function deleteSelected()
{

    const selected =
        Array.from(
            document.querySelectorAll(
                ".file-check:checked"
            )
        );


    if (selected.length === 0) {

        alert(
            "Silakan pilih minimal satu file."
        );

        return;

    }


    const ids =
        selected.map(
            function (item) {

                return item.value;

            }
        );


    const yakin =
        confirm(
            "Apakah Anda yakin ingin menghapus "
            +
            ids.length
            +
            " file sekaligus?"
        );


    if (!yakin) {
        return;
    }


const bulkButton =
    document.getElementById(
        "deleteSelectedButton"
    );


    if (bulkButton) {

        bulkButton.disabled =
            true;


        bulkButton.innerHTML =
            '<i class="fa fa-spinner fa-spin"></i> Menghapus...';

    }


    const params =
        new URLSearchParams();


    ids.forEach(
        function (id) {

            params.append(
                "ids[]",
                id
            );

        }
    );


    fetch(
        "delete.php",
        {

            method: "POST",

            headers: {

                "Content-Type":
                    "application/x-www-form-urlencoded"

            },

            body:
                params.toString()

        }
    )

    .then(
        response =>
            response.text()
    )

    .then(
        result => {

            if (
                result.trim() ===
                "success"
            ) {

                alert(
                    ids.length +
                    " file berhasil dihapus."
                );


                location.reload();

            }

            else {

                alert(
                    "Gagal menghapus file.\n\n"
                    +
                    result
                );


                if (bulkButton) {

                    bulkButton.disabled =
                        false;


                    bulkButton.innerHTML =
                        '<i class="fa fa-trash"></i> Hapus Terpilih';

                }

            }

        }
    )

    .catch(
        error => {

            console.error(
                error
            );


            alert(
                "Terjadi kesalahan saat menghapus file."
            );


            if (bulkButton) {

                bulkButton.disabled =
                    false;


                bulkButton.innerHTML =
                    '<i class="fa fa-trash"></i> Hapus Terpilih';

            }

        }
    );

}


/* =========================================================
   SHARE FILE
========================================================= */

async function shareFile(
    id,
    fileName
)
{

    try {

        const response =
            await fetch(
                "create_share.php",
                {

                    method: "POST",

                    headers: {

                        "Content-Type":
                            "application/x-www-form-urlencoded"

                    },

                    body:
                        "id="
                        +
                        encodeURIComponent(id)

                }
            );


        const result =
            await response.json();


        if (!result.success) {

            alert(
                result.message
                ||
                "Gagal membuat link share."
            );

            return;

        }


        const shareUrl =
            result.url;


        if (navigator.share) {

            try {

                await navigator.share({

                    title:
                        fileName,

                    text:
                        "Berbagi file: "
                        +
                        fileName,

                    url:
                        shareUrl

                });


                return;

            }

            catch (error) {

                if (
                    error.name ===
                    "AbortError"
                ) {

                    return;

                }

            }

        }


        if (navigator.clipboard) {

            await navigator.clipboard.writeText(
                shareUrl
            );

        }

        else {

            const temp =
                document.createElement(
                    "input"
                );


            temp.value =
                shareUrl;


            document.body.appendChild(
                temp
            );


            temp.select();


            document.execCommand(
                "copy"
            );


            document.body.removeChild(
                temp
            );

        }


        alert(
            "Link berhasil disalin!\n\n"
            +
            shareUrl
        );

    }

    catch (error) {

        console.error(
            error
        );


        alert(
            "Gagal membuat link share."
        );

    }

}


/* =========================================================
   FOLDER SELECTION
========================================================= */

function getSelectedFolderCheckboxes()
{
    return Array.from(
        document.querySelectorAll(".folder-check:checked")
    );
}


/* =========================================================
   UPDATE SELECTED FOLDER
========================================================= */

function updateSelectedFolders()
{
    const checkboxes =
        document.querySelectorAll(".folder-check");

    const selected =
        getSelectedFolderCheckboxes();

    const count =
        selected.length;

    const countText =
        document.getElementById("selectedFolderCount");

    const bulkBar =
        document.getElementById("folderBulkActionBar");

    const selectAll =
        document.getElementById("selectAllFolders");

    document.querySelectorAll(".folder-card").forEach(
        function(card) {
            const checkbox =
                card.querySelector(".folder-check");

            if (checkbox && checkbox.checked) {
                card.classList.add("folder-selected");
            } else {
                card.classList.remove("folder-selected");
            }
        }
    );

    if (countText) {
        countText.textContent =
            count + " folder dipilih";
    }

    if (bulkBar) {
        if (count > 0) {
            bulkBar.classList.add("show");
        } else {
            bulkBar.classList.remove("show");
        }
    }

    if (selectAll) {
        selectAll.checked =
            checkboxes.length > 0 &&
            count === checkboxes.length;
    }
}


/* =========================================================
   SELECT ALL FOLDER
========================================================= */

function toggleSelectAllFolders(source)
{
    document.querySelectorAll(".folder-check").forEach(
        function(checkbox) {
            checkbox.checked = source.checked;
        }
    );

    updateSelectedFolders();
}


/* =========================================================
   SHARE FOLDER
   Endpoint: share_folder.php
========================================================= */

async function shareFolder(folderId, folderName)
{
    folderId = parseInt(folderId, 10);

    if (!folderId || folderId <= 0) {
        alert("ID folder tidak valid.");
        return;
    }

    try {

        const response = await fetch(
            "share_folder.php",
            {
                method: "POST",

                headers: {
                    "Content-Type":
                        "application/x-www-form-urlencoded; charset=UTF-8",

                    "X-Requested-With":
                        "XMLHttpRequest"
                },

                credentials: "same-origin",

                body:
                    "folder_id=" +
                    encodeURIComponent(folderId)
            }
        );

        const text = await response.text();

        console.log("SHARE RESPONSE:", text);

        let result;

        try {

            result = JSON.parse(text);

        } catch (e) {

            throw new Error(
                "Server tidak mengirim response JSON yang valid.\n\n" +
                text.substring(0, 1000)
            );
        }

        if (!response.ok || !result.success) {

            throw new Error(
                result.message ||
                result.error ||
                "Gagal membuat link share folder."
            );
        }

        const shareUrl =
            result.url ||
            result.share_url ||
            "";

        if (!shareUrl) {

            throw new Error(
                "Server tidak mengembalikan URL share."
            );
        }

        /*
         * =====================================================
         * TAMPILKAN LINK SHARE
         * =====================================================
         */

        showShareModal(
            folderName || "Folder GosDrive",
            shareUrl
        );

    }
    catch (error) {

        console.error(
            "SHARE FOLDER ERROR:",
            error
        );

        alert(
            "Gagal membuat link share folder.\n\n" +
            error.message
        );
    }
}


/* =========================================================
   MODAL SHARE
========================================================= */

function showShareModal(folderName, shareUrl)
{
    let oldModal =
        document.getElementById("gosdriveShareModal");

    if (oldModal) {
        oldModal.remove();
    }

    const modal = document.createElement("div");

    modal.id = "gosdriveShareModal";

    modal.innerHTML = `
        <div class="gosdrive-share-overlay">

            <div class="gosdrive-share-box">

                <div class="gosdrive-share-header">

                    <div>
                        <i class="fa-solid fa-share-nodes"></i>
                        Bagikan Folder
                    </div>

                    <button
                        type="button"
                        class="gosdrive-share-close"
                        onclick="closeShareModal()">
                        &times;
                    </button>

                </div>

                <div class="gosdrive-share-body">

                    <div class="gosdrive-share-folder">

                        <i class="fa-solid fa-folder-open"></i>

                        <div>
                            <strong>
                                ${escapeShareHtml(folderName)}
                            </strong>

                            <small>
                                Link folder berhasil dibuat
                            </small>
                        </div>

                    </div>

                    <label class="gosdrive-share-label">
                        Link Share
                    </label>

                    <div class="gosdrive-share-input">

                        <input
                            type="text"
                            id="gosdriveShareUrl"
                            value="${escapeShareHtml(shareUrl)}"
                            readonly
                            onclick="this.select()"
                        >

                        <button
                            type="button"
                            onclick="copyShareLink()">

                            <i class="fa-solid fa-copy"></i>
                            Copy

                        </button>

                    </div>

                    <div
                        id="gosdriveShareStatus"
                        class="gosdrive-share-status">
                    </div>

                </div>

                <div class="gosdrive-share-footer">

                    <button
                        type="button"
                        class="gosdrive-btn-secondary"
                        onclick="closeShareModal()">

                        Tutup

                    </button>

                    <button
                        type="button"
                        class="gosdrive-btn-primary"
                        onclick="openShareLink()">

                        <i class="fa-solid fa-arrow-up-right-from-square"></i>
                        Buka Link

                    </button>

                </div>

            </div>

        </div>
    `;

    document.body.appendChild(modal);

    /*
     * Tambahkan CSS hanya sekali
     */

    if (!document.getElementById("gosdriveShareCSS")) {

        const style = document.createElement("style");

        style.id = "gosdriveShareCSS";

        style.textContent = `

            .gosdrive-share-overlay {
                position: fixed;
                inset: 0;
                background: rgba(0,0,0,.55);
                display: flex;
                align-items: center;
                justify-content: center;
                z-index: 99999;
                padding: 20px;
            }

            .gosdrive-share-box {
                width: 100%;
                max-width: 560px;
                background: #fff;
                border-radius: 16px;
                box-shadow: 0 20px 60px rgba(0,0,0,.30);
                overflow: hidden;
                animation: gosdriveShareShow .18s ease;
            }

            @keyframes gosdriveShareShow {
                from {
                    opacity: 0;
                    transform: translateY(15px) scale(.98);
                }

                to {
                    opacity: 1;
                    transform: translateY(0) scale(1);
                }
            }

            .gosdrive-share-header {
                padding: 18px 20px;
                background: #0d6efd;
                color: #fff;
                display: flex;
                align-items: center;
                justify-content: space-between;
                font-size: 18px;
                font-weight: 600;
            }

            .gosdrive-share-header i {
                margin-right: 8px;
            }

            .gosdrive-share-close {
                border: 0;
                background: transparent;
                color: #fff;
                font-size: 28px;
                cursor: pointer;
                line-height: 1;
            }

            .gosdrive-share-body {
                padding: 22px;
            }

            .gosdrive-share-folder {
                display: flex;
                align-items: center;
                gap: 14px;
                margin-bottom: 22px;
            }

            .gosdrive-share-folder > i {
                font-size: 36px;
                color: #ffc107;
            }

            .gosdrive-share-folder strong {
                display: block;
                font-size: 16px;
                word-break: break-word;
            }

            .gosdrive-share-folder small {
                display: block;
                margin-top: 4px;
                color: #6c757d;
            }

            .gosdrive-share-label {
                display: block;
                font-size: 13px;
                font-weight: 600;
                margin-bottom: 7px;
                color: #495057;
            }

            .gosdrive-share-input {
                display: flex;
                gap: 8px;
            }

            .gosdrive-share-input input {
                flex: 1;
                min-width: 0;
                border: 1px solid #ced4da;
                border-radius: 8px;
                padding: 11px 12px;
                font-size: 14px;
                outline: none;
                background: #f8f9fa;
            }

            .gosdrive-share-input input:focus {
                border-color: #0d6efd;
                box-shadow: 0 0 0 3px rgba(13,110,253,.12);
            }

            .gosdrive-share-input button {
                border: 0;
                background: #0d6efd;
                color: #fff;
                border-radius: 8px;
                padding: 0 16px;
                cursor: pointer;
                font-weight: 600;
                white-space: nowrap;
            }

            .gosdrive-share-input button:hover {
                background: #0b5ed7;
            }

            .gosdrive-share-status {
                min-height: 22px;
                margin-top: 10px;
                font-size: 13px;
                color: #198754;
            }

            .gosdrive-share-footer {
                padding: 15px 20px;
                border-top: 1px solid #eee;
                display: flex;
                justify-content: flex-end;
                gap: 8px;
            }

            .gosdrive-btn-secondary,
            .gosdrive-btn-primary {
                border: 0;
                border-radius: 8px;
                padding: 10px 16px;
                cursor: pointer;
                font-weight: 600;
            }

            .gosdrive-btn-secondary {
                background: #e9ecef;
                color: #343a40;
            }

            .gosdrive-btn-primary {
                background: #0d6efd;
                color: #fff;
            }

            .gosdrive-btn-primary:hover {
                background: #0b5ed7;
            }

            @media(max-width: 500px) {

                .gosdrive-share-box {
                    max-width: 100%;
                }

                .gosdrive-share-input {
                    flex-direction: column;
                }

                .gosdrive-share-input button {
                    height: 42px;
                }

                .gosdrive-share-footer {
                    flex-direction: column-reverse;
                }

                .gosdrive-btn-secondary,
                .gosdrive-btn-primary {
                    width: 100%;
                }
            }

        `;

        document.head.appendChild(style);
    }

    /*
     * Fokus ke input
     */

    setTimeout(() => {

        const input =
            document.getElementById("gosdriveShareUrl");

        if (input) {
            input.focus();
            input.select();
        }

    }, 100);
}


/* =========================================================
   COPY SHARE LINK
========================================================= */

async function copyShareLink()
{
    const input =
        document.getElementById("gosdriveShareUrl");

    const status =
        document.getElementById("gosdriveShareStatus");

    if (!input) {
        return;
    }

    const url = input.value;

    try {

        if (navigator.clipboard &&
            window.isSecureContext) {

            await navigator.clipboard.writeText(url);

        } else {

            input.focus();
            input.select();

            document.execCommand("copy");
        }

        if (status) {

            status.innerHTML =
                '<i class="fa-solid fa-circle-check"></i> ' +
                'Link berhasil disalin.';

        }

    }
    catch (error) {

        input.focus();
        input.select();

        if (status) {

            status.innerHTML =
                "Silakan tekan Ctrl+C untuk menyalin link.";

        }
    }
}


/* =========================================================
   BUKA SHARE LINK
========================================================= */

function openShareLink()
{
    const input =
        document.getElementById("gosdriveShareUrl");

    if (!input || !input.value) {
        return;
    }

    window.open(
        input.value,
        "_blank"
    );
}


/* =========================================================
   TUTUP MODAL
========================================================= */

function closeShareModal()
{
    const modal =
        document.getElementById("gosdriveShareModal");

    if (modal) {
        modal.remove();
    }
}


/* =========================================================
   ESCAPE HTML
========================================================= */

function escapeShareHtml(value)
{
    return String(value)
        .replace(/&/g, "&amp;")
        .replace(/</g, "&lt;")
        .replace(/>/g, "&gt;")
        .replace(/"/g, "&quot;")
        .replace(/'/g, "&#039;");
}


/* =========================================================
   DOWNLOAD FOLDER
   Endpoint: download_folder.php
========================================================= */

function downloadFolder(folderId)
{
    folderId = parseInt(folderId, 10);

    if (!folderId || folderId <= 0) {
        alert("ID folder tidak valid.");
        return;
    }

    const button =
        document.querySelector(
            '.folder-card[data-folder-id="' +
            folderId +
            '"] .folder-actions .btn-outline-success'
        );

    if (button && button.disabled) {
        return;
    }

    if (button) {
        button.disabled = true;
        button.innerHTML =
            '<i class="fa fa-spinner fa-spin"></i>';
    }

    const url =
        "download_folder.php?folder_id=" +
        encodeURIComponent(folderId);

    /*
     * Navigasi langsung memungkinkan PHP mengirim
     * header Content-Disposition untuk ZIP.
     */
    window.location.href = url;

    setTimeout(
        function() {
            if (button) {
                button.disabled = false;
                button.innerHTML =
                    '<i class="fa fa-download"></i>';
            }
        },
        5000
    );
}


/* =========================================================
   DOWNLOAD FOLDER TERPILIH
   Endpoint: download_folder.php
========================================================= */

function downloadSelectedFolders()
{
    const selected =
        getSelectedFolderCheckboxes();

    if (selected.length === 0) {
        alert("Silakan pilih minimal satu folder.");
        return;
    }

    const button =
        document.getElementById(
            "downloadSelectedFoldersButton"
        );

    const shareButton =
        document.getElementById(
            "shareSelectedFoldersButton"
        );

    if (button && button.disabled) {
        return;
    }

    const ids =
        selected.map(
            function(item) {
                return item.value;
            }
        );

    const yakin =
        confirm(
            "Download " +
            ids.length +
            " folder sebagai ZIP?"
        );

    if (!yakin) {
        return;
    }

    if (button) {
        button.disabled = true;
        button.innerHTML =
            '<i class="fa fa-spinner fa-spin"></i> Menyiapkan ZIP...';
    }

    if (shareButton) {
        shareButton.disabled = true;
    }

    const form =
        document.createElement("form");

    form.method = "POST";
    form.action = "download_folder.php";
    form.style.display = "none";

    ids.forEach(
        function(id) {
            const input =
                document.createElement("input");

            input.type = "hidden";
            input.name = "folder_ids[]";
            input.value = id;

            form.appendChild(input);
        }
    );

    document.body.appendChild(form);
    form.submit();

    setTimeout(
        function() {
            if (form && form.parentNode) {
                form.remove();
            }
        },
        1000
    );

    setTimeout(
        function() {
            if (button) {
                button.disabled = false;
                button.innerHTML =
                    '<i class="fa fa-download"></i> Download Folder Terpilih';
            }

            if (shareButton) {
                shareButton.disabled = false;
            }
        },
        5000
    );
}


/* =========================================================
   SHARE FOLDER TERPILIH
========================================================= */

async function shareSelectedFolders()
{
    const selected =
        getSelectedFolderCheckboxes();

    if (selected.length === 0) {
        alert("Silakan pilih minimal satu folder.");
        return;
    }

    if (selected.length > 1) {
        alert(
            "Untuk Share, pilih satu folder saja.\n\n" +
            "Jika memilih beberapa folder, gunakan Download Folder Terpilih."
        );
        return;
    }

    const checkbox = selected[0];

    const folderId =
        parseInt(checkbox.value, 10);

    const folderName =
        checkbox.dataset.folderName ||
        "Folder GosDrive";

    await shareFolder(
        folderId,
        folderName
    );
}


/* =========================================================
   COPY TO CLIPBOARD
========================================================= */

async function copyToClipboard(text)
{
    if (navigator.clipboard) {
        await navigator.clipboard.writeText(text);
        return;
    }

    const temp =
        document.createElement("input");

    temp.value = text;

    document.body.appendChild(temp);

    temp.select();

    document.execCommand("copy");

    document.body.removeChild(temp);
}


/* =========================================================
   INIT FOLDER SELECTION
========================================================= */

document.addEventListener(
    "DOMContentLoaded",
    function() {
        updateSelectedFolders();
    }
);


/* =========================================================
   GRID / LIST
========================================================= */

function setView(mode)
{

    const container =
        document.getElementById(
            "fileContainer"
        );


    const buttons =
        document.querySelectorAll(
            ".view-toggle button"
        );


    if (!container) {
        return;
    }


    container.classList.remove(
        "grid-view",
        "list-view"
    );


    document.body.classList.remove(
        "list-mode"
    );


    buttons.forEach(
        function (button) {

            button.classList.remove(
                "active"
            );

        }
    );


    if (mode === "grid") {

        container.classList.add(
            "grid-view"
        );


        if (buttons[0]) {

            buttons[0].classList.add(
                "active"
            );

        }

    }

    else {

        container.classList.add(
            "list-view"
        );


        document.body.classList.add(
            "list-mode"
        );


        if (buttons[1]) {

            buttons[1].classList.add(
                "active"
            );

        }

    }


    localStorage.setItem(
        "viewMode",
        mode
    );

}


/* =========================================================
   LOAD VIEW
========================================================= */

document.addEventListener(
    "DOMContentLoaded",
    function () {

        const saved =
            localStorage.getItem(
                "viewMode"
            );


        if (saved === "list") {

            setView(
                "list"
            );

        }

        else {

            setView(
                "grid"
            );

        }

    }
);



/* =========================================================
   RENAME FOLDER MODAL
========================================================= */

let renameFolderId = null;

let renameFolderOldName = "";


/* =========================================================
   ELEMENT MODAL
========================================================= */

const renameFolderModal =
    document.getElementById(
        "renameFolderModal"
    );


const renameFolderInput =
    document.getElementById(
        "renameFolderInput"
    );


const renameFolderSaveButton =
    document.getElementById(
        "renameFolderSaveButton"
    );


const renameFolderCancelButton =
    document.getElementById(
        "renameFolderCancelButton"
    );


const renameFolderCloseButton =
    document.getElementById(
        "renameFolderCloseButton"
    );


const renameFolderError =
    document.getElementById(
        "renameFolderError"
    );


/* =========================================================
   BUKA MODAL
========================================================= */

function openRenameFolderModal(
    folderId,
    currentName
)
{

    renameFolderId =
        parseInt(
            folderId,
            10
        );


    renameFolderOldName =
        String(
            currentName || ""
        );


    if (
        !renameFolderId ||
        renameFolderId <= 0
    ) {

        alert(
            "ID folder tidak valid."
        );

        return;

    }


    /* =====================================================
       ISI INPUT
    ===================================================== */

    renameFolderInput.value =
        renameFolderOldName;


    /* =====================================================
       RESET ERROR
    ===================================================== */

    renameFolderError.textContent =
        "";

    renameFolderError.style.display =
        "none";


    renameFolderInput.classList.remove(
        "error"
    );


    /* =====================================================
       AKTIFKAN TOMBOL
    ===================================================== */

    renameFolderSaveButton.disabled =
        false;

    renameFolderCancelButton.disabled =
        false;


    renameFolderSaveButton.innerHTML =
        '<i class="fa fa-check"></i> Simpan';


    /* =====================================================
       TAMPILKAN MODAL
    ===================================================== */

    renameFolderModal.style.display =
        "flex";


    document.body.style.overflow =
        "hidden";


    /* =====================================================
       FOCUS + SELECT
    ===================================================== */

    setTimeout(
        function () {

            renameFolderInput.focus();

            renameFolderInput.select();

        },
        50
    );

}


/* =========================================================
   TUTUP MODAL
========================================================= */

function closeRenameFolderModal()
{

    renameFolderModal.style.display =
        "none";


    document.body.style.overflow =
        "";


    renameFolderId =
        null;


    renameFolderOldName =
        "";

}


/* =========================================================
   TAMPILKAN ERROR
========================================================= */

function showRenameFolderError(
    message
)
{

    renameFolderError.textContent =
        message;


    renameFolderError.style.display =
        "block";


    renameFolderInput.classList.add(
        "error"
    );


    renameFolderInput.focus();

}


/* =========================================================
   SIMPAN RENAME
========================================================= */

async function saveRenameFolder()
{

    if (
        !renameFolderId
    ) {

        showRenameFolderError(
            "ID folder tidak valid."
        );

        return;

    }


    const folderName =
        renameFolderInput.value.trim();


    /* =====================================================
       VALIDASI KOSONG
    ===================================================== */

    if (
        folderName === ""
    ) {

        showRenameFolderError(
            "Nama folder tidak boleh kosong."
        );

        return;

    }


    /* =====================================================
       VALIDASI TIDAK BERUBAH
    ===================================================== */

    if (
        folderName ===
        renameFolderOldName
    ) {

        closeRenameFolderModal();

        return;

    }


    /* =====================================================
       DISABLE BUTTON
    ===================================================== */

    renameFolderSaveButton.disabled =
        true;


    renameFolderCancelButton.disabled =
        true;


    renameFolderSaveButton.innerHTML =
        '<i class="fa fa-spinner fa-spin"></i> Menyimpan...';


    renameFolderError.textContent =
        "";

    renameFolderError.style.display =
        "none";


    renameFolderInput.classList.remove(
        "error"
    );


    /* =====================================================
       FORM DATA
    ===================================================== */

    const formData =
        new FormData();


    formData.append(
        "folder_id",
        String(renameFolderId)
    );


    formData.append(
        "folder_name",
        folderName
    );


    try {

        /* =================================================
           REQUEST
        ================================================= */

        const response =
            await fetch(
                "rename_folder.php",
                {

                    method: "POST",

                    body: formData,

                    credentials:
                        "same-origin",

                    headers: {

                        "X-Requested-With":
                            "XMLHttpRequest"

                    }

                }
            );


        /* =================================================
           RESPONSE TEXT
        ================================================= */

        const text =
            await response.text();


        let result;


        try {

            result =
                JSON.parse(
                    text
                );

        }

        catch (jsonError) {

            console.error(
                "Rename Folder JSON Error:",
                jsonError
            );


            throw new Error(
                "Server tidak mengirim response JSON yang valid."
            );

        }


        /* =================================================
           VALIDASI RESPONSE
        ================================================= */

        if (
            !response.ok ||
            !result.success
        ) {

            throw new Error(

                result.message ||
                result.error ||
                "Gagal mengubah nama folder."

            );

        }


        /* =================================================
           NAMA BARU DARI SERVER
        ================================================= */

        const newFolderName =
            result.folder_name ||
            folderName;


        /* =================================================
           UPDATE FOLDER DI HALAMAN
           TANPA RELOAD
        ================================================= */

        const folderCard =
            document.querySelector(
                '.folder-card[data-folder-id="' +
                renameFolderId +
                '"]'
            );


        if (folderCard) {

            const folderNameElement =
                folderCard.querySelector(
                    ".folder-name"
                );


            if (folderNameElement) {

                folderNameElement.textContent =
                    newFolderName;


                folderNameElement.title =
                    newFolderName;

            }

        }


        /* =================================================
           UPDATE ELEMEN LAIN YANG MUNGKIN
        ================================================= */

        document
            .querySelectorAll(
                '[data-folder-id="' +
                renameFolderId +
                '"] .folder-name'
            )
            .forEach(
                function (element) {

                    element.textContent =
                        newFolderName;


                    element.title =
                        newFolderName;

                }
            );


        /* =================================================
           TUTUP MODAL
        ================================================= */

        closeRenameFolderModal();


        /* =================================================
           NOTIFIKASI
        ================================================= */

        showRenameFolderToast(
            "Folder berhasil diubah menjadi \"" +
            newFolderName +
            "\"."
        );


    }

    catch (error) {

        console.error(
            "RENAME FOLDER ERROR:",
            error
        );


        showRenameFolderError(
            error.message ||
            "Terjadi kesalahan saat mengubah nama folder."
        );


        renameFolderSaveButton.disabled =
            false;


        renameFolderCancelButton.disabled =
            false;


        renameFolderSaveButton.innerHTML =
            '<i class="fa fa-check"></i> Simpan';

    }

}


/* =========================================================
   ENTER = SIMPAN
   ESC = BATAL
========================================================= */

renameFolderInput.addEventListener(
    "keydown",
    function (event) {

        if (
            event.key === "Enter"
        ) {

            event.preventDefault();

            saveRenameFolder();

        }


        if (
            event.key === "Escape"
        ) {

            event.preventDefault();

            closeRenameFolderModal();

        }

    }
);


/* =========================================================
   TOMBOL SIMPAN
========================================================= */

renameFolderSaveButton.addEventListener(
    "click",
    function () {

        saveRenameFolder();

    }
);


/* =========================================================
   TOMBOL BATAL
========================================================= */

renameFolderCancelButton.addEventListener(
    "click",
    function () {

        closeRenameFolderModal();

    }
);


/* =========================================================
   TOMBOL X
========================================================= */

renameFolderCloseButton.addEventListener(
    "click",
    function () {

        closeRenameFolderModal();

    }
);


/* =========================================================
   KLIK DI LUAR MODAL = TUTUP
========================================================= */

renameFolderModal.addEventListener(
    "click",
    function (event) {

        if (
            event.target ===
            renameFolderModal
        ) {

            closeRenameFolderModal();

        }

    }
);


/* =========================================================
   TOAST
========================================================= */

function showRenameFolderToast(
    message
)
{

    let toast =
        document.getElementById(
            "renameFolderToast"
        );


    if (!toast) {

        toast =
            document.createElement(
                "div"
            );


        toast.id =
            "renameFolderToast";


        toast.style.position =
            "fixed";


        toast.style.right =
            "20px";


        toast.style.bottom =
            "20px";


        toast.style.zIndex =
            "100000";


        toast.style.background =
            "#202124";


        toast.style.color =
            "#fff";


        toast.style.padding =
            "12px 16px";


        toast.style.borderRadius =
            "8px";


        toast.style.boxShadow =
            "0 5px 20px rgba(0,0,0,.2)";


        toast.style.fontSize =
            "14px";


        toast.style.maxWidth =
            "calc(100vw - 40px)";


        toast.style.transition =
            "opacity .2s";


        document.body.appendChild(
            toast
        );

    }


    toast.textContent =
        message;


    toast.style.opacity =
        "1";


    clearTimeout(
        toast._timer
    );


    toast._timer =
        setTimeout(
            function () {

                toast.style.opacity =
                    "0";

            },
            2500
        );

}

/* =========================================================
   DOWNLOAD SELECTED FILES
========================================================= */

function downloadSelected()
{

    /* =====================================================
       AMBIL FILE TERPILIH
    ===================================================== */

    const selected =
        Array.from(
            document.querySelectorAll(
                ".file-check:checked"
            )
        );


    /* =====================================================
       VALIDASI
    ===================================================== */

    if (selected.length === 0) {

        alert(
            "Silakan pilih minimal satu file."
        );

        return;

    }


    /* =====================================================
       AMBIL TOMBOL
    ===================================================== */

    const downloadButton =
        document.getElementById(
            "downloadSelectedButton"
        );


    const deleteButton =
        document.getElementById(
            "deleteSelectedButton"
        );


    /* =====================================================
       CEGAH KLIK GANDA
    ===================================================== */

    if (
        downloadButton &&
        downloadButton.disabled
    ) {

        return;

    }


    /* =====================================================
       AMBIL ID FILE
    ===================================================== */

    const ids =
        selected.map(
            function (item) {

                return item.value;

            }
        );


    /* =====================================================
       UBAH TOMBOL DOWNLOAD
    ===================================================== */

    if (downloadButton) {

        downloadButton.disabled =
            true;


        downloadButton.innerHTML =
            '<i class="fa fa-spinner fa-spin"></i> ' +
            'Menyiapkan ZIP...';

    }


    /* =====================================================
       NONAKTIFKAN TOMBOL HAPUS
    ===================================================== */

    if (deleteButton) {

        deleteButton.disabled =
            true;

    }


    /* =====================================================
       BUAT FORM
    ===================================================== */

    const form =
        document.createElement(
            "form"
        );


    form.method =
        "POST";


    form.action =
        "download_zip.php";


    form.style.display =
        "none";


    /* =====================================================
       MASUKKAN ID FILE
    ===================================================== */

    ids.forEach(
        function (id) {

            const input =
                document.createElement(
                    "input"
                );


            input.type =
                "hidden";


            input.name =
                "ids[]";


            input.value =
                id;


            form.appendChild(
                input
            );

        }
    );


    /* =====================================================
       KIRIM FORM
    ===================================================== */

    document.body.appendChild(
        form
    );


    form.submit();


    /* =====================================================
       BERSIHKAN FORM
    ===================================================== */

    setTimeout(
        function () {

            if (
                form &&
                form.parentNode
            ) {

                form.remove();

            }

        },
        1000
    );


    /*
     * Jangan langsung mengaktifkan tombol kembali.
     *
     * Karena browser masih memproses download ZIP.
     *
     * Tombol tetap disabled selama beberapa detik
     * agar pengguna tidak melakukan klik berulang.
     */

    setTimeout(
        function () {

            if (downloadButton) {

                downloadButton.disabled =
                    false;


                downloadButton.innerHTML =
                    '<i class="fa fa-download"></i> ' +
                    'Download Terpilih';

            }


            if (deleteButton) {

                deleteButton.disabled =
                    false;

            }

        },
        5000
    );

}

</script>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
</body>

</html>