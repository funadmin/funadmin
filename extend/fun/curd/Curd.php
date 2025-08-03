<?php
/**
 * FunAdmin
 * ============================================================================
 * 版权所有 2017-2028 FunAdmin，并保留所有权利。
 * 网站地址: https://www.FunAdmin.com
 * ----------------------------------------------------------------------------
 * 采用最新Thinkphp8实现
 * ============================================================================
 * Author: yuege
 * Date: 2020/9/21
 */

namespace fun\curd;

use fun\curd\service\CurdService;
use think\console\Command;
use think\console\Input;
use think\console\input\Option;
use think\console\Output;
use think\facade\Cache;
use think\facade\Db;
use think\helper\Arr;
use think\helper\Str;

/**
 * Class Curd
 * @package app\backend\command
 * 功能待完善
 */
class Curd extends Command
{

    protected $connect;
    protected $config = [
        'driver'        => 'mysql',
        'database'      => 'funadmin',
        'prefix'        => '',
        'app'           => 'backend',
        'common'        =>  0,
        'table'         => '',
        'controller'    => '',
        'model'         => '',
        'validate'      => '',
        'method'        => '',
        'priKey'        =>'id',
        'joinTable'     => [],
        'joinModel'     => [],
        'joinName'      => [],
        'joinMethod'    => [],
        'joinPrimaryKey' => [],
        'joinForeignKey' => [],
        'joinFields'    => [],
        'joinSelectFields'  => [],
        'joinModelList'   => [],//关联模型数据
        'page'          => 'true',
        'limit'         => 15,
        'menu'          => 0,
        'force'         => false,
        'jump'          => true,
        'nodeType'      => '__u',
        'keepFields'    => ['admin_id', 'member_id'],//保留字段
        'sortFields'    =>['id'],
        'statusFields'  =>[],
        'fieldsList'    => [],//显示的字段
        'fieldsAttrList' => [],//字段属性列表
        'formFields'    => [],//添加的字段
        'ignoreFields'  => ['create_time', 'update_time', 'delete_time'],//忽略字段
    ];

    // 后缀判断
    protected $suffixMap = [
        'tagsSuffix' => ['tags', 'tag'],//识别为tag类型
        'urlSuffix' => ['url', 'urls'],//识别为url类型
        'fileSuffix' => ['file', 'files', 'path', 'paths'],//识别为文件字段
        'priSuffix' => ['_id'],//识别为别的表的主键
        'priSelectsSuffix' => ['_ids'],//识别为别的表的主键
        'sortSuffix' => ['sort','orderby','weight'],//排序
        'imageSuffix' => ['image', 'images', 'thumb', 'thumbs', 'avatar', 'avatars','picture', 'pictures'],//识别为图片字段
        'editorSuffix' => ['editor', 'content'],//识别为编辑器字段
        'iconSuffix' => ['icon'],//识别为图标字段
        'colorSuffix' => ['color'],//颜色
        'jsonSuffix' => ['json'],//识别为json字段
        'arraySuffix' => ['form','array'],//识别为数组字段
        'timeSuffix' => ['time', 'date', 'datetime'],//识别为日期时间字段
        'switchSuffix' => ['switch'],//开关
        'checkboxSuffix' => ['checkbox','checkboxs','radios','states','state','data'],//多选
        'radioSuffix' => ['radio','state','status','data'],//单选
        'selectSuffix' => ['select','enum','data'],//下拉框
        'selectsSuffix' => ['selects','set','data'],//下拉多选
        'citySuffix' => ['city'],//城市
        'priceSuffix' =>['price','amount'], //价格
    ];

    //关键保留字
    protected $internalKeywords = [
        'abstract',
        'and',
        'array',
        'as',
        'break',
        'callable',
        'case',
        'catch',
        'class',
        'clone',
        'const',
        'continue',
        'declare',
        'default',
        'die',
        'do',
        'echo',
        'else',
        'elseif',
        'empty',
        'enddeclare',
        'endfor',
        'endforeach',
        'endif',
        'endswitch',
        'endwhile',
        'eval',
        'exit',
        'extends',
        'final',
        'for',
        'foreach',
        'function',
        'global',
        'goto',
        'if',
        'implements',
        'include',
        'include_once',
        'instanceof',
        'insteadof',
        'interface',
        'isset',
        'list',
        'namespace',
        'new',
        'or',
        'print',
        'private',
        'protected',
        'public',
        'require',
        'require_once',
        'return',
        'static',
        'switch',
        'throw',
        'trait',
        'try',
        'unset',
        'use',
        'var',
        'while',
        'xor',
    ];

    protected function configure()
    {
        $this->setName('curd')
            ->addOption('driver', '', Option::VALUE_OPTIONAL, '数据库', 'mysql')
            ->addOption('table', 't', Option::VALUE_REQUIRED, '表名', null)
            ->addOption('controller', 'c', Option::VALUE_OPTIONAL, '控制器名', null)
            ->addOption('model', 'm', Option::VALUE_OPTIONAL, '模型名', null)
            ->addOption('validate', 'l', Option::VALUE_OPTIONAL, '验证器', null)
            ->addOption('sortFields', '', Option::VALUE_OPTIONAL, '排序字段', null)
            ->addOption('keepFields', '', Option::VALUE_OPTIONAL, '状态字段', null)
            ->addOption('statusFields', '', Option::VALUE_OPTIONAL, '状态字段', null)
            ->addOption('formFields', '', Option::VALUE_OPTIONAL | Option::VALUE_IS_ARRAY, '表单字段', null)
            ->addOption('fieldsList', 'i', Option::VALUE_OPTIONAL|Option::VALUE_IS_ARRAY, '显示列表字段', null)
            ->addOption('fieldsAttrList', '', Option::VALUE_OPTIONAL|Option::VALUE_IS_ARRAY, '字段属性数组', null)
            ->addOption('ignoreFields', 'g', Option::VALUE_OPTIONAL | Option::VALUE_IS_ARRAY, '忽略的字段', null)
            ->addOption('joinTable', 'j', Option::VALUE_OPTIONAL | Option::VALUE_IS_ARRAY, '关联表名', null)
            ->addOption('joinModel', 'o', Option::VALUE_OPTIONAL | Option::VALUE_IS_ARRAY, '关联模型', null)
            ->addOption('joinName', 'e', Option::VALUE_OPTIONAL | Option::VALUE_IS_ARRAY, '关联模型名字', null)
            ->addOption('joinMethod', 'w', Option::VALUE_OPTIONAL | Option::VALUE_IS_ARRAY, '关系表方式 hasone or belongsto等', null)
            ->addOption('joinPrimaryKey', 'p', Option::VALUE_OPTIONAL | Option::VALUE_IS_ARRAY, '关联主键', null)
            ->addOption('joinForeignKey', 'k', Option::VALUE_OPTIONAL | Option::VALUE_IS_ARRAY, '关联外键', null)
            ->addOption('joinFields', 's', Option::VALUE_OPTIONAL | Option::VALUE_IS_ARRAY, '关联表显示字段', null)
            ->addOption('joinSelectFields', '', Option::VALUE_OPTIONAL | Option::VALUE_IS_ARRAY, '关联表下拉显示字段', null)
            ->addOption('tagsSuffix', '', Option::VALUE_OPTIONAL | Option::VALUE_IS_ARRAY, '标签', null)
            ->addOption('urlSuffix', '', Option::VALUE_OPTIONAL | Option::VALUE_IS_ARRAY, '链接', null)
            ->addOption('priSuffix', '', Option::VALUE_OPTIONAL | Option::VALUE_IS_ARRAY, '主键', null)
            ->addOption('priSelectsSuffix', '', Option::VALUE_OPTIONAL | Option::VALUE_IS_ARRAY, '主键多选', null)
            ->addOption('sortSuffix', '', Option::VALUE_OPTIONAL | Option::VALUE_IS_ARRAY, '排序', null)
            ->addOption('editorSuffix', '', Option::VALUE_OPTIONAL | Option::VALUE_IS_ARRAY, '编辑器', null)
            ->addOption('colorSuffix', '', Option::VALUE_OPTIONAL | Option::VALUE_IS_ARRAY, '颜色', null)
            ->addOption('jsonSuffix', '', Option::VALUE_OPTIONAL | Option::VALUE_IS_ARRAY, 'JSON', null)
            ->addOption('imageSuffix', '', Option::VALUE_OPTIONAL | Option::VALUE_IS_ARRAY, '图片后缀', null)
            ->addOption('fileSuffix', '', Option::VALUE_OPTIONAL | Option::VALUE_IS_ARRAY, '文件后缀', null)
            ->addOption('timeSuffix', '', Option::VALUE_OPTIONAL | Option::VALUE_IS_ARRAY, '时间后缀', null)
            ->addOption('switchSuffix', '', Option::VALUE_OPTIONAL | Option::VALUE_IS_ARRAY, '开关后缀', null)
            ->addOption('radioSuffix', '', Option::VALUE_OPTIONAL | Option::VALUE_IS_ARRAY, '单选后缀', null)
            ->addOption('checkboxSuffix', '', Option::VALUE_OPTIONAL | Option::VALUE_IS_ARRAY, '多选后缀', null)
            ->addOption('citySuffix', '', Option::VALUE_OPTIONAL | Option::VALUE_IS_ARRAY, '城市后缀', null)
            ->addOption('priceSuffix', '', Option::VALUE_OPTIONAL | Option::VALUE_IS_ARRAY, '价格后缀', null)
            ->addOption('iconSuffix', '', Option::VALUE_OPTIONAL | Option::VALUE_IS_ARRAY, '图标后缀', null)
            ->addOption('jsonSuffix', '', Option::VALUE_OPTIONAL | Option::VALUE_IS_ARRAY, 'json后缀', null)
            ->addOption('arraySuffix', '', Option::VALUE_OPTIONAL | Option::VALUE_IS_ARRAY, '数组后缀', null)
            ->addOption('selectSuffix', '', Option::VALUE_OPTIONAL | Option::VALUE_IS_ARRAY, '下拉选择后缀', null)
            ->addOption('selectsSuffix', '', Option::VALUE_OPTIONAL | Option::VALUE_IS_ARRAY, '下拉多选后缀', null)
            ->addOption('method', '', Option::VALUE_OPTIONAL, '方法', 'index,add,edit,destroy,delete,recycle,import,export,modify,restore')
            ->addOption('page', '', Option::VALUE_OPTIONAL, '是否页', 1)
            ->addOption('limit', '', Option::VALUE_OPTIONAL , '分页大小', 15)
            ->addOption('menu', 'u', Option::VALUE_OPTIONAL, '菜单', 0)
            ->addOption('menuname', '', Option::VALUE_OPTIONAL, '菜单名称', null)
            ->addOption('force', 'f', Option::VALUE_OPTIONAL, '强制覆盖或删除', 0)
            ->addOption('delete', 'd', Option::VALUE_OPTIONAL, '删除', 0)
            ->addOption('jump', '', Option::VALUE_OPTIONAL, '跳过重复文件', 1)
            ->addOption('app', '', Option::VALUE_OPTIONAL, '是否是APP', 'backend')
            ->addOption('common', '', Option::VALUE_OPTIONAL, '模型是否是放在common', 0)
            ->setDescription('Curd Command');
    }

