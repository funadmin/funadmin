<?php

declare(strict_types=1);

namespace app\admin\authorization\model;

use app\common\model\BaseModel;
use app\admin\authorization\service\DataScopeService;
use app\admin\authorization\service\RoleScopeService;
use app\common\traits\DataFormat;
use app\common\model\concern\LaravelSoftDelete;

class Department extends BaseModel
{
    use LaravelSoftDelete;
    use DataFormat;

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
        if ($create && !array_key_exists('name', $data)) {
            return '部门名称不能为空且不能超过 100 个字符';
        }
        if (array_key_exists('name', $data)
            && (!is_string($data['name']) || trim($data['name']) === '' || mb_strlen($data['name']) > 100)) {
            return '部门名称不能为空且不能超过 100 个字符';
        }
        foreach (['leader', 'phone'] as $field) {
            if (array_key_exists($field, $data) && !is_string($data[$field])) {
                return '部门信息格式不正确';
            }
        }
        if (array_key_exists('email', $data)
            && (!is_string($data['email']) || ($data['email'] !== '' && !filter_var($data['email'], FILTER_VALIDATE_EMAIL)))) {
            return '邮箱格式不正确';
        }
        if (array_key_exists('sort_order', $data)
            && (!is_int($data['sort_order']) || $data['sort_order'] < 0)) {
            return '排序值不正确';
        }
        if (array_key_exists('pid', $data)
            && (!is_int($data['pid']) || $data['pid'] < 0)) {
            return '上级部门不正确';
        }
        if (array_key_exists('status', $data)
            && (!is_int($data['status']) || !in_array($data['status'], [0, 1], true))) {
            return '状态值不正确';
        }
        return null;
    }

    public function toApiData(): array
    {
        return [
            'id' => (int) $this->id,
            'parentId' => (int) $this->pid,
            'name' => (string) $this->getAttribute('name'),
            'leader' => (string) $this->leader,
            'phone' => (string) $this->phone,
            'email' => (string) $this->email,
            'sort' => (int) $this->sort_order,
            'status' => (int) $this->status,
        ];
    }
}
