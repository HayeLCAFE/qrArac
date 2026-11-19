<?php
declare(strict_types=1);

require_once __DIR__ . '/config.php';

if (PHP_SAPI !== 'cli') {
    app_enforce_https();
    if (session_status() !== PHP_SESSION_ACTIVE) {
        app_send_security_headers();
        ini_set('session.use_strict_mode', '1');
        ini_set('session.gc_maxlifetime', (string) (30 * 24 * 60 * 60));
        $https = app_request_is_https();
        session_set_cookie_params([
            'lifetime' => 30 * 24 * 60 * 60,
            'path' => '/',
            'secure' => $https,
            'httponly' => true,
            'samesite' => 'Lax'
        ]);
        session_start();
    }
}

function app_request_is_https(): bool
{
    if (getenv('APP_FORCE_HTTPS') === '1') {
        return true;
    }
    $https = $_SERVER['HTTPS'] ?? '';
    if ($https !== '' && $https !== 'off') {
        return true;
    }
    $forwarded = strtolower((string) ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? ''));
    return $forwarded === 'https';
}

function app_enforce_https(): void
{
    $settings = [];
    try {
        $settings = is_file(APP_SETTINGS_PATH) ? app_settings() : [];
    } catch (Throwable $exception) {
        $settings = [];
    }
    $force = getenv('APP_FORCE_HTTPS') === '1' || !empty($settings['force_https']);
    if (!$force || app_request_is_https()) {
        return;
    }
    $host = (string) ($_SERVER['HTTP_HOST'] ?? '');
    if ($host === '' || str_starts_with($host, '127.') || $host === 'localhost' || str_starts_with($host, 'localhost:')) {
        return;
    }
    $configuredBaseUrl = rtrim(trim((string) ($settings['public_base_url'] ?? '')), '/');
    $configuredHost = $configuredBaseUrl !== '' ? strtolower((string) parse_url($configuredBaseUrl, PHP_URL_HOST)) : '';
    $requestHost = strtolower((string) parse_url('//' . $host, PHP_URL_HOST));
    if ($configuredHost !== '' && hash_equals($configuredHost, $requestHost)) {
        $redirectHost = $configuredHost;
    } else if ($configuredHost === '' && preg_match('/^(?=.{1,253}$)[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?(?:\.[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?)*(:\d{1,5})?$/i', $host)) {
        $redirectHost = $host;
    } else {
        http_response_code(421);
        exit;
    }
    $uri = $_SERVER['REQUEST_URI'] ?? '/';
    header('Location: https://' . $redirectHost . $uri, true, 301);
    exit;
}

function app_csp_nonce(): string
{
    static $nonce = null;
    if ($nonce === null) {
        $nonce = bin2hex(random_bytes(16));
    }
    return $nonce;
}

function app_send_security_headers(): void
{
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: DENY');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    header('Permissions-Policy: camera=(), microphone=(), geolocation=()');
    header("Content-Security-Policy: default-src 'self'; img-src 'self' data:; style-src 'self' 'nonce-" . app_csp_nonce() . "'; script-src 'self' 'nonce-" . app_csp_nonce() . "'; font-src 'self'; connect-src 'self'; base-uri 'self'; form-action 'self' https://www.shopier.com https://shopier.com; frame-ancestors 'none'; object-src 'none'");
    if (app_request_is_https()) {
        header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
    }
}

