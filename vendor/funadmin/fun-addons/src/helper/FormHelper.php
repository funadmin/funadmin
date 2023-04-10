<?php

/**
 * FunAdmin
 * ============================================================================
 * 版权所有 2017-2028 FunAdmin，并保留所有权利。
 * 网站地址: http://www.funadmin.com
 * ----------------------------------------------------------------------------
 * 采用最新Thinkphp6 + layui 实现
 * ============================================================================
 * Author: yuege
 * Date: 2019/9/22
 */

namespace fun\helper;


use think\helper\Str;

class FormHelper
{
    /**
     * @param $name
     * @param $value
     * @param $options
     * @param $list
     * @param $attr
     * @return string
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\DbException
     * @throws \think\db\exception\ModelNotFoundException
     */
    public  function config($name='',$options=[],$value='')
    {
        $where = ['code'=>$name];
        $data = \app\common\model\Config::where($where)->find();
        if(!$data) return '';
        $extra=[];
        if(!empty($options['extra'])){
            $data['extra'] = $options['extra'];
        }
        if ($data['extra'] && is_string($data['extra'])){
            $arr = array_filter(explode("\n",str_replace("\r",'',$data['extra'])));
            foreach ($arr as $v){
                $kk = explode(':',$v);
                $extra[$kk[0]] = $kk[1];
            }
        }
        $options['verify'] = $options['verify']??$data['verify'];
        $options['label'] = $options['label']??$data['remark'];
        $value = $value?$value:$data['value'];
        switch ($data['type']) {
                case'switch':
                    $list = ($options['list']??$extra);
                    $form =  $this->switchs($name,$list,$options,$value);
                    break;
                case'radio':
                    $list = ($options['list']??$extra);
                    $form =  $this->radio($name,$list,$options,$value);
                    break;
                case 'hidden':
                    $form = $this->hidden($name,  $options, $value);
                    break;
                case 'float':
                case 'decimal':
                case 'number':
                    $form = $this->number($name, $options, $value);
                    break;
                case 'select':
                    $attr = $options['attr']??['id','title'];
                    $list = ($options['list']??$extra);
                    $form =  $this->multiselect($name,$list,$options,$attr,$value);
                    break;
                case 'selects':
                    $options['multiple'] = 'multiple';
                    $attr = $options['attr']??['id','title'];
                    $list = ($options['list']??$extra);
                    $form =  $this->multiselect($name,$list,$options,$attr,$value);
                    break;
                case 'xmselect':
                    $attr = $options['attr']??['id','title'];
                    $list = ($options['list']??$extra);
                    $form =  $this->xmselect($name,$list, $options,$attr,$value);
                    break;
                case 'selectpage':
                    $list = ($options['list']??$extra);
                    $form =  $this->selectpage($name,$list,$options,$value);
                    break;
                case 'tags':
                    $form =  $this->tags($name, $options,$value);
                    break;
                case 'checkbox':
                    $list = ($options['list']??$extra);
                    $form =  $this->checkbox($name,$list, $options,$value);
                    break;
                case 'textarea':
                    $form =  $this->textarea($name, $options,$value);
                    break;
                case 'range':
                    $form = $this->range($name,  $options, $value);
                    break;
                case 'daterange':
                    $options['type'] = 'datetime';
                    $options['range'] = true;
                    $form =  $this->date($name, $options,$value);
                    break;
                case 'year':
                    $options['type'] = 'year';
                    $form =  $this->date($name, $options,$value);
                    break;
                 case 'month':
                    $options['type'] = 'month';
                    $form =  $this->date($name, $options,$value);
                 break;
                case 'time':
                    $options['type'] = 'time';
                    $form =  $this->date($name, $options,$value);
                    break;
                case 'date':
                case 'datetime':
                    $options['type'] = 'datetime';
                    $form =  $this->date($name, $options,$value);
                    break;
                case 'password':
                    $form =  $this->password($name, $options,$value);
                    break;
                case 'image':
                case 'file':
                    $form =  $this->upload($name,$value,$options,$value);
                    break;
                case "images":
                case 'files':
                    $options['num'] = 100;
                    $form =  $this->upload($name,$value,$options,$value);
                    break;
                case 'editor':
                    $form =  $this->editor($name,2,$options,$value);
                    break;
                case 'color':
                    $form =  $this->color($name,$options,$value);
                    break;
                case 'icon':
                    $form =  $this->icon($name,$options,$value);
                    break;
                case 'token':
                    $form =  $this->token($name,$value);
                    break;
                case 'email':
                    $form =  $this->email($name,$options,$value);
                    break;
                case 'tel':
                    $form =  $this->tel($name,$options,$value);
                    break;
                case 'url':
                    $form =  $this->url($name,$options,$value);
                    break;
                case 'rate':
                    $form =  $this->rate($name,$options,$value);
                    break;
                case 'slider':
                    $form =  $this->slider($name,$options,$value);
                    break;
                case 'arrays':
                    $attr = $options['attr']??['id','title'];
                    $list = ($options['list']??$extra);
                    $form =  $this->arrays($name,$list,$options);
                    break;
                case 'selectn':
                    $attr = $options['attr']??['id','title'];
                    $list = ($options['list']??$extra);
                    $form =  $this->selectn($name,$list,$options,$attr,$value);
                    break;
                case 'selectplus':
                    $attr = $options['attr']??['id','title'];
                    $list = ($options['list']??$extra);
                    $form =  $this->selectplus($name,$list,$options,$attr,$value);
                    break;
                case 'city':
                    $form =  $this->city($name,$options);
                    break;
                case 'region':
                    $form =  $this->region($name,$options);
                    break;
                default :
                    $form =  $this->input($name, 'text',$options,$value);
                    break;
            }
        return $form;
    }


    public  function token($name = '__token__', $type = 'md5')
    {
        if (function_exists('token')) {
            return token($name, $type);
        }
        return '';
    }

