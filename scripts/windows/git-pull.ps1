# Pull latest changes from dev into your branch, pull theme, and sync Moodle.
#
# Usage: .\scripts\windows\git-pull.ps1

$ErrorActionPreference = "Stop"
$REPO = Split-Path -Parent (Split-Path -Parent (Split-Path -Parent $MyInvocation.MyCommand.Path))

# --- Load .env ---
$envFile = Join-Path $REPO ".env"
if (Test-Path $envFile) {
    Get-Content $envFile | ForEach-Object {
        if ($_ -match '^\s*([^#][^=]+)=(.+)$') {
            Set-Variable -Name $matches[1].Trim() -Value $matches[2].Trim() -Scope Script
        }
    }
}

if (-not $GIT_BRANCH) {
    $GIT_BRANCH = git -C $REPO branch --show-current
}

Write-Host "=== Pulling dev into $GIT_BRANCH ==="
git -C $REPO checkout $GIT_BRANCH
git -C $REPO fetch origin dev
git -C $REPO merge origin/dev

Write-Host ""
Write-Host "=== Pulling theme ==="
git -C (Join-Path $REPO "theme_smartmind") pull origin dev

Write-Host ""
Write-Host "=== Syncing to Moodle ==="
& (Join-Path $REPO "scripts\windows\sync-theme.ps1")

Write-Host ""
Write-Host "Done!"
