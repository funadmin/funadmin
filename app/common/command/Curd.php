<?php

namespace app\common\command;
use think\console\Command;
use think\console\Input;
use think\console\input\Option;
use think\console\Output;

class Curd extends Command{
    /**
     * 保留字段
     * @var string[]
     */
    protected $keepField = ['admin_id','member_id'];
    /**
     * 添加时间字段
     * @var string
     */
    protected $createTimeField = 'create_time';
    /**
     * 更新时间字段
     * @var string
     */
    protected $updateTimeField = 'update_time';
    /**
     * 软删除时间字段
     * @var string
     */
    protected $deleteTimeField = 'delete_time';
    /**
     * 状态字段
     * @var string
     */
    protected $statusField = 'status';
    /**
     * 排序字段
     */
    protected $sortField = 'sort';
    /**
     * 识别为文件字段
     */
    protected $fileField = ['file', 'files','path','paths'];
    /**
     * 图片字段
     */
    protected $imageField = ['image','thumb', 'images', 'avatar', 'avatars'];
    /**
     * 表单字段名
     * @var string[]
     */
    /**
     * 忽略字段
     * @var array
     */
    protected $ignoreFields = ['create_time','status','update_time', 'delete_time'];

    protected $formType= [
        'text',
        'image',
        'images',
        'file',
        'files',
        'select',
        'switch',
        'checkbox',
        'icon',
        'city',
        'cropper',
        'date',
        'editor',
        'textarea',
        'checkbox',
        'radio'
    ];
    /**
     * 表前缀
     * @var string
     */
    protected $tablePrefix ='fun_';
    /**
     * 数据库名
     * @var string
     */
    protected $dbName ='funadmin';

    protected function configure(){
        $this->setName('curd')
            ->addOption('table', 't', Option::VALUE_REQUIRED, '表名', null)
            ->addOption('controller', 'c', Option::VALUE_OPTIONAL, '控制器名', null)
            ->addOption('model', 'm', Option::VALUE_OPTIONAL, '模型名', null)

            ->addOption('relationTable', 'r', Option::VALUE_REQUIRED | Option::VALUE_IS_ARRAY, '关联表名', null)
            ->addOption('relationModel', 'o', Option::VALUE_REQUIRED | Option::VALUE_IS_ARRAY, '关联模型', null)
            ->addOption('relationWay', 'w', Option::VALUE_OPTIONAL | Option::VALUE_IS_ARRAY, '关系表方式 hasone or belongsto等', null)
            ->addOption('fields', 'f', Option::VALUE_OPTIONAL, '模型字段', null)
            ->addOption('relationForeignKey', 'k', Option::VALUE_REQUIRED | Option::VALUE_IS_ARRAY, '关联外键', null)
            ->addOption('relationPrimaryKey', 'p', Option::VALUE_REQUIRED | Option::VALUE_IS_ARRAY, '关联主键', null)
            ->addOption('relationFields', 'i', Option::VALUE_OPTIONAL | Option::VALUE_IS_ARRAY, '关联表字段', null)
            ->addOption('fields', 'm', Option::VALUE_OPTIONAL, '模型名', null)
            ->addOption('imageField', 'image', Option::VALUE_OPTIONAL | Option::VALUE_IS_ARRAY, '图片后缀', null)
            ->addOption('fileField', 'file', Option::VALUE_OPTIONAL | Option::VALUE_IS_ARRAY, '文件后缀', null)
            ->addOption('timeSuffix', 'time', Option::VALUE_OPTIONAL | Option::VALUE_IS_ARRAY, '时间后缀', null)
            ->addOption('switchSuffix', 'switch', Option::VALUE_OPTIONAL | Option::VALUE_IS_ARRAY, '开关后缀', null)
            ->addOption('radioSuffix', 'radio', Option::VALUE_OPTIONAL | Option::VALUE_IS_ARRAY, '单选后缀', null)
            ->addOption('checkboxSuffix', 'checkbox', Option::VALUE_OPTIONAL | Option::VALUE_IS_ARRAY, '多选后缀', null)
            ->addOption('citySuffix', 'city', Option::VALUE_OPTIONAL | Option::VALUE_IS_ARRAY, '城市后缀', null)
            ->addOption('iconSuffix', 'icon', Option::VALUE_OPTIONAL | Option::VALUE_IS_ARRAY, '图标后缀', null)
            ->addOption('jsonSuffix', 'json', Option::VALUE_OPTIONAL | Option::VALUE_IS_ARRAY, 'json后缀', null)
            ->addOption('selectSuffix', 'select', Option::VALUE_OPTIONAL | Option::VALUE_IS_ARRAY, '下拉选择后缀', null)
            ->addOption('selectsSuffix', 'selects', Option::VALUE_OPTIONAL | Option::VALUE_IS_ARRAY, '下拉多选后缀', null)
            ->addOption('sortField', 'sort', Option::VALUE_OPTIONAL, '排序字段', null)
            ->addOption('editorField', 'editor', Option::VALUE_OPTIONAL, '编辑器字段', null)
            ->addOption('statusField', 'status', Option::VALUE_OPTIONAL, '状态字段', null)
            ->addOption('ignoreFields', 'ignore', Option::VALUE_REQUIRED | Option::VALUE_IS_ARRAY, '忽略的字段', null)
            ->addOption('local', 'l', Option::VALUE_OPTIONAL, '模块', 1)
            ->addOption('force', 'f', Option::VALUE_REQUIRED, '强制覆盖或删除', 0)
            ->addOption('delete', 'd', Option::VALUE_REQUIRED, '删除', 0)
            ->setDescription('Curd Command');
    }

