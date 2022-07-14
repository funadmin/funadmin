<?php

/**
 * FunAdmin
 * ============================================================================
 * 版权所有 2017-2028 FunAdmin，并保留所有权利。
 * 网站地址: https://www.FunAdmin.com
 * ----------------------------------------------------------------------------
 * 采用最新Thinkphp6实现
 * ============================================================================
 * Author: yuege
 * Date: 2019/10/3
 */

use fun\helper\FormHelper;

if (!function_exists('form_input')) {
    /**
     * @param string $name
     * @param string $type
     * @param array $options
     * @param '' $value
     * @return string
     */
    function form_input($name = '', $type = 'text', $options = [], $value = '')
    {
        return FormHelper::input($name, $type, $options, $value);
    }
}
if (!function_exists('form_rate')) {
    /**
     * 评分
     * @param string $name
     * @param array $options
     * @param '' $value
     * @return string
     */
    function form_rate($name = '', $options = [], $value = '')
    {
        return FormHelper::rate($name, $options, $value);
    }
}

if (!function_exists('form_slider')) {
    /**
     * 滑块
     * @param string $name
     * @param array $options
     * @param '' $value
     * @return string
     */
    function form_slider($name = '', $options = [], $value = '')
    {
        return FormHelper::slider($name, $options, $value);
    }
}
if (!function_exists('form_radio')) {
    /**
     * @param '' $name
     * @param '' $radiolist
     * @param array $options
     * @param string $value
     * @return string
     */
    function form_radio($name = '', $radiolist = '', $options = [], $value = '')
    {
        return FormHelper::radio($name, $radiolist, $options, $value);
    }
}

if (!function_exists('form_switch')) {
    /**
     * @param $name
     * @param $switch
     * @param $option
     * @param $value
     * @return string
     */
    function form_switch($name='', $switch = [], $option = [], $value = '')
    {
        return FormHelper::switchs($name, $switch, $option, $value);
    }
}
if (!function_exists('form_checkbox')) {
    /**
     * @param $name
     * @return string
     */
    function form_checkbox($name ='', $list = [], $option = [], $value = '')
    {
        return FormHelper::checkbox($name, $list, $option, $value);
    }
}

if (!function_exists('form_arrays')) {
    /**
     * @param $name
     * @return string
     */
    function form_arrays($name='', $list = [], $option = [])
    {
        return FormHelper::arrays($name, $list, $option);
    }
}


if (!function_exists('form_textarea')) {
    /**
     * @param $name
     * @return string
     */
    function form_textarea($name = '', $option = [], $value = '')
    {
        return FormHelper::textarea($name, $option, $value);
    }
}
if (!function_exists('form_select')) {
    /**
     * @param '' $name
     * @param array $options
     * @return string
     */
    function form_select($name = '', $select = [], $options = [], $attr = '', $value = '')
    {
        if (!empty($attr) and !is_array($attr)) $attr = explode(',', $attr);
        if (!empty($value) and !is_array($value)) $value = explode(',', $value);
        return FormHelper::multiselect($name, $select, $options, $attr, $value);
    }
}
if (!function_exists('form_multiselect')) {
    /**
     * @param $name
     * @param $select
     * @param $options
     * @param $attr
     * @param $value
     * @return string
     */
    function form_multiselect($name = '', $select = [], $options = [], $attr = '', $value = '')
    {
        if (!empty($attr) and !is_array($attr)) $attr = explode(',', $attr);
        return FormHelper::multiselect($name, $select, $options, $attr, $value);
    }
}
if (!function_exists('form_selectplus')) {
    /**
     * @param $name
     * @param $select
     * @param $options
     * @param $attr
     * @param $value
     * @return string
     */
    function form_selectplus($name = '', $select = [], $options = [], $attr = '', $value = '')
    {
        if (!empty($attr) and !is_array($attr)) $attr = explode(',', $attr);
        return FormHelper::selectplus($name, $select, $options, $attr, $value);
    }
}
if (!function_exists('form_selectn')) {
    /**
     * @param $name
     * @param $select
     * @param $options
     * @param $attr
     * @param $value
     * @return string
     */
    function form_selectn($name = '', $select = [], $options = [], $attr = '', $value = '')
    {
        if (!empty($attr) and !is_array($attr)) $attr = explode(',', $attr);
        return FormHelper::selectn($name, $select, $options, $attr, $value);
    }
}
if (!function_exists('form_xmselect')) {
    /**
     * @param '' $name
     * @param array $options
     * @return string
     */
    function form_xmselect($name = '', $select = [], $options = [], $attr = '', $value = '')
    {
        if (!empty($attr) and is_array($attr)) $attr = implode(',', $attr);
        return FormHelper::xmselect($name, $select, $options, $attr, $value);
    }
}
if (!function_exists('form_icon')) {
    /**
     * @param array $options
     * @return string
     */

    function form_icon($name = '', $options = [], $value = '')
    {
        return FormHelper::icon($name, $options, $value);
    }
}

if (!function_exists('form_date')) {
    /**
     * @param array $options
     * @return string
     */

    function form_date($name = '', $options = [], $value = '')
    {
        return FormHelper::date($name, $options, $value);
    }
}

if (!function_exists('form_city')) {
    /**
     * @param array $options
     * @return string
     */

    function form_city($name = 'cityPicker', $options = [])
    {
        return FormHelper::city($name, $options);
    }
}
if (!function_exists('form_region')) {
    /**
     * @param array $options
     * @return string
     */

    function form_region($name = 'regionCheck', $options = [])
    {
        return FormHelper::region($name, $options);
    }
}
if (!function_exists('form_tags')) {
    /**
     * @param array $options
     * @return string
     */

    function form_tags($name = '', $options = [], $value = '')
    {
        $value = is_array($value) ? implode(',', $value) : $value;
        return FormHelper::tags($name, $options, $value);
    }
}
if (!function_exists('form_color')) {
    /**
     * @param array $options
     * @return string
     */

    function form_color($name = '', $options = [], $value = '')
    {
        return FormHelper::color($name, $options, $value);
    }
}
if (!function_exists('form_submitbtn')) {
    /**
     * @param bool $reset
     * @param array $options
     * @return string
     */
    function form_submitbtn($reset = true, $options = [])
    {
        return FormHelper::submitbtn($reset, $options);
    }
}
if (!function_exists('form_closebtn')) {
    /**
     * @param bool $reset
     * @param array $options
     * @return string
     */
    function form_closebtn($reset = true, $options = [])
    {
        return FormHelper::closebtn($reset, $options);
    }
}
if (!function_exists('form_upload')) {
    /**
     * @param $name
     * @param '' $formdata
     * @return string
     */
    function form_upload($name = '', $formdata = [], $options = [], $value = '')
    {
        return FormHelper::upload($name, $formdata, $options, $value);
    }
}
if (!function_exists('form_editor')) {
    /**
     * @param $name
     * @return string
     */
    function form_editor($name = 'container', $type = 1, $options = [], $value = '')
    {
        return FormHelper::editor($name, $type, $options, $value);
    }
}
