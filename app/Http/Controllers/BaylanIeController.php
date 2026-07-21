<?php

namespace App\Http\Controllers;

use Illuminate\Http\Response;

/**
 * Chrome → Edge IE modu köprüsü.
 *
 * Windows kiosk PC'de bir kez kurulum yapılır:
 *  - baylan-ie: protokolü Edge'i IE kiosk modunda açar
 *  - Chrome AutoLaunch politikası harici protokol uyarısını kapatır
 */
class BaylanIeController extends Controller
{
    public function installPs1(): Response
    {
        $url = $this->escapePsSingle($this->baylanUrl());
        $origin = $this->escapePsSingle($this->kioskOrigin());
        $kioskUrl = $this->escapePsSingle($this->kioskOrigin().'/kiosk');
        $policy = $this->escapePsSingle($this->autoLaunchPolicyJson());

        // ASCII-only: irm|iex ve Windows PowerShell encoding sorunlarini onler
        $script = <<<POWERSHELL
# Kirklareli Kiosk - Baylan IE kurulum (Yonetici GEREKMEZ)
# Kullanim:  irm http://KIOSK-ADRESI/baylan-ie/kurulum.ps1 | iex
#     veya:  powershell -ExecutionPolicy Bypass -File .\\baylan-ie-kurulum.ps1
\$ErrorActionPreference = 'Stop'

\$url = '$url'
\$origin = '$origin'
\$kioskUrl = '$kioskUrl'
\$policyJson = '$policy'

function Find-Exe([string[]]\$candidates) {
    foreach (\$p in \$candidates) {
        if (\$p -and (Test-Path -LiteralPath \$p)) { return \$p }
    }
    return \$null
}

\$pf86 = [Environment]::GetFolderPath('ProgramFilesX86')
\$pf = [Environment]::GetFolderPath('ProgramFiles')

\$edge = Find-Exe @(
    (Join-Path \$pf86 'Microsoft\\Edge\\Application\\msedge.exe'),
    (Join-Path \$pf 'Microsoft\\Edge\\Application\\msedge.exe')
)
if (-not \$edge) {
    Write-Host 'HATA: Microsoft Edge bulunamadi.' -ForegroundColor Red
    exit 1
}

\$chrome = Find-Exe @(
    (Join-Path \$pf 'Google\\Chrome\\Application\\chrome.exe'),
    (Join-Path \$pf86 'Google\\Chrome\\Application\\chrome.exe')
)

\$dir = Join-Path \$env:LOCALAPPDATA 'KioskBaylan'
New-Item -ItemType Directory -Force -Path \$dir | Out-Null
\$edgeProfile = Join-Path \$dir 'EdgeProfile'
\$chromeProfile = Join-Path \$dir 'ChromeProfile'

# Protokol komutu: Edge dogrudan
\$baylanLaunch = '"{0}" --user-data-dir="{1}" --edge-kiosk-type=fullscreen --ie-mode-force --no-first-run --disable-pinch --overscroll-history-navigation=0 --kiosk "{2}"' -f \$edge, \$edgeProfile, \$url

# 1) baylan-ie: protokolu (HKCU)
[Microsoft.Win32.Registry]::SetValue('HKEY_CURRENT_USER\\Software\\Classes\\baylan-ie', '', 'URL:Baylan IE Mode')
[Microsoft.Win32.Registry]::SetValue('HKEY_CURRENT_USER\\Software\\Classes\\baylan-ie', 'URL Protocol', '')
[Microsoft.Win32.Registry]::SetValue('HKEY_CURRENT_USER\\Software\\Classes\\baylan-ie\\shell\\open\\command', '', \$baylanLaunch)

# 2) Chrome/Edge AutoLaunch (uyari olmadan ac)
foreach (\$root in @(
    'HKEY_CURRENT_USER\\Software\\Policies\\Google\\Chrome',
    'HKEY_CURRENT_USER\\Software\\Policies\\Microsoft\\Edge'
)) {
    [Microsoft.Win32.Registry]::SetValue(\$root, 'AutoLaunchProtocolsFromOrigins', \$policyJson)
    [Microsoft.Win32.Registry]::SetValue(\$root, 'ExternalProtocolDialogShowAlwaysOpenCheckbox', 1, [Microsoft.Win32.RegistryValueKind]::DWord)
}

# 3) Opsiyonel: oturum acilista Chrome kiosk
if (\$chrome) {
    \$kioskLaunch = '"{0}" --user-data-dir="{1}" --no-first-run --disable-pinch --overscroll-history-navigation=0 --disable-session-crashed-bubble --kiosk "{2}"' -f \$chrome, \$chromeProfile, \$kioskUrl
    \$kioskCmd = Join-Path \$dir 'open-kiosk.cmd'
    @(
        '@echo off'
        'taskkill /F /IM chrome.exe >nul 2>&1'
        ('start "" ' + \$kioskLaunch)
    ) | Set-Content -LiteralPath \$kioskCmd -Encoding ASCII
    [Microsoft.Win32.Registry]::SetValue(
        'HKEY_CURRENT_USER\\Software\\Microsoft\\Windows\\CurrentVersion\\Run',
        'KirklareliKiosk',
        ('"' + \$kioskCmd + '"')
    )
}

\$verify = [Microsoft.Win32.Registry]::GetValue('HKEY_CURRENT_USER\\Software\\Classes\\baylan-ie\\shell\\open\\command', '', \$null)
if (-not \$verify) {
    Write-Host 'HATA: Protokol kaydi yazilamadi.' -ForegroundColor Red
    exit 1
}

Write-Host ''
Write-Host 'Kurulum OK.' -ForegroundColor Green
Write-Host ('Edge: ' + \$edge)
Write-Host ('Baylan URL: ' + \$url)
Write-Host ('Kayit: ' + \$verify)
Write-Host ''
Write-Host 'SIMDI YAPIN:'
Write-Host '  1) Chrome u TAMAMEN kapatin (Gorev Yoneticisi > chrome.exe End Task)'
Write-Host '  2) Chrome u yeniden acin'
Write-Host '  3) Adres: chrome://policy  -> AutoLaunchProtocolsFromOrigins'
Write-Host ('  4) ' + \$origin + '/kiosk  -> BAYLAN')
Write-Host ''

try {
    \$args = @(
        ('--user-data-dir=' + \$edgeProfile),
        '--edge-kiosk-type=fullscreen',
        '--ie-mode-force',
        '--no-first-run',
        '--kiosk',
        \$url
    )
    Start-Process -FilePath \$edge -ArgumentList \$args | Out-Null
    Write-Host 'Test: Edge baslatildi. Baylan sayfasi geldiyse kurulum dogru.' -ForegroundColor Green
} catch {
    Write-Host ('Test uyarisi: ' + \$_.Exception.Message) -ForegroundColor Yellow
}

Write-Host ''
Write-Host 'Pencereyi kapatabilirsiniz.'
Start-Sleep -Seconds 5
POWERSHELL;

        return response($this->toDosText($script), 200, [
            'Content-Type' => 'text/plain; charset=utf-8',
            'Content-Disposition' => 'inline; filename="baylan-ie-kurulum.ps1"',
            'Cache-Control' => 'no-store',
        ]);
    }

