<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\{AtlWorkspace, AuditChecklist, AuditorAuditListing};
use App\Support\MongoId;
use Illuminate\Http\Request;

/** Mobile projection over the exact same scoped audit records as the web workspace. */
class AuditWorkspaceController extends Controller
{
    public function index(Request $request)
    {
        $request->validate(['search' => 'nullable|string|max:160', 'status' => 'nullable|in:draft,ready,in_progress,submitted,returned,accepted', 'page' => 'nullable|integer|min:1', 'perPage' => 'nullable|integer|min:1|max:100']);
        $result = app(AuditorAuditListing::class)->page($request->user(), $request);
        return response()->json(['data' => $result['audits']->items(), 'summary' => $result['summary'], 'page' => $result['audits']->currentPage(), 'lastPage' => $result['audits']->lastPage()])->header('Cache-Control', 'private, no-store');
    }

    public function show(Request $request, string $id)
    {
        $audit = app(AtlWorkspace::class)->audits($request->user())->where('_id', MongoId::parse($id))->firstOrFail();
        $items = $audit->items ?? [];
        $card = app(AuditorAuditListing::class)->card($audit);
        return response()->json(['data' => $card + [
            'version' => (int) $audit->version,
            'storedStatus' => $audit->status,
            'scope' => $audit->scope,
            'criteria' => $audit->criteria,
            'items' => array_values($items),
            'readiness' => AuditChecklist::readiness($items, $audit->evidenceRequests ?? []),
            'canEdit' => (int) $request->user()->roleId === 2 && in_array($audit->status, ['draft', 'ready', 'returned'], true),
            'canReview' => (int) $request->user()->roleId === 1 && $audit->status === 'submitted',
        ]])->header('Cache-Control', 'private, no-store');
    }
}
