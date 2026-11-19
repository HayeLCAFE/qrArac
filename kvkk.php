<?php
declare(strict_types=1);
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/includes/layout.php';
layout_head('KVKK aydınlatma metni');
echo '<div class="page">';
layout_top('<a class="nav-link" href="index.php">Ana sayfa</a>');
?>
<main class="legal">
  <p class="eyebrow">Belgeler</p>
  <h1>KVKK aydınlatma metni</h1>
  <p>6698 sayılı Kişisel Verilerin Korunması Kanunu uyarınca veri sorumlusu, kartı satan işletmedir. İşleme amaçları: siparişin ifası, aktivasyon kodunun iletilmesi, iletişim sayfasının yayınlanması, güvenlik ve yasal yükümlülükler.</p>
  <p>Hukuki sebepler: sözleşmenin kurulması ve ifası, meşru menfaat (güvenlik, dolandırıcılık önleme), açık rıza gerektiren hallerde rıza.</p>
  <p>Toplanan iletişim bilgileri QR adresinde yer almaz; kısa jeton ile sunucudan okunur. Yine de kartı tarayan kişiler sayfadaki telefonu görür; bu, ürünün doğası gereği alenileşen veridir.</p>
  <p>Kanun’un 11. maddesindeki haklarınız (erişim, düzeltme, silme, itiraz) için yönetici e-postasına başvurabilirsiniz. Başvurular en geç 30 gün içinde yanıtlanır.</p>
</main>
<?php
layout_footer();
echo '</div>';
layout_end();
