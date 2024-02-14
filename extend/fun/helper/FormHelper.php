<?php

/**
 * FunAdmin
 * ============================================================================
 * 版权所有 2017-2028 FunAdmin，并保留所有权利。
 * 网站地址: http://www.funadmin.com
 * ----------------------------------------------------------------------------
 * 采用最新Thinkphp8 + layui 实现
 * ============================================================================
 * Author: yuege
 * Date: 2019/9/22
 */

namespace fun\helper;


use think\helper\Str;

class FormHelper
{
    /**
     * 表单html
     * @var array
     */
    protected static $instance;

    /**
     * 获取单例
     * @param array $options
     * @return static
     */
    public static function instance($options = [])
    {
        if (is_null(self::$instance)) {
            self::$instance = new static($options);
        }

        return self::$instance;
    }

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
    public function config($name = '', $options = [], $value = '')
    {
        $where = ['code' => $name];
        $data = \app\common\model\Config::where($where)->find();
        if (!$data) return '';
        $extra = [];
        if (!empty($options['extra'])) {
            $data['extra'] = $options['extra'];
        }
        if ($data['extra'] && is_string($data['extra'])) {
            $arr = array_filter(explode("\n", str_replace("\r", '', $data['extra'])));
            foreach ($arr as $v) {
                $kk = explode(':', $v);
                $extra[$kk[0]] = $kk[1];
            }
        }
        $options['verify'] = $options['verify'] ?? $data['verify'];
        $options['label'] = $options['label'] ?? $data['remark'];
        $value = $value ?: $data['value'];
        switch ($data['type']) {
            case'switch':
                $list = ($options['list'] ?? $extra);
                $form = $this->switchs($name, $list, $options, $value);
                break;
            case'radio':
                $list = ($options['list'] ?? $extra);
                $form = $this->radio($name, $list, $options, $value);
                break;
            case 'hidden':
                $form = $this->hidden($name, $options, $value);
                break;
            case 'float':
            case 'decimal':
            case 'number':
                $form = $this->number($name, $options, $value);
                break;
            case 'select':
                $attr = $options['attr'] ?? ['id', 'title'];
                $list = ($options['list'] ?? $extra);
                $form = $this->multiselect($name, $list, $options, $attr, $value);
                break;
            case 'selects':
                $options['multiple'] = 'multiple';
                $attr = $options['attr'] ?? ['id', 'title'];
                $list = ($options['list'] ?? $extra);
                $form = $this->multiselect($name, $list, $options, $attr, $value);
                break;
            case 'xmselect':
                $attr = $options['attr'] ?? ['id', 'title'];
                $list = ($options['list'] ?? $extra);
                $form = $this->xmselect($name, $list, $options, $attr, $value);
                break;
            case 'selectpage':
                $list = ($options['list'] ?? $extra);
                $form = $this->selectpage($name, $list, $options, $value);
                break;
            case 'tags':
                $form = $this->tags($name, $options, $value);
                break;
            case 'checkbox':
                $list = ($options['list'] ?? $extra);
                $form = $this->checkbox($name, $list, $options, $value);
                break;
            case 'textarea':
                $form = $this->textarea($name, $options, $value);
                break;
            case 'range':
                $form = $this->range($name, $options, $value);
                break;
            case 'daterange':
                $options['type'] = 'datetime';
                $options['range'] = true;
                $form = $this->date($name, $options, $value);
                break;
            case 'year':
                $options['type'] = 'year';
                $form = $this->date($name, $options, $value);
                break;
            case 'month':
                $options['type'] = 'month';
                $form = $this->date($name, $options, $value);
                break;
            case 'time':
                $options['type'] = 'time';
                $form = $this->date($name, $options, $value);
                break;
            case 'date':
            case 'datetime':
                $options['type'] = 'datetime';
                $form = $this->date($name, $options, $value);
                break;
            case 'password':
                $form = $this->password($name, $options, $value);
                break;
            case 'image':
            case 'file':
                $form = $this->upload($name, $options, $value);
                break;
            case "images":
            case 'files':
                $options['num'] = 100;
                $form = $this->upload($name, $options, $value);
                break;
            case 'editor':
                $form = $this->editor($name, $options, $value);
                break;
            case 'color':
                $form = $this->color($name, $options, $value);
                break;
            case 'icon':
                $form = $this->icon($name, $options, $value);
                break;
            case 'token':
                $form = $this->token($name, $value);
                break;
            case 'email':
                $form = $this->email($name, $options, $value);
                break;
            case 'tel':
                $form = $this->tel($name, $options, $value);
                break;
            case 'url':
                $form = $this->url($name, $options, $value);
                break;
            case 'rate':
                $form = $this->rate($name, $options, $value);
                break;
            case 'slider':
                $form = $this->slider($name, $options, $value);
                break;
            case 'arrays':
            case 'array':
                $attr = $options['attr'] ?? ['id', 'title'];
                $list = ($options['list'] ?? $extra);
                $form = $this->arrays($name, $list, $options);
                break;
            case 'selectn':
                $attr = $options['attr'] ?? ['id', 'title'];
                $list = ($options['list'] ?? $extra);
                $form = $this->selectn($name, $list, $options, $attr, $value);
                break;
            case 'selectplus':
                $attr = $options['attr'] ?? ['id', 'title'];
                $list = ($options['list'] ?? $extra);
                $form = $this->selectplus($name, $list, $options, $attr, $value);
                break;
            case 'city':
                $form = $this->city($name, $options, $value);
                break;
            case 'region':
                $form = $this->region($name, $options, $value);
                break;
            case 'autocomplete':
                $attr = $options['attr'] ?? ['id', 'title'];
                $list = ($options['list'] ?? $extra);
                $form = $this->autocomplete($name, $list, $options, $attr, $value);
                break;
            default :
                $form = $this->input($name, 'text', $options, $value);
                break;
        }

        return $form;
    }


    public function token($name = '__token__', $type = 'md5')
    {
        $str = '';
        if (function_exists('token')) {
            $str = token($name, $type);
        }
        return $str;
    }

    /**
     * 生成文本框(按类型) password .text
     * @param string $name
     * @param string $type
     * @param array $options
     * @return string
     */
    public function input(string $name = '', string $type = 'text', array $options = [], $value = '')
    {
        $type = $options['type'] ?? $type;
        $disorread = $this->readonlyOrdisabled($options);
        if ($type == 'hidden') {
            return <<<EOF
            <input  type="{$type}" {$this->getDataPropAttr($name, $value, $options)} autocomplete="off"  class="layui-input {$this->getClass($options)}  {$disorread}"/>
EOF;
        }
        $options['affix'] = $options['affix']??'clear';
        $str = <<<EOF
<div class="layui-form-item {$this->getClass($options,'outclass')}">{$this->label($name, $options)}
        <div class="layui-input-block">
         <input  type="{$type}" {$this->getDataPropAttr($name, $value, $options)}  autocomplete="off"
          {$this->getStyle($options)}  class="layui-input  {$this->getClass($options)}  $disorread "/>
         {$this->tips($options)} 
         </div></div>
EOF;

        return $str;
    }

