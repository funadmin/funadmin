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
use app\admin\model\BackendModel;

class Admin extends BackendModel {

    /**
     * @var bool
     */
    use LaravelSoftDelete;


    



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

}
