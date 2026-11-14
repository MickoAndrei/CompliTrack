<?php
namespace Tests\Feature;

use App\Models\{ArchiveDocument, Audit, Department, QmsCategory, User};
use App\Services\{ArchiveEncryptionService, DocumentEditorToken, QmsArchiveMigration};
use Illuminate\Support\Facades\{DB, File, Http};
use Tests\TestCase;

class OnlyOfficeMongoTest extends TestCase
{
    private string $databaseName;
    private string $directory;
    private array $sessions = [];

    protected function setUp(): void
    {
        parent::setUp();
        if (getenv('RUN_MONGODB_AUDIT_TESTS') !== '1') { $this->markTestSkipped('Requires isolated MongoDB.'); }
        $this->databaseName = 'ct_onlyoffice_test_'.bin2hex(random_bytes(5));
        $this->directory = storage_path('framework/testing/'.$this->databaseName);
        File::ensureDirectoryExists($this->directory.'/archives');
        config(['database.connections.mongodb.database' => $this->databaseName, 'filesystems.disks.local.root' => $this->directory,
            'qms.backup_directory' => $this->directory.'/backup', 'services.complitrack.encryption_key' => base64_encode(str_repeat('e', 32)),
            'documents.url' => 'https://editor.example.test/document-server', 'documents.internal_url' => 'http://127.0.0.1:8082',
            'documents.app_url' => 'http://host.docker.internal:8017', 'documents.secret' => str_repeat('j', 48)]);
        DB::purge('mongodb');
        $this->assertSame($this->databaseName, DB::connection('mongodb')->getMongoDB()->getDatabaseName());
        app(QmsArchiveMigration::class)->run(true);
    }

    protected function tearDown(): void
    {
        foreach ($this->sessions as $session) { \Illuminate\Support\Facades\Cache::store('file')->forget('document-editor:'.$session); }
        if (isset($this->databaseName)) {
            $db = DB::connection('mongodb')->getMongoDB();
            if ($db->getDatabaseName() === $this->databaseName && str_starts_with($this->databaseName, 'ct_onlyoffice_test_')) { $db->drop(); }
            DB::purge('mongodb');
        }
        if (isset($this->directory) && is_dir($this->directory) && str_starts_with(realpath($this->directory), realpath(storage_path('framework/testing')).DIRECTORY_SEPARATOR)) { File::deleteDirectory($this->directory); }
        parent::tearDown();
    }

    private function actor(Department $unit, string $responsibility = 'department_head', int $role = 3, array $extra = []): User
    {
        return User::create($extra + ['fullName' => 'Isolated editor actor', 'email' => bin2hex(random_bytes(5)).'@example.test',
            'roleId' => $role, 'isActive' => true, 'departmentId' => $unit->getKey(), 'archiveResponsibility' => $responsibility]);
    }

    private function package(string $extension, string $content): string
    {
        $temporary = tempnam(sys_get_temp_dir(), 'ct-office-'); $zip = new \ZipArchive();
        $zip->open($temporary, \ZipArchive::CREATE | \ZipArchive::OVERWRITE);
        $zip->addFromString('[Content_Types].xml', '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"/>');
        $zip->addFromString(['docx' => 'word/document.xml', 'xlsx' => 'xl/workbook.xml', 'pptx' => 'ppt/presentation.xml'][$extension], '<test>'.$content.'</test>');
        $zip->close(); $bytes = file_get_contents($temporary); unlink($temporary); return $bytes;
    }

