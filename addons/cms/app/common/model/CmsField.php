<?php
// +----------------------------------------------------------------------
// | Yzncms [ 御宅男工作室 ]
// +----------------------------------------------------------------------
// | Copyright (c) 2018 http://yzncms.com All rights reserved.
// +----------------------------------------------------------------------
// | Licensed ( http://www.apache.org/licenses/LICENSE-2.0 )
// +----------------------------------------------------------------------
// | Author: 御宅男 <530765310@qq.com>
// +----------------------------------------------------------------------

// +----------------------------------------------------------------------
// | 字段模型
// +----------------------------------------------------------------------
namespace app\common\model;

use think\facade\Db;
use app\common\model\CmsModule;
/**
 * 字段模型
 */
class CmsField extends Common
{
    public function __construct(array $data = [])
    {
        parent::__construct($data);
    }

    //添加字段
    public static function addField($data = null)
    {
        $data['field'] = strtolower($data['field']);
        //模型id
        $moduleid = $data['moduleid'];
        //完整表名获取 判断主表 还是副表
        $tablename = self::get_tablename($moduleid);
        if (!self::table_exists($tablename)) {
            throw new \Exception(lang('table is not exist'));
        }
        $tablename = self::get_table_prefix() . $tablename;
        //判断字段名唯一性
        if (self::where('field', $data['field'])->where('moduleid', $moduleid)->value('id')) {
            throw new \Exception("field'" . $data['field'] . "`is already exist");
        }
        if ($data['required']) {
            throw new \Exception(lang('field is required'));
        }
        //先将字段存在设置的主表或附表里面 再将数据存入ModelField
        $sql = <<<LEMO
            ALTER TABLE `{$tablename}`
            ADD COLUMN `{$data['field']}` {$data['field_define']} COMMENT '{$data['name']}';
LEMO;
        Db::execute($sql);
        $fieldid = self::create($data);
        if ($fieldid) {
            return true;
        } else {
            //删除字段
            Db::execute("ALTER TABLE  `{$tablename}` DROP  `{$data['field']}`");
            throw new \Exception('add fail');

        }
        return true;
    }

    /**
     *  编辑字段
     * @param type $data 编辑字段数据
     * @param type $fieldid 字段id
     * @return boolean
     */
    public static function editField($data, $fieldid = 0)
    {
        $data['field'] = strtolower($data['field']);
        if (!isset($data['fieldid'])) {
            throw new \Exception(lang('fieldid is not exist'));
        } else {
            $fieldid = $fieldid ? $fieldid : (int) $data['fieldid'];
        }
        //原字段信息
        $info = self::where("id", $fieldid)->find();
        if (empty($info)) {
            throw new \Exception("field'" . $fieldid . "`is already exist");
        }
        //模型id
        $data['moduleid'] = $moduleid = $info['moduleid'];

        $tablename = self::get_tablename($moduleid);
        if (!self::table_exists($tablename)) {
            throw new \Exception(lang('table is not exist'));
        }
        $tablename = self::get_table_prefix() . $tablename;
        //判断字段名唯一性
        if (self::where('field',$data['field'])->where('moduleid', $moduleid)->where('id','<>', $fieldid)->find()) {
            throw new \Exception(lang("field'" . $data['field'] . "`is already exist"));
        }
        $sql = <<<LEMO
            ALTER TABLE `{$tablename}`
            CHANGE COLUMN `{$info['field']}` `{$data['field']}` {$data['field_define']} COMMENT '{$data['name']}';
LEMO;
        try {
            Db::execute($sql);
        } catch (\Exception $e) {

            self::addField($data);
        }
        self::update($data, ['id' => $fieldid]);
        return true;
    }

    /**
     * 删除字段
     * @param type $fieldid 字段id
     * @return boolean
     */
    public function deleteField($fieldid)
    {

        //原字段信息
        $info = self::where(array("id" => $fieldid))->find();
        if (empty($info)) {
            throw new \Exception(lang('field is not exist'));
        }
        //模型id
        $moduleid = $info['moduleid'];

        //完整表名获取
        $tablename = self::get_tablename($moduleid, $info['ifsystem']);
        if (!self::table_exists($tablename)) {
            throw new \Exception(lang('table is not exist'));
        }
        $tablename = self::get_table_prefix() . $tablename;
        //sql
        $sql = <<<LEMO
            ALTER TABLE `{$tablename}`
            DROP COLUMN `{$info['name']}`;
LEMO;
        Db::execute($sql);
        self::destroy($fieldid);
        return true;
    }

    /**
     * 根据模型 id，返回表名
     * @param type $moduleid
     * @param type $moduleid
     * @return string
     */
    public static function get_tablename($moduleid)
    {
        $table =Db::name('cms_module')->find($moduleid);
        if($table){
            return $table['name'];
        }else{
            return false;
        }
    }

    /**
     * 检查表是否存在
     * $table 不带表前缀
     */
    public static function table_exists($table)
    {
        $table = self::get_table_prefix() . strtolower($table);
        if (true == Db::query("SHOW TABLES LIKE '{$table}'")) {
            return true;
        } else {
            return false;
        }
    }

    //判断表中是否存在所选字段
    public static function isset_field($table, $field){

        $fields = \think\facade\Db::getTableFields($table);
        return array_search($field,$fields);

    }


}