    protected function execute(Input $input, Output $output)
    {
        $this->config['driver'] = $input->getOption('driver');
        $this->config['app'] = $input->getOption('app');
        $this->config['common'] = $input->getOption('common');
        $this->config['table'] = $input->getOption('table');
        $this->config['controller'] = $input->getOption('controller')??$this->config['table'];
        $this->config['model'] = $input->getOption('model')??$this->config['controller'];
        $this->config['validate']  = $input->getOption('validate')??$this->config['controller'];
        $this->config['method']  = $input->getOption('method');
        $this->config['force'] = $input->getOption('force');//强制覆盖或删除
        $this->config['delete'] = $input->getOption('delete');
        $this->config['menu'] = $input->getOption('menu');
        $this->config['menuname'] = $input->getOption('menuname');
        $this->config['jump'] = $input->getOption('jump');
        $this->config['page'] = $input->getOption('page');
        $this->config['limit'] = $input->getOption('limit');
        $this->config['keepFields'] = array_merge($this->config['keepFields'],$input->getOption('keepFields')??[]);
        $this->config['sortFields'] = array_merge($this->config['sortFields'],$input->getOption('sortFields')??[]);
        $this->config['statusFields'] = array_merge($this->config['statusFields'],$input->getOption('statusFields')??[]);
        $this->config['fieldsList'] = array_merge($this->config['fieldsList'],$input->getOption('fieldsList')??[]);
        $this->config['formFields'] = array_merge($this->config['formFields'],$input->getOption('formFields')??[]);
        $this->config['fieldsAttrList'] = array_merge($this->config['fieldsAttrList'],$input->getOption('fieldsAttrList')??[]);
        $this->config['ignoreFields'] = array_merge($this->config['ignoreFields'],$input->getOption('ignoreFields')??[]);

        $this->config['joinTable'] = array_merge($this->config['joinTable'],$input->getOption('joinTable')??[]);
        $this->config['joinModel'] = array_merge($this->config['joinModel'],$input->getOption('joinModel')??[]);
        $this->config['joinName'] = array_merge($this->config['joinName'],$input->getOption('joinName')??[]);
        $this->config['joinMethod'] = array_merge($this->config['joinMethod'],$input->getOption('joinMethod')??[]);
        $this->config['joinPrimaryKey'] = array_merge($this->config['joinPrimaryKey'],$input->getOption('joinPrimaryKey')??[]);
        $this->config['joinForeignKey'] = array_merge($this->config['joinForeignKey'],$input->getOption('joinForeignKey')??[]);
        $this->config['joinFields'] = array_merge($this->config['joinFields'],$input->getOption('joinFields')??[]);
        $this->config['joinSelectFields'] = array_merge($this->config['joinSelectFields'],$input->getOption('joinSelectFields')??[]);

        $this->suffixMap['tagsSuffix'] = array_merge($this->suffixMap['tagsSuffix'],$input->getOption('tagsSuffix')??[]);
        $this->suffixMap['urlSuffix'] = array_merge($this->suffixMap['urlSuffix'],$input->getOption('urlSuffix')??[]);
        $this->suffixMap['priSuffix'] = array_merge($this->suffixMap['priSuffix'],$input->getOption('priSuffix')??[]);
        $this->suffixMap['priSelectsSuffix'] = array_merge($this->suffixMap['priSelectsSuffix'],$input->getOption('priSelectsSuffix')??[]);
        $this->suffixMap['sortSuffix'] = array_merge($this->suffixMap['sortSuffix'],$input->getOption('sortSuffix')??[]);
        $this->suffixMap['editorSuffix'] = array_merge($this->suffixMap['editorSuffix'],$input->getOption('editorSuffix')??[]);
        $this->suffixMap['colorSuffix'] = array_merge($this->suffixMap['colorSuffix'],$input->getOption('colorSuffix')??[]);
        $this->suffixMap['jsonSuffix'] = array_merge($this->suffixMap['jsonSuffix'],$input->getOption('jsonSuffix')??[]);
        $this->suffixMap['imageSuffix'] = array_merge($this->suffixMap['imageSuffix'],$input->getOption('imageSuffix')??[]);
        $this->suffixMap['fileSuffix'] = array_merge($this->suffixMap['fileSuffix'],$input->getOption('fileSuffix')??[]);
        $this->suffixMap['timeSuffix'] = array_merge($this->suffixMap['timeSuffix'],$input->getOption('timeSuffix')??[]);
        $this->suffixMap['switchSuffix'] = array_merge($this->suffixMap['switchSuffix'],$input->getOption('switchSuffix')??[]);
        $this->suffixMap['radioSuffix'] = array_merge($this->suffixMap['radioSuffix'],$input->getOption('radioSuffix')??[]);
        $this->suffixMap['checkboxSuffix'] = array_merge($this->suffixMap['checkboxSuffix'],$input->getOption('checkboxSuffix')??[]);
        $this->suffixMap['citySuffix'] = array_merge($this->suffixMap['citySuffix'],$input->getOption('citySuffix')??[]);
        $this->suffixMap['iconSuffix'] = array_merge($this->suffixMap['iconSuffix'],$input->getOption('iconSuffix')??[]);
        $this->suffixMap['jsonSuffix'] = array_merge($this->suffixMap['jsonSuffix'],$input->getOption('jsonSuffix')??[]);
        $this->suffixMap['arraySuffix'] = array_merge($this->suffixMap['arraySuffix'],$input->getOption('arraySuffix')??[]);
        $this->suffixMap['selectSuffix'] = array_merge($this->suffixMap['selectSuffix'],$input->getOption('selectSuffix')??[]);
        $this->suffixMap['selectsSuffix'] = array_merge($this->suffixMap['selectsSuffix'],$input->getOption('selectsSuffix')??[]);

        try {
            $this->config['prefix'] = config('database.connections.' . $this->config['driver'] . '.prefix');
            $this->config['database'] = config('database.connections' . '.' . $this->config['driver'] . '.database');
            $this->connect =  Db::connect($this->config['driver']);
            $this->config['table'] = stripos($this->config['table'], $this->config['prefix']) === 0 ? substr($this->config['table'], strlen($this->config['prefix'])) : $this->config['table'];
            $this->config['controller'] = str_replace($this->config['prefix'], '', $this->config['controller']);
            $this->config['model'] = str_replace($this->config['prefix'], '', $this->config['model']);
            $this->config['validate'] = str_replace($this->config['prefix'], '', $this->config['validate']);
            $this->config['page'] = (!isset($this->config['page']) ||  $this->config['page']==null ||  $this->config['page'] == 1 ) ? "true" : 'false';
            if (empty($this->config['table'])) {
                throw new \Exception('main table can\'t be empty');
            }
            //系统表无法生成，防止后台错乱
            if (in_array( $this->config['table'],getSystemTable())) {
                throw new \Exception('system table can\'t be curd');
            }
            //控制器
            $controllerData = $this->getMvcData($this->config['app'], $this->config['controller'],'controller',false);
            //模型
            $modelData = $this->getMvcData($this->config['app'], $this->config['model'],'model',$this->config['common']);
            //验证器
//            $validateData = $this->getMvcData($this->config['app'], $this->config['validate'],'validate',$this->config['common']);
            if(!$this->config['delete']){
                if(file_exists($controllerData['file']) && !$this->config['force']){
                    throw new \Exception("控制器已经存在,请加上force参数");
                }
                if(file_exists( $modelData['file']) && !$this->config['force']){
                    throw new \Exception("模型已经存在,请加上force参数");
                }
//                if(file_exists( $validateData['file']) && !$this->config['force']){
//                    throw new \Exception("验证器已经存在,请加上force参数");
//                }
            }
            // 原始文件路径
            $filePath = $controllerData['file'];
            // 1. 替换 controller 为 view
            $filePath = str_replace('/controller/', '/view/', $filePath);
            // 2. 提取文件名并去除扩展名
            $fileName = pathinfo($filePath, PATHINFO_FILENAME);
            // 3. 将驼峰命名转换为蛇形命名（小写且用下划线分隔）
            $newFileName = strtolower(preg_replace('/(?<!^)[A-Z]/', '_$0', $fileName));
            // 4. 构建新路径
            $viewPath = dirname($filePath) . '/' . $newFileName;
            $langPath = str_replace('/view/', '/lang/zh-cn/', $viewPath);
            $langFile =  $langPath . '.php';
            $indexFile =  $viewPath . '/index.html';
            $addFile =  $viewPath . '/add.html';
            $jsFile = root_path("public/static/{$this->config['app']}/js").strtolower(implode('/', $controllerData['parseArr'])).'.js';
            $fileList = [
                $controllerData['file'],
                $modelData['file'],
//                $validateData['file'],
                $langFile,
                $indexFile,
                $addFile,
                $jsFile,
            ];
            $dirList = [
                dirname($controllerData['file']),
                dirname($modelData['file']),
//                dirname($validateData['file']),
                dirname($langFile),
                dirname($indexFile),
                dirname($addFile),
                dirname($jsFile),
            ];
            if($this->config['delete'] && $this->config['force']){
                $this->deleteFile($fileList,$dirList);
                //删除菜单
                if ($this->config['menu']) {
                    \think\facade\Console::call('menu', ["--controller={$this->config['controller']}",'--app='.$this->config['app'],'--menuname='.$this->config['menuname'], "--delete=1", "--force=1"]);
                }
            }
            //从数据库中获取表字段信息
            $sql = "SELECT * FROM `information_schema`.`columns` "
                . "WHERE TABLE_SCHEMA = ? AND table_name = ? "
                . "ORDER BY ORDINAL_POSITION";
            $joinTables = $this->config['joinTable']??[];
            $joinArrData = [];
            $langList = [];
            foreach ($joinTables as $k => &$joinTable) {
                $joinTable = stripos($joinTable, $this->config['prefix']) === 0 ? substr($joinTable, strlen($this->config['prefix'])) : $joinTable;
                if(!$this->tableExists($joinTable)){
                    throw new \Exception( '表不存在:'.$joinTable);
                }
                $fullJoinTable = $this->config['prefix'].$joinTable;
                $joinColumnList = $this->connect->query($sql, [$this->config['database'], $fullJoinTable]);
                $fields = array_column($joinColumnList,'COLUMN_NAME');
                $joinArr = [
                    'joinTable' => $joinTable,
                    'joinName' => $this->config['joinName']??'',
                    'joinModel' => $this->config['joinModel'][$k]??$joinTable,
                    'joinMethod' => $this->config['joinMethod'][$k]??'hasOne',
                    'joinForeignKey' => $this->config['joinForeignKey'][$k]??'id',
                    'joinPrimaryKey' => $this->config['joinPrimaryKey'][$k]??$joinTable.'_id',
                    'joinFields' => $this->config['joinFields'][$k]??'',
                    'joinSelectField' => $this->config['joinSelectFields'][$k]??'',
                    'joinColumnList' => $joinColumnList,
                ];
                //    if(!$joinArr['joinFields']){
                //        $joinArr['joinFields'] = $joinArr['joinSelectFields'];
                //    }
                $joinFileData = $this->getMvcData($this->config['app'], $joinArr['joinModel'], 'model',$this->config['common']);
                $joinArr['joinName'] =  $joinArr['joinName']?:$joinFileData['name'];
                if(!$joinArr['joinSelectField']){
                    if(in_array('title',$fields)){
                        $joinArr['joinSelectField'] = 'title';
                    }elseif(in_array('name',$fields)){
                        $joinArr['joinSelectField'] = 'name';
                    }
                }
                $joinArr = array_merge($joinArr,$joinFileData);
                if($this->config['delete'] && $this->config['force']){
                    $this->deleteFile([$joinFileData['file']], [dirname($joinFileData['file'])]);
                    continue;
                }
                $modelAttrData = [];
                $modelAttrData = $this->getModelAttrData($joinColumnList,'join');
                $joinArr['priKey'] = $modelAttrData['primaryKey']??'id';
                $joinArr['langList'] = $modelAttrData['langList']??[];
                $langList = array_merge($langList,$joinArr['langList']);
                $joinArrData[] = $joinArr;
                $tableName= '';
                if($joinArr['joinTable'] != Str::snake($joinArr['joinName']) ){
                    $tableName = $joinArr['joinTable'];
                }
                $modelAttrArr = [];
                $appendsListStr = $modelAttrData['appendsList']?implode(','.PHP_EOL,$modelAttrData['appendsList']):'';
                $modelAttrArr = [
                    'namespace' => $joinFileData['namespace'],
                    'name' => $joinFileData['name'],
                    'softDelete' => $modelAttrData['softDelete'],
                    'connection' => $this->config['driver'] == 'mysql' ? "" : 'rotected \$connection = "' . $this->config['driver'] . '";',
                    'primaryKey' => $modelAttrData['primaryKey'] ? 'protected $pk = \'' . $modelAttrData['primaryKey'] . '\';' : '',
                    'tableName' => $tableName ? 'protected $name = \'' . $tableName . '\';' : '',
                    'attrsList' => $modelAttrData['attrsList'] ?? '',
                    'appendsList' => $appendsListStr,
                ];
                $this->makeFile('model',$modelAttrArr,$joinFileData['file'],'php');
            }
            $this->config['joinModelList'] = $joinArrData;
            //加载主表的列
            $fullTable = $this->config['prefix'].$this->config['table'];
            $tablecolumnList = $this->connect->query($sql, [$this->config['database'], $fullTable]);
            $sql = "SELECT TABLE_COMMENT FROM INFORMATION_SCHEMA.TABLES  WHERE TABLE_NAME = ? ";
            $tableComment = $this->connect->query($sql, [$fullTable] );
            // 防止 Undefined array key 0 错误
            $tableComment = !empty($tableComment) && isset($tableComment[0]['TABLE_COMMENT']) ? $tableComment[0]['TABLE_COMMENT'] : '';
            $modelAttrData = $this->getModelAttrData($tablecolumnList);

            $tableName = '';
            if($this->config['table'] != Str::snake($modelData['name']) ){
                $tableName = $this->config['table'];
            }
            if(!empty($joinModelAttrArr)){
                $modelAttrData['attrsList'] = array_merge($modelAttrData['attrsList'],$joinModelAttrArr);
            }
            $appendsListStr = $modelAttrData['appendsList']?implode(','.PHP_EOL,$modelAttrData['appendsList']):'';
            $modelArr = [
                'namespace' => $modelData['namespace'],
                'name' => $modelData['name'],
                'softDelete' => $modelAttrData['softDelete'],
                'connection' => $this->config['driver'] == 'mysql' ? "" : 'rotected \$connection = "' . $this->config['driver'] . '";',
                'primaryKey' => $modelAttrData['primaryKey'] ? 'protected $pk = \'' . $modelAttrData['primaryKey'] . '\';' : '',
                'tableName' => $tableName ? 'protected $name = \'' . $tableName . '\';' : '',
                'methodsList' => $modelAttrData['methodsList'] ?? '',
                'attrsList' => $modelAttrData['attrsList'] ?? '',
                'appendsList' => $appendsListStr,
            ];
            $controllerArr = [
                'namespace' => $controllerData['namespace'],
                'namespaceModel' => $modelData['namespace'],
                'modelName' => $modelData['name'],
                'name' => $controllerData['name'],
                'limit' => $this->config['limit'],
                'layout' =>  $this->config['app']=='backend'?'layout/main':'../../backend/view/layout/main',
                'tableComment' => $tableComment,
                'indexMethod' => '',
                'recycleMethod' => '',
                'assignList' => $this->getAssignList($modelAttrData['assignList'],'php'),
            ];
            $relationSearch = '';
            if(!empty($joinArrData)){
                // $relationSearch = '$this->relationSearch = true;';
                $withMethod = [];
                foreach($joinArrData as $k=>$v){
                    $withMethod[]= $v['joinName'];
                    // 根据关联类型确定主键和外键
                    if (in_array($v['joinMethod'],['hasOne','hasMany'])) {
                        list($joinPrimaryKey, $joinForeignKey) = array($v['joinForeignKey'], $v['joinPrimaryKey']);
                    } elseif($v['joinMethod'] == 'belongsTo') {
                        list($joinPrimaryKey, $joinForeignKey) = array($v['joinPrimaryKey'], $v['joinForeignKey']);
                    } else {
                        // 默认处理
                        $joinPrimaryKey = $v['joinPrimaryKey'];
                        $joinForeignKey = $v['joinForeignKey'];
                    }
                    $modelArr['attrsList'][] = <<<EOF
    public function {$v['joinName']}()
    {
        return \$this->{$v['joinMethod']}(\\{$v['namespace']}\\{$v['name']}::class,'{$joinForeignKey}','{$joinPrimaryKey}');
    }     
EOF;
                }

                $withJoin = "->withJoin(['" . implode("','", $withMethod) . "'])";
                $controllerArr['indexMethod'] = $this->replaceTemplate('index',['withJoin'=>$withJoin,'relationSearch'=>$relationSearch]);
                if($modelAttrData['softDelete']){
                    $controllerArr['recycleMethod'] = $this->replaceTemplate('recycle',['withJoin'=>$withJoin,'relationSearch'=>$relationSearch]);
                }
            }
//            $validateArr = [
//                'namespace' => $validateData['namespace'],
//                'name' => $validateData['name'],
//            ];
            $indexHtmlArr = [
                'assignList' => $this->getAssignList($modelAttrData['assignList'],'js'),
            ];
            $addHtmlArr = [
                'formFieldList' => $modelAttrData['formList'],
            ];
            $methodArr = explode(',', $this->config['method']);
            $prefix_url = $this->getPrefixUrl(implode('.',$controllerData['parseArr']));
            $requests = [];
            $requestsRecycle = [];
            foreach ($methodArr as $k => $v) {
                if (!$modelAttrData['softDelete'] && $v == 'recycle') continue;
                if ($v != 'refresh') {
                    if (!in_array($v, ['restore'])) {
                        $requests[] = <<<EOF
                    '{$v}_url': '{$prefix_url}/{$v}' + location.search
EOF;
                    }
                    if (in_array($v, ['recycle', 'restore', 'delete'])) {
                        $requestsRecycle[] = <<<EOF
                    '{$v}_url': '{$prefix_url}/{$v}' + location.search
EOF;
                    }
                }
            }
            $toolbar = "'refresh','add','destroy','import','export'";
            if ($modelAttrData['softDelete']) {
                $toolbar = "'refresh','add','delete','import','export','recycle'";
            }
            $primaryKey = $modelAttrData['primaryKey']?'primaryKey:"'.$modelAttrData['primaryKey'].'",':'';
            $jsArr = [
                'requests' => implode("," . PHP_EOL, $requests),
                'primaryKey' => $primaryKey,
                'toolbar' => $toolbar,
                'jsCols' => implode("," .PHP_EOL,$modelAttrData['jsCols']),
                'limit' => $this->config['limit'],
                'page' => $this->config['page'],
                'jsRecycle' => $modelAttrData['softDelete'] ? $this->replaceTemplate('recycle',
                    [
                        'requests' => implode("," . PHP_EOL, $requestsRecycle),
                        'primaryKey' => $primaryKey,
                        'jsCols' => implode("," .PHP_EOL,$modelAttrData['jsColsRecycle']),
                        'limit' => $this->config['limit'],
                        'page' => $this->config['page']
                    ], 'js') : '',
            ];
            $langList = array_unique(array_merge($modelAttrData['langList'],$langList));
            $langArr['langList'] = implode(','.PHP_EOL,$langList);
            $this->makeFile('controller',$controllerArr,$controllerData['file'],'php');
            $this->makeFile('model',$modelArr,$modelData['file'],'php');
//            $this->makeFile('validate',$validateArr,$validateData['file'],'php');
            $this->makeFile('lang',$langArr,$langFile,'php');
            $this->makeFile('index',$indexHtmlArr,$indexFile,'html');
            $this->makeFile('add',$addHtmlArr,$addFile,'html');
            $this->makeFile('js',$jsArr,$jsFile,'js');
            //添加菜单
            if ($this->config['menu']) {
                \think\facade\Console::call('menu', ["--controller={$this->config['controller']}",'--app='.$this->config['app'],'--menuname='.$this->config['menuname'], "--delete=1", "--force=1"]);
            }
            $output->info('make success');
        }catch (\Exception $e){
            $output->writeln('----------------');
            $output->error($e->getMessage());
            $output->writeln('----------------');
        }
    }
    /**
     * 获取控制器、模型、验证器数据
     * @param string $app 应用
     * @param string $name 名称
     * @param string $type 类型
     * @return array 控制器、模型、验证器数据
     */
    public function getMvcData($app,$name,$type = 'controller',$common = false){
        if($common && $type!='controller'){
            $app = 'common';
        }
        $name  = parse_name($name,1);
        $name  = str_replace(['.', '/', '\\'], '/', $name);
        $arr   = explode('/', $name);
        $arr   = array_map(function($item) {
            return lcfirst($item); // 首字母小写
        }, $arr);
        $name = ucfirst(array_pop($arr));
        $parseArr  = $arr;
        array_push($parseArr, $name);
        //类名不能为内部关键字
        if (in_array(strtolower($name), $this->internalKeywords)) {
            throw new \Exception('Unable to use internal variable:' . $name);
        }
        // if($type == 'model'){
        //     $arr = [];
        //     $parseArr = [$name];
        // }
        $namespace = "app\\{$app}\\{$type}" . ($arr ? "\\" . implode("\\", $arr) : "");
        $file      = root_path('app/'.$app.DS.$type) . ($arr ? implode(DS, $arr) . DS : '') . $name . '.php';
        return compact('namespace','name','file','parseArr');
    }

