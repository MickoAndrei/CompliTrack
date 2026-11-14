<?php

namespace Tests\Feature;

use App\Models\{Audit, Department, User};
use Illuminate\Support\Facades\DB;
use MongoDB\BSON\ObjectId;
use Tests\TestCase;

class AuditWorkflowMongoTest extends TestCase
{
    use \Tests\Support\AuthorizesAuditPlan;

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
        \App\Models\Role::create(['roleId' => 2, 'permissions' => ['cars.verify', 'cars.close']]);
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
        $department = Department::create(['ownershipVerified' => true, 'departmentName' => 'Test IT', 'collegeName' => 'CET', 'isActive' => true]);
        $departmentId = new ObjectId((string) $department->getKey());
        $admin = User::create(['fullName' => 'Test Admin', 'roleId' => 1, 'isActive' => true, 'departmentId' => $departmentId]);
        $auditor = User::create(['fullName' => 'Test Auditor', 'roleId' => 2, 'isActive' => true, 'departmentId' => (string) $departmentId, 'assignedDepartmentIds' => [$departmentId]]);
        $other = User::create(['fullName' => 'Other Auditor', 'roleId' => 2, 'isActive' => true, 'departmentId' => $departmentId, 'assignedDepartmentIds' => [$departmentId]]);

        $planId = $this->authorizedPlan($admin, $auditor, $department, 'Test process', 'Test criterion');
        $this->actingAs($admin)->post('/audits', ['planId' => $planId, 'independenceConfirmed' => '1', 'independenceNotes' => 'No own work or declared conflict', 'title' => 'Assignment regression test', 'scope' => 'Test process', 'criteria' => 'Test criterion',
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
        $department = Department::create(['ownershipVerified' => true, 'departmentName' => 'Test IT', 'collegeName' => 'CET', 'isActive' => true]);
        $id = new ObjectId((string) $department->getKey());
        $admin = User::create(['fullName' => 'Reviewer', 'roleId' => 1, 'isActive' => true, 'departmentId' => $id]);
        $auditor = User::create(['fullName' => 'Auditor', 'roleId' => 2, 'isActive' => true, 'departmentId' => $id, 'assignedDepartmentIds' => [$id]]);
        $this->actingAs($auditor)->get('/audits')->assertOk()->assertSee('No Assigned Audits Yet');
        $planId = $this->authorizedPlan($admin, $auditor, $department, 'Process', 'Requirement');
        $data = ['planId' => $planId, 'independenceConfirmed' => '1', 'independenceNotes' => 'No own work or declared conflict', 'title' => 'Workflow test', 'scope' => 'Process', 'criteria' => 'Requirement', 'schedule' => '2026-11-06',
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

    public function test_my_assigned_audits_metrics_filters_actions_and_feedback_are_assignment_scoped(): void
    {
        [$admin, $auditor, $audit] = $this->fixture();
        session()->forget(['errors', '_old_input', 'status']);
        $audit->update(['title' => 'Prepare recorded checklist']);
        foreach (['ready', 'active', 'submitted', 'returned', 'accepted'] as $index => $status) {
            Audit::create(['auditCode' => 'RECORDED-'.($index + 2), 'title' => $status === 'active' ? 'Control.* [sample]' : 'Recorded '.$status,
                'departmentId' => $audit->departmentId, 'departmentName' => $audit->departmentName, 'auditorId' => $auditor->getKey(), 'reviewerId' => $admin->getKey(),
                'status' => $status === 'active' ? 'ready' : $status, 'schedule' => '2026-11-13', 'version' => 1,
                'items' => $status === 'ready' ? [] : [['result' => 'N', 'evidence' => [['method' => 'physical-original', 'notes' => 'PRIVATE EVIDENCE']]]],
                'history' => [['action' => 'return', 'actor' => (string) $admin->getKey(), 'notes' => 'Clarify the verification sample', 'at' => '2026-11-13T01:00:00Z', 'previousItems' => [['remarks' => 'PRIVATE SNAPSHOT']]],
                    ['action' => 'interview', 'actor' => (string) $auditor->getKey(), 'notes' => 'PRIVATE INTERVIEW'],
                    ['action' => 'return', 'actor' => (string) new ObjectId(), 'notes' => 'UNAUTHORIZED FEEDBACK']]]);
        }
        $outsider = User::create(['fullName' => 'Other Assigned Auditor', 'roleId' => 2, 'isActive' => true, 'departmentId' => $audit->departmentId]);
        $private = Audit::create(['auditCode' => 'PRIVATE-REFERENCE', 'title' => 'PRIVATE AUDIT', 'departmentId' => $audit->departmentId,
            'departmentName' => 'PRIVATE UNIT LABEL', 'auditorId' => $outsider->getKey(), 'reviewerId' => $admin->getKey(), 'status' => 'submitted']);
        $this->actingAs($auditor);
        $full = $this->get('/audits')->assertOk()->assertViewHas('summary', ['total' => 6, 'inProgress' => 3, 'awaitingReview' => 1, 'completed' => 1])
            ->assertSee('Prepare Checklist')->assertSee('Open Audit')->assertSee('Continue Audit')->assertSee('View Submission')
            ->assertSee('Review Feedback &amp; Revise', false)->assertSee('View Final Results')->assertSee('Awaiting Audit Team Leader Review')
            ->assertSee('Clarify the verification sample')->assertDontSee('PRIVATE')->assertDontSee('UNAUTHORIZED')
            ->assertSee('Progress shows checklist items evaluated, not compliance percentage.');
        $this->get('/audits/'.$private->getKey())->assertNotFound();
        $ajax = ['X-Requested-With' => 'XMLHttpRequest'];
        $submitted = $this->get('/audits?status=submitted', $ajax)->assertOk()->assertDontSee('<!DOCTYPE html>', false)
            ->assertViewHas('audits', fn ($rows) => $rows->total() === 1)->assertViewHas('summary', fn ($counts) => $counts['total'] === 6);
        $this->get('/audits?status=ready', $ajax)->assertOk()->assertSee('Recorded ready')->assertDontSee('Control.* [sample]');
        $this->get('/audits?status=in_progress', $ajax)->assertOk()->assertSee('Control.* [sample]')->assertDontSee('Recorded ready');
        $this->get('/audits?search='.urlencode('.* [sample]'), $ajax)->assertOk()->assertViewHas('audits', fn ($rows) => $rows->total() === 1);
        $this->get('/audits?search=recorded&status=submitted&departmentId='.$audit->departmentId, $ajax)->assertOk()
            ->assertViewHas('audits', fn ($rows) => $rows->total() === 1);
        $none = $this->get('/audits?search=does-not-exist', $ajax)->assertOk()->assertSee('No matching audits')->assertDontSee('No Assigned Audits Yet');
        $this->get('/audits?departmentId='.(string) new ObjectId(), $ajax)->assertOk()->assertViewHas('audits', fn ($rows) => $rows->total() === 0);
        foreach (['status=invalid', 'search[]=invalid', 'departmentId=invalid', 'page=-1'] as $query) { $this->getJson('/audits?'.$query)->assertUnprocessable(); }
        $auditor->update(['assignedDepartmentIds' => []]);
        $empty = $this->get('/audits')->assertOk()->assertSee('No Assigned Audits Yet')
            ->assertViewHas('summary', ['total' => 0, 'inProgress' => 0, 'awaitingReview' => 0, 'completed' => 0]);
        if (getenv('WRITE_MY_AUDITS_UI_FIXTURES') === '1') {
            $dir = storage_path('framework/testing/my-audits-ui');
            \Illuminate\Support\Facades\File::ensureDirectoryExists($dir);
            foreach (['full' => $full, 'submitted' => $submitted, 'none' => $none, 'empty' => $empty] as $key => $response) { file_put_contents($dir.'/'.$key.'.html', $response->getContent()); }
        }
    }

    public function test_workspace_sections_readiness_and_phase_actions_preserve_real_records(): void
    {
        [$admin, $auditor, $audit] = $this->fixture();
        $this->actingAs($auditor);
        $this->saveAction($audit, 'add', ['question' => "Does the team's process follow standards?", 'reference' => 'ISO 9001:2015 9.2'])->assertSessionHasNoErrors();
        $this->saveAction($audit, 'add', ['question' => 'Are controlled records verified?', 'reference' => 'ISO 9001:2015 7.5'])->assertSessionHasNoErrors();
        $person = User::create(['fullName' => 'Workspace Auditee', 'roleId' => 3, 'isActive' => true, 'departmentId' => new ObjectId((string) $audit->departmentId)]);
        $this->saveAction($audit, 'interview', ['auditeeId' => (string) $person->getKey(), 'process' => 'Records control', 'topics' => 'Verification process', 'responses' => 'Originals are verified', 'notes' => 'Internal factual interview note'])->assertRedirect()->assertSessionHasNoErrors();
        $this->post('/audits/'.$audit->getKey().'/requests', ['version' => $audit->fresh()->version, 'itemId' => $audit->fresh()->items[1]['id'], 'auditeeId' => (string) $person->getKey(), 'description' => 'Please make the original available', 'referenceStandard' => 'ISO9001-9.2'])->assertRedirect()->assertSessionHasNoErrors();
        $draft = $this->get('/audits/'.$audit->getKey())->assertOk()->assertHeader('Cache-Control', 'no-store, private')
            ->assertSee('Audit Checklist')->assertSee('Auditee Interviews')->assertSee('Review &amp; Submit', false)
            ->assertSee('workspace-question-dialog')->assertSee('Mark checklist ready')->assertDontSee('name="result"', false)
            ->assertSee('Workspace Auditee')->assertSee('Internal factual interview note')->assertSee('Please make the original available')
            ->assertViewHas('readiness', fn ($value) => !$value['eligible'] && $value['decisions'] === 2);
        $this->saveAction($audit, 'ready')->assertSessionHasNoErrors();
        $this->saveAction($audit, 'evidence', ['item' => 0, 'method' => 'physical-original', 'notes' => 'Original reviewed', 'signature' => 'verified'])->assertSessionHasNoErrors();
        $this->saveAction($audit, 'evaluate', ['item' => 0, 'result' => 'N', 'remarks' => 'Required record missing', 'finding' => 'Missing required record'])->assertSessionHasNoErrors();
        $ready = $this->get('/audits/'.$audit->getKey())->assertOk()->assertSee('Save Evaluation')->assertSee('Original reviewed')->assertSee('Auditor')
            ->assertDontSee('workspace-question-dialog')->assertDontSee('Edit question')
            ->assertViewHas('readiness', fn ($value) => !$value['eligible'] && array_keys($value['incomplete']) === [1]);
        $this->saveAction($audit, 'submit')->assertSessionHasErrors('checklist');
        $audit->refresh()->update(['status' => 'submitted']);
        $locked = $this->get('/audits/'.$audit->getKey())->assertOk()->assertSee('Results locked')->assertDontSee('Save Evaluation')
            ->assertDontSee('workspace-evidence-dialog')->assertDontSee('workspace-interview-dialog');
        if (getenv('WRITE_WORKSPACE_UI_FIXTURES') === '1') {
            $dir = storage_path('framework/testing/workspace-ui');
            \Illuminate\Support\Facades\File::ensureDirectoryExists($dir);
            foreach (['draft' => $draft, 'ready' => $ready, 'submitted' => $locked] as $phase => $response) { file_put_contents($dir.'/'.$phase.'.html', $response->getContent()); }
        }
    }

    public function test_assignment_scope_conflicts_and_plan_authority_are_enforced_on_the_server(): void
    {
        [$admin, $auditor, $audit] = $this->fixture();
        $data = ['planId' => (string) $audit->planId, 'title' => 'Additional assignment', 'departmentId' => (string) $audit->departmentId,
            'auditorId' => (string) $auditor->getKey(), 'schedule' => '2026-11-07', 'scope' => 'Forged scope', 'criteria' => 'Forged criteria',
            'independenceConfirmed' => '1', 'independenceNotes' => 'Procedure checked'];
        $this->actingAs($admin);
        $this->postJson('/audits', array_replace($data, ['planId' => (string) new ObjectId()]))->assertUnprocessable()->assertJsonValidationErrors('planId');
        $this->postJson('/audits', array_replace($data, ['schedule' => '2031-01-01']))->assertUnprocessable()->assertJsonValidationErrors('schedule');
        $this->postJson('/audits', array_replace($data, ['independenceConfirmed' => '0']))->assertUnprocessable();
        $homeId = $auditor->departmentId;
        $auditor->update(['departmentId' => $audit->departmentId]);
        $this->postJson('/audits', $data)->assertUnprocessable()->assertJsonValidationErrors('auditorId');
        $auditor->forceFill(['departmentId' => $homeId, 'conflictDepartmentIds' => [$audit->departmentId]])->save();
        $this->postJson('/audits', $data)->assertUnprocessable()->assertJsonValidationErrors('auditorId');
        $auditor->forceFill(['conflictDepartmentIds' => []])->save();
        $office = Department::create(['collegeName' => 'CET', 'departmentName' => 'Office of the Dean', 'unitType' => 'college_office', 'ownershipVerified' => true, 'isActive' => true]);
        $this->postJson('/audits', array_replace($data, ['departmentId' => (string) $office->getKey()]))->assertUnprocessable()->assertJsonValidationErrors('departmentId');
        $plan = \App\Models\ArchiveDocument::findOrFail($audit->planId);
        $authorization = $plan->assignmentAuthorization;
        $authorization['unitIds'][] = $office->getKey();
        $approval = $plan->auditPlan;
        $approval['header']['unitIds'][] = (string) $office->getKey();
        $plan->forceFill(['assignmentAuthorization' => $authorization, 'auditPlan' => $approval])->save();
        $auditor->update(['assignedDepartmentIds' => [$audit->departmentId, $office->getKey()]]);
        $this->post('/audits', array_replace($data, ['departmentId' => (string) $office->getKey()]))->assertRedirect()->assertSessionHasNoErrors();
        $created = Audit::where('departmentId', new ObjectId((string) $office->getKey()))->firstOrFail();
        $this->assertSame('Process', $created->scope);
        $this->assertSame('Requirement', $created->criteria);
        $this->assertInstanceOf(ObjectId::class, $created->getRawOriginal('planId'));
        $this->actingAs($auditor)->get('/audits')->assertSee('Additional assignment');
        $authorization['status'] = 'revoked';
        $plan->forceFill(['assignmentAuthorization' => $authorization])->save();
        $this->actingAs($admin)->postJson('/audits', $data)->assertUnprocessable()->assertJsonValidationErrors('planId');
        $this->get('/audits')->assertOk()->assertSee('No audit plans have confirmed assignment authorization');
    }

    public function test_assignment_changes_are_versioned_notify_both_auditors_and_lock_after_submission(): void
    {
        [$admin, $auditor, $audit] = $this->fixture();
        $replacement = User::create(['fullName' => 'Replacement Auditor', 'roleId' => 2, 'isActive' => true,
            'departmentId' => $auditor->departmentId, 'assignedDepartmentIds' => [$audit->departmentId]]);
        $data = ['version' => $audit->version, 'planId' => (string) $audit->planId, 'departmentId' => (string) $audit->departmentId,
            'auditorId' => (string) $replacement->getKey(), 'title' => $audit->title, 'schedule' => '2026-11-08',
            'independenceConfirmed' => '1', 'independenceNotes' => 'Independence checked', 'changeReason' => 'Availability changed'];
        $url = '/audits/'.$audit->getKey().'/assignment';
        $this->actingAs($auditor)->postJson($url, $data)->assertForbidden();
        $this->actingAs($admin)->post($url, $data)->assertRedirect()->assertSessionHasNoErrors();
        $this->postJson($url, $data)->assertConflict();
        $this->assertSame(2, \App\Models\Notification::where('type', 'audit_assignment_changed')->count());
        $this->assertSame((string) $auditor->getKey(), (string) $audit->fresh()->history[1]['previousAssignment']['auditorId']);
        $this->actingAs($auditor)->get('/audits/'.$audit->getKey())->assertNotFound();
        $this->actingAs($replacement)->get('/audits/'.$audit->getKey())->assertOk();
        $audit->refresh();
        $this->saveAction($audit, 'add', ['question' => 'Question'])->assertRedirect();
        $this->saveAction($audit, 'ready')->assertRedirect();
        $this->saveAction($audit, 'evidence', ['item' => 0, 'method' => 'digital', 'notes' => 'Reviewed'])->assertRedirect();
        $this->saveAction($audit, 'evaluate', ['item' => 0, 'result' => 'C', 'remarks' => 'Meets criterion'])->assertRedirect();
        $this->saveAction($audit, 'submit')->assertRedirect();
        $this->actingAs($admin)->postJson($url, array_replace($data, ['version' => $audit->fresh()->version]))->assertForbidden();
        $this->saveAction($audit, 'return', ['reviewNotes' => 'Clarify the finding'])->assertRedirect()->assertSessionHasNoErrors();
        $this->postJson($url, array_replace($data, ['version' => $audit->fresh()->version]))->assertForbidden();
    }

    public function test_admin_assignment_and_review_views_are_separate_and_review_tabs_filter_records(): void
    {
        [$admin, $auditor, $audit] = $this->fixture();
        session()->forget(['errors', '_old_input']);
        $this->actingAs($admin);
        $response = $this->get('/audits')->assertOk()->assertViewIs('admin.audit-assignments')->assertSee('New Audit Assignment')
            ->assertSee('Assignment Register')->assertDontSee('data-atl-review aria-haspopup', false);
        $this->assertSame(1, substr_count($response->getContent(), 'Assign auditors to departments and offices based on authorized audit plans.'));
        $this->get('/audits?view=reviews')->assertOk()->assertViewIs('admin.audit-reviews')->assertSee('Awaiting Review')->assertDontSee('Workflow test')->assertDontSee('New Audit Assignment');
        $this->get('/audits?view=reviews&status=draft')->assertUnprocessable();
        $audit->update(['status' => 'submitted']);
        $review = $this->get('/audits?view=reviews')->assertOk()->assertSee('Workflow test');
        $this->get('/audits?view=reviews&status=returned')->assertOk()->assertDontSee('Workflow test');
        $detail = $this->get('/audits/'.$audit->getKey().'?review=1')->assertOk()->assertSee('Accept Results')->assertDontSee('name="result"', false);
        if (getenv('AUDIT_ASSIGNMENT_BROWSER_FIXTURES') === '1') {
            $dir = storage_path('framework/testing/assignment-ui');
            \Illuminate\Support\Facades\File::ensureDirectoryExists($dir);
            file_put_contents($dir.'/assignments.html', $response->getContent());
            file_put_contents($dir.'/reviews.html', $review->getContent());
            file_put_contents($dir.'/review.html', $detail->getContent());
        }
        $this->saveAction($audit, 'evaluate', ['item' => 0, 'result' => 'N', 'remarks' => 'Overwrite'])->assertForbidden();
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
        $this->actingAs($admin)->post('/audits', ['planId' => (string) $audit->planId, 'independenceConfirmed' => '1', 'independenceNotes' => 'Checked', 'title' => 'Outside', 'scope' => 'Test', 'criteria' => 'Test', 'schedule' => '2026-11-06',
            'departmentId' => (string) $outside->getKey(), 'auditorId' => (string) $auditor->getKey()])->assertRedirect()->assertSessionHasErrors('departmentId');
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
        $archive = \App\Models\ArchiveDocument::create(['ownershipVerified' => true, 'fileCode' => 'TEST-PRIVATE', 'departmentId' => $audit->departmentId,
            'uploadedByUserId' => $auditee->getKey(), 'originalName' => 'Private.pdf']);
        $this->actingAs($auditor)->getJson('/api/v1/compliance-checks')->assertOk()->assertJsonPath('meta.total', 1);
        $this->getJson('/api/v1/documents')->assertOk()->assertJsonPath('meta.total', 0);
        $check->update(['linkedArchiveIds' => [$archive->getKey()]]);
        $this->getJson('/api/v1/documents')->assertOk()->assertJsonPath('meta.total', 1);
        $this->patchJson('/api/v1/compliance-checks/'.$check->getKey().'/status', ['correctiveStatus' => 'closed'])->assertUnprocessable();
        $this->patchJson('/api/v1/compliance-checks/'.$check->getKey().'/status', ['correctiveStatus' => 'closed', 'remarks' => 'Corrective action verified'])->assertOk();
        $this->assertSame('closed', $check->fresh()->correctiveStatus);
        $this->saveAction($audit, 'interview', ['auditeeId' => (string) $auditee->getKey(), 'process' => 'Test process', 'topics' => 'Question', 'responses' => 'Factual response'])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame('Factual response', $audit->fresh()->interviews[0]['responses']);
        $this->actingAs($other)->getJson('/api/v1/compliance-checks/'.$check->getKey())->assertNotFound();
        $this->getJson('/api/v1/documents/'.$archive->getKey().'/download')->assertNotFound();
        $this->saveAction($audit, 'interview', ['auditeeId' => (string) $auditee->getKey(), 'process' => 'Test', 'topics' => 'Q', 'responses' => 'R'])->assertNotFound();
        $this->actingAs($admin)->get('/audits')->assertOk();
        $this->actingAs($auditor)->get('/auditor/checks')->assertRedirect('/auditor/historical-records');
        $this->get('/auditor/historical-records')->assertOk()->assertDontSee('New Compliance Check');
        $this->get('/auditor/documents')->assertOk();
        $this->get('/auditor/corrective-actions')->assertOk();
        $this->get('/auditor/notifications')->assertOk();
    }

    public function test_car_timeline_reuses_status_records_preserves_reopening_and_enforces_closure_permissions(): void
    {
        [$admin, $auditor, $audit] = $this->fixture();
        $person = User::create(['fullName' => 'CAR Recipient', 'roleId' => 3, 'isActive' => true, 'departmentId' => $audit->departmentId]);
        $other = User::create(['fullName' => 'Other Recipient', 'roleId' => 3, 'isActive' => true, 'departmentId' => $audit->departmentId]);
        $created = $this->actingAs($auditor)->postJson('/api/v1/compliance-checks', ['departmentId' => (string) $audit->departmentId,
            'auditeeId' => (string) $person->getKey(), 'referenceStandard' => 'ISO9001-9.2', 'result' => 'non_compliant'])->assertCreated();
        $id = $created->json('data._id');
        $statusUrl = '/api/v1/compliance-checks/'.$id.'/status';
        $this->patchJson($statusUrl, ['correctiveStatus' => 'closed', 'remarks' => 'Skip review'])->assertUnprocessable();
        $this->actingAs($person)->patchJson($statusUrl, ['correctiveStatus' => 'closed', 'remarks' => 'Self closure'])->assertForbidden();
        $this->actingAs($admin)->patchJson($statusUrl, ['correctiveStatus' => 'in_progress'])->assertForbidden();
        $this->travel(1)->minutes();
        $this->actingAs($auditor)->patchJson($statusUrl, ['correctiveStatus' => 'in_progress'])->assertOk();
        $respond = function () use ($person, $id) {
            $this->actingAs($person)->postJson('/auditee/finding-details/'.$id.'/response', [
                'submissionKey' => (string) \Illuminate\Support\Str::uuid(), 'method' => 'digital-reference', 'notes' => 'Authorized corrective evidence'])->assertOk();
        };
        $respond();
        $this->assertSame('in_progress', \App\Models\ComplianceCheck::findOrFail($id)->correctiveStatus);
        $this->travel(1)->minutes();
        $this->actingAs($auditor)->patchJson($statusUrl, ['correctiveStatus' => 'closed', 'remarks' => 'Verified corrective evidence'])->assertOk();
        $this->actingAs($person)->postJson('/auditee/finding-details/'.$id.'/response', [
            'submissionKey' => (string) \Illuminate\Support\Str::uuid(), 'method' => 'digital-reference', 'notes' => 'Attempt after closure'])->assertForbidden();
        $this->travel(1)->minutes();
        $this->actingAs($auditor)->patchJson($statusUrl, ['correctiveStatus' => 'open', 'remarks' => 'Returned for further corrective evidence'])->assertOk();
        $respond();
        $respond();
        $check = \App\Models\ComplianceCheck::findOrFail($id);
        $this->assertSame('open', $check->correctiveStatus);
        $this->assertNull($check->closedAt);
        $this->assertCount(4, $check->statusHistory);
        $this->assertCount(3, $check->evidenceResponses);
        $notificationCount = \App\Models\Notification::count();
        $detailUrl = '/auditee/finding-details/'.$id;
        $response = $this->get($detailUrl, ['X-Requested-With' => 'XMLHttpRequest'])->assertOk()
            ->assertSee('Status Timeline')->assertSee('Corrective evidence resubmitted')->assertSee('Responsible role: Auditor')
            ->assertSee('Responsible role: Auditee')->assertSee('Awaiting auditor verification')->assertDontSee('This corrective action is closed.');
        $timeline = collect($response->viewData('carTimeline'));
        $this->assertSame(['open'], $timeline->where('state', 'current')->pluck('status')->all());
        $this->assertSame('completed', $timeline->where('kind', 'status')->firstWhere('status', 'closed')['state']);
        $this->assertSame($notificationCount, \App\Models\Notification::count());
        $this->actingAs($other)->get($detailUrl, ['X-Requested-With' => 'XMLHttpRequest'])->assertNotFound();
        $this->patchJson($statusUrl, ['correctiveStatus' => 'closed', 'remarks' => 'Unauthorized'])->assertForbidden();
    }

    public function test_evidence_cards_count_requests_filter_and_guard_popup_access(): void
    {
        [$admin, $auditor, $audit] = $this->fixture();
        $person = User::create(['fullName' => 'Card Recipient', 'roleId' => 3, 'isActive' => true, 'departmentId' => $audit->departmentId]);
        $other = User::create(['fullName' => 'Other Recipient', 'roleId' => 3, 'isActive' => true, 'departmentId' => $audit->departmentId]);
        $entries = [];
        foreach (['requested', 'needs_information', 'response_submitted', 'reviewed'] as $number => $status) {
            $entries[] = ['id' => (string) \Illuminate\Support\Str::uuid(), 'auditeeId' => (string) $person->getKey(),
                'title' => 'Permitted request '.$number, 'description' => 'Evidence description '.$number,
                'status' => $status, 'referenceStandard' => 'ISO9001-9.2', 'allowUpload' => false, 'responses' => [],
                'dueDate' => $number === 0 ? '2026-12-20' : null, 'createdAt' => now()->toIso8601String()];
        }
        $private = array_merge($entries[0], ['id' => (string) \Illuminate\Support\Str::uuid(), 'auditeeId' => (string) $other->getKey(), 'description' => 'PRIVATE REQUEST TEXT']);
        $audit->update(['evidenceRequests' => [...$entries, $private], 'reviewNotes' => 'PRIVATE AUDITOR NOTES',
            'items' => [['question' => 'PRIVATE CHECKLIST DRAFT']]]);
        $ajax = ['X-Requested-With' => 'XMLHttpRequest'];
        $this->actingAs($person)->get('/auditee/evidence-requests')->assertOk()
            ->assertViewHas('summary', ['active' => 3, 'awaiting' => 2, 'submitted' => 1, 'completed' => 1])
            ->assertViewHas('requests', fn ($requests) => $requests->total() === 4)
            ->assertSee('Dec 20, 2026')->assertSee('Awaiting Response')->assertSee('Submitted')->assertSee('Completed')
            ->assertDontSee('PRIVATE')->assertSee('data-evidence-open', false)->assertSee('id="evidence-request-modal"', false);
        $this->get('/auditee/evidence-requests?requestStatus=requested')->assertOk()->assertViewHas('requests', fn ($requests) => $requests->total() === 2);
        $this->get('/auditee/evidence-requests?requestStatus=reviewed')->assertOk()->assertViewHas('requests', fn ($requests) => $requests->total() === 1);
        foreach ([$audit->auditCode, 'Evidence description 0', 'Auditor', 'Information Technology'] as $term) {
            $this->get('/auditee/evidence-requests?search='.urlencode($term))->assertOk()->assertSee('Permitted request 0')->assertDontSee('PRIVATE');
        }
        $this->get('/auditee/evidence-requests?search=no-match')->assertOk()->assertSee('No matching requests')
            ->assertViewHas('summary', fn ($summary) => $summary['active'] === 3);
        $detailUrl = '/auditee/evidence-requests?auditId='.$audit->getKey().'&requestId='.$entries[0]['id'];
        $this->get($detailUrl, $ajax)->assertOk()->assertViewIs('auditee.evidence-request-detail')->assertDontSee('<html', false)
            ->assertSee('Evidence requirements')->assertSee('Submission history')->assertSee('Digital document reference')
            ->assertSee('Physical original')->assertSee('File copying is not authorized')->assertDontSee('type="file"', false)->assertDontSee('PRIVATE');
        $this->get('/auditee/evidence-requests?requestId='.$private['id'], $ajax)->assertNotFound();
        $this->actingAs($other)->get($detailUrl, $ajax)->assertNotFound();
        $this->actingAs($auditor)->get($detailUrl, $ajax)->assertForbidden();
        $this->actingAs($person);
        $audit->update(['status' => 'submitted']);
        $this->get('/auditee/evidence-requests?requestStatus=under_review')->assertOk()->assertSee('Under Review')
            ->assertViewHas('requests', fn ($requests) => $requests->total() === 1);
        $this->get($detailUrl, $ajax)->assertOk()->assertDontSee('name="submissionKey"', false)->assertSee('read-only');
        $this->postJson('/auditee/evidence-requests/'.$audit->getKey().'/'.$entries[0]['id'], [
            'version' => $audit->version, 'submissionKey' => (string) \Illuminate\Support\Str::uuid(), 'action' => 'submit',
            'method' => 'digital-reference', 'notes' => 'Locked attempt'])->assertForbidden();
        $audit->update(['departmentId' => new ObjectId()]);
        $this->get($detailUrl, $ajax)->assertNotFound();
        $this->get('/auditee/evidence-requests')->assertOk()->assertViewHas('summary', ['active' => 0, 'awaiting' => 0, 'submitted' => 0, 'completed' => 0]);
        $person->update(['isActive' => false]);
        $this->actingAs($person)->get('/auditee/evidence-requests')->assertForbidden();
    }

    public function test_evidence_popup_json_drafts_submission_validation_and_pagination(): void
    {
        [$admin, $auditor, $audit] = $this->fixture();
        $person = User::create(['fullName' => 'Popup Recipient', 'roleId' => 3, 'isActive' => true, 'departmentId' => $audit->departmentId]);
        $entries = collect(range(1, 13))->map(fn ($i) => ['id' => (string) \Illuminate\Support\Str::uuid(),
            'auditeeId' => (string) $person->getKey(), 'description' => 'Request '.$i, 'status' => 'requested',
            'referenceStandard' => 'ISO9001-9.2', 'responses' => [], 'allowUpload' => false])->all();
        $audit->update(['evidenceRequests' => $entries]);
        $this->actingAs($person)->get('/auditee/evidence-requests?page=2')->assertOk()
            ->assertViewHas('requests', fn ($requests) => $requests->total() === 13 && $requests->count() === 1);
        $url = '/auditee/evidence-requests/'.$audit->getKey().'/'.$entries[0]['id'];
        $data = ['version' => $audit->version, 'submissionKey' => (string) \Illuminate\Support\Str::uuid(),
            'method' => 'digital-reference', 'notes' => 'Permitted response', 'action' => 'draft'];
        $this->postJson($url, $data)->assertOk()->assertJsonPath('message', 'Draft saved. You can return to finish your response.');
        $this->assertSame('requested', $audit->fresh()->evidenceRequests[0]['status']);
        $detailUrl = '/auditee/evidence-requests?requestId='.$entries[0]['id'];
        $this->get($detailUrl, ['X-Requested-With' => 'XMLHttpRequest'])->assertOk()->assertSee('Permitted response');
        $this->postJson($url, array_merge($data, ['action' => 'submit']))->assertConflict();
        $data['version'] = $audit->fresh()->version;
        $this->postJson($url, array_merge($data, ['method' => 'file']))->assertForbidden();
        $this->postJson($url, array_merge($data, ['notes' => '']))->assertUnprocessable()->assertJsonValidationErrors('notes');
        $this->postJson($url, array_merge($data, ['action' => 'submit']))->assertOk()->assertJsonPath('message', 'Evidence submitted for auditor review.');
        $this->postJson($url, array_merge($data, ['action' => 'submit']))->assertOk()->assertJsonPath('message', 'Response already saved.');
        $this->assertCount(1, $audit->fresh()->evidenceRequests[0]['responses']);
        $this->get($detailUrl, ['X-Requested-With' => 'XMLHttpRequest'])->assertOk()->assertSee('Permitted response')->assertDontSee('name="submissionKey"', false);
        $this->getJson('/auditee/evidence-requests?search[]=invalid')->assertUnprocessable();
        $this->getJson('/auditee/evidence-requests?requestId=invalid')->assertUnprocessable();
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
        $this->get('/auditee/evidence-requests?requestId='.$entry['id'], ['X-Requested-With' => 'XMLHttpRequest'])->assertOk()->assertSee('Factual response');
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
        $this->actingAs($person)->get('/auditee/evidence-requests?requestId='.$entry['id'], ['X-Requested-With' => 'XMLHttpRequest'])->assertOk()->assertSee('Original signature inspected')->assertDontSee('CONFIDENTIAL');
        $this->get('/auditee/my-findings')->assertOk()->assertDontSee('Requirement reviewed');
        $publishUrl = '/audits/'.$audit->getKey().'/requests/'.$entry['id'].'/publish';
        $this->post($publishUrl, ['version' => $audit->fresh()->version, 'publicSummary' => 'Not authorized'])->assertForbidden();
        $itemsBeforeSharing = $audit->fresh()->items;
        $this->actingAs($auditor)->post($publishUrl, ['version' => $audit->fresh()->version, 'publicSummary' => 'Permitted accepted result'])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame($itemsBeforeSharing, $audit->fresh()->items);
        $this->actingAs($person)->get('/auditee/my-findings')->assertOk()->assertSee('Permitted accepted result')->assertDontSee('CONFIDENTIAL');
        $this->actingAs($other)->get('/auditee/my-findings')->assertOk()->assertDontSee('Permitted accepted result');
        $this->actingAs($person);
        foreach (['dashboard','my-findings','upload','documents','notifications','help'] as $page) { $this->get('/auditee/'.$page)->assertOk(); }
        $this->get('/profile')->assertRedirect('/auditee/profile');
        $this->get('/auditee/profile')->assertOk();
        $this->getJson('/api/v1/notifications?unread=1&type=evidence_requested')->assertOk()->assertJsonPath('meta.total', 1);
    }

    public function test_corrective_references_and_encrypted_uploads_do_not_close_cars(): void
    {
        [$admin, $auditor, $audit] = $this->fixture();
        $person = User::create(['fullName' => 'Auditee', 'roleId' => 3, 'isActive' => true, 'departmentId' => (string) $audit->departmentId]);
        $other = User::create(['fullName' => 'Other', 'roleId' => 3, 'isActive' => true, 'departmentId' => (string) $audit->departmentId]);
        $check = \App\Models\ComplianceCheck::create(['checkCode' => 'TEST-EVIDENCE', 'auditorId' => $auditor->getKey(), 'auditeeId' => $person->getKey(),
            'departmentId' => $audit->departmentId, 'referenceStandard' => 'ISO9001-9.2', 'result' => 'non_compliant', 'correctiveStatus' => 'open', 'linkedArchiveIds' => []]);
        $this->actingAs($person)->get('/auditee/finding-details/'.$check->getKey())
            ->assertRedirect('/auditee/my-findings?detail='.$check->getKey());
        $key = (string) \Illuminate\Support\Str::uuid();
        $this->post('/auditee/finding-details/'.$check->getKey().'/response', ['submissionKey' => $key, 'method' => 'physical-original', 'notes' => 'Ready for inspection', 'availability' => 'available'])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame('open', $check->fresh()->correctiveStatus);
        $this->assertSame('non_compliant', $check->fresh()->result);
        $this->assertArrayNotHasKey('signature', $check->fresh()->evidenceResponses[0]);
        $plaintext = "%PDF-1.4\nAuthorized test evidence";
        $file = \Illuminate\Http\UploadedFile::fake()->createWithContent('evidence.pdf', $plaintext);
        $upload = $this->post('/api/v1/documents/upload', ['file' => $file, 'checkId' => (string) $check->getKey(), 'referenceStandard' => 'ISO9001-9.2'], ['Accept' => 'application/json'])
            ->assertCreated()->assertJsonMissingPath('data.encryptedPath');
        $id = $upload->json('data._id');
        $archive = \App\Models\ArchiveDocument::findOrFail($id);
        $ciphertext = file_get_contents($this->testStorage.DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $archive->encryptedPath));
        $this->assertNotSame($plaintext, $ciphertext);
        $this->assertSame(hash('sha256', $plaintext), $archive->checksum);
        $this->get('/api/v1/documents/'.$id.'/download')->assertOk()->assertContent($plaintext);
        $this->getJson('/api/v1/documents')->assertOk()->assertJsonPath('meta.total', 1);
        $this->assertCount(1, $check->fresh()->linkedArchiveIds);
        $this->assertSame('open', $check->fresh()->correctiveStatus);
        $this->post('/api/v1/documents/upload', ['file' => \Illuminate\Http\UploadedFile::fake()->createWithContent('bad.docx', 'PK-not-a-document'), 'checkId' => (string) $check->getKey(), 'referenceStandard' => 'ISO9001-9.2'], ['Accept' => 'application/json'])->assertUnprocessable();
        $this->post('/api/v1/documents/upload', ['file' => \Illuminate\Http\UploadedFile::fake()->create('large.pdf', 20481), 'checkId' => (string) $check->getKey(), 'referenceStandard' => 'ISO9001-9.2'], ['Accept' => 'application/json'])->assertUnprocessable();
        $this->actingAs($other)->get('/auditee/finding-details/'.$check->getKey())->assertNotFound();
        $this->getJson('/api/v1/documents/'.$id.'/download')->assertNotFound();
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
        $this->get('/auditee/finding-details/'.$open->getKey())->assertRedirect('/auditee/my-findings?detail='.$open->getKey());
        $this->get('/auditee/finding-details/'.$open->getKey(), ['X-Requested-With' => 'XMLHttpRequest'])->assertOk()
            ->assertViewIs('auditee.result-detail-content')->assertDontSee('<html', false)
            ->assertSee('Released explanation')->assertSee('Check item / requirement')->assertSee('Provide Corrective Evidence')
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
        $archiveCountBeforeConflict = \App\Models\ArchiveDocument::count();
        $this->actingAs($person)->postJson('/auditee/evidence-requests/'.$audit->getKey().'/'.$entry['id'], ['version' => $audit->fresh()->version - 1,
            'submissionKey' => (string) \Illuminate\Support\Str::uuid(), 'action' => 'submit', 'method' => 'file', 'notes' => 'Keep this file response', 'copyAuthorized' => '1',
            'file' => \Illuminate\Http\UploadedFile::fake()->createWithContent('stale.pdf', "%PDF-1.4\nStale")])->assertConflict();
        $this->assertSame($archiveCountBeforeConflict, \App\Models\ArchiveDocument::count(), 'Stale submissions are rejected before storing a file.');
        $this->actingAs($person)->post('/auditee/evidence-requests/'.$audit->getKey().'/'.$entry['id'], ['version' => $audit->fresh()->version,
            'submissionKey' => (string) \Illuminate\Support\Str::uuid(), 'action' => 'draft', 'method' => 'file', 'notes' => 'Permitted sample', 'copyAuthorized' => '1',
            'file' => \Illuminate\Http\UploadedFile::fake()->createWithContent('test.pdf', "%PDF-1.4\nSample")])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertNotEmpty($audit->fresh()->evidenceRequests[0]['draft']['archiveId']);
        $this->post('/auditee/evidence-requests/'.$audit->getKey().'/'.$entry['id'], ['version' => $audit->fresh()->version,
            'submissionKey' => (string) \Illuminate\Support\Str::uuid(), 'action' => 'submit', 'method' => 'file', 'notes' => 'Submit saved file', 'copyAuthorized' => '1'])
            ->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame(1, \App\Models\ArchiveDocument::where('recordType', '!=', 'physical_reference')->count());
        $this->assertNotEmpty($audit->fresh()->evidenceRequests[0]['responses'][0]['archiveId']);
        $detailUrl = '/auditee/evidence-requests?requestId='.$entry['id'];
        $archiveId = $audit->fresh()->evidenceRequests[0]['responses'][0]['archiveId'];
        $this->get($detailUrl, ['X-Requested-With' => 'XMLHttpRequest'])->assertOk()
            ->assertSee('/api/v1/documents/'.$archiveId.'/download')->assertDontSee('encryptedPath');
        // Even a stored link cannot expose an archive after its ownership changes.
        $archive = \App\Models\ArchiveDocument::findOrFail($archiveId);
        $archive->update(['uploadedByUserId' => $auditor->getKey()]);
        $this->get($detailUrl, ['X-Requested-With' => 'XMLHttpRequest'])->assertOk()
            ->assertDontSee('/api/v1/documents/'.$archiveId.'/download');
        $this->getJson('/api/v1/documents/'.$archiveId.'/download')->assertNotFound();
        $this->assertNull($audit->fresh()->items[0]['result']);
        $this->assertSame(0, \App\Models\ComplianceCheck::count());
    }

    public function test_stale_evidence_request_returns_to_the_form_with_entries_instead_of_an_error_page(): void
    {
        [$admin, $auditor, $audit] = $this->fixture();
        $person = User::create(['fullName' => 'Conflict Recipient', 'roleId' => 3, 'isActive' => true, 'departmentId' => $audit->departmentId]);
        $this->actingAs($auditor);
        $this->saveAction($audit, 'add', ['question' => 'Inspect the original record'])->assertRedirect()->assertSessionHasNoErrors();
        $workspace = '/audits/'.$audit->getKey();
        $data = ['version' => 1, 'workspaceAction' => 'request', 'itemId' => $audit->fresh()->items[0]['id'], 'auditeeId' => (string) $person->getKey(),
            'description' => 'Keep this authorized upload request', 'allowUpload' => '1', 'referenceStandard' => 'ISO9001-9.2'];
        $this->from($workspace)->post($workspace.'/requests', $data)->assertRedirect($workspace)->assertSessionHasErrors('version')
            ->assertSessionHasInput('description', $data['description'])->assertSessionHasInput('allowUpload', '1');
        $this->assertEmpty($audit->fresh()->evidenceRequests ?? []);
        $this->get($workspace)->assertOk()->assertSee('data-recovery="request"', false)->assertSee($data['description'])
            ->assertSee('value="1" checked', false);
        $this->postJson($workspace.'/requests', $data)->assertConflict();
        $data['version'] = $audit->fresh()->version;
        $this->post($workspace.'/requests', $data)->assertRedirect()->assertSessionHasNoErrors();
        $this->assertCount(1, $audit->fresh()->evidenceRequests);
        $this->assertTrue((bool) $audit->fresh()->evidenceRequests[0]['allowUpload']);
    }
}
