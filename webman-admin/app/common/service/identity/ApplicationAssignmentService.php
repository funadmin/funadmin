<?php

declare(strict_types=1);

namespace app\common\service\identity;

use app\common\model\identity\ApplicationAssignment;
use DomainException;
use InvalidArgumentException;

final class ApplicationAssignmentService
{
    /** @return array{subject_type:string,subject_id:?int,effect:string,status:int} */
    public static function validateSubject(array $input): array
    {
        $type = (string) ($input['subjectType'] ?? $input['subject_type'] ?? '');
        $effect = (string) ($input['effect'] ?? 'allow');
        $id = $input['subjectId'] ?? $input['subject_id'] ?? null;
        if (!in_array($type, ['all', 'user', 'department', 'role'], true) || !in_array($effect, ['allow', 'deny'], true)) {
            throw new InvalidArgumentException('访问主体或效果无效');
        }
        if (($type === 'all' && $id !== null && $id !== '') || ($type !== 'all' && (!is_numeric($id) || (int) $id <= 0))) {
            throw new InvalidArgumentException('访问范围必须且只能指定一个有效主体');
        }
        return ['subject_type' => $type, 'subject_id' => $type === 'all' ? null : (int) $id, 'effect' => $effect, 'status' => (int) ($input['status'] ?? 1)];
    }

    public function replace(int $tenantId, int $applicationId, array $assignments): array
    {
        $this->assertApplication($tenantId, $applicationId);
        ApplicationAssignment::forTenant($tenantId)->where('application_id', $applicationId)->delete();
        $result = [];
        foreach ($assignments as $assignment) {
            $row = self::validateSubject((array) $assignment) + ['tenant_id' => $tenantId, 'application_id' => $applicationId];
            $result[] = ApplicationAssignment::create($row)->toArray();
        }
        return $result;
    }

    public function list(int $tenantId, int $applicationId): array
    {
        $this->assertApplication($tenantId, $applicationId);
        return ApplicationAssignment::forTenant($tenantId)->where('application_id', $applicationId)->select()->toArray();
    }

    public static function decide(array $assignments, int $userId, array $departmentIds, array $roleIds): bool
    {
        $allowed = false;
        foreach ($assignments as $assignment) {
            if ((int) ($assignment['status'] ?? 1) !== 1 || !self::matches($assignment, $userId, $departmentIds, $roleIds)) {
                continue;
            }
            if (($assignment['effect'] ?? 'allow') === 'deny') {
                return false;
            }
            $allowed = true;
        }
        return $allowed;
    }

    /**
     * 按应用可见性判定启动权限；任何匹配的显式 deny 均拥有最高优先级。
     */
    public static function canLaunch(string $visibility, ?int $ownerIdentityUserId, array $assignments, int $userId, array $departmentIds, array $roleIds): bool
    {
        if ($userId <= 0 || self::hasMatchingDeny($assignments, $userId, $departmentIds, $roleIds)) {
            return false;
        }
        if ($visibility !== 'private') {
            return in_array($visibility, ['tenant', 'public'], true);
        }
        if ($ownerIdentityUserId !== null && $ownerIdentityUserId === $userId) {
            return true;
        }
        foreach ($assignments as $assignment) {
            if ((int) ($assignment['status'] ?? 1) === 1
                && ($assignment['effect'] ?? 'allow') === 'allow'
                && ($assignment['subject_type'] ?? '') === 'user'
                && self::matches($assignment, $userId, $departmentIds, $roleIds)) {
                return true;
            }
        }
        return false;
    }

    public static function hasMatchingAssignment(array $assignments, int $userId, array $departmentIds, array $roleIds): bool
    {
        foreach ($assignments as $assignment) {
            if ((int) ($assignment['status'] ?? 1) === 1
                && self::matches($assignment, $userId, $departmentIds, $roleIds)) {
                return true;
            }
        }
        return false;
    }

    private static function hasMatchingDeny(array $assignments, int $userId, array $departmentIds, array $roleIds): bool
    {
        foreach ($assignments as $assignment) {
            if ((int) ($assignment['status'] ?? 1) === 1
                && ($assignment['effect'] ?? 'allow') === 'deny'
                && self::matches($assignment, $userId, $departmentIds, $roleIds)) {
                return true;
            }
        }
        return false;
    }

    private static function matches(array $assignment, int $userId, array $departmentIds, array $roleIds): bool
    {
        return match ($assignment['subject_type'] ?? '') {
            'all' => true,
            'user' => (int) ($assignment['subject_id'] ?? 0) === $userId,
            'department' => in_array((int) ($assignment['subject_id'] ?? 0), array_map('intval', $departmentIds), true),
            'role' => in_array((int) ($assignment['subject_id'] ?? 0), array_map('intval', $roleIds), true),
            default => false,
        };
    }

    private function assertApplication(int $tenantId, int $applicationId): void
    {
        if (!\app\common\model\identity\EnterpriseApplication::forTenant($tenantId)->where('id', $applicationId)->find()) {
            throw new DomainException('应用不存在或不属于当前租户');
        }
    }
}
