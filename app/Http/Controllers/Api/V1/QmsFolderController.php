<?php
namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\{ArchiveDocument, Department, QmsCategory, User};
use App\Services\{AuditLogService, QmsFolderAccess};
use App\Support\MongoId;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use MongoDB\BSON\ObjectId;

class QmsFolderController extends Controller
{
    public function __construct(private QmsFolderAccess $access, private AuditLogService $logs) {}

    public function show(Request $request, string $id)
    {
        $request->validate(['unitId' => 'nullable|string|regex:/^[a-f0-9]{24}$/i']);
        $department = app(\App\Services\QmsArchiveAccess::class)->organizationalUnits($request->user(), true)->where('_id', MongoId::parse($id))->first();
        if ($department) {
            return response()->json(['data' => ['name' => $department->departmentName, 'type' => 'Organizational folder', 'location' => \App\Models\College::find($department->collegeId)?->collegeName,
                'accessSummary' => 'Access follows organizational responsibilities and document permissions.',
                ...app(\App\Services\QmsBrowser::class)->folderMetadata($department, $request->user())]])->header('Cache-Control', 'private, no-store');
        }
        [$folder, $unit, $permissions] = app(\App\Services\QmsBrowser::class)->folder($request->user(), $id, $request->query('unitId'));
        $metadata = app(\App\Services\QmsBrowser::class)->folderMetadata($folder, $request->user());
        return response()->json(['data' => ['name' => $folder->name, 'type' => 'Folder', 'location' => $unit?->departmentName ?: 'CET repository',
            'accessSummary' => match ($folder->folderPolicy) {'evidence' => 'Eligible assignment and authorized evidence only.', 'notes' => 'Assigned auditor and permitted ATL oversight.',
                'administration' => 'Authorized CET audit administration.', default => 'Authorized organizational members; document classification still applies.'},
            ...$metadata]])->header('Cache-Control', 'private, no-store');
    }

    public function star(Request $request, string $id)
    {
        $data = $request->validate(['starred' => 'required|boolean', 'unitId' => 'nullable|string|regex:/^[a-f0-9]{24}$/i']);
        $browser = app(\App\Services\QmsBrowser::class);
        if (Department::where('_id', MongoId::parse($id))->exists()) {
            abort_unless(app(\App\Services\QmsArchiveAccess::class)->organizationalUnits($request->user(), true)->where('_id', MongoId::parse($id))->exists(), 404);
        } else { $browser->folder($request->user(), $id, $data['unitId'] ?? null); }
        $user = $request->user();
        $update = [$data['starred'] ? '$addToSet' : '$pull' => ['qmsStarredFolderIds' => $id]];
        if ($data['starred'] && !empty($data['unitId'])) { $update['$set'] = ['qmsStarredFolderUnits.'.$id => $data['unitId']]; }
        $user->getConnection()->getMongoDB()->selectCollection($user->getTable())->updateOne(['_id' => MongoId::parse((string) $user->getKey())], $update);
        return response()->json(['isStarred' => $data['starred']])->header('Cache-Control', 'private, no-store');
    }

    private function validateLocation(Request $request): array
    {
        return $request->validate(['unitId' => 'required|string|regex:/^[a-f0-9]{24}$/i', 'parentId' => 'nullable|string|regex:/^[a-f0-9]{24}$/i',
            'collegeId' => 'prohibited', 'departmentId' => 'prohibited', 'classification' => 'prohibited', 'userManaged' => 'prohibited', 'auditId' => 'prohibited']);
    }

    private function name(Request $request): array
    {
        $data = $request->validate(['name' => ['required', 'string', 'max:100', 'not_regex:/[\x00-\x1f\x7f\\\\\/]/u']]);
        $name = trim(preg_replace('/\s+/u', ' ', $data['name']));
        abort_if($name === '' || in_array($name, ['.', '..'], true), 422, 'Enter a valid folder name.');
        return [$name, mb_strtolower($name)];
    }

