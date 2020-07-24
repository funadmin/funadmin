<?php
/**
 * SpeedAdmin
 * ============================================================================
 * 版权所有 2018-2027 SpeedAdmin，并保留所有权利。
 * 网站地址: https://www.SpeedAdmin.cn
 * ----------------------------------------------------------------------------
 * 采用最新Thinkphp6实现
 * ============================================================================
 * Author: yuege
 * Date: 2019/8/2
 */
namespace app\backend\controller\sys;

use app\common\controller\Backend;
use app\common\traits\Curd;
use app\backend\model\AdminLog as LogModel;
use think\App;

class Adminlog extends Backend {

    use Curd;
    public function __construct(App $app)
    {
        parent::__construct($app);
        $this->modelClass = new LogModel();

    }

    /**
     * @return array|string
     * @throws \think\db\exception\DbException
     */
    public function index(){
        if($this->request->isAjax()){

                list($this->page, $this->pageSize,$sort,$where) = $this->buildParames();
                $count = $this->modelClass
                    ->where($where)
                    ->count();
                $list = $this->modelClass
                    ->where($where)
                    ->order($sort)
                    ->page($this->page,$this->pageSize)
                    ->select();
//                if(!empty($list)){
//                    foreach ($list['data'] as $k => $v) {
//                        $useragent = explode('(', $v['log_agent']);
//                        $list['data'][$k]['log_agent'] = $useragent[0]??'';
//                    }
//                }
                $result = ['code' => 0, 'msg' => lang('Delete Data Success'), 'data' => $list, 'count' => $count];
                return json($result);

        }

        return view();
    }




}
