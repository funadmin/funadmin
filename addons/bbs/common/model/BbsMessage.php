<?php


namespace addons\bbs\common\model;

use app\common\model\BaseModel;

class BbsMessage extends BaseModel {

    public function __construct(array $data = [])
    {
        parent::__construct($data);
    }

    public function bbs(){
       return  $this->hasOne('Bbs','id','bbs_id');
    }
    /**
     * @param $data
     * @return array
     * 添加消息
     */
    public function add($data){
        if(!$data['receive_id']){
            return ['code'=>0,'msg'=>'接收用户id为空'];
        }
        if(!$data['content']){
            return ['code'=>0,'msg'=>'内容为空'];
        }
        $mess = new self;
        $res = $mess->allowField(['receive_id','send_id','bbs_id','type','score','create_time'])->create($data);

        if($res){
            return ['code'=>1,'msg'=>'发送成功'];
        }else{
            return ['code'=>0,'msg'=>'发送失败'];

        }
    }
}