    /**
     * @param string $name
     * @param array $options
     * @param  $value
     * @return string
     */
    public function text(string $name, array $options = [], $value = null)
    {
        return $this->input($name, 'text', $options, $value);
    }

    /**
     * 创建一个密码输入字段
     *
     * @param string $name
     * @param array $options
     *
     * @return string
     */
    public function password(string $name, array $options = [], $value = '')
    {
        $options['verify'] = $options['verify'] ?? 'pass';
        $options['type'] = 'password';
        $options['affix'] = $options['affix'] ?? 'eye';
        return $this->input($name, 'password', $options, $value);
    }

    /**
     * 创建一个范围输入选择器
     *
     * @param string $name
     * @param null $value
     * @param array $options
     *
     * @return string
     */
    public function range($name, $options = [], $value = null)
    {

        $disorread = $this->readonlyOrdisabled($options);
        $str = <<<EOF
                <div class="layui-form-item {$this->getClass($options,'outclass')}">{$this->label($name, $options)}
            <div class="layui-input-block">
              <div class="layui-input-inline" style="width: 100px;">
                <input {$this->getOptionsAttr($name, $options)} type="text" name="{$name}_min" autocomplete="off"  class="layui-input {$this->getClass($options)}  {$disorread} "/>
              </div>
              <div class="layui-form-mid">-</div>
              <div class="layui-input-inline" style="width: 100px;">
                <input {$this->getOptionsAttr($name, $options)}  type="text" name="{$name}_max"  autocomplete="off"  class="layui-input  {$this->getClass($options)}  {$disorread}" />
              </div>
            </div>
          </div>
EOF;

        return $str;
    }

    /**
     * 创建一个隐藏的输入字段
     *
     * @param string $name
     * @param null $value
     * @param array $options
     *
     * @return string
     */
    public function hidden($name, $options = [], $value = null)
    {
        return $this->input($name, 'hidden', $options, $value);
    }

    /**
     * 创建一个电子邮件输入字段
     *
     * @param string $name
     * @param null $value
     * @param array $options
     *
     * @return string
     */
    public function email($name, $options = [], $value = null)
    {
        $options['verify'] = $options['verify'] ?? 'email';
        return $this->input($name, 'email', $options, $value);
    }

    /**
     * 创建一个tel输入字段
     *
     * @param string $name
     * @param null $value
     * @param array $options
     *
     * @return string
     */
    public function tel($name, $options = [], $value = null)
    {
        $options['verify'] = $options['verify'] ?? 'phone';
        return $this->input($name, 'tel', $options, $value);
    }

    /**
     * 创建一个数字输入字段
     *
     * @param string $name
     * @param null $value
     * @param array $options
     *
     * @return string
     */
    public function number($name, $options = [], $value = null)
    {
        $options['verify'] = $options['verify'] ?? 'number';
        $options['affix'] = $options['affix'] ?? 'number';
        return $this->input($name, 'number', $options, $value);
    }

    /**
     * 创建一个url输入字段
     *
     * @param string $name
     * @param null $value
     * @param array $options
     *
     * @return string
     */
    public function url($name, $options = [], $value = null)
    {
        $options['verify'] = $options['verify'] ?? 'url';
        return $this->input($name, 'url', $options, $value);
    }

    /**
     * 评分
     * @param $name
     * @param $options
     * @param $value
     * @return string
     */
    public function rate($name = '', $options = [], $value = '')
    {
        $options['filter'] = $options['filter'] ?? 'rate';
        $str = <<<EOF
<div class='layui-form-item {$this->getClass($options,'outclass')}' > 
    {$this->label($name, $options)}
    <div class='layui-input-block'>
        <input readonly type='text' {$this->layverify($options)} {$this->getNameValueAttr($name, $value, $options)} class='layui-input'>
        <div {$this->getOptionsAttr($name, $options)}  {$this->getStyle($options)} class='{$this->getClass($options)}'>
        {$this->tips($options)} 
        </div>
    </div>
</div>
EOF;

        return $str;
    }

    /**
     * 滑块
     * @param $name
     * @param $options
     * @param $value
     * @return string
     */
    public function slider($name = '', $options = [], $value = '')
    {
        list($name, $id) = $this->getNameId($name, $options);
        $options['filter'] = $options['filter'] ?? 'slider';
        $disorread = $this->readonlyOrdisabled($options) ? 'layui-disabled' : '';
        $str = <<<EOF
<div class='layui-form-item {$this->getClass($options,'outclass')}'>{$this->label($name, $options)}
    <div class='layui-input-block' >
        <div {$this->getDataPropAttr($name,$value, $options)} style='top:16px'   class='{$disorread} {$this->getClass($options)}'>
        {$this->tips($options)}
        </div>
    </div>
</div>
EOF;

        return $str;
    }

