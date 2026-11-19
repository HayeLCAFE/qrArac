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
$assert(international_phone('+44 20 7946 0958') === '+442079460958', 'artı önekli yabancı numara');
$assert(international_phone('0049 30 901820') === '+4930901820', '00 önekli yabancı numara');
$assert(str_contains(whatsapp_link('0532 111 22 33', 'merhaba'), 'wa.me/905321112233'), 'WhatsApp bağlantısı');
$assert(str_contains(whatsapp_link('+1 415 555 2671', 'merhaba'), 'wa.me/14155552671'), 'yabancı WhatsApp bağlantısı');

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
$assert(shopier_amount_candidates(['total_price' => '399.00']) === [39900], 'Shopier tutar (ondalık)');
$assert(in_array(39900, shopier_amount_candidates(['price' => 399]), true), 'Shopier tutar (tam sayı TL)');
$assert(shopier_amount_candidates(['buyer' => 'x']) === [], 'Shopier tutar alanı yok');

$database = app_db();
$assert($database instanceof PDO, 'veritabanı bağlantısı');

$assert(money_tl(39900) === '399,00 TL', 'para biçimi');

fwrite(STDOUT, $failed === 0 ? "ALL PASSED\n" : "FAILED {$failed}\n");
exit($failed === 0 ? 0 : 1);
