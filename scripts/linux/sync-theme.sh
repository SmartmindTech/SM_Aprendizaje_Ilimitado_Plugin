#!/bin/bash
# Sync plugin + theme files to Moodle + purge caches
#
# Usage: ./scripts/linux/sync-theme.sh

set -e

REPO="$(cd "$(dirname "$0")/../.." && pwd)"

# --- Load .env file ---
if [ ! -f "$REPO/.env" ]; then
    echo "ERROR: .env file not found. Run ./scripts/linux/setup.sh first."
    exit 1
fi

# shellcheck disable=SC1091
source "$REPO/.env"

if [ -z "$MOODLE_PATH" ]; then
    echo "ERROR: MOODLE_PATH is not set in .env"
    exit 1
fi

PLUGIN_DEST="$MOODLE_PATH/local/sm_graphics_plugin"
THEME_DEST="$MOODLE_PATH/theme/smartmind"

# --- Build AMD JS (copy src → build as .min.js) ---
if [ -d "$REPO/amd/src" ]; then
    mkdir -p "$REPO/amd/build"
    for src in "$REPO"/amd/src/*.js; do
        [ -f "$src" ] || continue
        base="$(basename "$src" .js)"
        cp "$src" "$REPO/amd/build/${base}.min.js"
    done
fi

# --- Sync plugin files ---
echo "Syncing plugin..."
cp -r "$REPO"/version.php "$REPO"/lib.php "$REPO"/settings.php "$REPO"/update.xml "$PLUGIN_DEST/"
for dir in db pages templates lang classes amd certificate_type; do
    [ -d "$REPO/$dir" ] && cp -r "$REPO/$dir" "$PLUGIN_DEST/"
done
# Copy update.php for auto-update feature.
[ -f "$REPO/update.php" ] && cp "$REPO/update.php" "$PLUGIN_DEST/"

# --- Sync theme files ---
echo "Syncing theme..."
rsync -a --delete --exclude='.git' "$REPO/theme_smartmind/" "$THEME_DEST/"

# --- Deploy lang overrides + purge caches ---
echo "Deploying lang overrides and purging caches..."
if [ -f "$MOODLE_PATH/../php/php.exe" ]; then
    PHP="$MOODLE_PATH/../php/php.exe"
    WINMOODLE=$(echo "$MOODLE_PATH" | sed 's|/mnt/c/|C:\\|;s|/|\\|g')
    "$PHP" -r "define('CLI_SCRIPT',true); require('$WINMOODLE\\config.php'); require_once('$WINMOODLE\\local\\sm_graphics_plugin\\db\\install.php'); local_sm_graphics_plugin_deploy_lang_overrides(); purge_all_caches();"
elif command -v php &>/dev/null; then
    php -r "define('CLI_SCRIPT',true); require('$MOODLE_PATH/config.php'); require_once('$MOODLE_PATH/local/sm_graphics_plugin/db/install.php'); local_sm_graphics_plugin_deploy_lang_overrides(); purge_all_caches();"
else
    echo "WARNING: Could not find PHP. Purge caches manually."
fi

echo "Done! Plugin and theme synced."
