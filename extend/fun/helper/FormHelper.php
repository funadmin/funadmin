<?php

namespace fun\helper;


use think\helper\Str;

class FormHelper
{

    public static function token($name = '__token__', $type = 'md5')
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
    public static function input($name = '', $type = 'text', $options = [], $value='')
    {
        $label = isset($options['label']) ? $options['label'] : $name;
        $tips = isset($options['tips']) ? $options['tips'] : $label;
        $placeholder = isset($options['placeholder']) ? $options['placeholder'] : $tips;
        $value = !is_null($value) ? 'value="' . $value . '"' : '';
        $disorread = self::readonlyOrdisabled($options)?self::readonlyOrdisabled($options):self::readonlyOrdisabled($options);
        $disorread  = $disorread?'layui-disabled':'';
        if ($type == 'hidden') {
            return '<input type="' . $type . '" name="' . $name . '"  ' . self::verify($options) . self::filter($options) . self::readonlyOrdisabled($options) . ' autocomplete="off"         placeholder="' . $placeholder . '" class="layui-input '.$disorread.'" ' . $value . '/>';
        }
        $str = '<div class="layui-form-item"> 
        <label class="layui-form-label ' . self::labelRequire($options) . '">' . lang(Str::title($label)) . '</label>
        <div class="layui-input-block">
         <input type="' . $type . '" name="' . $name . '"  ' . self::verify($options) . self::filter($options) . self::readonlyOrdisabled($options) . ' autocomplete="off"
         placeholder="' . lang($placeholder) . '" class="layui-input '.self::addClass($options). ' '.$disorread.'"' . $value . '/>
         ' . self::tips($options) . '
         </div></div>';
        return $str;
    }
    /**
     * @param $name
     * @param $radiolist
     * @param array $options
     * @param string $value
     * @return string
     */
    public static function radio($name, $radiolist, $options = [], $value='')
    {
        if (is_null($radiolist)) {
            $radiolist = $name;
        }
        $label = isset($options['label']) ? $options['label'] : $name;
        $input = '';
        if (is_string($radiolist) && strpos($radiolist, "\n") !== false) $radiolist = explode("\n", $radiolist);
        if (is_array($radiolist)) {
            foreach ($radiolist as $k => $v) {
                if (is_string($v) && strpos($v, ':') !== false) {
                    $v = explode(":", $v);
                    $input .= '<input class="'.self::addClass($options). '" type="radio"' . self::selectedOrchecked($value, $v[0], 2) . ' name="' . $name . '" ' . self::verify($options) . self::filter($options) . self::readonlyOrdisabled($options) . ' value="' . $v[0] . '" title="' . lang($v[1]) . '" />';
                } else {
                    $input .= '<input class="'.self::addClass($options). '" type="radio"' . self::selectedOrchecked($value, $k, 2) . ' name="' . $name . '" ' . self::verify($options) . self::filter($options) . self::readonlyOrdisabled($options) . ' value="' . $k . '" title="' . lang($v) . '" />';
                }
            }
        } else {
            $input .= '<input class="'.self::addClass($options). '" type="radio" name="' . $name . '" ' . self::verify($options) . self::filter($options) . ' value="' . $radiolist . '" title="' . lang($radiolist) . '" />';
        }

        $str = ' <div class="layui-form-item">
        <label class="layui-form-label ' . self::labelRequire($options) . '">' . lang(Str::title($label)) . '</label>
        <div class="layui-input-block">
        ' . $input . '
        ' . self::tips($options) . '
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

    public static function switchs($name , $switch, $options = [], $value='')
    {
        $label = isset($options['label']) ? $options['label'] : $name;
        $switchArr = $switch;
        if (is_string($switch) && strpos($switch, '|')) {
            $switchArr = implode('|', $switch);
        }
        $switchStr = $switchArr ? lang($switchArr[1]).'|'.lang($switchArr[0]):lang('open').'|'.'close';
        $str = '<div class="layui-form-item">
        <label class="layui-form-label ' . self::labelRequire($options) . '">' . lang(Str::title($label)) . '</label>
        <div class="layui-input-block">
        <input class="'. self::addClass($options) .'" type="checkbox" value="' . $value . '" checked="" name="' . $name . '" ' . self::verify($options) . self::filter($options) . self::readonlyOrdisabled($options) . ' lay-skin="switch" lay-text="'.$switchStr.'"  data-text="' . lang($value) . '"/>
        ' . self::tips($options) . '
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
    public static function checkbox($name = null, $list = [], $options = [], $value='')
    {
        if (empty($value)) {
            $value = $name;
        }
        if (is_string($value) && strpos($value, "\n") !== false) $value = explode("\n", $value);
        if (is_string($value) && strpos($value, ",") !== false) $value = explode(",", $value);
        if (is_string($value) && strpos($value, "|") !== false) $value = explode("|", $value);
        if (is_string($list) && strpos($list, "\n") !== false) $list = explode("\n", $list);
        if (is_string($list) && strpos($list, ",") !== false) $list = explode(",", $list);
        if (is_string($list) && strpos($list, "|") !== false) $list = explode("|", $list);
        if (is_string($value)
            && strpos($value, "\n") === false
            && strpos($value, ",") === false
            && strpos($value, "|") === false
        ) $value = explode(",", $value);
        $input = '';
        $skin = '';
        if (isset($options['skin'])) {
            $skin = 'lay-skin="' . $options['skin'] . '"';
        }
        if (is_array($list) and $list) {
            foreach ($list as $k => $v) {
                if (is_string($v) && strpos($v, ':') !== false) {
                    $v = explode(":", $v);
                    $check = '';
                    if (is_array($value) && in_array($v[0], $value) || $value == $v[0]) {
                        $check = 'checked';
                    }
                    $input .= '<input class="'.self::addClass($options). '" type="checkbox" ' . $check . ' value="'.$k.'"  name="' . $name . '[' . $v[0] . ']" ' . $skin . self::verify($options) . self::filter($options) . self::readonlyOrdisabled($options) . ' title="' . lang($v[1]) . '"/>';
                } else {
                    $check = '';
                    if ((is_array($value) &&  is_array($v) && in_array($v[0], $value)) || $value == $v) {
                        $check = 'checked';
                    }
                    elseif ((is_array($value) &&  is_string($v) && in_array($k, $value)) || $value == $v) {
                        $check = 'checked';
                    }
                    $input .= '<input class="'.self::addClass($options). '" type="checkbox" ' . $check .  '  value="'.$k.'" name="' . $name . '[' . $k . ']" ' . $skin . self::verify($options) . self::filter($options) . self::readonlyOrdisabled($options) . ' title="' . lang($v) . '"/>';
                }
            }
        } else {
            $input .= '<input class="'.self::addClass($options). '" type="checkbox" name="' . $name . '[]"  ' . $skin . self::verify($options) . self::filter($options) . self::readonlyOrdisabled($options) . '  title="' . lang($value) . '"/>';
        }
        $label = isset($options['label'])?($options['label']):$name;
        $str = '<div class="layui-form-item">
        <label class="layui-form-label ' . self::labelRequire($options) . '">' . lang(Str::title($label)) . '</label>
        <div class="layui-input-block">
         ' . $input . self::tips($options) . '
        </div>';
        return $str;
    }

    /**
     * 数组表单
     * @param null $name
     * @param array $options
     * @param array $list
     * @return string
     */
    public static function arrays($name=null, $options = [], $list = [])
    {
        $label = isset($options['label']) ? $options['label'] : $name;
        $arr = '';
        $i=0;
        if(empty($list)){
            $arr .= '<div class="layui-form-item" ><label class="layui-form-label ' . self::labelRequire($options) . '">'.$label.'</label><div class="layui-input-inline">
                <input type="text"  name="'.$name.'[key][]"  value="" placeholder="'.lang('key').'" autocomplete="off" class="layui-input input-double-width">
            </div>
            <div class="layui-input-inline">
                <input type="text"  name="'.$name.'[value][]"  value="" placeholder="'.lang('value').'" autocomplete="off" class="layui-input input-double-width">
            </div><div class="layui-input-inline" >
                <button  data-name="'.$name.'" type="button" class="layui-btn layui-btn-warm layui-btn-sm addInput" lay-event="addInput">
                    <i class="layui-icon">&#xe654;</i>
                </button>
            </div></div>';
        }
        foreach ($list as $key=>$value){
            if($i==0){
                $arr .= '<div class="layui-form-item" ><label class="layui-form-label ' . self::labelRequire($options) . '">'.$label.'</label><div class="layui-input-inline">
                <input type="text"  name="'.$name.'[key][]"  value="'.$key.'" placeholder="'.lang('key').'" autocomplete="off" class="layui-input input-double-width">
            </div>
            <div class="layui-input-inline">
                <input type="text"  name="'.$name.'[value][]"  value="'.$value.'" placeholder="'.lang('value').'" autocomplete="off" class="layui-input input-double-width">
            </div><div class="layui-input-inline" >
                <button  data-name="'.$name.'" type="button" class="layui-btn layui-btn-warm layui-btn-sm addInput" lay-event="addInput">
                    <i class="layui-icon">&#xe654;</i>
                </button>
            </div></div>';
                ;
            }else{
                $arr.='<div class="layui-form-item"><label class="layui-form-label"></label><div class="layui-input-inline">
                <input type="text"  name="'.$name.'[key][]" value="'.$key.'"  placeholder="'.lang('key').'" autocomplete="off" class="layui-input input-double-width">
                </div><div class="layui-input-inline">
                <input type="text"  name="'.$name.'[value][]" value="'.$value.'" placeholder="'.lang('value').'" autocomplete="off" class="layui-input input-double-width">
            </div><div class="layui-input-inline">
                <button  data-name="'.$name.'" type="button" class="layui-btn layui-btn-danger layui-btn-sm removeInupt" lay-event="removeInupt">
                    <i class="layui-icon">&#xe67e;</i>
                </button>
            </div></div>';
            }
            $i++;
        }
        $str ='<div id="'.$name.'">'.$arr.'</div>';
        return $str;
    }

    /**
     * 文本
     * @param null $name
     * @param array $options
     * @param $value
     * @return string
     */
    public static function textarea($name = null, $options = [], $value='')
    {
        $label = isset($options['label']) ? $options['label'] : $name;
        $tips = isset($options['tips']) ? $options['tips'] : $name;
        $str = ' <div class="layui-form-item layui-form-text">
            <label class="layui-form-label ' . self::labelRequire($options) . '">' . lang(Str::title($label)) . '</label>
            <div class="layui-input-block">
            <textarea placeholder="' . lang($tips) . '" class="layui-textarea '. self::addClass($options) .'" 
            ' . self::filter($options) . self::verify($options) . ' name="' . $name . '"
            value="' . $value . '"></textarea>
            ' . self::tips($options) . '
            </div></div>';
        return $str;
    }

    /**
     * @param $name
     * @param $value
     * @param $options
     */
    public static function multiselect($name, $select, $options, $attr, $value)
    {
        $op = '';
        foreach ($select as $k => $v) {
            $selected = '';
            if (is_array($value) && is_array($attr) && !empty($attr) && in_array($v[$attr[0]], $value) || (is_array($attr) && !empty($attr)  && $v[$attr[0]] == $value)) {
                $selected = 'selected';
            }
            if ($value!=null && $value == $k  && !$attr){
                $selected = 'selected';
            }
            if (!empty($attr)) {
                $op .= '<option ' . $selected . ' value="' . $v[$attr[0]] . '">' . lang($v[$attr[1]]) . '</option>';
            } else {
                $op .= '<option ' . $selected . ' value="' . $k . '">' . lang($v) . '</option>';
            }
        }
        $label = isset($options['label']) ? $options['label'] : $name;
        $multiple = '';
        if (isset($options['multiple'])) {
            $multiple = 'multiple="multiple"';
        }
        if (isset($options['default'])) {
            $default = lang($options['default']);
        } else {
            $default = lang('Default');
        }
        $str = '<div class="layui-form-item">
                <label class="layui-form-label ' . self::labelRequire($options) . '">' . lang(Str::title($label)) . '</label>
                <div class="layui-input-block">
                  <select class="layui-select'. self::addClass($options) .'" name="' . $name . '" ' . $multiple . self::filter($options) . self::verify($options) . self::search($options).' ' . self::readonlyOrdisabled($options). ' >
                    <option value="">' . lang($default) . '</option>
                    ' . $op . '
                  </select>
                  ' . self::tips($options) . '
                </div>
                </div>';
        return $str;
    }
    /**
     * @param $name
     * @param $value
     * @param $options
     */
    public static function xmselect($name, $select, $options, $attr, $value)
    {
        $op = '';
        if(is_array($select)){
            $op.=" data-data='".json_encode($select,JSON_UNESCAPED_UNICODE)."'";
        }
        if(is_object($select)){
            $op.=" data-value='".$select."'";
        }
        $attr? $op.=' data-attr="'.$attr.'"':"";
        $value? $op.=' data-value="'.json_encode($value,true).'"':"";
        isset($options['lang'])?$op.=' data-lang="'.$options['lang'].'"':'';
        isset($options['tips'])?$op.=' data-tips="'.$options['tips'].'"':'';
        isset($options['empty'])?$op.=' data-empty="'.$options['empty'].'"':'';
        isset($options['repeat'])?$op.=' data-repeat="'.$options['repeat'].'"':'';
        isset($options['content'])?$op.=' data-content="'.$options['content'].'"':'';
        isset($options['searchTips'])?$op.=' data-searchtips="'.$options['searchTips'].'"':'';
        isset($options['style'])?$op.=' data-style="'.$options['style'].'"':'';
        isset($options['filterable'])?$op.=' data-filterable="'.$options['filterable'].'"':'';
        isset($options['remoteSearch'])? $op.=" data-remotesearch='".$options['remoteSearch']."'":'';
        isset($options['remoteMethod'])? $op.=" data-remotemethod='".$options['remoteMethod']."'":'';
        isset($options['height'])? $op.=" data-height='".$options['height']."'":'';
        isset($options['paging'])? $op.=" data-paging='".$options['paging']."'":'';
        isset($options['size'])? $op.=" data-size='".$options['size']."'":'';
        isset($options['pageSize'])? $op.=" data-pagesize='".$options['pageSize']."'":'';
        isset($options['pageRemote'])? $op.=" data-pageremote='".$options['pageRemote']."'":'';
        isset($options['clickClose'])? $op.=" data-clickclose='".$options['clickClose']."'":'';
        isset($options['reqext'])? $op.=" data-reqtext='".$options['reqtext']."'":'';
        isset($options['radio'])?$op.=" data-radio='true'":'';
        isset($options['url']) ?$op.=" data-url='".$options['url']."'":'';
        isset($options['tree']) ?$op.=" data-tree='".$options['tree']."'":'';
        isset($options['prop']) ?$op.=" data-prop='".$options['prop']."'":'';
        isset($options['parentField']) ?$op.=" data-parentField='".$options['parentField']."'":'pid';
        isset($options['max']) ?$op.=" data-max='".$options['max']."'":'';
        isset($options['verify']) ?$op.=" data-verify='".$options['verify']."'":'';
        isset($options['disabled']) ?$op.=" data-disabled='".$options['disabled']."'":'';
        isset($options['create']) ?$op.=" data-create='".$options['create']."'":'';
        isset($options['theme']) ?$op.=" data-theme='".$options['theme']."'":'';
        isset($options['value']) ?$op.=" data-value='".$options['value']."'":'';
        $label = isset($options['label']) ? $options['label'] : $name;
        $str = '<div class="layui-form-item">
                <label class="layui-form-label ' . self::labelRequire($options) . '">' . lang(Str::title($label)) . '</label>
                <div id="'.$name.'" name="'.$name.'" class="layui-input-block '. self::addClass($options) .'" '.$op.' lay-filter="xmSelect">
                ' . self::tips($options) . '
                </div>
                </div>';
        return $str;
    }
    /**
     * @param $id
     * @param $name
     * @param $value
     * @param array $options
     * @return string
     * 颜色选择
     */
    public static function tags($id, $name, $options = [], $value='')
    {
        $label = isset($options['label']) ? $options['label'] : $name;
        $str = '<div class="layui-form-item">
                    <label class="layui-form-label ' . self::labelRequire($options) . '">' . lang($label) . '</label>
                    <div class="layui-input-block">
                    <div class="tags" >
                        <input type="hidden" name="' . $name . '" value="' . $value . '" />
                        <input class=". self::addClass($options) ." id="' . $id . '" lay-filter="tags" type="text" placeholder="' . lang("Space To Generate Tags") . '" ' . self::filter($options) . self::readonlyOrdisabled($options) . '/>
                    </div>
                    </div>
                </div>';
        return $str;
    }

    /**
     * @param $id
     * @param $name
     * @param $value
     * @param array $options
     * @return string
     * 颜色选择
     */
    public static function color($id, $name, $options = [], $value='')
    {

        $label = isset($options['label']) ? $options['label'] : $name;
        $str = '<div class="layui-form-item">
                    <label class="layui-form-label ' . self::labelRequire($options) . '">' . lang($label) . '</label>
                    <div class="layui-input-block">
                        <input class=". self::addClass($options) ." type="hidden" name="' . $name . '"  value="' . $value . '"' . self::filter($options) . self::readonlyOrdisabled($options) . '/>
                          <div id="' . $id . '" lay-filter="colorPicker"></div>
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
    public static function icon($name, $options = [], $value='')
    {
        $name = $name ? $name : 'icon';
        $value = $value ? $value : 'layui-icon-rate';
        $id = isset($options['id']) ? $options['id'] : 'iconPicker';
        $str = '<div class="layui-form-item">
                    <label class="layui-form-label ' . self::labelRequire($options) . '">' . lang('Icon') . '</label>
                    <div class="layui-input-block">
                        <input type="hidden" name="' . $name . '"  id="' . $id . '" value="' . $value . '" 
                          lay-filter="iconPickers"  class="hide '. self::addClass($options) .'" />
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
    public static function date($name, $options, $value)
    {
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
        $label = isset($options['label']) ? $options['label'] : $name;
        $str = '<div class="layui-form-item">
         <label class="layui-form-label ' . self::labelRequire($options) . '">' . lang($label) . '</label>
         <div class="layui-input-block">
         <input class="layui-input '. self::addClass($options) .'" type="text" name="' . $name . '" value="' . $value . '" lay-filter="date" ' . $op . ' placeholder="yyyy-MM-dd HH:mm:ss"/>
         <i class="layui-icon layui-icon-date"></i></div>
        </div>';
        return $str;
    }
    /**
     * 城市选择
     * @param string $name
     * @param string $id
     * @param $options
     * @return string
     */
    public static function city($name = 'cityPicker', $id = 'cityPicker', $options=[])
    {
        $options['provinceId'] = isset($options['provinceId'])?$options['provinceId']:'province_id';
        $options['cityId'] = isset($options['cityId'])?$options['cityId']:'city_id';
        $options['districtId'] = isset($options['districtId'])?$options['districtId']:'area_id';
        $attr = 'data-districtid="'.$options['districtId'].'" data-cityid="'.$options['cityId'].'" data-provinceid="'.$options['provinceId'].'"';
        $str = ' <div class="layui-form-item">
                    <label class="layui-form-label width_auto text-r" style="margin-top:2px">省市县：</label>
                    <div class="layui-input-block">
                        <input type="hidden" autocomplete="on" cclass="layui-input '. self::addClass($options) .'" '.$attr.' lay-filter="cityPicker" id="' . $id . '" name="' . $name . '" readonly="readonly" data-toggle="city-picker" placeholder="请选择"/>
                    </div>
                    </div>';
        return $str;
    }

    /**
     * 城市选择
     * @param string $name
     * @param string $id
     * @param $options
     * @return string
     */
    public static function region($name = 'regionCheck', $id = 'regionCheck', $options = [])
    {

        $str = ' <div class="layui-form-item">
                    <label class="layui-form-label ">区域</label>
                    <div class="layui-input-block">
                        <input type="hidden" name="' . $name . '" value="" />
                        <div class=". self::addClass($options) ." id="' . $id . '" name="' . $name . '" lay-filter="regionCheck">
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
    public static function editor($name = 'container', $id = null, $type = 1, $options = [],$value='')
    {
        if ($id == '') {
            $id = $name;
        }
        $height = isset($options['height'])?$options['height']:'350px';
        $path = isset($options['path'])?$options['path']:'';
        $label = isset($options['label']) ? $options['label'] : $name;
        $str = '<div class="layui-form-item">
         <label class="layui-form-label ' . self::labelRequire($options) . ' ">' . lang(Str::title($label)) . '</label>
         <div class="layui-input-block">';
        if ($type == 1) {
            //百度。quill wangeditor ckeditor
            $str .= '<div data-value="'.$value.'" id="' . $id . '" name="' . $name . '" 
            data-editor="' . $type . '" lay-filter="editor" data-path="'.$path.'" data-height="'.$height.'" type="text/plain"></div>';
        } else {
            //LAYEDIT  Ckeditor
            $str .= '<textarea id="' . $id . '" name="' . $name . '" data-path="'.$path.'" data-editor="' . $type . '" lay-verify="layedit" lay-filter="editor" type="text/plain"></textarea>';
        }
        $str .= '</div></div>';
        return $str;

    }
//1
    /**
     * 上传
     * @param string $name
     * @param string $formData
     * @param array $options
     * @return string
     */
    public static function upload($name = 'avatar', $formData = '', $options = [],$value='')
    {
        if (!isset($options['type'])) {
            $options['type'] = 'radio';
        }
        if (!isset($options['mime'])) {
            $options['mime'] = 'image';
        }
        if (!isset($options['num'])) {
            $options['num'] = 1;
        }
        if (!isset($options['path'])) {
            $options['path'] = 'upload';//上传路劲
        }
        $css = isset($options['css'])?$options['css']:'display:inline-block;';
        $label = isset($options['label']) ? $options['label'] : $name;
        $li = '';
        $croper_container = '';
        if (isset($options['cropper'])) {
            $width = isset($options['width']) ? $options['width'] : '300';
            $height = isset($options['height']) ? $options['height'] : '300';
            $mark = isset($options['mark']) ? $options['mark'] : '1';
            $area = isset($options['area']) ? $options['area'] : '900px';
            $cops = ['width' => $width, 'height' => $height, 'mark' => $mark, 'area' => $area];
            $crpperops = 'data-value="' . json_encode($cops, true) . '"';
            $croper_container = '<button type="button" 
               ' . $crpperops . '
                class="layui-btn layui-btn-warm"  lay-filter="cropper" id="cropper">'
                . lang('Cropper') .
                '</button>';
            $options['num'] = 1;
            $options['type'] = 'radio';
            $css .='width:53%!important;';
        }
        $values = [];
        $formData = is_object($formData)?($formData->toArray()):$formData;
        if($formData && array_key_exists($name,$formData)){
            $values =explode(',', $formData[$name]);
        }
        $values = $value ?explode(',', $value) : $values;
        if($value) $values = explode(',',$value);
        if(!empty(array_filter($values))){
            foreach ($values as $k => $v) {
                if($k+1<=$options['num']){
                    switch ($options['mime']) {
                        case 'video':
                            $li .= '<li><video lay-event="" class="layui-upload-img fl"  width="150" src="' . $v . '"></video>
                    <i class="layui-icon layui-icon-close" lay-event="upfileDelete"
                                   data-fileurl="' . $v . '"></i></li>';
                            break;
                        case 'audio':
                            $li .= '<li><audio lay-event="" class="layui-upload-img fl"  width="150" src="' . $v . '"></audio>
                    <i class="layui-icon layui-icon-close" lay-event="upfileDelete"
                                   data-fileurl="' . $v . '"></i></li>';
                            break;
                        case 'image':
                            $li .= '<li><img lay-event="photos" class="layui-upload-img fl"  width="150" src="' . $v . '"></img>
                    <i class="layui-icon layui-icon-close" lay-event="upfileDelete"
                                   data-fileurl="' . $v . '"></i></li>';
                            break;
                        case 'zip':
                            $li .= '<li><img lay-event="" class="layui-upload-img fl"  width="150" src="/static/backend/images/filetype/zip.jpg"></img>
                    <i class="layui-icon layui-icon-close" lay-event="upfileDelete"
                                   data-fileurl="' . $v . '"></i></li>';
                            break;
                        case 'office':
                            $li .= '<li><img lay-event="" class="layui-upload-img fl"  width="150" src="/static/backend/images/filetype/office.jpg"></img>
                    <i class="layui-icon layui-icon-close" lay-event="upfileDelete"
                                   data-fileurl="' . $v . '"></i></li>';
                            break;
                        default:
                            $li .= '<li><img lay-event="photos" class="layui-upload-img fl"  width="150" src="/static/backend/images/filetype/file.jpg">
                    <i class="layui-icon layui-icon-close" lay-event="upfileDelete"
                                   data-fileurl="' . $v . '"></i></li>';
                            break;
                    }
                }
            }
            $value = implode(',',$values);
        }
        $op = [
            'path' => isset($options['path']) ? $options['path'] : '',
            'mime' => isset($options['mime']) ? $options['mime'] : '',
            'num' => isset($options['num']) ? $options['num'] : '',
            'type' => isset($options['type']) ? $options['type'] : '',
            'size' => isset($options['size']) ? $options['size'] : '',
            'exts' => isset($options['exts']) ? $options['exts'] : '',
            'accept' => isset($options['accept']) ? $options['accept'] : '',
            'multiple' => isset($options['multiple']) ? $options['multiple'] : '',
            'selecturl' => isset($options['selecturl']) ? $options['selecturl'] : '',
            'tableurl' => isset($options['tableurl']) ? $options['tableurl'] : '',
        ];
        $op = "data-value='" . json_encode($op, true) . "'";
        $select_container= '';
        if ((isset($options['select']) and $options['select']) || !isset($options['select'])) {
            $options['select'] = $options['select']??'upload-select'; //可选upload-choose
            $select_container =  '<button id="'.$name.'" type="button" class="layui-btn layui-btn-danger ' .$options['select'].'" ' . $op . '  lay-filter="'.$options['select'].'"><i class="layui-icon layui-icon-align-center"></i>' . lang('Choose') . '</button>';
        }
        $str = ' <div class="layui-form-item">
                <label class="layui-form-label ' . self::labelRequire($options) . '">' . lang(Str::title($label)) . '</label>
                <div class="layui-input-block">
                    <div class="layui-upload">
                        <input value="' . $value . '" style="'.$css.' ;width:65% " type="text" name="' . $name . '" class="layui-input attach '.self::addClass($options).'"' . self::verify($options) . '/>
                       ' . $croper_container . '
                        <button type="button"  style="margin-left:0px" class="layui-btn"  ' . $op . ' lay-filter="upload"><i class="layui-icon layui-icon-upload-circle"></i>' . lang('Uploads') . '</button>
                        '. $select_container .'
                        <div class="layui-upload-list">'
            . $li . '
                        </div>
                    </div>
                </div>
            </div>';
        return $str;
    }
    /**
     * @param bool $reset
     * @param array $options
     * @return string
     */
    public static function closebtn($reset = true, $options = [])
    {
        $show = '';
        if (!isset($options['show'])) {
            $show = 'layui-hide';
        }
        $str = '<div class="layui-form-item layui-btn-center ' . $show . '">
                <button type="close" class="layui-btn layui-btn-sm '.self::addClass($options).'" onclick="parent.layui.layer.closeAll();">' . lang('Close') .
            '</button>
            </div>';

        return $str;

    }


    /**
     * @param bool $reset
     * @param array $options
     * @return string
     */
    public static function submitbtn($reset, $options)
    {
        $show = '';
        if (!isset($options['show'])) {
            $show = 'layui-hide';
        }
        $str = '<input type="hidden" name="__token__" value="' . self::token() . '"><div class=" layui-btn-submit layui-form-item layui-btn-center ' . $show . '" />
            <button type="submit" class="layui-btn layui-btn-sm" lay-fitler="submit" lay-submit>' . lang('Submit') .
            '</button>';
        if ($reset) {
            $str .= '<button type="reset" class="layui-btn layui-btn-sm layui-btn-primary">' . lang('Reset') . '</button>';
        }
        $str .= '</div>';

        return $str;
    }

    /**
     * @param $options
     * @return string
     * 提示
     */
    protected static function tips($options = [])
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
    protected static function verify($options = [])
    {
        $verify = '';
        if (isset($options['verify'])) {
            $verify = 'lay-verify="' . $options['verify'] . '"';
        }
        return $verify;

    }

    /** 过滤
     * @param $options
     * @return string
     */
    protected static function filter($options = [])
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
    protected static function search($options = [])
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
    protected static function selectedOrchecked($select, $val, $type = 1)
    {
        if ($select == $val) {
            if ($type == 1) return 'selected';
            return 'checked';
        } else {
            return '';
        }
    }

    protected static function labelRequire($options)
    {

        if (isset($options['verify']) && ($options['verify'] == 'required' || strpos($options['verify'], 'required'))) {
            return 'required';
        }
        return '';
    }

    protected static function readonlyOrdisabled($options)
    {

        if (isset($options['readonly'])) {
            return 'readonly';
        }
        if (isset($options['disabled'])) {
            return 'disabled';
        }
        return '';
    }
    //自定义class属性
    protected static function addClass($options)
    {
        if (isset($options['class']) and $options['class']) {
            $classArr = is_array($options['class'])?$options['class']:explode(',',$options['class']);
            return implode(' ',$classArr);
        }
        return '';
    }
}