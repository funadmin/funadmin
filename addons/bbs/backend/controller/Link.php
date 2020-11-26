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
 * Date: 2019/8/26
 */
namespace addons\bbs\backend\controller;
use app\common\controller\AddonsBackend;
use think\App;
use think\facade\Request;
use think\facade\View;
use addons\bbs\common\model\BbsLink as BbsLinkModel;
use think\Validate;

class Link extends AddonsBackend
{

    public function __construct(App $app)
    {
        parent::__construct($app);
        $this->modelClass = new BbsLinkModel();
    }

}