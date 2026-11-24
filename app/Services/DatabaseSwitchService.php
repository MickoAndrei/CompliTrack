<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\URL;
use Illuminate\Validation\ValidationException;
use RuntimeException;

class DatabaseSwitchService
{
    public function __construct(private MongoConnectionProbe $probe) {}

    public function status(): array
    {
        $active = $this->activeEnvironment();
        $target = $active === null ? null : ($active === 'local' ? 'remote' : 'local');
        $state = $this->readState();
        $pending = $state['pendingRequest'];
        $audit = $state['audit'];
        $lastAudit = $audit === [] ? null : $audit[array_key_last($audit)];

        return [
            'activeEnvironment' => $active,
            'activeLabel' => match ($active) {
                'local' => 'Local MongoDB',
                'remote' => 'Remote MongoDB',
                default => 'Unknown MongoDB environment',
            },
            'health' => $this->probe->healthy(config('database.connections.mongodb', []))
                ? 'connected'
                : 'unavailable',
            'targetEnvironment' => $target,
            'targetConfigured' => $target !== null && $this->targetIsConfigured($target),
            'pendingRequest' => $pending === null ? null : [
                'requestId' => $pending['requestId'],
                'targetEnvironment' => $pending['targetEnvironment'],
                'requestedAt' => $pending['requestedAt'],
                'outcome' => $pending['outcome'],
                'stage' => $pending['stage'] ?? 'queued',
                'statusUrl' => $this->progressUrl($pending['requestId']),
            ],
            'lastOutcome' => $lastAudit['outcome'] ?? null,
        ];
    }

    public function progressUrl(string $requestId): string
    {
        return URL::temporarySignedRoute('database-switch.progress', now()->addMinutes(15),
            ['requestId' => $requestId], absolute: false);
    }

    public function progress(string $requestId): array
    {
        $state = $this->readState();
        $pending = $state['pendingRequest'];
        if ($pending !== null && hash_equals($pending['requestId'], $requestId)) {
            return [
                'complete' => ($pending['stage'] ?? '') === 'rollback_failed',
                'stage' => $pending['stage'] ?? 'queued',
                'outcome' => ($pending['stage'] ?? '') === 'rollback_failed' ? 'rollback_failed' : null,
            ];
        }

        foreach (array_reverse($state['audit']) as $entry) {
            if (hash_equals($entry['requestId'], $requestId)
                && in_array($entry['outcome'], ['switch_verified', 'rolled_back', 'cancelled', 'automatic_worker_launch_failed'], true)) {
                return ['complete' => true, 'stage' => 'finished', 'outcome' => $entry['outcome']];
            }
        }

        abort(404);
    }

    public function request(string $target, string $confirmation, User $actor): array
    {
        $directory = $this->directory();
        $lockPath = $directory.DIRECTORY_SEPARATOR.'database-switch.lock';
        $lock = fopen($lockPath, 'c+');
        if ($lock === false) {
            throw new RuntimeException('Unable to access the database switch request lock.');
        }

        $requestId = (string) Str::uuid();
        $requestedAt = now()->utc()->toIso8601String();
        $source = $this->activeEnvironment();

        if (! flock($lock, LOCK_EX)) {
            fclose($lock);
            throw new RuntimeException('Unable to lock the database switch request state.');
        }

        try {
            $state = $this->readState();
            if ($source === null) {
                $this->reject($actor, null, $target, $requestId, $requestedAt, 'active_environment_unknown', 409,
                    'The active MongoDB environment cannot be identified.');
            }
            if ($target === $source) {
                $this->reject($actor, $source, $target, $requestId, $requestedAt, 'same_environment_requested', 409,
                    'The requested target is already active.');
            }
            if ($state['pendingRequest'] !== null) {
                $this->reject($actor, $source, $target, $requestId, $requestedAt, 'pending_request_exists', 409,
                    'A database maintenance request is already pending.');
            }
            if (! $this->targetIsConfigured($target)) {
                $this->reject($actor, $source, $target, $requestId, $requestedAt, 'target_unconfigured', 422,
                    'The requested MongoDB target is not configured.');
            }
            if (! hash_equals('SWITCH TO '.strtoupper($target), $confirmation)) {
                $this->recordAuditLocked($actor, $source, $target, $requestId, $requestedAt, 'confirmation_failed');
                throw ValidationException::withMessages([
                    'confirmation' => ['Type SWITCH TO '.strtoupper($target).' to confirm this maintenance request.'],
                ]);
            }
            if (! $this->probe->healthy($this->targetConnection($target))) {
                $this->reject($actor, $source, $target, $requestId, $requestedAt, 'target_unavailable', 503,
                    'The requested MongoDB target is unavailable. No switch was performed.');
            }
            if (! $this->probe->healthy(config('database.connections.mongodb', []))) {
                $this->reject($actor, $source, $target, $requestId, $requestedAt, 'source_unavailable', 503,
                    'The active MongoDB target is unavailable. No switch was performed.');
            }

            $request = [
                'requestId' => $requestId,
                'requestingAdminId' => (string) $actor->getKey(),
                'requestingAdminName' => (string) $actor->fullName,
                'sourceEnvironment' => $source,
                'targetEnvironment' => $target,
                'requestedAt' => $requestedAt,
                'outcome' => 'automatic_switch_queued',
                'stage' => 'queued',
            ];
            $state['pendingRequest'] = $request;
            $state['audit'][] = $this->auditEntry($actor, $source, $target, $requestId, $requestedAt, $request['outcome']);
            $this->writeState($state);

            return [
                'requestId' => $requestId,
                'outcome' => $request['outcome'],
            ];
        } finally {
            flock($lock, LOCK_UN);
            fclose($lock);
        }
    }

