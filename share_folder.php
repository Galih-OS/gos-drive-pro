<?php

declare(strict_types=1);

/* =========================================================
   GOSDRIVE - SHARE FOLDER

   DEFAULT:
       ?token=XXXX
       -> TAMPILKAN HALAMAN PREVIEW FOLDER

   PREVIEW FILE:
       ?token=XXXX&action=preview&id=123

   DOWNLOAD FILE:
       ?token=XXXX&action=download&id=123

   DOWNLOAD SEMUA ZIP:
       ?token=XXXX&action=download_zip

   DOWNLOAD FILE TERPILIH:
       POST token + action=download_selected + selected_ids[]
   ========================================================= */

ini_set('display_errors', '0');
ini_set('display_startup_errors', '0');
error_reporting(E_ALL);
set_time_limit(0);

if (ob_get_level() === 0) {
    ob_start();
}

require 'config/db.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');

function clearOutputBuffers(): void
{
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
}

function jsonResponse(
    bool $success,
    string $message = '',
    array $extra = [],
    int $statusCode = 200
): never {
    clearOutputBuffers();
    http_response_code($statusCode);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(
        array_merge(
            ['success' => $success, 'message' => $message],
            $extra
        ),
        JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
    );
    exit;
}

function getBaseUrl(): string
{
    $https =
        (
            isset($_SERVER['HTTPS']) &&
            $_SERVER['HTTPS'] !== 'off'
        ) ||
        (
            isset($_SERVER['HTTP_X_FORWARDED_PROTO']) &&
            strtolower((string)$_SERVER['HTTP_X_FORWARDED_PROTO']) === 'https'
        );

    $scheme = $https ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $script = $_SERVER['SCRIPT_NAME'] ?? '/share_folder.php';
    $directory = str_replace('\\', '/', dirname($script));

    if ($directory === '/' || $directory === '.' || $directory === '\\') {
        $directory = '';
    }

    return $scheme . '://' . $host . rtrim($directory, '/');
}

function safeFileName(string $name): string
{
    $name = basename($name);
    $name = preg_replace('/[<>:"\/\\|?*\x00-\x1F]/u', '_', $name);
    $name = trim((string)$name, ". ");

    return $name !== '' ? $name : 'file';
}

