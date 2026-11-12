<?php
declare(strict_types=1);
namespace app\admin\plugins\cms\controller;

use OpenApi\Annotations as OA;

use support\annotation\route\Get;
use support\annotation\route\Post;
use support\annotation\route\RouteGroup;
use support\Response;
use support\Db;
use app\cms\service\Input;
use app\cms\service\SiteService;
use app\cms\service\SiteAccess;
use app\cms\service\SiteAuthorization;
use app\cms\service\PermissionCatalog;
use app\cms\model\ContentModel;
use app\cms\model\SiteLocale;
use app\cms\model\SiteModel;
#[RouteGroup('/admin/plugins/cms')]
final class Sites extends Base
{
    /** 站点保存以 id 指定站点，其余接口仍用 site_id。 */
    protected function invalidatePublicPages(): void
    {
        parent::invalidatePublicPages();$id=(string)($this->input()['id']??'');
        if(($this->input()['site_id']??'')===''&&$id!=='')try{(new \app\cms\service\PublicPageCache())->invalidateSite($id);}catch(\Throwable){}
    }
    /**
     * 获取当前管理员可进入的站点、启用语言与本站权限。
     *
     * @OA\Get(
     *     path="/admin/plugins/cms/context",
     *     operationId="admin_plugins_cms_controller_Sites_context_get",
     *     tags={"CMS 站点"},
     *     summary="获取当前管理员可进入的站点、启用语言与本站权限。",
     *     description="普通后台业务协议：成功 code=200；HTTP 200 也可能表示 code=400/422 的业务失败，请同时检查响应体 code。 先校验站点访问与本站操作权限（403），他站记录返回 404；写操作成功后刷新该站点的公开页面缓存。",
     *     security={{"AdminSession"={}}},
     *     @OA\Parameter(name="menu", in="query", required=false, description="只返回拥有该菜单权限的站点；须为已登记菜单。", @OA\Schema(type="string")),
     *     @OA\Response(response=200, description="业务响应；以响应体 code 判断成功与失败。", @OA\JsonContent(ref="#/components/schemas/AdminResult")),
     *     @OA\Response(response=401, ref="#/components/responses/AdminError"),
     *     @OA\Response(response=403, ref="#/components/responses/AdminError"),
     *     @OA\Response(response="default", ref="#/components/responses/AdminError")
     * )
     */
    #[Get('/context')]
    public function context(): Response { return $this->action(function():array {
        $menu=(string)($this->input()['menu']??'');if($menu!==''&&!in_array($menu,PermissionCatalog::menus(),true))throw new \InvalidArgumentException('未知菜单',422);
        $auth=new SiteAuthorization();$items=[];
        foreach((new SiteService())->listing($this->actor()) as $site){$effective=$auth->effective($site['id'],$this->actor());if(!$effective||$menu!==''&&!in_array($menu,$effective,true))continue;
            $locales=Input::rows(SiteLocale::query()->where('site_id',$site['id'])->where('status',1)->select(['id','site_id','locale','name','url_prefix','status'])->orderBy('id')->get());
            $items[]=['id'=>$site['id'],'name'=>$site['name'],'default_locale'=>$site['default_locale'],'locales'=>$locales,'permissions'=>$effective];
        }
        return ['items'=>$items,'is_super'=>(new SiteAccess())->isSuper($this->actor())];
    }); }
    /**
     * 获取站点列表（同站点上下文）。
     *
     * @OA\Get(
     *     path="/admin/plugins/cms/sites",
     *     operationId="admin_plugins_cms_controller_Sites_index_get",
     *     tags={"CMS 站点"},
     *     summary="获取站点列表（同站点上下文）。",
     *     description="普通后台业务协议：成功 code=200；HTTP 200 也可能表示 code=400/422 的业务失败，请同时检查响应体 code。 先校验站点访问与本站操作权限（403），他站记录返回 404；写操作成功后刷新该站点的公开页面缓存。",
     *     security={{"AdminSession"={}}},
     *     @OA\Parameter(name="menu", in="query", required=false, description="只返回拥有该菜单权限的站点。", @OA\Schema(type="string")),
     *     @OA\Response(response=200, description="业务响应；以响应体 code 判断成功与失败。", @OA\JsonContent(ref="#/components/schemas/AdminResult")),
     *     @OA\Response(response=401, ref="#/components/responses/AdminError"),
     *     @OA\Response(response=403, ref="#/components/responses/AdminError"),
     *     @OA\Response(response="default", ref="#/components/responses/AdminError")
     * )
     */
    #[Get('/sites')]
    public function index(): Response { return $this->context(); }
    /**
     * 新建站点需平台管理员；编辑站点沿用本站编辑权限读取可用主题。
     *
     * @OA\Get(
     *     path="/admin/plugins/cms/sites/options",
     *     operationId="admin_plugins_cms_controller_Sites_options_get",
     *     tags={"CMS 站点"},
     *     summary="获取站点表单的主题选项；无 site_id 时需平台管理员。",
     *     description="普通后台业务协议：成功 code=200；HTTP 200 也可能表示 code=400/422 的业务失败，请同时检查响应体 code。 先校验站点访问与本站操作权限（403），他站记录返回 404；写操作成功后刷新该站点的公开页面缓存。",
     *     security={{"AdminSession"={}}},
     *     @OA\Parameter(name="site_id", in="query", required=false, description="编辑站点时传入站点 ID。", @OA\Schema(type="string", pattern="^[1-9][0-9]{0,19}$")),
     *     @OA\Response(response=200, description="业务响应；以响应体 code 判断成功与失败。", @OA\JsonContent(ref="#/components/schemas/AdminResult")),
     *     @OA\Response(response=401, ref="#/components/responses/AdminError"),
     *     @OA\Response(response=403, ref="#/components/responses/AdminError"),
     *     @OA\Response(response="default", ref="#/components/responses/AdminError")
     * )
     */
    #[Get('/sites/options')]
    public function options(): Response { return $this->action(function(): array {
        if(empty($this->input()['site_id']))(new SiteAccess())->super($this->actor());else $this->site('cms:site:edit');
        return ['themes'=>(new \app\cms\service\TemplateCatalog())->themes()];
    }); }
    /**
     * 获取站点详情、语言、域名与模型绑定。
     *
     * @OA\Get(
     *     path="/admin/plugins/cms/settings",
     *     operationId="admin_plugins_cms_controller_Sites_settings_get",
     *     tags={"CMS 站点"},
     *     summary="获取站点详情、语言、域名与模型绑定。",
     *     description="普通后台业务协议：成功 code=200；HTTP 200 也可能表示 code=400/422 的业务失败，请同时检查响应体 code。 先校验站点访问与本站操作权限（403），他站记录返回 404；写操作成功后刷新该站点的公开页面缓存。",
     *     security={{"AdminSession"={}}},
     *     @OA\Parameter(name="site_id", in="query", required=true, description="站点 ID。", @OA\Schema(type="string", pattern="^[1-9][0-9]{0,19}$")),
     *     @OA\Response(response=200, description="业务响应；以响应体 code 判断成功与失败。", @OA\JsonContent(ref="#/components/schemas/AdminResult")),
     *     @OA\Response(response=401, ref="#/components/responses/AdminError"),
     *     @OA\Response(response=403, ref="#/components/responses/AdminError"),
     *     @OA\Response(response=404, ref="#/components/responses/AdminError"),
     *     @OA\Response(response="default", ref="#/components/responses/AdminError")
     * )
     */
    #[Get('/settings')]
    public function settings(): Response { return $this->action(function():array {$id=$this->site('cms:site:detail');$s=new SiteService();return ['site'=>$s->get($id),'locales'=>$s->locales($id),'domains'=>$s->domains($id),'models'=>Input::rows(SiteModel::query()->where('site_id',$id)->get())];}); }
    /**
     * 读取站点配置（可按语言）。
     *
     * @OA\Get(
     *     path="/admin/plugins/cms/configuration",
     *     operationId="admin_plugins_cms_controller_Sites_configuration_get",
     *     tags={"CMS 站点"},
     *     summary="读取站点配置（可按语言）。",
     *     description="普通后台业务协议：成功 code=200；HTTP 200 也可能表示 code=400/422 的业务失败，请同时检查响应体 code。 先校验站点访问与本站操作权限（403），他站记录返回 404；写操作成功后刷新该站点的公开页面缓存。",
     *     security={{"AdminSession"={}}},
     *     @OA\Parameter(name="site_id", in="query", required=true, description="站点 ID。", @OA\Schema(type="string", pattern="^[1-9][0-9]{0,19}$")),
     *     @OA\Parameter(name="locale", in="query", required=false, description="语言代码；省略时读取站点级配置。", @OA\Schema(type="string")),
     *     @OA\Response(response=200, description="业务响应；以响应体 code 判断成功与失败。", @OA\JsonContent(ref="#/components/schemas/AdminResult")),
     *     @OA\Response(response=401, ref="#/components/responses/AdminError"),
     *     @OA\Response(response=403, ref="#/components/responses/AdminError"),
     *     @OA\Response(response=404, ref="#/components/responses/AdminError"),
     *     @OA\Response(response="default", ref="#/components/responses/AdminError")
     * )
     */
    #[Get('/configuration')]
    public function configuration(): Response { return $this->action(fn()=>(new \app\cms\service\SiteConfiguration())->read($this->site('cms:site:detail'),empty($this->input()['locale'])?'':Input::locale($this->input()['locale']))); }
    /**
     * 保存站点配置。
     *
     * @OA\Post(
     *     path="/admin/plugins/cms/configuration/save",
     *     operationId="admin_plugins_cms_controller_Sites_configurationSave_post",
     *     tags={"CMS 站点"},
     *     summary="保存站点配置。",
     *     description="普通后台业务协议：成功 code=200；HTTP 200 也可能表示 code=400/422 的业务失败，请同时检查响应体 code。 先校验站点访问与本站操作权限（403），他站记录返回 404；写操作成功后刷新该站点的公开页面缓存。",
     *     security={{"AdminSession"={}, "CsrfToken"={}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             type="object",
     *             required={"site_id"},
     *             description="配置字段由 SiteConfiguration::save 校验。",
     *             additionalProperties=true,
     *             @OA\Property(property="site_id", type="string", pattern="^[1-9][0-9]{0,19}$", description="站点 ID。"),
     *             @OA\Property(property="locale", type="string", description="内容语言代码，默认 zh-cn。")
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
    #[Post('/configuration/save')]
    public function configurationSave(): Response { return $this->action(fn()=>(new \app\cms\service\SiteConfiguration())->save(Input::id($this->input()['site_id']??''),$this->actor(),$this->input())); }
    /**
     * 新增站点（平台管理员）或保存站点；修改域名与默认语言另需对应本站权限。
     *
     * @OA\Post(
     *     path="/admin/plugins/cms/sites/save",
     *     operationId="admin_plugins_cms_controller_Sites_save_post",
     *     tags={"CMS 站点"},
     *     summary="新增站点（平台管理员）或保存站点；修改域名与默认语言另需对应本站权限。",
     *     description="普通后台业务协议：成功 code=200；HTTP 200 也可能表示 code=400/422 的业务失败，请同时检查响应体 code。 先校验站点访问与本站操作权限（403），他站记录返回 404；写操作成功后刷新该站点的公开页面缓存。",
     *     security={{"AdminSession"={}, "CsrfToken"={}}},
     *     @OA\RequestBody(
     *         required=false,
     *         @OA\JsonContent(
     *             type="object",
     *             description="站点字段由 SiteService::save 校验。",
     *             additionalProperties=true,
     *             @OA\Property(property="id", type="string", pattern="^[1-9][0-9]{0,19}$", description="站点 ID；省略时新增。"),
     *             @OA\Property(property="host", type="string", description="主域名。"),
     *             @OA\Property(property="primary_domain_id", type="string", pattern="^[1-9][0-9]{0,19}$", description="主域名 ID。"),
     *             @OA\Property(property="default_locale", type="string", description="默认语言。")
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
    #[Post('/sites/save')]
    public function save(): Response { return $this->action(fn()=>Db::transaction(function():array {
        $input=$this->input();if(!empty($input['id'])){
            $id=Input::id($input['id']);(new SiteService())->lock($id);$auth=new SiteAuthorization();$auth->assert($id,$this->actor(),'cms:site:edit');$old=(new SiteService())->get($id);
            if(!empty($input['host'])||isset($input['primary_domain_id'])&&(string)$input['primary_domain_id']!==(string)$old['primary_domain_id'])$auth->assert($id,$this->actor(),'cms:site:domain');
            if(isset($input['default_locale'])&&$input['default_locale']!==$old['default_locale'])$auth->assert($id,$this->actor(),'cms:site:locale');
        }return (new SiteService())->save($input,$this->actor());
    })); }
    /**
     * 新增或保存站点语言。
     *
     * @OA\Post(
     *     path="/admin/plugins/cms/locales/save",
     *     operationId="admin_plugins_cms_controller_Sites_localeSave_post",
     *     tags={"CMS 站点"},
     *     summary="新增或保存站点语言。",
     *     description="普通后台业务协议：成功 code=200；HTTP 200 也可能表示 code=400/422 的业务失败，请同时检查响应体 code。 先校验站点访问与本站操作权限（403），他站记录返回 404；写操作成功后刷新该站点的公开页面缓存。",
     *     security={{"AdminSession"={}, "CsrfToken"={}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             type="object",
     *             required={"site_id"},
     *             description="语言字段由 SiteService::saveLocale 校验。",
     *             additionalProperties=true,
     *             @OA\Property(property="site_id", type="string", pattern="^[1-9][0-9]{0,19}$", description="站点 ID。")
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
    #[Post('/locales/save')]
    public function localeSave(): Response { return $this->action(fn()=>(new SiteService())->saveLocale($this->site('cms:site:locale'),$this->input())); }
    /**
     * 新增或保存站点域名。
     *
     * @OA\Post(
     *     path="/admin/plugins/cms/domains/save",
     *     operationId="admin_plugins_cms_controller_Sites_domainSave_post",
     *     tags={"CMS 站点"},
     *     summary="新增或保存站点域名。",
     *     description="普通后台业务协议：成功 code=200；HTTP 200 也可能表示 code=400/422 的业务失败，请同时检查响应体 code。 先校验站点访问与本站操作权限（403），他站记录返回 404；写操作成功后刷新该站点的公开页面缓存。",
     *     security={{"AdminSession"={}, "CsrfToken"={}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             type="object",
     *             required={"site_id"},
     *             description="域名字段由 SiteService::saveDomain 校验。",
     *             additionalProperties=true,
     *             @OA\Property(property="site_id", type="string", pattern="^[1-9][0-9]{0,19}$", description="站点 ID。")
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
    #[Post('/domains/save')]
    public function domainSave(): Response { return $this->action(fn()=>(new SiteService())->saveDomain($this->site('cms:site:domain'),$this->input())); }
    /**
     * 设置本站模型绑定的启用状态与默认模板。
     *
     * @OA\Post(
     *     path="/admin/plugins/cms/site-models/save",
     *     operationId="admin_plugins_cms_controller_Sites_modelSave_post",
     *     tags={"CMS 站点"},
     *     summary="设置本站模型绑定的启用状态与默认模板。",
     *     description="普通后台业务协议：成功 code=200；HTTP 200 也可能表示 code=400/422 的业务失败，请同时检查响应体 code。 先校验站点访问与本站操作权限（403），他站记录返回 404；写操作成功后刷新该站点的公开页面缓存。",
     *     security={{"AdminSession"={}, "CsrfToken"={}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             type="object",
     *             required={"site_id", "model_id"},
     *             @OA\Property(property="site_id", type="string", pattern="^[1-9][0-9]{0,19}$", description="站点 ID。"),
     *             @OA\Property(property="model_id", type="string", pattern="^[1-9][0-9]{0,19}$", description="模型 ID。"),
     *             @OA\Property(property="status", type="integer", description="1 启用，0 停用。"),
     *             @OA\Property(property="default_template", type="string", description="默认模板，默认 detail。")
     *         )
     *     ),
     *     @OA\Response(response=200, description="业务响应；以响应体 code 判断成功与失败。", @OA\JsonContent(ref="#/components/schemas/AdminResult")),
     *     @OA\Response(response=401, ref="#/components/responses/AdminError"),
     *     @OA\Response(response=403, ref="#/components/responses/AdminError"),
     *     @OA\Response(response=404, ref="#/components/responses/AdminError"),
     *     @OA\Response(response="default", ref="#/components/responses/AdminError")
     * )
     */
    #[Post('/site-models/save')]
    public function modelSave(): Response { return $this->action(fn()=>Db::transaction(function():bool {
        $site=Input::id($this->input()['site_id']??'');(new SiteService())->lock($site);$this->siteAny(['cms:model:status','cms:model:template']);$input=$this->input();$id=Input::id($input['model_id']??'');
        if(!ContentModel::query()->where('id',$id)->exists())throw new \InvalidArgumentException('模型不存在',404);
        $old=SiteModel::query()->where('site_id',$site)->where('model_id',$id)->first();$auth=new SiteAuthorization();
        $status=(int)($input['status']??$old->status??0);$template=Input::template($input['default_template']??$old->default_template??'detail');
        if($status!==(int)($old->status??0))$auth->assert($site,$this->actor(),'cms:model:status');
        if($template!==($old->default_template??'detail'))$auth->assert($site,$this->actor(),'cms:model:template');
        // 只更新未删除的绑定；没有时新增一行（已删除的绑定保持删除，不被复活）。
        if($old)SiteModel::query()->whereKey($old->getKey())->update(['status'=>$status,'default_template'=>$template]);else SiteModel::query()->insert(['site_id'=>$site,'model_id'=>$id,'status'=>$status,'default_template'=>$template]);return true;
    })); }
}
