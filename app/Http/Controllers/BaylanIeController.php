<?php

namespace App\Http\Controllers;

use Illuminate\Http\Response;

/**
 * Uzak sunucu → Windows kiosk PC köprüsü.
 *
 * BAYLAN tıklanınca Chrome şu protokolü çağırır:
 *   baylan-ie:http://belapp.belediye.local/.../baylan.aspx?...
 * Kiosk PC'deki handler Edge'i --ie-mode-force ile o URL'de açar.
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
<title>Kırklareli Kiosk · Baylan Kurulum</title>
<style>
  body { font-family: system-ui, Segoe UI, Arial, sans-serif; background:#0f172a; color:#e2e8f0; margin:0; padding:32px; }
  .card { max-width:760px; margin:0 auto; background:#1e293b; border:1px solid #334155; border-radius:18px; padding:28px 32px; }
  h1 { font-size:22px; margin:0 0 4px; }
  p.sub { color:#94a3b8; margin:0 0 20px; }
  .warn { background:#422006; border:1px solid #b45309; color:#fdba74; padding:12px 14px; border-radius:10px; margin-bottom:20px; font-size:14px; line-height:1.5; }
  .btns { display:flex; gap:14px; flex-wrap:wrap; margin:16px 0 24px; }
  a.btn { display:inline-block; text-decoration:none; font-weight:700; padding:16px 22px; border-radius:12px; }
  a.primary { background:#0ea5e9; color:#04283a; }
  a.secondary { background:#334155; color:#e2e8f0; }
  ol { line-height:1.75; padding-left:22px; }
  code { background:#0f172a; padding:2px 7px; border-radius:6px; color:#7dd3fc; word-break:break-all; }
  .note { margin-top:22px; font-size:13px; color:#94a3b8; border-top:1px solid #334155; padding-top:16px; }
</style>
</head>
<body>
<div class="card">
  <h1>Baylan IE — Kiosk PC Kurulumu</h1>
  <p class="sub">Bu sayfayı <b>kiosk Windows bilgisayarında</b> açın (sunucuda değil).</p>

  <div class="warn">
    BAYLAN'a tıklanınca şu adres Edge <b>IE modunda</b> açılmalıdır.<br>
    Bunun için aşağıdaki kurulumu <b>bir kez kiosk PC'de</b> çalıştırın.
  </div>

  <div class="btns">
    <a class="btn primary" href="$cmdUrl">1) Otomatik kurulum (.cmd)</a>
    <a class="btn secondary" href="$regUrl">veya .reg indir</a>
  </div>

  <ol>
    <li><b>.cmd</b> indirip <b>çift tıklayın</b>. Edge test için Baylan sayfasını IE modunda açar; dosya kendini siler.</li>
    <li>Alternatif: <b>.reg</b> → çift tık → <b>Evet</b>.</li>
    <li><b>Chrome'u tamamen kapatıp</b> yeniden açın.</li>
    <li>Kiosk'tan <b>BAYLAN</b>'a tıklayın → avans kredi sayfası IE modunda açılır.</li>
  </ol>

  <div class="note">
    Açılacak adres:<br><code>$baylanUrl</code><br><br>
    Kiosk: <code>$kioskUrl</code>
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
        $url = $this->baylanUrl();
        $kioskUrl = $this->kioskOrigin().'/kiosk';
        $edge = 'C:\\Program Files (x86)\\Microsoft\\Edge\\Application\\msedge.exe';
        $chrome = 'C:\\Program Files\\Google\\Chrome\\Application\\chrome.exe';
        $policyReg = str_replace('"', '\\"', $this->autoLaunchPolicyJson());

        // %1 = baylan-ie:http://...  → open-baylan.cmd protokol önekini silip Edge IE açar
        // .reg sabit yol kullanamaz (%LOCALAPPDATA% genişlemez); Edge'i doğrudan çağırır,
        // URL protokol argümanından veya yedek olarak kayıtlı adresten gelir.
        // En güvenlisi .cmd kurulumu (LOCALAPPDATA altında launcher yazar).
        $baylanCmd = $this->regCommandValue(
            '"'.$edge.'" --edge-kiosk-type=fullscreen --ie-mode-force --no-first-run'
            .' --disable-pinch --overscroll-history-navigation=0'
            .' --kiosk "'.$url.'"'
        );
        $kioskCmd = $this->regCommandValue(
            '"'.$chrome.'" --no-first-run --disable-pinch --overscroll-history-navigation=0'
            .' --disable-session-crashed-bubble'
            .' --kiosk "'.$kioskUrl.'"'
        );

        $reg = <<<REG
Windows Registry Editor Version 5.00

; Kirklareli Kiosk - Baylan IE
; BAYLAN → Edge IE modunda baylan.aspx (avans kredi)

[HKEY_CURRENT_USER\\Software\\Classes\\baylan-ie]
@="URL:Baylan IE Mode"
"URL Protocol"=""

[HKEY_CURRENT_USER\\Software\\Classes\\baylan-ie\\shell\\open\\command]
@="$baylanCmd"

[HKEY_CURRENT_USER\\Software\\Policies\\Google\\Chrome]
"AutoLaunchProtocolsFromOrigins"="$policyReg"
"ExternalProtocolDialogShowAlwaysOpenCheckbox"=dword:00000001

[HKEY_CURRENT_USER\\Software\\Policies\\Microsoft\\Edge]
"HideFirstRunExperience"=dword:00000001

[HKEY_CURRENT_USER\\Software\\Microsoft\\Windows\\CurrentVersion\\Run]
"KirklareliKiosk"="$kioskCmd"

REG;

        return response($this->toDosText($reg), 200, [
            'Content-Type' => 'application/octet-stream; charset=utf-8',
            'Content-Disposition' => 'attachment; filename="kurulum.reg"',
            'Cache-Control' => 'no-store',
        ]);
    }

    public function installCmd(): Response
    {
        $ps1 = $this->buildInstallPs1();
        $b64 = base64_encode($ps1);

        $cmd = <<<CMD
@echo off
chcp 65001 >nul
title Kirklareli Kiosk Baylan Kurulum
echo Kurulum basliyor...
echo.

powershell -NoProfile -ExecutionPolicy Bypass -Command "\$p=Join-Path \$env:TEMP 'kiosk-baylan-kurulum.ps1'; [IO.File]::WriteAllBytes(\$p,[Convert]::FromBase64String('{$b64}')); try { & \$p; \$code=\$LASTEXITCODE } catch { Write-Host \$_.Exception.Message -ForegroundColor Red; \$code=1 }; Remove-Item -LiteralPath \$p -Force -ErrorAction SilentlyContinue; exit \$code"

if errorlevel 1 (
  echo.
  echo HATA: Kurulum basarisiz.
  pause
  goto cleanup
)

echo.
echo Kurulum tamam. Bu pencere kapaninca dosya silinecek.
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

    private function buildInstallPs1(): string
    {
        $url = $this->escapePsSingle($this->baylanUrl());
        $origin = $this->escapePsSingle($this->kioskOrigin());
        $kioskUrl = $this->escapePsSingle($this->kioskOrigin().'/kiosk');
        $policy = $this->escapePsSingle($this->autoLaunchPolicyJson());

        // Launcher: baylan-ie:http://... veya baylan-ie:open → Edge IE kiosk
        return <<<POWERSHELL
\$ErrorActionPreference = 'Stop'
\$defaultUrl = '$url'
\$origin = '$origin'
\$kioskUrl = '$kioskUrl'
\$policyJson = '$policy'

\$pf86 = [Environment]::GetFolderPath('ProgramFilesX86')
\$pf = [Environment]::GetFolderPath('ProgramFiles')

\$edge = \$null
foreach (\$p in @(
    (Join-Path \$pf86 'Microsoft\\Edge\\Application\\msedge.exe'),
    (Join-Path \$pf 'Microsoft\\Edge\\Application\\msedge.exe')
)) {
    if (Test-Path -LiteralPath \$p) { \$edge = \$p; break }
}
if (-not \$edge) {
    Write-Host 'HATA: Microsoft Edge bulunamadi.' -ForegroundColor Red
    exit 1
}

\$chrome = \$null
foreach (\$p in @(
    (Join-Path \$pf 'Google\\Chrome\\Application\\chrome.exe'),
    (Join-Path \$pf86 'Google\\Chrome\\Application\\chrome.exe')
)) {
    if (Test-Path -LiteralPath \$p) { \$chrome = \$p; break }
}

\$dir = Join-Path \$env:LOCALAPPDATA 'KioskBaylan'
New-Item -ItemType Directory -Force -Path \$dir | Out-Null
\$edgeProfile = Join-Path \$dir 'EdgeProfile'

# open-baylan.cmd: protokol argümanından URL çıkar, yoksa varsayılan Baylan adresi
\$launcher = Join-Path \$dir 'open-baylan.cmd'
\$launcherLines = @(
    '@echo off',
    'setlocal EnableExtensions',
    'set "EDGE=' + \$edge + '"',
    'set "PROFILE=' + \$edgeProfile + '"',
    'set "DEFAULT=' + \$defaultUrl + '"',
    'set "RAW=%~1"',
    'if "%RAW%"=="" set "RAW=%DEFAULT%"',
    'set "RAW=%RAW:baylan-ie:=%"',
    'if /I "%RAW%"=="open" set "RAW=%DEFAULT%"',
    'if "%RAW%"=="" set "RAW=%DEFAULT%"',
    'start "" "%EDGE%" --user-data-dir="%PROFILE%" --edge-kiosk-type=fullscreen --ie-mode-force --no-first-run --disable-pinch --overscroll-history-navigation=0 --kiosk "%RAW%"'
)
\$launcherLines | Set-Content -LiteralPath \$launcher -Encoding ASCII

\$protocolCmd = '"' + \$launcher + '" "%1"'
[Microsoft.Win32.Registry]::SetValue('HKEY_CURRENT_USER\\Software\\Classes\\baylan-ie', '', 'URL:Baylan IE Mode')
[Microsoft.Win32.Registry]::SetValue('HKEY_CURRENT_USER\\Software\\Classes\\baylan-ie', 'URL Protocol', '')
[Microsoft.Win32.Registry]::SetValue('HKEY_CURRENT_USER\\Software\\Classes\\baylan-ie\\shell\\open\\command', '', \$protocolCmd)

[Microsoft.Win32.Registry]::SetValue('HKEY_CURRENT_USER\\Software\\Policies\\Google\\Chrome', 'AutoLaunchProtocolsFromOrigins', \$policyJson)
[Microsoft.Win32.Registry]::SetValue('HKEY_CURRENT_USER\\Software\\Policies\\Google\\Chrome', 'ExternalProtocolDialogShowAlwaysOpenCheckbox', 1, [Microsoft.Win32.RegistryValueKind]::DWord)
[Microsoft.Win32.Registry]::SetValue('HKEY_CURRENT_USER\\Software\\Policies\\Microsoft\\Edge', 'HideFirstRunExperience', 1, [Microsoft.Win32.RegistryValueKind]::DWord)

if (\$chrome) {
    \$chromeProfile = Join-Path \$dir 'ChromeProfile'
    \$kioskLaunch = '"{0}" --user-data-dir="{1}" --no-first-run --disable-pinch --overscroll-history-navigation=0 --disable-session-crashed-bubble --kiosk "{2}"' -f \$chrome, \$chromeProfile, \$kioskUrl
    \$kioskCmd = Join-Path \$dir 'open-kiosk.cmd'
    @('@echo off', ('start "" ' + \$kioskLaunch)) | Set-Content -LiteralPath \$kioskCmd -Encoding ASCII
    [Microsoft.Win32.Registry]::SetValue('HKEY_CURRENT_USER\\Software\\Microsoft\\Windows\\CurrentVersion\\Run', 'KirklareliKiosk', ('"' + \$kioskCmd + '"'))
}

\$verify = [Microsoft.Win32.Registry]::GetValue('HKEY_CURRENT_USER\\Software\\Classes\\baylan-ie\\shell\\open\\command', '', \$null)
if (-not \$verify) {
    Write-Host 'HATA: Protokol kaydi yazilamadi.' -ForegroundColor Red
    exit 1
}

Write-Host 'Kurulum OK.' -ForegroundColor Green
Write-Host ('Edge: ' + \$edge)
Write-Host ('Baylan (IE): ' + \$defaultUrl)

Start-Process -FilePath \$edge -ArgumentList @(
    ('--user-data-dir=' + \$edgeProfile),
    '--edge-kiosk-type=fullscreen',
    '--ie-mode-force',
    '--no-first-run',
    '--kiosk',
    \$defaultUrl
) | Out-Null

Write-Host 'Test: Edge IE modunda Baylan avans kredi sayfasi acildi.' -ForegroundColor Green
Write-Host 'SIMDI: Chrome u tamamen kapatip yeniden acin, sonra BAYLAN a tiklayin.'
exit 0
POWERSHELL;
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

    private function autoLaunchPolicyJson(): string
    {
        $origin = $this->kioskOrigin();

        return json_encode([
            [
                'protocol' => 'baylan-ie',
                'allowed_origins' => array_values(array_unique([
                    '*',
                    $origin,
                    'http://localhost',
                    'http://127.0.0.1',
                    'http://kiosk.test',
                ])),
            ],
        ], JSON_UNESCAPED_SLASHES);
    }

    private function escapePsSingle(string $value): string
    {
        return str_replace("'", "''", $value);
    }

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
