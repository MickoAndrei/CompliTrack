<?php
namespace Tests\Feature;

use App\Models\{ArchiveDocument, Audit, College, ComplianceCheck, Department, QmsCategory, User};
use App\Services\{ArchiveEncryptionService, QmsArchiveMigration};
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\{DB, File};
use MongoDB\BSON\ObjectId;
use Tests\TestCase;

class QmsArchivesMongoTest extends TestCase
{
    use \Tests\Support\AuthorizesAuditPlan;
    private ?string $databaseName = null;
    private ?string $directory = null;
    protected function setUp(): void
    {
        parent::setUp();
        if (getenv('RUN_MONGODB_AUDIT_TESTS') !== '1') { $this->markTestSkipped('Uses an isolated MongoDB database.'); }
        $this->databaseName = 'complitrack_qms_test_'.bin2hex(random_bytes(5));
        $this->directory = storage_path('framework/testing/'.$this->databaseName);
        File::ensureDirectoryExists($this->directory);
        config(['database.connections.mongodb.database' => $this->databaseName, 'filesystems.disks.local.root' => $this->directory,
            'qms.backup_directory' => $this->directory.'/backups', 'services.complitrack.encryption_key' => base64_encode(str_repeat('q', 32))]);
        DB::purge('mongodb');
        $this->assertSame($this->databaseName, DB::connection('mongodb')->getMongoDB()->getDatabaseName());
    }
    protected function tearDown(): void
    {
        if ($this->databaseName) {
            $database = DB::connection('mongodb')->getMongoDB();
            if ($database->getDatabaseName() === $this->databaseName && str_starts_with($this->databaseName, 'complitrack_qms_test_')) { $database->drop(); }
            DB::purge('mongodb');
        }
        if ($this->directory && is_dir($this->directory)) {
            $resolved = realpath($this->directory); $allowed = realpath(storage_path('framework/testing'));
            if ($resolved && $allowed && str_starts_with($resolved, $allowed.DIRECTORY_SEPARATOR)) { File::deleteDirectory($resolved); }
        }
        parent::tearDown();
    }
    public function test_browser_views_and_stars_only_narrow_current_document_permissions(): void
    {
        $this->migrate();
        $units = $this->units(); $unit = $units->first(); $outside = $units->last();
        $reader = $this->user($unit, 'department_secretary'); $owner = $this->user($unit);
        $shared = $this->document($unit, $owner, ['title' => 'Alpha shared', 'visibility' => 'restricted', 'accessUserIds' => [$reader->getKey()]]);
        $own = $this->document($unit, $reader, ['title' => 'Zulu personal', 'visibility' => 'owner']);
        $foreign = $this->document($outside, $owner);
        $old = $this->document($unit, $owner, ['title' => 'Old authorized']);
        $old->forceFill(['createdAt' => now()->subDays(60)])->save();
        $count = ArchiveDocument::count();
        $this->actingAs($reader)->getJson('/api/v1/documents?view=shared')->assertOk()->assertJsonPath('meta.total', 1)->assertJsonPath('data.0._id', (string) $shared->getKey())->assertHeader('Cache-Control', 'no-store, private');
        $this->getJson('/api/v1/documents?view=recent')->assertOk()->assertJsonPath('meta.total', 2);
        $this->getJson('/api/v1/documents?sort=name')->assertOk()->assertJsonPath('data.0.title', 'Alpha shared');
        $this->getJson('/api/v1/documents?view=starred')->assertJsonPath('meta.total', 0);
        $this->patchJson('/api/v1/documents/'.$shared->getKey().'/star', ['starred' => true])->assertOk();
        $this->patchJson('/api/v1/documents/'.$shared->getKey().'/star', ['starred' => true])->assertOk();
        $this->assertCount(1, $reader->fresh()->qmsStarredIds);
        $this->actingAs($reader->fresh())->getJson('/api/v1/documents?view=starred')->assertJsonPath('meta.total', 1)->assertJsonPath('data.0.isStarred', true);
        $this->patchJson('/api/v1/documents/'.$foreign->getKey().'/star', ['starred' => true])->assertNotFound();
        $this->getJson('/api/v1/documents?view=starred&unitId='.$outside->getKey())->assertJsonPath('meta.total', 0);
        $shared->update(['accessUserIds' => []]);
        $this->getJson('/api/v1/documents?view=starred')->assertJsonPath('meta.total', 0);
        $this->getJson('/api/v1/documents/'.$shared->getKey())->assertNotFound();
        $this->patchJson('/api/v1/documents/'.$shared->getKey().'/star', ['starred' => false])->assertNotFound();
        $auditor = $this->user($unit, 'faculty_staff', 2, ['assignedDepartmentIds' => [$unit->getKey()]]);
        $audit = Audit::create(['departmentId' => $unit->getKey(), 'auditorId' => $auditor->getKey(), 'status' => 'draft']);
        $own->update(['sharedAuditIds' => [$audit->getKey()]]);
        $this->getJson('/api/v1/documents?view=audit')->assertOk()->assertJsonPath('meta.total', 1);
        $this->actingAs($auditor)->getJson('/api/v1/documents?view=audit')->assertOk()->assertJsonPath('meta.total', 1);
        $auditor->update(['assignedDepartmentIds' => []]);
        $this->actingAs($auditor->fresh())->getJson('/api/v1/documents?view=audit')->assertJsonPath('meta.total', 0);
        $this->assertSame($count, ArchiveDocument::count());
    }

