#!/bin/bash
# SmartMind Plugin — Development Setup (WSL/Linux/Mac)
#
# Initializes the theme submodule and deploys plugin + theme to Moodle.
#
# Prerequisites:
#   1. Copy .env.example to .env and set MOODLE_PATH
#   2. Run: ./scripts/linux/setup.sh

set -e

REPO="$(cd "$(dirname "$0")/../.." && pwd)"

# --- Load .env file ---
if [ ! -f "$REPO/.env" ]; then
    echo "ERROR: .env file not found."
    echo ""
    echo "Copy .env.example to .env and set MOODLE_PATH:"
    echo "  cp .env.example .env"
    echo "  nano .env"
    exit 1
fi

# shellcheck disable=SC1091
source "$REPO/.env"

if [ -z "$MOODLE_PATH" ]; then
    echo "ERROR: MOODLE_PATH is not set in .env"
    exit 1
fi

if [ ! -d "$MOODLE_PATH/admin" ]; then
    echo "ERROR: $MOODLE_PATH does not look like a Moodle installation."
    echo "Make sure MOODLE_PATH points to the Moodle root (containing admin/, theme/, local/)."
    exit 1
fi

PLUGIN_DEST="$MOODLE_PATH/local/sm_graphics_plugin"
THEME_DEST="$MOODLE_PATH/theme/smartmind"

# --- Initialize submodule and pull latest ---
echo "Initializing theme submodule..."
git -C "$REPO" submodule init
git -C "$REPO" submodule update
git -C "$REPO/theme_smartmind" checkout dev
echo "Pulling latest theme..."
git -C "$REPO/theme_smartmind" pull origin dev

# --- Deploy plugin ---
echo ""
echo "Deploying plugin to $PLUGIN_DEST..."
mkdir -p "$PLUGIN_DEST"
cp -r "$REPO"/version.php "$REPO"/lib.php "$REPO"/settings.php "$REPO"/update.xml "$PLUGIN_DEST/"
for dir in db pages templates lang classes; do
    [ -d "$REPO/$dir" ] && cp -r "$REPO/$dir" "$PLUGIN_DEST/"
done

# --- Deploy theme ---
echo "Deploying theme to $THEME_DEST..."
mkdir -p "$THEME_DEST"
rsync -a --exclude='.git' "$REPO/theme_smartmind/" "$THEME_DEST/"

# --- Run Moodle upgrade + activate theme + purge caches ---
echo ""
echo "Running Moodle upgrade (registers plugin + theme)..."
if [ -f "$MOODLE_PATH/../php/php.exe" ]; then
    PHP="$MOODLE_PATH/../php/php.exe"
    WINMOODLE=$(echo "$MOODLE_PATH" | sed 's|/mnt/c/|C:\\|;s|/|\\|g')
    "$PHP" "$WINMOODLE\\admin\\cli\\upgrade.php" --non-interactive
    echo ""
    echo "Activating SmartMind theme (site + all IOMAD companies)..."
    "$PHP" -r "define('CLI_SCRIPT',true); require('$WINMOODLE\\config.php'); set_config('theme','smartmind'); if (\\\$DB->get_manager()->table_exists('company')) { \\\$DB->set_field('company','theme','smartmind',[]); } theme_reset_all_caches();"
    echo ""
    echo "Purging caches..."
    "$PHP" "$WINMOODLE\\admin\\cli\\purge_caches.php"
elif command -v php &>/dev/null; then
    php "$MOODLE_PATH/admin/cli/upgrade.php" --non-interactive
    echo ""
    echo "Activating SmartMind theme (site + all IOMAD companies)..."
    php -r "define('CLI_SCRIPT',true); require('$MOODLE_PATH/config.php'); set_config('theme','smartmind'); if (\\\$DB->get_manager()->table_exists('company')) { \\\$DB->set_field('company','theme','smartmind',[]); } theme_reset_all_caches();"
    echo ""
    echo "Purging caches..."
    php "$MOODLE_PATH/admin/cli/purge_caches.php"
else
    echo "WARNING: Could not find PHP. Run upgrade manually via Moodle admin."
fi

echo ""
echo "Setup complete!"
echo "  Plugin: $PLUGIN_DEST"
echo "  Theme:  $THEME_DEST"
echo ""
echo "Run ./scripts/linux/sync-theme.sh after making changes to deploy updates."
