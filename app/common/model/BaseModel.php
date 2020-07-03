<?php


namespace app\common\model;

use think\facade\Config;
use think\Model;

class BaseModel extends Model
{
    /**
     * @var bool 自动写入2019年8月14日 12:32:24
     */
    protected $autoWriteTimestamp = true;
    protected $createTime = 'create_time';
    protected $updateTime = 'update_time';

    public function __construct(array $data = [])
    {
        parent::__construct($data);

    }

    public static function getList($where = array(), $pageSize=10, $order = ['sort', 'id' => 'desc'])
    {
        return self::where($where)->where($order)->select();

    }

    public static function getOne($id)
    {

        return self::find($id);
    }

    //表前缀
    public static function get_table_prefix(){

        return Config::get('database.connections.mysql.prefix');


    }

    public function add($data){
        $result = $this->save($data);
        if($result) {
            return $result;
        } else {
            return '';
        }
    }

    public function edit($data){
        $info  = $this->find($data['id']);
        unset($data['id']);
        $result = $info->save($data);
        if($result) {
            return $result;
        } else {
            return '';
        }
    }

    public function del($ids){
        if(is_array($ids)){

            $info  = $this->where('id','in',$ids)->select();
        }else{
            $info  = $this->find($ids);
        }
        $result = $info->delete();
        if($result) {
            return $result;
        } else {
            return '';
        }
    }

    public function state($data){
        $id = $data['id'];
        $field =  $data['field'];
        if (empty($id)) {
           return '';
        }
        $info = $this->find($id);
        $info->$field = $info->$field == 1 ? 0 : 1;
        $result = $info->save();
       if($result){
           return $result;
       }else{
           return '';
       }

    }


    
}
