<?php
/**
 * funadmin
 * ============================================================================
 * 版权所有 2018-2027 funadmin，并保留所有权利。
 * 网站地址: https://www.funadmin.com
 * ----------------------------------------------------------------------------
 * 采用最新Thinkphp6实现
 * ============================================================================
 * Author: yuege
 * Date: 2019/8/2
 */
namespace app\cms\validate;
use think\Validate;

class Field extends Validate
{
    protected $rule = [
        'moduleid|模型名称' => [
            'require' => 'require',
            'max'     => '5',
        ],
        'diyformid|自定义模型名' => [
            'require' => 'require',
            'max'     => '5',
        ],
        'type|字段类型' => [
            'require' => 'require',
            'max'     => '20',
        ],
        'field|字段名' => [
            'require' => 'require',
            'max'     => '20',
        ],
        'name|别名' => [
            'require' => 'require',
            'max'     => '50',
        ],
        'sort|排序' => [
            'require' => 'require',
            'number'  => 'number',
            'max'     => '10',
        ]
    ];
    protected $scene = [
        'module'  =>  ['type','field','name','sort'],
        'diyform'  =>  ['type','field','name','sort'],
    ];

}