<?php
/**
 * FunAdmin
 * ============================================================================
 * 版权所有 2017-2028 FunAdmin，并保留所有权利。
 * 网站地址: http://www.FunAdmin.com
 * ----------------------------------------------------------------------------
 * 采用最新Thinkphp8实现
 * ============================================================================
 * Author: yuege
 * Date: 2017/8/2
 */

namespace app\backend\controller\member;

use app\common\controller\Backend;
use app\common\model\Provinces;
use app\common\traits\Curd;
use fun\builder\TableBuilder;
use fun\FormBuilder;
use think\facade\Request;
use think\facade\View;
use app\backend\model\MemberLevel;
use app\backend\model\MemberGroup;
use app\backend\model\Member as MemberModel;
use think\App;

class Member extends Backend
{
    protected $allowModifyFields = ['*'];
    protected $relationSearch = true;
    public function __construct(App $app)
    {
        parent::__construct($app);
        $this->modelClass = new MemberModel();
        View::assign('memberLevel', MemberLevel::column('name','id'));
    }

    public function getcitys(){
        if($this->request->isAjax()) {}{
            $pid = $this->request->param('pid',0);
            $pid = $this->request->param('province_id',0);
            $city_id = $this->request->param('city_id');
            if($city_id){
                $citys = Provinces::where('pid',$city_id)->order('id desc')->field('id,name,pid')
                    ->cache('provinces.'.$city_id,3600*24)->select()->toArray();
            }else{
                $citys = Provinces::where('pid',$pid)->order('id desc')->field('id,name,pid')
                    ->cache('provinces.'.$pid,3600*24)->select()->toArray();
            }

            $this->success('','',$citys);
        }
    }
    public function getgroup(){
        if($this->request->isAjax()) {}{
            $memberGroup = MemberGroup::where('status', 1)->select()->toArray();

            $this->success('','',$memberGroup);
        }
    }
    public function index()
    {
        if ($this->request->isAjax()) {
            if ($this->request->param('selectFields')) {
                $this->selectList();
            }
            list($this->page, $this->pageSize, $sort, $where) = $this->buildParames();
            $list = $this->modelClass
                ->withJoin(['group','level'])
                ->where($where)
                ->order($sort)
                ->paginate([
                    'list_rows'=> $this->pageSize,
                    'page' => $this->page,
                ]);
            $result = ['code' => 0, 'msg' => lang('Get Data Success'), 'data' => $list->items(), 'count' =>$list->total()];
            return json($result);
        }
/*        $cols = [
            ['checkbox' => true],
            ['field' => 'id', 'title' => __('ID'),],
            ['field' => 'username', 'title' => __('memberName'), 'width' => 120,],
            ['field' => 'email', 'title' => __('Email'), 'width' => 180,],
            ['field' => 'mobile', 'title' => __('mobile'), 'width' => 120, 'edit' => 'text'],
            [
                'field' => 'sex',
                'title' => __('Sex'),
                'filter' => 'sex',
                'width' => 120,
                'search' => 'select',
                'selectList' => [0 => __('Secret'), 1 => __('Male'), 2 => __('Female')],
                'templet' => 'Table.templet.select',
                'tips' => __('Female') . '|' . __('Male')
            ],
            [
                'field' => 'memberLevel.name',
                'title' => __('MemberLevel'),
                'width' => 120,
//                'url' => 'member.memberLevel/index',
                // selectList=> memberLevel,
                'prop' => 'name,name',
                'search' => 'select',
                'templet' => 'Table.templet.tags'
            ],
        ];
        $operat = [
            'fixed' => 'right',
            'minWidth' => 80,
            'align' => 'center',
            'title' => __('Operat'),
            'init' => 'Table.init',
            'templet' => 'Table.templet.operat',
            'operat' => ['edit', 'destroy',]
        ];
        $js = <<<EOF
<script>
$('.layui-btn').click(function (){
    alert('111')
})

</script>

EOF;

        return TableBuilder::instance()->cols($cols)->operat($operat)->extraJs($js)->assign()->view();
 */       return view();
    }

