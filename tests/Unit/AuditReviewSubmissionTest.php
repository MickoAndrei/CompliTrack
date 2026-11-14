<?php

namespace Tests\Unit;

use App\Models\Audit;
use App\Services\AuditReviewQueue;
use PHPUnit\Framework\TestCase;

class AuditReviewSubmissionTest extends TestCase
{
    public function test_returning_a_first_submission_does_not_label_it_as_resubmitted(): void
    {
        $audit = new Audit(['history' => [['action' => 'submit', 'at' => '2026-11-01'], ['action' => 'return']]]);
        $this->assertSame(['at' => '2026-11-01', 'resubmitted' => false], AuditReviewQueue::submission($audit));
        $audit->history = [...$audit->history, ['action' => 'submit', 'at' => '2026-11-02']];
        $this->assertSame(['at' => '2026-11-02', 'resubmitted' => true], AuditReviewQueue::submission($audit));
        $this->assertSame(['at' => null, 'resubmitted' => false], AuditReviewQueue::submission(new Audit));
    }
}
