<?php

namespace Tests\Unit;

use App\Services\AllTargetLogout;
use Illuminate\Support\Facades\{DB, File};
use Mockery;
use Tests\TestCase;

class AllTargetLogoutTest extends TestCase
{
    public function test_missing_second_target_does_not_clear_file_sessions(): void
    {
        config(['session.driver' => 'file', 'database.mongodb_targets.local' => ['dsn' => 'mongodb://localhost', 'database' => 'fake'],
            'database.mongodb_targets.remote' => []]);
        DB::shouldReceive('purge')->andReturnNull();
        File::shouldReceive('cleanDirectory')->never();
        $this->expectExceptionMessage('No sessions or credentials were changed');
        app(AllTargetLogout::class)->run();
    }

    public function test_unreachable_second_target_does_not_clear_first_target(): void
    {
        $this->configureTargets();
        File::shouldReceive('cleanDirectory')->never();
        $first = Mockery::mock(); $second = Mockery::mock();
        DB::shouldReceive('purge')->andReturnNull();
        DB::shouldReceive('connection')->with('database_switch_logout_local')->andReturn($first);
        DB::shouldReceive('connection')->with('database_switch_logout_remote')->andReturn($second);
        $first->shouldReceive('getMongoDB->command->toArray')->once()->andReturn([]);
        $first->shouldReceive('table')->never();
        $second->shouldReceive('getMongoDB')->once()->andThrow(new \RuntimeException('connection failure'));
        $this->expectExceptionMessage('No sessions or credentials were changed');
        app(AllTargetLogout::class)->run();
    }

    public function test_write_failure_reports_stores_where_cleanup_started(): void
    {
        $this->configureTargets();
        $first = Mockery::mock(); $second = Mockery::mock();
        DB::shouldReceive('purge')->andReturnNull();
        DB::shouldReceive('connection')->with('database_switch_logout_local')->andReturn($first);
        DB::shouldReceive('connection')->with('database_switch_logout_remote')->andReturn($second);
        foreach ([$first, $second] as $connection) {
            $connection->shouldReceive('getMongoDB->command->toArray')->once()->andReturn([]);
        }
        File::shouldReceive('cleanDirectory')->once()->andReturn(true);
        $first->shouldReceive('table')->with('users')->once()->andReturnSelf();
        $first->shouldReceive('update')->once()->andReturn(1);
        $first->shouldReceive('table')->with('personal_access_tokens')->once()->andReturnSelf();
        $first->shouldReceive('delete')->once()->andReturn(1);
        $second->shouldReceive('table')->once()->andThrow(new \RuntimeException('write failure'));
        $this->expectExceptionMessage('Cleanup started for: file sessions, local, remote');
        app(AllTargetLogout::class)->run();
    }

    private function configureTargets(): void
    {
        config(['session.driver' => 'file', 'session.files' => 'mocked-only']);
        foreach (['local', 'remote'] as $target) {
            config(['database.mongodb_targets.'.$target => ['dsn' => 'mongodb://localhost', 'database' => 'fake']]);
        }
    }
}
