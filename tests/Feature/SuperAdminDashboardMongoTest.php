<?php

namespace Tests\Feature;

use App\Models\{ArchiveDocument, Audit, College, ComplianceCheck, Department, User};
use Illuminate\Support\Facades\{DB, Http};
use MongoDB\BSON\ObjectId;
use Tests\TestCase;

class SuperAdminDashboardMongoTest extends TestCase
{
    private ?string $databaseName = null;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        if (getenv('RUN_MONGODB_AUDIT_TESTS') !== '1') { $this->markTestSkipped('Requires an isolated MongoDB database.'); }
        $this->databaseName = 'complitrack_dashboard_test_'.bin2hex(random_bytes(5));
        config(['database.connections.mongodb.database' => $this->databaseName]);
        DB::purge('mongodb');
        $this->assertSame($this->databaseName, DB::connection('mongodb')->getMongoDB()->getDatabaseName());
    }

    protected function tearDown(): void
    {
        if ($this->databaseName) {
            $database = DB::connection('mongodb')->getMongoDB();
            if ($database->getDatabaseName() === $this->databaseName && str_starts_with($this->databaseName, 'complitrack_dashboard_test_')) { $database->drop(); }
            DB::purge('mongodb');
        }
        parent::tearDown();
    }

    private function actor(int $role = 0): User
    {
        return User::create(['fullName' => 'Dashboard test', 'email' => bin2hex(random_bytes(5)).'@example.test', 'roleId' => $role, 'isActive' => true]);
    }

    public function test_dashboard_distinguishes_completed_evaluations_legacy_checks_cars_and_authorized_storage(): void
    {
        $super = $this->actor();
        $college = College::create(['collegeCode' => 'CET', 'collegeName' => 'CET', 'isActive' => true]);
        $units = collect(config('qms.departments'))->map(fn ($name, $code) => Department::create(['departmentCode' => $code,
            'departmentName' => $name, 'collegeName' => 'CET', 'collegeId' => $college->getKey(), 'unitType' => 'academic_department', 'ownershipVerified' => true, 'isActive' => true]))->values();
        $office = Department::create(['departmentName' => 'Office of the Dean', 'collegeName' => 'CET', 'collegeId' => $college->getKey(), 'unitType' => 'college_office', 'ownershipVerified' => true, 'isActive' => true]);
        Department::create(['departmentName' => 'Legacy Engineering', 'collegeName' => 'CET', 'unitType' => 'legacy_unclassified', 'isActive' => true]);
        User::create(['fullName' => 'Inactive registered account', 'roleId' => 3, 'isActive' => false]);
        $accepted = Audit::create(['auditCode' => 'COMPLETED', 'departmentId' => $units[0]->getKey(), 'status' => 'accepted',
            'items' => [['result' => 'C'], ['result' => 'N'], ['result' => 'O']]]);
        Audit::create(['auditCode' => 'OBSERVATIONS-ONLY', 'departmentId' => $units[1]->getKey(), 'status' => 'accepted', 'items' => [['result' => 'O']]]);
        Audit::create(['auditCode' => 'ZERO-COMPLIANCE', 'departmentId' => $units[2]->getKey(), 'status' => 'accepted', 'items' => [['result' => 'N']]]);
        foreach (['draft', 'ready', 'submitted', 'returned'] as $status) {
            Audit::create(['auditCode' => strtoupper($status), 'departmentId' => $units[3]->getKey(), 'status' => $status, 'items' => [['result' => 'C']]]);
        }
        ComplianceCheck::create(['checkCode' => 'LEGACY-OPEN', 'departmentId' => $units[3]->getKey(), 'result' => 'non_compliant', 'correctiveStatus' => 'open', 'dueDate' => now()->subDays(2)]);
        $linked = ComplianceCheck::create(['checkCode' => 'LINKED-IN-PROGRESS', 'departmentId' => $units[0]->getKey(), 'auditeeId' => $super->getKey(), 'result' => 'non_compliant', 'correctiveStatus' => 'in_progress', 'dueDate' => now()->addDays(2)]);
        $linked->forceFill(['sourceAuditId' => new ObjectId((string) $accepted->getKey())])->save();
        ComplianceCheck::create(['checkCode' => 'CLOSED', 'result' => 'non_compliant', 'correctiveStatus' => 'closed']);
        ComplianceCheck::create(['checkCode' => 'COMPLIANT-IS-NOT-A-CAR', 'result' => 'compliant', 'correctiveStatus' => 'open']);
        foreach ([[$units[0], 'file', 1536, true], [$office, 'file', 1048576, true], [$units[0], 'physical-original', 9999, true], [$units[0], 'file', 99999, false]] as [$unit, $type, $bytes, $verified]) {
            ArchiveDocument::create(['departmentId' => $unit->getKey(), 'collegeId' => $college->getKey(), 'recordType' => $type, 'fileSize' => $bytes, 'ownershipVerified' => $verified]);
        }
        $response = $this->actingAs($super)->getJson('/api/v1/system/dashboard')->assertOk()
            ->assertJsonPath('data.stats.totalUsers', 2)->assertJsonPath('data.stats.cetDepartments', 5)
            ->assertJsonPath('data.stats.activeAudits', 4)->assertJsonPath('data.stats.openCars', 2)
            ->assertJsonPath('data.stats.archivedDocuments', 3)->assertJsonPath('data.stats.archiveStorageBytes', 1050112)
            ->assertJsonPath('data.recordSummary.legacyChecks', 3)->assertJsonPath('data.recordSummary.checklistLinkedCars', 1)
            ->assertJsonCount(5, 'data.departmentCompliance')->assertJsonCount(6, 'data.recentAuditActivity')->assertJsonCount(6, 'data.userGrowth')
            ->assertJsonPath('data.correctiveAttention.0.reference', 'LEGACY-OPEN')->assertJsonPath('data.correctiveAttention.0.overdue', true);
        $comparison = collect($response->json('data.departmentCompliance'))->keyBy('code');
        $this->assertEquals(50, $comparison['CET-ABE']['percentage']);
        $this->assertSame(['N' => 1, 'C' => 1, 'O' => 1], $comparison['CET-ABE']['counts']);
        $this->assertNull($comparison['CET-ENV']['percentage']);
        $this->assertEquals(0, $comparison['CET-FPT']['percentage']);
        $this->assertNull($comparison['CET-HM']['percentage']);
        $this->assertNull($comparison['CET-IT']['percentage']);
        $this->assertSame(0, $response->json('data.userGrowth.0.count'));
        $linked->update(['correctiveStatus' => 'closed']);
        $afterClosure = $this->getJson('/api/v1/system/dashboard')->assertOk()->assertJsonPath('data.stats.openCars', 1);
        $this->assertSame(1, collect($afterClosure->json('data.departmentCompliance'))->keyBy('code')['CET-ABE']['counts']['N']);
    }

    public function test_management_routes_remain_super_admin_only_and_do_not_grant_audit_editing(): void
    {
        $this->getJson('/api/v1/system/dashboard')->assertUnauthorized();
        foreach ([1, 2, 3] as $role) {
            $this->actingAs($this->actor($role));
            foreach (['/api/v1/system/dashboard', '/api/v1/system/dashboard/health', '/api/v1/dashboard/health', '/api/v1/archives/organization', '/super-admin/dashboard', '/super-admin/departments'] as $url) {
                $this->getJson($url)->assertForbidden();
            }
            $this->patchJson('/api/v1/archives/organization/'.new ObjectId(), ['responsibility' => 'college_dean', 'unitIds' => [], 'collegeIds' => []])->assertForbidden();
        }
        $this->actingAs($this->actor());
        foreach (['dashboard', 'users', 'roles', 'departments', 'archives', 'logs', 'settings'] as $page) { $this->get('/super-admin/'.$page)->assertOk(); }
        $this->get('/audits')->assertForbidden();
        $this->postJson('/api/v1/compliance-checks', ['departmentId' => (string) new ObjectId(), 'auditeeId' => (string) new ObjectId(),
            'referenceStandard' => 'ISO9001-9.2', 'result' => 'compliant'])->assertForbidden();
        $this->get('/super-admin/dashboard')->assertSee('Not Yet Assessed')->assertSee('Archive Storage Usage')->assertSee('System Health');
        $this->get('/super-admin/departments')->assertSee('Organizational Responsibilities')->assertSee('College Secretary');
    }

    public function test_health_checks_use_realtime_response_and_do_not_fabricate_availability(): void
    {
        $this->actingAs($this->actor());
        Http::preventStrayRequests();
        Http::fakeSequence()->push(['status' => 'ok', 'database' => 'ok'])
            ->push(['status' => 'unavailable', 'database' => 'unavailable'], 503)->push('<html>Wrong service</html>')
            ->push(['status' => 'ok', 'database' => 'ok', 'pushConfigured' => true]);
        $this->getJson('/api/v1/system/dashboard/health')->assertOk()->assertJsonPath('data.laravel', 'ok')
            ->assertJsonPath('data.mongodb', 'ok')->assertJsonPath('data.realtime', 'ok')
            ->assertJsonCount(4, 'data.components')->assertJsonPath('data.components.1.name', 'Node.js Service')
            ->assertJsonPath('data.components.3.name', 'FCM Push Service')->assertJsonPath('data.components.3.status', 'Not Configured');
        $this->getJson('/api/v1/system/dashboard/health')->assertOk()->assertJsonPath('data.realtime', 'unavailable')
            ->assertJsonPath('data.components.1.status', 'Unavailable')->assertJsonPath('data.components.3.status', 'Unknown');
        $this->getJson('/api/v1/system/dashboard/health')->assertOk()->assertJsonPath('data.realtime', 'unavailable');
        config(['services.complitrack.realtime_health_url' => 'https://realtime.example.test/service-health']);
        $this->getJson('/api/v1/dashboard/health')->assertOk()->assertJsonPath('data.components.3.status', 'Configured');
        Http::assertSent(fn ($request) => $request->url() === 'https://realtime.example.test/service-health');
    }
}
