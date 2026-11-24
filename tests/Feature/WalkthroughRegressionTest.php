<?php

namespace Tests\Feature;

use App\Models\{ArchiveDocument, Audit, ComplianceCheck, Department, Role, User};
use App\Services\{AnnualAuditReport, AuditChecklist};
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class WalkthroughRegressionTest extends TestCase
{
    private ?string $database = null;
    private Department $unit;
    private User $atl;
    private User $auditor;
    private User $auditee;

    protected function setUp(): void
    {
        parent::setUp();
        if (getenv('RUN_MONGODB_AUDIT_TESTS') !== '1') { $this->markTestSkipped('Isolated MongoDB test required.'); }
        $this->database = 'ct_walkthrough_test_'.bin2hex(random_bytes(6));
        config(['database.connections.mongodb.database' => $this->database]); DB::purge('mongodb');
        $this->assertSame($this->database, DB::connection('mongodb')->getMongoDB()->getDatabaseName());
        $this->withoutVite();
        $this->unit = Department::create(['departmentName' => 'Information Technology', 'departmentCode' => 'CET-IT',
            'collegeName' => 'CET', 'unitType' => 'academic_department', 'ownershipVerified' => true, 'isActive' => true]);
        $this->atl = $this->user(1); $this->auditor = $this->user(2); $this->auditee = $this->user(3);
        Role::create(['roleId' => 2, 'permissions' => ['cars.verify', 'cars.close']]);
    }

    protected function tearDown(): void
    {
        if ($this->database) {
            $db = DB::connection('mongodb')->getMongoDB();
            if ($db->getDatabaseName() === $this->database && str_starts_with($this->database, 'ct_walkthrough_test_')) { $db->drop(); }
            DB::purge('mongodb');
        }
        parent::tearDown();
    }

    private function user(int $role): User
    {
        return User::create(['fullName' => 'Walkthrough role '.$role, 'roleId' => $role, 'isActive' => true,
            'departmentId' => $this->unit->getKey(), 'assignedDepartmentIds' => [$this->unit->getKey()]]);
    }

    public function test_auditee_result_fragment_and_redirected_standalone_history_use_manila_without_mutating_instants(): void
    {
        $instant = '2026-11-04T05:42:20.123Z';
        $car = ComplianceCheck::create(['checkCode' => 'ISOLATED-TIMELINE', 'departmentId' => $this->unit->getKey(),
            'auditorId' => $this->auditor->getKey(), 'auditeeId' => $this->auditee->getKey(), 'result' => 'non_compliant',
            'correctiveStatus' => 'in_progress', 'dueDate' => '2026-11-05', 'statusHistory' => [['status' => 'in_progress', 'timestamp' => $instant]]]);
        $car->forceFill(['evidenceResponses' => [['method' => 'digital-reference', 'notes' => 'Isolated reference', 'at' => $instant]]])->save();
        $before = $car->fresh()->getRawOriginal();
        $url = '/auditee/finding-details/'.$car->getKey();
        $fragment = $this->actingAs($this->auditee)->get($url, ['X-Requested-With' => 'XMLHttpRequest'])->assertOk()
            ->assertSee('Nov 4, 2026 1:42:20 PM PST (UTC+08:00)')->assertSee('2026-11-04T05:42:20.123000Z')->assertSee('Deadline: Nov 5, 2026')
            ->assertDontSee('5:42:20 AM')->assertDontSee('UTC</time>', false);
        $standalone = $this->get($url)->assertRedirect('/auditee/my-findings?detail='.$car->getKey());
        $index = $this->get('/auditee/my-findings?detail='.$car->getKey())->assertOk()->assertSee('data-result-modal-open', false);
        $this->assertEquals($before, $car->fresh()->getRawOriginal());
        $requestId = (string) \Illuminate\Support\Str::uuid();
        $audit = Audit::create(['auditCode' => 'ISOLATED-HISTORY', 'title' => 'History rendering', 'departmentId' => $this->unit->getKey(),
            'auditorId' => $this->auditor->getKey(), 'status' => 'ready', 'version' => 1,
            'evidenceRequests' => [['id' => $requestId, 'auditeeId' => (string) $this->auditee->getKey(), 'status' => 'reviewed',
                'description' => 'History only', 'referenceStandard' => 'ISO9001-9.2', 'dueDate' => '2026-11-05',
                'responses' => [['method' => 'digital-reference', 'notes' => 'Historical reference', 'at' => $instant,
                    'review' => ['feedback' => 'Historical review', 'at' => $instant]]]]]]);
        $history = $this->get('/auditee/evidence-requests?auditId='.$audit->getKey().'&requestId='.$requestId,
            ['X-Requested-With' => 'XMLHttpRequest'])->assertOk()->assertSee('Nov 5, 2026')->assertDontSee($instant);
        $this->assertSame(2, substr_count($history->getContent(), 'Nov 4, 2026 1:42:20 PM PST (UTC+08:00)'));
        if (getenv('WRITE_RETEST_FIXTURES') === '1') {
            $dir = storage_path('framework/testing/live-retest'); \Illuminate\Support\Facades\File::ensureDirectoryExists($dir);
            file_put_contents($dir.'/auditee-fragment.html', $fragment->getContent());
            file_put_contents($dir.'/auditee-index.html', $index->getContent());
            file_put_contents($dir.'/auditee-url.txt', $url);
        }
    }

    public function test_ambiguous_progress_never_infers_verification_and_alias_transitions_keep_history(): void
    {
        $car = ComplianceCheck::create(['checkCode' => 'PROGRESS', 'departmentId' => $this->unit->getKey(),
            'auditorId' => $this->auditor->getKey(), 'auditeeId' => $this->auditee->getKey(), 'result' => 'non_compliant',
            'correctiveStatus' => 'In progress', 'statusHistory' => [['status' => 'In progress', 'timestamp' => '2026-01-01T00:00:00Z']]]);
        // A historical submission is not a recorded workflow transition or proof of permitted corrective evidence.
        $car->forceFill(['evidenceResponses' => [['at' => '2026-02-01T00:00:00Z', 'author' => 'legacy']]])->save();
        $this->actingAs($this->auditee)->getJson('/api/v1/compliance-checks/'.$car->getKey())->assertOk()->assertJsonPath('data.correctiveStatus', 'in_progress');
        $detail = $this->actingAs($this->auditor)->getJson('/api/v1/compliance-checks/'.$car->getKey().'?correctiveActions=1')->assertOk()
            ->assertJsonPath('data.displayStatus', 'in_progress')->json('data');
        $this->assertSame('In Progress', $detail['statusLabel']);
        $this->auditor->update(['assignedDepartmentIds' => []]);
        $this->actingAs($this->auditor)->getJson('/api/v1/compliance-checks/'.$car->getKey())->assertOk()->assertJsonCount(0, 'data.allowedTransitions');
        $this->patchJson('/api/v1/compliance-checks/'.$car->getKey().'/status', ['correctiveStatus' => 'closed', 'remarks' => 'Revoked assignment'])->assertForbidden();
        $this->auditor->update(['assignedDepartmentIds' => [$this->unit->getKey()]]);
        $this->actingAs($this->auditee)->getJson('/api/v1/compliance-checks?correctiveStatus=in_progress')->assertOk()->assertJsonPath('meta.total', 1);
        $this->assertCount(1, array_filter($detail['timeline'], fn ($e) => $e['state'] === 'current'));
        $this->actingAs($this->user(2))->patchJson('/api/v1/compliance-checks/'.$car->getKey().'/status', ['correctiveStatus' => 'closed', 'remarks' => 'Verified original'])->assertNotFound();
        $this->actingAs($this->auditor)->patchJson('/api/v1/compliance-checks/'.$car->getKey().'/status', ['correctiveStatus' => 'closed', 'remarks' => 'Verified physical original control'])->assertOk();
        $this->assertSame('In progress', $car->fresh()->statusHistory[0]['status']);
        $car->update(['correctiveStatus' => 'unmapped_legacy']);
        $this->patchJson('/api/v1/compliance-checks/'.$car->getKey().'/status', ['correctiveStatus' => 'closed', 'remarks' => 'Cannot bypass recovery'])->assertUnprocessable();
        $this->patchJson('/api/v1/compliance-checks/'.$car->getKey().'/status', ['correctiveStatus' => 'open'])->assertUnprocessable();
        $this->patchJson('/api/v1/compliance-checks/'.$car->getKey().'/status', ['correctiveStatus' => 'open', 'remarks' => 'Inspected legacy events; corrective work remains'])->assertOk();
    }

    public function test_pending_response_blocks_server_submission_despite_old_physical_review_and_stale_version(): void
    {
        $items = [['id' => 'i1', 'question' => 'Control?', 'result' => 'C', 'remarks' => 'Reviewed',
            'evidence' => [['method' => 'physical-original', 'signature' => 'verified', 'notes' => 'Earlier original review']]]];
        $audit = Audit::create(['auditCode' => 'READINESS', 'title' => 'Readiness', 'auditorId' => $this->auditor->getKey(),
            'reviewerId' => $this->atl->getKey(), 'departmentId' => $this->unit->getKey(), 'status' => 'ready', 'version' => 1, 'items' => $items]);
        $request = ['id' => 'r1', 'itemId' => 'i1', 'auditeeId' => (string) $this->auditee->getKey(), 'description' => 'New response',
            'status' => 'response_submitted', 'responses' => [['method' => 'physical-original', 'reference' => 'CAB-1', 'submissionKey' => 'unique', 'at' => '2026-11-01T00:00:00Z']]];
        $audit->update(['evidenceRequests' => [$request], 'version' => 2]);
        $this->actingAs($this->auditor)->postJson('/audits/'.$audit->getKey(), ['action' => 'submit', 'version' => 1])->assertStatus(409);
        $this->postJson('/audits/'.$audit->getKey(), ['action' => 'submit', 'version' => 2])->assertUnprocessable()->assertJsonValidationErrors('evidenceRequests');
        $this->assertSame(0, AuditChecklist::readiness($items, [$request])['evidence']);
        $this->assertSame(1, AuditChecklist::readiness($items, [$request])['pendingRequests']);
        // Review the new response through the real endpoint, preserving physical-signature validation.
        $this->post('/audits/'.$audit->getKey().'/requests/r1/review', ['version' => 2, 'decision' => 'reviewed', 'feedback' => 'Verified revised original', 'signature' => 'verified'])->assertRedirect();
        $this->postJson('/audits/'.$audit->getKey(), ['action' => 'submit', 'version' => $audit->fresh()->version])->assertOk();
        $this->assertSame('submitted', $audit->fresh()->status);
    }

    public function test_response_readiness_locks_revision_acceptance_and_configured_car_authority_end_to_end(): void
    {
        $itemId = (string) \Illuminate\Support\Str::uuid();
        $items = [['id' => $itemId, 'question' => 'Original control', 'result' => 'N', 'finding' => 'Original nonconformity',
            'remarks' => '', 'evidence' => [['method' => 'physical-original', 'signature' => 'verified', 'notes' => 'Earlier review']]]];
        $audit = Audit::create(['auditCode' => 'ISOLATED-LIFECYCLE', 'title' => 'Lifecycle regression', 'departmentId' => $this->unit->getKey(),
            'auditorId' => $this->auditor->getKey(), 'reviewerId' => $this->atl->getKey(), 'status' => 'ready', 'version' => 1, 'items' => $items]);
        $car = ComplianceCheck::create(['checkCode' => 'ISOLATED-CAR', 'departmentId' => $this->unit->getKey(), 'auditorId' => $this->auditor->getKey(),
            'auditeeId' => $this->auditee->getKey(), 'result' => 'non_compliant', 'correctiveStatus' => 'open']);
        $car->forceFill(['sourceAuditId' => $audit->getKey(), 'sourceItemId' => $itemId])->save();
        $url = '/audits/'.$audit->getKey();
        $post = fn ($action, $extra = []) => $this->postJson($url, $extra + ['action' => $action, 'version' => $audit->fresh()->version]);
        $this->actingAs($this->auditor)->post($url.'/requests', ['version' => 1, 'itemId' => $itemId,
            'auditeeId' => (string) $this->auditee->getKey(), 'description' => 'Inspect latest original', 'referenceStandard' => 'ISO9001-9.2'])
            ->assertRedirect()->assertSessionHasNoErrors();
        $requestId = $audit->fresh()->evidenceRequests[0]['id'];
        $this->actingAs($this->auditee)->postJson('/auditee/evidence-requests/'.$audit->getKey().'/'.$requestId,
            ['version' => $audit->fresh()->version, 'submissionKey' => (string) \Illuminate\Support\Str::uuid(), 'action' => 'submit',
                'method' => 'physical-original', 'availability' => 'available', 'notes' => 'Original ready'])->assertOk();
        $this->actingAs($this->auditor)->postJson($url, ['action' => 'submit', 'version' => 1])->assertStatus(409);
        $post('submit')->assertUnprocessable()->assertJsonValidationErrors('evidenceRequests');
        $this->post($url.'/requests/'.$requestId.'/review', ['version' => $audit->fresh()->version, 'decision' => 'reviewed',
            'feedback' => 'Latest physical original verified', 'signature' => 'verified'])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame([], AuditChecklist::outstandingRequests($audit->fresh()->evidenceRequests));
        $post('submit')->assertUnprocessable()->assertJsonValidationErrors('checklist'); // Missing remarks still block.
        $evaluation = ['item' => 0, 'result' => 'N', 'finding' => 'Original nonconformity', 'remarks' => 'Verified incomplete control'];
        $post('evaluate', $evaluation)->assertOk();
        $post('submit')->assertOk();
        $post('evaluate', $evaluation)->assertForbidden();
        $this->actingAs($this->user(1)); $post('return', ['reviewNotes' => 'Unauthorized reviewer'])->assertNotFound();
        $this->actingAs($this->atl); $post('return', ['reviewNotes' => 'Clarify the evidence review remarks'])->assertOk();
        $this->actingAs($this->user(2)); $post('evaluate', $evaluation)->assertNotFound();
        $this->actingAs($this->auditor); $post('evaluate', $evaluation)->assertOk();
        $post('edit', ['item' => 0, 'question' => 'Rewrite prepared question'])->assertForbidden();
        $post('submit')->assertOk();
        $original = $audit->fresh()->items; $carBefore = $car->fresh()->getRawOriginal();
        $this->actingAs($this->atl); $post('accept', ['reviewNotes' => 'Accepted recorded findings'])->assertOk();
        $this->assertSame($original, $audit->fresh()->items);
        $this->assertEquals($carBefore, $car->fresh()->getRawOriginal());
        $this->actingAs($this->auditor); $post('evaluate', $evaluation)->assertForbidden();
        $carUrl = '/api/v1/compliance-checks/'.$car->getKey().'/status';
        $this->patchJson($carUrl, ['correctiveStatus' => 'in_progress'])->assertOk();
        $role = Role::where('roleId', 2)->firstOrFail(); $role->update(['permissions' => []]);
        $this->patchJson($carUrl, ['correctiveStatus' => 'closed', 'remarks' => 'Verification'])->assertForbidden();
        $role->update(['permissions' => ['cars.verify']]);
        $this->patchJson($carUrl, ['correctiveStatus' => 'closed', 'remarks' => 'Verification'])->assertForbidden();
        $role->update(['permissions' => ['cars.verify', 'cars.close']]);
        $this->actingAs($this->user(2))->patchJson($carUrl, ['correctiveStatus' => 'closed', 'remarks' => 'Wrong owner'])->assertNotFound();
        $this->auditor->update(['assignedDepartmentIds' => []]);
        $this->actingAs($this->auditor)->patchJson($carUrl, ['correctiveStatus' => 'closed', 'remarks' => 'Revoked scope'])->assertForbidden();
        $this->auditor->update(['assignedDepartmentIds' => [$this->unit->getKey()]]);
        $this->patchJson($carUrl, ['correctiveStatus' => 'closed'])->assertUnprocessable();
        $this->patchJson($carUrl, ['correctiveStatus' => 'closed', 'remarks' => 'Verified corrective control against original'])->assertOk();
        $this->assertSame('closed', $car->fresh()->correctiveStatus);
        $this->assertSame('N', $audit->fresh()->items[0]['result']);
        $this->assertSame('Original nonconformity', $audit->fresh()->items[0]['finding']);
    }

    public function test_annual_filters_scope_denominator_and_csv_match_without_counting_cars_as_findings(): void
    {
        foreach (config('qms.departments') as $code => $name) {
            if ($code === 'CET-IT') { continue; }
            Department::create(['departmentName' => $name, 'departmentCode' => $code, 'collegeName' => 'CET',
                'unitType' => 'academic_department', 'ownershipVerified' => true, 'isActive' => true]);
        }
        Department::create(['departmentName' => 'Office of the Dean', 'departmentCode' => 'CET-DEAN', 'collegeName' => 'CET',
            'unitType' => 'college_office', 'ownershipVerified' => true, 'isActive' => true]);
        $plan = ArchiveDocument::create(['title' => 'Annual plan', 'departmentId' => $this->unit->getKey(), 'ownershipVerified' => true]);
        $plan->forceFill(['auditPlan' => ['status' => 'accepted', 'header' => ['unitIds' => [(string) $this->unit->getKey()],
            'reviewerIds' => [(string) $this->atl->getKey()], 'scheduleStart' => '2026-01-01', 'scheduleEnd' => '2026-12-31']]])->save();
        $audit = Audit::create(['auditCode' => 'ANNUAL', 'planId' => $plan->getKey(), 'departmentId' => $this->unit->getKey(),
            'auditorId' => $this->auditor->getKey(), 'reviewerId' => $this->atl->getKey(), 'schedule' => '2026-12-31', 'status' => 'accepted',
            'items' => [['result' => 'C'], ['result' => 'N'], ['result' => 'O']], 'evidenceRequests' => [['status' => 'requested']]]);
        $car = ComplianceCheck::create(['departmentId' => $this->unit->getKey(), 'auditorId' => $this->auditor->getKey(), 'auditeeId' => $this->auditee->getKey(),
            'result' => 'non_compliant', 'correctiveStatus' => 'closed']);
        $car->forceFill(['sourceAuditId' => $audit->getKey(), 'sourceItemId' => 'i2'])->save();
        $otherAtl = $this->user(1);
        Audit::create(['departmentId' => $this->unit->getKey(), 'reviewerId' => $otherAtl->getKey(), 'schedule' => '2026-05-01', 'status' => 'accepted', 'items' => [['result' => 'C']]]);
        $report = app(AnnualAuditReport::class)->generate($this->atl, 2026, (string) $plan->getKey());
        $row = $report['rows'][0];
        $this->assertSame(1, $row['assigned']); $this->assertSame(1, $row['planned']);
        $this->assertSame(50.0, $row['compliance']); $this->assertSame(1, $row['O']);
        $this->assertSame(0, $row['legacyChecks']); $this->assertSame(1, $row['linkedCars']); $this->assertSame(1, $row['closedCars']);
        $this->assertSame(1, $row['outstandingEvidence']);
        $empty = app(AnnualAuditReport::class)->generate($this->atl, 2025);
        $this->assertCount(6, $empty['rows']);
        $this->assertContains('Office of the Dean', array_column($empty['rows'], 'unit'));
        $this->assertNull($empty['rows'][0]['compliance']);
        $this->actingAs($this->atl)->getJson('/api/v1/reports?annual=1&year=2026&planId='.$plan->getKey())->assertOk()->assertJsonPath('data.rows.0.C', 1);
        $csv = $this->get('/api/v1/reports?annual=1&year=2026&format=csv&planId='.$plan->getKey())->assertOk()->streamedContent();
        $this->assertStringContainsString('50%', $csv); $this->assertStringContainsString('Information Technology', $csv);
        $privatePlan = ArchiveDocument::create(['title' => 'Private plan', 'ownershipVerified' => true, 'departmentId' => $this->unit->getKey()]);
        $privatePlan->forceFill(['auditPlan' => ['header' => ['reviewerIds' => [(string) $otherAtl->getKey()]]]])->save();
        $this->getJson('/api/v1/reports?annual=1&year=2026&planId='.$privatePlan->getKey())->assertNotFound();
        $this->actingAs($this->auditee)->getJson('/api/v1/reports?annual=1&year=2026&format=csv')->assertForbidden();
    }
}