    private function lockUnit(string $id): Department
    {
        $unit = Department::findOrFail(MongoId::parse($id));
        $unit->getConnection()->getMongoDB()->selectCollection($unit->getTable())->updateOne(['_id' => MongoId::parse((string) $unit->getKey())], ['$inc' => ['qmsFolderRevision' => 1]]);
        return $unit;
    }

    private function unique(Department $unit, ?QmsCategory $parent, string $normalized, ?string $except = null): void
    {
        $query = QmsCategory::where('isActive', true)->where('parentId', $parent ? MongoId::parse((string) $parent->getKey()) : null)->where('collegeId', MongoId::parse((string) $unit->collegeId))->where('unitType', $unit->unitType)
            ->where(fn ($q) => $q->where('userManaged', '!=', true)->orWhere('departmentId', MongoId::parse((string) $unit->getKey())));
        if ($except) { $query->where('_id', '!=', MongoId::parse($except)); }
        abort_if($query->get(['name'])->contains(fn ($folder) => mb_strtolower(trim($folder->name)) === $normalized), 422, 'A folder with this name already exists here.');
    }

    private function depth(?QmsCategory $parent, ?string $source = null): int
    {
        $seen = []; $depth = 0;
        while ($parent) {
            $id = (string) $parent->getKey();
            abort_if($id === $source || in_array($id, $seen, true), 422, 'A folder cannot be moved into itself or its descendants.');
            $seen[] = $id; abort_if(++$depth >= 12, 422, 'The maximum folder depth is 12.');
            $parent = $parent->parentId ? QmsCategory::find($parent->parentId) : null;
        }
        return $depth;
    }

    public function store(Request $request)
    {
        if ($request->has('folderPolicy')) { return $this->storeRepositoryFolder($request); }
        $data = $this->validateLocation($request); [$name, $normalized] = $this->name($request);
        return DB::connection('mongodb')->transaction(function () use ($request, $data, $name, $normalized) {
            $unit = $this->lockUnit($data['unitId']); $parent = $this->access->parent($unit, $data['parentId'] ?? null);
            abort_if($parent?->folderPolicy, 422, 'Choose the inherited repository folder policy.');
            abort_unless($this->access->permissions($request->user(), $unit, $parent)['create'], 403);
            $this->depth($parent); $this->unique($unit, $parent, $normalized);
            $id = new ObjectId();
            $folder = new QmsCategory(['name' => $name, 'normalizedName' => $normalized, 'code' => 'UF-'.$id,
                'collegeId' => $unit->collegeId, 'departmentId' => MongoId::parse((string) $unit->getKey()), 'unitType' => $unit->unitType,
                'parentId' => $parent?->getKey(), 'classification' => $parent?->classification ?: 'department',
                'userManaged' => true, 'isActive' => true, 'provisional' => false, 'createdByUserId' => $request->user()->getKey()]);
            $folder->setAttribute('_id', $id); $folder->save();
            $this->logs->record($request, 'Created QMS subfolder', 'qms_category', $folder->getKey());
            return response()->json(['data' => ['_id' => (string) $folder->getKey(), 'name' => $name]], 201)->header('Cache-Control', 'private, no-store');
        }, 3);
    }

