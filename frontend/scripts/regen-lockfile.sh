#!/usr/bin/env sh
# Regenerate frontend/package-lock.json inside a Linux Node 22 container so
# the result matches the CI runner exactly. Run this whenever you bump or
# add a frontend dependency to avoid `npm ci` failing in GitHub Actions
# with EUSAGE / "Missing: <pkg> from lock file".
#
# Usage (from anywhere):
#   ./frontend/scripts/regen-lockfile.sh
#
# Requires Docker. On Windows Git Bash you may need to prefix with
# MSYS_NO_PATHCONV=1 to keep the volume mount path verbatim.

set -e

SCRIPT_DIR="$(cd "$(dirname "$0")" && pwd)"
FRONTEND_DIR="$(cd "$SCRIPT_DIR/.." && pwd)"

echo "Regenerating package-lock.json in node:22-alpine..."
docker run --rm \
  -v "$FRONTEND_DIR:/work" \
  -w /work \
  node:22-alpine \
  sh -c "npm install --package-lock-only"

echo
echo "Verifying npm ci accepts the new lockfile..."
docker run --rm \
  -v "$FRONTEND_DIR:/work" \
  -w /work \
  node:22-alpine \
  sh -c "npm ci --dry-run > /dev/null && echo 'OK: npm ci passes'"
