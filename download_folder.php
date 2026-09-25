<?php
require 'config/db.php';

if (session_status() === PHP_SESSION_NONE) session_start();

if (!isset($_SESSION['user'])) {
    http_response_code(401);
    exit('Anda belum login.');
}

$user_id = (int)$_SESSION['user']['id'];

if (!class_exists('ZipArchive')) {
    http_response_code(500);
    exit('Fitur ZIP belum tersedia pada server PHP. Aktifkan extension=zip pada php.ini XAMPP.');
}

/*
 * Menerima:
 * GET  ?folder_id=123
 * POST folder_ids[]=123&folder_ids[]=456
 */
$folderIds = [];

if (isset($_GET['folder_id'])) {
    $id = (int)$_GET['folder_id'];
    if ($id > 0) $folderIds[] = $id;
}

if (isset($_POST['folder_ids']) && is_array($_POST['folder_ids'])) {
    foreach ($_POST['folder_ids'] as $id) {
        $id = (int)$id;
        if ($id > 0) $folderIds[] = $id;
    }
}

$folderIds = array_values(array_unique($folderIds));

if (!$folderIds) {
    http_response_code(400);
    exit('Tidak ada folder yang dipilih.');
}

if (count($folderIds) > 100) {
    http_response_code(400);
    exit('Maksimal 100 folder dapat didownload sekaligus.');
}

function cleanZipName($name)
{
    $name = trim((string)$name);
    $name = preg_replace('/[\\\\\/:*?"<>|]+/u', '_', $name);
    $name = preg_replace('/\s+/u', ' ', $name);
    $name = trim($name, ". ");
    return $name !== '' ? $name : 'Folder';
}

