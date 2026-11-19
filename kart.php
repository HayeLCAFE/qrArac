<?php
declare(strict_types=1);
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/includes/layout.php';

$token = preg_replace('/[^a-fA-F0-9]/', '', (string) ($_GET['k'] ?? ''));
$card = null;
if (strlen($token) === 24) {
    $query = app_db()->prepare('SELECT * FROM cards WHERE public_token = :token');
    $query->execute(['token' => strtolower($token)]);
    $card = $query->fetch() ?: null;
}

header('Cache-Control: no-store');
header('X-Robots-Tag: noindex, nofollow');

if (!app_rate_limit_allows('card-view', 600, 10)) {
    http_response_code(429);
    exit('Too many requests');
}
if (!$card) {
    http_response_code(404);
    layout_head('Kart bulunamadı | Araç İletişim');
    echo '<div class="page">';
    layout_top('<a class="nav-link" href="index.php">Ana sayfa</a>');
    echo '<main class="legal"><h1>Kart bulunamadı</h1><p>Bu bağlantı geçersiz veya kart silinmiş.</p></main>';
    layout_footer();
    echo '</div>';
    layout_end();
    exit;
}

$locale = $card['locale'] === 'en' ? 'en' : 'tr';
$en = $locale === 'en';
$copy = $en
    ? [
        'title' => 'Contact the vehicle owner',
        'ready' => 'Ready to help',
        'phone' => 'Phone',
        'call' => 'Call now',
        'sms' => 'Send message',
        'wa' => 'WhatsApp',
        'note' => 'Choose a contact option below to reach the vehicle owner.',
        'buy' => 'Get your card',
        'emergency' => 'Emergency info',
        'emergencyContact' => 'Emergency contact',
        'bloodType' => 'Blood type',
        'allergies' => 'Allergies',
        'chronic' => 'Chronic conditions',
        'notes' => 'Special notes',
        'quickTitle' => 'Send a quick message',
        'quickSub' => 'Pick a message to send instantly.',
        'otherTitle' => 'Other contact options',
        'callSub' => 'Start a voice call',
        'smsSub' => 'Open messages',
        'waSub' => 'Chat on WhatsApp',
        'scan' => 'Scanned from a vehicle card'
    ]
    : [
        'title' => 'Araç sahibine ulaşın',
        'ready' => 'Size yardımcı olmaya hazır',
        'phone' => 'Telefon',
        'call' => 'Hemen ara',
        'sms' => 'Mesaj gönder',
        'wa' => 'WhatsApp',
        'note' => 'Araç sahibine ulaşmak için aşağıdaki iletişim seçeneklerinden birini kullanın.',
        'buy' => 'Kartını satın al',
        'emergency' => 'Acil bilgiler',
        'emergencyContact' => 'Acil durum numarası',
        'bloodType' => 'Kan grubu',
        'allergies' => 'Alerjiler',
        'chronic' => 'Kronik durumlar',
        'notes' => 'Özel notlar',
        'quickTitle' => 'Hızlı mesaj gönder',
        'quickSub' => 'Tek dokunuşla hazır mesaj gönderin.',
        'otherTitle' => 'Diğer iletişim seçenekleri',
        'callSub' => 'Sesli arama başlat',
        'smsSub' => 'Mesaj uygulamasını aç',
        'waSub' => 'WhatsApp üzerinden yaz',
        'scan' => 'Araç kartından okutuldu'
    ];

$templates = card_templates();
$template = $templates[(string) $card['template']] ?? $templates['navy'];

