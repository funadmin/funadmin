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
use app\common\model\Attach as AttachModel;
use think\App;
use app\common\annotation\ControllerAnnotation;
use app\common\annotation\NodeAnnotation;

/**
 * @ControllerAnnotation(title="文件")
 * Class Attach
 * @package app\backend\controller\sys
 */
class Attach extends Backend
{


    public function __construct(App $app)
    {
        parent::__construct($app);
        $this->modelClass = new AttachModel();

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
            list($this->page, $this->pageSize, $sort, $where) = $this->buildParames();
            $count = $this->modelClass
                ->where($where)
                ->count();
            $list = $this->modelClass
                ->where($where)
                ->order($sort)
                ->page($this->page, $this->pageSize)
                ->select();
            $result = ['code' => 0, 'msg' => lang('operation success'), 'data' => $list, 'count' => $count];
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
        $list = $this->modelClass->where('id','in', $ids)->select();
        if (empty($list)) $this->error('Data is not exist');
        try {
            foreach ($list as $v) {
                @unlink(app()->getRootPath() . 'public' . $v->path);
                $save = $v->delete();
            }
            $save = $list->delete();
        } catch (\Exception $e) {
            $this->error(lang("operation success"));
        }
        $save ? $this->success(lang('operation success')) : $this->error(lang("Delete fail"));
    }

}