    public function update(Request $request, string $id)
    {
        if (QmsCategory::find($id)?->folderPolicy) { return $this->updateRepositoryFolder($request, $id); }
        $data = $this->validateLocation($request);
        $request->validate(['operation' => 'required|in:rename,move']);
        $operation = $request->input('operation');
        [$name, $normalized] = $operation === 'rename' ? $this->name($request) : [null, null];
        return DB::connection('mongodb')->transaction(function () use ($request, $data, $id, $operation, $name, $normalized) {
            $unit = $this->lockUnit($data['unitId']); $folder = $this->access->parent($unit, $id);
            abort_unless($folder->userManaged && $this->access->permissions($request->user(), $unit, $folder)[$operation], 403);
            if ($operation === 'rename') {
                $parent = $folder->parentId ? $this->access->parent($unit, (string) $folder->parentId) : null;
                $this->unique($unit, $parent, $normalized, $id); $folder->name = $name; $folder->normalizedName = $normalized;
            } else {
                $hasContents = QmsCategory::where('parentId', MongoId::parse($id))->where('isActive', true)->exists()
                    || ArchiveDocument::whereIn('categoryId', [MongoId::parse($id), $id])->exists();
                abort_if($hasContents && !$this->access->permissions($request->user(), $unit, $folder)['manage'], 403, 'Managing an existing folder structure requires the manage permission.');
                $parent = $this->access->parent($unit, $data['parentId'] ?? null);
                abort_if($parent?->folderPolicy, 422, 'Legacy folders cannot be moved across repository policy boundaries.');
                abort_unless($this->access->permissions($request->user(), $unit, $parent)['create'], 403);
                abort_unless(($folder->classification ?: 'department') === ($parent?->classification ?: 'department'), 422, 'Folders must retain their inherited classification.');
                $depth = $this->depth($parent, $id); $descendants = [$folder];
                for ($level = 1; $descendants !== []; $level++) {
                    abort_if($depth + $level > 12, 422, 'The move exceeds the maximum folder depth.');
                    $descendants = QmsCategory::where('isActive', true)->whereIn('parentId', array_map(fn ($item) => MongoId::parse((string) $item->getKey()), $descendants))->get()->all();
                }
                $this->unique($unit, $parent, $folder->normalizedName ?: mb_strtolower($folder->name), $id);
                $folder->parentId = $parent?->getKey();
            }
            $folder->save(); $this->logs->record($request, $operation === 'rename' ? 'Renamed QMS subfolder' : 'Moved QMS subfolder', 'qms_category', $folder->getKey());
            return response()->json(['message' => 'Folder updated.'])->header('Cache-Control', 'private, no-store');
        }, 3);
    }

    public function destroy(Request $request, string $id)
    {
        if (QmsCategory::find($id)?->folderPolicy) { return $this->deleteRepositoryFolder($request, $id); }
        $data = $this->validateLocation($request);
        return DB::connection('mongodb')->transaction(function () use ($request, $data, $id) {
            $unit = $this->lockUnit($data['unitId']); $folder = $this->access->parent($unit, $id);
            abort_unless($folder->userManaged && $this->access->permissions($request->user(), $unit, $folder)['delete'], 403);
            abort_if(QmsCategory::where('parentId', MongoId::parse((string) $folder->getKey()))->where('isActive', true)->exists()
                || ArchiveDocument::whereIn('categoryId', [MongoId::parse((string) $folder->getKey()), (string) $folder->getKey()])->exists(), 409, 'Only empty folders can be deleted. Move or retain their contents first.');
            $folder->isActive = false; $folder->save();
            $this->logs->record($request, 'Deleted empty QMS subfolder', 'qms_category', $folder->getKey());
            return response()->json(['message' => 'Empty folder removed.'])->header('Cache-Control', 'private, no-store');
        }, 3);
    }

    public function grants(Request $request, string $id)
    {
        abort_unless((int) $request->user()->roleId === 0, 403);
        $data = $request->validate(['grants' => 'present|array|max:50', 'grants.*.unitId' => 'required|string|regex:/^[a-f0-9]{24}$/i|distinct',
            'grants.*.actions' => 'present|array', 'grants.*.actions.*' => ['string', Rule::in(QmsFolderAccess::ACTIONS)],
            'grants.*.classifications' => 'present|array', 'grants.*.classifications.*' => 'string|in:department,restricted,college']);
        $user = User::findOrFail(MongoId::parse($id));
        $grants = array_map(fn ($grant) => ['unitId' => $grant['unitId'], 'actions' => $grant['actions'], 'classifications' => $grant['classifications']], $data['grants']);
        foreach ($grants as $grant) { abort_unless($this->access->units($user)->where('_id', MongoId::parse($grant['unitId']))->exists(), 422, 'Grants must remain inside the account organizational scope.'); }
        $user->setAttribute('qmsFolderGrants', $grants); $user->save();
        $this->logs->record($request, 'Updated scoped QMS folder permissions', 'user', $user->getKey());
        return response()->json(['message' => 'Folder grants updated.'])->header('Cache-Control', 'private, no-store');
    }

