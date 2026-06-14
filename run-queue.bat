@echo off
cd /d "%~dp0backend"
echo Starting queue worker...
php artisan queue:work redis --queue=gmail-sync,ai
