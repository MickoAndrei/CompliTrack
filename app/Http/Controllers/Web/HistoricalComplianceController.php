<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Services\RoleScopeService;
use App\Support\MongoId;
use Illuminate\Http\Request;

class HistoricalComplianceController extends Controller
{
    public function show(Request $request, string $id, RoleScopeService $scopes)
    {
        // Preserve the API's existing ownership boundary before rendering a legacy detail URL.
        abort_unless($request->user()->isActive, 403);
        $check = $scopes->checks($request->user())->where('_id', MongoId::parse($id))->firstOrFail();
        $attachments = app(\App\Services\AtlWorkspace::class)->evidence($request->user(), $check->linkedArchiveIds ?? []);

        return response()->view('auditor.check-details', compact('check', 'attachments'))->header('Cache-Control', 'private, no-store');
    }
}
