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
