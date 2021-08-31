<?php
declare (strict_types = 1);

namespace app\backend\model;
use app\common\model\BaseModel;
use think\Model;
use think\model\concern\SoftDelete;
/**
 * @mixin \think\Model
 */
class Test extends BaseModel
{
    use SoftDelete;
    protected $defaultSoftDelete =0;
    public function __construct(array $data = [])
    {
        parent::__construct($data);
    }
    
    public function getWeekList()
    {
        return ['monday'=>'Week monday','tuesday'=>'Week tuesday','wednesday'=>'Week wednesday',];
    }


    public function getSexdataList()
    {
        return ['male'=>'Sexdata male','female'=>'Sexdata female','secret'=>'Sexdata secret',];
    }


    public function getSwitchList()
    {
        return ['0'=>'Switch 0','1'=>'Switch 1',];
    }


    public function getOpenSwitchList()
    {
        return ['0'=>'OpenSwitch 0','1'=>'OpenSwitch 1',];
    }


    public function getTeststateList()
    {
        return ['1'=>'Teststate 1','2'=>'Teststate 2','3'=>'Teststate 3',];
    }


    public function getTest2stateList()
    {
        return ['0'=>'Test2state 0','1'=>'Test2state 1','2'=>'Test2state 2',];
    }


    public function getStatusList()
    {
        return [0=>"enabled",1=>"disabled"];
    }


}
