<?php

declare(strict_types=1);

namespace app\api\controller\v2;

use OpenApi\Annotations as OA;

use app\common\annotation\NoNeedLogin;
use app\common\controller\ApiController;
use app\common\service\member\MemberApiSessionService;
use app\common\service\member\MemberAuthService;
use support\Request;
use support\Response;
use Webman\Limiter\Limiter;

/**
 * 会员 API 令牌：登录签发、一次性轮换刷新、登出吊销。
 *
 * 登录与刷新在未登录状态下调用，标注 #[NoNeedLogin]；登出默认需要登录。
 */
class Token extends ApiController
{
    private const IP_LIMIT = 10;
    private const ACCOUNT_LIMIT = 5;
    private const WINDOW = 60;

    public function __construct(
        private readonly MemberApiSessionService $sessions = new MemberApiSessionService(),
        private readonly MemberAuthService $memberAuthService = new MemberAuthService()
    ) {
    }

    /**
     * 使用会员账号和密码登录，开启新会话并签发 access token 与 refresh token。
     *
     * 请求字段：username、password。
     *
     * @param Request $request 当前 HTTP 请求。
     * @return Response JSON 格式的接口响应。
     *
     * @OA\Post(
     *     path="/api/v2/token",
     *     operationId="api_controller_v2_Token_build_post",
     *     tags={"会员 API 令牌"},
     *     summary="会员登录，签发 access token 与 refresh token。",
     *     description="每次登录开启一个独立会话，不影响该会员在其它设备上的登录。按 IP 每分钟限 10 次，并按账号每分钟限 5 次。",
     *     security={},
     *     @OA\RequestBody(required=true, @OA\JsonContent(ref="#/components/schemas/MemberApiLoginInput")),
     *     @OA\Response(response=200, description="登录成功。", @OA\JsonContent(ref="#/components/schemas/MemberApiTokenResult")),
     *     @OA\Response(response=400, description="参数缺失或长度不合法。", @OA\JsonContent(ref="#/components/schemas/MemberApiEnvelope")),
     *     @OA\Response(response=401, description="账号或密码错误，或会员已停用。", @OA\JsonContent(ref="#/components/schemas/MemberApiEnvelope")),
     *     @OA\Response(response=429, ref="#/components/responses/MemberApiTooManyRequests")
     * )
     */
    #[NoNeedLogin]
    public function build(Request $request): Response
    {
        if ($limited = $this->throttle('ip:' . $request->path() . ':' . hash('sha256', $request->getRemoteIp()), self::IP_LIMIT)) return $limited;
        $account = trim((string) $request->post('username', ''));
        $password = (string) $request->post('password', '');
        if ($account === '' || mb_strlen($account) > 100 || strlen($password) < 6 || strlen($password) > 255) {
            return $this->fail(msg: trans('Invalid parameters'), code: 400);
        }
        // 按账号限流：分散 IP 的撞库也会被同一账号的配额挡住。
        if ($limited = $this->throttle('account:' . hash('sha256', mb_strtolower($account)), self::ACCOUNT_LIMIT)) return $limited;

        $member = $this->memberAuthService->authenticate($account, $password);
        if (!$member) {
            return $this->fail(msg: trans('Account or password is incorrect'), code: 401);
        }

        return $this->ok(trans('Tokens generated successfully'), $this->sessions->open((int) $member['id'], (string) $request->getRemoteIp()));
    }

    /**
     * 使用 refresh token 一次性换取新的 access token 与 refresh token。
     *
     * 请求字段：refresh_token。
     *
     * @param Request $request 当前 HTTP 请求。
     * @return Response JSON 格式的接口响应。
     *
     * @OA\Post(
     *     path="/api/v2/token/refresh",
     *     operationId="api_controller_v2_Token_refresh_post",
     *     tags={"会员 API 令牌"},
     *     summary="一次性轮换 refresh token，换取新的令牌对。",
     *     description="refresh token 只能使用一次：成功后旧值立即失效，调用方必须保存响应中的新 refresh token。已用过的旧值再次提交会被视为泄露重放，返回 401 并吊销整个会话（含刚换出的新令牌）。会话已登出、已过期或会员已停用也返回 401。按 IP 每分钟限 10 次。",
     *     security={},
     *     @OA\RequestBody(required=true, @OA\JsonContent(ref="#/components/schemas/MemberApiRefreshInput")),
     *     @OA\Response(response=200, description="刷新成功，返回新的令牌对。", @OA\JsonContent(ref="#/components/schemas/MemberApiTokenResult")),
     *     @OA\Response(response=400, description="缺少 refresh_token。", @OA\JsonContent(ref="#/components/schemas/MemberApiEnvelope")),
     *     @OA\Response(response=401, description="refresh token 无效、已使用、已过期，或会话已吊销。", @OA\JsonContent(ref="#/components/schemas/MemberApiEnvelope")),
     *     @OA\Response(response=429, ref="#/components/responses/MemberApiTooManyRequests")
     * )
     */
    #[NoNeedLogin]
    public function refresh(Request $request): Response
    {
        if ($limited = $this->throttle('ip:' . $request->path() . ':' . hash('sha256', $request->getRemoteIp()), self::IP_LIMIT)) return $limited;
        $refreshToken = trim((string) $request->post('refresh_token', ''));
        if ($refreshToken === '') {
            return $this->fail(msg: trans('Invalid parameters'), code: 400);
        }

        $tokens = $this->sessions->rotate($refreshToken);
        if ($tokens === null) {
            return $this->fail(msg: trans('Invalid refresh token'), code: 401);
        }

        return $this->ok(trans('Access token refreshed successfully'), $tokens);
    }

    /**
     * 登出当前会话，吊销该会话的 access token 与 refresh token。
     *
     * @param Request $request 当前 HTTP 请求，已由 ApiMiddleware 完成会员鉴权。
     * @return Response JSON 格式的接口响应。
     *
     * @OA\Post(
     *     path="/api/v2/logout",
     *     operationId="api_controller_v2_Token_logout_post",
     *     tags={"会员 API 令牌"},
     *     summary="登出当前会话。",
     *     description="只吊销当前 access token 所属的会话，不影响该会员在其它设备上的登录。",
     *     security={{"MemberBearer"={}}},
     *     @OA\Response(response=200, description="登出成功。", @OA\JsonContent(ref="#/components/schemas/MemberApiLogoutResult")),
     *     @OA\Response(response=401, ref="#/components/responses/MemberApiError")
     * )
     */
    public function logout(Request $request): Response
    {
        $this->sessions->revoke((string) $request->member_session_id);
        return $this->ok(trans('Logged out successfully'));
    }

    private function throttle(string $key, int $limit): ?Response
    {
        if (Limiter::attempt('member_token:' . $key, $limit, self::WINDOW)) return null;
        return $this->fail(msg: trans('Too many requests, please try again later'), code: 429)
            ->withHeaders(['Retry-After' => (string) self::WINDOW, 'Cache-Control' => 'no-store']);
    }
}
