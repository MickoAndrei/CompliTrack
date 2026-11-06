<?php

namespace Tests\Feature;

use App\Models\{ComplianceCheck, User};
use Tests\TestCase;

class AuditeeDashboardViewTest extends TestCase
{
    private function dashboard(array $data = [])
    {
        $this->actingAs(new User(['fullName' => 'Dashboard test', 'roleId' => 3, 'isActive' => true]));
        return $this->view('auditee.dashboard', $data + [
            'checks' => collect(), 'published' => collect(), 'requests' => collect(), 'notifications' => collect(),
        ]);
    }

    public function test_recent_activity_does_not_repeat_a_notified_legacy_check(): void
    {
        $id = new \MongoDB\BSON\ObjectId();
        $check = new ComplianceCheck(['checkCode' => 'DUPLICATE-CHECK', 'result' => 'compliant']);
        $check->setAttribute('_id', $id);
        $check->setAttribute('createdAt', now());
        $note = new \App\Models\Notification(['title' => 'Result ready', 'message' => 'Reviewed result', 'relatedCheckId' => $id]);
        $note->setAttribute('createdAt', now());
        $this->dashboard(['checks' => collect([$check]), 'notifications' => collect([$note])])
            ->assertSee('Result ready')->assertSee('Reviewed result')->assertDontSee('DUPLICATE-CHECK');
    }
    public function test_empty_dashboard_explains_each_empty_state(): void
    {
        $this->dashboard()->assertSee("You're up to date", false)
            ->assertSee('No pending requests')->assertSee('No activity yet')
            ->assertDontSee('No accepted checklist decisions have been shared with you yet.')
            ->assertSeeInOrder(['My Next Actions', 'Compliance Summary', 'Evidence Requests', 'Recent Activity']);
    }

    public function test_decisions_and_legacy_counts_remain_separate_and_locked_requests_are_not_actions(): void
    {
        $check = new ComplianceCheck(['checkCode' => 'CHECK-TEST', 'result' => 'non_compliant', 'correctiveStatus' => 'open']);
        $check->setAttribute('_id', new \MongoDB\BSON\ObjectId());
        $view = $this->dashboard([
            'checks' => collect([$check]),
            'published' => collect([['result' => 'N'], ['result' => 'O']]),
            'requests' => collect([
                ['id' => 'locked-test', 'auditCode' => 'LOCKED-TEST', 'status' => 'requested', 'auditStatus' => 'accepted', 'description' => 'Locked evidence'],
                ['id' => 'ready-test', 'auditCode' => 'READY-TEST', 'status' => 'needs_information', 'auditStatus' => 'returned', 'description' => 'Ready evidence'],
            ]),
        ]);
        $view->assertSee('2 actions')->assertSee('1 legacy checks reported separately')
            ->assertSee('Shared accepted checklist decisions')->assertDontSee('Legacy compliance checks')->assertDontSee('Reported separately.')
            ->assertSee('Observation')->assertSee('Read-only: audit under review or locked')
            ->assertSee('/auditee/finding-details/'.$check->getKey())
            ->assertSee('/auditee/evidence-requests#request-ready-test')
            ->assertDontSee('C ?')->assertDontSee('N ?');
        $html = (string) $view;
        $actions = substr($html, strpos($html, 'id="next-actions-title"'), strpos($html, 'id="compliance-title"') - strpos($html, 'id="next-actions-title"'));
        $this->assertStringNotContainsString('LOCKED-TEST', $actions);
        $this->assertStringContainsString('READY-TEST', $actions);
    }
}
