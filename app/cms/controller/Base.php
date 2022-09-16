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
namespace app\cms\controller;

use app\common\controller\Frontend;
use app\common\model\Config as  ConfigModel;
use think\App;
use think\facade\Config;
use think\facade\View;
use think\validate\ValidateRule;

class Base extends Frontend {

    //CMS相关配置
    protected $site ;
    public $theme = 'default';
    //初始化
    public $layout = 'layout_main';
    public function __construct(App $app)
    {
        parent::__construct($app);
        $appName = app()->http->getName();
        $addonsconfig = get_addons_config($appName);
        $addonsinfo = get_addons_info($appName);
        $view_config = Config::get('view');
        $view_config = array_merge($view_config,['view_path' => app()->getAppPath() .'view'.DS.$addonsconfig['theme']['value'].DS]);
        View::engine('Think')->config($view_config);
        if($addonsconfig['status']['value']==0 || $addonsinfo['install']==0){
            $this->redirect(addons_url('error/notice'));
        }
        $this->theme =  $addonsconfig['theme']['value'];
        $ACTION = request()->action();
        $seo  = $addonsconfig['seo']['value'];
        $logo  = $addonsconfig['logo']['value'];
        $view = [
            'seo'=>$seo,'logo'=>$logo,'ACTION'=>$ACTION,
            'static_path'=>'/static/'.$appName.'/index/'.$this->theme,
        ];
        View::assign($view);;
    }
}