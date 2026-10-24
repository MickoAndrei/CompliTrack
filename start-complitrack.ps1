$ErrorActionPreference = "Stop"

$repoRoot = Split-Path -Parent $MyInvocation.MyCommand.Path
Set-Location $repoRoot

$logDir = Join-Path $repoRoot "logs"
New-Item -ItemType Directory -Force -Path $logDir | Out-Null

$appPort = 8000
$realtimePort = 4000
$laravelPidFile = Join-Path $repoRoot ".laravel.pid"
$vitePidFile = Join-Path $repoRoot ".vite.pid"
$realtimePidFile = Join-Path $repoRoot ".realtime.pid"

function Test-PortInUse([int]$port) {
    try {
        $listener = Get-NetTCPConnection -LocalPort $port -ErrorAction SilentlyContinue
        return $null -ne $listener
    } catch {
        return $false
    }
}

function Stop-PidFile([string]$pidFile) {
    if (-not (Test-Path $pidFile)) { return }
    $pid = Get-Content $pidFile -ErrorAction SilentlyContinue
    if ($pid) {
        $proc = Get-Process -Id $pid -ErrorAction SilentlyContinue
        if ($proc) {
            Stop-Process -Id $pid -Force -ErrorAction SilentlyContinue
            Start-Sleep -Seconds 1
        }
    }
    Remove-Item $pidFile -Force -ErrorAction SilentlyContinue
}

function Ensure-MongoRunning {
    $mongod = Get-Command mongod -ErrorAction SilentlyContinue
    if ($mongod) {
        $running = Get-Process mongod -ErrorAction SilentlyContinue
        if (-not $running) {
            $mongoData = Join-Path $repoRoot "mongodb-data"
            New-Item -ItemType Directory -Force -Path $mongoData | Out-Null

            $logPath = Join-Path $mongoData "mongod.log"
            $dbPath = Join-Path $mongoData "data"

            New-Item -ItemType Directory -Force -Path $dbPath | Out-Null

            Start-Process -FilePath $mongod.Source `
                -ArgumentList "--dbpath `"$dbPath`" --logpath `"$logPath`" --fork --bind_ip_all --port 27017" `
                -NoNewWindow -RedirectStandardOutput "$logDir\mongod.out.log" -RedirectStandardError "$logDir\mongod.err.log"
            Write-Host "MongoDB started with mongod"
        } else {
            Write-Host "MongoDB already running"
        }
        return
    }

    $docker = Get-Command docker -ErrorAction SilentlyContinue
    if ($docker) {
        $containers = docker ps --format "{{.Names}}" 2>$null
        if (-not ($containers -contains "complitrack-mongo")) {
            $mongoData = Join-Path $repoRoot "mongodb-data"
            New-Item -ItemType Directory -Force -Path $mongoData | Out-Null

            docker rm -f complitrack-mongo 2>$null | Out-Null
            docker run -d `
                --name complitrack-mongo `
                -p 27017:27017 `
                -v "${mongoData}:/data/db" `
                mongo:7 `
                --bind_ip_all | Out-Null

            Write-Host "MongoDB container started"
        } else {
            Write-Host "MongoDB container already running"
        }
        return
    }

    Write-Warning "MongoDB not installed and Docker not available."
}

Write-Host "Checking required tools..."
$commands = @("php", "composer", "node", "npm")
foreach ($cmd in $commands) {
    $found = Get-Command $cmd -ErrorAction SilentlyContinue
    if (-not $found) { throw "$cmd is not installed or not in PATH" }
    Write-Host "  [OK] $cmd found"
}

$phpVersion = (php -r "echo PHP_VERSION;")
if ([version]$phpVersion -lt [version]"8.2.0") {
    throw "PHP 8.2+ is required. Detected: $phpVersion"
}
Write-Host "  [OK] PHP version: $phpVersion"

$nodeVersion = (node -p "process.versions.node")
if ([version]$nodeVersion -lt [version]"20.0.0") {
    throw "Node.js 20+ is required. Detected: $nodeVersion"
}
Write-Host "  [OK] Node.js version: $nodeVersion"

Write-Host ""
Write-Host "Checking PHP extension set..."
$requiredExts = @("pdo", "pdo_sqlite", "sqlite3", "mbstring", "openssl", "tokenizer", "json", "ctype", "fileinfo", "xml", "dom", "curl")
foreach ($ext in $requiredExts) {
    $mods = php -m
    if ($mods -contains $ext) {
        Write-Host "  [OK] PHP extension: $ext"
    } else {
        Write-Warning "PHP extension missing: $ext"
    }
}

if (-not (Test-Path (Join-Path $repoRoot ".env"))) {
    if (Test-Path (Join-Path $repoRoot ".env.example")) {
        Copy-Item (Join-Path $repoRoot ".env.example") (Join-Path $repoRoot ".env")
        Write-Host "  [OK] Created .env from .env.example"
    } else {
        @"
APP_NAME=CompliTrack
APP_ENV=local
APP_KEY=
APP_DEBUG=true
APP_URL=http://localhost:8000

LOG_CHANNEL=stack
LOG_LEVEL=debug

DB_CONNECTION=sqlite
DB_DATABASE=$repoRoot\database\database.sqlite
DB_FOREIGN_KEYS=true

CACHE_DRIVER=file
QUEUE_CONNECTION=sync
SESSION_DRIVER=file
SESSION_LIFETIME=120

MONGODB_URI=mongodb://127.0.0.1:27017/?directConnection=true
MONGODB_DATABASE=complitrack

BROADCAST_DRIVER=log
FILESYSTEM_DISK=local
MAIL_MAILER=log
"@ | Set-Content (Join-Path $repoRoot ".env")
        Write-Host "  [OK] Created default .env"
    }
}