    public function add()
    {
        if ($this->request->isPost()) {
            $post = $this->request->post();
            $rule = [
                'username|用户名' => 'require|unique:member',
                'mobile|手机号' => 'require|unique:member',
            ];
            $this->validate($post, $rule);
            $save = $this->modelClass->save($post);
            if ($save) {
                $this->success(lang('operation success'));
            } else {
                $this->error(lang('add fail'));
            }
        }
        $memberLevel = MemberLevel::where('status', 1)->select();
        $memberGroup = MemberGroup::where('status', 1)->select();

        $view = [
            'formData' => '',
            'title' => lang('Add'),
            'memberLevel' => $memberLevel,
            'memberGroup' => $memberGroup,
        ];
        View::assign($view);
        return view();
    }

    public function edit()
    {
        $id = $this->request->get('id');
        if ($this->request->isPost()) {
            $list = $this->modelClass->find($id);
            empty($list) && $this->error(lang('Data is not exist'));
            $post = $this->request->post();
            $rule = [
                'username|用户名' => 'require',
                'group_id|用户组别' => 'require',
                'level_id|用户级别' => 'require',
            ];
            $this->validate($post, $rule);
            $res = $list->save($post);
            if ($res) {
                $this->success(lang('operation success'), __u('index'));
            } else {
                $this->error(lang('Edit fail'));
            }
        }
        $list = MemberModel::find(Request::get('id'));
        $memberLevel = MemberLevel::where('status', 1)->select();
        $memberGroup = MemberGroup::where('status', 1)->select();
        $view = [
            'formData' => $list,
            'title' => lang('Edit'),
            'memberLevel' => $memberLevel,
            'memberGroup' => $memberGroup,
        ];
        View::assign($view);
        return view('add');
    }

    public function copy()
    {
        $id = $this->request->get('id');
        if ($this->request->isPost()) {
            $list = $this->modelClass->find($id);
            empty($list) && $this->error(lang('Data is not exist'));
            $post = $this->request->post();
            $rule = [
                'username|用户名' => 'require',
                'group_id|用户组别' => 'require',
                'level_id|用户级别' => 'require',
            ];
            $this->validate($post, $rule);
            try {
                $data = $list->toArray();
                $data = array_merge($data,$post);
                if(isset($data['create_time'])){
                    unset($data['create_time']);
                }
                if(isset($data['update_time'])){
                    unset($data['update_time']);
                }
                unset($data['id']);
                $this->modelClass->save($data);
            } catch (\Exception $e) {
                $this->error(lang($e->getMessage()));
            }
            $this->success(lang('operation success'));
        }
        $list = MemberModel::find(Request::get('id'));
        $memberLevel = MemberLevel::where('status', 1)->select();
        $memberGroup = MemberGroup::where('status', 1)->select();
        $view = [
            'formData' => $list,
            'title' => lang('Edit'),
            'memberLevel' => $memberLevel,
            'memberGroup' => $memberGroup,
        ];
        View::assign($view);
        return view('add');
    }
    public function recycle()
    {
        if ($this->request->isAjax()) {
            list($this->page, $this->pageSize, $sort, $where) = $this->buildParames();
            $count = $this->modelClass->onlyTrashed()
                ->withJoin(['group','level'])
                ->where($where)
                ->count();
            $list = $this->modelClass->onlyTrashed()
                ->withJoin(['group','level'])
                ->where($where)
                ->order($sort)
                ->page($this->page, $this->pageSize)
                ->select();
            $result = ['code' => 0, 'msg' => lang('Get Data Success'), 'data' => $list, 'count' => $count];
            return json($result);
        }

        /*
         $cols = [
            ['field' => 'sex',
                'title' => __('Sex'),
                'filter' => 'sex',
                'width' => 120,
                'search' => 'select',
                'selectList' => [0 => __('Secret'), 1 => __('Male'), 2 => __('Female')],
                'templet' => 'Table.templet.select',
                'tips' => __('Female') . '|' . __('Male')
            ],
            ['field' => 'username', 'title' => __('memberName'), 'width' => 120,],
        ];
        return TableBuilder::instance()->cols($cols)->assign()->view();
        */
        return view('index');
    }


}
