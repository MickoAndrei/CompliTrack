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
            foreach (['/api/v1/system/dashboard', '/api/v1/system/dashboard/health', '/api/v1/dashboard/health', '/api/v1/documents/organization', '/super-admin/dashboard', '/super-admin/departments'] as $url) {
                $this->getJson($url)->assertForbidden();
            }
            $this->patchJson('/api/v1/documents/organization/'.new ObjectId(), ['responsibility' => 'college_dean', 'unitIds' => [], 'collegeIds' => []])->assertForbidden();
        }
        $this->actingAs($this->actor());
        foreach (['dashboard', 'users', 'roles', 'departments', 'documents', 'logs', 'settings'] as $page) { $this->get('/super-admin/'.$page)->assertOk(); }
        $this->get('/audits')->assertForbidden();
        $this->postJson('/api/v1/compliance-checks', ['departmentId' => (string) new ObjectId(), 'auditeeId' => (string) new ObjectId(),
            'referenceStandard' => 'ISO9001-9.2', 'result' => 'compliant'])->assertForbidden();
        $this->get('/super-admin/dashboard')->assertSee('Not Yet Assessed')->assertSee('Archive Storage Usage')->assertSee('System Health');
        $this->get('/super-admin/departments')->assertSee('Positions')->assertSee('College Secretary');
    }

    public function test_department_college_dropdown_choices_can_be_saved(): void
    {
        $this->actingAs($this->actor());
        College::create(['collegeCode' => 'CET', 'collegeName' => 'College of Engineering and Technology', 'isActive' => true]);
        foreach (['CET', 'College of Agriculture', 'College of Arts and Sciences'] as $index => $college) {
            $this->postJson('/api/v1/departments', ['departmentCode' => 'CHOICE-'.$index,
                'departmentName' => $index === 0 ? array_values(config('qms.departments'))[0] : 'Test department '.$index,
                'collegeName' => $college, 'campusName' => 'Claveria'])->assertCreated()->assertJsonPath('data.collegeName', $college);
        }
        $department = Department::where('collegeName', 'College of Agriculture')->firstOrFail();
        $this->patchJson('/api/v1/departments/'.$department->getKey(), ['collegeName' => 'College of Arts and Sciences'])
            ->assertOk()->assertJsonPath('data.collegeName', 'College of Arts and Sciences');
        foreach (config('qms.campuses') as $campus) {
            $college = array_key_first(config('qms.campusColleges')[$campus]);
            if ($college === 'CET') { $college = 'College of Agriculture'; }
            $this->patchJson('/api/v1/departments/'.$department->getKey(), ['campusName' => $campus, 'collegeName' => $college])
                ->assertOk()->assertJsonPath('data.campusName', $campus);
        }
    }

    public function test_departments_reject_colleges_from_a_different_campus_and_accept_campus_level_records(): void
    {
        $this->actingAs($this->actor());
        $payload = ['departmentCode' => 'OFFICIAL-TEST', 'departmentName' => 'Test department', 'campusName' => 'Cagayan de Oro'];
        $this->postJson('/api/v1/departments', $payload + ['collegeName' => 'College of Agriculture'])
            ->assertUnprocessable()->assertJsonValidationErrors('collegeName');
        $this->postJson('/api/v1/departments', $payload)->assertUnprocessable()->assertJsonValidationErrors('collegeName');
        $id = $this->postJson('/api/v1/departments', $payload + ['collegeName' => 'College of Nursing'])
            ->assertCreated()->assertJsonPath('data.collegeName', 'College of Nursing')->json('data._id');
        $this->patchJson('/api/v1/departments/'.$id, ['campusName' => 'Claveria'])
            ->assertUnprocessable()->assertJsonValidationErrors('collegeName');
        $this->assertSame('Cagayan de Oro', Department::findOrFail($id)->campusName);
        $this->patchJson('/api/v1/departments/'.$id, ['campusName' => 'Claveria', 'collegeName' => 'College of Arts and Sciences'])->assertOk();
        $this->patchJson('/api/v1/departments/'.$id, ['campusName' => 'Jasaan', 'collegeName' => null])
            ->assertOk()->assertJsonPath('data.collegeName', null);
        $this->postJson('/api/v1/departments', array_merge($payload, ['campusName' => 'Jasaan', 'collegeName' => 'College of Agriculture']))
            ->assertUnprocessable()->assertJsonValidationErrors('collegeName');
        $this->postJson('/api/v1/departments', array_merge($payload, ['departmentCode' => 'CAMPUS-LEVEL', 'campusName' => 'Jasaan']))
            ->assertCreated()->assertJsonPath('data.collegeName', null);
    }

    public function test_department_sorting_and_search_apply_before_five_row_pagination(): void
    {
        $this->actingAs($this->actor());
        foreach (['Zulu', 'Alpha', 'Mike', 'Bravo', 'Yankee', 'Charlie'] as $index => $name) {
            Department::create(['departmentCode' => 'SORT-'.$index, 'departmentName' => 'Sort '.$name,
                'collegeName' => 'College of Agriculture', 'campusName' => 'Claveria', 'isActive' => true]);
        }
        $query = '/api/v1/departments?perPage=5&search=Sort&includeSummary=1';
        $this->getJson($query.'&sortBy=departmentName&sortDirection=asc')->assertOk()->assertJsonCount(5, 'data')
            ->assertJsonPath('data.0.departmentName', 'Sort Alpha')->assertJsonPath('meta.lastPage', 2)->assertJsonPath('summary.total', 6);
        $this->getJson($query.'&page=2&sortBy=departmentName&sortDirection=asc')->assertOk()->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.departmentName', 'Sort Zulu');
        $this->getJson($query.'&sortBy=departmentName&sortDirection=desc')->assertOk()->assertJsonPath('data.0.departmentName', 'Sort Zulu');
        $this->getJson($query.'&sortBy=departmentCode&sortDirection=desc')->assertOk()->assertJsonPath('data.0.departmentCode', 'SORT-5');
        $this->getJson($query.'&sortBy=campusName&sortDirection=asc')->assertOk()->assertJsonCount(5, 'data');
        $this->getJson($query.'&sortBy=collegeName&sortDirection=desc')->assertOk()->assertJsonCount(5, 'data');
        $this->getJson($query.'&sortBy=password')->assertUnprocessable();
        $this->getJson($query.'&sortDirection=invalid')->assertUnprocessable();
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
