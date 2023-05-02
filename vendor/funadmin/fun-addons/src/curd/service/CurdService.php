<?php
/**
 * FunAdmin
 * ============================================================================
 * 版权所有 2017-2028 FunAdmin，并保留所有权利。
 * 网站地址: http://www.FunAdmin.com
 * ----------------------------------------------------------------------------
 * 采用最新Thinkphp6实现
 * ============================================================================
 * Author: yuege
 * Date: 2017/8/2
 */

namespace fun\curd\service;

use app\backend\model\AuthRule;
use think\App;
use think\facade\Cache;
use think\facade\Config;
use think\facade\Db;
use think\helper\Str;

class CurdService
{
    protected $config = [
        'keepField' => ['admin_id', 'member_id'],//保留字段
        'fields' => [],//显示的字段
        'ignoreFields' => ['create_time', 'status', 'update_time', 'delete_time'],//忽略字段
        'tagsSuffix' => ['tags', 'tag'],//识别为tag类型
        'urlSuffix' => ['url', 'urls'],//识别为tag类型
        'fileSuffix' => ['file', 'files', 'path', 'paths'],//识别为文件字段
        'priSuffix' => ['_id', '_ids'],//识别为别的表的主键
        'sortSuffix' => ['sort'],//排序
        'imageSuffix' => ['image', 'images', 'thumb', 'thumbs', 'avatar', 'avatars'],//识别为图片字段
        'editorSuffix' => ['editor', 'content', 'detail', 'details', 'description'],//识别为编辑器字段
        'iconSuffix' => ['icon'],//识别为图标字段
        'colorSuffix' => ['color'],//颜色
        'jsonSuffix' => ['json'],//识别为json字段
        'timeSuffix' => ['time', 'date', 'datetime'],//识别为日期时间字段
        'checkboxSuffix' => ['checkbox'],//多选
        'selectSuffix' => ['select', 'selects'],//下拉框
        'switchSuffix' => ['switch'],//开关
        'enumRadioSuffix' => ['data', 'state', 'status', 'radio'],//开关
        'setCheckboxSuffix' => ['data', 'state', 'status',],//多选
    ];
    /**
     * /**
     * 表前缀
     * @var string
     */
    protected $tablePrefix = 'fun_';
    /**
     * 数据库名
     * @var string
     */
    protected $database = 'funadmin';
    protected $driver = 'mysql';
    protected $app = 'backend';
    protected $force = false;
    protected $jump = true;//跳过文件
    protected $rootPath;
    protected $tplPath;//模板路径
    protected $method = 'index,add,edit,destroy,delete,recycle,import,export,modify,restore';
    protected $fileList;
    protected $fieldsList;
    protected $table;
    protected $addon;
    protected $nodeType = '__u';
    protected $baseController;
    protected $tableComment;
    protected $controllerNamespace;
    protected $controllerName;
    protected $modelName;
    protected $modelNamespace;
    protected $controllerNamePrefix;
    protected $modelNamePrefix;
    protected $langNamePrefix;
    protected $indexNamePrefix;
    protected $addNamePrefix;
    protected $controllerUrl;
    protected $childMethod;
    protected $validateName;
    protected $validateNamespace;
    protected $joinMethod;
    protected $joinName;
    protected $joinModel;
    protected $joinTable;
    protected $joinForeignKey;
    protected $primaryKey = 'id';
    protected $joinPrimaryKey;
    protected $selectFields;
    protected $jsCols;
    protected $jsColsRecycle;
    protected $assign;
    protected $script;
    protected $requests;
    protected $requestsRecycle;
    protected $limit;
    protected $page = "true";
    protected $controllerArr;
    protected $modelArr;
    protected $softDelete;
    protected $menuList;
    protected $title;
    protected $author;
    protected $version;
    protected $requires;
    protected $description;

    public function __construct(array $config)
    {
        $this->tablePrefix = config('database.connections.' . $config['driver'] . '.prefix');
        $this->database = Config::get('database.connections' . '.' . $config['driver'] . '.database');
        $this->rootPath = root_path();
        $this->dir = __DIR__;
        $this->tplPath = $this->rootPath . 'vendor' . '/' . 'funadmin' . '/' . 'fun-addons' . '/' . 'src' . '/' . 'curd' . '/' . 'tpl' . '/';
        $this->setParam($config);
        $this->driver = $config['driver'];
        return $this;
    }

    /**
     * 获取配置
     * @return \string[][]
     */
    public function getParam()
    {
        return $this->config;
    }

    /**
     * 设置配置
     * @param $config
     */
    public function setParam($config)
    {
        $res = array();
        foreach ($this->config as $k => $v) {
            if (isset($config[$k])) {
                $config[$k] = $config[$k] ?
                    (is_array($config[$k]) ? $config[$k] : explode(',', $config[$k])) : [];
                $res[$k] = array_merge($this->config[$k], $config[$k]);
                unset($this->config[$k], $config[$k]);
            }
        }
//        foreach ($config as $k=>&$v){
//
//            if(!empty($v)  && is_array($v) && strpos($v[0],',')!==false) {
////                $v = explode(',',$v[0]);
//            }
//        }
//        unset($v);
        $this->config = array_merge($res, $this->config, $config);
        $this->setArg();
    }

