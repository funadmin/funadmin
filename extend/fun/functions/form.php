<?php

use fun\Form;
// Form别名
if (!class_exists('Form')) {
    class_alias('fun\\Form', 'Form');
}
// Form别名
if (!class_exists('FormBuilder')) {
    class_alias('fun\\FormBuilder', 'FormBuilder');
}
if (!class_exists('TableBuilder')) {
    class_alias('fun\\TableBuilder', 'TableBuilder');
}

if (!function_exists('form_script')) {
    /**
     * @param string $name
     * @param string $type
     * @param array $options
     * @param '' $value
     * @return string
     */
    function form_script($name=[], array $options=[])
    {
        return Form::script($name, $options);
    }
}
if (!function_exists('form_style')) {
    /**
     * @param string $name
     * @param string $type
     * @param array $options
     * @param '' $value
     * @return string
     */
    function form_style($name=[], array $options=[])
    {
        return Form::style($name, $options);
    }
}
if (!function_exists('form_js')) {
    /**
     * @param string $name
     * @param string $type
     * @param array $options
     * @param '' $value
     * @return string
     */
    function form_js($name=[], array $options=[])
    {
        return Form::js($name, $options);
    }
}
if (!function_exists('form_link')) {
    /**
     * @param string $name
     * @param string $type
     * @param array $options
     * @param '' $value
     * @return string
     */
    function form_link($name=[],$options=[])
    {
        return Form::link($name, $options);
    }
}
if (!function_exists('form_config')) {
    /**
     * @param string $name
     * @param string $type
     * @param array $options
     * @param '' $value
     * @return string
     */
    function form_config($name='',$options=[],$value='')
    {
        return Form::config($name, $options,$value);
    }
}
if (!function_exists('form_token')) {
    /**
     * @param string $name
     * @param string $type
     * @param array $options
     * @param '' $value
     * @return string
     */
    function form_token($name = '__token__', $type = 'md5')
    {
        return Form::token($name , $type);
    }
}

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
        return Form::input($name, $type, $options, $value);
    }
}

if (!function_exists('form_text')) {
    /**
     * @param $name
     * @param $options
     * @param $value
     * @return string
     */
    function form_text($name = '', $options = [], $value = '')
    {
        return Form::text($name,$options, $value);
    }
}
if (!function_exists('form_password')) {
    /**
     * @param $name
     * @param $options
     * @param $value
     * @return string
     */
    function form_password($name = '', $options = [], $value = '')
    {
        return Form::password($name,$options, $value);
    }
}
if (!function_exists('form_hidden')) {
    /**
     * @param $name
     * @param $options
     * @param $value
     * @return string
     */
    function form_hidden($name = '', $options = [], $value = '')
    {
        return Form::hidden($name,$options, $value);
    }
}
if (!function_exists('form_number')) {
    /**
     * @param $name
     * @param $options
     * @param $value
     * @return string
     */
    function form_number($name = '', $options = [], $value = '')
    {
        return Form::number($name,$options, $value);
    }
}
if (!function_exists('form_range')) {
    /**
     * @param $name
     * @param $options
     * @param $value
     * @return string
     */
    function form_range($name = '', $options = [], $value = '')
    {
        return Form::range($name,$options, $value);
    }
}
if (!function_exists('form_url')) {
    /**
     * @param $name
     * @param $options
     * @param $value
     * @return string
     */
    function form_url($name = '', $options = [], $value = '')
    {
        return Form::url($name,$options, $value);
    }
}
if (!function_exists('form_tel')) {
    /**
     * @param $name
     * @param $options
     * @param $value
     * @return string
     */
    function form_tel($name = '', $options = [], $value = '')
    {
        return Form::tel($name,$options, $value);
    }
}


