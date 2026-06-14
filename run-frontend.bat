@echo off
cd /d "%~dp0frontend"
echo Starting Next.js on http://localhost:3000
call "C:\Program Files\nodejs\npm.cmd" run dev
