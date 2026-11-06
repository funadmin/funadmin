<?php

declare(strict_types=1);

namespace app\api\controller\v2;

use OpenApi\Annotations as OA;

use app\common\annotation\NoNeedLogin;
use app\common\controller\ApiController;
use support\Request;
use support\Response;

class Member extends ApiController
{
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
}
