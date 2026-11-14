<?php

namespace Tests\Unit;

use App\Models\Audit;
use App\Services\AuditorAuditListing;
use Illuminate\Http\Request;
use Tests\TestCase;

class AuditorAuditListingTest extends TestCase
{
    public function test_actions_and_progress_are_derived_without_changing_lifecycle_statuses(): void
    {
        $presenter = new AuditorAuditListing;
        $audit = new Audit(['title' => 'Recorded title', 'auditCode' => 'RECORDED-001', 'status' => 'draft', 'reviewerId' => '000000000000000000000001', 'items' => []]);
        foreach (['draft' => 'Prepare Checklist', 'ready' => 'Open Audit', 'submitted' => 'View Submission', 'returned' => 'Review Feedback & Revise', 'accepted' => 'View Final Results'] as $status => $action) {
            $audit->status = $status;
            $card = $presenter->card($audit);
            $this->assertSame($action, $card['action']);
            $this->assertSame('Recorded title', $card['title']);
            $this->assertSame('RECORDED-001', $card['code']);
        }
        $audit->status = 'ready';
        $audit->items = [['result' => 'N', 'evidence' => [['method' => 'physical-original']]]];
        $card = $presenter->card($audit);
        $this->assertSame('Continue Audit', $card['action']);
        $this->assertSame('in_progress', $card['status']);
        $this->assertSame('ready', $audit->status);
        $this->assertEquals(100, $card['progress']['percent']);
        $this->assertSame(0, $card['progress']['C']);
    }

    public function test_only_authorized_return_feedback_is_projected_to_the_card(): void
    {
        $audit = new Audit(['status' => 'returned', 'reviewerId' => '000000000000000000000001', 'history' => [
            ['action' => 'return', 'actor' => '000000000000000000000001', 'notes' => 'Clarify the sampled records', 'at' => '2026-11-13T01:00:00Z', 'previousItems' => [['remarks' => 'PRIVATE']]],
            ['action' => 'interview', 'actor' => '000000000000000000000001', 'notes' => 'PRIVATE INTERVIEW'],
            ['action' => 'return', 'actor' => '000000000000000000000002', 'notes' => 'UNAUTHORIZED'],
        ]]);
        $card = (new AuditorAuditListing)->card($audit);
        $this->assertSame('Clarify the sampled records', $card['feedback']['notes']);
        $this->assertStringNotContainsString('PRIVATE', json_encode($card));
        $this->assertStringNotContainsString('UNAUTHORIZED', json_encode($card));
        $audit->status = 'accepted';
        $this->assertNull((new AuditorAuditListing)->card($audit)['feedback']);
        $audit->status = 'submitted'; $audit->reviewerId = null;
        $this->assertFalse((new AuditorAuditListing)->card($audit)['awaitingAtl']);
    }

    public function test_metrics_remain_global_to_the_authorized_collection_and_filters_are_literal(): void
    {
        $records = collect(range(1, 15))->map(fn ($i) => new Audit(['title' => $i === 1 ? 'A.* [sample]' : 'Routine '.$i,
            'auditCode' => 'REF-'.$i, 'departmentId' => '000000000000000000000001', 'departmentName' => 'Recorded Department',
            'status' => $i === 2 ? 'submitted' : 'draft', 'items' => []]));
        $presenter = new AuditorAuditListing;
        $data = $presenter->present($records, Request::create('/audits?search='.urlencode('.* [sample]')));
        $this->assertSame(15, $data['summary']['total']);
        $this->assertSame(1, $data['summary']['awaitingReview']);
        $this->assertSame(1, $data['audits']->total());
        $this->assertSame(1, $data['departments']->count());
        $page = $presenter->present($records, Request::create('/audits?page=2&status=draft'));
        $this->assertSame(14, $page['audits']->total());
        $this->assertSame(2, $page['audits']->count());
        $this->assertSame(0, $presenter->present($records, Request::create('/audits?departmentId=000000000000000000000099'))['audits']->total());
    }
}
