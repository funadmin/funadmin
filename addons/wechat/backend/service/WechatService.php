<?php
/**
 * FunAdmin
 * ============================================================================
 * 版权所有 2017-2028 FunAdmin，并保留所有权利。
 * 网站地址: https://www.FunAdmin.com
 * ----------------------------------------------------------------------------
 * 采用最新Thinkphp6实现
 * ============================================================================
 * Author: yuege
 * Date: 2019/9/7
 */

namespace addons\wechat\backend\service;

use addons\wechat\backend\model\AddonsWechatAccount;
use app\common\service\AbstractService;
use EasyWeChat\Factory;
use think\App;

class WechatService extends AbstractService
{
    protected $config =
        [
            'log' => [
                'default' => 'dev', // 默认使用的 channel，生产环境可以改为下面的 prod
                'channels' => [
                    // 测试环境
                    'dev' => [
                        'driver' => 'single',
                        'path' => '../runtime/easywechat.log',
                        'level' => 'debug',
                    ],
                    // 生产环境
                    'prod' => [
                        'driver' => 'daily',
                        'path' => '../runtime/easywechat.log',
                        'level' => 'info',
                    ],
                ],
            ],
            'http' => [
                'max_retries' => 1,
                'retry_delay' => 500,
                'timeout' => 5.0,
                // 'base_uri' => 'https://api.weixin.qq.com/', // 如果你在国外想要覆盖默认的 url 的时候才使用，根据不同的模块配置不同的 uri
            ],
            /**
             * OAuth 配置
             *
             * scopes：公众平台（snsapi_userinfo / snsapi_base），开放平台：snsapi_login
             * callback：OAuth授权完成后的回调页地址
             */
            'oauth' => [
                'scopes' => ['snsapi_userinfo'],
                'callback' => '/examples/oauth_callback.php', //回掉地址
            ],
        ];
    public $wechat;
    public $merchant_id;
    public $wechatObj;

    public function __construct(App $app)
    {
        parent::__construct($app);
        $this->initialize();
    }

    public function initialize($merchant_id = 1)
    {
        // 微信配置
        $this->merchant_id = $merchant_id;
        $this->wechat = AddonsWechatAccount::where('status', 1)
            ->where('merchant_id', $this->merchant_id)
            ->find();
        if ($this->wechat) {
            $this->config = ['app_id' => $this->wechat->app_id,
                'secret' => $this->wechat->app_secret,
                'token' => $this->wechat->w_token,
                'response_type' => 'array',
            ];
            $this->wechatObj = cache('wechatapp_' . $this->wechat->id);
            if (!$this->wechatObj) {
                $this->wechatObj = Factory::officialAccount($this->config);
                cache('wechatapp_' . $this->wechat->id, $this->wechatObj);

            }

        } else {

            return false;
        }
    }


}