    private function document(Department $unit, User $owner, string $extension = 'docx', array $extra = []): ArchiveDocument
    {
        $bytes = $this->package($extension, 'Original'); $encrypted = app(ArchiveEncryptionService::class)->encrypt($bytes);
        $code = 'TEST-'.bin2hex(random_bytes(6)); file_put_contents($this->directory.'/archives/'.$code.'.enc', $encrypted['ciphertext']);
        return ArchiveDocument::create($extra + ['fileCode' => $code, 'title' => 'Isolated office document', 'originalName' => 'test.'.$extension,
            'recordType' => 'file', 'departmentId' => $unit->getKey(), 'collegeId' => $unit->collegeId,
            'categoryId' => QmsCategory::where('unitType', 'academic_department')->firstOrFail()->getKey(),
            'ownershipVerified' => true, 'uploadedByUserId' => $owner->getKey(), 'visibility' => 'department', 'versionNumber' => 1,
            'encryptedPath' => 'archives/'.$code.'.enc', 'checksum' => hash('sha256', $bytes), 'isEncrypted' => true,
            'encryptionIv' => $encrypted['iv'], 'encryptionTag' => $encrypted['tag'], 'encryptionKeyId' => $encrypted['keyId']]);
    }

    private function start(User $actor, ArchiveDocument $document, string $mode = 'edit'): array
    {
        $data = $this->actingAs($actor)->postJson('/api/v1/documents/'.$document->getKey().'/editor', ['mode' => $mode])->assertOk()->json();
        $this->sessions[] = $data['sessionId']; return $data;
    }

    private function sendCallback(array $editor, array $values)
    {
        $payload = $values + ['key' => $editor['config']['document']['key']];
        return $this->postJson('/api/document-editor/'.$editor['sessionId'].'/callback', ['token' => app(DocumentEditorToken::class)->sign(['payload' => $payload])]);
    }

    public function test_save_command_requires_current_editing_permission_and_does_not_claim_persistence(): void
    {
        $unit = Department::where('departmentCode', 'CET-IT')->firstOrFail();
        $owner = $this->actor($unit); $document = $this->document($unit, $owner);
        $editor = $this->start($owner, $document);
        $url = '/api/v1/document-editor/'.$editor['sessionId'].'/save';
        Http::fake(['http://127.0.0.1:8082/command' => Http::response(['error' => 0])]);
        $this->postJson($url)->assertOk()->assertJsonPath('message', 'Save requested. Waiting for CompliTrack to store the revision.');
        Http::assertSent(function ($request) use ($editor) {
            $claims = app(DocumentEditorToken::class)->verify($request['token']);
            return $request['c'] === 'forcesave' && $claims['key'] === $editor['config']['document']['key'];
        });
        $this->assertSame(1, ArchiveDocument::count());
        $view = $this->start($owner, $document, 'view');
        $this->postJson('/api/v1/document-editor/'.$view['sessionId'].'/save')->assertForbidden();
        $other = $this->actor($unit, 'department_secretary');
        $this->actingAs($other)->postJson($url)->assertForbidden();
        $owner->forceFill(['archiveResponsibility' => 'faculty_staff'])->save();
        $this->actingAs($owner)->postJson($url)->assertForbidden();
    }

