# Belediye Ödeme Kiosk

Windows 10 kiosk modunda **1024×768** dokunmatik ekranlar için tasarlanmış belediye ödeme/sorgulama arayüzü. **Belsis SOAP web servisleri** ile entegre.

## Gereksinimler

- PHP 8.2+ (ext-curl, ext-dom, ext-simplexml)
- Composer
- Belsis sunucusuna ağ erişimi (IP yetkilendirmesi gerekebilir)

## Kurulum

```bash
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate
```

`.env` dosyasında Belsis ayarlarını yapın:

```env
BELSIS_USERNAME=sa
BELSIS_PASSWORD=ada11sql
BELSIS_TAHAKKUK_URL=http://aykome.belsis.uygulama.belsis.com.tr/tahakkukWebServis.asmx
BELSIS_TAHSILAT_URL=http://aykome.belsis.uygulama.belsis.com.tr/tahsilatWebServis.asmx
BELSIS_IP_ADDRESS=127.0.0.1
BELSIS_MOCK=false
```

> Demo yalnızca sicil `89874` için: `BELSIS_MOCK=true` ve `BELSIS_MOCK_SICILS=89874`. Gerçek TC her zaman Belsis'e gider.

## Canlı Veri (Belsis SOAP) — Ne Yapmalısınız?

Belsis web servisi **dış IP'lerden engellenmiş** durumda. Evden veya ofisten doğrudan `curl` / `belsis:test` çalıştırdığınızda login sayfası veya HTTP 301 döner; bu kod hatası değil, **ağ güvenlik duvarı**dır.

Canlı veri almak için **aşağıdakilerden biri şart**:

### Seçenek A — Kiosk uygulamasını belediye ağındaki PC'de çalıştırın (önerilen)

Kiosk bilgisayarı zaten belediye ağındadır. Uygulamayı **o makinede** kurun:

```bash
composer install
cp .env.example .env && php artisan key:generate
```

`.env`:

```env
BELSIS_MOCK=false
BELSIS_IP_ADDRESS=<kiosk PC'nin yerel IP'si, örn. 192.168.1.50>
```

Test:

```bash
php artisan config:clear
php artisan belsis:test 89874
```

Başarılıysa tarayıcıda `http://127.0.0.1:8000` açın.

### Seçenek B — Belsis'ten IP yetkisi isteyin

Belediye/Belsis IT'ye şunu iletin:

- Kiosk uygulamasının çalışacağı sunucunun **dış IP adresi**
- Açılması gereken adresler:
  - `http://aykome.belsis.uygulama.belsis.com.tr/tahakkukWebServis.asmx`
  - `http://aykome.belsis.uygulama.belsis.com.tr/tahsilatWebServis.asmx`
- SOAP kullanıcısı: `sa` (zaten tanımlı)

IP tanımlandıktan sonra `.env` içinde `BELSIS_MOCK=false` yapıp `php artisan belsis:test 89874` ile doğrulayın.

### Seçenek C — VPN ile belediye ağına bağlanın

Belediye VPN'i varsa bağlanın, ardından Seçenek A adımlarını uygulayın.

### Kontrol listesi

| Adım | Komut / Ayar |
|------|----------------|
| Mock kapat | `BELSIS_MOCK=false` |
| Cache temizle | `php artisan config:clear` |
| Bağlantı testi | `php artisan belsis:test 89874` |
| Başarı kriteri | "Oturum: ..." ve borç listesi görünmeli |

`belsis:test` başarılı olmadan kiosk canlı veri göstermez.

## Çalıştırma

```bash
php artisan serve
# veya port meşgulse:
php artisan serve --port=8001
```

Tarayıcı: **http://127.0.0.1:8000**

## Belsis Bağlantı Testi

```bash
php artisan belsis:test 89874
php artisan belsis:webservis-test 89874
```

`belsis:webservis-test` — `webservis/` dokümantasyonundaki tüm tahsilat ve tahakkuk methodlarını sırayla dener (ödeme/iptal methodları varsayılan olarak atlanır).

`belsis:test` sırasıyla:
1. `login` ile tahsilat servisinde oturum açar
2. `arama` / `sicilSorgula` ile vatandaş bilgisini çözer
3. `borcSorgula` ile ödenmemiş borç listesini çeker
4. Ödeme: `odemeYap` (banka/kredi kartı, `BELSIS_ODEME_SEKLI=5`)

