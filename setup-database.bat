@echo off
cd /d "%~dp0backend"

if not exist database\database.sqlite (
    echo Creating SQLite database...
    type nul > database\database.sqlite
)

echo Running migrations...
php artisan migrate --force

echo.
echo Database ready!
echo   File: backend\database\database.sqlite
echo   View with: DB Browser for SQLite (https://sqlitebrowser.org/)
pause
