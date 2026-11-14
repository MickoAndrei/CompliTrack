<?php

namespace Tests\Feature;

use App\Models\{ArchiveDocument, Audit, Department, Role, User};
use App\Services\{AuditAssignment, AuditPlanPermissions};
use Illuminate\Support\Facades\{DB, File, Hash};
use MongoDB\BSON\ObjectId;
use Tests\TestCase;

class AuditPlanApprovalMongoTest extends TestCase
{
    private ?string $database = null;
    private ?string $directory = null;
    private User $super;
    private User $atl;
    private User $auditor;
    private User $approver;
    private ArchiveDocument $archive;
    private Department $unit;
    private array $header;

    protected function setUp(): void
    {
        parent::setUp();
        if (getenv('RUN_MONGODB_AUDIT_TESTS') !== '1') { $this->markTestSkipped('Uses an isolated MongoDB database.'); }
        $this->database = 'ct_plan_test_'.bin2hex(random_bytes(6));
        config(['database.connections.mongodb.database' => $this->database]);
        DB::purge('mongodb');
        $this->assertSame($this->database, DB::connection('mongodb')->getMongoDB()->getDatabaseName());
        $this->directory = storage_path('framework/testing/'.$this->database);
        config(['system.path' => $this->directory.'/system']);
        $this->withoutVite();
        $this->seed(\Database\Seeders\RoleSeeder::class);
        $this->unit = Department::create(['collegeName' => 'CET', 'departmentCode' => 'CET-IT', 'departmentName' => 'Information Technology',
            'ownershipVerified' => true, 'isActive' => true, 'unitType' => 'academic_department']);
        $home = Department::create(['collegeName' => 'CET', 'departmentCode' => 'CET-ENV', 'departmentName' => 'Environmental Engineering',
            'ownershipVerified' => true, 'isActive' => true, 'unitType' => 'academic_department']);
        foreach (['super' => 0, 'atl' => 1, 'auditor' => 2, 'approver' => 3] as $property => $role) {
            $this->$property = User::create(['fullName' => 'Isolated '.$property, 'roleId' => $role, 'isActive' => true,
                'password' => Hash::make('IsolatedPassword123!'), 'departmentId' => $role === 2 ? $home->getKey() : $this->unit->getKey(),
                'assignedDepartmentIds' => $role === 2 ? [$this->unit->getKey()] : []]);
        }
        // Represents an existing ATL assignment; it must survive all approval operations.
        Audit::create(['title' => 'Preserved legacy assignment', 'auditCode' => 'LEGACY-PLAN-TEST', 'departmentId' => $this->unit->getKey(),
            'reviewerId' => $this->atl->getKey(), 'auditorId' => $this->auditor->getKey(), 'status' => 'draft', 'version' => 1]);
        $this->archive = ArchiveDocument::create(['title' => 'Controlled isolated audit plan', 'departmentId' => $this->unit->getKey(),
            'ownershipVerified' => true, 'uploadedByUserId' => $this->atl->getKey(), 'recordType' => 'physical-original',
            'documentReference' => 'ISOLATED-PLAN', 'physicalLocation' => 'Isolated test cabinet', 'visibility' => 'owner']);
        $this->header = ['unitIds' => [(string) $this->unit->getKey()], 'reviewerIds' => [(string) $this->atl->getKey()],
            'scope' => 'Process scope', 'criteria' => 'Controlled criteria', 'scheduleStart' => '2026-11-01', 'scheduleEnd' => '2026-12-31',
            'procedureReference' => 'Isolated independence procedure'];
    }

    protected function tearDown(): void
    {
        if ($this->database) {
            $db = DB::connection('mongodb')->getMongoDB();
            if ($db->getDatabaseName() === $this->database && str_starts_with($this->database, 'ct_plan_test_')) { $db->drop(); }
            DB::purge('mongodb');
        }
        if ($this->directory && is_dir($this->directory)) {
            $resolved = realpath($this->directory); $allowed = realpath(storage_path('framework/testing'));
            if ($resolved && $allowed && str_starts_with($resolved, $allowed.DIRECTORY_SEPARATOR)) { File::deleteDirectory($resolved); }
        }
        parent::tearDown();
    }

