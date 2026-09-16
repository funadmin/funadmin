<?php

declare(strict_types=1);

namespace app\admin\model;

use app\admin\authorization\service\DataScopeService;
use app\admin\authorization\service\RoleScopeService;
use app\admin\traits\AdminDataFormat;
use app\common\model\concern\LaravelSoftDelete;

class Department extends BackendModel
{
    use LaravelSoftDelete;
    use AdminDataFormat;

    protected $name = 'department';

    /**
     * 当前数据范围可见部门 ID；null 表示超级管理员不受限制。
     */
    public static function allowedIds(): ?array
    {
        if ((new RoleScopeService())->isSuperAdmin()) {
            return null;
        }
        $scope = (new DataScopeService())->resolve();
        return array_map('intval', $scope['departmentIds']);
    }

    public static function canAccess(int $departmentId): bool
    {
        $allowedIds = self::allowedIds();
        return $allowedIds === null || in_array($departmentId, $allowedIds, true);
    }

    public static function validateAttributes(array $data, bool $create = true): ?string
    {
        if (($create || array_key_exists('name', $data)) && (($data['name'] ?? '') === '' || mb_strlen((string) $data['name']) > 100)) {
            return '部门名称不能为空且不能超过 100 个字符';
        }
        if (isset($data['email']) && $data['email'] !== '' && !filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
            return '邮箱格式不正确';
        }
        return null;
    }

    public function toApiData(): array
    {
        return [
            'id' => (int) $this->id,
            'parentId' => (int) $this->pid,
            'name' => (string) $this->name,
            'leader' => (string) $this->leader,
            'phone' => (string) $this->phone,
            'email' => (string) $this->email,
            'sort' => (int) $this->sort_order,
            'status' => (int) $this->status,
        ];
    }
}
