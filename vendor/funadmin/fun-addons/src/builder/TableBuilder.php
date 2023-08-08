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
    protected $fields = [];
    protected $node = [];
    protected $methods = [];
    protected $script = '';
    protected $extraScript = '';
    protected $style = '';
    protected $extraStyle = '';
    protected $html = '';
    protected $requests = [
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
    protected $options = [
        'index' => [
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
            'operat' => ['restore', 'delete'],
        ],
        'recycle' => [
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

        ],
    ];
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
    private function __construct(array $options = [])
    {
        $this->fields = $options['fields'] ?? [];
        // 初始化
        $this->template = $options['template'] ?? '../../../vendor/funadmin/fun-addons/src/builder/layout/table';
        $this->options['index']['url'] = __u('index');
        $this->options['recycle']['url'] = __u('recycle');
        $this->modelClass = $options['model'] ?? ($options['modelClass'] ?? '');
        $this->driver = $options['driver'] ?? 'mysql';
        $this->tablePrefix = config('database.connections.' . $this->driver . '.prefix');
        $this->database = Config::get('database.connections' . '.' . $this->driver . '.database');
    }

    public function node(array $node = [])
    {
        foreach ($node as $item) {
            $this->node[] = ['data-node-' . $item => __u($item)];
        }
        return $this;
    }

    public function url(string|array|object $url, $action = 'index')
    {
        $this->options[$action]['url'] = $url;
        return $this;
    }

    public function data(array $data = [], string $action = 'index')
    {
        $this->options[$action]['data'] = $data;
        return $this;
    }

    public function searchShow(bool $show = false, string $action = 'index')
    {
        $this->options[$action]['searchShow'] = $show;
        return $this;
    }

    public function searchTpl(string $tpl = '', string $action = 'index')
    {
        $this->options[$action]['searchTpl'] = $tpl;
        return $this;
    }

    public function rowDouble(bool $rowDouble = true, string $action = 'index')
    {
        $this->options[$action]['rowDouble'] = $rowDouble;
        return $this;
    }

    public function searchInput($show = true, string $action = 'index')
    {
        $this->options[$action]['searchInput'] = $show;
        return $this;
    }

    /**
     * @param $column
     * @return $this
     */
    public function col(array $column = [], string $action = 'index')
    {
        array_push($this->options[$action]['cols'][0], $column);
        return $this;
    }

    /**
     * @param $columns
     * @return $this
     */
    public function cols($columns = [], string $action = 'index')
    {
        if (!empty($columns)) {
            foreach ($columns as $column) {
                call_user_func_array([$this, 'col'], [$column, $action]);
            }
        }
        return $this;
    }

    public function width(string $width, string $action = 'index')
    {
        $this->options[$action]['width'] = $width;
        return $this;
    }

    public function height(string $height, string $action = 'index')
    {
        $this->options[$action]['height'] = $height;
        return $this;

    }

    public function cellMinWidth(string $cellMinWidth, string $action = 'index')
    {
        $this->options[$action]['cellMinWidth'] = $cellMinWidth;
        return $this;

    }

    public function lineStyle(string $lineStyle, string $action = 'index')
    {
        $this->options[$action]['lineStyle'] = $lineStyle;
        return $this;

    }

    public function className(string $className, string $action = 'index')
    {
        $this->options[$action]['className'] = $className;
        return $this;
    }

    public function css(string $css, string $action = 'index')
    {
        $this->options[$action]['css'] = $css;
        return $this;
    }

    public function escape(bool $escape, string $action = 'index')
    {
        $this->options[$action]['escape'] = $escape;
        return $this;
    }

    public function totalRow(string $totalRow, string $action = 'index')
    {
        $this->options[$action]['totalRow'] = $totalRow;
        return $this;
    }

    public function page(bool $page = true, string $action = 'index')
    {
        $this->options[$action]['page'] = $page;
        return $this;
    }

    public function pagebar(string $pagebar, string $action = 'index')
    {
        $this->options[$action]['pagebar'] = $pagebar;
        return $this;
    }

    public function limit(int $limit, string $action = 'index')
    {
        $this->options[$action]['limit'] = $limit;
        return $this;
    }

    public function limits(array $limits = [], string $action = 'index')
    {
        $this->options[$action]['limits'] = $limits;
        return $this;
    }

    public function loading(bool $loading, string $action = 'index')
    {
        $this->options[$action]['loading'] = $loading;
        return $this;
    }

    public function scrollPos(string $scrollPos, string $action = 'index')
    {
        //fixed 重载数据时，保持滚动条位置不变reset 重载数据时，滚动条位置恢复置顶default 默认方式，无需设置。即重载数据或切换分页
        $this->options[$action]['scrollPos'] = $scrollPos;
        return $this;
    }

    /**
     * dblclick|click
     * @param string $editTrigger
     * @param string $action
     * @return $this
     */
    public function editTrigger(string $editTrigger, string $action = 'index')
    {
        $this->options[$action]['editTrigger'] = $editTrigger;
        return $this;
    }

    public function title(string $title, string $action = 'index')
    {
        $this->options[$action]['title'] = $title;
        return $this;
    }

    public function text(array $text, string $action = 'index')
    {
        $this->options[$action]['text'] = $text;
        return $this;
    }

    public function autoSort(bool $autoSort, string $action = 'index')
    {
        $this->options[$action]['autoSort'] = $autoSort;
        return $this;
    }

    public function initSort(array $initSort, string $action = 'index')
    {
        $this->options[$action]['initSort'] = $initSort;
        return $this;
    }

    public function skin(string $skin, string $action = 'index')
    {
        $this->options[$action]['skin'] = $skin;//grid|line|row|nob
        return $this;
    }

    public function size(string $size, string $action = 'index')
    {
        $this->options[$action]['size'] = $size;//sm|md|lg
        return $this;
    }

    public function even(string $even, string $action = 'index')
    {
        $this->options[$action]['even'] = $even;
        return $this;
    }

    public function before(string $before, string $action = 'index')
    {
        $this->options[$action]['before'] = $before;
        return $this;
    }

    public function done(mixed $done, string $action = 'index')
    {
        $this->options[$action]['done'] = $done;
        return $this;
    }

    public function error(mixed $error, string $action = 'index')
    {
        $this->options[$action]['error'] = $error;
        return $this;
    }

    public function method(string $method = 'GET', string $action = 'index')
    {
        $this->options[$action]['method'] = $method;
        return $this;
    }

    public function where(mixed $where, string $action = 'index')
    {
        $this->options[$action]['where'] = $where;
        return $this;
    }

    public function headers(mixed $headers, string $action = 'index')
    {
        $this->options[$action]['headers'] = $headers;
        return $this;
    }

    public function contentType(mixed $contentType, string $action = 'index')
    {
        $this->options[$action]['contentType'] = $contentType;
        return $this;
    }

    public function dataType(mixed $dataType, string $action = 'index')
    {
        $this->options[$action]['dataType'] = $dataType;
        return $this;
    }

    public function request(mixed $request, string $action = 'index')
    {
        $this->options[$action]['request'] = $request;
        return $this;
    }

    public function parseData(mixed $parseData, string $action = 'index')
    {
        $this->options[$action]['parseData'] = $parseData;
        return $this;
    }

    public function cellMaxWidth(string $cellMaxWidth, string $action = 'index')
    {
        $this->options[$action]['cellMaxWidth'] = $cellMaxWidth;
    }

    public function maxHeight(string $maxHeight, string $action = 'index')
    {
        $this->options[$action]['maxHeight '] = $maxHeight;
    }


    /**
     * 设置表格主键
     * @param string $key 主键名称
     * @return $this
     */
    public function primaryKey($key = 'id', string $action = 'index')
    {
        $this->options[$action]['primaryKey'] = $key;
        return $this;
    }

    public function pageSize($pageSize = [], string $action = 'index')
    {

        $this->options[$action]['pageSize'] = !empty($pageSize) ? $pageSize : [];
        return $this;
    }

    /**
     * 额外的参数
     * @param $data
     * @return $this
     */
    public function extra($data = [], string $action = 'index')
    {
        $this->options[$action] = array_merge($this->options[$action], $data);
        return $this;
    }

    public function tree($data = [], string $action = 'index')
    {
        if (!empty($data)) {
            $this->options[$action]['tree'] = $data;
        }
        return $this;
    }

    public function style($style = '', string $action = 'index')
    {
        $this->style = $style;
        return $this;
    }

    public function script($script = '', string $action = 'index')
    {
        $this->script = $script;
        return $this;
    }

    public function extraStyle($style = '', string $action = 'index')
    {
        $this->extraStyle = $style;
        return $this;
    }

    public function extraScript($script = '', string $action = 'index')
    {
        $this->extraScript = $script;
        return $this;
    }

    /**
     * 设置额外HTML代码
     * @param string $html 额外HTML代码
     * @return $this
     */
    public function html($html = '', string $action = 'index')
    {
        $this->html = $html;
        return $this;
    }

    public function index(array $options = [], string $action = 'index')
    {
        $this->options[$action] = array_merge($this->options[$action], $options);
        return $this;
    }

    public function recycle(array $options = [], string $action = 'recycle')
    {
        if ($options == false) {
            foreach ($this->options as $key => $value) {
                if (!empty($value['toolbar']['recycle'])) {
                    unset($this->options[$key]['toolbar']['recycle']);
                }
                unset($this->options['recycle']);
            }
        }
        $this->options[$action] = array_merge($this->options[$action], $options);
        return $this;
    }

    /**
     * @param array $request
     * @return $this
     */
    public function requests(array $request = [], string $action = 'index')
    {
        $this->options[$action]['requests'] = $request;
        $this->requests = array_merge($this->requests, $request);
        return $this;
    }

    /**
     * 添加一个右侧按钮
     * @param array $attribute 按钮属性
     * @param array $extra 扩展参数(待用)
     * @return $this
     */
    public function operat(array $operat = [], $action = 'index')
    {
        array_push($this->options[$action]['cols'][0], $operat);
        return $this;
    }

    public function elem(string $elem,$action='index')
    {
        $this->options[$action]['elem'] = $elem;
        return $this;
    }

    public function id(string $id, string $action = 'index')
    {
        $this->options[$action]['id'] = $id;
        return $this;
    }

    public function defaultToolbar(array $default = ['filter', 'print', 'exports'], string $action = 'index')
    {

        $this->options[$action]['defaultToolbar'] = $default;
        return $this;

    }

    public function toolbar($buttons = [], string $action = 'index')
    {
        $this->options[$action]['toolbar'] = $buttons;
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
            'extraScript' => $this->extraScript,
            'tableStyle' => $this->style,
            'extraStyle' => $this->extraStyle,
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
