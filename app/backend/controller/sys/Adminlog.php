<?php
/**
 * FunAdmin
 * ============================================================================
 * 版权所有 2017-2028 FunAdmin，并保留所有权利。
 * 网站地址: https://www.FunAdmin.com
 * ----------------------------------------------------------------------------
 * 采用最新Thinkphp6实现
 * ============================================================================
 * Author: yuege
 * Date: 2017/8/2
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
