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
 * Date: 2019/9/22
 */

namespace fun\auth;

use app\common\service\PredisService;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use fun\auth\Send;
use think\Exception;
use think\facade\Db;
use think\facade\Config;
use think\facade\Request;

/**
 * API鉴权验证
 */
class SimpleOauth
{
    use Send;

    protected $options = [];
    protected $config = [];

    protected static $instance = null;

    public function __construct($options = [])
    {
        if ($config = Config::get('api')) {
            $this->config = array_merge($this->config, $config);
        }
        $this->options = array_merge($this->config, $options);
    }

    /**
     *
     * @param array $options 参数
     * @return Oauth
     */
    public static function instance($options = [])
    {
        if (is_null(self::$instance)) {
            self::$instance = new static($options);
        }

        return self::$instance;
    }

    /**
     * 认证授权 通过用户信息和路由
     * @return array|mixed
     */
    final function authenticate()
    {
        return $this->certification();
    }

    /**
     * 获取用户信息后 验证权限
     * @return mixed
     */
    public function certification()
    {
        $match = $this->match(!empty($this->options['noAuth'])?$this->options['noAuth']:[]);
        if (!$match || ($match && Request::header(config('api.authentication')))) {               //请求方法白名单
            try {
                $data = $this->getClient();
            }catch (\Exception $e) {
                if(!$match) $this->error($e->getMessage(),[],401);
            }

            return $data;
        }
        return '';
       
    }

    /**
     * 解密
     * @param $authorizationHeader
     * @return array
     * @throws \Exception
     */
   
}
