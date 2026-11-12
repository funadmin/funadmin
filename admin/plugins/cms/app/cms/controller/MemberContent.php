<?php

declare (strict_types=1);
namespace app\cms\controller;

use OpenApi\Annotations as OA;

use app\cms\service\{ContributionPolicy, ContributionService, Input};
use support\annotation\route\{Get, Post, RouteGroup};
use support\Response;
#[RouteGroup('/cms/member')]
final class MemberContent extends MemberEndpoint
{
    /**
     * 获取可投稿站点、语言与投稿策略。
     *
     * @OA\Get(
     *     path="/cms/member/context",
     *     operationId="cms_controller_MemberContent_context_get",
     *     tags={"CMS 会员投稿"},
     *     summary="获取可投稿站点、语言与投稿策略。",
     *     description="会员接口：以会员 API v2 access token 识别会员；响应不缓存。",
     *     security={},
     *     @OA\Response(response=200, description="成功。", @OA\JsonContent(ref="#/components/schemas/PluginEnvelope")),
     *     @OA\Response(response="default", ref="#/components/responses/PluginError")
     * )
     */
    #[Get('/context')]
    public function context(): Response
    {
        return $this->action(fn() => (new ContributionPolicy())->context());
    }
    /**
     * 获取当前会员 ID（data.member_id）。
     *
     * @OA\Get(
     *     path="/cms/member/identity",
     *     operationId="cms_controller_MemberContent_identity_get",
     *     tags={"CMS 会员投稿"},
     *     summary="获取当前会员 ID（data.member_id）。",
     *     description="会员接口：以会员 API v2 access token 识别会员；响应不缓存。",
     *     security={{"MemberBearer"={}}},
     *     @OA\Response(response=200, description="成功。", @OA\JsonContent(ref="#/components/schemas/PluginEnvelope")),
     *     @OA\Response(response=401, ref="#/components/responses/PluginError"),
     *     @OA\Response(response="default", ref="#/components/responses/PluginError")
     * )
     */
    #[Get('/identity')]
    public function identity(): Response
    {
        return $this->action(fn() => ['member_id' => $this->member()]);
    }
    /**
     * 获取当前会员在本站的投稿列表。
     *
     * @OA\Get(
     *     path="/cms/member/submissions",
     *     operationId="cms_controller_MemberContent_submissions_get",
     *     tags={"CMS 会员投稿"},
     *     summary="获取当前会员在本站的投稿列表。",
     *     description="会员接口：以会员 API v2 access token 识别会员；响应不缓存。",
     *     security={{"MemberBearer"={}}},
     *     @OA\Parameter(name="site_id", in="query", required=true, description="站点 ID。", @OA\Schema(type="string", pattern="^[1-9][0-9]{0,19}$")),
     *     @OA\Parameter(name="locale", in="query", required=false, description="内容语言代码，默认 zh-cn。", @OA\Schema(type="string")),
     *     @OA\Parameter(name="page", in="query", required=false, description="页码，从 1 开始。", @OA\Schema(type="integer", minimum=1)),
     *     @OA\Response(response=200, description="成功。", @OA\JsonContent(ref="#/components/schemas/PluginEnvelope")),
     *     @OA\Response(response=401, ref="#/components/responses/PluginError"),
     *     @OA\Response(response=404, ref="#/components/responses/PluginError"),
     *     @OA\Response(response="default", ref="#/components/responses/PluginError")
     * )
     */
    #[Get('/submissions')]
    public function submissions(): Response
    {
        return $this->action(function (): array {
            $member = $this->member();
            $input = $this->input();
            return (new ContributionService())->mine(Input::id($input['site_id'] ?? ''), Input::locale($input['locale'] ?? 'zh-cn'), $member, (int) ($input['page'] ?? 1));
        });
    }
    /**
     * 提交内容投稿；同一 request_key 重放返回已有投稿。
     *
     * @OA\Post(
     *     path="/cms/member/submissions",
     *     operationId="cms_controller_MemberContent_submit_post",
     *     tags={"CMS 会员投稿"},
     *     summary="提交内容投稿；同一 request_key 重放返回已有投稿。",
     *     description="会员接口：以会员 API v2 access token 识别会员；响应不缓存。",
     *     security={{"MemberBearer"={}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             type="object",
     *             required={"site_id", "request_key", "title"},
     *             description="其余字段由 ContributionService::submit 校验。",
     *             additionalProperties=true,
     *             @OA\Property(property="site_id", type="string", pattern="^[1-9][0-9]{0,19}$", description="站点 ID。"),
     *             @OA\Property(property="locale", type="string", description="内容语言代码，默认 zh-cn。"),
     *             @OA\Property(property="request_key", type="string", description="幂等键，8 到 64 位字母、数字、下划线或短横线。"),
     *             @OA\Property(property="title", type="string", description="标题，最多 255 字。"),
     *             @OA\Property(property="body_html", type="string", description="正文 HTML，会被清洗。"),
     *             @OA\Property(property="data", type="object", additionalProperties=true, description="模型字段。")
     *         )
     *     ),
     *     @OA\Response(response=200, description="成功。", @OA\JsonContent(ref="#/components/schemas/PluginEnvelope")),
     *     @OA\Response(response=401, ref="#/components/responses/PluginError"),
     *     @OA\Response(response=403, ref="#/components/responses/PluginError"),
     *     @OA\Response(response=404, ref="#/components/responses/PluginError"),
     *     @OA\Response(response=409, ref="#/components/responses/PluginError"),
     *     @OA\Response(response=422, ref="#/components/responses/PluginError"),
     *     @OA\Response(response=429, ref="#/components/responses/PluginError"),
     *     @OA\Response(response="default", ref="#/components/responses/PluginError")
     * )
     */
    #[Post('/submissions')]
    public function submit(): Response
    {
        return $this->action(function (): array {
            $member = $this->member();
            $input = $this->input();
            return (new ContributionService())->submit(Input::id($input['site_id'] ?? ''), Input::locale($input['locale'] ?? 'zh-cn'), $member, 'content', $input);
        });
    }
    /**
     * 撤回本人的投稿。
     *
     * @OA\Post(
     *     path="/cms/member/submissions/{id}/withdraw",
     *     operationId="cms_controller_MemberContent_withdraw_post",
     *     tags={"CMS 会员投稿"},
     *     summary="撤回本人的投稿。",
     *     description="会员接口：以会员 API v2 access token 识别会员；响应不缓存。",
     *     security={{"MemberBearer"={}}},
     *     @OA\Parameter(name="id", in="path", required=true, description="投稿 ID。", @OA\Schema(type="string")),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             type="object",
     *             required={"site_id", "lock_version"},
     *             @OA\Property(property="site_id", type="string", pattern="^[1-9][0-9]{0,19}$", description="站点 ID。"),
     *             @OA\Property(property="lock_version", type="integer", description="乐观锁版本（非负整数）。")
     *         )
     *     ),
     *     @OA\Response(response=200, description="成功。", @OA\JsonContent(ref="#/components/schemas/PluginEnvelope")),
     *     @OA\Response(response=401, ref="#/components/responses/PluginError"),
     *     @OA\Response(response=404, ref="#/components/responses/PluginError"),
     *     @OA\Response(response=409, ref="#/components/responses/PluginError"),
     *     @OA\Response(response=422, ref="#/components/responses/PluginError"),
     *     @OA\Response(response="default", ref="#/components/responses/PluginError")
     * )
     */
    #[Post('/submissions/{id}/withdraw')]
    public function withdraw(string $id): Response
    {
        return $this->action(function () use ($id): array {
            $member = $this->member();
            $input = $this->input();
            $version = filter_var($input['lock_version'] ?? null, FILTER_VALIDATE_INT);
            if ($version === false || $version === null || $version < 0) {
                throw new \InvalidArgumentException('lock_version 必须为非负整数', 422);
            }
            return (new ContributionService())->withdraw(Input::id($input['site_id'] ?? ''), $member, Input::id($id), $version);
        });
    }
}
