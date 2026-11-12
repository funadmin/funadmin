<?php

declare (strict_types=1);
namespace app\admin\plugins\cms\controller;

use OpenApi\Annotations as OA;

use app\cms\service\{ContributionPolicy, ContributionService};
use support\annotation\route\{Get, Post, RouteGroup};
use support\{Db, Response};
#[RouteGroup('/admin/plugins/cms/contributions')]
final class Contributions extends Base
{
    /**
     * 获取会员内容投稿列表。
     *
     * @OA\Get(
     *     path="/admin/plugins/cms/contributions",
     *     operationId="admin_plugins_cms_controller_Contributions_index_get",
     *     tags={"CMS 投稿审核"},
     *     summary="获取会员内容投稿列表。",
     *     description="普通后台业务协议：成功 code=200；HTTP 200 也可能表示 code=400/422 的业务失败，请同时检查响应体 code。 先校验站点访问与本站操作权限（403），他站记录返回 404；写操作成功后刷新该站点的公开页面缓存。",
     *     security={{"AdminSession"={}}},
     *     @OA\Parameter(name="site_id", in="query", required=true, description="站点 ID。", @OA\Schema(type="string", pattern="^[1-9][0-9]{0,19}$")),
     *     @OA\Parameter(name="locale", in="query", required=false, description="内容语言代码，默认 zh-cn。", @OA\Schema(type="string")),
     *     @OA\Parameter(name="status", in="query", required=false, description="投稿状态，默认 pending。", @OA\Schema(type="string")),
     *     @OA\Parameter(name="page", in="query", required=false, description="页码，从 1 开始。", @OA\Schema(type="integer", minimum=1)),
     *     @OA\Response(response=200, description="业务响应；以响应体 code 判断成功与失败。", @OA\JsonContent(ref="#/components/schemas/AdminResult")),
     *     @OA\Response(response=401, ref="#/components/responses/AdminError"),
     *     @OA\Response(response=403, ref="#/components/responses/AdminError"),
     *     @OA\Response(response="default", ref="#/components/responses/AdminError")
     * )
     */
    #[Get('')]
    public function index(): Response
    {
        return $this->action(function (): array {
            $site = $this->site('cms:submission:list');
            $input = $this->input();
            return (new ContributionService())->listing($site, $this->locale(), 'content', $input['status'] ?? 'pending', (int) ($input['page'] ?? 1));
        });
    }
    /**
     * 审核会员内容投稿（通过或驳回）。
     *
     * @OA\Post(
     *     path="/admin/plugins/cms/contributions/review",
     *     operationId="admin_plugins_cms_controller_Contributions_review_post",
     *     tags={"CMS 投稿审核"},
     *     summary="审核会员内容投稿（通过或驳回）。",
     *     description="普通后台业务协议：成功 code=200；HTTP 200 也可能表示 code=400/422 的业务失败，请同时检查响应体 code。 先校验站点访问与本站操作权限（403），他站记录返回 404；写操作成功后刷新该站点的公开页面缓存。",
     *     security={{"AdminSession"={}, "CsrfToken"={}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             type="object",
     *             required={"site_id", "id", "lock_version", "decision"},
     *             @OA\Property(property="site_id", type="string", pattern="^[1-9][0-9]{0,19}$", description="站点 ID。"),
     *             @OA\Property(property="id", type="string", pattern="^[1-9][0-9]{0,19}$", description="投稿 ID。"),
     *             @OA\Property(property="lock_version", type="integer", description="乐观锁版本（非负整数）。"),
     *             @OA\Property(property="decision", type="string", description="审核决定。"),
     *             @OA\Property(property="reason", type="string", description="驳回原因，最多 1000 字。")
     *         )
     *     ),
     *     @OA\Response(response=200, description="业务响应；以响应体 code 判断成功与失败。", @OA\JsonContent(ref="#/components/schemas/AdminResult")),
     *     @OA\Response(response=401, ref="#/components/responses/AdminError"),
     *     @OA\Response(response=403, ref="#/components/responses/AdminError"),
     *     @OA\Response(response=404, ref="#/components/responses/AdminError"),
     *     @OA\Response(response=409, ref="#/components/responses/AdminError"),
     *     @OA\Response(response="default", ref="#/components/responses/AdminError")
     * )
     */
    #[Post('/review')]
    public function review(): Response
    {
        return $this->action(function (): array {
            $site = $this->site('cms:submission:review');
            $input = $this->input();
            $version = filter_var($input['lock_version'] ?? null, FILTER_VALIDATE_INT);
            if ($version === false || $version === null || $version < 0) {
                throw new \InvalidArgumentException('lock_version 必须为非负整数', 422);
            }
            return (new ContributionService())->reviewContent($site, $this->actor(), \app\cms\service\Input::id($input['id'] ?? ''), $version, \app\cms\service\Input::text($input['decision'] ?? '', 20), \app\cms\service\Input::text($input['reason'] ?? '', 1000));
        });
    }
    /**
     * 读取本站投稿策略。
     *
     * @OA\Get(
     *     path="/admin/plugins/cms/contributions/settings",
     *     operationId="admin_plugins_cms_controller_Contributions_settings_get",
     *     tags={"CMS 投稿审核"},
     *     summary="读取本站投稿策略。",
     *     description="普通后台业务协议：成功 code=200；HTTP 200 也可能表示 code=400/422 的业务失败，请同时检查响应体 code。 先校验站点访问与本站操作权限（403），他站记录返回 404；写操作成功后刷新该站点的公开页面缓存。",
     *     security={{"AdminSession"={}}},
     *     @OA\Parameter(name="site_id", in="query", required=true, description="站点 ID。", @OA\Schema(type="string", pattern="^[1-9][0-9]{0,19}$")),
     *     @OA\Response(response=200, description="业务响应；以响应体 code 判断成功与失败。", @OA\JsonContent(ref="#/components/schemas/AdminResult")),
     *     @OA\Response(response=401, ref="#/components/responses/AdminError"),
     *     @OA\Response(response=403, ref="#/components/responses/AdminError"),
     *     @OA\Response(response=409, ref="#/components/responses/AdminError"),
     *     @OA\Response(response="default", ref="#/components/responses/AdminError")
     * )
     */
    #[Get('/settings')]
    public function settings(): Response
    {
        return $this->action(fn() => $this->policy()->read($this->site('cms:site:detail')));
    }
    /**
     * 保存本站投稿策略。
     *
     * @OA\Post(
     *     path="/admin/plugins/cms/contributions/settings/save",
     *     operationId="admin_plugins_cms_controller_Contributions_saveSettings_post",
     *     tags={"CMS 投稿审核"},
     *     summary="保存本站投稿策略。",
     *     description="普通后台业务协议：成功 code=200；HTTP 200 也可能表示 code=400/422 的业务失败，请同时检查响应体 code。 先校验站点访问与本站操作权限（403），他站记录返回 404；写操作成功后刷新该站点的公开页面缓存。",
     *     security={{"AdminSession"={}, "CsrfToken"={}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             type="object",
     *             required={"site_id"},
     *             description="策略字段由 ContributionPolicy::save 校验。",
     *             additionalProperties=true,
     *             @OA\Property(property="site_id", type="string", pattern="^[1-9][0-9]{0,19}$", description="站点 ID。")
     *         )
     *     ),
     *     @OA\Response(response=200, description="业务响应；以响应体 code 判断成功与失败。", @OA\JsonContent(ref="#/components/schemas/AdminResult")),
     *     @OA\Response(response=401, ref="#/components/responses/AdminError"),
     *     @OA\Response(response=403, ref="#/components/responses/AdminError"),
     *     @OA\Response(response=409, ref="#/components/responses/AdminError"),
     *     @OA\Response(response="default", ref="#/components/responses/AdminError")
     * )
     */
    #[Post('/settings/save')]
    public function saveSettings(): Response
    {
        return $this->action(fn() => $this->policy()->save($this->site('cms:site:edit'), $this->actor(), $this->input()));
    }
    private function policy(): ContributionPolicy
    {
        if (!Db::schema()->hasTable('cms_member_submission')) {
            throw new \InvalidArgumentException('请完成 CMS 会员投稿升级', 409);
        }
        return new ContributionPolicy();
    }
}
