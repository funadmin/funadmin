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

namespace fun\builder;


use fun\Form;
use think\facade\View;
use think\helper\Str;

class FormBuilder
{
    protected $modelClass = '';
    /**
     * style
     * @var array
     */
    protected $style = [];
    /**
     * css
     * @var array
     */
    protected $link = [];
    /**
     * js
     * @var array
     */
    protected $js = [];
    /**
     * script
     * @var array
     */
    protected $script = [];
    /**
     * 表单html
     * @var array
     */
    protected $formHtml = [];

    protected $extraJs = '';

    /**
     * 模板
     * @var string
     */
    protected $template = '';
    /**
     * @var
     */
    private static $instance;
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
    protected function __construct($options=[])
    {
        $this->template = $options['template']??'../../../extend/fun/builder/layout/add';
        $this->modelClass = $this->modelClass?: ($config['model'] ?? ($config['modelClass'] ?? ''));

    }

    /**
     * 私有化clone函数
     */
    private function __clone()
    {
        // TODO: Implement __clone() method.
    }

    /**
     * @param $name
     * @param $options
     * @param $value
     * @return $this
     */
    public  function config($name='',$options=[],$value=''): static
    {
        $this->formHtml[] = Form::config($name,$options,$value);
        return $this;
    }
    /**
     * @param $name
     * @param $type
     * @return $this
     */
    public  function token($name = '__token__', $type = 'md5'): static
    {
        $this->formHtml[] = Form::token($name = '__token__', $type = 'md5');
        return $this;
    }

    /**
     * 生成文本框(按类型) password .text
     * @param string $name
     * @param string $type
     * @param array $options
     * @return $this
     */
    public  function input(string $name = '', string $type = 'text',array $options = [], $value = ''): static
    {
        $this->formHtml[] = Form::input($name, $type,$options,$value);
        return $this;
    }
    /**
     * @param $name
     * @param $list
     * @param $options
     * @param $attr
     * @param $value
     * @return $this
     */
    public  function autocomplete(string $name = '', array $list = [],array $options = [],$attr= [], $value = '')
    {
        $this->formHtml[] = Form::autocomplete($name, $list,$options,$attr,$value);
        return $this;
    }

    /**
     * @param string $name
     * @param array $options
     * @param  $value
     * @return $this
     */
    public  function text(string $name,array $options = [], $value = null)
    {
        $this->formHtml[] = Form::input($name, 'text',$options,$value);
        return $this;
    }

    /**
     * 创建一个密码输入字段
     *
     * @param  string  $name
     * @param  array   $options
     * @return $this
     */
    public  function password(string $name, array $options = [],$value=''): static
    {
        $this->formHtml[] = Form::input($name, 'password', $options,$value);
        return $this;
    }

    /**
     * 创建一个范围输入选择器
     *
     * @param  string  $name
     * @param  array   $options
     * @param  mixed    $value
     * @return $this
     */
    public  function range($name, $options = [], $value = null): static
    {
        $this->formHtml[] = Form::input($name,$options,$value);
        return $this;
    }

    /**
     * 创建一个隐藏的输入字段
     *
     * @param  string  $name
     * @param  mixed    $value
     * @param  array   $options
     *
     * @return $this
     */
    public  function hidden($name,  $options = [],$value = null): static
    {
        $this->formHtml[] = Form::input($name,'hidden',$options,$value);
        return $this;
    }

    /**
     * 创建一个电子邮件输入字段
     *
     * @param  string  $name
     * @param  mixed    $value
     * @param  array   $options
     * @return $this
     */
    public  function email($name,  $options = [],$value = null): static
    {
        $this->formHtml[] = Form::input($name,'email',$options,$value);
        return $this;
    }

    /**
     * 创建一个tel输入字段
     *
     * @param  string  $name
     * @param  mixed    $value
     * @param  array   $options
     *
     * @return string
     */
    public  function tel($name,  $options = [],$value = null): static
    {
        $this->formHtml[] = Form::input($name,'tel',$options,$value);
        return $this;
    }

    /**
     * 创建一个数字输入字段
     *
     * @param  string  $name
     * @param  mixed    $value
     * @param  array   $options
     *
     * @return string
     */
    public  function number($name,  $options = [],$value = null): static
    {
        $this->formHtml[] = Form::input($name,'number',$options,$value);
        return $this;
    }

    /**
     * 创建一个url输入字段
     *
     * @param  string  $name
     * @param  mixed    $value
     * @param  array   $options
     *
     * @return string
     */
    public  function url($name,  $options = [],$value = null): static
    {
        $this->formHtml[] = Form::input($name,'url',$options,$value);
        return $this;
    }

