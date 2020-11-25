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
 * Date: 2019/8/2
 */
namespace app\admin\validate;

use think\validate;

class BbsAdvPosition extends validate
{
    protected $rule = [
        'position_name|广告位置名' => [
            'require' => 'require',
            'unique' =>'bbs_adv_position'
        ],


    ];
}