    /**
     * getModelAttrData
     */
    protected function getModelAttrData(&$fieldList = [],$type='main'): array
    {
        $fieldData = [
            'langList' => [],
            'optionList' => [],
            'attrsList'=>[],
            'appendsList'=>[],
            'assignList' => [],
            'softDelete' => '',
            'primaryKey' => '',
            'jsCols' => [],
            'jsColsRecycle' => [],
            'formFieldList' => [],
        ];
        if (empty($fieldList)) {
            return $fieldData;
        }
        $fieldData['jsCols'][] = $fieldData['jsColsRecycle'][] = $this->buildFieldConfig(['checkbox'=>true]);
        foreach ($fieldList as $k => &$field) {
            $field['type'] = $this->getFieldType($field);
            if($field['COLUMN_KEY'] == 'PRI' && $field['COLUMN_NAME']!='id'){
                $fieldData['primaryKey'] = $field['COLUMN_NAME'];
                //主表
                if($type=='main'){
                    $this->config['priKey'] = $field['COLUMN_NAME'];
                }
            }
            if ($field['COLUMN_NAME'] == 'delete_time') {
                $fieldData['softDelete'] = $this->getSoftDelete($field);
            }
            $langList = $this->getArrayString($this->getLangArr($field));
            if(!empty($langList)){
                $fieldData['langList'][] = $langList;
            }
            $optionList =  $this->getOptionArr($field);
            if(!empty($optionList)){
                $fieldData['optionList'][] = $optionList;
            }
            list($attrsList,$appendsList,$assignList) = $this->getModelAttr($field);
            if(!empty($attrsList)){
                $fieldData['attrsList'][] = $attrsList;
            }
            if(!empty($appendsList)){
                $fieldData['appendsList'][] = $appendsList;
            }
            if(!empty($assignList)){
                $fieldData['assignList'][] = $assignList;
            }
            if($type=='main'){
                list($jsCols,$jsColsRecycle) = $this->getColsData($field,$fieldData['softDelete']);
                if(!empty($jsCols)){
                    $fieldData['jsCols'][] = $jsCols;
                }
                if(!empty($jsColsRecycle)){
                    $fieldData['jsColsRecycle'][] = $jsColsRecycle;
                }
                $formList = $this->getFormData($field);
                if(!empty($formList)){
                    $fieldData['formList'][] = $formList;
                }
            }
        }
        $operat = $fieldData['softDelete']?["edit", "destroy","delete"]:["edit", "destroy"];
        $fieldData['jsCols'][] = $this->buildFieldConfig(['minWidth'=>250,'title'=>__('Operat'),'init'=>'Table.init','templet'=>'Table.templet.operat','operat'=>$operat]);
        if($fieldData['softDelete']){
            $operat = ['restore','delete'];
            $fieldData['jsColsRecycle'][] = $this->buildFieldConfig(['minWidth'=>250,'title'=>__('Operat'),'init'=>'Table.init','templet'=>'Table.templet.operat','operat'=>$operat]);
        }
        return $fieldData;
    }

