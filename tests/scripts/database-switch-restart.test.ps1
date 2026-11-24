$ErrorActionPreference = 'Stop'
$workspace = Split-Path -Parent (Split-Path -Parent $PSScriptRoot)
$fixture = Join-Path $workspace ('storage/framework/testing/switch-restart-' + [guid]::NewGuid().ToString('N'))
$starts = [System.Collections.Generic.List[string]]::new()
$commands = [System.Collections.Generic.List[string]]::new()
try {
    foreach ($directory in @('scripts', 'vendor', 'realtime/node_modules', 'public/build')) {
        New-Item -ItemType Directory -Force -Path (Join-Path $fixture $directory) | Out-Null
    }
    foreach ($file in @('vendor/autoload.php', 'public/build/manifest.json')) {
        New-Item -ItemType File -Path (Join-Path $fixture $file) | Out-Null
    }
    "MONGODB_ACTIVE_ENVIRONMENT=remote`nMONGODB_REMOTE_URI=mongodb://remote.example.test`nMONGODB_REMOTE_DATABASE=test" | Set-Content -LiteralPath (Join-Path $fixture '.env')
    'function Start-CompliTrackPhpServer { param($RepoRoot,$Port,$ServerHost,$EnableOpcache) return @{ Id = 12345 } }' | Set-Content -LiteralPath (Join-Path $fixture 'scripts/php-development-server.ps1')
    Copy-Item -LiteralPath (Join-Path $workspace 'start-complitrack.ps1') -Destination (Join-Path $fixture 'start-complitrack.ps1')
    function Get-NetTCPConnection { param($State,$LocalPort,$ErrorAction) return $null }
    function Start-Process {
        param($FilePath,$ArgumentList,$WorkingDirectory,$WindowStyle,$RedirectStandardOutput,$RedirectStandardError,[switch]$PassThru)
        $starts.Add("$FilePath $ArgumentList")
        return @{ Id = 12345 }
    }
    function php { $commands.Add(($args -join ' ')); $global:LASTEXITCODE = 0 }
    function composer { throw 'Controlled restart must not run Composer.' }
    function npm { throw 'Controlled restart must not run npm.' }
    function npm.cmd { throw 'Controlled restart must not run npm.' }
    $previousLocation = Get-Location
    & (Join-Path $fixture 'start-complitrack.ps1') -DatabaseSwitchRestart
    if ($starts.Count -ne 2) { throw 'Expected only realtime and scheduler starts; Vite must not start.' }
    if ($starts[0] -notmatch 'node.+realtime.+src.server.js') { throw 'Realtime must start directly through Node.' }
    if ($starts | Where-Object { $_ -match 'npm|vite|install|build' }) { throw 'Restart invoked frontend tooling.' }
    if ($commands | Where-Object { $_ -match 'migrate|db:seed|logout-all|key:generate| -m|version' }) { throw 'Restart repeated installation or bootstrap tasks.' }
    if (-not ($commands -contains 'artisan mongodb:check --ping')) { throw 'Restart must verify MongoDB connectivity.' }
    if (Test-Path -LiteralPath (Join-Path $fixture 'public/hot')) { throw 'Restart must use built assets.' }
    Write-Output 'PASS: database switch restart uses built assets and direct Node without npm, Vite, installations, or seeders.'
} finally {
    if ($previousLocation) { Set-Location $previousLocation }
    $resolved = [IO.Path]::GetFullPath($fixture)
    $allowed = [IO.Path]::GetFullPath((Join-Path $workspace 'storage/framework/testing')) + [IO.Path]::DirectorySeparatorChar
    if (-not $resolved.StartsWith($allowed, [StringComparison]::OrdinalIgnoreCase)) { throw 'Unsafe fixture cleanup path.' }
    Remove-Item -LiteralPath $resolved -Recurse -Force
}
