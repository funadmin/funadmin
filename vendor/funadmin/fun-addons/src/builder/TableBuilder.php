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
 * Date: 2023/7/22
 */

namespace fun\builder;

use fun\Form;
use think\facade\Config;
use think\facade\Db;
use think\facade\View;

class TableBuilder
{
    private static $instance;
    protected $modelClass = '';
    protected $driver = 'mysql';
    protected $database = 'funadmin';
    protected $tablePrefix = 'fun_';
    public $fields = [];
    public $node = [];
    public $methods = [];
    public $script = '';
    public $extraJs = '';
    public $js = '';
    public $style = '';
    public $link = '';
    public $html = '';
    public $requests = [
        'index_url' => 'index',
        'add_url' => 'add',
        'edit_url' => 'edit',
        'delete_url' => 'delete',
        'destroy_url' => 'destroy',
        'modify_url' => 'modify',
        'preview_url' => 'preview',
        'copy_url' => 'copy',
        'export_url' => 'export',
        'import_url' => 'import',
        'recycle_url' => 'recycle',
    ];
    public  $index = [
        'elem' => 'list',
        'id' => 'list',
        'init' => '',
        'url' => 'index',
        'defaultToolbar' => ['filter', 'print', 'exports'],
        'primaryKey' => 'id',
        'page' => true,
        'limits'=>[15, 30, 50, 100, 200, 500, 1000, 5000, 10000],
        'limit'=> 15,
        'searchInput' => true,
        'searchShow' => false,
        'searchTpl' => '',
        'lineStyle' => '',
        'rowDouble' => true,
        'cols' => [
            []
        ],
        'toolbar' => ['refresh', 'add', 'destroy', 'import', 'export', 'recycle'],
        'operat' => ['edit', 'delete'],
    ];
    public $recycle = [
        'elem' => 'list',
        'id' => 'list',
        'url' => 'recycle',
        'defaultToolbar' => ['filter', 'print', 'exports'],
        'toolbar' => ['refresh', 'delete', 'restore'],
        'primaryKey' => 'id',
        'page' => true,
        'limits'=>[15, 30, 50, 100, 200, 500, 1000, 5000, 10000],
        'limit'=> 15,
        'searchInput' => true,
        'searchShow' => false,
        'searchTpl' => '',
        'lineStyle' => '',
        'rowDouble' => true,
        'cols' => [
            []
        ],
        'operat' => ['restore', 'delete'],

    ];
    public $options = [];
    /**
     * @var
     */

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
     * 私有化clone函数
     */
    private function __clone()
    {
        // TODO: Implement __clone() method.
    }

    /**
     * 私有化构造函数
     */
    private function __construct(array $config = [])
    {
        $this->fields = $config['fields'] ?? [];
        // 初始化
        $this->template = $config['template'] ?? '../../../vendor/funadmin/fun-addons/src/builder/layout/table';
        $this->modelClass = $config['model'] ?? ($config['modelClass'] ?? '');
        $this->driver = $config['driver'] ?? 'mysql';
        $this->tablePrefix = config('database.connections.' . $this->driver . '.prefix');
        $this->database = Config::get('database.connections' . '.' . $this->driver . '.database');
        foreach ($this->requests as &$request) {
                        $request = __u($request);
                }
        unset($request);
    }
    // 表格options
    public function options(array $data=[], string $tableId = 'list'){
        if(request()->action()=='index' && empty($data)) {
            $this->index['url'] = __u(request()->action());
            $this->options[$tableId] = $this->index;
        }elseif(request()->action()=='recycle' && empty($data)){
            $this->index['url'] = __u(request()->action());
            $this->options[$tableId] = $this->recycle;
        }else{
            $this->options[$tableId] = $data;
        }
        return $this;
    }
    public function node(array $node = [])
    {
        foreach ($node as $item) {
            $this->node[] = ['data-node-' . $item => __u($item)];
        }
        return $this;
    }

    public function url(string|array|object $url, string $tableId = 'list')
    {
        $this->options[$tableId]['url'] = $url;
        return $this;
    }

    public function data(array $data=[], string $tableId = 'list')
    {
        $this->options[$tableId]['data'] = $data;
        return $this;
    }

    public function searchShow(bool $show = false, string $tableId = 'list')
    {
        $this->options[$tableId]['searchShow'] = $show;
        return $this;
    }

    public function searchTpl(string $tpl = '', string $tableId = 'list')
    {
        $this->options[$tableId]['searchTpl'] = $tpl;
        return $this;
    }

    public function rowDouble(bool $rowDouble = true, string $tableId = 'list')
    {
        $this->options[$tableId]['rowDouble'] = $rowDouble;
        return $this;
    }

    public function searchInput($show = true, string $tableId = 'list')
    {
        $this->options[$tableId]['searchInput'] = $show;
        return $this;
    }

    /**
     * @param $column
     * @return $this
     */
    public function col(array $column = [], string $tableId = 'list')
    {
        if(!$this->options[$tableId]){
            $this->options();
        }
        array_push($this->options[$tableId]['cols'][0], $column);
        return $this;
    }