    /**
     * 生成文本框(按类型) password .text
     * @param string $name
     * @param string $type
     * @param array $options
     * @return string
     */
    public  function input(string $name = '', string $type = 'text',array $options = [], $value = '')
    {
        $name = $options['formname']??$name;
        $label = $options['label'] ?? $name;
        $tips = $options['tips'] ?? $label;
        $placeholder = $options['placeholder'] ?? $tips;
        $type = $options['type']??$type;
        $value = !is_null($value) ? 'value="' . $value . '"' : '';
        $disorread = $this->readonlyOrdisabled($options) ? $this->readonlyOrdisabled($options) : $this->readonlyOrdisabled($options);
        $disorread  = $disorread ? 'layui-disabled' : '';
        if ($type == 'hidden') {
            return '<input  type="' . $type . '" name="' . $name . '"  ' . $this->verify($options) . $this->filter($options) . $this->readonlyOrdisabled($options) . ' autocomplete="off"         placeholder="' . $placeholder . '" class="layui-input ' . $this->addClass($options) . ' ' . $disorread . '" ' . $value . '/>';
        }
        $str = '<div class="layui-form-item ">'.$this->label($label,$options). '<div class="layui-input-block">
         <input ' . $this->addextend($options) . '  type="' . $type . '" name="' . $name . '"  ' . $this->verify($options) . $this->filter($options) . $this->readonlyOrdisabled($options) . ' autocomplete="off"
         placeholder="' . lang($placeholder) . '" ' . $this->addstyle($options) . ' class="layui-input ' . $this->addClass($options) . ' ' . $disorread . '"' . $value . '/>
         ' . $this->tips($options) . '
         </div></div>';
        return $str;
    }

    /**
     * @param string $name
     * @param array $options
     * @param  $value
     * @return string
     */
    public  function text(string $name,array $options = [], $value = null)
    {
        return $this->input( $name,'text',$options, $value);
    }

    /**
     * 创建一个密码输入字段
     *
     * @param  string  $name
     * @param  array   $options
     *
     * @return string
     */
    public  function password(string $name, array $options = [],$value='')
    {
        $options['verify'] = isset($options['verify'])?$options['verify']:'pass';
        $options['type'] = 'password';
        return $this->input($name, 'password', $options,$value);
    }

    /**
     * 创建一个范围输入选择器
     *
     * @param  string  $name
     * @param  null    $value
     * @param  array   $options
     *
     * @return string
     */
    public  function range($name, $options = [], $value = null)
    {
        $name = $options['formname']??$name;
        $label = $options['label'] ?? $name;
        $tips = $options['tips'] ?? $label;
        $placeholder = $options['placeholder'] ?? $tips;
        $value = !is_null($value) ? 'value="' . $value . '"' : '';
        $disorread = $this->readonlyOrdisabled($options) ? $this->readonlyOrdisabled($options) : $this->readonlyOrdisabled($options);
        $disorread  = $disorread ? 'layui-disabled' : '';
        return ' <div class="layui-form-item">              '.$this->label($label, $options).'
            <div class="layui-input-block">
              <div class="layui-input-inline" style="width: 100px;">
                <input '. $this->verify($options) . $this->filter($options) . $this->readonlyOrdisabled($options) .' type="text" name="'.$name.'_min" placeholder="'.lang($placeholder).'" autocomplete="off"  class="layui-input ' . $this->addClass($options) . ' ' . $disorread . '" ' . $value . '/>
              </div>
              <div class="layui-form-mid">-</div>
              <div class="layui-input-inline" style="width: 100px;">
                <input '. $this->verify($options) . $this->filter($options) . $this->readonlyOrdisabled($options) .'  type="text" name="'.$name.'_max" placeholder="'.lang($placeholder).'" autocomplete="off"  class="layui-input ' . $this->addClass($options) . ' ' . $disorread . '" ' . $value . '/>
              </div>
            </div>
          </div>';
    }

    /**
     * 创建一个隐藏的输入字段
     *
     * @param  string  $name
     * @param  null    $value
     * @param  array   $options
     *
     * @return string
     */
    public  function hidden($name,  $options = [],$value = null)
    {
        return $this->input( $name,'hidden', $options, $value);
    }

    /**
     * 创建一个电子邮件输入字段
     *
     * @param  string  $name
     * @param  null    $value
     * @param  array   $options
     *
     * @return string
     */
    public  function email($name,  $options = [],$value = null)
    {
        $options['verify'] = isset($options['verify'])?$options['verify']:'email';
        return $this->input( $name,'email', $options, $value);
    }

    /**
     * 创建一个tel输入字段
     *
     * @param  string  $name
     * @param  null    $value
     * @param  array   $options
     *
     * @return string
     */
    public  function tel($name,  $options = [],$value = null)
    {
        $options['verify'] = isset($options['verify'])?$options['verify']:'phone';
        return $this->input( $name,'tel', $options, $value);
    }

    /**
     * 创建一个数字输入字段
     *
     * @param  string  $name
     * @param  null    $value
     * @param  array   $options
     *
     * @return string
     */
    public  function number($name,  $options = [],$value = null)
    {
        $options['verify'] = isset($options['verify'])?$options['verify']:'number';
        return $this->input( $name,'number', $options, $value);
    }

    /**
     * 创建一个url输入字段
     *
     * @param  string  $name
     * @param  null    $value
     * @param  array   $options
     *
     * @return string
     */
    public  function url($name,  $options = [],$value = null)
    {
        $options['verify'] = isset($options['verify'])?$options['verify']:'url';
        return $this->input( $name,'url', $options, $value);
    }

