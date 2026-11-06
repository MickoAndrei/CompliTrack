<?php

namespace Tests\Feature;

use App\Models\{Audit, Department, User};
use Illuminate\Support\Facades\DB;
use MongoDB\BSON\ObjectId;
use Tests\TestCase;

class AuditWorkflowMongoTest extends TestCase
{
    private ?string $testDatabase = null;
    private ?string $testStorage = null;

    protected function setUp(): void
    {
        parent::setUp();
        if (getenv('RUN_MONGODB_AUDIT_TESTS') !== '1') {
            $this->markTestSkipped('Opt-in integration test uses a new isolated MongoDB database.');
        }
        $this->testDatabase = 'complitrack_audit_test_'.bin2hex(random_bytes(4));
        config(['database.connections.mongodb.database' => $this->testDatabase]);
        DB::purge('mongodb');
        $this->assertSame($this->testDatabase, DB::connection('mongodb')->getMongoDB()->getDatabaseName());
        DB::connection('mongodb')->getMongoDB()->selectCollection('audits')->createIndex(['assignmentKey' => 1], ['unique' => true, 'sparse' => true]);
        DB::connection('mongodb')->getMongoDB()->selectCollection('notifications')->createIndex(['dedupeKey' => 1], ['unique' => true, 'sparse' => true]);
        $this->testStorage = storage_path('framework/testing/auditee-'.bin2hex(random_bytes(6)));
        \Illuminate\Support\Facades\File::ensureDirectoryExists($this->testStorage);
        config(['filesystems.disks.local.root' => $this->testStorage, 'services.complitrack.encryption_key' => base64_encode(str_repeat('t', 32))]);
    }

    protected function tearDown(): void
    {
        if ($this->testDatabase !== null) {
            $database = DB::connection('mongodb')->getMongoDB();
            if ($database->getDatabaseName() === $this->testDatabase && str_starts_with($this->testDatabase, 'complitrack_audit_test_')) {
                $database->drop();
            }
            DB::purge('mongodb');
        }
        if ($this->testStorage && is_dir($this->testStorage)) {
            $resolved = realpath($this->testStorage);
            $allowed = realpath(storage_path('framework/testing'));
            if ($resolved && $allowed && str_starts_with($resolved, $allowed.DIRECTORY_SEPARATOR)) {
                \Illuminate\Support\Facades\File::deleteDirectory($resolved);
            }
        }
        parent::tearDown();
    }

    public function test_authorized_assignment_appears_and_opens_without_exposing_other_audits(): void
    {
        $department = Department::create(['departmentName' => 'Test IT', 'collegeName' => 'CET', 'isActive' => true]);
        $departmentId = new ObjectId((string) $department->getKey());
        $admin = User::create(['fullName' => 'Test Admin', 'roleId' => 1, 'isActive' => true, 'departmentId' => $departmentId]);
        $auditor = User::create(['fullName' => 'Test Auditor', 'roleId' => 2, 'isActive' => true, 'departmentId' => (string) $departmentId, 'assignedDepartmentIds' => [$departmentId]]);
        $other = User::create(['fullName' => 'Other Auditor', 'roleId' => 2, 'isActive' => true, 'departmentId' => $departmentId, 'assignedDepartmentIds' => [$departmentId]]);

        $this->actingAs($admin)->post('/audits', ['title' => 'Assignment regression test', 'scope' => 'Test process', 'criteria' => 'Test criterion',
            'schedule' => '2020-01-01', 'departmentId' => (string) $departmentId, 'auditorId' => (string) $auditor->getKey()])->assertRedirect();
        $audit = Audit::firstOrFail();
        $this->assertInstanceOf(ObjectId::class, $audit->getRawOriginal('auditorId'));
        $this->actingAs($admin)->get('/audits')->assertOk()->assertSee('Assignment regression test');
        $this->actingAs($auditor)->get('/audits')->assertOk()->assertSee('Assignment regression test')->assertDontSee('No Assigned Audits Yet');
        $this->get('/audits/'.$audit->getKey())->assertOk()->assertSee('Audit Workspace');
        foreach (['ready', 'submitted', 'returned', 'accepted'] as $status) {
            $audit->update(['status' => $status]);
            $this->get('/audits')->assertOk()->assertSee('Assignment regression test');
        }
        $this->actingAs($other)->get('/audits')->assertOk()->assertDontSee('Assignment regression test');
        $this->get('/audits/'.$audit->getKey())->assertNotFound();
        $auditor->update(['assignedDepartmentIds' => []]);
        $this->actingAs($auditor)->get('/audits/'.$audit->getKey())->assertNotFound();
        $this->get('/audits/not-an-id')->assertRedirect()->assertSessionHasErrors();
    }