    public function test_editor_permissions_collaboration_and_direct_retrieval_enforce_live_department_scope(): void
    {
        $unit = Department::where('departmentCode', 'CET-IT')->firstOrFail(); $other = Department::where('departmentCode', 'CET-ENV')->firstOrFail();
        $owner = $this->actor($unit); $secretary = $this->actor($unit, 'department_secretary'); $document = $this->document($unit, $owner);
        $first = $this->start($owner, $document); $second = $this->start($secretary, $document);
        $this->assertSame($first['config']['document']['key'], $second['config']['document']['key']);
        $this->assertSame('word', $first['config']['documentType']);
        $this->assertTrue($first['config']['document']['permissions']['edit']);
        $this->assertArrayNotHasKey('secret', $first);
        $local = $this->postJson('http://localhost/api/v1/documents/'.$document->getKey().'/editor', ['mode' => 'view'])->assertOk()->json();
        $this->sessions[] = $local['sessionId'];
        $this->assertSame('http://127.0.0.1:8082/web-apps/apps/api/documents/api.js', $local['scriptUrl']);
        $public = $this->postJson('https://app.example.test/api/v1/documents/'.$document->getKey().'/editor', ['mode' => 'view'])->assertOk()->json();
        $this->sessions[] = $public['sessionId'];
        $this->assertSame('https://editor.example.test/document-server/web-apps/apps/api/documents/api.js', $public['scriptUrl']);
        $this->get('/api/document-editor/'.$first['sessionId'].'/file')->assertForbidden();
        $token = app(DocumentEditorToken::class)->sign(['payload' => ['url' => $first['config']['document']['url']]]);
        $this->get('/api/document-editor/'.$first['sessionId'].'/file', ['Authorization' => 'Bearer '.$token])->assertOk()->assertHeader('Cache-Control', 'no-store, private');
        $this->actingAs($this->actor($other))->postJson('/api/v1/documents/'.$document->getKey().'/editor', ['mode' => 'edit'])->assertNotFound();
        $atl = $this->actor($unit, 'faculty_staff', 1);
        Audit::create(['reviewerId' => $atl->getKey(), 'departmentId' => $other->getKey(), 'status' => 'draft']);
        $this->actingAs($atl)->postJson('/api/v1/documents/'.$document->getKey().'/editor', ['mode' => 'view'])->assertForbidden();
        $this->postJson('/api/v1/documents/'.$document->getKey().'/editor', ['mode' => 'edit'])->assertForbidden();
        $mixedRoleChair = $this->actor($unit, 'department_head', 1);
        Audit::create(['reviewerId' => $mixedRoleChair->getKey(), 'departmentId' => $other->getKey(), 'status' => 'draft']);
        $this->start($mixedRoleChair, $document);
        $auditor = $this->actor($other, 'faculty_staff', 2, ['assignedDepartmentIds' => [$unit->getKey()]]);
        $audit = Audit::create(['departmentId' => $unit->getKey(), 'auditorId' => $auditor->getKey(), 'status' => 'draft']);
        $document->update(['sharedAuditIds' => [$audit->getKey()]]);
        $reader = $this->start($auditor, $document, 'view');
        $this->assertFalse($reader['config']['document']['permissions']['edit']);
        $this->assertSame('view', $reader['config']['editorConfig']['mode']);
        $this->postJson('/api/v1/documents/'.$document->getKey().'/editor', ['mode' => 'edit'])->assertForbidden();
        $this->sendCallback($reader, ['status' => 6])->assertForbidden();
        $cluster = $this->actor($unit, 'cluster_administrator', 3, ['archiveCluster' => 'cluster_a']);
        $restricted = $this->document($unit, $owner, 'docx', ['visibility' => 'restricted']);
        $this->actingAs($cluster)->postJson('/api/v1/documents/'.$restricted->getKey().'/editor', ['mode' => 'edit'])->assertNotFound();
        $owner->update(['isActive' => false]);
        $this->get('/api/document-editor/'.$first['sessionId'].'/file', ['Authorization' => 'Bearer '.$token])->assertForbidden();
        $this->sendCallback($first, ['status' => 6])->assertForbidden();
    }