function html(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

function formatBytes(int|float|null $bytes): string
{
    if ($bytes === null || $bytes < 0) {
        return '-';
    }

    $units = ['B', 'KB', 'MB', 'GB', 'TB'];
    $i = 0;
    $size = (float)$bytes;

    while ($size >= 1024 && $i < count($units) - 1) {
        $size /= 1024;
        $i++;
    }

    return number_format($size, $i === 0 ? 0 : 2, ',', '.') . ' ' . $units[$i];
}

function mimeFromFile(string $path, string $filename = ''): string
{
    $mime = '';

    if (function_exists('finfo_open') && is_file($path)) {
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        if ($finfo !== false) {
            $detected = finfo_file($finfo, $path);
            finfo_close($finfo);
            if (is_string($detected)) {
                $mime = $detected;
            }
        }
    }

    if ($mime === '' || $mime === 'application/octet-stream') {
        $ext = strtolower(pathinfo($filename !== '' ? $filename : $path, PATHINFO_EXTENSION));
        $map = [
            'pdf' => 'application/pdf',
            'txt' => 'text/plain',
            'csv' => 'text/csv',
            'html' => 'text/html',
            'htm' => 'text/html',
            'jpg' => 'image/jpeg',
            'jpeg' => 'image/jpeg',
            'png' => 'image/png',
            'gif' => 'image/gif',
            'webp' => 'image/webp',
            'svg' => 'image/svg+xml',
            'mp4' => 'video/mp4',
            'webm' => 'video/webm',
            'ogg' => 'video/ogg',
            'mp3' => 'audio/mpeg',
            'wav' => 'audio/wav',
            'm4a' => 'audio/mp4',
        ];
        $mime = $map[$ext] ?? 'application/octet-stream';
    }

    return $mime;
}

function resolveFilePath(string $filepath): ?string
{
    $filepath = trim($filepath);

    if ($filepath === '') {
        return null;
    }

    if (is_file($filepath)) {
        return $filepath;
    }

    $alternativePath = str_replace('/', '\\', $filepath);
    if (is_file($alternativePath)) {
        return $alternativePath;
    }

    return null;
}

function sendFile(string $path, string $filename, bool $download): never
{
    if (!is_file($path) || !is_readable($path)) {
        clearOutputBuffers();
        http_response_code(404);
        header('Content-Type: text/plain; charset=utf-8');
        exit('File tidak tersedia.');
    }

    $size = filesize($path);
    $mime = mimeFromFile($path, $filename);

    clearOutputBuffers();

    http_response_code(200);
    header('Content-Type: ' . $mime);
    header('Content-Length: ' . (string)($size === false ? 0 : $size));
    header('X-Content-Type-Options: nosniff');
    header('Accept-Ranges: bytes');
    header('Cache-Control: private, max-age=0, must-revalidate');

    $safeName = safeFileName($filename);
    $disposition = $download ? 'attachment' : 'inline';
    header("Content-Disposition: {$disposition}; filename=\"{$safeName}\"");

    $handle = fopen($path, 'rb');
    if ($handle === false) {
        http_response_code(500);
        exit;
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
}

function renderErrorPage(string $title, string $message, int $status = 400): never
{
    clearOutputBuffers();
    http_response_code($status);
    header('Content-Type: text/html; charset=utf-8');
    ?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="shortcut icon" href="ghostdrive.png">
    <title><?= html($title) ?> - GosDrive</title>
    <style>
        *{box-sizing:border-box}
        body{margin:0;min-height:100vh;display:flex;align-items:center;justify-content:center;font-family:Arial,sans-serif;background:linear-gradient(135deg,#111827,#312e81);color:#fff}
        .box{width:430px;max-width:calc(100% - 30px);padding:40px;text-align:center;border-radius:22px;background:rgba(255,255,255,.10);backdrop-filter:blur(15px);box-shadow:0 20px 60px rgba(0,0,0,.25)}
        .icon{font-size:55px;margin-bottom:18px}.box h2{margin:0 0 10px}.box p{opacity:.8;line-height:1.6}
    </style>
</head>
<body>
<div class="box">
    <div class="icon">🔗</div>
    <h2><?= html($title) ?></h2>
    <p><?= html($message) ?></p>
</div>


</body>
</html>
<?php
    exit;
}

/* =========================================================
   PARAMETER
   ========================================================= */

$token = trim((string)($_GET['token'] ?? $_POST['token'] ?? ''));
$action = trim((string)($_GET['action'] ?? $_POST['action'] ?? ''));
$fileId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$selectedIds = isset($_POST['selected_ids']) && is_array($_POST['selected_ids'])
    ? array_values(array_unique(array_map('intval', $_POST['selected_ids'])))
    : [];

/* =========================================================
   POST - MEMBUAT LINK SHARE
   ========================================================= */

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action !== 'download_selected') {
    $requestedWith = $_SERVER['HTTP_X_REQUESTED_WITH'] ?? '';

    if (strtolower($requestedWith) !== 'xmlhttprequest') {
        jsonResponse(false, 'Permintaan tidak valid.', [], 400);
    }

    if (
        !isset($_SESSION['user']) ||
        !is_array($_SESSION['user']) ||
        !isset($_SESSION['user']['id'])
    ) {
        jsonResponse(false, 'Anda belum login.', [], 401);
    }

    $userId = (int)$_SESSION['user']['id'];
    $folderId = isset($_POST['folder_id']) ? (int)$_POST['folder_id'] : 0;

    if ($folderId <= 0) {
        jsonResponse(false, 'ID folder tidak valid.', [], 400);
    }

    $stmt = $pdo->prepare("
        SELECT id, user_id, parent_id, folder_name, share_token
        FROM folders
        WHERE id = ? AND user_id = ?
        LIMIT 1
    ");
    $stmt->execute([$folderId, $userId]);
    $folder = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$folder) {
        jsonResponse(false, 'Folder tidak ditemukan atau bukan milik Anda.', [], 404);
    }

    $shareToken = trim((string)($folder['share_token'] ?? ''));

    if ($shareToken === '') {
        $shareToken = bin2hex(random_bytes(32));

        $stmtToken = $pdo->prepare("
            UPDATE folders
            SET share_token = ?
            WHERE id = ? AND user_id = ?
        ");
        $stmtToken->execute([$shareToken, $folderId, $userId]);
    }

    $shareUrl = getBaseUrl() . '/share_folder.php?token=' . rawurlencode($shareToken);

    jsonResponse(true, 'Link share folder berhasil dibuat.', [
        'url' => $shareUrl,
        'share_url' => $shareUrl,
        'folder_id' => $folderId,
        'folder_name' => $folder['folder_name']
    ]);
}

if ($_SERVER['REQUEST_METHOD'] !== 'GET' && $action !== 'download_selected') {
    jsonResponse(false, 'Method request tidak diperbolehkan.', [], 405);
}

if ($token === '') {
    renderErrorPage('Link Tidak Valid', 'Link share folder tidak ditemukan atau tidak lengkap.', 400);
}

/* =========================================================
   CARI ROOT FOLDER BERDASARKAN TOKEN
   ========================================================= */

$stmt = $pdo->prepare("
    SELECT id, user_id, parent_id, folder_name, share_token
    FROM folders
    WHERE share_token = ?
    LIMIT 1
");
$stmt->execute([$token]);
$rootFolder = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$rootFolder) {
    renderErrorPage('Folder Tidak Ditemukan', 'Link share mungkin sudah tidak berlaku atau folder telah dihapus.', 404);
}

$rootFolderId = (int)$rootFolder['id'];
$rootUserId = (int)$rootFolder['user_id'];
$rootFolderName = safeFileName((string)$rootFolder['folder_name']);

/* =========================================================
   KUMPULKAN SEMUA FOLDER DI DALAM ROOT
   ========================================================= */

$folderIds = [$rootFolderId];
$processed = [];
$index = 0;

while (isset($folderIds[$index])) {
    $currentFolderId = (int)$folderIds[$index];
    $index++;

    if (isset($processed[$currentFolderId])) {
        continue;
    }

    $processed[$currentFolderId] = true;

    $stmtChildren = $pdo->prepare("
        SELECT id
        FROM folders
        WHERE user_id = ? AND parent_id = ?
        ORDER BY folder_name ASC
    ");
    $stmtChildren->execute([$rootUserId, $currentFolderId]);

    $children = $stmtChildren->fetchAll(PDO::FETCH_COLUMN);

    foreach ($children as $childId) {
        $childId = (int)$childId;

        if (!isset($processed[$childId]) && !in_array($childId, $folderIds, true)) {
            $folderIds[] = $childId;
        }
    }
}

/* =========================================================
   AMBIL FILE DALAM FOLDER SHARE
   ========================================================= */

$placeholders = implode(',', array_fill(0, count($folderIds), '?'));
$params = array_merge([$rootUserId], $folderIds);

$stmtFiles = $pdo->prepare("
    SELECT id, filename, filepath, folder_id
    FROM files
    WHERE user_id = ?
      AND folder_id IN ($placeholders)
    ORDER BY filename ASC
");
$stmtFiles->execute($params);
$files = $stmtFiles->fetchAll(PDO::FETCH_ASSOC);

/* =========================================================
   INDEX FILE UNTUK AKSES PREVIEW / DOWNLOAD
   ========================================================= */

$fileMap = [];
foreach ($files as $file) {
    $fileMap[(int)$file['id']] = $file;
}

/* =========================================================
   PREVIEW / DOWNLOAD SATU FILE
   ========================================================= */

if ($action === 'preview' || $action === 'download') {
    if ($fileId <= 0 || !isset($fileMap[$fileId])) {
        renderErrorPage('File Tidak Ditemukan', 'File tidak ditemukan di dalam folder yang dibagikan.', 404);
    }

    $file = $fileMap[$fileId];
    $path = resolveFilePath((string)$file['filepath']);

    if ($path === null) {
        renderErrorPage('File Tidak Tersedia', 'File ada di database tetapi tidak dapat ditemukan di penyimpanan.', 404);
    }

    sendFile(
        $path,
        (string)$file['filename'],
        $action === 'download'
    );
}

/* =========================================================
   DOWNLOAD SEMUA FILE SEBAGAI ZIP
   ========================================================= */

if ($action === 'download_zip' || $action === 'download_selected') {
    if (!class_exists('ZipArchive')) {
        renderErrorPage('Fitur ZIP Tidak Tersedia', 'Extension PHP ZipArchive belum aktif di XAMPP.', 500);
    }

    $tempDirectory = sys_get_temp_dir();

    if (!is_dir($tempDirectory) || !is_writable($tempDirectory)) {
        renderErrorPage('Gagal Membuat ZIP', 'Folder temporary PHP tidak dapat ditulis.', 500);
    }

    $zipFile = tempnam($tempDirectory, 'gosdrive_');

    if ($zipFile === false) {
        renderErrorPage('Gagal Membuat ZIP', 'Tidak dapat membuat file sementara.', 500);
    }

    $zip = new ZipArchive();
    $openResult = $zip->open($zipFile, ZipArchive::CREATE | ZipArchive::OVERWRITE);

    if ($openResult !== true) {
        @unlink($zipFile);
        renderErrorPage('Gagal Membuat ZIP', 'Tidak dapat membuka file ZIP. Error code: ' . (string)$openResult, 500);
    }

    /*
     * Download Semua  -> gunakan semua file.
     * Download Terpilih -> hanya gunakan file yang dicentang.
     * File ID tetap diverifikasi terhadap $fileMap, sehingga pengguna
     * tidak dapat memasukkan ID file di luar folder share.
     */
    $filesToZip = $files;

    if ($action === 'download_selected') {
        if ($selectedIds === []) {
            @unlink($zipFile);
            renderErrorPage('Tidak Ada File Dipilih', 'Silakan pilih minimal satu file untuk diunduh.', 400);
        }

        $filesToZip = [];
        foreach ($selectedIds as $selectedId) {
            if (isset($fileMap[$selectedId])) {
                $filesToZip[] = $fileMap[$selectedId];
            }
        }

        if ($filesToZip === []) {
            @unlink($zipFile);
            renderErrorPage('File Tidak Ditemukan', 'File yang dipilih tidak ditemukan atau tidak termasuk dalam folder share.', 404);
        }
    }

    $folderPathCache = [$rootFolderId => $rootFolderName];
    $addedFiles = 0;

    foreach ($filesToZip as $file) {
        $realPath = resolveFilePath((string)($file['filepath'] ?? ''));

        if ($realPath === null) {
            continue;
        }

        $fileName = safeFileName((string)$file['filename']);
        $folderId = (int)$file['folder_id'];

        if (isset($folderPathCache[$folderId])) {
            $relativeFolder = $folderPathCache[$folderId];
        } else {
            $chain = [];
            $currentId = $folderId;
            $guard = 0;

            while ($currentId !== $rootFolderId && $currentId > 0 && $guard < 100) {
                $guard++;

                $stmtParent = $pdo->prepare("
                    SELECT id, parent_id, folder_name
                    FROM folders
                    WHERE id = ? AND user_id = ?
                    LIMIT 1
                ");
                $stmtParent->execute([$currentId, $rootUserId]);
                $parentFolder = $stmtParent->fetch(PDO::FETCH_ASSOC);

                if (!$parentFolder) {
                    break;
                }

                $chain[] = safeFileName((string)$parentFolder['folder_name']);
                $currentId = (int)($parentFolder['parent_id'] ?? 0);
            }

            if ($currentId === $rootFolderId) {
                $chain[] = $rootFolderName;
                $chain = array_reverse($chain);
                $relativeFolder = implode('/', $chain);
            } else {
                $relativeFolder = $rootFolderName;
            }

            $folderPathCache[$folderId] = $relativeFolder;
        }

        $zipPath = trim($relativeFolder . '/' . $fileName, '/');

        if ($zip->addFile($realPath, $zipPath)) {
            $addedFiles++;
        }
    }

    $closeResult = $zip->close();

    if (!$closeResult) {
        @unlink($zipFile);
        renderErrorPage('Gagal Membuat ZIP', 'Gagal menyelesaikan file ZIP.', 500);
    }

    if (!is_file($zipFile)) {
        renderErrorPage('Gagal Membuat ZIP', 'File ZIP tidak berhasil dibuat.', 500);
    }

    $zipSize = filesize($zipFile);

    if ($zipSize === false || $zipSize < 22) {
        @unlink($zipFile);
        renderErrorPage('ZIP Kosong', 'File ZIP kosong atau rusak.', 500);
    }

    if ($addedFiles === 0) {
        @unlink($zipFile);
        renderErrorPage('Folder Kosong', 'Tidak ada file yang dapat dimasukkan ke ZIP.', 404);
    }

    $downloadName = safeFileName((string)$rootFolder['folder_name']);
    if ($action === 'download_selected') {
        $downloadName .= '-selected';
    }
    $downloadName .= '.zip';

    clearOutputBuffers();

    http_response_code(200);
    header('Content-Type: application/zip');
    header('Content-Disposition: attachment; filename="' . $downloadName . '"');
    header('Content-Length: ' . (string)$zipSize);
    header('Content-Transfer-Encoding: binary');
    header('Accept-Ranges: bytes');
    header('X-Content-Type-Options: nosniff');
    header('Cache-Control: no-store, no-cache, must-revalidate');
    header('Pragma: no-cache');

    $handle = fopen($zipFile, 'rb');

    if ($handle === false) {
        @unlink($zipFile);
        exit;
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
    @unlink($zipFile);
    exit;
}

/* =========================================================
   DEFAULT = PREVIEW FOLDER
   PENTING: token saja TIDAK BOLEH DOWNLOAD ZIP.
   ========================================================= */

clearOutputBuffers();
header('Content-Type: text/html; charset=utf-8');

$totalFiles = count($files);
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex,nofollow">
    <link rel="shortcut icon" href="ghostdrive.png">
    <title><?= html($rootFolderName) ?> - GosDrive</title>
    <style>
        *{box-sizing:border-box}
        body{margin:0;min-height:100vh;font-family:Arial,Helvetica,sans-serif;background:linear-gradient(135deg,#0f172a,#1e3a8a);color:#172033}
        .page{width:min(1050px,calc(100% - 30px));margin:40px auto;padding-bottom:40px}
        .card{background:rgba(255,255,255,.97);border-radius:24px;box-shadow:0 25px 70px rgba(0,0,0,.25);overflow:hidden}
        .header{padding:28px 30px;background:linear-gradient(135deg,#2563eb,#1d4ed8);color:#fff}
        .brand{font-size:14px;font-weight:700;letter-spacing:.5px;opacity:.9;margin-bottom:14px}
        .folder-title{display:flex;align-items:center;gap:14px}
        .folder-icon{font-size:42px}.folder-title h1{margin:0;font-size:28px;word-break:break-word}.folder-title p{margin:6px 0 0;opacity:.85}
        .toolbar{display:flex;justify-content:space-between;align-items:center;gap:15px;padding:20px 30px;border-bottom:1px solid #e5e7eb;flex-wrap:wrap}
        .count{color:#64748b;font-size:14px}
        .selection-info{margin-top:5px;color:#2563eb;font-size:13px;font-weight:700}
        .toolbar-actions{display:flex;align-items:center;gap:8px;flex-wrap:wrap;justify-content:flex-end}
        .tool-btn{border:0;cursor:pointer;display:inline-flex;align-items:center;justify-content:center;gap:7px;padding:10px 13px;border-radius:10px;font-size:13px;font-weight:700}
        .select-all{background:#dbeafe;color:#1d4ed8}
        .clear-select{background:#f1f5f9;color:#475569}
        .selected-download{background:#16a34a;color:#fff}
        .selected-download:disabled{background:#cbd5e1;color:#64748b;cursor:not-allowed}
        .zip-btn{display:inline-flex;align-items:center;gap:8px;text-decoration:none;background:#111827;color:#fff;padding:11px 17px;border-radius:10px;font-weight:700;font-size:14px}
        .zip-btn:hover{background:#000}
        .files{padding:12px 20px 25px}
        .file{display:flex;align-items:center;gap:12px;padding:16px 10px;border-bottom:1px solid #edf0f4}
        .file.is-selected{background:#eff6ff;border-radius:12px}
        .check-wrap{width:28px;display:flex;align-items:center;justify-content:center;flex:none;cursor:pointer}
        .file-check{width:19px;height:19px;cursor:pointer;accent-color:#2563eb}
        .file-icon{width:44px;height:44px;display:flex;align-items:center;justify-content:center;border-radius:12px;background:#eff6ff;font-size:22px;flex:none}
        .file:last-child{border-bottom:0}
        .file-info{min-width:0;flex:1}.file-name{font-weight:700;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}.file-meta{font-size:12px;color:#64748b;margin-top:5px}
        .actions{display:flex;gap:8px;flex:none;flex-wrap:wrap;justify-content:flex-end}
        .btn{display:inline-flex;align-items:center;gap:6px;text-decoration:none;padding:9px 12px;border-radius:9px;font-size:13px;font-weight:700}
        .preview{background:#dbeafe;color:#1d4ed8}.download{background:#f1f5f9;color:#334155}
        .empty{text-align:center;padding:55px 20px;color:#64748b}.empty-icon{font-size:50px;margin-bottom:10px}
        .footer{text-align:center;color:rgba(255,255,255,.75);font-size:12px;margin-top:18px}
        @media(max-width:650px){.page{width:calc(100% - 18px);margin:18px auto}.header{padding:22px 20px}.toolbar{padding:16px 20px}.files{padding:8px 12px 18px}.file{align-items:flex-start}.actions{width:100%;padding-left:59px;justify-content:flex-start}.folder-title h1{font-size:22px}.zip-btn{width:100%;justify-content:center}}
    </style>
</head>
<body>
<div class="page">
    <div class="card">
        <div class="header">
            <div class="brand">GosDrive · Shared Folder</div>
            <div class="folder-title">
                <div class="folder-icon">📁</div>
                <div>
                    <h1><?= html($rootFolderName) ?></h1>
                    <p>Folder dibagikan melalui GosDrive</p>
                </div>
            </div>
        </div>

        <form id="bulkForm" method="post" action="">
            <input type="hidden" name="token" value="<?= html($token) ?>">
            <input type="hidden" name="action" value="download_selected">

            <div class="toolbar">
                <div>
                    <div class="count">📄 <?= $totalFiles ?> file tersedia</div>
                    <?php if ($totalFiles > 0): ?>
                        <div class="selection-info"><span id="selectedCount">0</span> file dipilih</div>
                    <?php endif; ?>
                </div>

                <div class="toolbar-actions">
                    <?php if ($totalFiles > 0): ?>
                        <button type="button" class="tool-btn select-all" id="selectAllBtn">☑ Pilih Semua</button>
                        <button type="button" class="tool-btn clear-select" id="clearSelectBtn">☐ Batal Pilih</button>
                        <button type="submit" class="tool-btn selected-download" id="downloadSelectedBtn" disabled>⬇ Download Terpilih</button>
                        <a class="zip-btn" href="?token=<?= rawurlencode($token) ?>&action=download_zip">📦 Download Semua ZIP</a>
                    <?php endif; ?>
                </div>
            </div>

            <div class="files">
            <?php if ($totalFiles === 0): ?>
                <div class="empty">
                    <div class="empty-icon">📂</div>
                    <strong>Folder masih kosong</strong>
                    <div>Tidak ada file yang dapat ditampilkan.</div>
                </div>
            <?php else: ?>
                <?php foreach ($files as $file):
                    $id = (int)$file['id'];
                    $name = (string)$file['filename'];
                    $path = resolveFilePath((string)$file['filepath']);
                    $size = $path !== null && is_file($path) ? filesize($path) : null;
                    $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
                    $icon = '📄';

                    if (in_array($ext, ['jpg','jpeg','png','gif','webp','svg'], true)) $icon = '🖼️';
                    elseif ($ext === 'pdf') $icon = '📕';
                    elseif (in_array($ext, ['mp4','webm','ogg'], true)) $icon = '🎬';
                    elseif (in_array($ext, ['mp3','wav','m4a'], true)) $icon = '🎵';
                    elseif (in_array($ext, ['xls','xlsx','csv'], true)) $icon = '📊';
                    elseif (in_array($ext, ['doc','docx'], true)) $icon = '📝';
                    elseif (in_array($ext, ['zip','rar','7z'], true)) $icon = '🗜️';
                ?>
                    <div class="file">
                        <label class="check-wrap" title="Pilih file ini">
                            <input type="checkbox" class="file-check" name="selected_ids[]" value="<?= $id ?>">
                            <span class="checkmark"></span>
                        </label>
                        <div class="file-icon"><?= $icon ?></div>
                        <div class="file-info">
                            <div class="file-name" title="<?= html($name) ?>"><?= html($name) ?></div>
                            <div class="file-meta">
                                <?= $ext !== '' ? strtoupper($ext) : 'FILE' ?>
                                · <?= formatBytes($size) ?>
                            </div>
                        </div>
                        <div class="actions">
                            <a class="btn preview" target="_blank" href="?token=<?= rawurlencode($token) ?>&action=preview&id=<?= $id ?>">👁 Preview</a>
                            <a class="btn download" href="?token=<?= rawurlencode($token) ?>&action=download&id=<?= $id ?>">⬇ Download</a>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
            </div>
        </form>
    </div>

    <div class="footer">GosDrive · Secure shared files</div>
</div>

<script>
(function(){
    const form = document.getElementById('bulkForm');
    if (!form) return;

    const checks = Array.from(document.querySelectorAll('.file-check'));
    const count = document.getElementById('selectedCount');
    const downloadBtn = document.getElementById('downloadSelectedBtn');
    const selectAllBtn = document.getElementById('selectAllBtn');
    const clearSelectBtn = document.getElementById('clearSelectBtn');

    function updateSelection(){
        const selected = checks.filter(c => c.checked);
        if (count) count.textContent = selected.length;
        if (downloadBtn) downloadBtn.disabled = selected.length === 0;
        checks.forEach(c => {
            const row = c.closest('.file');
            if (row) row.classList.toggle('is-selected', c.checked);
        });
    }

    checks.forEach(c => c.addEventListener('change', updateSelection));

    if (selectAllBtn) {
        selectAllBtn.addEventListener('click', function(){
            checks.forEach(c => c.checked = true);
            updateSelection();
        });
    }

    if (clearSelectBtn) {
        clearSelectBtn.addEventListener('click', function(){
            checks.forEach(c => c.checked = false);
            updateSelection();
        });
    }

    form.addEventListener('submit', function(e){
        const selected = checks.filter(c => c.checked);
        if (selected.length === 0) {
            e.preventDefault();
            alert('Silakan pilih minimal satu file untuk diunduh.');
            return;
        }
        if (downloadBtn) {
            downloadBtn.disabled = true;
            downloadBtn.textContent = '⏳ Menyiapkan ZIP...';
        }
    });

    updateSelection();
})();
</script>
</body>
</html>
<?php
exit;