    /**
     * @param $columns
     * @return $this
     */
    public function cols($columns = [], string $tableId = 'list')
    {
        if(!$this->options[$tableId]){
            $this->options();
        }
        if (!empty($columns)) {
            foreach ($columns as $column) {
                call_user_func_array([$this, 'col'], [$column, $tableId]);
            }
        }
        return $this;
    }

    public function width(string $width, string $tableId = 'list')
    {
        $this->options[$tableId]['width'] = $width;
        return $this;
    }

    public function height(string $height, string $tableId = 'list')
    {
        $this->options[$tableId]['height'] = $height;
        return $this;

    }

    public function cellMinWidth(string $cellMinWidth, string $tableId = 'list')
    {
        $this->options[$tableId]['cellMinWidth'] = $cellMinWidth;
        return $this;

    }

    public function lineStyle(string $lineStyle, string $tableId = 'list')
    {
        $this->options[$tableId]['lineStyle'] = $lineStyle;
        return $this;

    }

    public function className(string $className, string $tableId = 'list')
    {
        $this->options[$tableId]['className'] = $className;
        return $this;
    }

    public function css(string $css, string $tableId = 'list')
    {
        $this->options[$tableId]['css'] = $css;
        return $this;
    }

    public function escape(bool $escape, string $tableId = 'list')
    {
        $this->options[$tableId]['escape'] = $escape;
        return $this;
    }

    public function totalRow(string $totalRow, string $tableId = 'list')
    {
        $this->options[$tableId]['totalRow'] = $totalRow;
        return $this;
    }

    public function page(bool $page = true, string $tableId = 'list')
    {
        $this->options[$tableId]['page'] = $page;
        return $this;
    }

    public function pagebar(string $pagebar, string $tableId = 'list')
    {
        $this->options[$tableId]['pagebar'] = $pagebar;
        return $this;
    }

    public function limit(int $limit, string $tableId = 'list')
    {
        $this->options[$tableId]['limit'] = $limit;
        return $this;
    }

    public function limits(array $limits = [], string $tableId = 'list')
    {
        $this->options[$tableId]['limits'] = $limits;
        return $this;
    }

    public function loading(bool $loading, string $tableId = 'list')
    {
        $this->options[$tableId]['loading'] = $loading;
        return $this;
    }

    public function scrollPos(string $scrollPos, string $tableId = 'list')
    {
        //fixed 重载数据时，保持滚动条位置不变reset 重载数据时，滚动条位置恢复置顶default 默认方式，无需设置。即重载数据或切换分页
        $this->options[$tableId]['scrollPos'] = $scrollPos;
        return $this;
    }

    /**
     * dblclick|click
     * @param string $editTrigger
     * @param string $tableId
     * @return $this
     */
    public function editTrigger(string $editTrigger, string $tableId = 'list')
    {
        $this->options[$tableId]['editTrigger'] = $editTrigger;
        return $this;
    }

    public function title(string $title, string $tableId = 'list')
    {
        $this->options[$tableId]['title'] = $title;
        return $this;
    }

    public function text(array $text, string $tableId = 'list')
    {
        $this->options[$tableId]['text'] = $text;
        return $this;
    }

    public function autoSort(bool $autoSort, string $tableId = 'list')
    {
        $this->options[$tableId]['autoSort'] = $autoSort;
        return $this;
    }

    public function initSort(array $initSort, string $tableId = 'list')
    {
        $this->options[$tableId]['initSort'] = $initSort;
        return $this;
    }

    public function skin(string $skin, string $tableId = 'list')
    {
        $this->options[$tableId]['skin'] = $skin;//grid|line|row|nob
        return $this;
    }

    public function size(string $size, string $tableId = 'list')
    {
        $this->options[$tableId]['size'] = $size;//sm|md|lg
        return $this;
    }

    public function even(string $even, string $tableId = 'list')
    {
        $this->options[$tableId]['even'] = $even;
        return $this;
    }

    public function before(string $before, string $tableId = 'list')
    {
        $this->options[$tableId]['before'] = $before;
        return $this;
    }

    public function done(mixed $done, string $tableId = 'list')
    {
        $this->options[$tableId]['done'] = $done;
        return $this;
    }

    public function error(mixed $error, string $tableId = 'list')
    {
        $this->options[$tableId]['error'] = $error;
        return $this;
    }

    public function method(string $method = 'GET', string $tableId = 'list')
    {
        $this->options[$tableId]['method'] = $method;
        return $this;
    }

    public function where(mixed $where, string $tableId = 'list')
    {
        $this->options[$tableId]['where'] = $where;
        return $this;
    }

    public function headers(mixed $headers, string $tableId = 'list')
    {
        $this->options[$tableId]['headers'] = $headers;
        return $this;
    }

    public function contentType(mixed $contentType, string $tableId = 'list')
    {
        $this->options[$tableId]['contentType'] = $contentType;
        return $this;
    }

