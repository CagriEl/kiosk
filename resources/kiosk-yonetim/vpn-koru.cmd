@echo off
chcp 65001 >nul
title Kirklareli Kiosk - GlobalProtect VPN Koruyucu

REM ============================================================
REM  Palo Alto GlobalProtect — kopunca yeniden baglanmayi dener.
REM  http://10.0.1.1/kiosk/public/baylan-ie/vpn-koru.cmd
REM
REM  Yontem: 10.0.1.1 yanit vermezse PanGPS servisini yeniden baslatir.
REM  (Kayitli portal / kullanici ile GlobalProtect genelde otomatik baglanir.)
REM ============================================================

set "CHECK_HOST=10.0.1.1"
set "INTERVAL=20"
set "FAIL_NEED=2"
set "FAILS=0"

REM Yonetici degilse kendini yukselt (servis restart icin gerekli)
net session >nul 2>&1
if errorlevel 1 (
  powershell -NoProfile -ExecutionPolicy Bypass -Command "Start-Process -FilePath '%~f0' -Verb RunAs"
  exit /b
)

echo GlobalProtect VPN koruyucu basladi.
echo  Kontrol : %CHECK_HOST% her %INTERVAL% sn
echo  Durdurmak icin bu pencereyi kapatin.
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
REM Servis adi genelde PanGPS
sc query PanGPS >nul 2>&1
if errorlevel 1 (
  echo [%TIME%] HATA: PanGPS servisi bulunamadi. GlobalProtect kurulu mu?
  goto :eof
)

net stop PanGPS /y >nul 2>&1
timeout /t 3 /nobreak >nul
net start PanGPS >nul 2>&1
if errorlevel 1 (
  echo [%TIME%] PanGPS start basarisiz — sc ile deneniyor...
  sc start PanGPS >nul 2>&1
)

REM Arayuz aciksa bir kez tetikle (kuruluysa)
if exist "%ProgramFiles%\Palo Alto Networks\GlobalProtect\PanGPA.exe" (
  start "" "%ProgramFiles%\Palo Alto Networks\GlobalProtect\PanGPA.exe"
)

echo [%TIME%] Yeniden baslatma komutu gonderildi.
goto :eof
