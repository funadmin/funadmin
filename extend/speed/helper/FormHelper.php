<?php

namespace speed\helper;


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
     * @param string $type
     * @param string $name
     * @param string $value
     * @param array $options
     * @return string
     */
    public static function input($type='text', $name='', $options = [])
    {

        $str = '<div class="layui-form-item"> 
        <label class="layui-form-label">{$name}</label>
        <div class="layui-input-block">
         <input type="' . $type . '" name="' . '.$name.' . '"  ' . self::verify($options) . self::filter($options) . ' autocomplete="off"
         placeholder="' . $options['tips'] . '" class="layui-input">
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
    public static function radio($name=null, $value = null, $options = [])
    {
        if (is_null($value)) {
            $value = $name;
        }
        $input = '';
        if (is_array($value)) {
            foreach ($value as $k => $v) {
                $input .= '<input type="radio" name="' . $name . '" ' . self::verify($options) . self::filter($options) . ' value="' . $k . '" title="' . lang($v) . '" >';
            }
        } else {
            $input .= '<input type="radio" name="' . $name . '" ' . self::verify($options) . self::filter($options) . ' value="' . $value . '" title="' . lang($value) . '" >';
        }

        $str = ' <div class="layui-form-item">
        <label class="layui-form-label">' . lang($name) . '</label>
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
            $value = implode('|', $value);
        }
        $str = '<div class="layui-form-item">
        <label class="layui-form-label">' . lang($name) . '</label>
        <div class="layui-input-block">
        <input type="checkbox" checked="" name="' . $name . '" ' . self::verify($options) . self::filter($options) . ' lay-skin="switch"   lay-text="' . lang($value) . '">
        ' . self::tips($options) . '
        </div>
        </div>';

        return $str;
    }


    public static function checkbox($name=null, $value = null, $options=[])
    {
        if (empty($value)) {
            $value = $name;
        }
        $input = '';
        if ($options['skin']) {
            $skin = 'lay-skin="' . $options['skin'] . '"';
        }
        if (is_array($value)) {
            foreach ($value as $k => $v) {
                $input .= '<input type="checkbox" name="' . $name . '[' . $k . ']" ' . $skin . self::verify($options) . self::filter($options) . ' title="' . lang($v) . '">';
            }
        } else {
            $input .= '<input type="checkbox" name="' . $name . '[]"  ' . $skin . self::verify($options) . self::filter($options) . '  title="' . lang($value) . '">';
        }
        $str = '<div class="layui-form-item">
        <label class="layui-form-label">' . lang($name) . '</label>
        <div class="layui-input-block">
         ' . $input . self::tips($options) . '
        </div>';
        return $str;
    }

    public static function textarea($name=null, $value = null, $options=[])
    {

        $str = ' <div class="layui-form-item layui-form-text">
    <label class="layui-form-label">' . lang($options['$name']) . '</label>
    <div class="layui-input-block">
      <textarea placeholder="' . lang($options['place']) . '" class="layui-textarea" 
      ' . self::filter($options) . self::verify($options) . ' name="'.$name.'"
      value="' . $value . '"></textarea>
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
    public static function select($name=null, $value=[], $options=[])
    {
        $op = '';
        foreach ($value as $k => $v) {
            $op .= '<option value=".$k.">' . $v . '</option>';
        }
        if (isset($options['multiple'])) {
            $multiple = 'multiple="multiple"';
        }
        if(isset($options['default'])){
            $default = lang($options['default']);
        }else{
            $default = lang('Default');
        }
        $str = '<div class="layui-form-item">
        <label class="layui-form-label">' . lang($name) . '</label>
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
         <label class="layui-form-label">' . lang('Select Date') . '</label>
         <div class="layui-input-block">
         <input  type="text" name="' . $name . '" class="layui-input" lay-date ' . $op . ' placeholder="yyyy-MM-dd HH:mm:ss">
         </div>
        </div></div>';
        return $str;
    }


    public static function ueditor($id='container', $name='container')
    {
        $str = '<div class="layui-form-item"><div class="layui-inline">
         <label class="layui-form-label">' . lang($name) . '</label>
         <div class="layui-input-block"><script id="' . $id . '" name="' . $name . '" lay-ueditor type="text/plain"></script></div></div></div>';
        return $str;

    }
    public static function wangeditor($id='container', $name='container')
    {
        $str = '<div class="layui-form-item"><div class="layui-inline">
         <label class="layui-form-label">' . lang($name) . '</label>
         <div class="layui-input-block"><div id="' . $id . '" name="' . $name . '" lay-wangeditor type="text/plain"></div></div></div>';
        return $str;

    }

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
        $op .= 'lay-mime="' . $options['mime'] . '"';
        $str = ' <div class="layui-form-item">
                <label class="layui-form-label required">'.lang('Avatar').'</label>
                <div class="layui-input-block">
                    <div class="layui-upload">
                        <input value="' . $value . '" style="display: inline-block;width:65% " type="text" name="' . $name . '" class="layui-input attach"' . self::verify($options) . '>
                        <button type="button" class="layui-btn layui-btn-normal"  '.$op.' lay-upload><i class="layui-icon layui-icon-upload-circle"></i>'.lang('Uploads').'</button>
                        <button type="button" class="layui-btn layui-btn-danger"  '.$op.'  lay-upload-select><i class="layui-icon layui-icon-align-center"></i>'.lang('Choose').'</button>
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
    public static function submitbtn($reset = true, $options=[])
    {
        $str = self::token() . '<div class="layui-form-item center">
                                <button type="submit" class="layui-btn" lay-fitler="submit" lay-submit>' . lang('Submit') .
            '</button>';
        if ($reset) {
            $str .= '<button type="reset" class="layui-btn layui-btn-primary">' . lang('Reset') . '</button>';
        }
        $str .= '</div>';

        return $str;

    }


    /**
     * @param $options
     * @return string
     * 提示
     */
    public static function tips($options=[])
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
    public static function verify($options=[])
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
    public static function filter($options=[])
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
    public static function search($options = [])
    {
        $search = '';
        if (!isset($options['search']) || isset($options['search'])==true)  {
            $search = 'lay-search=""';
        }
        return $search;
    }

}