    /**
     * Summary of deleteFile
     * @param mixed $fileList
     * @param mixed $dirList
     * @return void
     */
    public function deleteFile($fileList,$dirList){
        foreach($fileList as $key=>$v){
            if(file_exists($v)){
                @unlink($v);
            }
        }
        foreach($dirList as $dir){
            if (is_dir($dir)) {
                // 使用 glob 函数获取文件夹内的文件和目录列表，排除 . 和 ..
                $files = glob($dir . '/*', GLOB_MARK);
                if (empty($files)) {
                    // 若文件夹为空，尝试删除文件夹
                    @rmdir($dir);
                }
            }
        }
    }
    /**
     * 获取字段类型的通用方法
     * @param array $field 字段信息
     * @return string
     */
    protected function getFieldType(array &$field): string
    {
        $field['required'] = $field['IS_NULLABLE'] == 'NO' ? 'required' : "";
        $fieldsName = $field['COLUMN_NAME'];
        $dataType = strtolower($field['DATA_TYPE']);
        $field['COLUMN_COMMENT'] = trim($field['COLUMN_COMMENT'], ' ');
        $field['COLUMN_COMMENT'] = str_replace(array("\r\n", "\r", "\n"), "", $field['COLUMN_COMMENT']);
        $field['COLUMN_COMMENT'] = str_replace('：', ':', $field['COLUMN_COMMENT']);
        $field['value'] = $field['COLUMN_DEFAULT'];
        $field['DATA_TYPE'] = strtolower($field['DATA_TYPE']);
        // 第二种模式：冒号在前，等号在内部键值对中
        if (preg_match('/(\w+):([\d]+=[^,]+(?:,[\d]+=[^,]+)*)/u', $field['COLUMN_COMMENT'])) {
            $comment = $field['COLUMN_COMMENT'] ? explode(':', $field['COLUMN_COMMENT']) : [Str::studly($field['COLUMN_NAME']),''];
            $comment[1] = str_replace('=',':',$comment[1]);
        }else{
            $comment = $field['COLUMN_COMMENT'] ? explode('=', $field['COLUMN_COMMENT']) : [Str::studly($field['COLUMN_NAME']),''];
        }
        if(in_array($field['DATA_TYPE'],['set', 'enum']) && empty($comment[1])){
            $comment[1] = str_replace([$field['DATA_TYPE'].'(',"'"],['(',""],$field['COLUMN_TYPE']);
        }
        if($field['DATA_TYPE'] == 'tinyint' && in_array($field['COLUMN_NAME'],['status','state']) && empty($comment[1])){
            $comment[1] = [0=>"disabled",1=>"enabled"];
        }
        $field['COMMENT_DATA'] = $comment;
        // 基础数据类型判断
        $typeMap = [
            'number' => ['tinyint', 'smallint', 'int', 'mediumint', 'bigint'],
            'float' =>['decimal', 'double', 'float'] ,
            'select' => ['enum', 'set'],
            'textarea' => ['tinytext', 'smalltext', 'text', 'mediumtext', 'longatext', 'json'],
            'datetime' => ['timestamp', 'datetime'],
            'date' => ['date'],
            'year' => ['year'],
            'time' => ['time'],
            'json' => ['json']
        ];
        foreach ($typeMap as $type => $dbTypes) {
            if (in_array($dataType, $dbTypes)) {
                $fieldType = $type;
                break;
            }
        }
        if($field['COLUMN_KEY'] == 'PRI'){
            $fieldType = 'id';
        }
        if($field['DATA_TYPE'] == 'tinyint' && in_array($field['COLUMN_NAME'],['status','state'])){
            $fieldType = 'radio';
        }
        $field['step']  = null;
        if(!empty($fieldType) && $fieldType=='float'){
            $field['step']  = $field['NUMERIC_SCALE'] > 0 ? "0." . str_repeat(0, $field['NUMERIC_SCALE'] - 1) . "1" : 0;
        }
        $fieldType = $fieldType ?? 'text';
        // 根据字段后缀进行特殊处理
        foreach ($this->suffixMap as $configKey => $suffixList) {
            if ($this->hasSuffix($fieldsName, $suffixList)) {
                $fieldType = $this->processSuffixFieldType($configKey, $dataType, $field, $comment, $fieldType);
                break;
            }
        }

        return $fieldType;
    }

