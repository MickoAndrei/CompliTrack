$ErrorActionPreference = 'Stop'
$workspace = Split-Path -Parent (Split-Path -Parent $PSScriptRoot)
$workerFile = Join-Path $workspace 'scripts/database-switch.ps1'
$parseTokens = $null
$parseErrors = $null
$workerAst = [System.Management.Automation.Language.Parser]::ParseFile($workerFile, [ref]$parseTokens, [ref]$parseErrors)
if ($parseErrors.Count) { throw 'Database switch worker has syntax errors.' }

# Exercise the real worker and .env replacement with isolated files. Replace only
# process control, sleep, and database commands; never stop this workspace's services.
foreach ($definition in $workerAst.FindAll({ param($node) $node -is [System.Management.Automation.Language.FunctionDefinitionAst] }, $false)) {
    . ([scriptblock]::Create($definition.Extent.Text))
}
$workerBody = $workerAst.EndBlock.Statements | Where-Object { $_ -is [System.Management.Automation.Language.TryStatementAst] } | Select-Object -Last 1
if (-not $workerBody) { throw 'Worker execution block was not found.' }

function Start-Sleep { param([int]$Seconds, [int]$Milliseconds) }
function Assert-ManagedServices {}
function Stop-ManagedServices { $script:servicesStopped = $true; $script:stops++ }
function Start-ManagedServices { $script:starts++ }
function Test-ServicesHealthy([string]$Environment) { return -not ($script:failTarget -and $Environment -eq 'remote') }
function Invoke-Artisan([string[]]$Arguments) { $script:commands.Add(($Arguments -join ' ')) }

foreach ($scenario in @('success', 'rollback')) {
    $repoRoot = Join-Path $workspace ('storage/framework/testing/database-switch-worker-' + [guid]::NewGuid().ToString('N'))
    $envPath = Join-Path $repoRoot '.env'
    $statePath = Join-Path $repoRoot 'database-switch-state.json'
    $logPath = Join-Path $repoRoot 'switch.log'
    New-Item -ItemType Directory -Path $repoRoot -Force | Out-Null
    [IO.File]::WriteAllText($envPath, "MONGODB_ACTIVE_ENVIRONMENT=local`nJWT_ALGO=HS256`nAPP_NAME=Preserved`n")
    $pending = @{ requestId = [guid]::NewGuid().ToString(); sourceEnvironment = 'local'; targetEnvironment = 'remote'; outcome = 'automatic_switch_queued' }
    [IO.File]::WriteAllText($statePath, (@{ pendingRequest = $pending } | ConvertTo-Json))
    $requestId = $null; $source = $null; $target = $null; $currentStage = 'queued'
    $servicesStopped = $false; $configurationChanged = $false; $switchFailed = $false
    $stops = 0; $starts = 0; $failTarget = $scenario -eq 'rollback'
    $commands = [System.Collections.Generic.List[string]]::new()
    if ($scenario -eq 'success') {
        $lockedLog = [IO.File]::Open($logPath, [IO.FileMode]::Create, [IO.FileAccess]::ReadWrite, [IO.FileShare]::None)
        try { Write-SwitchLog 'Log contention must not stop the switch.' } finally { $lockedLog.Dispose() }
    }
    . ([scriptblock]::Create($workerBody.Extent.Text))

    $expected = if ($failTarget) { 'local' } else { 'remote' }
    $outcome = if ($failTarget) { 'rolled_back' } else { 'switch_verified' }
    $saved = [IO.File]::ReadAllText($envPath)
    if ($saved -notmatch "(?m)^MONGODB_ACTIVE_ENVIRONMENT=$expected`r?$") { throw "Wrong active target after $scenario" }
    if ($saved -notmatch '(?m)^APP_NAME=Preserved\r?$') { throw 'Existing environment values changed.' }
    if (-not ($commands | Where-Object { $_ -eq "complitrack:database-switch:resolve $($pending.requestId) $outcome" })) { throw "Missing verified outcome after $scenario" }
    if ($starts -ne $(if ($failTarget) { 2 } else { 1 })) { throw 'Services were not restarted as expected.' }
    if ($commands | Where-Object { $_ -match 'migrate|db:seed' }) { throw 'Switch unexpectedly ran migrations or seeders.' }
    if ($switchFailed -ne $failTarget) { throw 'Incorrect worker exit outcome.' }
    Write-Output "PASS: automatic switch $scenario"

    $resolvedFixture = [IO.Path]::GetFullPath($repoRoot)
    $allowedFixtures = [IO.Path]::GetFullPath((Join-Path $workspace 'storage/framework/testing')) + [IO.Path]::DirectorySeparatorChar
    if (-not $resolvedFixture.StartsWith($allowedFixtures, [StringComparison]::OrdinalIgnoreCase)) { throw 'Fixture cleanup escaped testing directory.' }
    Remove-Item -LiteralPath $resolvedFixture -Recurse -Force
}
