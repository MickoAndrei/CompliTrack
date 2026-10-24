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

    $processId = Get-Content $pidFile -ErrorAction SilentlyContinue
    if ($processId) {
        $parsedProcessId = 0
        if ([int]::TryParse([string]$processId, [ref]$parsedProcessId) -and $parsedProcessId -gt 0) {
            $proc = Get-Process -Id $parsedProcessId -ErrorAction SilentlyContinue
            if ($proc) {
                # npm.cmd runs the actual Node service as a child process. Stop the
                # saved process and its descendants so the listener is released too.
                & taskkill.exe /PID $parsedProcessId /T /F 2>$null | Out-Null
                if ($LASTEXITCODE -ne 0) {
                    Stop-Process -Id $parsedProcessId -Force -ErrorAction SilentlyContinue
                }
                Start-Sleep -Seconds 1
                Write-Host "Stopped process tree rooted at PID $parsedProcessId"
            } else {
                Write-Host "No active process found for PID $parsedProcessId"
            }
        } else {
            Write-Warning "Ignoring invalid PID in ${pidFile}: $processId"
        }
    }

    Remove-Item $pidFile -Force -ErrorAction SilentlyContinue
}

function Stop-PortListener([int]$port) {
    try {
        $processIds = @(Get-NetTCPConnection -State Listen -LocalPort $port -ErrorAction SilentlyContinue |
            Select-Object -ExpandProperty OwningProcess -Unique)
    } catch {
        Write-Warning "Could not inspect listener on port $port."
        return
    }

    foreach ($processId in $processIds) {
        if ($processId -gt 0 -and (Get-Process -Id $processId -ErrorAction SilentlyContinue)) {
            & taskkill.exe /PID $processId /T /F 2>$null | Out-Null
            if ($LASTEXITCODE -ne 0) {
                Stop-Process -Id $processId -Force -ErrorAction SilentlyContinue
            }
            Write-Host "Stopped listener process tree on port $port (PID $processId)"
        }
    }
}

Stop-PidFile $laravelPidFile
Stop-PidFile $vitePidFile
Stop-PidFile $realtimePidFile

# Also stop services that were started before PID files were written or restored.
Stop-PortListener 8000
Stop-PortListener 4000
Stop-PortListener 5173

Write-Host "CompliTrack services stopped."
