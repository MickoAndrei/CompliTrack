param([string]$PhpExecutable = 'php')

$ErrorActionPreference = 'Stop'
$phpCommand = (Get-Command $PhpExecutable -ErrorAction Stop).Source
$env:Path = (Split-Path -Parent $phpCommand) + [IO.Path]::PathSeparator + $env:Path

$repoRoot = Split-Path -Parent $PSScriptRoot
$envPath = Join-Path $repoRoot '.env'
$statePath = Join-Path $repoRoot 'storage\app\system\database-switch\database-switch-state.json'
$logPath = Join-Path $repoRoot 'logs\database-switch.log'
$requestId = $null
$source = $null
$target = $null
$currentStage = 'queued'
$servicesStopped = $false
$configurationChanged = $false
$switchFailed = $false

function Write-SwitchLog([string]$message) {
    $directory = Split-Path -Parent $logPath
    New-Item -ItemType Directory -Force -Path $directory | Out-Null
    Add-Content -LiteralPath $logPath -Value ('{0:o} {1}' -f [DateTime]::UtcNow, $message)
}

function Get-PendingRequest {
    if (-not (Test-Path -LiteralPath $statePath)) {
        throw 'The switch request state is missing.'
    }

    $state = Get-Content -LiteralPath $statePath -Raw | ConvertFrom-Json
    $pending = $state.pendingRequest
    if (-not $pending -or
        $pending.outcome -ne 'automatic_switch_queued' -or
        $pending.requestId -notmatch '^[a-f0-9-]{36}$' -or
        $pending.sourceEnvironment -notin @('local', 'remote') -or
        $pending.targetEnvironment -notin @('local', 'remote') -or
        $pending.sourceEnvironment -eq $pending.targetEnvironment) {
        throw 'The saved switch request is invalid.'
    }

    return $pending
}

function Invoke-Artisan([string[]]$Arguments) {
    Push-Location $repoRoot
    try {
        & $phpCommand artisan @Arguments *> $null
        if ($LASTEXITCODE -ne 0) {
            Write-SwitchLog ('Laravel command failed: {0}' -f $Arguments[0])
            throw 'A required Laravel maintenance command failed.'
        }
    } finally {
        Pop-Location
    }
}

function Set-SwitchStage([string]$Stage) {
    Invoke-Artisan @('complitrack:database-switch:stage', $requestId, $Stage)
    $script:currentStage = $Stage
    Write-SwitchLog ('Stage: {0}' -f $Stage)
}

function Test-ManagedProcess([string]$PidFile, [string]$RequiredCommand) {
    if (-not (Test-Path -LiteralPath $PidFile)) { return $false }
    $processId = 0
    if (-not [int]::TryParse((Get-Content -LiteralPath $PidFile -Raw).Trim(), [ref]$processId) -or $processId -le 0) {
        return $false
    }

    $process = Get-CimInstance Win32_Process -Filter "ProcessId = $processId" -ErrorAction SilentlyContinue
    if (-not $process) { return $false }
    $commandLine = [string]$process.CommandLine
    return $commandLine.IndexOf($repoRoot, [StringComparison]::OrdinalIgnoreCase) -ge 0 -and
        $commandLine.IndexOf($RequiredCommand, [StringComparison]::OrdinalIgnoreCase) -ge 0
}

function Assert-ManagedServices {
    if (-not (Test-ManagedProcess (Join-Path $repoRoot '.laravel.pid') 'server.php')) {
        throw 'The Laravel server is not managed by start-complitrack.ps1.'
    }
    if (-not (Test-ManagedProcess (Join-Path $repoRoot '.realtime.pid') 'realtime')) {
        throw 'The realtime service is not managed by start-complitrack.ps1.'
    }
    if (-not (Test-ManagedProcess (Join-Path $repoRoot '.scheduler.pid') 'schedule:work')) {
        throw 'The scheduler is not managed by start-complitrack.ps1.'
    }

    $queueWorkers = @(Get-CimInstance Win32_Process | Where-Object {
        $line = [string]$_.CommandLine
        $line.IndexOf($repoRoot, [StringComparison]::OrdinalIgnoreCase) -ge 0 -and
            $line -match 'artisan.+queue:(work|listen)'
    })
    if ($queueWorkers.Count -gt 0) {
        throw 'An unmanaged Laravel queue worker is running; automatic switching was stopped.'
    }
}

