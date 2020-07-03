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


return [
     \think\middleware\LoadLangPack::class,

     \think\middleware\SessionInit::class,
    //访问频率
//    \think\middleware\Throttle::class,
];