    public function test_user_subfolders_are_department_scoped_inherit_restrictions_and_protect_roots(): void
    {
        $this->migrate();
        (require database_path('migrations/2026_11_14_150000_index_qms_user_subfolders.php'))->up();
        $units = $this->units(); $unit = $units->first(); $other = $units->last();
        $root = QmsCategory::where('unitType', 'academic_department')->whereNull('parentId')->firstOrFail();
        $chair = $this->user($unit, 'department_head'); $secretary = $this->user($unit, 'department_secretary');
        $base = ['unitId' => (string) $unit->getKey(), 'parentId' => (string) $root->getKey(), 'name' => 'AY 2026-2027'];
        $folder = $this->actingAs($chair)->postJson('/api/v1/documents/folders', $base)->assertCreated()->json('data._id');
        $created = QmsCategory::findOrFail($folder);
        $this->assertSame((string) $unit->getKey(), (string) $created->departmentId);
        $this->assertSame((string) $unit->collegeId, (string) $created->collegeId);
        $this->assertSame('department', $created->classification); $this->assertTrue($created->userManaged);
        $this->postJson('/api/v1/documents/folders', array_replace($base, ['name' => ' ay 2026-2027 ']))->assertUnprocessable();
        foreach (['../bad', 'bad/name', '..', '   '] as $name) { $this->postJson('/api/v1/documents/folders', array_replace($base, ['name' => $name]))->assertUnprocessable(); }
        foreach (['collegeId', 'departmentId', 'classification', 'userManaged', 'auditId'] as $field) {
            $this->postJson('/api/v1/documents/folders', $base + [$field => (string) $other->getKey()])->assertUnprocessable();
        }
        $child = $this->actingAs($secretary)->postJson('/api/v1/documents/folders', array_replace($base, ['parentId' => $folder, 'name' => 'Semester 1']))->assertCreated()->json('data._id');
        $this->patchJson('/api/v1/documents/folders/'.$folder, ['unitId' => (string) $unit->getKey(), 'operation' => 'rename', 'name' => 'Academic year'])->assertOk();
        $this->deleteJson('/api/v1/documents/folders/'.$folder, ['unitId' => (string) $unit->getKey()])->assertConflict();
        $this->patchJson('/api/v1/documents/folders/'.$folder, ['unitId' => (string) $unit->getKey(), 'operation' => 'move', 'parentId' => $child])->assertUnprocessable();
        $this->patchJson('/api/v1/documents/folders/'.$folder, ['unitId' => (string) $other->getKey(), 'operation' => 'move', 'parentId' => null])->assertNotFound();
        foreach (['rename', 'move'] as $operation) {
            $this->patchJson('/api/v1/documents/folders/'.$root->getKey(), ['unitId' => (string) $unit->getKey(), 'operation' => $operation, 'name' => 'Forbidden'])->assertForbidden();
        }
        $this->deleteJson('/api/v1/documents/folders/'.$root->getKey(), ['unitId' => (string) $unit->getKey()])->assertForbidden();
        $this->patchJson('/api/v1/documents/folders/'.$child, ['unitId' => (string) $unit->getKey(), 'operation' => 'move', 'parentId' => null])->assertOk();
        $record = ['purpose' => 'qms', 'unitId' => (string) $unit->getKey(), 'categoryId' => $child, 'title' => 'Signed register',
            'recordType' => 'physical-original', 'physicalLocation' => 'Cabinet A', 'visibility' => 'department'];
        $document = $this->postJson('/api/v1/documents/upload', $record)->assertCreated()->json('data._id');
        $this->postJson('/api/v1/documents/upload', array_replace($record, ['visibility' => 'college']))->assertUnprocessable();
        $this->deleteJson('/api/v1/documents/folders/'.$child, ['unitId' => (string) $unit->getKey()])->assertConflict();
        $this->actingAs($this->user($other, 'department_head'))->getJson('/api/v1/documents/tree')->assertOk()->assertJsonMissing(['_id' => $child]);
        $this->getJson('/api/v1/documents/'.$document)->assertNotFound();
        $this->postJson('/api/v1/documents/upload', array_replace($record, ['unitId' => (string) $other->getKey()]))->assertForbidden();
        $this->actingAs($chair)->deleteJson('/api/v1/documents/folders/'.$folder, ['unitId' => (string) $unit->getKey()])->assertOk();
        $this->assertFalse(QmsCategory::findOrFail($folder)->isActive);
        $this->assertNotNull(ArchiveDocument::find($document));
        $this->assertGreaterThanOrEqual(5, \App\Models\AuditLog::where('entityType', 'qms_category')->count());
        $this->getJson('/api/v1/documents/tree')->assertOk()->assertJsonCount(1, 'units');
        $college = College::findOrFail($unit->collegeId); $college->isActive = false; $college->save();
        $this->postJson('/api/v1/documents/folders', array_replace($base, ['name' => 'Inactive college']))->assertForbidden();
    }

    public function test_folder_responsibilities_grants_classification_and_revocation_are_enforced_by_api(): void
    {
        $this->migrate(); $units = $this->units(); $unit = $units->first(); $other = $units->last();
        $college = College::firstOrFail(); $base = ['unitId' => (string) $unit->getKey(), 'name' => 'Working folder'];
        $super = $this->user($unit, 'faculty_staff', 0);
        foreach ([$super, $this->user($unit), $this->user($unit, 'faculty_staff', 2, ['assignedDepartmentIds' => [$unit->getKey()]]), $this->user($unit, 'faculty_staff', 1)] as $actor) {
            $this->actingAs($actor)->postJson('/api/v1/documents/folders', $base)->assertForbidden();
        }
        $secretary = $this->user($unit, 'college_secretary', 3, ['archiveCollegeIds' => [$college->getKey()]]);
        foreach ([$unit, $other, Department::where('unitType', 'college_office')->firstOrFail()] as $target) {
            $this->actingAs($secretary)->postJson('/api/v1/documents/folders', array_replace($base, ['unitId' => (string) $target->getKey()]))->assertCreated();
        }
        $outsideCollege = College::create(['collegeCode' => 'OTHER', 'collegeName' => 'Other', 'campusName' => 'Cagayan de Oro', 'isActive' => true]);
        $outside = Department::create(['departmentName' => 'Other unit', 'collegeId' => $outsideCollege->getKey(), 'unitType' => 'academic_department', 'ownershipVerified' => true, 'isActive' => true]);
        $this->postJson('/api/v1/documents/folders', array_replace($base, ['unitId' => (string) $outside->getKey()]))->assertForbidden();
        $dean = $this->user($unit, 'college_dean', 3, ['archiveCollegeIds' => [$college->getKey()]]);
        $this->actingAs($dean)->postJson('/api/v1/documents/folders', $base)->assertForbidden();
        $grants = ['grants' => [['unitId' => (string) $unit->getKey(), 'actions' => ['view', 'create', 'upload'], 'classifications' => ['department', 'restricted']]]];
        $this->patchJson('/api/v1/documents/folder-grants/'.$dean->getKey(), $grants)->assertForbidden();
        $this->actingAs($super)->patchJson('/api/v1/documents/folder-grants/'.$dean->getKey(), $grants)->assertOk();
        $restricted = QmsCategory::create(['collegeId' => $unit->collegeId, 'unitType' => $unit->unitType, 'code' => 'RESTRICTED', 'name' => 'Restricted originals', 'classification' => 'restricted', 'isActive' => true]);
        $folder = $this->actingAs($dean->fresh())->postJson('/api/v1/documents/folders', array_replace($base, ['parentId' => (string) $restricted->getKey()]))->assertCreated()->json('data._id');
        $this->assertSame('restricted', QmsCategory::findOrFail($folder)->classification);
        $this->patchJson('/api/v1/documents/folders/'.$folder, $base + ['operation' => 'rename'])->assertForbidden();
        $record = ['purpose' => 'qms', 'unitId' => (string) $unit->getKey(), 'categoryId' => $folder, 'title' => 'Controlled physical register', 'recordType' => 'physical-original', 'physicalLocation' => 'Dean safe', 'visibility' => 'restricted'];
        $document = $this->postJson('/api/v1/documents/upload', $record)->assertCreated()->json('data._id');
        $this->postJson('/api/v1/documents/upload', array_replace($record, ['visibility' => 'department']))->assertUnprocessable();
        $this->actingAs($super)->patchJson('/api/v1/documents/folder-grants/'.$dean->getKey(), ['grants' => []])->assertOk();
        $this->actingAs($dean->fresh())->getJson('/api/v1/documents/'.$document)->assertNotFound();
        $this->getJson('/api/v1/documents?search=Controlled')->assertJsonPath('meta.total', 0);
        $this->getJson('/api/v1/documents/tree')->assertJsonMissing(['_id' => $folder]);
        $atl = $this->leader($unit);
        $this->actingAs($atl)->postJson('/api/v1/documents/folders', array_replace($base, ['name' => 'Audit work']))->assertCreated();
        $this->postJson('/api/v1/documents/folders', array_replace($base, ['unitId' => (string) $other->getKey(), 'name' => 'Out of scope']))->assertForbidden();
        $cluster = $this->user($unit, 'cluster_administrator', 3, ['archiveCluster' => 'cluster_a']);
        $this->actingAs($cluster)->postJson('/api/v1/documents/folders', array_replace($base, ['name' => 'Cluster work']))->assertCreated();
        $this->postJson('/api/v1/documents/folders', array_replace($base, ['unitId' => (string) $outside->getKey()]))->assertForbidden();
        $this->assertSame(5, Department::where('collegeName', 'CET')->where('unitType', 'academic_department')->count());
        $this->assertSame(1, Department::where('collegeName', 'CET')->where('unitType', 'college_office')->count());
    }

