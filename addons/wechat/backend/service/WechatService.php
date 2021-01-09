<?php

namespace addons\wechat\backend\service;

use app\common\service\AbstractService;
use addons\wechat\backend\model\WechatAccount;
use EasyWeChat\Factory;
use think\App;
class WechatService extends AbstractService
{
    protected $wxapp;
    public $config = [];
    public function __construct(App $app)
    {
        parent::__construct($app);
        $this->config = WechatAccount::where('status',1)->cache(3600)->find();
        $this->config['response_type'] = 'array';
    }
    public function wxapp(){
        $this->wxapp = Factory::officialAccount($this->config);
        return $this->wxapp;
    }

}