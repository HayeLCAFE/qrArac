<?php
declare(strict_types=1);
require_once __DIR__ . '/auth.php';
header('Cache-Control: no-store, private');
header('X-Robots-Tag: noindex, nofollow');

$database = app_db();
$passwordReady = is_file(ADMIN_PASSWORD_HASH_PATH);
$adminAuthenticated = ($_SESSION['is_admin'] ?? false) === true && $passwordReady;
if (!$passwordReady) {
  unset($_SESSION['is_admin'], $_SESSION['admin_last_activity'], $_SESSION['admin_flash']);
}
if ($adminAuthenticated) {
  $lastActivity = filter_var($_SESSION['admin_last_activity'] ?? null, FILTER_VALIDATE_INT);
  if ($lastActivity && time() - $lastActivity > 20 * 60) {
    unset($_SESSION['is_admin'], $_SESSION['admin_last_activity'], $_SESSION['admin_flash']);
    session_regenerate_id(true);
    header('Location: yonetim.php?timeout=1', true, 303);
    exit;
  }
  $_SESSION['admin_last_activity'] = time();
}
$errorMessage = '';

function redirect_admin(): never
{
    header('Location: yonetim.php', true, 303);
    exit;
}

function store_admin_flash(array $flash): void
{
    $_SESSION['admin_flash'] = $flash;
    redirect_admin();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_is_valid()) {
        http_response_code(400);
        $errorMessage = 'Form doğrulaması başarısız oldu. Sayfayı yenileyip tekrar deneyin.';
    } else {
        $action = is_string($_POST['action'] ?? null) ? $_POST['action'] : '';

        if ($action === 'login' && !$adminAuthenticated) {
            $ipHash = request_ip_hash();
            $attemptCount = $database->prepare(
                "SELECT COUNT(*) FROM admin_login_attempts WHERE ip_hash = :ip AND succeeded = 0 AND attempted_at >= datetime('now', '-15 minutes')"
            );
            $attemptCount->execute(['ip' => $ipHash]);

            if ((int) $attemptCount->fetchColumn() >= 8) {
                $errorMessage = 'Çok fazla başarısız deneme oldu. 15 dakika sonra tekrar deneyin.';
            } else {
                $password = is_string($_POST['password'] ?? null) ? $_POST['password'] : '';
                $storedHash = $passwordReady ? trim((string) file_get_contents(ADMIN_PASSWORD_HASH_PATH)) : '';
                $isValid = $passwordReady && password_verify($password, $storedHash);
                $recordAttempt = $database->prepare('INSERT INTO admin_login_attempts (ip_hash, succeeded) VALUES (:ip, :succeeded)');
                $recordAttempt->execute(['ip' => $ipHash, 'succeeded' => $isValid ? 1 : 0]);
                $database->exec("DELETE FROM admin_login_attempts WHERE attempted_at < datetime('now', '-1 day')");

                if ($isValid) {
                    if (password_needs_rehash($storedHash, PASSWORD_DEFAULT)) {
                        $rehashed = password_hash($password, PASSWORD_DEFAULT);
                        if ($rehashed !== false) {
                            file_put_contents(ADMIN_PASSWORD_HASH_PATH, $rehashed, LOCK_EX);
                            @chmod(ADMIN_PASSWORD_HASH_PATH, 0600);
                        }
                    }
                    session_regenerate_id(true);
                    $_SESSION['is_admin'] = true;
                    unset($_SESSION['csrf_token']);
                    redirect_admin();
                }

                $errorMessage = 'Giriş bilgileri doğrulanamadı.';
            }
        } elseif ($adminAuthenticated && $action === 'logout') {
            $_SESSION = [];
            session_regenerate_id(true);
            redirect_admin();
        } elseif ($adminAuthenticated && $action === 'clear_flash') {
          unset($_SESSION['admin_flash']);
          redirect_admin();
        } elseif ($adminAuthenticated && $action === 'save_settings') {
            try {
                $priceText = str_replace(',', '.', trim((string) ($_POST['price_tl'] ?? '')));
                if (!preg_match('/^\d{1,6}(?:\.\d{1,2})?$/', $priceText)) {
                    throw new InvalidArgumentException('Fiyatı TL olarak girin. En fazla iki ondalık basamak kullanın.');
                }
                $priceKurus = (int) round((float) $priceText * 100);
                if ($priceKurus < 100 || $priceKurus > 100000000) {
                    throw new InvalidArgumentException('Fiyat 1 TL ile 1.000.000 TL arasında olmalı.');
                }

                $shopierUrl = trim((string) ($_POST['shopier_url'] ?? ''));
                if ($shopierUrl !== '') {
                    $host = strtolower((string) parse_url($shopierUrl, PHP_URL_HOST));
                    if (!in_array($host, ['shopier.com', 'www.shopier.com'], true) || parse_url($shopierUrl, PHP_URL_SCHEME) !== 'https') {
                        throw new InvalidArgumentException('Shopier adresi https://www.shopier.com ile başlamalı.');
                    }
                }

                $iban = strtoupper(preg_replace('/\s+/', '', trim((string) ($_POST['iban'] ?? ''))));
                if ($iban !== '' && !validate_turkish_iban($iban)) {
                    throw new InvalidArgumentException('IBAN geçerli bir Türkiye IBAN numarası değil.');
                }
                $bankName = trim((string) ($_POST['bank_name'] ?? ''));
                $accountHolder = trim((string) ($_POST['account_holder'] ?? ''));
                if ($iban !== '' && ($bankName === '' || $accountHolder === '')) {
                    throw new InvalidArgumentException('IBAN kaydetmek için banka ve hesap sahibi bilgileri gerekli.');
                }

                $adminEmail = trim((string) ($_POST['admin_email'] ?? ''));
                $mailFrom = trim((string) ($_POST['mail_from'] ?? ''));
                $publicEmail = trim((string) ($_POST['public_email'] ?? ''));
                $publicPhone = trim((string) ($_POST['public_phone'] ?? ''));
                if (($adminEmail !== '' && !filter_var($adminEmail, FILTER_VALIDATE_EMAIL)) || ($mailFrom !== '' && !filter_var($mailFrom, FILTER_VALIDATE_EMAIL)) || ($publicEmail !== '' && !filter_var($publicEmail, FILTER_VALIDATE_EMAIL))) {
                    throw new InvalidArgumentException('E-posta adreslerini kontrol edin.');
                }
                $publicPhoneDigits = preg_replace('/\D/', '', $publicPhone);
                if ($publicPhone !== '' && (strlen($publicPhoneDigits) < 10 || strlen($publicPhoneDigits) > 15)) {
                    throw new InvalidArgumentException('Herkese açık telefon numarasını kontrol edin.');
                }

                $currentSettings = app_settings();
                $shopierSecret = (string) ($_POST['shopier_api_secret'] ?? '');
                $smtpPass = (string) ($_POST['smtp_pass'] ?? '');
                $smsPassword = (string) ($_POST['sms_password'] ?? '');
                $smtpPort = (int) ($_POST['smtp_port'] ?? 587);
                if ($smtpPort < 1 || $smtpPort > 65535) {
                    $smtpPort = 587;
                }
                $maxCards = filter_var($_POST['max_cards_per_license'] ?? 3, FILTER_VALIDATE_INT);
                $baseUrl = trim((string) ($_POST['public_base_url'] ?? ''));
                if ($baseUrl !== '' && !preg_match('#^https://#i', $baseUrl)) {
                    throw new InvalidArgumentException('Genel site adresi https:// ile başlamalı.');
                }
                $posted = [
                    'price_kurus' => $priceKurus,
                    'shopier_url' => $shopierUrl,
                    'bank_name' => $bankName,
                    'account_holder' => $accountHolder,
                    'iban' => $iban,
                    'payment_note' => trim((string) ($_POST['payment_note'] ?? '')),
                    'admin_email' => $adminEmail,
                    'mail_from' => $mailFrom,
                    'force_https' => isset($_POST['force_https']),
                    'max_cards_per_license' => $maxCards ? max(1, min(20, $maxCards)) : 3,
                    'public_base_url' => rtrim($baseUrl, '/'),
                    'company_name' => trim((string) ($_POST['company_name'] ?? '')),
                    'company_address' => trim((string) ($_POST['company_address'] ?? '')),
                    'company_tax_no' => trim((string) ($_POST['company_tax_no'] ?? '')),
                    'public_email' => $publicEmail,
                    'public_phone' => $publicPhone,
                    'trusted_proxies' => trim((string) ($_POST['trusted_proxies'] ?? '')),
                    'shopier_api_key' => trim((string) ($_POST['shopier_api_key'] ?? $currentSettings['shopier_api_key'])),
                    'shopier_api_secret' => $shopierSecret === '' ? $currentSettings['shopier_api_secret'] : $shopierSecret,
                    'shopier_webhook_token' => trim((string) ($_POST['shopier_webhook_token'] ?? $currentSettings['shopier_webhook_token'])),
                    'smtp_host' => trim((string) ($_POST['smtp_host'] ?? $currentSettings['smtp_host'])),
                    'smtp_port' => $smtpPort,
                    'smtp_user' => trim((string) ($_POST['smtp_user'] ?? $currentSettings['smtp_user'])),
                    'smtp_pass' => $smtpPass === '' ? $currentSettings['smtp_pass'] : $smtpPass,
                    'smtp_encryption' => in_array($_POST['smtp_encryption'] ?? 'tls', ['tls', 'ssl', 'none'], true) ? $_POST['smtp_encryption'] : 'tls',
                    'sms_provider' => trim((string) ($_POST['sms_provider'] ?? $currentSettings['sms_provider'])),
                    'sms_usercode' => trim((string) ($_POST['sms_usercode'] ?? $currentSettings['sms_usercode'])),
                    'sms_password' => $smsPassword === '' ? $currentSettings['sms_password'] : $smsPassword,
                    'sms_header' => trim((string) ($_POST['sms_header'] ?? $currentSettings['sms_header']))
                ];
                if (strlen($posted['payment_note']) > 500) {
                    throw new InvalidArgumentException('Ödeme açıklaması 500 karakterden kısa olmalı.');
                }
                if (strlen($posted['company_name']) > 160 || strlen($posted['company_address']) > 300 || strlen($posted['company_tax_no']) > 40 || strlen($posted['trusted_proxies']) > 300) {
                    throw new InvalidArgumentException('İletişim ve şirket bilgileri çok uzun.');
                }

                $settings = array_merge($currentSettings, $posted);
                save_app_settings($settings);
                store_admin_flash(['type' => 'success', 'message' => 'Ayarlar kaydedildi.']);
            } catch (Throwable $exception) {
                $errorMessage = $exception instanceof InvalidArgumentException
                    ? $exception->getMessage()
                    : 'Ayarlar kaydedilemedi. Depolama dizini yazma izinlerini kontrol edin.';
            }
        } elseif ($adminAuthenticated && $action === 'test_email') {
            $target = trim((string) ($_POST['test_email'] ?? app_settings()['admin_email']));
            if (trim((string) app_settings()['mail_from']) === '') {
                store_admin_flash(['type' => 'error', 'message' => 'Önce ayarlardan gönderici e-postasını (mail_from) doldurun; boşken e-posta alıcının adresinden gider ve spam\'e düşer.']);
            }
            $ok = app_send_email($target, 'Araç İletişim test e-postası', 'SMTP/mail ayarı çalışıyor.');
            store_admin_flash(['type' => $ok ? 'success' : 'error', 'message' => $ok ? 'Test e-postası gönderildi.' : 'E-posta gönderilemedi. SMTP ayarlarını kontrol edin.']);
        } elseif ($adminAuthenticated && $action === 'test_sms') {
            $target = trim((string) ($_POST['test_phone'] ?? ''));
            $ok = app_send_sms($target, 'Araç Iletisim SMS testi');
            store_admin_flash(['type' => $ok ? 'success' : 'error', 'message' => $ok ? 'Test SMS gönderildi.' : 'SMS gönderilemedi. Netgsm bilgilerini kontrol edin veya SMS uygulamasını kullanın.']);
        } elseif ($adminAuthenticated && $action === 'change_password') {
            $currentPassword = is_string($_POST['current_password'] ?? null) ? $_POST['current_password'] : '';
            $newPassword = is_string($_POST['new_password'] ?? null) ? $_POST['new_password'] : '';
            $confirmPassword = is_string($_POST['confirm_password'] ?? null) ? $_POST['confirm_password'] : '';
            $storedHash = trim((string) file_get_contents(ADMIN_PASSWORD_HASH_PATH));

            if (!password_verify($currentPassword, $storedHash)) {
                $errorMessage = 'Mevcut parola doğrulanamadı.';
            } elseif (strlen($newPassword) < 14 || !hash_equals($newPassword, $confirmPassword)) {
                $errorMessage = 'Yeni parola en az 14 karakter olmalı ve doğrulama alanıyla eşleşmeli.';
            } else {
                $newHash = password_hash($newPassword, PASSWORD_DEFAULT);
                if ($newHash === false || file_put_contents(ADMIN_PASSWORD_HASH_PATH, $newHash, LOCK_EX) === false) {
                    $errorMessage = 'Yeni parola kaydedilemedi.';
                } else {
                    @chmod(ADMIN_PASSWORD_HASH_PATH, 0600);
                    session_regenerate_id(true);
                    store_admin_flash(['type' => 'success', 'message' => 'Yönetici parolası değiştirildi.']);
                }
            }
              } elseif ($adminAuthenticated && in_array($action, ['recover_license_email', 'recover_license_sms'], true)) {
                $orderReference = trim((string) ($_POST['order_ref'] ?? ''));
                $delivery = $action === 'recover_license_email' ? 'email' : 'sms';
                $email = trim((string) ($_POST['email'] ?? ''));
                $phone = trim((string) ($_POST['phone'] ?? ''));
                $phoneDigits = preg_replace('/\D/', '', $phone);

                if ($orderReference === '' || strlen($orderReference) > 120) {
                  $errorMessage = 'Sipariş referansını kontrol edin.';
                } elseif ($delivery === 'email' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                  $errorMessage = 'Geçerli bir e-posta adresi girin.';
                } elseif ($delivery === 'sms' && (strlen($phoneDigits) < 10 || strlen($phoneDigits) > 15)) {
                  $errorMessage = 'Geçerli bir telefon numarası girin.';
                } else {
                  try {
                    $database->beginTransaction();
                    $recovery = recover_activation_code($database, $orderReference);
                    if ($recovery === null) {
                      $database->rollBack();
                      $errorMessage = 'Bu sipariş referansı için aktivasyon kodu bulunamadı.';
                    } else {
                      $database->commit();
                      $code = $recovery['code'];
                      $deliveryResult = false;
                      if ($delivery === 'email') {
                        $deliveryResult = app_send_email(
                          $email,
                          'Araç iletişim kartı aktivasyon kodunuz',
                          "Talebiniz üzerine aktivasyon kodunuz hazırlandı. satin.php sayfasında kullanın:\n\n{$code}\n\nÖnceki cihaz oturumu sıfırlandı."
                        );
                      }

                      $message = $recovery['replaced']
                        ? 'Eski kayıtta geri alınabilir kod yoktu; yenisi üretildi ve eski kod iptal edildi.'
                        : 'Aynı kod yeniden hazırlandı; önceki cihaz oturumu sıfırlandı.';
                      if ($delivery === 'email' && !$deliveryResult) {
                        $message .= ' E-posta gönderilemedi; kodu güvenli kanaldan iletin.';
                      }
                      $waLink = whatsapp_link($phone, "Aktivasyon kodunuz: {$code}");
                      store_admin_flash([
                        'type' => 'success',
                        'message' => $message,
                        'code' => $code,
                        'wa_link' => $delivery === 'sms' ? $waLink : ''
                      ]);
                    }
                  } catch (Throwable $exception) {
                    if ($database->inTransaction()) {
                      $database->rollBack();
                    }
                    error_log($exception->getMessage());
                    $errorMessage = 'Kod kurtarılamadı. Sipariş referansını ve özel depolamadaki activation.key dosyasını kontrol edin.';
                  }
                }
        } elseif ($adminAuthenticated && str_starts_with($action, 'payment_')) {
            $paymentId = filter_var($_POST['payment_id'] ?? null, FILTER_VALIDATE_INT);
          $recoveryActions = ['payment_recover_email', 'payment_recover_sms'];
          $expectedStatus = in_array($action, $recoveryActions, true) ? 'approved' : 'pending';
          $paymentQuery = $database->prepare('SELECT * FROM payment_notifications WHERE id = :id AND status = :status');
          $paymentQuery->execute(['id' => $paymentId, 'status' => $expectedStatus]);
            $payment = $paymentQuery->fetch();

            if (!$payment) {
                $errorMessage = 'Bildirim bulunamadı veya daha önce işlendi.';
            } elseif ($action === 'payment_reject') {
                $reject = $database->prepare('UPDATE payment_notifications SET status = "rejected", processed_at = CURRENT_TIMESTAMP WHERE id = :id AND status = "pending"');
                $reject->execute(['id' => $paymentId]);
                store_admin_flash(['type' => 'success', 'message' => 'Ödeme bildirimi reddedildi.']);
            } elseif (in_array($action, ['payment_email', 'payment_sms'], true)) {
                $delivery = $action === 'payment_email' ? 'email' : 'sms';
                if (($delivery === 'email' && empty($payment['email'])) || ($delivery === 'sms' && empty($payment['phone']))) {
                    $errorMessage = $delivery === 'email' ? 'Bu müşteri e-posta adresi paylaşmamış.' : 'Bu müşteri telefon numarası paylaşmamış.';
                } else {
                    try {
                        $database->beginTransaction();
                        $code = strtoupper(bin2hex(random_bytes(12)));
                        $license = $database->prepare('INSERT INTO activation_codes (code_hash, code_ciphertext, order_ref) VALUES (:hash, :ciphertext, :order_ref)');
                        $license->execute([
                            'hash' => hash('sha256', $code),
                          'ciphertext' => encrypt_activation_code($code),
                            'order_ref' => 'bank-transfer-' . $paymentId
                        ]);
                        $licenseId = (int) $database->lastInsertId();
                        ensure_default_card($database, $licenseId, (string) $payment['customer_name'], (string) ($payment['phone'] ?? ''));
                        $update = $database->prepare('UPDATE payment_notifications SET status = "approved", processed_at = CURRENT_TIMESTAMP WHERE id = :id AND status = "pending"');
                        $update->execute(['id' => $paymentId]);
                        if ($update->rowCount() !== 1) {
                            throw new RuntimeException('Payment notification was already processed.');
                        }
                        $database->commit();

                        $deliveryResult = false;
                        if ($delivery === 'email') {
                            $deliveryResult = app_send_email(
                                (string) $payment['email'],
                                'Araç iletişim kartı aktivasyon kodunuz',
                                "Ödemeniz onaylandı. Kartınızı oluşturmak için satin.php sayfasına gidip bu kodu girin:\n\n{$code}\n\nBu lisans tek kullanımlıktır; istediğiniz cihazdan tekrar girebilirsiniz."
                            );
                        }

                        $message = $delivery === 'email'
                            ? ($deliveryResult ? 'Ödeme onaylandı ve kod e-posta ile gönderildi.' : 'Ödeme onaylandı ancak e-posta sunucusu gönderemedi. Kodu kopyalayıp müşteriye iletin.')
                            : 'Ödeme onaylandı. Kodu aşağıdan kopyalayın veya WhatsApp bağlantısını kullanın.';
                        $waLink = whatsapp_link((string) $payment['phone'], "Ödemeniz onaylandı. Araç iletişim kartınızı oluşturmak için aktivasyon kodunuz: {$code}");
                        store_admin_flash([
                            'type' => 'success',
                            'message' => $message,
                            'code' => $code,
                            'wa_link' => $delivery === 'sms' ? $waLink : ''
                        ]);
                    } catch (Throwable $exception) {
                        if ($database->inTransaction()) {
                            $database->rollBack();
                        }
                        error_log($exception->getMessage());
                        $errorMessage = 'Kod oluşturulamadı. Veritabanı kayıtlarını kontrol edin.';
                    }
                }
                  } elseif (in_array($action, $recoveryActions, true)) {
                    $delivery = $action === 'payment_recover_email' ? 'email' : 'sms';
                    if (($delivery === 'email' && empty($payment['email'])) || ($delivery === 'sms' && empty($payment['phone']))) {
                      $errorMessage = $delivery === 'email' ? 'Bu müşteri e-posta adresi paylaşmamış.' : 'Bu müşteri telefon numarası paylaşmamış.';
                    } else {
                      $orderReference = 'bank-transfer-' . $paymentId;
                      $licenseQuery = $database->prepare('SELECT id, code_ciphertext FROM activation_codes WHERE order_ref = :order_ref');
                      $licenseQuery->execute(['order_ref' => $orderReference]);
                      $license = $licenseQuery->fetch();

                      try {
                        $database->beginTransaction();
                        $code = $license ? decrypt_activation_code($license['code_ciphertext']) : null;
                        $replaced = !is_string($code);

                        if ($replaced) {
                          $code = strtoupper(bin2hex(random_bytes(12)));
                          if ($license) {
                            $replace = $database->prepare('UPDATE activation_codes SET code_hash = :hash, code_ciphertext = :ciphertext, redeemed_at = NULL, redeemed_session = NULL WHERE id = :id');
                            $replace->execute([
                              'hash' => hash('sha256', $code),
                              'ciphertext' => encrypt_activation_code($code),
                              'id' => $license['id']
                            ]);
                          } else {
                            $insert = $database->prepare('INSERT INTO activation_codes (code_hash, code_ciphertext, order_ref) VALUES (:hash, :ciphertext, :order_ref)');
                            $insert->execute([
                              'hash' => hash('sha256', $code),
                              'ciphertext' => encrypt_activation_code($code),
                              'order_ref' => $orderReference
                            ]);
                          }
                        } else {
                          $reset = $database->prepare('UPDATE activation_codes SET redeemed_at = NULL, redeemed_session = NULL WHERE id = :id');
                          $reset->execute(['id' => $license['id']]);
                        }
                        ensure_default_card(
                          $database,
                          $license ? (int) $license['id'] : (int) $database->lastInsertId(),
                          (string) $payment['customer_name'],
                          (string) ($payment['phone'] ?? '')
                        );
                        $database->commit();

                        $deliveryResult = false;
                        if ($delivery === 'email') {
                          $deliveryResult = app_send_email(
                            (string) $payment['email'],
                            'Araç iletişim kartı aktivasyon kodunuz',
                            "Talebiniz üzerine aktivasyon kodunuz yeniden hazırlandı. satin.php sayfasında kullanın:\n\n{$code}\n\nÖnceki cihaz oturumu sıfırlandı."
                          );
                        }

                        $message = $replaced
                          ? 'Eski kayıtta kodun geri alınabilir kopyası yoktu; yeni kod üretildi ve eski kod iptal edildi.'
                          : 'Aynı kod yeniden hazırlandı; önceki cihaz oturumu sıfırlandı.';
                        if ($delivery === 'email' && !$deliveryResult) {
                          $message .= ' E-posta gönderilemedi; kodu güvenli kanaldan iletin.';
                        }
                        $waLink = whatsapp_link((string) $payment['phone'], "Aktivasyon kodunuz: {$code}");
                        store_admin_flash([
                          'type' => 'success',
                          'message' => $message,
                          'code' => $code,
                          'wa_link' => $delivery === 'sms' ? $waLink : ''
                        ]);
                      } catch (Throwable $exception) {
                        if ($database->inTransaction()) {
                          $database->rollBack();
                        }
                        error_log($exception->getMessage());
                        $errorMessage = 'Kod kurtarılamadı. Özel depolamadaki activation.key dosyasını kontrol edin.';
                      }
                    }
            }
        }
    }
}