    /**
     * 评分
     * @param $name
     * @param $options
     * @param $value
     * @return string
     */
    public  function rate($name = '', $options = [], $value = '')
    {        
        $name = $options['formname']??$name;
        $label = $options['label'] ?? $name;
        $id = ($options['id']) ?? $name;
        $value = !is_null($value) ? $value  : '';
        $data_value = '';
        foreach ($options as $key => $val) {
            $data_value .= ' data-'.$key.'="'.$val.'" ';
        }
        $disorread = $this->readonlyOrdisabled($options) ? $this->readonlyOrdisabled($options) : $this->readonlyOrdisabled($options);
        $disorread  = $disorread ? 'layui-disabled' : '';
        $op = json_encode($options,JSON_UNESCAPED_UNICODE);
        $str = "<div class='layui-form-item " . $this->addClass($options) . "'> 
       " .$this->label($label,$options) . "
        <div class='layui-input-block'>
        <input  type='hidden' name='" . $name . "' class='layui-input' value='" . $value . "'>
        <div ". $data_value . $this->addextend($options) . $this->addstyle($options)  ." data-name='" . $name . "' data-value ='" . $value . "' id='" . $id . "'  lay-filter='rate' class='" . $this->addClass($options) . "' data-options='" . $op . "'>
        " . $this->tips($options) . "</div></div></div>";
        return $str;
    }
    /**
     * 滑块
     * @param $name
     * @param $options
     * @param $value
     * @return string
     */
    public  function slider($name = '', $options = [], $value = '')
    {
        $name = $options['formname']??$name;
        $label = $options['label'] ?? $name;
        $id = ($options['id']) ?? $name;
        $value = !is_null($value) ? $value  : '';
        $data_value = '';
        foreach ($options as $key => $val) {
            $data_value .= ' data-'.$key.'="'.$val.'" ';
        }
        $disorread = $this->readonlyOrdisabled($options) ? $this->readonlyOrdisabled($options) : $this->readonlyOrdisabled($options);
        $disorread  = $disorread ? 'layui-disabled' : '';
        $op = json_encode($options,JSON_UNESCAPED_UNICODE);
        $str = "<div class='layui-form-item " . $this->addClass($options) . "'> ". $this->label($label, $options)."
        <div class='layui-input-block' >
        <input  type='hidden'  name='" . $name . "' class='layui-input layui-input-inline' value='" . $value . "'>
        <div " .$data_value . $this->addextend($options) ." style='top:16px' data-name='" . $name . "' data-value ='" . $value . "' id='" . $id . "'  lay-filter='slider' class='" . $this->addClass($options) . "' data-options='" . $op . "'>
        " . $this->tips($options) . "
        </div></div></div>";
        return $str;
    }
    /**
     * @param $name
     * @param $radiolist
     * @param array $options
     * @param string $value
     * @return string
     */
    public  function radio($name = '', $radiolist=[], $options = [], $value = '')
    {
        if (is_null($radiolist)) {
            $radiolist = $name;
        }
        $name = $options['formname']??$name;
        $label = $options['label'] ?? $name;
        $input = '';
        if (is_string($radiolist) && strpos($radiolist, "\n") !== false) $radiolist = explode("\n", $radiolist);
        if (is_array($radiolist)) {
            foreach ($radiolist as $k => $v) {
                if (is_string($v) && strpos($v, ':') !== false) {
                    $v = explode(":", $v);
                    $input .= '<input ' . $this->addextend($options) . '  ' . $this->addstyle($options) . ' class="' . $this->addClass($options) . '" type="radio"' . $this->selectedOrchecked($value, $v[0], 2) . ' name="' . $name . '" ' . $this->verify($options) . $this->filter($options) . $this->readonlyOrdisabled($options) . ' value="' . $v[0] . '" title="' . lang($v[1]) . '" />';
                } else {
                    $input .= '<input ' . $this->addextend($options) . '  ' . $this->addstyle($options) . ' class="' . $this->addClass($options) . '" type="radio"' . $this->selectedOrchecked($value, $k, 2) . ' name="' . $name . '" ' . $this->verify($options) . $this->filter($options) . $this->readonlyOrdisabled($options) . ' value="' . $k . '" title="' . lang($v) . '" />';
                }
            }
        } else {
            $input .= '<input ' . $this->addextend($options) . '  ' . $this->addstyle($options) . ' class="' . $this->addClass($options) . '" type="radio" name="' . $name . '" ' . $this->verify($options) . $this->filter($options) . ' value="' . $radiolist . '" title="' . lang($radiolist) . '" />';
        }

        $str = ' <div class="layui-form-item">' .$this->label($label,$options) . '
            <div class="layui-input-block">
            ' . $input . '
            ' . $this->tips($options) . '
            </div>
        </div>';
        return $str;
    }

    /**
     * 生成开关
     * @param $name
     * @param $value
     * @param array $options
     * @return string
     * switch是关键字不能用
     */

    public  function switchs($name = '', $switch=[], $options = [], $value = '')
    {
        $name = $options['formname']??$name;
        $label = $options['label'] ?? $name;
        $switchArr = $switch;
        if (is_string($switch) && strpos($switch, '|')) {
            $switchArr = implode('|', $switch);
        }
        $switchStr = $switchArr ? lang($switchArr[1]) . '|' . lang($switchArr[0]) : lang('open') . '|' . 'close';
        $str = '<div class="layui-form-item">' .$this->label($label,$optons) . '
        <div class="layui-input-block">
        <input ' . $this->addextend($options) . '  ' . $this->addstyle($options) . '  class="' . $this->addClass($options) . '" type="checkbox" value="' . $value . '" checked="" name="' . $name . '" ' . $this->verify($options) . $this->filter($options) . $this->readonlyOrdisabled($options) . ' lay-skin="switch" lay-text="' . $switchStr . '"  data-text="' . lang($value) . '"/>
        ' . $this->tips($options) . '
        </div>
        </div>';

        return $str;
    }

    /**
     * 多选
     * @param null $name
     * @param array $list
     * @param array $options
     * @param $value
     * @return string
     */
    public  function checkbox($name = '', $list = [], $options = [], $value = '')
    {
        $name = $options['formname']??$name;
        if (empty($value)) $value = $name;
        if (is_string($value) && strpos($value, "\n") !== false) $value = explode("\n", $value);
        if (is_string($value) && strpos($value, ",") !== false) $value = explode(",", $value);
        if (is_string($value) && strpos($value, "|") !== false) $value = explode("|", $value);
        if (is_string($list) && strpos($list, "\n") !== false) $list = explode("\n", $list);
        if (is_string($list) && strpos($list, ",") !== false) $list = explode(",", $list);
        if (is_string($list) && strpos($list, "|") !== false) $list = explode("|", $list);
        if (
            is_string($value)
            && strpos($value, "\n") === false
            && strpos($value, ",") === false
            && strpos($value, "|") === false
        ) $value = explode(",", $value);
        $input = '';$skin = '';
        if (isset($options['skin'])) $skin = 'lay-skin="' . $options['skin'] . '"';
        if (is_array($list) && $list) {
            foreach ($list as $k => $v) {
                if (is_string($v) && strpos($v, ':') !== false) {
                    $v = explode(":", $v);
                    $check = '';
                    if (is_array($value) && in_array($v[0], $value) || $value == $v[0]) {
                        $check = 'checked';
                    }
                    $input .= '<input ' . $this->addextend($options) . '  ' . $this->addstyle($options) . '  class="' . $this->addClass($options) . '" type="checkbox" ' . $check . ' value="' . $k . '"  name="' . $name . '[' . $v[0] . ']" ' . $skin . $this->verify($options) . $this->filter($options) . $this->readonlyOrdisabled($options) . ' title="' . lang($v[1]) . '"/>';
                } else {
                    $check = '';
                    if ((is_array($value) &&  is_array($v) && in_array($v[0], $value)) || $value == $v) {
                        $check = 'checked';
                    } elseif ((is_array($value) &&  is_string($v) && in_array($k, $value)) || $value == $v) {
                        $check = 'checked';
                    }
                    $input .= '<input ' . $this->addextend($options) . '  ' . $this->addstyle($options) . '  class="' . $this->addClass($options) . '" type="checkbox" ' . $check .  '  value="' . $k . '" name="' . $name . '[' . $k . ']" ' . $skin . $this->verify($options) . $this->filter($options) . $this->readonlyOrdisabled($options) . ' title="' . lang($v) . '"/>';
                }
            }
        } else {
            $input .= '<input ' . $this->addextend($options) . '  ' . $this->addstyle($options) . '  class="' . $this->addClass($options) . '" type="checkbox" name="' . $name . '[]"  ' . $skin . $this->verify($options) . $this->filter($options) . $this->readonlyOrdisabled($options) . '  title="' . lang($value) . '"/>';
        }
        $label = $options['label'] ?? $name;
        $str = '<div class="layui-form-item">' .$this->label($label,$options) . '
        <div class="layui-input-block">
        ' . $input . $this->tips($options) . '
        </div></div>';
        return $str;
    }

