<?php

namespace Tests\Unit;

use App\Http\Controllers\Api\V1\AuditLogController;
use App\Models\User;
use App\Services\RoleScopeService;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Validation\ValidationException;
use Mockery;
use Tests\TestCase;

class AuditLogDateRangeTest extends TestCase
{
    public function test_date_range_includes_the_entire_end_day(): void
    {
        $actor = new User();
        $request = Request::create('/api/v1/logs', 'GET', ['startDate' => '2026-11-01', 'endDate' => '2026-11-10']);
        $request->setUserResolver(fn () => $actor);
        $query = Mockery::mock();
        $query->shouldReceive('where')->once()->with('createdAt', '>=', Mockery::on(fn ($date) => $date->format('Y-m-d H:i:s') === '2026-11-01 00:00:00'))->andReturnSelf();
        $query->shouldReceive('where')->once()->with('createdAt', '<', Mockery::on(fn ($date) => $date->format('Y-m-d H:i:s') === '2026-11-11 00:00:00'))->andReturnSelf();
        $query->shouldReceive('orderBy')->with('createdAt', 'desc')->andReturnSelf();
        $query->shouldReceive('orderBy')->with('_id', 'desc')->andReturnSelf();
        $query->shouldReceive('paginate')->andReturn(new LengthAwarePaginator([], 0, 10));
        $scopes = Mockery::mock(RoleScopeService::class);
        $scopes->shouldReceive('auditLogs')->with($actor)->once()->andReturn($query);
        $response = (new AuditLogController($scopes))->index($request);
        $this->assertSame(200, $response->getStatusCode());
    }

    public function test_reversed_dates_are_rejected_before_querying_logs(): void
    {
        $scopes = Mockery::mock(RoleScopeService::class);
        $scopes->shouldNotReceive('auditLogs');
        $request = Request::create('/api/v1/logs', 'GET', ['startDate' => '2026-11-10', 'endDate' => '2026-11-01']);
        $this->expectException(ValidationException::class);
        (new AuditLogController($scopes))->index($request);
    }

    public function test_invalid_dates_are_rejected_before_querying_logs(): void
    {
        $scopes = Mockery::mock(RoleScopeService::class);
        $scopes->shouldNotReceive('auditLogs');
        $request = Request::create('/api/v1/logs', 'GET', ['startDate' => '2026-02-30']);
        $this->expectException(ValidationException::class);
        (new AuditLogController($scopes))->index($request);
    }
}