if (!function_exists('form_email')) {
    /**
     * @param $name
     * @param $options
     * @param $value
     * @return string
     */
    function form_email($name = '', $options = [], $value = '')
    {
        return Form::email($name,$options, $value);
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
        return Form::rate($name, $options, $value);
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
        return Form::slider($name, $options, $value);
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
    function form_radio($name = '', $radiolist = [], $options = [], $value = '')
    {
        return Form::radio($name, $radiolist, $options, $value);
    }
}
if (!function_exists('form_switchs')) {
    /**
     * @param $name
     * @param $switch
     * @param $option
     * @param $value
     * @return string
     */
    function form_switchs($name='', $switch = [], $option = [], $value = '')
    {
        return Form::switchs($name, $switch, $option, $value);
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
        return Form::switchs($name, $switch, $option, $value);
    }
}
if (!function_exists('form_checkbox')) {
    /**
     * @param $name
     * @return string
     */
    function form_checkbox($name ='', $list = [], $option = [], $value = '')
    {
        return Form::checkbox($name, $list, $option, $value);
    }
}

if (!function_exists('form_arrays')) {
    /**
     * @param $name
     * @return string
     */
    function form_arrays($name='', $list = [], $option = [],$attr=[['key'=>'key','title'=>'key','type'=>'text'],['key'=>'value','title'=>'value','type'=>'text']])
    {
        return Form::arrays($name, $list, $option,$attr);
    }
}

if (!function_exists('form_array')) {
    /**
     * @param $name
     * @return string
     */
    function form_array($name='', $list = [], $option = [],$attr=[['key'=>'key','title'=>'key','type'=>'text'],['key'=>'value','title'=>'value','type'=>'text']])
    {
        return Form::arrays($name, $list, $option,$attr);
    }
}


if (!function_exists('form_textarea')) {
    /**
     * @param $name
     * @return string
     */
    function form_textarea($name = '', $option = [], $value = '')
    {
        return Form::textarea($name, $option, $value);
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
        if (!empty($value) and !is_array($value)) $value = explode(',', (string)$value);
        return Form::select($name, $select, $options, $attr, $value);
    }
}

if (!function_exists('form_selects')) {
    /**
     * @param $name
     * @param $select
     * @param $options
     * @param $attr
     * @param $value
     * @return string
     */
    function form_selects($name = '', $select = [], $options = [], $attr = '', $value = '')
    {
        if (!empty($attr) and !is_array($attr)) $attr = explode(',', $attr);
        return Form::selects($name, $select, $options, $attr, $value);
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
        return Form::selects($name, $select, $options, $attr, $value);
    }
}

if (!function_exists('form_selectcx')) {
    /**
     * @param $name
     * @param $select
     * @param $options
     * @param $attr
     * @param $value
     * @return string
     */
    function form_selectcx($name = '', $select = [], $options = [], $attr = ['province_id','city_id','area_id'], $value = '')
    {
        if (!empty($attr) and !is_array($attr)) $attr = explode(',', $attr);
        return Form::selectcx($name, $select, $options, $attr, $value);
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
        return Form::xmselect($name, $select, $options, $attr, $value);
    }
}
if (!function_exists('form_icon')) {
    /**
     * @param array $options
     * @return string
     */

    function form_icon($name = '', $options = [], $value = '')
    {
        return Form::icon($name, $options, $value);
    }
}

if (!function_exists('form_date')) {
    /**
     * @param array $options
     * @return string
     */

    function form_date($name = '', $options = [], $value = '')
    {
        return Form::date($name, $options, $value);
    }
}

if (!function_exists('form_city')) {
    /**
     * @param array $options
     * @return string
     */

    function form_city($name = 'cityPicker', $options = [])
    {
        return Form::city($name, $options);
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
        return Form::tags($name, $options, $value);
    }
}
if (!function_exists('form_color')) {
    /**
     * @param array $options
     * @return string
     */

    function form_color($name = '', $options = [], $value = '')
    {
        return Form::color($name, $options, $value);
    }
}

if (!function_exists('form_label')) {
    /**
     * @param bool $reset
     * @param array $options
     * @return string
     */
    function form_label($label = '', $options = [])
    {
        return Form::label($label, $options);
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
        return Form::submitbtn($reset, $options);
    }
}

if (!function_exists('form_submit')) {
    /**
     * @param bool $reset
     * @param array $options
     * @return string
     */
    function form_submit($reset = true, $options = [])
    {
        return Form::submit($reset, $options);
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
        return Form::closebtn($reset, $options);
    }
}
if (!function_exists('form_upload')) {
    /**
     * @param $name
     * @param '' $formdata
     * @return string
     */
    function form_upload($name = '',  $options = [], $value = '')
    {
        return Form::upload($name, $options, $value);
    }
}
if (!function_exists('form_editor')) {
    /**
     * @param $name
     * @return string
     */
    function form_editor($name = 'content', $options = [], $value = '')
    {
        return Form::editor($name, $options, $value);
    }
}
if (!function_exists('form_json')) {
    /**
     * @param $name
     * @return string
     */
    function form_json($name = 'json', $options = [], $value = '')
    {
        return Form::json($name, $options, $value);
    }
}

if (!function_exists('form_selectpage')) {
    /**
     * @param $name
     * @return string
     */
    function form_selectpage($name = 'selectpage', $list = [], $options = [], $value=null)
    {
        return Form::selectpage($name, $list, $options, $value);
    }
}
if (!function_exists('form_autocomplete')) {
    /**
     * @param $name
     * @return string
     */
    function form_autocomplete($name = 'autocomplete', $list = [], $options = [], $value=null)
    {
        return Form::autocomplete( $name, $list ,  $options ,$value) ;
    }
}
if (!function_exists('form_transfer')) {
    /**
     * @param $name
     * @return string
     */
    function form_transfer($name = 'transfer',$select=[], $options = [], $value = '')
    {
        return Form::transfer($name,$select, $options, $value);
    }
}