    protected function execute(Input $input, Output $output){

        $table = $input->getOption('table');
        $controller = $input->getOption('controller');
        $model = $input->getOption('model');
        $validate = $model;        //验证器类
        $local = $input->getOption('local');        //模块
        $fields = $input->getOption('fields');//自定义显示字段
        $checkboxSuffix = $input->getOption('checkboxSuffix');
        $radioSuffix = $input->getOption('radioSuffix');
        $imageSuffix = $input->getOption('imageSuffix');
        $fileSuffix = $input->getOption('fileSuffix');
        $dateSuffix = $input->getOption('dateSuffix');
        $iconSuffix = $input->getOption('iconSuffix');
        $switchSuffix = $input->getOption('switchSuffix');
        $selectSuffix = $input->getOption('selectSuffix');
        $selectsSuffix = $input->getOption('selectsSuffix');
        $sortFields = $input->getOption('sortFields');
        $ignoreFields = $input->getOption('ignoreFields');

        $relationTable = $input->getOption('relationTable');
        $relationModel = $input->getOption('relationModel');
        $relationWay = $input->getOption('relationWay');
        $relationForeignKey = $input->getOption('relationForeignKey');
        $relationPrimaryKey = $input->getOption('relationPrimaryKey');

        $force = $input->getOption('force');//强制覆盖
        $delete = $input->getOption('delete');
        $this->keepField = array_merge($this->keepField, [$this->createTimeField, $this->updateTimeField, $this->deleteTimeField,$this->statusField,$this->sortField]);
        if ($ignoreFields) {
            $this->ignoreFields =array_merge($this->ignoreFields,[$ignoreFields]) ;
        }
        if (empty($table)) {
            $output->info("table is empty");
            return false;
        }
        $moduleName = 'backend';
        $modelModuleName = $local ? $moduleName : 'common';
        $validateModuleName = $local ? $moduleName : 'common';

        $this->tablePrefix = config('database.connections.mysql.prefix');
        $this->dbName = config('database.connections.mysql.database');
        $relations = [];
        foreach ($relationTable as $key => $val) {
            $relations[] = [
                'relationTable'         => $relationTable[$key],
                'relationNamespace'     => $relationNamespace,
                'relationWay'     => $relationWay,
                'relationForeignKey'    => isset($relationForeignKey[$key]) ? $relationForeignKey[$key] : null,
                'relationPrimaryKey'    => isset($relationPrimaryKey[$key]) ? $relationPrimaryKey[$key] : null,
                'relationModel' => isset($relationModel[$key]) ? $relationModel[$key] : null,
            ];
        }

    }

    protected function buildModel(){

    }
    protected function buildClass(string $name)
    {
        $stub = file_get_contents($this->getStub());

        $namespace = trim(implode('\\', array_slice(explode('\\', $name), 0, -1)), '\\');

        $class = str_replace($namespace . '\\', '', $name);

        return str_replace(['{%className%}', '{%actionSuffix%}', '{%namespace%}', '{%app_namespace%}'], [
            $class,
            $this->app->config->get('route.action_suffix'),
            $namespace,
            $this->app->getNamespace(),
        ], $stub);
    }
    protected function getStub(string $name): string
    {
        $stubPath = __DIR__ . DIRECTORY_SEPARATOR . 'stubs' . DIRECTORY_SEPARATOR;
        return __DIR__ . DS . 'Curd' . DS . 'stubs' . DS . $name . '.stub';
    }
    /**
     * 生成js
     */
    protected  function buildJs(){


    }
    protected  function buildView(){

    }
    /**
     * 生成文件
     */
    public function buildFile($name,$data,$pathname){

        if (!is_dir(dirname($pathname))) {
            @mkdir(dirname($pathname), 0755, true);
        }
        file_put_contents($pathname, $this->buildClass($classname));
    }


}