    public function dataType(mixed $dataType, string $tableId = 'list')
    {
        $this->options[$tableId]['dataType'] = $dataType;
        return $this;
    }

    public function request(mixed $request, string $tableId = 'list')
    {
        $this->options[$tableId]['request'] = $request;
        return $this;
    }

    public function parseData(mixed $parseData, string $tableId = 'list')
    {
        $this->options[$tableId]['parseData'] = $parseData;
        return $this;
    }

    public function cellMaxWidth(string $cellMaxWidth, string $tableId = 'list')
    {
        $this->options[$tableId]['cellMaxWidth'] = $cellMaxWidth;
    }

    public function maxHeight(string $maxHeight, string $tableId = 'list')
    {
        $this->options[$tableId]['maxHeight '] = $maxHeight;
    }


    /**
     * 设置表格主键
     * @param string $key 主键名称
     * @return $this
     */
    public function primaryKey($key = 'id', string $tableId = 'list')
    {
        $this->options[$tableId]['primaryKey'] = $key;
        return $this;
    }

    public function pageSize($pageSize = [], string $tableId = 'list')
    {

        $this->options[$tableId]['pageSize'] = !empty($pageSize) ? $pageSize : [];
        return $this;
    }

    /**
     * 额外的参数
     * @param $data
     * @return $this
     */
    public function extra($data = [], string $tableId = 'list')
    {
        $this->options[$tableId] = array_merge($this->options[$tableId], $data);
        return $this;
    }

    public function tree($data = [], string $tableId = 'list')
    {
        if (!empty($data)) {
            $this->options[$tableId]['tree'] = $data;
        }
        return $this;
    }

    public function style($style = '', string $tableId = 'list')
    {
        $this->style = $style;
        return $this;
    }
    public function link(string|array $link = '', string $tableId = 'list')
    {
        $this->link = Form::link($link,[]);
        return $this;
    }
    public function js(string|array$js = '', string $tableId = 'list')
    {
        $this->js = Form::js($js,[]);
        return $this;
    }
    public function script(string$script = '', string $tableId = 'list')
    {
        $this->script = $script;
        return $this;
    }
    public function extraJs(string$script = '', string $tableId = 'list')
    {
        $reg = '/<script.*?>([\s\S]*?)<\/script>/im';
        preg_match($reg, $script,$match);
        $this->extraJs = empty($match)?$script:$match[1];
        return $this;
    }
    /**
     * 设置额外HTML代码
     * @param string $html 额外HTML代码
     * @return $this
     */
    public function html($html = '', string $tableId = 'list')
    {
        $this->html = $html;
        return $this;
    }

    public function index(array $options = [], string $tableId = 'list')
    {
        $this->options[$tableId] = array_merge($this->options[$tableId], $options);
        return $this;
    }

    public function recycle(array $options = [], string $tableId = 'recycle')
    {
        if ($options == false) {
            foreach ($this->options as $key => $value) {
                if (!empty($value['toolbar']['recycle'])) {
                    unset($this->options[$key]['toolbar']['recycle']);
                }
                unset($this->options['recycle']);
            }
        }
        $this->options[$tableId] = array_merge($this->options[$tableId], $options);
        return $this;
    }

    /**
     * @param array $request
     * @return $this
     */
    public function requests(array $request = [], string $tableId = 'list')
    {
        $this->options[$tableId]['requests'] = $request;
        $this->requests = array_merge($this->requests, $request);
        return $this;
    }

    /**
     * 添加一个右侧按钮
     * @param array $attribute 按钮属性
     * @param array $extra 扩展参数(待用)
     * @return $this
     */
    public function operat(array $operat = [], string $tableId = 'list')
    {
        array_push($this->options[$tableId]['cols'][0], $operat);
        return $this;
    }

    public function elem(string $elem,string $tableId = 'list')
    {
        $this->options[$tableId]['elem'] = $elem;
        return $this;
    }

    public function id(string $id, string $tableId = 'list')
    {
        $this->options[$tableId]['id'] = $id;
        return $this;
    }

    public function defaultToolbar(array $default = ['filter', 'print', 'exports'], string $tableId = 'list')
    {

        $this->options[$tableId]['defaultToolbar'] = $default;
        return $this;

    }

    public function toolbar($buttons = [], string $tableId = 'list')
    {
        $this->options[$tableId]['toolbar'] = $buttons;
        return $this;
    }

    /**分配变量
     * @param $data
     * @return $this
     */
    public function assign(array $data = [])
    {
        View::assign([
            'node' => implode(' ', $this->node),
            'options' => $this->options,
            'requests' => $this->requests,
            'html' => $this->html,
            'tableScript' => $this->script,
            'extraJs' => $this->extraJs,
            'tableStyle' => $this->style,
            'tableLink' => $this->link,
            'data' => $data,
        ]);
        return $this;
    }

    /**
     * 渲染视图
     * @param string $template
     * @return \think\response\View
     */
    public function view(string $template = '')
    {
        $template = $template ?: $this->template;
        return view($template);
    }
}
