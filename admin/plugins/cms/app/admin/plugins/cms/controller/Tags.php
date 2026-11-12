<?php
declare(strict_types=1);
namespace app\admin\plugins\cms\controller;

use OpenApi\Annotations as OA;

use support\annotation\route\{Get,Post,RouteGroup};
use support\Response;
use app\cms\service\Input;
use app\cms\service\TemplateTagService;
use app\cms\tags\BuiltinTags;
use app\cms\template\RenderContext;
#[RouteGroup('/admin/plugins/cms/tags')]
final class Tags extends Base
{
    /**
     * 获取本站模板标签列表。
     *
     * @OA\Get(
     *     path="/admin/plugins/cms/tags",
     *     operationId="admin_plugins_cms_controller_Tags_index_get",
     *     tags={"CMS 模板标签"},
     *     summary="获取本站模板标签列表。",
     *     description="普通后台业务协议：成功 code=200；HTTP 200 也可能表示 code=400/422 的业务失败，请同时检查响应体 code。 先校验站点访问与本站操作权限（403），他站记录返回 404；写操作成功后刷新该站点的公开页面缓存。",
     *     security={{"AdminSession"={}}},
     *     @OA\Parameter(name="site_id", in="query", required=true, description="站点 ID。", @OA\Schema(type="string", pattern="^[1-9][0-9]{0,19}$")),
     *     @OA\Parameter(name="locale", in="query", required=false, description="内容语言代码，默认 zh-cn。", @OA\Schema(type="string")),
     *     @OA\Response(response=200, description="业务响应；以响应体 code 判断成功与失败。", @OA\JsonContent(ref="#/components/schemas/AdminResult")),
     *     @OA\Response(response=401, ref="#/components/responses/AdminError"),
     *     @OA\Response(response=403, ref="#/components/responses/AdminError"),
     *     @OA\Response(response="default", ref="#/components/responses/AdminError")
     * )
     */
    #[Get('')]
    public function index(): Response { return $this->action(fn()=>(new TemplateTagService())->listing($this->site('cms:tag:list'),$this->locale())); }
    /**
     * 获取内置标签提供者定义。
     *
     * @OA\Get(
     *     path="/admin/plugins/cms/tags/providers",
     *     operationId="admin_plugins_cms_controller_Tags_providers_get",
     *     tags={"CMS 模板标签"},
     *     summary="获取内置标签提供者定义。",
     *     description="普通后台业务协议：成功 code=200；HTTP 200 也可能表示 code=400/422 的业务失败，请同时检查响应体 code。 先校验站点访问与本站操作权限（403），他站记录返回 404；写操作成功后刷新该站点的公开页面缓存。",
     *     security={{"AdminSession"={}}},
     *     @OA\Parameter(name="site_id", in="query", required=true, description="站点 ID。", @OA\Schema(type="string", pattern="^[1-9][0-9]{0,19}$")),
     *     @OA\Parameter(name="locale", in="query", required=false, description="内容语言代码，默认 zh-cn。", @OA\Schema(type="string")),
     *     @OA\Response(response=200, description="业务响应；以响应体 code 判断成功与失败。", @OA\JsonContent(ref="#/components/schemas/AdminResult")),
     *     @OA\Response(response=401, ref="#/components/responses/AdminError"),
     *     @OA\Response(response=403, ref="#/components/responses/AdminError"),
     *     @OA\Response(response="default", ref="#/components/responses/AdminError")
     * )
     */
    #[Get('/providers')]
    public function providers(): Response { return $this->action(fn()=>(new BuiltinTags())->registry(new RenderContext($this->site('cms:tag:list'),$this->locale()))->definitions()); }
    /**
     * 返回当前站点、语言的标签参数选项，仅需模板标签查看权限。
     *
     * @OA\Get(
     *     path="/admin/plugins/cms/tags/options",
     *     operationId="admin_plugins_cms_controller_Tags_options_get",
     *     tags={"CMS 模板标签"},
     *     summary="获取当前站点、语言的标签参数选项。",
     *     description="普通后台业务协议：成功 code=200；HTTP 200 也可能表示 code=400/422 的业务失败，请同时检查响应体 code。 先校验站点访问与本站操作权限（403），他站记录返回 404；写操作成功后刷新该站点的公开页面缓存。",
     *     security={{"AdminSession"={}}},
     *     @OA\Parameter(name="site_id", in="query", required=true, description="站点 ID。", @OA\Schema(type="string", pattern="^[1-9][0-9]{0,19}$")),
     *     @OA\Parameter(name="locale", in="query", required=false, description="内容语言代码，默认 zh-cn。", @OA\Schema(type="string")),
     *     @OA\Response(response=200, description="业务响应；以响应体 code 判断成功与失败。", @OA\JsonContent(ref="#/components/schemas/AdminResult")),
     *     @OA\Response(response=401, ref="#/components/responses/AdminError"),
     *     @OA\Response(response=403, ref="#/components/responses/AdminError"),
     *     @OA\Response(response="default", ref="#/components/responses/AdminError")
     * )
     */
    #[Get('/options')]
    public function options(): Response { return $this->action(fn()=>(new TemplateTagService())->editorOptions($this->site('cms:tag:list'),$this->locale())); }
    /**
     * 新增或保存模板标签。
     *
     * @OA\Post(
     *     path="/admin/plugins/cms/tags/save",
     *     operationId="admin_plugins_cms_controller_Tags_save_post",
     *     tags={"CMS 模板标签"},
     *     summary="新增或保存模板标签。",
     *     description="普通后台业务协议：成功 code=200；HTTP 200 也可能表示 code=400/422 的业务失败，请同时检查响应体 code。 先校验站点访问与本站操作权限（403），他站记录返回 404；写操作成功后刷新该站点的公开页面缓存。",
     *     security={{"AdminSession"={}, "CsrfToken"={}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             type="object",
     *             required={"site_id"},
     *             description="标签字段由 TemplateTagService::save 校验。",
     *             additionalProperties=true,
     *             @OA\Property(property="site_id", type="string", pattern="^[1-9][0-9]{0,19}$", description="站点 ID。"),
     *             @OA\Property(property="locale", type="string", description="内容语言代码，默认 zh-cn。"),
     *             @OA\Property(property="id", type="string", pattern="^[1-9][0-9]{0,19}$", description="标签 ID；省略时新增。"),
     *             @OA\Property(property="tag_locale", type="string", description="标签所属语言；空字符串表示全语言。")
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
    public function save(): Response { return $this->action(fn()=>$this->mutate('tag',fn($site)=>(new TemplateTagService())->save($site,($this->input()['tag_locale']??'')===''?'':Input::locale($this->input()['tag_locale']),$this->input(),$this->actor()))); }
    /**
     * 发布模板标签的指定版本。
     *
     * @OA\Post(
     *     path="/admin/plugins/cms/tags/publish",
     *     operationId="admin_plugins_cms_controller_Tags_publish_post",
     *     tags={"CMS 模板标签"},
     *     summary="发布模板标签的指定版本。",
     *     description="普通后台业务协议：成功 code=200；HTTP 200 也可能表示 code=400/422 的业务失败，请同时检查响应体 code。 先校验站点访问与本站操作权限（403），他站记录返回 404；写操作成功后刷新该站点的公开页面缓存。",
     *     security={{"AdminSession"={}, "CsrfToken"={}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             type="object",
     *             required={"site_id", "id", "version"},
     *             @OA\Property(property="site_id", type="string", pattern="^[1-9][0-9]{0,19}$", description="站点 ID。"),
     *             @OA\Property(property="id", type="string", pattern="^[1-9][0-9]{0,19}$", description="标签 ID。"),
     *             @OA\Property(property="version", type="integer", description="版本号。")
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
    #[Post('/publish')]
    public function publish(): Response { return $this->action(function(): bool {(new TemplateTagService())->publish($this->site('cms:tag:publish'),Input::id($this->input()['id']??''),(int)($this->input()['version']??0));return true;}); }
    /**
     * 渲染模板标签预览（data.html）。
     *
     * @OA\Get(
     *     path="/admin/plugins/cms/tags/preview",
     *     operationId="admin_plugins_cms_controller_Tags_preview_get",
     *     tags={"CMS 模板标签"},
     *     summary="渲染模板标签预览（data.html）。",
     *     description="普通后台业务协议：成功 code=200；HTTP 200 也可能表示 code=400/422 的业务失败，请同时检查响应体 code。 先校验站点访问与本站操作权限（403），他站记录返回 404；写操作成功后刷新该站点的公开页面缓存。",
     *     security={{"AdminSession"={}}},
     *     @OA\Parameter(name="site_id", in="query", required=true, description="站点 ID。", @OA\Schema(type="string", pattern="^[1-9][0-9]{0,19}$")),
     *     @OA\Parameter(name="locale", in="query", required=false, description="内容语言代码，默认 zh-cn。", @OA\Schema(type="string")),
     *     @OA\Parameter(name="id", in="query", required=true, description="标签 ID。", @OA\Schema(type="string", pattern="^[1-9][0-9]{0,19}$")),
     *     @OA\Parameter(name="version", in="query", required=false, description="版本号。", @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="业务响应；以响应体 code 判断成功与失败。", @OA\JsonContent(ref="#/components/schemas/AdminResult")),
     *     @OA\Response(response=401, ref="#/components/responses/AdminError"),
     *     @OA\Response(response=403, ref="#/components/responses/AdminError"),
     *     @OA\Response(response=404, ref="#/components/responses/AdminError"),
     *     @OA\Response(response="default", ref="#/components/responses/AdminError")
     * )
     */
    #[Get('/preview')]
    public function preview(): Response { return $this->action(fn()=>['html'=>(new TemplateTagService())->preview($this->site('cms:tag:preview'),$this->locale(),Input::id($this->input()['id']??''),(int)($this->input()['version']??0))]); }
}
