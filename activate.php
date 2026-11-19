<?php
declare(strict_types=1);
require_once __DIR__ . '/auth.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !csrf_is_valid()) {
    header('Location: satin.php?activation=invalid', true, 303);
    exit;
}

if (!app_rate_limit_allows('activate', 40, 10)) {
    header('Location: satin.php?activation=invalid', true, 303);
    exit;
}

$submittedCode = $_POST['code'] ?? '';
$code = is_string($submittedCode) ? strtoupper(preg_replace('/[^a-fA-F0-9]/', '', $submittedCode)) : '';

$now = time();
$windowStart = (int) ($_SESSION['activate_window'] ?? 0);
$failCount = (int) ($_SESSION['activate_fails'] ?? 0);
if ($windowStart === 0 || $now - $windowStart > 600) {
    $windowStart = $now;
    $failCount = 0;
}
$_SESSION['activate_window'] = $windowStart;
$_SESSION['activate_fails'] = $failCount;

$reject = static function () use (&$failCount): never {
    $_SESSION['activate_fails'] = $failCount + 1;
    header('Location: satin.php?activation=invalid', true, 303);
    exit;
};

if (strlen($code) !== 24 || $failCount >= 10) {
    $reject();
}

$database = app_db();
$database->beginTransaction();

try {
    $statement = $database->prepare('SELECT id, redeemed_session FROM activation_codes WHERE code_hash = :hash');
    $statement->execute(['hash' => hash('sha256', $code)]);
    $license = $statement->fetch();

    if (!$license) {
        $database->rollBack();
        $reject();
    }

    session_regenerate_id(true);
    $sessionId = session_id();
    $update = $database->prepare(
        'UPDATE activation_codes SET redeemed_at = CURRENT_TIMESTAMP, redeemed_session = :session WHERE id = :id'
    );
    $update->execute(['session' => $sessionId, 'id' => $license['id']]);
    $database->commit();

    $_SESSION['paid_license_id'] = (int) $license['id'];
    $_SESSION['paid_session_id'] = $sessionId;
    unset($_SESSION['activate_fails'], $_SESSION['activate_window']);
    header('Location: studio.php', true, 303);
    exit;
} catch (Throwable $error) {
    if ($database->inTransaction()) {
        $database->rollBack();
    }
    error_log($error->getMessage());
    header('Location: satin.php?activation=invalid', true, 303);
    exit;
}