    public function test_cluster_scope_does_not_require_review_of_an_unrelated_legacy_home_department(): void
    {
        $this->migrate();
        $unit = $this->units()->first();
        $legacy = Department::create(['departmentName' => 'Legacy home office', 'collegeName' => 'CET',
            'unitType' => 'legacy_unclassified', 'ownershipVerified' => false, 'isActive' => true]);
        $actor = $this->user($legacy);
        $authorized = $this->document($unit, $actor);
        $unverified = $this->document($legacy, $actor, ['ownershipVerified' => false]);
        $this->actingAs($actor)->getJson('/api/v1/documents/tree')->assertOk()->assertJsonPath('ownershipReviewRequired', true)->assertJsonCount(0, 'units');
        $actor->update(['archiveResponsibility' => 'cluster_administrator', 'archiveCluster' => 'cluster_a']);
        $this->actingAs($actor->fresh())->getJson('/api/v1/documents/tree')->assertOk()->assertJsonPath('ownershipReviewRequired', false)
            ->assertJsonCount(6, 'units')->assertJsonPath('total', 1);
        $this->getJson('/api/v1/documents/'.$authorized->getKey())->assertOk();
        $this->getJson('/api/v1/documents/'.$unverified->getKey())->assertNotFound();
        $actor->update(['archiveCluster' => null, 'archiveCollegeIds' => []]);
        $this->actingAs($actor->fresh())->getJson('/api/v1/documents/tree')->assertOk()->assertJsonPath('ownershipReviewRequired', false)->assertJsonCount(0, 'units');
        $this->getJson('/api/v1/documents/'.$authorized->getKey())->assertNotFound();
        $this->assertSame((string) $legacy->getKey(), (string) $actor->fresh()->departmentId);
        $this->assertFalse($legacy->fresh()->ownershipVerified);
    }

    private function migrate(): void { app(QmsArchiveMigration::class)->run(true); }
    private function user(Department $unit, string $responsibility = 'faculty_staff', int $role = 3, array $extra = []): User
    {
        return User::create($extra + ['fullName' => 'Test '.$responsibility, 'email' => bin2hex(random_bytes(5)).'@example.test',
            'isActive' => true, 'roleId' => $role, 'departmentId' => $unit->getKey(), 'archiveResponsibility' => $responsibility]);
    }
    private function leader(Department $unit): User
    {
        $leader = $this->user($unit, 'faculty_staff', 1);
        Audit::create(['departmentId' => $unit->getKey(), 'reviewerId' => $leader->getKey(), 'status' => 'draft']);
        return $leader;
    }

    private function document(Department $unit, User $owner, array $extra = []): ArchiveDocument
    {
        return ArchiveDocument::create($extra + ['fileCode' => 'QMS-'.bin2hex(random_bytes(4)), 'title' => $unit->departmentName.' record',
            'originalName' => 'record.pdf', 'recordType' => 'file', 'departmentId' => $unit->getKey(), 'collegeId' => $unit->collegeId,
            'uploadedByUserId' => $owner->getKey(), 'ownershipVerified' => true, 'visibility' => 'department']);
    }
    private function units() { return Department::where('unitType', 'academic_department')->orderBy('departmentCode')->get(); }

