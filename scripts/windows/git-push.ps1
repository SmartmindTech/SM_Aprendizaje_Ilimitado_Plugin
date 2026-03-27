# Commit and push changes for both plugin and theme.
# Then merges your branch into dev and pushes dev.
#
# Usage: .\scripts\windows\git-push.ps1 "commit message"

param(
    [Parameter(Mandatory=$true, Position=0)]
    [string]$MSG
)

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

$themeDir = Join-Path $REPO "theme_smartmind"

# --- Check for changes ---
$themeChanged = (git -C $themeDir status --porcelain) -ne $null
$pluginChanged = (git -C $REPO status --porcelain -- . ":!theme_smartmind") -ne $null

$submoduleChanged = $false
git -C $REPO diff --quiet theme_smartmind 2>$null
if ($LASTEXITCODE -ne 0) { $submoduleChanged = $true }

if (-not $themeChanged -and -not $pluginChanged -and -not $submoduleChanged) {
    Write-Host "Nothing to commit."
    exit 0
}

# --- Make sure we're on our branch ---
git -C $REPO checkout $GIT_BRANCH

# --- Commit and push theme ---
if ($themeChanged) {
    Write-Host "=== Committing theme ==="
    git -C $themeDir add -A
    git -C $themeDir commit -m $MSG
    Write-Host "=== Pushing theme ==="
    git -C $themeDir push origin dev
    git -C $REPO add theme_smartmind
}

# --- Commit and push plugin ---
$needsPluginCommit = $pluginChanged -or $themeChanged -or $submoduleChanged
if ($needsPluginCommit) {
    Write-Host ""
    Write-Host "=== Committing plugin on $GIT_BRANCH ==="
    git -C $REPO add -A -- . ":!theme_smartmind"

    $commitMsg = $MSG
    if ($themeChanged -and $pluginChanged) {
        $commitMsg = "$MSG (plugin + theme)"
    } elseif ($themeChanged) {
        $commitMsg = "Update theme submodule: $MSG"
    }
    git -C $REPO commit -m $commitMsg

    Write-Host "=== Pushing $GIT_BRANCH ==="
    git -C $REPO push origin $GIT_BRANCH
}

# --- Merge into dev ---
Write-Host ""
Write-Host "=== Merging $GIT_BRANCH into dev ==="
git -C $REPO checkout dev
git -C $REPO pull origin dev
git -C $REPO merge $GIT_BRANCH
git -C $REPO push origin dev

# --- Back to dev branch ---
git -C $REPO checkout $GIT_BRANCH

# --- Sync to Moodle ---
Write-Host ""
Write-Host "=== Syncing to Moodle ==="
& (Join-Path $REPO "scripts\windows\sync-theme.ps1")

Write-Host ""
Write-Host "Done!"
