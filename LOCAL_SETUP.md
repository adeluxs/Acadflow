# AcadFlow local setup notes

This document records the local setup performed for this workspace and the exact reproducible steps.

## Project discovered

- Primary application directory: `acadflow-web`.
- `acadflow-app` is present but empty in this workspace.
- Framework: Laravel 12.x on PHP 8.2 or newer.
- Frontend: Blade, Tailwind CSS 3, Vite 6, Axios, and Vue 3 for selected components.
- Auth/API: Laravel Sanctum.
- PDF tooling: DomPDF and smalot/pdfparser.
- Queue names used by the app: `default`, `ai`, `indexing`, and `analytics`.

## Local machine versions used

- PHP CLI: 8.5.2.
- Composer: 2.8.10.
- Node.js: 22.16.0.
- npm: 10.9.2.
- PHP extensions verified: pdo_mysql, pdo_sqlite, curl, dom, fileinfo, mbstring, openssl, tokenizer, xml, and zip.

## Service requirements

The canonical project documentation recommends MySQL for normal development and production. For this local run, no MySQL service was available, so SQLite was used successfully for a self-contained local setup.

Recommended service setup:

- MySQL or MariaDB for normal local parity with production.
- Redis is optional locally. The template defaults to database queues/cache, and production Redis is recommended when available.
- Mail is safe with `MAIL_MAILER=log` for local development.
- External AI/payment/scholarly/SMS services are optional unless testing those integrations. Their keys can remain blank for basic app startup.

## Important local cleanup performed

The extracted workspace contained many iCloud/duplicate artifact files such as files ending in ` 2.php`, ` 2.js`, ` 2.json`, ` 2.md`, ` 2.txt`, and `.icloud`. These duplicate migration files caused repeated migrations to run and fail on duplicate indexes.

For this local setup, those artifacts were moved out of the active app tree into:

- `_local_setup_quarantine/icloud_duplicates_20261012164335`

The failed partial SQLite database was moved to:

- `database/database.sqlite.failed_duplicate_migrations_20261012164337`

A fresh `database/database.sqlite` was then created.

## Environment values used locally

Start from `.env.example`, then set these local overrides:

```env
APP_URL=http://127.0.0.1:8001
APP_MAINTENANCE_DRIVER=file
APP_MAINTENANCE_STORE=file
LOG_STACK=single
AUTH_GUARD=web
AUTH_MODEL=App\Models\User
AUTH_PASSWORD_BROKER=users
AUTH_PASSWORD_RESET_TOKEN_TABLE=password_reset_tokens
AUTH_PASSWORD_TIMEOUT=10800
DB_CONNECTION=sqlite
DB_DATABASE=database/database.sqlite
DB_HOST=
DB_PORT=
DB_USERNAME=
DB_PASSWORD=
CACHE_STORE=file
QUEUE_CONNECTION=sync
AI_QUEUE_CONNECTION=sync
SESSION_DRIVER=file
MAIL_MAILER=log
```

Notes:

- `APP_MAINTENANCE_DRIVER=file`, `LOG_STACK=single`, and `AUTH_GUARD=web` are required because blank values in the template can override Laravel defaults and produce runtime errors.
- `QUEUE_CONNECTION=sync`, `CACHE_STORE=file`, and `SESSION_DRIVER=file` avoid requiring database-backed runtime tables or Redis during a minimal local boot.
- The app originally attempted port 8000, but that port was already in use, so port 8001 was used.

## Exact setup commands from a clean extracted copy

Run these from the repository root, then work inside `acadflow-web`.

```bash
cd acadflow-web
composer install --prefer-dist --no-interaction
npm install
cp .env.example .env
mkdir -p bootstrap/cache storage/framework/cache/data storage/framework/sessions storage/framework/testing storage/framework/views storage/logs
php artisan key:generate --ansi
php artisan storage:link --ansi
```

If the extracted copy contains iCloud duplicate artifacts, quarantine them before migrating:

```bash
python3 - <<'PY'
from pathlib import Path
import shutil
from datetime import datetime
root = Path('.')
quarantine = root / '_local_setup_quarantine' / ('icloud_duplicates_' + datetime.utcnow().strftime('%Y%m%d%H%M%S'))
patterns = ['* 2.php','* 2.js','* 2.json','* 2.md','* 2.txt','*.icloud']
files = []
for pat in patterns:
    files.extend([p for p in root.rglob(pat) if '_local_setup_quarantine' not in p.parts and p.is_file()])
for p in sorted(set(files)):
    target = quarantine / p
    target.parent.mkdir(parents=True, exist_ok=True)
    shutil.move(str(p), str(target))
print(f'Moved {len(set(files))} duplicate/iCloud artifact files to {quarantine}')
PY
```

