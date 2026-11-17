<?php

namespace Tests\Feature;

use App\Models\{ArchiveDocument, Audit, ComplianceCheck, Department, User};
use Illuminate\Support\Facades\{DB, File};
use Tests\TestCase;

class LiveRetestReportTest extends TestCase
{
    private ?string $database = null;

    protected function setUp(): void
    {
        parent::setUp();
        if (getenv('RUN_MONGODB_AUDIT_TESTS') !== '1') { $this->markTestSkipped('Isolated MongoDB required.'); }
        $this->database = 'ct_report_retest_'.bin2hex(random_bytes(6));
        config(['database.connections.mongodb.database' => $this->database]); DB::purge('mongodb');
        $this->assertSame($this->database, DB::connection('mongodb')->getMongoDB()->getDatabaseName());
        $this->withoutVite();
    }

    protected function tearDown(): void
    {
        if ($this->database) {
            $db = DB::connection('mongodb')->getMongoDB();
            if ($db->getDatabaseName() === $this->database && str_starts_with($this->database, 'ct_report_retest_')) { $db->drop(); }
            DB::purge('mongodb');
        }
        parent::tearDown();
    }

    public function test_real_report_routes_export_every_filtered_metric_and_isolate_scope_and_empty_results(): void
    {
        $units = collect(config('qms.departments'))->map(fn ($name, $code) => Department::create(['departmentCode' => $code,
            'departmentName' => $name, 'collegeName' => 'CET', 'unitType' => 'academic_department', 'ownershipVerified' => true, 'isActive' => true]))->values();
        $units->push(Department::create(['departmentCode' => 'CET-DEAN', 'departmentName' => 'Office of the Dean', 'collegeName' => 'CET',
            'unitType' => 'college_office', 'ownershipVerified' => true, 'isActive' => true]));
        $atl = User::create(['fullName' => 'Isolated report ATL', 'roleId' => 1, 'isActive' => true]);
        $other = User::create(['fullName' => 'Isolated other ATL', 'roleId' => 1, 'isActive' => true]);
        $auditor = User::create(['fullName' => 'Isolated auditor', 'roleId' => 2, 'isActive' => true]);
        $plan = ArchiveDocument::create(['title' => 'Isolated CET annual plan', 'departmentId' => $units[5]->getKey(), 'ownershipVerified' => true]);
        $plan->forceFill(['auditPlan' => ['status' => 'accepted', 'header' => ['scheduleStart' => '2026-01-01', 'scheduleEnd' => '2026-12-31',
            'unitIds' => $units->map(fn ($u) => (string) $u->getKey())->all(), 'reviewerIds' => [(string) $atl->getKey()]]]])->save();
        $audits = [];
        foreach (['accepted', 'ready', 'submitted', 'returned', 'draft', 'accepted'] as $i => $status) {
            $audits[] = Audit::create(['auditCode' => 'REPORT-'.$i, 'planId' => $plan->getKey(), 'departmentId' => $units[$i]->getKey(),
                'departmentName' => $units[$i]->departmentName, 'auditorId' => $auditor->getKey(), 'reviewerId' => $atl->getKey(),
                'schedule' => '2026-12-31', 'status' => $status, 'items' => $i === 0 ? [['result' => 'C'], ['result' => 'C'], ['result' => 'N'], ['result' => 'O']] : [['result' => 'O']],
                'evidenceRequests' => $i === 1 ? [['status' => 'response_submitted', 'responses' => [['at' => '2026-11-01T00:00:00Z']]]] : []]);
        }
        $car = fn ($i, $status, $extra = []) => ComplianceCheck::create($extra + ['departmentId' => $units[$i]->getKey(), 'result' => 'non_compliant',
            'correctiveStatus' => $status, 'dueDate' => '2020-01-01']);
        foreach ([0 => 'in_progress', 2 => 'awaiting_verification', 5 => 'closed'] as $i => $status) {
            $car($i, $status)->forceFill(['sourceAuditId' => $audits[$i]->getKey(), 'sourceItemId' => 'original'])->save();
        }
        $car(0, 'open', ['createdAt' => '2026-01-03T00:00:00Z']);
        ComplianceCheck::create(['departmentId' => $units[0]->getKey(), 'result' => 'compliant', 'createdAt' => '2026-01-03T00:00:00Z']);
        Audit::create(['planId' => $plan->getKey(), 'departmentId' => $units[0]->getKey(), 'reviewerId' => $other->getKey(),
            'schedule' => '2026-01-01', 'status' => 'accepted', 'items' => array_fill(0, 9, ['result' => 'C'])]);
        $this->actingAs($atl);
        $cases = ['selected' => ['year' => 2026, 'planId' => (string) $plan->getKey()], 'all' => ['year' => 2026],
            'empty' => ['year' => 2024, 'planId' => (string) $plan->getKey()]];
        $snapshots = [];
        foreach ($cases as $case => $filter) {
            $query = http_build_query(['annual' => 1] + $filter);
            $response = $this->getJson('/api/v1/reports?'.$query)->assertOk();
            $report = $response->json('data');
            $download = $this->get('/api/v1/reports?'.$query.'&format=csv')->assertOk();
            $this->assertStringContainsString('attachment;', $download->headers->get('Content-Disposition'));
            $csv = $download->streamedContent();
            $this->compareCsv($report, $csv);
            $snapshots[$case] = ['filter' => $filter, 'report' => $report, 'csv' => $csv];
        }
        $selected = $snapshots['selected']['report']['rows'];
        $this->assertCount(6, $selected);
        $this->assertSame('Office of the Dean', $selected[5]['unit']);
        $this->assertSame([2, 1, 1, 3, 66.7], array_map(fn ($key) => $selected[0][$key], ['C', 'N', 'O', 'denominator', 'compliance']));
        $this->assertSame([1, 1, 0], array_map(fn ($key) => $selected[0][$key], ['openCars', 'overdueCars', 'legacyChecks']));
        $this->assertSame(1, $selected[1]['outstandingEvidence']);
        $this->assertSame(1, $selected[2]['awaitingVerificationCars']);
        $this->assertSame(1, $selected[5]['closedCars']);
        $this->assertNull($selected[5]['compliance']);
        $this->assertSame(2, $snapshots['all']['report']['rows'][0]['legacyChecks']);
        $this->assertSame(1, $snapshots['all']['report']['rows'][0]['legacyCompliant']);
        $this->assertSame([], $snapshots['empty']['report']['rows']);
        $private = ArchiveDocument::create(['departmentId' => $units[0]->getKey(), 'ownershipVerified' => true]);
        $private->forceFill(['auditPlan' => ['header' => ['reviewerIds' => [(string) $other->getKey()]]]])->save();
        foreach (['json', 'csv'] as $format) {
            $this->get('/api/v1/reports?annual=1&year=2026&planId='.$private->getKey().'&format='.$format)->assertNotFound();
        }
        // The same controlled unit identity can have a malicious legacy display name; spreadsheet cells must remain inert.
        foreach (["=1+1, \"Quoted\"\nDepartment", '+SUM(1,2)', '-1+2', '@SUM(1)', "\t=1+1", "\r=1+1"] as $name) {
            $units[0]->update(['departmentName' => $name]);
            $csv = $this->get('/api/v1/reports?annual=1&year=2026&format=csv')->assertOk()->streamedContent();
            $rows = $this->parseCsv($csv);
            $this->assertSame("'".$name, $rows[3][1]);
        }
        $units[0]->update(['departmentName' => config('qms.departments')[$units[0]->departmentCode]]);
        $page = $this->get('/admin/reports')->assertOk();
        if (getenv('WRITE_RETEST_FIXTURES') === '1') {
            $dir = storage_path('framework/testing/live-retest'); File::ensureDirectoryExists($dir);
            file_put_contents($dir.'/reports.html', $page->getContent());
            file_put_contents($dir.'/reports.json', json_encode($snapshots));
            foreach ($snapshots as $case => $snapshot) { file_put_contents($dir.'/'.$case.'.csv', $snapshot['csv']); }
        }
        $this->actingAs($auditor)->get('/api/v1/reports?annual=1&year=2026&format=csv')->assertForbidden();
    }

    private function parseCsv(string $csv): array
    {
        $stream = fopen('php://temp', 'r+'); fwrite($stream, $csv); rewind($stream); $rows = [];
        while (($row = fgetcsv($stream)) !== false) { $rows[] = $row; } fclose($stream); return $rows;
    }

    private function compareCsv(array $report, string $csv): void
    {
        $rows = $this->parseCsv($csv);
        $this->assertSame('Annual IQA report', $rows[0][0]); $this->assertSame((string) $report['year'], $rows[0][1]);
        $this->assertSame($report['scope'], $rows[1][0]);
        $this->assertCount(count($report['rows']) + ($report['rows'] ? 3 : 2), $rows);
        foreach ($report['rows'] as $i => $row) {
            $this->assertSame(array_keys($row), $rows[2]);
            $row['compliance'] = $row['compliance'] === null ? 'Not yet assessed' : $row['compliance'].'%';
            $this->assertSame(array_map('strval', array_values($row)), $rows[$i + 3]);
        }
    }
}
