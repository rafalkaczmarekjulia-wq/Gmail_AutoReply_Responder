@echo off
cd /d "%~dp0backend"
echo Starting Laravel scheduler (auto Gmail poll every minute when Pub/Sub is off)...
php artisan schedule:work
