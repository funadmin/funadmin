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
 * @see \fun\helper\FormHelper
 * @class   Form
 * @mixin  \fun\helper\FormHelper
 * @package fun-addons
 * @method static string token() 生成Token
 * @method static string label(string $name,  array $options = [], string $value = null) label标签
 * @method static string tags(string $name,  array $options = [], string $value = null) label标签
 * @method static string input($name,$type, array $options = [], string $value = null) 按类型生成文本框
 * @method static string text(string $name,  array $options = [], string $value = null) 普通文本框
 * @method static string password(string $name, array $options = [], string $value = null) 密码文本框
 * @method static string hidden(string $name,  array $options = [], string $value = null) 隐藏文本框
 * @method static string email(string $name, array $options = [], string $value = null) Email文本框
 * @method static string url(string $name,  array $options = [], string $value = null) URL文本框
 * @method static string tel(string $name,  array $options = [], string $value = null) URL文本框
 * @method static string range(string $name,  array $options = [], string $value = null) URL文本框
 * @method static string color(string $name,  array $options = [], string $value = null) numb文本框
 * @method static string rate(string $name,  array $options = [], string $value = null) numb文本框
 * @method static string icon(string $name,  array $options = [], string $value = null) numb文本框
 * @method static string slider(string $name,  array $options = [], string $value = null) numb文本框
 * @method static string upload(string $name, array $options = [], string $value = null) 文件上传组件
 * @method static string textarea(string $name, array $options = [], string $value = null) 多行文本框
 * @method static string editor(string $name, array $options = [], string $value = null) 富文本编辑器
 * @method static string arrays(string $name, array $list = [],  array $options = []) 数组
 * @method static string select(string $name, array $list = [],  array $options = [],array $attr = [],$value) 下拉列表组件
 * @method static string selects(string $name, array $list = [],  array $options = [],array $attr = [],$value) 下拉列表组件
 * @method static string selectplus(string $name, array $list = [],  array $options = [],array $attr = [],$value) 下拉列表组件
 * @method static string selectn(string $name, array $list = [],  array $options = [],array $attr = [],$value) 下拉列表组件
 * @method static string multiselect(string $name, array $list = [],  array $options = [],array $attr = [],$value) 下拉列表组件
 * @method static string xmselect(string $name, array $list = [],  array $options = [],array $attr = [],$value) 下拉列表组件
 * @method static string selectpage(string $name,array $list = [], array $options = [],array $attr = [],$value='') 动态下拉列表组件
 * @method static string autocomplete(string $name,array $list = [], array $options = [],array $attr = [],$value='') 自动完成
 * @method static string citypicker(string $name, array $options = [], string $value = null) 城市选择组件
 * @method static string region(string $name, array $options = [], string $value = null) 城市选择组件
 * @method static string date(string $name, array $options = [],string $value= null) 日期选择组件
 * @method static string switchs(string $name, array $list = [], array $options = [], string $value = null) 切换组件
 * @method static string checkbox(string $name, array $list = [],  array $options = [],string $value = '1', ) 单个复选框
 * @method static string radio(string $name, array $list = [], array $options = []) 单个单选框
 * @method static string link(string $name = null,array $options = []) css
 * @method static string style(string $name = null, array $options = []) 上传文件组件(多文件)）
 * @method static string js(string $name = null, array $options = []) 表单button
 * @method static string script(string $name = null, array $options = []) 表单button
 */
class Form extends Facade
{
    /**
     * 获取当前Facade对应类名（或者已经绑定的容器对象标识）
     * @access protected
     * @return string
     */
    protected static function getFacadeClass()
    {
        return 'fun\helper\FormHelper';
    }
}
