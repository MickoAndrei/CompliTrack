param([string]$Domain = 'undercook-ecosystem-detract.ngrok-free.dev')
$ErrorActionPreference = 'Stop'
if ($Domain -notmatch '^[a-z0-9-]+\.(ngrok-free\.dev|ngrok\.app)$') { throw 'Use the hostname assigned to your ngrok account.' }
$repoRoot = $PSScriptRoot
$privateDir = Join-Path $repoRoot 'storage/app/private/ngrok'
New-Item -ItemType Directory -Force -Path $privateDir | Out-Null
$envPath = Join-Path $repoRoot '.env'
$envText = [IO.File]::ReadAllText($envPath)
foreach ($entry in @{ APP_URL="https://$Domain"; APP_DEBUG='false' }.GetEnumerator()) {
    $pattern = '(?m)^'+[regex]::Escape($entry.Key)+'=[^\r\n]*'
    if ([regex]::IsMatch($envText, $pattern)) { $envText = [regex]::Replace($envText, $pattern, ($entry.Key+'='+$entry.Value)) }
    else { $envText += "`r`n"+$entry.Key+'='+$entry.Value }
}
[IO.File]::WriteAllText($envPath, $envText)
Push-Location $repoRoot
try {
    & php artisan config:clear
    if ($LASTEXITCODE -ne 0) { throw 'Could not clear Laravel configuration.' }
    $hotFile = Join-Path $repoRoot 'public/hot'
    if (Test-Path -LiteralPath $hotFile) {
        Move-Item -LiteralPath $hotFile -Destination (Join-Path $privateDir ('vite-hot-'+[DateTime]::UtcNow.ToString('yyyyMMddHHmmss')+'.backup'))
    }
    if (-not (Get-NetTCPConnection -LocalPort 8000 -State Listen -ErrorAction SilentlyContinue)) {
        $appProcess = Start-Process -FilePath (Get-Command php).Source -ArgumentList @('artisan','serve','--host=127.0.0.1','--port=8000') -WorkingDirectory $repoRoot -WindowStyle Hidden -PassThru
        $appProcess.Id | Set-Content -LiteralPath (Join-Path $privateDir 'laravel.pid')
    }
    if (-not (Get-NetTCPConnection -LocalPort 8080 -State Listen -ErrorAction SilentlyContinue)) {
        $savedAppUpstream = $env:COMPLITRACK_APP_UPSTREAM
        $savedRealtimeUpstream = $env:COMPLITRACK_REALTIME_UPSTREAM
        try {
            $env:COMPLITRACK_APP_UPSTREAM = 'http://127.0.0.1:8000'
            $env:COMPLITRACK_REALTIME_UPSTREAM = 'http://127.0.0.1:4000'
            $proxy = Start-Process -FilePath (Get-Command node).Source -ArgumentList @('realtime/src/devProxy.js') -WorkingDirectory $repoRoot -WindowStyle Hidden -PassThru
            $proxy.Id | Set-Content -LiteralPath (Join-Path $privateDir 'proxy.pid')
        } finally {
            $env:COMPLITRACK_APP_UPSTREAM = $savedAppUpstream
            $env:COMPLITRACK_REALTIME_UPSTREAM = $savedRealtimeUpstream
        }
    }
    if (Get-Process ngrok -ErrorAction SilentlyContinue) { throw 'An ngrok agent is already running. Check its endpoint before starting a second agent.' }
    $agent = Start-Process -FilePath (Get-Command ngrok).Source -ArgumentList @('http','8080',"--url=https://$Domain",'--inspect=false','--log=stdout','--log-format=json') -WorkingDirectory $repoRoot -WindowStyle Hidden -PassThru -RedirectStandardOutput (Join-Path $privateDir 'agent.log') -RedirectStandardError (Join-Path $privateDir 'agent.err.log')
    $agent.Id | Set-Content -LiteralPath (Join-Path $privateDir 'agent.pid')
    Write-Output "ngrok started for https://$Domain (PID $($agent.Id)). Request inspection is disabled."
} finally { Pop-Location }