    private function repositoryLock(): \App\Models\College
    {
        $college = app(\App\Services\QmsRepositoryPolicy::class)->college();
        abort_unless($college, 422, 'The active CET college must be configured first.');
        $college->increment('qmsFolderRevision');
        return $college;
    }

    private function repositoryParent(?string $id): ?QmsCategory
    {
        if (!$id) { return null; }
        return QmsCategory::where('_id', MongoId::parse($id))->whereNotNull('folderPolicy')->where('isActive', true)->firstOrFail();
    }

    private function repositoryUnique($college, ?QmsCategory $parent, string $normalized, ?string $except = null): void
    {
        $query = QmsCategory::where('collegeId', MongoId::parse((string) $college->getKey()))->where('isActive', true)->whereNotNull('folderPolicy')
            ->where('parentId', $parent ? MongoId::parse((string) $parent->getKey()) : null)->where('normalizedName', $normalized);
        if ($except) { $query->where('_id', '!=', MongoId::parse($except)); }
        abort_if($query->exists(), 422, 'A folder with this name already exists here.');
    }

    private function storeRepositoryFolder(Request $request)
    {
        $data = $request->validate(['folderPolicy' => 'required|in:department,evidence,notes,administration',
            'parentId' => 'nullable|string|regex:/^[a-f0-9]{24}$/i', 'unitId' => 'nullable|string|regex:/^[a-f0-9]{24}$/i',
            'auditAssignmentId' => 'nullable|string|regex:/^[a-f0-9]{24}$/i', 'auditorId' => 'prohibited',
            'collegeId' => 'prohibited', 'departmentId' => 'prohibited', 'classification' => 'prohibited', 'userManaged' => 'prohibited']);
        [$name, $normalized] = $this->name($request);
        return DB::connection('mongodb')->transaction(function () use ($request, $data, $name, $normalized) {
            $policy = app(\App\Services\QmsRepositoryPolicy::class); $actor = $request->user();
            $college = $this->repositoryLock(); $parent = $this->repositoryParent($data['parentId'] ?? null);
            if ($parent) {
                abort_unless($policy->permissions($actor, $parent)['create'], 403);
                // Only an administrative container may contain independent scope boundaries.
                if ($parent->folderPolicy !== 'administration') {
                    abort_unless($data['folderPolicy'] === $parent->folderPolicy
                        && (string) ($data['unitId'] ?? '') === (string) $parent->departmentId
                        && (string) ($data['auditAssignmentId'] ?? '') === (string) $parent->auditAssignmentId, 422, 'Subfolders must retain their parent scope and binding.');
                }
            }
            $audit = null; $unit = null;
            if (in_array($data['folderPolicy'], ['evidence', 'notes'], true)) {
                $audit = $policy->assignment($actor, $data['auditAssignmentId'] ?? null);
                abort_unless($audit, 422, 'Choose an existing eligible audit assignment.');
                abort_if(!empty($data['unitId']) && (string) $audit->departmentId !== $data['unitId'], 422, 'The department must match the assignment.');
                $unit = $policy->units()->where('_id', $audit->departmentId)->firstOrFail();
            } elseif ($data['folderPolicy'] === 'department') {
                abort_if(!empty($data['auditAssignmentId']), 422, 'Department folders cannot have an audit binding.');
                $unit = $policy->units()->where('_id', MongoId::parse($data['unitId'] ?? ''))->firstOrFail();
            } else {
                abort_if(!empty($data['unitId']) || !empty($data['auditAssignmentId']), 422, 'Administrative folders have a college scope.');
                abort_unless($policy->leader($actor), 403);
            }
            $folder = new QmsCategory(['name' => $name, 'code' => 'RF-'.new ObjectId(), 'normalizedName' => $normalized, 'collegeId' => $college->getKey(),
                'departmentId' => $unit?->getKey(), 'unitType' => $unit?->unitType ?: 'repository', 'folderPolicy' => $data['folderPolicy'],
                'auditAssignmentId' => $audit?->getKey(), 'auditorId' => $audit?->auditorId,
                'classification' => $data['folderPolicy'] === 'notes' ? 'restricted' : 'department',
                'parentId' => $parent?->getKey(), 'userManaged' => true, 'isActive' => true, 'createdByUserId' => $actor->getKey()]);
            abort_unless($policy->permissions($actor, $folder)['create'], 403);
            $this->depth($parent); $this->repositoryUnique($college, $parent, $normalized);
            $folder->save(); $this->logs->record($request, 'Created scoped QMS folder', 'qms_category', $folder->getKey());
            return response()->json(['data' => ['_id' => (string) $folder->getKey(), 'name' => $name]], 201)->header('Cache-Control', 'private, no-store');
        }, 3);
    }