    /**
     * 设置基础参数
     */
    protected function setArg()
    {
        $this->table = $this->config['table'];
        $this->table = str_replace($this->tablePrefix, '', $this->table);
        $this->addon = isset($this->config['addon']) && $this->config['addon'] ? $this->config['addon'] : '';
        $this->force = $this->config['force'];
        $this->app = $this->config['app'];
        $this->title = $this->config['title']?:$this->addon;
        $this->description = $this->config['description']?:$this->addon;
        $this->requires = $this->config['requires']?:3.0;
        $this->author = $this->config['author']?:$this->addon;
        $this->version = $this->config['version']?:1.0;
        $this->jump = $this->config['jump'];
        $this->limit = $this->config['limit'] ?: 15;
        $this->page = ($this->config['page']==null ||  $this->config['page'] == 1) ? "true" : 'false';
        $this->joinTable = $this->config['joinTable'];
        foreach ($this->joinTable as $k => $v) {
            $this->joinTable[$k] = str_replace($this->tablePrefix, '', $v);
        }
        $this->joinName = $this->config['joinName'] ?: $this->joinTable;
        $this->joinModel = $this->config['joinModel'] ?: $this->joinTable;
        $this->joinMethod = $this->config['joinMethod'];
        $this->joinForeignKey = $this->config['joinForeignKey'];
        if ($this->joinForeignKey && count($this->joinForeignKey) == 1 && strpos($this->joinForeignKey[0], ',')) {
            $this->joinForeignKey = array_filter(explode(',', ($this->joinForeignKey[0])));
        }
        $this->joinPrimaryKey = $this->config['joinPrimaryKey'];
        if ($this->joinForeignKey && count($this->joinPrimaryKey) == 1 && strpos($this->joinPrimaryKey[0], ',')) {
            $this->joinPrimaryKey = array_filter(explode(',', ($this->joinPrimaryKey[0])));
        }
        $this->selectFields = $this->config['selectFields'];
        $controllerStr = $this->config['controller'] ?: Str::studly($this->table);
        $controllerArr = explode('/', $controllerStr);
        foreach ($controllerArr as $k => &$v) {
            $v = ucfirst(Str::studly($v));
        }
        unset($v);
        $this->controllerName = array_pop($controllerArr);
        $this->controllerArr = $controllerArr;
        $modelStr = $this->config['model'] ?: Str::studly($this->table);
        $modelArr = explode('/', $modelStr);
        foreach ($modelArr as $k => &$v) {
            $v = ucfirst(Str::studly($v));
        }
        unset($v);
        $this->modelName = array_pop($modelArr);
        $modelArr ? $modelArr[0] = Str::lower($modelArr[0]) : '';
        $this->modelArr = $modelArr;
        $this->validateName = $this->config['validate'] ?: $this->modelName;
        $this->validateName = Str::studly($this->validateName);
        $this->controllerUrl = $controllerArr ? Str::lower($controllerArr[0]) . '.' . Str::camel($this->controllerName) : Str::camel($this->controllerName);
        if (isset($this->config['method']) and $this->config['method']) {
            $this->method = $this->config['method'];
        }
        $nameSpace = $controllerArr ? '\\' . Str::lower($controllerArr[0]) : "";
        //普通模式
        $this->controllerNamePrefix = $controllerArr ? Str::lower($controllerArr[0]) . '/' . $this->controllerName : $this->controllerName;
        $this->modelNamePrefix = $modelArr ? $modelArr[0] . '/' : '' . ($this->modelName);
        $this->langNamePrefix = $controllerArr ? Str::lower($controllerArr[0]) . '/' . Str::lower($this->controllerName) : Str::lower($this->controllerName);
        $this->indexNamePrefix = $controllerArr ? Str::lower($controllerArr[0]) . '/' . Str::snake($this->controllerName) : Str::snake($this->controllerName);
        $this->addNamePrefix = $controllerArr ? Str::lower($controllerArr[0]) . '/' . Str::snake($this->controllerName) : Str::snake($this->controllerName);
        if (!$this->addon) {//普通app应用或后台应用
            $this->controllerNamespace = 'app\\' . $this->app . '\\controller' . $nameSpace;
            $this->baseController = '\\app\\common\\controller\\Backend';
            $this->modelNamespace = "app\\{$this->app}\\model" . ($modelArr ? '\\' . $modelArr[0] : '');
            $this->validateNamespace = "app\\{$this->app}\\validate" . ($modelArr ? '\\' . $modelArr[0] : '');
            $path = $this->rootPath . "app" . '/' . $this->app . '/';
            $this->fileList = [
                'controllerFileName' =>
                    $path . "controller" . '/' . $this->controllerNamePrefix . '.php',
                'modelFileName' =>
                    $path . "model" . '/' . $this->modelNamePrefix . '.php',
                'validateFileName' =>
                    $path . "validate" . '/' . $this->modelNamePrefix . '.php',
                'langFileName' =>
                    $path . "lang" . '/' . "zh-cn" . '/' . $this->langNamePrefix . '.php',
                'jsFileName' =>
                    $this->rootPath . "public" . '/' . "static" . '/' . $this->app . '/' . "js" . '/' . $this->langNamePrefix . '.js',
                'indexFileName' =>
                    $path . "view" . '/' . $this->indexNamePrefix . '/' . 'index.html',
                'addFileName' =>
                    $path . "view" . '/' . $this->indexNamePrefix . '/' . 'add.html',
            ];

        } else {
            //插件模式
            $this->controllerNamespace = "app\\{$this->addon}\\controller" . $nameSpace;
            $this->baseController = '\\app\\common\\controller\\Backend';
            //默认没有二级目录
            $this->modelNamespace = "app\\{$this->addon}\\model";
            $this->validateNamespace = "app\\{$this->addon}\\validate";
            $path = $this->rootPath . "addons" . '/' . $this->addon . '/' . 'app' . '/' . $this->addon . '/';
            $this->fileList = [
                'controllerFileName' =>
                    $path . "controller" . '/' . $this->controllerNamePrefix . '.php',
                'modelFileName' =>
                    $path . "model" . '/' . $this->modelNamePrefix . '.php',
                'validateFileName' =>
                    $path . "validate" . '/' . $this->modelNamePrefix . '.php',
                'langFileName' =>
                    $path . "lang" . '/' . "zh-cn" . '/' . $this->langNamePrefix . '.php',
                'jsFileName' =>
                    $this->rootPath . "addons" . '/' . $this->addon . '/' . "public" . '/' . "js" . '/' . $this->langNamePrefix . '.js',
                'indexFileName' =>
                    $path . "view" . '/' . $this->indexNamePrefix . '/' . 'index.html',
                'addFileName' =>
                    $path . "view" . '/' . $this->indexNamePrefix . '/' . 'add.html',
                'pluginFileName' => $this->rootPath . "addons" . '/' . $this->addon . '/' . "Plugin.php",
                'pluginIniFileName' => $this->rootPath . "addons" . '/' . $this->addon . '/' . "plugin.ini",
                'pluginMenuFileName' => $this->rootPath . "addons" . '/' . $this->addon . '/' . "menu.php",
                'pluginConfigFileName' => $this->rootPath . "addons" . '/' . $this->addon . '/' . "config.php",
                'pluginControllerFileName' => $this->rootPath . "addons" . '/' . $this->addon . '/' . "controller" . '/' . 'Index.php',
                'pluginViewFileName' => $this->rootPath . "addons" . '/' . $this->addon . '/' . "view" . '/' . 'index/index.html',
            ];
        }
        return $this;
    }

    /**
     *
     */
    public function maker()
    {
        list($this->tableComment,$this->fieldsList,$this->assign,$this->lang,$this->softDelete,$this->requests,$this->requestsRecycle) = $this->getFieldList();
        if (!$this->config['delete']) {
            $this->makeModel();
            $this->makeController();
            $this->makeJs();
            $this->makeView();
            $this->makeMenu(1);
            $this->makeAddon();
        } elseif ($this->config['force'] and $this->config['delete']) {
            foreach ($this->fileList as $k => $v) {
                @unlink($v);
            }
            $this->makeMenu(2);
        }
        return $this;
    }

