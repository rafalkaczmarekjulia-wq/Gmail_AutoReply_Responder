# Windows quick start

PowerShell blocks scripts by default on many PCs. Use these commands exactly.

## Install (one time)

**Option A — CMD (easiest):**

```cmd
cd D:\gmail-auto-responder
install.bat
```

**Option B — PowerShell (note the `.\` prefix):**

```powershell
cd D:\gmail-auto-responder
.\install.bat
```

**Option C — manual (if scripts still fail):**

```powershell
cd D:\gmail-auto-responder\backend
php composer.phar install

cd ..\frontend
& "C:\Program Files\nodejs\npm.cmd" install
```

## Run the app (3 terminals)

Double-click these files in `D:\gmail-auto-responder\`:

| File | What it does |
|------|----------------|
| `run-backend.bat` | API + auto Gmail poll (scheduler in background) |
| `run-scheduler.bat` | Optional — only if backend started without scheduler |
| `run-queue.bat` | Background job worker |
| `run-frontend.bat` | UI → http://localhost:3000 |

Or in **CMD**:

```cmd
cd D:\gmail-auto-responder
run-backend.bat
run-queue.bat
run-frontend.bat
```

## If `npm` fails in PowerShell

PowerShell runs `npm.ps1`, which is blocked. Use **npm.cmd** instead:

```powershell
& "C:\Program Files\nodejs\npm.cmd" install
& "C:\Program Files\nodejs\npm.cmd" run dev
```

Or switch terminal to **Command Prompt** (not PowerShell).

## Database setup

**Easiest (no Docker/MySQL):** double-click or run:

```cmd
cd D:\gmail-auto-responder
setup-database.bat
```

This uses **SQLite** — a single file at `backend\database\database.sqlite`. No server to install.

**View/edit data:** install [DB Browser for SQLite](https://sqlitebrowser.org/) and open that file.

**Common commands** (from `backend` folder):

```cmd
php artisan migrate          REM create/update tables
php artisan migrate:status   REM see which migrations ran
php artisan migrate:fresh    REM wipe and recreate all tables
```

**If you later use MySQL + Docker**, change `backend\.env` back to:

```env
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=gmail_responder
DB_USERNAME=gmail
DB_PASSWORD=secret
QUEUE_CONNECTION=redis
CACHE_STORE=redis
```

Then run `docker compose up -d` and `php artisan migrate`.

## Gmail connect: `cURL error 60` (SSL certificate)

If Connect Gmail fails after Google approval with **SSL certificate problem** / `oauth2.googleapis.com/token`:

```cmd
cd D:\gmail-auto-responder
fix-php-ssl.bat
```

Then **restart** `run-backend.bat` and try **Connect Gmail** again.

The project also ships `backend\cacert.pem` and uses it for Google API calls automatically.

---

Run once in PowerShell:

```powershell
Set-ExecutionPolicy -ExecutionPolicy RemoteSigned -Scope CurrentUser
```

Then `npm install` and `.\install.ps1` will work normally.
