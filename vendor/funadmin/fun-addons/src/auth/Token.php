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

use think\facade\Config;
use think\facade\Request;
use fun\auth\Send;
use fun\auth\Oauth;
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
    public $authapp = false;
    /**
     * 测试appid，正式请数据库进行相关验证
     */
    public $appid = 'funadmin';
    /**
     * appsecret
     */
    public $appsecret = '';

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
        $this->timeDif = Config::get('api.timeDif')??$this->timeDif;
        $this->refreshExpires =Config::get('api.timeDif')??$this->refreshExpires;
        $this->expires =Config::get('api.timeDif')??$this->expires;
        $this->responseType = Config::get('api.responseType')??$this->responseType;
        $this->responseType = Config::get('api.responseType')??$this->responseType;
        $this->authapp = Config::get('api.authapp')??$this->authapp;

        if ($this->authapp) {
            $appid = Request::post('appid');
            $appsecret = Request::post('appsecret');
            $oauth2_client = Db::name('oauth2_client')->where('appid', $appid)->find();
            if (!$oauth2_client) {
                $this->error('Invalid authorization credentials', '', 401);
            }
            if ($oauth2_client['appsecret'] != $appsecret) {
                $this->error(lang('appsecret is not right'));
            }
            $this->appid = $oauth2_client['appid'];
            $this->appsecret = $oauth2_client['appsecret'];
        }
    }

    /**
     * 生成token
     */
    public function accessToken(Request $request)
    {
        //参数验证
        $validate = new \fun\auth\validate\Token;
        if ($this->authapp) {
            if (!$validate->scene('authapp')->check(Request::post())) {
                $this->error($validate->getError(),'',500);
            }
        } else {
            if (!$validate->scene('noauthapp')->check(Request::post())) {
                $this->error($validate->getError(),'',500);
            }
        }
        $this->checkParams(Request::post());  //参数校验
        //数据库已经有一个用户,这里需要根据input('mobile')去数据库查找有没有这个用户
        $memberInfo = $this->getMember(Request::post('username'), Request::post('password'));
        //虚拟一个uid返回给调用方
        try {
            $accessToken = $this->setAccessToken(array_merge($memberInfo, ['appid'=>Request::post('appid')]));  //传入参数应该是根据手机号查询改用户的数据
        } catch (\Exception $e) {
            $this->error($e->getMessage(), $e, 500);
        }
        $this->success('success', $accessToken);

    }

    /**
     * token 过期 刷新token
     */
    public function refresh()
    {
        $refresh_token = Request::post('refresh_token')?Request::post('refresh_token'):Request::get('refresh_token');
        $refresh_token_info = Db::name('oauth2_access_token')
            ->where('refresh_token',$refresh_token)->order('id desc')->find();
        if (!$refresh_token_info) {
            $this->error('refresh_token is error', '', 401);
        } else {
            if ($refresh_token_info['refresh_expires_time'] <time()) {
                $this->error('refresh_token is expired', '', 401);
            } else {    //重新给用户生成调用token
                $member =  Db::name('member')->where('status',1)->find($refresh_token_info['member_id']);
                $client =  Db::name('oauth2_client')
                    ->field('appid')->find($refresh_token_info['client_id']);
                $clientInfo = array_merge($member,$client);
                $accessToken = $this->setAccessToken($clientInfo,$refresh_token);
                $this->success('success', $accessToken);
            }
        }
    }

    /**
     * 参数检测和验证签名
     */
    public function checkParams($params = [])
    {
        //时间戳校验
        if (abs($params['timestamp'] - time()) > $this->timeDif) {
            
            $this->error('请求时间戳与服务器时间戳异常' . time(), '', 401);
        }
        if ($this->authapp && $params['appid'] !== $this->appid) {
            //appid检测，查找数据库或者redis进行验证
            $this->error('appid 错误', '', 401);
        }
        //签名检测
        $Oauth = new Oauth();
        $sign = $Oauth->makeSign($params, $this->appsecret);
        if ($sign !== $params['sign']) {
            $this->error('sign错误','', 401);
        }
        

    }

    /**
     * 设置AccessToken
     * @param $clientInfo
     * @return int
     */
    protected function setAccessToken($clientInfo,$refresh_token='')
    {
        $accessTokenInfo = [
            'access_token' => '',//访问令牌
            'expires_time' => time() + $this->expires,      //过期时间时间戳
            'refresh_token' => $refresh_token,//刷新的token
            'refresh_expires_time' => time() + $this->refreshExpires,      //过期时间时间戳
            'client' => $clientInfo,//用户信息
        ];
        $token =  Db::name('oauth2_access_token')->where('member_id',$clientInfo['id'])->order('id desc')->limit(1)->find();
        if($token and $token['expires_time']>time()){
            $accessTokenInfo['access_token'] = $token['access_token'];
            $accessTokenInfo['refresh_token'] = $token['refresh_token'];
            $accessTokenInfo['expires_time'] = $token['expires_time'];
            $accessTokenInfo['refresh_expires_time'] = $token['refresh_expires_time'];
        }else{
            $accessTokenInfo['access_token'] = $this->buildAccessToken();
            $accessTokenInfo['refresh_token'] = $this->getRefreshToken($clientInfo,$refresh_token);
        }
        $this->saveToken($accessTokenInfo);  //保存本次token
        return $accessTokenInfo;
    }

    /**
     * 获取刷新用的token检测是否还有效
     */
    public function getRefreshToken($clientInfo,$refresh_token)
    {
        if(!$refresh_token){
            return $this->buildAccessToken();
        }
        $accessToken =Db::name('oauth2_access_token')->where('member_id',$clientInfo['id'])
            ->where('refresh_token',$refresh_token)
            ->field('refresh_token')
            ->find();
        return $accessToken?$refresh_token:$this->buildAccessToken();
    }

    /**
     * 生成AccessToken
     * @return string
     */
    protected function buildAccessToken($lenght = 32)
    {
        //生成AccessToken
        $str_pol = "1234567890ABCDEFGHIJKLMNOPQRSTUVWXYZ123456789abcdefghijklmnopqrstuvwxyz";
        return substr(str_shuffle($str_pol), 0, $lenght);
    }

    /**
     * 存储token
     * @param $accessTokenInfo
     */
    protected function saveToken($accessTokenInfo)
    {
        $client = Db::name('oauth2_client')->where('appid',$this->appid)
            ->where('appsecret',$this->appsecret)->find();
        $accessToken =Db::name('oauth2_access_token')->where('member_id',$accessTokenInfo['client']['id'])
            ->where('access_token',$accessTokenInfo['access_token'])
            ->find();
        if(!$accessToken){
            $data = [
                'client_id'=>$client['id'],
                'member_id'=>$accessTokenInfo['client']['id'],
                'group'=>isset($accessTokenInfo['client']['group'])?$accessTokenInfo['client']['group']:'api',
                'openid'=>isset($accessTokenInfo['client']['openid'])?$accessTokenInfo['client']['openid']:'',
                'access_token'=>$accessTokenInfo['access_token'],
                'expires_time'=>time() + $this->expires,
                'refresh_token'=>$accessTokenInfo['refresh_token'],
                'refresh_expires_time' => time() + $this->refreshExpires,      //过期时间时间戳
                'create_time' => time()      //创建时间
            ];
            Db::name('oauth2_access_token')->save($data);
        }
    }

    protected function getMember($membername, $password)
    {
        $member = Db::name('member')
            ->where('status',1)
            ->where('username', $membername)
            ->whereOr('mobile', $membername)
            ->whereOr('email', $membername)
            ->field('id,password')
            ->find();
        if ($member) {
            if (password_verify($password, $member['password'])) {
                $member['uid'] = $member['id'];
                unset($member['password']);
                return $member;
            } else {
                $this->error(lang('Password is not right'), '', 401);
            }
        } else {
            $this->error(lang('Account is not exist'), '', 401);
        }
    }
}