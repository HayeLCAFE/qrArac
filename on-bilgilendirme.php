<?php
declare(strict_types=1);
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/includes/layout.php';
layout_head('Ön bilgilendirme formu');
echo '<div class="page">';
layout_top('<a class="nav-link" href="index.php">Ana sayfa</a>');
$controller = company_info_html();
$price = money_tl((int) app_settings()['price_kurus']);
?>
<main class="legal">
  <p class="eyebrow">Belgeler</p>
  <h1>Ön bilgilendirme formu</h1>
  <p><em>Bu metin bilgilendirme amaçlı bir örnektir; yayına almadan önce bir hukukçu tarafından incelenmelidir.</em></p>

  <h2>Satıcı</h2>
  <p><?= $controller !== '' ? $controller : 'Satıcı bilgileri yönetim panelindeki şirket ayarlarından yayınlanır.' ?></p>

  <h2>Sözleşme konusu mal/hizmet</h2>
  <p>Kişiye özel QR araç iletişim kartı paketi: dijital iletişim sayfası + 55 × 90 mm baskıya hazır QR tasarımı + Shopier üzerinden fiziksel UV kart siparişi.</p>

  <h2>Temel özellikler</h2>
  <ul>
    <li>Ad, telefon, plaka, not ve isteğe bağlı acil durum bilgilerinin girilebildiği kişisel sayfa</li>
    <li>Türkçe/İngilizce görünüm, 3 kart rengi, baskı formatında yüksek kontrastlı QR</li>
    <li>Filo desteği: lisans başına birden fazla kart</li>
    <li>NFC yazma desteği (uygun cihaz ve fiziksel NFC etiketi gerekir)</li>
  </ul>

  <h2>Satış fiyatı ve ödeme</h2>
  <p>Fiyat: <?= escape_html($price) ?> (KDV dahil, tek seferlik). Ödeme: Shopier kredi kartı veya havale/EFT.</p>

  <h2>Teslimat</h2>
  <p>Dijital teslimat (aktivasyon kodu) ödeme onayı sonrası e-posta/WhatsApp ile; fiziksel kart Shopier süreciyle.</p>

  <h2>Cayma hakkı</h2>
  <p>Mesafeli Sözleşmeler Yönetmeliği m.15 gereği, kişiye özel üretilen mal ve hizmetler cayma hakkı istisnası kapsamındadır. Detaylar için <a href="iade.php">iade ve cayma sayfasına</a> bakın.</p>

  <h2>Şikâyet ve uyuşmazlık</h2>
  <p>Başvurularınız için yukarıdaki iletişim kanallarını kullanabilirsiniz. Uyuşmazlık halinde tüketici hakem heyetleri ve tüketici mahkemeleri yetkilidir.</p>
</main>
<?php
layout_footer();
echo '</div>';
layout_end();
