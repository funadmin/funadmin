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
use think\facade\Config;
use think\facade\Request;
use fun\auth\Send;
use think\facade\Db;
use think\Lang;
use Firebase\JWT\JWT;
/**
 * 生成token
 */
class Token
{
    use Send;

    protected static $instance = null;

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
     * 构造方法
     * @param Request $request Request对象
     */
    public function __construct($options=[])
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
        $this->group =  $this->request->param('group')?:$this->group;
        $this->merchant_id =  $this->request->param('merchant_id')?$this->request->param('merchant_id'):0;

    }

    /**
     * 生成token
     */
    public function build()
    {
        //参数验证
        $validate = new \fun\auth\validate\Token;
        if (!$validate->scene('jwt')->check(Request::post())) {
            $this->error(lang($validate->getError()), '', 500);
        }
        $this->checkParams(Request::post());  //参数校验
        //数据库已经有一个用户,这里需要根据input('mobile')去数据库查找有没有这个用户
        $memberInfo = $this->getMember(Request::post('username'), Request::post('password'));
        //虚拟一个member_id返回给调用方
        try {
            $accessToken = $this->setAccessToken(array_merge($memberInfo, [
                'client_id' => $this->client['id'],
                'appid'=>$this->appid,
                'group'=>$this->group,
                'merchant_id'=>$this->merchant_id,
            ]));
        } catch (\Exception $e) {
            $this->error(lang($e->getMessage()), [], 500);
        }
        $this->success(lang('success'), $accessToken);

    }

    /**
     * token 过期 刷新token
     */
    public function refresh()
    {
        $refresh_token = Request::post('refresh_token');
        if(Config::get('api.driver')=='redis'){
            $this->redis = PredisService::instance();
            $refresh_token_info = $this->redis->get(Config::get('api.redisRefreshTokenKey').$refresh_token);
            $refresh_token_info = unserialize($refresh_token_info);
        }else{
            $refresh_token_info = Db::name('oauth2_access_token')
                ->where('refresh_token',$refresh_token)
                ->where('tablename',$this->tableName)
                ->where('group',$this->group)
                ->order('id desc')->find();
        }
        if (!$refresh_token_info) {
            $this->error(lang('refresh_token is error or expired'), [], 401);
        }
        if ($refresh_token_info['refresh_expires_time'] <time()) {
            $this->error(lang('refresh_token is error or expired'), [], 401);
        }
        //重新给用户生成调用token
        $member =  Db::name($this->tableName)->where('status',1)
            ->field('id as member_id')->find($refresh_token_info['member_id']);

        if(!$member) $this->error(lang('member is not exist'), [],401);

        $this->client =  $this->getClientData(['id'=>$refresh_token_info['client_id']],'id as client_id,appid,group,merchant_id');
        if(!$this->client) $this->error(lang('client is not exist'), [],401);
        $this->appid  = $this->client['appid'];
        $this->group  = $this->client['group'];
        $this->merchant_id  = $this->client['merchant_id'];
        $memberInfo = array_merge($member,$this->client);
        $accessToken = $this->setAccessToken($memberInfo,$refresh_token);
        $this->success(lang('success'), $accessToken);
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
            $this->redis->set(Config::get('api.redisTokenKey').$accessTokenInfo['access_token'],serialize($accessTokenInfo),$this->expires);
            $this->redis->set(Config::get('api.redisRefreshTokenKey') .$accessTokenInfo['refresh_token'],serialize($accessTokenInfo),$this->refreshExpires);
        }else{
            $where = [
                ['member_id','=',$memberInfo['member_id']],
                ['tablename','=',$this->tableName],
                ['group','=',$this->group]
            ];
            $token =  Db::name('oauth2_access_token')
                ->where($where)
                ->order('id desc')->limit(1)
                ->find();
            if($token && $token['expires_time'] > time() && !$refresh_token) {
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
     * 参数检测和验证签名
     */
    public function checkParams($params = [])
    {
        //时间戳校验
        if (abs($params['timestamp'] - time()) > $this->timeDif) {
            $this->error(lang('Request timestamp and server timestamp are abnormal'), [], 401);
        }
        $where = [
            ['appid','=' ,$params['appid']],
            ['appsecret', '=',$params['appsecret']],
            ['merchant_id', '=',$this->merchant_id],
            ['group', '=',$this->group],
        ];
        $this->client = $this->getClientData($where,'id,appid,appsecret,group,merchant_id');
        if (!$this->client) {
            $this->error(lang('Invalid authorization app'), [], 401);
        }
        $this->appid = $this->client['appid'];
        $this->appsecret = $this->client['appsecret'];
        if(Config::get('api.sign')) {
            if(empty($params['nonce']) || empty($params['sign'])){
                $this->error(lang('sign or nonce cannot be empty'), [],401);
            }
            if($params !=$this->buildSign($params)){
                $this->error(lang('sign is not right'));
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
            ->limit(1)
            ->find();
        if ($member) {
            if (password_verify($password, $member['password'])) {
                unset($member['password']);
                return $member;
            } else {
                $this->error(lang('Password is not right'), [], 401);
            }
        } else {
            $this->error(lang('Account is not exist'), [], 401);
        }
    }


    /**
     * 生成签名
     * 字符开头的变量不参与签名
     */
    protected  function buildSign ($data = [])
    {
        unset($data['version']);
        unset($data['sign']);
        ksort($data);
        $data['key'] = $this->app_secret;
        return strtolower(md5(urldecode(http_build_query($data))));
    }
}
