<?php

namespace fun\helper;


use phpDocumentor\Reflection\Types\Self_;
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
    public static function input($name='',$type='text', $options = [],$value='')
    {
        $lable = isset($options['lable'])?$options['lable']:$name;
        $tips = isset($options['tips'])?$options['tips']:$lable;
        $value = !empty($value)? 'value="'.$value.'"' :'';
        $str = '<div class="layui-form-item"> 
        <label class="layui-form-label '.self::labelRequire($options).'">'.lang(Str::title($lable)).'</label>
        <div class="layui-input-block">
         <input type="' . $type . '" name="' . $name. '"  ' . self::verify($options) . self::filter($options) . ' autocomplete="off"
         placeholder="' . $tips. '" class="layui-input"'.$value.'>
         ' . self::tips($options) . '
         </div></div>';

        return $str;
    }

    /**
     * 生成单选
     *
     * @param $name
     * @param null $value
     * @param null $checked
     * @param array $options
     * @return string
     */
    public static function radio($name=null, $list = null, $options = [])
    {
        if (is_null($list)) {
            $list = $name;
        }
        $label = isset($options['label'])?$options['label']:$name;
        $input = '';
        if(is_string($list) && strpos($list,"\n")!==false) $list = explode("\n",$list);
        if (is_array($list)) {
            foreach ($list as $k=>$v) {
                if(is_string($v) && strpos($v,':')!==false){
                    $v = explode(":",$v);
                    $input .= '<input type="radio"'.self::selectedOrchecked($options,$v[0],2).' name="' . $name . '" ' . self::verify($options) . self::filter($options) . ' value="' . $v[0] . '" title="' . lang($v[1]) . '" >';
                }else{
                    $input .= '<input type="radio"'.self::selectedOrchecked($options,$k,2).' name="' . $name . '" ' . self::verify($options) . self::filter($options) . ' value="' . $k . '" title="' . lang($v) . '" >';

                }
            }
        } else {
            $input .= '<input type="radio" name="' . $name . '" ' . self::verify($options) . self::filter($options) . ' value="' . $list . '" title="' . lang($list) . '" >';
        }

        $str = ' <div class="layui-form-item">
        <label class="layui-form-label '.self::labelRequire($options).'">' . lang(Str::title($label)) . '</label>
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
     */

    public static function switch($name=null, $value, $options = [])
    {

        if (is_array($value)) {
            if(is_string($value) && strpos($value,'|')) $value = implode('|', $value);
        }
        $str = '<div class="layui-form-item">
        <label class="layui-form-label '.self::labelRequire($options).'">' . lang(Str::title($name)) . '</label>
        <div class="layui-input-block">
        <input type="checkbox" checked="" name="' . $name . '" ' . self::verify($options) . self::filter($options) . ' lay-skin="switch"   lay-text="' . lang($value) . '">
        ' . self::tips($options) . '
        </div>
        </div>';

        return $str;
    }


    public static function checkbox($name=null,$value=null,$list = [], $options=[])
    {
        if (empty($value)) {
            $value = $name;
        }
        if(is_string($value) && strpos($value,"\n")!==false) $value = explode("\n",$value);
        if(is_string($value) && strpos($value,",")!==false) $value = explode(",",$value);
        if(is_string($value) && strpos($value,"|")!==false) $value = explode("|",$value);
        if(is_string($list) && strpos($list,"\n")!==false) $list = explode("\n",$list);
        if(is_string($list) && strpos($list,",")!==false) $list = explode(",",$list);
        if(is_string($list) && strpos($list,"|")!==false) $list = explode("|",$list);
        $input = '';
        $skin = '';
        if (isset($options['skin'])) {
            $skin = 'lay-skin="' . $options['skin'] . '"';
        }

        if (is_array($list) and $list) {
            foreach ($list as $k => $v) {
                if(is_string($v) && strpos($v,':')!==false) {
                    $v = explode(":", $v);
                    $check = '';
                    if(is_array($value) && in_array($v[0],$value) || $value = $v[0]){
                        $check = 'checked';
                    }
                    $input .= '<input type="checkbox" '.$check.'  name="' . $name . '[' . $v[0] . ']" ' . $skin . self::verify($options) . self::filter($options) . ' title="' . lang($v[1]) . '">';

                }else{
                    $check = '';
                    if(is_array($value) && in_array($v[0],$value) || $value = $v){
                        $check = 'checked';
                    }
                    $input .= '<input type="checkbox" '.$check.' name="' . $name . '[' . $k . ']" ' . $skin . self::verify($options) . self::filter($options) . ' title="' . lang($v) . '">';
                }
            }
        } else {
            $input .= '<input type="checkbox" name="' . $name . '[]"  ' . $skin . self::verify($options) . self::filter($options) . '  title="' . lang($value) . '">';
        }
        $str = '<div class="layui-form-item">
        <label class="layui-form-label '.self::labelRequire($options).'">' . lang(Str::title($name)) . '</label>
        <div class="layui-input-block">
         ' . $input . self::tips($options) . '
        </div>';
        return $str;
    }

    public static function textarea($name=null, $value = null, $options=[])
    {
        $label = isset($options['label'])?$options['label']:$name;
        $tips = isset($options['tips'])?$options['tips']:$name;
        $str = ' <div class="layui-form-item layui-form-text">
            <label class="layui-form-label '.self::labelRequire($options).'">' . lang(Str::title($label)) . '</label>
            <div class="layui-input-block">
            <textarea placeholder="' . $tips . '" class="layui-textarea" 
            ' . self::filter($options) . self::verify($options) . ' name="'.$name.'"
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
    public static function select($name=null,$select=[],$options=[],$attr=[],$value)
    {
        $op = '';
        foreach ($select as $k => $v) {
            $select = '';
            if(is_array($value) && in_array($v[$attr[0]],$value) || $v[$attr[0]]==$value) $select = 'selected';
            if(!empty($attr)){
                $op .= '<option '.$select.' value="'.$v[$attr[0]].'">'.$v[$attr[1]].'</option>';
            }else{
                $op .= '<option value="'.$k.'">'. $v.'</option>';

            }
        }
        $label = isset($options['label'])?$options['label']:$name;
        $multiple='';
        if (isset($options['multiple'])) {
            $multiple = 'multiple="multiple"';
        }
        if(isset($options['default'])){
            $default = lang($options['default']);
        }else{
            $default = lang('Default');
        }
        $str = '<div class="layui-form-item">
        <label class="layui-form-label '.self::labelRequire($options).'">' . lang(Str::title($label)) . '</label>
        <div class="layui-input-block">
          <select name="' . $name . '" ' . $multiple . self::filter($options) . self::verify($options) . self::search($options) . ' >
            <option value="">' . $default . '</option>
            ' . $op . '
          </select>
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
    public static function color($id,$name,$value,$options=[]){

        $str = '<div class="layui-form-item">
                    <label class="layui-form-label '.self::labelRequire($options).'">'.lang('Icon').'</label>
                    <div class="layui-input-block">
                        <input type="hidden" name="'.$name .'"  value="'.$value.'"' .self::filter($options) . '>
                          <div id="'.$id.'" lay-filter="colorPicker"></div>

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
    public static function icon($name,$value,$options=[]){
        $name = $name?$name:'icon';
        $value = $value?$value:'layui-icon-rate';
        $id = isset($options['id'])?$options['id']:'iconPicker';
        $str = '<div class="layui-form-item">
                    <label class="layui-form-label '.self::labelRequire($options).'">'.lang('Icon').'</label>
                    <div class="layui-input-block">
                        <input type="hidden" name="'.$name.'"  id="'.$id.'" value="'.$value.'" 
                          lay-filters="iconPickers" lay-filter="'.$id.'" class="hide">
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
    public static function date($name=null, $options=[])
    {
        $op = '';
        if (isset($options['type'])) {
            $op .= 'lay-type="' . $options['range'] . '"';
        }
        if (isset($options['type'])) {
            $op .= 'lay-type="' . $options['type'] . '"';

        }
        if (isset($options['format'])) {
            $op .= 'lay-format="' . $options['format'] . '"';

        }
        $str = '<div class="layui-form-item"><div class="layui-inline">
         <label class="layui-form-label '.self::labelRequire($options).'">' . lang('Select Date') . '</label>
         <div class="layui-input-block">
         <input  type="text" name="' . $name . '" class="layui-input" lay-date ' . $op . ' placeholder="yyyy-MM-dd HH:mm:ss">
         </div>
        </div></div>';
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
    public static function editor($name='container',$id,$type=1,$options=[])
    {

        if($id==''){
            $id = $name;
        }
        $str = '<div class="layui-form-item"><div class="layui-inline">
         <label class="layui-form-label '.self::labelRequire($options).' ">' . lang(Str::title($name)) . '</label>
         <div class="layui-input-block">';
        if($type==1){
            //百度。quill wangeditor
         $str.='<div id="' . $id . '" name="' . $name . '" lay-editor="'.$type.'" type="text/plain"></div>';
        }else{
            //LAYEDIT
            $str.='<textarea id="' . $id . '" name="' . $name . '" lay-editor="'.$type.'" type="text/plain"></textarea>';
        }
        $str.='</div></div></div>';

        return $str;

    }
    /**
     * @param string $name
     * @param string $formdata
     * @param array $options
     * @return string
     * 上传
     */
    public static function upload($name='avatar', $formdata='', $options=[])
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
            $options['path'] = 'upload';
        }
        $li = '';
        $croper_container = '';
        if(isset($options['cropper'])){
            $width = isset($options['width'])?$options['width']:'300';
            $height = isset($options['width'])?$options['width']:'300';
            $mark = isset($options['width'])?$options['width']:'1';
            $area = isset($options['area'])?$options['area']:'900px';
            $croper_container = '<button type="button" 
                lay-width = "'.$width.'"
                lay-height = "'.$height.'"
                lay-mark = "'.$mark.'"
                lay-area = "'.$area.'"
                class="layui-btn layui-btn-warm"  lay-cropper id="cropper">'
                .lang('Cropper').
                '</button>';
            $options['num'] = 1;
            $options['type'] = 'radio';
        }
        if ($formdata) {
            foreach (explode(',', $formdata[$name]) as $k => $v) {
                switch ($options['mime']) {

                    case 'video':
                        $li .= '<li><video lay-event="" class="layui-upload-img fl"  width="150" src="' . $v . '"></video>
                    <i class="layui-icon layui-icon-close" lay-event="upfileDelete"
                                   lay-fileurl="' . $v . '"></i></li>';
                        break;
                    case 'audio':
                        $li .= '<li><audio lay-event="" class="layui-upload-img fl"  width="150" src="' . $v . '"></audio>
                    <i class="layui-icon layui-icon-close" lay-event="upfileDelete"
                                   lay-fileurl="' . $v . '"></i></li>';
                        break;
                    case 'image':
                        $li .= '<li><img lay-event="photos" class="layui-upload-img fl"  width="150" src="' . $v . '"></img>
                    <i class="layui-icon layui-icon-close" lay-event="upfileDelete"
                                   lay-fileurl="' . $v . '"></i></li>';
                        break;
                    case 'zip':
                        $li .= '<li><img lay-event="" class="layui-upload-img fl"  width="150" src="/static/backend/images/filetype/zip.jpg"></img>
                    <i class="layui-icon layui-icon-close" lay-event="upfileDelete"
                                   lay-fileurl="' . $v . '"></i></li>';
                        break;
                    case 'office':
                        $li .= '<li><img lay-event="" class="layui-upload-img fl"  width="150" src="/static/backend/images/filetype/office.jpg"></img>
                    <i class="layui-icon layui-icon-close" lay-event="upfileDelete"
                                   lay-fileurl="' . $v . '"></i></li>';
                        break;
                    default:
                        $li .= '<li><img lay-event="photos" class="layui-upload-img fl"  width="150" src="/static/backend/images/filetype/file.jpg">
                    <i class="layui-icon layui-icon-close" lay-event="upfileDelete"
                                   lay-fileurl="' . $v . '"></i></li>';
                        break;
                }

            }
        }
        $value = isset($formdata[$name])?$formdata[$name]:'';
        $op = '';
        $op .= 'lay-path="' . $options['path'] . '"';
        $op .= 'lay-mime="' . $options['mime'] . '"';
        $op .= 'lay-num="' . $options['num'] . '"';
        $op .= 'lay-type="' . $options['type'] . '"';
        $str = ' <div class="layui-form-item">
                <label class="layui-form-label '.self::labelRequire($options).'">'.lang(Str::title($name)).'</label>
                <div class="layui-input-block">
                    <div class="layui-upload">
                        <input value="' . $value . '" style="display: inline-block;width:65% " type="text" name="' . $name . '" class="layui-input attach"' . self::verify($options) . '>
                       '.$croper_container.'
                        <button type="button" class="layui-btn layui-btn-normal"  '.$op.' lay-upload><i class="layui-icon layui-icon-upload-circle"></i>'.lang('Uploads').'</button>
                        <button id="select-upload" type="button" class="layui-btn layui-btn-danger"  '.$op.'  lay-upload-select><i class="layui-icon layui-icon-align-center"></i>'.lang('Choose').'</button>
                        <div class="layui-upload-list">'
                        .$li.'
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
    public static function closebtn($reset = true, $options=[])
    {
        $str =   '<div class="layui-form-item center">
                <button type="button" class="layui-btn layui-btn-sm" onclick="parent.layui.layer.closeAll();">' . lang('Close') .
            '</button>
            </div>';

        return $str;

    }
    /**
     * @param bool $reset
     * @param array $options
     * @return string
     */
    public static function submitbtn($reset = true, $options=[])
    {
        $str =   '<input type="hidden" name="__token__" value="'.self::token().'"><div class="layui-form-item center">
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
    protected static function tips($options=[])
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
    protected static function verify($options=[])
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
    protected static function filter($options=[])
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
        if (!isset($options['search']) || $options['search']==true)  {
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
    protected static function selectedOrchecked($options,$val,$type=1){
        if (!isset($options['select']))  {
           return '';
        }else{
            if($options['select']==$val){
                if($type==1) return 'selected';
                return 'checked';
            }else{
                return '';
            }
        }
    }

    protected static function labelRequire($options){

        if(isset($options['verfiy']) && ($options['verfiy']=='required' || strpos($options['verfiy'],'required'))) {
            return 'required';
        }
        return '';


    }

}