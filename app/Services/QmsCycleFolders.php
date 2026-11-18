<?php
namespace App\Services;

/** Read-only cycle outline for Super Admin. It never grants access to records. */
class QmsCycleFolders
{
    public function navigation(array $saved, array $units): array
    {
        $current = config('qms.currentAuditCycle');
        $roots = array_values(array_filter($saved, fn ($folder) => empty($folder['parentId'])
            && preg_match('/^Internal Quality Audit\b/i', $folder['name'])));
        $ids = array_column($roots, '_id');
        do {
            $before = count($ids);
            foreach ($saved as $folder) {
                if (in_array($folder['parentId'], $ids, true) && !in_array($folder['_id'], $ids, true)) { $ids[] = $folder['_id']; }
            }
        } while (count($ids) !== $before);
        $result = array_values(array_filter($saved, fn ($folder) => in_array($folder['_id'], $ids, true)));
        foreach ($result as &$folder) {
            $folder['auditRelated'] = true;
            $folder['currentCycle'] = empty($folder['parentId']) && $folder['name'] === $current;
            $folder['permissions'] = array_fill_keys(QmsFolderAccess::ACTIONS, false);
            $folder['permissions']['view'] = true;
        }
        unset($folder);
        if (in_array($current, array_column($roots, 'name'), true)) { return $result; }

        // Show the documented outline even before the ATL creates repository folders.
        $add = function (string $name, ?string $parent = null) use (&$result, $current): string {
            $id = 'cycle-outline:'.sha1($current.'|'.$parent.'|'.$name);
            $result[] = ['_id' => $id, 'name' => $name, 'parentId' => $parent, 'collegeId' => '', 'departmentId' => null,
                'folderPolicy' => 'administration', 'auditRelated' => true, 'currentCycle' => $parent === null,
                'permissions' => ['view' => true], 'ownerName' => null, 'createdAt' => null, 'updatedAt' => null];
            return $id;
        };
        $root = $add($current);
        $campus = $add('USTP Claveria', $root);
        $admin = $add('01 Audit Administration', $campus);
        foreach (['Approved Audit Plan', 'Audit Schedule', 'Auditor Assignments'] as $name) { $add($name, $admin); }
        $add('02 Auditor Workspaces', $campus);
        $evidence = $add('03 Department and Office Evidence', $campus);
        $results = $add('04 Audit Results and CARs', $campus);
        foreach ($units as $unitName) {
            $unit = $add($unitName, $evidence);
            foreach (['Digital Files', 'Document References', 'Physical Original References'] as $name) { $add($name, $unit); }
            $unit = $add($unitName, $results);
            foreach (['Released Audit Results', 'Corrective Action Requests', 'Corrective Evidence'] as $name) { $add($name, $unit); }
        }
        $reports = $add('05 Consolidated Reports', $campus);
        foreach (['Annual Audit Summary', 'Consolidated CAR Log'] as $name) { $add($name, $reports); }
        return $result;
    }
}