    private function fixture(): array
    {
        $department = Department::create(['departmentName' => 'Test IT', 'collegeName' => 'CET', 'isActive' => true]);
        $id = new ObjectId((string) $department->getKey());
        $admin = User::create(['fullName' => 'Reviewer', 'roleId' => 1, 'isActive' => true, 'departmentId' => $id]);
        $auditor = User::create(['fullName' => 'Auditor', 'roleId' => 2, 'isActive' => true, 'departmentId' => $id, 'assignedDepartmentIds' => [$id]]);
        $this->actingAs($auditor)->get('/audits')->assertOk()->assertSee('No Assigned Audits Yet');
        $data = ['title' => 'Workflow test', 'scope' => 'Process', 'criteria' => 'Requirement', 'schedule' => '2026-11-06',
            'departmentId' => (string) $id, 'auditorId' => (string) $auditor->getKey()];
        $this->actingAs($admin)->post('/audits', $data)->assertRedirect()->assertSessionHasNoErrors();
        $this->post('/audits', $data)->assertRedirect()->assertSessionHasErrors('title');
        $this->assertSame(1, Audit::count());
        return [$admin, $auditor, Audit::firstOrFail()];
    }

    private function saveAction(Audit $audit, string $action, array $data = [])
    {
        return $this->post('/audits/'.$audit->getKey(), ['action' => $action, 'version' => $audit->fresh()->version] + $data);
    }