    // 创建控制器文件
    protected function makeController()
    {
        $controllerTpl = $this->tplPath . 'controller.tpl';
        $modelTpl = $this->tplPath . 'model.tpl';
        $attrTpl = $this->tplPath . 'attr.tpl';
        $indexTpl = '';
        $recycleTpl = '';
        $relationSearch = '';
        $statusResult = Db::connect($this->driver)->query("SELECT COUNT(*) FROM information_schema.columns WHERE table_name ='" . $this->tablePrefix . $this->table . "' AND column_name ='status'");
        $status = $statusResult[0]['COUNT(*)'];
        if ($this->joinTable) {
            $relationSearch = '$this->relationSearch = true;';
            $joinIndexMethod = "withJoin([";
            foreach ($this->joinTable as $k => $v) {
                $joinName = lcfirst(Str::studly($this->joinName[$k]));
                $joinIndexMethod .= "'{$joinName}'" . ',';
                if (!$this->addon) {
                    $joinclass = "app" . '/' . $this->app . '/' . "model" . '/' . ($this->modelArr ? $this->modelArr[0] . '/' : '') . ucfirst(Str::studly($this->joinTable[$k])) ;
                    $joinModelFile = $this->rootPath . $joinclass . '.php';
                } else {
                    $joinclass =   "app" . '/' . $this->addon .'/'. "model" . '/' . ucfirst(Str::studly($this->joinTable[$k])) ;
                    $joinModelFile = $this->rootPath . "addons" . '/' . $this->addon . '/' . $joinclass . '.php';
                }
                $softDelete = '';
                //判断是否有删除字段
                $sql = "select COLUMN_NAME as name, COLUMN_DEFAULT as value from information_schema.columns where table_name = '" . $this->tablePrefix . $v . "' and table_schema = '" . $this->database . "' and column_name = 'delete_time'";
                $delete = Db::connect($this->driver)->query($sql);
                if (!empty($delete)) {
                    $softDelete = $this->getSoftDelete($delete[0]);
                }
                list($tableComment,$fieldsList,$assign,$lang,$softDelete,$requests,$requestsRecycle)  = $this->getFieldList($v,'*');
                $joinTplStr = '';
                $joinclass = str_replace(DS, '\\', $joinclass);
                if (file_exists($joinModelFile)) include_once $joinModelFile;
                if ($assign) {
                    foreach ($assign as $key => $val) {
                        $kk = Str::studly($key);
                        if (!$this->hasSuffix($k, $this->config['priSuffix'])) {
                            $joinMethod = 'get' . $kk;
                            if (class_exists($joinclass)) {
                                $joinClassMethods = get_class_methods($joinclass);
                                if(!in_array($joinMethod,$joinClassMethods)){
                                    $joinTplStr .= str_replace(['{{$method}}', '{{$values}}'],
                                            ['get' . $kk, $val],
                                            file_get_contents($attrTpl)) . PHP_EOL;
                                }
                            }else{
                                $joinTplStr .= str_replace(['{{$method}}', '{{$values}}'],
                                        ['get' . $kk, $val],
                                        file_get_contents($attrTpl)) . PHP_EOL;
                            }
                        }
                    }
                }
                $attrStr = $this->modifyAttr($fieldsList);
                //生成关联表的模型
                $connection = $this->driver == 'mysql' ? "" : "protected \$connection = '" . $this->driver . "';";
                if (!$this->force && class_exists($joinclass) && $joinTplStr) {
                    $content = str_replace('?>','',file_get_contents($joinModelFile));
                    $content = substr($content,0,strrpos($content,'}',0)).$joinTplStr .PHP_EOL .'}';
                    file_put_contents($joinModelFile,$content);
                }else{
                    $modelTplTemp = str_replace([
                        '{{$modelNamespace}}',
                        '{{$modelName}}',
                        '{{$modelTableName}}',
                        '{{$softDelete}}',
                        '{{$connection}}',
                        '{{$joinTpl}}',
                        '{{$attrTpl}}',
                        '{{$primaryKey}}',
                    ],
                        [
                            $this->modelNamespace,
                            ucfirst(Str::studly($this->joinName[$k])),
                            $this->joinName[$k],
                            $softDelete,
                            $connection,
                            $joinTplStr,
                            $attrStr,
                            $this->joinPrimaryKey[$k]
                        ],
                        file_get_contents($modelTpl));

                    //插件类需要加载进来
                    $this->makeFile($joinModelFile, $modelTplTemp);
                }

            }
            $joinIndexMethod = substr($joinIndexMethod, 0, strlen($joinIndexMethod) - 1);
            $joinIndexMethod .= "])";
            $joinIndexMethod = trim($joinIndexMethod, ',');
            $indexTpl = $this->tplPath . 'index.tpl';
            $indexTpl = str_replace(
                [
                    '{{$joinIndexMethod}}',
                    '{{$relationSearch}}',
                    '{{$table}}',
                    '{{$status}}',
                ],
                [$joinIndexMethod, $relationSearch, $this->table . '.', $status], file_get_contents($indexTpl));
            if ($this->softDelete) {
                $recycleTpl = $this->tplPath . 'indexrecycle.tpl';
                $recycleTpl = str_replace(
                    [
                        '{{$joinIndexMethod}}',
                        '{{$relationSearch}}',
                        '{{$table}}',
                        '{{$status}}',
                    ],
                    [$joinIndexMethod, $relationSearch, $this->table . '.', $status], file_get_contents($recycleTpl));
            }

        }
        $assignTpl = file_get_contents($this->tplPath . 'assign.tpl');
        $scriptTpl = file_get_contents($this->tplPath . 'script.tpl');
        $assignStr = '';
        $scriptStr = '<script>';
        foreach ($this->assign as $k => $v) {
            $kk = Str::studly($k);
            if (!$this->hasSuffix($k, $this->config['priSuffix'])) {
                $assignStr .= str_replace(['{{$name}}', '{{$method}}'], [lcfirst($kk), 'get' . $kk], $assignTpl) . PHP_EOL;
                $scriptStr .= str_replace(['{{$name}}', '{{$method}}'], [lcfirst($kk), lcfirst($kk)], $scriptTpl) . PHP_EOL;
            } elseif ($this->hasSuffix($k, $this->config['priSuffix'])
                and $this->joinTable
                and in_array(substr($k, 0, strlen($k) - 4), $this->joinForeignKey)
            ) {
                $assignStr .= str_replace(['{{$name}}', '{{$method}}'], [lcfirst($kk), 'get' . $kk], $assignTpl) . PHP_EOL;
                $scriptStr .= str_replace(['{{$name}}', '{{$method}}'], [lcfirst($kk), lcfirst($kk)], $scriptTpl) . PHP_EOL;
            }
        }
        $scriptStr .= '</script>';
        $this->script = $scriptStr;
        $this->tableComment = $this->tableComment ?: $this->controllerName;
        if ($this->addon || $this->app !== 'backend') {
            $layout = '../../backend/view/layout/main';
        } else {
            $layout = 'layout/main';
        }
        $controllerTplBack = str_replace(
            [
                '{{$controllerNamespace}}',
                '{{$controllerName}}',
                '{{$baseController}}',
                '{{$tableComment}}',
                '{{$modelName}}',
                '{{$modelNamespace}}',
                '{{$assign}}',
                '{{$indexTpl}}',
                '{{$recycleTpl}}',
                '{{$layout}}',
                '{{$limit}}',

            ],
            [
                $this->controllerNamespace,
                $this->controllerName,
                $this->baseController,
                $this->tableComment,
                $this->modelName,
                $this->modelNamespace,
                $assignStr,
                $indexTpl,
                $recycleTpl,
                $layout,
                $this->limit,
            ],
            file_get_contents($controllerTpl));
        $this->makeFile($this->fileList['controllerFileName'], $controllerTplBack);
        //语言文件
        $langTpl = $this->tplPath . 'lang.tpl';
        $langTpl = str_replace(
            [
                '{{$lang}}',
            ],
            [
                $this->lang,
            ],
            file_get_contents($langTpl));
        $this->makeFile(
            $this->fileList['langFileName'],
            $langTpl
        );

    }

    // 创建模型文件
    protected function makeModel()
    {
        $modelTpl = $this->tplPath . 'model.tpl';
        $validateTpl = $this->tplPath . 'validate.tpl';
        $attrTpl = $this->tplPath . 'attr.tpl';
        $joinAttrTpl = $this->tplPath . 'joinAttr.tpl';
        //单模型
        $joinTplStr = '';
        if ($this->joinTable) {
            foreach ($this->joinTable as $k => $v) {
                $method = 'hasOne';
                if (isset($this->joinMethod[$k])) $method = $this->joinMethod[$k];
                if ($method == 'hasOne') {
                    list($joinPrimaryKey, $joinForeignKey) = array($this->joinForeignKey[$k], $this->joinPrimaryKey[$k]);
                } else {
                    list($joinPrimaryKey, $joinForeignKey) = array($this->joinPrimaryKey[$k], $this->joinForeignKey[$k]);
                }
                $joinTpl = $this->tplPath . 'join.tpl';
                $joinTplStr .= str_replace([
                        '{{$joinName}}',
                        '{{$joinMethod}}',
                        '{{$joinModel}}',
                        '{{$joinForeignKey}}',
                        '{{$joinPrimaryKey}}'],
                        [
                            lcfirst(Str::studly($v)),
                            $method,
                            ucfirst(Str::studly($this->joinModel[$k])),
                            $joinForeignKey,
                            $joinPrimaryKey],
                        file_get_contents($joinTpl)) . PHP_EOL;
            }
        }
        //变量分配
        $i = 0;
        if ($this->assign) {
            foreach ($this->assign as $k => $v) {
                $kk = Str::studly($k);
                if (!$this->hasSuffix($k, $this->config['priSuffix'])) {
                    $joinTplStr .= str_replace(['{{$method}}', '{{$values}}'],
                            ['get' . $kk, $v],
                            file_get_contents($attrTpl)) . PHP_EOL;
                } elseif ($this->hasSuffix($k, $this->config['priSuffix'])
                    and  $this->joinTable
                    and in_array(substr($k, 0, strlen($k) - 4), $this->joinForeignKey)
                ) {
                    //关联模型搜索属性
                    $model = isset($this->joinModel[$i]) ? $this->joinModel[$i] : $this->joinModel[0];
                    if ($this->joinTable && count($this->joinTable) == 1) {
                        $value = isset($this->selectFields[0]) ? $this->selectFields[0] : 'title';
                    } else {
                        $value = isset($this->selectFields[$i]) ? $this->selectFields[$i] : 'title';
                    }
                    $k = str_replace(['_id', '_ids'], ['', ''], $k);
                    $joinTplStr .= str_replace(['{{$method}}', '{{$values}}', '{{$joinModel}}'],
                            ['get' . ucfirst($kk), $value, ucfirst(Str::studly($model))],
                            file_get_contents($joinAttrTpl)) . PHP_EOL;
                    $i++;
                }
            }
        }
        $attrStr = $this->modifyAttr();
        $connection = $this->driver == 'mysql' ? "" : "protected \$connection = '" . $this->driver . "';";
        $modelTpl = str_replace([
            '{{$modelNamespace}}',
            '{{$modelName}}',
            '{{$modelTableName}}',
            '{{$joinTpl}}',
            '{{$attrTpl}}',
            '{{$softDelete}}',
            '{{$connection}}',
            '{{$primaryKey}}',
        ],
            [$this->modelNamespace,
                ucfirst($this->modelName),
                $this->table,
                $joinTplStr,
                $attrStr,
                $this->softDelete,
                $connection,
                $this->primaryKey,
            ],
            file_get_contents($modelTpl));
        $validateTpl = str_replace(
            ['{{$validateNamespace}}', '{{$validateName}}'],
            [$this->validateNamespace, $this->validateName], file_get_contents($validateTpl));
        $this->makeFile($this->fileList['modelFileName'], $modelTpl);
        $this->makeFile($this->fileList['validateFileName'], $validateTpl);
    }

