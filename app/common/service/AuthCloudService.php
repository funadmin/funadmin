<?php
/**
 * FunAdmin
 * ============================================================================
 * 版权所有 2017-2028 FunAdmin，并保留所有权利。
 * 网站地址: http://www.FunAdmin.com
 * ----------------------------------------------------------------------------
 * 采用最新Thinkphp6实现
 * ============================================================================
 * Author: yuege
 * Date: 2021/5/20
 * Time: 9:56
 */

namespace app\common\service;

use fun\helper\HttpHelper;
use fun\helper\StringHelper;
use think\App;
use think\facade\Cookie;

class AuthCloudService extends AbstractService
{
    // 请求的数据
    //服务器地址
    public $api_domain = 'https://www.funadmin.com/';
    public $appid = 'funadmin';
    public $appsecret = '6087ed6cd51e4860ff596cee0635d8d8';
    // 接口
    public $api_url = 'api/v1.token/accessToken';
    public $expire = 3600*24*7;
    // 请求类型
    public $method = 'post';
    public $token = '';
    public $params = [];
    public $userdata = [];
    public $cookie = [];
    public $options = [];
    public $header = [];

    public function __construct(App $app)
    {
        parent::__construct($app);
    }

    /**
     * $expire
     * @return mixed
     */
    public function setExpire($expire)
    {
        $this->expire = $expire?:$this->expire;
        return $this;

    }
    /**
     * token
     * @return mixed
     */
    public function setToken()
    {
        $this->token = StringHelper::randomNum();
        return $this;

    }
    /**
     * token
     * @return mixed
     */
    public function getToken()
    {
        return $this->token;
    }
    /**
     * 设置账号缓存
     * @return mixed
     */
    public function setAuth($memberInfo=[])
    {
        Cookie::set('auth_account',base64_encode(serialize($memberInfo)),$this->expire);
        return $this;
    }
    /**
     * 获取授权账号
     * @return mixed
     */
    public function getAuth()
    {
        return unserialize(base64_decode((Cookie::get('auth_account'))));
    }
    public function getApiUrl()
    {
        return $this->api_url;
    }

    public function setApiUrl($url = '')
    {
        if (!$url) {
            $this->api_url = $this->api_domain . $this->api_url;
        } else {
            $this->api_url = $this->api_domain . $url;
        }
        return $this;
    }

    public function setParams($params = [])
    {

        $this->params = $params;
        return $this;
    }

    public function setUserParams($data){
        $data['timestamp'] = $this->getTimestamp();
        $data['nonce'] = $this->getNonce();
        $data['appid'] = $this->appid;
        $data['appsecret'] = $this->appsecret;
        $data['key'] = $data['appsecret'];
        $data['sign'] = $this->getSign($data);
        $this->userdata= $data;
        return $this;
    }
    public function getUserParams(){
        return $this->userdata;
    }
    /**
     * 请求类型
     * @return string
     */
    public function getmethod()
    {
        return $this->api_url;
    }

    public function setMethod($method = 'post')
    {
        $this->method = $method;
        return $this;
    }

    public function setOptions($options = '')
    {
        $this->options = $options;
        return $this;
    }
    /**
     * 设置请求头
     * @param array $header
     * @return $this
     */
    public function setHeader($header=[])
    {
        $data = $this->getAuth();
        $str= $data['client']['id'] .' '.base64_encode($this->appid.':'.$data['access_token'].':'.$data['client']['id']);
        $header = array_merge([config('api.authentication').":".$str],$header);
        $this->header = $header;
        return $this;
    }

    /**
     * 获取头部信息
     * @return array
     */
    public function getHeader()
    {
        return $this->header ;
    }
    public function setCookie($cookie = [])
    {
        $this->cookie = $cookie;
        return $this;
    }

    public function run()
    {
        $ret = HttpHelper::sendRequest($this->api_url, $this->params, $this->method,$this->header, $this->options, $this->cookie);
        return json_decode($ret['msg'], true);
    }

    //随机数
    public function getNonce($len = 8)
    {
        return StringHelper::randomNum($len);
    }

    //时间错
    public function getTimestamp()
    {
        return time();
    }

    //签名
    public function getSign($params = [])
    {
        $params['appid'] = $this->appid;
        $params['appsecret'] = $this->appsecret;
        ksort($params);
        return strtolower(md5(urldecode(http_build_query($params))));
    }

    /**
     * 下载并保存
     * @param $file_url
     * @param $filepath
     * @return false|mixed
     */
    public function down($savePath,$saveName){
        $res = HttpHelper::download($this->api_url,$savePath,$saveName,$this->method,$this->params,$this->header);
        return $res;
    }

}