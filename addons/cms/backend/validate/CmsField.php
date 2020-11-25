<?php
namespace addons\cms\backend\validate;
use think\Validate;

class CmsField extends Validate
{
    protected $rule = [
        'moduleid|模型名称' => [
            'require' => 'require',
            'max'     => '5',
        ],
        'type|字段类型' => [
            'require' => 'require',
            'max'     => '20',
        ],
        'name|字段名' => [
            'require' => 'require',
            'max'     => '20',
        ],
        'aliasname|别名' => [
            'require' => 'require',
            'max'     => '50',
        ],
        'sort|排序' => [
            'require' => 'require',
            'number'  => 'number',
            'max'     => '10',
        ]
    ];
}