<?php
declare (strict_types = 1);

namespace app\backend\validate;

use think\Validate;

class TestCate extends Validate
{
    /**
     * 定义验证规则
     * 格式：'字段名' =>  ['规则1','规则2'...]
     * @var array
     */
    protected $rule = [];

    /**
     * 定义错误信息
     * 格式：'字段名.规则名' =>  '错误信息'
     * @var array
     */
    protected $message = [];

    /**
    * 验证场景
    */
    protected $scene = [
        'add'  => [],
        'edit' => [],
    ];
}