$databaseDir = Join-Path $repoRoot "database"
New-Item -ItemType Directory -Force -Path $databaseDir | Out-Null
$databaseFile = Join-Path $databaseDir "database.sqlite"
if (-not (Test-Path $databaseFile)) { New-Item -ItemType File -Path $databaseFile -Force | Out-Null }

if (Test-Path (Join-Path $repoRoot "realtime")) {
    if (-not (Test-Path (Join-Path $repoRoot "realtime\.env"))) {
        if (Test-Path (Join-Path $repoRoot "realtime\.env.example")) {
            Copy-Item (Join-Path $repoRoot "realtime\.env.example") (Join-Path $repoRoot "realtime\.env")
            Write-Host "  [OK] Created realtime\.env"
        } else {
            @"
MONGODB_URI=mongodb://127.0.0.1:27017/?directConnection=true
MONGODB_DATABASE=complitrack
PORT=4000
SOCKET_CORS_ORIGIN=http://localhost:8000
FIREBASE_CREDENTIALS=
"@ | Set-Content (Join-Path $repoRoot "realtime\.env")
            Write-Host "  [OK] Created default realtime\.env"
        }
    }
}

Write-Host ""
Write-Host "Installing dependencies..."
if (-not (Test-Path (Join-Path $repoRoot "vendor"))) {
    composer install --no-interaction --prefer-dist
    Write-Host "  [OK] Installed PHP dependencies"
} else {
    Write-Host "  [OK] PHP dependencies already installed"
}

if (-not (Test-Path (Join-Path $repoRoot "node_modules"))) {
    npm install
    Write-Host "  [OK] Installed Node dependencies"
} else {
    Write-Host "  [OK] Node dependencies already installed"
}

if (Test-Path (Join-Path $repoRoot "realtime")) {
    $rtNodeModules = Join-Path $repoRoot "realtime\node_modules"
    if (-not (Test-Path $rtNodeModules)) {
        Push-Location (Join-Path $repoRoot "realtime")
        npm install
        Pop-Location
        Write-Host "  [OK] Installed realtime dependencies"
    } else {
        Write-Host "  [OK] Realtime dependencies already installed"
    }
}

if (-not ((Get-Content (Join-Path $repoRoot ".env")) -match '^APP_KEY=') -or ((Get-Content (Join-Path $repoRoot ".env")) -match '^APP_KEY=$')) {
    php artisan key:generate --force
    Write-Host "  [OK] Generated Laravel APP_KEY"
} else {
    Write-Host "  [OK] APP_KEY already present"
}

Ensure-MongoRunning

Write-Host ""
Write-Host "Running Laravel migrations..."
php artisan migrate --force

if (Test-PortInUse $appPort) {
    Write-Host "Laravel already running on port $appPort"
} else {
    Stop-PidFile $laravelPidFile
    Write-Host "Starting Laravel on port $appPort..."
    $p = Start-Process -FilePath "php" -ArgumentList "artisan serve --host=0.0.0.0 --port=$appPort" -WorkingDirectory $repoRoot -RedirectStandardOutput "$logDir\laravel.log" -RedirectStandardError "$logDir\laravel.err.log" -PassThru
    $p.Id | Set-Content $laravelPidFile
}

if (Test-PortInUse $realtimePort) {
    Write-Host "Realtime already running on port $realtimePort"
} else {
    Stop-PidFile $realtimePidFile
    Write-Host "Starting realtime service..."
    $realtimePath = Join-Path $repoRoot "realtime"
    $p = Start-Process -FilePath "npm" -ArgumentList "start" -WorkingDirectory $realtimePath -RedirectStandardOutput "$logDir\realtime.log" -RedirectStandardError "$logDir\realtime.err.log" -PassThru
    $p.Id | Set-Content $realtimePidFile
}

Write-Host ""
Write-Host "Building frontend..."
npm run build > "$logDir\frontend-build.log" 2>&1
Write-Host "  [OK] Frontend build complete"

if (Test-PortInUse 5173) {
    Write-Host "Vite dev server already running"
} else {
    Stop-PidFile $vitePidFile
    Write-Host "Starting Vite dev server..."
    $p = Start-Process -FilePath "npm" -ArgumentList "run dev" -WorkingDirectory $repoRoot -RedirectStandardOutput "$logDir\vite.log" -RedirectStandardError "$logDir\vite.err.log" -PassThru
    $p.Id | Set-Content $vitePidFile
}

Write-Host ""
Write-Host "========================================"
Write-Host "CompliTrack is running"
Write-Host "Laravel: http://localhost:$appPort"
Write-Host "Realtime: http://localhost:$realtimePort/health"
Write-Host "Vite: http://localhost:5173"
Write-Host "Logs: $logDir"
Write-Host "========================================"