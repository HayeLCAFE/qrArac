<?php
declare(strict_types=1);
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/includes/layout.php';
layout_head('Gizlilik politikası');
echo '<div class="page">';
layout_top('<a class="nav-link" href="index.php">Ana sayfa</a>');
$controller = company_info_html();
?>
<main class="legal">
  <p class="eyebrow">Belgeler</p>
  <h1>Gizlilik politikası</h1>
  <p>Araç İletişim, QR kartı satışı ve kişiye özel iletişim sayfası sunar. Veriler Türkiye’de barındırılan uygulama depolama dizininde tutulur.</p>
  <?php if ($controller !== ''): ?>
    <h2>Veri sorumlusu</h2>
    <p><?= $controller ?></p>
  <?php endif; ?>
  <h2>Toplanan veriler</h2>
  <ul>
    <li>Satın alma ve havale bildiriminde ad, e-posta, telefon, tutar, banka referansı</li>
    <li>Kart kaydında ad, telefon, isteğe bağlı not ve plaka</li>
    <li>Stüdyo "Acil durum" bölümünde isteğe bağlı sağlık bilgileri (kan grubu, alerji, kronik durum, acil not); yalnızca açık onayınızla kaydedilir ve kart sayfanızda herkese görünür</li>
    <li>Yönetici girişi için IP karması ve oturum kimliği</li>
  </ul>
  <h2>Saklama</h2>
  <p>Sipariş ve kart kayıtları hizmet sürdüğü sürece, yasal saklama yükümlülüğü varsa ilgili süre boyunca tutulur. Havale bildirimlerindeki IP yalnızca kötüye kullanımı sınırlamak için hash olarak saklanır. Sağlık bilgilerini stüdyodan istediğiniz zaman silebilirsiniz.</p>
  <h2>Paylaşım</h2>
  <p>Ödeme Shopier veya bankanız üzerinden yürür. SMS için Netgsm kullanırsanız telefon numarası o sağlayıcıya iletilir. Üçüncü taraf font, analitik veya izleme servisi kullanılmaz.</p>
  <p>İletişim talepleriniz için <?= $controller !== '' ? 'yukarıdaki iletişim kanallarını' : 'yönetim panelindeki e-posta adresini' ?> kullanın.</p>
</main>
<?php
layout_footer();
echo '</div>';
layout_end();
