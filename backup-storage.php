<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once __DIR__ . '/auth.php';

ensure_storage_directory();
if (!class_exists('ZipArchive')) {
    fwrite(STDERR, "ZipArchive PHP eklentisi gerekli.\n");
    exit(1);
}

$backupDir = APP_STORAGE_DIR . DIRECTORY_SEPARATOR . 'backups';
if (!is_dir($backupDir) && !mkdir($backupDir, 0700, true) && !is_dir($backupDir)) {
    fwrite(STDERR, "Yedek klasörü oluşturulamadı.\n");
    exit(1);
}

$archivePath = $backupDir . DIRECTORY_SEPARATOR . 'arac-' . gmdate('Ymd-His') . '.zip';
$zip = new ZipArchive();
if ($zip->open($archivePath, ZipArchive::CREATE) !== true) {
    fwrite(STDERR, "Arşiv açılamadı.\n");
    exit(1);
}

$skip = realpath($backupDir);
$iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(APP_STORAGE_DIR, FilesystemIterator::SKIP_DOTS));
foreach ($iterator as $file) {
    $path = $file->getPathname();
    if ($skip && str_starts_with(realpath($file->getPath()) ?: '', $skip)) {
        continue;
    }
    $local = substr($path, strlen(APP_STORAGE_DIR) + 1);
    $zip->addFile($path, $local);
}
$zip->close();
@chmod($archivePath, 0600);
fwrite(STDOUT, "Backup written: {$archivePath}\n");
