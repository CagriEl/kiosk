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
        $policyJson = $this->autoLaunchPolicyJson();
        $policyPs = str_replace("'", "''", $policyJson);

        $script = <<<POWERSHELL
# Baylan IE Mode — Yönetici olarak bir kez çalıştırın (Chrome uyarısız acilis icin)
\$ErrorActionPreference = 'Stop'
\$dir = Join-Path \$env:LOCALAPPDATA 'KioskBaylan'
New-Item -ItemType Directory -Force -Path \$dir | Out-Null

\$url = '$urlPs'
\$origin = '$originPs'
\$policyJson = '$policyPs'

\$edgeX86 = Join-Path \${env:ProgramFiles(x86)} 'Microsoft\\Edge\\Application\\msedge.exe'
\$edge64  = Join-Path \$env:ProgramFiles 'Microsoft\\Edge\\Application\\msedge.exe'
if (Test-Path \$edgeX86) { \$edge = \$edgeX86 }
elseif (Test-Path \$edge64) { \$edge = \$edge64 }
else { \$edge = 'msedge' }

\$cmdPath = Join-Path \$dir 'open.cmd'
@(
    '@echo off'
    ('start "" "' + \$edge + '" --ie-mode-force "' + \$url + '"')
) | Set-Content -Path \$cmdPath -Encoding ASCII

# 1) baylan-ie: protokolu
\$base = 'HKCU:\\Software\\Classes\\baylan-ie'
New-Item -Path \$base -Force | Out-Null
Set-ItemProperty -Path \$base -Name '(default)' -Value 'URL:Baylan IE Mode'
New-ItemProperty -Path \$base -Name 'URL Protocol' -Value '' -PropertyType String -Force | Out-Null
\$cmdKey = Join-Path \$base 'shell\\open\\command'
New-Item -Path \$cmdKey -Force | Out-Null
Set-ItemProperty -Path \$cmdKey -Name '(default)' -Value ('"' + \$cmdPath + '" "%1"')

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

Write-Host ''
Write-Host 'Kurulum tamam.' -ForegroundColor Green
Write-Host '1) Chrome u TAMAMEN kapatip yeniden acin'
Write-Host '2) chrome://policy adresinde AutoLaunchProtocolsFromOrigins gorunmeli'
Write-Host "3) Kiosk (\$origin) uzerinden BAYLAN a tiklayin — uyari cikmamali"
Write-Host "Hedef: \$url"
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
        $edge = 'C:\\Program Files (x86)\\Microsoft\\Edge\\Application\\msedge.exe';

        $edgeReg = str_replace('\\', '\\\\', $edge);
        $urlReg = str_replace(['\\', '"'], ['\\\\', '\\"'], $url);
        $command = '\\"'.$edgeReg.'\\" --ie-mode-force \\"'.$urlReg.'\\"';

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
     * "*" = bu kiosk PC'den her origin (IP/hostname fark etmez).
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
