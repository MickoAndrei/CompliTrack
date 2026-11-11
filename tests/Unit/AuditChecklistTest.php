<?php

namespace Tests\Unit;

use App\Services\AuditChecklist;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class AuditChecklistTest extends TestCase
{
    public function test_progress_counts_only_saved_nco_results(): void
    {
        $progress = AuditChecklist::progress([
            ['result' => 'C'], ['result' => 'C'], ['result' => 'N'], [], ['result' => null],
        ]);
        $this->assertSame(3, $progress['evaluated']);
        $this->assertSame(2, $progress['pending']);
        $this->assertEquals(60, $progress['percent']);
        $this->assertSame(2, $progress['C']);
        $this->assertEquals(0, AuditChecklist::progress([])['percent']);
    }

    public function test_reference_only_reviews_allow_submission_without_uploads(): void
    {
        AuditChecklist::validateSubmission([['result' => 'C', 'remarks' => 'Reviewed original',
            'evidence' => [['method' => 'physical-original', 'notes' => 'Signature verified', 'signature' => 'verified']]]]);
        $this->addToAssertionCount(1);
    }

    public function test_pending_items_cannot_be_submitted(): void
    {
        $this->expectException(ValidationException::class);
        AuditChecklist::validateSubmission([['result' => null]]);
    }

    public function test_nonconformance_requires_a_finding(): void
    {
        $this->expectException(ValidationException::class);
        AuditChecklist::validateSubmission([['result' => 'N', 'remarks' => 'Missing required record', 'evidence' => [['notes' => 'Sample reviewed']]]]);
    }

    public function test_ofi_is_not_a_compliance_result(): void
    {
        $this->expectException(ValidationException::class);
        AuditChecklist::validateSubmission([['result' => 'OFI', 'remarks' => 'Review', 'evidence' => [['notes' => 'Reviewed']]]]);
    }
}
