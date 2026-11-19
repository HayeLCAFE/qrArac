<?php
declare(strict_types=1);
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/includes/layout.php';
layout_head('NFC yazma rehberi');
echo '<div class="page">';
layout_top('<a class="nav-link" href="index.php">Ana sayfa</a>');
?>
<main class="legal">
  <p class="eyebrow">Kurulum</p>
  <h1>NFC çipini programlama</h1>
  <ol>
    <li>Fiziksel NDEF uyumlu NFC etiketini karta veya cam etiketine yerleştirin.</li>
    <li>Sitenin HTTPS adresinde stüdyoyu açın. Web NFC yalnızca Android Chrome’da vardır.</li>
    <li>Kartı kaydedip QR oluşturun; ardından “NFC’ye aktar” ile aynı kısa bağlantıyı yazın.</li>
    <li>Yazdıktan sonra ikinci bir telefonla okutup iletişim sayfasının açıldığını doğrulayın.</li>
    <li>iPhone NFC yazma için Web NFC yoktur; çipi Android ile yazın veya hazır NDEF URL etiketi kullanın.</li>
  </ol>
  <p>Baskıdaki NFC işareti tek başına çalışmaz. Çip yoksa yalnızca QR kullanılır.</p>
  <p><a class="button" href="studio.php">Stüdyoya git</a></p>
</main>
<?php
layout_footer();
echo '</div>';
layout_end();
