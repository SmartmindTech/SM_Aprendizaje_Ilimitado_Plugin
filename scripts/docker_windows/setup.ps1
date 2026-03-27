# SmartMind Plugin - Development Setup (Docker + Windows PowerShell)
#
# Copies plugin + theme into the Docker container and runs upgrade.
#
# Prerequisites:
#   1. Copy .env.example to .env and set DOCKER_CONTAINER
#   2. Run: .\scripts\docker_windows\setup.ps1

$ErrorActionPreference = "Stop"
$REPO = Split-Path -Parent (Split-Path -Parent (Split-Path -Parent $MyInvocation.MyCommand.Path))
$MR = "/var/www/html"

# --- Load .env ---
$envFile = Join-Path $REPO ".env"
if (-not (Test-Path $envFile)) {
    Write-Host "ERROR: .env file not found." -ForegroundColor Red
    Write-Host ""
    Write-Host "Copy .env.example to .env and set DOCKER_CONTAINER:"
    Write-Host "  Copy-Item .env.example .env"
    Write-Host "  notepad .env"
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

# --- Initialize submodule and pull latest ---
Write-Host "Initializing theme submodule..."
git -C $REPO submodule init
git -C $REPO submodule update
$themeDir = Join-Path $REPO "theme_smartmind"
git -C $themeDir checkout dev
Write-Host "Pulling latest theme..."
git -C $themeDir pull origin dev

# --- Copy plugin to container ---
Write-Host ""
Write-Host "Deploying plugin..."
docker exec $DOCKER_CONTAINER mkdir -p "$MR/local/sm_graphics_plugin"
foreach ($file in @("version.php", "lib.php", "settings.php", "update.xml")) {
    $src = Join-Path $REPO $file
    if (Test-Path $src) { docker cp "$src" "${DOCKER_CONTAINER}:${MR}/local/sm_graphics_plugin/" }
}
foreach ($dir in @("db", "pages", "templates", "lang", "classes")) {
    $src = Join-Path $REPO $dir
    if (Test-Path $src) { docker cp "$src" "${DOCKER_CONTAINER}:${MR}/local/sm_graphics_plugin/" }
}

# --- Copy theme to container ---
Write-Host "Deploying theme..."
docker exec $DOCKER_CONTAINER mkdir -p "$MR/theme/smartmind"
Get-ChildItem $themeDir -Exclude ".git", ".idea", "dump.html" | ForEach-Object {
    docker cp "$($_.FullName)" "${DOCKER_CONTAINER}:${MR}/theme/smartmind/"
}

# --- Run Moodle upgrade + activate theme + purge caches ---
Write-Host ""
Write-Host "Running Moodle upgrade (registers plugin + theme)..."
docker exec $DOCKER_CONTAINER php "$MR/admin/cli/upgrade.php" --non-interactive
Write-Host ""
Write-Host "Activating SmartMind theme (site + all IOMAD companies)..."
docker exec $DOCKER_CONTAINER php -r "define('CLI_SCRIPT',true); require('/var/www/html/config.php'); set_config('theme','smartmind'); if (`$DB->get_manager()->table_exists('company')) { `$DB->set_field('company','theme','smartmind',[]); } theme_reset_all_caches();"
Write-Host ""
Write-Host "Purging caches..."
docker exec $DOCKER_CONTAINER php "$MR/admin/cli/purge_caches.php"

Write-Host ""
Write-Host "Setup complete!" -ForegroundColor Green
Write-Host "  Container: $DOCKER_CONTAINER"
Write-Host ""
Write-Host "Run .\scripts\docker_windows\sync-theme.ps1 after making changes."