    public function test_checklist_evidence_findings_metrics_submission_and_revision_persist(): void
    {
        [$admin, $auditor, $audit] = $this->fixture();
        $this->actingAs($auditor);
        foreach (['First', 'Second', 'Third', 'Remove me'] as $question) {
            $this->saveAction($audit, 'add', ['question' => $question, 'reference' => 'Permitted criterion'])->assertRedirect()->assertSessionHasNoErrors();
        }
        $this->saveAction($audit, 'edit', ['item' => 0, 'question' => 'Edited'])->assertRedirect()->assertSessionHasNoErrors();
        $this->saveAction($audit, 'move', ['item' => 1])->assertRedirect()->assertSessionHasNoErrors();
        $this->saveAction($audit, 'remove', ['item' => 3])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame('Second', $audit->fresh()->items[0]['question']);
        $this->get('/audits/'.$audit->getKey())->assertOk()->assertSee('Edited');
        $this->saveAction($audit, 'ready')->assertRedirect()->assertSessionHasNoErrors();
        $this->saveAction($audit, 'submit')->assertRedirect()->assertSessionHasErrors();
        $this->saveAction($audit, 'add', ['question' => 'Too late'])->assertForbidden();
        foreach (['C', 'N', 'O'] as $index => $result) {
            $this->saveAction($audit, 'evidence', ['item' => $index, 'method' => 'digital', 'notes' => 'Record reviewed', 'reference' => 'Permitted reference'])->assertRedirect()->assertSessionHasNoErrors();
            $this->saveAction($audit, 'evaluate', ['item' => $index, 'result' => $result, 'remarks' => 'Factual remarks', 'finding' => $result === 'N' ? 'Required record absent' : null])->assertRedirect()->assertSessionHasNoErrors();
        }
        $this->saveAction($audit, 'evidence', ['item' => 1, 'method' => 'physical-original', 'notes' => 'Original reviewed', 'signature' => 'missing-incomplete'])->assertRedirect()->assertSessionHasNoErrors();
        $this->saveAction($audit, 'evidence', ['item' => 2, 'method' => 'restricted', 'notes' => 'Authorized verification only'])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertCount(2, $audit->fresh()->items[1]['evidence']);
        $this->assertSame('missing-incomplete', $audit->fresh()->items[1]['evidence'][1]['signature']);
        $this->assertSame('N', $audit->fresh()->items[1]['result']);
        $this->assertSame(0, \App\Models\ComplianceCheck::count());
        $request = \Illuminate\Http\Request::create('/api/v1/auditor/metrics');
        $request->setUserResolver(fn () => $auditor);
        $metrics = app(\App\Http\Controllers\Web\AuditController::class)->metrics($request)->getData(true);
        foreach (['totalChecks' => 3, 'compliantChecks' => 1, 'nonCompliantChecks' => 1, 'observations' => 1, 'pendingChecks' => 0, 'nonconformities' => 1, 'openCars' => 0] as $key => $value) {
            $this->assertSame($value, $metrics['data'][$key], $key);
        }
        $this->assertCount(1, $metrics['assignments']);
        $this->assertNotEmpty($metrics['changes']);
        $this->saveAction($audit, 'submit')->assertRedirect()->assertSessionHasNoErrors();
        $this->saveAction($audit, 'evaluate', ['item' => 0, 'result' => 'N', 'remarks' => 'Overwrite'])->assertForbidden();
        $this->actingAs($admin);
        $this->saveAction($audit, 'return', ['reviewNotes' => 'Clarify the remarks'])->assertRedirect()->assertSessionHasNoErrors();
        $this->actingAs($auditor);
        $this->saveAction($audit, 'evaluate', ['item' => 0, 'result' => 'C', 'remarks' => 'Clarified review'])->assertRedirect()->assertSessionHasNoErrors();
        $this->saveAction($audit, 'submit')->assertRedirect()->assertSessionHasNoErrors();
        $this->actingAs($admin);
        $this->saveAction($audit, 'accept', ['reviewNotes' => 'Accepted'])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame('accepted', $audit->fresh()->status);
        $this->assertNotEmpty($audit->fresh()->history);
        $this->assertSame(5, \App\Models\Notification::count());
        $this->assertSame(1, \App\Models\Notification::where('type', 'audit_assigned')->count());
        $this->assertSame(2, \App\Models\Notification::where('type', 'audit_submitted')->count());
        $this->assertSame(1, \App\Models\Notification::where('type', 'audit_accepted')->count());
        $this->assertSame(1, \App\Models\Notification::where('type', 'audit_returned')->count());
        app(\App\Services\NotificationDispatchService::class)->persist($auditor->getKey(), 'audit:'.$audit->getKey().':assigned', 'audit_assigned', 'Duplicate', 'Retry');
        $this->assertSame(5, \App\Models\Notification::count());
        $this->assertSame(2, app(\App\Services\RoleScopeService::class)->notifications($admin)->count());
    }

