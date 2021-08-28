<?php
/**
 * FunAdmin
 * ============================================================================
 * 版权所有 2017-2028 FunAdmin，并保留所有权利。
 * 网站地址: http://www.FunAdmin.com
 * ----------------------------------------------------------------------------
 * 采用最新Thinkphp6实现
 * ============================================================================
 * Author: yuege
 * Date: 2017/8/2
 */
namespace app\backend\controller\sys;

use app\common\controller\Backend;
use app\backend\model\AdminLog as LogModel;
use think\App;
use app\common\annotation\ControllerAnnotation;
use app\common\annotation\NodeAnnotation;
use think\facade\Db;

/**
 * @ControllerAnnotation(title="日志")
 * Class Adminlog
 * @package app\backend\controller\sys
 */
class Adminlog extends Backend {
    public function __construct(App $app)
    {
        parent::__construct($app);
        $this->modelClass = new LogModel();

    }

    /**
     * @NodeAnnotation(title="列表")
     * @return array|string
     * @throws \think\db\exception\DbException
     */
    public function index(){
        if($this->request->isAjax()){
            if ($this->request->param('selectFields')) {
                $this->selectList();
            }
            list($this->page, $this->pageSize,$sort,$where) = $this->buildParames();
            if(session('admin.group_id') != 1){
                $where[] = ['admin_id','=',session('admin.id')];
            }
            $count = $this->modelClass
                ->where($where)
                ->count();
            $list = $this->modelClass
                ->where($where)
                ->order($sort)
                ->page($this->page,$this->pageSize)
                ->select();
            $result = ['code' => 0, 'msg' => lang('operation Success'), 'data' => $list, 'count' => $count];
            return json($result);
        }
        return view();
    }




}
