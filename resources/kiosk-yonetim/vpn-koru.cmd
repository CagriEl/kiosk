@echo off
chcp 65001 >nul
title Kirklareli Kiosk - GlobalProtect VPN Koruyucu

REM Tek dosya: kurulum + koruyucu
REM Yonetimden indir → cift tik → UAC Evet
REM Acilista otomatik baslar; 10.0.1.1 yoksa PanGPS yeniden baslatilir.

set "CHECK_HOST=10.0.1.1"
set "INTERVAL=20"
set "FAIL_NEED=2"
set "FAILS=0"
set "DIR=%LOCALAPPDATA%\KioskBekleyen"
set "DST=%DIR%\vpn-koru.cmd"
set "VBS=%DIR%\vpn-koru-gizli.vbs"

if /I "%~1"=="run" goto elevate_ok

REM Ilk calistirma: yonetici yap, kur, sonra koru
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
  echo Set sh = CreateObject("WScript.Shell")
  echo sh.Run "powershell -NoProfile -WindowStyle Hidden -Command ""Start-Process -FilePath '%DST%' -ArgumentList 'run' -Verb RunAs""", 0, False
)

reg add "HKCU\Software\Microsoft\Windows\CurrentVersion\Run" /v "KirklareliVpnKoru" /t REG_SZ /d "wscript.exe //nologo \"%VBS%\"" /f >nul

echo.
echo GlobalProtect VPN koruyucu hazir.
echo  Kurulum  : acilista otomatik
echo  Kontrol  : %CHECK_HOST% her %INTERVAL% sn
echo  Durdurmak: Gorev Yoneticisi'nden bu pencereyi kapatin
echo.

:loop
ping -n 1 -w 1500 %CHECK_HOST% >nul 2>&1
if errorlevel 1 (
  set /a FAILS+=1
  echo [%TIME%] %CHECK_HOST% yanit yok  (%FAILS%/%FAIL_NEED%)
) else (
  if not "%FAILS%"=="0" echo [%TIME%] Baglanti OK.
  set "FAILS=0"
)

if %FAILS% GEQ %FAIL_NEED% (
  echo [%TIME%] GlobalProtect yeniden baglaniliyor (PanGPS)...
  call :reconnect
  set "FAILS=0"
  timeout /t 8 /nobreak >nul
)

timeout /t %INTERVAL% /nobreak >nul
goto loop

:reconnect
sc query PanGPS >nul 2>&1
if errorlevel 1 (
  echo [%TIME%] HATA: PanGPS servisi bulunamadi. GlobalProtect kurulu mu?
  goto :eof
)

net stop PanGPS /y >nul 2>&1
timeout /t 3 /nobreak >nul
net start PanGPS >nul 2>&1
if errorlevel 1 sc start PanGPS >nul 2>&1

if exist "%ProgramFiles%\Palo Alto Networks\GlobalProtect\PanGPA.exe" (
  start "" "%ProgramFiles%\Palo Alto Networks\GlobalProtect\PanGPA.exe"
)

echo [%TIME%] Yeniden baslatma komutu gonderildi.
goto :eof
