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

use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use think\exception\HttpResponseException;
use think\facade\Db;
use think\facade\Request;
use think\Response;

trait Send
{
    /**
     * 时间差
     * @var int
     */
    public  $timeDif = 10000;
    /**
     * 刷新有效期
     * @var float|int
     */
    public  $refreshExpires = 3600 * 24 * 30;   //刷新token过期时间
    /**
     * 有效期
     * @var float|int
     */
    public  $expires = 7200*12;
    /**
     * 多个用户表
     * @var string
     */
    public  $tableName = 'member';

    /**
     * redis 对象
     * @var 
     */
    public  $redis ;
    /**
     * 客户端对象
     * @var 
     */
    public  $client ;

    /**
     * 商戶ID
     * @var int
     */
    protected $merchant_id = 0;

    /**
     * 测试appid，正式请数据库进行相关验证
     */
    protected $appid = '';
    /**
     * appsecret
     */
    protected $appsecret = '';
    /**
     * JWT key
     * @var string 
     */
    public $key = '';
    
    public $group = 'api';

    /**
     * 返沪格式
     * @var string
     */
    public  $responseType = 'json';
    /**
     * 操作成功返回的数据
     * @param string $msg 提示信息
     * @param mixed $data 要返回的数据
     * @param int $code 错误码，默认为1
     * @param string $type 输出类型
     * @param array $header 发送的 Header 信息
     */
    public  function success($msg = '', $data = null, $code = 200, $type = null, array $header = [])
    {
        $this->result($msg, $data, $code, $type, $header);
    }

    /**
     * 操作失败返回的数据
     * @param string $msg 提示信息
     * @param mixed $data 要返回的数据
     * @param int $code 错误码，默认为0
     * @param string $type 输出类型
     * @param array $header 发送的 Header 信息
     */
    public function error($msg = '', $data = null, $code = 404, $type = null, array $header = [])
    {
        $this->result($msg, $data, $code, $type, $header);
    }

    protected  function result($msg, $data = null, $code = 404, $type = null, array $header = [])
    {
        $result = [
            'code' => $code,
            'msg' => $msg,
            'time' => \think\facade\Request::instance()->server('REQUEST_TIME'),
            'data' => $data,
        ];
        // 如果未设置类型则自动判断
        $type = $type ? $type : (\think\facade\Request::param(config('var_jsonp_handler')) ? 'jsonp' : $this->responseType);
        // 发送头部信息
        foreach ($header as $name => $val) {
            if (is_null($val)) {
                header($name);
            } else {
                header($name . ':' . $val);
            }
        }
        $response = Response::create($result, $type)->header($header);
        throw new HttpResponseException($response);
    }

    protected function getClientData($where=[],$field='*'){

        return Db::name('oauth2_client')->where($where)->field($field)->find();
    }

    protected function checkToken($jwt)
    {
        try {

            JWT::$leeway = 60;//当前时间减去60，把时间留点余地
            $decoded = JWT::decode($jwt, new Key(md5(config('api.jwt_key')), 'HS256'));
            $jwtAuth = (array)$decoded;
            $clientInfo['access_token'] = $jwt;
            $clientInfo['member_id'] = $jwtAuth['member_id'];
            $clientInfo['appid'] = $jwtAuth['appid'];
        } catch (\Firebase\JWT\SignatureInvalidException $e) {  //签名不正确
            throw new \Exception ($e->getMessage());
        } catch (\Firebase\JWT\BeforeValidException $e) {  // 签名在某个时间点之后才能用
            throw new \Exception($e->getMessage());
        } catch (\Firebase\JWT\ExpiredException $e) {  // token过期
            throw new \Exception($e->getMessage());
        } catch (Exception $e) {  //其他错误
            throw new \Exception($e->getMessage());
        }
        return $clientInfo;

    }

    /**
     * 获取客户端
     * @return array|false
     */
    protected function getClient()
    {
        //获取头部信息
        $authorization = config('api.authentication') ? config('api.authentication') : 'Authorization';
        $authorizationHeader = Request::header($authorization);
        if (!$authorizationHeader) {
            throw new \Exception(lang('Invalid authorization token'));
        }
        try {
            //jwt
            $clientInfo = $this->checkToken($authorizationHeader);
        } catch (\Exception $e) {
            throw new \Exception($e->getMessage());
        }
        return $clientInfo;
    }
    /**
     * 检测当前控制器和方法是否匹配传递的数组
     *
     * @param array $arr 需要验证权限的数组
     * @return boolean
     */
    public function match($arr = [])
    {
        $request = Request::instance();
        $arr = is_array($arr) ? $arr : explode(',', $arr);
        if (!$arr) {
            return false;
        }
        $arr = array_map('strtolower', $arr);
        // 是否存在
        if (in_array(strtolower($request->action()), $arr) || in_array('*', $arr)) {
            return true;
        }
        // 没找到匹配
        return false;
    }


}