function Set-DotEnvValues([hashtable]$Values) {
    $encoding = New-Object System.Text.UTF8Encoding($false)
    $lines = [System.Collections.Generic.List[string]]::new()
    foreach ($line in [System.IO.File]::ReadAllLines($envPath)) {
        $lines.Add($line)
    }

    foreach ($key in $Values.Keys) {
        $pattern = '^\s*' + [regex]::Escape($key) + '\s*='
        $indexes = @()
        for ($index = 0; $index -lt $lines.Count; $index++) {
            if ($lines[$index] -match $pattern) {
                $indexes += $index
            }
        }
        if ($indexes.Count -gt 1) {
            throw "The .env key $key is duplicated."
        }

        $line = "$key=$($Values[$key])"
        if ($indexes.Count -eq 1) {
            $lines[$indexes[0]] = $line
        } else {
            $lines.Add($line)
        }
    }

    $temporaryPath = Join-Path (Split-Path -Parent $envPath) ('.env.switch-' + $requestId + '.tmp')
    $backupPath = Join-Path (Split-Path -Parent $envPath) ('.env.switch-' + $requestId + '.bak')
    try {
        [System.IO.File]::WriteAllLines($temporaryPath, $lines.ToArray(), $encoding)
        [System.IO.File]::Replace($temporaryPath, $envPath, $backupPath)
    } finally {
        if (Test-Path -LiteralPath $temporaryPath) {
            Remove-Item -LiteralPath $temporaryPath -Force
        }
        if (Test-Path -LiteralPath $backupPath) {
            Remove-Item -LiteralPath $backupPath -Force
        }
    }
}

function Set-ActiveEnvironment([string]$Environment) {
    Set-DotEnvValues @{ 'MONGODB_ACTIVE_ENVIRONMENT' = $Environment }
}

function Start-ManagedServices {
    try {
        & (Join-Path $repoRoot 'start-complitrack.ps1') -DatabaseSwitchRestart
    } catch {
        Write-SwitchLog ('Managed service startup script failed: {0}' -f $_.Exception.GetType().Name)
        throw
    }
}

function Stop-ManagedServices {
    $script:servicesStopped = $true
    try {
        & (Join-Path $repoRoot 'stop-complitrack.ps1')
    } catch {
        Write-SwitchLog ('Managed service stop script failed: {0}' -f $_.Exception.GetType().Name)
        throw
    }
    $deadline = [DateTime]::UtcNow.AddSeconds(30)
    while ([DateTime]::UtcNow -lt $deadline) {
        $listeners = @(Get-NetTCPConnection -State Listen -LocalPort 8000,4000,5173 -ErrorAction SilentlyContinue)
        if ($listeners.Count -eq 0) {
            return
        }
        Start-Sleep -Seconds 1
    }
    throw 'Managed CompliTrack services did not stop.'
}

function Test-ServicesHealthy([string]$Environment) {
    $deadline = [DateTime]::UtcNow.AddSeconds(90)
    $lastFailedCheck = 'managed_processes'
    $lastFailureType = 'not_ready'
    while ([DateTime]::UtcNow -lt $deadline) {
        $laravelManaged = Test-ManagedProcess (Join-Path $repoRoot '.laravel.pid') 'server.php'
        $realtimeManaged = Test-ManagedProcess (Join-Path $repoRoot '.realtime.pid') 'realtime'
        $schedulerManaged = Test-ManagedProcess (Join-Path $repoRoot '.scheduler.pid') 'schedule:work'
        if (-not $laravelManaged -or -not $realtimeManaged -or -not $schedulerManaged) {
            $missing = @()
            if (-not $laravelManaged) { $missing += 'laravel' }
            if (-not $realtimeManaged) { $missing += 'realtime' }
            if (-not $schedulerManaged) { $missing += 'scheduler' }
            $lastFailedCheck = 'managed_processes'
            $lastFailureType = [string]::Join(',', $missing)
            Start-Sleep -Seconds 2
            continue
        }

        $check = 'mongodb_ping'
        try {
            Invoke-Artisan @('mongodb:check', '--ping')
            $check = 'web_http'
            $web = Invoke-WebRequest -Uri 'http://127.0.0.1:8000/' -UseBasicParsing -TimeoutSec 4
            $check = 'realtime_http'
            $realtime = Invoke-WebRequest -Uri 'http://127.0.0.1:4000/health' -UseBasicParsing -TimeoutSec 4
            if ($web.StatusCode -ge 200 -and $web.StatusCode -lt 400 -and
                $realtime.StatusCode -ge 200 -and $realtime.StatusCode -lt 300) {
                $check = 'target_probe'
                Invoke-Artisan @('complitrack:database-switch:probe', $Environment)
                return $true
            }
            $lastFailedCheck = 'http_status'
            $lastFailureType = 'web={0};realtime={1}' -f $web.StatusCode, $realtime.StatusCode
        } catch {
            $lastFailedCheck = $check
            $lastFailureType = $_.Exception.GetType().Name
            Start-Sleep -Seconds 2
        }
    }

    Write-SwitchLog ('Health verification timed out for {0}; check={1}; failure={2}' -f $Environment, $lastFailedCheck, $lastFailureType)
    return $false
}

