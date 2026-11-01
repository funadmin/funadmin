<?php

declare(strict_types=1);

namespace app\common\service\identity;

use app\common\model\identity\IdentityUserDepartment;

class IdentityDepartmentService
{
    public function sync(int $tenantId, int $userId, array $departmentIds, int $primaryDepartmentId = 0): void
    {
        $departmentIds = array_values(array_unique(array_filter(array_map('intval', $departmentIds))));
        IdentityUserDepartment::forTenant($tenantId)->where('user_id', $userId)->delete();
        if ($departmentIds === []) {
            return;
        }

        $now = date('Y-m-d H:i:s');
        $rows = array_map(static fn (int $departmentId): array => [
            'tenant_id' => $tenantId,
            'user_id' => $userId,
            'department_id' => $departmentId,
            'is_primary' => $departmentId === $primaryDepartmentId ? 1 : 0,
            'created_at' => $now,
            'updated_at' => $now,
        ], $departmentIds);
        (new IdentityUserDepartment())->saveAll($rows);
    }
}
