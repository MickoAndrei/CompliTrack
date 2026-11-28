<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\{Campus, College, Department};
use Illuminate\Http\Request;

class OrganizationController extends Controller
{
    public function index(Request $request)
    {
        abort_unless((int) $request->user()->roleId === 0 && $request->user()->isActive, 403);
        $request->validate(['collegeId' => 'nullable|string|regex:/^[a-f0-9]{24}$/i', 'search' => 'nullable|string|max:100']);
        $campuses = Campus::query()->orderBy('campusName')->get();
        $colleges = College::query()->orderBy('collegeName')->get();
        $search = trim((string) $request->query('search', ''));
        $selectedId = (string) $request->query('collegeId', '');
        $selected = $selectedId !== '' ? $colleges->first(fn ($unit) => (string) $unit->getKey() === $selectedId) : $colleges->first();
        abort_if($selectedId !== '' && !$selected, 404);
        $units = $selected ? Department::query()->where('collegeId', $selected->getKey())->orderBy('departmentName')->get() : collect();
        if ($search !== '') {
            $units = $units->filter(fn ($unit) => mb_stripos($unit->departmentName.' '.$unit->departmentCode, $search) !== false);
        }
        return response()->view('super-admin.organizations', compact('campuses', 'colleges', 'selected', 'units', 'search'))->header('Cache-Control', 'private, no-store');
    }
}
