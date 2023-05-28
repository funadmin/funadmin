<?php
/**
 * ============================================================================
 * Created by FunAdmin.
 * 版权所有 2017-2028 FunAdmin，并保留所有权利。
 * 网站地址: http://www.FunAdmin.com
 * ----------------------------------------------------------------------------
 * 采用最新Thinkphp6实现
 * ============================================================================
 * User: yuege
 * Date: 2020/2/10
 * Time: 18:51
 */

namespace app\backend\controller\sys;

use app\common\controller\Backend;
use app\common\traits\Curd;
use app\backend\model\AttachGroup as AttachGroupModel;
use fun\helper\TreeHelper;
use think\App;
use app\common\annotation\ControllerAnnotation;
use app\common\annotation\NodeAnnotation;
use think\facade\Cache;
use think\facade\View;

/**
 * @ControllerAnnotation(title="文件")
 * Class Attach
 * @package app\backend\controller\sys
 */
class AttachGroup extends Backend
{


    public function __construct(App $app)
    {
        parent::__construct($app);
        $this->modelClass = new AttachGroupModel();
        View::assign('groupTreeList', TreeHelper::cateTree($this->modelClass->select()));
    }
    /**
     * @NodeAnnotation(title="列表")
     * @return mixed|\think\response\Json|\think\response\View
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\DbException
     * @throws \think\db\exception\ModelNotFoundException
     */
    public function index()
    {
        if ($this->request->isAjax()) {
            if ($this->request->param('selectFields')) {
                $this->selectList();
            }
            $list = $this->modelClass
                ->order('pid asc,sort asc')
                ->select()->toArray();
            foreach ($list as $k => &$v) {
                $v['title'] = lang($v['title']);
            }
            unset($v);
            $list = TreeHelper::getTree($list);
            sort($list);
            $result = ['code' => 0, 'msg' => lang('get info success'), 'data' => $list, 'count' => count($list), 'is' => true, 'tip' => '操作成功'];
            return json($result);
        }
        return view();
    }

    /**
     * @NodeAnnotation(title="删除")
     * @return mixed|void
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\DbException
     * @throws \think\db\exception\ModelNotFoundException
     */
    public function delete()
    {
        $ids = $this->request->param('ids') ? $this->request->param('ids') : $this->request->param('id');
        $child= $this->getallIdsBypid($ids);
        if($child){
            $this->error(lang('please delete child node first'));
        }
        if($ids==1){
            $this->error(lang('default group cannot delete'));
        }
        $list = $this->modelClass->where('id','in', $ids)->select();
        if (empty($list)) $this->error('Data is not exist');
        try {
            foreach ($list as $k=>$v){
                $res = \app\common\model\Attach::where('group_id',$v->id)->update(['group_id'=>0]);
                $save = $v->force()->delete();
            }
        } catch (\Exception $e) {
            $this->error(lang("operation success"));
        }
        $save ? $this->success(lang('operation success')) : $this->error(lang("Delete fail"));
    }
    protected function getallIdsBypid($pid)
    {
        $res = $this->modelClass->where('pid', $pid)->select();
        $str = '';
        if (!empty($res)) {
            foreach ($res as $k => $v) {
                $str .= "," . $v['id'];
                $str .= $this->getallIdsBypid($v['id']);
            }
        }
        return $str;
    }


}

