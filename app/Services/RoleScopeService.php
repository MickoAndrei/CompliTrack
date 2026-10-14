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
                ->whereIn('departmentId', $this->cetDepartmentIds()),
            2 => $query->where('roleId', 3)
                ->whereIn('departmentId', $this->assignedDepartmentIds($actor)),
            3 => $query->where('_id', $actor->getKey()),
            default => $query->whereRaw(['_id' => null]),
        };
    }

    public function departments(User $actor)
    {
        $query = Department::query();

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
        $query = ArchiveDocument::query();

        return match ((int) $actor->roleId) {
            0 => $query,
            1 => $query->whereIn('departmentId', $this->cetDepartmentIds()),
            2 => $query->whereIn('departmentId', $this->assignedDepartmentIds($actor)),
            3 => $query->where('uploadedByUserId', $actor->getKey()),
            default => $query->whereRaw(['_id' => null]),
        };
    }

    public function checks(User $actor)
    {
        $query = ComplianceCheck::query();

        return match ((int) $actor->roleId) {
            0 => $query,
            1 => $query->whereIn('departmentId', $this->cetDepartmentIds()),
            2 => $query->where('auditorId', $actor->getKey()),
            3 => $query->where('auditeeId', $actor->getKey()),
            default => $query->whereRaw(['_id' => null]),
        };
    }

    public function notifications(User $actor)
    {
        return Notification::where('recipientUserId', $actor->getKey());
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
            $this->assignedDepartmentIds($actor, includeHomeDepartment: true),
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
        return Department::where('collegeName', 'CET')->pluck('_id')
            ->map(static fn ($id) => $id instanceof ObjectId ? $id : new ObjectId((string) $id))
            ->all();
    }

    private function objectId(?string $id): ?ObjectId
    {
        return $id ? new ObjectId($id) : null;
    }
}
