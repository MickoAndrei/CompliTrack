$ErrorActionPreference = 'Stop'
$documentRoot = $PSScriptRoot
$dockerCommand = Get-Command docker -ErrorAction SilentlyContinue
if (-not $dockerCommand) {
    $dockerExecutable = 'C:/Program Files/Docker/Docker/resources/bin/docker.exe'
    if (Test-Path -LiteralPath $dockerExecutable) { $dockerCommand = Get-Item -LiteralPath $dockerExecutable }
}
if (-not $dockerCommand) { throw 'Install Docker Desktop with WSL 2, restart Windows if prompted, then run this script again.' }
$dockerExecutable = if ($dockerCommand.Source) { $dockerCommand.Source } else { $dockerCommand.FullName }
$privateDirectory = Join-Path $documentRoot 'storage/app/private'
New-Item -ItemType Directory -Force -Path $privateDirectory | Out-Null
$secretFile = Join-Path $privateDirectory 'onlyoffice.env'
if (-not (Test-Path -LiteralPath $secretFile)) {
    $randomBytes = New-Object byte[] 48
    $randomGenerator = [Security.Cryptography.RandomNumberGenerator]::Create()
    try { $randomGenerator.GetBytes($randomBytes) } finally { $randomGenerator.Dispose() }
    $documentSecret = [Convert]::ToBase64String($randomBytes)
    [IO.File]::WriteAllText($secretFile, "JWT_SECRET=$documentSecret`n", [Text.UTF8Encoding]::new($false))
}
$secretText = [IO.File]::ReadAllText($secretFile)
$documentSecret = [regex]::Match($secretText, '(?m)^JWT_SECRET=(.+)$').Groups[1].Value.Trim()
if (-not $documentSecret) { throw 'The private document server JWT secret is missing.' }
Push-Location $documentRoot
try {
    & $dockerExecutable compose -f docker-compose.documents.yml up -d --wait --wait-timeout 600
    if ($LASTEXITCODE -ne 0) { throw 'Document server did not become healthy. Check Docker Desktop and container logs.' }
    $envPath = Join-Path $documentRoot '.env'
    $envText = [IO.File]::ReadAllText($envPath)
    $publicAppUrl = [regex]::Match($envText, '(?m)^APP_URL=(.+)$').Groups[1].Value.Trim().Trim('"').TrimEnd('/')
    $browserDocumentUrl = if ($publicAppUrl.StartsWith('https://')) { $publicAppUrl+'/document-server' } else { 'http://localhost:8082' }
    foreach ($entry in @{ ONLYOFFICE_URL=$browserDocumentUrl; ONLYOFFICE_INTERNAL_URL='http://127.0.0.1:8082'; ONLYOFFICE_APP_URL='http://host.docker.internal:8000'; ONLYOFFICE_JWT_SECRET=$documentSecret }.GetEnumerator()) {
        $pattern = '(?m)^'+[regex]::Escape($entry.Key)+'=[^\r\n]*'
        if ([regex]::IsMatch($envText, $pattern)) { $envText = [regex]::Replace($envText, $pattern, ($entry.Key+'='+$entry.Value)) }
        else { $envText += "`r`n"+$entry.Key+'='+$entry.Value }
    }
    [IO.File]::WriteAllText($envPath, $envText, [Text.UTF8Encoding]::new($false))
    & php artisan config:clear
    Write-Output 'Document server is ready at http://localhost:8082. Local Laravel must run on port 8000.'
} finally { Pop-Location }