    /**
     * 根据字段后缀处理字段类型
     * @param string $configKey 配置键
     * @param string $dataType 数据类型
     * @param array $field 字段信息
     * @param array $comment 注释数据
     * @param string $currentFieldType 当前字段类型
     * @return string
     */
    protected function processSuffixFieldType(string $configKey, string $dataType, array $field, array $comment, string $currentFieldType): string
    {
        // 根据不同的后缀类型进行处理
        switch ($configKey) {
            case 'fileSuffix':
                return isset($comment[1]) && $comment[1] > 1 ? 'files' : 'file';

            case 'imageSuffix':
                if (in_array($dataType, ['varchar','char','text','smalltext','tinytext','mediumtext','longtext','json'])) {
                    return isset($comment[1]) && $comment[1] > 1 ? 'images' : 'image';
                }
                break;

            case 'editorSuffix':
                if (in_array($dataType, ['text','smalltext','tinytext','mediumtext','longtext'])) {
                    return 'editor';
                }
                break;
            case 'timeSuffix':
                if (!in_array($currentFieldType, ['time', 'year', 'date','datetime'])) {
                    return 'datetime';
                }
                break;

            case 'selectSuffix':
                if (in_array($dataType, ['enum', 'varchar', 'char','text','smalltext','tinytext','mediumtext','longtext'])) {
                    return 'select';
                }
                if (in_array($dataType, ['tinyint','smallint','mediumint','int','bigint'])) {
                    return 'radio';
                }
                break;
            case 'selectsSuffix':
                if (in_array($dataType, ['set','varchar','char','text','smalltext','tinytext','mediumtext','longtext'])) {
                    return 'selects';
                }
                if (in_array($dataType, ['enum','varchar','char','tinyint','smallint','mediumint','int','bigint'])) {
                    return 'radio';
                }
                break;
            case 'radioSuffix':
                if (($dataType === 'enum' || $dataType === 'tinyint') &&
                    $field['COLUMN_DEFAULT'] !== '' && $field['COLUMN_DEFAULT'] !== null) {
                    return 'radio';
                }
                break;
            case 'checkboxSuffix':
                if ($dataType === 'set') {
                    return 'checkbox';
                }
                break;
            case 'switchSuffix':
                if (($dataType === 'tinyint' || $dataType === 'int' || $field['COLUMN_TYPE'] === 'char(1)') &&
                    $field['COLUMN_DEFAULT'] !== '' && $field['COLUMN_DEFAULT'] !== null) {
                    return 'switch';
                }
                break;

            case 'colorSuffix':
                if (in_array($dataType, ['varchar', 'char'])) {
                    return 'color';
                }
                break;

            case 'iconSuffix':
                if (in_array($dataType, ['varchar', 'char'])) {
                    return 'icon';
                }
                break;

            case 'priceSuffix':
                if (in_array($dataType, ['decimal', 'double', 'float'])) {
                    return 'price';
                }
                break;
            case 'priSuffix':
                if (in_array($dataType, ['tinyint', 'smallint', 'mediumint', 'int', 'bigint', 'varchar', 'char','text','smalltext','tinytext','mediumtext','longtext'])) {
                    return '_id';
                }
                break;
            case 'priSelectsSuffix':
                if (in_array($dataType, ['set','varchar','char','text','smalltext','tinytext','mediumtext','longtext'])) {
                    return '_ids';
                }
                break;
            case 'tagsSuffix':
                return 'tags';

            case 'urlSuffix':
                return 'url';

            case 'sortSuffix':
                return 'number';

            case 'jsonSuffix':
                return 'json';

            case 'arraySuffix':
                return 'array';

            case 'citySuffix':
                return 'city';
        }
        // 如果没有匹配的特殊处理，返回原始字段类型
        return $currentFieldType;
    }

    /**
     * 获取选项数组
     * @param $field
     * @return array
     */
    protected function getOptionArr(&$field): array
    {
        $name = Str::studly($field['COLUMN_NAME']);
        if(empty($field['COMMENT_DATA'][1])){
            return [];
        }
        $op = trim(trim($field['COMMENT_DATA'][1], '('), ')');
        $options = explode(',', (trim(trim($op, '['), ']')));
        $optionsArr = [];
        foreach ($options as $k => $v) {
            $ops = explode(":", $v);
            if (isset($ops[0])) {
                $key = trim($ops[0]);
                $optionsArr[$key] = $name.' '. $ops[0];

            }
        }
        $field['COMMENT_DATA'][1] = $optionsArr;
        return $optionsArr;
    }

