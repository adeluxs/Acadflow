@echo off
setlocal
cd /d "%~dp0"

echo Clearing generated Laravel bootstrap caches without booting the framework...
for %%F in (bootstrap\cache\routes-*.php bootstrap\cache\config.php bootstrap\cache\events.php) do (
    if exist "%%F" (
        del /F /Q "%%F"
        if errorlevel 1 (
            echo Failed to delete %%F
            exit /b 1
        )
    )
)

echo Generated bootstrap caches cleared.
echo You can now run: php artisan optimize:clear
exit /b 0
