# Windows Composer installation repair

## Error addressed

```text
vendor/composer/tmp-....zip: Failed to open stream: Permission denied
Source fallback is disabled.
```

This error occurs before Laravel starts. Windows, antivirus software, Controlled Folder Access, a stale partial `vendor` folder, or another running process is preventing Composer from writing its temporary package archive.

Composer 2.10 disables automatic source fallback after a distribution download failure. The source-fallback line is therefore expected after the actual write failure; enabling fallback alone does not repair the blocked directory.

## Recommended installation

1. Extract the ZIP completely. Do not run the project inside the ZIP preview.
2. Double-click `FIX_COMPOSER_WINDOWS.bat`.
3. The installer automatically relocates projects extracted under `Downloads` to:

   ```text
   %USERPROFILE%\Projects\AcadFlow
   ```

4. It clears read-only attributes, restores the current user's Modify permission, removes the incomplete `vendor` folder, verifies `vendor/composer` is writable, clears Composer's cache, and runs `composer install`.
5. If distribution ZIP installation still fails and Git is available, it retries with `--prefer-source`.

## Manual PowerShell repair

From a normal PowerShell window:

```powershell
$source = "$env:USERPROFILE\Downloads\Acadflow-enterprise-regenerated-source\Acadflow-main"
$target = "$env:USERPROFILE\Projects\AcadFlow"
New-Item -ItemType Directory -Force $target | Out-Null
robocopy $source $target /E /XD vendor node_modules .git
Set-Location $target
Get-ChildItem -Force -Recurse | ForEach-Object { $_.IsReadOnly = $false }
icacls . /grant:r "$($env:USERNAME):(OI)(CI)M" /T /C
Remove-Item vendor -Recurse -Force -ErrorAction SilentlyContinue
composer clear-cache
composer install --prefer-dist --no-interaction --optimize-autoloader
```

If the final command is still blocked, close VS Code, `php artisan serve`, File Explorer windows, and antivirus scans using the directory. Then run:

```powershell
Remove-Item vendor -Recurse -Force -ErrorAction SilentlyContinue
composer install --prefer-source --no-interaction --optimize-autoloader
```

The source installation fallback requires Git in `PATH`.

## After Composer succeeds

```powershell
Copy-Item .env.example .env -ErrorAction SilentlyContinue
php artisan key:generate
php artisan optimize:clear
# Configure database values in .env before this command:
php artisan migrate --seed
npm install
npm run build
php artisan serve
```

## Windows Security exception

Only when the script's write test explicitly reports Controlled Folder Access, allow `php.exe` and `composer.exe` through **Windows Security → Virus & threat protection → Ransomware protection → Allow an app through Controlled folder access**. Do not disable antivirus protection globally.
