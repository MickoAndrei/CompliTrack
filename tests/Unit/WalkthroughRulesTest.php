<?php

namespace Tests\Unit;

use App\Models\ArchiveDocument;
use App\Services\{AuditChecklist, AuditPlanDocument};
use App\Support\{CarStatus, IndependenceProcedure, PortalDate};
use Tests\TestCase;

class WalkthroughRulesTest extends TestCase
{
    public function test_status_spelling_is_normalized_without_guessing_unknown_or_verification(): void
    {
        foreach (['In progress', 'in-progress', 'in_progress', 'INPROGRESS'] as $value) { $this->assertSame('in_progress', CarStatus::canonical($value)); }
        $this->assertSame('awaiting_verification', CarStatus::canonical('Awaiting Verification'));
        $this->assertSame('closed_pending', CarStatus::canonical('closed_pending'));
        $this->assertFalse(CarStatus::known('closed_pending'));
        $this->assertStringContainsString('Assigned auditor', CarStatus::nextAction('closed_pending'));
    }

    public function test_current_response_must_have_its_own_completed_review(): void
    {
        $items = [['result' => 'C', 'remarks' => 'Reviewed', 'evidence' => [['method' => 'digital-reference']]]];
        $review = ['decision' => 'reviewed', 'author' => 'assigned', 'at' => '2026-11-01T00:00:00Z'];
        $request = ['status' => 'reviewed', 'responses' => [['review' => $review], ['reference' => 'new original']]];
        $this->assertFalse(AuditChecklist::readiness($items, [$request])['eligible']);
        $request['responses'][1]['review'] = $review;
        $this->assertTrue(AuditChecklist::readiness($items, [$request])['eligible']);
        $request['status'] = 'needs_information';
        $this->assertFalse(AuditChecklist::readiness($items, [$request])['eligible']);
    }

    public function test_plan_eligibility_needs_versioned_purpose_and_controlled_reference_not_filename(): void
    {
        $document = new ArchiveDocument(['title' => 'Official Audit Plan.pdf', 'fileCode' => 'PLAN-1', 'ownershipVerified' => true]);
        $this->assertFalse(AuditPlanDocument::eligible($document));
        $document->auditPlanClassification = ['purpose' => 'audit-plan', 'verifiedBy' => 'atl', 'reason' => 'Verified controlled register', 'documentVersion' => 1, 'checksum' => null];
        $this->assertTrue(AuditPlanDocument::eligible($document));
        $document->versionNumber = 2;
        $this->assertFalse(AuditPlanDocument::eligible($document));
        foreach (['none', ' N/A ', 'T.B.D.', '---', 'nil', 'not applicable', '0000'] as $placeholder) { $this->assertFalse(IndependenceProcedure::meaningful($placeholder), $placeholder); }
        $this->assertTrue(IndependenceProcedure::meaningful('QMS-PRO-09 revision 2, Auditor independence'));
    }

    public function test_manila_timestamps_cross_midnight_but_date_only_values_do_not_shift(): void
    {
        $this->assertSame('Jan 1, 2027 7:59:59 AM PHT (UTC+08:00)', PortalDate::format('2026-12-31T23:59:59Z'));
        $this->assertSame('Dec 31, 2026', PortalDate::format('2026-12-31'));
        $this->assertSame('Not recorded', PortalDate::format('invalid'));
    }
}