    /**
     * 获取字段翻译
     * @param $field
     * @return array
     */
    protected function getLangArr($field): array
    {
        $comment = $field['COMMENT_DATA'];
        // 防止 Undefined array key 0 错误
        $optionsLang[Str::studly($field['COLUMN_NAME'])] = isset($comment[0]) ? $comment[0] : Str::studly($field['COLUMN_NAME']);
        if (!empty($comment[1])) {
            $comment[1] = str_replace('=',':',$comment[1]);
            if (strpos($comment[1], ':') !== false) { //判断是否是枚举等类型
                $op = trim(trim($comment[1], '('), ')');
                $options = explode(',', (trim(trim($op, '['), ']')));
                foreach ($options as $kk => $vv) {
                    $vv = str_replace("：", ':', $vv);
                    $opArr = explode(':', $vv);
                    if (isset($opArr[0]) && isset($opArr[1])) {
                        $optionsLang[Str::studly($field['COLUMN_NAME']) . ' ' . $opArr[0]] = $opArr[1];
                    }
                }
            }
        }
        return $optionsLang;
    }

    /**
     * 判断字段是否符合指定后缀
     * @param string $field 字段名
     * @param array|string $suffix 后缀或后缀数组
     * @return bool
     */
    protected function hasSuffix($field, $suffix): bool
    {
        $suffix = is_array($suffix) ? $suffix : explode(',', $suffix);
        foreach ($suffix as $v) {
            if (Str::endsWith($field, $v)) {
                return true;
            }
        }
        return false;
    }

    /**
     * 生成PHP数组格式的选项字符串
     * 例如：['monday'=>'Week monday','tuesday'=>'Week tuesday','wednesday'=>'Week wednesday']
     * @param array $data 选项数组
     * @return string PHP数组格式字符串
     */
    protected function getArrStr($data): string
    {
        $str = '[';
        foreach($data as $k => $v){
            $str .= "'" . $k . "'=>'" . $v . "',";
        }
        $str .= ']';
        return $str;
    }
    /**
     * 生成JavaScript对象格式的选项字符串
     * 例如：{'monday':'Week monday','tuesday':'Week tuesday','wednesday':'Week wednesday'}
     * @param array $data 选项数组
     * @return string JavaScript对象格式字符串
     */
    protected function getObjStr($data): string
    {
        $str = '';
        foreach($data as $k => $v){
            $str .= "{'" . $k . "':'" . $v . "'},";
        }
        return $str;
    }
    /**
     * Summary of getAssignList
     * @param mixed $list
     * @param mixed $type
     * @return string
     */
    protected function getAssignList($list,$type='php'){
        foreach($list as $key=>$val){
            $name = lcfirst(Str::studly($val));
            if($type=='php'){
                $strArr[] = <<<EOF
        View::assign('{$name}',\$this->modelClass->get{$val}());
EOF;
            }elseif($type=='js'){
                $strArr[] = <<<EOF
        var {$name} = {:json_encode(\${$name},JSON_UNESCAPED_UNICODE)};
EOF;
            }
        }
        return implode(PHP_EOL,$strArr);
    }

    /**
     * 获取前缀URL的通用方法
     * @return string
     */
    protected function getPrefixUrl($controller): string
    {
        if ($this->config['app'] != 'backend') {
            return $this->app . '/' . str_replace('/', '.', $controller);
        } else {
            return str_replace('/', '.', $controller);
        }
    }
    /**
     * 获取软删除
     * @param $value
     * @return string
     */
    protected function getSoftDelete($value): string
    {
        $default = $value['value'] == '' ? 'null' : $value['value'];
        $arr[] = <<<EOF
use SoftDelete;
EOF;
        if($default!='null'){
            $arr[] = <<<EOF
    /**
     * 软删除值
     * @var string
     */
    protected \$defaultSoftDelete = {$default};
EOF;
        }
        return implode("\r\n",$arr);

    }

