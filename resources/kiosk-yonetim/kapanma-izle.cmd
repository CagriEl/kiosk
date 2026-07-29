@echo off
chcp 65001 >nul
title Kirklareli Kiosk - Kapanma Izleyici

REM ============================================================
REM  Cihaz kapanma / yeniden baslatma nedenini loglar,
REM  Telegram'a bildirim gonderir.
REM  Tek dosya: kurulum + izleyici.
REM  Yonetimden indir → cift tik → UAC Evet
REM
REM  AYARLAR:
REM ============================================================

REM Telegram bot token (BotFather'dan alin)
set "TG_BOT_TOKEN=BOT_TOKEN_BURAYA"

REM Telegram chat ID (sizin veya grubun)
set "TG_CHAT_ID=CHAT_ID_BURAYA"

REM Kiosk sunucu heartbeat adresi
set "HEARTBEAT_URL=http://10.0.1.1/kiosk/public/api/kiosk/heartbeat"

REM Kac saniyede bir heartbeat
set "HB_INTERVAL=60"

REM ============================================================

set "DIR=%LOCALAPPDATA%\KioskBekleyen"
set "DST=%DIR%\kapanma-izle.cmd"
set "LOG=%DIR%\kapanma-log.txt"
set "VBS=%DIR%\kapanma-izle-gizli.vbs"

if /I "%~1"=="run" goto elevate_ok

powershell -NoProfile -ExecutionPolicy Bypass -Command "Start-Process -FilePath '%~f0' -ArgumentList 'run' -Verb RunAs"
exit /b

:elevate_ok
net session >nul 2>&1
if errorlevel 1 (
  echo HATA: Yonetici yetkisi yok.
  pause
  exit /b 1
)

mkdir "%DIR%" 2>nul
copy /Y "%~f0" "%DST%" >nul

> "%VBS%" (
  echo Set sh = CreateObject("WScript.Shell"^)
  echo sh.Run "powershell -NoProfile -WindowStyle Hidden -Command ""Start-Process -FilePath '%DST%' -ArgumentList 'run' -Verb RunAs""", 0, False
)

reg add "HKCU\Software\Microsoft\Windows\CurrentVersion\Run" /v "KirklareliKapanmaIzle" /t REG_SZ /d "wscript.exe //nologo ""%VBS%""" /f >nul

echo.
echo Kapanma izleyici kuruldu.
echo.

REM Son kapanma nedenini kontrol et ve bildir
call :checkLastShutdown
call :heartbeatLoop

exit /b 0

:checkLastShutdown
echo [%DATE% %TIME%] Son kapanma nedeni kontrol ediliyor...

REM Windows Event Log'dan son kapanma/yeniden baslatma olaylarini cek
REM Event ID 41  = Beklenmeyen kapanma (Kernel-Power)
REM Event ID 1074 = Planli kapanma/yeniden baslatma
REM Event ID 6006 = Event Log servisi durduruldu (temiz kapanma)
REM Event ID 6008 = Onceki kapanis beklenmedikti

powershell -NoProfile -ExecutionPolicy Bypass -Command ^
  "$hostname = $env:COMPUTERNAME;" ^
  "$now = Get-Date -Format 'yyyy-MM-dd HH:mm:ss';" ^
  "$token = '%TG_BOT_TOKEN%';" ^
  "$chatId = '%TG_CHAT_ID%';" ^
  "$logFile = '%LOG%';" ^
  "" ^
  "if ($token -eq 'BOT_TOKEN_BURAYA' -or $chatId -eq 'CHAT_ID_BURAYA') {" ^
  "  Write-Host 'UYARI: Telegram ayarlari yapilmamis. Once TG_BOT_TOKEN ve TG_CHAT_ID ayarlayin.';" ^
  "  return;" ^
  "}" ^
  "" ^
  "$events = @();" ^
  "try {" ^
  "  $events += Get-WinEvent -FilterHashtable @{LogName='System'; ProviderName='Microsoft-Windows-Kernel-Power'; Id=41; StartTime=(Get-Date).AddHours(-2)} -MaxEvents 3 -EA SilentlyContinue;" ^
  "} catch {}" ^
  "try {" ^
  "  $events += Get-WinEvent -FilterHashtable @{LogName='System'; ProviderName='EventLog'; Id=6008; StartTime=(Get-Date).AddHours(-2)} -MaxEvents 3 -EA SilentlyContinue;" ^
  "} catch {}" ^
  "try {" ^
  "  $events += Get-WinEvent -FilterHashtable @{LogName='System'; ProviderName='User32'; Id=1074; StartTime=(Get-Date).AddHours(-2)} -MaxEvents 3 -EA SilentlyContinue;" ^
  "} catch {}" ^
  "" ^
  "$events = $events | Sort-Object TimeCreated -Descending | Select-Object -First 5;" ^
  "" ^
  "if ($events.Count -eq 0) {" ^
  "  $msg = [char]0x2705 + ' Kiosk ($hostname) normal acildi. Son kapanma olaysiz.';" ^
  "  $line = $now + ' | NORMAL_BOOT | Olay yok';" ^
  "} else {" ^
  "  $detail = '';" ^
  "  foreach ($ev in $events) {" ^
  "    $id = $ev.Id;" ^
  "    $t = $ev.TimeCreated.ToString('dd.MM.yyyy HH:mm:ss');" ^
  "    $m = $ev.Message -replace '`r`n',' ' -replace '`n',' ';" ^
  "    if ($m.Length -gt 200) { $m = $m.Substring(0,200) + '...' }" ^
  "    $tag = switch ($id) { 41 {'BEKLENMEYEN KAPANMA'} 6008 {'ONCEKI KAPANIS ANORMAL'} 1074 {'PLANLI KAPANMA/RESTART'} default {$id} };" ^
  "    $detail += \"`n$tag ($t): $m\";" ^
  "  }" ^
  "  $emoji = [char]0x1F534;" ^
  "  $msg = $emoji + ' Kiosk ($hostname) KAPANMA TESPIT EDILDI!' + $detail;" ^
  "  $line = $now + ' | SHUTDOWN | ' + ($events[0].Id) + ' | ' + $events[0].TimeCreated.ToString('dd.MM.yyyy HH:mm:ss');" ^
  "}" ^
  "" ^
  "Add-Content -Path $logFile -Value $line -Encoding UTF8;" ^
  "" ^
  "$body = @{chat_id=$chatId; text=$msg; parse_mode='HTML'} | ConvertTo-Json -Compress;" ^
  "try {" ^
  "  Invoke-RestMethod -Uri ('https://api.telegram.org/bot' + $token + '/sendMessage') -Method Post -ContentType 'application/json; charset=utf-8' -Body ([System.Text.Encoding]::UTF8.GetBytes($body)) -EA Stop | Out-Null;" ^
  "  Write-Host 'Telegram bildirim gonderildi.';" ^
  "} catch {" ^
  "  Write-Host ('Telegram gonderilemedi: ' + $_.Exception.Message);" ^
  "}"

goto :eof

:heartbeatLoop
echo [%DATE% %TIME%] Heartbeat dongusu basliyor (her %HB_INTERVAL% sn)...

:hbLoop
powershell -NoProfile -ExecutionPolicy Bypass -Command ^
  "$token = '%TG_BOT_TOKEN%';" ^
  "$chatId = '%TG_CHAT_ID%';" ^
  "$hostname = $env:COMPUTERNAME;" ^
  "" ^
  "try {" ^
  "  $r = Invoke-WebRequest -Uri '%HEARTBEAT_URL%?kiosk_id=kiosk-1' -Method GET -TimeoutSec 10 -UseBasicParsing -EA Stop;" ^
  "} catch {" ^
  "  # Sunucu erisilemediyse sessiz kal (VPN kopuk olabilir)" ^
  "}"

timeout /t %HB_INTERVAL% /nobreak >nul
goto hbLoop