    /**
     * 评分
     * @param $name
     * @param $options
     * @param $value
     * @return $this
     */
    public  function rate($name = '', $options = [], $value = ''): static
    {
        $this->formHtml[] = Form::rate($name,$options,$value);
        return $this;
    }
    /**
     * 滑块
     * @param $name
     * @param $options
     * @param $value
     * @return $this
     */
    public  function slider($name = '', $options = [], $value = ''): static
    {
        $this->formHtml[] = Form::slider($name,$options,$value);
        return $this;
    }
    /**
     * @param $name
     * @param $radiolist
     * @param array $options
     * @param string $value
     * @return $this
     */
    public  function radio($name = '', $radiolist=[], $options = [], $value = ''): static
    {
        $this->formHtml[] = Form::radio($name,$radiolist,$options,$value);
        return $this;
    }

    /**
     * 生成开关
     * @param $name
     * @param $value
     * @param array $options
     * @return $this
     * switch是关键字不能用
     */

    public  function switchs($name = '', $switch=[], $options = [], $value = ''): static
    {
        $this->formHtml[] = Form::switchs($name,$switch,$options,$value);
        return $this;
    }

    /**
     * 多选
     * @param string $name
     * @param array $list
     * @param array $options
     * @param $value
     * @return self
     */
    public  function checkbox($name = '', $list = [], $options = [], $value = ''): static
    {
        $this->formHtml[] = Form::checkbox($name,$list,$options,$value);
        return $this;
    }

    /**
     * 数组表单
     * @param string $name
     * @param array $options
     * @param array $list
     * @return self
     */
    public  function arrays($name = '', $list = [], $options = []): static
    {
        $this->formHtml[] = Form::arrays($name,$list,$options);
        return $this;
    }

    /**
     * 文本
     * @param string $name
     * @param array $options
     * @param $value
     * @return $this
     */
    public  function textarea($name = '', $options = [], $value = ''): static
    {
        $this->formHtml[] = Form::textarea($name,$options,$value);
        return $this;
    }

    /**
     * @param $name
     * @param $select
     * @param $options
     * @param $attr
     * @param $value
     * @return $this
     */
    public  function selectcx($name = '', $select= [], $options=[], $attr=[], $value=''): static
    {
        $this->formHtml[] = Form::selectcx($name,$select,$options,$attr,$value);
        return $this;
    }
    /**
     * @param $name
     * @param $select
     * @param $options
     * @param $attr
     * @param $value
     * @return $this
     */
    public  function multiselect($name = '', $select=[], $options=[], $attr=[], $value=''): static
    {
        $this->formHtml[] = Form::selects($name,$select,$options,$attr,$value);
        return $this;
    }

    /**
     * @param $name
     * @param $select
     * @param $options
     * @param $attr
     * @param $value
     * @return $this
     */
    public  function selects($name = '', $select=[], $options=[], $attr=[], $value=''): static
    {
        $this->formHtml[] = Form::selects($name,$select,$options,$attr,$value);
        return $this;
    }
    /**
     * @param $name
     * @param $select
     * @param $options
     * @param $attr
     * @param $value
     * @return $this
     */
    public  function xmselect($name = '', $select=[], $options=[], $attr=[], $value=''): static 
    {
        $this->formHtml[] = Form::xmselect($name,$select,$options,$attr,$value);
        return $this;
    }

    /**
     * 创建动态下拉列表字段
     * @param $name
     * @param $options
     * @param $value
     * @return $this
     */
    public  function selectpage(string $name,array $lists= [],array $options = [],$value=null): static
    {
        $this->formHtml[] = Form::selectpage($name,$lists,$options,$value);
        return $this;
    }
    /**
     * @param $name
     * @param $value
     * @param array $options
     * @return $this
     * tag
     */
    public function tags($name = '', $options = [], $value = ''): static
    {
        $this->formHtml[] = Form::tags($name,$options,$value);
        return $this;
    }

    /**
     * @param $name
     * @param $value
     * @param array $options
     * @return $this
     * 颜色选择
     */
    public  function color($name = '', $options = [], $value = ''): static
    {
        $this->formHtml[] = Form::color($name,$options,$value);
        return $this;
    }

    /**
     * @param $name
     * @param $value
     * @param array $options
     * @return $this
     * 图标，有点小问题
     */
    public  function icon($name = '', $options = [], $value = ''): static
    {
        $this->formHtml[] = Form::icon($name,$options,$value);
        return $this;
    }

    /**
     * @param null $name
     * @param array $options
     * @return $this
     * 日期
     */
    public  function date($name='', $options=[], $value=''): static
    {
        $this->formHtml[] = Form::date($name,$options,$value);
        return $this;
    }
    /**
     * 城市选择
     * @param string $name
     * @param $options
     * @return $this
     */
    public  function city($name = 'cityPicker', $options = [],$value=''): static
    {
        $this->formHtml[] = Form::city($name,$options,$value);
        return $this;
    }

