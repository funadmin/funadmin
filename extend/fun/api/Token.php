<?php
/**
 * FunAdmin
 * ============================================================================
 * 版权所有 2017-2028 FunAdmin，并保留所有权利。
 * 网站地址: https://www.FunAdmin.cn
 * ----------------------------------------------------------------------------
 * 采用最新Thinkphp6实现
 * ============================================================================
 * Author: yuege
 * Date: 2019/10/3
 */
namespace funapi;

use think\facade\Request;
use funapi\Send;
use funapi\Oauth;
use think\facade\Cache;
use funapi\util\wxBizDataCrypt;
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
     * 请求时间差
     */
    public static $timeDif = 10000;

    public static $accessTokenPrefix = 'accessToken_';
    public static $refreshAccessTokenPrefix = 'refreshAccessToken_';
    public static $expires = 7200;
    public static $refreshExpires = 3600*24*30;   //刷新token过期时间
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

        $this->request = Request::instance();
        $appid = Request::post('appid');
        $appsecret = Request::post('appsecret');
        $oauth2_client = Db::name('oauth2_client')->where('appid', $appid)->find();
        if (!$oauth2_client) {
            return self::returnMsg(401, 'Invalid authorization credentials');

        }
        if($oauth2_client['appsecret']!=$appsecret){
            return self::returnMsg('401',lang('appsecret is not right'));
        }
        self::$appid = $oauth2_client['appid'];
        self::$appsecret = $oauth2_client['appsecret'];
    }

    /**
     * 生成token $accessToken
     */
    public function accessToken(Request $request)
    {
        //参数验证
        $validate = new \funapi\validate\Token;
        if (!$validate->check(Request::post())) {
            return self::returnMsg(401, $validate->getError());
        }
        self::checkParams(Request::post());  //参数校验
        //数据库已经有一个用户,这里需要根据input('mobile')去数据库查找有没有这个用户
        $userInfo = self::getUser(Request::post('username'),Request::post('password'));
        //虚拟一个uid返回给调用方
        try {
            $accessToken = self::setAccessToken(array_merge($userInfo, Request::post()));  //传入参数应该是根据手机号查询改用户的数据
            return self::returnMsg(200, 'success', $accessToken);
        } catch (\Exception $e) {
            return self::returnMsg(500, 'fail', $e);
        }
    }

    /** 小程序
     * @param string $code
     * @param string $encryptedData
     * @param string $iv
     * @param array $appInfo
     */
	public function getOpenId($code = '',$encryptedData = '',$iv = '',$appInfo = [])
	{
        $result = json_decode(file_get_contents("https://api.weixin.qq.com/sns/jscode2session?appid=" . $appInfo['wx_appid'] . "&secret=" . $appInfo['wx_appsecret'] . "&js_code=" . $code . "&grant_type=authorization_code"), true);
		if(empty($result['session_key'])){
			return $this->returnmsg(401,'获取token失败!'.$result['errmsg']);
		}else{
            $pc = new wxBizDataCrypt($appInfo['wx_appid'], $result['session_key']);
            $data = $pc->decryptData($encryptedData, $iv); //解密用户基础信息
            $data = json_decode($data, true);
            if (!empty($data['openId'])) {
                if (isset($data['unionId'])) { //含有unionid
                    $is_unionid['is_unionid'] = true;
                    $userInfo = WxFans::get(['unionid' => $data['unionId']]); //按照unionid查找
                    if(empty($userInfo)){
                        $userInfo = WxFans::get(['openid' => $data['openId']]); //按照openid查找
                    }
                } else {
                    $is_unionid['is_unionid'] = false;
                    $userInfo = WxFans::get(['openid' => $data['openId']]); //按照openid查找
                }
                $userAdd['openid']     = $data['openId'];
                $userAdd['unionid']    = isset($data['unionId']) ? $data['unionId'] : '';
                $userAdd['nickname']   = $data['nickName'];
                $userAdd['headimgurl']     = $data['avatarUrl'];
                $userAdd['sex']        = $data['gender'];
                $userAdd['province']   = $data['province'];
                $userAdd['country']    = $data['country'];
				if(empty($userInfo)){   //用户没有在fans表里面
                    $userAdd['subscribe_scene'] = 'WEIXIN';
                    $userAdd['source'] = 2;
                    WxFans::create($userAdd);  //插入到粉丝表
				}else{
                    $userAdd['update_time'] = time();
                    WxFans::where('fans_id',$userInfo['fans_id'])->update($userAdd);
                    $userAdd['uid'] = $userInfo['id'];
				}
				return $userAdd;
			}else{
				return $this->returnmsg(401,'获取token失败!解析数据失败');
			}
		}

	}

    /**
     * token 过期 刷新token
     */
    public function refresh($refresh_token = '', $appid = '')
    {
        $cache_refresh_token = Cache::get(self::$refreshAccessTokenPrefix . $appid);  //查看刷新token是否存在
        if (!$cache_refresh_token) {
            return self::returnMsg(401, 'fail', 'refresh_token is null');
        } else {
            if ($cache_refresh_token !== $refresh_token) {
                return self::returnMsg(401, 'fail', 'refresh_token is error');
            } else {    //重新给用户生成调用token
                $data['appid'] = $appid;
                $accessToken = self::setAccessToken($data);
                return self::returnMsg(200, 'success', $accessToken);
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

            return self::returnMsg(401, '请求时间戳与服务器时间戳异常', 'timestamp：' . time());
        }

        //appid检测，这里是在本地进行测试，正式的应该是查找数据库或者redis进行验证
        if ($params['appid'] !== self::$appid) {
            return self::returnMsg(401, 'appid 错误');
        }

        //签名检测
        $sign = Oauth::makeSign($params, self::$appsecret);
        if ($sign !== $params['sign']) {
            return self::returnMsg(401, 'sign错误', 'sign：' . $sign);
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
     * 刷新用的token检测是否还有效
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
        //存储accessToken
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

    protected static function getUser($username,$password)
    {
        $user = Db::name('user')->where('username', $username)
            ->whereOr('mobile',$username)
            ->whereOr('email',$username)->find();
        if ($user) {
            if(password_verify($password,$user['password'])){
                $user['uid'] = $user['id'];
                return $user;
            }else{
                return self::returnMsg(401, lang('密码错误'));

            }

        } else {
            return self::returnMsg(401, lang('Account is not exist'));
        }
    }
}