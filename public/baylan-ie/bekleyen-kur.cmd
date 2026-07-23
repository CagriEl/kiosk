@echo off
chcp 65001 >nul
title Kirklareli Kiosk - Bekleyen Kurulum

REM VPN yokken yerel bekleyen sayfa.
REM http://10.0.1.1/kiosk/public/baylan-ie/bekleyen-kur.cmd
REM Ayni klasorde bekleyen.html olmali.

set "SRC_HTML=%~dp0bekleyen.html"
set "DIR=%LOCALAPPDATA%\KioskBekleyen"
set "DST_HTML=%DIR%\bekleyen.html"
set "BASLAT=%DIR%\baslat.cmd"
set "DESKTOP=%USERPROFILE%\Desktop"

if not exist "%SRC_HTML%" (
  echo HATA: bekleyen.html bulunamadi.
  echo Once bekleyen.html dosyasini bu CMD ile ayni klasore indirin.
  pause
  exit /b 1
)

mkdir "%DIR%" 2>nul
copy /Y "%SRC_HTML%" "%DST_HTML%" >nul

set "CHROME="
if exist "%ProgramFiles%\Google\Chrome\Application\chrome.exe" set "CHROME=%ProgramFiles%\Google\Chrome\Application\chrome.exe"
if not defined CHROME if exist "%ProgramFiles(x86)%\Google\Chrome\Application\chrome.exe" set "CHROME=%ProgramFiles(x86)%\Google\Chrome\Application\chrome.exe"
if not defined CHROME (
  echo HATA: Google Chrome bulunamadi.
  pause
  exit /b 1
)

for /f "usebackq delims=" %%I in (`powershell -NoProfile -Command "[uri]::new($env:DST_HTML).AbsoluteUri"`) do set "FILE_URL=%%I"
if not defined FILE_URL (
  rem DST_HTML env alternatif
  for /f "usebackq delims=" %%I in (`powershell -NoProfile -Command "[uri]::new('%DST_HTML%').AbsoluteUri"`) do set "FILE_URL=%%I"
)

> "%BASLAT%" (
  echo @echo off
  echo chcp 65001 ^>nul
  echo title Kirklareli Kiosk
  echo set "CHROME=%CHROME%"
  echo set "PROFILE=%DIR%\ChromeProfile"
  echo set "URL=%FILE_URL%"
  echo.
  echo powershell -NoProfile -Command "try { $p=Get-CimInstance Win32_Process -Filter \"Name='chrome.exe'\" -EA SilentlyContinue ^| ? { $_.CommandLine -like '*KioskBekleyen*ChromeProfile*' }; if ($p) { exit 2 } else { exit 0 } } catch { exit 0 }"
  echo if errorlevel 2 ^(
  echo   echo Kiosk zaten calisiyor.
  echo   exit /b 0
  echo ^)
  echo.
  echo start "" "%%CHROME%%" --user-data-dir="%%PROFILE%%" --no-first-run --disable-session-crashed-bubble --disable-pinch --overscroll-history-navigation=0 --kiosk "%%URL%%"
)

powershell -NoProfile -ExecutionPolicy Bypass -Command "$ws=New-Object -ComObject WScript.Shell; $s=$ws.CreateShortcut([Environment]::GetFolderPath('Desktop') + '\Kiosk.lnk'); $s.TargetPath='%BASLAT%'; $s.WorkingDirectory='%DIR%'; $s.WindowStyle=7; $s.Description='Kirklareli Kiosk'; $s.Save()"

reg add "HKCU\Software\Microsoft\Windows\CurrentVersion\Run" /v "KirklareliKiosk" /t REG_SZ /d "\"%BASLAT%\"" /f >nul

echo.
echo Kurulum OK.
echo.
echo  Yerel sayfa : %DST_HTML%
echo  Baslat      : %BASLAT%
echo  Masaustu    : Kiosk.lnk
echo.
echo VPN yokken bekleyen ekran / gelince ayni pencerede kiosk.
echo.
pause
call "%BASLAT%"
exit /b 0
