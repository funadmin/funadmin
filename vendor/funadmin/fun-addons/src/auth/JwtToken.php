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
use fun\auth\Oauth;
use think\facade\Config;
use think\facade\Request;
use fun\auth\Send;
use think\facade\Db;
use think\Lang;
use Firebase\JWT\JWT;
/**
 * 生成token
 */
class JwtToken
{
    use Send;
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
        $this->key = md5(Config::get('api.jwt_key'));
        $this->timeDif = Config::get('api.timeDif')??$this->timeDif;
        $this->refreshExpires =Config::get('api.refreshExpires')??$this->refreshExpires;
        $this->expires =Config::get('api.expires')??$this->expires;
        $this->responseType = Config::get('api.responseType')??$this->responseType;
        $this->authapp = Config::get('api.authapp')??$this->authapp;
        $this->group =  $this->request->param('group')?$this->request->param('group'):'api';

    }

    /**
     * 生成token
     */
    public function accessToken(Request $request)
    {
        //参数验证
        $validate = new \fun\auth\validate\Token;
        if($this->authapp){
            if (!$validate->scene('authappjwt')->check(Request::post())) {
                $this->error($validate->getError(), '', 500);
            }
        }else {
            if (!$validate->scene('jwt')->check(Request::post())) {
                $this->error($validate->getError(), '', 500);
            }
        }
        $this->checkParams(Request::post());  //参数校验
        //数据库已经有一个用户,这里需要根据input('mobile')去数据库查找有没有这个用户
        $memberInfo = $this->getMember(Request::post('username'), Request::post('password'));
        $client = $this->getClient($this->appid,$this->appsecret,'id,group');
        //虚拟一个member_id返回给调用方
        try {
            $accessToken = $this->setAccessToken(array_merge($memberInfo, ['client_id' => $client['id'],'appid'=>$this->appid]));
        } catch (\Exception $e) {
            $this->error($e->getMessage(), $e, 500);
        }
        $this->success('success', $accessToken);

    }
    /**
     * 设置AccessToken
     * @param $memberInfo
     * @return int
     */
    protected function setAccessToken($memberInfo,$refresh_token='')
    {
        $accessTokenInfo = [
            'expires_time'=>time()+$this->expires,
            'refresh_expires_time'=>time()+$this->refreshExpires,
        ];
        $accessTokenInfo = array_merge($accessTokenInfo,$memberInfo);
        $driver = Config::get('api.driver');
        if($driver =='redis'){
            $accessTokenInfo['access_token'] = $this->buildAccessToken($memberInfo,$this->expires);
            $accessTokenInfo['refresh_token'] = $this->buildAccessToken($memberInfo,$this->refreshExpires);
            //可以保存到数据库 也可以去掉下面两句,本身jwt不需要存储
            $this->redis = PredisService::instance();
            $this->redis->set(Config::get('api.redisTokenKey').$this->appid. $this->tableName .  $accessTokenInfo['access_token'],serialize($accessTokenInfo),$this->expires);
            $this->redis->set(Config::get('api.redisRefreshTokenKey') . $this->appid . $this->tableName . $accessTokenInfo['refresh_token'],serialize($accessTokenInfo),$this->refreshExpires);
        }else{
            $token =  Db::name('oauth2_access_token')->where('member_id',$memberInfo['member_id'])
                ->where('tablename',$this->tableName)
                ->where('group',$this->group)
                ->order('id desc')->limit(1)
                ->find();
            if($token and $token['expires_time'] > time() && !$refresh_token) {
                $accessTokenInfo['access_token'] = $token['access_token'];
                $accessTokenInfo['refresh_token'] = $token['refresh_token'];
                $accessTokenInfo['expires_time'] = $token['expires_time'];
                $accessTokenInfo['refresh_expires_time'] = $token['refresh_expires_time'];
            }else{
                $accessTokenInfo['access_token'] = $this->buildAccessToken($memberInfo,$this->expires);
                $accessTokenInfo['refresh_token'] = $this->getRefreshToken($memberInfo,$refresh_token);
            }
            $this->saveToken($accessTokenInfo);  //保存本次token
        }
        return $accessTokenInfo;
    }

    /**
     * token 过期 刷新token
     */
    public function refresh()
    {
        $refresh_token = Request::param('refresh_token');
        if(Config::get('api.driver')=='redis'){
            $this->redis = PredisService::instance();
            $refresh_token_info = $this->redis->get(Config::get('api.redisRefreshTokenKey').$this->appid.$this->tableName.$refresh_token);
            $refresh_token_info = unserialize($refresh_token_info);
        }else{
            $refresh_token_info = Db::name('oauth2_access_token')
                ->where('refresh_token',$refresh_token)
                ->where('tablename',$this->tableName)
                ->where('group',$this->group)
                ->order('id desc')->find();
        }
        if (!$refresh_token_info) {
            $this->error('refresh_token is error or expired', '', 401);
        }
        if ($refresh_token_info['refresh_expires_time'] <time()) {
            $this->error('refresh_token is error or expired', '', 401);
        }
        //重新给用户生成调用token
        $member =  Db::name($this->tableName)->where('status',1)
            ->field('id as member_id')->find($refresh_token_info['member_id']);
        $client =  Db::name('oauth2_client')
            ->field('id as client_id,appid,group')->find($refresh_token_info['client_id']);
        $memberInfo = array_merge($member,$client);
        $accessToken = $this->setAccessToken($memberInfo,$refresh_token);
        $this->success('success', $accessToken);
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
        if ($this->authapp && $params['appsecret'] !== $this->appsecret) {
            //appid检测，查找数据库或者redis进行验证
            $this->error('appsecret 错误', '', 401);
        }
        if($this->authapp){
            $oauth2_client = Db::name('oauth2_client')
                ->where('appid', $params['appid'])
                ->where('appsecret', $params['appsecret'])
                ->field('id')
                ->find();
            if (!$oauth2_client) {
                $this->error('Invalid authorization app', '', 401);
            }
        }

    }

    /**
     * 生成AccessToken
     * @return string
     */
    protected function buildAccessToken($memberInfo,$expires)
    {
        $time = time(); //签发时间
        $expire = $time + $expires; //过期时间
        $scopes = 'role_access';
        if($expires==$this->refreshExpires)  $scopes = 'role_refresh';
        $token = array(
            "member_id" => $memberInfo['member_id'],
            'appid'=>$this->appid,
            'appsecret'=>$this->appsecret,
            "iss" => "https://www.funadmin.com",//签发组织
            "aud" => "https://www.funadmin.com", //签发作者
            "scopes" => $scopes, //刷新
            "iat" => $time,
            "nbf" => $time,
            "exp" => $expire,      //过期时间时间戳
        );
        return   JWT::encode($token,  $this->key, 'HS256');
    }

    /**
     * 获取刷新用的token检测是否还有效
     */
    protected function getRefreshToken($memberInfo,$refresh_token)
    {
        if(!$refresh_token){
            return $this->buildAccessToken($memberInfo,$this->refreshExpires);
        }
        $accessToken =Db::name('oauth2_access_token')->where('member_id',$memberInfo['member_id'])
            ->where('refresh_token',$refresh_token)
            ->where('tablename',$this->tableName)
            ->where('group',$this->group)
            ->field('refresh_token')
            ->find();
        return $accessToken?$refresh_token:$this->buildAccessToken($memberInfo,$this->refreshExpires);
    }

    /**
     * 存储token
     * @param $accessTokenInfo
     */
    protected function saveToken($accessTokenInfo)
    {
        $accessToken =Db::name('oauth2_access_token')->where('member_id',$accessTokenInfo['member_id'])
            ->where('tablename',$this->tableName)
            ->where('group',$this->group)
            ->find();
        $data = [
            'client_id'=>$accessTokenInfo['client_id'],
            'member_id'=>$accessTokenInfo['member_id'],
            'tablename'=>$this->tableName,
            'group'=>$this->group,
            'openid'=>isset($accessTokenInfo['openid'])?$accessTokenInfo['openid']:'',
            'access_token'=>$accessTokenInfo['access_token'],
            'expires_time'=>time() + $this->expires,
            'refresh_token'=>$accessTokenInfo['refresh_token'],
            'refresh_expires_time' => time() + $this->refreshExpires,      //过期时间时间戳
            'create_time' => time()      //创建时间
        ];
        if(!$accessToken){
            Db::name('oauth2_access_token')->save($data);
        }else{
            Db::name('oauth2_access_token')->where('member_id',$accessTokenInfo['member_id'])
                ->where('group',$this->group)
                ->update($data);
        }
        return true;
    }

    protected function getMember($membername, $password)
    {
        $member = Db::name($this->tableName)
            ->where('status',1)
            ->where('username', $membername)
            ->whereOr('mobile', $membername)
            ->whereOr('email', $membername)
            ->field('id as member_id,password')
            ->cache($this->appid.$membername,3600)
            ->find();
        if ($member) {
            if (password_verify($password, $member['password'])) {
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