    public function test_editor_saves_encrypted_revisions_preserves_originals_and_rejects_unsigned_callbacks(): void
    {
        $this->migrate();
        config(['documents.url' => 'http://localhost:8082', 'documents.internal_url' => 'http://127.0.0.1:8082',
            'documents.app_url' => 'http://host.docker.internal:8000', 'documents.secret' => str_repeat('t', 48)]);
        $unit = $this->units()->first(); $leader = $this->leader($unit);
        $category = QmsCategory::where('unitType', 'academic_department')->firstOrFail();
        $original = "%PDF-1.4\nOriginal revision";
        $data = ['purpose' => 'qms', 'unitId' => (string) $unit->getKey(), 'categoryId' => (string) $category->getKey(),
            'title' => 'Editable record', 'recordType' => 'file', 'visibility' => 'department', 'copyAuthorized' => true,
            'file' => UploadedFile::fake()->createWithContent('record.pdf', $original)];
        $first = $this->actingAs($leader)->post('/api/v1/documents/upload', $data, ['Accept' => 'application/json'])
            ->assertCreated()->json('data._id');
        $editor = $this->postJson('/api/v1/documents/'.$first.'/editor')->assertOk()->json();
        $session = $editor['sessionId']; $callback = '/api/document-editor/'.$session.'/callback';
        $fileToken = app(\App\Services\DocumentEditorToken::class)->sign(['payload' => ['url' => $editor['config']['document']['url']]]);
        $this->get('/api/document-editor/'.$session.'/file', ['Authorization' => 'Bearer '.$fileToken])->assertOk()->assertContent($original);
        $this->postJson($callback, ['status' => 6, 'key' => $editor['config']['document']['key']])->assertForbidden();
        $revised = "%PDF-1.4\nRevised text";
        \Illuminate\Support\Facades\Http::fake(['http://127.0.0.1:8082/cache/files/revision.pdf' => \Illuminate\Support\Facades\Http::response($revised)]);
        $payload = ['status' => 6, 'key' => $editor['config']['document']['key'], 'filetype' => 'pdf', 'url' => 'http://localhost:8082/cache/files/revision.pdf'];
        $token = app(\App\Services\DocumentEditorToken::class)->sign($payload);
        $this->postJson($callback, $payload + ['token' => $token])->assertOk()->assertJsonPath('error', 0);
        $saved = $this->getJson('/api/v1/document-editor/'.$session.'/status')->assertOk()->assertJsonCount(1, 'savedVersions')->json('currentId');
        $this->assertNotSame($first, $saved);
        $this->get('/api/v1/documents/'.$first.'/download')->assertOk()->assertContent($original);
        $this->get('/api/v1/documents/'.$saved.'/download')->assertOk()->assertContent($revised);
        $this->getJson('/api/v1/documents/'.$saved)->assertOk()->assertJsonCount(2, 'versions')->assertJsonPath('data.versionNumber', 2);
        $this->postJson($callback, $payload + ['token' => $token])->assertOk()->assertJsonPath('error', 0);
        $this->getJson('/api/v1/document-editor/'.$session.'/status')->assertOk()->assertJsonCount(1, 'savedVersions');
        $denied = $payload; $denied['url'] = 'http://attacker.test/file.pdf';
        $this->postJson($callback, $denied + ['token' => app(\App\Services\DocumentEditorToken::class)->sign($denied)])->assertUnprocessable();
        $this->actingAs($this->user($unit))->postJson('/api/v1/documents/'.$saved.'/editor')->assertForbidden();
        $leader->update(['isActive' => false]);
        $this->get('/api/document-editor/'.$session.'/file')->assertForbidden();
        \Illuminate\Support\Facades\Cache::store('file')->forget('document-editor:'.$session);
    }

    public function test_scoped_folder_uploads_preserve_atl_document_management_and_super_admin_folder_only_access(): void
    {
        $this->migrate();
        $units = $this->units(); $unit = $units->first(); $other = $units->last();
        $owner = $this->user($unit, 'department_head');
        $document = $this->document($other, $owner, ['visibility' => 'restricted', 'downloadAllowed' => false, 'fileSize' => 2048]);
        $this->document($other, $owner, ['recordType' => 'digital-reference', 'fileSize' => 99999]);
        $super = $this->user($unit, 'faculty_staff', 0);
        $category = QmsCategory::where('unitType', 'academic_department')->firstOrFail();
        $record = ['purpose' => 'qms', 'unitId' => (string) $unit->getKey(), 'categoryId' => (string) $category->getKey(),
            'title' => 'Leader reference', 'recordType' => 'digital-reference', 'documentReference' => 'QMS-LEADER', 'visibility' => 'department'];
        $this->actingAs($super)->getJson('/api/v1/documents/tree')->assertOk()->assertJsonCount(6, 'units')
            ->assertJsonPath('canManageOrganization', false)->assertJsonPath('canViewDocuments', false)->assertJsonPath('units.0.canCreate', false)
            ->assertJsonPath('total', 2)->assertJsonPath('archiveStorageBytes', 2048);
        $this->getJson('/api/v1/system/dashboard')->assertOk()->assertJsonPath('data.stats.archivedDocuments', 2)->assertJsonPath('data.stats.archiveStorageBytes', 2048);
        $this->getJson('/api/v1/documents')->assertOk()->assertJsonCount(0, 'data')->assertJsonPath('meta.total', 0);
        foreach (['', '/download', '/verify-checksum'] as $suffix) {
            $this->getJson('/api/v1/documents/'.$document->getKey().$suffix)->assertNotFound();
        }
        $this->getJson('/api/v1/documents/organization')->assertForbidden();
        $this->getJson('/api/v1/directory/organization')->assertOk()->assertJsonMissingPath('users.0.email');
        $this->postJson('/api/v1/documents/upload', $record)->assertForbidden();
        $this->patchJson('/api/v1/documents/categories/'.$category->getKey(), ['name' => 'Denied rename'])->assertForbidden();
        $this->actingAs($owner)->postJson('/api/v1/documents/upload', $record)->assertCreated();
        foreach ([$this->user($unit), $this->user($unit, 'faculty_staff', 1), $this->user($unit, 'faculty_staff', 2)] as $actor) {
            $this->actingAs($actor)->getJson('/api/v1/documents/tree')->assertOk()->assertJsonPath('canManageOrganization', false);
            $this->getJson('/api/v1/documents/organization')->assertForbidden();
            $this->postJson('/api/v1/documents/upload', $record)->assertForbidden();
        }
        $leader = $this->leader($unit);
        $this->actingAs($leader)->getJson('/api/v1/documents/tree')->assertOk()->assertJsonCount(6, 'units')
            ->assertJsonPath('canManageOrganization', true)->assertJsonPath('canViewDocuments', true)->assertJsonPath('units.0.canCreate', true);
        $this->getJson('/api/v1/documents/'.$document->getKey())->assertOk()->assertJsonPath('data.canManage', true)->assertJsonPath('data.canDownload', true);
        $this->getJson('/api/v1/documents/organization')->assertOk();
        $this->postJson('/api/v1/documents/upload', $record)->assertCreated();
        $this->patchJson('/api/v1/documents/categories/'.$category->getKey(), ['name' => 'Leader category'])->assertForbidden();
        $this->patchJson('/api/v1/documents/'.$document->getKey().'/permissions', ['visibility' => 'restricted', 'downloadAllowed' => false, 'accessUserIds' => []])->assertOk();
        $leader->update(['isActive' => false]);
        $this->actingAs($leader)->getJson('/api/v1/documents/organization')->assertUnauthorized();
    }