    public function test_scope_invalid_inputs_and_stale_updates_are_rejected(): void
    {
        [$admin, $auditor, $audit] = $this->fixture();
        $outside = Department::create(['departmentName' => 'Outside', 'collegeName' => 'OTHER', 'isActive' => true]);
        $this->actingAs($admin)->post('/audits', ['title' => 'Outside', 'scope' => 'Test', 'criteria' => 'Test', 'schedule' => '2026-11-06',
            'departmentId' => (string) $outside->getKey(), 'auditorId' => (string) $auditor->getKey()])->assertNotFound();
        $this->actingAs($auditor)->post('/audits', [])->assertForbidden();
        $this->saveAction($audit, 'add', ['question' => 'Question'])->assertRedirect()->assertSessionHasNoErrors();
        $this->post('/audits/'.$audit->getKey(), ['version' => 1, 'action' => 'add', 'question' => 'Stale'])->assertStatus(409);
        $this->saveAction($audit, 'ready')->assertRedirect()->assertSessionHasNoErrors();
        $this->saveAction($audit, 'evidence', ['item' => 0, 'method' => 'digital', 'notes' => 'Permitted verification'])->assertRedirect()->assertSessionHasNoErrors();
        $this->saveAction($audit, 'evaluate', ['item' => 0, 'result' => 'OFI', 'remarks' => 'Invalid'])->assertRedirect()->assertSessionHasErrors('result');
        $this->saveAction($audit, 'evidence', ['item' => 0, 'method' => 'physical-original', 'notes' => 'Review'])->assertRedirect()->assertSessionHasErrors('signature');
        $other = User::create(['fullName' => 'Unassigned', 'roleId' => 2, 'isActive' => true, 'assignedDepartmentIds' => []]);
        $this->actingAs($other);
        $this->saveAction($audit, 'add', ['question' => 'Intrusion'])->assertNotFound();
        $this->saveAction($audit, 'submit')->assertNotFound();
        $this->assertSame(1, count($audit->fresh()->items));
    }

    public function test_legacy_records_notifications_and_private_evidence_remain_scoped(): void
    {
        [$admin, $auditor, $audit] = $this->fixture();
        $auditee = User::create(['fullName' => 'Auditee', 'roleId' => 3, 'isActive' => true,
            'departmentId' => new ObjectId((string) $audit->departmentId)]);
        $other = User::create(['fullName' => 'Other', 'roleId' => 2, 'isActive' => true, 'assignedDepartmentIds' => []]);
        $check = \App\Models\ComplianceCheck::create(['checkCode' => 'TEST-CAR', 'auditorId' => $auditor->getKey(),
            'auditeeId' => $auditee->getKey(), 'departmentId' => $audit->departmentId, 'result' => 'non_compliant',
            'correctiveStatus' => 'in_progress', 'statusHistory' => []]);
        $archive = \App\Models\ArchiveDocument::create(['fileCode' => 'TEST-PRIVATE', 'departmentId' => $audit->departmentId,
            'uploadedByUserId' => $auditee->getKey(), 'originalName' => 'Private.pdf']);
        $this->actingAs($auditor)->getJson('/api/v1/compliance-checks')->assertOk()->assertJsonPath('meta.total', 1);
        $this->getJson('/api/v1/archives')->assertOk()->assertJsonPath('meta.total', 1);
        $this->patchJson('/api/v1/compliance-checks/'.$check->getKey().'/status', ['correctiveStatus' => 'closed'])->assertUnprocessable();
        $this->patchJson('/api/v1/compliance-checks/'.$check->getKey().'/status', ['correctiveStatus' => 'closed', 'remarks' => 'Corrective action verified'])->assertOk();
        $this->assertSame('closed', $check->fresh()->correctiveStatus);
        $this->saveAction($audit, 'interview', ['auditeeId' => (string) $auditee->getKey(), 'process' => 'Test process', 'topics' => 'Question', 'responses' => 'Factual response'])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame('Factual response', $audit->fresh()->interviews[0]['responses']);
        $this->actingAs($other)->getJson('/api/v1/compliance-checks/'.$check->getKey())->assertNotFound();
        $this->getJson('/api/v1/archives/'.$archive->getKey().'/download')->assertNotFound();
        $this->saveAction($audit, 'interview', ['auditeeId' => (string) $auditee->getKey(), 'process' => 'Test', 'topics' => 'Q', 'responses' => 'R'])->assertNotFound();
        $this->actingAs($admin)->get('/audits')->assertOk();
        $this->actingAs($auditor)->get('/auditor/checks')->assertOk();
        $this->get('/auditor/archives')->assertOk();
        $this->get('/auditor/corrective-actions')->assertOk();
        $this->get('/auditor/notifications')->assertOk();
    }

