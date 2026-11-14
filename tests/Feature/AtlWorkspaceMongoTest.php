<?php

namespace Tests\Feature;

use App\Models\{ArchiveDocument, Audit, ComplianceCheck, Department, User};
use Illuminate\Support\Facades\{DB, File};
use MongoDB\BSON\ObjectId;
use Tests\TestCase;

class AtlWorkspaceMongoTest extends TestCase
{
    private ?string $databaseName = null;
    private ?string $directory = null;
    private $units;
    private User $admin;
    private User $auditor;
    private User $auditee;

    protected function setUp(): void
    {
        parent::setUp();
        if (getenv('RUN_MONGODB_AUDIT_TESTS') !== '1') { $this->markTestSkipped('Uses an isolated MongoDB database.'); }
        $this->databaseName = 'ct_atl_test_'.bin2hex(random_bytes(6));
        $this->directory = storage_path('framework/testing/'.$this->databaseName);
        config(['database.connections.mongodb.database' => $this->databaseName, 'system.path' => $this->directory.'/system']);
        DB::purge('mongodb');
        $this->assertSame($this->databaseName, DB::connection('mongodb')->getMongoDB()->getDatabaseName());
        $this->withoutVite();
        $this->units = collect(config('qms.departments'))->map(fn ($name, $code) => Department::create([
            'departmentCode' => $code, 'departmentName' => $name, 'collegeName' => 'CET', 'unitType' => 'academic_department',
            'ownershipVerified' => true, 'isActive' => true,
        ]))->values();
        $this->admin = $this->account(1);
        $this->auditor = $this->account(2);
        $this->auditee = $this->account(3);
    }

    protected function tearDown(): void
    {
        if ($this->databaseName) {
            $db = DB::connection('mongodb')->getMongoDB();
            if ($db->getDatabaseName() === $this->databaseName && str_starts_with($this->databaseName, 'ct_atl_test_')) { $db->drop(); }
            DB::purge('mongodb');
        }
        if ($this->directory && is_dir($this->directory)) {
            $resolved = realpath($this->directory); $root = realpath(storage_path('framework/testing'));
            if ($resolved && $root && str_starts_with($resolved, $root.DIRECTORY_SEPARATOR)) { File::deleteDirectory($resolved); }
        }
        parent::tearDown();
    }

    private function account(int $role): User
    {
        return User::create(['fullName' => 'ATL test '.$role, 'roleId' => $role, 'isActive' => true,
            'departmentId' => $this->units->first()->getKey(), 'assignedDepartmentIds' => [$this->units->first()->getKey()]]);
    }

    private function items(array $results = ['C', 'N', 'O']): array
    {
        return array_map(fn ($result) => ['id' => 'question-'.$result, 'question' => 'Question '.$result, 'result' => $result,
            'remarks' => 'Auditor factual remarks '.$result, 'finding' => $result === 'N' ? 'Recorded finding' : null,
            'evidence' => [['method' => 'physical-original', 'reference' => 'Permitted reference', 'notes' => 'Original reviewed', 'signature' => 'verified']]], $results);
    }

    private function audit(string $status = 'submitted', array $extra = []): Audit
    {
        return Audit::create($extra + ['auditCode' => 'ATL-'.bin2hex(random_bytes(3)), 'title' => 'Assigned audit', 'scope' => 'Process scope',
            'criteria' => 'ISO criterion', 'departmentId' => $this->units->first()->getKey(), 'departmentName' => $this->units->first()->departmentName,
            'auditorId' => $this->auditor->getKey(), 'reviewerId' => $this->admin->getKey(), 'version' => 1,
            'status' => $status, 'schedule' => '2026-11-10', 'items' => $this->items(),
            'history' => [['action' => 'submit', 'at' => now()->toIso8601String(), 'actor' => (string) $this->auditor->getKey(), 'previousItems' => [], 'previousStatus' => 'ready']]]);
    }

    private function car(array $extra = []): ComplianceCheck
    {
        return ComplianceCheck::create($extra + ['checkCode' => 'CAR-'.bin2hex(random_bytes(3)), 'departmentId' => $this->units->first()->getKey(),
            'auditorId' => $this->auditor->getKey(), 'auditeeId' => $this->auditee->getKey(), 'result' => 'non_compliant',
            'correctiveStatus' => 'open', 'dueDate' => now()->subDays(2), 'statusHistory' => []]);
    }

