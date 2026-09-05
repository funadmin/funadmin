<?php

declare(strict_types=1);

namespace app\api\controller\v2;

use app\common\controller\Api;
use app\common\service\MemberAuthService;
use app\common\service\TokenService;
use think\annotation\route\Group;
use think\annotation\route\Middleware;
use think\annotation\route\Post;
use think\middleware\Throttle;
use think\Request;
use think\Response;

/**
 * 生成token
 */
#[Group('v2/token')]
class Token extends Api
{
    public function __construct(
        private readonly TokenService $tokenService,
        private readonly MemberAuthService $memberAuthService
    ) {
    }

    /**
     * 获取token
     * @param Request $request
     * @return void
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\DbException
     * @throws \think\db\exception\ModelNotFoundException
     */
    #[Post('')]
    #[Middleware(Throttle::class, [
        'visit_method' => ['POST'],
        'visit_rate' => '10/m',
        'key' => '__CONTROLLER__/__ACTION__/__IP__',
    ])]
    public function build(Request $request): Response
    {
        $account = trim((string) $request->post('username', ''));
        $password = (string) $request->post('password', '');
        if ($account === '' || mb_strlen($account) > 100 || strlen($password) < 6 || strlen($password) > 255) {
            return $this->fail(msg: __('Invalid parameters'), code: 400);
        }

        $member = $this->memberAuthService->authenticate($account, $password);
        if (!$member) {
            return $this->fail(msg: __('Account or password is incorrect'), code: 401);
        }

        return $this->ok(__('Tokens generated successfully'), [
            'access_token' => $this->tokenService->build(['id' => $member['id']]),
            'refresh_token' => $this->tokenService->build(['id' => $member['id']], TokenService::TYPE_REFRESH),
            'expires_in' => (int) config('api.access_token_ttl'),
        ]);
    }



    /**
     * @param Request $request
     * @return \think\response\Json
     */
    #[Post('refresh')]
    public function refresh(Request $request): Response
    {
        $refreshToken = trim((string) $request->post('refresh_token', ''));
        if ($refreshToken === '') {
            return $this->fail(msg: __('Invalid parameters'), code: 400);
        }

        $tokenData = $this->tokenService->validateToken($refreshToken, TokenService::TYPE_REFRESH);
        $memberId = is_array($tokenData) ? (int) ($tokenData['id'] ?? 0) : 0;
        $member = $memberId > 0 ? $this->memberAuthService->activeMember($memberId) : null;
        if (!$member) {
            return $this->fail(msg: __('Invalid refresh token'), code: 401);
        }

        return $this->ok(__('Access token refreshed successfully'), [
            'access_token' => $this->tokenService->build(['id' => $member['id']]),
            'expires_in' => (int) config('api.access_token_ttl'),
        ]);
    }

}
