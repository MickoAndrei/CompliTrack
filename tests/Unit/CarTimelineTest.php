<?php

namespace Tests\Unit;

use App\Models\ComplianceCheck;
use App\Support\CarTimeline;
use MongoDB\BSON\ObjectId;
use MongoDB\BSON\UTCDateTime;
use Tests\TestCase;

class CarTimelineTest extends TestCase
{
    public function test_repeated_events_and_resubmissions_are_ordered_without_treating_a_past_closure_as_current(): void
    {
        $auditor = new ObjectId();
        $auditee = new ObjectId();
        $check = new ComplianceCheck(['result' => 'non_compliant', 'correctiveStatus' => 'open',
            'auditorId' => $auditor, 'auditeeId' => $auditee, 'statusHistory' => [
                ['status' => 'closed', 'timestamp' => new UTCDateTime(strtotime('2026-11-03 UTC') * 1000), 'changedByUserId' => $auditor, 'remarks' => 'Private note'],
                ['status' => 'open', 'timestamp' => '2026-11-01T00:00:00Z'],
                ['status' => 'in_progress', 'timestamp' => ['$date' => '2026-11-02T00:00:00Z']],
                ['status' => 'open', 'timestamp' => '2026-11-04T00:00:00Z', 'changedByUserId' => $auditor],
            ]]);
        $check->setAttribute('evidenceResponses', [
            ['at' => '2026-11-02T12:00:00Z', 'author' => (string) $auditee],
            ['at' => '2026-11-04T12:00:00Z', 'author' => (string) $auditee],
        ]);
        $timeline = collect(CarTimeline::forCheck($check, [(string) $auditor => 2, (string) $auditee => 3]));
        $this->assertSame(['open', 'in_progress', 'closed', 'open'], $timeline->where('kind', 'status')->pluck('status')->all());
        $this->assertSame(['open'], $timeline->where('state', 'current')->pluck('status')->all());
        $this->assertSame(['Corrective evidence submitted', 'Corrective evidence resubmitted'], $timeline->where('kind', 'evidence')->pluck('label')->all());
        $this->assertSame('completed', $timeline->where('kind', 'status')->firstWhere('status', 'closed')['state']);
        $this->assertSame('Auditee', $timeline->firstWhere('kind', 'evidence')['role']);
        $this->assertSame('Auditor', $timeline->where('kind', 'status')->firstWhere('status', 'closed')['role']);
        $this->assertSame(['in_progress', 'closed'], $timeline->where('state', 'upcoming')->pluck('status')->all());
        $this->assertStringNotContainsString('Private note', json_encode($timeline));
    }

    public function test_missing_history_does_not_invent_dates_and_compliant_records_have_no_timeline(): void
    {
        $check = new ComplianceCheck(['result' => 'non_compliant', 'correctiveStatus' => 'in_progress']);
        $timeline = CarTimeline::forCheck($check);
        $this->assertSame('current', $timeline[0]['state']);
        $this->assertNull($timeline[0]['at']);
        $this->assertNull($timeline[0]['role']);
        $this->assertSame('Awaiting auditor verification', $timeline[1]['label']);
        $check->result = 'compliant';
        $this->assertSame([], CarTimeline::forCheck($check));
        $this->assertNull(CarTimeline::date('not-a-timestamp'));
        $this->assertSame('2026-11-03', CarTimeline::date(['$date' => ['$numberLong' => (string) (strtotime('2026-11-03 UTC') * 1000)]])->format('Y-m-d'));
    }

    public function test_legacy_returned_events_are_not_collapsed_or_relabelled_as_closed(): void
    {
        $check = new ComplianceCheck(['result' => 'non_compliant', 'correctiveStatus' => 'in_progress', 'statusHistory' => [
            ['status' => 'in_progress', 'timestamp' => '2026-11-01T00:00:00Z'],
            ['status' => 'returned_for_revision', 'timestamp' => '2026-11-02T00:00:00Z'],
            ['status' => 'in_progress', 'timestamp' => '2026-11-03T00:00:00Z'],
        ]]);
        $timeline = collect(CarTimeline::forCheck($check));
        $this->assertSame(['in_progress', 'returned_for_revision', 'in_progress'], $timeline->where('kind', 'status')->pluck('status')->all());
        $this->assertSame('Returned for revision', $timeline[1]['label']);
        $this->assertSame(['in_progress'], $timeline->where('state', 'current')->pluck('status')->all());
        $check->correctiveStatus = 'closed_pending';
        $timeline = collect(CarTimeline::forCheck($check));
        $this->assertSame('Closed pending', $timeline->firstWhere('state', 'current')['label']);
        $this->assertSame('closed_pending', $check->correctiveStatus);
    }
}