    public function test_development_scenarios_reuse_existing_atl_and_users_and_preserve_authorization(): void
    {
        $this->migrate();
        config(['qms.import_directory' => $this->directory.'/imports', 'qms.development_directory' => $this->directory.'/development']);
        $it = Department::where('departmentCode', 'CET-IT')->firstOrFail();
        $super = $this->user($it, 'faculty_staff', 0);
        $legacyUnit = Department::create(['departmentName' => '[DEMO] Legacy admin office', 'collegeName' => 'CET', 'unitType' => 'legacy_unclassified', 'ownershipVerified' => false, 'isActive' => true]);
        $atl = $this->user($legacyUnit, 'faculty_staff', 1, ['email' => 'existing.atl@example.test']);
        $auditor = $this->user($it, 'faculty_staff', 2, ['assignedDepartmentIds' => [new ObjectId((string) $it->getKey())]]);
        $db = DB::connection('mongodb')->getMongoDB();
        $snapshotUser = fn ($user) => \MongoDB\BSON\toCanonicalExtendedJSON(\MongoDB\BSON\fromPHP($db->users->findOne(['_id' => new ObjectId((string) $user->getKey())])));
        $before = $snapshotUser($atl); $beforeAuditor = $snapshotUser($auditor);
        $seeder = app(\App\Services\QmsDevelopmentData::class);
        $this->assertFalse($seeder->run()['applied']);
        $report = $seeder->run(true);
        $this->assertSame((string) $atl->getKey(), $report['atl']['id']);
        $this->assertSame(22, $report['createdAccounts']); $this->assertSame(2, $report['reusedAccounts']);
        $this->assertSame(1, User::where('roleId', 1)->count());
        $this->assertSame($before, $snapshotUser($atl)); $this->assertSame($beforeAuditor, $snapshotUser($auditor));
        $this->assertCount(11, $report['audits']); $this->assertCount(55, $report['documents']);
        $this->assertSame(7, ComplianceCheck::count());
        $counts = $seeder->counts();
        $this->assertTrue($seeder->run(true)['alreadySeeded']); $this->assertSame($counts, $seeder->counts());
        $users = collect($report['accounts'])->keyBy('key');
        $secretary = User::findOrFail($users['CET-IT:department_secretary']['id']);
        $other = User::findOrFail($users['CET-ENV:department_secretary']['id']);
        $head = User::findOrFail($users['CET-IT:department_head']['id']);
        $dean = User::findOrFail($users['college_dean']['id']);
        $cluster = User::findOrFail($users['cluster_administrator']['id']);
        $this->assertSame(3, $cluster->roleId); $this->assertNotSame((string) $atl->getKey(), (string) $cluster->getKey());
        $public = ArchiveDocument::where('mockSeedKey', \App\Services\QmsDevelopmentData::KEY.':document:CET-IT:public')->firstOrFail();
        $this->actingAs($secretary)->getJson('/api/v1/documents/'.$public->getKey())->assertOk();
        $this->actingAs($other)->getJson('/api/v1/documents/'.$public->getKey())->assertNotFound();
        $this->getJson('/api/v1/documents/'.$public->getKey().'/download')->assertNotFound();
        $this->getJson('/api/v1/documents?search=IT%20syllabus')->assertJsonPath('meta.total', 0);
        $this->actingAs($dean)->getJson('/api/v1/documents/tree')->assertOk()->assertJsonCount(6, 'units');
        $restricted = ArchiveDocument::where('mockSeedKey', \App\Services\QmsDevelopmentData::KEY.':document:CET-IT:restricted')->firstOrFail();
        $this->actingAs($secretary)->getJson('/api/v1/documents/'.$restricted->getKey())->assertNotFound();
        $this->actingAs($head)->getJson('/api/v1/documents/'.$restricted->getKey())->assertOk();
        $this->get('/auditee/dashboard')->assertOk()->assertSee('Authorized oversight')->assertDontSee('QMS-DEMO-RETURNED');
        $submitted = Audit::where('mockSeedKey', \App\Services\QmsDevelopmentData::KEY.':audit:submitted')->firstOrFail();
        $this->actingAs($atl)->get('/audits/'.$submitted->getKey())->assertOk();
        $kitPlan = ArchiveDocument::where('sourceImportKey', 'complitrack-claveria-cet-demo-v1:DOC-PLAN-001')->firstOrFail();
        $this->get('/api/v1/documents/'.$kitPlan->getKey().'/download')->assertOk();
        $this->getJson('/api/v1/documents/'.$public->getKey())->assertOk()->assertJsonPath('data.canManage', true);
        $this->post('/audits/'.$submitted->getKey(), ['action' => 'accept', 'version' => $submitted->version, 'reviewNotes' => 'Authorized existing ATL reviewed the mock result.'])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame('accepted', $submitted->fresh()->status);
        $planId = $this->authorizedPlan($atl, $auditor, Department::findOrFail($auditor->assignedDepartmentIds[0]), 'Synthetic process', 'Synthetic requirement');
        $this->post('/audits', ['planId' => $planId, 'independenceConfirmed' => '1', 'independenceNotes' => 'No own work or declared conflict', 'title' => '[MOCK] ATL assignment acceptance test', 'scope' => 'Synthetic process', 'criteria' => 'Synthetic requirement', 'schedule' => now()->toDateString(),
            'departmentId' => (string) $it->getKey(), 'auditorId' => (string) $auditor->getKey()])->assertRedirect()->assertSessionHasNoErrors();
        $this->actingAs($cluster)->get('/audits/'.$submitted->getKey())->assertForbidden();
        $car = ComplianceCheck::where('checkCode', 'CAR-DEMO-002')->firstOrFail();
        $this->actingAs(User::findOrFail($car->auditeeId))->patchJson('/api/v1/compliance-checks/'.$car->getKey().'/status', ['correctiveStatus' => 'closed', 'remarks' => 'Unauthorized close'])->assertForbidden();
        $this->actingAs($atl)->patchJson('/api/v1/compliance-checks/'.$car->getKey().'/status', ['correctiveStatus' => 'closed', 'remarks' => 'ATL remains audit reviewer only'])->assertForbidden();
        $this->actingAs($auditor)->patchJson('/api/v1/compliance-checks/'.$car->getKey().'/status', ['correctiveStatus' => 'closed', 'remarks' => 'Assigned auditor verified the synthetic evidence.'])->assertOk();
        $credentials = json_decode(file_get_contents($report['credentialsFile']), true);
        foreach ($report['accounts'] as $account) {
            if ($account['status'] !== 'created') { continue; }
            $user = User::findOrFail($account['id']);
            $this->assertTrue(\Illuminate\Support\Facades\Hash::check($credentials[$user->email]['password'], $user->password));
        }
        $this->assertCount(22, array_unique(array_column($credentials, 'password')));
        $this->postJson('/api/v1/auth/login', ['email' => $secretary->email, 'password' => $credentials[$secretary->email]['password']])->assertOk()->assertJsonPath('user.roleId', 3);
        $digital = Audit::where('mockSeedKey', \App\Services\QmsDevelopmentData::KEY.':audit:digital-submission')->firstOrFail();
        $entry = $digital->evidenceRequests[0];
        $this->actingAs(User::findOrFail($entry['auditeeId']))->get('/auditee/evidence-requests?auditId='.$digital->getKey().'&requestId='.$entry['id'], ['X-Requested-With' => 'XMLHttpRequest'])
            ->assertOk()->assertSee('/api/v1/documents/'.$entry['responses'][0]['archiveId'].'/download');
    }

