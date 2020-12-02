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

namespace addons\cms\backend\controller;

use app\common\controller\AddonsBackend;
use addons\cms\common\model\CmsAdvPosition;
use app\common\traits\Curd;
use think\App;

class CmsAdvPos extends AddonsBackend
{
    use Curd;

    protected $modelClass;

    public function __construct(App $app)
    {
        parent::__construct($app);
        $this->modelClass = new CmsAdvPosition();
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

    /**
     * @return \think\response\View
     */
    public function add()
    {
        if ($this->request->isPost()) {
            $post = $this->request->post();
            $rule = [
                'name|广告位置' => [
                    'require' => 'require',
                ],
            ];
            try {
                $this->validate($post, $rule);
            }catch (\ValidateException $e){
                $this->error(lang($e->getMessage()));
            }
            try {
                $save = $this->modelClass->save($post);
            } catch (\Exception $e) {
                $this->error(lang('Save Failed'));
            }
            $save ? $this->success(lang('Save Success')) : $this->error(lang('Save Failed'));
        }
        $view = [
            'formData' => '',
            'title' => lang('Add'),
        ];
        return view('',$view);
    }


}