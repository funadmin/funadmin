<?php
/**
 * FunAdmin
 * ============================================================================
 * 版权所有 2017-2028 FunAdmin，并保留所有权利。
 * 网站地址: http://www.FunAdmin.com
 * ----------------------------------------------------------------------------
 * 采用最新Thinkphp8实现
 * ============================================================================
 * Author: yuege
 * Date: 2017/8/2
 */


namespace app\admin\authorization\model;


use app\common\model\concern\LaravelSoftDelete;
use app\admin\authorization\service\CasbinService;
use app\admin\authorization\service\RoleGuardService;
use app\admin\authorization\service\RoleScopeService;
use app\admin\model\BackendModel;
use app\admin\traits\AdminDataFormat;

class AuthGroup extends BackendModel
{

    /**
     * @var bool
     */
    use LaravelSoftDelete;
    use AdminDataFormat;


    


    public function __construct(array $data = [])
    {
        parent::__construct($data);
    }
    public function getAllIdsBypid($pid)
    {
        $res = self::where('pid','in', $pid)->where('status', 1)->select();
        $str = '';
        if (!empty($res)) {
            foreach ($res as $k => $v) {
                $str .= "," . $v['id'];
                $str .= $this->getAllIdsBypid($v['id']);
            }
        }
        return $str;
    }

    /**
     * 角色管理查询边界：非超级管理员只能看到自身分支内的下级角色。
     */
    public static function manageableQuery()
    {
        $query = self::where('id', '<>', (int) config('funadmin.superRoleId'));
        $roleScope = new RoleScopeService();
        if (!$roleScope->isSuperAdmin()) {
            $query->whereIn('id', $roleScope->manageableRoleIds() ?: [0])
                ->where('level', '>', (new RoleGuardService())->currentLevel());
        }
        return $query;
    }

    public static function validateAttributes(array $data): ?string
    {
        if ($data['name'] === '' || mb_strlen($data['name']) > 100) {
            return '角色名称不能为空且不能超过 100 个字符';
        }
        if (!preg_match('/^[A-Za-z][A-Za-z0-9_]{1,49}$/', $data['code'])) {
            return '角色标识需以字母开头，只能包含字母、数字和下划线';
        }
        if (mb_strlen($data['remark']) > 255) {
            return '备注不能超过 255 个字符';
        }
        return null;
    }

    /**
     * 重建角色继承、自定义部门范围与 Casbin 继承关系。
     */
    public static function syncRelations(int $roleId, array $data): void
    {
        AuthGroupInherit::where('role_id', $roleId)->delete();
        $parentRoleIds = array_values(array_unique(array_filter(array_merge(
            $data['parentRoleIds'],
            $data['parentId'] > 0 ? [$data['parentId']] : []
        ))));
        $inheritRows = array_map(static fn (int $parentId): array => [
            'role_id' => $roleId,
            'parent_role_id' => $parentId,
            'created_at' => time()
        ], $parentRoleIds);
        if ($inheritRows) {
            (new AuthGroupInherit())->saveAll($inheritRows);
        }

        AuthGroupDepartment::where('role_id', $roleId)->delete();
        if ($data['dataScope'] === 'custom') {
            $departmentRows = array_map(static fn (int $departmentId): array => [
                'role_id' => $roleId,
                'dept_id' => $departmentId,
                'created_at' => time()
            ], $data['departmentIds']);
            (new AuthGroupDepartment())->saveAll($departmentRows);
        }
        CasbinService::instance()->syncRoleInheritance($roleId, $parentRoleIds);
    }

    public function toRoleData(): array
    {
        $roleId = (int) $this->id;
        $pid = (int) $this->pid;
        $roleScope = new RoleScopeService();
        return [
            'id' => $roleId,
            'name' => (string) $this->name,
            'code' => (string) $this->code,
            'level' => (int) $this->level,
            'dataScope' => (string) $this->data_scope,
            'remark' => (string) $this->remark,
            'status' => (int) $this->status,
            'parentId' => $pid,
            'parentRoleIds' => array_values(array_filter(
                array_map('intval', AuthGroupInherit::where('role_id', $roleId)->column('parent_role_id')),
                static fn (int $parentRoleId): bool => $parentRoleId !== $pid
            )),
            'departmentIds' => array_map('intval', AuthGroupDepartment::where('role_id', $roleId)->column('dept_id')),
            'permissionIds' => $roleScope->rolePermissionIds($roleId),
            'createdAt' => $this->formatTime($this->created_at),
        ];
    }

}