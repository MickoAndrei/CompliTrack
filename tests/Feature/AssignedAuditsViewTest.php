<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\AuditorAuditListing;
use MongoDB\BSON\ObjectId;
use Tests\TestCase;

class AssignedAuditsViewTest extends TestCase
{
    public function test_database_failure_displays_retry_without_fabricated_zero_metrics(): void
    {
        $this->withoutVite();
        $user = new User(['fullName' => 'Assigned Auditor', 'roleId' => 2, 'isActive' => true]);
        $user->setAttribute('_id', new ObjectId());
        $this->mock(AuditorAuditListing::class)->shouldReceive('page')->once()->andThrow(new \MongoDB\Driver\Exception\ConnectionTimeoutException('Database unavailable'));
        $this->actingAs($user)->get('/audits')->assertStatus(503)->assertSee('Assigned audits unavailable')->assertSee('Retry loading audits')
            ->assertDontSee('data-metric=', false)->assertDontSee('No Assigned Audits Yet');
    }
}
