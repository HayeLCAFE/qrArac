<?php
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once __DIR__ . '/auth.php';

ensure_storage_directory();
fwrite(STDOUT, "Set a strong administrator password (at least 14 characters).\nPassword: ");
$password = trim((string) fgets(STDIN));
fwrite(STDOUT, "Confirm password: ");
$confirmation = trim((string) fgets(STDIN));

if (strlen($password) < 14 || !hash_equals($password, $confirmation)) {
    fwrite(STDERR, "Passwords must match and contain at least 14 characters. No changes were made.\n");
    exit(1);
}

$hash = password_hash($password, PASSWORD_DEFAULT);
if ($hash === false || file_put_contents(ADMIN_PASSWORD_HASH_PATH, $hash, LOCK_EX) === false) {
    fwrite(STDERR, "Unable to save the administrator password hash.\n");
    exit(1);
}

@chmod(ADMIN_PASSWORD_HASH_PATH, 0600);
fwrite(STDOUT, "Administrator password saved outside the public web directory.\n");
