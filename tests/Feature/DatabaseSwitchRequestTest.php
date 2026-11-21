<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\AuditLogService;
use App\Services\DatabaseSwitchService;
use App\Services\MongoConnectionProbe;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Route;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class DatabaseSwitchRequestTest extends TestCase
{
    private string $directory;

    private array $local;

    private array $remote;

    protected function setUp(): void
    {
        parent::setUp();
        $this->directory = storage_path('framework/testing/database-switch-'.bin2hex(random_bytes(6)));
        config(['system.path' => $this->directory.'/system']);
        $this->local = ['driver' => 'mongodb', 'dsn' => 'mongodb://local.example.test', 'database' => 'complitrack_local'];
        $this->remote = ['driver' => 'mongodb', 'dsn' => 'mongodb://remote.example.test', 'database' => 'complitrack_remote'];
        $this->activate('local');
        $this->withoutVite();
        $this->mock(AuditLogService::class)->shouldReceive('record')->zeroOrMoreTimes();
    }

    protected function tearDown(): void
    {
        if (is_dir($this->directory)) {
            $resolved = realpath($this->directory);
            $allowed = realpath(storage_path('framework/testing'));
            if ($resolved && $allowed && str_starts_with($resolved, $allowed.DIRECTORY_SEPARATOR)) {
                File::deleteDirectory($resolved);
            }
        }
        parent::tearDown();
    }

    public function test_both_directions_create_audited_requests_without_changing_active_configuration(): void
    {
        foreach ([
            ['local', 'remote', $this->remote],
            ['remote', 'local', $this->local],
        ] as [$source, $target, $targetConfig]) {
            $this->activate($source);
            $probe = $this->mock(MongoConnectionProbe::class);
            $probe->shouldReceive('healthy')->once()->with($this->targetConnection($target))->andReturn(true);
            $probe->shouldReceive('healthy')->once()->with($source === 'local' ? $this->local : $this->remote)->andReturn(true);

            $original = config('database.connections.mongodb');
            $result = app(DatabaseSwitchService::class)->request($target, 'SWITCH TO '.strtoupper($target), $this->admin());
            $state = $this->switchState();
            $pending = $state['pendingRequest'];
            $audit = $state['audit'];

            $this->assertSame('automatic_switch_queued', $result['outcome']);
            $this->assertSame($source, $pending['sourceEnvironment']);
            $this->assertSame($target, $pending['targetEnvironment']);
            $this->assertSame('automatic_switch_queued', $audit[array_key_last($audit)]['outcome']);
            $this->assertSame('queued', $pending['stage']);
            $this->assertSame((string) $this->admin()->getKey(), $audit[array_key_last($audit)]['requestingAdminId']);
            $this->assertSame($original, config('database.connections.mongodb'));
            $this->assertSame($targetConfig['dsn'], config('database.mongodb_targets.'.$target.'.dsn'));
            $this->assertSame('mongodb', config('session.driver'));
            $this->assertSame('mongodb', config('queue.default'));
            $this->assertSame('mongodb', config('cache.default'));
            File::delete($this->statePath());
        }
    }

    public function test_confirmation_is_required_before_a_maintenance_request_is_queued(): void
    {
        $this->mock(MongoConnectionProbe::class)
            ->shouldReceive('healthy')->never();

        try {
            app(DatabaseSwitchService::class)->request('remote', '', $this->admin());
            $this->fail('A request without the required confirmation must be rejected.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('confirmation', $exception->errors());
        }

        $state = $this->switchState();
        $this->assertSame('confirmation_failed', $state['audit'][0]['outcome']);
        $this->assertNull($state['pendingRequest']);
    }

    public function test_unavailable_target_is_audited_and_does_not_change_the_active_database(): void
    {
        $this->mock(MongoConnectionProbe::class)
            ->shouldReceive('healthy')->once()->with($this->targetConnection('remote'))->andReturn(false);
        $original = config('database.connections.mongodb');

        try {
            app(DatabaseSwitchService::class)->request('remote', 'SWITCH TO REMOTE', $this->admin());
            $this->fail('An unavailable target must be rejected.');
        } catch (HttpException $exception) {
            $this->assertSame(503, $exception->getStatusCode());
        }

        $this->assertSame($original, config('database.connections.mongodb'));
        $state = $this->switchState();
        $this->assertSame('target_unavailable', $state['audit'][0]['outcome']);
        $this->assertNull($state['pendingRequest']);
    }

    public function test_unavailable_source_is_audited_and_not_queued(): void
    {
        $probe = $this->mock(MongoConnectionProbe::class);
        $probe->shouldReceive('healthy')->once()->with($this->targetConnection('remote'))->andReturn(true);
        $probe->shouldReceive('healthy')->once()->with($this->local)->andReturn(false);

        try {
            app(DatabaseSwitchService::class)->request('remote', 'SWITCH TO REMOTE', $this->admin());
            $this->fail('An unavailable active target must prevent switching.');
        } catch (HttpException $exception) {
            $this->assertSame(503, $exception->getStatusCode());
        }

        $state = $this->switchState();
        $this->assertSame('source_unavailable', $state['audit'][0]['outcome']);
        $this->assertNull($state['pendingRequest']);
    }

    public function test_pending_requests_serialize_repeated_submissions_and_audit_the_rejection(): void
    {
        $probe = $this->mock(MongoConnectionProbe::class);
        $probe->shouldReceive('healthy')->once()->with($this->targetConnection('remote'))->andReturn(true);
        $probe->shouldReceive('healthy')->once()->with($this->local)->andReturn(true);
        $service = app(DatabaseSwitchService::class);
        $service->request('remote', 'SWITCH TO REMOTE', $this->admin());

        try {
            $service->request('remote', 'SWITCH TO REMOTE', $this->admin());
            $this->fail('A second pending request must be rejected.');
        } catch (HttpException $exception) {
            $this->assertSame(409, $exception->getStatusCode());
        }
        $this->assertSame('pending_request_exists', $this->switchState()['audit'][1]['outcome']);
    }

    public function test_a_request_waiting_on_another_process_lock_is_serialized_safely(): void
    {
        if (! function_exists('proc_open')) {
            $this->markTestSkipped('Process-based lock validation requires proc_open.');
        }

        $lockPath = $this->directory.'/system/database-switch/database-switch.lock';
        File::ensureDirectoryExists(dirname($lockPath), 0700);
        $script = '$lock = fopen($argv[1], "c+"); flock($lock, LOCK_EX); echo "ready\\n"; flush(); usleep(250000); flock($lock, LOCK_UN); fclose($lock);';
        $pipes = [];
        $process = proc_open([PHP_BINARY, '-r', $script, $lockPath], [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ], $pipes, base_path());
        $this->assertIsResource($process);
        $this->assertSame("ready\n", fgets($pipes[1]));
        $probe = $this->mock(MongoConnectionProbe::class);
        $probe->shouldReceive('healthy')->once()->with($this->targetConnection('remote'))->andReturn(true);
        $probe->shouldReceive('healthy')->once()->with($this->local)->andReturn(true);

        $result = app(DatabaseSwitchService::class)->request('remote', 'SWITCH TO REMOTE', $this->admin());
        fclose($pipes[0]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        $this->assertSame(0, proc_close($process));
        $this->assertSame('automatic_switch_queued', $result['outcome']);
        $this->assertCount(1, $this->switchState()['audit']);
        $this->assertSame('remote', $this->switchState()['pendingRequest']['targetEnvironment']);
    }

    public function test_resolution_requires_the_expected_active_environment_and_a_healthy_database(): void
    {
        $probe = $this->mock(MongoConnectionProbe::class);
        $probe->shouldReceive('healthy')->once()->with($this->targetConnection('remote'))->andReturn(true);
        $probe->shouldReceive('healthy')->times(3)->with($this->local)->andReturn(true);
        $service = app(DatabaseSwitchService::class);
        $request = $service->request('remote', 'SWITCH TO REMOTE', $this->admin());

        try {
            $service->resolve($request['requestId'], 'switch_verified');
            $this->fail('A switch cannot be marked verified while the source remains active.');
        } catch (\RuntimeException $exception) {
            $this->assertStringContainsString('does not match', $exception->getMessage());
        }
        $this->assertSame('resolution_verification_failed', $this->switchState()['audit'][1]['outcome']);
        $this->assertNotNull($this->switchState()['pendingRequest']);

        $this->assertSame('rolled_back', $service->resolve($request['requestId'], 'rolled_back')['outcome']);
        $this->assertSame('rolled_back', $this->switchState()['audit'][2]['outcome']);
        $this->assertNull($this->switchState()['pendingRequest']);
    }

    public function test_verified_resolution_requires_the_target_to_be_active_and_healthy(): void
    {
        $probe = $this->mock(MongoConnectionProbe::class);
        $probe->shouldReceive('healthy')->twice()->with($this->targetConnection('remote'))->andReturn(true);
        $probe->shouldReceive('healthy')->once()->with($this->local)->andReturn(true);
        $service = app(DatabaseSwitchService::class);
        $request = $service->request('remote', 'SWITCH TO REMOTE', $this->admin());
        $this->activate('remote');

        $this->assertSame('switch_verified', $service->resolve($request['requestId'], 'switch_verified')['outcome']);
        $this->assertSame('switch_verified', $this->switchState()['audit'][1]['outcome']);
        $this->assertNull($this->switchState()['pendingRequest']);
        $this->assertSame($this->remote, config('database.connections.mongodb'));
    }

    public function test_operator_command_accepts_only_the_reconciliation_service_contract(): void
    {
        $this->mock(DatabaseSwitchService::class)
            ->shouldReceive('resolve')
            ->once()
            ->with('request-id', 'switch_verified')
            ->andReturn(['requestId' => 'request-id', 'outcome' => 'switch_verified']);

        $this->artisan('complitrack:database-switch:resolve request-id switch_verified')
            ->expectsOutput('Database maintenance request resolved as switch_verified.')
            ->assertExitCode(0);
    }

    public function test_legacy_request_requires_typed_confirmation_before_automatic_execution(): void
    {
        $pending = [
            'requestId' => 'legacy-request-id',
            'requestingAdminId' => 'test-admin-0',
            'requestingAdminName' => 'Switch Test Admin',
            'sourceEnvironment' => 'local',
            'targetEnvironment' => 'remote',
            'requestedAt' => now()->utc()->toIso8601String(),
            'outcome' => 'maintenance_request_pending_not_switched',
        ];
        File::ensureDirectoryExists(dirname($this->statePath()), 0700);
        File::put($this->statePath(), json_encode(['pendingRequest' => $pending, 'audit' => []], JSON_THROW_ON_ERROR));
        $probe = $this->mock(MongoConnectionProbe::class);
        $probe->shouldReceive('healthy')->once()->with($this->local)->andReturn(true);
        $probe->shouldReceive('healthy')->once()->with($this->targetConnection('remote'))->andReturn(true);

        $result = app(DatabaseSwitchService::class)->adoptPendingRequest('SWITCH TO REMOTE');
        $state = $this->switchState();

        $this->assertSame('legacy-request-id', $result['requestId']);
        $this->assertSame('automatic_switch_queued', $state['pendingRequest']['outcome']);
        $this->assertSame('queued', $state['pendingRequest']['stage']);
        $this->assertSame('automatic_switch_approved_for_execution', $state['audit'][0]['outcome']);
        $this->assertSame('test-admin-0', $state['audit'][0]['requestingAdminId']);
    }

    public function test_failed_automatic_request_can_only_be_retried_with_confirmation_and_healthy_targets(): void
    {
        $pending = [
            'requestId' => 'retry-request-id',
            'requestingAdminId' => 'test-admin-0',
            'requestingAdminName' => 'Switch Test Admin',
            'sourceEnvironment' => 'local',
            'targetEnvironment' => 'remote',
            'requestedAt' => now()->utc()->toIso8601String(),
            'outcome' => 'automatic_switch_queued',
            'stage' => 'rollback_failed',
        ];
        File::ensureDirectoryExists(dirname($this->statePath()), 0700);
        File::put($this->statePath(), json_encode(['pendingRequest' => $pending, 'audit' => []], JSON_THROW_ON_ERROR));
        $probe = $this->mock(MongoConnectionProbe::class);
        $probe->shouldReceive('healthy')->once()->with($this->local)->andReturn(true);
        $probe->shouldReceive('healthy')->once()->with($this->targetConnection('remote'))->andReturn(true);

        $result = app(DatabaseSwitchService::class)->adoptPendingRequest('SWITCH TO REMOTE');
        $state = $this->switchState();

        $this->assertSame('retry-request-id', $result['requestId']);
        $this->assertSame('queued', $state['pendingRequest']['stage']);
        $this->assertSame('automatic_switch_retry_approved', $state['audit'][0]['outcome']);
    }

    public function test_automatic_pending_execution_command_uses_fixed_worker(): void
    {
        $switch = \Mockery::mock(DatabaseSwitchService::class);
        $switch->shouldReceive('adoptPendingRequest')
            ->once()
            ->with('SWITCH TO REMOTE')
            ->andReturn(['requestId' => 'request-id', 'outcome' => 'automatic_switch_queued']);
        $switch->shouldReceive('launchAutomaticSwitch')->once()->with('request-id');
        app()->instance(DatabaseSwitchService::class, $switch);

        $this->artisan('complitrack:database-switch:execute-pending "SWITCH TO REMOTE"')
            ->expectsOutput('The previously confirmed database request is executing through the controlled local switch workflow.')
            ->assertExitCode(0);
    }

    public function test_request_route_requires_superadmin_permission_and_web_csrf_middleware(): void
    {
        config(['session.driver' => 'array', 'cache.default' => 'array']);
        $this->getJson('/api/v1/settings')->assertUnauthorized();

        foreach ([1, 2, 3, 4] as $role) {
            $actor = $this->account($role);
            $this->actingAs($actor)->getJson('/api/v1/settings')->assertForbidden();
            $this->actingAs($actor)->postJson('/super-admin/system/database-switch', [
                'target' => 'remote',
                'confirmation' => 'SWITCH TO REMOTE',
            ])->assertForbidden();
            $this->get('/super-admin/settings')->assertForbidden();
        }
        $inactiveAdmin = $this->account(0);
        $inactiveAdmin->isActive = false;
        $this->actingAs($inactiveAdmin)->get('/super-admin/settings')->assertForbidden();
        $this->actingAs($inactiveAdmin)->postJson('/super-admin/system/database-switch', [
            'target' => 'remote',
            'confirmation' => 'SWITCH TO REMOTE',
        ])->assertForbidden();

        $route = Route::getRoutes()->match(Request::create('/super-admin/system/database-switch', 'POST'));
        $this->assertContains('web', $route->gatherMiddleware());
        $this->assertNotContains('api', $route->gatherMiddleware());
        $this->assertContains(ValidateCsrfToken::class, app('router')->getMiddlewareGroups()['web']);
        $this->postJson('/api/v1/settings/database-switch', [
            'target' => 'remote',
            'confirmation' => 'SWITCH TO REMOTE',
        ])->assertNotFound();

        $page = $this->actingAs($this->admin())->get('/super-admin/settings')->assertOk()
            ->assertSee('Database Configuration')
            ->assertSee('data-database-switch-button');
        $this->assertSame(1, substr_count($page->getContent(), 'data-database-switch-button'));
        $this->assertStringNotContainsString('mongodb://', $page->getContent());
    }

    public function test_superadmin_can_request_through_the_server_authorized_endpoint(): void
    {
        config(['session.driver' => 'array', 'cache.default' => 'array']);
        $admin = $this->admin();
        app()->instance(DatabaseSwitchService::class, $switch = \Mockery::mock(DatabaseSwitchService::class));
        $switch->shouldReceive('request')
            ->once()
            ->with('remote', 'SWITCH TO REMOTE', $admin)
            ->andReturn(['requestId' => 'request-id', 'outcome' => 'automatic_switch_queued']);
        $switch->shouldReceive('launchAutomaticSwitch')->once()->with('request-id');

        $this->actingAs($admin)->postJson('/super-admin/system/database-switch', [
            'target' => 'remote',
            'confirmation' => 'SWITCH TO REMOTE',
            'command' => 'ignored input; never executed',
        ])->assertAccepted()
            ->assertJsonPath('request.outcome', 'automatic_switch_queued')
            ->assertJsonPath('message', 'The database switch started. Services will restart and all users will need to sign in again. No migrations, seeders, or data synchronization will run.');
    }

    public function test_worker_launch_failure_is_reported_and_clears_the_undispatched_request(): void
    {
        config(['session.driver' => 'array', 'cache.default' => 'array']);
        $admin = $this->admin();
        $switch = \Mockery::mock(DatabaseSwitchService::class);
        $switch->shouldReceive('request')
            ->once()
            ->with('remote', 'SWITCH TO REMOTE', $admin)
            ->andReturn(['requestId' => 'request-id', 'outcome' => 'automatic_switch_queued']);
        $switch->shouldReceive('launchAutomaticSwitch')
            ->once()
            ->with('request-id')
            ->andThrow(new \RuntimeException('worker unavailable'));
        $switch->shouldReceive('cancelUndispatched')->once()->with('request-id');
        app()->instance(DatabaseSwitchService::class, $switch);

        $this->actingAs($admin)->postJson('/super-admin/system/database-switch', [
            'target' => 'remote',
            'confirmation' => 'SWITCH TO REMOTE',
        ])->assertServiceUnavailable()
            ->assertJsonPath('message', 'The automatic switch worker could not start. The active database was not changed.');
    }

    public function test_status_shows_environment_health_and_pending_request_without_connection_details(): void
    {
        $this->mock(MongoConnectionProbe::class)
            ->shouldReceive('healthy')->once()->andReturn(true);

        $status = app(DatabaseSwitchService::class)->status();

        $this->assertSame('local', $status['activeEnvironment']);
        $this->assertSame('Local MongoDB', $status['activeLabel']);
        $this->assertSame('connected', $status['health']);
        $this->assertTrue($status['targetConfigured']);
        $this->assertStringNotContainsString('mongodb://', json_encode($status));
        $this->assertStringNotContainsString('example.test', json_encode($status));
    }

    private function activate(string $environment): void
    {
        $active = $environment === 'local' ? $this->local : $this->remote;
        config([
            'database.connections.mongodb' => $active,
            'database.mongodb_targets.local' => $this->local,
            'database.mongodb_targets.remote' => $this->remote,
            'session.driver' => 'mongodb',
            'queue.default' => 'mongodb',
            'cache.default' => 'mongodb',
        ]);
    }

    private function targetConnection(string $target): array
    {
        return array_replace(config('database.connections.mongodb'), $target === 'local' ? $this->local : $this->remote);
    }

    private function admin(): User
    {
        return $this->account(0);
    }

    private function account(int $role): User
    {
        $user = new User;
        $user->forceFill([
            '_id' => 'test-admin-'.$role,
            'fullName' => 'Switch Test Admin',
            'roleId' => $role,
            'isActive' => true,
        ]);

        return $user;
    }

    private function statePath(): string
    {
        return $this->directory.'/system/database-switch/database-switch-state.json';
    }

    private function switchState(): array
    {
        $path = $this->statePath();
        if (! is_file($path)) {
            return ['pendingRequest' => null, 'audit' => []];
        }

        return json_decode(File::get($path), true, 512, JSON_THROW_ON_ERROR);
    }
}