    public function test_auditee_requests_drafts_reviews_and_private_notes_are_scoped(): void
    {
        [$admin, $auditor, $audit] = $this->fixture();
        $person = User::create(['fullName' => 'Request Recipient', 'roleId' => 3, 'isActive' => true, 'departmentId' => (string) $audit->departmentId]);
        $other = User::create(['fullName' => 'Not Recipient', 'roleId' => 3, 'isActive' => true, 'departmentId' => (string) $audit->departmentId]);
        $this->actingAs($auditor);
        $this->saveAction($audit, 'add', ['question' => 'CONFIDENTIAL internal checklist text'])->assertRedirect()->assertSessionHasNoErrors();
        $this->post('/audits/'.$audit->getKey().'/requests', ['version' => $audit->fresh()->version, 'itemId' => $audit->fresh()->items[0]['id'],
            'auditeeId' => (string) $person->getKey(), 'description' => 'Permitted record request', 'referenceStandard' => 'ISO9001-9.2'])->assertRedirect()->assertSessionHasNoErrors();
        $entry = $audit->fresh()->evidenceRequests[0];
        $url = '/auditee/evidence-requests/'.$audit->getKey().'/'.$entry['id'];
        $this->actingAs($other)->get('/auditee/evidence-requests')->assertOk()->assertDontSee('Permitted record request');
        $key = (string) \Illuminate\Support\Str::uuid();
        $this->post($url, ['version' => $audit->fresh()->version, 'submissionKey' => $key, 'action' => 'submit', 'method' => 'digital-reference', 'notes' => 'Intrusion'])->assertNotFound();
        $this->actingAs($person)->get('/auditee/evidence-requests')->assertOk()->assertSee('Permitted record request')->assertDontSee('CONFIDENTIAL');
        $this->get('/auditee/evidence-requests?requestStatus=requested')->assertOk()->assertSee('Permitted record request');
        $this->get('/audits/'.$audit->getKey())->assertForbidden();
        $payload = ['submissionKey' => $key, 'method' => 'digital-reference', 'notes' => 'Factual response', 'reference' => 'Permitted code'];
        $this->post($url, $payload + ['version' => $audit->fresh()->version, 'action' => 'draft'])->assertRedirect()->assertSessionHasNoErrors();
        $this->get('/auditee/evidence-requests')->assertOk()->assertSee('Factual response');
        $this->assertSame('requested', $audit->fresh()->evidenceRequests[0]['status']);
        $this->post($url, $payload + ['version' => $audit->fresh()->version, 'action' => 'submit'])->assertRedirect()->assertSessionHasNoErrors();
        $this->post($url, $payload + ['version' => 1, 'action' => 'submit'])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertCount(1, $audit->fresh()->evidenceRequests[0]['responses']);
        $this->assertNull($audit->fresh()->items[0]['result']);
        $this->actingAs($auditor)->post('/audits/'.$audit->getKey().'/requests/'.$entry['id'].'/review', ['version' => $audit->fresh()->version,
            'decision' => 'needs_information', 'feedback' => 'Arrange the original'])->assertRedirect()->assertSessionHasNoErrors();
        $physical = ['submissionKey' => (string) \Illuminate\Support\Str::uuid(), 'method' => 'physical-original', 'notes' => 'Original available', 'availability' => 'available', 'signature' => 'verified'];
        $this->actingAs($person)->post($url, $physical + ['version' => $audit->fresh()->version, 'action' => 'submit'])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertArrayNotHasKey('signature', $audit->fresh()->evidenceRequests[0]['responses'][1]);
        $this->actingAs($auditor)->post('/audits/'.$audit->getKey().'/requests/'.$entry['id'].'/review', ['version' => $audit->fresh()->version,
            'decision' => 'reviewed', 'feedback' => 'Original signature inspected', 'signature' => 'verified'])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame('verified', $audit->fresh()->items[0]['evidence'][0]['signature']);
        $this->saveAction($audit, 'ready')->assertRedirect()->assertSessionHasNoErrors();
        $this->saveAction($audit, 'evaluate', ['item' => 0, 'result' => 'C', 'remarks' => 'Requirement reviewed'])->assertRedirect()->assertSessionHasNoErrors();
        $this->saveAction($audit, 'submit')->assertRedirect()->assertSessionHasNoErrors();
        $this->actingAs($admin);
        $this->saveAction($audit, 'accept', ['reviewNotes' => 'Accepted'])->assertRedirect()->assertSessionHasNoErrors();
        $this->actingAs($person)->get('/auditee/evidence-requests')->assertOk()->assertSee('Original signature inspected')->assertDontSee('CONFIDENTIAL');
        $this->get('/auditee/my-findings')->assertOk()->assertDontSee('Requirement reviewed');
        $publishUrl = '/audits/'.$audit->getKey().'/requests/'.$entry['id'].'/publish';
        $this->post($publishUrl, ['version' => $audit->fresh()->version, 'publicSummary' => 'Not authorized'])->assertForbidden();
        $itemsBeforeSharing = $audit->fresh()->items;
        $this->actingAs($auditor)->post($publishUrl, ['version' => $audit->fresh()->version, 'publicSummary' => 'Permitted accepted result'])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame($itemsBeforeSharing, $audit->fresh()->items);
        $this->actingAs($person)->get('/auditee/my-findings')->assertOk()->assertSee('Permitted accepted result')->assertDontSee('CONFIDENTIAL');
        $this->actingAs($other)->get('/auditee/my-findings')->assertOk()->assertDontSee('Permitted accepted result');
        $this->actingAs($person);
        foreach (['dashboard','my-findings','upload','archives','notifications','help'] as $page) { $this->get('/auditee/'.$page)->assertOk(); }
        $this->get('/profile')->assertOk();
        $this->getJson('/api/v1/notifications?unread=1&type=evidence_requested')->assertOk()->assertJsonPath('meta.total', 1);
    }

