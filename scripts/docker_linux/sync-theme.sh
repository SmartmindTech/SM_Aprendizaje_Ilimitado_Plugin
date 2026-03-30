#!/bin/bash
# Sync plugin + theme files into Docker container + purge caches
#
# Usage: ./scripts/docker_linux/sync-theme.sh

set -e

REPO="$(cd "$(dirname "$0")/../.." && pwd)"

# --- Load .env file ---
if [ ! -f "$REPO/.env" ]; then
    echo "ERROR: .env file not found. Run ./scripts/docker_linux/setup.sh first."
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

# --- Build AMD JS (copy src → build as .min.js) ---
if [ -d "$REPO/amd/src" ]; then
    mkdir -p "$REPO/amd/build"
    for src in "$REPO"/amd/src/*.js; do
        [ -f "$src" ] || continue
        base="$(basename "$src" .js)"
        cp "$src" "$REPO/amd/build/${base}.min.js"
    done
fi

# --- Copy .env file into plugin dir (provides GEMINI_API_KEY etc. to PHP) ---
if [ -f "$REPO/.env" ]; then
    $DOCKER_CMD cp "$REPO/.env" "$DOCKER_CONTAINER:$MOODLE_ROOT/local/sm_graphics_plugin/.env"
fi

# --- Sync plugin files ---
echo "Syncing plugin..."
$DOCKER_CMD exec "$DOCKER_CONTAINER" mkdir -p "$MOODLE_ROOT/local/sm_graphics_plugin"
$DOCKER_CMD cp "$REPO/version.php" "$DOCKER_CONTAINER:$MOODLE_ROOT/local/sm_graphics_plugin/"
$DOCKER_CMD cp "$REPO/lib.php" "$DOCKER_CONTAINER:$MOODLE_ROOT/local/sm_graphics_plugin/"
$DOCKER_CMD cp "$REPO/settings.php" "$DOCKER_CONTAINER:$MOODLE_ROOT/local/sm_graphics_plugin/"
$DOCKER_CMD cp "$REPO/update.xml" "$DOCKER_CONTAINER:$MOODLE_ROOT/local/sm_graphics_plugin/"
for dir in db pages templates lang classes amd certificate_type; do
    [ -d "$REPO/$dir" ] && $DOCKER_CMD cp "$REPO/$dir" "$DOCKER_CONTAINER:$MOODLE_ROOT/local/sm_graphics_plugin/"
done
# Copy update.php for auto-update feature.
[ -f "$REPO/update.php" ] && $DOCKER_CMD cp "$REPO/update.php" "$DOCKER_CONTAINER:$MOODLE_ROOT/local/sm_graphics_plugin/"

# --- Sync theme files ---
echo "Syncing theme..."
for item in "$REPO"/theme_smartmind/*; do
    basename="$(basename "$item")"
    [ "$basename" = ".git" ] || [ "$basename" = ".idea" ] || [ "$basename" = "dump.html" ] && continue
    $DOCKER_CMD cp "$item" "$DOCKER_CONTAINER:$MOODLE_ROOT/theme/smartmind/"
done

# --- Deploy lang overrides + purge caches ---
echo "Deploying lang overrides and purging caches..."
$DOCKER_CMD exec "$DOCKER_CONTAINER" php -r "
define('CLI_SCRIPT', true);
require('/var/www/html/config.php');
require_once('/var/www/html/local/sm_graphics_plugin/db/install.php');
local_sm_graphics_plugin_deploy_lang_overrides();
purge_all_caches();
"

echo "Done! Plugin and theme synced."