    public function test_mock_document_import_verifies_files_preserves_permissions_and_skips_duplicates(): void
    {
        $this->migrate();
        config(['qms.import_directory' => $this->directory.'/imports']);
        $unit = Department::where('departmentCode', 'CET-IT')->firstOrFail();
        $super = $this->user($unit, 'faculty_staff', 0);
        $faculty = $this->user($unit);
        $auditor = $this->user($unit, 'faculty_staff', 2, ['assignedDepartmentIds' => [$unit->getKey()]]);
        $importer = app(\App\Services\MockArchiveImporter::class);
        $importLeader = $this->leader($unit);
        $directory = base_path('dataset_files/CompliTrack_CET_Demo_Kit-3');
        $plan = $importer->run($directory, $importLeader);
        $this->assertSame(27, $plan['toImport']); $this->assertSame(0, ArchiveDocument::count());
        $report = $importer->run($directory, $importLeader, true);
        $this->assertTrue($report['applied']); $this->assertCount(27, $report['records']);
        $this->assertFileExists($report['backup']); $this->assertSame(27, ArchiveDocument::count());
        $this->assertSame(27, $importer->run($directory, $importLeader, true)['existing']);
        $this->assertSame(27, ArchiveDocument::count());
        $first = ArchiveDocument::where('sourceImportKey', $plan['dataset'].':DOC-DPM-001')->firstOrFail();
        $this->actingAs($super)->get('/api/v1/documents/'.$first->getKey().'/download')->assertNotFound();
        $this->actingAs($this->leader($unit))->get('/api/v1/documents/'.$first->getKey().'/download')->assertOk()
            ->assertContent(file_get_contents($directory.'/documents/Academics/DPM-USTP-CET-001_Management_of_Learning.docx'));
        $this->actingAs($faculty)->getJson('/api/v1/documents')->assertOk()->assertJsonPath('meta.total', 27 - $report['restricted']);
        $restricted = ArchiveDocument::where('sourceImportKey', $plan['dataset'].':DOC-CHK-001')->firstOrFail();
        $this->getJson('/api/v1/documents/'.$restricted->getKey())->assertNotFound();
        $this->actingAs($auditor)->getJson('/api/v1/documents')->assertOk()->assertJsonPath('meta.total', 0);
        $first->forceFill(['checksum' => str_repeat('0', 64)])->save();
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Existing import differs');
        $importer->run($directory, $importLeader, true);
    }

    public function test_backup_first_migration_preserves_ids_and_relationships_and_is_idempotent(): void
    {
        $official = Department::create(['departmentCode' => 'CET-IT', 'departmentName' => 'Information Technology', 'collegeName' => 'Collage of Engineering and Technology', 'isActive' => true]);
        $legacy = Department::create(['departmentCode' => 'DEMO-ENG', 'departmentName' => '[DEMO] Engineering', 'collegeName' => 'CET', 'isActive' => true]);
        $user = $this->user($legacy); $old = $this->document($legacy, $user); $known = $this->document($official, $user);
        $migration = app(QmsArchiveMigration::class);
        $plan = $migration->run(); $this->assertFalse($plan['applied']); $this->assertSame(2, Department::count());
        $applied = $migration->run(true); $this->assertTrue($applied['applied']);
        $backup = json_decode(file_get_contents($applied['backup']), true);
        $plaintext = app(ArchiveEncryptionService::class)->decrypt(base64_decode($backup['ciphertext']), $backup['iv'], $backup['tag']);
        $this->assertSame($backup['sha256'], hash('sha256', $plaintext));
        $snapshot = json_decode($plaintext, true); $this->assertCount(2, $snapshot['documents']['departments']);
        $this->assertArrayHasKey('archive_documents', $snapshot['indexes']);
        $this->assertCount(5, $this->units());
        $this->assertEqualsCanonicalizing(array_values(config('qms.departments')), $this->units()->pluck('departmentName')->all());
        $this->assertSame(1, Department::where('unitType', 'college_office')->count());
        $this->assertSame((string) $official->getKey(), (string) Department::where('departmentCode', 'CET-IT')->first()->getKey());
        $this->assertSame('CET', $official->fresh()->collegeName);
        $this->assertSame((string) $legacy->getKey(), (string) $user->fresh()->departmentId);
        $this->assertSame((string) $legacy->getKey(), (string) $old->fresh()->departmentId);
        $this->assertFalse($old->fresh()->ownershipVerified); $this->assertTrue($known->fresh()->ownershipVerified);
        $this->assertSame('legacy_unclassified', $legacy->fresh()->unitType);
        $this->assertTrue($migration->run(true)['alreadyApplied']); $this->assertSame(7, Department::count());
        $this->assertSame(1, DB::connection('mongodb')->getMongoDB()->migrations->countDocuments(['migration' => QmsArchiveMigration::NAME]));
        $this->assertGreaterThan(10, QmsCategory::where('provisional', true)->count());
        $this->assertGreaterThanOrEqual(5, count(iterator_to_array(DB::connection('mongodb')->getMongoDB()->archive_documents->listIndexes())));
        $super = $this->user($official, 'faculty_staff', 0);
        $this->actingAs($super)->getJson('/api/v1/departments')->assertOk()->assertJsonPath('meta.total', 5)->assertDontSee('Office of the Dean');
        $this->patchJson('/api/v1/users/'.$user->getKey(), ['fullName' => 'Maintained legacy account'])->assertOk();
        $this->assertSame((string) $legacy->getKey(), (string) $user->fresh()->departmentId);
        $canonicalUser = $this->user($official);
        $this->patchJson('/api/v1/users/'.$canonicalUser->getKey(), ['departmentId' => (string) $legacy->getKey()])->assertUnprocessable();
        $this->postJson('/api/v1/departments', ['departmentCode' => 'CET-NEW', 'departmentName' => 'Electrical Engineering', 'collegeName' => 'CET', 'campusName' => 'Claveria'])->assertCreated();
        $this->actingAs($user)->getJson('/api/v1/documents')->assertOk()->assertJsonPath('meta.total', 0);
        $this->getJson('/api/v1/documents/tree')->assertOk()->assertJsonPath('ownershipReviewRequired', true)->assertJsonCount(0, 'units');
    }

