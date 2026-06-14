# Install all dependencies for Gmail Auto-Responder (Windows)

Write-Host "Installing backend (Composer)..." -ForegroundColor Cyan
Set-Location "$PSScriptRoot\backend"

if (-not (Test-Path "composer.phar")) {
    Write-Host "Downloading Composer..."
    php -r "copy('https://getcomposer.org/installer', 'composer-setup.php');"
    php composer-setup.php
    php -r "unlink('composer-setup.php');"
}

php composer.phar install --no-interaction

if (-not (Test-Path ".env")) {
    Copy-Item .env.example .env
    php artisan key:generate --force
}

Write-Host "Installing frontend (npm)..." -ForegroundColor Cyan
Set-Location "$PSScriptRoot\frontend"
npm install

if (-not (Test-Path ".env.local")) {
    Copy-Item .env.local.example .env.local
}

Set-Location $PSScriptRoot
Write-Host ""
Write-Host "Done! Next steps:" -ForegroundColor Green
Write-Host "  1. docker compose up -d          # MySQL + Redis"
Write-Host "  2. cd backend && php artisan migrate"
Write-Host "  3. php artisan serve             # API on :8000"
Write-Host "  4. php artisan queue:work redis --queue=gmail-sync,ai"
Write-Host "  5. cd frontend && npm run dev    # UI on :3000"
