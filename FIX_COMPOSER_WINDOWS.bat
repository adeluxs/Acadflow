@echo off
setlocal
cd /d "%~dp0"
echo Starting the AcadFlow Windows Composer repair installer...
powershell.exe -NoProfile -ExecutionPolicy Bypass -File "%~dp0install-windows.ps1"
set EXIT_CODE=%ERRORLEVEL%
echo.
if not "%EXIT_CODE%"=="0" (
  echo Installation did not complete. Review the error above.
) else (
  echo Installation completed successfully.
)
pause
exit /b %EXIT_CODE%