function app_db(): PDO
{
    static $database = null;

    if ($database instanceof PDO) {
        return $database;
    }

    ensure_storage_directory();

    $database = new PDO('sqlite:' . APP_DB_PATH, null, null, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false
    ]);
    $database->exec('PRAGMA foreign_keys = ON');
    $database->exec('PRAGMA journal_mode = WAL');
    $database->exec(
        'CREATE TABLE IF NOT EXISTS activation_codes (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            code_hash TEXT NOT NULL UNIQUE,
            code_ciphertext TEXT,
            order_ref TEXT NOT NULL UNIQUE,
            source TEXT NOT NULL DEFAULT "manual",
            customer_email TEXT,
            customer_phone TEXT,
            created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
            redeemed_at TEXT,
            redeemed_session TEXT
        )'
    );
    app_ensure_columns($database, 'activation_codes', [
        'code_ciphertext' => 'TEXT',
        'source' => 'TEXT NOT NULL DEFAULT "manual"',
        'customer_email' => 'TEXT',
        'customer_phone' => 'TEXT'
    ]);
    $database->exec(
        'CREATE TABLE IF NOT EXISTS payment_notifications (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            customer_name TEXT NOT NULL,
            email TEXT,
            phone TEXT,
            amount_kurus INTEGER NOT NULL,
            transfer_reference TEXT NOT NULL,
            status TEXT NOT NULL DEFAULT "pending" CHECK (status IN ("pending", "approved", "rejected")),
            ip_hash TEXT NOT NULL,
            created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
            processed_at TEXT
        )'
    );
    $database->exec(
        'CREATE TABLE IF NOT EXISTS admin_login_attempts (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            ip_hash TEXT NOT NULL,
            succeeded INTEGER NOT NULL DEFAULT 0,
            attempted_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
        )'
    );
    $database->exec(
        'CREATE TABLE IF NOT EXISTS cards (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            public_token TEXT NOT NULL UNIQUE,
            license_id INTEGER NOT NULL,
            owner_name TEXT NOT NULL,
            phone TEXT NOT NULL,
            note TEXT NOT NULL DEFAULT "",
            plate TEXT NOT NULL DEFAULT "",
            locale TEXT NOT NULL DEFAULT "tr",
            template TEXT NOT NULL DEFAULT "navy",
            whatsapp INTEGER NOT NULL DEFAULT 1,
            emergency_phone TEXT NOT NULL DEFAULT "",
            blood_type TEXT NOT NULL DEFAULT "",
            allergies TEXT NOT NULL DEFAULT "",
            chronic_conditions TEXT NOT NULL DEFAULT "",
            special_notes TEXT NOT NULL DEFAULT "",
            created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (license_id) REFERENCES activation_codes(id)
        )'
    );
    $cardColumns = $database->query('PRAGMA table_info(cards)')->fetchAll(PDO::FETCH_COLUMN, 1);
    $cardMigrations = [
        'emergency_phone' => 'ALTER TABLE cards ADD COLUMN emergency_phone TEXT NOT NULL DEFAULT ""',
        'blood_type' => 'ALTER TABLE cards ADD COLUMN blood_type TEXT NOT NULL DEFAULT ""',
        'allergies' => 'ALTER TABLE cards ADD COLUMN allergies TEXT NOT NULL DEFAULT ""',
        'chronic_conditions' => 'ALTER TABLE cards ADD COLUMN chronic_conditions TEXT NOT NULL DEFAULT ""',
        'special_notes' => 'ALTER TABLE cards ADD COLUMN special_notes TEXT NOT NULL DEFAULT ""'
    ];
    foreach ($cardMigrations as $column => $sql) {
        if (!in_array($column, $cardColumns, true)) {
            $database->exec($sql);
        }
    }
    $database->exec(
        'CREATE TABLE IF NOT EXISTS shopier_events (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            event_key TEXT NOT NULL UNIQUE,
            payload_hash TEXT NOT NULL,
            created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
        )'
    );
    $database->exec('CREATE INDEX IF NOT EXISTS idx_payments_status ON payment_notifications(status, created_at)');
    $database->exec('CREATE INDEX IF NOT EXISTS idx_payments_reference ON payment_notifications(transfer_reference)');
    $database->exec('CREATE INDEX IF NOT EXISTS idx_admin_attempts_ip_time ON admin_login_attempts(ip_hash, attempted_at)');
    $database->exec('CREATE INDEX IF NOT EXISTS idx_cards_license ON cards(license_id)');
    $database->exec(
        'CREATE TABLE IF NOT EXISTS app_rate_limits (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            bucket TEXT NOT NULL,
            ip_hash TEXT NOT NULL,
            attempted_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
        )'
    );
    $database->exec('CREATE INDEX IF NOT EXISTS idx_rate_limits_lookup ON app_rate_limits(bucket, ip_hash, attempted_at)');
    try {
        $database->exec(
            'CREATE UNIQUE INDEX IF NOT EXISTS idx_payments_ref_active ON payment_notifications(transfer_reference) WHERE status != "rejected"'
        );
    } catch (Throwable $exception) {
        error_log('Unable to create unique payment reference index: ' . $exception->getMessage());
    }

    @chmod(APP_DB_PATH, 0600);
    foreach (['', '-wal', '-shm'] as $suffix) {
        $sidecar = APP_DB_PATH . $suffix;
        if (is_file($sidecar)) {
            @chmod($sidecar, 0600);
        }
    }

    return $database;
}