    /**
     * Undocumented function
     *
     * @param [type] $field
     * @return array
     */
    protected function getModelAttr(&$field):array
    {
        $attrsList = [];
        $appendsList = [];
        $assignList = [];
        $name = Str::studly($field['COLUMN_NAME']);
        $method = ucfirst($name);
        switch ($field['type']) {
            case '_id':
            case '_ids':
                $_fieldName = $field['COLUMN_NAME'];
                if($field['type']=='_ids'){
                    $_fieldName = substr($_fieldName,0,-1);
                }
                if(in_array($_fieldName,$this->config['joinForeignKey'])){
                    $key = array_search($_fieldName,$this->config['joinForeignKey']);
                    $joinModel = $this->config['joinModelList'][$key];
                    $selectField = $joinModel['joinSelectField'];
                    $priKey = $joinModel['priKey'];
                    if($selectField){
                        $assignList = "{$method}List";
                        $attrsList = <<<EOF
    public function get{$method}List()
    {
        return \\{$joinModel['namespace']}\\{$joinModel['name']}::field("{$selectField},{$priKey}")->select();
    }
EOF;
                    }
                }
                break;
            case 'timestamp':
            case 'datetime':
            case 'range':
            case 'year':
            case "date":
            case "time":
                if ($field['DATA_TYPE'] == 'int') {
                    //生成关联表的模型
                    $attrsList = <<<EOF
    public function get{$method}Attr(\$value)
    {
        \$value = \$value ? \$value  : '';
        return is_numeric(\$value) ? date("Y-m-d H:i:s", \$value) : \$value;
    }
    public function set{$method}Attr(\$value)
    {
        \$value = \$value ? \$value  : '';
        return \$value == '' ? null : (\$value && !is_numeric(\$value) ? strtotime(\$value) : \$value);
    }
EOF;
                    $appendsList = <<<EOF
            '{$field['COLUMN_NAME']}_text'
EOF;
                }
                break;
            case 'files':
            case 'images':
                //生成关联表的模型
                $attrsList = <<<EOF
    public function get{$method}TextAttr(\$value)
    {
        \$value = \$value ? \$value : '';
        return explode(',', \$value);
    }
    public function set{$method}Attr(\$value)
    {
        return is_array(\$value) ? implode(',', \$value) : \$value;
    }
EOF;
                break;
            case 'radio':
            case 'switch':
            case 'select':
            case 'checkbox':
            case 'selects':
                $list = $field['COMMENT_DATA'][1]??[0=>'disabled',1=>'enabled'];
                $assignList = "{$method}List";
                $list = $this->getArrStr($list);
                $attrsList = <<<EOF
    public function get{$method}List()
    {
        return {$list};
    }
    public function get{$method}TextAttr(\$value)
    {
        \$value = \$value ? \$value : '';
        return explode(',', \$value);
    }
EOF;
                $appendsList = <<<EOF
            '{$field['COLUMN_NAME']}_text'
EOF;
                break;
            case 'number':
                switch ($field['DATA_TYPE']) {
                    case 'tinyint':
                        $assignList = "{$method}List";
                        $list = $field['COMMENT_DATA'][1]??[0=>'disabled',1=>'enabled'];
                        $list = $this->getArrStr($list);
                        $attrsList = <<<EOF
    public function get{$method}List()
    {
        return {$list};
    }
    public function get{$method}TextAttr(\$value,\$data)
    {
        \$list = \$this->get{$method}List();
        return \$list[\$data['{$field['COLUMN_NAME']}']]??'';
    }
EOF;
                        $appendsList = <<<EOF
                '{$field['COLUMN_NAME']}_text'
EOF;
                        break;
                }
                break;
        }
        return [$attrsList,$appendsList,$assignList];
    }
    /**
     * 获取add表单
     * @return string
     */
    protected function getFormData($field): string
    {
        $formFieldData = '';
        if ($field['COLUMN_KEY'] == 'PRI') return '';
        if (in_array($field['COLUMN_NAME'], $this->config['ignoreFields'])) return '';
        if(!empty($this->config['formFields']) && !in_array($field['COLUMN_NAME'], $this->config['formFields'])) return '';
        $name = Str::studly($field['COLUMN_NAME']);
        $fieldType = $field['type'];
        // $listAttr = $field['COMMENT_DATA'][1]??[];
        $fieldName = $field['COLUMN_NAME'];
        $fieldType = $field['type'];
        switch ($fieldType) {
            case "text":
                $formFieldData = "{:Form::input('{$fieldName}', 'text', ['label' => '{$name}', 'verify' => '{$field['required']}'], '{$field['value']}')}";
                break;
            case "tags":
                $formFieldData = "{:Form::tags('{$fieldName}', ['label' => '{$name}', 'verify' => '{$field['required']}'], '{$field['value']}')}";
                break;
            case "number":
                $formFieldData = "{:Form::input('{$fieldName}', 'number', ['label' => '{$name}', 'verify' => '{$field['required']}'], '{$field['value']}')}";
                break;
            case "float":
            case "price":
                $formFieldData = "{:Form::input('{$fieldName}', 'number', ['label' => '{$name}','step'=>{$field['step']}, 'verify' => '{$field['required']}'], '{$field['value']}')}";
                break;
            case "switch":
                $listName = lcfirst(Str::studly($fieldName)).'List';
                $formFieldData = "{:Form::radio('{$fieldName}' ,\${$listName}, ['label' => '{$name}', 'verify' => '{$field['required']}'], '{$field['value']}')}";
                break;
            case "array":
                $formFieldData = "{:Form::array('{$fieldName}', ['label' => '{$name}', 'verify' => '{$field['required']}'])}";
                break;
            case "checkbox":
                $listName = lcfirst(Str::studly($fieldName)).'List';
                $formFieldData = "{:Form::checkbox('{$fieldName}', \${$listName},['label' => '{$name}', 'verify' => '{$field['required']}'], \$formData?\$formData['{$fieldName}']:'{$field['value']}')}";
                break;
            case "radio":
                $listName = lcfirst(Str::studly($fieldName)).'List';
                $formFieldData = "{:Form::radio('{$fieldName}' ,\${$listName}, ['label' => '{$name}', 'verify' => '{$field['required']}'], '{$field['value']}')}";
                break;
            case "_id":
            case "_ids":
                if (!empty($this->config['joinTable'])) {
                    $_fieldName = $fieldName;
                    if($fieldType=='_ids'){
                        $_fieldName = substr($fieldName,0,-1);
                    }
                    $listName = lcfirst(Str::studly($fieldName)).'List';
                    if (in_array($_fieldName, $this->config['joinForeignKey'])) {
                        $key = array_search($_fieldName,$this->config['joinForeignKey']);
                        $model = $this->config['joinModelList'][$key];
                        $priKey = $model['priKey'];
                        $selectField =  $model['joinSelectField'];
                        $multiple = strpos($fieldName, '_ids') !== false ? ",'multiple'=>1" : '';
                        $formFieldData = "{:Form::select('{$fieldName}',\${$listName}, ['label' => '{$name}', 'verify' => '{$field['required']}' {$multiple}, 'search' => 1], ['{$priKey}','{$selectField}'], '{$field['value']}')}";
                    } else {
                        $formFieldData = "{:Form::input('{$fieldName}', 'text', ['label' => '{$name}', 'verify' => '{$field['required']}'], '{$field['value']}')}";
                    }
                } else {
                    $formFieldData = "{:Form::input('{$fieldName}', 'text', ['label' => '{$name}', 'verify' => '{$field['required']}'], '{$field['value']}')}";
                }
                break;
            case "select":
                $listName = lcfirst(Str::studly($fieldName)).'List';
                if (in_array($field['DATA_TYPE'], ['set', 'varchar', 'char'])) {
                    $formFieldData = "{:Form::select('{$fieldName}',\${$listName}, ['label' => '{$name}', 'verify' => '{$field['required']}', 'multiple'=>1,'search' => 1], [], '{$field['value']}')}";
                } else {
                    $formFieldData = "{:Form::select('{$fieldName}',\${$listName}, ['label' => '{$name}', 'verify' => '{$field['required']}', 'search' => 1], [], '{$field['value']}')}";
                }
                break;
            case "color":
                $formFieldData = "{:Form::color('{$fieldName}',['label' => '{$name}', 'verify' => '{$field['required']}', 'search' => 1])}";
                break;
            case "timestamp":
            case "datetime":
                $formFieldData = "{:Form::date('{$fieldName}', ['label' => '{$name}', 'verify' => '{$field['required']}'])}";
                break;
            case "year":
                $formFieldData = "{:Form::date('{$fieldName}', ['label' => '{$name}', 'verify' => '{$field['required']}', 'type' => 'year'])}";
                break;
            case "date":
                $formFieldData = "{:Form::date('{$fieldName}', ['label' => '{$name}', 'verify' => '{$field['required']}', 'type' => 'date'])}";
                break;
            case "time":
                $formFieldData = "{:Form::date('{$fieldName}', ['label' => '{$name}', 'verify' => '{$field['required']}', 'type' => 'time'])}";
                break;
            case "range":
                $formFieldData = "{:Form::date('{$fieldName}', ['label' => '{$name}', 'verify' => '{$field['required']}','range' => 'range'])}";
                break;
            case "textarea":
                $formFieldData = "{:Form::textarea('{$fieldName}', ['label' => '{$name}', 'verify' => '{$field['required']}',], '{$field['value']}')}";
                break;
            case "image":
                $formFieldData = "{:Form::upload('{$fieldName}', ['label' => '{$name}', 'verify' => '{$field['required']}', 'type' => 'radio', 'mime' => 'image', 'path' => '{$this->config["app"]}', 'num' => '1'], \$formData?\$formData['{$fieldName}']:'{$field['value']}', )}";
                break;
            case "images":
                $formFieldData = "{:Form::upload('{$fieldName}', ['label' => '{$name}', 'verify' => '{$field['required']}', 'type' => 'checkbox', 'mime' => 'image', 'path' =>'{$this->config["app"]}', 'num' => '*'], \$formData?\$formData['{$fieldName}']:'{$field['value']}', )}";
                break;
            case "file":
                $formFieldData = "{:Form::upload('{$fieldName}', ['label' => '{$name}', 'verify' => '{$field['required']}', 'type' => 'radio', 'mime' => 'file', 'path' =>'{$this->config["app"]}', 'num' => '1'], \$formData?\$formData['{$fieldName}']:'{$field['value']}', )}";
                break;
            case "files":
                $formFieldData = "{:Form::upload('{$fieldName}', ['label' => '{$name}', 'verify' => '{$field['required']}', 'type' => 'checkbox', 'mime' => 'file', 'path' => '{$this->config["app"]}', 'num' => '*'], \$formData?\$formData['{$fieldName}']:'{$field['value']}', )}";
                break;
            case "editor":
                $formFieldData = "{:Form::editor('{$fieldName}', ['label'=>'{$name}','verify' => '{$field['required']}'])}";
                break;
            default :
                $formFieldData = "{:Form::input('{$fieldName}', 'text', ['label' => '{$name}', 'verify' => '{$field['required']}'], '{$field['value']}')}";
                break;
        }
        return $formFieldData;
    }
    /**
     * 获取字段配置
     * @param $field
     * @param $softDelete
     * @return array
     */
    protected function getColsData($field,$softDelete): array
    {
        $jsCols = '';
        $jsColsRecycle = '';
        $fieldsList = $this->config['fieldsList']?explode(',',$this->config['fieldsList']):[];
        if ($this->shouldShowField($field, $fieldsList)) {
            if($field['COLUMN_NAME']=='delete_time'){
                return [$jsCols,$jsColsRecycle];
            }
            $name = Str::studly($field['COLUMN_NAME']);
            // $listAttr = $field['COMMENT_DATA'][1]??[];
            $listAttr = !empty($field['COMMENT_DATA'][1])? lcfirst($name).'List':'';
            $fieldName = $field['COLUMN_NAME'];
            $fieldType = $field['type'];
            if($fieldType=='_id' || $fieldType=='_ids'){
                $listAttr = lcfirst(Str::studly($name)).'List';
            }
            // 生成字段配置
            $fieldConfig = $this->getFieldJsConfig($fieldType, $fieldName, $name, $listAttr, $field);
            if ($fieldConfig) {
                $jsCols = $fieldConfig;
                if($field['COLUMN_NAME']=='title' && $softDelete){
                    $jsColsRecycle = $fieldConfig;
                }
                if($field['COLUMN_NAME']=='name' && $softDelete){
                    $jsColsRecycle = $fieldConfig;
                }
            }
        }
        return [$jsCols,$jsColsRecycle];
    }

