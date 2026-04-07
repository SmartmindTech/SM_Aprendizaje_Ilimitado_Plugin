# Watch for file changes and auto-sync to the Docker container, while also
# running the Nuxt dev server in the background for instant Vue/SCSS HMR.
#
# - PHP / lang / db / classes / theme edits  -> sync-theme.ps1 into container
# - Vue / SCSS / i18n edits                  -> handled by `npm run dev` HMR
#
# Logs from the dev server are written to scripts\docker_windows\.nuxt-dev.log.
# The dev server is killed automatically when this script exits (Ctrl+C).
#
# Usage: .\scripts\docker_windows\watch.ps1
# Press Ctrl+C to stop.

$ErrorActionPreference = "Stop"
$REPO = Split-Path -Parent (Split-Path -Parent (Split-Path -Parent $MyInvocation.MyCommand.Path))
$syncScript = Join-Path $REPO "scripts\docker_windows\sync-theme.ps1"
$devLog     = Join-Path $REPO "scripts\docker_windows\.nuxt-dev.log"
$frontendDir = Join-Path $REPO "frontend"

$devProcess = $null

# Start Nuxt dev server in background.
if (Test-Path (Join-Path $frontendDir "node_modules")) {
    Write-Host "Starting Nuxt dev server in background (logs: $devLog)..."
    "" | Out-File -FilePath $devLog -Encoding utf8
    $devProcess = Start-Process -FilePath "npm.cmd" `
        -ArgumentList "run", "dev" `
        -WorkingDirectory $frontendDir `
        -RedirectStandardOutput $devLog `
        -RedirectStandardError "$devLog.err" `
        -NoNewWindow `
        -PassThru
    Start-Sleep -Seconds 1
    if ($devProcess.HasExited) {
        Write-Host "  X Nuxt dev server failed to start. See $devLog" -ForegroundColor Red
        $devProcess = $null
    } else {
        Write-Host "  OK Nuxt dev server pid $($devProcess.Id) - give it ~10s to be reachable on the configured SPA_DEV_PORT"
    }
} else {
    Write-Host "  ! frontend\node_modules not found - run 'cd frontend; npm install' first." -ForegroundColor Yellow
    Write-Host "  Skipping Nuxt dev server. Falling back to static frontend_dist\."
}

Write-Host ""
Write-Host "Watching $REPO for plugin/theme changes..."
Write-Host "Press Ctrl+C to stop."
Write-Host ""

$watcher = New-Object System.IO.FileSystemWatcher
$watcher.Path = $REPO
$watcher.Filter = "*.*"
$watcher.IncludeSubdirectories = $true
$watcher.NotifyFilter = [System.IO.NotifyFilters]::LastWrite
$watcher.EnableRaisingEvents = $true

$ignorePattern = '(^|[\\/])(\.git|scripts|frontend[\\/]\.nuxt|frontend[\\/]\.output|frontend[\\/]node_modules)([\\/]|$)|(^|[\\/])(\.env|CLAUDE\.md|dump.*\.html|image[0-9]*\.png)$'

try {
    while ($true) {
        $result = $watcher.WaitForChanged([System.IO.WatcherChangeTypes]::Changed, 60000)
        if (-not $result.TimedOut) {
            $name = $result.Name
            if ($name -match $ignorePattern) { continue }
            $time = Get-Date -Format "HH:mm:ss"
            Write-Host "[$time] Changed: $name"
            Write-Host "  -> Syncing to container..."
            & $syncScript
            Write-Host ""
        }
    }
}
finally {
    if ($devProcess -and -not $devProcess.HasExited) {
        Write-Host ""
        Write-Host "Stopping Nuxt dev server (pid $($devProcess.Id))..."
        try {
            # Kill the npm process and any node children it spawned.
            taskkill /PID $devProcess.Id /T /F | Out-Null
        } catch { }
    }
}
