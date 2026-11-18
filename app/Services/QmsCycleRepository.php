<?php
namespace App\Services;

use App\Models\{Audit, QmsCategory, User};
use App\Support\MongoId;

/** Idempotent creation of the documented cycle; existing files and folders stay in place. */
class QmsCycleRepository
{
    public function sync(): int
    {
        $policy = app(QmsRepositoryPolicy::class);
        $college = $policy->college();
        if (!$college) { return 0; }
        $cycle = config('qms.currentAuditCycle'); $created = 0;
        $add = function (string $key, string $name, ?QmsCategory $parent = null, array $attributes = []) use ($cycle, $college, &$created) {
            $code = 'CYCLE-'.sha1($cycle.'|'.$key);
            $folder = QmsCategory::firstOrCreate(['collegeId' => MongoId::parse((string) $college->getKey()), 'code' => $code],
                [...$attributes, 'name' => $name, 'normalizedName' => mb_strtolower(trim($name)), 'parentId' => $parent?->getKey(), 'folderPolicy' => $attributes['folderPolicy'] ?? 'administration',
                    'unitType' => $attributes['unitType'] ?? 'repository', 'cycleKey' => $cycle, 'isActive' => true, 'userManaged' => false]);
            if ($folder->wasRecentlyCreated) { $created++; }
            if (!$folder->normalizedName) { $folder->forceFill(['normalizedName' => mb_strtolower(trim($name))])->save(); }
            if (!$folder->unitType) { $folder->forceFill(['unitType' => $attributes['unitType'] ?? 'repository'])->save(); }
            return $folder;
        };
        // Adopt an existing named root so imports do not create a second cycle.
        $root = QmsCategory::where('collegeId', MongoId::parse((string) $college->getKey()))->whereNull('parentId')->where('name', $cycle)->where('isActive', true)->first();
        if (!$root) { $root = $add('root', $cycle, null, ['cycleRoot' => true]); }
        elseif (!$root->cycleRoot) { $root->forceFill(['cycleRoot' => true, 'cycleKey' => $cycle, 'folderPolicy' => 'administration'])->save(); }
        $campus = $add('campus', 'USTP Claveria', $root);
        $admin = $add('admin', '01 Audit Administration', $campus);
        foreach (['Approved Audit Plan', 'Audit Schedule', 'Auditor Assignments'] as $name) { $add('admin/'.$name, $name, $admin); }
        $workspaces = $add('workspaces', '02 Auditor Workspaces', $campus, ['workspaceArea' => true, 'workspaceRoot' => true]);
        $evidence = $add('evidence', '03 Department and Office Evidence', $campus);
        $results = $add('results', '04 Audit Results and CARs', $campus);
        $units = $policy->units()->get();
        foreach ($units as $unit) {
            foreach (['evidence' => [$evidence, ['Digital Files', 'Document References', 'Physical Original References']],
                'results' => [$results, ['Released Audit Results', 'Corrective Action Requests', 'Corrective Evidence']]] as $key => [$parent, $names]) {
                $attributes = ['folderPolicy' => 'department', 'departmentId' => $unit->getKey(), 'unitType' => $unit->unitType];
                $folder = $add($key.'/'.$unit->getKey(), $unit->departmentName, $parent, $attributes);
                foreach ($names as $name) { $add($key.'/'.$unit->getKey().'/'.$name, $name, $folder, $attributes); }
            }
        }
        $reports = $add('reports', '05 Consolidated Reports', $campus);
        foreach (['Annual Audit Summary', 'Consolidated CAR Log'] as $name) { $add('reports/'.$name, $name, $reports); }
        $ids = app(QmsArchiveAccess::class)->ids($units->modelKeys());
        $audits = Audit::whereIn('departmentId', array_merge($ids, array_map('strval', $ids)))->whereNotIn('status', ['cancelled', 'closed'])->get();
        foreach ($audits as $audit) {
            $auditor = User::find($audit->auditorId);
            $unit = $units->first(fn ($unit) => (string) $unit->getKey() === (string) $audit->departmentId);
            if (!$auditor || !$unit) { continue; }
            $attributes = ['workspaceArea' => true, 'folderPolicy' => 'notes', 'departmentId' => $unit->getKey(), 'unitType' => $unit->unitType,
                'auditorId' => $auditor->getKey(), 'auditAssignmentId' => $audit->getKey()];
            $personName = $auditor->fullName;
            $duplicateName = QmsCategory::where('parentId', MongoId::parse((string) $workspaces->getKey()))
                ->where('normalizedName', mb_strtolower(trim($personName)))->where('isActive', true)->get()
                ->contains(fn ($folder) => (string) $folder->auditorId !== (string) $auditor->getKey());
            if ($duplicateName) { $personName .= ' ('.($auditor->userCode ?: substr((string) $auditor->getKey(), -8)).')'; }
            $person = $add('auditor/'.$auditor->getKey(), $personName, $workspaces, ['workspaceArea' => true, 'auditorId' => $auditor->getKey()]);
            $assignmentKey = 'assignment/'.$auditor->getKey().'/'.$audit->getKey();
            $sameUnit = $audits->filter(fn ($candidate) => (string) $candidate->auditorId === (string) $audit->auditorId
                && (string) $candidate->departmentId === (string) $audit->departmentId)->count();
            $name = $unit->departmentName.($sameUnit > 1 ? ' ('.($audit->auditCode ?: substr((string) $audit->getKey(), -8)).')' : '');
            $department = $add($assignmentKey, $name, $person, $attributes);
            foreach (['Audit Checklists', 'Working Notes', 'Evidence References'] as $name) {
                $add($assignmentKey.'/'.$name, $name, $department,
                    [...$attributes, 'folderPolicy' => $name === 'Evidence References' ? 'evidence' : 'notes']);
            }
        }
        return $created;
    }
}