    /**
     * 数组表单
     * @param null $name
     * @param array $options
     * @param array $list
     * @return string
     */
    public  function arrays($name = '', $list = [], $options = [])
    {
        $name = $options['formname']??$name;
        $label = $options['label'] ?? $name;
        $arr = '';
        $i = 0;
        if (empty($list)) {
            $arr .= '<div class="layui-form-item" >' .$this->label($label,$options) . '<div class="layui-input-inline">
                <input '. $this->verify($options) . '  type="text"  name="' . $name . '[key][]"  value="" placeholder="' . lang('key') . '" autocomplete="off" class="layui-input input-double-width">
            </div>
            <div class="layui-input-inline">
                <input '. $this->verify($options) . '  type="text"  name="' . $name . '[value][]"  value="" placeholder="' . lang('value') . '" autocomplete="off" class="layui-input input-double-width">
            </div><div class="layui-input-inline" >
                <button  data-name="' . $name . '" type="button" class="layui-btn layui-btn-warm layui-btn-sm addInput" lay-event="addInput">
                    <i class="layui-icon">&#xe654;</i>
                </button>
            </div></div>';
        }
        foreach ($list as $key => $value) {
            if ($i == 0) {
                $arr .= '<div class="layui-form-item" >' .$this->label($label,$options) . '<div class="layui-input-inline">
                <input '. $this->verify($options) . ' type="text"  name="' . $name . '[key][]"  value="' . $key . '" placeholder="' . lang('key') . '" autocomplete="off" class="layui-input input-double-width">
            </div>
            <div class="layui-input-inline">
                <input '. $this->verify($options) . ' type="text"  name="' . $name . '[value][]"  value="' . $value . '" placeholder="' . lang('value') . '" autocomplete="off" class="layui-input input-double-width">
            </div><div class="layui-input-inline" >
                <button  data-name="' . $name . '" type="button" class="layui-btn layui-btn-warm layui-btn-sm addInput" lay-event="addInput">
                    <i class="layui-icon">&#xe654;</i>
                </button>
            </div></div>';;
            } else {
                $arr .= '<div class="layui-form-item"><label class="layui-form-label ' . $this->labelRequire($options) . '"></label><div class="layui-input-inline">
                <input '. $this->verify($options) . ' type="text"  name="' . $name . '[key][]" value="' . $key . '"  placeholder="' . lang('key') . '" autocomplete="off" class="layui-input input-double-width">
                </div><div class="layui-input-inline">
                <input '. $this->verify($options) . ' type="text"  name="' . $name . '[value][]" value="' . $value . '" placeholder="' . lang('value') . '" autocomplete="off" class="layui-input input-double-width">
            </div><div class="layui-input-inline">
                <button  data-name="' . $name . '" type="button" class="layui-btn layui-btn-danger layui-btn-sm removeInupt" lay-event="removeInupt">
                    <i class="layui-icon">&#xe67e;</i>
                </button>
            </div></div>';
            }
            $i++;
        }
        $str = '<div id="' . $name . '">' . $arr . '</div>';
        return $str;
    }

    /**
     * 文本
     * @param null $name
     * @param array $options
     * @param $value
     * @return string
     */
    public  function textarea($name = '', $options = [], $value = '')
    {
        $name = $options['formname']??$name;
        $label = $options['label'] ?? $name;
        $tips = $options['tips'] ?? $name;
        $placeholder = $options['placeholder'] ?? $tips;
        $str = ' <div class="layui-form-item layui-form-text">' .$this->label($label,$options) . '            <div class="layui-input-block">
            <textarea '. $this->addextend($options) . ' ' . $this->addstyle($options) . '  placeholder="' . lang($placeholder) . '" class="layui-textarea ' . $this->addClass($options) . '" 
            ' . $this->filter($options) . $this->verify($options) . ' name="' . $name . '"
            value="' . $value . '">' . $value . '</textarea>
            ' . $this->tips($options) . '
            </div></div>';
        return $str;
    }

