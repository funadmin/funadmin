<?php
/**
 * FunAdmin
 * ============================================================================
 * 版权所有 2017-2028 FunAdmin，并保留所有权利。
 * 网站地址: http://www.FunAdmin.com
 * ----------------------------------------------------------------------------
 * 采用最新Thinkphp8实现
 * ============================================================================
 * Author: yuege
 * Date: 2021/5/20
 * Time: 9:56
 */

namespace app\common\service;

use fun\helper\HttpHelper;
use fun\helper\StringHelper;
use think\App;
use think\facade\Config;

class AuthCloudService extends AbstractService
{
    // 请求的数据
    //服务器地址
    public string $api_domain;
    // 接口
    public string $api_url ;
    // 请求类型
    public string $method = 'GET';
    public int $expire = 3600*24*7;
    public array $params = [];
    public array $options = [];
    public array $header = [];
    public $cloud_account_key = 'cloud_account';
    public $cloud_access_toke_key = 'cloud_access_token';

    public function __construct()
    {
        parent::__construct();
        $this->api_domain = config('funadmin.api_domain');
    }

    public function getAppVersion(){
        $config = config('funadmin');
        return $config?$config['version']:'';

    }

    /**
     * @param array $memberInfo
     * @return $this
     */
    public function setMember(array $memberInfo = []): static
    {
        if(empty($memberInfo)){
            cookie($this->cloud_account_key,null);
        }else{
            cookie($this->cloud_account_key,base64_encode(serialize($memberInfo)),$this->expire);
        }
        return $this;
    }

    /**
     * @return mixed|string
     */
    public function getMember()
    {
        $account = cookie($this->cloud_account_key);
        return $account?unserialize(base64_decode($account)):'';
    }
    /**
     * @param string $token
     * @return $this
     */
    public function setToken(string $token=''): static
    {
        if($token){
            cookie($this->cloud_access_toke_key,$token,$this->expire);
        }else{
            cookie($this->cloud_access_toke_key,null);
        }
        return $this;
    }

    public function getToken(array $memberInfo = []) : string
    {
        return cookie($this->cloud_access_toke_key)?:'';
    }


    /**
     * @param string $url
     * @return $this
     */
    public function setApiUrl(string $url): static
    {
        $this->api_url = rtrim($this->api_domain, '/') . '/' . ltrim($url, '/');
        return $this;
    }

    /**
     * @param array $params
     * @return $this
     */
    public function setParams(array $params = []): static
    {

        $this->params['app_version'] = $this->getAppVersion();
        $this->params = array_merge($this->params, $params);
        return $this;
    }

    /**
     * @param string $method
     * @return $this
     */
    public function setMethod(string $method = 'post'): static
    {
        $this->method = $method;
        return $this;
    }

    /**
     * @param array $options
     * @return $this
     */
    public function setOptions(array $options = []): static
    {
        $this->options = $options;
        return $this;
    }

    /**
     * @param array $header
     * @return $this
     */
    public function setHeader(array $header=[]): static
    {
        if(empty($header['Authorization']) && empty($header['access_token'])){
            if($this->getToken()){
                $header['Authorization'] = 'Bearer '.$this->getToken();
                $this->header = $header;
                return $this;
            }
        }
        if(!empty($header['access_token']) || !empty($header['Authorization'])){
            $token = $header['access_token']??$header['Authorization'];
            $header['Authorization'] = 'Bearer '.$token;
            unset($header['access_token']);
        }
        $this->header = $header;
        return $this;
    }

    /**
     * @return array
     */
    public function getHeader(): array
    {
        return $this->header ;
    }


    /**
     * Summary of run
     * @throws \Exception
     */
    public function run()
    {
        try {
            $ret = HttpHelper::sendRequest($this->api_url, $this->params, $this->method, $this->header, $this->options);
            if (empty($ret['data'])) {
                throw new \Exception('Invalid response format');
            }
            $data = json_decode($ret['data'], true);
            if (json_last_error()) {
                throw new \Exception($ret['data']);
            }
            return $data;
        } catch (\Exception $e) {
            throw new \Exception($e->getMessage());
        }
    }

    /**
     * @param string $savePath
     * @param string $saveName
     * @return mixed
     */
    public function down(string $savePath,string $saveName): mixed
    {
        $res = HttpHelper::download($this->api_url,$savePath. DIRECTORY_SEPARATOR . $saveName);
        return $res;
    }


    public function getAccessToken($data)
    {
        $result = $this->setApiUrl('/api/v2.token/build')
            ->setParams($data)
            ->setMethod('POST')
            ->run();
        if (!isset($result['code']) || $result['code'] !== 200) {
            $this->error($result['msg']);
        }

        return $result['data'];
    }

    /**
     * @param $accessToken
     * @return mixed
     * @throws Exception
     */
    public function getMemberInfo($accessToken)
    {
        $member =  $this->setApiUrl('/api/v2.member/get')
            ->setToken($accessToken)
            ->setHeader(['access_token' => $accessToken])
            ->run();

        if (!isset($member['code']) || $member['code'] !== 200) {
            throw new Exception($member['msg']);
        }
        return $member['data'];
    }
}