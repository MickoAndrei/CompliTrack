<?php
namespace Tests\Feature;

use App\Models\{ArchiveDocument, Audit, Campus, College, Department, User};
use App\Services\{ArchiveEncryptionService, QmsArchiveMigration, QmsCampusReferences};
use Illuminate\Support\Facades\{DB, File};
use MongoDB\BSON\ObjectId;
use Tests\TestCase;

class QmsAuditArchiveMongoTest extends TestCase
{
    private ?string $databaseName = null;
    private ?string $directory = null;

    protected function setUp(): void
    {
        parent::setUp();
        if (getenv('RUN_MONGODB_AUDIT_TESTS') !== '1') { $this->markTestSkipped('Requires an isolated MongoDB database.'); }
        $this->databaseName = 'complitrack_plan_test_'.bin2hex(random_bytes(5));
        $this->directory = storage_path('framework/testing/'.$this->databaseName);
        File::ensureDirectoryExists($this->directory);
        config(['database.connections.mongodb.database' => $this->databaseName, 'filesystems.disks.local.root' => $this->directory,
            'qms.backup_directory' => $this->directory.'/backups', 'services.complitrack.encryption_key' => base64_encode(str_repeat('p', 32))]);
        DB::purge('mongodb');
        $this->assertSame($this->databaseName, DB::connection('mongodb')->getMongoDB()->getDatabaseName());
        app(QmsArchiveMigration::class)->run(true); app(QmsCampusReferences::class)->migrate();
    }

    protected function tearDown(): void
    {
        if ($this->databaseName) {
            $db = DB::connection('mongodb')->getMongoDB();
            if ($db->getDatabaseName() === $this->databaseName && str_starts_with($this->databaseName, 'complitrack_plan_test_')) { $db->drop(); }
            DB::purge('mongodb');
        }
        if ($this->directory && is_dir($this->directory)) {
            $resolved = realpath($this->directory); $allowed = realpath(storage_path('framework/testing'));
            if ($resolved && $allowed && str_starts_with($resolved, $allowed.DIRECTORY_SEPARATOR)) { File::deleteDirectory($resolved); }
        }
        parent::tearDown();
    }

    private function actor(Department $unit, int $role = 3, array $extra = []): User
    {
        return User::create($extra + ['fullName' => 'Plan archive actor', 'email' => bin2hex(random_bytes(5)).'@example.test',
            'roleId' => $role, 'isActive' => true, 'departmentId' => $unit->getKey(), 'archiveResponsibility' => 'department_head']);
    }

    private function plan(User $reviewer, array $units): ArchiveDocument
    {
        $unit = $units[0];
        $plan = ArchiveDocument::create(['title' => 'Official Internal Quality Audit Plan', 'fileCode' => 'ARCH-TRACE-001', 'documentReference' => 'IQA-PLAN-2026-004',
            'recordType' => 'digital-reference', 'departmentId' => $unit->getKey(), 'collegeId' => $unit->collegeId, 'uploadedByUserId' => $reviewer->getKey(), 'ownershipVerified' => true]);
        $at = now()->utc()->toIso8601String();
        $header = ['unitIds' => array_map(fn ($unit) => (string) $unit->getKey(), $units), 'reviewerIds' => [(string) $reviewer->getKey()],
            'scope' => 'Authorized QA controls', 'criteria' => 'Approved internal quality criteria', 'scheduleStart' => '2026-01-01', 'scheduleEnd' => '2026-12-31', 'procedureReference' => 'IQA-PROC-001'];
        $plan->forceFill(['auditPlan' => ['status' => 'accepted', 'version' => 1, 'approvedBy' => $reviewer->getKey(), 'approvedAt' => $at,
            'header' => $header, 'documentVersion' => 1, 'documentChecksum' => $plan->checksum],
            'assignmentAuthorization' => $header + ['status' => 'authorized', 'approvedBy' => $reviewer->getKey(), 'approvedAt' => $at, 'approvalVersion' => 1]])->save();
        return $plan;
    }

