<?php
declare(strict_types=1);
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/includes/layout.php';

$settings = app_settings();
$price = money_tl((int) $settings['price_kurus']);
$extra = '
:root{--ink-strong:#0f1f19}
.landing{width:100%;max-width:1080px;margin:auto}
.hero{display:grid;grid-template-columns:1.05fr .95fr;gap:clamp(28px,5vw,64px);align-items:center;padding:56px 0 44px}
.hero-badge{display:inline-flex;align-items:center;gap:8px;margin-bottom:18px;padding:7px 13px;border:1px solid #cfe0d2;border-radius:999px;background:#fff;color:var(--green);font-size:11.5px;font-weight:700;letter-spacing:.06em;text-transform:uppercase}
.hero-badge i{width:7px;height:7px;border-radius:50%;background:#57a05a;box-shadow:0 0 0 3px #57a05a22}
h1{margin:0 0 18px;font:800 clamp(38px,5.4vw,60px)/1.04 "Segoe UI",sans-serif;letter-spacing:-.03em;color:var(--ink-strong)}
h1 .hl{color:var(--green)}
.hero-sub{max-width:480px;margin:0 0 26px;color:var(--muted);font-size:16.5px;line-height:1.7}
.hero-cta{display:flex;gap:12px;flex-wrap:wrap;align-items:center}
.hero-meta{display:flex;gap:18px;flex-wrap:wrap;margin-top:22px;color:var(--muted);font-size:12px;font-weight:600}
.hero-meta span{display:flex;align-items:center;gap:6px}
.hero-meta span::before{width:6px;height:6px;border-radius:50%;background:var(--green);content:""}
.product-shot{position:relative;display:grid;place-items:center;padding:26px}
.shot-glow{position:absolute;inset:8% 4%;border-radius:24px;background:radial-gradient(closest-side,#d5ef73cc,transparent 72%);filter:blur(6px)}
.mock-card{position:relative;width:min(420px,100%);aspect-ratio:55/90;max-height:520px;display:flex;flex-direction:column;overflow:hidden;border-radius:14px;background:#fff;box-shadow:0 30px 70px #17241f30;border:1px solid #e2e7df;transform:rotate(-2deg)}
.mock-top{display:flex;align-items:center;justify-content:center;gap:10px;padding:14px 16px;background:#0c1a31;color:#fff}
.mock-top .mk{width:26px;height:26px;display:grid;place-items:center;border-radius:8px;background:#d8f1fa;color:#0c1a31;font:800 13px "Segoe UI",sans-serif}
.mock-top b{font:800 9px "Segoe UI",sans-serif;letter-spacing:.2em}
.mock-mid{flex:1;display:grid;place-items:center;padding:18px 20px}
.mock-qr{width:min(190px,72%);aspect-ratio:1;padding:8px;border:1px solid #e6ebe2;border-radius:8px;background:#fff}
.mock-qr svg{width:100%;height:100%;display:block}
.mock-bottom{padding:6px 20px 20px;text-align:center}
.mock-bottom .t{margin:0;font:800 10px "Segoe UI",sans-serif;letter-spacing:.08em;text-transform:uppercase;color:#14324b}
.mock-bottom .n{margin:6px 0 0;font:800 19px "Segoe UI",sans-serif;color:#17241f}
.mock-bottom .h{margin:6px 0 0;font:700 10px "Segoe UI",sans-serif;color:#0c1a31}
.steps{display:grid;grid-template-columns:repeat(3,1fr);gap:14px;padding:8px 0 40px}
.step{position:relative;padding:20px;border:1px solid var(--line);border-radius:12px;background:#fff}
.step .num{display:inline-grid;place-items:center;width:28px;height:28px;margin-bottom:12px;border-radius:9px;background:var(--green);color:#fff;font:800 13px "Segoe UI",sans-serif}
.step h3{margin:0 0 7px;font-size:15px}
.step p{margin:0;color:var(--muted);font-size:12.5px;line-height:1.6}
.sec-head{text-align:center;max-width:560px;margin:0 auto 30px}
.sec-head .eyebrow{margin-bottom:10px}
.sec-head h2{margin:0 0 10px;font:800 clamp(26px,3.4vw,36px)/1.15 "Segoe UI",sans-serif;letter-spacing:-.02em;color:var(--ink-strong)}
.sec-head p{margin:0;color:var(--muted);font-size:14.5px;line-height:1.65}
.features{display:grid;grid-template-columns:repeat(3,1fr);gap:14px;padding-bottom:44px}
.feature{padding:20px;border:1px solid var(--line);border-radius:12px;background:#fff;transition:box-shadow .18s ease,transform .18s ease}
.feature:hover{transform:translateY(-2px);box-shadow:0 14px 30px #17241f12}
.feature .fi{width:38px;height:38px;display:grid;place-items:center;margin-bottom:12px;border-radius:10px;background:var(--tpl-soft,#eef4f0);color:var(--green);font-size:17px}
.feature h3{margin:0 0 7px;font-size:14.5px}
.feature p{margin:0;color:var(--muted);font-size:12.5px;line-height:1.6}
.pricing{display:grid;grid-template-columns:1.1fr .9fr;gap:24px;align-items:stretch;padding-bottom:44px}
.price-card{display:flex;flex-direction:column;padding:30px;border-radius:16px;background:var(--ink-strong);color:#fff;box-shadow:0 24px 60px #17241f26}
.price-card .eyebrow{color:#9fd8a8}
.price-row{display:flex;align-items:baseline;gap:10px;margin:14px 0 6px}
.price{font:800 44px/1 "Segoe UI",sans-serif;letter-spacing:-.02em}
.price-note{color:#a9bcb2;font-size:13px}
.price-list{margin:18px 0 24px;padding:0;list-style:none;display:grid;gap:10px}
.price-list li{display:flex;gap:10px;align-items:flex-start;color:#d7e2da;font-size:13.5px;line-height:1.5}
.price-list li::before{content:"✓";flex:0 0 auto;width:18px;height:18px;display:grid;place-items:center;margin-top:1px;border-radius:50%;background:#57a05a;color:#fff;font-size:11px;font-weight:800}
.price-card .buy-button{margin-top:auto;justify-content:center}
.faq{padding-bottom:48px}
.faq details{border:1px solid var(--line);border-radius:12px;background:#fff;margin-bottom:10px;overflow:hidden}
.faq summary{display:flex;align-items:center;justify-content:space-between;gap:12px;padding:16px 18px;cursor:pointer;list-style:none;font:700 14px "Segoe UI",sans-serif}
.faq summary::-webkit-details-marker{display:none}
.faq summary::after{content:"+";flex:0 0 auto;width:24px;height:24px;display:grid;place-items:center;border-radius:8px;background:var(--tpl-soft,#eef4f0);color:var(--green);font-size:16px;font-weight:800;transition:transform .2s ease}
.faq details[open] summary::after{transform:rotate(45deg)}
.faq .a{padding:0 18px 16px;color:var(--muted);font-size:13px;line-height:1.7}
.final-cta{padding:38px 0 8px;text-align:center}
.final-cta h2{margin:0 0 10px;font:800 clamp(24px,3vw,32px)/1.2 "Segoe UI",sans-serif;color:var(--ink-strong)}
.final-cta p{margin:0 0 20px;color:var(--muted);font-size:14px}
@media(max-width:860px){.hero,.pricing{grid-template-columns:1fr}.product-shot{order:-1;padding:10px 0 4px}.mock-card{transform:none}.steps,.features{grid-template-columns:1fr}}
@media(max-width:520px){.hero{padding:36px 0 30px}.sec-head h2{font-size:25px}}
';
layout_head('Araç İletişim Kartı — QR ile aracınızı bulun', $extra);
echo '<div class="page">';
layout_top('<a class="builder-link" href="satin.php">Satın al</a>');
?>
<main class="landing">
  <section class="hero">
    <div>
      <span class="hero-badge"><i></i>QR araç kartı</span>
      <h1>Park sorunu mu? <span class="hl">Tek dokunuşla</span> sahibine ulaşın.</h1>
      <p class="hero-sub">Ön camınıza yapıştırılan kişisel kart: biri QR'ı okuttuğunda telefon, plaka ve WhatsApp bilgileriniz hazır bir sayfada açılır. Numaranız asla QR adresinde görünmez.</p>
      <div class="hero-cta">
        <a class="buy-button" href="satin.php">Hemen satın al · <?= escape_html($price) ?></a>
        <a class="transfer-link" href="#nasil-calisir">Nasıl çalışır?</a>
      </div>
      <div class="hero-meta">
        <span>Anında teslim</span>
        <span>Güvenli ödeme</span>
        <span>Filo desteği</span>
      </div>
    </div>
    <div class="product-shot" aria-hidden="true">
      <div class="shot-glow"></div>
      <div class="mock-card">
        <div class="mock-top"><span class="mk">A</span><b>ARAÇ İLETİŞİM</b></div>
        <div class="mock-mid">
          <div class="mock-qr">
            <svg viewBox="0 0 100 100" shape-rendering="crispEdges"><rect width="100" height="100" fill="#fff"/><g fill="#17241f"><rect x="8" y="8" width="22" height="22"/><rect x="70" y="8" width="22" height="22"/><rect x="8" y="70" width="22" height="22"/><g fill="#fff"><rect x="13" y="13" width="12" height="12"/><rect x="75" y="13" width="12" height="12"/><rect x="13" y="75" width="12" height="12"/></g><rect x="36" y="10" width="6" height="6"/><rect x="46" y="10" width="6" height="6"/><rect x="36" y="20" width="6" height="6"/><rect x="10" y="36" width="6" height="6"/><rect x="20" y="36" width="6" height="6"/><rect x="36" y="36" width="6" height="6"/><rect x="46" y="36" width="6" height="6"/><rect x="56" y="36" width="6" height="6"/><rect x="70" y="36" width="6" height="6"/><rect x="80" y="36" width="6" height="6"/><rect x="90" y="36" width="6" height="6"/><rect x="10" y="46" width="6" height="6"/><rect x="26" y="46" width="6" height="6"/><rect x="36" y="46" width="6" height="6"/><rect x="56" y="46" width="6" height="6"/><rect x="66" y="46" width="6" height="6"/><rect x="76" y="46" width="6" height="6"/><rect x="36" y="56" width="6" height="6"/><rect x="46" y="56" width="6" height="6"/><rect x="56" y="56" width="6" height="6"/><rect x="70" y="56" width="6" height="6"/><rect x="80" y="56" width="6" height="6"/><rect x="10" y="60" width="6" height="6"/><rect x="20" y="60" width="6" height="6"/><rect x="36" y="60" width="6" height="6"/><rect x="56" y="60" width="6" height="6"/><rect x="70" y="60" width="6" height="6"/><rect x="90" y="60" width="6" height="6"/><rect x="36" y="70" width="6" height="6"/><rect x="46" y="70" width="6" height="6"/><rect x="56" y="70" width="6" height="6"/><rect x="70" y="70" width="6" height="6"/><rect x="80" y="70" width="6" height="6"/><rect x="90" y="70" width="6" height="6"/><rect x="36" y="80" width="6" height="6"/><rect x="46" y="80" width="6" height="6"/><rect x="56" y="80" width="6" height="6"/><rect x="70" y="80" width="6" height="6"/><rect x="80" y="80" width="6" height="6"/><rect x="90" y="80" width="6" height="6"/><rect x="36" y="90" width="6" height="6"/><rect x="56" y="90" width="6" height="6"/><rect x="70" y="90" width="6" height="6"/><rect x="80" y="90" width="6" height="6"/></g></svg>
          </div>
        </div>
        <div class="mock-bottom">
          <p class="t">Araç sahibine ulaşın</p>
          <p class="n">Ahmet Yılmaz</p>
          <p class="h">▸ QR kodu okutun</p>
        </div>
      </div>
    </div>
  </section>

  <section class="steps" id="nasil-calisir" aria-label="Nasıl çalışır">
    <div class="step"><span class="num">1</span><h3>Satın al</h3><p>Shopier ile güvenli ödeme veya havale bildirimi. Onay sonrası aktivasyon kodunuz anında iletilir.</p></div>
    <div class="step"><span class="num">2</span><h3>Kartı hazırla</h3><p>Kodunuzu girip stüdyoda ad, telefon, plaka ve acil bilgilerinizi ekleyin. QR otomatik üretilir.</p></div>
    <div class="step"><span class="num">3</span><h3>Baskı ve yapıştır</h3><p>55 × 90 mm kartı yazdırın ve ön cama yerleştirin. İsterseniz laminasyonla ömrünü uzatın.</p></div>
  </section>

  <section aria-label="Özellikler">
    <div class="sec-head">
      <p class="eyebrow">Özellikler</p>
      <h2>Küçük bir kart, büyük bir fark</h2>
      <p>Yanlış park, kaza veya acil durumda sizi dakikalar içinde bulabilsinler.</p>
    </div>
    <div class="features">
      <div class="feature"><span class="fi">◎</span><h3>Gizlilik odaklı</h3><p>Telefon numaranız QR adresinde yer almaz; yalnızca kart sayfasında görünür.</p></div>
      <div class="feature"><span class="fi">✆</span><h3>Tek dokunuşla ara</h3><p>QR'ı okutan kişi tek tıkla sizi arar, mesaj atar veya WhatsApp'tan yazabilir.</p></div>
      <div class="feature"><span class="fi">▦</span><h3>Baskıya hazır QR</h3><p>Yüksek kontrastlı QR kod 55 × 90 mm baskı formatında, her telefonda okunur.</p></div>
      <div class="feature"><span class="fi">🌐</span><h3>Çoklu dil</h3><p>İletişim sayfanız Türkçe veya İngilizce açılır; yabancı plakalı araçlar için idealdir.</p></div>
      <div class="feature"><span class="fi">⛑</span><h3>Acil durum bilgileri</h3><p>Kan grubu, alerji ve kronik durumlar opsiyonel "Acil bilgiler" bölümünde gösterilir.</p></div>
      <div class="feature"><span class="fi">🚗</span><h3>Filo yönetimi</h3><p>Tek lisansla birden fazla araç kartı oluşturun; her kart kendi kısa bağlantısına sahip.</p></div>
    </div>
  </section>

  <section class="pricing" aria-label="Fiyatlandırma">
    <div class="price-card">
      <p class="eyebrow">Tek seferlik ödeme</p>
      <div class="price-row"><span class="price"><?= escape_html($price) ?></span><span class="price-note">KDV dahil</span></div>
      <ul class="price-list">
        <li>Sınırsız düzenleme ve yeniden baskı</li>
        <li>Kişiye özel iletişim sayfası (TR/EN)</li>
        <li>Baskı formatında yüksek kontrastlı QR</li>
        <li>Acil durum bilgi bölümü</li>
        <li>Filo: birden fazla araç kartı</li>
      </ul>
      <a class="buy-button" href="satin.php">Satın al · <?= escape_html($price) ?></a>
    </div>
    <div class="faq">
      <details>
        <summary>Birden fazla araç kartı olur mu?</summary>
        <p class="a">Evet. Tek lisansla birden fazla kart oluşturabilirsiniz; her kart kendi kısa bağlantısına, ad ve telefonuna sahiptir. Sayı sınırı yönetim ayarlarından belirlenir.</p>
      </details>
      <details>
        <summary>Telefon numaram herkese açık olur mu?</summary>
        <p class="a">Numaranız yalnızca kart sayfasında görünür. Kısa bağlantı (`kart.php?k=...`) ad, telefon veya plaka içermez; paylaşsanız bile kişisel veri sızmaz.</p>
      </details>
      <details>
        <summary>Aktivasyon kodu nasıl teslim edilir?</summary>
        <p class="a">Ödemeniz onaylandıktan sonra kodunuz e-posta ve/veya WhatsApp ile size iletilir. Kod tek kullanımlıktır ve yalnızca sizin oturumunuza bağlanır.</p>
      </details>
      <details>
        <summary>Ödeme seçenekleri nelerdir?</summary>
        <p class="a">Shopier üzerinden kredi kartı ile güvenli ödeme veya havale/EFT ile manuel ödeme yapabilirsiniz. Her iki yöntemde de onay sonrası aktivasyon kodunuz iletilir.</p>
      </details>
    </div>
  </section>

  <section class="final-cta">
    <h2>Aracınızı dakikalar içinde bulunabilir hale getirin.</h2>
    <p>Ödeme onayı sonrası kartınızı hemen hazırlamaya başlayın.</p>
    <a class="buy-button" href="satin.php">Satın al · <?= escape_html($price) ?></a>
  </section>
</main>
<?php
layout_footer();
layout_end();
