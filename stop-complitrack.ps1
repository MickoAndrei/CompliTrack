$repoRoot = Split-Path -Parent $MyInvocation.MyCommand.Path
Set-Location $repoRoot

$laravelPidFile = Join-Path $repoRoot ".laravel.pid"
$vitePidFile = Join-Path $repoRoot ".vite.pid"
$realtimePidFile = Join-Path $repoRoot ".realtime.pid"

function Test-CompliTrackProcess([int]$processId) {
    try {
        $process = Get-CimInstance Win32_Process -Filter "ProcessId = $processId" -ErrorAction Stop
    } catch {
        Write-Warning "Could not verify PID $processId; leaving it running for safety."
        return $false
    }

    if (-not $process) { return $false }
    $commandLine = [string]$process.CommandLine
    return $commandLine.IndexOf($repoRoot, [StringComparison]::OrdinalIgnoreCase) -ge 0
}

function Stop-PidFile([string]$pidFile) {
    if (-not (Test-Path $pidFile)) {
        Write-Host "No PID file: $pidFile"
        return
    }

    $removePidFile = $true
    $processId = Get-Content $pidFile -ErrorAction SilentlyContinue
    if ($processId) {
        $parsedProcessId = 0
        if ([int]::TryParse([string]$processId, [ref]$parsedProcessId) -and $parsedProcessId -gt 0) {
            $proc = Get-Process -Id $parsedProcessId -ErrorAction SilentlyContinue
            if ($proc -and (Test-CompliTrackProcess $parsedProcessId)) {
                # npm.cmd runs the actual Node service as a child process. Stop the
                # saved process and its descendants so the listener is released too.
                & taskkill.exe /PID $parsedProcessId /T /F 2>$null | Out-Null
                if ($LASTEXITCODE -ne 0) {
                    Stop-Process -Id $parsedProcessId -Force -ErrorAction SilentlyContinue
                }
                Start-Sleep -Seconds 1
                Write-Host "Stopped process tree rooted at PID $parsedProcessId"
            } elseif (-not $proc) {
                Write-Host "No active process found for PID $parsedProcessId"
            } else {
                Write-Warning "PID $parsedProcessId does not identify this CompliTrack checkout; leaving it running."
                $removePidFile = $false
            }
        } else {
            Write-Warning "Ignoring invalid PID in ${pidFile}: $processId"
        }
    }

    if ($removePidFile) {
        Remove-Item $pidFile -Force -ErrorAction SilentlyContinue
    }
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
        if ($processId -gt 0 -and
            (Get-Process -Id $processId -ErrorAction SilentlyContinue) -and
            (Test-CompliTrackProcess $processId)) {
            & taskkill.exe /PID $processId /T /F 2>$null | Out-Null
            if ($LASTEXITCODE -ne 0) {
                Stop-Process -Id $processId -Force -ErrorAction SilentlyContinue
            }
            Write-Host "Stopped listener process tree on port $port (PID $processId)"
        } elseif ($processId -gt 0) {
            Write-Host "Listener on port $port (PID $processId) is not verified as this checkout; leaving it running."
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
