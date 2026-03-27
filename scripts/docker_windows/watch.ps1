# Watch for file changes and auto-sync to Docker container.
#
# Usage: .\scripts\docker_windows\watch.ps1
# Press Ctrl+C to stop.

$ErrorActionPreference = "Stop"
$REPO = Split-Path -Parent (Split-Path -Parent (Split-Path -Parent $MyInvocation.MyCommand.Path))
$syncScript = Join-Path $REPO "scripts\docker_windows\sync-theme.ps1"

Write-Host "Watching for changes... Press Ctrl+C to stop."
Write-Host ""

$watcher = New-Object System.IO.FileSystemWatcher
$watcher.Path = $REPO
$watcher.Filter = "*.*"
$watcher.IncludeSubdirectories = $true
$watcher.NotifyFilter = [System.IO.NotifyFilters]::LastWrite
$watcher.EnableRaisingEvents = $true

while ($true) {
    $result = $watcher.WaitForChanged([System.IO.WatcherChangeTypes]::Changed, 60000)
    if (-not $result.TimedOut) {
        $name = $result.Name
        if ($name -match '\.git|\.env|scripts[\\/]') { continue }
        $time = Get-Date -Format "HH:mm:ss"
        Write-Host "[$time] Changed: $name"
        Write-Host "Syncing..."
        & $syncScript
        Write-Host ""
    }
}