function app_rate_limit_allows(string $bucket, int $maxAttempts, int $windowMinutes): bool
{
    $database = app_db();
    $ipHash = request_ip_hash();
    $count = $database->prepare(
        "SELECT COUNT(*) FROM app_rate_limits WHERE bucket = :bucket AND ip_hash = :ip AND attempted_at >= datetime('now', '-" . $windowMinutes . " minutes')"
    );
    $count->execute(['bucket' => $bucket, 'ip' => $ipHash]);
    if ((int) $count->fetchColumn() >= $maxAttempts) {
        return false;
    }

    $insert = $database->prepare('INSERT INTO app_rate_limits (bucket, ip_hash) VALUES (:bucket, :ip)');
    $insert->execute(['bucket' => $bucket, 'ip' => $ipHash]);
    $database->exec("DELETE FROM app_rate_limits WHERE attempted_at < datetime('now', '-1 day')");
    return true;
}

function app_ensure_columns(PDO $database, string $table, array $columns): void
{
    $existing = $database->query('PRAGMA table_info(' . $table . ')')->fetchAll(PDO::FETCH_COLUMN, 1);
    foreach ($columns as $name => $definition) {
        if (!in_array($name, $existing, true)) {
            $database->exec('ALTER TABLE ' . $table . ' ADD COLUMN ' . $name . ' ' . $definition);
        }
    }
}

function ensure_storage_directory(): void
{
    if (!is_dir(APP_STORAGE_DIR) && !mkdir(APP_STORAGE_DIR, 0700, true) && !is_dir(APP_STORAGE_DIR)) {
        throw new RuntimeException('Unable to create the private application storage directory.');
    }
}

function app_setting_defaults(): array
{
    return [
        'price_kurus' => PRODUCT_PRICE_KURUS,
        'shopier_url' => SHOPIER_PRODUCT_URL,
        'shopier_api_key' => '',
        'shopier_api_secret' => '',
        'shopier_webhook_token' => '',
        'bank_name' => '',
        'account_holder' => '',
        'iban' => '',
        'payment_note' => 'Açıklama alanına ödeme bildiriminizdeki referans numarasını yazın.',
        'admin_email' => '',
        'mail_from' => '',
        'smtp_host' => '',
        'smtp_port' => 587,
        'smtp_user' => '',
        'smtp_pass' => '',
        'smtp_encryption' => 'tls',
        'sms_provider' => '',
        'sms_usercode' => '',
        'sms_password' => '',
        'sms_header' => '',
        'force_https' => false,
        'max_cards_per_license' => 3,
        'public_base_url' => '',
        'company_name' => '',
        'company_address' => '',
        'company_tax_no' => '',
        'public_email' => '',
        'public_phone' => '',
        'trusted_proxies' => ''
    ];
}

function app_settings(): array
{
    $defaults = app_setting_defaults();
    if (!is_file(APP_SETTINGS_PATH)) {
        return $defaults;
    }

    $decoded = json_decode((string) file_get_contents(APP_SETTINGS_PATH), true);
    return is_array($decoded) ? array_merge($defaults, $decoded) : $defaults;
}