    private function permission(int $role, bool $enabled, int $version = 0, array $extra = [])
    {
        return $this->actingAs($this->super)->patchJson('/api/v1/roles/'.$role.'/permissions', array_replace([
            'permission' => AuditPlanPermissions::APPROVE, 'enabled' => $enabled, 'version' => $version,
            'password' => 'IsolatedPassword123!', 'remarks' => 'Explicit isolated role decision',
        ], $extra));
    }

    private function action(User $actor, string $action, array $extra = [])
    {
        return $this->actingAs($actor)->postJson('/api/v1/audit-plans/'.$this->archive->getKey(), array_replace([
            'action' => $action, 'version' => $this->archive->fresh()->auditPlan['version'] ?? 0, 'remarks' => 'Recorded '.$action.' remarks',
        ], $extra));
    }

    private function submit(): void
    {
        $this->action($this->atl, 'save', $this->header)->assertOk()->assertJsonPath('status', 'draft');
        $this->action($this->atl, 'submit')->assertOk()->assertJsonPath('status', 'submitted');
    }

    public function test_all_roles_including_super_admin_wildcard_are_denied_without_explicit_permission(): void
    {
        $this->submit();
        foreach ([$this->super, $this->atl, $this->auditor, $this->approver] as $actor) {
            $this->assertFalse(app(AuditPlanPermissions::class)->allows($actor));
            $this->action($actor, 'approve')->assertForbidden();
            $this->action($actor, 'return')->assertForbidden();
        }
        $this->assertSame(['*'], Role::where('roleId', 0)->firstOrFail()->permissions);
        $this->assertSame('submitted', $this->archive->fresh()->auditPlan['status']);
        $this->assertCount(0, app(AuditAssignment::class)->plans($this->atl));
    }

    public function test_only_super_admin_can_manage_exact_permission_with_password_history_and_version_safeguards(): void
    {
        foreach ([$this->atl, $this->auditor, $this->approver] as $actor) {
            $this->actingAs($actor)->patchJson('/api/v1/roles/3/permissions', [])->assertForbidden();
        }
        $this->permission(3, true, 0, ['password' => 'Incorrect'])->assertUnprocessable()->assertJsonValidationErrors('password');
        $this->permission(3, true, 0, ['permission' => '*'])->assertUnprocessable();
        $this->permission(3, true)->assertOk();
        $role = Role::where('roleId', 3)->firstOrFail();
        $this->assertSame([AuditPlanPermissions::APPROVE], $role->permissions);
        $this->assertSame('Isolated super', $role->permissionHistory[0]['actorName']);
        $this->assertInstanceOf(ObjectId::class, $role->permissionHistory[0]['actorId']);
        $this->permission(3, false, 0)->assertConflict();
        $this->seed(\Database\Seeders\RoleSeeder::class);
        $this->assertTrue(app(AuditPlanPermissions::class)->allows($this->approver));
        $this->permission(3, false, 1)->assertOk();
        $this->assertFalse(app(AuditPlanPermissions::class)->allows($this->approver));
        $this->assertCount(2, $role->fresh()->permissionHistory);
        $this->assertSame(['*'], Role::where('roleId', 0)->firstOrFail()->permissions);
    }

