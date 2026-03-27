# SmartMind Plugin — Development Setup (PowerShell)
#
# Deploys plugin + theme to Moodle and runs upgrade.
#
# Prerequisites:
#   1. Copy .env.example to .env and set MOODLE_PATH
#   2. Run: .\scripts\windows\setup.ps1

$ErrorActionPreference = "Stop"
$REPO = Split-Path -Parent (Split-Path -Parent (Split-Path -Parent $MyInvocation.MyCommand.Path))

# --- Load .env ---
$envFile = Join-Path $REPO ".env"
if (-not (Test-Path $envFile)) {
    Write-Host "ERROR: .env file not found." -ForegroundColor Red
    Write-Host ""
    Write-Host "Copy .env.example to .env and set MOODLE_PATH:"
    Write-Host "  Copy-Item .env.example .env"
    Write-Host "  notepad .env"
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

if (-not (Test-Path (Join-Path $MOODLE_PATH "admin"))) {
    Write-Host "ERROR: $MOODLE_PATH does not look like a Moodle installation." -ForegroundColor Red
    Write-Host "Make sure MOODLE_PATH points to the Moodle root (containing admin\, theme\, local\)."
    exit 1
}

$PLUGIN_DEST = Join-Path $MOODLE_PATH "local\sm_graphics_plugin"
$THEME_DEST = Join-Path $MOODLE_PATH "theme\smartmind"

$themeDir = Join-Path $REPO "theme_smartmind"

# --- Copy plugin files ---
Write-Host ""
Write-Host "Copying plugin to $PLUGIN_DEST..."
if (-not (Test-Path $PLUGIN_DEST)) { New-Item -ItemType Directory -Path $PLUGIN_DEST | Out-Null }
foreach ($file in @("version.php", "lib.php", "settings.php", "update.xml")) {
    $src = Join-Path $REPO $file
    if (Test-Path $src) { Copy-Item $src $PLUGIN_DEST -Force }
}
foreach ($dir in @("db", "pages", "templates", "lang", "classes")) {
    $src = Join-Path $REPO $dir
    if (Test-Path $src) { Copy-Item $src (Join-Path $PLUGIN_DEST $dir) -Recurse -Force }
}

# --- Copy theme files ---
Write-Host "Copying theme to $THEME_DEST..."
if (-not (Test-Path $THEME_DEST)) { New-Item -ItemType Directory -Path $THEME_DEST | Out-Null }
Get-ChildItem $themeDir -Exclude ".git", ".idea", "dump.html" | Copy-Item -Destination $THEME_DEST -Recurse -Force

# --- Run Moodle upgrade + purge caches ---
Write-Host ""
Write-Host "Running Moodle upgrade (registers plugin + theme)..."
$phpPath = Join-Path $MOODLE_PATH "..\php\php.exe"
$upgradeScript = Join-Path $MOODLE_PATH "admin\cli\upgrade.php"
$purgeScript = Join-Path $MOODLE_PATH "admin\cli\purge_caches.php"

if (-not (Test-Path $phpPath)) {
    if (Get-Command php -ErrorAction SilentlyContinue) {
        $phpPath = "php"
    } else {
        Write-Host "WARNING: Could not find PHP." -ForegroundColor Yellow
        Write-Host "Run the upgrade manually: Site Administration > Notifications"
        Write-Host ""
        Write-Host "Setup complete (files copied)!" -ForegroundColor Green
        exit 0
    }
}

& $phpPath $upgradeScript --non-interactive
Write-Host ""
Write-Host "Activating SmartMind theme (site + all IOMAD companies)..."
$configPath = Join-Path $MOODLE_PATH "config.php"
& $phpPath -r "define('CLI_SCRIPT',true); require('$configPath'); set_config('theme','smartmind'); if (`$DB->get_manager()->table_exists('company')) { `$DB->set_field('company','theme','smartmind',[]); } theme_reset_all_caches();"
Write-Host ""
Write-Host "Purging caches..."
& $phpPath $purgeScript

Write-Host ""
Write-Host "Setup complete!" -ForegroundColor Green
Write-Host ""
Write-Host "After changes, run .\scripts\windows\sync-theme.ps1 to deploy and purge caches."
Write-Host "Or run .\scripts\windows\watch.ps1 to auto-sync on every file save."