function save_app_settings(array $settings): void
{
    ensure_storage_directory();
    $temporaryPath = APP_SETTINGS_PATH . '.' . bin2hex(random_bytes(6)) . '.tmp';
    $encoded = json_encode($settings, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

    if ($encoded === false || file_put_contents($temporaryPath, $encoded, LOCK_EX) === false) {
        throw new RuntimeException('Unable to save application settings.');
    }

    @chmod($temporaryPath, 0600);
    if (!rename($temporaryPath, APP_SETTINGS_PATH)) {
        @unlink($temporaryPath);
        throw new RuntimeException('Unable to replace application settings.');
    }
    @chmod(APP_SETTINGS_PATH, 0600);
}

function app_send_email(string $recipient, string $subject, string $body): bool
{
    if (!filter_var($recipient, FILTER_VALIDATE_EMAIL)) {
        return false;
    }

    $settings = app_settings();
    $from = filter_var($settings['mail_from'], FILTER_VALIDATE_EMAIL) ? $settings['mail_from'] : $recipient;
    $encodedSubject = '=?UTF-8?B?' . base64_encode($subject) . '?=';

    if (trim((string) $settings['smtp_host']) !== '') {
        return app_smtp_send($from, $recipient, $encodedSubject, $body, $settings);
    }

    $headers = [
        'MIME-Version: 1.0',
        'Content-Type: text/plain; charset=UTF-8',
        'From: ' . $from
    ];
    return mail($recipient, $encodedSubject, $body, implode("\r\n", $headers));
}

function app_smtp_send(string $from, string $to, string $subject, string $body, array $settings): bool
{
    $host = trim((string) $settings['smtp_host']);
    $port = (int) $settings['smtp_port'];
    if ($port < 1 || $port > 65535) {
        $port = 587;
    }
    $encryption = strtolower((string) ($settings['smtp_encryption'] ?? 'tls'));
    $remote = ($encryption === 'ssl' ? 'ssl://' : '') . $host . ':' . $port;
    $context = stream_context_create([
        'ssl' => [
            'verify_peer' => true,
            'verify_peer_name' => true,
            'allow_self_signed' => false
        ]
    ]);
    $socket = @stream_socket_client($remote, $errno, $errstr, 20, STREAM_CLIENT_CONNECT, $context);
    if (!is_resource($socket)) {
        error_log('SMTP connect failed: ' . $errstr);
        return false;
    }
    stream_set_timeout($socket, 20);

    $read = static function () use ($socket): string {
        $data = '';
        while (!feof($socket)) {
            $line = fgets($socket, 515);
            if ($line === false) {
                break;
            }
            $data .= $line;
            if (isset($line[3]) && $line[3] === ' ') {
                break;
            }
        }
        return $data;
    };
    $write = static function (string $command) use ($socket): void {
        fwrite($socket, $command . "\r\n");
    };
    $expect = static function (string $response, string $prefix) use ($socket): bool {
        if (!str_starts_with($response, $prefix)) {
            error_log('SMTP unexpected: ' . trim($response));
            fclose($socket);
            return false;
        }
        return true;
    };

    $greeting = $read();
    if (!$expect($greeting, '220')) {
        return false;
    }
    $ehloHost = 'localhost';
    $write('EHLO ' . $ehloHost);
    $ehlo = $read();
    if (!$expect($ehlo, '250')) {
        return false;
    }
    if ($encryption === 'tls') {
        $write('STARTTLS');
        if (!$expect($read(), '220')) {
            return false;
        }
        if (!stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
            fclose($socket);
            return false;
        }
        $write('EHLO ' . $ehloHost);
        if (!$expect($read(), '250')) {
            return false;
        }
    }

    $user = (string) $settings['smtp_user'];
    $pass = (string) $settings['smtp_pass'];
    if ($user !== '') {
        $write('AUTH LOGIN');
        if (!$expect($read(), '334')) {
            return false;
        }
        $write(base64_encode($user));
        if (!$expect($read(), '334')) {
            return false;
        }
        $write(base64_encode($pass));
        if (!$expect($read(), '235')) {
            return false;
        }
    }

    $write('MAIL FROM:<' . $from . '>');
    if (!$expect($read(), '250')) {
        return false;
    }
    $write('RCPT TO:<' . $to . '>');
    $rcpt = $read();
    if (!str_starts_with($rcpt, '250') && !str_starts_with($rcpt, '251')) {
        error_log('SMTP RCPT: ' . trim($rcpt));
        fclose($socket);
        return false;
    }
    $write('DATA');
    if (!$expect($read(), '354')) {
        return false;
    }
    $messageId = '<' . bin2hex(random_bytes(12)) . '@' . (parse_url(app_public_base_url(), PHP_URL_HOST) ?: 'localhost') . '>';
    $payload = 'From: ' . $from . "\r\n"
        . 'To: ' . $to . "\r\n"
        . 'Subject: ' . $subject . "\r\n"
        . 'Date: ' . date('r') . "\r\n"
        . 'Message-ID: ' . $messageId . "\r\n"
        . "MIME-Version: 1.0\r\n"
        . "Content-Type: text/plain; charset=UTF-8\r\n"
        . "Content-Transfer-Encoding: 8bit\r\n"
        . "\r\n"
        . str_replace("\n", "\r\n", str_replace("\n.", "\n..", str_replace(["\r\n", "\r"], "\n", $body)));
    $write($payload . "\r\n.");
    if (!$expect($read(), '250')) {
        return false;
    }
    $write('QUIT');
    fclose($socket);
    return true;
}

function app_send_sms(string $phone, string $message): bool
{
    $digits = preg_replace('/\D/', '', $phone);
    if (strlen($digits) < 10) {
        return false;
    }
    if (!str_starts_with($digits, '90')) {
        $digits = '90' . ltrim($digits, '0');
    }
    $settings = app_settings();
    $provider = strtolower(trim((string) $settings['sms_provider']));
    if ($provider !== 'netgsm') {
        return false;
    }
    $user = trim((string) $settings['sms_usercode']);
    $password = (string) $settings['sms_password'];
    $header = trim((string) $settings['sms_header']);
    if ($user === '' || $password === '' || $header === '') {
        return false;
    }
    $query = http_build_query([
        'usercode' => $user,
        'password' => $password,
        'gsmno' => $digits,
        'message' => $message,
        'msgheader' => $header,
        'dil' => 'TR'
    ]);
    $url = 'https://api.netgsm.com.tr/sms/send/get/?' . $query;
    $response = app_http_get($url);
    return is_string($response) && str_starts_with(trim($response), '00');
}

function app_http_get(string $url): ?string
{
    if (function_exists('curl_init')) {
        $curl = curl_init($url);
        curl_setopt_array($curl, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 20,
            CURLOPT_PROTOCOLS => CURLPROTO_HTTPS
        ]);
        $result = curl_exec($curl);
        $curlError = curl_errno($curl);
        curl_close($curl);
        return $curlError === 0 && is_string($result) ? $result : null;
    }
    $context = stream_context_create(['http' => ['timeout' => 20], 'ssl' => ['verify_peer' => true, 'verify_peer_name' => true]]);
    $result = @file_get_contents($url, false, $context);
    return is_string($result) ? $result : null;
}

