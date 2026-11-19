<?php
declare(strict_types=1);
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/includes/layout.php';
layout_head('İade ve cayma');
echo '<div class="page">';
layout_top('<a class="nav-link" href="index.php">Ana sayfa</a>');
$controller = company_info_html();
?>
<main class="legal">
  <p class="eyebrow">Belgeler</p>
  <h1>İade ve cayma hakkı</h1>

  <h2>Genel kural</h2>
  <p>Mesafeli Sözleşmeler Yönetmeliği uyarınca alıcı, ürünü teslim aldığı tarihten veya dijital hizmete erişim sağladığı tarihten itibaren 14 gün içinde sözleşmeden cayma hakkına sahiptir. Cayma bildirimi yazılı olarak (e-posta veya satıcıyla iletişim kanalı) yapılır.</p>

  <h2>Kişiye özel ürün istisnası</h2>
  <p>QR iletişim sayfası ve ona bağlı fiziksel kart <strong>alıcıya özel olarak hazırlanan</strong> ürünlerdir. Yönetmeliğin kişiye özel mallara ilişkin istisnası gereği, dijital kart oluşturucuya erişim sağlandıktan ve/veya kişiye özel sayfa yayına alındıktan sonra cayma talebi kabul edilemez. Ödeme onayı öncesinde cayma talebinde bulunmak isterseniz siparişinizi Shopier üzerinden iptal edebilir veya satıcıyla iletişime geçebilirsiniz.</p>

  <h2>Hatalı veya hasarlı teslimat</h2>
  <p>Fiziksel kartın baskı hatası, hasar veya yanlış ürün olması durumunda teslim tarihinden itibaren 14 gün içinde talep oluşturabilirsiniz. Talepte sipariş referansınızı ve sorunun fotoğrafını paylaşmanız süreci hızlandırır. Onaylanan iadelerde bedel, ödemenin yapıldığı yöntemle iade edilir.</p>

  <h2>Nasıl başvurulur</h2>
  <p>Aşağıdaki iletişim kanallarından sipariş referansınızla birlikte yazın; talebiniz en geç 14 gün içinde sonuçlandırılır.</p>
  <?php if ($controller !== ''): ?>
    <p><?= $controller ?></p>
  <?php else: ?>
    <p>İletişim bilgileri yönetim panelindeki şirket ayarlarından yayınlanır.</p>
  <?php endif; ?>
</main>
<?php
layout_footer();
echo '</div>';
layout_end();
