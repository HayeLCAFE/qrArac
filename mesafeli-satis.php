<?php
declare(strict_types=1);
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/includes/layout.php';
layout_head('Mesafeli satış sözleşmesi');
echo '<div class="page">';
layout_top('<a class="nav-link" href="index.php">Ana sayfa</a>');
$controller = company_info_html();
$price = money_tl((int) app_settings()['price_kurus']);
?>
<main class="legal">
  <p class="eyebrow">Belgeler</p>
  <h1>Mesafeli satış sözleşmesi</h1>
  <p><em>Bu metin bilgilendirme amaçlı bir örnektir; yayına almadan önce bir hukukçu tarafından incelenmelidir.</em></p>

  <h2>1. Taraflar</h2>
  <p><strong>Satıcı:</strong><br><?= $controller !== '' ? $controller : 'Satıcı bilgileri yönetim panelindeki şirket ayarlarından yayınlanır.' ?></p>
  <p><strong>Alıcı:</strong> Satın alma sırasında bildirilen ad, e-posta ve/veya telefon bilgilerine sahip müşteridir.</p>

  <h2>2. Konu</h2>
  <p>İşbu sözleşmenin konusu; alıcının <?= escape_html($price) ?> karşılığında kişiye özel QR araç iletişim kartı paketini satın almasıdır. Paket; kişiye özel dijital QR iletişim sayfası, 55 × 90 mm baskı formatında QR tasarımı ve Shopier üzerinden takip edilen fiziksel UV baskı kart siparişini kapsar.</p>

  <h2>3. Fiyat ve ödeme</h2>
  <p>Ürün fiyatı <?= escape_html($price) ?>’dir (KDV dahil). Ödeme; Shopier güvenli ödeme sayfası üzerinden kredi kartı ile veya havale/EFT ile yapılabilir. Havale bildirimleri satıcı tarafından banka hesabında doğrulandıktan sonra onaylanır; bildirim tek başına ödeme onayı değildir.</p>

  <h2>4. Teslimat</h2>
  <p>Dijital teslimat (aktivasyon kodu ve kart oluşturucu erişimi) ödeme onayından sonra e-posta veya WhatsApp ile yapılır. Fiziksel kartın baskı ve teslimatı Shopier sipariş süreci üzerinden yürütülür; teslim süresi Shopier ürün sayfasında belirtilen şekilde uygulanır.</p>

  <h2>5. Cayma hakkı ve istisnası</h2>
  <p>Mesafeli Sözleşmeler Yönetmeliği uyarınca alıcı, ürünü teslim aldığı veya dijital hizmete eriştiği tarihten itibaren 14 gün içinde herhangi bir gerekçe göstermeksizin ve cezai şart ödemeksizin sözleşmeden cayma hakkına sahiptir. Ancak yönetmeliğin kişiye özel olarak üretilen mallara ilişkin istisnası gereği, <strong>alıcıya özel olarak hazırlanan QR iletişim sayfası ve ona bağlı fiziksel kart</strong> cayma hakkı kapsamı dışındadır; bu nedenle teslimden sonra cayma talebi kabul edilemez. Cayma hakkının kullanılabildiği durumlarda (örn. hatalı/hasarlı fiziksel kart teslimi) alıcı iade sayfasındaki adımları izleyerek satıcıyla iletişime geçer.</p>

  <h2>6. Kişisel veriler</h2>
  <p>Sipariş ve kart verilerinin işlenmesi KVKK aydınlatma metninde açıklanmıştır. Stüdyoda girilen sağlık bilgileri özel nitelikli veri olup açık onay olmadan kaydedilmez.</p>

  <h2>7. Uyuşmazlık</h2>
  <p>Uyuşmazlıklarda tüketici mevzuatı ve satıcının yerleşim yerindeki tüketici hakem heyetleri/tüketici mahkemeleri yetkilidir.</p>
</main>
<?php
layout_footer();
echo '</div>';
layout_end();