    private function updateRepositoryFolder(Request $request, string $id)
    {
        $data = $request->validate(['operation' => 'required|in:rename,move', 'parentId' => 'nullable|string|regex:/^[a-f0-9]{24}$/i',
            'folderPolicy' => 'prohibited', 'departmentId' => 'prohibited', 'auditAssignmentId' => 'prohibited', 'auditorId' => 'prohibited']);
        return DB::connection('mongodb')->transaction(function () use ($request, $id, $data) {
            $college = $this->repositoryLock(); $folder = $this->repositoryParent($id); $policy = app(\App\Services\QmsRepositoryPolicy::class);
            abort_unless($policy->permissions($request->user(), $folder)[$data['operation']], 403);
            $parent = $this->repositoryParent($data['operation'] === 'move' ? ($data['parentId'] ?? null) : ($folder->parentId ? (string) $folder->parentId : null));
            if ($data['operation'] === 'move') {
                if ($parent) {
                    abort_unless($policy->permissions($request->user(), $parent)['create'], 403);
                    abort_unless($parent->folderPolicy === 'administration' || ($parent->folderPolicy === $folder->folderPolicy
                        && (string) $parent->departmentId === (string) $folder->departmentId
                        && (string) $parent->auditAssignmentId === (string) $folder->auditAssignmentId), 422, 'A move cannot change folder scope.');
                }
                $depth = $this->depth($parent, $id); $children = [$folder];
                for ($level = 1; $children !== []; $level++) {
                    abort_if($depth + $level > 12, 422, 'The move exceeds the maximum folder depth.');
                    $children = QmsCategory::where('isActive', true)->whereIn('parentId', array_map(fn ($f) => MongoId::parse((string) $f->getKey()), $children))->get()->all();
                }
                $folder->parentId = $parent?->getKey();
            } else { [$folder->name, $folder->normalizedName] = $this->name($request); }
            $this->repositoryUnique($college, $parent, $folder->normalizedName, $id);
            $folder->save(); $this->logs->record($request, 'Updated scoped QMS folder', 'qms_category', $folder->getKey());
            return response()->json(['message' => 'Folder updated.'])->header('Cache-Control', 'private, no-store');
        }, 3);
    }

    private function deleteRepositoryFolder(Request $request, string $id)
    {
        return DB::connection('mongodb')->transaction(function () use ($request, $id) {
            $this->repositoryLock(); $folder = $this->repositoryParent($id);
            abort_unless(app(\App\Services\QmsRepositoryPolicy::class)->permissions($request->user(), $folder)['delete'], 403);
            abort_if(QmsCategory::where('parentId', MongoId::parse((string) $folder->getKey()))->where('isActive', true)->exists()
                || ArchiveDocument::whereIn('categoryId', [MongoId::parse((string) $folder->getKey()), (string) $folder->getKey()])->exists(), 409, 'Only empty folders can be deleted.');
            $folder->isActive = false; $folder->save(); $this->logs->record($request, 'Deleted empty scoped QMS folder', 'qms_category', $folder->getKey());
            return response()->json(['message' => 'Empty folder removed.'])->header('Cache-Control', 'private, no-store');
        }, 3);
    }
}