    /**
     * 生成字段配置
     * @param string $fieldType 字段类型
     * @param string $fieldName 字段名称
     * @param string $name 格式化后的名称
     * @param array $listName 列表属性
     * @param array $fieldInfo 字段信息
     * @return string|null
     */
    protected function getFieldJsConfig(string $fieldType, string $fieldName, string $name, array|string $listAttr, array $fieldInfo): ?string
    {
        $baseConfig = [
            'field' => $fieldName,
            'fieldAlias'=>$fieldName,
            'search'=>true,
            'title' => "__('$name')",
            'filter' => $fieldName,
            'align' => 'center',
            'sort' => true
        ];

        switch ($fieldType) {
            case '_id':
            case '_ids':
                $_fieldName = $fieldName;
                if($fieldType=='_ids'){
                    $_fieldName = substr($fieldName,0,-1);
                }
                if (!empty($this->config['joinTable']) && in_array($_fieldName, $this->config['joinForeignKey'])) {
                    $key = array_search($fieldName,$this->config['joinForeignKey']);
                    $joinModel = $this->config['joinModelList'][$key];
                    $selectField =  $joinModel['joinSelectField'];
                    $priKey = $joinModel['priKey'];
                    $prop = $priKey.','.$selectField;
                    return $this->buildFieldConfig(array_merge($baseConfig, [
                        'search' => 'selectpage',
                        'selectList' => $listAttr,
                        'sort' => true,
                        'prop' => $prop,
                        'templet' => 'Table.templet.tags'
                    ]));
                } else {
                    return $this->buildFieldConfig(array_merge($baseConfig, [
                    ]));
                }
            case 'image':
            case 'images':
                return $this->buildFieldConfig(array_merge($baseConfig, [
                    'templet' => 'Table.templet.image',
                    'search' => false,
                ]));

            case 'file':
            case 'files':
                return $this->buildFieldConfig(array_merge($baseConfig, [
                    'templet' => 'Table.templet.url',
                    'search' => false,
                ]));

            case 'url':
                return $this->buildFieldConfig(array_merge($baseConfig, [
                    'templet' => 'Table.templet.url',
                    'search' => false,
                ]));

            case 'checkbox':
            case 'tags':
                return $this->buildFieldConfig(array_merge($baseConfig, [
                    'templet' => 'Table.templet.tags',
                    'search' => 'select',
                    'selectList' => $listAttr,
                ]));

            case 'select':
            case 'radio':
                return $this->buildFieldConfig(array_merge($baseConfig, [
                    'search' => 'select',
                    'selectList' => $listAttr,
                    'templet' => 'Table.templet.select'
                ]));

            case 'switch':
                return $this->buildFieldConfig(array_merge($baseConfig, [
                    'search' => 'select',
                    'selectList' =>$listAttr,
                    'templet' => 'Table.templet.switch'
                ]));

            case 'number':
                if ($this->hasSuffix($fieldName, ['sort'])) {
                    $baseConfig['edit'] = 'text';
                }
                return $this->buildFieldConfig($baseConfig);

            case 'date':
                return $this->buildFieldConfig(array_merge($baseConfig, [
                    'align' => 'center',
                    'dateformat' => 'yyyy-MM-dd',
                    'searchdateformat' => 'yyyy-MM-dd',
                    'search' => 'timerange',
                    'templet' => 'Table.templet.time',
                    'sort' => true
                ]));

            case 'timestamp':
            case 'datetime':
                // 跳过系统时间字段
                if (in_array($fieldName, ['update_time', 'delete_time'])) {
                    return null;
                }
                return $this->buildFieldConfig(array_merge($baseConfig, [
                    'align' => 'center',
                    'timeType' => 'datetime',
                    'dateformat' => 'yyyy-MM-dd HH:mm:ss',
                    'searchdateformat' => 'yyyy-MM-dd HH:mm:ss',
                    'search' => 'timerange',
                    'templet' => 'Table.templet.time',
                    'sort' => true,
                    'searchOp' => 'daterange'
                ]));

            case 'year':
                return $this->buildFieldConfig(array_merge($baseConfig, [
                    'align' => 'center',
                    'dateformat' => 'yyyy',
                    'searchdateformat' => 'yyyy',
                    'timeType' => 'year',
                    'search' => 'timerange',
                    'templet' => 'Table.templet.time',
                    'sort' => true,
                    'searchOp' => 'daterange'
                ]));

            case 'time':
                return $this->buildFieldConfig(array_merge($baseConfig, [
                    'align' => 'center',
                    'dateformat' => 'HH:mm:ss',
                    'searchdateformat' => 'HH:mm:ss',
                    'timeType' => 'time',
                    'search' => 'timerange',
                    'templet' => 'Table.templet.time',
                    'sort' => true,
                    'searchOp' => 'daterange'
                ]));
            case 'textarea':
            case 'editor':
            case 'color':
                return $this->buildFieldConfig(array_merge($baseConfig, [
                    'search' => false,
                ]));
            default:
                return $this->buildFieldConfig(array_merge($baseConfig, [
                ]));
        }
    }

    /**
     * 构建字段配置字符串
     * @param array $config 配置数组
     * @return string
     */
    protected function buildFieldConfig(array $config): string
    {
        $parts = [];

        foreach ($config as $key => $value) {
            if (is_bool($value)) {
                $parts[] = "$key:" . ($value ? 'true' : 'false');
            } elseif ($key == 'selectList') {
                // 处理selectList，确保它是有效的数组或对象
                if (is_array($value) || is_object($value)) {
                    $value = json_encode($value, JSON_FORCE_OBJECT | JSON_UNESCAPED_UNICODE);
                    $parts[] = "$key:{$value}";
                } elseif (is_string($value) && !empty($value)) {
                    // 如果是非空字符串，直接使用
                    $parts[] = "$key:{$value}";
                } else {
                    // 如果是空值，使用空对象
                    $parts[] = "$key:{}";
                }
            } elseif (is_string($value) && (strpos($value, 'Table.') === 0 || strpos($value, '__') === 0)) {
                // 不加引号的值（函数调用、变量等）
                $parts[] = "$key:$value";
            } else {
                if(is_array($value)){
                    $value = json_encode($value,JSON_UNESCAPED_UNICODE);
                }else{
                    $value = "'{$value}'";
                }
                // 加引号的值
                $parts[] = "$key:$value";
            }
        }

        $str =  '{' . implode(',', $parts) . '}';
        return <<<EOF
                    {$str}
EOF;
    }

    /**
     * 写入到文件
     * @param string $name
     * @param array  $data
     * @param string $pathname
     * @return mixed
     */
    public function makeFile($name, $data, $pathname,$type='php')
    {
        foreach ($data as &$datum) {
            if(is_array($datum)){
                $datum = implode("\r\n",$datum);
            }
        }
        unset($datum);
        $content = $this->replaceTemplate($name, $data,$type);

        if (!is_dir(dirname($pathname))) {
            mkdir(dirname($pathname), 0755, true);
        }
        if($this->config['force'] && file_exists($pathname) || !file_exists($pathname)){
            return file_put_contents($pathname, data: $content);
        }
    }

    /**
     * 检查数据库中是否存在指定表格
     * @param string $table 表名（不包含前缀）
     * @return bool
     */
    protected function tableExists($table): bool
    {
        $table = trim($table);
        if (empty($table)) {
            return false;
        }

        // 添加表前缀
        $fullTableName = $this->config['prefix'] . $table;

        try {
            // 使用 SHOW TABLES LIKE 查询检查表是否存在
            $sql = "SHOW TABLES LIKE '{$fullTableName}'";
            $result = $this->connect->query($sql);
            return !empty($result);
        } catch (\Exception $e) {
            // 如果查询失败，使用备用方法
            try {
                $sql = "SELECT COUNT(*) as count FROM 
                information_schema.tables WHERE 
                table_name = '{$fullTableName}' 
                AND table_schema = '{$this->config['database']}'";
                $result = $this->connect->query($sql);
                return isset($result[0]['count']) && $result[0]['count'] > 0;
            } catch (\Exception $e) {
                throw new \Exception($e->getMessage());
            }
        }
    }
    /**
     * 判断字段是否需要显示
     * @param array $field 字段信息
     * @param array $fields 指定显示的字段列表
     * @return bool
     */
    public function shouldShowField(array $field, array $fieldsList): bool
    {
        // 如果指定了显示字段，则只显示指定的字段
        if (!empty($fieldsList)) {
            return in_array($field['COLUMN_NAME'], $fieldsList);
        }

        // 默认显示所有非主键字段
        return true;
    }



    /**
     * 获取替换后的数据
     * @param string $name
     * @param array  $data
     * @return string
     */
    protected function replaceTemplate($name, $replacements,$type='php')
    {
        foreach ($replacements as $index => &$datum) {
            if(is_array($datum)){
                $datum = implode("\r\n",$datum);
            }
        }
        unset($datum);
        $search = $replace = [];
        foreach ($replacements as $k => $v) {
            $search[]  = "{%{$k}%}";
            $replace[] = $v;
        }
        $template = root_path('extend/fun/curd/tpl/'.$type). $name. '.tpl';
        $tpl = file_get_contents($template);
        $content = str_replace($search, $replace, $tpl);
        return $content;
    }

    /**
     * 将数据转换成带字符串
     * @param array $arr
     * @return string
     */
    protected function getArrayString($arr)
    {
        if (!is_array($arr)) {
            return $arr;
        }
        $stringArr = [];
        foreach ($arr as $k => $v) {
            $is_var = in_array(substr($v, 0, 1), ['$', '_']);
            if (!$is_var) {
                $v = str_replace("'", "\'", $v);
                $k = str_replace("'", "\'", $k);
            }
            $stringArr[] = "'" . $k . "' => " . ($is_var ? $v : "'{$v}'");
        }
        return implode(", ", $stringArr);
    }
}
