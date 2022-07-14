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

use app\common\model\Oauth2AccessToken;
use app\common\service\PredisService;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use fun\auth\Send;
use think\Exception;
use think\facade\Db;
use think\facade\Request;

/**
 * API鉴权验证
 */
class Oauth
{
    use Send;

    /**
     * 认证授权 通过用户信息和路由
     * @param Request $request
     * @return \Exception|UnauthorizedException|mixed|Exception
     * @throws UnauthorizedException
     */
    final function authenticate()
    {      
        return $this->certification($this->getClient());
    }


    /**
     * 获取用户信息后 验证权限
     * @return mixed
     */
    public  function certification($data = []){

        if(config('api.driver')=='redis'){
            $this->redis = PredisService::instance();
            $AccessToken = $this->redis->get(config('api.redisTokenKey'). $this->appid . $this->tableName.$data['access_token']);
            $AccessToken = unserialize($AccessToken);
        }else{
            $AccessToken = Db::name('oauth2_access_token')
                ->where('member_id',$data['member_id'])
                ->where('tablename',$this->tableName)
                ->where('group',$this->group)
                ->where('access_token',$data['access_token'])->order('id desc')->find();
        }
        if(!$AccessToken){
            $this->error('access_token不存在或过期','',401);
        }
        $client = Db::name('oauth2_client')->find($AccessToken['client_id']);
        if(!$client || $client['appid'] !== $data['appid']){
            $this->error('appid错误','',401);//appid与缓存中的appid不匹配
        }
        return $data;
    }


    /**
     * 获取用户信息
     * @param Request $request
     * @return $this
     * @throws UnauthorizedException
     */
    public  function getClient()
    {   
        //获取头部信息
        $authorization = config('api.authentication')?config('api.authentication'):'authentication';
        $authorizationHeader = Request::header($authorization);
        if(!$authorizationHeader){
            $this->error('Invalid authorization credentials','',401,'',$authorizationHeader?$authorizationHeader:[]);
        }
        try {
            //jwt
            if(config('api.is_jwt')) {
                $clientInfo = $this->jwtcheck($authorizationHeader);
                return $clientInfo;
            }
            $authorizationArr = explode(" ", $authorizationHeader);//explode分割，获取后面一窜base64加密数据
            $authorizationInfo  = explode(":", base64_decode($authorizationArr[1]));  //对base_64解密，获取到用:拼接的自字符串，然后分割，可获取appid、accesstoken、member_id这三个参数
            $clientInfo['appid'] = $authorizationInfo[0];
            $clientInfo['access_token'] = $authorizationInfo[1];
            $clientInfo['member_id'] = $authorizationInfo[2];
            return $clientInfo;
        } catch (\Exception $e) {
            $this->error($e->getMessage(),$authorizationHeader,401,'',[]);
        }
    }

    /**
     * 解密
     * @param $authorizationHeader
     * @return array
     * @throws \Exception
     */
    public function jwtcheck($jwt){
        try {

            JWT::$leeway = 60;//当前时间减去60，把时间留点余地
            $decoded = JWT::decode($jwt, new Key(md5(config('api.jwt_key')), 'HS256'));
            $jwtAuth = (array)$decoded;
            $clientInfo['access_token'] = $jwt;
            $clientInfo['member_id'] = $jwtAuth['member_id'];
            $clientInfo['appid'] = $jwtAuth['appid'];
        } catch(\Firebase\JWT\SignatureInvalidException $e) {  //签名不正确
            throw new \Exception ($e->getMessage());
        }catch(\Firebase\JWT\BeforeValidException $e) {  // 签名在某个时间点之后才能用
            throw new \Exception( $e->getMessage());
        }catch(\Firebase\JWT\ExpiredException $e) {  // token过期
            throw new \Exception( $e->getMessage());
        }catch(Exception $e) {  //其他错误
            throw new \Exception( $e->getMessage());
        }
        return $clientInfo;

    }




    /**
     * 检测当前控制器和方法是否匹配传递的数组
     *
     * @param array $arr 需要验证权限的数组
     * @return boolean
     */
    public  function match($arr = [])
    {
        $request = Request::instance();
        $arr = is_array($arr) ? $arr : explode(',', $arr);
        if (!$arr) {
            return false;
        }
        $arr = array_map('strtolower', $arr);
        // 是否存在
        if (in_array(strtolower($request->action()), $arr) || in_array('*', $arr))
        {
            return true;
        }

        // 没找到匹配
        return false;
    }

    /**
     * 生成签名
     * _字符开头的变量不参与签名
     */
    public  function makeSign ($data = [],$app_secret = '')
    {
        unset($data['version']);
        unset($data['sign']);
        return $this->buildSign($data,$app_secret);
    }

    /**
     * 计算ORDER的MD5签名
     */
    private  function buildSign($params = [] , $app_secret = '') {
        ksort($params);
        $params['key'] = $app_secret;
        return strtolower(md5(urldecode(http_build_query($params))));
    }

}
