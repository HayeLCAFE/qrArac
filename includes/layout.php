<?php
declare(strict_types=1);

function layout_head(string $title, string $extraCss = ''): void
{
    echo '<!doctype html><html lang="tr"><head><meta charset="utf-8">';
    echo '<meta name="viewport" content="width=device-width, initial-scale=1">';
    echo '<meta name="theme-color" content="#f2f3ed">';
    echo '<meta name="referrer" content="strict-origin-when-cross-origin">';
    echo '<title>' . escape_html($title) . '</title>';
    echo '<link rel="stylesheet" href="assets/app.css">';
    if ($extraCss !== '') {
        echo '<style nonce="' . app_csp_nonce() . '">' . $extraCss . '</style>';
    }
    echo '</head><body>';
}

function layout_top(string $rightHtml = ''): void
{
    echo '<header class="topbar"><div class="brand"><span class="brand-mark" aria-hidden="true">A</span><span>ARAÇ İLETİŞİM</span></div>';
    echo $rightHtml;
    echo '</header>';
}

function layout_footer(): void
{
    echo '<footer class="footer"><span>ARAÇ İLETİŞİM</span><span>' . legal_links_html() . '</span></footer>';
    echo '<div class="cookie-bar" id="cookie-bar" role="dialog" aria-label="Çerez bildirimi"><p>Oturum çerezleri yalnızca satın alma ve yönetim için kullanılır. Zorunlu çerezler dışında izleme yoktur. Ayrıntı: <a href="cerez.php">Çerez politikası</a>.</p><button class="button" type="button" id="cookie-accept">Tamam</button></div>';
    echo '<script nonce="' . app_csp_nonce() . '">const bar=document.getElementById("cookie-bar");if(bar&&!localStorage.getItem("arac_cookie_ok")){bar.classList.add("is-visible");document.getElementById("cookie-accept").addEventListener("click",()=>{localStorage.setItem("arac_cookie_ok","1");bar.classList.remove("is-visible");});}</script>';
}

function layout_end(): void
{
    echo '</body></html>';
}
