<?php

namespace App\Http\Controllers;

use Illuminate\Http\Response;

/**
 * Chrome → Edge IE modu köprüsü.
 *
 * Uzak sunucudaki PHP istemci PC'de Edge açamaz. Windows istemcide bir kez
 * "baylan-ie:" protokolü + Chrome AutoLaunch politikası kurulur; böylece
 * Chrome harici protokol uyarısı göstermeden Edge'i IE modunda açar.
 */
class BaylanIeController extends Controller
{
    public function installPs1(): Response
    {
        $url = $this->baylanUrl();
        $urlPs = str_replace("'", "''", $url);
        $origin = $this->kioskOrigin();
        $originPs = str_replace("'", "''", $origin);
        $kioskUrlPs = str_replace("'", "''", $origin.'/kiosk');
        $policyJson = $this->autoLaunchPolicyJson();
        $policyPs = str_replace("'", "''", $policyJson);

        $script = <<<POWERSHELL
# Kırklareli Kiosk — Yönetici olarak bir kez çalıştırın
# Ana ekran: Chrome kiosk modu
# Baylan: Edge IE modu + tam ekran + harici protokol uyarısı olmadan
\$ErrorActionPreference = 'Stop'
\$dir = Join-Path \$env:LOCALAPPDATA 'KioskBaylan'
New-Item -ItemType Directory -Force -Path \$dir | Out-Null

\$url = '$urlPs'
\$origin = '$originPs'
\$kioskUrl = '$kioskUrlPs'
\$policyJson = '$policyPs'

\$edgeX86 = Join-Path \${env:ProgramFiles(x86)} 'Microsoft\\Edge\\Application\\msedge.exe'
\$edge64  = Join-Path \$env:ProgramFiles 'Microsoft\\Edge\\Application\\msedge.exe'
if (Test-Path \$edgeX86) { \$edge = \$edgeX86 }
elseif (Test-Path \$edge64) { \$edge = \$edge64 }
else { \$edge = 'msedge' }

\$chromeX86 = Join-Path \${env:ProgramFiles(x86)} 'Google\\Chrome\\Application\\chrome.exe'
\$chrome64  = Join-Path \$env:ProgramFiles 'Google\\Chrome\\Application\\chrome.exe'
if (Test-Path \$chromeX86) { \$chrome = \$chromeX86 }
elseif (Test-Path \$chrome64) { \$chrome = \$chrome64 }
else { \$chrome = 'chrome' }

\$baylanCmdPath = Join-Path \$dir 'open-baylan.cmd'
\$edgeProfile = Join-Path \$dir 'EdgeProfile'
@(
    '@echo off'
    ('start "" "' + \$edge + '" --user-data-dir="' + \$edgeProfile + '" --edge-kiosk-type=fullscreen --ie-mode-force --no-first-run --disable-pinch --overscroll-history-navigation=0 --kiosk "' + \$url + '"')
) | Set-Content -Path \$baylanCmdPath -Encoding ASCII

\$kioskCmdPath = Join-Path \$dir 'open-kiosk.cmd'
\$chromeProfile = Join-Path \$dir 'ChromeProfile'
@(
    '@echo off'
    'taskkill /F /IM chrome.exe >nul 2>&1'
    ('start "" "' + \$chrome + '" --user-data-dir="' + \$chromeProfile + '" --kiosk "' + \$kioskUrl + '" --no-first-run --disable-pinch --overscroll-history-navigation=0 --disable-session-crashed-bubble')
) | Set-Content -Path \$kioskCmdPath -Encoding ASCII

# 1) baylan-ie: protokolu
\$base = 'HKCU:\\Software\\Classes\\baylan-ie'
New-Item -Path \$base -Force | Out-Null
Set-ItemProperty -Path \$base -Name '(default)' -Value 'URL:Baylan IE Mode'
New-ItemProperty -Path \$base -Name 'URL Protocol' -Value '' -PropertyType String -Force | Out-Null
\$cmdKey = Join-Path \$base 'shell\\open\\command'
New-Item -Path \$cmdKey -Force | Out-Null
Set-ItemProperty -Path \$cmdKey -Name '(default)' -Value ('"' + \$baylanCmdPath + '" "%1"')

# 2) Chrome/Edge: harici protokol uyarisini kapat (AutoLaunchProtocolsFromOrigins)
function Set-BrowserAutoLaunch([string]\$root) {
    if (-not (Test-Path \$root)) { New-Item -Path \$root -Force | Out-Null }
    New-ItemProperty -Path \$root -Name 'AutoLaunchProtocolsFromOrigins' -Value \$policyJson -PropertyType String -Force | Out-Null
    New-ItemProperty -Path \$root -Name 'ExternalProtocolDialogShowAlwaysOpenCheckbox' -Value 1 -PropertyType DWord -Force | Out-Null
}

foreach (\$browserRoot in @(
    'HKCU:\\Software\\Policies\\Google\\Chrome',
    'HKCU:\\Software\\Policies\\Microsoft\\Edge',
    'HKLM:\\Software\\Policies\\Google\\Chrome',
    'HKLM:\\Software\\Policies\\Microsoft\\Edge'
)) {
    try { Set-BrowserAutoLaunch \$browserRoot } catch { }
}

# 3) Windows oturum açılışında ana kiosku Chrome tam ekran başlat
\$runKey = 'HKCU:\\Software\\Microsoft\\Windows\\CurrentVersion\\Run'
if (-not (Test-Path \$runKey)) { New-Item -Path \$runKey -Force | Out-Null }
New-ItemProperty -Path \$runKey -Name 'KirklareliKiosk' -Value ('"' + \$kioskCmdPath + '"') -PropertyType String -Force | Out-Null

Write-Host ''
Write-Host 'Kurulum tamam.' -ForegroundColor Green
Write-Host '1) Chrome u TAMAMEN kapatip yeniden acin'
Write-Host '2) chrome://policy adresinde AutoLaunchProtocolsFromOrigins gorunmeli'
Write-Host '3) Windows oturumunu kapatip acin; kiosk otomatik tam ekran baslayacak'
Write-Host "4) Kiosk (\$origin) uzerinden BAYLAN a tiklayin — uyari olmadan Edge IE tam ekran acilacak"
Write-Host "Ana kiosk: \$kioskUrl"
Write-Host "Baylan: \$url"
Start-Sleep -Seconds 4
POWERSHELL;

        return response($script, 200, [
            'Content-Type' => 'application/octet-stream; charset=utf-8',
            'Content-Disposition' => 'attachment; filename="baylan-ie-kurulum.ps1"',
            'Cache-Control' => 'no-store',
        ]);
    }

