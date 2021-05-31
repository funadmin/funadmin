<?php
declare (strict_types = 1);

namespace app\backend\model;
use app\common\model\BaseModel;
use think\Model;

/**
 * @mixin \think\Model
 */
class Test extends BaseModel
{
    public function __construct(array $data = [])
    {
        parent::__construct($data);
    }
    
    public function getCateIdList()
    {
        return ;
    }


    public function getCateIdsList()
    {
        return ;
    }


    public function getWeekList()
    {
        return ['monday'=>'星期一','tuesday'=>'星期二','wednesday'=>'星期三',];
    }


    public function getSexdataList()
    {
        return ['male'=>'男','female'=>'女','secret'=>'保密',];
    }


    public function getSwitchList()
    {
        return ['0'=>'下架','1'=>'正常',];
    }


    public function getOpenSwitchList()
    {
        return ['0'=>'OFF','1'=>'ON',];
    }


    public function getTeststateList()
    {
        return ['1'=>'选项1','2'=>'选项2','3'=>'选项3',];
    }


    public function getTest2stateList()
    {
        return ['0'=>'唱歌','1'=>'跳舞','2'=>'游泳',];
    }


    public function getStatusList()
    {
        return [0=>"enabled",1=>"disabled"];
    }


}