    public function test_authorized_approval_records_decision_and_enables_only_in_scope_assignments(): void
    {
        $this->submit();
        $this->permission(3, true)->assertOk();
        $this->action($this->approver, 'approve', ['approvedBy' => (string) $this->super->getKey(), 'scope' => 'Forged criteria'])->assertOk()->assertJsonPath('status', 'accepted');
        $plan = $this->archive->fresh();
        $this->assertSame((string) $this->approver->getKey(), (string) $plan->auditPlan['approvedBy']);
        $this->assertInstanceOf(ObjectId::class, $plan->auditPlan['approvedBy']);
        $this->assertNotEmpty($plan->auditPlan['approvedAt']);
        $event = collect($plan->auditPlan['history'])->last();
        $this->assertSame('approve', $event['decision']);
        $this->assertSame('Recorded approve remarks', $event['remarks']);
        $this->assertSame('Process scope', $plan->assignmentAuthorization['scope']);
        $this->assertInstanceOf(ObjectId::class, $plan->assignmentAuthorization['unitIds'][0]);
        $this->assertCount(1, app(AuditAssignment::class)->plans($this->atl));
        $assignment = ['planId' => (string) $plan->getKey(), 'title' => 'Approved plan assignment', 'departmentId' => (string) $this->unit->getKey(),
            'auditorId' => (string) $this->auditor->getKey(), 'schedule' => '2026-11-20', 'independenceConfirmed' => '1', 'independenceNotes' => 'Procedure checked'];
        $this->actingAs($this->atl)->post('/audits', $assignment)->assertRedirect()->assertSessionHasNoErrors();
        $audit = Audit::where('title', 'Approved plan assignment')->firstOrFail();
        $this->assertSame('Controlled criteria', $audit->criteria);
        $this->assertInstanceOf(ObjectId::class, $audit->getRawOriginal('planId'));
        $this->actingAs($this->auditor)->get('/audits')->assertOk()->assertSee('Approved plan assignment');
        $this->assertSame(1, Audit::where('auditCode', 'LEGACY-PLAN-TEST')->count());
        $this->assertSame(4, User::count());
        $authorization = $plan->assignmentAuthorization;
        $plan->forceFill(['assignmentAuthorization' => array_replace($authorization, ['scope' => 'Unapproved extension'])])->save();
        $this->assertCount(0, app(AuditAssignment::class)->plans($this->atl));
        $plan->forceFill(['assignmentAuthorization' => $authorization, 'versionNumber' => 2])->save();
        $this->assertCount(0, app(AuditAssignment::class)->plans($this->atl), 'A changed document revision needs its own approval.');
        $this->assertSame('Process scope', $audit->fresh()->scope);
    }

    public function test_revocation_is_enforced_for_existing_sessions_and_preserves_historical_decisions(): void
    {
        $this->submit(); $this->permission(3, true)->assertOk();
        $this->action($this->approver, 'approve')->assertOk();
        $approved = $this->archive->fresh()->auditPlan['history'];
        $this->permission(3, false, 1)->assertOk();
        $this->assertCount(1, app(AuditAssignment::class)->plans($this->atl), 'Revocation does not erase an already-issued approval.');
        $this->action($this->atl, 'revise')->assertOk()->assertJsonPath('status', 'draft');
        $this->assertCount(0, app(AuditAssignment::class)->plans($this->atl));
        $this->action($this->atl, 'submit')->assertOk();
        $this->action($this->approver, 'approve')->assertForbidden();
        $this->action($this->approver, 'return')->assertForbidden();
        $this->assertEquals($approved, array_slice($this->archive->fresh()->auditPlan['history'], 0, 3));
    }

    public function test_return_revision_resubmission_and_stale_decisions_preserve_plan_history(): void
    {
        $this->submit(); $this->permission(2, true)->assertOk();
        $this->action($this->auditor, 'return')->assertOk()->assertJsonPath('status', 'returned');
        $this->assertCount(0, app(AuditAssignment::class)->plans($this->atl));
        $this->action($this->atl, 'save', array_replace($this->header, ['scope' => 'Revised scope']))->assertOk();
        $this->action($this->atl, 'submit')->assertOk();
        $version = $this->archive->fresh()->auditPlan['version'];
        $this->action($this->auditor, 'approve', ['version' => $version - 1])->assertConflict();
        $this->action($this->auditor, 'approve')->assertOk();
        $this->action($this->atl, 'save', $this->header)->assertForbidden();
        $this->assertSame('Revised scope', $this->archive->fresh()->assignmentAuthorization['scope']);
        $this->assertCount(6, $this->archive->fresh()->auditPlan['history']);
    }

