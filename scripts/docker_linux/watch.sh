#!/bin/bash
# Watch for file changes and auto-sync to the Docker container, while also
# running the Nuxt dev server in the background for instant Vue/SCSS HMR.
#
# - PHP / lang / db / classes / theme edits  → sync-theme.sh into container
# - Vue / SCSS / i18n edits                  → handled by `npm run dev` HMR
#
# Logs from the dev server are written to scripts/docker_linux/.nuxt-dev.log.
# The dev server is killed automatically when this script exits (Ctrl+C).
#
# Usage: ./scripts/docker_linux/watch.sh
# Press Ctrl+C to stop.

REPO="$(cd "$(dirname "$0")/../.." && pwd)"
DEV_LOG="$REPO/scripts/docker_linux/.nuxt-dev.log"
DEV_PID=""

cleanup() {
    if [[ -n "$DEV_PID" ]] && kill -0 "$DEV_PID" 2>/dev/null; then
        echo ""
        echo "Stopping Nuxt dev server (pid $DEV_PID)..."
        # Kill the entire process group so node + its children all die.
        kill -- -"$DEV_PID" 2>/dev/null || kill "$DEV_PID" 2>/dev/null
    fi
}
trap cleanup EXIT INT TERM

# Start `npm run dev` in its own process group so we can clean it up later.
if [[ -d "$REPO/frontend/node_modules" ]]; then
    echo "Starting Nuxt dev server in background (logs: $DEV_LOG)..."
    : > "$DEV_LOG"
    ( cd "$REPO/frontend" && setsid npm run dev >> "$DEV_LOG" 2>&1 ) &
    DEV_PID=$!
    sleep 1
    if ! kill -0 "$DEV_PID" 2>/dev/null; then
        echo "  ✖ Nuxt dev server failed to start. See $DEV_LOG"
        DEV_PID=""
    else
        echo "  ✓ Nuxt dev server pid $DEV_PID — give it ~10s to be reachable on the configured SPA_DEV_PORT"
    fi
else
    echo "  ⚠ frontend/node_modules not found — run 'cd frontend && npm install' first."
    echo "  Skipping Nuxt dev server. Falling back to static frontend_dist/."
fi

echo ""
echo "Watching $REPO for plugin/theme changes..."
echo "Press Ctrl+C to stop."
echo ""

inotifywait -m -r -e modify,create,delete \
  --exclude '(\.git|\.env$|CLAUDE\.md|scripts/|frontend/\.nuxt/|frontend/\.output/|frontend/node_modules/|dump.*\.html|image[0-9]*\.png)' \
  "$REPO" \
  --format '%T %w%f' --timefmt '%H:%M:%S' |
while read -r time file; do
    echo "[$time] Changed: ${file#$REPO/}"
    echo "  → Syncing to container..."
    bash "$REPO/scripts/docker_linux/sync-theme.sh" 2>&1 | tail -1
    echo ""
done
