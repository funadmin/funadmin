<?php

namespace app\api\controller\v2;

use app\common\controller\Api;
use app\common\service\TokenService;
use think\App;
use think\facade\Db;
use think\Request;
use think\facade\Config;

/**
 * 生成token
 */
class Token extends Api
{

    protected TokenService $tokenService;
    protected array $noNeedLogin = ['build','refresh'];

    public function __construct(App $app)
    {
        parent::__construct($app);
        $this->tokenService = TokenService::instance();
        //跨域
        header('Access-Control-Allow-Origin:*');
        header('Access-Control-Allow-Headers:Accept,Referer,Host,Keep-Alive,User-Agent,X-Requested-With,Cache-Control,Content-Type,Cookie,token');
        header('Access-Control-Allow-Credentials:true');
        header('Access-Control-Allow-Methods:GET, POST, PATCH, PUT, DELETE,OPTIONS');
        if(!$app->request->isPost()){
            $this->error(__('Page not find'),[],404);
        }
    }

    /**
     * 获取token
     * @param Request $request
     * @return void
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\DbException
     * @throws \think\db\exception\ModelNotFoundException
     */
    public function build(Request $request)
    {
        $username = $request->post('username');
        $password = $request->post('password');
        // 这里应该有用户验证逻辑，例如查询数据库验证用户名和密码
        // 为了示例，假设验证通过
        $member = \app\common\model\Member::where('status', 1)
            ->where('username', $username)
            ->whereOr('mobile', $username)
            ->whereOr('email', $username)
            ->field('id ,nickname,username,password')
            ->limit(1)
            ->find();
        if ($member) {
            $member = $member->toArray();
            if (password_verify($password, $member['password'])) {
                unset($member['password']);
                $accessToken = $this->tokenService->build($member);
                $refreshToken = $this->tokenService->build($member, 'refresh');
                $this->success(__('Tokens generated successfully'), [
                    'access_token' => $accessToken,
                    'refresh_token' => $refreshToken,
                    'expires_in' => Config::get('api.access_token_ttl'),
                ]);
            } else {
                $this->error(lang('Password is not right'), [], 401);
            }
        } else {
            $this->error(lang('Account is not exist'), [], 401);
        }
    }



    /**
     * @param Request $request
     * @return \think\response\Json
     */
    public function refresh(Request $request){
        // 获取 Authorization 头
        if(input('access_token')){
            $refreshToken = input('access_token');
        }else{
            $authHeader = $request->header('Authorization');
            if (!$authHeader || !preg_match('/Bearer\s(\S+)/', $authHeader, $matches)) {
                $this->error(__('Unauthorized'), [], 401);
            }
            $refreshToken = $matches[1];
        }
        // 验证 refresh_token
        $userData = $this->tokenService->validateToken($refreshToken, 'refresh');
        if (!$userData) {
            $this->error(__('Invalid refresh token'), [], 401);
        }
        // 生成新的 access_token
        $newAccessToken = $this->tokenService->build($userData, Config::get('api.access_token_ttl', 3600*2));
        $this->success(__('Access token refreshed successfully'), [
            'access_token' => $newAccessToken,
        ]);
    }

}
