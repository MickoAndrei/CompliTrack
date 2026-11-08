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
    private function migrate(): void { app(QmsArchiveMigration::class)->run(true); }
    private function user(Department $unit, string $responsibility = 'faculty_staff', int $role = 3, array $extra = []): User
    {
        return User::create($extra + ['fullName' => 'Test '.$responsibility, 'email' => bin2hex(random_bytes(5)).'@example.test',
            'isActive' => true, 'roleId' => $role, 'departmentId' => $unit->getKey(), 'archiveResponsibility' => $responsibility]);
    }
    private function document(Department $unit, User $owner, array $extra = []): ArchiveDocument
    {
        return ArchiveDocument::create($extra + ['fileCode' => 'QMS-'.bin2hex(random_bytes(4)), 'title' => $unit->departmentName.' record',
            'originalName' => 'record.pdf', 'recordType' => 'file', 'departmentId' => $unit->getKey(), 'collegeId' => $unit->collegeId,
            'uploadedByUserId' => $owner->getKey(), 'ownershipVerified' => true, 'visibility' => 'department']);
    }
    private function units() { return Department::where('unitType', 'academic_department')->orderBy('departmentCode')->get(); }

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
        $this->actingAs($secretary)->getJson('/api/v1/archives/'.$public->getKey())->assertOk();
        $this->actingAs($other)->getJson('/api/v1/archives/'.$public->getKey())->assertNotFound();
        $this->getJson('/api/v1/archives/'.$public->getKey().'/download')->assertNotFound();
        $this->getJson('/api/v1/archives?search=IT%20syllabus')->assertJsonPath('meta.total', 0);
        $this->actingAs($dean)->getJson('/api/v1/archives/tree')->assertOk()->assertJsonCount(6, 'units');
        $restricted = ArchiveDocument::where('mockSeedKey', \App\Services\QmsDevelopmentData::KEY.':document:CET-IT:restricted')->firstOrFail();
        $this->actingAs($secretary)->getJson('/api/v1/archives/'.$restricted->getKey())->assertNotFound();
        $this->actingAs($head)->getJson('/api/v1/archives/'.$restricted->getKey())->assertOk();
        $this->get('/auditee/dashboard')->assertOk()->assertSee('Authorized oversight')->assertDontSee('QMS-DEMO-RETURNED');
        $submitted = Audit::where('mockSeedKey', \App\Services\QmsDevelopmentData::KEY.':audit:submitted')->firstOrFail();
        $this->actingAs($atl)->get('/audits/'.$submitted->getKey())->assertOk();
        $kitPlan = ArchiveDocument::where('sourceImportKey', 'complitrack-claveria-cet-demo-v1:DOC-PLAN-001')->firstOrFail();
        $this->get('/api/v1/archives/'.$kitPlan->getKey().'/download')->assertOk();
        $this->getJson('/api/v1/archives/'.$public->getKey())->assertNotFound();
        $this->post('/audits/'.$submitted->getKey(), ['action' => 'accept', 'version' => $submitted->version, 'reviewNotes' => 'Authorized existing ATL reviewed the mock result.'])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame('accepted', $submitted->fresh()->status);
        $this->post('/audits', ['title' => '[MOCK] ATL assignment acceptance test', 'scope' => 'Synthetic process', 'criteria' => 'Synthetic requirement', 'schedule' => now()->toDateString(),
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
            ->assertOk()->assertSee('/api/v1/archives/'.$entry['responses'][0]['archiveId'].'/download');
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
        $directory = base_path('dataset_files/CompliTrack_CET_Demo_Kit-3');
        $plan = $importer->run($directory, $super);
        $this->assertSame(27, $plan['toImport']); $this->assertSame(0, ArchiveDocument::count());
        $report = $importer->run($directory, $super, true);
        $this->assertTrue($report['applied']); $this->assertCount(27, $report['records']);
        $this->assertFileExists($report['backup']); $this->assertSame(27, ArchiveDocument::count());
        $this->assertSame(27, $importer->run($directory, $super, true)['existing']);
        $this->assertSame(27, ArchiveDocument::count());
        $first = ArchiveDocument::where('sourceImportKey', $plan['dataset'].':DOC-DPM-001')->firstOrFail();
        $this->actingAs($super)->get('/api/v1/archives/'.$first->getKey().'/download')->assertOk()
            ->assertContent(file_get_contents($directory.'/documents/Academics/DPM-USTP-CET-001_Management_of_Learning.docx'));
        $this->actingAs($faculty)->getJson('/api/v1/archives')->assertOk()->assertJsonPath('meta.total', 27 - $report['restricted']);
        $restricted = ArchiveDocument::where('sourceImportKey', $plan['dataset'].':DOC-CHK-001')->firstOrFail();
        $this->getJson('/api/v1/archives/'.$restricted->getKey())->assertNotFound();
        $this->actingAs($auditor)->getJson('/api/v1/archives')->assertOk()->assertJsonPath('meta.total', 0);
        $first->forceFill(['checksum' => str_repeat('0', 64)])->save();
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Existing import differs');
        $importer->run($directory, $super, true);
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
        $this->postJson('/api/v1/departments', ['departmentCode' => 'CET-NEW', 'departmentName' => 'Electrical Engineering', 'collegeName' => 'CET', 'campusName' => 'Claveria'])->assertUnprocessable();
        $this->actingAs($user)->getJson('/api/v1/archives')->assertOk()->assertJsonPath('meta.total', 0);
        $this->getJson('/api/v1/archives/tree')->assertOk()->assertJsonPath('ownershipReviewRequired', true)->assertJsonCount(0, 'units');
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
            $this->actingAs($user)->getJson('/api/v1/archives')->assertOk()->assertJsonPath('meta.total', 1);
            $this->getJson('/api/v1/archives/tree')->assertOk()->assertJsonCount(1, 'units')->assertJsonPath('total', 1);
            foreach (['', '/download', '/verify-checksum'] as $suffix) { $this->getJson('/api/v1/archives/'.$foreign->getKey().$suffix)->assertNotFound(); }
            $this->getJson('/api/v1/archives?search=Outside')->assertOk()->assertJsonPath('meta.total', 0);
            $this->getJson('/api/v1/archives?unitId='.$it->getKey())->assertOk()->assertJsonPath('meta.total', 0);
            $this->getJson('/api/v1/archives/'.$restricted->getKey())->assertNotFound();
            $this->patchJson('/api/v1/archives/organization/'.$user->getKey(), ['responsibility' => 'college_dean', 'collegeIds' => [(string) $college->getKey()], 'unitIds' => []])->assertForbidden();
        }
        foreach (['college_dean', 'college_secretary', 'cluster_administrator'] as $responsibility) {
            $user = $this->user($first, $responsibility, 1, ['archiveCollegeIds' => [$college->getKey()]]);
            $this->actingAs($user)->getJson('/api/v1/archives')->assertOk()->assertJsonPath('meta.total', 6);
            $this->getJson('/api/v1/archives/tree')->assertOk()->assertJsonCount(6, 'units')->assertJsonPath('total', 6);
            $this->getJson('/api/v1/archives/'.$foreign->getKey())->assertNotFound();
            $this->getJson('/api/v1/archives/'.$restricted->getKey())->assertNotFound();
            $this->getJson('/api/v1/archives?search=Outside')->assertJsonPath('meta.total', 0);
        }
        $unscoped = $this->user($first, 'cluster_administrator', 1);
        $this->actingAs($unscoped)->getJson('/api/v1/archives/tree')->assertOk()->assertJsonCount(0, 'units');
        $faculty = $this->user($it);
        $restricted->update(['accessUserIds' => [$faculty->getKey()]]);
        $this->actingAs($faculty)->getJson('/api/v1/archives/'.$restricted->getKey())->assertOk()->assertJsonPath('data.canManage', false)->assertJsonMissingPath('data.encryptedPath');
        $this->patchJson('/api/v1/archives/'.$restricted->getKey().'/permissions', ['visibility' => 'department', 'downloadAllowed' => true, 'accessUserIds' => []])->assertForbidden();
        $unverified = $this->document($it, $faculty, ['ownershipVerified' => false]);
        $this->getJson('/api/v1/archives/'.$unverified->getKey())->assertNotFound();
        $this->get('/auditee/archives')->assertOk()->assertSee('QMS Archives')->assertSee('qmsArchives');
    }

    public function test_physical_original_reference_upload_versions_and_controlled_downloads(): void
    {
        $this->migrate(); $unit = $this->units()->first(); $user = $this->user($unit, 'department_secretary');
        $category = QmsCategory::where('unitType', 'academic_department')->whereNotNull('parentId')->firstOrFail();
        $data = ['purpose' => 'qms', 'unitId' => (string) $unit->getKey(), 'categoryId' => (string) $category->getKey(), 'title' => 'Signed faculty register', 'visibility' => 'department'];
        $this->actingAs($user);
        $physical = $this->postJson('/api/v1/archives/upload', $data + ['recordType' => 'physical-original', 'physicalLocation' => 'Office cabinet A', 'signatureStatus' => 'verified'])
            ->assertCreated()->assertJsonPath('data.signatureStatus', 'not_verified')->assertJsonPath('data.canDownload', false)->json('data._id');
        $this->getJson('/api/v1/archives/'.$physical.'/download')->assertNotFound();
        $reference = $this->postJson('/api/v1/archives/upload', $data + ['recordType' => 'digital-reference', 'documentReference' => 'QMS-REG-001'])
            ->assertCreated()->assertJsonPath('data.documentReference', 'QMS-REG-001')->json('data._id');
        $this->getJson('/api/v1/archives?search=QMS-REG-001')->assertJsonPath('meta.total', 1);
        $this->getJson('/api/v1/archives?categoryId='.$category->parentId)->assertJsonPath('meta.total', 2);
        $bytes = "%PDF-1.4\nAuthorized QMS document";
        $fileData = $data + ['recordType' => 'file', 'copyAuthorized' => true, 'downloadAllowed' => true];
        $first = $this->post('/api/v1/archives/upload', $fileData + ['file' => UploadedFile::fake()->createWithContent('register.pdf', $bytes)], ['Accept' => 'application/json'])
            ->assertCreated()->assertJsonPath('data.canDownload', true)->json('data._id');
        $archive = ArchiveDocument::findOrFail($first);
        $this->assertNotSame($bytes, file_get_contents($this->directory.'/'.$archive->encryptedPath));
        $this->get('/api/v1/archives/'.$first.'/download')->assertOk()->assertContent($bytes)->assertHeader('X-Content-Type-Options', 'nosniff');
        $this->getJson('/api/v1/archives/'.$first.'/verify-checksum')->assertOk()->assertJsonPath('isVerified', true);
        $this->getJson('/api/v1/archives/'.$first)->assertOk()->assertJsonPath('data.departmentId', (string) $unit->getKey())->assertJsonMissingPath('data.encryptionIv');
        $version = $this->post('/api/v1/archives/'.$first.'/versions', $fileData + ['file' => UploadedFile::fake()->createWithContent('register-v2.pdf', $bytes.' version 2')], ['Accept' => 'application/json'])
            ->assertCreated()->assertJsonPath('data.versionNumber', 2)->json('data._id');
        $this->getJson('/api/v1/archives/'.$version)->assertOk()->assertJsonCount(2, 'versions');
        $this->post('/api/v1/archives/'.$first.'/versions', $fileData + ['file' => UploadedFile::fake()->createWithContent('conflicting-version.pdf', $bytes)], ['Accept' => 'application/json'])->assertConflict();
        $this->get('/api/v1/archives/'.$first.'/download')->assertOk()->assertContent($bytes);
        $this->patchJson('/api/v1/archives/'.$version.'/permissions', ['visibility' => 'restricted', 'downloadAllowed' => false, 'accessUserIds' => []])->assertOk();
        $this->getJson('/api/v1/archives/'.$version.'/download')->assertNotFound();
        $this->getJson('/api/v1/archives/'.$version)->assertOk()->assertJsonPath('data.canDownload', false);
        $this->post('/api/v1/archives/upload', array_replace($fileData, ['copyAuthorized' => false, 'file' => UploadedFile::fake()->createWithContent('bad.pdf', $bytes)]), ['Accept' => 'application/json'])->assertUnprocessable();
        $this->post('/api/v1/archives/upload', $fileData + ['file' => UploadedFile::fake()->createWithContent('bad.pdf', 'not a PDF')], ['Accept' => 'application/json'])->assertUnprocessable();
        $this->assertSame(4, ArchiveDocument::count());
    }

    public function test_auditors_require_explicit_assigned_evidence_and_cannot_browse_drafts(): void
    {
        $this->migrate(); $unit = $this->units()->first(); $owner = $this->user($unit); $auditor = $this->user($unit, 'faculty_staff', 2, ['assignedDepartmentIds' => [$unit->getKey()]]);
        $private = $this->document($unit, $owner, ['visibility' => 'restricted']); $draft = $this->document($unit, $owner);
        $audit = Audit::create(['departmentId' => $unit->getKey(), 'auditorId' => $auditor->getKey(), 'status' => 'draft',
            'items' => [], 'evidenceRequests' => [['draft' => ['archiveId' => (string) $draft->getKey()], 'responses' => []]]]);
        $this->actingAs($auditor)->getJson('/api/v1/archives')->assertOk()->assertJsonPath('meta.total', 0);
        $this->getJson('/api/v1/archives/tree')->assertOk()->assertJsonCount(0, 'units');
        $this->getJson('/api/v1/archives/'.$draft->getKey())->assertNotFound();
        $this->actingAs($owner)->postJson('/api/v1/archives/'.$private->getKey().'/share', ['auditId' => (string) $audit->getKey()])->assertOk();
        $this->actingAs($auditor)->getJson('/api/v1/archives')->assertOk()->assertJsonPath('meta.total', 1);
        $this->getJson('/api/v1/archives/'.$private->getKey())->assertOk()->assertJsonPath('data.canManage', false);
        $this->getJson('/api/v1/archives/'.$private->getKey())->assertJsonCount(0, 'accessOptions.accounts')->assertJsonCount(0, 'accessOptions.audits');
        $this->getJson('/api/v1/archives?search='.$draft->fileCode)->assertJsonPath('meta.total', 0);
        $this->postJson('/api/v1/archives/'.$private->getKey().'/share', ['auditId' => (string) $audit->getKey()])->assertForbidden();
        $this->actingAs($owner)->deleteJson('/api/v1/archives/'.$private->getKey().'/share/'.$audit->getKey())->assertOk();
        $this->actingAs($auditor)->getJson('/api/v1/archives/'.$private->getKey())->assertNotFound();
        $audit->update(['evidenceRequests' => [['responses' => [['archiveId' => (string) $draft->getKey()]]]]]);
        $this->getJson('/api/v1/archives/'.$draft->getKey())->assertOk();
        $auditor->update(['assignedDepartmentIds' => []]);
        $this->actingAs($auditor)->getJson('/api/v1/archives/'.$draft->getKey())->assertNotFound();
        $super = $this->user($unit, 'faculty_staff', 0);
        $this->actingAs($super)->patchJson('/api/v1/archives/organization/'.$auditor->getKey(), ['responsibility' => 'college_dean', 'collegeIds' => [(string) $unit->collegeId], 'unitIds' => []])->assertUnprocessable();
        $this->patchJson('/api/v1/archives/organization/'.$owner->getKey(), ['responsibility' => 'college_secretary', 'collegeIds' => [(string) $unit->collegeId], 'unitIds' => []])->assertOk();
        $this->assertSame(3, $owner->fresh()->roleId);
        $otherUnit = $this->units()->skip(1)->first();
        $this->patchJson('/api/v1/archives/organization/'.$owner->getKey(), ['responsibility' => 'department_head', 'collegeIds' => [], 'unitIds' => [(string) $unit->getKey(), (string) $otherUnit->getKey()]])->assertUnprocessable();
        $this->patchJson('/api/v1/archives/organization/'.$owner->getKey(), ['responsibility' => 'department_head', 'collegeIds' => [], 'unitIds' => [(string) $otherUnit->getKey()]])->assertOk();
        $this->actingAs($owner->fresh())->getJson('/api/v1/archives/tree')->assertOk()->assertJsonCount(1, 'units')->assertJsonPath('units.0._id', (string) $otherUnit->getKey());
        $this->getJson('/api/v1/archives/'.$private->getKey())->assertNotFound();
    }
}