function Clear-ChildMongoEnvironment {
    foreach ($name in @(
        'MONGODB_URI',
        'MONGODB_DATABASE',
        'MONGODB_ACTIVE_ENVIRONMENT',
        'MONGODB_LOCAL_URI',
        'MONGODB_LOCAL_DATABASE',
        'MONGODB_REMOTE_URI',
        'MONGODB_REMOTE_DATABASE',
        'JWT_SECRET',
        'JWT_ALGO',
        'SESSION_DRIVER',
        'SESSION_CONNECTION',
        'SESSION_TABLE',
        'CACHE_STORE',
        'CACHE_DRIVER',
        'CACHE_PREFIX',
        'COMPLITRACK_CACHE_NAMESPACE'
    )) {
        Remove-Item -LiteralPath "Env:$name" -ErrorAction SilentlyContinue
    }
}

try {
    Start-Sleep -Seconds 2
    Clear-ChildMongoEnvironment
    $pending = Get-PendingRequest
    $requestId = $pending.requestId
    $source = $pending.sourceEnvironment
    $target = $pending.targetEnvironment
    Set-SwitchStage 'preflight'

    Assert-ManagedServices
    $jwtAlgorithm = 'HS256'
    foreach ($line in [System.IO.File]::ReadAllLines($envPath)) {
        if ($line -match '^\s*JWT_ALGO\s*=\s*(.*?)\s*$') {
            $jwtAlgorithm = $Matches[1].Trim().Trim('"').Trim("'")
        }
    }
    if ($jwtAlgorithm -notin @('HS256', 'HS384', 'HS512')) {
        throw 'Automatic logout requires a symmetric JWT algorithm.'
    }

    Invoke-Artisan @('complitrack:database-switch:probe', $source)
    Invoke-Artisan @('complitrack:database-switch:probe', $target)
    Set-SwitchStage 'stopping_services'
    Stop-ManagedServices
    Start-Sleep -Seconds 2

    Set-SwitchStage 'changing_target'
    Set-ActiveEnvironment $target
    $configurationChanged = $true
    Invoke-Artisan @('config:clear')
    Set-SwitchStage 'verifying_target'
    Invoke-Artisan @('mongodb:check', '--ping')
    Invoke-Artisan @('complitrack:database-switch:probe', $target)

    Set-SwitchStage 'invalidating_logins'
    $randomBytes = New-Object byte[] 64
    $randomGenerator = [System.Security.Cryptography.RandomNumberGenerator]::Create()
    try {
        $randomGenerator.GetBytes($randomBytes)
        $newJwtSecret = [BitConverter]::ToString($randomBytes).Replace('-', '').ToLowerInvariant()
    } finally {
        $randomGenerator.Dispose()
    }
    Set-DotEnvValues @{
        'JWT_SECRET' = $newJwtSecret
        'COMPLITRACK_CACHE_NAMESPACE' = [Guid]::NewGuid().ToString('N')
    }
    Invoke-Artisan @('complitrack:logout-all', '--all-mongodb-targets')
    Set-SwitchStage 'starting_services'
    Start-ManagedServices
    if (-not (Test-ServicesHealthy $target)) {
        throw 'The target application services did not become healthy.'
    }

    Invoke-Artisan @('complitrack:database-switch:resolve', $requestId, 'switch_verified')
    Write-SwitchLog 'Database switch completed and target services verified.'
} catch {
    $switchFailed = $true
    $failureStage = $currentStage
    Write-SwitchLog ('Switch failed during stage: {0}' -f $failureStage)
    if ($requestId) {
        try {
            if ($servicesStopped -or $configurationChanged) {
                Set-SwitchStage 'rolling_back'
                if ($servicesStopped) {
                    Stop-ManagedServices
                }
                if ($configurationChanged) {
                    Set-ActiveEnvironment $source
                    Invoke-Artisan @('config:clear')
                    Invoke-Artisan @('mongodb:check', '--ping')
                }
                Start-ManagedServices
                if (-not (Test-ServicesHealthy $source)) {
                    throw 'The previous CompliTrack services did not become healthy.'
                }
                Invoke-Artisan @('complitrack:database-switch:resolve', $requestId, 'rolled_back')
                Write-SwitchLog 'Database switch failed; previous target restored and verified.'
            } else {
                Invoke-Artisan @('complitrack:database-switch:resolve', $requestId, 'cancelled')
                Write-SwitchLog 'Database switch cancelled during preflight; active target was unchanged.'
            }
        } catch {
            try {
                Set-SwitchStage 'rollback_failed'
            } catch {
                Write-SwitchLog 'Unable to persist the rollback-failed stage.'
            }
            Write-SwitchLog 'Database switch and rollback did not complete; operator intervention is required.'
        }
    } else {
        Write-SwitchLog 'Database switch worker failed before loading a valid request.'
    }
}

if ($switchFailed) {
    exit 1
}
