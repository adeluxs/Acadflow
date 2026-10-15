[CmdletBinding()]
param(
    [switch]$Relocated,
    [switch]$PreferSource
)

$ErrorActionPreference = 'Stop'
Set-StrictMode -Version Latest

function Write-Step([string]$Message) {
    Write-Host "`n==> $Message" -ForegroundColor Cyan
}

function Write-Warn([string]$Message) {
    Write-Host "WARNING: $Message" -ForegroundColor Yellow
}

function Fail([string]$Message) {
    Write-Host "ERROR: $Message" -ForegroundColor Red
    exit 1
}

$ProjectRoot = Split-Path -Parent $MyInvocation.MyCommand.Path
Set-Location $ProjectRoot

Write-Host "AcadFlow Windows Composer installer" -ForegroundColor Green
Write-Host "Project: $ProjectRoot"

# Downloads is frequently watched by antivirus/Controlled Folder Access and can
# lock Composer's vendor/composer/tmp-*.zip files. Work from a user-owned project
# directory instead of repeatedly failing in Downloads.
if (-not $Relocated -and $ProjectRoot -match '[\\/]Downloads[\\/]') {
    $SafeRoot = Join-Path $env:USERPROFILE 'Projects\AcadFlow'
    Write-Step "Relocating the project from Downloads to $SafeRoot"

    New-Item -ItemType Directory -Force -Path $SafeRoot | Out-Null

    $robocopy = Get-Command robocopy.exe -ErrorAction SilentlyContinue
    if (-not $robocopy) {
        Fail 'robocopy.exe is unavailable. Move the project manually to %USERPROFILE%\Projects\AcadFlow and run this script again.'
    }

    # /E copies source files; partial dependency folders are deliberately omitted.
    & $robocopy.Source $ProjectRoot $SafeRoot /E /R:2 /W:1 /XD vendor node_modules .git .composer-cache /NFL /NDL /NJH /NJS /NP | Out-Null
    $copyCode = $LASTEXITCODE
    if ($copyCode -ge 8) {
        Fail "Project relocation failed with robocopy exit code $copyCode."
    }

    $RelocatedScript = Join-Path $SafeRoot 'install-windows.ps1'
    if (-not (Test-Path $RelocatedScript)) {
        Fail "The installer was not copied to $RelocatedScript."
    }

    Write-Host "Continuing installation from $SafeRoot" -ForegroundColor Green
    $PowerShellArguments = @('-NoProfile', '-ExecutionPolicy', 'Bypass', '-File', $RelocatedScript, '-Relocated')
    if ($PreferSource) {
        $PowerShellArguments += '-PreferSource'
    }
    & powershell.exe @PowerShellArguments
    exit $LASTEXITCODE
}

Write-Step 'Checking required commands'
$php = Get-Command php.exe -ErrorAction SilentlyContinue
if (-not $php) {
    $php = Get-Command php -ErrorAction SilentlyContinue
}
if (-not $php) {
    Fail 'PHP is not available in PATH.'
}

$composer = Get-Command composer.bat -ErrorAction SilentlyContinue
if (-not $composer) {
    $composer = Get-Command composer.exe -ErrorAction SilentlyContinue
}
if (-not $composer) {
    $composer = Get-Command composer -ErrorAction SilentlyContinue
}
if (-not $composer) {
    Fail 'Composer is not available in PATH. Install Composer for Windows, reopen PowerShell, and run this script again.'
}

& $php.Source -v | Select-Object -First 1
& $composer.Source --version

Write-Step 'Clearing read-only attributes and restoring write permissions'
try {
    Get-ChildItem -LiteralPath $ProjectRoot -Force -Recurse -ErrorAction SilentlyContinue | ForEach-Object {
        if ($_.Attributes -band [IO.FileAttributes]::ReadOnly) {
            $_.Attributes = $_.Attributes -band (-bnot [IO.FileAttributes]::ReadOnly)
        }
    }
} catch {
    Write-Warn "Some read-only attributes could not be cleared: $($_.Exception.Message)"
}

# Grant the current user Modify permission recursively. This normally succeeds
# without elevation because the extracted project belongs to the current user.
& icacls.exe $ProjectRoot /grant:r "$($env:USERNAME):(OI)(CI)M" /T /C /Q | Out-Null
if ($LASTEXITCODE -ne 0) {
    Write-Warn 'Windows did not update every ACL entry. The write test below will confirm whether installation can continue.'
}

Write-Step 'Creating Laravel writable runtime directories'
$LaravelWritableDirectories = @(
    (Join-Path $ProjectRoot 'bootstrap\cache'),
    (Join-Path $ProjectRoot 'storage\framework\cache'),
    (Join-Path $ProjectRoot 'storage\framework\cache\data'),
    (Join-Path $ProjectRoot 'storage\framework\sessions'),
    (Join-Path $ProjectRoot 'storage\framework\testing'),
    (Join-Path $ProjectRoot 'storage\framework\views'),
    (Join-Path $ProjectRoot 'storage\logs')
)

