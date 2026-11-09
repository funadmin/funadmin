<?php

declare(strict_types=1);

namespace app\api\controller\v2;

use OpenApi\Annotations as OA;

use app\common\annotation\NoNeedLogin;
use app\common\controller\ApiController;
use app\common\service\member\MemberAccountService;
use app\common\service\member\MemberApiSessionService;
use support\Request;
use support\Response;

class Member extends ApiController
{
    public function __construct(
        private readonly MemberAccountService $accounts = new MemberAccountService(),
        private readonly MemberApiSessionService $sessions = new MemberApiSessionService()
    ) {
    }

    /**
     * 返回当前登录会员的公开资料。
     *
     * 只输出白名单字段，避免会员数据来源扩展时泄露敏感列。
     *
     * @param Request $request 当前 HTTP 请求，已由 ApiMiddleware 完成会员鉴权（默认需要登录）。
     * @return Response JSON 格式的接口响应。
     *
     * @OA\Get(
     *     path="/api/v2/member",
     *     operationId="api_controller_v2_Member_show_get",
     *     tags={"会员 API 资料"},
     *     summary="获取当前会员资料。",
     *     description="只返回 id、username、nickname 三个字段。",
     *     security={{"MemberBearer"={}}},
     *     @OA\Response(response=200, description="当前会员资料。", @OA\JsonContent(ref="#/components/schemas/MemberApiProfileResult")),
     *     @OA\Response(response=401, ref="#/components/responses/MemberApiError")
     * )
     */
    public function show(Request $request): Response
    {
        $member = (array) $request->member;
        return $this->ok(data: ['user' => [
            'id' => (int) $member['id'],
            'username' => (string) ($member['username'] ?? ''),
            'nickname' => (string) ($member['nickname'] ?? ''),
        ]]);
    }

    /**
     * 示例：无需登录的接口，返回当前访问者的登录状态。
     *
     * 标注 #[NoNeedLogin] 后游客也能访问；若请求带了有效令牌，ApiMiddleware 仍会注入
     * $request->member，因此可以按是否登录返回不同内容。无效令牌按游客处理。
     *
     * @param Request $request 当前 HTTP 请求。
     * @return Response JSON 格式的接口响应。
     *
     * @OA\Get(
     *     path="/api/v2/member/status",
     *     operationId="api_controller_v2_Member_status_get",
     *     tags={"会员 API 资料"},
     *     summary="获取当前访问者的登录状态（无需登录）。",
     *     description="游客返回 loggedIn=false；携带有效令牌时返回会员 id 与昵称。无效令牌按游客处理，不返回 401。",
     *     security={{}, {"MemberBearer"={}}},
     *     @OA\Response(
     *         response=200,
     *         description="登录状态。",
     *         @OA\JsonContent(allOf={
     *             @OA\Schema(ref="#/components/schemas/MemberApiEnvelope"),
     *             @OA\Schema(type="object", @OA\Property(
     *                 property="data", type="object", required={"loggedIn", "user"},
     *                 @OA\Property(property="loggedIn", type="boolean", example=false),
     *                 @OA\Property(property="user", type="object", nullable=true, required={"id", "nickname"},
     *                     @OA\Property(property="id", type="integer", example=1),
     *                     @OA\Property(property="nickname", type="string", example="会员昵称")
     *                 )
     *             ))
     *         })
     *     )
     * )
     */
    #[NoNeedLogin]
    public function status(Request $request): Response
    {
        $member = $request->member ?? null;
        if ($member === null) {
            return $this->ok(data: ['loggedIn' => false, 'user' => null]);
        }
        return $this->ok(data: ['loggedIn' => true, 'user' => [
            'id' => (int) $member['id'],
            'nickname' => (string) ($member['nickname'] ?? ''),
        ]]);
    }
    /**
     * 注册会员并直接开启一个 API 会话。
     *
     * 请求字段：username、email、nickname（可选）、password、password_confirm。
     *
     * @param Request $request 当前 HTTP 请求。
     * @return Response JSON 格式的接口响应。
     *
     * @OA\Post(
     *     path="/api/v2/member",
     *     operationId="api_controller_v2_Member_register_post",
     *     tags={"会员 API 资料"},
     *     summary="注册会员，签发 access token 与 refresh token。",
     *     description="校验用户名、邮箱、密码强度与唯一性；同一 IP 每小时最多注册 5 次。成功后返回令牌对与会员资料。",
     *     security={},
     *     @OA\RequestBody(required=true, @OA\JsonContent(ref="#/components/schemas/MemberApiRegisterInput")),
     *     @OA\Response(response=200, description="注册成功。", @OA\JsonContent(ref="#/components/schemas/MemberApiRegisterResult")),
     *     @OA\Response(response=422, description="注册信息不合法、已被占用或注册过于频繁。", @OA\JsonContent(ref="#/components/schemas/MemberApiEnvelope"))
     * )
     */
    #[NoNeedLogin]
    public function register(Request $request): Response
    {
        $payload = [
            'username' => trim((string) $request->post('username', '')),
            'email' => trim((string) $request->post('email', '')),
            'nickname' => trim((string) $request->post('nickname', '')),
            'password' => (string) $request->post('password', ''),
            'password_confirm' => (string) $request->post('password_confirm', ''),
        ];
        if ($payload['username'] === '' || $payload['email'] === '' || $payload['password'] === '') {
            return $this->fail(msg: trans('Complete all registration fields'), code: 422);
        }
        try {
            $memberId = $this->accounts->register($payload, (string) $request->getRemoteIp());
        } catch (\RuntimeException $exception) {
            return $this->fail(msg: $exception->getMessage(), code: 422);
        }
        return $this->ok(trans('Registered successfully'), $this->sessions->open($memberId, (string) $request->getRemoteIp()) + ['user' => $this->account($memberId)]);
    }

