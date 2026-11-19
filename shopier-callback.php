<?php
declare(strict_types=1);
require_once __DIR__ . '/auth.php';

header('Content-Type: text/plain; charset=UTF-8');
header('Cache-Control: no-store');

$settings = app_settings();
$raw = (string) file_get_contents('php://input');
$signatureHeader = (string) ($_SERVER['HTTP_SHOPIER_SIGNATURE'] ?? '');

if (!app_rate_limit_allows('shopier-callback', 300, 60)) {
    http_response_code(429);
    echo 'rate-limited';
    exit;
}

$verified = false;
$orderRef = '';
$email = null;
$phone = null;
$statusOk = false;

$token = (string) $settings['shopier_webhook_token'];
$secret = (string) $settings['shopier_api_secret'];
if ($token !== '' && $signatureHeader !== '' && shopier_verify_webhook($raw, $signatureHeader, $token)) {
    $verified = true;
    $json = json_decode($raw, true);
    if (is_array($json)) {
        $data = is_array($json['data'] ?? null) ? $json['data'] : $json;
        $orderRef = (string) ($data['id'] ?? $data['order_id'] ?? $data['platform_order_id'] ?? '');
        $email = filter_var((string) ($data['email'] ?? $data['buyer_email'] ?? ''), FILTER_VALIDATE_EMAIL) ?: null;
        $phone = (string) ($data['phone'] ?? $data['buyer_phone'] ?? '');
        $type = strtolower((string) ($json['type'] ?? ''));
        $status = strtolower((string) ($data['status'] ?? $data['payment_status'] ?? ''));
        $statusOk = in_array($status, ['success', 'completed', 'paid', '1'], true)
            && !in_array($type, ['order.cancelled', 'order.canceled', 'order.refunded', 'payment.failed', 'order.failed'], true);
    }
} elseif ($secret !== '' && shopier_verify_form_callback($_POST, $secret)) {
    $verified = true;
    $orderRef = (string) ($_POST['platform_order_id'] ?? $_POST['orderid'] ?? '');
    $email = filter_var((string) ($_POST['email'] ?? $_POST['buyer_email'] ?? ''), FILTER_VALIDATE_EMAIL) ?: null;
    $phone = (string) ($_POST['phone'] ?? $_POST['buyer_phone'] ?? '');
    $status = strtolower((string) ($_POST['status'] ?? $_POST['payment_status'] ?? 'success'));
    $statusOk = in_array($status, ['success', 'completed', 'paid', '1', ''], true);
}

if (!$verified || !$statusOk || $orderRef === '' || strlen($orderRef) > 120) {
    http_response_code(400);
    echo 'invalid';
    exit;
}

$phone = $phone !== '' && $phone !== null ? $phone : null;
$eventKey = 'shopier:' . $orderRef;
$database = app_db();

try {
    $database->beginTransaction();
    $mark = $database->prepare('INSERT OR IGNORE INTO shopier_events (event_key, payload_hash) VALUES (:key, :hash)');
    $mark->execute(['key' => $eventKey, 'hash' => hash('sha256', $raw !== '' ? $raw : json_encode($_POST))]);
    if ($mark->rowCount() === 0) {
        $database->commit();
        echo 'ok';
        exit;
    }

    $existing = $database->prepare('SELECT id, customer_email, customer_phone FROM activation_codes WHERE order_ref = :ref');
    $existing->execute(['ref' => $orderRef]);
    $license = $existing->fetch();
    $code = null;
    if ($license) {
        $recovered = recover_activation_code($database, $orderRef);
        $code = $recovered['code'] ?? null;
        if ($email || $phone) {
            $touch = $database->prepare('UPDATE activation_codes SET customer_email = COALESCE(:email, customer_email), customer_phone = COALESCE(:phone, customer_phone) WHERE id = :id');
            $touch->execute(['email' => $email, 'phone' => $phone, 'id' => $license['id']]);
        }
    } else {
        $code = issue_activation_code($database, $orderRef, 'shopier', $email, $phone);
    }
    $database->commit();
} catch (Throwable $exception) {
    if ($database->inTransaction()) {
        $database->rollBack();
    }
    error_log($exception->getMessage());
    http_response_code(500);
    echo 'error';
    exit;
}

if (is_string($code)) {
    deliver_activation_code($code, $email, $phone, 'Shopier ödemeniz alındı.');
    $admin = (string) app_settings()['admin_email'];
    if ($admin !== '') {
        app_send_email($admin, 'Shopier siparişi işlendi', "Referans: {$orderRef}\nE-posta: {$email}");
    }
}

echo 'ok';