    public function test_corrective_references_and_encrypted_uploads_do_not_close_cars(): void
    {
        [$admin, $auditor, $audit] = $this->fixture();
        $person = User::create(['fullName' => 'Auditee', 'roleId' => 3, 'isActive' => true, 'departmentId' => (string) $audit->departmentId]);
        $other = User::create(['fullName' => 'Other', 'roleId' => 3, 'isActive' => true, 'departmentId' => (string) $audit->departmentId]);
        $check = \App\Models\ComplianceCheck::create(['checkCode' => 'TEST-EVIDENCE', 'auditorId' => $auditor->getKey(), 'auditeeId' => $person->getKey(),
            'departmentId' => $audit->departmentId, 'referenceStandard' => 'ISO9001-9.2', 'result' => 'non_compliant', 'correctiveStatus' => 'open', 'linkedArchiveIds' => []]);
        $this->actingAs($person)->get('/auditee/finding-details/'.$check->getKey())->assertOk();
        $key = (string) \Illuminate\Support\Str::uuid();
        $this->post('/auditee/finding-details/'.$check->getKey().'/response', ['submissionKey' => $key, 'method' => 'physical-original', 'notes' => 'Ready for inspection', 'availability' => 'available'])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame('open', $check->fresh()->correctiveStatus);
        $this->assertSame('non_compliant', $check->fresh()->result);
        $this->assertArrayNotHasKey('signature', $check->fresh()->evidenceResponses[0]);
        $plaintext = "%PDF-1.4\nAuthorized test evidence";
        $file = \Illuminate\Http\UploadedFile::fake()->createWithContent('evidence.pdf', $plaintext);
        $upload = $this->post('/api/v1/archives/upload', ['file' => $file, 'checkId' => (string) $check->getKey(), 'referenceStandard' => 'ISO9001-9.2'], ['Accept' => 'application/json'])
            ->assertCreated()->assertJsonMissingPath('data.encryptedPath');
        $id = $upload->json('data._id');
        $archive = \App\Models\ArchiveDocument::findOrFail($id);
        $ciphertext = file_get_contents($this->testStorage.DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $archive->encryptedPath));
        $this->assertNotSame($plaintext, $ciphertext);
        $this->assertSame(hash('sha256', $plaintext), $archive->checksum);
        $this->get('/api/v1/archives/'.$id.'/download')->assertOk()->assertContent($plaintext);
        $this->getJson('/api/v1/archives')->assertOk()->assertJsonPath('meta.total', 1);
        $this->assertCount(1, $check->fresh()->linkedArchiveIds);
        $this->assertSame('open', $check->fresh()->correctiveStatus);
        $this->post('/api/v1/archives/upload', ['file' => \Illuminate\Http\UploadedFile::fake()->createWithContent('bad.docx', 'PK-not-a-document'), 'checkId' => (string) $check->getKey(), 'referenceStandard' => 'ISO9001-9.2'], ['Accept' => 'application/json'])->assertUnprocessable();
        $this->post('/api/v1/archives/upload', ['file' => \Illuminate\Http\UploadedFile::fake()->create('large.pdf', 20481), 'checkId' => (string) $check->getKey(), 'referenceStandard' => 'ISO9001-9.2'], ['Accept' => 'application/json'])->assertUnprocessable();
        $this->actingAs($other)->get('/auditee/finding-details/'.$check->getKey())->assertNotFound();
        $this->getJson('/api/v1/archives/'.$id.'/download')->assertNotFound();
    }

    public function test_results_summary_filters_and_details_use_only_authorized_legacy_records(): void
    {
        [$admin, $auditor, $audit] = $this->fixture();
        $person = User::create(['fullName' => 'Results Recipient', 'roleId' => 3, 'isActive' => true, 'departmentId' => (string) $audit->departmentId]);
        $other = User::create(['fullName' => 'Other Recipient', 'roleId' => 3, 'isActive' => true, 'departmentId' => (string) $audit->departmentId]);
        $base = ['auditorId' => $auditor->getKey(), 'auditeeId' => $person->getKey(), 'departmentId' => $audit->departmentId,
            'referenceStandard' => 'ISO9001-9.2', 'linkedArchiveIds' => []];
        $open = \App\Models\ComplianceCheck::create($base + ['checkCode' => 'RESULT-OPEN', 'result' => 'non_compliant', 'correctiveStatus' => 'open', 'remarks' => 'Released explanation']);
        \App\Models\ComplianceCheck::create($base + ['checkCode' => 'RESULT-CLOSED', 'result' => 'non_compliant', 'correctiveStatus' => 'closed']);
        \App\Models\ComplianceCheck::create($base + ['checkCode' => 'RESULT-COMPLIANT', 'result' => 'compliant']);
        \App\Models\ComplianceCheck::create(array_merge($base, ['auditeeId' => $other->getKey(), 'checkCode' => 'PRIVATE-RESULT', 'result' => 'non_compliant', 'correctiveStatus' => 'open']));
        $this->actingAs($person)->get('/auditee/my-findings')->assertOk()->assertViewHas('summary', ['total' => 3, 'nonconformities' => 2, 'openCars' => 1])->assertDontSee('PRIVATE-RESULT');
        $this->get('/auditee/my-findings?search=RESULT-OPEN&result=non_compliant&actionable=1')->assertOk()
            ->assertViewHas('checks', fn ($checks) => $checks->total() === 1)->assertSee('RESULT-OPEN')->assertDontSee('RESULT-CLOSED')
            ->assertViewHas('summary', fn ($summary) => $summary['total'] === 3);
        $this->get('/auditee/my-findings?archived=1')->assertOk()->assertSee('RESULT-CLOSED')->assertDontSee('RESULT-OPEN');
        $this->get('/auditee/my-findings?result=observation')->assertOk()->assertViewHas('checks', fn ($checks) => $checks->total() === 0);
        $this->get('/auditee/finding-details/'.$open->getKey())->assertOk()->assertSee('Released explanation')->assertSee('Check item / requirement')->assertSee('Provide Corrective Evidence');
        $this->get('/auditee/finding-details/'.$open->getKey(), ['X-Requested-With' => 'XMLHttpRequest'])->assertOk()
            ->assertSee('data-result-detail', false)->assertSee('Evidence Reviewed')->assertSee('Review date: Not recorded');
        $this->postJson('/auditee/finding-details/'.$open->getKey().'/response', ['submissionKey' => (string) \Illuminate\Support\Str::uuid(),
            'method' => 'physical-original', 'availability' => 'available', 'notes' => 'Permitted original ready for review'])->assertOk()
            ->assertJsonPath('message', 'Corrective evidence saved; the CAR remains subject to authorized verification.');
        $this->assertSame('open', $open->fresh()->correctiveStatus);
        $this->postJson('/auditee/finding-details/'.$open->getKey().'/response', ['method' => 'physical-original'])->assertUnprocessable();
        $this->actingAs($other)->get('/auditee/finding-details/'.$open->getKey())->assertNotFound();
        $this->get('/auditee/finding-details/'.$open->getKey(), ['X-Requested-With' => 'XMLHttpRequest'])->assertNotFound();
        $this->postJson('/auditee/finding-details/'.$open->getKey().'/response', ['submissionKey' => (string) \Illuminate\Support\Str::uuid(),
            'method' => 'digital-reference', 'notes' => 'Unauthorized attempt'])->assertNotFound();
        $this->actingAs($person)->from('/auditee/my-findings')->get('/auditee/my-findings?search[]=bad')->assertRedirect('/auditee/my-findings')->assertSessionHasErrors('search');
    }

    public function test_during_audit_authorized_file_uses_existing_private_storage(): void
    {
        [$admin, $auditor, $audit] = $this->fixture();
        $person = User::create(['fullName' => 'File Recipient', 'roleId' => 3, 'isActive' => true, 'departmentId' => (string) $audit->departmentId]);
        $this->actingAs($auditor);
        $this->saveAction($audit, 'add', ['question' => 'File test'])->assertRedirect()->assertSessionHasNoErrors();
        $this->post('/audits/'.$audit->getKey().'/requests', ['version' => $audit->fresh()->version, 'itemId' => $audit->fresh()->items[0]['id'], 'auditeeId' => (string) $person->getKey(),
            'description' => 'Authorized sample file', 'allowUpload' => '1', 'referenceStandard' => 'ISO9001-9.2'])->assertRedirect()->assertSessionHasNoErrors();
        $entry = $audit->fresh()->evidenceRequests[0];
        $this->actingAs($person)->post('/auditee/evidence-requests/'.$audit->getKey().'/'.$entry['id'], ['version' => $audit->fresh()->version,
            'submissionKey' => (string) \Illuminate\Support\Str::uuid(), 'action' => 'draft', 'method' => 'file', 'notes' => 'Permitted sample', 'copyAuthorized' => '1',
            'file' => \Illuminate\Http\UploadedFile::fake()->createWithContent('test.pdf', "%PDF-1.4\nSample")])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertNotEmpty($audit->fresh()->evidenceRequests[0]['draft']['archiveId']);
        $this->post('/auditee/evidence-requests/'.$audit->getKey().'/'.$entry['id'], ['version' => $audit->fresh()->version,
            'submissionKey' => (string) \Illuminate\Support\Str::uuid(), 'action' => 'submit', 'method' => 'file', 'notes' => 'Submit saved file', 'copyAuthorized' => '1'])
            ->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame(1, \App\Models\ArchiveDocument::count());
        $this->assertNotEmpty($audit->fresh()->evidenceRequests[0]['responses'][0]['archiveId']);
        $this->assertNull($audit->fresh()->items[0]['result']);
        $this->assertSame(0, \App\Models\ComplianceCheck::count());
    }
}