foreach ($Directory in $LaravelWritableDirectories) {
    New-Item -ItemType Directory -Force -Path $Directory | Out-Null

    # Re-apply permissions after creation. This also repairs folders extracted
    # without inherited permissions from ZIP archives.
    & icacls.exe $Directory /inheritance:e /grant:r "$($env:USERNAME):(OI)(CI)M" /T /C /Q | Out-Null
    if ($LASTEXITCODE -ne 0) {
        Write-Warn "Could not update every ACL entry under $Directory. The write test will determine whether installation can continue."
    }

    $DirectoryWriteTest = Join-Path $Directory '.acadflow-write-test'
    try {
        [IO.File]::WriteAllText($DirectoryWriteTest, 'ok')
        Remove-Item -LiteralPath $DirectoryWriteTest -Force
    } catch {
        Fail "Laravel requires $Directory to exist and be writable. Close programs using the project, disable Controlled Folder Access for this project or move it to %USERPROFILE%\Projects\AcadFlow, then retry. Details: $($_.Exception.Message)"
    }
}

Write-Step 'Removing the incomplete vendor directory'
if (Test-Path (Join-Path $ProjectRoot 'vendor')) {
    try {
        Remove-Item -LiteralPath (Join-Path $ProjectRoot 'vendor') -Recurse -Force
    } catch {
        Fail "The vendor directory is locked. Close VS Code, terminals, PHP servers, antivirus scans, and File Explorer windows using this folder, then retry. Details: $($_.Exception.Message)"
    }
}

$ComposerTemp = Join-Path $ProjectRoot 'vendor\composer'
New-Item -ItemType Directory -Force -Path $ComposerTemp | Out-Null
$WriteTest = Join-Path $ComposerTemp '.acadflow-write-test'
try {
    [IO.File]::WriteAllText($WriteTest, 'ok')
    Remove-Item -LiteralPath $WriteTest -Force
} catch {
    Fail "Windows still blocks writes to $ComposerTemp. Add $ProjectRoot to Windows Security > Virus & threat protection > Ransomware protection > Allow an app through Controlled folder access, or move the project to another user-owned folder. Details: $($_.Exception.Message)"
}

Write-Step 'Preparing a writable Composer cache'
$ComposerCache = Join-Path $env:LOCALAPPDATA 'Composer\Cache'
New-Item -ItemType Directory -Force -Path $ComposerCache | Out-Null
$env:COMPOSER_CACHE_DIR = $ComposerCache
$env:COMPOSER_PROCESS_TIMEOUT = '1800'

Write-Step 'Checking Composer configuration'
& $composer.Source diagnose
if ($LASTEXITCODE -ne 0) {
    Write-Warn 'Composer diagnose reported warnings. Installation will still be attempted.'
}

& $composer.Source clear-cache
if ($LASTEXITCODE -ne 0) {
    Write-Warn 'Composer cache could not be fully cleared. Continuing with the new writable cache directory.'
}

function Invoke-ComposerInstall([switch]$UseSource) {
    if ($UseSource) {
        Write-Step 'Installing dependencies from source'
        $git = Get-Command git.exe -ErrorAction SilentlyContinue
        if (-not $git) {
            $git = Get-Command git -ErrorAction SilentlyContinue
        }
        if (-not $git) {
            Fail 'Git is required for --prefer-source but was not found in PATH.'
        }
        & $composer.Source install --prefer-source --no-interaction --no-progress --optimize-autoloader
    } else {
        Write-Step 'Installing dependencies from distribution archives'
        & $composer.Source install --prefer-dist --no-interaction --no-progress --optimize-autoloader
    }
    return $LASTEXITCODE
}

$installCode = Invoke-ComposerInstall -UseSource:$PreferSource
if ($installCode -ne 0 -and -not $PreferSource) {
    Write-Warn 'Distribution installation failed. Retrying from source after cleaning the partial vendor directory.'
    if (Test-Path (Join-Path $ProjectRoot 'vendor')) {
        Remove-Item -LiteralPath (Join-Path $ProjectRoot 'vendor') -Recurse -Force
    }
    New-Item -ItemType Directory -Force -Path $ComposerTemp | Out-Null
    $installCode = Invoke-ComposerInstall -UseSource
}

if ($installCode -ne 0) {
    Fail "Composer installation failed with exit code $installCode. The project is writable, so review the Composer output for a network, PHP-extension, or package-resolution error."
}

Write-Step 'Completing Laravel initialization'
if (-not (Test-Path (Join-Path $ProjectRoot '.env'))) {
    Copy-Item -LiteralPath (Join-Path $ProjectRoot '.env.example') -Destination (Join-Path $ProjectRoot '.env')
}

& $php.Source artisan key:generate --ansi --force
if ($LASTEXITCODE -ne 0) {
    Fail 'Dependencies installed, but Laravel could not generate the application key.'
}

& $php.Source artisan optimize:clear
if ($LASTEXITCODE -ne 0) {
    Write-Warn 'Dependencies installed, but some Laravel caches could not be cleared. Check storage and bootstrap/cache permissions.'
}

Write-Host "`nComposer dependencies installed successfully." -ForegroundColor Green
Write-Host "Project location: $ProjectRoot"
Write-Host 'Next: configure .env, then run php artisan migrate --seed.'
exit 0