    /**
     * @param $name
     * @param $select
     * @param $options
     * @param $attr
     * @param $value
     * @return string
     */
    public  function selectn($name = '', $select= [], $options=[], $attr=[], $value='')
    {
        $name = $options['formname']??$name;
        $label = $options['label'] ?? $name;
        $options['url'] =  $options['url'] ?? '';
        $options['delimiter'] =   $options['delimiter'] ?? '';
        $options['search']=   isset($options['search']) ? true : '';
        $options['num'] =   $options['num'] ?? 3;
        $options['last'] =   $options['last'] ?? '';
        if ($attr) {
            $attr = is_array($attr) ? implode(',', $attr) : $attr;
        }
        $op = '';
        foreach ($options as $key => $val) {
            $op .= ' data-'.$key.'="'.$val.'" ';
        }
        $op .='data-value="' . $value . '" data-attr="' . $attr . '"';
        if (is_array($select)) {
            $op .= ' data-data="' . json_encode($select, JSON_UNESCAPED_UNICODE) . '"';
        }
        if (is_object($select)) {
            $op .= ' data-data="' . json_encode((array)$select, JSON_UNESCAPED_UNICODE) .'"';
        }
        $str = '<div class="layui-form-item layui-form" lay-filter="' . $name . '">' .$this->label($label,$options) . '
                <div class="layui-input-block">
                  <div  data-verify ="'.$this->labelRequire($options).'"' . $this->addextend($options) . '  id="' . $name . '"' . $op . ' lay-filter="selectN" ' . $this->addClass($options) . ' name="' . $name . '" '   . ' ' . $this->search($options) . ' ' . $this->readonlyOrdisabled($options) . ' >
                  </div>
                  ' . $this->tips($options) . '
                </div>
                </div>';
        return $str;
    }
    /**
     * @param $name
     * @param $select
     * @param $options
     * @param $attr
     * @param $value
     * @return string
     */
    public  function selectplus($name = '', $select= [], $options=[], $attr=[], $value='')
    {
        $name = $options['formname']??$name;
        $options['url']  = $options['url'] ?? '';
        $id = $options['id'] ?? $name;
        $label = $options['label'] ?? $name;
        $options['delimiter'] =   $options['delimiter'] ?? '';
        $options['fielddelimiter'] =   $options['fielddelimiter'] ?? '';
        $multiple = isset($options['multiple']) ? 'multiple="multiple"' : '';
        $options['multiple'] = $multiple?1:'';
        if ($attr) {
            $attr = is_array($attr) ? implode(',', $attr) : $attr;
        }
        $op = '';
        foreach ($options as $key => $val) {
            $op .= ' data-'.$key.'="'.$val.'" ';
        }
        $op .=  ' data-value="' . $value . '" data-attr="' . $attr . '" ';
        if (is_array($select)) {
            $op .= " data-data='" . json_encode($select, JSON_UNESCAPED_UNICODE) . "'";
        }
        if (is_object($select)) {
            $op .= ' data-data="' . json_encode((array)$select, JSON_UNESCAPED_UNICODE) . '"';
        }
        $str = '<div class="layui-form-item">' .$this->label($label,$options) . '
                <div class="layui-input-block">
                  <div id="' . $id . '"  data-verify ="'.$this->labelRequire($options).'"' . $this->addextend($options) .  $op . ' lay-filter="selectPlus" ' . $this->addClass($options) . ' name="' . $name . '" ' . $multiple . ' ' . $this->search($options) . ' ' . $this->readonlyOrdisabled($options) . ' >
                  
                  </div>
                  ' . $this->tips($options) . '
                </div>
                </div>';
        return $str;
    }
    /**
     * @param $name
     * @param $select
     * @param $options
     * @param $attr
     * @param $value
     * @return string
     */
    public  function multiselect($name = '', $select=[], $options=[], $attr=[], $value='')
    {
        $name = $options['formname']??$name;
        $op = '';
        if ($select) {
            foreach ($select as $k => $v) {
                $selected = '';
                if (is_array($v) && (is_array($value) && is_array($attr) && !empty($attr) && in_array($v[$attr[0]], $value) || (is_array($attr) && !empty($attr)  && $v[$attr[0]] == $value))) {
                    $selected = 'selected';
                }
                if (is_array($value) && in_array($k, $value) && !$attr) {
                    $selected = 'selected';
                }
                if(is_string($v)){
                    $op .= '<option ' . $selected . ' value="' . $k . '">' . lang($v) . '</option>';
                }
                if (!empty($attr) && (is_array($v) || is_object($v))) {
                    $op .= '<option ' . $selected . ' value="' . $v[$attr[0]] . '">' . lang($v[$attr[1]]) . '</option>';
                }
            }
        }
        $id = $options['id']??$name;
        $label = $options['label'] ?? $name;
        $url = $options['url'] ?? '';
        $multiple = '';
        if (isset($options['multiple'])) {
            $multiple = 'multiple="multiple"';
        }
        if (isset($options['default'])) {
            $default = lang($options['default']);
        } else {
            $default = lang('Default');
        }
        $attr = is_array($attr) ? implode(',', $attr) : $attr;
        $str = '<div class="layui-form-item">' .$this->label($label,$options) . '
                <div class="layui-input-block">
                  <select data-id="'.$id.'" data-attr="' . $attr . '" data-url="' . $url . '" ' .  $this->addextend($options) . ' ' . $this->addstyle($options) . '  class="layui-select-url layui-select' . $this->addClass($options) . '" name="' . $name . '" ' . $multiple . ' ' . $this->filter($options) . ' ' . $this->verify($options) . ' ' . $this->search($options) . ' ' . $this->readonlyOrdisabled($options) . ' >
                    <option value="">' . lang($default) . '</option>
                    ' . $op . '
                  </select>
                  ' . $this->tips($options) . '
                </div>
                </div>';
        return $str;
    }
    /**
     * @param $name
     * @param $select
     * @param $options
     * @param $attr
     * @param $value
     * @return string
     */
    public  function xmselect($name = '', $select=[], $options=[], $attr=[], $value='')
    {
        $name = $options['formname']??$name;
        $op = '';
        if (is_array($select)) {
            $op .= " data-data='" . json_encode($select, JSON_UNESCAPED_UNICODE) . "'";
        }
        if (is_object($select)) {
            $op .= " data-data='" . json_encode((array)$select, JSON_UNESCAPED_UNICODE) . "'";
        }
        $attr = is_array($attr) ? implode(',', $attr):$attr;
        $attr ? $op .= ' data-attr="' . $attr . '"' : "";
        $value = is_array($value) ? implode($value) : $value;
        $value ? $op .= ' data-value="' . $value . '"' : "";
        $options['lang'] = $options['lang'] ?? '';
        $options['tips'] = $options['tips']?? '';
        $options['empty'] =  $options['empty'] ?? '';
        $options['repeat'] = $options['repeat'] ??'';
        $options['content'] =  $options['content'] ?? '';
        $options['searchTips'] = $options['searchTips'] ?? '';
        $options['style'] = $options['style'] ?? '';
        $options['filterable'] = $options['filterable'] ?? '';
        $options['remoteSearch'] = $options['remoteSearch']  ??  '';
        $options['remoteMethod'] =  $options['remoteMethod']  ??  '';
        $options['height'] = $options['height'] ??'';
        $options['paging'] =  $options['paging'] ??'';
        $options['size'] =   $options['size'] ??'';
        $options['pageSize'] = $options['pageSize'] ??'';
        $options['pageRemote'] = $options['pageRemote'] ??'';
        $options['clickClose'] =  $options['clickClose'] ??'';
        $options['reqext'] =  $options['reqtext'] ??'';
        $options['radio'] =  $options['radio'] ?? '';
        $options['url'] =  $options['url'] ??'';
        $options['tree'] =  $options['tree'] ??'';
        $options['prop'] = $options['prop'] ??'';
        $options['parentField'] =  $options['parentField'] ??'pid';
        $options['max'] =  $options['max'] ??'';
        $options['verify'] = $options['verify'] ??'';
        $options['disabled'] =  $options['disabled'] ??'';
        $options['create'] =  $options['create'] ??'';
        $options['theme'] =  $options['theme'] ??'';
        $options['value'] = $options['value'] ??'';
        $options['autorow'] =  $options['autorow'] ??'';
        $options['toolbar'] = isset($options['toolbar'])?json_encode($options['toolbar'],JSON_UNESCAPED_UNICODE)  : '';
        foreach($options as $key=>$val){
            $op .= ' data-'.$key.'="'.$val.'" ';
        }
        $label = $options['label'] ?? $name;
        $str = '<div class="layui-form-item">' .$this->label($label,$options) . '      
                <div ' . $this->addextend($options) . '  ' . $this->addstyle($options) . '  id="' . $name . '" name="' . $name . '" class="layui-input-block ' . $this->addClass($options) . '" ' . $op . ' lay-filter="xmSelect">
                ' . $this->tips($options) . '
                </div>
                </div>';
        return $str;
    }

