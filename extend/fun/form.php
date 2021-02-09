<?php
use fun\helper\FormHelper;
if (!function_exists('form_input')) {
    /**
     * @param $type
     * @param $name
     * @return string
     */
    function form_input($name='',$type='text',$options=[],$value=null)
    {
        return FormHelper::input($name,$type,$options,$value);
    }
}
if (!function_exists('form_radio')) {
    /**
     * @param $id
     * @param $name
     * @return string
     */
    function form_radio($name=null, $radiolist = null, $options = [],$value='')
    {
        return FormHelper::radio($name,$radiolist,$options,$value);
    }
}

if (!function_exists('form_switch')) {
    /**
     * @param $id
     * @param $name
     * @param $switch
     * @param $option
     * @param $value
     * @return string
     */
    function form_switch($name,$switch=[] , $option=[],$value='')
    {
        return FormHelper::switchs($name,$switch , $option,$value);
    }
}
if (!function_exists('form_checkbox')) {
    /**
     * @param $id
     * @param $name
     * @return string
     */
    function form_checkbox($name,$list, $option, $value)
    {
        return FormHelper::checkbox($name,$list, $option, $value);
    }
}

if (!function_exists('form_arrays')) {
    /**
     * @param $id
     * @param $name
     * @return string
     */
    function form_arrays($name, $option, $list=[])
    {
        return FormHelper::arrays($name, $option, $list);
    }
}


if (!function_exists('form_textarea')) {
    /**
     * @param $id
     * @param $name
     * @return string
     */
    function form_textarea($name=null, $option=[], $value=null)
    {
        return FormHelper::textarea($name, $option,$value);
    }
}
if (!function_exists('form_select')) {
    /**
     * @param null $name
     * @param array $options
     * @return string
     */
    function form_select($name = null,$select=[], $options = [],$attr='',$value=null)
    {
        if(!empty($attr) and !is_array($attr)) $attr = explode(',',$attr);
        return FormHelper::multiselect($name,$select,$options,$attr,$value);
    }
}
if (!function_exists('form_multiselect')) {
    /**
     * @param null $name
     * @param array $options
     * @return string
     */
    function form_multiselect($name = null,$select=[], $options = [],$attr='',$value='')
    {
        if(!empty($attr) and !is_array($attr))$attr = explode(',',$attr);
        return FormHelper::multiselect($name,$select,$options,$attr,$value);
    }
}
if (!function_exists('form_xmselect')) {
    /**
     * @param null $name
     * @param array $options
     * @return string
     */
    function form_xmselect($name = null,$select=[], $options = [],$attr='',$value='')
    {
        if(!empty($attr) and !is_array($attr))$attr = explode(',',$attr);
        return FormHelper::xmselect($name,$select,$options,$attr,$value);
    }
}
if (!function_exists('form_icon')) {
    /**
     * @param array $options
     * @return string
     */

    function form_icon($name=null,$options = [],$value=null)
    {
        return FormHelper::icon($name, $options,$value);
    }
}

if (!function_exists('form_date')) {
    /**
     * @param array $options
     * @return string
     */

    function form_date($name=null,$options = [],$value='')
    {
        return FormHelper::date($name, $options,$value);
    }
}

if (!function_exists('form_city')) {
    /**
     * @param array $options
     * @return string
     */

    function form_city($name='cityPicker',$id='cityPicker',$options = [])
    {
        return FormHelper::city($name,$id,$options);
    }
}
if (!function_exists('form_region')) {
    /**
     * @param array $options
     * @return string
     */

    function form_region($name='regionCheck',$id='regionCheck',$options = [])
    {
        return FormHelper::region($name,$id,$options);
    }
}
if (!function_exists('form_tags')) {
    /**
     * @param array $options
     * @return string
     */

    function form_tags($id='tags',$name='',$options = [],$value='')
    {
        return FormHelper::tags($id,$name,$options,$value);
    }
}
if (!function_exists('form_color')) {
    /**
     * @param array $options
     * @return string
     */

    function form_color($id='iconPicker',$name=null,$options = [],$value='')
    {
        return FormHelper::color($id,$name,$options,$value);
    }
}
if (!function_exists('form_submitbtn')) {
    /**
     * @param bool $reset
     * @param array $options
     * @return string
     */
    function form_submitbtn($reset = true, $options=[])
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
    function form_closebtn($reset = true, $options=[])
    {
        return FormHelper::closebtn($reset,$options);
    }
}
if (!function_exists('form_upload')) {
    /**
     * @param $name
     * @param null $formdata
     * @return string
     */
    function form_upload($name=null,$formdata=[],$options=[])
    {
        return FormHelper::upload($name,$formdata,$options);
    }
}
if (!function_exists('form_editor')) {
    /**
     * @param $id
     * @param $name
     * @return string
     */
    function form_editor($name='container',$id='container',$type=1,$options=[])
    {
        return FormHelper::editor($name,$id,$type,$options);
    }
}
