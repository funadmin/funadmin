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
use think\App;
use think\facade\Config;
use think\facade\View;
use think\validate\ValidateRule;

class CmsBase extends AddonsFrontend {

    //CMS相关配置
    protected $site ;
    public $cmsConfig=null;
    //初始化
    public function __construct(App $app)
    {
        parent::__construct($app);
        $addonsconfig = get_addons_config('cms');
        $view_config = Config::get('view');
        $view_config = array_merge($view_config,['view_path' => $this->addon_path .'view'.DS.$this->module.DS.$addonsconfig['theme']['value'].DS]);
        View::engine('Think')->config($view_config);
        if($addonsconfig['status']['value']==0){
            $this->redirect(url('Error/notice'));
        }
        $ACTION = $this->request->action();
        $seo  = $addonsconfig['seo']['value'];
        $logo  = $addonsconfig['logo']['value'];
        View::assign('seo',$seo);
        View::assign('logo',$logo);
        View::assign('ACTION',$ACTION);
    }

    public function initialize()
    {

    }

}