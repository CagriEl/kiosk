@echo off
chcp 65001 >nul
title Kirklareli Kiosk - GlobalProtect Koruyucu Kurulum

REM vpn-koru.cmd ile ayni klasorde calistirin.
REM Acilista yonetici olarak sessiz baslar.

set "SRC=%~dp0vpn-koru.cmd"
set "DIR=%LOCALAPPDATA%\KioskBekleyen"
set "DST=%DIR%\vpn-koru.cmd"

if not exist "%SRC%" (
  echo HATA: vpn-koru.cmd bulunamadi.
  pause
  exit /b 1
)

mkdir "%DIR%" 2>nul
copy /Y "%SRC%" "%DST%" >nul

> "%DIR%\vpn-koru-gizli.vbs" (
  echo Set sh = CreateObject("WScript.Shell")
  echo sh.Run "powershell -NoProfile -WindowStyle Hidden -Command ""Start-Process -FilePath '%DST%' -Verb RunAs""", 0, False
)

reg add "HKCU\Software\Microsoft\Windows\CurrentVersion\Run" /v "KirklareliVpnKoru" /t REG_SZ /d "wscript.exe //nologo \"%DIR%\vpn-koru-gizli.vbs\"" /f >nul

echo.
echo Kurulum OK — Palo Alto GlobalProtect.
echo Acilista VPN koruyucu yonetici olarak baslar.
echo 10.0.1.1 yanit vermezse PanGPS servisini yeniden baslatir.
echo.
echo Simdi baslatilsin mi?
pause
wscript.exe //nologo "%DIR%\vpn-koru-gizli.vbs"
echo Calisiyor. (UAC Evet diyin)
timeout /t 3 >nul
exit /b 0