    public function test_dashboard_uses_scoped_real_audits_and_deadlines_and_five_departments(): void
    {
        $this->audit('ready'); $submitted = $this->audit(); $this->audit('returned'); $this->audit('accepted');
        $this->audit('accepted', ['departmentId' => $this->units[1]->getKey(), 'items' => $this->items(['O'])]);
        $other = $this->account(1); $this->audit('submitted', ['reviewerId' => $other->getKey(), 'title' => 'PRIVATE OTHER REVIEW']);
        $outside = Department::create(['collegeName' => 'OTHER', 'departmentName' => 'Outside', 'isActive' => true]);
        $this->audit('submitted', ['departmentId' => $outside->getKey(), 'title' => 'PRIVATE OUTSIDE']);
        $this->car(); $this->car(['correctiveStatus' => 'in_progress']); $this->car(['dueDate' => null]);
        $this->car(['correctiveStatus' => 'closed', 'statusHistory' => [['status' => 'closed', 'remarks' => 'Verified']]]);
        $this->car(['departmentId' => $outside->getKey()]);
        $response = $this->actingAs($this->admin)->getJson('/api/v1/admin/workspace')->assertOk()
            ->assertJsonPath('data.stats.activeAudits', 3)->assertJsonPath('data.stats.pendingReviews', 1)
            ->assertJsonPath('data.stats.openCars', 3)->assertJsonPath('data.stats.overdueCars', 2)
            ->assertJsonPath('data.people.auditors', 1)->assertJsonPath('data.people.auditees', 1)
            ->assertJsonCount(5, 'data.departmentCompliance')->assertJsonCount(4, 'data.attention')
            ->assertJsonCount(5, 'data.assignments')->assertDontSee('PRIVATE OTHER REVIEW')->assertDontSee('PRIVATE OUTSIDE');
        $comparison = collect($response->json('data.departmentCompliance'))->keyBy('id');
        $this->assertSame(50, $comparison[(string) $this->units[0]->getKey()]['percentage']);
        $this->assertNull($comparison[(string) $this->units[1]->getKey()]['percentage']);
        $this->get('/audits?view=reviews&status=submitted')->assertOk()->assertSee($submitted->auditCode)->assertDontSee('PRIVATE OTHER REVIEW');
        $this->getJson('/api/v1/reports')->assertOk()->assertJsonPath('data.activeAudits', 3)->assertJsonPath('data.deptCompliance.0.assessedChecks', 4)
            ->assertJsonStructure(['data' => ['deptCompliance' => [['assessedChecks']], 'monthlyComplianceTrend' => [['assessedChecks']]]]);
        $this->assertSame(5, Department::where('unitType', 'academic_department')->count());
    }

    public function test_dashboard_assignment_page_sizes_preserve_scope_and_clamp_stale_pages(): void
    {
        for ($i = 0; $i < 12; $i++) { $this->audit('ready', ['title' => 'Paged audit '.$i]); }
        $other = $this->account(1);
        $this->audit('ready', ['reviewerId' => $other->getKey(), 'title' => 'PRIVATE OTHER REVIEW']);
        $first = $this->actingAs($this->admin)->getJson('/api/v1/admin/workspace')->assertOk()
            ->assertJsonCount(5, 'data.assignments')->assertJsonPath('data.assignmentTotal', 12)
            ->assertJsonPath('data.assignmentPage', 1)->assertJsonPath('data.assignmentPages', 3)
            ->assertDontSee('PRIVATE OTHER REVIEW')->json('data.assignments');
        $second = $this->getJson('/api/v1/admin/workspace?page=2&perPage=5')->assertOk()
            ->assertJsonCount(5, 'data.assignments')->assertJsonPath('data.assignmentPage', 2)->json('data.assignments');
        $this->assertEmpty(array_intersect(array_column($first, 'id'), array_column($second, 'id')));
        $this->getJson('/api/v1/admin/workspace?perPage=10')->assertOk()
            ->assertJsonCount(10, 'data.assignments')->assertJsonPath('data.assignmentPages', 2);
        $this->getJson('/api/v1/admin/workspace?page=99&perPage=10')->assertOk()
            ->assertJsonCount(2, 'data.assignments')->assertJsonPath('data.assignmentPage', 2);
        $this->getJson('/api/v1/admin/workspace?perPage=20')->assertUnprocessable();
        Audit::query()->delete();
        $this->getJson('/api/v1/admin/workspace?page=2')->assertOk()
            ->assertJsonCount(0, 'data.assignments')->assertJsonPath('data.assignmentPage', 1)
            ->assertJsonPath('data.assignmentPages', 1)->assertJsonPath('data.assignmentTotal', 0);
    }

