<?php
declare(strict_types=1);

const PRODUCT_PRICE_KURUS = 39900;
const SHOPIER_PRODUCT_URL = '';

$storageDirectory = getenv('ARAC_STORAGE_DIR');
$documentRoot = $_SERVER['DOCUMENT_ROOT'] ?? '';
if (PHP_SAPI === 'cli-server' && $documentRoot !== '' && realpath(__DIR__) === realpath($documentRoot)) {
    $documentRoot = dirname($documentRoot);
}
$defaultStorageDirectory = $documentRoot !== ''
    ? dirname(rtrim($documentRoot, '/\\')) . DIRECTORY_SEPARATOR . 'arac-storage'
    : dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'arac-storage';

define(
    'APP_STORAGE_DIR',
    $storageDirectory === false || trim($storageDirectory) === ''
        ? $defaultStorageDirectory
        : trim($storageDirectory)
);
define('APP_DB_PATH', APP_STORAGE_DIR . DIRECTORY_SEPARATOR . 'licenses.sqlite');
define('APP_SETTINGS_PATH', APP_STORAGE_DIR . DIRECTORY_SEPARATOR . 'settings.json');
define('ADMIN_PASSWORD_HASH_PATH', APP_STORAGE_DIR . DIRECTORY_SEPARATOR . 'admin-password.hash');