    /**
     * @param string $name
     * @param $id
     * @param int $type
     * @param array $options
     * @return $this
     * 编辑器
     */
    public  function editor($name = 'container', $options = [], $value = ''): static
    {
        $this->formHtml[] = Form::editor($name,$options,$value);
        return $this;
    }

    /**
     * @param string $name
     * @param $id
     * @param int $type
     * @param array $options
     * @return $this
     * json
     */
    public  function json($name = 'json', $options = [], $value = ''): static
    {
        $this->formHtml[] = Form::json($name,$options,$value);
        return $this;
    }

    /**
     * @param string $name
     * @param $id
     * @param int $type
     * @param array $options
     * @return $this
     * json
     */
    public  function transfer($name = 'transfer',$select=[], $options = [], $value = ''): static
    {
        $this->formHtml[] = Form::transfer($name,$select,$options,$value);
        return $this;
    }
    /**
     * 上传
     * @param string $name
     * @param string $formData
     * @param array $options
     * @return $this
     */
    public  function upload($name = 'avatar', $options = [], $value = ''): static
    {
        $this->formHtml[] = Form::upload($name,$options,$value);
        return $this;
    }
    /**
     * @param bool $reset
     * @param array $options
     * @return $this
     */
    public  function closebtn($reset = true, $options = []): static
    {
        $this->formHtml[] = Form::closebtn($reset,$options);
        return $this;
    }

    /**
     * @param bool $reset
     * @param array $options
     * @return $this
     */
    public  function close($reset = true, $options = []): static
    {
        $this->formHtml[] = Form::closebtn($reset,$options);
        return $this;
    }
    /**
     * @param bool $reset
     * @param array $options
     * @return $this
     */
    public  function submitbtn($reset=true, $options=[]): static
    {
        $this->formHtml[] = Form::submitbtn($reset,$options);
        return $this;
    }

    public function submit($reset=true, $options=[]): static
    {
        $this->formHtml[] = Form::submitbtn($reset,$options);
        return $this;
    }

    /**
     * @param $script
     * @return void
     */
    public function js($name=[],$options=[]): static
    {
        if($options['merge']){
            $this->formHtml[] = Form::js($name,$options);
        }else{
            $this->js[] = Form::js($name,$options);
        }
        return $this;
    }
    public function link($name=[],$options=[]): static
    {
        if($options['merge']){
            $this->formHtml[] = Form::link($name,$options);
        }else{
            $this->link[] = Form::link($name,$options);
        }

        return $this;
    }
    public function script(string $name,$options=[]): static
    {
        if($options['merge']){
            $this->formHtml[]= Form::script($name,$options);
        }else{
            $this->script[] = Form::script($name,$options);
        }
        return $this;
    }

    /**
     * js 内js
     * @param $js
     * @return $this
     */
    public function extrajs($js,$options=[]): static
    {
        $reg = '/<script.*?>([\s\S]*?)<\/script>/im';
        preg_match($reg, $js,$match);
        $this->extraJs = empty($match)?$js:$match[1];
        return $this;
    }
    public function style(string $name,$options=[]): static
    {
        if($options['merge']){
            $this->formHtml[] = Form::style($name,$options);
        }else{
            $this->style[] = Form::style($name,$options);
        }
        return $this;
    }
    /**
     * 渲染视图
     * @return string
     */
    public function assign($data=[]): static
    {
        $form = $this;
        View::assign(array_merge([
            'formBuilder'=>$form,
            'formStyle'=>implode('',$this->style),
            'formLink'=>implode('',$this->link),
            'formScript'=>implode('',$this->script),
            'formJs'=>implode('',$this->js),
            'formHtml'=>implode('',$this->formHtml),
            'extraJs'=>implode('',$this->extraJs),
        ],$data));
        return $this;
    }

    /**
     * 渲染视图
     * @param string $template
     * @return \think\response\View
     */
    public function view(string $template=''): \think\response\View
    {

        $template = $template?:$this->template;
        return view($template);
    }

    public function extraHtml($html,$position='bottom'): static
    {
        if($position=='bottom'){
            $this->formHtml[] = $html;
        }else{
            array_unshift($this->formHtml,$html);
        }
        return $this;
    }
    /**
     * 额外的代码
     * @param $html
     * @return $this
     */
    public function html($html,$options=[]): static
    {
        $this->formHtml[] = Form::html($html,$options);
        return $this;
    }

    /**
     *
     * @param $formValue
     * @return $this
     */
    public function formValue($formValue=[]){
        $formValue = json_encode($formValue);
        foreach ($formValue as $k=>$item) {
            if(empty($item)){
                unset($formValue[$k]);
            }
        }
        $this->formHtml[] = <<<EOF
        <script>
            layui.form.val("form", {$formValue});
            layui.form.render();
        </script>;
EOF;
        return $this;
    }
}
