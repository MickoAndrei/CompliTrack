<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\DB;
use Mockery;
use MongoDB\Driver\Exception\ConnectionTimeoutException;
use Tests\TestCase;

class MongoConnectivityCommandTest extends TestCase
{
    public function test_startup_ping_does_not_inspect_change_streams_or_write_data(): void
    {
        $database = Mockery::mock(\MongoDB\Database::class);
        $cursor = Mockery::mock(\MongoDB\Driver\CursorInterface::class);
        $cursor->shouldReceive('toArray')->once()->andReturn([['ok' => 1]]);
        $database->shouldReceive('command')->once()->with(['ping' => 1])
            ->andReturn($cursor);
        $connection = Mockery::mock();
        $connection->shouldReceive('getMongoDB')->once()->andReturn($database);
        DB::shouldReceive('connection')->once()->with('mongodb')->andReturn($connection);

        $this->artisan('mongodb:check', ['--ping' => true])
            ->expectsOutput('MongoDB is reachable.')
            ->assertExitCode(0);
    }

    public function test_tls_failure_returns_failure_with_atlas_guidance(): void
    {
        DB::shouldReceive('connection')->once()->with('mongodb')
            ->andThrow(new ConnectionTimeoutException('TLS handshake failed'));

        $this->artisan('mongodb:check', ['--ping' => true])
            ->expectsOutput('MongoDB connection check failed: TLS handshake failed')
            ->expectsOutputToContain('Network Access IP access list')
            ->expectsOutputToContain('outbound TCP port 27017')
            ->assertExitCode(1);
    }
}