function getFolder($pdo, $userId, $id)
{
    $s = $pdo->prepare("
        SELECT id, parent_id, folder_name
        FROM folders
        WHERE id = ? AND user_id = ?
        LIMIT 1
    ");
    $s->execute([$id, $userId]);
    return $s->fetch(PDO::FETCH_ASSOC);
}

function getDescendantIds($pdo, $userId, $rootId)
{
    $all = [$rootId];
    $queue = [$rootId];

    while ($queue) {
        $parent = (int)array_shift($queue);

        $s = $pdo->prepare("
            SELECT id
            FROM folders
            WHERE user_id = ? AND parent_id = ?
            ORDER BY folder_name ASC
        ");
        $s->execute([$userId, $parent]);

        foreach ($s->fetchAll(PDO::FETCH_COLUMN) as $child) {
            $child = (int)$child;
            if (!in_array($child, $all, true)) {
                $all[] = $child;
                $queue[] = $child;
            }
        }
    }

    return $all;
}

function getRelativeFolderPath($pdo, $userId, $folderId, $rootId)
{
    $parts = [];
    $current = $folderId;
    $guard = 0;

    while ($current > 0 && $guard++ < 100) {
        $folder = getFolder($pdo, $userId, $current);
        if (!$folder) break;

        array_unshift($parts, cleanZipName($folder['folder_name']));

        if ((int)$folder['id'] === $rootId) break;

        $current = !empty($folder['parent_id'])
            ? (int)$folder['parent_id']
            : 0;
    }

    if (!$parts) return '';

    array_shift($parts);
    return implode('/', $parts);
}

/* Validasi semua folder milik user */
$selected = [];
foreach ($folderIds as $id) {
    $folder = getFolder($pdo, $user_id, $id);
    if (!$folder) {
        http_response_code(403);
        exit('Folder tidak ditemukan atau bukan milik Anda.');
    }
    $selected[$id] = $folder;
}

/*
 * Jika user memilih folder induk sekaligus anaknya,
 * anak tidak perlu dibuat sebagai ZIP root terpisah.
 */
$rootIds = [];

foreach ($folderIds as $candidate) {
    $nested = false;
    $current = $candidate;
    $guard = 0;

    while ($current > 0 && $guard++ < 100) {
        $folder = getFolder($pdo, $user_id, $current);
        if (!$folder) break;

        $parent = !empty($folder['parent_id'])
            ? (int)$folder['parent_id']
            : 0;

        if ($parent > 0 && in_array($parent, $folderIds, true)) {
            $nested = true;
            break;
        }

        $current = $parent;
    }

    if (!$nested) $rootIds[] = $candidate;
}

$temp = tempnam(sys_get_temp_dir(), 'gosdrive_');
if ($temp === false) {
    http_response_code(500);
    exit('Gagal membuat file temporary.');
}

$zipPath = $temp . '.zip';
@unlink($temp);

$zip = new ZipArchive();
if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
    @unlink($zipPath);
    http_response_code(500);
    exit('Gagal membuat ZIP.');
}

$totalFiles = 0;

foreach ($rootIds as $rootId) {
    $root = getFolder($pdo, $user_id, $rootId);
    $rootName = cleanZipName($root['folder_name']);
    $zipRoot = $rootName;

    $zip->addEmptyDir($zipRoot);

    $folderIdsInside = getDescendantIds(
        $pdo,
        $user_id,
        $rootId
    );

    /*
     * Buat struktur subfolder.
     */
    foreach ($folderIdsInside as $fid) {
        if ($fid === $rootId) continue;

        $relative = getRelativeFolderPath(
            $pdo,
            $user_id,
            $fid,
            $rootId
        );

        if ($relative !== '') {
            $zip->addEmptyDir($zipRoot . '/' . $relative);
        }
    }

    /*
     * Ambil file seluruh isi folder.
     */
    $marks = implode(',', array_fill(0, count($folderIdsInside), '?'));
    $params = array_merge([$user_id], $folderIdsInside);

    $stmt = $pdo->prepare("
        SELECT id, folder_id, filename, filepath
        FROM files
        WHERE user_id = ?
        AND folder_id IN ($marks)
        ORDER BY filename ASC
    ");
    $stmt->execute($params);

    $usedNames = [];

    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $file) {
        $source = trim((string)$file['filepath']);

        if ($source === '' || !is_file($source) || !is_readable($source)) {
            continue;
        }

        $filename = cleanZipName($file['filename']);
        $relative = getRelativeFolderPath(
            $pdo,
            $user_id,
            (int)$file['folder_id'],
            $rootId
        );

        $zipName = $zipRoot . '/';
        if ($relative !== '') $zipName .= $relative . '/';
        $zipName .= $filename;

        /*
         * Hindari nama file kembar.
         */
        $original = $zipName;
        $n = 1;

        while (isset($usedNames[$zipName]) || $zip->locateName($zipName) !== false) {
            $info = pathinfo($original);
            $dir = isset($info['dirname']) ? $info['dirname'] . '/' : '';
            $base = isset($info['filename']) ? $info['filename'] : 'file';
            $ext = isset($info['extension']) ? '.' . $info['extension'] : '';

            $zipName = $dir . $base . ' (' . $n . ')' . $ext;
            $n++;
        }

        if ($zip->addFile($source, $zipName)) {
            $usedNames[$zipName] = true;
            $totalFiles++;
        }
    }
}

if (!$zip->close()) {
    @unlink($zipPath);
    http_response_code(500);
    exit('Gagal menyelesaikan ZIP.');
}

if (!is_file($zipPath) || filesize($zipPath) <= 0 || $totalFiles <= 0) {
    @unlink($zipPath);
    http_response_code(404);
    exit('Tidak ada file yang dapat dimasukkan ke ZIP, Folder wajib di isi file');
}

if (count($rootIds) === 1) {
    $downloadName = cleanZipName(
        $selected[$rootIds[0]]['folder_name']
    ) . '.zip';
} else {
    $downloadName =
        'GosDrive_Folder_Terpilih_' .
        date('Y-m-d_H-i-s') .
        '.zip';
}

while (ob_get_level() > 0) ob_end_clean();

$size = filesize($zipPath);

header('Content-Type: application/zip');
header(
    'Content-Disposition: attachment; filename="' .
    addcslashes($downloadName, "\"\\") .
    '"'
);
header('Content-Length: ' . $size);
header('Content-Transfer-Encoding: binary');
header('Cache-Control: no-store, no-cache, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');

$handle = fopen($zipPath, 'rb');

if (!$handle) {
    @unlink($zipPath);
    http_response_code(500);
    exit('Gagal membaca ZIP.');
}

while (!feof($handle)) {
    echo fread($handle, 1024 * 1024);
    flush();
}

fclose($handle);
@unlink($zipPath);
exit;
