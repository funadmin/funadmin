<?php
/**
 * funadmin
 * ============================================================================
 * 版权所有 2018-2027 funadmin，并保留所有权利。
 * 网站地址: https://www.funadmin.com
 * ----------------------------------------------------------------------------
 * 采用最新Thinkphp6实现
 * ============================================================================
 * Author: yuege
 * Date: 2019/10/9
 */

namespace addons\cms\common\model;

use app\common\model\BaseModel;
use think\Exception;
use think\facade\Cache;
use think\facade\Db;

class CmsModule extends BaseModel
{
    protected $name = 'addons_cms_module';
    public function __construct(array $data = [])
    {
        parent::__construct($data);
    }

    public static function onAfterWrite($model){

    }
    /**
     * @param $tablename
     * @param string $field
     * @return mixed
     * 获取表字段
     */
    public static function getTableColumn($tablename,$field='*'){
        $keys = $tablename.'fields';
        $tablefield =  Cache::get($keys);
        if(!$tablefield){
            $sql = "select $field from information_schema.columns  where table_name='".self::get_table_prefix().$tablename."' and table_schema='".self::get_databasename()."'";
            $tablefield = Db::query($sql);
            Cache::tag($tablename)->set($keys,$tablefield,7200);
        }
        return $tablefield;

    }

    /**
     * @return array
     * 获取所有表
     */
    public static function getTables(){
        $tableslist =Db::query('SHOW TABLES');
        $tables = [];
        foreach ($tableslist as $key=>$value){
            $tables[$key] = $value['Tables_in_'.self::get_databasename()];
        }
        return $tables;
    }

    public static function addModule($tablename,$tableprefix){

        $post = request()->post();
        $rule = [
            'modulename|模型名称' => [
                'require' => 'require',
                'max'     => '100',
                'unique'  => 'addons_cms_module',
            ],
            'tablename|表名' => [
                'require' => 'require',
                'max'     => '50',
                'unique'  => 'addons_cms_module',
            ],
            'listfields|列表页字段' => [
                'require' => 'require',
                'max'     => '255',
            ],
            'intro|描述' => [
                'max' => '200',
            ],
            'sort|排序' => [
                'require' => 'require',
                'number'  => 'number',
            ]
        ];
        try {
            validate($rule)->check($post);
        } catch (\ValidateException $e) {
            throw  new \ValidateException($e->getError());
        }
        // 启动事务
        Db::startTrans();
        try {
            $post['template']=isset($post['template'])? json_encode($post['template'],true): "";
            $post['tablename']= substr($tablename,strlen(self::get_table_prefix()));
            $module = self::create($post);
            if (!$module) {
                throw  new Exception(lang('Add Fail'));
            }
            // 提交事务
            Db::commit();
        } catch (\Exception $e) {
            // 回滚事务
            Db::rollback();
            throw new Exception($e->getMessage());
        }


    }
    //加表
    public static function onAfterInsert($model)
    {
        $sql = <<<EOF
        CREATE TABLE `{$model->tablename}` (
          `id` int(11) unsigned NOT NULL AUTO_INCREMENT COMMENT 'ID',
          `content` longtext NOT NULL  COMMENT '内容',
          PRIMARY KEY (`id`)
        ) ENGINE=InnoDb  DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='{$model->modulename}模型表';
EOF;
        Db::execute($sql);
//        Db::execute("INSERT INTO `" . self::get_table_prefix() . "addons_cms_field` (`diyformid`,`moduleid`,`field`,`name`,`required`,`radix`,`maxlength`,`rule`,`msg`,`type`,`options`,`value`,`sort`,`status`,`create_time`,`is_filter`) VALUES (0, '" . $model->id . "', 'content', '内容',  '0','0',  '0',  '', '', 'editor','0:ueditor\n1:quill\n2:wangedit\n3:layedit','wangedit','7', '1', '" . time() . "' ,1)");
        return true;

    }

//    //添加模型
//    public static function addModel($table){
//        $modelName = StringHelper::formatClass($table);
//        $file = app()->getRootPath().'view/admin/template/model.html';
//        $content = file_get_contents($file);
//        $content = str_replace('$table',$modelName,$content);
//        $modelFile = app()->getRootPath()."app/common/model/".$modelName.'.php';
//        FileHelper::createFile($modelFile,$content);
//    }
//    public static function delModel($table){
//
//
//    }
//    //添加控制器
//    public static function addController($table){
//        $controllerName = StringHelper::formatClass($table);
//        $file = app()->getRootPath().'view/admin/template/controller.html';
//        $content = file_get_contents($file);
//        $content = str_replace('$table',$controllerName,$content);
//        $controllerFile = app()->getRootPath()."app/admin/controller/".$controllerName.'.php';
//        FileHelper::createFile($controllerFile,$content);
//        return true;
//    }
//
//    public static function delController($table){
//        $controllerName = StringHelper::formatClass($table);
//
//    }
//    //添加视图
//    public static function addView($table){
//        $view = app()->getRootPath().'view/admin/'.$table.'/add.html';
//        $view1 = app()->getRootPath().'view/admin/'.$table.'/index.html';
//        $source = app()->getRootPath().'view/admin/template/add.html';
//        $source1 = app()->getRootPath().'view/admin/template/index.html';
//        FileHelper::copyDir($source,$view);
//        FileHelper::copyDir($source1,$view1);
//        return true;
//
//    }
//
//    public static function delView($table){
//
//    }

}