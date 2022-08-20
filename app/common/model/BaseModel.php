<?php


namespace app\common\model;

use think\facade\Config;
use think\Model;
use think\model\concern\SoftDelete;
class BaseModel extends Model
{

    public function __construct(array $data = [])
    {
        parent::__construct($data);
    }

    //表前缀
    public static function get_table_prefix(){

        return Config::get('database.connections'.'.'.config('database.default').'.prefix');
    }
    //当前数据库
    public static function get_databasename(){
        return Config::get('database.connections'.'.'.Config::get('database.default').'.database');
    }

    public static function get_addonstablename($tablename,$addon)
    {
        $tablename = str_replace($addon.'_','',str_replace('addons_','',$tablename));
        return $tablename = self::get_table_prefix() .'addons_'.$addon.'_'. $tablename;
    }


}
