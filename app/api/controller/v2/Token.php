<?php

declare(strict_types=1);

namespace app\api\controller\v2;

use app\common\controller\Api;
use app\common\service\MemberAuthService;
use app\common\service\TokenService;
use think\App;
use think\Request;

/**
 * 生成token
 */
class Token extends Api
{

    protected array $noNeedLogin = ['build', 'refresh'];

    private TokenService $tokenService;
    private MemberAuthService $memberAuthService;

    public function __construct(App $app)
    {
        parent::__construct($app);
        $this->tokenService = TokenService::instance();
        $this->memberAuthService = MemberAuthService::instance();
    }

    /**
     * 获取token
     * @param Request $request
     * @return void
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\DbException
     * @throws \think\db\exception\ModelNotFoundException
     */
    public function build(Request $request): \think\Response
    {
        $account = trim((string) $request->post('username', ''));
        $password = (string) $request->post('password', '');
        if ($account === '' || mb_strlen($account) > 100 || strlen($password) < 6 || strlen($password) > 255) {
            return $this->fail(__('Invalid parameters'), 400);
        }

        $member = $this->memberAuthService->authenticate($account, $password);
        if (!$member) {
            return $this->fail(__('Account or password is incorrect'), 401);
        }

        return $this->ok([
            'access_token' => $this->tokenService->build(['id' => $member['id']]),
            'refresh_token' => $this->tokenService->build(['id' => $member['id']], TokenService::TYPE_REFRESH),
            'expires_in' => (int) config('api.access_token_ttl'),
        ], __('Tokens generated successfully'));
    }



    /**
     * @param Request $request
     * @return \think\response\Json
     */
    public function refresh(Request $request): \think\Response
    {
        $refreshToken = trim((string) $request->post('refresh_token', $request->post('access_token', '')));
        if ($refreshToken === '') {
            $authHeader = (string) $request->header('Authorization', '');
            if (!preg_match('/^Bearer\s+(\S+)$/i', $authHeader, $matches)) {
                return $this->fail(__('Unauthorized'), 401);
            }
            $refreshToken = $matches[1];
        }

        $tokenData = $this->tokenService->validateToken($refreshToken, TokenService::TYPE_REFRESH);
        $memberId = is_array($tokenData) ? (int) ($tokenData['id'] ?? 0) : 0;
        $member = $memberId > 0 ? $this->memberAuthService->activeMember($memberId) : null;
        if (!$member) {
            return $this->fail(__('Invalid refresh token'), 401);
        }

        return $this->ok([
            'access_token' => $this->tokenService->build(['id' => $member['id']]),
            'expires_in' => (int) config('api.access_token_ttl'),
        ], __('Access token refreshed successfully'));
    }

}
