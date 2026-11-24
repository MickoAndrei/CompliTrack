<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\ReportGeneratorService;
use Illuminate\Http\Request;

class ReportController extends Controller
{
    public function __construct(private readonly ReportGeneratorService $reports)
    {
    }

    public function generate(Request $request)
    {
        if ($request->boolean('annual')) {
            $filter = $request->validate(['year' => 'required|integer|min:2000|max:2200',
                'planId' => 'nullable|regex:/^[a-f0-9]{24}$/i', 'format' => 'nullable|in:csv,json']);
            $report = app(\App\Services\AnnualAuditReport::class)->generate($request->user(), (int) $filter['year'], $filter['planId'] ?? null);
            if (($filter['format'] ?? '') === 'csv') {
                return response()->streamDownload(function () use ($report) {
                    $out = fopen('php://output', 'w');
                    fputcsv($out, ['Annual IQA report', $report['year'], $report['planId'] ?? 'All authorized plans']);
                    fputcsv($out, [$report['scope']]);
                    if ($report['rows']) {
                        fputcsv($out, array_keys($report['rows'][0]));
                        foreach ($report['rows'] as $row) {
                            $row['compliance'] = $row['compliance'] === null ? 'Not yet assessed' : $row['compliance'].'%';
                            fputcsv($out, array_map(static fn ($v) => is_string($v) && preg_match('/^[=+@\-\t\r]/', $v) ? "'".$v : $v, $row));
                        }
                    }
                    fclose($out);
                }, 'annual-iqa-'.$report['year'].'.csv', ['Content-Type' => 'text/csv; charset=UTF-8', 'Cache-Control' => 'no-store, private']);
            }
            return response()->json(['data' => $report])->header('Cache-Control', 'no-store, private');
        }
        $dates = $request->validate([
            'startDate' => ['sometimes', 'date'],
            'endDate' => ['sometimes', 'date', 'after_or_equal:startDate'],
        ]);

        return response()->json([
            'data' => $this->reports->generate(
                $request->user(),
                isset($dates['startDate'])
                    ? \Illuminate\Support\Carbon::parse($dates['startDate'])->startOfDay()
                    : null,
                isset($dates['endDate'])
                    ? \Illuminate\Support\Carbon::parse($dates['endDate'])->endOfDay()
                    : null,
            ),
        ]);
    }
}