    public function launchAutomaticSwitch(string $requestId): void
    {
        if (PHP_OS_FAMILY !== 'Windows' || ! function_exists('proc_open')) {
            throw new RuntimeException('Automatic database switching is available only through the managed Windows startup script.');
        }

        $pending = $this->readState()['pendingRequest'];
        if (! is_array($pending)
            || ! hash_equals((string) $pending['requestId'], $requestId)
            || ($pending['outcome'] ?? null) !== 'automatic_switch_queued') {
            throw new RuntimeException('The automatic database switch request is no longer pending.');
        }

        $scriptPath = base_path('scripts/database-switch.ps1');
        if (! is_file($scriptPath)) {
            throw new RuntimeException('The controlled Windows database switch worker is missing.');
        }

        $encodedCommand = $this->workerLauncherCommand($scriptPath, base_path(), PHP_BINARY);
        $pipes = [];
        $process = proc_open(
            ['powershell.exe', '-NoProfile', '-NonInteractive', '-EncodedCommand', $encodedCommand],
            [
                0 => ['pipe', 'r'],
                1 => ['pipe', 'w'],
                2 => ['pipe', 'w'],
            ],
            $pipes,
            base_path(),
        );
        if (! is_resource($process)) {
            throw new RuntimeException('Unable to launch the controlled database switch worker.');
        }

        fclose($pipes[0]);
        stream_get_contents($pipes[1]);
        stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        $exitCode = proc_close($process);
        if ($exitCode !== 0) {
            throw new RuntimeException('Unable to launch the controlled database switch worker.');
        }
    }

    private function workerLauncherCommand(string $scriptPath, string $workingDirectory, string $phpExecutable): string
    {
        $scriptLiteral = str_replace("'", "''", $scriptPath);
        $workingDirectoryLiteral = str_replace("'", "''", $workingDirectory);
        $phpLiteral = str_replace("'", "''", $phpExecutable);
        File::ensureDirectoryExists($workingDirectory.'/logs');
        // WMI creates the worker outside PHP's process tree and without inherited
        // listening sockets. Start-Process can keep port 8000 alive after PHP exits.
        $workerCommand = "& '{$scriptLiteral}' -PhpExecutable '{$phpLiteral}' "
            ."1> '{$workingDirectoryLiteral}/logs/database-switch-worker.log' "
            ."2> '{$workingDirectoryLiteral}/logs/database-switch-worker.err.log'";
        $workerEncoded = base64_encode(mb_convert_encoding($workerCommand, 'UTF-16LE', 'UTF-8'));
        $command = "\$ErrorActionPreference = 'Stop'; "
            ."\$scriptPath = '{$scriptLiteral}'; "
            ."\$parseErrors = \$null; \$tokens = \$null; "
            ."[System.Management.Automation.Language.Parser]::ParseFile(\$scriptPath, [ref]\$tokens, [ref]\$parseErrors) | Out-Null; "
            ."if (\$parseErrors.Count -gt 0) { exit 1 }; "
            ."\$startup = New-CimInstance -ClassName Win32_ProcessStartup -ClientOnly -Property @{ ShowWindow = [uint16]0 }; "
            ."\$powershell = Join-Path \$PSHOME 'powershell.exe'; "
            ."\$workerLine = [char]34 + \$powershell + [char]34 + ' -NoProfile -NonInteractive -ExecutionPolicy Bypass -EncodedCommand {$workerEncoded}'; "
            ."\$started = Invoke-CimMethod -ClassName Win32_Process -MethodName Create -Arguments @{ "
            ."CommandLine = \$workerLine; CurrentDirectory = '{$workingDirectoryLiteral}'; ProcessStartupInformation = \$startup }; "
            ."if (\$started.ReturnValue -ne 0 -or -not \$started.ProcessId) { exit 1 }";
        return base64_encode(mb_convert_encoding($command, 'UTF-16LE', 'UTF-8'));
    }

