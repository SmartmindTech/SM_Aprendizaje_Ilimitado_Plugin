#!/bin/bash
# SmartMind Plugin — Development Setup (Docker + Linux/WSL/Mac)
#
# Copies plugin + theme into the Docker container and runs upgrade.
#
# Prerequisites:
#   1. Copy .env.example to .env and set DOCKER_CONTAINER
#   2. Run: ./scripts/docker_linux/setup.sh

set -e

REPO="$(cd "$(dirname "$0")/../.." && pwd)"

# --- Load .env file ---
if [ ! -f "$REPO/.env" ]; then
    echo "ERROR: .env file not found."
    echo ""
    echo "Copy .env.example to .env and set DOCKER_CONTAINER:"
    echo "  cp .env.example .env"
    echo "  nano .env"
    exit 1
fi

# shellcheck disable=SC1091
source "$REPO/.env"

if [ -z "$DOCKER_CONTAINER" ]; then
    echo "ERROR: DOCKER_CONTAINER is not set in .env"
    exit 1
fi

DOCKER_CMD="docker"
MOODLE_ROOT="/var/www/html"

# --- Initialize submodule and pull latest ---
echo "Initializing theme submodule..."
git -C "$REPO" submodule init
git -C "$REPO" submodule update
git -C "$REPO/theme_smartmind" checkout dev
echo "Pulling latest theme..."
git -C "$REPO/theme_smartmind" pull origin dev

# --- Copy plugin to container ---
echo ""
echo "Deploying plugin to $DOCKER_CONTAINER:$MOODLE_ROOT/local/sm_graphics_plugin/..."
$DOCKER_CMD exec "$DOCKER_CONTAINER" mkdir -p "$MOODLE_ROOT/local/sm_graphics_plugin"
$DOCKER_CMD cp "$REPO/version.php" "$DOCKER_CONTAINER:$MOODLE_ROOT/local/sm_graphics_plugin/"
$DOCKER_CMD cp "$REPO/lib.php" "$DOCKER_CONTAINER:$MOODLE_ROOT/local/sm_graphics_plugin/"
$DOCKER_CMD cp "$REPO/settings.php" "$DOCKER_CONTAINER:$MOODLE_ROOT/local/sm_graphics_plugin/"
$DOCKER_CMD cp "$REPO/update.xml" "$DOCKER_CONTAINER:$MOODLE_ROOT/local/sm_graphics_plugin/"
for dir in db pages templates lang classes; do
    [ -d "$REPO/$dir" ] && $DOCKER_CMD cp "$REPO/$dir" "$DOCKER_CONTAINER:$MOODLE_ROOT/local/sm_graphics_plugin/"
done

# --- Copy theme to container ---
echo "Deploying theme to $DOCKER_CONTAINER:$MOODLE_ROOT/theme/smartmind/..."
$DOCKER_CMD exec "$DOCKER_CONTAINER" mkdir -p "$MOODLE_ROOT/theme/smartmind"
# Copy contents excluding .git
for item in "$REPO"/theme_smartmind/*; do
    basename="$(basename "$item")"
    [ "$basename" = ".git" ] || [ "$basename" = ".idea" ] || [ "$basename" = "dump.html" ] && continue
    $DOCKER_CMD cp "$item" "$DOCKER_CONTAINER:$MOODLE_ROOT/theme/smartmind/"
done

# --- Run Moodle upgrade + activate theme + purge caches ---
echo ""
echo "Running Moodle upgrade (registers plugin + theme)..."
$DOCKER_CMD exec "$DOCKER_CONTAINER" php "$MOODLE_ROOT/admin/cli/upgrade.php" --non-interactive
echo ""
echo "Activating SmartMind theme (site + all IOMAD companies)..."
$DOCKER_CMD exec "$DOCKER_CONTAINER" php -r "define('CLI_SCRIPT',true); require('/var/www/html/config.php'); set_config('theme','smartmind'); if (\$DB->get_manager()->table_exists('company')) { \$DB->set_field('company','theme','smartmind',[]); } theme_reset_all_caches();"
echo ""
echo "Purging caches..."
$DOCKER_CMD exec "$DOCKER_CONTAINER" php "$MOODLE_ROOT/admin/cli/purge_caches.php"

echo ""
echo "Setup complete!"
echo "  Container: $DOCKER_CONTAINER"
echo "  Plugin:    $MOODLE_ROOT/local/sm_graphics_plugin/"
echo "  Theme:     $MOODLE_ROOT/theme/smartmind/"
echo ""
echo "Run ./scripts/docker_linux/sync-theme.sh after making changes."