function request_client_ip(): string
{
    $remote = trim((string) ($_SERVER['REMOTE_ADDR'] ?? ''));
    if ($remote === '') {
        return 'unknown';
    }

    $trusted = array_filter(array_map('trim', preg_split('/[\s,]+/', (string) (app_settings()['trusted_proxies'] ?? ''))));
    $isTrusted = false;
    if ($trusted !== []) {
        $isTrusted = in_array($remote, $trusted, true);
    } else {
        $isTrusted = filter_var($remote, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false;
    }

    if (!$isTrusted) {
        return $remote;
    }

    $forwarded = trim(explode(',', (string) ($_SERVER['HTTP_CF_CONNECTING_IP'] ?? ''))[0]);
    if ($forwarded === '') {
        $forwarded = trim(explode(',', (string) ($_SERVER['HTTP_X_FORWARDED_FOR'] ?? ''))[0]);
    }
    return filter_var($forwarded, FILTER_VALIDATE_IP) !== false ? $forwarded : $remote;
}

function request_ip_hash(): string
{
    return hash_hmac('sha256', request_client_ip(), activation_encryption_key());
}

function validate_turkish_iban(string $iban): bool
{
    $iban = strtoupper(preg_replace('/\s+/', '', $iban));
    if (!preg_match('/^TR\d{24}$/', $iban)) {
        return false;
    }

    $rearranged = substr($iban, 4) . substr($iban, 0, 4);
    $remainder = 0;
    foreach (str_split($rearranged) as $character) {
        $digits = ctype_alpha($character) ? (string) (ord($character) - 55) : $character;
        foreach (str_split($digits) as $digit) {
            $remainder = (($remainder * 10) + (int) $digit) % 97;
        }
    }

    return $remainder === 1;
}

function csrf_token(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }

    return $_SESSION['csrf_token'];
}

function csrf_is_valid(): bool
{
    return isset($_POST['csrf_token'], $_SESSION['csrf_token'])
        && is_string($_POST['csrf_token'])
        && hash_equals($_SESSION['csrf_token'], $_POST['csrf_token']);
}

