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
    public function build(Request $request): void
    {
        $account = trim((string) $request->post('username', ''));
        $password = (string) $request->post('password', '');
        if ($account === '' || mb_strlen($account) > 100 || strlen($password) < 6 || strlen($password) > 255) {
            $this->error(__('Invalid parameters'), [], 400);
        }

        $member = $this->memberAuthService->authenticate($account, $password);
        if (!$member) {
            $this->error(__('Account or password is incorrect'), [], 401);
        }

        $this->success(__('Tokens generated successfully'), [
            'access_token' => $this->tokenService->build(['id' => $member['id']]),
            'refresh_token' => $this->tokenService->build(['id' => $member['id']], TokenService::TYPE_REFRESH),
            'expires_in' => (int) config('api.access_token_ttl'),
        ]);
    }



    /**
     * @param Request $request
     * @return \think\response\Json
     */
    public function refresh(Request $request): void
    {
        $refreshToken = trim((string) $request->post('refresh_token', $request->post('access_token', '')));
        if ($refreshToken === '') {
            $authHeader = (string) $request->header('Authorization', '');
            if (!preg_match('/^Bearer\s+(\S+)$/i', $authHeader, $matches)) {
                $this->error(__('Unauthorized'), [], 401);
            }
            $refreshToken = $matches[1];
        }

        $tokenData = $this->tokenService->validateToken($refreshToken, TokenService::TYPE_REFRESH);
        $memberId = is_array($tokenData) ? (int) ($tokenData['id'] ?? 0) : 0;
        $member = $memberId > 0 ? $this->memberAuthService->activeMember($memberId) : null;
        if (!$member) {
            $this->error(__('Invalid refresh token'), [], 401);
        }

        $this->success(__('Access token refreshed successfully'), [
            'access_token' => $this->tokenService->build(['id' => $member['id']]),
            'expires_in' => (int) config('api.access_token_ttl'),
        ]);
    }

}
