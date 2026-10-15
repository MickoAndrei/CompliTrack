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