    public function test_popup_and_decisions_preserve_evaluations_and_record_review_history(): void
    {
        $audit = $this->audit();
        $allowed = ArchiveDocument::create(['title' => 'Authorized file', 'departmentId' => $audit->departmentId, 'ownershipVerified' => true, 'recordType' => 'file']);
        $denied = ArchiveDocument::create(['title' => 'SECRET DENIED TITLE', 'departmentId' => $audit->departmentId, 'ownershipVerified' => false, 'recordType' => 'file']);
        $audit->update(['planArchiveIds' => [$allowed->getKey(), $denied->getKey()]]);
        $detail = $this->actingAs($this->admin)->get('/audits/'.$audit->getKey().'?review=1')->assertOk()
            ->assertSee('Question C')->assertSee('Question N')->assertSee('Question O')->assertSee('Auditor factual remarks')
            ->assertSee('Recorded finding')->assertSee('Original reviewed')->assertSee('Submission and Review History')
            ->assertSee('Authorized file')->assertDontSee('SECRET DENIED TITLE')->assertSee('Accept Results');
        if (getenv('WRITE_REVIEW_UI_FIXTURES') === '1') {
            $directory = storage_path('framework/testing/audit-reviews'); File::ensureDirectoryExists($directory);
            File::put($directory.'/review.html', $detail->getContent());
            File::put($directory.'/modal.html', view('components.atl-review-modal')->render());
        }
        $items = $audit->fresh()->items;
        $this->postJson('/audits/'.$audit->getKey(), ['action' => 'evaluate', 'version' => 1, 'item' => 0, 'result' => 'N', 'remarks' => 'Overwrite'])->assertForbidden();
        $this->postJson('/audits/'.$audit->getKey(), ['action' => 'accept', 'version' => 1, 'reviewNotes' => 'Accepted with recorded review', 'items' => []])->assertOk();
        $this->assertSame($items, $audit->fresh()->items);
        $this->assertSame('accepted', $audit->fresh()->status);
        $this->assertSame('accept', collect($audit->fresh()->history)->last()['action']);
        $this->assertSame($items, collect($audit->fresh()->history)->last()['previousItems']);
        $this->get('/audits/'.$audit->getKey().'?review=1')->assertOk()->assertDontSee('data-atl-review-form', false)->assertSee('Accepted with recorded review');
        $this->postJson('/audits/'.$audit->getKey(), ['action' => 'return', 'version' => 2, 'reviewNotes' => 'Invalid repeat'])->assertForbidden();
    }

    public function test_return_revision_resubmission_and_stale_conflicts_follow_existing_workflow(): void
    {
        $audit = $this->audit();
        $url = '/audits/'.$audit->getKey();
        $this->actingAs($this->admin)->postJson($url, ['action' => 'return', 'version' => 1, 'reviewNotes' => 'Clarify finding'])->assertOk();
        $this->actingAs($this->auditor)->postJson($url, ['action' => 'evaluate', 'version' => 2, 'item' => 1, 'result' => 'N', 'remarks' => 'Clarified finding', 'finding' => 'Confirmed finding'])->assertOk();
        $this->postJson($url, ['action' => 'submit', 'version' => 3])->assertOk();
        $this->actingAs($this->admin)->get($url.'?review=1')->assertOk()->assertSee('Revised since return')->assertSee('Clarified finding');
        $this->get('/audits?view=reviews')->assertOk()->assertSee('Resubmission');
        $this->actingAs($this->admin)->postJson($url, ['action' => 'accept', 'version' => 1, 'reviewNotes' => 'Stale review'])->assertStatus(409);
        $this->assertSame('submitted', $audit->fresh()->status);
        $this->postJson($url, ['action' => 'accept', 'version' => 4, 'reviewNotes' => 'Reviewed revised submission'])->assertOk();
        $this->assertSame('Clarified finding', $audit->fresh()->items[1]['remarks']);
    }

    public function test_review_queue_filters_submission_order_and_real_counts(): void
    {
        $first = $this->audit('submitted', ['auditCode' => 'OLDEST-REVIEW', 'history' => [['action' => 'submit', 'at' => '2026-01-01T00:00:00Z']]]);
        $second = $this->audit('submitted', ['auditCode' => 'NEWEST-REVIEW', 'history' => [['action' => 'submit', 'at' => '2026-02-01T00:00:00Z']]]);
        $returned = $this->audit('returned'); $accepted = $this->audit('accepted');
        $queue = $this->actingAs($this->admin)->get('/audits?view=reviews')->assertOk()->assertSeeInOrder(['OLDEST-REVIEW', 'NEWEST-REVIEW'])
            ->assertViewHas('counts', ['submitted' => 2, 'returned' => 1, 'accepted' => 1]);
        if (getenv('WRITE_REVIEW_UI_FIXTURES') === '1') {
            $directory = storage_path('framework/testing/audit-reviews'); File::ensureDirectoryExists($directory);
            File::put($directory.'/queue.html', $queue->getContent());
        }
        $this->get('/audits?view=reviews&search=OLDEST')->assertOk()->assertSee($first->auditCode)->assertDontSee($second->auditCode);
        $this->get('/audits?view=reviews&status=returned')->assertOk()->assertSee($returned->auditCode)->assertDontSee($accepted->auditCode);
        $this->get('/audits?view=reviews&status=accepted')->assertOk()->assertSee($accepted->auditCode)->assertDontSee($returned->auditCode);
        $this->get('/audits?view=reviews&auditorId='.new ObjectId)->assertOk()->assertSee('No audits match this queue');
        $this->get('/audits?view=reviews&departmentId='.$this->units[1]->getKey())->assertOk()->assertSee('No audits match this queue');
        $this->get('/audits?view=reviews&planId='.new ObjectId)->assertOk()->assertSee('No audits match this queue');
    }