    public function test_department_and_college_responsibilities_enforce_document_permissions_everywhere(): void
    {
        $this->migrate(); $units = $this->units(); $it = $units->last(); $first = $units->first();
        $deanOffice = Department::where('unitType', 'college_office')->firstOrFail();
        $college = College::firstOrFail(); $owner = $this->user($it);
        $outsideCollege = College::create(['collegeCode' => 'OTHER', 'collegeName' => 'Other college', 'isActive' => true]);
        $outside = Department::create(['departmentName' => 'Outside college unit', 'collegeName' => 'OTHER', 'collegeId' => $outsideCollege->getKey(), 'unitType' => 'academic_department', 'ownershipVerified' => true, 'isActive' => true]);
        foreach ([...$units, $deanOffice, $outside] as $unit) { $this->document($unit, $owner); }
        $restricted = $this->document($it, $owner, ['visibility' => 'restricted']);
        $foreign = ArchiveDocument::where('departmentId', new ObjectId((string) $outside->getKey()))->firstOrFail();
        foreach (['department_head', 'department_secretary', 'faculty_staff'] as $responsibility) {
            $user = $this->user($first, $responsibility);
            $this->actingAs($user)->getJson('/api/v1/documents')->assertOk()->assertJsonPath('meta.total', 1);
            $this->getJson('/api/v1/documents/tree')->assertOk()->assertJsonCount(1, 'units')->assertJsonPath('total', 1);
            foreach (['', '/download', '/verify-checksum'] as $suffix) { $this->getJson('/api/v1/documents/'.$foreign->getKey().$suffix)->assertNotFound(); }
            $this->getJson('/api/v1/documents?search=Outside')->assertOk()->assertJsonPath('meta.total', 0);
            $this->getJson('/api/v1/documents?unitId='.$it->getKey())->assertOk()->assertJsonPath('meta.total', 0);
            $this->getJson('/api/v1/documents/'.$restricted->getKey())->assertNotFound();
            $this->patchJson('/api/v1/documents/organization/'.$user->getKey(), ['responsibility' => 'college_dean', 'collegeIds' => [(string) $college->getKey()], 'unitIds' => []])->assertForbidden();
        }
        foreach (['college_dean', 'college_secretary', 'cluster_administrator'] as $responsibility) {
            $user = $this->user($first, $responsibility, 1, ['archiveCollegeIds' => [$college->getKey()]]);
            $this->actingAs($user)->getJson('/api/v1/documents')->assertOk()->assertJsonPath('meta.total', 6);
            $this->getJson('/api/v1/documents/tree')->assertOk()->assertJsonCount(6, 'units')->assertJsonPath('total', 6);
            $this->getJson('/api/v1/documents/'.$foreign->getKey())->assertNotFound();
            $this->getJson('/api/v1/documents/'.$restricted->getKey())->assertNotFound();
            $this->getJson('/api/v1/documents?search=Outside')->assertJsonPath('meta.total', 0);
        }
        $unscoped = $this->user($first, 'cluster_administrator', 1);
        $this->actingAs($unscoped)->getJson('/api/v1/documents/tree')->assertOk()->assertJsonCount(0, 'units');
        $faculty = $this->user($it);
        $restricted->update(['accessUserIds' => [$faculty->getKey()]]);
        $this->actingAs($faculty)->getJson('/api/v1/documents/'.$restricted->getKey())->assertOk()->assertJsonPath('data.canManage', false)->assertJsonMissingPath('data.encryptedPath');
        $this->patchJson('/api/v1/documents/'.$restricted->getKey().'/permissions', ['visibility' => 'department', 'downloadAllowed' => true, 'accessUserIds' => []])->assertForbidden();
        $unverified = $this->document($it, $faculty, ['ownershipVerified' => false]);
        $this->getJson('/api/v1/documents/'.$unverified->getKey())->assertNotFound();
        $this->get('/auditee/documents')->assertOk()->assertSee('QMS Documents')->assertSee('qmsArchives');
    }

