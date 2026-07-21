<?php

namespace App\Http\Controllers;

use Illuminate\Http\Response;

/**
 * Baylan / kiosk kurulum köprüsü.
 *
 * BAYLAN'a tıklanınca Edge, kiosk PC'de çalışan PHP tarafından doğrudan
 * açılır (KioskApiController@openBaylan). Bu yüzden tarayıcı protokolü
 * (baylan-ie:) artık kullanılmaz.
 *
 * Buradaki kurulum dosyaları yalnızca opsiyonel kiosk kilidini ayarlar:
 *  - Windows oturum açılışında Chrome'u tam ekran (kiosk) başlatır
 *  - Edge için ilk çalıştırma sihirbazını kapatır
 * Kurulum ekranından .reg veya kendini silen .cmd indirilebilir.
 */
class BaylanIeController extends Controller
{
    public function installPage(): Response
    {
        $origin = $this->kioskOrigin();
        $baylanUrl = e($this->baylanUrl());
        $kioskUrl = e($origin.'/kiosk');
        $regUrl = e($origin.'/baylan-ie/kurulum.reg');
        $cmdUrl = e($origin.'/baylan-ie/kurulum.cmd');

        $html = <<<HTML
<!doctype html>
<html lang="tr">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Kırklareli Kiosk · Kurulum</title>
<style>
  body { font-family: system-ui, Segoe UI, Arial, sans-serif; background:#0f172a; color:#e2e8f0; margin:0; padding:32px; }
  .card { max-width:720px; margin:0 auto; background:#1e293b; border:1px solid #334155; border-radius:18px; padding:28px 32px; }
  h1 { font-size:22px; margin:0 0 4px; }
  p.sub { color:#94a3b8; margin:0 0 24px; }
  .btns { display:flex; gap:14px; flex-wrap:wrap; margin:20px 0 28px; }
  a.btn { display:inline-block; text-decoration:none; font-weight:700; padding:16px 22px; border-radius:12px; }
  a.primary { background:#0ea5e9; color:#04283a; }
  a.secondary { background:#334155; color:#e2e8f0; }
  ol { line-height:1.7; }
  code { background:#0f172a; padding:2px 7px; border-radius:6px; color:#7dd3fc; }
  .note { margin-top:22px; font-size:13px; color:#94a3b8; border-top:1px solid #334155; padding-top:16px; }
</style>
</head>
<body>
<div class="card">
  <h1>Kırklareli Kiosk — Kurulum</h1>
  <p class="sub">Bu ekranı yalnızca kiosk bilgisayarında açın.</p>

  <div class="btns">
    <a class="btn primary" href="$cmdUrl">Otomatik kurulum indir (.cmd)</a>
    <a class="btn secondary" href="$regUrl">Registry dosyası indir (.reg)</a>
  </div>

  <ol>
    <li><b>.cmd</b> dosyasını indirin ve <b>çift tıklayın</b> (yönetici gerekmez). Kurulum biter, dosya kendini siler.</li>
    <li>Alternatif: <b>.reg</b> dosyasını çift tıklayıp <b>Evet</b> deyin.</li>
    <li>Chrome'u <b>tamamen kapatıp</b> yeniden açın.</li>
    <li>Windows oturumunu kapatıp açtığınızda kiosk tam ekran otomatik başlar.</li>
  </ol>

  <div class="note">
    BAYLAN'a tıklandığında Edge IE modunda bu adrese açılır:<br>
    <code>$baylanUrl</code><br><br>
    Kiosk ana ekranı: <code>$kioskUrl</code><br>
    Edge'i açan PHP bu kiosk bilgisayarında çalışmalıdır.
  </div>
</div>
</body>
</html>
HTML;

        return response($html, 200, [
            'Content-Type' => 'text/html; charset=utf-8',
            'Cache-Control' => 'no-store',
        ]);
    }

    public function registerReg(): Response
    {
        $chrome = 'C:\\Program Files\\Google\\Chrome\\Application\\chrome.exe';
        $kioskUrl = $this->kioskOrigin().'/kiosk';

        $kioskCmd = $this->regCommandValue(
            '"'.$chrome.'" --no-first-run --disable-pinch --overscroll-history-navigation=0'
            .' --disable-session-crashed-bubble'
            .' --kiosk "'.$kioskUrl.'"'
        );

        $reg = <<<REG
Windows Registry Editor Version 5.00

; Kirklareli Kiosk kurulum
; Cift tiklayin (Yonetici GEREKMEZ), Evet deyin.
; Sonra Windows oturumunu kapatip acin.

[HKEY_CURRENT_USER\\Software\\Microsoft\\Windows\\CurrentVersion\\Run]
"KirklareliKiosk"="$kioskCmd"

; Edge ilk calistirma sihirbazini kapat (IE modu temiz acilsin)
[HKEY_CURRENT_USER\\Software\\Policies\\Microsoft\\Edge]
"HideFirstRunExperience"=dword:00000001

REG;

        return response($this->toDosText($reg), 200, [
            'Content-Type' => 'application/octet-stream; charset=utf-8',
            'Content-Disposition' => 'attachment; filename="kurulum.reg"',
            'Cache-Control' => 'no-store',
        ]);
    }

    /**
     * Çift tıklanınca registry'yi yazar, sonra kendini siler.
     */
    public function installCmd(): Response
    {
        $chrome = 'C:\\Program Files\\Google\\Chrome\\Application\\chrome.exe';
        $kioskUrl = $this->kioskOrigin().'/kiosk';

        $kioskLaunch = '"'.$chrome.'" --no-first-run --disable-pinch'
            .' --overscroll-history-navigation=0 --disable-session-crashed-bubble'
            .' --kiosk "'.$kioskUrl.'"';
        $kioskForReg = str_replace('"', '\\"', $kioskLaunch);

        $cmd = <<<CMD
@echo off
chcp 65001 >nul
title Kirklareli Kiosk Kurulum
echo Kirklareli Kiosk - kurulum
echo.

reg add "HKCU\\Software\\Microsoft\\Windows\\CurrentVersion\\Run" /v KirklareliKiosk /t REG_SZ /d "{$kioskForReg}" /f >nul
if errorlevel 1 goto err

reg add "HKCU\\Software\\Policies\\Microsoft\\Edge" /v HideFirstRunExperience /t REG_DWORD /d 1 /f >nul

echo Kurulum OK.
echo.
echo 1^) Chrome'u TAMAMEN kapatip yeniden acin
echo 2^) Windows oturumunu kapatip acin -^> kiosk tam ekran baslar
echo 3^) Kiosk'tan BAYLAN'a tiklayin
echo.
echo Bu pencere kapaninca kurulum dosyasi silinecek.
pause
goto cleanup

:err
echo HATA: Registry yazilamadi.
pause

:cleanup
(goto) 2>nul & del "%~f0"
CMD;

        return response($this->toDosText($cmd), 200, [
            'Content-Type' => 'application/octet-stream; charset=utf-8',
            'Content-Disposition' => 'attachment; filename="kurulum.cmd"',
            'Cache-Control' => 'no-store',
        ]);
    }

    private function baylanUrl(): string
    {
        $url = trim((string) config('belsis.baylan_ie_url'));
        if ($url === '' || ! filter_var($url, FILTER_VALIDATE_URL)) {
            abort(500, 'Baylan adresi yapılandırılmamış.');
        }

        return $url;
    }

    private function kioskOrigin(): string
    {
        return rtrim(request()->getSchemeAndHttpHost(), '/');
    }

    /** .reg komut satırı değeri: ters bölü ve tırnakları kaçır. */
    private function regCommandValue(string $commandLine): string
    {
        $escaped = str_replace('\\', '\\\\', $commandLine);

        return str_replace('"', '\\"', $escaped);
    }

    private function toDosText(string $content): string
    {
        return str_replace(["\r\n", "\n"], "\r\n", $content);
    }
}