    /**
     * 创建动态下拉列表字段
     * @param $name
     * @param $options
     * @param $value
     * @return string
     */
    public  function selectpage(string $name,array $lists= [],array $options = [],$value=null)
    {
        $name = $options['formname']??$name;
        $url = $options['url']??'';
        foreach ($options as $k => $v) {
            $op['extend']['data-'.$k] = $v;
        }
        $op['extend']['lay-filter'] = 'selectPage';
        $op['extend']['data-data'] = empty($lists)?'':json_encode($lists);
        $op['extend']['data-field'] = $options['field']??'title';
        $op['extend']['data-primarykey'] = $options['field']??'id';
        $op['extend']['data-multiple'] = $options['multiple']??'';
        $op['extend']['data-init'] = $value;
        $options  = array_merge($options,$op);
        return $this->input($name,'text',$options, $value);
    }
    /**
     * @param $name
     * @param $value
     * @param array $options
     * @return string
     * tag
     */
    public function tags($name = '', $options = [], $value = '')
    {
        $name = $options['formname']??$name;
        $label = $options['label'] ?? $name;
        $id = $options['id'] ?? $name;
        $str = '<div class="layui-form-item">' .$this->label($label,$options) . '
                    <div class="layui-input-block">
                    <div class="tags" >
                        <input type="hidden" name="' . $name . '" value="' . $value . '" />
                        <input ' . $this->verify($options) . $this->addextend($options) . ' ' . $this->addstyle($options) . '  class="' . $this->addClass($options) . '" id="' . $id . '" lay-filter="tags" type="text" placeholder="' . lang("Space To Generate Tags") . '" ' . $this->filter($options) . $this->readonlyOrdisabled($options) . '/>
                    </div>
                    </div>
                </div>';
        return $str;
    }

    /**
     * @param $name
     * @param $value
     * @param array $options
     * @return string
     * 颜色选择
     */
    public  function color($name = '', $options = [], $value = '')
    {

        $name = $options['formname']??$name;
        $id = $options['id'] ?? $name;$label = $options['label'] ?? $name;$format = $options['format'] ?? 'hex';
        $str = '<div class="layui-form-item">' .$this->label($label,$options) . '
                    <div class="layui-input-block">
                        <input ' . $this->verify($options) . $this->addstyle($options) . '  class="layui-input layui-input-inline' . $this->addClass($options) . '" type="text" name="' . $name . '"  value="' . $value . '"' . $this->filter($options) . $this->readonlyOrdisabled($options) . '/>
                        <div ' . $this->addextend($options) . '  id="' . $id . '" lay-filter="colorPicker" data-name="' . $name . '" data-format = "' . $format . '"   ></div>
                    </div>
                </div>';
        return $str;
    }

    /**
     * @param $name
     * @param $value
     * @param array $options
     * @return string
     * 图标，有点小问题
     */
    public  function icon($name = '', $options = [], $value = '')
    {
        $name = $options['formname']??$name;
        $name = $name ? $name : 'icon';
        $label = $options['label'] ?? $name;
        $value = $value ? $value : 'layui-icon-rate';
        $id = $options['id'] ?? $name;
        $str = '<div class="layui-form-item">' .$this->label($label,$options) . '
                    <div class="layui-input-block">
                        <input ' . $this->verify($options) . $this->addextend($options) . ' type="hidden" name="' . $name . '"  id="' . $id . '" value="' . $value . '" 
                        lay-filter="iconPickers"  class="hide ' . $this->addClass($options) . '" />
                    </div>
                </div>';
        return $str;
    }

    /**
     * @param null $name
     * @param array $options
     * @return string
     * 日期
     */
    public  function date($name='', $options=[], $value='')
    {
        $name = $options['formname']??$name;
        $op = '';
        if (isset($options['range'])) {
            $op .= 'data-range="' . $options['range'] . '"';
        }
        if (isset($options['type'])) {
            $op .= 'data-type="' . $options['type'] . '"';
        }
        if (isset($options['format'])) {
            $op .= 'data-format="' . $options['format'] . '"';
        }
        $placeholder = $options['placeholder']??'yyyy-MM-dd HH:mm:ss';
        $label = $options['label'] ?? $name;
        $str = '<div class="layui-form-item">' .$this->label($label,$options) . '       
        <div class="layui-input-block layui-input-wrap">
        <div class="layui-input-prefix"><i class="layui-icon layui-icon-date"></i></div>
         <input ' . $this->verify($options) . $this->addextend($options) . ' ' . $this->addstyle($options) . '  class="layui-input ' . $this->addClass($options) . '" type="text" name="' . $name . '" value="' . $value . '" lay-filter="date" ' . $op . ' placeholder="'.$placeholder.'"/>
        </div>';
        return $str;
    }
    /**
     * 城市选择
     * @param string $name
     * @param $options
     * @return string
     */
    public  function city($name = 'cityPicker', $options = [])
    {
        $name = $options['formname']??$name;
        $id =  $options['id'] ?? $name;
        $options['provinceId'] = $options['provinceId'] ?? 'province_id';
        $options['cityId'] = $options['cityId'] ?? 'city_id';
        $options['districtId'] = $options['districtId'] ?? 'area_id';
        $attr = 'data-districtid="' . $options['districtId'] . '" data-cityid="' . $options['cityId'] . '" data-provinceid="' . $options['provinceId'] . '"';
        $str = ' <div class="layui-form-item">
                    <label class="layui-form-label width_auto text-r" style="margin-top:2px">省市县：</label>
                    <div class="layui-input-block">
                        <input ' . $this->verify($options) . $this->addextend($options) . ' type="hidden" autocomplete="on" class="layui-input ' . $this->addClass($options) . '" ' . $attr . ' lay-filter="cityPicker" id="' . $id . '" name="' . $name . '" readonly="readonly" data-toggle="city-picker" placeholder="请选择"/>
                    </div>
                    </div>';
        return $str;
    }

    /**
     * 城市选择
     * @param string $name
     * @param $options
     * @return string
     */
    public  function region($name = 'regionCheck',  $options = [])
    {
        $name = $options['formname']??$name;
        $label = $options['label'] ?? $name;
        $id = $options['id'] ?? $name;
        $str = ' <div class="layui-form-item">' .$this->label($label,$options) . '
                    <div class="layui-input-block">
                        <input type="hidden" name="' . $name . '" value="" />
                        <div ' . $this->verify($options) . $this->addextend($options) . ' ' . $this->addstyle($options) . '  class="' . $this->addClass($options) . '" id="' . $id . '" name="' . $name . '" lay-filter="regionCheck">
                        </div>
                    </div>
                </div>';
        return $str;
    }