    private function assignment(ArchiveDocument $plan, User $auditor, User $reviewer, Department $unit): Audit
    {
        return Audit::create(['planId' => $plan->getKey(), 'planTitle' => 'Stale cached plan name', 'title' => 'August 2026', 'auditCode' => 'IQA-'.bin2hex(random_bytes(3)),
            'departmentId' => $unit->getKey(), 'auditorId' => $auditor->getKey(), 'reviewerId' => $reviewer->getKey(), 'planAuthorizationSnapshot' => $plan->assignmentAuthorization,
            'status' => 'draft', 'items' => [], 'evidenceRequests' => []]);
    }

    private function evidence(Department $unit, User $owner, Audit $audit, array $extra = []): ArchiveDocument
    {
        return ArchiveDocument::create($extra + ['title' => 'Authorized evidence', 'recordType' => 'digital-reference', 'documentReference' => 'CONTROL-001',
            'departmentId' => $unit->getKey(), 'collegeId' => $unit->collegeId, 'ownershipVerified' => true, 'uploadedByUserId' => $owner->getKey(),
            'visibility' => 'department', 'sharedAuditIds' => [$audit->getKey()]]);
    }

    private function path(ArchiveDocument $plan, User $auditor, Department $unit): array
    {
        $college = College::findOrFail($unit->collegeId);
        return ['planId' => (string) $plan->getKey(), 'auditorId' => (string) $auditor->getKey(), 'campusId' => (string) $college->campusId,
            'collegeId' => (string) $college->getKey(), 'unitId' => (string) $unit->getKey()];
    }

    public function test_official_plan_hierarchy_has_stable_deduplicated_ids_and_assignment_scoped_units(): void
    {
        $unit = Department::where('departmentCode', 'CET-IT')->firstOrFail(); $other = Department::where('departmentCode', 'CET-ENV')->firstOrFail();
        $reviewer = $this->actor($other, 1, ['archiveResponsibility' => 'faculty_staff']);
        $auditor = $this->actor($other, 2, ['assignedDepartmentIds' => [$unit->getKey()], 'archiveResponsibility' => 'faculty_staff']);
        $owner = $this->actor($unit); $plan = $this->plan($reviewer, [$unit, $other]);
        $first = $this->assignment($plan, $auditor, $reviewer, $unit); $second = $this->assignment($plan, $auditor, $reviewer, $unit);
        $file = $this->evidence($unit, $owner, $first);
        $path = $this->path($plan, $auditor, $unit); $count = ArchiveDocument::count(); $categories = \App\Models\QmsCategory::count();
        $this->actingAs($auditor)->getJson('/api/v1/documents/audit-folders')->assertOk()->assertJsonCount(1, 'folders')
            ->assertJsonPath('folders.0.name', $plan->title)->assertJsonPath('folders.0.reference', 'IQA-PLAN-2026-004')->assertJsonPath('folders.0._id', (string) $plan->getKey())
            ->assertJsonMissing(['name' => 'August 2026'])->assertHeader('Cache-Control', 'no-store, private');
        for ($depth = 1; $depth < 5; $depth++) {
            $response = $this->getJson('/api/v1/documents/audit-folders?'.http_build_query(array_slice($path, 0, $depth, true)))->assertOk()->assertJsonCount(1, 'folders');
            $this->assertSame(array_values($path)[$depth], $response->json('folders.0._id'));
        }
        $this->getJson('/api/v1/documents/audit-folders?'.http_build_query($path))->assertOk()->assertJsonPath('leaf', true)->assertJsonCount(6, 'breadcrumbs');
        $this->getJson('/api/v1/documents?'.http_build_query($path + ['view' => 'audit']))->assertOk()->assertJsonPath('meta.total', 1)->assertJsonPath('data.0._id', (string) $file->getKey());
        $this->getJson('/api/v1/documents/audit-folders?'.http_build_query(array_replace($path, ['unitId' => (string) $other->getKey()])))->assertNotFound();
        $this->getJson('/api/v1/documents/audit-folders?auditorId='.$auditor->getKey())->assertUnprocessable();
        app(QmsCampusReferences::class)->migrate();
        $this->assertSame($path['campusId'], (string) College::find($unit->collegeId)->campusId); $this->assertSame(1, Campus::count());
        $this->assertSame($count, ArchiveDocument::count()); $this->assertSame($categories, \App\Models\QmsCategory::count());
        $unassigned = $this->actor($other, 2, ['assignedDepartmentIds' => [$unit->getKey()]]);
        $this->actingAs($unassigned)->getJson('/api/v1/documents/audit-folders')->assertJsonCount(0, 'folders');
        $this->getJson('/api/v1/documents/'.$file->getKey().'?'.http_build_query($path))->assertNotFound();
        $this->actingAs($owner)->getJson('/api/v1/documents')->assertJsonPath('meta.total', 1);
        $this->assertSame((string) $unit->getKey(), (string) $file->fresh()->departmentId);
    }

