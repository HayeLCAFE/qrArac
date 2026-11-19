<?php
declare(strict_types=1);
require_once __DIR__ . '/auth.php';

$settings = app_settings();
$priceKurus = (int) $settings['price_kurus'];
$bankConfigured = !empty($settings['bank_name'])
    && !empty($settings['account_holder'])
    && validate_turkish_iban((string) $settings['iban']);
$errorMessage = '';
$flash = $_SESSION['payment_flash'] ?? null;
unset($_SESSION['payment_flash']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_is_valid()) {
        http_response_code(400);
        $errorMessage = 'Form doğrulaması başarısız oldu. Sayfayı yenileyip tekrar deneyin.';
    } elseif (!$bankConfigured) {
        http_response_code(503);
        $errorMessage = 'Havale bilgileri henüz hazır değil. Lütfen daha sonra tekrar deneyin.';
    } else {
        $name = trim((string) ($_POST['customer_name'] ?? ''));
        $email = trim((string) ($_POST['email'] ?? ''));
        $phone = trim((string) ($_POST['phone'] ?? ''));
        $phoneDigits = preg_replace('/\D/', '', $phone);
        $reference = trim((string) ($_POST['transfer_reference'] ?? ''));
        $amountText = str_replace(',', '.', trim((string) ($_POST['amount_tl'] ?? '')));
        $amountValid = preg_match('/^\d{1,8}(?:\.\d{1,2})?$/', $amountText) === 1;
        $amountKurus = $amountValid ? (int) round((float) $amountText * 100) : 0;

        if (mb_strlen($name) < 2 || mb_strlen($name) > 120) {
            $errorMessage = 'Ad soyad 2 ile 120 karakter arasında olmalı.';
        } elseif (($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) && (strlen($phoneDigits) < 10 || strlen($phoneDigits) > 15)) {
            $errorMessage = 'Size kodu iletebilmemiz için geçerli bir e-posta veya telefon numarası girin.';
        } elseif ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errorMessage = 'E-posta adresini kontrol edin veya e-posta alanını boş bırakın.';
        } elseif ($phone !== '' && (strlen($phoneDigits) < 10 || strlen($phoneDigits) > 15)) {
            $errorMessage = 'Telefon numarasını kontrol edin veya telefon alanını boş bırakın.';
        } elseif (!$amountValid || $amountKurus < 100 || $amountKurus > 100000000) {
            $errorMessage = 'Havale tutarını TL olarak doğru girin.';
        } elseif (abs($amountKurus - $priceKurus) > 100) {
            $errorMessage = 'Gönderilen tutar ürün fiyatıyla eşleşmiyor. Paneldeki fiyatı kullanın: ' . money_tl($priceKurus);
        } elseif (mb_strlen($reference) < 3 || mb_strlen($reference) > 120) {
            $errorMessage = 'Havale açıklaması veya banka işlem referansı en az 3 karakter olmalı.';
        } else {
            $database = app_db();
            $duplicate = $database->prepare(
                'SELECT id FROM payment_notifications WHERE transfer_reference = :ref AND status != "rejected"'
            );
            $duplicate->execute(['ref' => $reference]);
            if ($duplicate->fetch()) {
                $errorMessage = 'Bu banka referansı daha önce bildirilmiş. Yeni bir işlem numarası kullanın veya bekleyin.';
            } else {
                $ipHash = request_ip_hash();
                $rateLimit = $database->prepare(
                    "SELECT COUNT(*) FROM payment_notifications WHERE ip_hash = :ip AND created_at >= datetime('now', '-1 hour')"
                );
                $rateLimit->execute(['ip' => $ipHash]);

                if ((int) $rateLimit->fetchColumn() >= 5) {
                    http_response_code(429);
                    $errorMessage = 'Çok sayıda bildirim gönderildi. Bir saat sonra tekrar deneyin.';
                } else {
                    try {
                        $insert = $database->prepare(
                            'INSERT INTO payment_notifications (customer_name, email, phone, amount_kurus, transfer_reference, ip_hash) VALUES (:name, :email, :phone, :amount, :reference, :ip)'
                        );
                        $insert->execute([
                            'name' => $name,
                            'email' => $email !== '' ? $email : null,
                            'phone' => $phone !== '' ? $phone : null,
                            'amount' => $amountKurus,
                            'reference' => $reference,
                            'ip' => $ipHash
                        ]);
                        $notificationId = (int) $database->lastInsertId();

                        $adminMessage = "Yeni havale bildirimi #{$notificationId}\nAd: {$name}\nTutar: " . number_format($amountKurus / 100, 2, ',', '.') . " TL\nReferans: {$reference}\nE-posta: {$email}\nTelefon: {$phone}\nYönetim paneli: yonetim.php";
                        $adminNotified = app_send_email((string) $settings['admin_email'], 'Yeni araç kartı havale bildirimi', $adminMessage);
                        if ($email !== '') {
                            app_send_email(
                                $email,
                                'Havale bildiriminiz alındı',
                                "Havale bildiriminiz (#{$notificationId}) alındı. Ödeme banka hesabında doğrulandıktan sonra aktivasyon kodunuz iletilecek. Bu e-posta ödeme onayı değildir."
                            );
                        }

                        $_SESSION['payment_flash'] = [
                            'id' => $notificationId,
                            'admin_notified' => $adminNotified
                        ];
                        header('Location: odeme-bildir.php?gonderildi=1', true, 303);
                        exit;
                    } catch (PDOException $exception) {
                        $isDuplicate = str_contains($exception->getMessage(), 'UNIQUE');
                        if (!$isDuplicate) {
                            error_log($exception->getMessage());
                        }
                        $errorMessage = $isDuplicate
                            ? 'Bu banka referansı daha önce bildirilmiş. Yeni bir işlem numarası kullanın veya bekleyin.'
                            : 'Bildirim kaydedilemedi. Lütfen daha sonra tekrar deneyin.';
                    }
                }
            }
        }
    }
}

