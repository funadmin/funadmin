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
use addons\cms\common\model\CmsModule;
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

    /**
     * @param \think\Model $model
     * @return mixed|void
     * @throws \Exception
     * 写入前
     */
    public static function onBeforeWrite($model){
        if (!preg_match("/^([a-zA-Z0-9_]+)$/i", $model['field'])) {
            throw new \Exception(lang('fields_only_support_alphanumeric_underscore'));
        }
        if (is_numeric(substr($model['field'],  0, 1))) {
            throw new \Exception(lang('field_cannot_start_with_a_number'));
        }
    }

    /**
     * 更新字段表
     * @param \think\Model $model
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\DbException
     * @throws \think\db\exception\ModelNotFoundException
     */
    public static function onAfterWrite($model){
        if($model->moduleid){
            $field = CmsField::getfield($model->moduleid,1,'field');
        }else{
            $field = CmsField::getfield($model->diyformid,2,'field');
        }
        $fieledata = '';
        if($field){
            foreach ($field as $k => $v){
                $fieledata.=$v['field'].',';
            }
        }
        if($model->moduleid){
            $cmsmodel = CmsModule::find($model->moduleid);
            $fieledata.='content,';

        }else{
            $cmsmodel = CmsDiyform::find($model->diyformid);
        }
        $fieledata.='uid,adminid,status,create_time,update_time,delete_time,';
        $cmsmodel->fieldlist = $fieledata;
        $cmsmodel->save();
    }
    //添加字段
    public static function addField($data = null)
    {
        $data['field'] = strtolower($data['field']);
        //模型id
        $moduleid = $data['moduleid'];
        $diyformid = $data['diyformid'];
        if($moduleid){
            $tablename = self::get_tablename($moduleid);
        }else{
            $tablename = self::get_tablename($diyformid,2);
        }
        if (!self::table_exists($tablename)) {
            throw new \Exception(lang('table is not exist'));
        }
        //完整表名获取 判断主表 还是副表
        $tablename = self::get_table_prefix() . $tablename;
        if($moduleid) {
            //普通的模型，判断字段是否存在
            if(in_array($data['field'],['content','id'])){
                throw new \Exception("field `" . $data['field'] . " `is already exist");
            }
            //判断字段名唯一性
            if (self::where('field', $data['field'])->where('moduleid', $moduleid)->value('id')) {
                throw new \Exception("field `" . $data['field'] . "`is already exist");
            }
        }else{
            if (self::where('field', $data['field'])->where('diyformid', $diyformid)->value('id')) {
                throw new \Exception("field `" . $data['field'] . "`is already exist");
            }
        }
        Db::startTrans();
        try {

        //先将字段存在设置的主表或附表里面删除 再将数据存入ModelField
            $sql = <<<EOF
                ALTER TABLE `{$tablename}` 
                ADD COLUMN `{$data['field']}` {$data['field_define']} COMMENT '{$data['name']}';
EOF;
            Db::execute($sql);
            $fieldid = self::create($data);
//            if ($fieldid) {
//                return true;
//            } else {
//                //删除字段
//                Db::execute("ALTER TABLE  `{$tablename}` DROP  `{$data['field']}`");
//                throw new \Exception(lang('add fail'));
//
//            }
            Db::commit();
        } catch (\Exception $e) {
            // 回滚事务
            Db::rollback();
            throw new Exception($e->getMessage());
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
            throw new \Exception("field `" . $fieldid . "` is already exist");
        }
        if ($one['field']==$data['field']) {
            self::update($data, ['id' => $fieldid]);
            return true;
        }
        //模型id
        $data['moduleid'] = $moduleid = $one['moduleid'];
        $data['diyformid'] = $diyformid = $one['diyformid'];

        if($moduleid){
            $tablename = self::get_tablename($moduleid);
        }else{
            $tablename = self::get_tablename($diyformid,2);
        }
        if (!self::table_exists($tablename)) {
            throw new \Exception(lang('table is not exist'));
        }
        $tablename = self::get_table_prefix() . $tablename;
        //判断字段名唯一性
        if($moduleid) {
            //普通的模型，判断字段是否存在
            if(in_array($data['field'],['id','content','create_time','update_time','delete_time','status','uid','adminid'])){
                throw new \Exception("field `" . $data['field'] . "` is already exist");
            }
            //判断字段名唯一性
            if (!self::where('field', $data['field'])->where('moduleid', $moduleid)->value('id')) {
                throw new \Exception("field `" . $data['field'] . "` is not already exist");
            }
        }else{
            if(in_array($data['field'],['id','create_time','update_time','delete_time','status','uid','adminid'])){
                throw new \Exception("field `" . $data['field'] . "` is already exist");
            }
            if (self::where('field', $data['field'])->where('diyformid', $diyformid)->value('id')) {
                throw new \Exception("field `" . $data['field'] . "` is already exist");
            }

        }
        Db::startTrans();
        try {
            $sql = <<<EOF
            ALTER TABLE `{$tablename}`
            CHANGE COLUMN `{$one['field']}` `{$data['field']}` {$data['field_define']} COMMENT '{$data['name']}';
EOF;
            try {
                Db::execute($sql);
                self::update($data, ['id' => $fieldid]);
            } catch (\Exception $e) {
                self::addField($data);
            }
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
        Db::startTrans();
        try {
            //原字段信息
            $one = self::find($fieldid);
            if (empty($one)) {
                throw new \Exception(lang('field is not exist'));
            }
            $moduleid = $one['moduleid'];
            $diyformid = $one['diyformid'];
            if($moduleid){
                $tablename = self::get_tablename($moduleid,1);
            }else{
                //获取完整表名
                $tablename = self::get_tablename($diyformid,2);
            }
            if (!self::table_exists($tablename)) {
                throw new \Exception(lang('table is not exist'));
            }
            $tablename = self::get_table_prefix() . $tablename;
            //sql
            $sql = <<<EOF
                ALTER TABLE `{$tablename}`
                DROP COLUMN `{$one['field']}`;
EOF;
            Db::execute($sql);
            self::destroy($fieldid);
            Db::commit();
        } catch (\Exception $e) {
            // 回滚事务
            Db::rollback();
            throw new Exception($e->getMessage());
        }
        return true;
    }

    /**
     * 获取所有字段列表
     * @param $moduleid
     * @param int $type
     * @param string $field
     * @return mixed
     */
    public static function getfield($moduleid,$type=1,$field='*'){
        if($type ==1){
            return self::where('moduleid',$moduleid)->field($field)->select();
        }
        return self::where('diyformid',$moduleid)->field($field)->select();
    }
    /**
     * 根据模型 id，返回表名
     * @param type $moduleid
     * @param type $moduleid
     * @return string
     */
    public static function get_tablename($modiyid,$type=1)
    {
        if($type ==1){
            $table = Db::name('addons_cms_module')->find($modiyid);
            if ($table) {
                return $table['tablename'];
            } else {
                return false;
            }
        }else{
            $table = Db::name('addons_cms_diyform')->find($modiyid);
            if ($table) {
                return $table['tablename'];
            } else {
                return false;
            }
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