    public function test_approval_metadata_access_does_not_grant_general_archives_or_preparation_and_legacy_records_are_not_autoapproved(): void
    {
        $legacy = $this->archive->toArray();
        $this->actingAs($this->atl)->getJson('/api/v1/audit-plans')->assertOk()->assertJsonCount(0, 'plans')->assertJsonCount(1, 'archives');
        $this->assertSame($legacy, $this->archive->fresh()->toArray());
        $this->submit(); $this->permission(3, true)->assertOk();
        $this->actingAs($this->approver)->get('/audit-plans')->assertOk()->assertSee('Audit Plans');
        $this->getJson('/api/v1/audit-plans')->assertOk()->assertJsonPath('plans.0.canDecide', true);
        $this->getJson('/api/v1/archives/'.$this->archive->getKey())->assertNotFound();
        $this->action($this->approver, 'save', $this->header)->assertForbidden();
        $this->action($this->atl, 'approve')->assertForbidden();
        $this->actingAs($this->super)->getJson('/api/v1/audit-plans')->assertForbidden();
        $this->actingAs($this->super)->get('/super-admin/roles')->assertOk()->assertSee('Audit Plan Approval Permission');
    }

    public function test_scope_and_identity_validation_and_pending_edits_cannot_bypass_approval(): void
    {
        $outside = Department::create(['collegeName' => 'OTHER', 'departmentName' => 'Outside', 'ownershipVerified' => true, 'isActive' => true, 'unitType' => 'office']);
        $this->action($this->atl, 'save', array_replace($this->header, ['unitIds' => [(string) $outside->getKey()]]))->assertUnprocessable();
        $this->action($this->atl, 'save', array_replace($this->header, ['reviewerIds' => [(string) $this->auditor->getKey()]]))->assertUnprocessable();
        $this->action($this->atl, 'save', array_replace($this->header, ['scheduleEnd' => '2020-01-01']))->assertUnprocessable();
        $this->submit();
        $this->action($this->atl, 'save', $this->header)->assertForbidden();
        $this->assertCount(0, app(AuditAssignment::class)->plans($this->atl));
        $this->permission(3, true)->assertOk();
        $this->approver->update(['isActive' => false]);
        $this->action($this->approver, 'approve')->assertStatus(401);
    }

    public function test_super_admin_may_approve_only_after_an_explicit_grant_and_other_permissions_survive(): void
    {
        $this->submit();
        $this->action($this->super, 'approve')->assertForbidden();
        $this->permission(0, true)->assertOk();
        $this->action($this->super, 'approve')->assertOk();
        $this->assertSame(['*', AuditPlanPermissions::APPROVE], Role::where('roleId', 0)->firstOrFail()->permissions);
        $this->permission(0, false, 1)->assertOk();
        $this->assertSame(['*'], Role::where('roleId', 0)->firstOrFail()->permissions);
        $this->assertFalse(app(AuditPlanPermissions::class)->allows($this->super));
        $this->assertTrue(Audit::where('auditCode', 'LEGACY-PLAN-TEST')->exists());
    }

    public function test_plan_permission_and_workflow_views_render_without_changing_existing_roles_or_records(): void
    {
        $this->submit(); $this->permission(3, true)->assertOk();
        $roles = $this->actingAs($this->super)->get('/super-admin/roles')->assertOk()->assertSee('Role overview')->assertSee('Audit Plan Approval Permission');
        $roleData = $this->getJson('/api/v1/roles')->assertOk();
        $plans = $this->actingAs($this->atl)->get('/audit-plans')->assertOk()->assertSee('Prepare Audit Plan');
        $preparerData = $this->getJson('/api/v1/audit-plans')->assertOk()->assertJsonPath('canPrepare', true)->assertJsonPath('canApprove', false);
        $approvals = $this->actingAs($this->approver)->get('/audit-plans')->assertOk();
        $approvalData = $this->getJson('/api/v1/audit-plans')->assertOk()->assertJsonPath('canPrepare', false)->assertJsonPath('canApprove', true);
        if (getenv('AUDIT_PLAN_BROWSER_FIXTURES') === '1') {
            $dir = storage_path('framework/testing/plan-ui'); File::ensureDirectoryExists($dir);
            foreach (['roles.html' => $roles, 'roles.json' => $roleData, 'plans.html' => $plans, 'preparer.json' => $preparerData,
                'approvals.html' => $approvals, 'approval.json' => $approvalData] as $file => $response) { file_put_contents($dir.'/'.$file, $response->getContent()); }
        }
        $this->assertSame(4, Role::count()); $this->assertSame(4, User::count());
        $this->assertSame(1, ArchiveDocument::count()); $this->assertSame(1, Audit::count());
    }
}