## Canlı Sunucu (Kırklareli)

```
https://kiosk.kirklareli.bel.tr/public/
```

`.env` ayarları:

```env
APP_URL=https://kiosk.kirklareli.bel.tr/public
APP_DEBUG=false
BELSIS_MOCK=false
BELSIS_IP_ADDRESS=<sunucunun yerel IP adresi>
BELSIS_ODEME_SEKLI=5
```

Sunucuda:

```bash
php artisan config:clear
php artisan view:clear
php artisan belsis:test 89874
```

## Hızlı Başlat

```bash
./start.sh
```

Tarayıcı: **http://127.0.0.1:8000** (meşgulse 8001)

## Demo Akışı

1. **Karşılama** — "BAŞLAMAK İÇİN DOKUNUN"
2. **Kimlik girişi** — Sicil No `89874` veya T.C. Kimlik No (min. 5 hane)
3. **Borç listesi** — Belsis'ten gelen tahakkuklar
4. **Banka kartı ödeme** — POS cihazına kart okut → `odemeYap` ile tahsilat kaydı
5. **Başarı** — Makbuz no + tutar gösterimi, 7 sn sonra ana ekrana dönüş

## API Uçları

| Method | Endpoint | Belsis Metodu |
|--------|----------|---------------|
| GET | `/api/kiosk/payment-methods` | `odemeSekilleri` |
| GET | `/api/kiosk/receipt/{makbuzId}` | `makbuzSorgula` |
| GET | `/api/kiosk/citizen/{identityNo}` | `borcSorgula` + `sicilSorgula` |
| GET | `/api/kiosk/debts/{identityNo}` | `borcSorgula` |
| POST | `/api/kiosk/payment/bank` | Ödeme başlatma |
| POST | `/api/kiosk/payment/{id}/confirm` | `odemeYap` + `makbuzSorgula` |

## Belsis Entegrasyon Mimarisi

```
app/Services/Belsis/
  BelsisSoapClient.php              → SOAP envelope + HTTP + XML parse
  BelsisAuthService.php             → login (tahsilatWebServis)
  BelsisIdentityResolver.php        → TC → sicil (arama + borcSorgula)
  BelsisTahsilatCatalogService.php  → odemeSekilleri, kdvHesaplari, kdvOranlari, tahakkukTurleri
  BelsisTahsilatQueryService.php    → arama, borcSorgula, sicilSorgula, makbuzSorgula, tahsilatSorgula...
  BelsisTahsilatService.php         → odemeYap (tahakkuklu/tahakkuksuz), makbuzIptal
  BelsisTahakkukService.php         → tahakkukBilgileriniGetir, tahakkukEkle, tahakkukIptal, gmkSorgula...
  BelsisKioskService.php            → Kiosk API orchestrator
config/belsis.php                   → URL, kimlik bilgileri
```

### Tahsilat Web Servisi (`tahsilatWebServis_1.wsdl`)

- `login`, `odemeSekilleri`, `kdvHesaplari`, `kdvOranlari`, `tahakkukTurleri`
- `arama`, `borcSorgula`, `sicilSorgula`, `sicilBorcBeyanSorgula`
- `mukellefMakbuzSorgula`, `tahsilatSorgula`, `tahsilatDetaySorgula`
- `odemeYap` (tahakkuklu + tahakkuksuz), `makbuzSorgula`, `makbuzIptal`

### Tahakkuk Web Servisi (`tahakkukWebServis_1.wsdl`)

- `login`, `tahakkukTurleri`, `kdvHesaplari`, `kdvOranlari`, `sicilSorgula`
- `tahakkukBilgileriniGetir`, `tahakkukOdemeBilgileriniGetir`
- `tahakkukEkle`, `tahakkukIptal`, `genmahSorgulaCombo`, `gmkSorgula`

## Dosya Yapısı

```
resources/views/kiosk/index.blade.php  → 4 ekranlı kiosk UI
app/Http/Controllers/Api/KioskApiController.php
routes/web.php, routes/api.php
```

## Kiosk Özellikleri

- 1024×768 sabit, kaydırma yok
- Entegre numpad
- 45 sn hareketsizlik → 15 sn uyarı → oturum sıfırlama
- Sağ tık / metin seçimi engeli
