@echo off
cd /d "%~dp0backend"

if not exist cacert.pem (
    echo Downloading CA certificate bundle...
    curl.exe -s -o cacert.pem https://curl.se/ca/cacert.pem
    if errorlevel 1 (
        echo Failed to download cacert.pem
        pause
        exit /b 1
    )
)

for /f "delims=" %%i in ('php --ini ^| findstr /i "Loaded Configuration File"') do set PHPINI=%%i
set PHPINI=%PHPINI:Loaded Configuration File:         =%

echo PHP ini: %PHPINI%
echo.
echo Gmail OAuth needs SSL certificates. Updating php.ini...
echo Also fixed in code via backend\cacert.pem — restart run-backend.bat after this.
echo.

powershell -NoProfile -Command "$ini='%PHPINI%'; $cert=(Resolve-Path 'cacert.pem').Path.Replace('\','/'); $c=Get-Content $ini -Raw; $c=$c -replace ';curl.cainfo\s*=.*','curl.cainfo = \"'+$cert+'\"'; $c=$c -replace ';openssl.cafile=.*','openssl.cafile=\"'+$cert+'\"'; if($c -notmatch 'curl.cainfo'){ $c+=\"`ncurl.cainfo = `\"$cert`\"`n\" }; Set-Content $ini $c -NoNewline"

echo Done. Restart run-backend.bat and try Connect Gmail again.
pause
