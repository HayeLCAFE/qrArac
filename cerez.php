<?php
declare(strict_types=1);
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/includes/layout.php';
layout_head('Çerez politikası');
echo '<div class="page">';
layout_top('<a class="nav-link" href="index.php">Ana sayfa</a>');
?>
<main class="legal">
  <p class="eyebrow">Belgeler</p>
  <h1>Çerez politikası</h1>
  <p>Sitede yalnızca birinci taraf oturum çerezleri kullanılır. Amaçlar: CSRF koruması, satın alma sonrası stüdyo erişimi, yönetici oturumu.</p>
  <p>Reklam, analitik veya üçüncü taraf izleme çerezi yoktur. Çerezler HttpOnly ve SameSite=Lax olarak set edilir; HTTPS’de Secure bayrağı açılır.</p>
  <p>Tarayıcınızdan çerezleri silebilirsiniz. Zorunlu oturum çerezleri olmadan kod aktivasyonu ve yönetim paneli çalışmaz.</p>
</main>
<?php
layout_footer();
echo '</div>';
layout_end();
