<?php
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once __DIR__ . '/auth.php';

$orderReference = trim($argv[1] ?? '');

if ($orderReference === '' || strlen($orderReference) > 120) {
    fwrite(STDERR, "Usage: php issue-code.php SHOPIER_ORDER_REFERENCE\n");
    exit(1);
}

try {
    $code = issue_activation_code(app_db(), $orderReference, 'cli', null, null);
} catch (PDOException $error) {
    if (str_contains($error->getMessage(), 'UNIQUE constraint failed')) {
        fwrite(STDERR, "An activation code already exists for this order reference.\n");
        exit(1);
    }

    throw $error;
}

fwrite(STDOUT, "Activation code: {$code}\n");