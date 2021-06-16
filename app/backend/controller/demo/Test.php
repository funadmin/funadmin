<?php
declare (strict_types = 1);

namespace app\backend\controller\demo;

use think\Request;
use think\App;
use think\facade\View;
use app\backend\model\Test as TestModel;
use app\common\annotation\NodeAnnotation;
use app\common\annotation\ControllerAnnotation;

/**
 * @ControllerAnnotation(title="测试表")
 */
class Test extends \app\common\controller\Backend
{
    protected $pageSize = 15;
    public function __construct(App $app)
    {
        parent::__construct($app);
        $this->modelClass = new TestModel();
        View::assign('cateIdList',$this->modelClass->getCateIdList());
        View::assign('cateIdsList',$this->modelClass->getCateIdsList());
        View::assign('weekList',$this->modelClass->getWeekList());
        View::assign('sexdataList',$this->modelClass->getSexdataList());
        View::assign('switchList',$this->modelClass->getSwitchList());
        View::assign('openSwitchList',$this->modelClass->getOpenSwitchList());
        View::assign('teststateList',$this->modelClass->getTeststateList());
        View::assign('test2stateList',$this->modelClass->getTest2stateList());
        View::assign('statusList',$this->modelClass->getStatusList());


    }

    /**
    * @NodeAnnotation (title="List")
    */
    public function index()
    {
        $this->relationSearch = true;
        if ($this->request->isAjax()) {
            list($this->page, $this->pageSize,$sort,$where) = $this->buildParames('','',1);
            $count = $this->modelClass
                ->where($where)
                ->withJoin('testCate')->count();
            $list = $this->modelClass
                ->where($where)
                ->withJoin('testCate')
                ->order($sort)
                ->page($this->page,$this->pageSize)
                ->select();
            $result = ['code' => 0, 'msg' => lang('operation success'), 'data' => $list, 'count' => $count];
            return json($result);
        }
        return view();
    }

    /**
    * @NodeAnnotation (title="Recycle")
    */
    public function recycle()
    {
        $this->relationSearch = true;
        if ($this->request->isAjax()) {
            list($this->page, $this->pageSize,$sort,$where) = $this->buildParames('','',false);
            $where[]  = ['test.status','=',-1];
            $count = $this->modelClass
                ->where($where)
                ->withJoin('testCate')->count();
            $list = $this->modelClass
                ->where($where)
                ->withJoin('testCate')
                ->order($sort)
                ->page($this->page,$this->pageSize)
                ->select();
            $result = ['code' => 0, 'msg' => lang('operation success'), 'data' => $list, 'count' => $count];
            return json($result);
        }
        return view('index');
    }

}