    /**
     * @param string $name
     * @param $id
     * @param int $type
     * @param array $options
     * @return string
     * 编辑器
     */
    public  function editor($name = 'container', $type = 1, $options = [], $value = '')
    {
        $name = $options['formname']??$name;
        $id = $options['id'] ?? $name;
        $height = $options['height'] ?? '400px';
        $path = $options['path'] ?? 'upload';
        $label = $options['label'] ?? $name;
        $str = '<div class="layui-form-item">' .$this->label($label,$options) . '
         <div class="layui-input-block">';
        if ($type == 1) {
            //百度。quill wangeditor ckeditor,editormd
            $textarea = '';
            if (!empty($options['textarea'])) {
                $textarea = '<textarea '  . $this->addextend($options) . '  name="' . $name . '" data-path="' . $path . '" >'   .  $value  . '</textarea>';
            }
            //百度。quill wangeditor ckeditor
            $str .= '<div ' . $this->addextend($options) . '  data-value="' . htmlentities($value) . '" id="' . $id . '" name="' . $name . '" 
            data-editor="' . $type . '" lay-filter="editor"  lay-editor data-path="' . $path . '" data-height="' . $height . '" type="text/plain" >
          ' .    $textarea   . '  </div>';
        } else {
            //LAYEDIT  tinyedit
            $str .= '<textarea ' . $this->addextend($options) . '  id="' . $id . '" name="' . $name . '" data-path="' . $path . '"   data-editor="' . $type . '"  lay-filter="editor" lay-editor type="text/plain">' . $value . '</textarea>';
        }
        $str .= '</div></div>';
        return $str;
    }
    /**
     * 上传
     * @param string $name
     * @param string $formData
     * @param array $options
     * @return string
     */
    public  function upload($name = 'avatar', $formData = '', $options = [], $value = '')
    {
        $name = $options['formname']??$name;
        if (!isset($options['type'])) $options['type'] = 'radio';
        if (!isset($options['mime'])) $options['mime'] = 'images';
        if (!isset($options['num'])) $options['num'] = 1;
        if (isset($options['num']) && $options['num'] == '*') $options['num'] = 100;
        if (!isset($options['path'])) $options['path'] = 'upload'; //上传路劲
        $id = $options['id']??$name;
        $css = isset($options['css']) ? $options['css'] : 'display:inline-block;';
        $label = $options['label'] ?? $name;
        $li = '';
        $croper_container = '';
        if (isset($options['cropper'])) {
            $width = $options['width'] ?? '300';
            $height = $options['height'] ?? '300';
            $mark =  $options['mark'] ?? '1';
            $area = $options['area'] ?? '800px';
            $cops = ['name'=>$name,'path' => $options['path'], 'width' => $width, 'height' => $height, 'mark' => $mark, 'area' => $area];
            $crpperops = 'data-value="' . json_encode($cops, true) . '"';
            $data_value = '';
            foreach ($cops as $key => $val) {
                $data_value .= ' data-'.$key.'="'.$val.'" ';
            }
            $croper_container = '<button type="button" '. $data_value  . $crpperops . '
                class="layui-btn"  lay-filter="cropper" id="' .$id .'"><i class="layui-icon layui-icon-upload"></i>'
                . lang('Cropper') .
                '</button>';
            $options['type'] = 'radio';
            $css .= 'width:53%!important;';
        }
        $values = [];
        $formData = is_object($formData) ? ($formData->toArray()) : $formData;
        if ($formData && is_array($formData) && array_key_exists($name, $formData)) {
            $values = explode(',', $formData[$name]);
        } elseif ($formData && is_string($formData)) {
            $values = explode(',', $formData);
        }
        $values = $value ? explode(',', $value) : $values;
        if ($value) $values = explode(',', $value);
        if (!empty(array_filter($values))) {
            foreach ($values as $k => $v) {
                if ($k + 1 <= $options['num']) {
                    switch ($options['mime']) {
                        case 'video':
                            $li .= '<li><video lay-event="" class="layui-upload-img fl"  width="150" src="' . $v . '"></video>
                    <i class="layui-icon layui-icon-close" lay-event="filedelete"
                    data-fileurl="' . $v . '"></i></li>';
                            break;
                        case 'audio':
                            $li .= '<li><audio lay-event="" class="layui-upload-img fl"  width="150" src="' . $v . '"></audio>
                    <i class="layui-icon layui-icon-close" lay-event="filedelete"
                    data-fileurl="' . $v . '"></i></li>';
                            break;
                        case 'images':
                            $li .= '<li><img lay-event="photos" class="layui-upload-img fl"  width="150" src="' . $v . '"></img>
                    <i class="layui-icon layui-icon-close" lay-event="filedelete"
                    data-fileurl="' . $v . '"></i></li>';
                            break;
                        case 'image':
                            $li .= '<li><img lay-event="photos" class="layui-upload-img fl"  width="150" src="' . $v . '"></img>
                    <i class="layui-icon layui-icon-close" lay-event="filedelete"
                    data-fileurl="' . $v . '"></i></li>';
                            break;
                        case 'zip':
                            $li .= '<li><img lay-event="" class="layui-upload-img fl"  width="150" src="/static//backend/images/filetype/zip.jpg"></img>
                    <i class="layui-icon layui-icon-close" lay-event="upfileDelete"
                    data-fileurl="' . $v . '"></i></li>';
                            break;
                        case 'office':
                            $li .= '<li><img lay-event="" class="layui-upload-img fl"  width="150" src="/static/backend/images/filetype/office.jpg"></img>
                    <i class="layui-icon layui-icon-close" lay-event="filedelete"
                    data-fileurl="' . $v . '"></i></li>';
                            break;
                        default:
                            $li .= '<li><img lay-event="photos" class="layui-upload-img fl"  width="150" src="/static/backend/images/filetype/file.jpg">
                    <i class="layui-icon layui-icon-close" lay-event="filedelete"
                    data-fileurl="' . $v . '"></i></li>';
                            break;
                    }
                }
            }
            $value = implode(',', $values);
        }
        $op = [
            'name' => $name,
            'path' => $options['path'] ?? 'upload',
            'mime' => $options['mime'] ?? '*',
            'num' => $options['num'] ?? '',
            'type' => $options['type'] ?? '',
            'size' => $options['size'] ?? '',
            'exts' =>  $options['exts'] ?? '*',
            'accept' =>  $options['accept'] ?? 'file',
            'multiple' =>  $options['multiple'] ?? '',
            'selecturl' =>  $options['selecturl'] ?? '',
            'tableurl' =>  $options['tableurl'] ?? '',
            'chunk' =>  $options['chunk'] ?? false,
        ];
        $data_value = '';
        foreach ($op as $key => $val) {
            $data_value .= ' data-'.$key.'="'.$val.'" ';
        }
        $op = " data-value='" . json_encode($op, true) . "'";
        $select_container = '';
        if ((isset($options['select']) && $options['select']) || !isset($options['select'])) {
            $options['select'] = $options['select'] ?? 'upload-select'; //可选upload-choose
            $select_container =  '<button id="' . $name . '" type="button" class="layui-btn layui-btn-danger ' . $options['select'] . '" ' .$data_value . $op . '  lay-filter="' . $options['select'] . '"><i class="layui-icon layui-icon-radio"></i>' . lang('Choose') . '</button>';
        }
        $options['upload'] = $options['upload'] ?? 'upload';
        $str = ' <div class="layui-form-item">' .$this->label($label,$options) . '
                <div class="layui-input-block">
                    <div class="layui-upload">
                        <input '  . $this->addextend($options) . ' ' . $this->addstyle($options) . '  value="' . $value . '" style="' . $css . ' ;width:65% " type="text" name="' . $name . '" class="layui-input attach ' . $this->addClass($options) . '"' . $this->verify($options) . '/>
                       ' . $croper_container . '
                        <button type="button" ' .$data_value .' style="margin-left:0px" class="layui-btn layui-btn-normal"  ' . $op . ' lay-filter="' . $options['upload'] . '"><i class="layui-icon layui-icon-upload-drag"></i>' . lang('Uploads') . '</button>
                        ' . $select_container . '
                        <div class="layui-upload-list">'
            . $li . '
                        </div>
                    </div>
                    ' . $this->tips($options) . '
                </div>
            </div>';
        return $str;
    }
    /**
     * @param bool $reset
     * @param array $options
     * @return string
     */
    public  function closebtn($reset = true, $options = [])
    {
        $show = '';
        if (!isset($options['show'])) {
            $show = 'layui-hide';
        }
        $str = '<div class="layui-btn-center ' . $show . '">
                <button ' . $this->addstyle($options) . '  type="close" class="layui-btn ' . $this->addClass($options) . '" onclick="parent.layui.layer.closeAll();">' . lang('Close') .
            '</button>
            </div>';

        return $str;
    }


