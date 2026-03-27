#!/bin/bash
# Watch for file changes and auto-sync to Moodle.
#
# Usage: ./scripts/linux/watch.sh
# Press Ctrl+C to stop.

REPO="$(cd "$(dirname "$0")/../.." && pwd)"

echo "Watching for changes in plugin and theme..."
echo "Press Ctrl+C to stop."
echo ""

inotifywait -m -r -e modify,create,delete \
  --exclude '(\.git|\.env|CLAUDE\.md|scripts/)' \
  "$REPO" \
  --format '%T %w%f' --timefmt '%H:%M:%S' |
while read -r time file; do
    echo "[$time] Changed: $file"
    echo "Syncing..."
    bash "$REPO/scripts/linux/sync-theme.sh" 2>&1 | tail -1
    echo ""
done