$adminAuthenticated = ($_SESSION['is_admin'] ?? false) === true && is_file(ADMIN_PASSWORD_HASH_PATH);
$settings = app_settings();
$flash = $_SESSION['admin_flash'] ?? null;
$notifications = [];
$pendingCount = 0;

if ($adminAuthenticated) {
    $notifications = $database->query('SELECT * FROM payment_notifications ORDER BY CASE status WHEN "pending" THEN 0 ELSE 1 END, id DESC LIMIT 100')->fetchAll();
    $pendingCount = (int) $database->query('SELECT COUNT(*) FROM payment_notifications WHERE status = "pending"')->fetchColumn();
}

?>
<!doctype html>
<html lang="tr">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="theme-color" content="#f2f3ed">
  <title>Yönetim | Araç İletişim</title>
  <style nonce="<?= app_csp_nonce() ?>">
    :root { color-scheme: light; --paper: #f2f3ed; --ink: #17241f; --muted: #68746e; --line: #dce1d8; --green: #245c48; --lime: #d5ef73; font-family: "Segoe UI", sans-serif; font-synthesis: none; text-rendering: optimizeLegibility; }
    * { box-sizing: border-box; }
    body { min-width: 320px; min-height: 100vh; margin: 0; color: var(--ink); background: var(--paper) radial-gradient(#cbd4c8 0.7px, transparent 0.7px); background-size: auto, 19px 19px; }
    .shell { width: min(1120px, calc(100% - 40px)); min-height: 100vh; margin: auto; padding: 22px 0; }
    .topbar, .brand, .top-actions, .panel-heading, .payment-actions { display: flex; align-items: center; }
    .topbar, .panel-heading { justify-content: space-between; gap: 16px; }
    .brand { gap: 10px; font-size: 13px; font-weight: 700; }
    .brand-mark { width: 30px; height: 30px; display: grid; place-items: center; border-radius: 9px; color: var(--lime); background: var(--green); font: 800 17px "Segoe UI", sans-serif; }
    .top-actions { gap: 12px; }
    .top-actions a { color: var(--green); font-size: 12px; font-weight: 700; text-decoration: none; }
    .logout-form { margin: 0; }
    .logout-button { padding: 8px 12px; border: 1px solid var(--line); border-radius: 3px; color: var(--ink); background: #fff; font: 600 12px "Segoe UI", sans-serif; cursor: pointer; }
    h1 { margin: 42px 0 6px; font: 800 34px/1.1 "Segoe UI", sans-serif; letter-spacing: 0; }
    .lede { margin: 0 0 26px; color: var(--muted); font-size: 13px; }
    .dashboard { display: grid; grid-template-columns: minmax(0, 0.86fr) minmax(0, 1.14fr); align-items: start; gap: 24px; }
    .panel { padding: 22px; border: 1px solid #dfe4dc; border-radius: 5px; background: #ffffffdf; box-shadow: 0 14px 34px #1c322210; }
    .panel + .panel { margin-top: 20px; }
    .panel h2 { margin: 0; font: 700 17px "Segoe UI", sans-serif; letter-spacing: 0; }
    .panel-heading { margin-bottom: 16px; }
    .count { min-width: 27px; height: 27px; display: grid; place-items: center; border-radius: 50%; color: #233414; background: var(--lime); font-size: 12px; font-weight: 800; }
    .field { display: block; margin: 0 0 14px; }
    .field > span { display: block; margin-bottom: 6px; font-size: 11px; font-weight: 700; }
    .field input, .field textarea, .field select { width: 100%; min-height: 42px; padding: 10px 11px; border: 1px solid #cdd5cb; border-radius: 3px; color: var(--ink); background: #fff; font: 13px "Segoe UI", sans-serif; }
    .subhead { margin: 20px 0 4px; font: 700 13px "Segoe UI", sans-serif; color: var(--ink); }
    .field input[type="checkbox"] { width: auto; min-height: 0; }
    .field.check { display: flex; gap: 8px; align-items: center; }
    .field.check > span { margin: 0; }
    .field textarea { min-height: 72px; resize: vertical; }
    .field input:focus, .field textarea:focus, .field select:focus { outline: 2px solid #245c4828; border-color: var(--green); }
    .grid-fields { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; }
    .button { min-height: 42px; display: inline-flex; align-items: center; justify-content: center; padding: 0 15px; border: 1px solid var(--green); border-radius: 3px; color: #fff; background: var(--green); font: 700 12px "Segoe UI", sans-serif; cursor: pointer; }
    .button:hover { background: #194533; }
    .button.secondary { border-color: #bac6bb; color: var(--ink); background: #fff; }
    .full-button { width: 100%; }
    .notice { margin: 0 0 16px; padding: 11px 12px; border-left: 3px solid #a2bd60; color: #405048; background: #f2f5eb; font-size: 12px; line-height: 1.5; }
    .notice.error { border-color: #b94a3b; color: #812c22; background: #fff1ed; }
    .notice.success { border-color: var(--green); }
    .muted { color: var(--muted); font-size: 11px; line-height: 1.55; }
    .payment-list { display: grid; gap: 12px; }
    .payment-item { padding: 15px; border: 1px solid var(--line); border-radius: 4px; background: #fff; }
    .payment-item.pending { border-left: 3px solid #a2bd60; }
    .payment-item h3 { margin: 0 0 7px; font: 700 14px "Segoe UI", sans-serif; }
    .payment-item p { margin: 3px 0; color: #526057; font-size: 11px; line-height: 1.5; overflow-wrap: anywhere; }
    .payment-actions { flex-wrap: wrap; gap: 7px; margin-top: 12px; }
    .payment-actions .button { min-height: 35px; padding: 0 10px; font-size: 11px; }
    .payment-actions .reject { border-color: #d4b8b2; color: #7c342b; background: #fff; }
    .flash-code { margin-top: 14px; padding: 13px; border: 1px solid #c9d6c5; border-radius: 3px; background: #f3f8ed; }
    .flash-code code { display: block; margin: 9px 0; font-size: 18px; font-weight: 800; letter-spacing: 0.04em; overflow-wrap: anywhere; }
    .flash-code a { color: var(--green); font-size: 12px; font-weight: 700; }
    .login-wrap { width: min(420px, 100%); margin: 12vh auto 0; }
    .login-wrap h1 { margin-top: 0; font-size: 30px; }
    .login-panel { padding: 26px; border: 1px solid #dfe4dc; border-radius: 5px; background: #ffffffdf; box-shadow: 0 18px 48px #1c32221a; }
    .login-panel .field { margin-top: 20px; }
    .login-panel input[type="password"] { min-height: 46px; }
    .code-command { padding: 10px; border-radius: 3px; color: #e8f4eb; background: #17241f; font: 12px Consolas, monospace; overflow-wrap: anywhere; }
    .empty { padding: 24px 12px; color: var(--muted); font-size: 12px; text-align: center; }
    .login-note { margin: 14px 0 0; }
    .flash-form { margin-top: 10px; }
    .field.spaced { margin-top: 14px; }
    @media (max-width: 800px) { .dashboard { grid-template-columns: 1fr; } }
    @media (max-width: 520px) { .shell { width: calc(100% - 28px); padding: 15px 0; } h1 { margin-top: 32px; font-size: 30px; } .panel { padding: 17px; } .grid-fields { grid-template-columns: 1fr; gap: 0; } .top-actions { gap: 8px; } }
  </style>
</head>
<body>
  <div class="shell">
    <?php if (!$adminAuthenticated): ?>
      <main class="login-wrap">
        <div class="brand"><span class="brand-mark" aria-hidden="true">A</span><span>ARAÇ İLETİŞİM · YÖNETİM</span></div>
        <h1>Yönetici girişi</h1>
        <p class="lede">Ayarlar ve ödeme bildirimleri yalnızca yönetici hesabına açıktır.</p>
        <section class="login-panel">
          <?php if (!$passwordReady): ?>
            <p class="notice">Yönetici parolası henüz kurulmamış. Sunucuda, proje klasöründe aşağıdaki komutu çalıştırın; parolanızı terminale girin. Parolayı kimseyle paylaşmayın.</p>
            <div class="code-command">C:\xampp\php\php.exe .\setup-admin-password.php</div>
          <?php else: ?>
            <?php if ($errorMessage !== ''): ?><p class="notice error" role="alert"><?= escape_html($errorMessage) ?></p><?php endif; ?>
            <form method="post" action="yonetim.php">
              <input type="hidden" name="csrf_token" value="<?= escape_html(csrf_token()) ?>">
              <input type="hidden" name="action" value="login">
              <label class="field"><span>Yönetici parolası</span><input type="password" name="password" autocomplete="current-password" required autofocus></label>
              <button class="button full-button" type="submit">Güvenli giriş</button>
            </form>
          <?php endif; ?>
          <p class="muted login-note">Giriş denemeleri sınırlandırılır. Üretimde bu sayfayı yalnızca HTTPS üzerinden kullanın.</p>
        </section>
      </main>
    <?php else: ?>
      <header class="topbar">
        <div class="brand"><span class="brand-mark" aria-hidden="true">A</span><span>ARAÇ İLETİŞİM · YÖNETİM</span></div>
        <div class="top-actions"><a href="satin.php">Mağazayı görüntüle</a><form class="logout-form" method="post"><input type="hidden" name="csrf_token" value="<?= escape_html(csrf_token()) ?>"><input type="hidden" name="action" value="logout"><button class="logout-button" type="submit">Çıkış</button></form></div>
      </header>
      <main>
        <h1>Yönetim paneli</h1>
        <p class="lede">Ürün ayarları, havale bildirimleri ve aktivasyon kodları.</p>
        <?php if ($errorMessage !== ''): ?><p class="notice error" role="alert"><?= escape_html($errorMessage) ?></p><?php endif; ?>
        <?php if (is_array($flash)): ?>
          <div class="notice <?= escape_html($flash['type'] ?? 'success') ?>" role="status"><?= escape_html($flash['message'] ?? '') ?></div>
          <?php if (!empty($flash['code'])): ?>
            <div class="flash-code"><strong>Aktivasyon kodu · müşteriye bir kez iletin</strong><code><?= escape_html($flash['code']) ?></code>
              <?php if (!empty($flash['wa_link'])): ?><a href="<?= escape_html($flash['wa_link']) ?>" target="_blank" rel="noopener">WhatsApp'ta hazırla</a><?php endif; ?>
              <form method="post" class="flash-form"><input type="hidden" name="csrf_token" value="<?= escape_html(csrf_token()) ?>"><input type="hidden" name="action" value="clear_flash"><button class="button secondary" type="submit">Kodu gizle</button></form>
            </div>
          <?php endif; ?>
        <?php endif; ?>

        <div class="dashboard">
          <div>
            <section class="panel">
              <div class="panel-heading"><h2>Ürün ve ödeme ayarları</h2></div>
              <form method="post" action="yonetim.php">
                <input type="hidden" name="csrf_token" value="<?= escape_html(csrf_token()) ?>"><input type="hidden" name="action" value="save_settings">
                <label class="field"><span>Ürün fiyatı (TL)</span><input name="price_tl" inputmode="decimal" value="<?= escape_html(number_format((int) $settings['price_kurus'] / 100, 2, ',', '')) ?>" required></label>
                <label class="field"><span>Shopier ürün bağlantısı</span><input name="shopier_url" type="url" placeholder="https://www.shopier.com/..." value="<?= escape_html((string) $settings['shopier_url']) ?>"></label>
                <div class="grid-fields">
                  <label class="field"><span>Banka adı</span><input name="bank_name" maxlength="100" value="<?= escape_html((string) $settings['bank_name']) ?>"></label>
                  <label class="field"><span>Hesap sahibi</span><input name="account_holder" maxlength="120" value="<?= escape_html((string) $settings['account_holder']) ?>"></label>
                </div>
                <label class="field"><span>IBAN</span><input name="iban" inputmode="text" autocomplete="off" maxlength="32" value="<?= escape_html((string) $settings['iban']) ?>" placeholder="TR ile başlayan IBAN"></label>
                <label class="field"><span>Havale açıklaması</span><textarea name="payment_note" maxlength="500"><?= escape_html((string) $settings['payment_note']) ?></textarea></label>
                <div class="grid-fields">
                  <label class="field"><span>Yönetici bildirim e-postası</span><input name="admin_email" type="email" maxlength="254" value="<?= escape_html((string) $settings['admin_email']) ?>"></label>
                  <label class="field"><span>Gönderici e-postası</span><input name="mail_from" type="email" maxlength="254" value="<?= escape_html((string) $settings['mail_from']) ?>"></label>
                </div>
                <?php if (trim((string) $settings['mail_from']) === ''): ?>
                  <p class="notice error">Gönderici e-postası (mail_from) boş. E-postalar alıcının adresinden gönderilir ve spam'e düşebilir; doldurmanız önerilir.</p>
                <?php endif; ?>
                <div class="grid-fields">
                  <label class="field"><span>Site adresi (https://…)</span><input name="public_base_url" type="url" maxlength="200" placeholder="https://alanadiniz.com" value="<?= escape_html((string) $settings['public_base_url']) ?>"></label>
                  <label class="field"><span>Güvenilir proxy IP'leri</span><input name="trusted_proxies" maxlength="300" placeholder="CDN/proxy arkasındaysa IP listesi" value="<?= escape_html((string) $settings['trusted_proxies']) ?>"></label>
                </div>
                <div class="grid-fields">
                  <label class="field"><span>Satıcı unvanı</span><input name="company_name" maxlength="160" value="<?= escape_html((string) $settings['company_name']) ?>"></label>
                  <label class="field"><span>Vergi / TC no</span><input name="company_tax_no" maxlength="40" value="<?= escape_html((string) $settings['company_tax_no']) ?>"></label>
                </div>
                <label class="field"><span>Şirket adresi</span><input name="company_address" maxlength="300" value="<?= escape_html((string) $settings['company_address']) ?>"></label>
                <div class="grid-fields">
                  <label class="field"><span>Herkese açık e-posta</span><input name="public_email" type="email" maxlength="254" value="<?= escape_html((string) $settings['public_email']) ?>"></label>
                  <label class="field"><span>Herkese açık telefon</span><input name="public_phone" type="tel" maxlength="20" value="<?= escape_html((string) $settings['public_phone']) ?>"></label>
                </div>
                <div class="grid-fields">
                  <label class="field"><span>Lisans başına kart sınırı</span><input name="max_cards_per_license" type="number" min="1" max="20" value="<?= (int) $settings['max_cards_per_license'] ?>"></label>
                  <label class="field check spaced"><input type="checkbox" name="force_https" value="1" <?= !empty($settings['force_https']) ? 'checked' : '' ?>><span>HTTP isteklerini HTTPS'e yönlendir</span></label>
                </div>

                <h3 class="subhead">Shopier API / webhook</h3>
                <label class="field"><span>Shopier API anahtarı</span><input name="shopier_api_key" maxlength="200" value="<?= escape_html((string) $settings['shopier_api_key']) ?>" autocomplete="off"></label>
                <label class="field"><span>Shopier API secret (boş = değişmez)</span><input name="shopier_api_secret" type="password" maxlength="200" autocomplete="new-password" placeholder="<?= trim((string) $settings['shopier_api_secret']) !== '' ? 'Kayıtlı — değiştirmek için yazın' : 'Secret girin' ?>"></label>
                <label class="field"><span>Shopier webhook token</span><input name="shopier_webhook_token" maxlength="200" value="<?= escape_html((string) $settings['shopier_webhook_token']) ?>" autocomplete="off"></label>

                <h3 class="subhead">E-posta (SMTP)</h3>
                <div class="grid-fields">
                  <label class="field"><span>SMTP sunucu</span><input name="smtp_host" maxlength="200" value="<?= escape_html((string) $settings['smtp_host']) ?>" placeholder="smtp.ornek.com" autocomplete="off"></label>
                  <label class="field"><span>Port</span><input name="smtp_port" type="number" min="1" max="65535" value="<?= (int) $settings['smtp_port'] ?>"></label>
                </div>
                <div class="grid-fields">
                  <label class="field"><span>SMTP kullanıcı</span><input name="smtp_user" maxlength="200" value="<?= escape_html((string) $settings['smtp_user']) ?>" autocomplete="off"></label>
                  <label class="field"><span>SMTP şifre (boş = değişmez)</span><input name="smtp_pass" type="password" maxlength="200" autocomplete="new-password" placeholder="<?= trim((string) $settings['smtp_pass']) !== '' ? 'Kayıtlı — değiştirmek için yazın' : 'Şifre girin' ?>"></label>
                </div>
                <label class="field"><span>Şifreleme</span>
                  <select name="smtp_encryption">
                    <?php foreach (['tls' => 'TLS (587)', 'ssl' => 'SSL (465)', 'none' => 'Yok'] as $encValue => $encLabel): ?>
                      <option value="<?= escape_html($encValue) ?>" <?= ($settings['smtp_encryption'] ?? 'tls') === $encValue ? 'selected' : '' ?>><?= escape_html($encLabel) ?></option>
                    <?php endforeach; ?>
                  </select>
                </label>

                <h3 class="subhead">SMS (Netgsm)</h3>
                <div class="grid-fields">
                  <label class="field"><span>Sağlayıcı</span><input name="sms_provider" maxlength="50" value="<?= escape_html((string) $settings['sms_provider']) ?>" placeholder="netgsm"></label>
                  <label class="field"><span>Gönderici başlığı</span><input name="sms_header" maxlength="50" value="<?= escape_html((string) $settings['sms_header']) ?>"></label>
                </div>
                <div class="grid-fields">
                  <label class="field"><span>Netgsm kullanıcı kodu</span><input name="sms_usercode" maxlength="100" value="<?= escape_html((string) $settings['sms_usercode']) ?>" autocomplete="off"></label>
                  <label class="field"><span>Netgsm şifre (boş = değişmez)</span><input name="sms_password" type="password" maxlength="200" autocomplete="new-password" placeholder="<?= trim((string) $settings['sms_password']) !== '' ? 'Kayıtlı — değiştirmek için yazın' : 'Şifre girin' ?>"></label>
                </div>
                <p class="muted">E-posta teslimi için SMTP veya sunucu mail() yapılandırması gerekir. Ayarlar ve IBAN web kökü dışındaki özel depoda tutulur.</p>
                <button class="button full-button" type="submit">Ayarları kaydet</button>
              </form>
            </section>

            <section class="panel">
              <div class="panel-heading"><h2>Bağlantı testleri</h2></div>
              <p class="muted">Satışa çıkmadan önce SMTP ve SMS ayarlarını gerçek alıcıyla deneyin.</p>
              <form method="post" action="yonetim.php">
                <input type="hidden" name="csrf_token" value="<?= escape_html(csrf_token()) ?>">
                <input type="hidden" name="action" value="test_email">
                <label class="field"><span>Test e-postası (boş = yönetici adresi)</span><input name="test_email" type="email" maxlength="254"></label>
                <button class="button full-button" type="submit">E-posta testi gönder</button>
              </form>
              <form method="post" action="yonetim.php">
                <input type="hidden" name="csrf_token" value="<?= escape_html(csrf_token()) ?>">
                <input type="hidden" name="action" value="test_sms">
                <label class="field spaced"><span>Test telefonu</span><input name="test_phone" type="tel" maxlength="20" required></label>
                <button class="button secondary full-button" type="submit">SMS testi gönder</button>
              </form>
            </section>

            <section class="panel">
              <div class="panel-heading"><h2>Shopier kodunu kurtar</h2></div>
              <p class="muted">Sipariş referansıyla mevcut kodu tekrar gösterin. Eski kayıtlarda şifreli kopya yoksa yeni kod üretilir ve eskisi iptal edilir.</p>
              <form method="post" action="yonetim.php">
                <input type="hidden" name="csrf_token" value="<?= escape_html(csrf_token()) ?>">
                <label class="field"><span>Shopier sipariş referansı</span><input name="order_ref" maxlength="120" required></label>
                <label class="field"><span>E-posta (e-posta ile teslim için)</span><input name="email" type="email" maxlength="254"></label>
                <button class="button full-button" name="action" value="recover_license_email" type="submit">Kodu e-posta ile tekrar ver</button>
                <label class="field spaced"><span>Telefon (WhatsApp için)</span><input name="phone" type="tel" inputmode="tel" maxlength="20"></label>
                <button class="button secondary full-button" name="action" value="recover_license_sms" type="submit">WhatsApp ile kodu gönder</button>
              </form>
            </section>

            <section class="panel">
              <div class="panel-heading"><h2>Yönetici parolasını değiştir</h2></div>
              <form method="post" action="yonetim.php">
                <input type="hidden" name="csrf_token" value="<?= escape_html(csrf_token()) ?>"><input type="hidden" name="action" value="change_password">
                <label class="field"><span>Mevcut parola</span><input type="password" name="current_password" autocomplete="current-password" required></label>
                <label class="field"><span>Yeni parola (en az 14 karakter)</span><input type="password" name="new_password" autocomplete="new-password" minlength="14" required></label>
                <label class="field"><span>Yeni parola tekrar</span><input type="password" name="confirm_password" autocomplete="new-password" minlength="14" required></label>
                <button class="button secondary full-button" type="submit">Parolayı değiştir</button>
              </form>
            </section>
          </div>

          <section class="panel">
            <div class="panel-heading"><h2>Havale bildirimleri</h2><span class="count" aria-label="Bekleyen bildirim sayısı"><?= $pendingCount ?></span></div>
            <?php if (!$notifications): ?>
              <p class="empty">Henüz ödeme bildirimi yok.</p>
            <?php else: ?>
              <div class="payment-list">
                <?php foreach ($notifications as $notification): ?>
                  <article class="payment-item <?= $notification['status'] === 'pending' ? 'pending' : '' ?>">
                    <h3>#<?= (int) $notification['id'] ?> · <?= escape_html($notification['customer_name']) ?></h3>
                    <p>Bildirim: <?= escape_html($notification['created_at']) ?> · <?= escape_html(money_tl((int) $notification['amount_kurus'])) ?></p>
                    <p>Referans: <?= escape_html($notification['transfer_reference']) ?></p>
                    <p><?= escape_html($notification['email'] ?: 'E-posta yok') ?> · <?= escape_html($notification['phone'] ?: 'Telefon yok') ?></p>
                    <p>Durum: <?= $notification['status'] === 'pending' ? 'Onay bekliyor' : ($notification['status'] === 'approved' ? 'Onaylandı' : 'Reddedildi') ?></p>
                    <?php if ($notification['status'] === 'pending'): ?>
                      <div class="payment-actions">
                        <?php if (!empty($notification['email'])): ?>
                          <form method="post"><input type="hidden" name="csrf_token" value="<?= escape_html(csrf_token()) ?>"><input type="hidden" name="payment_id" value="<?= (int) $notification['id'] ?>"><button class="button" name="action" value="payment_email" type="submit">Onayla + e-posta kodu</button></form>
                        <?php endif; ?>
                        <?php if (!empty($notification['phone'])): ?>
                          <form method="post"><input type="hidden" name="csrf_token" value="<?= escape_html(csrf_token()) ?>"><input type="hidden" name="payment_id" value="<?= (int) $notification['id'] ?>"><button class="button secondary" name="action" value="payment_sms" type="submit">Onayla + WhatsApp kodu</button></form>
                        <?php endif; ?>
                        <form method="post"><input type="hidden" name="csrf_token" value="<?= escape_html(csrf_token()) ?>"><input type="hidden" name="payment_id" value="<?= (int) $notification['id'] ?>"><button class="button reject" name="action" value="payment_reject" type="submit">Reddet</button></form>
                      </div>
                    <?php elseif ($notification['status'] === 'approved' && (!empty($notification['email']) || !empty($notification['phone']))): ?>
                      <div class="payment-actions">
                        <?php if (!empty($notification['email'])): ?>
                          <form method="post"><input type="hidden" name="csrf_token" value="<?= escape_html(csrf_token()) ?>"><input type="hidden" name="payment_id" value="<?= (int) $notification['id'] ?>"><button class="button secondary" name="action" value="payment_recover_email" type="submit">Kodu e-posta ile tekrar ver</button></form>
                        <?php endif; ?>
                        <?php if (!empty($notification['phone'])): ?>
                          <form method="post"><input type="hidden" name="csrf_token" value="<?= escape_html(csrf_token()) ?>"><input type="hidden" name="payment_id" value="<?= (int) $notification['id'] ?>"><button class="button secondary" name="action" value="payment_recover_sms" type="submit">Kodu WhatsApp ile gönder</button></form>
                        <?php endif; ?>
                      </div>
                      <p class="muted">Tekrar gönderimde önceki cihaz oturumu sıfırlanır. Eski kayıtlarda saklı kod yoksa yenisi oluşturulur.</p>
                    <?php endif; ?>
                  </article>
                <?php endforeach; ?>
              </div>
            <?php endif; ?>
          </section>
        </div>
      </main>
    <?php endif; ?>
  </div>
</body>
</html>