function current_license_id(): ?int
{
    $licenseId = filter_var($_SESSION['paid_license_id'] ?? null, FILTER_VALIDATE_INT);
    $sessionId = $_SESSION['paid_session_id'] ?? '';
    if (!$licenseId || !is_string($sessionId) || !hash_equals(session_id(), $sessionId)) {
        return null;
    }
    $statement = app_db()->prepare(
        'SELECT id FROM activation_codes WHERE id = :id AND redeemed_session = :session'
    );
    $statement->execute(['id' => $licenseId, 'session' => session_id()]);
    return $statement->fetch() ? $licenseId : null;
}

function require_paid_access(): int
{
    header('Cache-Control: no-store, private');
    $licenseId = current_license_id();
    if ($licenseId === null) {
        unset($_SESSION['paid_license_id'], $_SESSION['paid_session_id']);
        header('Location: satin.php?access=required', true, 303);
        exit;
    }
    return $licenseId;
}

function escape_html(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function money_tl(int $kurus): string
{
    return number_format($kurus / 100, 2, ',', '.') . ' TL';
}

function app_public_base_url(): string
{
    $configured = rtrim(trim((string) app_settings()['public_base_url']), '/');
    if ($configured !== '' && preg_match('#^https://#i', $configured)) {
        return $configured;
    }
    $https = app_request_is_https();
    $host = (string) ($_SERVER['HTTP_HOST'] ?? '');
    if (!preg_match('/^(?=.{1,253}$)[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?(?:\\.[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?)*(:\\d{1,5})?$/i', $host)) {
        $host = 'localhost';
    }
    $script = (string) ($_SERVER['SCRIPT_NAME'] ?? '/index.php');
    $dir = str_replace('\\', '/', dirname($script));
    if ($dir === '/' || $dir === '\\' || $dir === '.') {
        $dir = '';
    }
    return ($https ? 'https://' : 'http://') . $host . $dir;
}

function card_public_url(string $token): string
{
    return app_public_base_url() . '/kart.php?k=' . rawurlencode($token);
}

function new_activation_code(): string
{
    return strtoupper(bin2hex(random_bytes(12)));
}

function ensure_default_card(PDO $database, int $licenseId, string $ownerName = '', string $phone = ''): void
{
    $exists = $database->prepare('SELECT id FROM cards WHERE license_id = :license LIMIT 1');
    $exists->execute(['license' => $licenseId]);
    if ($exists->fetch()) {
        return;
    }

    $name = trim($ownerName);
    if (mb_strlen($name) < 2) {
        $name = 'Araç sahibi';
    }
    $name = mb_substr($name, 0, 48);

    $insert = $database->prepare(
        'INSERT INTO cards (public_token, license_id, owner_name, phone) VALUES (:token, :license, :name, :phone)'
    );
    $insert->execute([
        'token' => bin2hex(random_bytes(12)),
        'license' => $licenseId,
        'name' => $name,
        'phone' => trim($phone)
    ]);
}

function issue_activation_code(PDO $database, string $orderReference, string $source, ?string $email, ?string $phone): string
{
    $code = new_activation_code();
    $statement = $database->prepare(
        'INSERT INTO activation_codes (code_hash, code_ciphertext, order_ref, source, customer_email, customer_phone)
         VALUES (:hash, :ciphertext, :order_ref, :source, :email, :phone)'
    );
    $statement->execute([
        'hash' => hash('sha256', $code),
        'ciphertext' => encrypt_activation_code($code),
        'order_ref' => $orderReference,
        'source' => $source,
        'email' => $email,
        'phone' => $phone
    ]);
    ensure_default_card($database, (int) $database->lastInsertId(), '', (string) ($phone ?? ''));
    return $code;
}

function deliver_activation_code(string $code, ?string $email, ?string $phone, string $context): array
{
    $emailSent = false;
    $smsSent = false;
    $body = $context . "\n\nAktivasyon kodunuz:\n{$code}\n\nKodu satin.php sayfasında kullanın. Kod, girdiğiniz tarayıcıya bağlanır; başka cihazda tekrar girebilirsiniz.";
    if (is_string($email) && $email !== '') {
        $emailSent = app_send_email($email, 'Araç iletişim kartı aktivasyon kodunuz', $body);
    }
    if (is_string($phone) && $phone !== '') {
        $smsSent = app_send_sms($phone, 'Aktivasyon kodunuz: ' . $code);
    }
    $waLink = '';
    if (is_string($phone) && $phone !== '') {
        $waLink = whatsapp_link($phone, 'Aktivasyon kodunuz: ' . $code);
    }
    return ['email' => $emailSent, 'sms' => $smsSent, 'wa_link' => $waLink];
}

function activation_encryption_key(): string
{
    ensure_storage_directory();
    $keyPath = APP_STORAGE_DIR . DIRECTORY_SEPARATOR . 'activation.key';

    if (is_file($keyPath)) {
        $key = file_get_contents($keyPath);
        if (is_string($key) && strlen($key) === 32) {
            return $key;
        }
        throw new RuntimeException('The activation encryption key is invalid.');
    }

    $key = random_bytes(32);
    $handle = @fopen($keyPath, 'xb');
    if ($handle === false) {
        if (is_file($keyPath)) {
            return activation_encryption_key();
        }
        throw new RuntimeException('Unable to create the activation encryption key.');
    }

    try {
        if (fwrite($handle, $key) !== 32) {
            throw new RuntimeException('Unable to write the activation encryption key.');
        }
    } finally {
        fclose($handle);
    }

    @chmod($keyPath, 0600);
    return $key;
}

function encrypt_activation_code(string $code): string
{
    if (!function_exists('openssl_encrypt')) {
        throw new RuntimeException('OpenSSL is required to encrypt activation codes.');
    }

    $nonce = random_bytes(12);
    $tag = '';
    $ciphertext = openssl_encrypt(
        $code,
        'aes-256-gcm',
        activation_encryption_key(),
        OPENSSL_RAW_DATA,
        $nonce,
        $tag,
        '',
        16
    );

    if (!is_string($ciphertext)) {
        throw new RuntimeException('Unable to encrypt the activation code.');
    }

    return base64_encode($nonce . $tag . $ciphertext);
}

function decrypt_activation_code(?string $encoded): ?string
{
    if (!function_exists('openssl_decrypt') || !is_string($encoded)) {
        return null;
    }

    $payload = base64_decode($encoded, true);
    if (!is_string($payload) || strlen($payload) < 29) {
        return null;
    }

    $nonce = substr($payload, 0, 12);
    $tag = substr($payload, 12, 16);
    $ciphertext = substr($payload, 28);
    $code = openssl_decrypt(
        $ciphertext,
        'aes-256-gcm',
        activation_encryption_key(),
        OPENSSL_RAW_DATA,
        $nonce,
        $tag
    );

    return is_string($code) ? $code : null;
}

function recover_activation_code(PDO $database, string $orderReference): ?array
{
    if (!$database->inTransaction()) {
        throw new LogicException('Activation recovery must run inside a database transaction.');
    }

    $query = $database->prepare('SELECT id, code_ciphertext FROM activation_codes WHERE order_ref = :order_ref');
    $query->execute(['order_ref' => $orderReference]);
    $license = $query->fetch();
    if (!$license) {
        return null;
    }

    $code = decrypt_activation_code($license['code_ciphertext']);
    $replaced = !is_string($code);
    if ($replaced) {
        $code = new_activation_code();
        $update = $database->prepare(
            'UPDATE activation_codes SET code_hash = :hash, code_ciphertext = :ciphertext, redeemed_at = NULL, redeemed_session = NULL WHERE id = :id'
        );
        $update->execute([
            'hash' => hash('sha256', $code),
            'ciphertext' => encrypt_activation_code($code),
            'id' => $license['id']
        ]);
    } else {
        $reset = $database->prepare('UPDATE activation_codes SET redeemed_at = NULL, redeemed_session = NULL WHERE id = :id');
        $reset->execute(['id' => $license['id']]);
    }

    ensure_default_card($database, (int) $license['id']);
    return ['code' => $code, 'replaced' => $replaced];
}

function shopier_verify_form_callback(array $post, string $secret): bool
{
    $random = (string) ($post['random_nr'] ?? '');
    $orderId = (string) ($post['platform_order_id'] ?? $post['orderid'] ?? '');
    $signature = (string) ($post['signature'] ?? '');
    if ($secret === '' || $random === '' || $orderId === '' || $signature === '') {
        return false;
    }
    $expected = base64_encode(hash_hmac('sha256', $random . $orderId, $secret, true));
    return hash_equals($expected, $signature);
}

function shopier_verify_webhook(string $rawBody, string $signatureHeader, string $token): bool
{
    if ($token === '' || $signatureHeader === '' || $rawBody === '') {
        return false;
    }
    $binary = hash_hmac('sha256', $rawBody, $token, true);
    $hex = hash_hmac('sha256', $rawBody, $token);
    $b64 = base64_encode($binary);
    return hash_equals($hex, $signatureHeader)
        || hash_equals($b64, $signatureHeader)
        || hash_equals($hex, strtolower($signatureHeader));
}

function shopier_amount_candidates(array $data): array
{
    $candidates = [];
    foreach (['total_price', 'price', 'total', 'amount', 'total_amount', 'grand_total'] as $key) {
        if (!isset($data[$key]) || !is_numeric($data[$key])) {
            continue;
        }
        $raw = str_replace(',', '.', trim((string) $data[$key]));
        if (!is_numeric($raw)) {
            continue;
        }
        if (str_contains($raw, '.')) {
            $candidates[] = (int) round(((float) $raw) * 100);
        } else {
            $int = (int) $raw;
            $candidates[] = $int;
            $candidates[] = $int * 100;
        }
    }
    return array_values(array_unique($candidates));
}

function normalize_phone_digits(string $phone): string
{
    return preg_replace('/\D/', '', $phone);
}

function phone_is_international(string $phone): bool
{
    $trimmed = ltrim(trim($phone), " \t\n\r\0\x0B(");
    return str_starts_with($trimmed, '+') || str_starts_with($trimmed, '00');
}

function international_phone(string $phone): string
{
    $digits = normalize_phone_digits($phone);
    if ($digits === '') {
        return '';
    }
    if (phone_is_international($phone)) {
        return '+' . ltrim($digits, '0');
    }
    if (str_starts_with($digits, '90')) {
        return '+' . $digits;
    }
    return '+90' . ltrim($digits, '0');
}

function whatsapp_link(string $phone, string $message): string
{
    $digits = normalize_phone_digits($phone);
    if (strlen($digits) < 10) {
        return '';
    }
    if (phone_is_international($phone)) {
        $digits = ltrim($digits, '0');
    } else {
        if (!str_starts_with($digits, '90')) {
            $digits = '90' . ltrim($digits, '0');
        }
    }
    return 'https://wa.me/' . $digits . '?text=' . rawurlencode($message);
}

function card_templates(): array
{
    return [
        'navy' => ['label' => 'Lacivert', 'info' => '#0c1a31', 'qr' => '#d8f1fa', 'ink' => '#14324b'],
        'forest' => ['label' => 'Orman', 'info' => '#194533', 'qr' => '#d5ef73', 'ink' => '#17241f'],
        'sand' => ['label' => 'Kum', 'info' => '#3d3428', 'qr' => '#f3e6c8', 'ink' => '#3d3428']
    ];
}

function legal_links_html(): string
{
    return '<a href="gizlilik.php">Gizlilik</a> · <a href="kvkk.php">KVKK</a> · <a href="cerez.php">Çerezler</a> · <a href="mesafeli-satis.php">Mesafeli satış</a> · <a href="on-bilgilendirme.php">Ön bilgilendirme</a> · <a href="iade.php">İade</a>';
}

function company_info_html(): string
{
    $settings = app_settings();
    $lines = [];
    foreach (['company_name', 'company_address', 'company_tax_no'] as $key) {
        $value = trim((string) ($settings[$key] ?? ''));
        if ($value !== '') {
            $lines[] = escape_html($value);
        }
    }
    $contact = [];
    $email = trim((string) ($settings['public_email'] ?? ''));
    $phone = trim((string) ($settings['public_phone'] ?? ''));
    if ($email !== '') {
        $contact[] = '<a href="mailto:' . escape_html($email) . '">' . escape_html($email) . '</a>';
    }
    if ($phone !== '') {
        $contact[] = escape_html($phone);
    }
    if ($contact !== []) {
        $lines[] = implode(' · ', $contact);
    }
    return $lines === [] ? '' : implode('<br>', $lines);
}
