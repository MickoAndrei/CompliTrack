$ErrorActionPreference = 'Stop'
$workspace = Split-Path -Parent (Split-Path -Parent $PSScriptRoot)
$fixture = Join-Path $workspace ('storage/framework/testing/switch-launcher-' + [guid]::NewGuid().ToString('N'))
$phpServer = $null
$workerProcess = $null
New-Item -ItemType Directory -Path $fixture -Force | Out-Null
try {
    # Use the production launcher from inside a real PHP HTTP request. The harmless
    # worker stays alive while its web server exits, just like a database switch.
    @'
param([string]$PhpExecutable)
[IO.File]::WriteAllText((Join-Path $PSScriptRoot 'worker.pid'), [string]$PID)
Start-Sleep -Seconds 120
'@ | Set-Content -LiteralPath (Join-Path $fixture 'worker.ps1')
    $router = @'
<?php
require '__WORKSPACE__/vendor/autoload.php';
$app = require '__WORKSPACE__/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$service = $app->make(App\Services\DatabaseSwitchService::class);
$method = new ReflectionMethod($service, 'workerLauncherCommand');
$encoded = $method->invoke($service, __DIR__.'/worker.ps1', __DIR__, PHP_BINARY);
$process = proc_open(['powershell.exe', '-NoProfile', '-NonInteractive', '-EncodedCommand', $encoded],
    [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, __DIR__);
fclose($pipes[0]);
$output = stream_get_contents($pipes[1]);
$error = stream_get_contents($pipes[2]);
fclose($pipes[1]); fclose($pipes[2]);
if (proc_close($process) !== 0) { http_response_code(500); echo $error; exit; }
echo 'launched';
'@
    $router.Replace('__WORKSPACE__', $workspace.Replace('\', '/').Replace("'", "\'")) | Set-Content -LiteralPath (Join-Path $fixture 'router.php')
    $reservation = [Net.Sockets.TcpListener]::new([Net.IPAddress]::Loopback, 0)
    $reservation.Start()
    $port = $reservation.LocalEndpoint.Port
    $reservation.Stop()
    $php = (Get-Command php).Source
    $phpServer = Start-Process -FilePath $php -ArgumentList @('-S', "127.0.0.1:$port", ('"' + (Join-Path $fixture 'router.php') + '"')) -WindowStyle Hidden -PassThru -WorkingDirectory $fixture -RedirectStandardOutput (Join-Path $fixture 'server.log') -RedirectStandardError (Join-Path $fixture 'server.err.log')
    $deadline = [DateTime]::UtcNow.AddSeconds(30)
    do {
        Start-Sleep -Milliseconds 250
        $listening = Get-NetTCPConnection -State Listen -LocalPort $port -ErrorAction SilentlyContinue
    } while (-not $listening -and [DateTime]::UtcNow -lt $deadline)
    $response = Invoke-WebRequest -Uri "http://127.0.0.1:$port/" -UseBasicParsing -TimeoutSec 90
    if ($response.Content -ne 'launched') { throw 'Worker launch failed.' }
    $marker = Join-Path $fixture 'worker.pid'
    $deadline = [DateTime]::UtcNow.AddSeconds(30)
    while (-not (Test-Path -LiteralPath $marker) -and [DateTime]::UtcNow -lt $deadline) { Start-Sleep -Milliseconds 250 }
    $workerId = [int]([IO.File]::ReadAllText($marker))
    $workerProcess = Get-Process -Id $workerId -ErrorAction Stop
    $workerInfo = Get-CimInstance Win32_Process -Filter "ProcessId = $workerId"
    if ($workerInfo.ParentProcessId -eq $phpServer.Id) { throw 'Worker inherited the PHP process tree.' }
    Stop-Process -InputObject $phpServer -Force
    $phpServer.WaitForExit()
    $deadline = [DateTime]::UtcNow.AddSeconds(10)
    do {
        $listening = Get-NetTCPConnection -State Listen -LocalPort $port -ErrorAction SilentlyContinue
        if (-not $listening) { break }
        Start-Sleep -Milliseconds 250
    } while ([DateTime]::UtcNow -lt $deadline)
    if ($listening) { throw 'Worker kept the PHP listening socket open.' }
    if ($workerProcess.HasExited) { throw 'Worker did not survive the PHP server shutdown.' }
    Write-Output 'PASS: HTTP-launched worker survives PHP shutdown without retaining its listening socket.'
} finally {
    if ($phpServer -and -not $phpServer.HasExited) { Stop-Process -InputObject $phpServer -Force; $phpServer.WaitForExit() }
    if ($workerProcess -and -not $workerProcess.HasExited) { Stop-Process -InputObject $workerProcess -Force; $workerProcess.WaitForExit() }
    $resolved = [IO.Path]::GetFullPath($fixture)
    $allowed = [IO.Path]::GetFullPath((Join-Path $workspace 'storage/framework/testing')) + [IO.Path]::DirectorySeparatorChar
    if (-not $resolved.StartsWith($allowed, [StringComparison]::OrdinalIgnoreCase)) { throw 'Unsafe fixture cleanup path.' }
    for ($attempt = 0; $attempt -lt 20; $attempt++) {
        try { Remove-Item -LiteralPath $resolved -Recurse -Force; break }
        catch { if ($attempt -eq 19) { throw }; Start-Sleep -Milliseconds 250 }
    }
}