Configure the local SQLite/file-based environment:

```bash
perl -0pi -e 's/^DB_CONNECTION=mysql$/DB_CONNECTION=sqlite/m; s/^DB_DATABASE=acadflow$/DB_DATABASE=database\/database.sqlite/m; s/^DB_HOST=127\.0\.0\.1$/DB_HOST=/m; s/^DB_PORT=3306$/DB_PORT=/m; s/^DB_USERNAME=root$/DB_USERNAME=/m; s/^CACHE_STORE=database$/CACHE_STORE=file/m; s/^QUEUE_CONNECTION=database$/QUEUE_CONNECTION=sync/m; s/^AI_QUEUE_CONNECTION=database$/AI_QUEUE_CONNECTION=sync/m; s/^SESSION_DRIVER=database$/SESSION_DRIVER=file/m; s#^APP_URL=http://localhost$#APP_URL=http://127.0.0.1:8001#m; s/^LOG_STACK=$/LOG_STACK=single/m; s/^APP_MAINTENANCE_DRIVER=$/APP_MAINTENANCE_DRIVER=file/m; s/^APP_MAINTENANCE_STORE=$/APP_MAINTENANCE_STORE=file/m; s/^AUTH_GUARD=$/AUTH_GUARD=web/m; s/^AUTH_MODEL=$/AUTH_MODEL=App\\Models\\User/m; s/^AUTH_PASSWORD_BROKER=$/AUTH_PASSWORD_BROKER=users/m; s/^AUTH_PASSWORD_RESET_TOKEN_TABLE=$/AUTH_PASSWORD_RESET_TOKEN_TABLE=password_reset_tokens/m; s/^AUTH_PASSWORD_TIMEOUT=$/AUTH_PASSWORD_TIMEOUT=10800/m' .env
```

Initialize the local database and clear cached configuration:

```bash
touch database/database.sqlite
php artisan optimize:clear --ansi
php artisan migrate --force --ansi
php artisan db:seed --force --ansi
npm run build
```

Start the app:

```bash
php artisan serve --host=127.0.0.1 --port=8001
```

Then open:

```text
http://127.0.0.1:8001/
```

## Verification results

The following URLs were verified after setup:

```text
GET /         -> 200 text/html
GET /login    -> 200 text/html
GET /register -> 200 text/html
```

The home page returned title:

```text
AcadFlow — Academic Workflow Platform
```

Frontend production build also completed successfully with Vite.

Browser automation was attempted, but the bundled Puppeteer Chromium could not launch on macOS Big Sur because it referenced the missing system framework `LocalAuthenticationEmbeddedUI.framework`. Command-line HTTP verification was therefore used instead.

## Seeded demo access

The default seeder creates demo users with password `password123` unless `ACADEMIC_DEMO_PASSWORD` is set before seeding. Useful seeded accounts include:

- `admin@uniacademic.com`
- `universityadmin@futo.edu.ng`
- `deptadmin@futo.edu.ng`
- `dr.ibrahim@futo.edu.ng`
- `prof.adeyemi@futo.edu.ng`
- `student001@student.futo.edu.ng`
- `student002@student.futo.edu.ng`
- `student003@student.futo.edu.ng`

## Production or MySQL-parity setup

For MySQL parity, use the project defaults instead of the SQLite overrides:

```env
DB_CONNECTION=mysql
DB_DATABASE=acadflow
DB_HOST=127.0.0.1
DB_PORT=3306
DB_USERNAME=root
DB_PASSWORD=
CACHE_STORE=database
QUEUE_CONNECTION=database
SESSION_DRIVER=database
```

Create the MySQL database first, then run:

```bash
php artisan optimize:clear
php artisan migrate
php artisan db:seed
composer dev
```

The `composer dev` script starts the Laravel dev server, queue worker, Laravel Pail logs, and Vite concurrently. With the minimal SQLite setup above, running `php artisan serve --host=127.0.0.1 --port=8001` is enough because assets were built with `npm run build` and queues are synchronous.
