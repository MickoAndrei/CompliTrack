$repoRoot = Split-Path -Parent $MyInvocation.MyCommand.Path
Set-Location $repoRoot

$laravelPidFile = Join-Path $repoRoot ".laravel.pid"
$vitePidFile = Join-Path $repoRoot ".vite.pid"
$realtimePidFile = Join-Path $repoRoot ".realtime.pid"

function Stop-PidFile([string]$pidFile) {
    if (-not (Test-Path $pidFile)) {
        Write-Host "No PID file: $pidFile"
        return
    }

    $pid = Get-Content $pidFile -ErrorAction SilentlyContinue
    if ($pid) {
        $proc = Get-Process -Id $pid -ErrorAction SilentlyContinue
        if ($proc) {
            Stop-Process -Id $pid -Force -ErrorAction SilentlyContinue
            Start-Sleep -Seconds 1
            Write-Host "Stopped process $pid"
        } else {
            Write-Host "No active process found for PID $pid"
        }
    }

    Remove-Item $pidFile -Force -ErrorAction SilentlyContinue
}

Stop-PidFile $laravelPidFile
Stop-PidFile $vitePidFile
Stop-PidFile $realtimePidFile

Write-Host "CompliTrack services stopped."