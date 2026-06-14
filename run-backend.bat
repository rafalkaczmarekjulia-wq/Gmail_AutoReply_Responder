@echo off

cd /d "%~dp0backend"

echo Running database migrations...

php artisan migrate --force

echo Starting Laravel API on http://localhost:8000

echo Starting auto Gmail poll (every 1 min) in background...

start "Gmail Scheduler" /MIN cmd /c "cd /d %~dp0backend && php artisan schedule:work"

php artisan serve

