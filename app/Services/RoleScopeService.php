<?php

namespace App\Services;

use App\Models\ArchiveDocument;
use App\Models\AuditLog;
use App\Models\ComplianceCheck;
use App\Models\Department;
use App\Models\Notification;
use App\Models\User;
use MongoDB\BSON\ObjectId;

class RoleScopeService
{
    public function users(User $actor)
    {
        $query = User::query();

        return match ((int) $actor->roleId) {
            0 => $query,
            1 => $query->whereIn('roleId', [2, 3])
                ->whereIn('departmentId', array_merge($this->cetDepartmentIds(), array_map('strval', $this->cetDepartmentIds()))),
            2 => $query->where('roleId', 3)
                ->whereIn('departmentId', $this->assignedDepartmentIds($actor)),
            3 => $query->where('_id', $actor->getKey()),
            default => $query->whereRaw(['_id' => null]),
        };
    }

    public function departments(User $actor)
    {
        $query = Department::whereNotIn('unitType', ['college_office', 'legacy_unclassified']);

        return match ((int) $actor->roleId) {
            0 => $query,
            1 => $query->where('collegeName', 'CET'),
            2 => $query->whereIn('_id', $this->assignedDepartmentIds($actor)),
            3 => $query->where('_id', $this->objectId($actor->departmentId)),
            default => $query->whereRaw(['_id' => null]),
        };
    }

    public function archives(User $actor)
    {
        return app(QmsArchiveAccess::class)->documents($actor);
    }

    public function checks(User $actor)
    {
        $query = ComplianceCheck::query();

        return match ((int) $actor->roleId) {
            0 => $query,
            1 => $query->whereIn('departmentId', $this->cetDepartmentIds()),
            2 => $query->where('auditorId', new ObjectId((string) $actor->getKey())),
            3 => $query->where('auditeeId', new ObjectId((string) $actor->getKey())),
            default => $query->whereRaw(['_id' => null]),
        };
    }

    public function notifications(User $actor)
    {
        return Notification::where('recipientUserId', new ObjectId((string) $actor->getKey()));
    }

    public function auditLogs(User $actor)
    {
        abort_unless((int) $actor->roleId === 0, 403);

        return AuditLog::query();
    }

    public function canAccessDepartment(User $actor, ObjectId $departmentId): bool
    {
        if ((int) $actor->roleId === 0) {
            return true;
        }

        if ((int) $actor->roleId === 1) {
            return Department::where('_id', $departmentId)
                ->where('collegeName', 'CET')
                ->exists();
        }

        return in_array(
            (string) $departmentId,
            array_map('strval', $this->assignedDepartmentIds($actor, includeHomeDepartment: (int) $actor->roleId === 3)),
            true,
        );
    }

    public function assignedDepartmentIds(User $actor, bool $includeHomeDepartment = false): array
    {
        $ids = $actor->assignedDepartmentIds ?? [];
        if ($includeHomeDepartment && $actor->departmentId) {
            $ids[] = $actor->departmentId;
        }

        return array_values(array_unique(array_map(
            static fn ($id) => $id instanceof ObjectId ? $id : new ObjectId((string) $id),
            $ids,
        ), SORT_REGULAR));
    }

    private function cetDepartmentIds(): array
    {
        return Department::where('collegeName', 'CET')->get(['_id'])
            ->map(static fn ($department) => new ObjectId((string) $department->getKey()))
            ->all();
    }

    private function objectId(?string $id): ?ObjectId
    {
        return $id ? new ObjectId($id) : null;
    }
}
