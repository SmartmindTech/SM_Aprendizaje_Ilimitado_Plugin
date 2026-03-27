#!/bin/bash
# Pull latest changes from dev into your branch, pull theme, and sync to Docker.
#
# Usage: ./scripts/docker_linux/git-pull.sh

set -e

REPO="$(cd "$(dirname "$0")/../.." && pwd)"

# --- Load .env ---
if [ -f "$REPO/.env" ]; then
    source "$REPO/.env"
fi

BRANCH="${GIT_BRANCH:-$(git -C "$REPO" branch --show-current)}"

echo "=== Pulling dev into $BRANCH ==="
git -C "$REPO" checkout "$BRANCH"
git -C "$REPO" fetch origin dev
git -C "$REPO" merge origin/dev

echo ""
echo "=== Pulling theme ==="
git -C "$REPO/theme_smartmind" pull origin dev

echo ""
echo "=== Syncing to Docker ==="
bash "$REPO/scripts/docker_linux/sync-theme.sh"

echo ""
echo "Done!"
