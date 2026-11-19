# QR Araç Kartı Satışı

## Ürün ve fiyat

Paket fiyatı yönetim panelinden belirlenir. Başlangıç fiyatı 399 TL'dir. Paket; kişiye özel dijital QR iletişim sayfasını, 90 × 55 mm baskı dosyasını ve Shopier üzerinden takip edilen fiziksel UV kart siparişini kapsar.

Kart tasarımı QR ve NFC kullanımına uygundur. NFC için fiziksel NFC çipi/etiketi gerekir. Oluşturucu, aynı iletişim bağlantısını NFC etiketine Web NFC destekli Android Chrome ve HTTPS üzerinde yazabilir; etiketsiz baskıda NFC işareti tek başına işlev sağlamaz.

## Yönetici ve ayarlar

Yönetim sayfası `https://SITENIZ/yonetim.php` adresindedir. İlk kullanımda sunucuda proje klasöründe şu komutu çalıştırın:

```powershell
C:\xampp\php\php.exe .\setup-admin-password.php
```

En az 14 karakterli yönetici parolasını terminale yazın; parolayı sohbet veya e-posta ile paylaşmayın. Hash web kökü dışındaki özel depoda tutulur. Girişte 15 dakika içinde 8 denemeden sonra bloke uygulanır; yönetici oturumu 20 dakika hareketsizlikte kapanır.

Panelden fiyatı, Shopier ürün URL'sini, banka adı/IBAN/hesap sahibini, ödeme açıklamasını ve yönetici e-posta adresini ayarlayın. IBAN kaydedilirken Türkiye IBAN kontrol basamağı doğrulanır. Ayar dosyası web kökünün dışındadır.

## Havale / EFT ve kod teslimi

1. Yönetim paneline geçerli banka bilgilerini ve bildirim e-postasını girin.
2. Müşteri satış sayfasındaki **Havale / EFT ile öde** bağlantısından banka bilgilerini görür; transferden sonra adını, e-posta veya telefonunu, tutarı ve banka referansını gönderir.
3. Bildirim yönetim panelindeki bekleyenler listesine kaydolur. E-posta, sunucunun PHP `mail()`/SMTP taşıması yapılandırılmışsa yöneticiye bildirilir; ayar yoksa bildirim yine panelde kalır.
4. Banka hesabındaki parayı kendiniz doğruladıktan sonra panelden **Onayla + e-posta kodu** veya **Onayla + WhatsApp kodu** seçin. E-posta `mail()`/SMTP ile gönderilir. WhatsApp seçeneği, kodu mesajı hazır olarak içeren bir `wa.me` bağlantısı üretir; bağlantıyı açıp mesajı siz gönderirsiniz.
5. Aktivasyon kodu onaydan sonra yalnızca yönetici panelinde gösterilir ve müşterinin `satin.php` sayfasında kullanılır.

Müşteri bildirimi ödeme onayı değildir. Para hesabınızda görünmeden onay vermeyin. Gerçek e-posta teslimi için hosting sağlayıcısının SMTP/PHP mail ayarlarını yapıp test edin. Havale akışı manuel onayla çalışır; SMS gateway (Netgsm) ve e-posta `mail()`/SMTP üzerinden gider.

Onaylı havale kaydında **Kodu e-posta ile tekrar ver** veya **Kodu WhatsApp ile gönder** işlemi kullanılabilir. Kayıtlı kodun yeniden verilmesi aktivasyonu sıfırlar; müşterinin önceki cihaz oturumu kapanır. Yeni kodlar doğrulama hash'inin yanında `activation.key` ile AES-256-GCM kullanılarak şifreli saklanır. `activation.key` dosyasını `licenses.sqlite` ile aynı özel depoda yedekleyin; anahtar olmadan saklı kodlar çözülemez.

Bu özellik eklenmeden önce üretilen kodlarda yalnızca hash vardır; aynı kod geri alınamaz. Bu eski kayıtlarda panel eski kodu geçersiz kılıp yeni bir aktivasyon kodu üretir.

## Shopier

Shopier'de ürünü oluşturup URL'sini yönetim panelindeki alana girin. Ürün fiyatının paneldeki fiyatla aynı olduğundan emin olun.

