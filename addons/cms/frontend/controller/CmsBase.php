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
namespace addons\cms\frontend\controller;

use app\common\controller\AddonsFrontend;
use app\common\controller\Frontend;
use app\common\model\Addon;
use app\common\model\Config as  ConfigModel;
use think\facade\View;

class CmsBase extends AddonsFrontend {

    //CMS相关配置
    protected $site ;
    public $cmsConfig=null;
    //初始化
    public function initialize()
    {
//        parent::initialize();
//        $config = Addon::where('name','cms')->cache(3600)->find();
//        if($config->status==0){
//            $this->redirect(url('Error/notice'));
//        }
//        $ACTION = $this->request->action();
//        $this->cmsConfig = $cmsConfig = unserialize($config->config);
//        $seo  = $cmsConfig['seo']['value'];
//        $logo  = $cmsConfig['logo']['value'];
//        View::assign('seo',$seo);
//        View::assign('logo',$logo);
//        View::assign('ACTION',$ACTION);
    }
}