    public function adoptPendingRequest(string $confirmation): array
    {
        $lock = fopen($this->directory().DIRECTORY_SEPARATOR.'database-switch.lock', 'c+');
        if ($lock === false || ! flock($lock, LOCK_EX)) {
            if (is_resource($lock)) {
                fclose($lock);
            }
            throw new RuntimeException('Unable to lock the pending database switch request.');
        }

        try {
            $state = $this->readState();
            $pending = $state['pendingRequest'];
            $legacyRequest = is_array($pending)
                && ($pending['outcome'] ?? null) === 'maintenance_request_pending_not_switched';
            $failedAutomaticRequest = is_array($pending)
                && ($pending['outcome'] ?? null) === 'automatic_switch_queued'
                && ($pending['stage'] ?? null) === 'rollback_failed';
            if (! $legacyRequest && ! $failedAutomaticRequest) {
                throw new RuntimeException('There is no maintenance request awaiting an explicitly confirmed execution.');
            }

            $source = $this->activeEnvironment();
            $target = (string) $pending['targetEnvironment'];
            if ($source === null || $source !== $pending['sourceEnvironment']) {
                throw new RuntimeException('The active MongoDB environment no longer matches the pending request source.');
            }
            if (! $this->targetIsConfigured($target)) {
                throw new RuntimeException('The requested MongoDB target is not configured.');
            }
            if (! hash_equals('SWITCH TO '.strtoupper($target), $confirmation)) {
                $state['audit'][] = $this->resolvedAuditEntry(
                    $pending,
                    now()->utc()->toIso8601String(),
                    'automatic_execution_confirmation_failed',
                );
                $this->writeState($state);
                throw ValidationException::withMessages([
                    'confirmation' => ['Type SWITCH TO '.strtoupper($target).' to authorize automatic execution.'],
                ]);
            }
            if (! $this->probe->healthy(config('database.connections.mongodb', []))) {
                $state['audit'][] = $this->resolvedAuditEntry(
                    $pending,
                    now()->utc()->toIso8601String(),
                    'source_unavailable_before_execution',
                );
                $this->writeState($state);
                throw new RuntimeException('The source MongoDB is unavailable. No switch was started.');
            }
            if (! $this->probe->healthy($this->targetConnection($target))) {
                $state['audit'][] = $this->resolvedAuditEntry(
                    $pending,
                    now()->utc()->toIso8601String(),
                    'target_unavailable_before_execution',
                );
                $this->writeState($state);
                throw new RuntimeException('The target MongoDB is unavailable. No switch was started.');
            }

            $approvedAt = now()->utc()->toIso8601String();
            $pending['outcome'] = 'automatic_switch_queued';
            $pending['stage'] = 'queued';
            $pending['executionApprovedAt'] = $approvedAt;
            $state['pendingRequest'] = $pending;
            $state['audit'][] = [
                'requestId' => $pending['requestId'],
                'requestingAdminId' => $pending['requestingAdminId'],
                'requestingAdminName' => $pending['requestingAdminName'],
                'sourceEnvironment' => $pending['sourceEnvironment'],
                'targetEnvironment' => $pending['targetEnvironment'],
                'timestamp' => $approvedAt,
                'outcome' => $failedAutomaticRequest
                    ? 'automatic_switch_retry_approved'
                    : 'automatic_switch_approved_for_execution',
            ];
            $this->writeState($state);

            return [
                'requestId' => $pending['requestId'],
                'outcome' => $pending['outcome'],
            ];
        } finally {
            flock($lock, LOCK_UN);
            fclose($lock);
        }
    }

