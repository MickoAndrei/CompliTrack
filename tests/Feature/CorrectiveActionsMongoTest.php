<?php

namespace Tests\Feature;

use App\Models\{ArchiveDocument, Audit, ComplianceCheck, Department, Notification, User};
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class CorrectiveActionsMongoTest extends TestCase
{
    private ?string $databaseName = null;
    private User $auditor;
    private User $auditee;
    private Department $department;

    protected function setUp(): void
    {
        parent::setUp();
        if (getenv('RUN_MONGODB_AUDIT_TESTS') !== '1') { $this->markTestSkipped('Uses an isolated temporary MongoDB database.'); }
        $this->databaseName = 'ct_car_test_'.bin2hex(random_bytes(6));
        config(['database.connections.mongodb.database' => $this->databaseName]);
        DB::purge('mongodb');
        $this->assertSame($this->databaseName, DB::connection('mongodb')->getMongoDB()->getDatabaseName());
        $this->withoutVite();
        \App\Models\Role::create(['roleId' => 2, 'permissions' => ['cars.verify', 'cars.close']]);
        $this->department = Department::create(['departmentName' => 'Engineering', 'collegeName' => 'CET', 'ownershipVerified' => true, 'unitType' => 'academic_department', 'isActive' => true]);
        $this->auditor = $this->account(2, 'Assigned Auditor');
        $this->auditee = $this->account(3, 'Responsible Auditee');
    }

    protected function tearDown(): void
    {
        if ($this->databaseName) {
            $db = DB::connection('mongodb')->getMongoDB();
            if ($db->getDatabaseName() === $this->databaseName && str_starts_with($this->databaseName, 'ct_car_test_')) { $db->drop(); }
            DB::purge('mongodb');
        }
        parent::tearDown();
    }

    private function account(int $role, string $name = 'Other account'): User
    {
        return User::create(['fullName' => $name, 'roleId' => $role, 'isActive' => true, 'departmentId' => $this->department->getKey(), 'assignedDepartmentIds' => [$this->department->getKey()]]);
    }

    private function car(array $extra = []): ComplianceCheck
    {
        return ComplianceCheck::create($extra + ['checkCode' => 'CAR-'.bin2hex(random_bytes(4)), 'departmentId' => $this->department->getKey(),
            'auditorId' => $this->auditor->getKey(), 'auditeeId' => $this->auditee->getKey(), 'result' => 'non_compliant',
            'remarks' => 'Required control is not documented', 'referenceStandard' => 'ISO9001-9.2', 'correctiveStatus' => 'open',
            'statusHistory' => [['status' => 'open', 'timestamp' => now()->subDays(3)->toIso8601String(), 'changedByUserId' => $this->auditor->getKey(), 'remarks' => 'Finding issued']]]);
    }

    public function test_auditor_navigation_consolidates_evaluations_and_preserves_historical_routes(): void
    {
        $car = $this->car();
        $dashboard = $this->actingAs($this->auditor)->get('/auditor/dashboard')->assertOk()
            ->assertSee('href="/audits"', false)->assertSee('href="/auditor/historical-records"', false)
            ->assertSee('href="/auditor/corrective-actions"', false)
            ->assertDontSee('href="/auditor/checks"', false)->assertDontSee('href="/auditor/check-create"', false)
            ->assertDontSee('New Compliance Check');
        preg_match('~<nav[^>]+aria-label="Main navigation"[^>]*>(.*?)</nav>~s', $dashboard->getContent(), $navigation);
        $this->assertNotEmpty($navigation);
        $this->assertStringNotContainsString('Compliance Checks', $navigation[1]);
        $this->assertStringNotContainsString('Historical Compliance Records', $navigation[1]);
        foreach (['Dashboard', 'My Assigned Audits', 'Documents', 'Corrective Actions', 'Notifications'] as $label) {
            $this->assertStringContainsString($label, $navigation[1]);
        }
        $this->get('/auditor/historical-records')->assertOk()->assertSee('Historical Compliance Records')
            ->assertSee('detailBase:')->assertDontSee('New Compliance Check')->assertDontSee('checkCreatePage');
        $this->get('/auditor/checks?search=legacy')->assertRedirect('/auditor/historical-records?search=legacy');
        $this->get('/auditor/check-create')->assertRedirect('/audits');
        $this->get('/auditor/checks/'.$car->getKey())->assertOk()->assertSee('Back to historical records');
        $this->actingAs($this->account(2))->get('/auditor/checks/'.$car->getKey())->assertNotFound();
        foreach ([0, 1, 3] as $role) {
            $this->actingAs($this->account($role))->get('/auditor/historical-records')->assertForbidden();
            $this->get('/auditor/checks/'.$car->getKey())->assertForbidden();
            $this->get('/auditor/check-create')->assertForbidden();
        }
    }

    public function test_historical_reads_preserve_records_evidence_cars_and_notification_destinations(): void
    {
        $archive = ArchiveDocument::create(['originalName' => 'Historical evidence.pdf', 'departmentId' => $this->department->getKey(),
            'ownershipVerified' => true, 'recordType' => 'file', 'downloadAllowed' => true, 'uploadedByUserId' => $this->auditee->getKey()]);
        $car = $this->car(['linkedArchiveIds' => [$archive->getKey()]]);
        $compliant = $this->car(['result' => 'compliant', 'correctiveStatus' => null, 'statusHistory' => []]);
        $foreign = $this->car(['auditorId' => $this->account(2)->getKey(), 'remarks' => 'PRIVATE legacy finding']);
        $notification = Notification::create(['recipientUserId' => $this->auditor->getKey(), 'relatedCheckId' => $car->getKey(),
            'type' => 'evidence_submitted', 'title' => 'Legacy evidence received', 'message' => 'Review the original check.',
            'destination' => '/auditor/checks/'.$car->getKey(), 'isRead' => false]);
        $before = [$car->fresh()->toArray(), $compliant->fresh()->toArray(), $notification->fresh()->toArray()];
        $this->actingAs($this->auditor)->getJson('/api/v1/compliance-checks')->assertOk()->assertJsonPath('meta.total', 2)
            ->assertDontSee('PRIVATE legacy finding');
        $this->getJson('/api/v1/compliance-checks/'.$car->getKey())->assertOk()
            ->assertJsonPath('data.linkedArchiveIds.0', (string) $archive->getKey())->assertJsonPath('data.correctiveStatus', 'open');
        $this->getJson('/api/v1/compliance-checks/'.$compliant->getKey())->assertOk()->assertJsonPath('data.result', 'compliant');
        $this->getJson('/api/v1/compliance-checks/'.$foreign->getKey())->assertNotFound();
        $this->get('/api/v1/notifications/'.$notification->getKey().'/open')->assertRedirect('/auditor/checks/'.$car->getKey());
        $this->get('/auditor/checks/'.$car->getKey())->assertOk()->assertSee('Historical evidence.pdf')
            ->assertSee('Finding issued')->assertSee('/api/v1/documents/'.$archive->getKey().'/download');
        $this->getJson('/api/v1/compliance-checks/'.$car->getKey().'?correctiveActions=1')->assertOk()
            ->assertJsonPath('data.evidence.0.name', 'Historical evidence.pdf');
        $this->assertSame($before, [$car->fresh()->toArray(), $compliant->fresh()->toArray(), $notification->fresh()->toArray()]);
        $this->assertSame(3, ComplianceCheck::count());
        $this->assertSame(0, Audit::count());
        $this->assertSame(1, ArchiveDocument::count());
        $this->auditor->update(['isActive' => false]);
        $this->actingAs($this->auditor)->getJson('/api/v1/compliance-checks')->assertUnauthorized();
    }

    public function test_cards_search_filters_and_pagination_use_only_authorized_cars(): void
    {
        $this->car(); $this->car(['correctiveStatus' => 'in_progress']); $this->car(['correctiveStatus' => 'closed']);
        $waiting = $this->car(['correctiveStatus' => 'in_progress']);
        $waiting->forceFill(['evidenceResponses' => [['method' => 'digital-reference', 'reference' => 'Revised control', 'at' => now()->subDay()->toIso8601String(), 'author' => (string) $this->auditee->getKey()]]])->save();
        $this->car(['auditorId' => $this->account(2)->getKey(), 'remarks' => 'PRIVATE other auditor']);
        $this->car(['result' => 'compliant', 'correctiveStatus' => null]);
        $foreign = Department::create(['departmentName' => 'Unassigned', 'collegeName' => 'OTHER']);
        $this->car(['departmentId' => $foreign->getKey(), 'remarks' => 'PRIVATE revoked assignment']);
        $this->actingAs($this->auditor)->getJson('/api/v1/compliance-checks?correctiveActions=1&perPage=1')->assertOk()
            ->assertJsonPath('summary.total', 4)->assertJsonPath('summary.inProgress', 1)->assertJsonPath('summary.awaitingVerification', 1)
            ->assertJsonPath('summary.closed', 1)->assertJsonPath('meta.lastPage', 4)->assertJsonCount(1, 'data')->assertDontSee('PRIVATE');
        $this->getJson('/api/v1/compliance-checks?correctiveActions=1&status=awaiting_verification')->assertOk()
            ->assertJsonPath('meta.total', 1)->assertJsonPath('data.0._id', (string) $waiting->getKey())->assertJsonPath('summary.total', 4);
        $this->getJson('/api/v1/compliance-checks?correctiveActions=1&search=Responsible&departmentId='.$this->department->getKey().'&perPage=2&page=2')
            ->assertOk()->assertJsonCount(2, 'data')->assertJsonPath('meta.currentPage', 2);
        $this->getJson('/api/v1/compliance-checks?correctiveActions=1&departmentId='.$foreign->getKey())->assertOk()->assertJsonPath('meta.total', 0);
        $this->getJson('/api/v1/compliance-checks?correctiveActions=1&status=invalid')->assertUnprocessable();
    }

    public function test_details_reuse_source_finding_history_and_permitted_evidence(): void
    {
        $audit = Audit::create(['auditorId' => $this->auditor->getKey(), 'departmentId' => $this->department->getKey(), 'items' => [
            ['id' => 'item-1', 'question' => 'Are inspection records maintained?', 'finding' => 'Missing inspection register', 'reference' => 'ISO clause 9.2', 'remarks' => 'Register reviewed on site'],
        ]]);
        $car = $this->car(['correctiveStatus' => 'in_progress']);
        $allowed = ArchiveDocument::create(['originalName' => 'Permitted.pdf', 'departmentId' => $this->department->getKey(), 'ownershipVerified' => true,
            'recordType' => 'file', 'downloadAllowed' => true, 'uploadedByUserId' => $this->auditee->getKey()]);
        $foreign = ArchiveDocument::create(['originalName' => 'PRIVATE FILE.pdf', 'departmentId' => new \MongoDB\BSON\ObjectId(), 'ownershipVerified' => true]);
        $car->forceFill(['sourceAuditId' => $audit->getKey(), 'sourceItemId' => 'item-1', 'linkedArchiveIds' => [$allowed->getKey(), $foreign->getKey()],
            'evidenceResponses' => [['method' => 'physical-original', 'notes' => 'Original available', 'availability' => 'available', 'author' => (string) $this->auditee->getKey(), 'at' => now()->toIso8601String()]],
            'statusHistory' => [
                ['status' => 'open', 'timestamp' => '2026-01-01T10:00:00Z', 'changedByUserId' => $this->auditor->getKey()],
                ['status' => 'returned_for_revision', 'timestamp' => '2026-01-02T10:00:00Z', 'changedByUserId' => $this->auditor->getKey(), 'remarks' => 'Revise inspection evidence'],
                ['status' => 'in_progress', 'timestamp' => '2026-01-03T10:00:00Z', 'changedByUserId' => $this->auditor->getKey()],
            ]])->save();
        $detail = $this->actingAs($this->auditor)->getJson('/api/v1/compliance-checks/'.$car->getKey().'?correctiveActions=1')->assertOk()
            ->assertJsonPath('data.auditItem', 'Are inspection records maintained?')->assertJsonPath('data.finding', 'Missing inspection register')
            ->assertJsonPath('data.auditorRemarks', 'Register reviewed on site')->assertJsonPath('data.allowedTransitions.0', 'closed')
            ->assertJsonPath('data.evidence.0.canDownload', true)->assertDontSee('PRIVATE FILE')->assertDontSee('encryptedPath');
        $this->assertTrue(collect($detail->json('data.timeline'))->contains(fn ($event) => $event['returned'] && $event['remarks'] === 'Revise inspection evidence'));
        $this->assertTrue(collect($detail->json('data.timeline'))->contains(fn ($event) => $event['actor'] === 'Assigned Auditor' && $event['at'] === '2026-01-01T10:00:00+00:00'));
        $this->assertSame('in_progress', $car->fresh()->correctiveStatus);
        $this->getJson('/api/v1/compliance-checks?correctiveActions=1&search=inspection')->assertOk()->assertJsonPath('meta.total', 1);
        $allowed->update(['downloadAllowed' => false]);
        $this->getJson('/api/v1/compliance-checks/'.$car->getKey().'?correctiveActions=1')->assertOk()->assertJsonPath('data.evidence.0.canDownload', false);
    }

    public function test_admin_register_preserves_legacy_records_and_exports_official_columns_in_order(): void
    {
        $admin = $this->account(1, 'Monitoring Admin');
        $legacy = $this->car(['checkCode' => 'LEGACY-CHECK']);
        $formal = $this->car(['checkCode' => 'CHECK-FORMAL', 'dueDate' => now()->subDay(), 'carLog' => [
            'carNo' => 'CAR-2026-001', 'dateSubmitted' => '2026-11-01', 'issue' => 'Inspection evidence missing',
            'submittedByName' => 'Maria Santos', 'resultsFail' => 'Register incomplete', 'remarks' => '<script>alert(1)</script>',
        ]]);
        $before = [$legacy->fresh()->toArray(), $formal->fresh()->toArray()];
        $page = $this->actingAs($admin)->get('/admin/compliance-records?monitor=1')->assertOk()
            ->assertSee('Corrective Action Register')->assertSee('Legacy check')->assertSee('Responsible Auditee')
            ->assertSee('Inspection evidence missing')->assertSee('Target Closed-Out Date');
        $register = app(\App\Services\CarRegister::class);
        $request = \Illuminate\Http\Request::create('/admin/compliance-records', 'GET', ['monitor' => 1]);
        $request->setUserResolver(fn () => $admin);
        $data = $register->index($request);
        $this->assertSame(1, $data['summary']['Open']);
        $this->assertSame(1, $data['summary']['Overdue']);
        $this->assertSame(1, $data['legacyCount']);
        $export = $this->get('/admin/compliance-records?monitor=1&export=log')->assertOk()
            ->assertSee('A3 landscape')->assertSee('CAR-2026-001')->assertDontSee('LEGACY-CHECK')->assertDontSee('<script>alert(1)</script>', false);
        preg_match_all('/<th>(.*?)<\/th>/u', $export->getContent(), $headings);
        $this->assertSame(\App\Services\CarRegister::HEADINGS, $headings[1]);
        $detail = $this->get('/admin/compliance-records?monitor=1&history=1&car='.$formal->getKey())->assertOk()
            ->assertSee('Results — Fail')->assertSee('Register incomplete')->assertSee('Not recorded')->assertSee('Assigned Auditor')
            ->assertDontSee('changedByUserId');
        if (getenv('WRITE_CAR_UI_FIXTURES') === '1') {
            $directory = storage_path('framework/testing/car-register');
            \Illuminate\Support\Facades\File::ensureDirectoryExists($directory);
            file_put_contents($directory.'/register.html', $page->getContent());
            file_put_contents($directory.'/detail.html', $detail->getContent());
            file_put_contents($directory.'/export.html', $export->getContent());
        }
        $this->assertSame($before, [$legacy->fresh()->toArray(), $formal->fresh()->toArray()]);
    }

    public function test_admin_register_filters_pagination_and_organizational_boundaries(): void
    {
        $admin = $this->account(1);
        for ($i = 1; $i <= 12; $i++) {
            $this->car(['carLog' => ['carNo' => sprintf('CAR-%02d', $i)], 'dueDate' => now()->addDays($i)]);
        }
        $private = $this->car(['departmentId' => Department::create(['collegeName' => 'OTHER', 'departmentName' => 'Private'])->getKey(),
            'carLog' => ['carNo' => 'PRIVATE-CAR']]);
        $this->actingAs($admin)->get('/admin/compliance-records?monitor=1&sort=reference&page=2&perPage=10')->assertOk()
            ->assertSee('CAR-11')->assertSee('CAR-12')->assertDontSee('CAR-01')->assertDontSee('PRIVATE-CAR');
        $this->get('/admin/compliance-records?monitor=1&search=CAR-03')->assertOk()->assertSee('CAR-03')->assertDontSee('CAR-04');
        $this->get('/admin/compliance-records?monitor=1&overdue=1')->assertOk()->assertDontSee('CAR-01');
        foreach (['history=1&car=', 'export=log&car='] as $query) {
            $this->get('/admin/compliance-records?monitor=1&'.$query.$private->getKey())->assertNotFound();
        }
        $admin->update(['archiveResponsibility' => 'department_secretary', 'archiveUnitIds' => [(string) new \MongoDB\BSON\ObjectId()]]);
        $this->actingAs($admin)->get('/admin/compliance-records?monitor=1')->assertOk()->assertDontSee('CAR-01');
        $this->get('/admin/compliance-records?monitor=1&sort=bad')->assertSessionHasErrors('sort');
    }

    public function test_cluster_monitoring_cannot_widen_college_scope_or_reach_foreign_detail_and_export(): void
    {
        $college = \App\Models\College::create(['collegeName' => 'CET', 'isActive' => true, 'clusterId' => 'north']);
        $this->department->forceFill(['collegeId' => $college->getKey()])->save();
        $admin = $this->account(1);
        $admin->update(['archiveResponsibility' => 'cluster_administrator', 'archiveCluster' => 'north']);
        $allowed = $this->car(['carLog' => ['carNo' => 'NORTH-CAR']]);
        $otherCollege = \App\Models\College::create(['collegeName' => 'CET', 'isActive' => true, 'clusterId' => 'south']);
        $outside = Department::create(['departmentName' => 'South CET', 'collegeName' => 'CET', 'collegeId' => $otherCollege->getKey(),
            'unitType' => 'academic_department', 'ownershipVerified' => true, 'isActive' => true]);
        $private = $this->car(['departmentId' => $outside->getKey(), 'carLog' => ['carNo' => 'PRIVATE-SOUTH-CAR']]);
        $this->actingAs($admin)->get('/admin/compliance-records?monitor=1')->assertOk()->assertSee('NORTH-CAR')->assertDontSee('PRIVATE-SOUTH-CAR');
        $this->get('/admin/compliance-records?monitor=1&export=log')->assertOk()->assertSee('NORTH-CAR')->assertDontSee('PRIVATE-SOUTH-CAR');
        $this->get('/admin/compliance-records?monitor=1&history=1&car='.$private->getKey())->assertNotFound();
        $admin->update(['archiveCluster' => 'south']);
        $this->actingAs($admin)->get('/admin/compliance-records?monitor=1&car='.$allowed->getKey())->assertNotFound();
    }

    public function test_closure_requires_both_explicit_permissions_and_ignores_wildcards(): void
    {
        $car = $this->car(['correctiveStatus' => 'in_progress']);
        $role = \App\Models\Role::where('roleId', 2)->firstOrFail();
        $url = '/api/v1/compliance-checks/'.$car->getKey().'/status';
        foreach ([[], ['*'], ['cars.verify'], ['cars.close']] as $permissions) {
            $role->update(['permissions' => $permissions]);
            $this->actingAs($this->auditor)->patchJson($url, ['correctiveStatus' => 'closed', 'remarks' => 'Verified on site'])->assertForbidden();
            $this->getJson('/api/v1/compliance-checks/'.$car->getKey().'?correctiveActions=1')->assertOk()->assertJsonCount(0, 'data.allowedTransitions');
        }
        $this->assertSame('in_progress', $car->fresh()->correctiveStatus);
        $this->assertCount(1, $car->fresh()->statusHistory);
        $this->assertSame(0, Notification::count());
        $role->update(['permissions' => ['cars.verify', 'cars.close']]);
        $this->patchJson($url, ['correctiveStatus' => 'closed', 'remarks' => 'Verified on site'])->assertOk();
    }

    public function test_formal_metrics_separate_evidence_pending_status_results_and_deadline(): void
    {
        $admin = $this->account(1);
        $this->car(['carLog' => ['carNo' => 'FORMAL-OPEN']]);
        $this->car(['carLog' => ['carNo' => 'FORMAL-PROGRESS'], 'correctiveStatus' => 'in_progress', 'dueDate' => now()->subDays(2)]);
        $waiting = $this->car(['carLog' => ['carNo' => 'FORMAL-WAITING'], 'correctiveStatus' => 'in_progress']);
        $waiting->forceFill(['evidenceResponses' => [['method' => 'physical-original', 'at' => now()->toIso8601String(), 'author' => (string) $this->auditee->getKey()]]])->save();
        $this->car(['carLog' => ['carNo' => 'FORMAL-CLOSED'], 'correctiveStatus' => 'closed', 'closedAt' => now(), 'dueDate' => now()->subDays(3)]);
        $this->car(['dueDate' => now()->subDays(4)]);
        $request = \Illuminate\Http\Request::create('/', 'GET'); $request->setUserResolver(fn () => $admin);
        $data = app(\App\Services\CarRegister::class)->index($request);
        $this->assertSame(['Open' => 1, 'In Progress' => 1, 'Awaiting Verification' => 1, 'Overdue' => 1, 'Closed' => 1], $data['summary']);
        $this->assertTrue($data['exportRows']->every(fn ($row) => $row['verification'] === 'Not recorded'));
        $this->actingAs($admin)->get('/admin/compliance-records?monitor=1&status=awaiting_verification')->assertOk()
            ->assertSee('FORMAL-WAITING')->assertDontSee('FORMAL-PROGRESS');
    }

    public function test_only_assigned_active_auditor_can_verify_close_and_reopen_with_notes(): void
    {
        $car = $this->car(); $url = '/api/v1/compliance-checks/'.$car->getKey().'/status';
        foreach ([0, 1, 3] as $role) {
            $this->actingAs($this->account($role))->patchJson($url, ['correctiveStatus' => 'in_progress'])->assertForbidden();
            $this->patchJson($url, ['correctiveStatus' => 'closed', 'remarks' => 'Unauthorized closure'])->assertForbidden();
            $this->getJson('/api/v1/compliance-checks/'.$car->getKey().'?correctiveActions=1')->assertForbidden();
        }
        $this->actingAs($this->account(2))->patchJson($url, ['correctiveStatus' => 'in_progress'])->assertNotFound();
        $this->patchJson($url, ['correctiveStatus' => 'closed', 'remarks' => 'Not assigned'])->assertNotFound();
        $this->actingAs($this->auditor)->patchJson($url, ['correctiveStatus' => 'closed', 'remarks' => 'Skipped progress'])->assertUnprocessable();
        $compliant = $this->car(['result' => 'compliant', 'correctiveStatus' => 'in_progress']);
        $this->patchJson('/api/v1/compliance-checks/'.$compliant->getKey().'/status', ['correctiveStatus' => 'closed', 'remarks' => 'Invalid CAR'])->assertUnprocessable();
        $this->patchJson($url, ['correctiveStatus' => 'in_progress', 'remarks' => 'Implementation started'])->assertOk();
        $this->patchJson($url, ['correctiveStatus' => 'closed', 'remarks' => ' '])->assertUnprocessable();
        $this->patchJson($url, ['correctiveStatus' => 'closed', 'remarks' => 'Inspected physical register; correction verified'])->assertOk();
        $this->assertCount(3, $car->fresh()->statusHistory);
        $this->assertNotNull($car->fresh()->closedAt);
        $this->assertSame(2, Notification::where('relatedCheckId', \App\Support\MongoId::parse((string) $car->getKey()))->count());
        $this->patchJson($url, ['correctiveStatus' => 'open'])->assertUnprocessable();
        $this->patchJson($url, ['correctiveStatus' => 'open', 'remarks' => 'New discrepancy requires revision'])->assertOk();
        $this->assertNull($car->fresh()->closedAt);
        $this->assertCount(4, $car->fresh()->statusHistory);
        $this->auditor->update(['assignedDepartmentIds' => []]);
        $this->actingAs($this->auditor)->patchJson($url, ['correctiveStatus' => 'in_progress'])->assertForbidden();
        $this->getJson('/api/v1/compliance-checks/'.$car->getKey().'?correctiveActions=1')->assertNotFound();
        $this->auditor->update(['isActive' => false]);
        $this->actingAs($this->auditor)->getJson('/api/v1/compliance-checks?correctiveActions=1')->assertUnauthorized();
        $this->patchJson($url, ['correctiveStatus' => 'closed', 'remarks' => 'Inactive actor'])->assertUnauthorized();
    }
}
