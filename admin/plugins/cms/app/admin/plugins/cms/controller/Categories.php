<?php
declare(strict_types=1);
namespace app\admin\plugins\cms\controller;

use OpenApi\Annotations as OA;

use support\annotation\route\{Get,Post,RouteGroup};
use support\Response;
use app\cms\service\CategoryService;
#[RouteGroup('/admin/plugins/cms/categories')]
final class Categories extends Base
{
    /**
     * with_forms=1 时额外返回“附带表单”下拉选项（不新增接口，沿用栏目查看权限）；默认仍返回栏目数组。
     *
     * @OA\Get(
     *     path="/admin/plugins/cms/categories",
     *     operationId="admin_plugins_cms_controller_Categories_index_get",
     *     tags={"CMS 栏目"},
     *     summary="获取本站栏目列表。",
     *     description="普通后台业务协议：成功 code=200；HTTP 200 也可能表示 code=400/422 的业务失败，请同时检查响应体 code。 先校验站点访问与本站操作权限（403），他站记录返回 404；写操作成功后刷新该站点的公开页面缓存。",
     *     security={{"AdminSession"={}}},
     *     @OA\Parameter(name="site_id", in="query", required=true, description="站点 ID。", @OA\Schema(type="string", pattern="^[1-9][0-9]{0,19}$")),
     *     @OA\Parameter(name="locale", in="query", required=false, description="内容语言代码，默认 zh-cn。", @OA\Schema(type="string")),
     *     @OA\Parameter(name="with_forms", in="query", required=false, description="为 1 时返回 {categories, forms}，附带表单下拉选项。", @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="业务响应；以响应体 code 判断成功与失败。", @OA\JsonContent(ref="#/components/schemas/AdminResult")),
     *     @OA\Response(response=401, ref="#/components/responses/AdminError"),
     *     @OA\Response(response=403, ref="#/components/responses/AdminError"),
     *     @OA\Response(response="default", ref="#/components/responses/AdminError")
     * )
     */
    #[Get('')]
    public function index(): Response { return $this->action(function(): array {
        $site=$this->site('cms:category:list');$rows=(new CategoryService())->listing($site,$this->locale(),false);
        return !empty($this->input()['with_forms'])?['categories'=>$rows,'forms'=>(new \app\cms\service\FormService())->options($site)]:$rows;
    }); }
    /**
     * 启用或停用栏目。
     *
     * @OA\Post(
     *     path="/admin/plugins/cms/categories/status",
     *     operationId="admin_plugins_cms_controller_Categories_status_post",
     *     tags={"CMS 栏目"},
     *     summary="启用或停用栏目。",
     *     description="普通后台业务协议：成功 code=200；HTTP 200 也可能表示 code=400/422 的业务失败，请同时检查响应体 code。 先校验站点访问与本站操作权限（403），他站记录返回 404；写操作成功后刷新该站点的公开页面缓存。",
     *     security={{"AdminSession"={}, "CsrfToken"={}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             type="object",
     *             required={"site_id", "id", "status"},
     *             description="字段由 MutationAuthorization::setStatus 校验。",
     *             additionalProperties=true,
     *             @OA\Property(property="site_id", type="string", pattern="^[1-9][0-9]{0,19}$", description="站点 ID。"),
     *             @OA\Property(property="locale", type="string", description="内容语言代码，默认 zh-cn。"),
     *             @OA\Property(property="id", type="string", pattern="^[1-9][0-9]{0,19}$", description="栏目 ID。"),
     *             @OA\Property(property="status", type="integer", description="1 启用，0 停用。")
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
    #[Post('/status')]
    public function status(): Response { return $this->action(fn()=>(new \app\cms\service\MutationAuthorization())->setStatus($this->site('cms:category:status'),$this->actor(),'category',$this->locale(),$this->input())); }
    /**
     * 新增或保存栏目（含当前语言的栏目文案）。
     *
     * @OA\Post(
     *     path="/admin/plugins/cms/categories/save",
     *     operationId="admin_plugins_cms_controller_Categories_save_post",
     *     tags={"CMS 栏目"},
     *     summary="新增或保存栏目（含当前语言的栏目文案）。",
     *     description="普通后台业务协议：成功 code=200；HTTP 200 也可能表示 code=400/422 的业务失败，请同时检查响应体 code。 先校验站点访问与本站操作权限（403），他站记录返回 404；写操作成功后刷新该站点的公开页面缓存。",
     *     security={{"AdminSession"={}, "CsrfToken"={}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             type="object",
     *             required={"site_id"},
     *             description="栏目字段由 CategoryService::save 校验。",
     *             additionalProperties=true,
     *             @OA\Property(property="site_id", type="string", pattern="^[1-9][0-9]{0,19}$", description="站点 ID。"),
     *             @OA\Property(property="locale", type="string", description="内容语言代码，默认 zh-cn。"),
     *             @OA\Property(property="id", type="string", pattern="^[1-9][0-9]{0,19}$", description="栏目 ID；省略时新增。")
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
    #[Post('/save')]
    public function save(): Response { return $this->action(fn()=>$this->mutate('category',fn($site)=>(new CategoryService())->save($site,$this->locale(),$this->input()))); }
}