$phone = (string) $card['phone'];
$intl = international_phone($phone);
$wa = ltrim($intl, '+');
$digits = normalize_phone_digits($phone);
$phonePretty = $digits !== '' ? international_phone($phone) : '';
$note = trim((string) $card['note']);
$plate = trim((string) $card['plate']);
$ownerName = trim((string) $card['owner_name']);
$initial = mb_strtoupper(mb_substr($ownerName !== '' ? $ownerName : 'A', 0, 1));
$emergencyPhone = trim((string) ($card['emergency_phone'] ?? ''));
$emergencyIntl = $emergencyPhone !== '' ? international_phone($emergencyPhone) : '';
$bloodType = trim((string) ($card['blood_type'] ?? ''));
$allergies = trim((string) ($card['allergies'] ?? ''));
$chronicConditions = trim((string) ($card['chronic_conditions'] ?? ''));
$specialNotes = trim((string) ($card['special_notes'] ?? ''));
$hasEmergency = $emergencyIntl !== '' || $bloodType !== '' || $allergies !== '' || $chronicConditions !== '' || $specialNotes !== '';

$quickMessages = $en
    ? ['You have parked incorrectly.', 'Your vehicle was involved in an accident.', 'Your door or trunk is open.', 'Your headlights are on.']
    : ['Aracınızı yanlış park etmişsiniz.', 'Aracınız kazaya karışmış olabilir.', 'Aracınızın kapısı veya bagajı açık.', 'Aracınızın farları açık.'];