    public function test_supported_office_formats_save_atomic_versions_and_reopen_with_new_keys(): void
    {
        $unit = Department::where('departmentCode', 'CET-IT')->firstOrFail(); $owner = $this->actor($unit);
        foreach (['docx' => 'word', 'xlsx' => 'cell', 'pptx' => 'slide'] as $extension => $type) {
            $document = $this->document($unit, $owner, $extension); $editor = $this->start($owner, $document);
            $originalChecksum = $document->checksum;
            $this->assertSame($type, $editor['config']['documentType']);
            $this->sendCallback($editor, ['status' => 1])->assertJsonPath('error', 0);
            foreach ([3, 7] as $status) {
                $this->sendCallback($editor, ['status' => $status])->assertJsonPath('error', 0);
                $this->getJson('/api/v1/document-editor/'.$editor['sessionId'].'/status')->assertOk()->assertJsonCount(0, 'savedVersions');
            }
            $bytes = $this->package($extension, 'Edited');
            Http::fake(['http://127.0.0.1:8082/cache/files/revised.'.$extension => Http::response($bytes)]);
            $values = ['status' => 6, 'filetype' => $extension, 'url' => 'https://editor.example.test/document-server/cache/files/revised.'.$extension];
            $this->sendCallback($editor, $values)->assertJsonPath('error', 0);
            $this->sendCallback($editor, $values)->assertJsonPath('error', 0);
            $savedId = $this->getJson('/api/v1/document-editor/'.$editor['sessionId'].'/status')->assertJsonCount(1, 'savedVersions')->assertJsonPath('savedVersions.0.versionNumber', 2)->json('currentId');
            $this->assertSame($originalChecksum, $document->fresh()->checksum);
            $revision = ArchiveDocument::findOrFail($savedId);
            $this->assertSame((string) $owner->getKey(), (string) $revision->uploadedByUserId);
            $this->get('/api/v1/documents/'.$savedId.'/download')->assertOk()->assertContent($bytes);
            $this->sendCallback($editor, array_replace($values, ['status' => 2]))->assertJsonPath('error', 0);
            $historical = $this->start($owner, $document, 'view');
            $this->assertSame($savedId, $document->fresh()->onlyofficeWorkspace['current'], 'Viewing history cannot replace the latest editing workspace.');
            $reopened = $this->start($owner, $revision);
            $this->assertNotSame($editor['config']['document']['key'], $reopened['config']['document']['key']);
            $this->sendCallback($reopened, ['status' => 4])->assertJsonPath('error', 0);
        }
    }

    public function test_invalid_and_conflicting_saves_never_replace_originals(): void
    {
        $unit = Department::where('departmentCode', 'CET-IT')->firstOrFail(); $owner = $this->actor($unit); $document = $this->document($unit, $owner);
        $editor = $this->start($owner, $document); $original = $document->checksum;
        $this->postJson('/api/document-editor/'.$editor['sessionId'].'/callback', ['status' => 2])->assertForbidden();
        $this->sendCallback($editor, ['key' => 'wrong', 'status' => 2])->assertForbidden();
        $this->sendCallback($editor, ['status' => 2, 'users' => 'invalid'])->assertUnprocessable();
        $this->postJson('/api/document-editor/'.$editor['sessionId'].'/callback', ['token' => ['invalid']])->assertForbidden();
        $this->sendCallback($editor, ['status' => 2, 'url' => 'http://attacker.test/a.docx'])->assertUnprocessable();
        $this->sendCallback($editor, ['status' => 6, 'filetype' => 'xlsx'])->assertUnprocessable();
        Http::fake(['http://127.0.0.1:8082/cache/files/*' => Http::response('invalid office file')]);
        $values = ['status' => 6, 'url' => 'https://editor.example.test/document-server/cache/files/test.docx', 'filetype' => 'docx'];
        $this->sendCallback($editor, $values)->assertJsonPath('error', 1);
        $this->assertSame(1, ArchiveDocument::count());
        $this->assertSame($original, $document->fresh()->checksum);
        $this->getJson('/api/v1/document-editor/'.$editor['sessionId'].'/status')->assertJsonCount(0, 'savedVersions')->assertJsonPath('lastStatus', 0);
        $newer = $this->document($unit, $owner, 'docx', ['versionGroupId' => $document->getKey(), 'previousVersionId' => $document->getKey(), 'versionNumber' => 2]);
        Http::fake(['http://127.0.0.1:8082/cache/files/*' => Http::response($this->package('docx', 'Conflicting edit'))]);
        $this->sendCallback($editor, $values)->assertJsonPath('error', 1);
        $this->assertSame(2, ArchiveDocument::count());
        $this->assertSame($original, $document->fresh()->checksum);
    }
}
