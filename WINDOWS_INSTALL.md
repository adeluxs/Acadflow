# AcadFlow Windows installation

Run `FIX_COMPOSER_WINDOWS.bat` from the project root.

The installer creates and verifies all Laravel runtime directories before
Composer runs `artisan package:discover`:

- `bootstrap/cache`
- `storage/framework/cache/data`
- `storage/framework/sessions`
- `storage/framework/testing`
- `storage/framework/views`
- `storage/logs`

## Manual repair for an existing extracted folder

Open PowerShell in the project root and run:

```powershell
$dirs = @(
  'bootstrap/cache',
  'storage/framework/cache/data',
  'storage/framework/sessions',
  'storage/framework/testing',
  'storage/framework/views',
  'storage/logs'
)
foreach ($dir in $dirs) {
  New-Item -ItemType Directory -Force -Path $dir | Out-Null
  icacls $dir /inheritance:e /grant:r "$env:USERNAME`:(OI)(CI)M" /T /C
}
composer install --prefer-dist --no-interaction
php artisan optimize:clear
```

Avoid installing directly inside a protected or synchronized folder. The
included installer relocates projects extracted under `Downloads` to
`%USERPROFILE%\Projects\AcadFlow` automatically.

## Testing after production cache commands

AcadFlow has a large route table. On local Windows PHP installations limited to 128 MB, a production `routes-v7.php` cache can push PHPUnit over its memory limit even though normal uncached tests pass. The test suite now uses isolated test-only route/config/event cache paths, so current builds do not load the production route cache during PHPUnit.

If you are upgrading from an older build and already generated `bootstrap\cache\routes-v7.php`, run `RESET_LOCAL_LARAVEL_CACHES.bat` once, then:

```bat
php artisan optimize:clear
php artisan test
```

Do not treat `php artisan route:cache` as a required local-development step. Use it only for production deployments whose PHP memory limit has been verified to support the compiled route table.
