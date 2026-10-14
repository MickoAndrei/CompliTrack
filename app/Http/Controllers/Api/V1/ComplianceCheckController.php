<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\ComplianceCheck;
use App\Models\Department;
use App\Models\User;
use App\Services\ComplianceCheckService;
use App\Services\RoleScopeService;
use Illuminate\Http\Request;

class ComplianceCheckController extends Controller
{
    public function __construct(
        private readonly ComplianceCheckService $checks,
        private readonly RoleScopeService $scopes,
    ) {
    }

    public function index(Request $request)
    {
        $query = $this->scopes->checks($request->user());

        foreach (['correctiveStatus', 'result', 'referenceStandard'] as $field) {
            if ($request->filled($field)) {
                $query->where($field, $request->query($field));
            }
        }

        $page = $query->orderBy('createdAt', 'desc')
            ->paginate(min(max((int) $request->query('perPage', 20), 1), 100));
        $data = collect($page->items())->map(fn (ComplianceCheck $check) => $this->serialize($check));

        return response()->json([
            'data' => $data,
            'meta' => [
                'currentPage' => $page->currentPage(),
                'perPage' => $page->perPage(),
                'total' => $page->total(),
                'lastPage' => $page->lastPage(),
            ],
        ]);
    }

    public function show(Request $request, string $id)
    {
        $check = $this->scopes->checks($request->user())
            ->where('_id', \App\Support\MongoId::parse($id))
            ->firstOrFail();

        return response()->json(['data' => $this->serialize($check)]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'departmentId' => ['required', 'string', 'regex:/^[a-f0-9]{24}$/'],
            'auditeeId' => ['required', 'string', 'regex:/^[a-f0-9]{24}$/'],
            'referenceStandard' => ['required', 'in:ARTA-3days,ARTA-7days,ARTA-20days,ISO9001-9.2'],
            'result' => ['required', 'in:compliant,non_compliant'],
            'remarks' => ['nullable', 'string', 'max:5000'],
        ]);

        $check = $this->checks->create($request->user(), $data, $request);

        return response()->json(['data' => $this->serialize($check)], 201);
    }

    public function updateStatus(Request $request, string $id)
    {
        $data = $request->validate([
            'correctiveStatus' => ['required', 'in:open,in_progress,closed'],
            'remarks' => ['nullable', 'string', 'max:5000'],
        ]);

        $check = $this->checks->updateStatus(
            $request->user(),
            $id,
            $data['correctiveStatus'],
            $data['remarks'] ?? null,
            $request,
        );

        return response()->json(['data' => $this->serialize($check)]);
    }

    public function update()
    {
        abort(405, 'Compliance checks can only be changed through the status endpoint.');
    }

    public function destroy()
    {
        abort(405, 'Compliance checks are retained for audit purposes.');
    }

    private function serialize(ComplianceCheck $check): array
    {
        $data = $check->toArray();
        $data['_id'] = (string) $check->getKey();
        unset($data['id']);

        $department = Department::find($check->departmentId);
        $auditor = User::find($check->auditorId);
        $auditee = User::find($check->auditeeId);
        $data['departmentName'] = $department?->departmentName;
        $data['auditorName'] = $auditor?->fullName;
        $data['auditeeName'] = $auditee?->fullName;

        return $data;
    }
}
