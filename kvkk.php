<?php
declare(strict_types=1);
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/includes/layout.php';
layout_head('KVKK aydınlatma metni');
echo '<div class="page">';
layout_top('<a class="nav-link" href="index.php">Ana sayfa</a>');
$controller = company_info_html();
?>
<main class="legal">
  <p class="eyebrow">Belgeler</p>
  <h1>KVKK aydınlatma metni</h1>
  <h2>Veri sorumlusu</h2>
  <?php if ($controller !== ''): ?>
    <p><?= $controller ?></p>
  <?php else: ?>
    <p>Veri sorumlusu, kartı satan işletmedir. İletişim bilgileri yönetim panelindeki şirket ayarlarından güncellenir.</p>
  <?php endif; ?>
  <p>6698 sayılı Kişisel Verilerin Korunması Kanunu uyarınca verileriniz aşağıdaki amaçlarla işlenir: siparişin ifası, aktivasyon kodunun iletilmesi, iletişim sayfasının yayınlanması, güvenlik ve yasal yükümlülükler.</p>
  <h2>İşleme amaçları ve hukuki sebepler</h2>
  <p>Hukuki sebepler: sözleşmenin kurulması ve ifası, meşru menfaat (güvenlik, dolandırıcılık önleme), açık rıza gerektiren hallerde rıza.</p>
  <h2>Özel nitelikli kişisel veriler (sağlık)</h2>
  <p>Stüdyodaki "Acil durum" bölümünde kan grubu, alerji, kronik durum ve acil not gibi <strong>özel nitelikli</strong> sağlık verileri girebilirsiniz. Bu alanlar isteğe bağlıdır; boş bırakabilirsiniz.</p>
  <p>Doldurduğunuz sağlık bilgileri, QR kodu okutan herkesin görebileceği şekilde kart sayfanızda yayınlanır. Bu nedenle kayıt sırasında "bu bilgilerin QR’ı okutan herkese görüneceğini kabul ediyorum" onayı istenir. Onay vermeden sağlık alanları kaydedilmez.</p>
  <p>Sağlık verileri açık rızanıza dayanılarak işlenir (KVKK m.6). Rızanızı dilediğiniz zaman stüdyodan alanları temizleyerek geri çekebilirsiniz; kart sayfasındaki sağlık bölümü buna göre güncellenir.</p>
  <h2>Diğer veriler</h2>
  <p>Toplanan iletişim bilgileri QR adresinde yer almaz; kısa jeton ile sunucudan okunur. Yine de kartı tarayan kişiler sayfadaki telefonu görür; bu, ürünün doğası gereği alenileşen veridir.</p>
  <h2>Haklarınız</h2>
  <p>Kanun’un 11. maddesindeki haklarınız (erişim, düzeltme, silme, itiraz) için yukarıdaki iletişim kanallarından başvurabilirsiniz. Başvurular en geç 30 gün içinde yanıtlanır.</p>
</main>
<?php
layout_footer();
echo '</div>';
layout_end();
