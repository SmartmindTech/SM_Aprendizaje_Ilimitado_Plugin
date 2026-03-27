#!/bin/bash
# Commit and push changes for both plugin and theme.
# Then merges your branch into dev and pushes dev.
# Syncs to Docker container at the end.
#
# Usage:
#   ./scripts/docker_linux/git-push.sh "commit message"
#   ./scripts/docker_linux/git-push.sh                    (opens editor)

set -e

REPO="$(cd "$(dirname "$0")/../.." && pwd)"
MSG="${1:-}"

# --- Load .env ---
if [ -f "$REPO/.env" ]; then
    source "$REPO/.env"
fi

BRANCH="${GIT_BRANCH:-$(git -C "$REPO" branch --show-current)}"

THEME_CHANGED=false
PLUGIN_CHANGED=false

# --- Check for theme changes ---
if [ -n "$(git -C "$REPO/theme_smartmind" status --porcelain)" ]; then
    THEME_CHANGED=true
fi

# --- Check for plugin changes (excluding submodule pointer) ---
if [ -n "$(git -C "$REPO" status --porcelain -- . ':!theme_smartmind')" ]; then
    PLUGIN_CHANGED=true
fi

# --- Check submodule has new commits ---
if git -C "$REPO" diff --quiet theme_smartmind 2>/dev/null; then
    : # no submodule change
else
    PLUGIN_CHANGED=true
fi

if [ "$THEME_CHANGED" = false ] && [ "$PLUGIN_CHANGED" = false ]; then
    echo "Nothing to commit."
    exit 0
fi

# --- Make sure we're on our branch ---
git -C "$REPO" checkout "$BRANCH"

# --- Commit and push theme ---
if [ "$THEME_CHANGED" = true ]; then
    echo "=== Committing theme ==="
    git -C "$REPO/theme_smartmind" add -A
    if [ -n "$MSG" ]; then
        git -C "$REPO/theme_smartmind" commit -m "$MSG"
    else
        git -C "$REPO/theme_smartmind" commit
    fi
    echo "=== Pushing theme ==="
    git -C "$REPO/theme_smartmind" push origin dev
    git -C "$REPO" add theme_smartmind
fi

# --- Commit and push plugin ---
if [ "$PLUGIN_CHANGED" = true ] || [ "$THEME_CHANGED" = true ]; then
    echo ""
    echo "=== Committing plugin on $BRANCH ==="
    git -C "$REPO" add -A -- . ':!theme_smartmind'
    if [ -n "$MSG" ]; then
        COMMIT_MSG="$MSG"
        if [ "$THEME_CHANGED" = true ] && [ "$PLUGIN_CHANGED" = true ]; then
            COMMIT_MSG="$MSG (plugin + theme)"
        elif [ "$THEME_CHANGED" = true ]; then
            COMMIT_MSG="Update theme submodule: $MSG"
        fi
        git -C "$REPO" commit -m "$COMMIT_MSG"
    else
        git -C "$REPO" commit
    fi
    echo "=== Pushing $BRANCH ==="
    git -C "$REPO" push origin "$BRANCH"
fi

# --- Merge into dev and push ---
echo ""
echo "=== Merging $BRANCH into dev ==="
git -C "$REPO" checkout dev
git -C "$REPO" pull origin dev
git -C "$REPO" merge "$BRANCH"
git -C "$REPO" push origin dev

# --- Back to dev branch ---
git -C "$REPO" checkout "$BRANCH"

# --- Sync to Docker ---
echo ""
echo "=== Syncing to Docker ==="
bash "$REPO/scripts/docker_linux/sync-theme.sh"

echo ""
echo "Done!"
