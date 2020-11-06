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
namespace app\backend\controller\member;

use app\common\controller\Backend;
use app\common\traits\Curd;
use think\facade\Request;
use think\facade\View;
use app\backend\model\MemberLevel;
use app\backend\model\MemberGroup;
use app\backend\model\Member as MemberModel;
use think\App;

class Member extends Backend{

    use Curd;
    public function __construct(App $app)
    {
        parent::__construct($app);
        $this->modelClass =  new MemberModel();
    }


    public function index(){
        if ($this->request->isAjax()) {
            list($this->page, $this->pageSize,$sort,$where) = $this->buildParames();
            $count = $this->modelClass->with('group')
                ->where($where)
                ->count();
            $list =$this->modelClass
                ->with('memberGroup')
                ->with('memberLevel')
                ->where($where)
                ->order($sort)
                ->page( $this->page,$this->pageSize)
                ->select();
             $result = ['code' => 0, 'msg' => lang('Delete Data Success'), 'data' => $list, 'count' => $count];
             return json($result);
        }
        return view();

    }


    public function add(){
        if ($this->request->isPost()) {
            $post = $this->request->post();
            $rule = [
                'username|用户名'   => 'require|unique:member',
                'mobile|手机号'   => 'require|unique:member',
            ];
            $this->validate($post, $rule);
            $save =$this->modelClass->save($post);
            if ($save) {
                $this->success(lang('Add Success'));
            } else {
                $this->error(lang('add fail'));
            }
        }
        $memberLevel = MemberLevel::where('status',1)->select();
        $memberGroup = MemberGroup::where('status',1)->select();

        $view = [
            'formData' => '',
            'title' => lang('Add'),
            'memberLevel'=>$memberLevel,
            'memberGroup'=>$memberGroup,
        ];
        View::assign($view);
        return view();
    }

    public function edit($id){
        if ($this->request->isPost()) {
            $list  = $this->modelClass->find($id);
            empty($list) && $this->error(lang('Data is not exist'));
            $post = $this->request->post();
            $rule = [
                'username|用户名'   => 'require',
                'group_id|用户组别'   => 'require',
                'level_id|用户级别'   => 'require',
            ];
            $this->validate($post, $rule);
            $res = $list->save($post);
            if ($res) {
                $this->success(lang('Edit success'), __u('index'));
            } else {
                $this->error(lang('Edit fail'));
            }
        }
        $list = MemberModel::find(Request::get('id'));
        $memberLevel = MemberLevel::where('status',1)->select();
        $memberGroup = MemberGroup::where('status',1)->select();
        $view = [
            'formData' => $list,
            'title' => lang('Edit'),
            'memberLevel'=>$memberLevel,
            'memberGroup'=>$memberGroup,

        ];
        View::assign($view);
        return view('add');

    }



}