<?php

/**
 * FunAdmin
 * ============================================================================
 * 版权所有 2017-2028 FunAdmin，并保留所有权利。
 * 网站地址: https://www.FunAdmin.com
 * ----------------------------------------------------------------------------
 * 采用最新Thinkphp8实现
 * ============================================================================
 * Author: yuege
 * Date: 2019/10/3
 */

namespace fun;

use think\Facade;
/**
 * 表单元素生成
 * @class   FormBuilder
 * @mixin  \fun\builder\FormBuilder
 * @see \fun\builder\FormBuilder
*/
class FormBuilder extends Facade
{
    /**
     * 获取当前Facade对应类名（或者已经绑定的容器对象标识）
     * @access protected
     * @return string
     */
    protected static function getFacadeClass()
    {
        return 'fun\builder\FormBuilder';
    }
}
