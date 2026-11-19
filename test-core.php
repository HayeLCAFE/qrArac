<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once __DIR__ . '/auth.php';

$failed = 0;
$assert = static function (bool $ok, string $label) use (&$failed): void {
    if ($ok) {
        fwrite(STDOUT, "OK  {$label}\n");
        return;
    }
    $failed++;
    fwrite(STDERR, "FAIL {$label}\n");
};

$assert(validate_turkish_iban('TR330006100519786457841326') === true, 'geçerli IBAN');
$assert(validate_turkish_iban('TR000000000000000000000000') === false, 'geçersiz IBAN');
$assert(international_phone('0532 111 22 33') === '+905321112233', 'telefon uluslararası');

$sample = 'ABC123DEF456ABC123DEF456';
$enc = encrypt_activation_code($sample);
$assert(decrypt_activation_code($enc) === $sample, 'AES-GCM turu');

$secret = 'test-secret';
$post = ['random_nr' => '123456', 'platform_order_id' => 'ORD-1', 'signature' => base64_encode(hash_hmac('sha256', '123456ORD-1', $secret, true))];
$assert(shopier_verify_form_callback($post, $secret) === true, 'Shopier form imzası');
$assert(shopier_verify_form_callback($post, 'wrong') === false, 'Shopier sahte imza');

$body = '{"type":"order.created"}';
$token = 'whsec';
$sig = hash_hmac('sha256', $body, $token);
$assert(shopier_verify_webhook($body, $sig, $token) === true, 'Shopier webhook HMAC');

$database = app_db();
$assert($database instanceof PDO, 'veritabanı bağlantısı');
$database->exec('CREATE TABLE IF NOT EXISTS cards (id INTEGER PRIMARY KEY)');

$assert(money_tl(39900) === '399,00 TL', 'para biçimi');

fwrite(STDOUT, $failed === 0 ? "ALL PASSED\n" : "FAILED {$failed}\n");
exit($failed === 0 ? 0 : 1);
