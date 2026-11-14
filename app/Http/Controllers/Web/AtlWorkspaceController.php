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
        $request->validate(['page' => 'sometimes|integer|min:1', 'perPage' => 'sometimes|integer|in:5,10']);
        return response()->json(['data' => $workspace->dashboard($request->user(), $request->integer('page', 1), $request->integer('perPage', 5))])
            ->header('Cache-Control', 'no-store');
    }

    public function corrections(Request $request, AtlWorkspace $workspace)
    {
        $register = app(\App\Services\CarRegister::class);
        $data = $register->index($request);
        $detail = $request->filled('car') ? $register->detail($request->user(), $request->query('car')) : null;
        if ($request->boolean('history')) {
            abort_unless($detail, 422, 'Choose a corrective action to view its history.');
            return response()->view('admin.partials.car-history', compact('detail'))->header('Cache-Control', 'no-store, private');
        }
        if ($request->query('export') === 'log') {
            return response()->view('admin.car-log-export', $data)->header('Cache-Control', 'no-store, private');
        }
        return response()->view('admin.corrective-monitor', $data + compact('detail'))->header('Cache-Control', 'no-store, private');
    }
}