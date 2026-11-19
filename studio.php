<?php
declare(strict_types=1);
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/includes/layout.php';
header('X-Robots-Tag: noindex, nofollow');
require_paid_access();
$templates = card_templates();
$extra = '
.studio-grid{width:100%;max-width:1080px;margin:auto;padding:40px 0 56px;display:grid;grid-template-columns:minmax(340px,460px) minmax(0,1fr);gap:clamp(28px,5vw,56px);align-items:start}
.page-intro{margin-bottom:20px}
.page-intro h1{margin-bottom:10px;font:800 34px/1.1 "Segoe UI",sans-serif;letter-spacing:-.02em}
.card-list{display:grid;gap:8px;margin:0 0 18px}
.card-item{display:flex;align-items:center;justify-content:space-between;gap:10px;width:100%;padding:12px 14px;border:1px solid var(--line);border-radius:10px;background:#fff;color:var(--ink);font:600 13px "Segoe UI",sans-serif;text-align:left;cursor:pointer;transition:border-color .15s ease,box-shadow .15s ease}
.card-item:hover{border-color:#b9c9bd;box-shadow:0 6px 16px #17241f10}
.card-item.active{border-color:var(--green);box-shadow:0 0 0 1px var(--green) inset}
.card-item .ci-name{overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
.card-item .ci-plate{flex:0 0 auto;color:var(--muted);font-size:11px;font-weight:700;letter-spacing:.06em}
.card-count{margin:10px 2px 0}
.editor{padding:22px;border:1px solid #dfe4dc;border-radius:14px;background:#ffffffe6;box-shadow:0 14px 36px #17241f10}
.form-section{padding-bottom:18px;margin-bottom:18px;border-bottom:1px solid #edf0ea}
.form-section:last-of-type{padding-bottom:0;margin-bottom:0;border-bottom:0}
.form-section h3{margin:0 0 4px;font:700 13px "Segoe UI",sans-serif;color:var(--ink)}
.form-section .section-sub{margin:0 0 12px;color:var(--muted);font-size:11.5px;line-height:1.5}
.form-section .field{margin-bottom:13px}
.form-section .field:last-child{margin-bottom:0}
.field.check-row{display:flex;gap:8px;align-items:center}
.check-row>span{margin:0}
.field.spaced{margin-top:14px}
.template-swatches{display:flex;gap:8px;margin-top:4px}
.swatch{flex:1;display:flex;flex-direction:column;gap:6px;align-items:center;padding:9px 6px;border:1.5px solid var(--line);border-radius:10px;background:#fff;cursor:pointer;font:600 11px "Segoe UI",sans-serif;color:var(--muted)}
.swatch i{width:100%;height:22px;border-radius:6px;display:block}
.swatch.sel{border-color:var(--green);color:var(--green);box-shadow:0 0 0 1px var(--green) inset}
.editor-foot{display:grid;gap:9px;margin-top:6px}
.editor-note{margin:0;padding:10px 12px;border-left:3px solid #a2bd60;border-radius:0 8px 8px 0;background:#f2f5eb;color:#405048;font-size:11.5px;line-height:1.5}
.preview-column{position:sticky;top:24px}
.preview-panel{padding:22px;border:1px solid #dfe4dc;border-radius:14px;background:#ffffffe6;box-shadow:0 14px 36px #17241f10}
.preview-heading{display:flex;align-items:center;justify-content:space-between;gap:10px;margin-bottom:16px}
.preview-heading h2{margin:0;font:700 16px "Segoe UI",sans-serif}
.preview-size{color:var(--muted);font-size:11px;font-weight:700;letter-spacing:.06em}
.print-card{--card-info:#0c1a31;--card-accent:#d8f1fa;--card-ink:#14324b;width:100%;max-width:340px;aspect-ratio:55/90;margin:0 auto 14px;overflow:hidden;border:2px solid #d8ded5;border-radius:12px;background:#fff;print-color-adjust:exact;-webkit-print-color-adjust:exact;transition:border-color .2s ease;display:flex;flex-direction:column}
.card-side{display:flex;align-items:center;justify-content:center;gap:10px;padding:5.5% 6%;background:var(--card-info);color:#fff}
.card-brand{width:30px;height:30px;flex:0 0 30px;display:grid;place-items:center;border-radius:9px;background:var(--card-accent);color:var(--card-info);font:800 15px/1 "Segoe UI",sans-serif}
.card-brand-name{font:800 10px/1.2 "Segoe UI",sans-serif;letter-spacing:.2em}
.card-tag{padding:4px 8px;border:1px solid #ffffff55;border-radius:999px;font:700 8px/1 "Segoe UI",sans-serif;letter-spacing:.14em;text-transform:uppercase;opacity:.9}
.card-main{flex:1;min-width:0;display:flex;flex-direction:column;align-items:center;justify-content:center;gap:3.5%;padding:5% 6% 5%;background:#fff}
.qr-frame{position:relative;width:90%;max-width:250px;aspect-ratio:1;display:grid;place-items:center;padding:1.5%;border-radius:8px;background:#fff}
.card-text{flex:0 0 auto;display:flex;flex-direction:column;align-items:center;gap:6px;text-align:center}
.card-title{margin:0;font:800 clamp(8px,1.4vw,11px)/1.2 "Segoe UI",sans-serif;color:var(--card-ink);letter-spacing:.05em;text-transform:uppercase}
.card-owner-name{max-width:100%;overflow:hidden;font:800 clamp(13px,2.4vw,20px)/1.2 "Segoe UI",sans-serif;text-align:center;text-overflow:ellipsis;white-space:nowrap;color:#17241f}
.card-hint{font:700 clamp(7px,1.1vw,10px)/1.3 "Segoe UI",sans-serif;color:var(--card-info);letter-spacing:.03em}
.card-hint::before{content:"▸ ";color:var(--card-accent)}
#qr-code,#qr-code canvas,#qr-code img{width:100%;height:100%}
.qr-placeholder{position:absolute;inset:6%;display:grid;place-items:center;padding:8px;border:1.5px dashed #d5ddd3;border-radius:6px;color:var(--muted);font-size:10px;font-weight:600;text-align:center}
.qr-placeholder[hidden]{display:none}
.preview-status{min-height:34px;margin:0 0 14px;color:var(--muted);font-size:12px;line-height:1.5;overflow-wrap:anywhere}
.preview-status a{color:var(--green);font-weight:700}
.print-actions{display:grid;grid-template-columns:1fr 1fr;gap:9px}
.nfc-status{margin:10px 0 0;color:var(--muted);font-size:11px;line-height:1.5}
@media(max-width:900px){.studio-grid{grid-template-columns:1fr}.preview-column{position:static}}
@media(max-width:560px){.studio-grid{padding:24px 0 40px}.page-intro h1{font-size:27px}.print-card{max-width:340px}.print-actions{grid-template-columns:1fr}}
@media print{
  @page{size:55mm 90mm;margin:0}
  body{width:55mm;min-height:90mm;background:#fff}
  .page-shell{width:55mm;min-height:90mm;margin:0;padding:0;display:block}
  .topbar,.page-intro,.editor,.card-list,.preview-heading,.preview-status,.print-actions,.nfc-status,.footer,.cookie-bar{display:none!important}
  .studio-grid,.preview-column,.preview-panel{width:55mm;margin:0;padding:0;display:block;border:0;box-shadow:none;background:transparent}
  .print-card{width:55mm;height:90mm;max-width:none;margin:0;border:0;border-radius:3mm}
}
';
layout_head('QR Araç Kartı Oluştur', $extra);
?>
<div class="page-shell">
  <?php layout_top('<a class="back-link" href="index.php">Ana sayfa</a>'); ?>
  <main class="studio-grid">
    <div class="studio-left">
      <div class="page-intro">
        <p class="eyebrow">Ön cam kartı</p>
        <h1>QR kartını hazırla.</h1>
        <p class="muted">Bilgiler sunucuya kaydedilir; QR yalnızca kısa bağlantı taşır.</p>
      </div>
      <div class="card-list" id="card-list"></div>
      <form class="editor" id="card-form">
        <input type="hidden" name="csrf_token" value="<?= escape_html(csrf_token()) ?>">
        <input type="hidden" name="card_id" id="card-id" value="">

        <div class="form-section">
          <h3>İletişim</h3>
          <p class="section-sub">Kartta görünecek temel bilgiler.</p>
          <label class="field"><span>Adınız</span><input id="owner-input" name="name" maxlength="48" required></label>
          <label class="field"><span>Telefon</span><input id="phone-input" name="phone" type="tel" maxlength="20" required><small class="muted">Sayfada görünür; QR adresinde yer almaz.</small></label>
          <label class="field"><span>Plaka (isteğe bağlı)</span><input id="plate-input" name="plate" maxlength="16"></label>
          <label class="field"><span>Kısa mesaj</span><textarea id="note-input" name="note" maxlength="100"></textarea></label>
        </div>

        <div class="form-section">
          <h3>Acil durum</h3>
          <p class="section-sub">Dolu alanlar kartta "Acil bilgiler" bölümünde gösterilir.</p>
          <label class="field"><span>Acil durum telefonu</span><input id="emergency-phone-input" name="emergency_phone" type="tel" maxlength="20"><small class="muted">Kan ve sağlık bilgileriyle birlikte gösterilir.</small></label>
          <label class="field"><span>Kan grubu</span><input id="blood-type-input" name="blood_type" maxlength="8" placeholder="Örn. A+, AB-"></label>
          <label class="field"><span>Alerjiler</span><textarea id="allergies-input" name="allergies" maxlength="300" placeholder="Örn. Penisilinde alerji"></textarea></label>
          <label class="field"><span>Kronik durumlar</span><textarea id="chronic-input" name="chronic_conditions" maxlength="400"></textarea></label>
          <label class="field"><span>Özel acil notlar</span><textarea id="special-notes-input" name="special_notes" maxlength="500"></textarea></label>
        </div>

        <div class="form-section">
          <h3>Görünüm</h3>
          <p class="section-sub">İletişim sayfasının dili ve kart rengi.</p>
          <label class="field"><span>Dil</span>
            <select id="locale-input" name="locale"><option value="tr">Türkçe</option><option value="en">English</option></select>
          </label>
          <label class="field"><span>Kart şablonu</span></label>
          <div class="template-swatches" id="template-swatches">
            <?php foreach ($templates as $key => $meta): ?>
              <button class="swatch" type="button" data-template="<?= escape_html($key) ?>" aria-pressed="false"><i data-color="<?= escape_html($meta['info']) ?>"></i><?= escape_html($meta['label']) ?></button>
            <?php endforeach; ?>
          </div>
          <input type="hidden" id="template-input" name="template" value="navy">
          <label class="field check-row spaced">
            <input type="checkbox" id="whatsapp-input" name="whatsapp" value="1" checked>
            <span>WhatsApp düğmesi</span>
          </label>
        </div>

        <div class="editor-foot">
          <p class="editor-note">Baskıdan önce HTTPS adresinde QR’ı tarayın.</p>
          <button class="primary-button" type="submit">Kaydet ve QR oluştur</button>
          <button class="button secondary full-button" id="new-card" type="button">Yeni araç kartı</button>
        </div>
      </form>
    </div>

    <aside class="preview-column">
      <div class="preview-panel">
        <div class="preview-heading"><h2>Kart önizlemesi</h2><span class="preview-size">55 × 90 mm</span></div>
        <article class="print-card" id="print-card">
          <div class="card-side">
            <span class="card-brand">A</span>
            <span class="card-brand-name">ARAÇ İLETİŞİM</span>
            <span class="card-tag">Acil İletişim</span>
          </div>
          <div class="card-main">
            <div class="qr-frame">
              <div id="qr-code"></div>
              <div class="qr-placeholder" id="qr-placeholder">QR kod burada görünür</div>
            </div>
            <div class="card-text">
              <p class="card-title">Araç sahibine ulaşın</p>
              <span class="card-owner-name" id="card-owner">Araç sahibi</span>
              <span class="card-hint">QR kodu okutun</span>
            </div>
          </div>
        </article>
        <p class="preview-status" id="preview-status">Kartı kaydederek kısa bağlantılı QR üretin.</p>
        <div class="print-actions">
          <button class="primary-button" id="print-button" type="button" disabled>Yazdır / PDF kaydet</button>
          <button class="button secondary" id="nfc-write-button" type="button" hidden>NFC’ye aktar</button>
        </div>
        <p class="nfc-status" id="nfc-status" hidden>NFC: Android Chrome, HTTPS ve fiziksel çip.</p>
      </div>
    </aside>
  </main>
  <?php layout_footer(); ?>
</div>
<script src="assets/qrcode.min.js"></script>
<script nonce="<?= app_csp_nonce() ?>">
const templates = <?= json_encode($templates, JSON_UNESCAPED_UNICODE) ?>;
const form = document.querySelector("#card-form");
const csrf = form.querySelector("[name=csrf_token]").value;
let generatedUrl = "";
let activeCardId = "";

document.querySelectorAll("#template-swatches .swatch i").forEach((chip) => { chip.style.background = chip.dataset.color; });
const syncSwatches = () => {
  const key = document.querySelector("#template-input").value;
  document.querySelectorAll("#template-swatches .swatch").forEach((sw) => {
    const on = sw.dataset.template === key;
    sw.classList.toggle("sel", on);
    sw.setAttribute("aria-pressed", on ? "true" : "false");
  });
};
document.querySelectorAll("#template-swatches .swatch").forEach((sw) => {
  sw.addEventListener("click", () => {
    document.querySelector("#template-input").value = sw.dataset.template;
    applyTemplate();
    syncSwatches();
  });
});
const applyTemplate = () => {
  const key = document.querySelector("#template-input").value;
  const t = templates[key] || templates.navy;
  const card = document.querySelector("#print-card");
  card.style.setProperty("--card-info", t.info);
  card.style.setProperty("--card-accent", t.qr);
  card.style.setProperty("--card-ink", t.ink);
  card.style.borderColor = t.info;
};
syncSwatches();
document.querySelector("#owner-input").addEventListener("input", () => {
  document.querySelector("#card-owner").textContent = document.querySelector("#owner-input").value.trim() || "Araç sahibi";
});
const escHtml = (value) => String(value ?? "").replace(/[&<>"']/g, (ch) => ({ "&": "&amp;", "<": "&lt;", ">": "&gt;", '"': "&quot;", "'": "&#39;" }[ch]));
const renderList = (cards, max) => {
  const wrap = document.querySelector("#card-list");
  wrap.innerHTML = cards.map((card) => `<button class="card-item${String(card.id) === activeCardId ? " active" : ""}" type="button" data-id="${card.id}"><span class="ci-name">${escHtml(card.owner_name)}</span><span class="ci-plate">${escHtml(card.plate) || "Plaka yok"}</span></button>`).join("") + `<p class="muted card-count">${cards.length}/${max} kart</p>`;
  wrap.querySelectorAll("button[data-id]").forEach((button) => {
    button.addEventListener("click", () => loadCard(cards.find((item) => String(item.id) === button.dataset.id)));
  });
};
const loadCard = (card) => {
  if (!card) return;
  activeCardId = String(card.id);
  document.querySelector("#card-id").value = card.id;
  document.querySelector("#owner-input").value = card.owner_name;
  document.querySelector("#phone-input").value = card.phone;
  document.querySelector("#note-input").value = card.note;
  document.querySelector("#plate-input").value = card.plate;
  document.querySelector("#emergency-phone-input").value = card.emergency_phone || "";
  document.querySelector("#blood-type-input").value = card.blood_type || "";
  document.querySelector("#allergies-input").value = card.allergies || "";
  document.querySelector("#chronic-input").value = card.chronic_conditions || "";
  document.querySelector("#special-notes-input").value = card.special_notes || "";
  document.querySelector("#locale-input").value = card.locale;
  document.querySelector("#template-input").value = card.template;
  document.querySelector("#whatsapp-input").checked = Number(card.whatsapp) === 1;
  document.querySelector("#card-owner").textContent = card.owner_name;
  document.querySelectorAll("#card-list .card-item").forEach((item) => item.classList.toggle("active", item.dataset.id === activeCardId));
  applyTemplate();
  syncSwatches();
  if (card.url) paintQr(card.url);
};
const paintQr = (url) => {
  generatedUrl = url;
  const box = document.querySelector("#qr-code");
  box.replaceChildren();
  if (typeof QRCode === "undefined") {
    document.querySelector("#preview-status").textContent = "QR kütüphanesi yüklenemedi.";
    return;
  }
  new QRCode(box, { text: url, width: 512, height: 512, colorDark: "#17241f", colorLight: "#ffffff", correctLevel: QRCode.CorrectLevel.Q });
  document.querySelector("#qr-placeholder").hidden = true;
  document.querySelector("#print-button").disabled = false;
  document.querySelector("#nfc-write-button").hidden = true;
  const status = document.querySelector("#preview-status");
  status.textContent = "";
  const link = document.createElement("a");
  link.href = url;
  link.target = "_blank";
  link.rel = "noopener";
  link.textContent = url;
  status.appendChild(link);
};
const refresh = async () => {
  const response = await fetch("kart-api.php", { credentials: "same-origin" });
  const data = await response.json();
  if (!data.ok) return;
  renderList(data.cards, data.max);
  if (data.cards[0] && !document.querySelector("#card-id").value) loadCard(data.cards[0]);
};
document.querySelector("#new-card").addEventListener("click", () => {
  form.reset();
  document.querySelector("#card-id").value = "";
  activeCardId = "";
  document.querySelector("#whatsapp-input").checked = true;
  document.querySelector("#template-input").value = "navy";
  generatedUrl = "";
  document.querySelector("#qr-placeholder").hidden = false;
  document.querySelector("#qr-code").replaceChildren();
  document.querySelector("#print-button").disabled = true;
  document.querySelector("#nfc-write-button").hidden = true;
  document.querySelector("#preview-status").textContent = "Kartı kaydederek kısa bağlantılı QR üretin.";
  applyTemplate();
  syncSwatches();
  refresh();
});
form.addEventListener("submit", async (event) => {
  event.preventDefault();
  const body = new FormData(form);
  if (!document.querySelector("#whatsapp-input").checked) body.set("whatsapp", "0");
  const response = await fetch("kart-api.php", { method: "POST", body, credentials: "same-origin" });
  const data = await response.json();
  if (!data.ok) {
    document.querySelector("#preview-status").textContent = data.error || "Kayıt başarısız.";
    return;
  }
  document.querySelector("#card-id").value = data.id;
  activeCardId = String(data.id);
  paintQr(data.url);
  refresh();
});
document.querySelector("#nfc-write-button").addEventListener("click", async () => {
  if (!generatedUrl || !("NDEFReader" in window)) return;
  const status = document.querySelector("#nfc-status");
  try {
    await new NDEFReader().write({ records: [{ recordType: "url", data: generatedUrl }] });
    status.textContent = "NFC yazıldı. Okutup test edin.";
  } catch (error) {
    status.textContent = error.name === "NotAllowedError" ? "NFC izni verilmedi." : "NFC yazılamadı.";
  }
});
document.querySelector("#print-button").addEventListener("click", () => window.print());
applyTemplate();
refresh();
</script>
<?php layout_end(); ?>
