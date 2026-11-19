<?php
declare(strict_types=1);
require_once __DIR__ . '/auth.php';

$settings = app_settings();
$priceKurus = (int) $settings['price_kurus'];
$shopierProductUrl = (string) $settings['shopier_url'];
$shopierHost = parse_url($shopierProductUrl, PHP_URL_HOST) ?: '';
$shopierUrlIsValid = in_array(strtolower($shopierHost), ['shopier.com', 'www.shopier.com'], true);
$priceDecimals = $priceKurus % 100 === 0 ? 0 : 2;
$price = number_format($priceKurus / 100, $priceDecimals, ',', '.');
$activationMessages = [
    'invalid' => 'Kod geçersiz. Karakterleri kontrol edip yeniden deneyin.',
    'required' => 'Kart tasarımını oluşturmak için satın alma sonrası verilen kod gerekir.'
];
$activationMessage = $activationMessages[$_GET['activation'] ?? ''] ?? '';
if ($activationMessage === '' && ($_GET['access'] ?? '') === 'required') {
    $activationMessage = $activationMessages['required'];
}
?>
<!doctype html>
<html lang="tr">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="theme-color" content="#f2f3ed">
  <title>QR Araç Kartı | Satın Al</title>
  <link rel="stylesheet" href="assets/app.css">
  <style nonce="<?= app_csp_nonce() ?>">
    :root {
      color-scheme: light;
      --paper: #f2f3ed;
      --ink: #17241f;
      --muted: #68746e;
      --line: #dce1d8;
      --green: #245c48;
      --lime: #d5ef73;
      font-family: "DM Sans", sans-serif;
      font-synthesis: none;
      text-rendering: optimizeLegibility;
    }

    * { box-sizing: border-box; }
    .visually-hidden { position: absolute; width: 1px; height: 1px; padding: 0; margin: -1px; overflow: hidden; clip: rect(0, 0, 0, 0); white-space: nowrap; border: 0; }
    body {
      min-width: 320px;
      min-height: 100vh;
      margin: 0;
      color: var(--ink);
      background: var(--paper) radial-gradient(#cbd4c8 0.7px, transparent 0.7px);
      background-size: auto, 19px 19px;
    }

    .page { width: min(1120px, calc(100% - 48px)); min-height: 100vh; margin: auto; padding: 26px 0; display: grid; grid-template-rows: auto 1fr auto; }
    .topbar, .brand, .footer, .card-brand { display: flex; align-items: center; }
    .topbar, .footer { justify-content: space-between; }
    .brand { gap: 10px; font-size: 13px; font-weight: 700; }
    .brand-mark, .mini-mark { display: grid; place-items: center; color: var(--lime); background: var(--green); font: 800 17px/1 "Manrope", sans-serif; }
    .brand-mark { width: 30px; height: 30px; border-radius: 9px; }
    .nav-link { color: var(--green); font-size: 12px; font-weight: 700; text-decoration: none; }
    .main { width: 100%; max-width: 1020px; margin: auto; padding: 52px 0; }
    .product-grid { display: grid; grid-template-columns: minmax(0, 1fr) minmax(340px, 0.86fr); align-items: center; gap: clamp(42px, 8vw, 100px); }
    .product-stage { padding: clamp(20px, 4vw, 42px); background: #e4e9df; }
    .product-card { width: 100%; max-width: 340px; aspect-ratio: 55 / 90; margin: 0 auto; overflow: hidden; border: 1px solid #d8ded5; border-radius: 14px; background: #fff; box-shadow: 0 22px 40px #1c32221d; transform: rotate(-2deg); display: flex; flex-direction: column; }
    .card-top { display: flex; align-items: center; justify-content: center; gap: 10px; padding: 14px 16px; background: #0c1a31; color: #fff; }
    .mini-mark { width: 26px; height: 26px; flex: 0 0 26px; display: grid; place-items: center; border-radius: 8px; color: #0c1a31; background: #d8f1fa; font: 800 14px "Manrope", sans-serif; }
    .card-top b { font: 800 9px "Manrope", sans-serif; letter-spacing: 0.2em; }
    .card-tag { padding: 4px 8px; border: 1px solid #ffffff55; border-radius: 999px; font: 700 8px "DM Sans", sans-serif; letter-spacing: 0.14em; text-transform: uppercase; opacity: 0.9; }
    .card-mid { flex: 1; min-height: 0; display: grid; place-items: center; padding: 18px 20px; }
    .qr-preview { width: min(190px, 74%); aspect-ratio: 1; padding: 6%; display: grid; place-items: center; border: 1px solid #e6ebe2; border-radius: 8px; background: #fff; }
    .qr-pattern { width: 100%; height: 100%; background-color: #fff; background-image: repeating-conic-gradient(#17241f 0% 25%, #fff 0% 50%); background-size: 11px 11px; position: relative; }
    .qr-pattern::before, .qr-pattern::after { position: absolute; width: 29%; aspect-ratio: 1; border: 5px solid #17241f; background: #fff; content: ""; }
    .qr-pattern::before { top: 3%; left: 3%; }
    .qr-pattern::after { right: 3%; bottom: 3%; }
    .card-bottom { padding: 4px 22px 20px; text-align: center; }
    .card-bottom .bt { margin: 0; font: 800 10px "DM Sans", sans-serif; letter-spacing: 0.08em; text-transform: uppercase; color: #14324b; }
    .card-bottom .bn { margin: 7px 0 0; overflow: hidden; font: 800 19px "Manrope", sans-serif; color: #17241f; text-overflow: ellipsis; white-space: nowrap; }
    .card-bottom .bh { margin: 6px 0 0; font: 700 10px "DM Sans", sans-serif; color: #0c1a31; }
    .stage-caption { margin: 16px 0 0; color: #536159; font-size: 11px; line-height: 1.5; }
    .eyebrow { margin: 0 0 15px; color: var(--green); font-size: 11px; font-weight: 700; letter-spacing: 0.08em; text-transform: uppercase; }
    h1 { margin: 0 0 16px; font: 800 clamp(34px, 4vw, 52px)/1.05 "Manrope", sans-serif; letter-spacing: 0; }
    .description { max-width: 440px; margin: 0 0 25px; color: var(--muted); font-size: 15px; line-height: 1.65; }
    .price-row { display: flex; align-items: end; gap: 12px; margin-bottom: 19px; }
    .price { font: 800 38px/1 "Manrope", sans-serif; letter-spacing: 0; }
    .price-note { padding-bottom: 3px; color: var(--muted); font-size: 12px; }
    .buy-button, .disabled-button { width: 100%; min-height: 50px; display: flex; align-items: center; justify-content: center; gap: 9px; border: 1px solid var(--green); border-radius: 3px; color: #fff; background: var(--green); font: 700 14px "DM Sans", sans-serif; text-decoration: none; transition: background 160ms ease, transform 160ms ease; }
    .buy-button:hover { transform: translateY(-2px); background: #194533; }
    .transfer-link { width: 100%; min-height: 46px; margin-top: 9px; display: flex; align-items: center; justify-content: center; border: 1px solid #b9c9bd; border-radius: 3px; color: var(--green); background: #fff; font-size: 13px; font-weight: 700; text-decoration: none; }
    .transfer-link:hover { background: #f0f5ed; }
    .disabled-button { border-color: #d8ded7; color: #78827b; background: #edf0ea; cursor: not-allowed; }
    .checkout-note { min-height: 34px; margin: 10px 0 18px; color: var(--muted); font-size: 11px; line-height: 1.5; }
    .included { margin: 0; padding: 16px 0 0; border-top: 1px solid var(--line); list-style: none; }
    .included li { display: flex; gap: 10px; margin: 0 0 9px; color: #405048; font-size: 12px; line-height: 1.45; }
    .included li::before { color: var(--green); content: "✓"; font-weight: 800; }
    .activation { width: min(660px, 100%); margin: 54px auto 0; padding-top: 22px; border-top: 1px solid var(--line); }
    .activation h2 { margin: 0 0 7px; font: 700 18px "Manrope", sans-serif; letter-spacing: 0; }
    .activation p { margin: 0 0 14px; color: var(--muted); font-size: 12px; line-height: 1.5; }
    .activation-form { display: flex; gap: 9px; }
    .activation-form input { min-width: 0; min-height: 46px; flex: 1; padding: 0 12px; border: 1px solid #cdd5cb; border-radius: 3px; color: var(--ink); background: #fff; font: 14px "DM Sans", sans-serif; }
    .activation-form input:focus { outline: 2px solid #245c4833; border-color: var(--green); }
    .activate-button { min-height: 46px; padding: 0 18px; border: 1px solid var(--ink); border-radius: 3px; color: #fff; background: var(--ink); font: 700 12px "DM Sans", sans-serif; cursor: pointer; }
    .activation-message { margin: 10px 0 0 !important; color: #a1382d !important; }
    .footer { padding-top: 14px; border-top: 1px solid #d8ded5; color: var(--muted); font-size: 11px; }

    @media (max-width: 760px) {
      .page { width: min(100% - 36px, 520px); padding: 18px 0 14px; }
      .main { padding: 38px 0 28px; }
      .product-grid { grid-template-columns: 1fr; gap: 31px; }
      .product-stage { padding: 24px; }
      .product-card { transform: rotate(-1deg); }
      h1 { font-size: 37px; }
      .activation { margin-top: 38px; }
      .footer { font-size: 10px; }
    }

    @media (max-width: 390px) {
      .page { width: calc(100% - 28px); }
      .product-stage { padding: 15px; }
      .activation-form { flex-direction: column; }
      .activate-button { width: 100%; }
    }

    @media (prefers-reduced-motion: reduce) { *, *::before, *::after { transition-duration: 0.01ms !important; } }
  </style>
</head>
<body>
  <div class="page">
    <header class="topbar">
      <div class="brand"><span class="brand-mark" aria-hidden="true">A</span><span>ARAÇ İLETİŞİM</span></div>
      <a class="nav-link" href="index.php">Ana sayfa</a>
    </header>

    <main class="main">
      <div class="product-grid">
        <section class="product-stage" aria-label="QR araç kartı ürün önizlemesi">
          <article class="product-card">
            <div class="card-top"><span class="mini-mark" aria-hidden="true">A</span><b>ARAÇ İLETİŞİM</b><span class="card-tag">Acil İletişim</span></div>
            <div class="card-mid"><div class="qr-preview" aria-hidden="true"><div class="qr-pattern"></div></div></div>
            <div class="card-bottom">
              <p class="bt">Araç sahibine ulaşın</p>
              <p class="bn">Kişiye özel tasarım</p>
              <p class="bh">QR kodu okutun</p>
            </div>
          </article>
          <p class="stage-caption">55 × 90 mm dikey kart · UV baskıya uygun QR yüzeyi</p>
        </section>

        <section aria-labelledby="product-title">
          <p class="eyebrow">Kişiye özel QR araç kartı</p>
          <h1 id="product-title">Aracınız için kolay iletişim.</h1>
          <p class="description">QR kodunu okutan kişi; telefon, plaka ve WhatsApp bilgilerinizin olduğu kişisel sayfaya ulaşır. Kişisel iletişim sayfanızı oluşturun, fiziksel kart siparişinizi Shopier üzerinden takip edin.</p>
          <div class="price-row"><span class="price"><?= escape_html($price) ?> TL</span><span class="price-note">tek seferlik ödeme</span></div>

          <?php if ($shopierUrlIsValid): ?>
            <a class="buy-button" href="<?= escape_html($shopierProductUrl) ?>" target="_blank" rel="noopener">Shopier'de satın al</a>
            <p class="checkout-note">Ödeme Shopier'in güvenli ödeme sayfasında tamamlanır. Siparişiniz doğrulanınca aktivasyon kodunuz iletilir.</p>
          <?php else: ?>
            <span class="disabled-button" aria-disabled="true">Shopier satış bağlantısı hazırlanıyor</span>
            <p class="checkout-note">Satışa açmak için Shopier ürün bağlantısı yapılandırılmalıdır.</p>
          <?php endif; ?>
          <a class="transfer-link" href="odeme-bildir.php">Havale / EFT ile öde</a>

          <ul class="included">
            <li>Kişiye özel QR iletişim sayfası ve indirilebilir baskı dosyası</li>
            <li>55 × 90 mm dikey, UV baskıya uygun QR kart tasarımı</li>
            <li>Fiziksel kart sipariş ve teslimatı Shopier üzerinden takip edilir</li>
          </ul>
        </section>
      </div>

      <section class="activation" aria-labelledby="activation-title">
        <h2 id="activation-title">Satın aldınız mı?</h2>
        <p>Size iletilen 24 karakterli kodu girin. Aynı kodu başka bir tarayıcıda tekrar kullanarak stüdyoya dönebilirsiniz.</p>
        <form class="activation-form" action="activate.php" method="post">
          <input type="hidden" name="csrf_token" value="<?= escape_html(csrf_token()) ?>">
          <label for="activation-code" class="visually-hidden">Aktivasyon kodu</label>
          <input id="activation-code" name="code" type="text" inputmode="text" autocomplete="one-time-code" minlength="24" maxlength="24" pattern="[A-Fa-f0-9]{24}" placeholder="24 karakterli kod" required>
          <button class="activate-button" type="submit">Kart oluşturucuya geç</button>
        </form>
        <?php if ($activationMessage !== ''): ?>
          <p class="activation-message" role="alert"><?= escape_html($activationMessage) ?></p>
        <?php endif; ?>
      </section>
    </main>

    <footer class="footer"><span>ARAÇ İLETİŞİM · <?= escape_html($price) ?> TL</span><span><?= legal_links_html() ?></span></footer>
  </div>
</body>
</html>