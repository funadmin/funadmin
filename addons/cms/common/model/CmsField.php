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
 * Date: 2019/9/2
 */

namespace addons\cms\common\model;

use app\common\model\BaseModel;

use think\Exception;
use think\facade\Db;

/**
 * 字段模型
 */
class CmsField extends BaseModel
{

    protected $name = 'addons_cms_field';

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

        if(in_array($data['field'],['content','id'])){
            throw new \Exception("field'" . $data['field'] . "`is already exist");
        }
        //判断字段名唯一性
        if (self::where('field', $data['field'])->where('moduleid', $moduleid)->value('id')) {
            throw new \Exception("field'" . $data['field'] . "`is already exist");
        }
        //先将字段存在设置的主表或附表里面删除 再将数据存入ModelField
        $sql = <<<EOF
            ALTER TABLE `{$tablename}`
            ADD COLUMN `{$data['field']}` {$data['field_define']} COMMENT '{$data['name']}';
EOF;
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
        $fieldid = $fieldid ? (int)$fieldid : (int)$data['fieldid'];
        //原字段信息
        $one = self::where("id", $fieldid)->find();
        if (empty($one)) {
            throw new \Exception("field'" . $fieldid . "`is already exist");
        }
        //模型id
        $data['moduleid'] = $moduleid = $one['moduleid'];

        $tablename = self::get_tablename($moduleid);
        if (!self::table_exists($tablename)) {
            throw new \Exception(lang('table is not exist'));
        }
        $tablename = self::get_table_prefix() . $tablename;
        //判断字段名唯一性
        if(in_array($data['field'],['content','id'])){
            throw new \Exception("field'" . $data['field'] . "`is already exist");
        }
        if (self::where('field', $data['field'])->where('moduleid', $moduleid)->where('id', '<>', $fieldid)->find()) {
            throw new \Exception(lang("field'" . $data['field'] . "`is already exist"));
        }
        Db::startTrans();
        try {
            $sql = <<<EOF
            ALTER TABLE `{$tablename}`
            CHANGE COLUMN `{$one['field']}` `{$data['field']}` {$data['field_define']} COMMENT '{$data['name']}';
EOF;
            try {
                Db::execute($sql);
            } catch (\Exception $e) {
                self::addField($data);
            }
            self::update($data, ['id' => $fieldid]);
            // 提交事务
            Db::commit();
        } catch (\Exception $e) {
            // 回滚事务
            Db::rollback();
            throw new Exception($e->getMessage());
        }

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
        $table = Db::name('addons_cms_module')->find($moduleid);
        if ($table) {
            return $table['tablename'];
        } else {
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
    public static function isset_field($table, $field)
    {

        $fields = \think\facade\Db::getTableFields($table);
        return array_search($field, $fields);

    }


}