    /**
     * 返回当前会员的完整个人资料（会员中心使用）。
     *
     * @param Request $request 当前 HTTP 请求，已由 ApiMiddleware 完成会员鉴权。
     * @return Response JSON 格式的接口响应。
     *
     * @OA\Get(
     *     path="/api/v2/member/profile",
     *     operationId="api_controller_v2_Member_profile_get",
     *     tags={"会员 API 资料"},
     *     summary="获取当前会员的个人资料。",
     *     description="只返回本人可见的白名单字段，不含密码等敏感列。",
     *     security={{"MemberBearer"={}}},
     *     @OA\Response(response=200, description="个人资料。", @OA\JsonContent(ref="#/components/schemas/MemberApiAccountResult")),
     *     @OA\Response(response=401, ref="#/components/responses/MemberApiError")
     * )
     */
    public function profile(Request $request): Response
    {
        $account = $this->account((int) $request->member_id);
        return $account === null ? $this->fail(msg: trans('Unauthorized'), code: 401) : $this->ok(data: ['user' => $account]);
    }

    /**
     * 更新当前会员的昵称、简介与头像。
     *
     * 请求字段：nickname、motto、avatar（https 地址或空）。
     *
     * @param Request $request 当前 HTTP 请求，已由 ApiMiddleware 完成会员鉴权。
     * @return Response JSON 格式的接口响应。
     *
     * @OA\Put(
     *     path="/api/v2/member/profile",
     *     operationId="api_controller_v2_Member_updateProfile_put",
     *     tags={"会员 API 资料"},
     *     summary="更新当前会员资料。",
     *     security={{"MemberBearer"={}}},
     *     @OA\RequestBody(required=true, @OA\JsonContent(ref="#/components/schemas/MemberApiProfileInput")),
     *     @OA\Response(response=200, description="更新后的个人资料。", @OA\JsonContent(ref="#/components/schemas/MemberApiAccountResult")),
     *     @OA\Response(response=401, ref="#/components/responses/MemberApiError"),
     *     @OA\Response(response=422, description="资料不合法。", @OA\JsonContent(ref="#/components/schemas/MemberApiEnvelope"))
     * )
     */
    public function updateProfile(Request $request): Response
    {
        $memberId = (int) $request->member_id;
        try {
            $this->accounts->updateProfile($memberId, [
                'nickname' => trim((string) $request->post('nickname', '')),
                'motto' => trim((string) $request->post('motto', '')),
                'avatar' => trim((string) $request->post('avatar', '')),
            ]);
        } catch (\RuntimeException $exception) {
            return $this->fail(msg: $exception->getMessage(), code: 422);
        }
        return $this->ok(trans('Profile updated'), ['user' => $this->account($memberId)]);
    }

    /**
     * 修改当前会员密码，并吊销该会员的全部会话。
     *
     * 请求字段：current、password、password_confirm。
     *
     * @param Request $request 当前 HTTP 请求，已由 ApiMiddleware 完成会员鉴权。
     * @return Response JSON 格式的接口响应。
     *
     * @OA\Put(
     *     path="/api/v2/member/password",
     *     operationId="api_controller_v2_Member_password_put",
     *     tags={"会员 API 资料"},
     *     summary="修改当前会员密码。",
     *     description="成功后该会员在所有设备上的会话（含当前会话）全部吊销，需要重新登录。",
     *     security={{"MemberBearer"={}}},
     *     @OA\RequestBody(required=true, @OA\JsonContent(ref="#/components/schemas/MemberApiPasswordInput")),
     *     @OA\Response(response=200, description="密码已修改。", @OA\JsonContent(ref="#/components/schemas/MemberApiLogoutResult")),
     *     @OA\Response(response=401, ref="#/components/responses/MemberApiError"),
     *     @OA\Response(response=422, description="当前密码错误或新密码不合法。", @OA\JsonContent(ref="#/components/schemas/MemberApiEnvelope"))
     * )
     */
    public function password(Request $request): Response
    {
        try {
            $this->accounts->changePassword(
                (int) $request->member_id,
                (string) $request->post('current', ''),
                (string) $request->post('password', ''),
                (string) $request->post('password_confirm', '')
            );
        } catch (\RuntimeException $exception) {
            return $this->fail(msg: $exception->getMessage(), code: 422);
        }
        return $this->ok(trans('Password changed, please sign in again'));
    }

    /** @return array{id:int, username:string, nickname:string, email:string, mobile:string, avatar:string, motto:string, created_at:mixed, last_login:mixed}|null */
    private function account(int $memberId): ?array
    {
        $member = $this->accounts->profile($memberId);
        if ($member === null) {
            return null;
        }
        return [
            'id' => (int) $member['id'],
            'username' => (string) $member['username'],
            'nickname' => (string) (($member['nickname'] ?? '') ?: $member['username']),
            'email' => (string) ($member['email'] ?? ''),
            'mobile' => (string) ($member['mobile'] ?? ''),
            'avatar' => (string) ($member['avatar'] ?? ''),
            'motto' => (string) ($member['motto'] ?? ''),
            'created_at' => $member['created_at'] ?? null,
            'last_login' => $member['last_login'] ?? null,
        ];
    }
}
