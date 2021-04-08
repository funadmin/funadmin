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
 * Date: 2019/10/3
 */

namespace fun\auth;

use app\common\service\PredisService;
use think\facade\Request;
use fun\auth\Send;
use fun\auth\Oauth;
use think\facade\Cache;
use app\common\model\WxFans;
use think\facade\Db;
use think\Lang;

/**
 * 生成token
 */
class Token
{
    use Send;

    /**
     * @var bool
     * 是否需要验证数据库账号
     */
    public static $authapp = false;
    /**
     * 测试appid，正式请数据库进行相关验证
     */
    public static $appid = '';
    /**
     * appsecret
     */
    public static $appsecret = '';

    /**
     * 构造方法
     * @param Request $request Request对象
     */
    public function __construct(Request $request)
    {

        header('Access-Control-Allow-Origin:*');
        header('Access-Control-Allow-Headers:Accept,Referer,Host,Keep-Alive,User-Agent,X-Requested-With,Cache-Control,Content-Type,Cookie,token');
        header('Access-Control-Allow-Credentials:true');
        header('Access-Control-Allow-Methods:GET, POST, PATCH, PUT, DELETE,OPTIONS');
        $this->request = Request::instance();
        if(self::$authapp){
            $appid = Request::post('appid');
            $appsecret = Request::post('appsecret');
            $oauth2_client = Db::name('oauth2_client')->where('appid', $appid)->find();
            if (!$oauth2_client) {
                self::error('Invalid authorization credentials', '', 401);

            }
            if ($oauth2_client['appsecret'] != $appsecret) {
                self::error(lang('appsecret is not right'));
            }
            self::$appid = $oauth2_client['appid'];
            self::$appsecret = $oauth2_client['appsecret'];
        }

    }

    /**
     * 生成token $accessToken
     */
    public function accessToken(Request $request)
    {
        //参数验证
        $validate = new \fun\auth\validate\Token;
        if(self::$authapp) {
            if (!$validate->scene('authapp')->check(Request::post())) {
                self::error($validate->getError());
            }
        }else{
            if (!$validate->scene('noauthapp')->check(Request::post())) {
                self::error($validate->getError());
            }
        }
        self::checkParams(Request::post());  //参数校验
        //数据库已经有一个用户,这里需要根据input('mobile')去数据库查找有没有这个用户
        $memberInfo = self::getMember(Request::post('username'), Request::post('password'));
        //虚拟一个uid返回给调用方
        try {
            $accessToken = self::setAccessToken(array_merge($memberInfo, Request::post()));  //传入参数应该是根据手机号查询改用户的数据
            self::success('success', $accessToken);
        } catch (\Exception $e) {
            self::error($e, 'fail', 500);
        }
    }

    /**
     * token 过期 刷新token
     */
    public function refresh($refresh_token = '', $appid = '')
    {
        $cache_refresh_token = Cache::get(self::$refreshAccessTokenPrefix . $appid);  //查看刷新token是否存在
        if (!$cache_refresh_token) {
            self::error('refresh_token is null', '', 401);
        } else {
            if ($cache_refresh_token !== $refresh_token) {
                self::error('refresh_token is error', '', 401);
            } else {    //重新给用户生成调用token
                $data['appid'] = $appid;
                $accessToken = self::setAccessToken($data);
                self::success('success', $accessToken);
            }
        }
    }

    /**
     * 参数检测和验证签名
     */
    public static function checkParams($params = [])
    {
        //时间戳校验
        if (abs($params['timestamp'] - time()) > self::$timeDif) {

             self::error('请求时间戳与服务器时间戳异常' . time(), '', 401);
        }

        //appid检测，查找数据库或者redis进行验证
        if ($params['appid'] !== self::$appid) {
             self::error('appid 错误','',401);
        }

        //签名检测
        $sign = Oauth::makeSign($params, self::$appsecret);
        if ($sign !== $params['sign']) {
             self::error('sign错误',401);
        }
    }

    /**
     * 设置AccessToken
     * @param $clientInfo
     * @return int
     */
    protected function setAccessToken($clientInfo)
    {
        //生成令牌
        $accessToken = self::buildAccessToken();
        $refresh_token = self::getRefreshToken($clientInfo['appid']);

        $accessTokenInfo = [
            'access_token' => $accessToken,//访问令牌
            'expires_time' => time() + self::$expires,      //过期时间时间戳
            'refresh_token' => $refresh_token,//刷新的token
            'refresh_expires_time' => time() + self::$refreshExpires,      //过期时间时间戳
            'client' => $clientInfo,//用户信息
        ];
        self::saveAccessToken($accessToken, $accessTokenInfo);  //保存本次token
        self::saveRefreshToken($refresh_token, $clientInfo['appid']);
        return $accessTokenInfo;
    }

    /**
     * 获取刷新用的token检测是否还有效
     */
    public static function getRefreshToken($appid = '')
    {
        return Cache::get(self::$refreshAccessTokenPrefix . $appid) ? Cache::get(self::$refreshAccessTokenPrefix . $appid) : self::buildAccessToken();
    }

    /**
     * 生成AccessToken
     * @return string
     */
    protected static function buildAccessToken($lenght = 32)
    {
        //生成AccessToken
        $str_pol = "1234567890ABCDEFGHIJKLMNOPQRSTUVWXYZ123456789abcdefghijklmnopqrstuvwxyz";
        return substr(str_shuffle($str_pol), 0, $lenght);

    }

    /**
     * 存储token
     * @param $accessToken
     * @param $accessTokenInfo
     */
    protected static function saveAccessToken($accessToken, $accessTokenInfo)
    {   
        $token_type = config('api.auth.token_type');
        cache(self::$accessTokenPrefix . $accessToken, $accessTokenInfo, self::$expires);
    }

    /**
     * 刷新token存储
     * @param $accessToken
     * @param $accessTokenInfo
     */
    protected static function saveRefreshToken($refresh_token, $appid)
    {
        //存储RefreshToken
        cache(self::$refreshAccessTokenPrefix . $appid, $refresh_token, self::$refreshExpires);
    }

    protected static function getMember($membername, $password)
    {
        $member = Db::name('member')->where('username', $membername)
            ->whereOr('mobile', $membername)
            ->whereOr('email', $membername)->find();
        if ($member) {
            if (password_verify($password, $member['password'])) {
                $member['uid'] = $member['id'];
                return $member;
            } else {
                 self::error(lang('Password is not right'),'',401 );
            }

        } else {
             self::error( lang('Account is not exist'),'',401);
        }
    }
}