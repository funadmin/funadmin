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
 * Date: 2019/9/4
 */
namespace addons\wechat\backend\controller;

use addons\wechat\backend\service\WechatService;
use think\App;
use app\common\controller\AddonsBackend;
class WxBase extends AddonsBackend{

    protected $wxapp;
    protected $config;
    public function __construct(App $app) {
        parent::__construct($app);
        $service = new WechatService();
        $this->config = $service->config;
        $this->wxapp = (new WechatService())->wxapp();
    }

}
