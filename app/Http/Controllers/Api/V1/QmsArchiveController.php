<?php
namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\{ArchiveDocument, Audit, AuditLog, College, Department, QmsCategory, User};
use App\Services\{AuditLogService, QmsArchiveAccess, QmsArchivePresenter};
use App\Support\MongoId;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class QmsArchiveController extends Controller
{
    public function __construct(private QmsArchiveAccess $access, private QmsArchivePresenter $presenter, private AuditLogService $logs) {}

    public function auditFolders(Request $request)
    {
        $path = $request->validate(array_fill_keys(\App\Services\QmsAuditArchive::PATH, 'nullable|string|regex:/^[a-f0-9]{24}$/i'));
        return response()->json(app(\App\Services\QmsAuditArchive::class)->tree($request->user(), $path))->header('Cache-Control', 'private, no-store');
    }

    public function tree(Request $request)
    {
        $documents = $this->access->summaryDocuments($request->user())->get(['departmentId', 'categoryId', 'recordType', 'fileSize']);
        $units = $this->access->organizationalUnits($request->user(), true)->orderBy('departmentName')->get();
        if ((int) $request->user()->roleId === 2) {
            $unitIds = $documents->pluck('departmentId')->map('strval')->all();
            $units = $units->filter(fn ($unit) => in_array((string) $unit->getKey(), $unitIds, true));
        }
        if ((int) $request->user()->roleId === 1) {
            $sharedUnits = Department::whereIn('_id', $this->access->ids($documents->pluck('departmentId')->all()))
                ->where('ownershipVerified', true)->where('isActive', true)->get();
            $units = $units->merge($sharedUnits)->unique(fn ($unit) => (string) $unit->getKey())->sortBy('departmentName');
        }
        $collegeIds = $this->access->ids($units->pluck('collegeId')->filter()->unique()->all());
        $categories = QmsCategory::whereIn('collegeId', $collegeIds)->where('isActive', true)->orderBy('code')->get();
        $folders = app(\App\Services\QmsFolderAccess::class);
        $evidenceCategories = $documents->pluck('categoryId')->map('strval')->all();
        foreach ($categories as $category) {
            $parent = $category; $seen = [];
            while ($parent && in_array((string) $parent->getKey(), $evidenceCategories, true) && !in_array((string) $parent->getKey(), $seen, true)) {
                $seen[] = (string) $parent->getKey();
                if ($parent->parentId) { $evidenceCategories[] = (string) $parent->parentId; }
                $parent = $categories->first(fn ($item) => (string) $item->getKey() === (string) $parent->parentId);
            }
        }
        $categories = $categories->filter(function ($category) use ($units, $folders, $request, $evidenceCategories) {
            if (!$category->userManaged) { return true; }
            $unit = $units->first(fn ($unit) => (string) $unit->getKey() === (string) $category->departmentId);
            return $unit && $folders->permissions($request->user(), $unit, $category)['view']
                && ((int) $request->user()->roleId !== 2 || in_array((string) $category->getKey(), $evidenceCategories, true));
        });
        return response()->json([
            'campus' => config('qms.campus'),
            'colleges' => College::whereIn('_id', $collegeIds)->get()->map(fn ($college) => ['_id' => (string) $college->getKey(), 'name' => $college->collegeName, 'code' => $college->collegeCode])->values(),
            'units' => $units->map(fn ($unit) => ['_id' => (string) $unit->getKey(), 'collegeId' => (string) $unit->collegeId,
                'name' => $unit->departmentName, 'unitType' => $unit->unitType, 'canCreate' => $this->access->canCreate($request->user(), $unit),
                'systemManaged' => true, 'folderPermissions' => $folders->permissions($request->user(), $unit),
                'count' => $documents->filter(fn ($document) => (string) $document->departmentId === (string) $unit->getKey())->count()])->values(),
            'categories' => $categories->map(fn ($category) => ['_id' => (string) $category->getKey(), 'collegeId' => (string) $category->collegeId,
                'departmentId' => $category->departmentId ? (string) $category->departmentId : null,
                'userManaged' => (bool) $category->userManaged, 'classification' => $category->classification ?: 'department',
                'folderPermissions' => $units->mapWithKeys(fn ($unit) => [(string) $unit->getKey() => $folders->permissions($request->user(), $unit, $category)])->all(),
                'parentId' => $category->parentId ? (string) $category->parentId : null, 'code' => $category->code, 'name' => $category->name,
                'unitType' => $category->unitType, 'provisional' => (bool) $category->provisional,
                'counts' => $units->mapWithKeys(fn ($unit) => [(string) $unit->getKey() => $documents->filter(fn ($document) => (string) $document->departmentId === (string) $unit->getKey()
                    && in_array((string) $document->categoryId, [(string) $category->getKey(), ...$categories->where('parentId', $category->getKey())->map(fn ($child) => (string) $child->getKey())->all()], true))->count()])->all()])->values(),
            'total' => $documents->count(),
            'archiveStorageBytes' => (int) $documents->filter(fn ($document) => !$document->recordType || $document->recordType === 'file')->sum('fileSize'),
            'canManageOrganization' => $this->access->isAuditTeamLeader($request->user()),
            'canConfigureCategories' => false,
            'canViewDocuments' => (int) $request->user()->roleId !== 0,
            'preferredView' => (int) $request->user()->roleId === 2 ? 'audit' : 'my',
            'ownershipReviewRequired' => (int) $request->user()->roleId !== 0
                && $request->user()->archiveResponsibility !== 'cluster_administrator'
                && Department::find($request->user()->departmentId)?->unitType === 'legacy_unclassified',
        ])->header('Cache-Control', 'private, no-store');
    }

    public function show(Request $request, string $id)
    {
        $context = app(\App\Services\QmsAuditArchive::class);
        $document = $context->applyContext($this->access->documents($request->user()), $request->user(), $request)->where('_id', MongoId::parse($id))->firstOrFail();
        $this->logs->record($request, 'Viewed QMS document metadata', 'archive_document', $document->getKey());
        $group = MongoId::parse((string) ($document->versionGroupId ?? $document->getKey()));
        $versions = $context->applyContext($this->access->documents($request->user()), $request->user(), $request)->where(fn ($query) => $query->where('versionGroupId', $group)->orWhere('_id', $group))
            ->orderBy('versionNumber', 'desc')->get()->map(fn ($version) => $this->presenter->document($version, $request->user()));
        $activity = AuditLog::where('entityType', 'archive_document')->where('entityId', MongoId::parse((string) $document->getKey()))->orderBy('createdAt', 'desc')->limit(30)
            ->get(['action', 'userName', 'createdAt'])->map(fn ($entry) => ['action' => $entry->action, 'actor' => $entry->userName, 'at' => $entry->createdAt?->toIso8601String()]);
        $options = ['accounts' => [], 'audits' => []];
        if ($this->access->canManage($request->user(), $document)) {
            $unitValues = [MongoId::parse((string) $document->departmentId), (string) $document->departmentId];
            $collegeValues = $document->collegeId ? [MongoId::parse((string) $document->collegeId), (string) $document->collegeId] : [];
            $options['accounts'] = User::where('isActive', true)->where('roleId', '!=', 2)->whereRaw(['$or' => [
                ['departmentId' => ['$in' => $unitValues]], ['archiveUnitIds' => ['$in' => $unitValues]],
                ['$and' => [['archiveCollegeIds' => ['$in' => $collegeValues]], ['archiveResponsibility' => ['$in' => ['college_dean', 'college_secretary', 'cluster_administrator']]]]],
            ]])->get(['fullName'])->map(fn ($user) => ['_id' => (string) $user->getKey(), 'name' => $user->fullName]);
            $options['audits'] = Audit::where('departmentId', MongoId::parse((string) $document->departmentId))->get(['auditCode', 'auditorId'])
                ->filter(function ($audit) use ($document) {
                    $auditor = User::find($audit->auditorId);
                    return $auditor && $auditor->isActive && (int) $auditor->roleId === 2
                        && in_array((string) $document->departmentId, array_map('strval', $auditor->assignedDepartmentIds ?? []), true);
                })->map(fn ($audit) => ['_id' => (string) $audit->getKey(), 'reference' => $audit->auditCode ?: 'Assigned audit'])->values();
        }
        return response()->json(['data' => $this->presenter->document($document, $request->user()), 'versions' => $versions, 'activity' => $activity, 'accessOptions' => $options])->header('Cache-Control', 'private, no-store');
    }

    public function star(Request $request, string $id)
    {
        $document = $this->access->documents($request->user())->where('_id', MongoId::parse($id))->firstOrFail();
        $data = $request->validate(['starred' => 'required|boolean']);
        $user = $request->user();
        $user->getConnection()->getMongoDB()->selectCollection($user->getTable())->updateOne(
            ['_id' => MongoId::parse((string) $user->getKey())],
            [$data['starred'] ? '$addToSet' : '$pull' => ['qmsStarredIds' => (string) $document->getKey()]]
        );
        return response()->json(['isStarred' => $data['starred']])->header('Cache-Control', 'private, no-store');
    }

    public function permissions(Request $request, string $id)
    {
        $document = $this->access->documents($request->user())->where('_id', MongoId::parse($id))->firstOrFail();
        abort_unless($this->access->canManage($request->user(), $document), 403);
        $data = $request->validate(['visibility' => 'required|in:owner,department,college,restricted', 'downloadAllowed' => 'required|boolean',
            'accessUserIds' => 'present|array|max:100', 'accessUserIds.*' => 'string|regex:/^[a-f0-9]{24}$/i|distinct']);
        $data['accessUserIds'] = $this->access->ids($data['accessUserIds']);
        $folder = $document->categoryId ? QmsCategory::find($document->categoryId) : null;
        if ($folder && ($folder->userManaged || ($folder->classification && $folder->classification !== 'department'))) {
            abort_unless(in_array($data['visibility'], app(\App\Services\QmsFolderAccess::class)->allowedVisibility($folder), true), 422, 'Document visibility cannot exceed its folder classification.');
        }
        $document->fill($data)->save();
        $this->logs->record($request, 'Updated QMS document permissions', 'archive_document', $document->getKey());
        return response()->json(['data' => $this->presenter->document($document, $request->user())]);
    }

    public function share(Request $request, string $id)
    {
        $document = $this->access->documents($request->user())->where('_id', MongoId::parse($id))->firstOrFail();
        abort_unless($this->access->canManage($request->user(), $document), 403);
        $data = $request->validate(['auditId' => 'required|string|regex:/^[a-f0-9]{24}$/i']);
        $audit = Audit::where('_id', MongoId::parse($data['auditId']))->where('departmentId', MongoId::parse((string) $document->departmentId))->firstOrFail();
        $auditor = User::find($audit->auditorId);
        abort_unless($auditor && $auditor->isActive && (int) $auditor->roleId === 2
            && in_array((string) $document->departmentId, array_map('strval', $auditor->assignedDepartmentIds ?? []), true), 403);
        $document->getConnection()->getMongoDB()->selectCollection($document->getTable())->updateOne(['_id' => MongoId::parse((string) $document->getKey())], ['$addToSet' => ['sharedAuditIds' => MongoId::parse((string) $audit->getKey())]]);
        $this->logs->record($request, 'Shared QMS evidence with assigned audit', 'archive_document', $document->getKey());
        return response()->json(['message' => 'Evidence shared only with the assigned auditor for this audit.']);
    }

    public function revokeShare(Request $request, string $id, string $auditId)
    {
        $document = $this->access->documents($request->user())->where('_id', MongoId::parse($id))->firstOrFail();
        abort_unless($this->access->canManage($request->user(), $document), 403);
        $document->getConnection()->getMongoDB()->selectCollection($document->getTable())->updateOne(['_id' => MongoId::parse((string) $document->getKey())], ['$pull' => ['sharedAuditIds' => MongoId::parse($auditId)]]);
        $this->logs->record($request, 'Revoked explicit QMS audit share', 'archive_document', $document->getKey());
        return response()->json(['message' => 'Explicit audit share revoked. Existing evidence already submitted through an audit remains governed by that audit.']);
    }

    public function organization(Request $request)
    {
        abort_unless($this->access->isAuditTeamLeader($request->user()), 403);
        return response()->json(['users' => User::where('isActive', true)->get(['fullName', 'email', 'roleId', 'departmentId', 'archiveResponsibility', 'archiveUnitIds', 'archiveCollegeIds', 'archiveCluster'])
            ->map(fn ($user) => ['_id' => (string) $user->getKey(), 'name' => $user->fullName, 'email' => $user->email, 'roleId' => $user->roleId,
                'departmentId' => $user->departmentId ? (string) $user->departmentId : null, 'cluster' => $user->archiveCluster,
                'responsibility' => $user->archiveResponsibility ?: 'faculty_staff', 'unitIds' => array_map('strval', $user->archiveUnitIds ?? []), 'collegeIds' => array_map('strval', $user->archiveCollegeIds ?? [])]),
            'responsibilities' => config('qms.responsibilities'), 'clusters' => config('qms.clusters')]);
    }

    public function assign(Request $request, string $id)
    {
        abort_unless($this->access->isAuditTeamLeader($request->user()), 403);
        $user = User::findOrFail(MongoId::parse($id));
        abort_if((int) $user->roleId === 2, 422, 'Auditor access comes only from explicit audit evidence sharing.');
        $data = $request->validate(['responsibility' => ['required', Rule::in(config('qms.responsibilities'))],
            'cluster' => ['nullable', 'string', Rule::in(array_keys(config('qms.clusters')))],
            'unitIds' => 'present|array|max:50', 'unitIds.*' => 'string|regex:/^[a-f0-9]{24}$/i|distinct',
            'collegeIds' => 'present|array|max:20', 'collegeIds.*' => 'string|regex:/^[a-f0-9]{24}$/i|distinct']);
        $assignment = app(\App\Services\UserOrganization::class)->validate((int) $user->roleId, $data['responsibility'], $data['unitIds'], $data['collegeIds'], $data['cluster'] ?? null);
        $user->fill($assignment)->save();
        $this->logs->record($request, 'Assigned scoped QMS position', 'user', $user->getKey());
        return response()->json(['message' => 'Position updated. Authentication role is unchanged.']);
    }

    public function updateCategory(Request $request, string $id)
    {
        abort(403, 'System QMS categories are protected. Manage user subfolders through the scoped folder operations.');
    }
}
