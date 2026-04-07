# Sync plugin + theme files into Docker container + purge caches.
#
# Usage: .\scripts\docker_windows\sync-theme.ps1

$ErrorActionPreference = "Stop"
$REPO = Split-Path -Parent (Split-Path -Parent (Split-Path -Parent $MyInvocation.MyCommand.Path))
$MR = "/var/www/html"

# --- Load .env ---
$envFile = Join-Path $REPO ".env"
if (-not (Test-Path $envFile)) {
    Write-Host "ERROR: .env file not found. Run .\scripts\docker_windows\setup.ps1 first." -ForegroundColor Red
    exit 1
}

Get-Content $envFile | ForEach-Object {
    if ($_ -match '^\s*([^#][^=]+)=(.+)$') {
        Set-Variable -Name $matches[1].Trim() -Value $matches[2].Trim() -Scope Script
    }
}

if (-not $DOCKER_CONTAINER) {
    Write-Host "ERROR: DOCKER_CONTAINER is not set in .env" -ForegroundColor Red
    exit 1
}

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

# --- Sync plugin files ---
Write-Host "Syncing plugin..."
docker exec $DOCKER_CONTAINER mkdir -p "$MR/local/sm_graphics_plugin"
foreach ($file in @("version.php", "lib.php", "settings.php", "update.xml")) {
    $src = Join-Path $REPO $file
    if (Test-Path $src) { docker cp "$src" "${DOCKER_CONTAINER}:${MR}/local/sm_graphics_plugin/" }
}
# Copy .env so version.php can resolve UPDATE_BRANCH for the update channel.
$envSrc = Join-Path $REPO ".env"
if (Test-Path $envSrc) { docker cp "$envSrc" "${DOCKER_CONTAINER}:${MR}/local/sm_graphics_plugin/.env" }
foreach ($dir in @("db", "pages", "lang", "classes", "amd", "certificate_type", "frontend_dist")) {
    $src = Join-Path $REPO $dir
    if (Test-Path $src) { docker cp "$src" "${DOCKER_CONTAINER}:${MR}/local/sm_graphics_plugin/" }
}
# Copy update.php for auto-update feature.
$updateSrc = Join-Path $REPO "update.php"
if (Test-Path $updateSrc) { docker cp "$updateSrc" "${DOCKER_CONTAINER}:${MR}/local/sm_graphics_plugin/" }

# --- Sync theme files ---
Write-Host "Syncing theme..."
docker exec $DOCKER_CONTAINER mkdir -p "$MR/theme/smartmind"
Get-ChildItem $themeDir -Exclude ".git", ".idea", "dump.html" | ForEach-Object {
    docker cp "$($_.FullName)" "${DOCKER_CONTAINER}:${MR}/theme/smartmind/"
}

# --- Deploy lang overrides + purge caches ---
Write-Host "Deploying lang overrides and purging caches..."
docker exec $DOCKER_CONTAINER php -r "define('CLI_SCRIPT',true); require('/var/www/html/config.php'); require_once('/var/www/html/local/sm_graphics_plugin/db/install.php'); local_sm_graphics_plugin_deploy_lang_overrides(); purge_all_caches();"

Write-Host ""
Write-Host "Done!"