    // 创建模板
    protected function makeView()
    {
        $formFieldData = $this->getFormData();
        $indexViewTpl = $this->tplPath . 'view' . '/' . 'index.tpl';
        $indexViewTpl = str_replace(['{{$nodeType}}', '{{$script}}'], [$this->nodeType, $this->script], file_get_contents($indexViewTpl));
        $addViewTpl = $this->tplPath . 'view' . '/' . 'add.tpl';
        $addViewTpl = str_replace(['{{$formDataField}}'], [$formFieldData], file_get_contents($addViewTpl));
        $this->makeFile($this->fileList['indexFileName'], $indexViewTpl);
        $this->makeFile($this->fileList['addFileName'], $addViewTpl);
    }

    //生成js
    protected function makeJs()
    {
        $this->getCols();
        $jsTpl = $this->tplPath . 'js.tpl';
        $jsrecycleTpl = '';
        $toolbar = "'refresh','add','destroy','import','export'";
        if ($this->softDelete) {
            $toolbar = "'refresh','add','delete','import','export','recycle'";
            $jsrecycleTpl = $this->tplPath . 'jsrecycle.tpl';
            $jsrecycleTpl = str_replace(['{{$requestsRecycle}}', '{{$jsColsRecycle}}',
                '{{$limit}}', '{{$page}}','{{$primaryKey}}'
            ],
                [$this->requestsRecycle, $this->jsColsRecycle, $this->limit, $this->page,$this->primaryKey
                ],
                file_get_contents($jsrecycleTpl));
        }
        $jsTpl = str_replace(['{{$requests}}', '{{$jsCols}}', '{{$toolbar}}', '{{$limit}}', '{{$page}}','{{$primaryKey}}', '{{$jsrecycleTpl}}'],
            [$this->requests, $this->jsCols, $toolbar, $this->limit, $this->page, $this->primaryKey,$jsrecycleTpl],
            file_get_contents($jsTpl));
        $this->makeFile($this->fileList['jsFileName'], $jsTpl);
    }

    /**
     * 生成插件文件
     * @throws \Exception
     */
    public function makeAddon()
    {
        if ($this->addon && (!file_exists($this->fileList['pluginFileName']) || $this->force)) {
            $controllerTpl = $this->tplPath . 'addon' . '/' . 'controller.tpl';
            $viewTpl = $this->tplPath . 'addon' . '/' . 'view.tpl';
            $configTpl = $this->tplPath . 'addon' . '/' . 'config.tpl';
            $iniTpl = $this->tplPath . 'addon' . '/' . 'ini.tpl';
            $pluginTpl = $this->tplPath . 'addon' . '/' . 'plugin.tpl';
            $controllerTpl = str_replace(
                ['{{$addon}}'],
                [Str::lower($this->addon)], file_get_contents($controllerTpl));
            $viewTpl = str_replace(
                ['{{$addon}}'],
                [Str::lower($this->addon)], file_get_contents($viewTpl));
            $url = '/addons/' . $this->addon;
            $iniTpl = str_replace(
                ['{{$addon}}','{{$title}}','{{$description}}', '{{$author}}', '{{$requires}}', '{{$version}}', '{{$url}}', '{{$time}}', '{{$app}}']
                ,[Str::lower($this->addon),$this->title,$this->description,$this->author,$this->requires,$this->version, $url, date('Y-m-d H:i:s'), $this->addon]
                , file_get_contents($iniTpl));
            $pluginTpl = str_replace(
                ['{{$addon}}'],
                [Str::lower($this->addon)],
                file_get_contents($pluginTpl));
            $this->makeFile($this->fileList['pluginControllerFileName'], $controllerTpl);
            $this->makeFile($this->fileList['pluginViewFileName'], $viewTpl);
            $this->makeFile($this->fileList['pluginIniFileName'], $iniTpl);
            $this->makeFile($this->fileList['pluginFileName'], $pluginTpl);
            $this->makeFile($this->fileList['pluginConfigFileName'], file_get_contents($configTpl));
        }
        if ($this->addon && $this->menuList) {
            $menuTpl = '<?php ' . PHP_EOL .' return ' . var_export($this->menuList, true) . ';';
            $this->makeFile($this->fileList['pluginMenuFileName'], $menuTpl);
        }
    }

    /**
     *
     * 生成菜单
     * @param int $type
     */
    protected function makeMenu(int $type = 1)
    {
        $controllerName = str_replace('/', '.', $this->controllerNamePrefix);
        $href = $controllerName;
        if ($this->addon) {
            $title = $this->addon . str_replace('/', '', $this->controllerNamePrefix);
        } elseif ($this->app !== 'backend') {
            $title = $this->app . str_replace('/', '', $this->controllerNamePrefix);
        } else {
            $title = str_replace('/', '', $this->controllerNamePrefix);
        }
        $title = $this->tableComment ?: $title;
        $childMenu = [
            'href' => $href,
            'title' => $this->config['menuname']?:$title,
            'status' => 1,
            'type' => 1,
            'menu_status' => 1,
            'icon' => 'layui-icon layui-icon-app',
            'menulist' => []
        ];
        $menu = [
            'is_nav' => 1,//1导航栏；0 非导航栏
            'menu' => [ //菜单;
                'href' => 'table' . ($this->addon ?: ($this->app !== 'backend' ? $this->app : $this->controllerName)),
                'title' => $this->config['menuname']?:($this->addon ?: ($this->app !== 'backend' ? $this->app : $this->controllerName)),
                'status' => 1,
                'auth_verify' => 1,
                'type' => 1,
                'menu_status' => 1,
                'icon' => 'layui-icon layui-icon-app',
                'menulist' => [
                    $childMenu
                ]
            ]
        ];
        $addon_old_menu = [];
        if ($this->addon) {
            $menu['menu']['menulist'] = [];
            $addon_menu = get_addons_menu($this->addon);
            if ($addon_menu) {
                foreach ($addon_menu['menu']['menulist'] as $k => $v) {
                    if ($v['href'] != $childMenu['href']) {
                        $menu['menu']['menulist'][] = $v;
                    }
                }
            }
        }
        if (!$this->softDelete) {
            $this->method = 'index,add,edit,destroy,import,export,modify';
        }
        foreach (explode(',', $this->method) as $k => $v) {
            if ($v == 'refresh') continue;
            $menuList[] = [
                'href' => $href . '/' . $v,
                'title' => $v,
                'status' => 1,
                'menu_status' => 0,
                'icon' => 'layui-icon layui-icon-app'
            ];
            $childMethod[] = $href . '/' . $v;
        }
        $parentMethod = $href;
        $this->childMethod = array_merge($childMethod, [$parentMethod]);
        if ($this->addon) {
            $childMenu['menulist'] = array_merge($menuList, $addon_old_menu);
            array_push($menu['menu']['menulist'], $childMenu);
            $menu['menu']['menulist'] = array_unique($menu['menu']['menulist'], SORT_REGULAR);//去重
        } else {
            $menu['menu']['menulist'][0]['menulist'] = $menuList;
        }
        $menuListArr[] = $menu['menu'];
        $this->menuList = $menu;
        if ($this->config['menu']) {
            $this->buildMenu($menuListArr, $type);
        }
    }