    public function cancelUndispatched(string $requestId): void
    {
        $lock = fopen($this->directory().DIRECTORY_SEPARATOR.'database-switch.lock', 'c+');
        if ($lock === false || ! flock($lock, LOCK_EX)) {
            if (is_resource($lock)) {
                fclose($lock);
            }
            throw new RuntimeException('Unable to lock the database switch state after worker launch failed.');
        }

        try {
            $state = $this->readState();
            $pending = $state['pendingRequest'];
            if ($pending === null || ! hash_equals((string) $pending['requestId'], $requestId)) {
                return;
            }

            $state['pendingRequest'] = null;
            $state['audit'][] = $this->resolvedAuditEntry(
                $pending,
                now()->utc()->toIso8601String(),
                'automatic_worker_launch_failed',
            );
            $this->writeState($state);
        } finally {
            flock($lock, LOCK_UN);
            fclose($lock);
        }
    }

    public function setStage(string $requestId, string $stage): void
    {
        if (! in_array($stage, [
            'preflight',
            'stopping_services',
            'changing_target',
            'verifying_target',
            'invalidating_logins',
            'starting_services',
            'rolling_back',
            'rollback_failed',
        ], true)) {
            throw new RuntimeException('The requested database switch stage is invalid.');
        }

        $lock = fopen($this->directory().DIRECTORY_SEPARATOR.'database-switch.lock', 'c+');
        if ($lock === false || ! flock($lock, LOCK_EX)) {
            if (is_resource($lock)) {
                fclose($lock);
            }
            throw new RuntimeException('Unable to lock the database switch state.');
        }

        try {
            $state = $this->readState();
            $pending = $state['pendingRequest'];
            if ($pending === null || ! hash_equals((string) $pending['requestId'], $requestId)) {
                throw new RuntimeException('No matching pending database switch request was found.');
            }
            $pending['stage'] = $stage;
            $state['pendingRequest'] = $pending;
            $this->writeState($state);
        } finally {
            flock($lock, LOCK_UN);
            fclose($lock);
        }
    }

    public function targetIsHealthy(string $environment): bool
    {
        return $this->targetIsConfigured($environment)
            && $this->probe->healthy($this->targetConnection($environment));
    }

    public function resolve(string $requestId, string $outcome): array
    {
        if (! in_array($outcome, ['switch_verified', 'rolled_back', 'cancelled'], true)) {
            throw new RuntimeException('Resolution outcome must be switch_verified, rolled_back, or cancelled.');
        }

        $lock = fopen($this->directory().DIRECTORY_SEPARATOR.'database-switch.lock', 'c+');
        if ($lock === false || ! flock($lock, LOCK_EX)) {
            if (is_resource($lock)) {
                fclose($lock);
            }
            throw new RuntimeException('Unable to lock the database switch state for resolution.');
        }

        try {
            $state = $this->readState();
            $pending = $state['pendingRequest'];
            if ($pending === null || ! hash_equals((string) $pending['requestId'], $requestId)) {
                throw new RuntimeException('No matching pending database maintenance request was found.');
            }

            $expectedEnvironment = $outcome === 'switch_verified'
                ? $pending['targetEnvironment']
                : $pending['sourceEnvironment'];
            $healthy = $outcome === 'cancelled'
                || $this->probe->healthy(config('database.connections.mongodb', []));
            if ($this->activeEnvironment() !== $expectedEnvironment || ! $healthy) {
                $state['audit'][] = $this->resolvedAuditEntry($pending, now()->utc()->toIso8601String(), 'resolution_verification_failed');
                $this->writeState($state);
                throw new RuntimeException('The active MongoDB environment does not match the requested resolution. The request remains pending.');
            }

            $state['pendingRequest'] = null;
            $state['audit'][] = $this->resolvedAuditEntry($pending, now()->utc()->toIso8601String(), $outcome);
            $this->writeState($state);

            return ['requestId' => $requestId, 'outcome' => $outcome];
        } finally {
            flock($lock, LOCK_UN);
            fclose($lock);
        }
    }

