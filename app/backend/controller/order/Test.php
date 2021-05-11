<?php
declare (strict_types = 1);

namespace app\backend\controller\order;

use think\Request;
use think\App;
use think\facade\View;
use app\backend\model\Test as TestModel;
use app\common\annotation\NodeAnnotation;
use app\common\annotation\ControllerAnnotation;

/**
 * @ControllerAnnotation ('Test')
 */
class Test extends \app\common\controller\Backend
{
    protected $pageSize = 15;
    public function __construct(App $app)
    {
        parent::__construct($app);
        $this->modelClass = new TestModel();
        View::assign('weekList',$this->modelClass->getWeekList());
        View::assign('sexdataList',$this->modelClass->getSexdataList());
        View::assign('switchList',$this->modelClass->getSwitchList());
        View::assign('open_switchList',$this->modelClass->getOpen_switchList());
        View::assign('teststateList',$this->modelClass->getTeststateList());
        View::assign('test2stateList',$this->modelClass->getTest2stateList());


    }

    
}