Shopier webhook'u `shopier-callback.php` üzerinden çalışır. `shopier_webhook_token` ayarına Shopier'ın verdiği token'ı girin; gelen istek `Shopier-Signature` başlığıyla HMAC-SHA256 olarak doğrulanır. Doğrulanan siparişlerde tutar panel fiyatıyla eşleşmezse kod üretilmez; `order.cancelled`/`order.refunded` gibi iptal/iade olaylarında lisansın oturumu kapatılır ve kodu devre dışı bırakılır. Alternatif olarak `shopier_api_secret` ile form-callback imzası da doğrulanır. **İlk canlı test siparişinde payload alan adlarını (`id`, `status`, `email`, tutar alanı) gerçek yanıtla doğrulayın.**

CLI ile manuel aktivasyon kodu üretme aracı da bulunur:

```powershell
C:\xampp\php\php.exe .\issue-code.php SHOPIER_SIPARIS_NUMARASI
```

`SHOPIER_SIPARIS_NUMARASI` yerine gerçek ve benzersiz sipariş referansını yazın. Kod tek kullanımlıktır ve etkinleştiği tarayıcı oturumuna bağlanır.

## Sunucu gereksinimleri

- PHP 8.2 veya üzeri
- `pdo_sqlite` PHP eklentisi
- HTTPS ve PHP oturum çerezleri
- Depolama dizinine yazma izni
- PHP `mail()`/SMTP ayarı (e-posta bildirim ve teslimi için)

XAMPP Apache veya PHP'nin yerel sunucusu kullanılabilir. PHP yerel önizlemesi için proje klasöründe:

```powershell
C:\xampp\php\php.exe -S 127.0.0.1:8001 -t .
```

Varsayılan lisans veritabanı web kökünün dışında, `C:\xampp\arac-storage\licenses.sqlite` konumunda oluşturulur. `ARAC_STORAGE_DIR` ortam değişkeniyle başka bir özel dizin belirlenebilir.

Yedek almak için (CLI): `C:\xampp\php\php.exe .\backup-storage.php`. Betik veritabanını `VACUUM INTO` ile tutarlı bir anlık görüntü olarak arşive koyar; `activation.key` ise zip'in dışında ayrı bir `.key` dosyasına yazılır (anahtar olmadan saklı kodlar çözülemez). Yedekleri sunucu dışına taşıyın; cron/Task Scheduler ile örn. günlük çalıştırın. `activation.key` ve `admin-password.hash` mutlaka yedeklenmelidir.

Yasal sayfalar (`gizlilik.php`, `kvkk.php`, `cerez.php`, `mesafeli-satis.php`, `on-bilgilendirme.php`, `iade.php`) satıştan önce yönetim panelindeki şirket bilgileri (unvan, adres, vergi no, herkese açık e-posta/telefon) ile doldurulmalıdır. **Bu metinler örnektir; yayına almadan önce bir hukukçu tarafından incelenmelidir.**

Canlı site dosyaları doğrudan `public_html` içindeyse özel depolama varsayılan olarak `public_html` klasörünün bir üstündeki `arac-storage` dizinidir. Terminal kullanmadan geçiş için yereldeki `C:\xampp\arac-storage` klasörünün tamamını hosting Dosya Yöneticisi ile bu özel konuma yükleyin. İçindeki `admin-password.hash`, `settings.json` ve veritabanı dosyaları aynı kalır. Bu klasörü `public_html` içine yüklemeyin; `setup-admin-password.php` komutunu canlıda çalıştırmanız gerekmez.

## Önemli sınırlar

- QR kodundaki telefon numarası, QR'ı tarayan kişilere açıktır.
- WhatsApp bağlantısı mesajı göndermez; yönetici cihazında WhatsApp Web/uygulaması açılır, mesaj hazır gelir ve yönetici gönder düğmesine basar.
- NFC yazma için fiziksel NFC çipi, HTTPS ve Web NFC destekleyen Android Chrome gerekir.
- Gerçek baskıdan önce sitenin HTTPS alan adında yayınlandığını ve basılı QR'ın açıldığını test edin.