    /**
     * @param bool $reset
     * @param array $options
     * @return string
     */
    public  function submitbtn($reset=true, $options=[])
    {
        $show = '';
        if (!isset($options['show'])) {
            $show = 'layui-hide';
        }
        $str = '<input type="hidden" name="__token__" value="' . $this->token() . '"><div class=" layui-btn-submit layui-btn-center ' . $show . '" />
            <button type="submit" class="layui-btn layui-btn-normal submit " lay-fitler="submit" lay-submit>' . lang('Submit') .
            '</button>';
        if ($reset) {
            $str .= '<button type="reset" class="layui-btn  layui-btn-primary reset">' . lang('Reset') . '</button>';
        }
        $str .= '</div>';
        return $str;
    }
    /**
     * @param $label
     * @param $options
     * @return string
     */
    public  function label($label,$options= [],$escape_html = true){
        if ($escape_html) {
            $label = $this->entities($label);
        }
        return '<label class="layui-form-label ' . $this->labelRequire($options) . '">' . lang(Str::title($label)) . '</label>';
    }

    /**
     * 将HTML字符串转换为实体
     *
     * @param  string  $value
     *
     * @return string
     */
    protected function entities($value)
    {
        return htmlentities($value, ENT_QUOTES, 'UTF-8', false);
    }
    /**
     * @param $options
     * @return string
     * 提示
     */
    protected  function tips($options = [])
    {
        $tips = '';
        if (isset($options['tips'])) {
            $tips = '<div class="layui-form-mid layui-word-aux">' . lang($options['tips']) . '</div>';
        }
        return $tips;
    }

    /**
     * @ 验证
     * @return string
     */
    protected  function verify($options = [])
    {
        $verify = '';
        if (isset($options['verify'])) {
            $verify .= ' lay-verify="' . $options['verify'] . '"';
        }
        $type ='tips';
        if (isset($options['verType']) && $options['verType']) {
            $type = $options['verType'];
        }
        $verify.= ' lay-verType="' . $type . '" ';
        if (isset($options['reqText']) && $options['reqText']) {
            $verify.= ' lay-reqText="' . $options['reqText'] . '" ';
        }
        return $verify;
    }

    /** 过滤
     * @param $options
     * @return string
     */
    protected  function filter($options = [])
    {
        $filter = '';
        if (isset($options['filter'])) {
            $filter = 'lay-filter="' . $options['filter'] . '"';
        }
        return $filter;
    }

    /**搜索
     * @return string
     */
    protected  function search($options = [])
    {
        $search = '';
        if (!isset($options['search']) || $options['search'] == true) {
            $search = 'lay-search';
        }
        return $search;
    }
    /**
     * @param $ops
     * @param $val
     * @param int $type
     * @return string
     * 是否选中
     */
    protected  function selectedOrchecked($select=[], $val='', $type = 1)
    {
        if ($select == $val) {
            if ($type == 1) return 'selected';
            return 'checked';
        } else {
            return '';
        }
    }
   
    protected  function labelRequire($options=[])
    {

        if (isset($options['verify']) && ($options['verify'] == 'required' || strpos($options['verify'], 'required') !== false)) {
            return 'required';
        }
        return '';
    }

    protected  function readonlyOrdisabled($options=[])
    {

        if (isset($options['readonly'])  && $options['readonly']) {
            return 'readonly';
        }
        if (isset($options['disabled']) && $options['disabled']) {
            return 'disabled';
        }
        return '';
    }
    //自定义class属性
    protected  function addClass($options=[])
    {
        if (isset($options['class']) && $options['class']) {
            $classArr = is_array($options['class']) ? $options['class'] : explode(',', $options['class']);
            return ' ' .implode(' ', $classArr).' ';
        }
        return '';
    }
    protected  function addstyle( $options=[])
    {
        if (isset($options['style']) && $options['style']) {
            return ' style="' . $options['style'] . '" ';
        }
        return ' ';
    }
    protected  function addextend($options=[])
    {
        if (isset($options['extend']) && $options['extend']) {
            if(is_array($options['extend'])) {
                $attr = ' ';
                foreach($options['extend'] as $key => $value) {
                    $attr.= $key .'="'.$value . '"';
                }
                return $attr;
            }else{
                return ' ' . $options['extend'].' ';
            }
        }
        if (isset($options['extend']) && $options['extend']) {
            return ' ' . $options['extend'].' ';
        }
        return ' ';
    }

}