    public function test_organizational_and_reviewer_scopes_cannot_expand_plan_assignments(): void
    {
        $unit = Department::where('departmentCode', 'CET-IT')->firstOrFail();
        $other = Department::where('departmentCode', 'CET-ENV')->firstOrFail();
        $college = College::create(['collegeName' => 'Outside college', 'campusName' => 'Cagayan de Oro', 'isActive' => true]);
        $outside = Department::create(['departmentName' => 'Outside department', 'collegeId' => $college->getKey(), 'campusName' => 'Cagayan de Oro',
            'unitType' => 'academic_department', 'isActive' => true, 'ownershipVerified' => true]);
        app(QmsCampusReferences::class)->migrate();
        $reviewer = $this->actor($other, 1, ['archiveResponsibility' => 'faculty_staff']);
        $secondReviewer = $this->actor($other, 1, ['archiveResponsibility' => 'faculty_staff']);
        $auditor = $this->actor($unit, 2, ['assignedDepartmentIds' => [$unit->getKey(), $other->getKey(), $outside->getKey()]]);
        $plan = $this->plan($reviewer, [$unit, $other, $outside]);
        foreach ([$unit, $other, $outside] as $department) { $this->assignment($plan, $auditor, $reviewer, $department); }
        $foreignPlan = $this->plan($secondReviewer, [$unit]); $this->assignment($foreignPlan, $auditor, $secondReviewer, $unit);
        $draft = $this->plan($reviewer, [$unit]); $this->assignment($draft, $auditor, $reviewer, $unit);
        $draft->forceFill(['auditPlan' => ['status' => 'draft'], 'assignmentAuthorization' => ['status' => 'draft']])->save();
        $this->actingAs($reviewer)->getJson('/api/v1/documents/audit-folders')->assertJsonCount(1, 'folders')->assertJsonPath('folders.0._id', (string) $plan->getKey());
        $chair = $this->actor($unit, 1); // Also an ATL: that responsibility must not expand this department scope.
        $ownPlan = $this->plan($chair, [$unit, $other]);
        $this->assignment($ownPlan, $auditor, $chair, $other);
        $this->actingAs($chair)->getJson('/api/v1/documents/audit-folders?'.http_build_query($this->path($plan, $auditor, $other)))->assertNotFound();
        $this->getJson('/api/v1/documents/audit-folders?'.http_build_query($this->path($plan, $auditor, $unit)))->assertOk();
        foreach (['college_dean', 'college_secretary', 'cluster_administrator'] as $responsibility) {
            $actor = $this->actor($outside, 3, ['archiveResponsibility' => $responsibility, 'archiveCollegeIds' => [$unit->collegeId], 'archiveCluster' => 'cluster_a']);
            $this->actingAs($actor)->getJson('/api/v1/documents/audit-folders?'.http_build_query($this->path($plan, $auditor, $other)))->assertOk();
            $this->getJson('/api/v1/documents/audit-folders?'.http_build_query($this->path($plan, $auditor, $outside)))->assertNotFound();
        }
        $unscoped = $this->actor($unit, 3, ['archiveResponsibility' => 'college_secretary', 'archiveCollegeIds' => []]);
        $this->actingAs($unscoped)->getJson('/api/v1/documents/audit-folders')->assertJsonCount(0, 'folders');
        $this->assertNotNull($draft->fresh());
    }

