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

$stamp = gmdate('Ymd-His');
$archivePath = $backupDir . DIRECTORY_SEPARATOR . 'arac-' . $stamp . '.zip';

$database = app_db();
$snapshotPath = $backupDir . DIRECTORY_SEPARATOR . 'licenses-' . $stamp . '.sqlite';
try {
    $database->exec('VACUUM INTO ' . $database->quote($snapshotPath));
} catch (Throwable $exception) {
    fwrite(STDERR, "Veritabanı anlık görüntüsü alınamadı: " . $exception->getMessage() . "\n");
    exit(1);
}

$zip = new ZipArchive();
if ($zip->open($archivePath, ZipArchive::CREATE) !== true) {
    @unlink($snapshotPath);
    fwrite(STDERR, "Arşiv açılamadı.\n");
    exit(1);
}

$zip->addFile($snapshotPath, 'licenses.sqlite');
foreach ([APP_SETTINGS_PATH => 'settings.json', ADMIN_PASSWORD_HASH_PATH => 'admin-password.hash'] as $source => $name) {
    if (is_file($source)) {
        $zip->addFile($source, $name);
    }
}

$skipDir = realpath($backupDir);
$iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(APP_STORAGE_DIR, FilesystemIterator::SKIP_DOTS));
foreach ($iterator as $file) {
    $path = $file->getPathname();
    $real = realpath($path) ?: $path;
    if ($skipDir && str_starts_with($real, $skipDir)) {
        continue;
    }
    $base = basename($path);
    if ($base === 'activation.key' || str_starts_with($base, 'licenses.sqlite')) {
        continue;
    }
    $local = substr($path, strlen(APP_STORAGE_DIR) + 1);
    $zip->addFile($path, $local);
}
$zip->close();
@unlink($snapshotPath);
@chmod($archivePath, 0600);

$keyPath = APP_STORAGE_DIR . DIRECTORY_SEPARATOR . 'activation.key';
if (is_file($keyPath)) {
    $keyBackup = $backupDir . DIRECTORY_SEPARATOR . 'activation-key-' . $stamp . '.key';
    if (copy($keyPath, $keyBackup)) {
        @chmod($keyBackup, 0600);
        fwrite(STDOUT, "Activation key backed up separately: {$keyBackup}\n");
    } else {
        fwrite(STDERR, "Uyari: activation.key ayri yedeklenemedi.\n");
    }
} else {
    fwrite(STDERR, "Uyari: activation.key bulunamadi; kodlarin geri alinabilir kopyalari kaybolur.\n");
}

fwrite(STDOUT, "Backup written: {$archivePath}\n");
fwrite(STDOUT, "Not: Yedekleri sunucu disina tasiyin ve isi cron ile zamanlayin.\n");
