<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\{Department, User};
use App\Services\AtlWorkspace;
use App\Support\MongoId;
use Illuminate\Http\Request;

class AtlWorkspaceController extends Controller
{
    public function records(Request $request, AtlWorkspace $workspace)
    {
        return $request->boolean('monitor') ? $this->corrections($request, $workspace) : view('admin.compliance-records');
    }

    public function summary(Request $request, AtlWorkspace $workspace)
    {
        $request->validate(['page' => 'sometimes|integer|min:1']);
        return response()->json(['data' => $workspace->dashboard($request->user(), $request->integer('page', 1))])
            ->header('Cache-Control', 'no-store');
    }

    public function corrections(Request $request, AtlWorkspace $workspace)
    {
        $request->validate(['status' => 'nullable|in:open,in_progress,closed', 'overdue' => 'nullable|boolean', 'page' => 'nullable|integer|min:1']);
        $detail = $request->filled('car') ? $workspace->cars($request->user())->where('_id', MongoId::parse($request->query('car')))->firstOrFail() : null;
        $evidence = $detail ? $workspace->evidence($request->user(), array_merge($detail->linkedArchiveIds ?? [], collect($detail->evidenceResponses ?? [])->pluck('archiveId')->filter()->all())) : [];
        if ($request->boolean('history')) {
            abort_unless($detail, 422, 'Choose a corrective action to view its history.');
            return response()->view('admin.partials.car-history', compact('detail', 'workspace', 'evidence'))->header('Cache-Control', 'no-store');
        }
        $query = $workspace->cars($request->user());
        if ($request->filled('status')) { $query->where('correctiveStatus', $request->query('status')); }
        // Calculate deadlines consistently for mixed stored date values; a missing due date is never guessed.
        if ($request->boolean('overdue')) {
            $ids = (clone $query)->get(['dueDate', 'correctiveStatus'])->filter(fn ($c) => $workspace->isOverdue($c))->pluck('_id')->all();
            $query->whereIn('_id', $ids);
        }
        $cars = $query->orderBy('dueDate')->paginate(20)->withQueryString();
        $departments = Department::whereIn('_id', app(\App\Services\QmsArchiveAccess::class)->ids($cars->pluck('departmentId')->all()))
            ->get(['departmentName'])->keyBy(fn ($d) => (string) $d->getKey());
        $people = User::whereIn('_id', app(\App\Services\QmsArchiveAccess::class)->ids($cars->pluck('auditeeId')->merge($cars->pluck('auditorId'))->all()))
            ->get(['fullName'])->keyBy(fn ($u) => (string) $u->getKey());
        return view('admin.corrective-monitor', compact('cars', 'detail', 'workspace', 'departments', 'people', 'evidence'));
    }
}