$sent = ($_GET['gonderildi'] ?? '') === '1' && is_array($flash);
$priceText = number_format($priceKurus / 100, 2, ',', '.');
$formattedIban = preg_replace('/(.{4})/', '$1 ', (string) $settings['iban']);
?>
<!doctype html>
<html lang="tr">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="theme-color" content="#f2f3ed">
  <title>Havale / EFT | Araç İletişim</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&family=Manrope:wght@500;600;700;800&display=swap" rel="stylesheet">
  <style nonce="<?= app_csp_nonce() ?>">
    :root { color-scheme: light; --paper: #f2f3ed; --ink: #17241f; --muted: #68746e; --line: #dce1d8; --green: #245c48; --lime: #d5ef73; font-family: "DM Sans", sans-serif; font-synthesis: none; text-rendering: optimizeLegibility; }
    * { box-sizing: border-box; }
    body { min-width: 320px; min-height: 100vh; margin: 0; color: var(--ink); background: var(--paper) radial-gradient(#cbd4c8 0.7px, transparent 0.7px); background-size: auto, 19px 19px; }
    .page { width: min(940px, calc(100% - 40px)); min-height: 100vh; margin: auto; padding: 22px 0; display: grid; grid-template-rows: auto 1fr auto; }
    .topbar, .brand, .footer, .section-heading, .iban-top { display: flex; align-items: center; }
    .topbar, .footer, .section-heading, .iban-top { justify-content: space-between; gap: 12px; }
    .brand { gap: 10px; font-size: 13px; font-weight: 700; }
    .brand-mark { width: 30px; height: 30px; display: grid; place-items: center; border-radius: 9px; color: var(--lime); background: var(--green); font: 800 17px "Manrope", sans-serif; }
    .back { color: var(--green); font-size: 12px; font-weight: 700; text-decoration: none; }
    main { width: 100%; max-width: 780px; margin: auto; padding: 44px 0; }
    .eyebrow { margin: 0 0 11px; color: var(--green); font-size: 11px; font-weight: 700; letter-spacing: 0.08em; text-transform: uppercase; }
    h1 { margin: 0 0 12px; font: 800 38px/1.08 "Manrope", sans-serif; letter-spacing: 0; }
    .intro { max-width: 590px; margin: 0 0 24px; color: var(--muted); font-size: 14px; line-height: 1.6; }
    .columns { display: grid; grid-template-columns: minmax(0, 0.95fr) minmax(0, 1.05fr); gap: 18px; align-items: start; }
    .panel { padding: 21px; border: 1px solid #dfe4dc; border-radius: 5px; background: #ffffffdf; box-shadow: 0 14px 34px #1c322210; }
    .panel h2 { margin: 0; font: 700 17px "Manrope", sans-serif; letter-spacing: 0; }
    .section-heading { margin-bottom: 15px; }
    .amount { color: var(--green); font: 800 21px "Manrope", sans-serif; }
    .iban-box { padding: 14px; border: 1px solid #d8e1d6; border-radius: 4px; background: #f6f9f1; }
    .bank-meta { display: grid; gap: 10px; margin: 0 0 13px; }
    .bank-meta dt { margin-bottom: 3px; color: var(--muted); font-size: 10px; font-weight: 700; text-transform: uppercase; }
    .bank-meta dd { margin: 0; font-size: 13px; font-weight: 700; overflow-wrap: anywhere; }
    .iban-top { align-items: start; }
    .iban-copy { color: var(--green); font-size: 11px; font-weight: 700; cursor: pointer; }
    .payment-note { margin: 12px 0 0; color: #536159; font-size: 11px; line-height: 1.55; }
    .field { display: block; margin: 0 0 13px; }
    .field > span { display: block; margin-bottom: 6px; font-size: 11px; font-weight: 700; }
    .field input { width: 100%; min-height: 42px; padding: 10px 11px; border: 1px solid #cdd5cb; border-radius: 3px; color: var(--ink); background: #fff; font: 13px "DM Sans", sans-serif; }
    .field input:focus { outline: 2px solid #245c4828; border-color: var(--green); }
    .hint { margin: -5px 0 12px; color: var(--muted); font-size: 10px; line-height: 1.45; }
    .submit { width: 100%; min-height: 46px; border: 1px solid var(--green); border-radius: 3px; color: #fff; background: var(--green); font: 700 13px "DM Sans", sans-serif; cursor: pointer; }
    .submit:hover { background: #194533; }
    .notice { margin: 0 0 15px; padding: 12px; border-left: 3px solid #a2bd60; color: #405048; background: #f2f5eb; font-size: 12px; line-height: 1.55; }
    .notice.error { border-color: #b94a3b; color: #812c22; background: #fff1ed; }
    .footer { padding-top: 14px; border-top: 1px solid #d8ded5; color: var(--muted); font-size: 11px; }
    @media (max-width: 700px) { .page { width: min(100% - 32px, 480px); padding: 16px 0 12px; } main { padding: 34px 0 25px; } .columns { grid-template-columns: 1fr; } h1 { font-size: 33px; } .panel { padding: 18px; } }
  </style>
</head>
<body>
  <div class="page">
    <header class="topbar"><div class="brand"><span class="brand-mark" aria-hidden="true">A</span><span>ARAÇ İLETİŞİM</span></div><a class="back" href="satin.php">Satış sayfası</a></header>
    <main>
      <p class="eyebrow">Banka transferi</p>
      <h1>Havale bildirimi</h1>
      <p class="intro">Ödemeyi yaptıktan sonra banka işlem referansını girin. Ödeme hesabımızda doğrulanınca aktivasyon kodunuz seçtiğiniz iletişim kanalına gönderilir.</p>

      <?php if ($sent): ?>
        <p class="notice" role="status">Bildiriminiz alındı (#<?= (int) $flash['id'] ?>). Ödeme banka hesabında doğrulandıktan sonra kodunuz iletilecek. Bu bildirim tek başına ödeme onayı değildir.</p>
        <?php if (empty($flash['admin_notified'])): ?><p class="notice">Yönetici e-postası otomatik gönderilemedi; bildiriminiz yine de yönetim panelinde kayıtlı. E-posta sunucu ayarları yapılmalı.</p><?php endif; ?>
      <?php elseif ($errorMessage !== ''): ?>
        <p class="notice error" role="alert"><?= escape_html($errorMessage) ?></p>
      <?php endif; ?>

      <?php if (!$bankConfigured): ?>
        <p class="notice">Banka bilgileri henüz yönetici tarafından ayarlanmamış. Lütfen daha sonra tekrar deneyin.</p>
      <?php else: ?>
        <div class="columns">
          <section class="panel" aria-labelledby="bank-title">
            <div class="section-heading"><h2 id="bank-title">Havale bilgileri</h2><span class="amount"><?= escape_html($priceText) ?> TL</span></div>
            <div class="iban-box">
              <dl class="bank-meta">
                <div><dt>Banka</dt><dd><?= escape_html((string) $settings['bank_name']) ?></dd></div>
                <div><dt>Hesap sahibi</dt><dd><?= escape_html((string) $settings['account_holder']) ?></dd></div>
                <div><dt>IBAN</dt><dd><div class="iban-top"><span id="iban-value"><?= escape_html($formattedIban) ?></span><button class="iban-copy" id="copy-iban" type="button">Kopyala</button></div></dd></div>
              </dl>
              <p class="payment-note"><?= nl2br(escape_html((string) $settings['payment_note'])) ?></p>
            </div>
          </section>

          <section class="panel" aria-labelledby="form-title">
            <div class="section-heading"><h2 id="form-title">Ödemeyi bildirin</h2></div>
            <form method="post" action="odeme-bildir.php">
              <input type="hidden" name="csrf_token" value="<?= escape_html(csrf_token()) ?>">
              <label class="field"><span>Ad soyad</span><input name="customer_name" autocomplete="name" maxlength="120" required></label>
              <label class="field"><span>E-posta (veya telefon)</span><input name="email" type="email" autocomplete="email" maxlength="254" placeholder="kodun gönderileceği e-posta"></label>
              <label class="field"><span>Telefon (veya e-posta)</span><input name="phone" type="tel" inputmode="tel" autocomplete="tel" maxlength="20" placeholder="WhatsApp için telefon"></label>
              <p class="hint">Kodun iletilebilmesi için e-posta veya telefon alanlarından en az birini doldurun.</p>
              <label class="field"><span>Gönderilen tutar (TL)</span><input name="amount_tl" inputmode="decimal" value="<?= escape_html($priceText) ?>" required></label>
              <label class="field"><span>Banka işlem referansı / açıklama</span><input name="transfer_reference" maxlength="120" placeholder="Bankadaki işlem numarası" required></label>
              <button class="submit" type="submit">Ödeme bildirimi gönder</button>
            </form>
          </section>
        </div>
      <?php endif; ?>
    </main>
    <footer class="footer"><span>ARAÇ İLETİŞİM</span><a class="back" href="satin.php">Geri dön</a></footer>
  </div>
  <script nonce="<?= app_csp_nonce() ?>">
    document.querySelector('#copy-iban')?.addEventListener('click', async (event) => {
      const iban = document.querySelector('#iban-value').textContent.replace(/\s/g, '');
      try {
        await navigator.clipboard.writeText(iban);
        event.currentTarget.textContent = 'Kopyalandı';
      } catch {
        event.currentTarget.textContent = iban;
      }
    });
  </script>
</body>
</html>