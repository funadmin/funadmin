<?php
/**
 * lemocms
 * ============================================================================
 * 版权所有 2018-2027 lemocms，并保留所有权利。
 * 网站地址: https://www.lemocms.com
 * ----------------------------------------------------------------------------
 * 采用最新Thinkphp6实现
 * ============================================================================
 * Author: yuege
 * Date: 2019/9/2
 */
namespace app\admin\validate;
use think\Validate;

class CmsModule extends Validate
{
    protected $rule = [
        'title|模型名称' => [
            'require' => 'require',
            'max'     => '100',
            'unique'  => 'cms_module',
        ],
        'name|表名' => [
            'require' => 'require',
            'max'     => '50',
            'unique'  => 'cms_module',
        ],
        'listfields|列表页字段' => [
            'require' => 'require',
            'max'     => '255',
        ],
        'description|描述' => [
            'max' => '200',
        ],
        'sort|排序' => [
            'require' => 'require',
            'number'  => 'number',
        ]
    ];
}