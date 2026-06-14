@echo off
setlocal
cd /d "%~dp0"

echo Installing backend (Composer)...
cd backend
if not exist composer.phar (
    echo Downloading Composer...
    php -r "copy('https://getcomposer.org/installer', 'composer-setup.php');"
    php composer-setup.php
    del composer-setup.php
)
php composer.phar install --no-interaction
if not exist .env copy .env.example .env
php artisan key:generate --force

echo.
echo Installing frontend (npm)...
cd ..\frontend
call "C:\Program Files\nodejs\npm.cmd" install
if not exist .env.local copy .env.local.example .env.local

cd ..
echo.
echo Done! Run the app with:
echo   run-backend.bat
echo   run-frontend.bat
pause
