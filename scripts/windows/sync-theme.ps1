# Sync plugin + theme files to Moodle + purge caches.
#
# Usage: .\scripts\windows\sync-theme.ps1

$ErrorActionPreference = "Stop"
$REPO = Split-Path -Parent (Split-Path -Parent (Split-Path -Parent $MyInvocation.MyCommand.Path))

# --- Load .env ---
$envFile = Join-Path $REPO ".env"
if (-not (Test-Path $envFile)) {
    Write-Host "ERROR: .env file not found. Run .\scripts\windows\setup.ps1 first." -ForegroundColor Red
    exit 1
}

Get-Content $envFile | ForEach-Object {
    if ($_ -match '^\s*([^#][^=]+)=(.+)$') {
        Set-Variable -Name $matches[1].Trim() -Value $matches[2].Trim() -Scope Script
    }
}

if (-not $MOODLE_PATH) {
    Write-Host "ERROR: MOODLE_PATH is not set in .env" -ForegroundColor Red
    exit 1
}

$PLUGIN_DEST = Join-Path $MOODLE_PATH "local\sm_graphics_plugin"
$THEME_DEST = Join-Path $MOODLE_PATH "theme\smartmind"
$themeDir = Join-Path $REPO "theme_smartmind"

# --- Build AMD JS (copy src → build as .min.js) ---
$amdSrc = Join-Path $REPO "amd\src"
$amdBuild = Join-Path $REPO "amd\build"
if (Test-Path $amdSrc) {
    if (-not (Test-Path $amdBuild)) { New-Item -ItemType Directory -Path $amdBuild | Out-Null }
    Get-ChildItem "$amdSrc\*.js" | ForEach-Object {
        $dest = Join-Path $amdBuild "$($_.BaseName).min.js"
        Copy-Item $_.FullName $dest
    }
}

# --- Copy plugin files ---
Write-Host "Syncing plugin..."
if (-not (Test-Path $PLUGIN_DEST)) { New-Item -ItemType Directory -Path $PLUGIN_DEST | Out-Null }
foreach ($file in @("version.php", "lib.php", "settings.php", "update.xml")) {
    $src = Join-Path $REPO $file
    if (Test-Path $src) { Copy-Item $src $PLUGIN_DEST -Force }
}
foreach ($dir in @("db", "pages", "templates", "lang", "classes", "amd", "certificate_type")) {
    $src = Join-Path $REPO $dir
    if (Test-Path $src) { Copy-Item $src (Join-Path $PLUGIN_DEST $dir) -Recurse -Force }
}
# Copy update.php for auto-update feature.
$updateSrc = Join-Path $REPO "update.php"
if (Test-Path $updateSrc) { Copy-Item $updateSrc $PLUGIN_DEST -Force }

# --- Copy theme files ---
Write-Host "Syncing theme..."
if (-not (Test-Path $THEME_DEST)) { New-Item -ItemType Directory -Path $THEME_DEST | Out-Null }
Get-ChildItem $themeDir -Exclude ".git", ".idea", "dump.html" | Copy-Item -Destination $THEME_DEST -Recurse -Force

# --- Deploy lang overrides + purge caches ---
Write-Host "Deploying lang overrides and purging caches..."
$phpPath = Join-Path $MOODLE_PATH "..\php\php.exe"
$configPath = Join-Path $MOODLE_PATH "config.php"
$installPath = Join-Path $MOODLE_PATH "local\sm_graphics_plugin\db\install.php"
$phpCode = "define('CLI_SCRIPT',true); require('$configPath'); require_once('$installPath'); local_sm_graphics_plugin_deploy_lang_overrides(); purge_all_caches();"
if (Test-Path $phpPath) {
    & $phpPath -r $phpCode
} elseif (Get-Command php -ErrorAction SilentlyContinue) {
    php -r $phpCode
} else {
    Write-Host "WARNING: Could not find PHP. Purge caches manually via Moodle admin." -ForegroundColor Yellow
}

Write-Host ""
Write-Host "Done!"