    public function test_revision_requires_meaningful_notes_and_acceptance_preserves_cars(): void
    {
        $audit = $this->audit(); $car = $this->car(); $url = '/audits/'.$audit->getKey();
        foreach (['', '   ', 'fix', '123456789012'] as $notes) {
            $this->actingAs($this->admin)->postJson($url, ['action' => 'return', 'version' => 1, 'reviewNotes' => $notes])->assertUnprocessable();
        }
        $this->assertSame('submitted', $audit->fresh()->status);
        $this->postJson($url, ['action' => 'accept', 'version' => 1])->assertOk();
        $this->postJson($url, ['action' => 'return', 'version' => 1, 'reviewNotes' => 'Concurrent stale decision'])->assertConflict();
        $this->assertSame('open', $car->fresh()->correctiveStatus);
        $this->assertSame(1, collect($audit->fresh()->history)->where('action', 'accept')->count());
    }

    public function test_other_reviewers_roles_and_departments_cannot_read_or_decide(): void
    {
        $audit = $this->audit(); $url = '/audits/'.$audit->getKey();
        $this->actingAs($this->account(1))->get($url.'?review=1')->assertNotFound();
        $this->postJson($url, ['action' => 'accept', 'version' => 1, 'reviewNotes' => 'Not assigned'])->assertNotFound();
        foreach ([0, 2, 3] as $role) {
            $this->actingAs($this->account($role))->getJson('/api/v1/admin/workspace')->assertForbidden();
            $this->get($url.'?review=1')->assertStatus($role === 2 ? 404 : 403);
        }
        $this->actingAs($this->auditor)->get($url.'?review=1')->assertForbidden();
        $this->postJson($url, ['action' => 'accept', 'version' => 1, 'reviewNotes' => 'Auditor cannot accept'])->assertForbidden();
        $this->assertSame('submitted', $audit->fresh()->status);
    }

    public function test_car_monitoring_is_read_only_and_keeps_verification_and_evidence_scoped(): void
    {
        $car = $this->car(['correctiveStatus' => 'in_progress', 'statusHistory' => [['status' => 'in_progress', 'remarks' => 'Verification pending', 'timestamp' => now()->toIso8601String()]]]);
        $closed = $this->car(['correctiveStatus' => 'closed', 'statusHistory' => [['status' => 'closed', 'remarks' => 'Checked evidence and verified', 'timestamp' => now()->toIso8601String()]]]);
        $this->actingAs($this->admin)->get('/admin/compliance-records?monitor=1&overdue=1&car='.$car->getKey())->assertOk()
            ->assertSee($car->checkCode)->assertSee('Overdue')->assertSee('In Progress')->assertSee('Verification pending')->assertDontSee($closed->checkCode);
        $this->get('/admin/compliance-records?monitor=1&car='.$closed->getKey())->assertOk()->assertSee('Checked evidence and verified')->assertSee('Verification results: Not recorded');
        $this->get('/admin/compliance-records?monitor=1&car='.$closed->getKey())
            ->assertSee('id="car-history-dialog"', false)->assertSee('data-auto-open="true"', false)->assertSee('data-car-history', false);
        $this->get('/admin/compliance-records?monitor=1&history=1&car='.$closed->getKey())->assertOk()
            ->assertSee('Checked evidence and verified')->assertSee('Permitted linked files')->assertDontSee('<html', false)
            ->assertHeader('Cache-Control', 'no-store, private');
        $this->patchJson('/api/v1/compliance-checks/'.$car->getKey().'/status', ['correctiveStatus' => 'closed', 'remarks' => 'ATL override'])->assertForbidden();
        $outside = Department::create(['collegeName' => 'OTHER', 'departmentName' => 'Outside']);
        $foreign = $this->car(['departmentId' => $outside->getKey()]);
        $this->get('/admin/compliance-records?monitor=1&car='.$foreign->getKey())->assertNotFound();
        $this->get('/admin/compliance-records?monitor=1&history=1&car='.$foreign->getKey())->assertNotFound();
        $this->get('/admin/compliance-records?monitor=1&history=1')->assertStatus(422);
        $this->assertSame('in_progress', $car->fresh()->correctiveStatus);
    }
}