    public function test_specific_audit_context_rejects_other_audit_files_and_retains_authorized_history(): void
    {
        $unit = Department::where('departmentCode', 'CET-IT')->firstOrFail(); $other = Department::where('departmentCode', 'CET-ENV')->firstOrFail();
        $reviewer = $this->actor($other, 1, ['archiveResponsibility' => 'faculty_staff']);
        $auditor = $this->actor($other, 2, ['assignedDepartmentIds' => [$unit->getKey()]]); $owner = $this->actor($unit);
        $plan = $this->plan($reviewer, [$unit]); $audit = $this->assignment($plan, $auditor, $reviewer, $unit);
        $otherPlan = $this->plan($reviewer, [$unit]); $otherAudit = $this->assignment($otherPlan, $auditor, $reviewer, $unit);
        $bytes = "%PDF-1.4\nPlan-scoped evidence\n%%EOF";
        $encrypted = app(ArchiveEncryptionService::class)->encrypt($bytes);
        File::ensureDirectoryExists($this->directory.'/archives'); file_put_contents($this->directory.'/archives/context.enc', $encrypted['ciphertext']);
        $digital = ['recordType' => 'file', 'originalName' => 'evidence.pdf', 'mimeType' => 'application/pdf', 'encryptedPath' => 'archives/context.enc',
            'isEncrypted' => true, 'checksum' => hash('sha256', $bytes), 'encryptionIv' => $encrypted['iv'], 'encryptionTag' => $encrypted['tag'], 'encryptionKeyId' => $encrypted['keyId']];
        $allowed = $this->evidence($unit, $owner, $audit, $digital); $foreign = $this->evidence($unit, $owner, $otherAudit, $digital);
        $wrongDepartment = $this->evidence($other, $owner, $audit);
        $path = $this->path($plan, $auditor, $unit);
        $this->actingAs($auditor)->getJson('/api/v1/documents?'.http_build_query($path + ['view' => 'audit']))->assertJsonPath('meta.total', 1);
        $this->get('/api/v1/documents/'.$allowed->getKey().'/download?'.http_build_query($path))->assertOk()->assertContent($bytes)->assertHeader('Cache-Control', 'no-store, private');
        $this->getJson('/api/v1/documents/'.$allowed->getKey().'/verify-checksum?'.http_build_query($path))->assertOk()->assertJsonPath('isVerified', true);
        $this->get('/api/v1/documents/'.$foreign->getKey().'/download')->assertOk();
        foreach (['', '/download', '/verify-checksum'] as $suffix) { $this->getJson('/api/v1/documents/'.$foreign->getKey().$suffix.'?'.http_build_query($path))->assertNotFound(); }
        $this->getJson('/api/v1/documents/'.$wrongDepartment->getKey().'?'.http_build_query($path))->assertNotFound();
        $this->getJson('/api/v1/documents?'.http_build_query($path + ['search' => 'CONTROL-001']))->assertJsonPath('meta.total', 1);
        $audit->update(['status' => 'accepted', 'archivedAt' => now()->toIso8601String()]);
        $old = $plan->auditPlan;
        $plan->forceFill(['auditPlan' => array_replace($old, ['status' => 'draft', 'version' => 2, 'history' => [['decision' => 'revise', 'previousStatus' => 'accepted', 'previousAuthorization' => $plan->assignmentAuthorization]]]), 'assignmentAuthorization' => ['status' => 'draft']])->save();
        $this->getJson('/api/v1/documents/audit-folders?'.http_build_query($path))->assertOk()->assertJsonPath('leaf', true);
        $this->getJson('/api/v1/documents/'.$allowed->getKey().'?'.http_build_query($path))->assertOk();
        $replacement = $this->actor($other, 2, ['assignedDepartmentIds' => [$unit->getKey()]]);
        $audit->update(['auditorId' => $replacement->getKey()]);
        $this->getJson('/api/v1/documents/audit-folders?'.http_build_query($path))->assertNotFound();
        $this->getJson('/api/v1/documents/'.$allowed->getKey())->assertNotFound();
        $newPath = $this->path($plan, $replacement, $unit);
        $this->actingAs($replacement)->getJson('/api/v1/documents/audit-folders?'.http_build_query($newPath))->assertOk();
        $this->getJson('/api/v1/documents/'.$allowed->getKey().'?'.http_build_query($newPath))->assertOk();
        $replacement->update(['assignedDepartmentIds' => []]);
        $this->actingAs($replacement->fresh())->getJson('/api/v1/documents/'.$allowed->getKey().'?'.http_build_query($newPath))->assertNotFound();
        $this->assertNotNull($audit->fresh()); $this->assertNotNull($allowed->fresh());
    }
}