    /**
     * @param $name
     * @param $radiolist
     * @param array $options
     * @param string $value
     * @return string
     */
    public function radio($name = '', $radiolist = [], $options = [], $value = '')
    {
        $input = '';
        $radiolist = $this->getArray($name, $radiolist);
        if (is_array($radiolist)) {
            foreach ($radiolist as $k => $v) {
                if (is_string($v) && strpos($v, ':') !== false) {
                    $v = explode(":", $v);
                    $input .= <<<EOF
<input {$this->getDataPropAttr($name, $v[0], $options)} class="{$this->getClass($options)}" type="radio" {$this->selectedOrchecked($value, $v[0], 2)}   title="{$this->__($v[1])}" />
EOF;
                } else {
                    $input .= <<<EOF
<input {$this->getDataPropAttr($name, $k, $options)} class="{$this->getClass($options)}" {$this->getNameValueAttr($name, $value, $options)} type="radio" {$this->selectedOrchecked($value, $k, 2)}  title="{$this->__($v)}" />
EOF;
                }
            }
        } else {
            $input .= <<<EOF
 <input {$this->getDataPropAttr($name, $radiolist, $options)} class="{$this->getClass($options)}" type="radio"  title="{$this->__($radiolist)}" />
EOF;
        }
        $str = <<<EOF
<div class="layui-form-item {$this->getClass($options,'outclass')}">{$this->label($name, $options)}
    <div class="layui-input-block">
     {$input}
    {$this->tips($options)}
    </div>
</div>
EOF;

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

    public function switchs($name = '', $switch = [], $options = [], $value = '')
    {
        $switchArr = $this->getArray($name, $switch);
        $switchStr = $switchArr ? $this->__($switchArr[1]) . '|' . $this->__($switchArr[0]) : $this->__('open') . '|' . 'close';
        $checked = $value ? 'checked="true"' : '';
        $str = <<<EOF
        <div class="layui-form-item {$this->getClass($options,'outclass')}"> {$this->label($name, $options)} 
            <div class="layui-input-block">
            <input {$this->getDataPropAttr($name, $value, $options)} class="{$this->getClass($options)}" type="checkbox" {$checked} lay-skin="switch" lay-text="{$switchStr}"  data-text="{$this->__($value)}"/>
            {$this->tips($options)} 
            </div>
        </div>'
EOF;

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
    public function checkbox($name = '', $list = [], $options = [], $value = '')
    {
        $name = $options['formname'] ?? $name;
        if (empty($value)) $value = $name;
        $value = $this->getArray($name, $value);
        $list = $this->getArray($name, $list);
        $input = '';
        if (is_array($list) && $list) {
            foreach ($list as $k => $v) {
                if (is_string($v) && (Str::contains($v, ':') || Str::contains($v, '：'))) {
                    $v = str_replace('：', ':', $v);
                    $v = explode(":", $v);
                    $check = '';
                    if (is_array($value) && in_array($v[0], $value) || $v[0] == $value) {
                        $check = 'checked';
                    }
                    $value_tmp = $k;
                    $name_tmp = $name[$v[0]];
                    $input .= <<<EOF
<input {$this->getDataPropAttr($name_tmp, $value_tmp, $options)} class="{$this->getClass($options)}" type="checkbox" {$check}  title="{$this->__($v[1])}"/>
EOF;
                } else {
                    $check = '';
                    if ((is_array($value) && is_array($v) && in_array($v[0], $value)) || $value == $v) {
                        $check = 'checked';
                    } elseif ((is_array($value) && is_string($v) && in_array($k, $value)) || $value == $v) {
                        $check = 'checked';
                    }
                    $value_tmp = $k;
                    $name_tmp = $name.'['.$k.']';
                    $input .= <<<EOF
<input {$this->getDataPropAttr($name_tmp, $value_tmp, $options)} class="{$this->getClass($options)}" type="checkbox"  {$check}   title="{$this->__($v)}"/>
EOF;
                }
            }
        } else {
            $value_tmp = $value;
            $name_tmp = "{$name}[]";
            $input .= <<<EOF
<input {$this->getDataPropAttr($name_tmp, $value_tmp, $options)} class="{$this->getClass($options)}" type="checkbox"  title="{$this->__($value)}"/>
EOF;
        }
        $str = <<<EOF
<div class="layui-form-item {$this->getClass($options,'outclass')}">
{$this->label($name, $options)}
    <div class="layui-input-block">
     {$input} {$this->tips($options)}
    </div>
</div>
EOF;

        return $str;
    }

    /**
     * 数组表单
     * @param null $name
     * @param array $options
     * @param array $list
     * @return string
     */
    public function arrays($name = '', $list = [], $options = [])
    {
        list($name, $id) = $this->getNameId($name, $options);
        $arr = '';
        $tr = '';
        if (!empty($list)) {
            foreach ($list as $key => $val) {
                $tr .= <<<EOF
                       <tr class="tr sortable">
                        <td>
                        <div><input type="text" {$this->getDataPropAttr("{$name}[key][]", $key, $options)} placeholder="{$this->__('Key')}"  class="layui-input key">
                        </div>
                        </td>
                        <td>
                        <div><input type="text" {$this->getDataPropAttr("{$name}[value][]", $val, $options)}  placeholder="{$this->__('Value')}" class="layui-input value">
                        </div>
                        </td>
                         <th>
                            <div class="btn">
                                <span class="add">
                                <i class="layui-icon layui-icon-addition"></i>
                                </span><span class="del">
                                <i  class="layui-icon layui-icon-delete"></i></span>
                                </div>
                            </th>
                    </tr>
EOF;

            }

        } else {
            $tr .= <<<EOF

                        <tr class="tr sortable">
                        <td>
                        <div><input type="text" name="{$name}[key][]" value="" placeholder="{$this->__('Key')}" class="layui-input key">
                        </div>
                        </td>
                        <td>
                        <div><input type="text" name="{$name}[value][]" value="" placeholder="{$this->__('Value')}" class="layui-input value">
                        </div>
                        </td>
                         <th>
                            <div class="btn">
                                <span class="add">
                                <i class="layui-icon layui-icon-addition"></i>
                                </span><span class="del">
                                <i  class="layui-icon layui-icon-delete"></i></span>
                                </div>
                            </th>
                    </tr>
EOF;
        }
        $arr .= <<<EOF
     <div class="layui-form-item {$this->getClass($options,'outclass')}" >
    {$this->label($name, $options)}
        <div class="layui-input-block">
                <table class="layui-table" filter="array">
                    <thead>
                    <tr>
                        <th>{$this->__('Key')}</th>
                        <th>{$this->__('Value')}</th>
                        <td>{$this->__('Oprate')}</td>
                    </tr>
                    </thead>
                    <tbody class="form-sortable layui-table-tr">
                   {$tr}
                    </tbody>
                </table>
        </div>
    </div>
EOF;
        $str = '<div class="form-array" id="' . $name . '">' . $arr . '</div>';
        return $str;
    }

    /**
     * 文本
     * @param null $name
     * @param array $options
     * @param $value
     * @return string
     */
    public function textarea($name = '', $options = [], $value = '')
    {
        $str = <<<EOF
 <div class="layui-form-item layui-form-text {$this->getClass($options,'outclass')}">{$this->label($name, $options)}
    <div class="layui-input-block">
            <textarea {$this->getDataPropAttr($name, $value, $options)} class="layui-textarea {$this->getClass($options)}" 
            >{$value}</textarea>
            {$this->tips($options)}
            </div></div>
EOF;

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
    public function selectn($name = '', $select = [], $options = [], $attr = [], $value = '')
    {
        $name = $options['formname'] ?? $name;
        $label = $options['label'] ?? $name;
        $options['url'] = $options['url'] ?? '';
        $options['delimiter'] = $options['delimiter'] ?? '';
        $options['search'] = isset($options['search']) ? true : '';
        $options['num'] = $options['num'] ?? 3;
        $options['last'] = $options['last'] ?? '';
        if ($attr) {
            $attr = is_array($attr) ? implode(',', $attr) : $attr;
        }
        $options['filter'] = $options['filter'] ?? 'selectN';
        $options['data'] = json_encode((array)$select, JSON_UNESCAPED_UNICODE);
        $options['attr'] = $attr;
        $str = <<<EOF
<div class="layui-form-item layui-form {$this->getClass($options,'outclass')}" lay-filter="{$name}">{$this->label($name, $options)}
    <div class="layui-input-block">
      <div  
{$this->getDataPropAttr($name, $value, $options)}  class="{$this->getClass($options)}" {$this->laysearch($options)} {$this->readonlyOrdisabled($options)} >
      </div>
      {$this->tips($options)}
    </div>
</div>
EOF;

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
    public function selectplus($name = '', $select = [], $options = [], $attr = [], $value = '')
    {
        list($name, $id) = $this->getNameId($name, $select);
        $options['url'] = $options['url'] ?? '';
        $options['delimiter'] = $options['delimiter'] ?? '';
        $options['fielddelimiter'] = $options['fielddelimiter'] ?? '';
        $options['verify'] = $options['verify'] ?? '';
        $multiple = !empty($options['multiple']) ? 'multiple="multiple"' : '';

        $options['multiple'] = $multiple ? 1 : '';
        if ($attr) {
            $attr = is_array($attr) ? implode(',', $attr) : $attr;
        }
        $options['attr'] = $attr;
        $options['data'] = json_encode((array)$select, JSON_UNESCAPED_UNICODE);
        $options['filter'] = $options['filter'] ?? "selectPlus";
        $str = <<<EOF
    <div class="layui-form-item {$this->getClass($options,'outclass')}">{$this->label($name, $options)}
        <div class="layui-input-block">
          <div class="{$this->getClass($options)}" {$this->getDataPropAttr($name, $value, $options)}    {$multiple} >
          </div>
           {$this->tips($options)} 
        </div>
    </div>
EOF;

        return $str;
    }

    public function autocomplete($name = '', $list = [], $options = [], $attr = [], $value = '')
    {
        list($name, $id) = $this->getNameId($name, $options);
        $disorread = $this->readonlyOrdisabled($options);
        $options['filter'] = 'autoComplete';
        $data = json_encode($list);
        $attr = json_encode($attr);
        $str = <<<EOF
<div class="layui-form-item {$this->getClass($options,'outclass')}">{$this->label($name, $options)}
    <div class="layui-input-block">
     <input data-data='{$data}' data-attr="{$attr}" type="search" dir="ltr" spellcheck=false autocorrect="off" autocomplete="off" autocapitalize="off" maxlength="2048" tabindex="1"
        {$this->getDataPropAttr($name, $value, $options)} 
      {$this->getStyle($options)}  class="layui-input  {$this->getClass($options)}  $disorread "/>
     {$this->tips($options)} 
    </div>
 </div>
EOF;
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
    public function select($name = '', $select = [], $options = [], $attr = [], $value = '')
    {
        return $this->multiselect($name, $select, $options, $attr, $value);
    }

    /**
     * @param $name
     * @param $select
     * @param $options
     * @param $attr
     * @param $value
     * @return string
     */
    public function multiselect($name = '', $select = [], $options = [], $attr = [], $value = '')
    {
        list($name, $id) = $this->getNameId($name, $options);
        $op = '';
        if ($select) {
            foreach ($select as $k => $v) {
                $selected = '';
                if (is_array($v) && (is_array($value) && is_array($attr) && !empty($attr) && in_array($v[$attr[0]], $value) || (is_array($attr) && !empty($attr) && $v[$attr[0]] == $value))) {
                    $selected = 'selected';
                }
                if (is_array($value) && in_array($k, $value) && !$attr) {
                    $selected = 'selected';
                }
                if (is_string($v)) {
                    $op .= '<option ' . $selected . ' value="' . $k . '">' . $this->__($v) . '</option>';
                }
                if (!empty($attr) && (is_array($v) || is_object($v))) {
                    $op .= '<option ' . $selected . ' value="' . $v[$attr[0]] . '">' . $this->__($v[$attr[1]]) . '</option>';
                }
            }
        }
        $multiple = '';
        if (isset($options['multiple'])) {
            $multiple = 'multiple="multiple"';
        }
        if (isset($options['default'])) {
            $default = $this->__($options['default']);
        } else {
            $default = $this->__('Select');
        }
        $attr = is_array($attr) ? implode(',', $attr) : $attr;
        $options['attr'] = $attr;
        $str = <<<EOF
<div class="layui-form-item {$this->getClass($options,'outclass')}"> {$this->label($name, $options)}
    <div class="layui-input-block">
      <select {$this->getDataPropAttr($name, $value, $options)}  class="layui-select-url layui-select {$this->getClass($options)}"  {$multiple}    >
        <option value="">{$this->__($default)}</option>
        {$op}
      </select>
      {$this->tips($options)}
    </div>
</div>
EOF;

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
    public function xmselect($name = '', $select = [], $options = [], $attr = [], $value = '')
    {
        list($name, $id) = $this->getNameId($name, $options);
        $op = '';
        if (is_array($select)) {
            $op .= " data-data='" . json_encode($select, JSON_UNESCAPED_UNICODE) . "'";
        }
        if (is_object($select)) {
            $op .= " data-data='" . json_encode((array)$select, JSON_UNESCAPED_UNICODE) . "'";
        }
        $attr = is_array($attr) ? implode(',', $attr) : $attr;
        $value = is_array($value) ? implode($value) : $value;
        $options['attr'] = $options['attr'] ?? $attr;
        $options['lang'] = $options['lang'] ?? '';
        $options['tips'] = $options['tips'] ?? '';
        $options['empty'] = $options['empty'] ?? '';
        $options['repeat'] = $options['repeat'] ?? '';
        $options['content'] = $options['content'] ?? '';
        $options['searchTips'] = $options['searchTips'] ?? '';
        $options['style'] = $options['style'] ?? '';
        $options['filterable'] = $options['filterable'] ?? '';
        $options['remoteSearch'] = $options['remoteSearch'] ?? '';
        $options['remoteMethod'] = $options['remoteMethod'] ?? '';
        $options['height'] = $options['height'] ?? '';
        $options['paging'] = $options['paging'] ?? '';
        $options['size'] = $options['size'] ?? '';
        $options['pageSize'] = $options['pageSize'] ?? '';
        $options['pageRemote'] = $options['pageRemote'] ?? '';
        $options['clickClose'] = $options['clickClose'] ?? '';
        $options['layReqText'] = $options['reqText'] ?? ($options['layReqText']??'');
        $options['layVerify'] = $options['verify'] ?? ($options['layVerify']??'');
        $options['layVerType'] = $options['layVerType']?? ($options['verType']??($options['vertype']??''));
        $options['radio'] = $options['radio'] ?? '';
        $options['url'] = $options['url'] ?? '';
        $options['tree'] = $options['tree'] ?? '';
        $options['prop'] = $options['prop'] ?? '';
        $options['parentField'] = $options['parentField'] ?? 'pid';
        $options['max'] = $options['max'] ?? '';
        $options['verify'] = $options['verify'] ?? '';
        $options['disabled'] = $options['disabled'] ?? '';
        $options['create'] = $options['create'] ?? '';
        $options['theme'] = $options['theme'] ?? '';
        $options['value'] = $options['value'] ?? '';
        $options['autorow'] = $options['autorow'] ?? ($options['autoRow']??'');
        $options['filter'] = 'xmSelect';
        $options['toolbar'] = isset($options['toolbar']) ? json_encode($options['toolbar'], JSON_UNESCAPED_UNICODE) : '';
        $str = <<<EOF
<div class="layui-form-item {$this->getClass($options,'outclass')}">{$this->label($name, $options)}    
    <div {$this->getDataPropAttr($name, $value, $options)} class="layui-input-block {$this->getClass($options)} "  {$op}>
     {$this->tips($options)}
    </div>
</div>
EOF;

        return $str;
    }

    /**
     * 创建动态下拉列表字段
     * @param $name
     * @param $options
     * @param $value
     * @return string
     */
    public function selectpage(string $name, array $lists = [], array $options = [], $value = null)
    {
        list($name, $id) = $this->getNameId($name, $options);
        $options['filter'] = 'selectPage';
        $options['data'] = empty($lists) ? '' : json_encode($lists);
        $options['field'] = $options['field'] ?? 'title';
        $options['primaryKey'] = $options['field'] ?? 'id';
        $options['multiple'] = $options['multiple'] ?? '';
        $options['init'] = $value;
        return $this->input($name, 'text', $options, $value);
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
        list($name, $id) = $this->getNameId($name, $options);
        $options['filter'] = $options['filter'] ?? 'tags';
        $options['placeholder'] = $options['placeholder'] ?? 'Space To Generate Tags';
        $labelOptions = $options;
        $verify = '';
        if (isset($options['verify'])) {
            $verify = $this->layverify($options);
            unset($options['verify']);
        }
        $str = <<<EOF
<div class="layui-form-item {$this->getClass($options,'outclass')}">{$this->label($name, $labelOptions)}
    <div class="layui-input-block">
        <div class="tags" >
            <input id="{$id}" {$this->getOptionsAttr($name, $options)} class="{$this->getClass($options)}"   type="text"  />
            <input type="text" class="layui-input layui-form-required-hidden" name="{$name}" value="{$value}" {$verify} />
        </div>
    </div>
</div>
EOF;

        return $str;
    }

    /**
     * @param $name
     * @param $value
     * @param array $options
     * @return string
     * 颜色选择
     */
    public function color($name = '', $options = [], $value = '')
    {
        list($name, $id) = $this->getNameId($name, $options);
        $format = $options['format'] ?? 'hex';
        $options['filter'] = $options['filter'] ?? 'colorPicker';
        $options['verify'] = $options['verify'] ?? '';
        $str = <<<EOF
<div class="layui-form-item {$this->getClass($options,'outclass')}">{$this->label($name, $options)}
    <div class="layui-input-block">
        <input {$this->getNameValueAttr($name, $value, $options)} lay-verify="{$options['verify']}" lay-vertype="tips" class="layui-input layui-input-inline {$this->getClass($options)}"  type="text" />
        <div {$this->getOptionsAttr($name, $options)}   data-format = "{$format}" ></div>
    </div>
</div>
EOF;

        return $str;
    }

    /**
     * @param $name
     * @param $options
     * @param $value
     * @return string
     */
    public function icon($name = '', $options = [], $value = '')
    {
        list($name, $id) = $this->getNameId($name, $options);
        $value = $value ?: 'layui-icon layui-icon-app';
        $options['filter'] = 'iconPicker';
        $str = <<<EOF
<div class="layui-form-item {$this->getClass($options,'outclass')}">{$this->label($name, $options)}
    <div class="layui-input-block">
        <input {$this->getDataPropAttr($name, $value, $options)} type="text" class="hide layui-input layui-hide {$this->getClass($options)}" />
    </div>
</div>
EOF;

        return $str;
    }

    /**
     * @param $name
     * @param $options
     * @param $value
     * @return string
     */
    public function date($name = '', $options = [], $value = '')
    {
        list($name, $id) = $this->getNameId($name, $options);
        $options['placeholder'] = $options['placeholder'] ?? 'yyyy-MM-dd HH:mm:ss';
        $options['filter'] = $options['filter'] ?? 'date';
        $str = <<<EOF
<div class="layui-form-item {$this->getClass($options,'outclass')}"> {$this->label($name, $options)}       
    <div class="layui-input-block layui-input-wrap">
    <div class="layui-input-prefix"><i class="layui-icon layui-icon-date"></i></div>
    <input {$this->getDataPropAttr($name, $value, $options)}  class="layui-input {$this->getClass($options)} {$this->readonlyOrdisabled($options)}" type="text" />
    </div>
</div>

EOF;

        return $str;
    }

    /**
     * @param $name
     * @param $options
     * @param $value
     * @return string
     * 城市选择
     */
    public function city($name = 'cityPicker', $options = [], $value = '')
    {
        list($name, $id) = $this->getNameId($name, $options);
        $options['provinceId'] = $options['provinceId'] ?? 'province_id';
        $options['cityId'] = $options['cityId'] ?? 'city_id';
        $options['districtId'] = $options['districtId'] ?? 'area_id';
        $options['filter'] = $options['filter'] ?? 'cityPicker';
        $options['readonly'] = $options['readonly'] ?? 'readonly';
        $options['placeholder'] = $options['placeholder'] ?? '请选择';
        $str = <<<EOF
<div class="layui-form-item {$this->getClass($options,'outclass')}">
<label class="layui-form-label width_auto text-r" style="margin-top:2px">省市县：</label>
    <div class="layui-input-block">
        <input data-toggle="city-picker" {$this->getDataPropAttr($name, $value, $options)} type="text" autocomplete="off" class="layui-input layui-form-required-hidden {$this->getClass($options)} "  />
    </div>
</div>
EOF;

        return $str;
    }

    /**
     * 城市选择
     * @param string $name
     * @param $options
     * @return string
     */
    public function region($name = 'region', $options = [], $value = '')
    {
        list($name, $id) = $this->getNameId($name, $options);
        $options['filter'] = 'region';
        $str = <<<EOF
 <div class="layui-form-item {$this->getClass($options,'outclass')}">{$this->label($name, $options)}
    <div class="layui-input-block">
        <input type="text" class="layui-input" name="{$name}" value="{$value}" {$this->layverify($options)} />
        <div {$this->getOptionsAttr($name, $options)}  class="{$this->getClass($options)}" id="{$id}" name="{$name}">
        </div>
    </div>
</div>
EOF;

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
    public function editor($name = 'container', $options = [], $value = '')
    {
        $options['id'] = $options['id'] ?? $name;
        $options['path'] = $options['path'] ?? 'upload';
        $options['height'] = $options['height'] ?? '400px';
        $options['url'] = $options['url'] ?? '';
        $options['editor'] = $options['editor'] ?? (syscfg('upload', 'upload_editor') ?: 'tinymce');
        $options['filter'] = 'editor';
        if ($options['editor'] == 'tinymce') {
            // tinyedit
            $content = <<<EOF
            <textarea class="layui-form-required-hidden" {$this->getDataPropAttr($name, $value, $options)} lay-editor type="text/plain">{$value}</textarea>
EOF;
        } else {
            //百度。quill wangeditor ckeditor,editormd

            $text = '';
            if (isset($options['textarea'])) {
                $verify = '';
                if (!empty($options['verify'])) {
                    $verify = 'lay-verify ="' . $options['verify'] . '"';
                }
                $text = <<<EOF
 <textarea {$verify} {$this->getNameValueAttr($name, $value, $options)} </textarea>
EOF;
            }
            if (!empty($options['verify'])) {
                unset($options['verify']);
            }
            $content = <<<EOF
            <div {$this->getDataPropAttr($name, $value, $options)} lay-editor  type="text/plain" >
             {$text}
            </div>
EOF;
        }
        $str = <<<EOF
<div class="layui-form-item {$this->getClass($options,'outclass')}">{$this->label($name, $options)}
     <div class="layui-input-block">
    {$content}
    </div>
</div>
EOF;

        return $str;
    }

    /**
     * 上传
     * @param string $name
     * @param string $formData
     * @param array $options
     * @return string
     */
    public function upload($name = 'avatar', $options = [], $value = '')
    {
        list($name, $id) = $this->getNameId($name, $options);
        if (empty($options['type'])) $options['type'] = 'radio';
        if (empty($options['mime'])) $options['mime'] = 'images';
        if (empty($options['num'])) $options['num'] = 1;
        if (!empty($options['num']) && $options['num'] == '*') $options['num'] = 100;
        if (empty($options['path'])) $options['path'] = 'upload'; //上传路劲
        $css = $options['css'] ?? 'display:inline-block;';
        $li = '';
        $croper_container = '';
        if (!empty($options['cropper'])) {
            $cops = ['name' => $name,
                'path' => $options['path'],
                'width' => $options['saveW'] ?? '300',
                'height' => $options['saveW'] ?? '300',
                'mark' => $options['mark'] ?? 1,
                'area' => !empty($options['area']) ? json_encode($options['area']) : json_encode(['100%', '100%']),
                'filter' => 'cropper',
            ];
            $data_value = $this->getOptionsAttr($name, $cops);
            $croper_container = <<<EOF
<button type="button" {$data_value}  class="layui-btn layui-btn-sm" id="cropper-{$id}"><i class="layui-icon layui-icon-upload"></i>
                {$this->__('Cropper')}                
</button>
EOF;
        }
        $values = [];
        if ($value && is_string($value)) {
            $values = explode(',', $value);
        } else {
            $values = is_array($value) ? $value : [];
        }
        if (!empty(array_filter($values))) {
            foreach ($values as $k => $v) {
                if ($k + 1 <= $options['num']) {
                    switch ($options['mime']) {
                        case 'video':
                            $v = $v ?: '/static/backend/images/filetype/video.png';
                            $li .= <<<EOF
<li><video lay-event="" class="layui-upload-img fl"  width="150" src="{$v}"></video>  <i class="layui-icon layui-icon-close" lay-event="filedelete" data-fileurl="$v"></i></li>
EOF;
                            break;
                        case 'audio':
                            $v = $v ?: '/static/backend/images/filetype/audio.jpg';
                            $li .= <<<EOF
<li><audio lay-event="" class="layui-upload-img fl"  width="150" src="'{$v}"></audio> <i class="layui-icon layui-icon-close" lay-event="filedelete"  data-fileurl="{$v}"></i></li>
EOF;
                            break;
                        case 'images':
                            $v = $v ?: '/static/backend/images/filetype/image.jpg';
                            $li .= <<<EOF
<li><img lay-event="photos" class="layui-upload-img fl"  width="150" src="{$v}"></img>  <i class="layui-icon layui-icon-close" lay-event="filedelete" data-fileurl="{$v}"></i></li>
EOF;
                            break;
                        case 'image':
                            $v = $v ?: '/static/backend/images/filetype/image.jpg';
                            $li .= <<<EOF
<li><img lay-event="photos" class="layui-upload-img fl"  width="150" src="{$v}"></img>  <i class="layui-icon layui-icon-close" lay-event="filedelete" data-fileurl="{$v}"></i></li>
EOF;
                            break;
                        case 'zip':
                            $li .= <<<EOF
<li><img lay-event="" class="layui-upload-img fl"  width="150" src="/static//backend/images/filetype/zip.jpg"></img> <i class="layui-icon layui-icon-close" lay-event="upfileDelete" data-fileurl="{$v}"></i></li>
EOF;
                            break;
                        case 'office':
                            $li .= <<<EOF
<li><img lay-event="" class="layui-upload-img fl"  width="150" src="/static/backend/images/filetype/office.jpg"></img> <i class="layui-icon layui-icon-close" lay-event="filedelete"  data-fileurl="{$v}"></i></li>
EOF;
                            break;
                        default:
                            $li .= <<<EOF
<li><img lay-event="photos" class="layui-upload-img fl"  width="150" src="/static/backend/images/filetype/file.jpg"> <i class="layui-icon layui-icon-close" lay-event="filedelete" data-fileurl="{$v}"></i></li>
EOF;
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
            'exts' => $options['exts'] ?? '*',
            'accept' => $options['accept'] ?? 'file',
            'multiple' => $options['multiple'] ?? '',
            'selecturl' => $options['selecturl'] ?? '',
            'tableurl' => $options['tableurl'] ?? '',
            'chunk' => $options['chunk'] ?? false,
        ];
        $options = array_merge($op, $options);
        $label = $this->label($name, $options);
        $verify = $options['verify'] ?? "";
        $options['verify'] = '';
        $select_container = '';
        if ((isset($options['select']) && $options['select']) || !isset($options['select'])) {
            $select_options = $options;
            $select_options['filter'] = $options['select'] ?? 'upload-select'; //可选upload-choose
            $options['select'] = $select_options['filter'];
            $select_container = <<<EOF
<button id="select-{$id}" type="button" {$this->getOptionsAttr($name, $select_options)} class="layui-btn layui-btn-sm layui-btn-danger {$options['select']}"><i class="layui-icon layui-icon-radio"></i>{$this->__('Choose')}</button>
EOF;
        }

        if (!isset($options['filter'])) $options['filter'] = 'upload'; //监听
        $str = <<<EOF
<style>
.layui-input-upload{
{$css};
}
</style>
<div class="layui-form-item {$this->getClass($options,'outclass')}">{$label}
    <div class="layui-input-block">
        <div class="layui-upload">
            <input {$this->getNameValueAttr($name, $value, $options)} lay-verify="{$verify}"  type="text"  class="layui-input layui-input-upload attach {$this->getClass($options)}" />
           {$croper_container}
            <button id="upload-{$id}" type="button" {$this->getOptionsAttr($name, $options)} style="margin-left:0px" class="layui-btn layui-btn-sm layui-btn-normal "><i class="layui-icon layui-icon-upload-drag"></i>{$this->__('Uploads')}</button>
            {$select_container}
            <div class="layui-upload-list">{$li}
            </div>
        </div>
        {$this->tips($options)}
    </div>
</div>
EOF;

        return $str;
    }

    /**
     * @param bool $reset
     * @param array $options
     * @return string
     */
    public function closebtn($reset = true, $options = [])
    {
        $show = '';
        if (!isset($options['show']) || isset($options['hide'])) {
            $show = 'layui-hide';
        }
        $str = <<<EOF
<div class="layui-btn-center  {$show}">
        <button  {$this->getStyle($options)} type="close" class="layui-btn  {$this->getClass($options)} " onclick="parent.layui.layer.closeAll();">{$this->__('Close')}
    </button>
</div>
EOF;

        return $str;
    }


    /**
     * @param bool $reset
     * @param array $options
     * @return string
     */
    public function submitbtn($reset = true, $options = [])
    {
        $show = '';
        if (!isset($options['show']) || isset($options['hide'])) {
            $show = 'layui-hide';
        }
        if ($reset) {
            $reset = <<<EOF
<button type="reset" class="layui-btn  layui-btn-primary reset">{$this->__('Reset')}</button>
EOF;
        }
        $str = <<<EOF
        <input type="hidden" name="__token__" value="{$this->token()} ">
        <div class=" layui-btn-submit layui-btn-center {$show}" />
            <button type="submit" class="layui-btn layui-btn-normal submit " lay-fitler="submit" lay-submit>{$this->__('Submit')}
            </button>
            {$reset}
        </div>
EOF;

        return $str;
    }

    public function submit($reset = true, $options = [])
    {

        return $this->submitbtn($reset, $options);

    }

    /**额外的代码
     * @param $html
     * @return string
     */
    public function html($html, $options = [])
    {
        return $this->entities($html);
    }

    /**
     * script
     * @param $script
     * @return string
     */
    public function script(string $script, $options = [])
    {
        return $script;
    }

    /**
     * 样式
     * @param $style
     * @return mixed
     */
    public function style(string $style, $options = [])
    {
        return $style;
    }

    /**
     * JS
     * @param $name
     * @param $options
     * @return string
     */
    public function js($name = [], $options = [])
    {
        if (is_string($name)) {
            $name = explode(',', $name);
        }
        $str = '';
        $v = !empty($options['version']) ?$options['version']: (!empty($options['v'])?$options['v']:"");
        foreach ($name as $src) {
            $src = $v ? $src . '?v=' . $v : $src;
            $str .= '<script src="' . $src . '"></script>';
        }
        return $str;
    }

    /**
     * css
     * @param $name
     * @param $options
     * @return string
     */
    public function link($name = [], $options = [])
    {
        if (is_string($name)) {
            $name = explode(',', $name);
        }
        $str = '';
        $v = !empty($options['version']) ?$options['version']: (!empty($options['v'])?$options['v']:"");
        foreach ($name as $src) {
            $src = $v ? $src . '?v=' . $v : $src;
            $str .= '<link href="' . $src . '" />';
        }
        return $str;
    }

    /**
     * @param $label
     * @param $options
     * @return string
     */
    public function label($name, $options = [], $escape_html = true)
    {
        $label = $options['label'] ?? $name;
        if ($escape_html) {
            $label = $this->entities($label);
        }
        $class = '';
        if (isset($options['labelHide']) || isset($options['labelhide'])) {
            $class .= ' layui-hide';
        }
        $data = <<<EOF
<label class="layui-form-label {$this->labelRequire($options)}  {$class} "> {$this->getTitle($label)} </label>
EOF;
        if (isset($options['labelRemove']) || isset($options['labelremove'])) {
            $data = '';
        }
        return $data;
    }

    /**
     * 将HTML字符串转换为实体
     *
     * @param string $value
     *
     * @return string
     */
    protected function entities($value)
    {
        return htmlentities($value, ENT_QUOTES, 'UTF-8', false);
    }

    /**
     * 把 HTML 实体转换回字符
     * @param $value
     * @return string
     */
    protected function entity_decode($value)
    {
        return html_entity_decode($value);
    }

    /**
     * @param $options
     * @return string
     * 提示
     */
    protected function tips($options = [])
    {
        $tips = '';
        if (isset($options['tips'])) {
            $options['tips'] = $this->entities($options['tips']);
            $tips = <<<EOF
<div class="layui-form-mid layui-word-aux"> {$this->__($options['tips'])} </div>
EOF;
        }
        return $tips;
    }

    /**
     * @ 验证
     * @return string
     */
    protected function layverify($options = [])
    {
        $verify = '';
        if (isset($options['verify'])) {
            $verify .= 'data-verify="' . $options['verify'] . '" lay-verify="' . $options['verify'] . '"';
        }
        $type = 'tips';
        if (isset($options['verType']) && $options['verType']) {
            $type = $options['verType'];
        }else if (isset($options['vertype']) && $options['vertype']) {
            $type = $options['vertype'];
        }
        $verify .= ' lay-verType="' . $type . '" ';
        if (isset($options['reqText']) && $options['reqText']) {
            $verify .= ' lay-reqText="' . $options['reqText'] . '" ';
        }
        return $verify;
    }

    /** 过滤
     * @param $options
     * @return string
     */
    protected function layfilter($options = [])
    {
        $str = '';
        if (isset($options['filter'])) {
            $str = ' lay-filter="' . $options['filter'] . '"';
        }
        return $str;
    }

    protected function layaffix($options = [])
    {
        $str = '';
        if (isset($options['affix'])) {
            $str = ' lay-affix="' . $options['affix'] . '"';
        }
        return $str;
    }

    protected function layautocomplete($options = [])
    {
        $str = ' ';
        if (isset($options['autocomplete'])) {
            $str = ' autocomplete="' . $options['autocomplete'] . '"';
        }
        return $str;
    }

    protected function laysubmit($options = [])
    {
        $str = ' ';
        if (isset($options['submit'])) {
            $str = ' lay-submit="' . $options['submit'] . '"';
        }
        return $str;
    }

    protected function layignore($options = [])
    {
        $str = ' ';
        if (isset($options['ignore'])) {
            $str = ' lay-ignore="' . $options['ignore'] . '"';
        }
        return $str;
    }

    protected function laystep($options = [])
    {
        $str = ' ';
        if (isset($options['step'])) {
            $str = ' step="' . $options['step'] . '"';
        }
        return $str;
    }

    protected function layprecision($options = [])
    {
        $str = ' ';
        if (isset($options['precision'])) {
            $str = ' lay-precision="' . $options['precision'] . '"';
        }
        return $str;
    }
    /**搜索
     * @return string
     */
    protected function laysearch($options = [])
    {
        $str = '';
        if (!isset($options['search']) || $options['search'] == true) {
            $str = ' lay-search';
        }
        return $str;
    }

    protected function layskin($options = [])
    {
        $str = '';
        if (isset($options['skin'])) {
            $str = ' lay-skin="' . $options['skin'] . '"';
        }
        return $str;
    }

    /**
     * @param $ops
     * @param $val
     * @param int $type
     * @return string
     * 是否选中
     */
    protected function selectedOrchecked($select = [], $val = '', $type = 1)
    {
        if ($select == $val) {
            if ($type == 1) return 'selected';
            return 'checked';
        }
        return '';
    }

    protected function labelRequire($options = [])
    {
        if (isset($options['verify']) && ($options['verify'] == 'required' || strpos($options['verify'], 'required') !== false)) {
            return 'required';
        }
        return '';
    }

    protected function readonlyOrdisabled($options = [])
    {

        if (isset($options['readonly']) && $options['readonly']) {
            return 'readonly';
        }
        if (isset($options['disabled']) && $options['disabled']) {
            return 'disabled';
        }
        return '';
    }

    //自定义class属性
    protected function getClass($options = [],$className= 'class')
    {
        if (isset($options[$className]) && $options[$className]) {
            $classArr = is_array($options[$className]) ? $options[$className] : explode(',', $options[$className]);
            return ' ' . implode(' ', $classArr) . ' ';
        }
        return '';
    }

    protected function getStyle($options = [])
    {
        $options['style'] = '';
        if (!empty($options['style']) || !empty($options['css'])) {
            return ' style="' . $options['style'] . $options['style'] . '" ';
        }
        return ' ';
    }

    protected function getExtend($options = [])
    {
        if (is_array($options['extend'])) {
            $attr = ' ';
            foreach ($options['extend'] as $key => $value) {
                $attr .= $key . '="' . $value . '"';
            }
            return $attr;
        } else {
            return ' ' . $options['extend'] . ' ';
        }
    }

    /**
     * @param $name
     * @param $options
     * @return string
     */
    protected function getNameValueAttr($name = '', $value = '', $options = [])
    {
        list($name, $id) = $this->getNameId($name, $options);
        $value = $this->getValue($name, $value);
        $value = isset($value) ? 'data-value="'.$value.'" value="'.$value.'"':'';
        return <<<EOF
name="{$name}" {$value} id="{$id}"
EOF;
    }

    /**
     * @param $name
     * @param $options
     * @return string
     */
    public function getOptionsAttr($name = '', $options = [])
    {
        $attr = ' ';
        if (isset($options['extend']) && $options['extend']) {
            $attr .= $this->getExtend($options);
        } else {
            $options['id'] = $options['id'] ?? $name;
            $options['name'] = $options['formname'] ?? ($options['fromName'] ?? $name);
            $options['label'] = $options['label'] ?? $name;
            $options['tips'] = $options['tips'] ?? '';
            $options['filter'] = $options['filter'] ?? $name;
            foreach ($options as $key => $val) {
                switch ($key) {
                    case 'class':
                    case 'tips':
                    case 'css':
                    case 'label':
                        break;
                    case 'placeholder':
                        $attr .= $key . '="' . $this->__($val) . '" ';
                        break;
                    case 'verify':
                        $attr .= $this->layverify($options);
                        break;
                    case 'filter':
                        $attr .= $this->layfilter($options);
                        break;
                    case 'step':
                        $attr .= $this->laystep($options);
                        break;
                    case 'affix':
                        $attr .= $this->layaffix($options);
                        break;
                    case 'autocomplete':
                        $attr .= $this->layautocomplete($options);
                        break;
                    case 'submit':
                        $attr .= $this->laysubmit($options);
                        break;
                    case 'ignore':
                        $attr .= $this->layignore($options);
                        break;
                    case 'precision':
                        $attr .= $this->layprecision($options);
                        break;
                    case 'style':
                        $attr .= $this->getStyle($options);
                    case 'readonly':
                    case 'disabled':
                        $attr .= $this->readonlyOrdisabled($options);
                        break;
                    case 'search':
                        $attr .= $this->laysearch($options);
                        break;
                    case 'skin':
                        $attr .= $this->layskin($options);
                        break;
                    case 'value':
                        $attr .= $key . "='" . $this->entities($val) . "' data-" . $key . "='" . $this->entities($val) . "' ";
                        break;
                    case 'attr':
                        if (is_object($val) || is_array($val)) {
                            $val = (array)$val;
                            $val = implode(',', $val);
                        }
                        $attr .= " data-" . $key . "='" . $val . "' ";
                        break;
                    default:
                        if (is_object($val) || is_array($val)) {
                            $val = (array)$val;
                            $val = $val[$name] ?? json_encode($val, JSON_UNESCAPED_UNICODE);
                        }
                        $attr .= " data-" . $key . "='" . $val . "' ";
                        break;

                }
            }
        }
        return $attr;
    }

    /**
     * 获取data属性
     * @param $name
     * @param $value
     * @param $options
     * @return string
     */
    protected function getDataPropAttr($name = '', $value = '', $options = [])
    {
        $str = $this->getNameValueAttr($name, $value, $options);
        $str .= $this->getOptionsAttr($name, $options);
        return $str;
    }

    /**
     * 获取值
     * @param $name
     * @param $value
     * @return string
     */
    protected function getValue($name, $value)
    {

        if (is_object($value) || is_array($value)) {
            $value = (array)$value;
            $value = $value[$name] ?? implode(',', $value);
        }
        $value = !is_null($value) ? $value : '';
        return $this->entities($value);

    }

    /**
     * 获取name和id;
     * @param $name
     * @param $options
     * @return array
     */
    protected function getNameId($name = '', $options = [])
    {
        $name = $options['formname'] ?? $name;
        $id = $options['id'] ?? $name;
        return [$name, $id];
    }

    /**
     *获取数组
     * @param $name
     * @param $value
     * @return array|false|mixed|string[]
     */
    protected function getArray($name = '', $value = [])
    {

        if (is_string($value) && strpos($value, "\n") !== false) return explode("\n", $value);
        if (is_string($value) && strpos($value, ",") !== false) return explode(",", $value);
        if (is_string($value) && strpos($value, "|") !== false) return explode("|", $value);
        return $value;
    }

    /**
     * 翻译
     * @param $string
     * @return float|int|mixed|string
     */
    protected function __($string = '')
    {
        return lang($this->entity_decode($string));
    }

    protected function getTitle($string)
    {
        return $this->__(Str::title($string));
    }

}