    /**
     * 生成文件
     */
    public function makeFile($filename, $content)
    {
        if(!$this->force && Str::contains($filename,'menu.php') ===true){
            file_put_contents($filename, $content);
        }
        if(is_file($filename)){
            if($this->force && !$this->jump){
                file_put_contents($filename, $content);
            }
        }else{
            if (!is_dir(dirname($filename))) {
                @mkdir(dirname($filename), 0755, true);
            }
            file_put_contents($filename, $content);
        }
    }
    protected function buildMenu($menuListArr, $type = 1)
    {
        $module = $this->addon ?: $this->app;
        foreach ($menuListArr as $k => $v) {
            $v['pid'] = $this->config['menuid']?:0;
            $v['href'] = trim($v['href'], '/');
            $v['module'] = $module;
            $menu = AuthRule::withTrashed()->where('href', $v['href'])->where('module', $module)->find();
            if ($type == 1) {
                if (!$menu) {
                    $menu = AuthRule::create($v);
                } else {
                    $menu->restore();
                }
            } else {
                $child = AuthRule::withTrashed()->where('href', 'not in', $this->childMethod)
                    ->where('pid', $menu['id'])->where('module', $module)->find();
                if (!$child) {
                    $menu && $menu->delete();
                }
            }
            foreach ($v['menulist'] as $kk => $vv) {
                $menu2 = AuthRule::withTrashed()->where('href', $vv['href'])->where('module', $module)->find();
                if ($type == 1) {
                    if (!$menu2) {
                        $vv['pid'] = $menu['id'];
                        $vv['module'] = $module;
                        $menu2 = AuthRule::create($vv);
                    } else {
                        $menu2->restore();
                    }
                } else {
                    $menu2 && $menu2->delete();
                }
                foreach ($vv['menulist'] as $kkk => $vvv) {
                    $menu3 = AuthRule::withTrashed()->where('href', $vvv['href'])->where('module', $module)->find();
                    if ($type == 1) {
                        if (!$menu3) {
                            $vvv['pid'] = $menu2['id'];
                            $vvv['module'] = $module;
                            $menu3 = AuthRule::create($vvv);
                        } else {
                            $menu3->restore();
                        }
                    } else {
                        $menu3 && $menu3->delete();
                    }
                }
            }
        }
    }

    /**
     * 获取add表单
     * @param $fieldList
     * @param $this- >addon
     * @return string
     */
    protected function getFormData()
    {
        $formFieldData = '';
        foreach ($this->fieldsList as $k => $vo) {
            if ($vo['COLUMN_KEY'] == 'PRI') continue;
            if (in_array($vo['name'], $this->config['ignoreFields']) and $vo['name'] != 'status') continue;
            if(!empty($this->config['formFields']) && !in_array($vo['name'], $this->config['formFields'])) continue;
            $name = Str::studly($vo['name']);
            switch ($vo['type']) {
                case "text":
                    $formFieldData .= "{:form_input('{$vo['name']}', 'text', ['label' => '{$name}', 'verify' => '{$vo['required']}'], '{$vo['value']}')}" . PHP_EOL;
                    break;
                case "tags":
                    $formFieldData .= "{:form_tags('{$vo['name']}', ['label' => '{$name}', 'verify' => '{$vo['required']}'], '{$vo['value']}')}" . PHP_EOL;
                    break;
                case "number":
                    $formFieldData .= "{:form_input('{$vo['name']}', 'number', ['label' => '{$name}', 'verify' => '{$vo['required']}'], '{$vo['value']}')}" . PHP_EOL;
                    break;
                case "switch":
                    $vo['name_list'] = lcfirst(Str::studly($vo['name']));
//                    $formFieldData .= "{:form_switch('{$vo['name']}', \${$vo['name_list']}List, ['label' => '{$name}', 'verify' => '{$vo['required']}'], \$formData?\$formData['{$vo['name']}']:'{$vo['value']}') }" . PHP_EOL;
                    $formFieldData .= "{:form_radio('{$vo['name']}' ,\${$vo['name_list']}List, ['label' => '{$name}', 'verify' => '{$vo['required']}'], '{$vo['value']}')}" . PHP_EOL;
                    break;
                case "array":
                    $formFieldData .= "{:form_textarea('{$vo['name']}', ['label' => '{$name}', 'verify' => '{$vo['required']}'])}" . PHP_EOL;
                    break;
                case "checkbox":
                    $vo['name_list'] = lcfirst(Str::studly($vo['name']));
                    $formFieldData .= "{:form_checkbox('{$vo['name']}', \${$vo['name_list']}List,['label' => '{$name}', 'verify' => '{$vo['required']}'], \$formData?\$formData['{$vo['name']}']:'{$vo['value']}')}" . PHP_EOL;
                    break;
                case "radio":
                    $vo['name_list'] = lcfirst(Str::studly($vo['name']));
                    $formFieldData .= "{:form_radio('{$vo['name']}' ,\${$vo['name_list']}List, ['label' => '{$name}', 'verify' => '{$vo['required']}'], '{$vo['value']}')}" . PHP_EOL;
                    break;
                case "_id":
                    if ($this->joinTable) {
                        $vo['name_list'] = lcfirst(Str::studly($vo['name']));
                        if (strpos($vo['name'], '_ids') and in_array($vo['name'], $this->joinForeignKey)) {
                            $formFieldData .= "{:form_select('{$vo['name']}',\${$vo['name_list']}List, ['label' => '{$name}', 'verify' => '{$vo['required']}','multiple'=>1, 'search' => 1], [], '{$vo['value']}')}" . PHP_EOL;
                        } elseif (strpos($vo['name'], '_id') and in_array($vo['name'], $this->joinForeignKey)) {
                            $formFieldData .= "{:form_select('{$vo['name']}',\${$vo['name_list']}List, ['label' => '{$name}', 'verify' => '{$vo['required']}', 'search' => 1], [], '{$vo['value']}')}" . PHP_EOL;
                        } else {
                            $formFieldData .= "{:form_input('{$vo['name']}', 'text', ['label' => '{$name}', 'verify' => '{$vo['required']}'], '{$vo['value']}')}" . PHP_EOL;
                        }
                    } else {
                        $formFieldData .= "{:form_input('{$vo['name']}', 'text', ['label' => '{$name}', 'verify' => '{$vo['required']}'], '{$vo['value']}')}" . PHP_EOL;
                    }
                    break;
                case "select":
                    $vo['name_list'] = lcfirst(Str::studly($vo['name']));
                    if (in_array($vo['DATA_TYPE'], ['set', 'varchar', 'char'])) {
                        $formFieldData .= "{:form_select('{$vo['name']}',\${$vo['name_list']}List, ['label' => '{$name}', 'verify' => '{$vo['required']}', 'multiple'=>1,'search' => 1], [], '{$vo['value']}')}" . PHP_EOL;
                    } else {
                        $formFieldData .= "{:form_select('{$vo['name']}',\${$vo['name_list']}List, ['label' => '{$name}', 'verify' => '{$vo['required']}', 'search' => 1], [], '{$vo['value']}')}" . PHP_EOL;
                    }
                    break;
                case "color":
                    $formFieldData .= "{:form_color('{$vo['name']}',['label' => '{$name}', 'verify' => '{$vo['required']}', 'search' => 1])}" . PHP_EOL;
                    break;
                case "timestamp":
                case "datetime":
                    $formFieldData .= "{:form_date('{$vo['name']}', ['label' => '{$name}', 'verify' => '{$vo['required']}'])}" . PHP_EOL;
                    break;
                case "year":
                    $formFieldData .= "{:form_date('{$vo['name']}', ['label' => '{$name}', 'verify' => '{$vo['required']}', 'type' => 'year'])}" . PHP_EOL;
                    break;
                case "date":
                    $formFieldData .= "{:form_date('{$vo['name']}', ['label' => '{$name}', 'verify' => '{$vo['required']}', 'type' => 'date'])}" . PHP_EOL;
                    break;
                case "time":
                    $formFieldData .= "{:form_date('{$vo['name']}', ['label' => '{$name}', 'verify' => '{$vo['required']}', 'type' => 'time'])}" . PHP_EOL;
                    break;
                case "range":
                    $formFieldData .= "{:form_date('{$vo['name']}', ['label' => '{$name}', 'verify' => '{$vo['required']}','range' => 'range'])}" . PHP_EOL;
                    break;
                case "textarea":
                    $formFieldData .= "{:form_textarea('{$vo['name']}', ['label' => '{$name}', 'verify' => '{$vo['required']}',], '{$vo['value']}')}" . PHP_EOL;
                    break;
                case "image":
                    $formFieldData .= "{:form_upload('{$vo['name']}',\$formData?\$formData['{$vo['name']}']:'{$vo['value']}' ,['label' => '{$name}', 'verify' => '{$vo['required']}', 'type' => 'radio', 'mime' => 'image', 'path' => '{$this->modelName}', 'num' => '1'])}" . PHP_EOL;
                    break;
                case "images":
                    $formFieldData .= "{:form_upload('{$vo['name']}', \$formData?\$formData['{$vo['name']}']:'{$vo['value']}', ['label' => '{$name}', 'verify' => '{$vo['required']}', 'type' => 'checkbox', 'mime' => 'image', 'path' =>'{$this->modelName}', 'num' => '*'])}" . PHP_EOL;
                    break;
                case "file":
                    $formFieldData .= "{:form_upload('{$vo['name']}', \$formData?\$formData['{$vo['name']}']:'{$vo['value']}', ['label' => '{$name}', 'verify' => '{$vo['required']}', 'type' => 'radio', 'mime' => 'file', 'path' =>'{$this->modelName}', 'num' => '1'])}" . PHP_EOL;
                    break;
                case "files":
                    $formFieldData .= "{:form_upload('{$vo['name']}', \$formData?\$formData['{$vo['name']}']:'{$vo['value']}', ['label' => '{$name}', 'verify' => '{$vo['required']}', 'type' => 'checkbox', 'mime' => 'file', 'path' => '{$this->modelName}', 'num' => '*'])}" . PHP_EOL;
                    break;
                case "editor":
                    $formFieldData .= "{:form_editor('{$vo['name']}', 2,['label'=>'{$name}','verify' => '{$vo['required']}'])}" . PHP_EOL;
            }
        }
        return $formFieldData;
    }