    public function registerReg(): Response
    {
        $url = $this->baylanUrl();
        $kioskUrl = $this->kioskOrigin().'/kiosk';
        $edge = 'C:\\Program Files (x86)\\Microsoft\\Edge\\Application\\msedge.exe';
        $chrome = 'C:\\Program Files\\Google\\Chrome\\Application\\chrome.exe';

        // .reg komutlarında %LOCALAPPDATA% genişlemez; sabit Edge/Chrome yolu kullan.
        $edgeReg = str_replace('\\', '\\\\', $edge);
        $chromeReg = str_replace('\\', '\\\\', $chrome);
        $urlReg = str_replace(['\\', '"'], ['\\\\', '\\"'], $url);
        $kioskUrlReg = str_replace(['\\', '"'], ['\\\\', '\\"'], $kioskUrl);
        $command = '\\"'.$edgeReg.'\\" --edge-kiosk-type=fullscreen --ie-mode-force --no-first-run'
            .' --disable-pinch --overscroll-history-navigation=0'
            .' --kiosk \\"'.$urlReg.'\\"';
        $kioskCommand = '\\"'.$chromeReg.'\\" --no-first-run --disable-pinch --overscroll-history-navigation=0'
            .' --disable-session-crashed-bubble'
            .' --kiosk \\"'.$kioskUrlReg.'\\"';

        // .reg içinde JSON tırnakları \" olarak kaçar
        $policyReg = str_replace('"', '\\"', $this->autoLaunchPolicyJson());

        $reg = <<<REG
Windows Registry Editor Version 5.00

[HKEY_CURRENT_USER\\Software\\Classes\\baylan-ie]
@="URL:Baylan IE Mode"
"URL Protocol"=""

[HKEY_CURRENT_USER\\Software\\Classes\\baylan-ie\\shell]

[HKEY_CURRENT_USER\\Software\\Classes\\baylan-ie\\shell\\open]

[HKEY_CURRENT_USER\\Software\\Classes\\baylan-ie\\shell\\open\\command]
@="$command"

[HKEY_CURRENT_USER\\Software\\Microsoft\\Windows\\CurrentVersion\\Run]
"KirklareliKiosk"="$kioskCommand"

[HKEY_CURRENT_USER\\Software\\Policies\\Google\\Chrome]
"AutoLaunchProtocolsFromOrigins"="$policyReg"
"ExternalProtocolDialogShowAlwaysOpenCheckbox"=dword:00000001

[HKEY_LOCAL_MACHINE\\Software\\Policies\\Google\\Chrome]
"AutoLaunchProtocolsFromOrigins"="$policyReg"
"ExternalProtocolDialogShowAlwaysOpenCheckbox"=dword:00000001

[HKEY_CURRENT_USER\\Software\\Policies\\Microsoft\\Edge]
"AutoLaunchProtocolsFromOrigins"="$policyReg"

[HKEY_LOCAL_MACHINE\\Software\\Policies\\Microsoft\\Edge]
"AutoLaunchProtocolsFromOrigins"="$policyReg"

REG;

        return response($reg, 200, [
            'Content-Type' => 'application/octet-stream; charset=utf-8',
            'Content-Disposition' => 'attachment; filename="baylan-ie-kurulum.reg"',
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

    /**
     * Chrome'un baylan-ie: için uyarı göstermemesi.
     * Kiosk PC'de yalnızca bu uygulama çalıştığı için tüm origin'lere izin verilir;
     * dar origin eşleşmesi (IP / localhost farkı) protokolü sessizce engelleyebilir.
     */
    private function autoLaunchPolicyJson(): string
    {
        return json_encode([
            [
                'protocol' => 'baylan-ie',
                'allowed_origins' => ['*'],
            ],
        ], JSON_UNESCAPED_SLASHES);
    }
}