    private function activeEnvironment(): ?string
    {
        $active = config('database.connections.mongodb', []);
        $matches = [];

        foreach (['local', 'remote'] as $environment) {
            $target = $this->targetConnection($environment);
            if ($this->targetIsConfigured($environment)
                && hash_equals((string) $target['dsn'], (string) ($active['dsn'] ?? ''))
                && hash_equals((string) $target['database'], (string) ($active['database'] ?? ''))) {
                $matches[] = $environment;
            }
        }

        return count($matches) === 1 ? $matches[0] : null;
    }

    private function targetConnection(string $environment): array
    {
        $target = config('database.mongodb_targets.'.$environment, []);

        return array_replace(config('database.connections.mongodb', []), [
            'dsn' => $target['dsn'] ?? '',
            'database' => $target['database'] ?? '',
        ]);
    }

    private function targetIsConfigured(string $environment): bool
    {
        $target = config('database.mongodb_targets.'.$environment, []);

        return is_string($target['dsn'] ?? null)
            && trim($target['dsn']) !== ''
            && is_string($target['database'] ?? null)
            && trim($target['database']) !== '';
    }

    private function readState(): array
    {
        $path = $this->statePath();
        if (! is_file($path)) {
            return ['pendingRequest' => null, 'audit' => []];
        }

        $state = json_decode(file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);
        if (! is_array($state) || ! array_key_exists('pendingRequest', $state) || ! is_array($state['audit'] ?? null)) {
            throw new RuntimeException('The saved database switch state is invalid.');
        }
        $pending = $state['pendingRequest'];
        if ($pending !== null && (! is_array($pending)
            || ! isset(
                $pending['requestId'],
                $pending['requestingAdminId'],
                $pending['requestingAdminName'],
                $pending['sourceEnvironment'],
                $pending['targetEnvironment'],
                $pending['requestedAt'],
                $pending['outcome'],
            ))) {
            throw new RuntimeException('The saved database maintenance request is invalid.');
        }

        return $state;
    }

    private function writeState(array $state): void
    {
        $path = $this->statePath();
        $content = json_encode($state, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
        File::replace($path, $content, 0600);
        if (! is_file($path) || file_get_contents($path) !== $content) {
            throw new RuntimeException('Unable to safely persist the database switch state and audit log.');
        }
    }

    private function statePath(): string
    {
        return $this->directory().DIRECTORY_SEPARATOR.'database-switch-state.json';
    }

    private function reject(
        User $actor,
        ?string $source,
        string $target,
        string $requestId,
        string $requestedAt,
        string $outcome,
        int $status,
        string $message,
    ): never {
        $this->recordAuditLocked($actor, $source, $target, $requestId, $requestedAt, $outcome);
        abort($status, $message);
    }

    private function recordAuditLocked(
        User $actor,
        ?string $source,
        string $target,
        string $requestId,
        string $requestedAt,
        string $outcome,
    ): void {
        $state = $this->readState();
        $state['audit'][] = $this->auditEntry($actor, $source, $target, $requestId, $requestedAt, $outcome);
        $this->writeState($state);
    }

    private function auditEntry(
        User $actor,
        ?string $source,
        string $target,
        string $requestId,
        string $requestedAt,
        string $outcome,
    ): array {
        return [
            'requestId' => $requestId,
            'requestingAdminId' => (string) $actor->getKey(),
            'requestingAdminName' => (string) $actor->fullName,
            'sourceEnvironment' => $source,
            'targetEnvironment' => in_array($target, ['local', 'remote'], true) ? $target : 'invalid',
            'timestamp' => $requestedAt,
            'outcome' => $outcome,
        ];
    }

    private function resolvedAuditEntry(array $request, string $timestamp, string $outcome): array
    {
        return [
            'requestId' => $request['requestId'],
            'requestingAdminId' => $request['requestingAdminId'],
            'requestingAdminName' => $request['requestingAdminName'],
            'sourceEnvironment' => $request['sourceEnvironment'],
            'targetEnvironment' => $request['targetEnvironment'],
            'timestamp' => $timestamp,
            'outcome' => $outcome,
        ];
    }

    private function directory(): string
    {
        $path = app(SystemSettingsService::class)->path('database-switch');
        File::ensureDirectoryExists($path, 0700);

        return $path;
    }
}
