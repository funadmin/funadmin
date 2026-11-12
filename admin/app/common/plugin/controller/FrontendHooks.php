<?php

declare(strict_types=1);

namespace app\common\plugin\controller;

use OpenApi\Annotations as OA;

use app\admin\authentication\service\BrowserAuthService;
use app\admin\authorization\service\AuthorizationService;
use app\common\plugin\service\FrontendHookService;
use app\common\service\authentication\BearerTokenExtractor;
use app\common\service\authentication\TokenService;
use app\common\service\member\MemberAuthService;
use app\common\traits\JsonResponse;
use DomainException;
use support\Container;
use support\Request;
use support\Response;

final class FrontendHooks
{
    use JsonResponse;

    /**
     * 按受众返回已启用插件的前端钩子描述。
     *
     * @OA\Get(
     *     path="/plugins/hooks/frontend",
     *     operationId="common_plugin_controller_FrontendHooks_index_get",
     *     tags={"插件前端钩子"},
     *     summary="获取已启用插件的前端钩子描述。",
     *     description="路由在 config/route.php 中注册。audience=admin 时按后台会话 Cookie 的权限过滤，audience=member 时需要有效会员 Bearer 令牌；public 无需登录。响应体 code 与 HTTP 状态一致。",
     *     security={},
     *     @OA\Parameter(name="audience", in="query", required=false, description="钩子受众。", @OA\Schema(type="string", enum={"public", "member", "admin"}, default="public")),
     *     @OA\Response(response=200, description="钩子描述列表。", @OA\JsonContent(ref="#/components/schemas/AdminEnvelope")),
     *     @OA\Response(response=400, ref="#/components/responses/AdminError"),
     *     @OA\Response(response=401, ref="#/components/responses/AdminError"),
     *     @OA\Response(response=403, ref="#/components/responses/AdminError"),
     *     @OA\Response(response="default", ref="#/components/responses/AdminError")
     * )
     *
     * @OA\Get(
     *     path="/admin/plugins/hooks/frontend",
     *     operationId="common_plugin_controller_FrontendHooks_index_admin_get",
     *     tags={"插件前端钩子"},
     *     summary="获取已启用插件的前端钩子描述。",
     *     description="路由在 config/route.php 中注册。audience=admin 时按后台会话 Cookie 的权限过滤，audience=member 时需要有效会员 Bearer 令牌；public 无需登录。响应体 code 与 HTTP 状态一致。",
     *     security={},
     *     @OA\Parameter(name="audience", in="query", required=false, description="钩子受众。", @OA\Schema(type="string", enum={"public", "member", "admin"}, default="public")),
     *     @OA\Response(response=200, description="钩子描述列表。", @OA\JsonContent(ref="#/components/schemas/AdminEnvelope")),
     *     @OA\Response(response=400, ref="#/components/responses/AdminError"),
     *     @OA\Response(response=401, ref="#/components/responses/AdminError"),
     *     @OA\Response(response=403, ref="#/components/responses/AdminError"),
     *     @OA\Response(response="default", ref="#/components/responses/AdminError")
     * )
     */
    public function index(Request $request): Response
    {
        $audience = $request->get('audience', 'public');
        if (!is_string($audience) || !in_array($audience, ['public', 'member', 'admin'], true)) {
            return $this->fail('Invalid hook audience', code: 400);
        }

        $permissions = [];
        try {
            if ($audience === 'admin') {
                $cookie = (string) $request->cookie(BrowserAuthService::COOKIE, '');
                $user = (new BrowserAuthService())->user($cookie);
                $permissions = (new AuthorizationService())->userInfo($user)['permissions'];
            } elseif ($audience === 'member') {
                $token = (new BearerTokenExtractor())->extract($request);
                $identity = $token === null ? null : (new TokenService())->validateToken($token);
                $id = is_array($identity) ? (int) ($identity['id'] ?? 0) : 0;
                if ($id <= 0 || !(new MemberAuthService())->activeMember($id)) {
                    return $this->fail('Unauthorized', code: 401);
                }
            }

            $descriptors = Container::get(FrontendHookService::class)->descriptors($audience, $permissions);
            return $this->ok('操作成功', $descriptors);
        } catch (DomainException $error) {
            $status = in_array($error->getCode(), [401, 403], true) ? $error->getCode() : 401;
            return $this->fail($error->getMessage(), code: $status);
        }
    }
}
