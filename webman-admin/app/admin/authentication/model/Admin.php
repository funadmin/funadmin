<?php

/**
 * FunAdmin
 * ============================================================================
 * 版权所有 2017-2028 FunAdmin，并保留所有权利。
 * 网站地址: http://www.FunAdmin.com
 * ----------------------------------------------------------------------------
 * 采用最新webman实现
 * ============================================================================
 * Author: yuege
 * Date: 2020/8/2
 */
namespace app\admin\authentication\model;

use app\admin\authorization\model\AdminDepartment;
use support\Model;

class Admin extends Model
{
    protected $table = 'admin';
    protected $hidden = ['password', 'token'];
    protected $guarded = ['id'];
    protected $casts = ['status' => 'boolean'];

    public function departmentIds(): array
    {
        $departmentIds = AdminDepartment::where('admin_id', (int) $this->id)->pluck('dept_id')->all();
        return array_values(array_unique(array_filter(array_merge([(int) $this->dept_id], array_map('intval', $departmentIds)))));
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

    public static function validateAttributes(array $data, bool $create): ?string
    {
        if (($data['username'] ?? '') === '' || !preg_match('/^[A-Za-z][A-Za-z0-9_]{2,19}$/', (string) $data['username'])) {
            return '账号需以字母开头，由 3 到 20 位字母、数字或下划线组成';
        }
        if ($create && strlen((string) ($data['password'] ?? '')) < 8) {
            return '密码至少 8 位';
        }
        if (($data['email'] ?? '') !== '' && !filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
            return '邮箱格式不正确';
        }
        return null;
    }

    public static function syncDepartments(int $adminId, array $departmentIds): void
    {
        AdminDepartment::where('admin_id', $adminId)->delete();
        $now = date('Y-m-d H:i:s');
        foreach (array_values(array_unique(array_map('intval', $departmentIds))) as $departmentId) {
            if ($departmentId > 0) {
                AdminDepartment::create([
                    'admin_id' => $adminId,
                    'dept_id' => $departmentId,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        }
    }

    public function toManagementData(): array
    {
        return [
            'id' => (int) $this->id,
            'username' => (string) $this->username,
            'nickname' => (string) ($this->real_name ?: $this->username),
            'email' => (string) $this->email,
            'mobile' => (string) $this->mobile,
            'status' => (int) $this->status,
            'deptId' => (int) $this->dept_id,
            'departmentIds' => $this->departmentIds(),
            'createdAt' => $this->created_at?->toDateTimeString(),
            'updatedAt' => $this->updated_at?->toDateTimeString(),
        ];
    }
}
