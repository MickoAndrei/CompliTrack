<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\RoleScopeService;
use Illuminate\Pagination\LengthAwarePaginator;
use Tests\TestCase;

class AuditeeResultsViewTest extends TestCase
{
    public function test_authorized_files_offer_separate_preview_and_download_actions(): void
    {
        $this->actor();
        $check = new \App\Models\ComplianceCheck(['checkCode' => 'PREVIEW-TEST', 'result' => 'non_compliant', 'correctiveStatus' => 'open']);
        $check->setAttribute('_id', new \MongoDB\BSON\ObjectId());
        $archive = new \App\Models\ArchiveDocument(['originalName' => '<script>document</script>.pdf']);
        $archive->setAttribute('_id', new \MongoDB\BSON\ObjectId());
        $this->view('auditee.result-detail-content', ['check' => $check, 'archives' => collect([$archive])])
            ->assertSee('data-evidence-preview', false)->assertSee('Preview')->assertSee('Download')
            ->assertSee('/api/v1/archives/'.$archive->getKey().'/download')->assertDontSee('<script>document</script>', false);
        $this->view('auditee.result-modal')->assertSee('id="audit-result-modal"', false)
            ->assertSee('id="evidence-preview-modal"', false)->assertSee('Back to audit result');
    }

    private function actor(): User
    {
        $user = new User(['fullName' => 'Results test', 'roleId' => 3, 'isActive' => true]);
        $user->setAttribute('_id', new \MongoDB\BSON\ObjectId());
        $this->actingAs($user);
        view()->share('errors', new \Illuminate\Support\ViewErrorBag());
        return $user;
    }

    public function test_empty_results_and_shared_only_observation_are_distinct(): void
    {
        $this->actor();
        $this->view('auditee.my-findings', ['summary' => ['total' => 0, 'nonconformities' => 0, 'openCars' => 0],
            'checks' => new LengthAwarePaginator([], 0, 20), 'published' => collect()])
            ->assertSee('No accepted workspace results have been shared with you yet.')
            ->assertSee('No legacy compliance results yet.')->assertSee('data-results-loading hidden', false)->assertDontSee('Loading results…')
            ->assertDontSee('More filters')->assertSee('id="auditee-results-card"', false)
            ->assertSee('<dialog id="audit-result-modal"', false)->assertSee('aria-labelledby="audit-result-modal-title"', false);
        $this->view('auditee.my-findings', ['summary' => ['total' => 0, 'nonconformities' => 0, 'openCars' => 0],
            'checks' => new LengthAwarePaginator([], 0, 20),
            'published' => collect([['auditCode' => 'SHARED-OBSERVATION', 'result' => 'O', 'summary' => 'Permitted summary',
                'department' => 'Test department', 'at' => '2026-11-06', 'requestId' => 'test-request']])])
            ->assertSee('O — Observation')->assertSee('Permitted summary')->assertSee('No legacy compliance results yet.')
            ->assertSee('/auditee/evidence-requests#request-test-request');
    }

    public function test_database_failure_returns_retry_state_instead_of_zero_statistics(): void
    {
        $this->actor();
        $this->mock(RoleScopeService::class, function ($mock) {
            $mock->shouldReceive('checks')->once()->andThrow(new \MongoDB\Driver\Exception\ConnectionTimeoutException('Database unavailable'));
        });
        $this->get('/auditee/my-findings')->assertStatus(503)->assertSee('Audit results could not be loaded.')
            ->assertSee('Retry loading results')->assertDontSee('No legacy compliance results yet.')
            ->assertDontSee('Database unavailable')->assertViewHas('summary', null);
    }

    public function test_detail_view_preserves_evidence_form_only_for_actionable_findings(): void
    {
        $this->actor();
        $check = new \App\Models\ComplianceCheck(['checkCode' => 'DETAIL-TEST', 'referenceStandard' => 'ISO9001-9.2',
            'result' => 'non_compliant', 'correctiveStatus' => 'open', 'remarks' => '<script>private input</script>']);
        $check->setAttribute('_id', new \MongoDB\BSON\ObjectId());
        $data = ['check' => $check, 'archives' => collect(), 'auditorName' => 'Test Auditor', 'departmentName' => 'Test Department'];
        $this->view('auditee.result-detail-content', $data)->assertSee('N — Non-Conformance')->assertSee('Provide Corrective Evidence')
            ->assertSee('/auditee/finding-details/'.$check->getKey().'/response')->assertSee('Check item / requirement')
            ->assertSee('No accessible files')->assertDontSee('<script>private input</script>', false)
            ->assertSee('data-result-panel="overview"', false)->assertSee('data-result-panel="evidence"', false)
            ->assertSee('data-result-panel="corrective"', false)->assertSee('Review date: Not recorded')
            ->assertSee('No separate audit question is recorded');
        $check->correctiveStatus = 'closed';
        $this->view('auditee.result-detail-content', $data)->assertSee('This corrective action is closed.')
            ->assertDontSee('Provide Corrective Evidence')->assertDontSee('name="submissionKey"', false);
        $check->result = 'compliant';
        $check->correctiveStatus = null;
        $this->view('auditee.result-detail-content', $data)->assertSee('No corrective action (CAR) is recorded for this audit result.')
            ->assertDontSee('class="car-timeline"', false)->assertDontSee('name="submissionKey"', false);
    }
}
