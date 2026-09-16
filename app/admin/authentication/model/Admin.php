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
 * Date: 2020/8/2
 */
namespace app\admin\authentication\model;

use app\common\model\concern\LaravelSoftDelete;
use app\admin\authorization\model\AdminDepartment;
use app\admin\authorization\model\AuthGroup;
use app\admin\authorization\service\CasbinService;
use app\admin\authorization\service\DataScopeService;
use app\admin\authorization\service\RoleGuardService;
use app\admin\authorization\service\RoleScopeService;
use app\admin\model\BackendModel;
use app\admin\traits\AdminDataFormat;

class Admin extends BackendModel {

    /**
     * @var bool
     */
    use LaravelSoftDelete;
    use AdminDataFormat;


    



    public function __construct(array $data = [])
    {
        parent::__construct($data);
    }

    /**
     * 管理员归属的全部部门：主部门加多部门关系，去重去空。
     */
    public function departmentIds(): array
    {
        return array_values(array_unique(array_filter(array_merge(
            [(int) $this->dept_id],
            array_map('intval', AdminDepartment::where('admin_id', (int) $this->id)->column('dept_id'))
        ))));
    }

    public function toProfileData(): array
    {
        return [
            'id' => (int) $this->id,
            'username' => (string) $this->username,
            'nickname' => (string) (($this->real_name ?: $this->username)),
            'avatar' => (string) $this->avatar,
            'email' => (string) $this->email,
            'mobile' => (string) $this->mobile,
            'lastLoginIp' => (string) $this->last_login_ip,
        ];
    }

    /**
     * 账号管理查询边界：数据范围 + 可管理角色分支内的管理员。
     */
    public static function manageableQuery()
    {
        $query = (new DataScopeService())->apply(self::where('id', '<>', (int) config('funadmin.superAdminId')), 'id', 'dept_id');
        $roleScope = new RoleScopeService();
        if ($roleScope->isSuperAdmin()) {
            return $query;
        }
        $currentLevel = (new RoleGuardService())->currentLevel();
        $branchRoleIds = $roleScope->manageableRoleIds();
        $allowedRoleIds = array_map('intval', AuthGroup::whereIn('id', $branchRoleIds ?: [0])
            ->where('status', 1)->where('level', '>', $currentLevel)->column('id'));
        $forbiddenRoleIds = array_map('intval', AuthGroup::where('status', 1)
            ->where(function ($where) use ($currentLevel, $branchRoleIds) {
                $where->where('level', '<=', $currentLevel);
                if ($branchRoleIds) {
                    $where->whereOr('id', 'not in', $branchRoleIds);
                }
            })->column('id'));
        $casbin = CasbinService::instance();
        $allowedAdminIds = $casbin->adminIdsByRoles($allowedRoleIds);
        $forbiddenAdminIds = $casbin->adminIdsByRoles($forbiddenRoleIds);
        $query->whereIn('id', $allowedAdminIds ?: [0]);
        if ($forbiddenAdminIds) {
            $query->whereNotIn('id', $forbiddenAdminIds);
        }
        return $query;
    }

    public static function validateAttributes(array $data, bool $create): ?string
    {
        if ($data['username'] === '' || !preg_match('/^[A-Za-z][A-Za-z0-9_]{2,19}$/', $data['username'])) {
            return '账号需以字母开头，由 3 到 20 位字母、数字或下划线组成';
        }
        if ($data['nickname'] === '' || mb_strlen($data['nickname']) > 50) {
            return '昵称不能为空且不能超过 50 个字符';
        }
        if ($create && mb_strlen($data['password']) < 8) {
            return '密码至少 8 位';
        }
        if ($data['email'] !== '' && (!filter_var($data['email'], FILTER_VALIDATE_EMAIL) || strlen($data['email']) > 60)) {
            return '邮箱格式不正确或超过 60 个字符';
        }
        return null;
    }

    public static function syncDepartments(int $adminId, array $departmentIds): void
    {
        AdminDepartment::where('admin_id', $adminId)->delete();
        $rows = array_map(static fn (int $departmentId): array => [
            'admin_id' => $adminId,
            'dept_id' => $departmentId,
            'created_at' => date('Y-m-d H:i:s'),
        ], array_values(array_unique($departmentIds)));
        if ($rows !== []) {
            (new AdminDepartment())->saveAll($rows);
        }
    }

    public function inCurrentDataScope(): bool
    {
        $scope = (new DataScopeService())->resolve();
        return $scope['all']
            || (int) $this->id === (int) $scope['adminId']
            || array_intersect($this->departmentIds(), $scope['departmentIds']) !== [];
    }

    public function toManagementData(): array
    {
        $roleScope = new RoleScopeService();
        $deptId = (int) $this->dept_id;
        return [
            'id' => (int) $this->id,
            'username' => (string) $this->username,
            'nickname' => (string) $this->real_name,
            'email' => (string) $this->email,
            'mobile' => (string) $this->mobile,
            'status' => (int) $this->status,
            'deptId' => $deptId,
            'departmentIds' => array_values(array_filter(
                $this->departmentIds(),
                static fn (int $departmentId): bool => $departmentId !== $deptId
            )),
            'roleIds' => $roleScope->adminRoleIds((int) $this->id),
            'createdAt' => $this->formatTime($this->created_at),
            'updatedAt' => $this->formatTime($this->updated_at),
        ];
    }

}