    public function test_physical_original_reference_upload_versions_and_controlled_downloads(): void
    {
        $this->migrate(); $unit = $this->units()->first(); $user = $this->leader($unit);
        $category = QmsCategory::where('unitType', 'academic_department')->whereNotNull('parentId')->firstOrFail();
        $data = ['purpose' => 'qms', 'unitId' => (string) $unit->getKey(), 'categoryId' => (string) $category->getKey(), 'title' => 'Signed faculty register', 'visibility' => 'department'];
        $this->actingAs($user);
        $physical = $this->postJson('/api/v1/documents/upload', $data + ['recordType' => 'physical-original', 'physicalLocation' => 'Office cabinet A', 'signatureStatus' => 'verified'])
            ->assertCreated()->assertJsonPath('data.signatureStatus', 'not_verified')->assertJsonPath('data.canDownload', false)->json('data._id');
        $this->getJson('/api/v1/documents/'.$physical.'/download')->assertNotFound();
        $reference = $this->postJson('/api/v1/documents/upload', $data + ['recordType' => 'digital-reference', 'documentReference' => 'QMS-REG-001'])
            ->assertCreated()->assertJsonPath('data.documentReference', 'QMS-REG-001')->json('data._id');
        $this->getJson('/api/v1/documents?search=QMS-REG-001')->assertJsonPath('meta.total', 1);
        $this->getJson('/api/v1/documents?categoryId='.$category->parentId)->assertJsonPath('meta.total', 2);
        $bytes = "%PDF-1.4\nAuthorized QMS document";
        $fileData = $data + ['recordType' => 'file', 'copyAuthorized' => true, 'downloadAllowed' => true];
        $first = $this->post('/api/v1/documents/upload', $fileData + ['file' => UploadedFile::fake()->createWithContent('register.pdf', $bytes)], ['Accept' => 'application/json'])
            ->assertCreated()->assertJsonPath('data.canDownload', true)->json('data._id');
        $archive = ArchiveDocument::findOrFail($first);
        $this->assertNotSame($bytes, file_get_contents($this->directory.'/'.$archive->encryptedPath));
        $this->get('/api/v1/documents/'.$first.'/download')->assertOk()->assertContent($bytes)->assertHeader('X-Content-Type-Options', 'nosniff');
        $this->getJson('/api/v1/documents/'.$first.'/verify-checksum')->assertOk()->assertJsonPath('isVerified', true);
        $this->getJson('/api/v1/documents/'.$first)->assertOk()->assertJsonPath('data.departmentId', (string) $unit->getKey())->assertJsonMissingPath('data.encryptionIv');
        $version = $this->post('/api/v1/documents/'.$first.'/versions', $fileData + ['file' => UploadedFile::fake()->createWithContent('register-v2.pdf', $bytes.' version 2')], ['Accept' => 'application/json'])
            ->assertCreated()->assertJsonPath('data.versionNumber', 2)->json('data._id');
        $this->getJson('/api/v1/documents/'.$version)->assertOk()->assertJsonCount(2, 'versions');
        $this->post('/api/v1/documents/'.$first.'/versions', $fileData + ['file' => UploadedFile::fake()->createWithContent('conflicting-version.pdf', $bytes)], ['Accept' => 'application/json'])->assertConflict();
        $this->get('/api/v1/documents/'.$first.'/download')->assertOk()->assertContent($bytes);
        $this->patchJson('/api/v1/documents/'.$version.'/permissions', ['visibility' => 'restricted', 'downloadAllowed' => false, 'accessUserIds' => []])->assertOk();
        $this->get('/api/v1/documents/'.$version.'/download')->assertOk();
        $this->getJson('/api/v1/documents/'.$version)->assertOk()->assertJsonPath('data.canDownload', true);
        $this->post('/api/v1/documents/upload', array_replace($fileData, ['copyAuthorized' => false, 'file' => UploadedFile::fake()->createWithContent('bad.pdf', $bytes)]), ['Accept' => 'application/json'])->assertUnprocessable();
        $this->post('/api/v1/documents/upload', $fileData + ['file' => UploadedFile::fake()->createWithContent('bad.pdf', 'not a PDF')], ['Accept' => 'application/json'])->assertUnprocessable();
        $this->assertSame(4, ArchiveDocument::count());
    }

    public function test_auditors_require_explicit_assigned_evidence_and_cannot_browse_drafts(): void
    {
        $this->migrate(); $unit = $this->units()->first(); $owner = $this->user($unit); $auditor = $this->user($unit, 'faculty_staff', 2, ['assignedDepartmentIds' => [$unit->getKey()]]);
        $private = $this->document($unit, $owner, ['visibility' => 'restricted']); $draft = $this->document($unit, $owner);
        $audit = Audit::create(['departmentId' => $unit->getKey(), 'auditorId' => $auditor->getKey(), 'status' => 'draft',
            'items' => [], 'evidenceRequests' => [['draft' => ['archiveId' => (string) $draft->getKey()], 'responses' => []]]]);
        $this->actingAs($auditor)->getJson('/api/v1/documents')->assertOk()->assertJsonPath('meta.total', 0);
        $this->getJson('/api/v1/documents/tree')->assertOk()->assertJsonCount(0, 'units');
        $this->getJson('/api/v1/documents/'.$draft->getKey())->assertNotFound();
        $this->actingAs($this->leader($unit))->postJson('/api/v1/documents/'.$private->getKey().'/share', ['auditId' => (string) $audit->getKey()])->assertOk();
        $this->actingAs($auditor)->getJson('/api/v1/documents')->assertOk()->assertJsonPath('meta.total', 1);
        $this->getJson('/api/v1/documents/'.$private->getKey())->assertOk()->assertJsonPath('data.canManage', false);
        $this->getJson('/api/v1/documents/'.$private->getKey())->assertJsonCount(0, 'accessOptions.accounts')->assertJsonCount(0, 'accessOptions.audits');
        $this->getJson('/api/v1/documents?search='.$draft->fileCode)->assertJsonPath('meta.total', 0);
        $this->postJson('/api/v1/documents/'.$private->getKey().'/share', ['auditId' => (string) $audit->getKey()])->assertForbidden();
        $this->actingAs($this->leader($unit))->deleteJson('/api/v1/documents/'.$private->getKey().'/share/'.$audit->getKey())->assertOk();
        $this->actingAs($auditor)->getJson('/api/v1/documents/'.$private->getKey())->assertNotFound();
        $audit->update(['evidenceRequests' => [['responses' => [['archiveId' => (string) $draft->getKey()]]]]]);
        $this->getJson('/api/v1/documents/'.$draft->getKey())->assertOk();
        $auditor->update(['assignedDepartmentIds' => []]);
        $this->actingAs($auditor)->getJson('/api/v1/documents/'.$draft->getKey())->assertNotFound();
        $super = $this->user($unit, 'faculty_staff', 0);
        $this->actingAs($super)->getJson('/api/v1/documents/organization')->assertForbidden();
        $this->actingAs($this->leader($unit))->patchJson('/api/v1/documents/organization/'.$auditor->getKey(), ['responsibility' => 'college_dean', 'collegeIds' => [(string) $unit->collegeId], 'unitIds' => []])->assertUnprocessable();
        $this->patchJson('/api/v1/documents/organization/'.$owner->getKey(), ['responsibility' => 'college_secretary', 'collegeIds' => [(string) $unit->collegeId], 'unitIds' => []])->assertOk();
        $this->assertSame(3, $owner->fresh()->roleId);
        $otherUnit = $this->units()->skip(1)->first();
        $this->patchJson('/api/v1/documents/organization/'.$owner->getKey(), ['responsibility' => 'department_head', 'collegeIds' => [], 'unitIds' => [(string) $unit->getKey(), (string) $otherUnit->getKey()]])->assertUnprocessable();
        $this->patchJson('/api/v1/documents/organization/'.$owner->getKey(), ['responsibility' => 'department_head', 'collegeIds' => [], 'unitIds' => [(string) $otherUnit->getKey()]])->assertOk();
        $this->actingAs($owner->fresh())->getJson('/api/v1/documents/tree')->assertOk()->assertJsonCount(1, 'units')->assertJsonPath('units.0._id', (string) $otherUnit->getKey());
        $this->getJson('/api/v1/documents/'.$private->getKey())->assertNotFound();
    }
}
