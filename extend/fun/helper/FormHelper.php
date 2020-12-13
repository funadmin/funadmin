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
    public static function input($name='',$type='text', $options = [],$value)
    {
        $label = isset($options['label'])?$options['label']:$name;
        $tips = isset($options['tips'])?$options['tips']:$label;
        $placeholder = isset($options['ptips'])?$options['ptips']:$tips;
        $value = !is_null($value)? 'value="'.$value.'"'  :'';
        if($type=='hidden'){
            return  '<input type="' . $type . '" name="' . $name. '"  ' . self::verify($options) . self::filter($options) . self::readonlyOrdisabled($options) . ' autocomplete="off"         placeholder="' . $placeholder. '" class="layui-input" '. $value.'/>';
        }
        $str = '<div class="layui-form-item"> 
        <label class="layui-form-label '.self::labelRequire($options).'">'.lang(Str::title($label)).'</label>
        <div class="layui-input-block">
         <input type="' . $type . '" name="' . $name. '"  ' . self::verify($options) . self::filter($options) . self::readonlyOrdisabled($options) . ' autocomplete="off"
         placeholder="' . $placeholder. '" class="layui-input"'. $value.'/>
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
    public static function radio($name, $radiolist, $options = [], $value)
    {
        if (is_null($radiolist)) {
            $radiolist = $name;
        }
        $label = isset($options['label'])?$options['label']:$name;
        $input = '';
        if(is_string($radiolist) && strpos($radiolist,"\n")!==false) $radiolist = explode("\n",$radiolist);
        if (is_array($radiolist)) {
            foreach ($radiolist as $k=>$v) {
                if(is_string($v) && strpos($v,':')!==false){
                    $v = explode(":",$v);
                    $input .= '<input type="radio"'.self::selectedOrchecked($value,$v[0],2).' name="' . $name . '" ' . self::verify($options) . self::filter($options) .  self::readonlyOrdisabled($options) .' value="' . $v[0] . '" title="' . lang($v[1]) . '" />';
                }else{
                    $input .= '<input type="radio"'.self::selectedOrchecked($value,$k,2).' name="' . $name . '" ' . self::verify($options) . self::filter($options) .  self::readonlyOrdisabled($options) . ' value="' . $k . '" title="' . lang($v) . '" />';

                }
            }
        } else {
            $input .= '<input type="radio" name="' . $name . '" ' . self::verify($options) . self::filter($options) . ' value="' . $radiolist . '" title="' . lang($radiolist) . '" />';
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
     * switch是关键字不能用
     */

    public static function switchs($name=null, $switch, $options = [], $value)
    {

        if(is_string($switch) && strpos($switch,'|')) $switch = implode('|', $switch);
        $str = '<div class="layui-form-item">
        <label class="layui-form-label '.self::labelRequire($options).'">' . lang(Str::title($name)) . '</label>
        <div class="layui-input-block">
        <input type="checkbox" value="'.$value.'" checked="" name="' . $name . '" ' . self::verify($options) . self::filter($options) .  self::readonlyOrdisabled($options)  .' lay-skin="switch"   data-text="' . lang($value) . '"/>
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
    public static function checkbox($name=null,$list = [], $options=[],$value)
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
                    $input .= '<input type="checkbox" '.$check.'  name="' . $name . '[' . $v[0] . ']" ' . $skin . self::verify($options) . self::filter($options) . self::readonlyOrdisabled($options) . ' title="' . lang($v[1]) . '"/>';

                }else{
                    $check = '';
                    if(is_array($value) && in_array($v[0],$value) || $value = $v){
                        $check = 'checked';
                    }
                    $input .= '<input type="checkbox" '.$check.' name="' . $name . '[' . $k . ']" ' . $skin . self::verify($options) . self::filter($options) . self::readonlyOrdisabled($options) .' title="' . lang($v) . '"/>';
                }
            }
        } else {
            $input .= '<input type="checkbox" name="' . $name . '[]"  ' . $skin . self::verify($options) . self::filter($options) . self::readonlyOrdisabled($options) .'  title="' . lang($value) . '"/>';
        }
        $str = '<div class="layui-form-item">
        <label class="layui-form-label '.self::labelRequire($options).'">' . lang(Str::title($name)) . '</label>
        <div class="layui-input-block">
         ' . $input . self::tips($options) . '
        </div>';
        return $str;
    }

    public static function textarea($name=null, $options=[],$value)
    {
        $label = isset($options['label'])?$options['label']:$name;
        $tips = isset($options['tips'])?$options['tips']:$name;
        $str = ' <div class="layui-form-item layui-form-text">
            <label class="layui-form-label '.self::labelRequire($options).'">' . lang(Str::title($label)) . '</label>
            <div class="layui-input-block">
            <textarea placeholder="' . lang($tips) . '" class="layui-textarea" 
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
    public static function select($name,$select,$options,$attr,$value)
    {
        $op = '';
        foreach ($select as $k => $v) {
            $select = '';
            if(is_array($value) && is_array($attr) && in_array($v[$attr[0]],$value) ||  (is_array($attr) && $v[$attr[0]]==$value))
            {
                $select = 'selected';
            }
            if($value == $k && !$attr)
                $select = 'selected';
            if(!empty($attr)){
                $op .= '<option '.$select.' value="'.$v[$attr[0]].'">'.lang($v[$attr[1]]).'</option>';
            }else{
                $op .= '<option '.$select.' value="'.$k.'">'. lang($v).'</option>';

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
                    <option value="">' . lang($default) . '</option>
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
    public static function tags($id,$name,$options=[],$value){
        $label = isset($options['label'])?$options['label']:$name;
        $str = '<div class="layui-form-item">
                    <label class="layui-form-label '.self::labelRequire($options).'">'.lang($label).'</label>
                    <div class="layui-input-block">
                    <div class="tags" >
                        <input type="hidden" name="'.$name .'" value="'.$value.'" />
                        <input  id="'.$id.'" lay-filter="tags" type="text" placeholder="'.lang("Space To Generate Tags").'" ' .self::filter($options) . self::readonlyOrdisabled($options).'/>
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
    public static function color($id,$name,$options=[],$value){

        $label = isset($options['label'])?$options['label']:$name;
        $str = '<div class="layui-form-item">
                    <label class="layui-form-label '.self::labelRequire($options).'">'.lang($label).'</label>
                    <div class="layui-input-block">
                        <input type="hidden" name="'.$name .'"  value="'.$value.'"' .self::filter($options) . self::readonlyOrdisabled($options).'/>
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
    public static function icon($name,$options=[],$value){
        $name = $name?$name:'icon';
        $value = $value?$value:'layui-icon-rate';
        $id = isset($options['id'])?$options['id']:'iconPicker';
        $str = '<div class="layui-form-item">
                    <label class="layui-form-label '.self::labelRequire($options).'">'.lang('Icon').'</label>
                    <div class="layui-input-block">
                        <input type="hidden" name="'.$name.'"  id="'.$id.'" value="'.$value.'" 
                          lay-filter="iconPickers"  class="hide" />
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
    public static function date($name,$options, $value)
    {
        $op = '';
        if (isset($options['type'])) {
            $op .= 'data-type="' . $options['range'] . '"';
        }
        if (isset($options['type'])) {
            $op .= 'data-type="' . $options['type'] . '"';

        }
        if (isset($options['format'])) {
            $op .= 'data-format="' . $options['format'] . '"';

        }
        $label = isset($options['label'])?$options['label']:$name;
        $str = '<div class="layui-form-item">
         <label class="layui-form-label '.self::labelRequire($options).'">' . lang($label) . '</label>
         <div class="layui-input-block">
         <input  type="text" name="' . $name . '" value="'.$value.'" class="layui-input" lay-filter="date" ' . $op . ' placeholder="yyyy-MM-dd HH:mm:ss"/>
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
    public  static function city($name='cityPicker',$id='cityPicker',$options){

            $str = ' <div class="layui-form-item">
                    <label class="layui-form-label width_auto text-r" style="margin-top:2px">省市县：</label>
                    <div class="layui-input-block">
                        <input type="hidden" autocomplete="on" class="layui-input" lay-filter="cityPicker" id="'.$id.'" name="'.$name.'" readonly="readonly" data-toggle="city-picker" placeholder="请选择"/>
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
    public  static function region($name='regionCheck',$id='regionCheck',$options=[]){

        $str = ' <div class="layui-form-item">
                    <label class="layui-form-label ">区域</label>
                    <div class="layui-input-block">
                        <input type="hidden" name="'.$name.'" value="" />
                        <div id="'.$id.'" name="'.$name.'" lay-filter="regionCheck">
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
    public static function editor($name='container',$id=null,$type=1,$options=[])
    {

        if($id==''){
            $id = $name;
        }
        $label = isset($options['label'])? $options['label'] :$name;
        $str = '<div class="layui-form-item">
         <label class="layui-form-label '.self::labelRequire($options).' ">' . lang(Str::title($label)) . '</label>
         <div class="layui-input-block">';
        if($type==1){
            //百度。quill wangeditor
         $str.='<div id="' . $id . '" name="' . $name . '" data-editor="'.$type.'" lay-filter="editor" type="text/plain"></div>';
        }else{
            //LAYEDIT
            $str.='<textarea id="' . $id . '" name="' . $name . '" data-editor="'.$type.'" lay-verify="layedit" lay-filter="editor" type="text/plain"></textarea>';
        }
        $str.='</div></div>';

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
            $cops = ['width'=>$width, 'height'=>$height, 'mark'=>$mark,'area'=>$area];
            $crpperops = 'data-value="'.json_encode($cops,true).'"' ;
            $croper_container = '<button type="button" 
               '.$crpperops.'
                class="layui-btn layui-btn-warm"  lay-filter="cropper" id="cropper">'
                .lang('Cropper').
                '</button>';
            $options['num'] = 1;
            $options['type'] = 'radio';
        }
        if ($formdata) {
            if(isset($formdata[$name])){
                $formdata = explode(',', $formdata[$name]);
            }else{
                $formdata = explode(',', $formdata);
            }
            foreach ($formdata as $k => $v) {
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
        $value = isset($formdata[$name])?$formdata[$name]:'';

        $op = [
            'path'=>isset($options['path'])?$options['path']:'',
            'mine'=>isset($options['mine'])?$options['mine']:'',
            'num'=>isset($options['num'])?$options['num']:'',
            'type'=>isset($options['type'])?$options['type']:'',
            'size'=>isset($options['size'])?$options['size']:'',
            'exts'=>isset($options['exts'])?$options['exts']:'',
            'accept'=>isset($options['accept'])?$options['accept']:'',
            'multiple'=>isset($options['multiple'])?$options['multiple']:'',
        ];
        $op = "data-value='" .json_encode($op,true) . "'" ;
//        $op .= 'data-path="' . $options['path'] . '"';
//        $op .= 'data-mime="' . $options['mime'] . '"';
//        $op .= 'data-num="' . $options['num'] . '"';
//        $op .= 'data-type="' . $options['type'] . '"';
        $str = ' <div class="layui-form-item">
                <label class="layui-form-label '.self::labelRequire($options).'">'.lang(Str::title($name)).'</label>
                <div class="layui-input-block">
                    <div class="layui-upload">
                        <input value="' . $value . '" style="display: inline-block;width:65% " type="text" name="' . $name . '" class="layui-input attach"' . self::verify($options) . '/>
                       '.$croper_container.'
                        <button type="button" class="layui-btn layui-btn-normal"  '.$op.' lay-filter="upload"><i class="layui-icon layui-icon-upload-circle"></i>'.lang('Uploads').'</button>
                        <button id="select-upload" type="button" class="layui-btn layui-btn-danger"  '.$op.'  lay-filter="upload-select"><i class="layui-icon layui-icon-align-center"></i>'.lang('Choose').'</button>
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
        $show = '';
        if(!isset($options['show'])){
            $show = 'layui-hide';
        }
        $str =   '<div class="layui-form-item layui-btn-center '.$show.'">
                <button type="close" class="layui-btn layui-btn-sm" onclick="parent.layui.layer.closeAll();">' . lang('Close') .
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
        if(!isset($options['show'])){
            $show = 'layui-hide';
        }
        $str =   '<input type="hidden" name="__token__" value="'.self::token().'"><div class="layui-form-item layui-btn-center '.$show.'" />
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
    protected static function selectedOrchecked($select,$val,$type=1){
        if($select==$val){
            if($type==1) return 'selected';
            return 'checked';
        }else{
            return '';
        }
    }

    protected static function labelRequire($options){

        if(isset($options['verify']) && ($options['verify']=='required' || strpos($options['verify'],'required'))) {
            return 'required';
        }
        return '';
    }

    protected static function readonlyOrdisabled($options){

        if(isset($options['readonly'])) {
            return 'readonly';
        }
        if(isset($options['disabled'])) {
            return 'disabled';
        }
        return '';
    }
}