$extra = <<<'CSS'
:root{--tpl-info:__INFO__;--tpl-accent:__ACCENT__;--tpl-ink:__INK__;--tpl-soft:#eef4f0;--danger:#b3261e;--danger-soft:#fdeceb}
body{background:#f2f5f1}
.page{width:min(480px,calc(100% - 32px));grid-template-rows:auto 1fr auto;padding:18px 0 34px}
.topbar{padding:2px 0 14px}
.kart{display:grid;gap:14px}
.card{overflow:hidden;border:1px solid var(--line);border-radius:16px;background:#fff;box-shadow:0 12px 32px #17241f12}
.card-head{display:flex;align-items:center;gap:14px;padding:20px 20px 16px}
.avatar{width:52px;height:52px;flex:0 0 52px;display:grid;place-items:center;border-radius:14px;background:var(--tpl-info);color:#fff;font:800 22px/1 "Segoe UI",sans-serif}
.owner-meta{min-width:0;flex:1}
.owner-meta h1{margin:0 0 5px;font:800 19px/1.25 "Segoe UI",sans-serif;overflow-wrap:anywhere}
.ready-row{display:flex;align-items:center;gap:7px;margin:0;color:var(--muted);font-size:12px;font-weight:600}
.ready-row::before{width:7px;height:7px;border-radius:50%;background:#4c9a4c;box-shadow:0 0 0 3px #4c9a4c22;content:""}
.plate-chip{flex:0 0 auto;padding:7px 10px;border:1px solid var(--line);border-radius:8px;background:var(--tpl-soft);color:var(--tpl-ink);font:800 11px/1 "Segoe UI",sans-serif;letter-spacing:.1em;white-space:nowrap}
.card-body{padding:0 20px 20px}
.phone-hero{display:flex;align-items:center;justify-content:space-between;gap:14px;padding:16px 18px;margin-bottom:14px;border-radius:12px;background:var(--tpl-soft)}
.phone-label{margin:0 0 3px;color:var(--muted);font-size:10px;font-weight:800;letter-spacing:.09em;text-transform:uppercase}
.phone-number{margin:0;font:800 21px/1.2 "Segoe UI",sans-serif;letter-spacing:.01em;overflow-wrap:anywhere}
.phone-ico{width:40px;height:40px;flex:0 0 40px;display:grid;place-items:center;border-radius:50%;background:var(--tpl-info);color:#fff}
.phone-ico svg{width:18px;height:18px}
.note-box{margin:0 0 16px;padding:12px 14px;border:1px solid var(--line);border-left:3px solid var(--tpl-accent);border-radius:10px;background:#fafbf8;color:#405048;font-size:12.5px;line-height:1.6}
.actions{display:grid;grid-template-columns:1fr 1fr;gap:10px}
.action{min-height:56px;display:flex;align-items:center;gap:11px;padding:0 14px;border:1.5px solid var(--line);border-radius:12px;color:var(--ink);background:#fff;font:700 13.5px "Segoe UI",sans-serif;text-decoration:none;transition:transform .15s ease,box-shadow .15s ease,border-color .15s ease}
.action:hover{transform:translateY(-1px);box-shadow:0 8px 20px #17241f14;border-color:#c6d0c4}
.action:active{transform:scale(.985)}
.action svg{width:20px;height:20px;flex:0 0 20px}
.action .sub{display:block;margin-top:3px;color:var(--muted);font-size:10.5px;font-weight:600}
.action.call{grid-column:1/-1;min-height:60px;border-color:var(--tpl-info);color:#fff;background:var(--tpl-info)}
.action.call .sub{color:#ffffffb8}
.action.call:hover{border-color:var(--tpl-info);box-shadow:0 10px 24px #17241f22}
.action.wa{border-color:#1faa55;color:#0f7a3d}
.action.wa:hover{border-color:#1faa55;background:#f2fbf5}
.action.sms{color:var(--tpl-ink)}
.card-foot{margin:14px 0 0;color:var(--muted);font-size:10.5px;line-height:1.5;text-align:center}
.acc{border:1px solid var(--line);border-radius:14px;background:#fff;box-shadow:0 8px 22px #17241f0d;overflow:hidden}
.acc summary{display:flex;align-items:center;gap:10px;padding:15px 18px;cursor:pointer;list-style:none;font:700 13.5px "Segoe UI",sans-serif}
.acc summary::-webkit-details-marker{display:none}
.acc summary .chev{margin-left:auto;width:18px;height:18px;color:var(--muted);transition:transform .2s ease}
.acc[open] summary .chev{transform:rotate(180deg)}
.acc-ico{width:34px;height:34px;flex:0 0 34px;display:grid;place-items:center;border-radius:10px;font-size:15px}
.acc-ico.red{background:var(--danger-soft);color:var(--danger)}
.acc-ico.blue{background:#e8f0fb;color:#2b5ea7}
.acc-ico.green{background:#e9f6ec;color:#1d7a3d}
.acc-ico.amber{background:#fdf3e0;color:#9a6a12}
.acc-body{padding:2px 18px 16px;border-top:1px solid var(--line)}
.meo-line{display:flex;justify-content:space-between;align-items:baseline;gap:12px;padding:9px 0;border-bottom:1px dashed #edf0ea;font-size:12.5px}
.meo-line:last-child{border-bottom:0}
.meo-line span{color:var(--muted);font-weight:600}
.meo-line strong{font-weight:700;text-align:right;overflow-wrap:anywhere}
.meo-line a{color:var(--danger);font-weight:800;text-decoration:none}
.acc-intro{margin:10px 0 6px;color:var(--muted);font-size:12px;line-height:1.55}
.quick-list{display:grid;gap:8px;padding-bottom:14px}
.quick-msg{display:flex;align-items:center;justify-content:space-between;gap:10px;padding:11px 13px;border:1px solid var(--line);border-radius:10px;color:var(--ink);background:#fbfcfa;font-size:12.5px;font-weight:600;text-decoration:none;transition:border-color .15s ease,background .15s ease}
.quick-msg:hover{border-color:var(--tpl-accent);background:var(--tpl-soft)}
.quick-msg .arrow{color:var(--muted);font-weight:400}
.kart-scan{margin:18px 0 0;color:var(--muted);font-size:10px;letter-spacing:.14em;text-align:center;text-transform:uppercase}
@media(max-width:420px){.page{width:calc(100% - 20px);padding:12px 0 24px}.actions{grid-template-columns:1fr}.action.wa,.action.sms{grid-column:auto}.phone-number{font-size:19px}.card-head{padding:16px 16px 14px}.card-body{padding:0 16px 16px}}
CSS;
$extra = str_replace(
    ['__INFO__', '__ACCENT__', '__INK__'],
    [$template['info'], $template['qr'], $template['ink']],
    $extra
);

layout_head($copy['title'], $extra);
echo '<div class="page">';
layout_top('<a class="builder-link" href="satin.php">' . escape_html($copy['buy']) . '</a>');
?>
<main class="kart">
  <section class="card" aria-label="<?= escape_html($copy['title']) ?>">
    <div class="card-head">
      <span class="avatar" aria-hidden="true"><?= escape_html($initial) ?></span>
      <div class="owner-meta">
        <h1><?= escape_html($ownerName !== '' ? $ownerName : ($en ? 'Vehicle owner' : 'Araç sahibi')) ?></h1>
        <p class="ready-row"><?= escape_html($copy['ready']) ?></p>
      </div>
      <?php if ($plate !== ''): ?><span class="plate-chip"><?= escape_html(mb_strtoupper($plate)) ?></span><?php endif; ?>
    </div>
    <div class="card-body">
      <div class="phone-hero">
        <div>
          <p class="phone-label"><?= escape_html($copy['phone']) ?></p>
          <p class="phone-number"><?= escape_html($phonePretty !== '' ? $phonePretty : $phone) ?></p>
        </div>
        <span class="phone-ico" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M22 16.9v3a2 2 0 0 1-2.2 2 19.8 19.8 0 0 1-8.6-3.1 19.4 19.4 0 0 1-6-6A19.8 19.8 0 0 1 2.1 4.2 2 2 0 0 1 4.1 2h3a2 2 0 0 1 2 1.7c.1 1 .4 2 .7 2.9a2 2 0 0 1-.5 2.1L8 9.9a16 16 0 0 0 6 6l1.2-1.3a2 2 0 0 1 2.1-.5c.9.3 1.9.6 2.9.7a2 2 0 0 1 1.8 2.1Z"/></svg></span>
      </div>
      <?php if ($note !== ''): ?><p class="note-box"><?= escape_html($note) ?></p><?php endif; ?>
      <div class="actions">
        <?php if ($intl !== ''): ?>
          <a class="action call" href="tel:<?= escape_html($intl) ?>"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M22 16.9v3a2 2 0 0 1-2.2 2 19.8 19.8 0 0 1-8.6-3.1 19.4 19.4 0 0 1-6-6A19.8 19.8 0 0 1 2.1 4.2 2 2 0 0 1 4.1 2h3a2 2 0 0 1 2 1.7c.1 1 .4 2 .7 2.9a2 2 0 0 1-.5 2.1L8 9.9a16 16 0 0 0 6 6l1.2-1.3a2 2 0 0 1 2.1-.5c.9.3 1.9.6 2.9.7a2 2 0 0 1 1.8 2.1Z"/></svg><span><?= escape_html($copy['call']) ?><span class="sub"><?= escape_html($copy['callSub']) ?></span></span></a>
          <a class="action sms" href="sms:<?= escape_html($intl) ?>"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M21 11.5a8.4 8.4 0 0 1-.9 3.8 8.5 8.5 0 0 1-7.6 4.7 8.4 8.4 0 0 1-3.8-.9L3 21l1.9-5.7a8.4 8.4 0 0 1-.9-3.8 8.5 8.5 0 0 1 4.7-7.6 8.4 8.4 0 0 1 3.8-.9h.5a8.5 8.5 0 0 1 8 8v.5Z"/></svg><span><?= escape_html($copy['sms']) ?><span class="sub"><?= escape_html($copy['smsSub']) ?></span></span></a>
        <?php endif; ?>
        <?php if ((int) $card['whatsapp'] === 1 && $wa !== ''): ?>
          <a class="action wa" href="https://wa.me/<?= escape_html($wa) ?>" rel="noopener"><svg viewBox="0 0 24 24" fill="currentColor"><path d="M12 2a10 10 0 0 0-8.6 15.1L2 22l5-1.3A10 10 0 1 0 12 2Zm5.4 14.1c-.2.6-1.3 1.2-1.8 1.2-.5.1-1 .2-3.3-.7-2.8-1.1-4.5-4-4.7-4.2-.1-.2-1.1-1.5-1.1-2.9s.7-2 1-2.3c.2-.3.5-.3.7-.3h.5c.2 0 .4 0 .6.5l.9 2.1c.1.2.1.4 0 .6l-.4.6-.5.5c-.2.2-.3.4-.1.7.2.3.9 1.5 2 2.4 1.4 1.2 2.5 1.6 2.9 1.7.3.2.5.1.7-.1l1-1.2c.2-.3.4-.2.7-.1l2 .9c.3.2.5.3.6.4.1.2.1.7-.1 1.3Z"/></svg><span><?= escape_html($copy['wa']) ?><span class="sub"><?= escape_html($copy['waSub']) ?></span></span></a>
        <?php endif; ?>
      </div>
      <p class="card-foot"><?= escape_html($copy['note']) ?></p>
    </div>
  </section>

  <?php if ($hasEmergency): ?>
  <details class="acc emergency-acc">
    <summary>
      <span class="acc-ico red" aria-hidden="true">!</span>
      <span><?= escape_html($copy['emergency']) ?></span>
      <svg class="chev" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m6 9 6 6 6-6"/></svg>
    </summary>
    <div class="acc-body">
      <?php if ($emergencyIntl !== ''): ?><div class="meo-line"><span><?= escape_html($copy['emergencyContact']) ?></span><strong><a href="tel:<?= escape_html($emergencyIntl) ?>"><?= escape_html($emergencyIntl) ?></a></strong></div><?php endif; ?>
      <?php if ($bloodType !== ''): ?><div class="meo-line"><span><?= escape_html($copy['bloodType']) ?></span><strong><?= escape_html($bloodType) ?></strong></div><?php endif; ?>
      <?php if ($allergies !== ''): ?><div class="meo-line"><span><?= escape_html($copy['allergies']) ?></span><strong><?= escape_html($allergies) ?></strong></div><?php endif; ?>
      <?php if ($chronicConditions !== ''): ?><div class="meo-line"><span><?= escape_html($copy['chronic']) ?></span><strong><?= escape_html($chronicConditions) ?></strong></div><?php endif; ?>
      <?php if ($specialNotes !== ''): ?><div class="meo-line"><span><?= escape_html($copy['notes']) ?></span><strong><?= escape_html($specialNotes) ?></strong></div><?php endif; ?>
    </div>
  </details>
  <?php endif; ?>

  <details class="acc">
    <summary>
      <span class="acc-ico blue" aria-hidden="true">✉</span>
      <span><?= escape_html($copy['quickTitle']) ?></span>
      <svg class="chev" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m6 9 6 6 6-6"/></svg>
    </summary>
    <div class="acc-body">
      <p class="acc-intro"><?= escape_html($copy['quickSub']) ?></p>
      <div class="quick-list">
        <?php foreach ($quickMessages as $quickMessage): ?>
          <a class="quick-msg" href="sms:<?= escape_html($intl) ?>?body=<?= escape_html(rawurlencode($quickMessage)) ?>"><span><?= escape_html($quickMessage) ?></span><span class="arrow">›</span></a>
        <?php endforeach; ?>
      </div>
    </div>
  </details>

  <details class="acc">
    <summary>
      <span class="acc-ico green" aria-hidden="true">☎</span>
      <span><?= escape_html($copy['otherTitle']) ?></span>
      <svg class="chev" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m6 9 6 6 6-6"/></svg>
    </summary>
    <div class="acc-body">
      <div class="quick-list">
        <?php if ($intl !== ''): ?><a class="quick-msg" href="sms:<?= escape_html($intl) ?>"><span><?= escape_html($copy['sms']) ?></span><span class="arrow">›</span></a><?php endif; ?>
        <?php if ($wa !== ''): ?><a class="quick-msg" href="https://wa.me/<?= escape_html($wa) ?>" rel="noopener"><span>WhatsApp</span><span class="arrow">›</span></a><?php endif; ?>
      </div>
    </div>
  </details>

  <p class="kart-scan"><?= escape_html($copy['scan']) ?></p>
</main>
<?php
layout_footer();
layout_end();
