<?php
/**
 * funadmin
 * ============================================================================
 * 版权所有 2018-2027 funadmin，并保留所有权利。
 * 网站地址: https://www.funadmin.com
 * ----------------------------------------------------------------------------
 * 采用最新Thinkphp6实现
 * ============================================================================
 * Author: yuege
 * Date: 2019/8/2
 */

namespace app\cms\controller\backend;

use app\cms\controller\backend\CmsBackend;
use app\cms\model\AdvPosition;

use think\App;

class AdvPos extends CmsBackend
{


    protected $modelClass;

    public function __construct(App $app)
    {
        parent::__construct($app);
        $this->modelClass = new AdvPosition();
    }
    /*-----------------------广告管理----------------------*/
    public function index()
    {
        if ($this->request->isAjax()) {
            list($this->page, $this->pageSize, $sort, $where) = $this->buildParames();
            $count = $this->modelClass->with('group')
                ->where($where)
                ->count();
            $list = $this->modelClass
                ->where($where)
                ->order($sort)
                ->page($this->page, $this->pageSize)
                ->select();
            $result = ['code' => 0, 'msg' => lang('operation success'), 'data' => $list, 'count' => $count];
            return $result;
        }
        return view();
    }

}