    public function registerReg(): Response
    {
        $url = $this->baylanUrl();
        $kioskUrl = $this->kioskOrigin().'/kiosk';
        $edge = 'C:\\Program Files (x86)\\Microsoft\\Edge\\Application\\msedge.exe';
        $chrome = 'C:\\Program Files\\Google\\Chrome\\Application\\chrome.exe';

        // Sadece HKCU — cift tiklamada yonetici istemez / basarisiz olmaz
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
        $policyReg = str_replace('"', '\\"', $this->autoLaunchPolicyJson());

        $reg = <<<REG
Windows Registry Editor Version 5.00

; Kirklareli Kiosk - Baylan IE
; Cift tiklayin (Yonetici GEREKMEZ), Evet deyin.
; Sonra Chrome'u tamamen kapatip yeniden acin.

[HKEY_CURRENT_USER\\Software\\Classes\\baylan-ie]
@="URL:Baylan IE Mode"
"URL Protocol"=""

[HKEY_CURRENT_USER\\Software\\Classes\\baylan-ie\\shell\\open\\command]
@="$baylanCmd"

[HKEY_CURRENT_USER\\Software\\Policies\\Google\\Chrome]
"AutoLaunchProtocolsFromOrigins"="$policyReg"
"ExternalProtocolDialogShowAlwaysOpenCheckbox"=dword:00000001

[HKEY_CURRENT_USER\\Software\\Policies\\Microsoft\\Edge]
"AutoLaunchProtocolsFromOrigins"="$policyReg"
"ExternalProtocolDialogShowAlwaysOpenCheckbox"=dword:00000001

[HKEY_CURRENT_USER\\Software\\Microsoft\\Windows\\CurrentVersion\\Run]
"KirklareliKiosk"="$kioskCmd"

REG;

        return response($this->toDosText($reg), 200, [
            'Content-Type' => 'text/plain; charset=utf-8',
            'Content-Disposition' => 'attachment; filename="baylan-ie-kurulum.reg"',
            'Cache-Control' => 'no-store',
        ]);
    }

    /**
     * En kolay yol: indirip cift tiklayin — icinden .reg uretip import eder.
     */
    public function installCmd(): Response
    {
        $origin = $this->kioskOrigin();
        $regUrl = $origin.'/baylan-ie/kurulum.reg';

        $cmd = <<<CMD
@echo off
setlocal
echo Kirklareli Kiosk - Baylan IE kurulum
echo.

set "REGFILE=%TEMP%\\baylan-ie-kurulum.reg"
echo Indiriliyor: {$regUrl}
powershell -NoProfile -ExecutionPolicy Bypass -Command "try { Invoke-WebRequest -UseBasicParsing -Uri '{$regUrl}' -OutFile \$env:TEMP\\baylan-ie-kurulum.reg } catch { exit 1 }"
if errorlevel 1 (
  echo HATA: kurulum.reg indirilemedi. Ag / adres kontrol edin.
  pause
  exit /b 1
)

reg import "%REGFILE%"
if errorlevel 1 (
  echo HATA: Registry import basarisiz.
  pause
  exit /b 1
)

echo.
echo Kurulum OK.
echo 1^) Chrome'u TAMAMEN kapatin
echo 2^) Chrome'u yeniden acin
echo 3^) Kiosk'tan BAYLAN'a tiklayin
echo.
del "%REGFILE%" >nul 2>&1
pause
CMD;

        return response($this->toDosText($cmd), 200, [
            'Content-Type' => 'text/plain; charset=utf-8',
            'Content-Disposition' => 'attachment; filename="baylan-ie-kurulum.cmd"',
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

    /** .reg komut satırı: path → \\\"path\\\" */
    private function regCommandValue(string $commandLine): string
    {
        $escaped = str_replace('\\', '\\\\', $commandLine);
        $escaped = str_replace('"', '\\"', $escaped);

        return $escaped;
    }

    private function toDosText(string $content): string
    {
        return str_replace(["\r\n", "\n"], "\r\n", $content);
    }
}
