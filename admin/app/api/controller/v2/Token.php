<?php

declare(strict_types=1);

namespace app\api\controller\v2;

use app\common\controller\Api;
use app\common\service\member\MemberAuthService;
use app\common\service\authentication\TokenService;
use support\Request;
use support\Response;
use Webman\Limiter\Limiter;

/**
 * 生成token
 */
class Token extends Api
{
    public function __construct(
        private readonly TokenService $tokenService = new TokenService(),
        private readonly MemberAuthService $memberAuthService = new MemberAuthService()
    ) {
    }

    /** 获取访问令牌。 */
    public function build(Request $request): Response
    {
        if ($limited = $this->throttle($request)) return $limited;
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



    /** 使用 refresh token 获取新的访问令牌。 */
    public function refresh(Request $request): Response
    {
        if ($limited = $this->throttle($request)) return $limited;
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
    private function throttle(Request $request): ?Response
    {
        $key = 'member_token:' . $request->path() . ':' . hash('sha256', $request->getRemoteIp());
        if (Limiter::attempt($key, 10, 60)) return null;
        return $this->fail(msg: '请求过于频繁，请稍后重试', code: 429)
            ->withHeaders(['Retry-After' => '60', 'Cache-Control' => 'no-store']);
    }

}
