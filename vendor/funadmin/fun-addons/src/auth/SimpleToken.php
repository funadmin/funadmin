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
use Firebase\JWT\Key;
use think\facade\Config;
use think\facade\Request;
use fun\auth\Send;
use think\facade\Db;
use think\Lang;
use Firebase\JWT\JWT;
/**
 * 生成token
 */
class SimpleToken
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
    public function __construct($options = [])
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
    }

    /**
     * 生成token
     */
    public function build()
    {
        //参数验证
        $post = Request::post() ;
        $validate = new \fun\auth\validate\Token;
        if (!$validate->scene('simple')->check(Request::post())) {
            $this->error(lang($validate->getError()), '', 500);
        }
        //时间戳校验
        if (abs($post['timestamp'] - time()) > $this->timeDif) {
            $this->error(lang('Request timestamp and server timestamp are abnormal'), [], 401);
        }
        //数据库已经有一个用户,这里需要根据input('mobile')去数据库查找有没有这个用户
        $memberInfo = $this->getMember(Request::post('username'), Request::post('password'));
        $accessToken =  $this->buildAccessToken($memberInfo,$this->expires);
        $this->success(lang('success'), ['access_token'=>$accessToken]);

    }

    /**
     * token 过期 刷新token
     */
    public function refresh()
    {
        $client = $this->checkToken($this->request->post('refresh_token'));
        $memberInfo =  Db::name($this->tableName)->field('id as member_id,password')->find($client['member_id']);
        if(!$memberInfo){
            $this->error(lang('member is not exist'));
        }
        $accessToken =  $this->buildAccessToken($memberInfo,$this->expires);
        $this->success(lang('success'), ['access_token'=>$accessToken]);
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


    protected function getMember($membername, $password)
    {
        $member = Db::name($this->tableName)
            ->where('status',1)
            ->where('username', $membername)
            ->whereOr('mobile', $membername)
            ->whereOr('email', $membername)
            ->field('id as member_id,password')
//            ->cache($this->appid.$membername,3600)
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
}
