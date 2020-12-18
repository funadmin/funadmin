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
namespace addons\bbs\frontend\controller;

use addons\bbs\common\model\BbsAdv;
use addons\bbs\common\model\Bbs;
use addons\bbs\common\model\BBsCategory;
use think\App;
use think\facade\View;

class Index extends Comm {

    public function __construct(App $app) {
        parent::__construct($app);
        $this->modelClass = new \addons\bbs\common\model\Bbs();
    }
    /**
     * 首页
     * @return \think\response\View
     */
    public function index(){
        $this->getTop();
        $this->all();
        $this->getHots();
        $this->adv();
        $cate = BBsCategory::where('status',1)->select();
        View::assign('cate',$cate);
        return view();
    }
    //轮播图
    protected function adv(){
        $adv = BbsAdv::where('pid',1)->order('id desc')->select();
        View::assign('adv',$adv);
    }
    //首页所有
    protected function all(){
        $all = Bbs::where('status', 1)
            ->withCount('comment')
            ->with([  'cate' => function($query){
                    $query->where('status',1)->field('id,title');
                },
                'member' => function($query){
                    $query->field('id,username,avatar,level_id');
                }])
            ->cache(600)
            ->limit(15)
            ->order('id desc')
            ->paginate($this->pageSize,false,['query'=>$this->request->param()]);
        View::assign('all',$all);
    }


}