    /**
     * 获取js 栏目
     * @return string
     */
    protected function getCols()
    {
        $space = '                    ';
        $this->jsCols = "{checkbox: true,}," . PHP_EOL . $space . " {field: 'id', title: __('ID'), sort:true,}," . PHP_EOL;
        $fields = $this->config['fields'] ? explode(",", $this->config['fields'][0]) : [];
        foreach ($this->fieldsList as $k => $v) {
            if (($fields && in_array($v['name'], $fields)) || !$fields) {
                if ($v['COLUMN_KEY'] != "PRI") {
                    $name = Str::studly($v['name']);
                    $listName = lcfirst(Str::studly($v['name']));
                    switch ($v['type']) {
                        case '_id':
                            if ($this->joinTable and in_array($v['name'], $this->joinForeignKey)) { //
                                $this->jsCols .= $space . "{field:'{$v['name']}',search: true,title: __('{$name}'),selectList:{$listName}List,sort:true,templet: Table.templet.tags}," . PHP_EOL;;
                            } else {
                                $this->jsCols .= $space . "{field:'{$v['name']}', title: __('{$name}'),align: 'center',sort:true}," . PHP_EOL;
                            }
                            break;
                        case 'image':
                            $this->jsCols .= $space . "{field:'{$v['name']}',title: __('{$name}'),templet: Table.templet.image}," . PHP_EOL;;
                            break;
                        case 'images':
                            $this->jsCols .= $space . "{field:'{$v['name']}',title: __('{$name}'),templet: Table.templet.image}," . PHP_EOL;;
                            break;
                        case 'file':
                            $this->jsCols .= $space . "{field:'{$v['name']}',title: __('{$name}'),templet: Table.templet.url}," . PHP_EOL;;
                            break;
                        case 'files':
                            $this->jsCols .= $space . "{field:'{$v['name']}',title: __('{$name}'),templet: Table.templet.url}," . PHP_EOL;;
                            break;
                        case 'url':
                            $this->jsCols .= $space . "{field:'{$v['name']}',title: __('{$name}'),filter: '{$v['name']}',templet: Table.templet.url}," . PHP_EOL;;
                            break;
                        case 'checkbox':
                        case 'tags':
                            $this->jsCols .= $space . "{field:'{$v['name']}',title: __('{$name}'),filter: '{$v['name']}',templet: Table.templet.tags}," . PHP_EOL;;
                            break;
                        case 'select':
                        case 'radio':
                            $this->jsCols .= $space . "{field:'{$v['name']}',search: 'select',title: __('{$name}'),filter: '{$v['name']}',selectList:{$listName}List,templet: Table.templet.select}," . PHP_EOL;;
                            break;
                        case 'switch':
                            $this->jsCols .= $space . "{field:'{$v['name']}',search: 'select',title: __('{$name}'), filter: '{$v['name']}', selectList:{$listName}List,templet: Table.templet.switch}," . PHP_EOL;;
                            break;
                        case 'number':
                            if ($this->hasSuffix($v['name'], ['sort'])) {
                                $this->jsCols .= $space . "{field:'{$v['name']}',title: __('{$name}'),align: 'center',edit:'text'}," . PHP_EOL;;
                                break;
                            } else {
                                $this->jsCols .= $space . "{field:'{$v['name']}',title: __('{$name}'),align: 'center'}," . PHP_EOL;;
                                break;
                            }
                        case 'date':
                            $this->jsCols .= $space . "{field:'{$v['name']}',title: __('{$name}'),align: 'center',dateformat:'yyyy-MM-dd',searchdateformat:'yyyy-MM-dd',search:'time',templet: Table.templet.time,sort:true}," . PHP_EOL;;
                            break;
                        case 'timestamp':
                        case 'datetime':
                            if (in_array($v['name'], ['update_time', 'delete_time'])) {
                                break;
                            }
                            $this->jsCols .= $space . "{field:'{$v['name']}',title: __('{$name}'),align: 'center',timeType:'datetime',dateformat:'yyyy-MM-dd HH:mm:ss',searchdateformat:'yyyy-MM-dd HH:mm:ss',search:'time',templet: Table.templet.time,sort:true}," . PHP_EOL;;
                            break;
                        case 'year':
                            $this->jsCols .= $space . "{field:'{$v['name']}',title: __('{$name}'),align: 'center',dateformat:'yyyy',searchdateformat:'yyyy',timeType:'year',search:'time',templet: Table.templet.time,sort:true}," . PHP_EOL;;
                            break;
                        case 'time':
                            $this->jsCols .= $space . "{field:'{$v['name']}',title: __('{$name}'),align: 'center',dateformat:'HH:mm:ss',searchdateformat:'HH:mm:ss',timeType:'time',search:'time',templet: Table.templet.time,sort:true}," . PHP_EOL;;
                            break;
                        default :
                            $this->jsCols .= $space . "{field:'{$v['name']}', title: __('{$name}'),align: 'center'}," . PHP_EOL;
                            break;
                    }
                }
            }
        }
        $this->jsColsRecycle = $this->jsCols . $space . '{
                        minWidth: 250,
                        align: "center",
                        title: __("Operat"),
                        init: Table.init,
                        templet: Table.templet.operat,
                        operat: ["restore","delete"]
                    },';
        $operat = ' ["edit", "destroy","delete"]';
        if (!$this->softDelete) {
            $this->jsColsRecycle = '';
            $operat = '["edit","destroy"]';
        }
        $this->jsCols .= $space . '{
                        minWidth: 250,
                        align: "center",
                        title: __("Operat"),
                        init: Table.init,
                        templet: Table.templet.operat,
                        operat:' . $operat . '
                    },';
        return [$this->jsCols, $this->jsColsRecycle];
    }

    /**
     * 获取字段数据
     * @param $table
     */
    protected function getFieldList($model='',$field = '*')
    {
        $assign = [];
        $lang = '';
        $softDelete = '';
        $model = $model?:$this->table;
        $sql = "show tables like '{$this->tablePrefix}{$model}'";
        $table = Db::connect($this->driver)->query($sql);
        if (!$table) {
            throw new \Exception($model . '表不存在');
        }
        $sql = "select $field from information_schema . columns  where table_name = '" . $this->tablePrefix . $model . "' and table_schema = '" . $this->database . "' order by ORDINAL_POSITION ASC";
        $tableField = Db::connect($this->driver)->query($sql);
        $tableComment = Db::connect($this->driver)->query(' SELECT TABLE_COMMENT FROM INFORMATION_SCHEMA.TABLES  WHERE TABLE_NAME =  "' . $this->tablePrefix . $model . '";');
        $tableComment = $tableComment[0]['TABLE_COMMENT'];
        foreach ($tableField as $k => &$v) {
            $v['required'] = $v['IS_NULLABLE'] == 'NO' ? 'required' : "";
            $v['comment'] = trim($v['COLUMN_COMMENT'], ' ');
            $v['comment'] = str_replace(array("\r\n", "\r", "\n"), "", $v['COLUMN_COMMENT']);
            $v['comment'] = str_replace('：', ':', $v['comment']);
            $v['name'] = $v['COLUMN_NAME'];
            $v['value'] = $v['COLUMN_DEFAULT'];
            if($v['COLUMN_KEY'] == 'PRI'){
                $this->primaryKey = $v['name'];
            }
            if (!$v['COLUMN_COMMENT']) {
                $v['comment'] = $v['name'];
            }
            $v['type'] = 'text';
            if (in_array($v['DATA_TYPE'], ['tinyint', 'smallint', 'int', 'mediumint', 'bigint'])) {
                $v['type'] = 'number';
            }
            if (in_array($v['DATA_TYPE'], ['decimal', 'double', 'float'])) {
                $v['type'] = 'number';
            }
            if (in_array($v['DATA_TYPE'], ['enum', 'set'])) {
                $v['type'] = 'select';
            }
            if (in_array($v['DATA_TYPE'], ['tinytext', 'smalltext', 'text', 'mediumtext', 'longtext', 'json'])) {
                $v['type'] = 'textarea';
            }
            if (in_array($v['DATA_TYPE'], ['timestamp', 'datetime'])) {
                $v['type'] = 'datetime';
            }
            if (in_array($v['DATA_TYPE'], ['date'])) {
                $v['type'] = 'date';
            }
            if (in_array($v['DATA_TYPE'], ['year'])) {
                $v['type'] = 'year';
            }
            if (in_array($v['DATA_TYPE'], ['time'])) {
                $v['type'] = 'time';
            }
            $fieldsName = $v['COLUMN_NAME'];
            // 指定后缀说明也是个时间字段
            if ($this->hasSuffix($fieldsName, $this->config['fileSuffix'])) {
                $comment = explode('=', $v['comment']);
                $v['comment'] = $comment[0];
                $v['type'] = "file";
                if (isset($comment[1]) and $comment[1] > 1) {
                    $v['type'] = "files";
                }
            }
            // 指定后缀结尾且类型为varchar || char,文件上传
            if ($this->hasSuffix($fieldsName, $this->config['imageSuffix']) &&
                (($v['DATA_TYPE'] == 'varchar') || $v['DATA_TYPE'] == 'char')) {
                $comment = explode('=', $v['comment']);
                $v['comment'] = $comment[0];
                $v['type'] = "image";
                if (isset($comment[1]) and $comment[1] > 1) {
                    $v['type'] = "images";
                }
            }
            // 指定后缀说明也是个排序字段
            if ($this->hasSuffix($fieldsName, $this->config['sortSuffix'])) {
                $v['type'] = "number";
            }
            // 指定后缀说明也是个tags字段
            if ($this->hasSuffix($fieldsName, $this->config['tagsSuffix'])) {
                $v['type'] = "tags";
            }
            if ($this->hasSuffix($fieldsName, $this->config['urlSuffix'])) {
                $v['type'] = "url";
            }
            //指定后缀结尾 且类型为text系列的字段 为富文本编辑器
            if ($this->hasSuffix($fieldsName, $this->config['editorSuffix'])
                && in_array($v['DATA_TYPE'], ['longtext', 'mediumtext', 'text', 'smalltext', 'tinytext'])) {
                $v['type'] = "editor";
            }
            //指定后缀结尾 下来
            if ($this->hasSuffix($fieldsName, $this->config['selectSuffix'])
                && in_array($v['DATA_TYPE'], ['enum', 'set', 'varchar', 'char'])) {
                $v['type'] = "select";
            }
            // 指定后缀说明也是个时间字段
            if ($this->hasSuffix($fieldsName, $this->config['timeSuffix'])
                and $v['type'] != 'time' and $v['type'] != 'year'
                and $v['type'] != 'date'
            ) {
                $v['type'] = 'datetime';
            }
            // 指定后缀结尾且类型为enum,单选框
            if ($this->hasSuffix($fieldsName, $this->config['enumRadioSuffix']) && $v['DATA_TYPE'] == 'enum'
                &&
                $v['COLUMN_DEFAULT'] !== '' && $v['COLUMN_DEFAULT'] !== null
            ) {
                $v['type'] = "radio";
            }
            // 指定后缀结尾且类型为int,说明是radio
            if ($this->hasSuffix($fieldsName, $this->config['enumRadioSuffix']) && $v['DATA_TYPE'] == 'tinyint'
                &&
                $v['COLUMN_DEFAULT'] !== '' && $v['COLUMN_DEFAULT'] !== null
            ) {
                $v['type'] = "radio";
            }
            // 指定后缀结尾且类型为icon 颜色选择
            if ($this->hasSuffix($fieldsName, $this->config['iconSuffix']) && $v['DATA_TYPE'] == 'char') {
                $v['type'] = "icon";
            }
            // 指定后缀结尾且类型为set,说明是个复选框
            if ($this->hasSuffix($fieldsName, $this->config['setCheckboxSuffix']) && $v['DATA_TYPE'] == 'set') {
                $v['type'] = "checkbox";
            }
            // 指定后缀结尾且类型为char或tinyint且长度为1,说明是个Switch复选框
            if ($this->hasSuffix($fieldsName, $this->config['switchSuffix']) &&
                ($v['DATA_TYPE'] == 'tinyint' || $v['DATA_TYPE'] == 'int' || $v['COLUMN_TYPE'] == 'char(1)') &&
                $v['COLUMN_DEFAULT'] !== '' && $v['COLUMN_DEFAULT'] !== null) {
                $v['type'] = "switch";
            }
            //指定后缀结尾 且类型为input系列的字段 为颜色选择器
            if ($this->hasSuffix($fieldsName, $this->config['colorSuffix'])
                &&
                (($v['DATA_TYPE'] == 'varchar'
                    || $v['DATA_TYPE'] == 'char'))) {
                $v['type'] = "color";
            }
            //指定后缀结尾 且类型为number系列的字段 为其他表主键
            if ($this->hasSuffix($fieldsName, $this->config['priSuffix']) && (in_array($v['DATA_TYPE'], ['tinyint', 'smallint', 'mediumint', 'int', 'bigint', 'varchar', 'char']))) {
                $v['type'] = "_id";
                $assign[$v['name'] . 'List'] = '';
            }
            $lang .= $this->getlangStr($v);
            if (in_array($v['DATA_TYPE'], ['tinyint', 'set', 'enum']) and $v['type'] != '_id') {
                $comment = explode('=', $v['comment']);
                if (!in_array($v['name'], $this->config['ignoreFields'])) {
                    if ($comment && count($comment) != 2) {
                        $v['type'] = 'text';
                    } else {
                        if ($v['DATA_TYPE'] == 'tinyint') $v['type'] = 'radio';
                        $v['comment'] = $comment[0];
                        list($assign[$v['name'] . 'List'], $v['option']) = $this->getOptionStr($v['name'], $comment[1]);
                    }
                } else {
                    if ($v['name'] == 'status') {
                        $assign[$v['name'] . 'List'] = '[0=>"disabled",1=>"enabled"]';
                        $v['option'] = '{0:__("disabled"),1:__("enabled")}';
                        if (isset($comment[1])) list($assign[$v['name'] . 'List'], $v['option']) = $this->getOptionStr($v['name'], $comment[1]);
                    }
                    $v['comment'] = $comment[0];
                }
            }
            if ($v['name'] == 'delete_time') {

                $softDelete = $this->getSoftDelete($v);
            }

        }
        unset($v);
        $fieldsList = $tableField;
        $methodArr = explode(',', $this->method);
        if ($this->addon) {
            $prefix_url = $this->addon . '/' . str_replace('/', '.', $this->controllerNamePrefix);
        } elseif ($this->app != 'backend') {
            $prefix_url = $this->app . '/' . str_replace('/', '.', $this->controllerNamePrefix);
        } else {
            $prefix_url = str_replace('/', '.', $this->controllerNamePrefix);
        }
        $requests = '';
        $requestsRecycle = '';
        foreach ($methodArr as $k => $v) {
            if (!$this->softDelete && $v == 'recycle') continue;
            if ($v != 'refresh') {
                $space = $k == 0 ? '' : '                    ';
                if (!in_array($v, ['restore'])) {
                    $space = $k == 0 ? '' : '                    ';
                    $requests .= $space . $v . '_url:' . "'{$prefix_url}/{$v}'" . ',' . PHP_EOL;
                }
                if (in_array($v, ['recycle', 'restore', 'delete'])) {
                    $requestsRecycle .= $v . '_url:' . "'{$prefix_url}/{$v}'" . ',' . PHP_EOL . $space;
                }
            }
        }
        return [$tableComment,$fieldsList,$assign,$lang,$softDelete,$requests,$requestsRecycle];
    }

    /**
     * 获取软删除
     */
    protected function getSoftDelete($value)
    {
        $default = $value['value'] == '' ? 'null' : $value['value'];
        $str = 'use SoftDelete;' . PHP_EOL;
        $str .= '    protected $defaultSoftDelete = ' . $default . ';' . PHP_EOL;
        return $str;

    }


    /**
     * 设置属性
     * @return string
     */
    protected function modifyAttr($fieldList = '')
    {
        $fieldAttrData = '';
        $tpl = [
            $this->tplPath . 'attrtimeget.tpl',
            $this->tplPath . 'attrtimeset.tpl',
            $this->tplPath . 'attrmutiget.tpl',
            $this->tplPath . 'attrmutiset.tpl',
        ];
        $fieldList = $fieldsList?:$this->fieldsList;
        foreach ($fieldsList as $k => $vo) {
            if ($vo['COLUMN_KEY'] == 'PRI') continue;
            if (in_array($vo['name'], $this->config['ignoreFields']) and $vo['name'] != 'status') continue;
            $name = Str::studly($vo['name']);
            $method = ucfirst($name);
            switch ($vo['type']) {
                case "checkbox":
                case "_id":
                case "select":
                    if (strpos($vo['name'], '_ids') !== false ||
                        $vo['type'] == 'checkbox'
                        || ($vo['type'] == 'select' && in_array($vo['DATA_TYPE'], ['set', 'varchar', 'char']))) {
                        //生成关联表的模型
                        $getTpl = str_replace([
                            '{{$methodName}}',
                        ],
                            [$method,
                            ],
                            file_get_contents($tpl[2]));
                        $setTpl = str_replace([
                            '{{$methodName}}',
                        ],
                            [$method,
                            ],
                            file_get_contents($tpl[3]));
                        $fieldAttrData .= $getTpl . PHP_EOL . $setTpl;
                    }
                    break;
                case "timestamp":
                case "datetime":
                case "range":
                case "year":
                case "date":
                case "time":
                    if ($vo['DATA_TYPE'] == 'int') {
                        //生成关联表的模型
                        $getTpl = str_replace([
                            '{{$methodName}}',
                        ],
                            [$method,
                            ],
                            file_get_contents($tpl[0]));
                        $setTpl = str_replace([
                            '{{$methodName}}',
                        ],
                            [$method,
                            ],
                            file_get_contents($tpl[1]));
                        $fieldAttrData .= $getTpl . PHP_EOL . $setTpl;
                    }
                    break;

            }
        }
        return $fieldAttrData;
    }

    /**
     * @param $v
     * @return string
     */
    protected function getOptionStr($name, $op)
    {
        $name = Str::studly($name);
        $op = trim(trim($op, '('), ')');
        $option = explode(',', (trim(trim($op, '['), ']')));
        $optionsArrStr = "[";
        $optionObjStr = '{';
        foreach ($option as $k => $v) {
            $ops = explode(":", $v);
            $optionsArrStr .= "'" . $ops[0] . "'=>'" . $name . ' ' . $ops[0] . "',";
            $optionObjStr .= "'" . $ops[0] . "':'" . $name . ' ' . $ops[0] . "',";
        }
        $optionsArrStr .= "]";
        $optionObjStr .= "}";
        return [$optionsArrStr, $optionObjStr];
    }

    /**
     * 获取翻译字段
     * @param $v
     * @return string[]
     */
    protected function getLangStr($v)
    {
        $optionsLangStr = "";
        $comment = explode('=', $v['comment']);
        $optionsLangStr .= "'" . Str::studly($v['name']) . "'=>'" . $comment[0] . "'," . PHP_EOL;
        if (isset($comment[1])) {
            if (strpos($comment[1], ':') !== false) { //判断是否是枚举等类型
                $op = trim(trim($comment[1], '('), ')');
                $option = explode(',', (trim(trim($op, '['), ']')));
                foreach ($option as $kk => $vv) {
                    $vv = str_replace("：", ':', $vv);
                    $opArr = explode(':', $vv);
//                    isset($opArr[1])?$optionsLangStr.="'" . Str::studly($v['name']). ' '. $opArr[0]. "'=>'" . $opArr[1] . "',".PHP_EOL:'';
                    $optionsLangStr .= "'" . Str::studly($v['name']) . ' ' . $opArr[0] . "'=>'" . $opArr[1] . "'," . PHP_EOL;
                }
            }
        }
        $optionsLangStr .= "";
        return $optionsLangStr;
    }

    /**
     * 否符合指定后缀
     * @param $field
     * @param $suffixArr
     * @return bool
     */
    protected function hasSuffix($field, $suffix)
    {
        $suffix = is_array($suffix) ? $suffix : explode(',', $suffix);
        foreach ($suffix as $v) {
            if (strpos($field, $v) !== false) {
                return true;
            }
        }
        return false;
    }

}
