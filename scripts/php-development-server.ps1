function Start-CompliTrackPhpServer {
    param(
        [Parameter(Mandatory = $true)][string]$RepoRoot,
        [int]$Port = 8000,
        [string]$ServerHost = '127.0.0.1',
        [bool]$EnableOpcache = $true,
        [switch]$EnableDiagnostics
    )
    $phpExecutable = (Get-Command php -ErrorAction Stop).Source
    $phpArgs = @()
    if ($EnableOpcache) {
        & $phpExecutable --ri 'Zend OPcache' *> $null
        $extensionArgs = @()
        if ($LASTEXITCODE -ne 0) { $extensionArgs = @('-d','zend_extension=opcache') }
        & $phpExecutable @extensionArgs -d opcache.enable_cli=1 --ri 'Zend OPcache' *> $null
        if ($LASTEXITCODE -eq 0) {
            $phpArgs = $extensionArgs + @('-d','opcache.enable_cli=1','-d','opcache.validate_timestamps=1','-d','opcache.revalidate_freq=0')
            Write-Host 'PHP OPcache enabled for this server; PHP file changes are checked on every request.'
        } else { Write-Warning 'OPcache is unavailable; starting without it. Global php.ini was not changed.' }
    }
    $router = Join-Path $RepoRoot 'vendor/laravel/framework/src/Illuminate/Foundation/resources/server.php'
    $savedToken = $env:COMPLITRACK_DIAGNOSTIC_TOKEN
    try {
        $env:COMPLITRACK_DIAGNOSTIC_TOKEN = $null
        if ($EnableDiagnostics) {
            $router = Join-Path $RepoRoot 'scripts/performance/router.php'
            $tokenPath = Join-Path $RepoRoot 'storage/framework/testing/phase2-token.txt'
            if (-not (Test-Path -LiteralPath $tokenPath)) {
                New-Item -ItemType Directory -Force -Path (Split-Path -Parent $tokenPath) | Out-Null
                [IO.File]::WriteAllText($tokenPath, [guid]::NewGuid().ToString('N'))
            }
            $env:COMPLITRACK_DIAGNOSTIC_TOKEN = [IO.File]::ReadAllText($tokenPath)
        }
        $phpArgs += @('-S',"${ServerHost}:$Port",('"'+$router+'"'))
        $logDirectory = Join-Path $RepoRoot 'logs'
        New-Item -ItemType Directory -Force -Path $logDirectory | Out-Null
        return Start-Process -FilePath $phpExecutable -ArgumentList $phpArgs -WorkingDirectory (Join-Path $RepoRoot 'public') -WindowStyle Hidden -PassThru -RedirectStandardOutput (Join-Path $logDirectory "php-$Port.log") -RedirectStandardError (Join-Path $logDirectory "php-$Port.err.log")
    } finally { $env:COMPLITRACK_DIAGNOSTIC_TOKEN = $savedToken }
}
