<?php
declare(strict_types=1);
namespace app\admin\plugins\cms\controller;

use OpenApi\Annotations as OA;

use support\annotation\route\{Get,Post,RouteGroup};
use support\{Response,Db};
use app\cms\service\Input;
use app\cms\service\ContentService;
use app\cms\service\SiteService;
use app\cms\service\Renderer;
use app\cms\model\Content as ContentEntity;
use app\cms\model\ContentLocale;
use app\cms\model\ContentRevision;
use app\cms\model\Domain;
use app\cms\model\SiteModel;
use app\common\model\Attach;
#[RouteGroup('/admin/plugins/cms/content')]
final class Content extends Base
{
    /**
     * 获取本站内容列表。
     *
     * @OA\Get(
     *     path="/admin/plugins/cms/content",
     *     operationId="admin_plugins_cms_controller_Content_index_get",
     *     tags={"CMS 内容"},
     *     summary="获取本站内容列表。",
     *     description="普通后台业务协议：成功 code=200；HTTP 200 也可能表示 code=400/422 的业务失败，请同时检查响应体 code。 先校验站点访问与本站操作权限（403），他站记录返回 404；写操作成功后刷新该站点的公开页面缓存。",
     *     security={{"AdminSession"={}}},
     *     @OA\Parameter(name="site_id", in="query", required=true, description="站点 ID。", @OA\Schema(type="string", pattern="^[1-9][0-9]{0,19}$")),
     *     @OA\Parameter(name="locale", in="query", required=false, description="内容语言代码，默认 zh-cn。", @OA\Schema(type="string")),
     *     @OA\Parameter(name="q", in="query", required=false, description="标题关键字。", @OA\Schema(type="string")),
     *     @OA\Parameter(name="category_id", in="query", required=false, description="栏目 ID。", @OA\Schema(type="string", pattern="^[1-9][0-9]{0,19}$")),
     *     @OA\Parameter(name="model", in="query", required=false, description="模型代码。", @OA\Schema(type="string")),
     *     @OA\Parameter(name="order", in="query", required=false, description="排序方式。", @OA\Schema(type="string", enum={"published_desc", "published_asc", "title_asc", "title_desc", "id_asc", "id_desc"}, default="published_desc")),
     *     @OA\Parameter(name="page", in="query", required=false, description="页码，从 1 开始。", @OA\Schema(type="integer", minimum=1)),
     *     @OA\Parameter(name="limit", in="query", required=false, description="每页条数。", @OA\Schema(type="integer", minimum=1)),
     *     @OA\Parameter(name="offset", in="query", required=false, description="偏移量（0 至 10000），不能与 page>1 同时使用。", @OA\Schema(type="integer", minimum=0, maximum=10000)),
     *     @OA\Response(response=200, description="业务响应；以响应体 code 判断成功与失败。", @OA\JsonContent(ref="#/components/schemas/AdminResult")),
     *     @OA\Response(response=401, ref="#/components/responses/AdminError"),
     *     @OA\Response(response=403, ref="#/components/responses/AdminError"),
     *     @OA\Response(response="default", ref="#/components/responses/AdminError")
     * )
     */
    #[Get('')]
    public function index(): Response { return $this->action(fn()=>(new ContentService())->listing($this->site('cms:content:list'),$this->locale(),$this->input(),false)); }
    /**
     * 获取内容编辑所需的模型、栏目、模板与表单选项。
     *
     * @OA\Get(
     *     path="/admin/plugins/cms/content/lookups",
     *     operationId="admin_plugins_cms_controller_Content_lookups_get",
     *     tags={"CMS 内容"},
     *     summary="获取内容编辑所需的模型、栏目、模板与表单选项。",
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
    #[Get('/lookups')]
    public function lookups(): Response { return $this->action(function(): array {
        $site=$this->site('cms:content:list');$locale=$this->locale();
        $enabled=array_map('strval',SiteModel::query()->where('site_id',$site)->where('status',1)->pluck('model_id')->all());
        $models=array_values(array_filter((new \app\cms\service\ModelService())->listing(),static fn($model)=>in_array($model['id'],$enabled,true)&&(int)$model['status']===1));
        return ['models'=>$models,'categories'=>(new \app\cms\service\CategoryService())->listing($site,$locale,false),'templates'=>(new \app\cms\service\TemplateCatalog())->details($site),'forms'=>(new \app\cms\service\FormService())->options($site)];
    }); }
    /**
     * 获取内容在指定语言下的编辑数据；尚无译文时返回空修订。
     *
     * @OA\Get(
     *     path="/admin/plugins/cms/content/translation",
     *     operationId="admin_plugins_cms_controller_Content_translation_get",
     *     tags={"CMS 内容"},
     *     summary="获取内容在指定语言下的编辑数据；尚无译文时返回空修订。",
     *     description="普通后台业务协议：成功 code=200；HTTP 200 也可能表示 code=400/422 的业务失败，请同时检查响应体 code。 先校验站点访问与本站操作权限（403），他站记录返回 404；写操作成功后刷新该站点的公开页面缓存。",
     *     security={{"AdminSession"={}}},
     *     @OA\Parameter(name="site_id", in="query", required=true, description="站点 ID。", @OA\Schema(type="string", pattern="^[1-9][0-9]{0,19}$")),
     *     @OA\Parameter(name="locale", in="query", required=false, description="内容语言代码，默认 zh-cn。", @OA\Schema(type="string")),
     *     @OA\Parameter(name="content_id", in="query", required=true, description="内容 ID。", @OA\Schema(type="string", pattern="^[1-9][0-9]{0,19}$")),
     *     @OA\Response(response=200, description="业务响应；以响应体 code 判断成功与失败。", @OA\JsonContent(ref="#/components/schemas/AdminResult")),
     *     @OA\Response(response=401, ref="#/components/responses/AdminError"),
     *     @OA\Response(response=403, ref="#/components/responses/AdminError"),
     *     @OA\Response(response=404, ref="#/components/responses/AdminError"),
     *     @OA\Response(response="default", ref="#/components/responses/AdminError")
     * )
     */
    #[Get('/translation')]
    public function translation(): Response { return $this->action(function(): array {
        $site=$this->site('cms:content:list');$locale=$this->locale();(new SiteService())->language($site,$locale);
        $content=Input::row(ContentEntity::query()->where('site_id',$site)->where('id',Input::id($this->input()['content_id']??''))->first())??throw new \InvalidArgumentException('内容不存在',404);
        $id=ContentLocale::query()->where('site_id',$site)->where('content_id',$content['id'])->where('locale',$locale)->value('id');
        return $id?(new ContentService())->editor($site,(string)$id):['content_id'=>$content['id'],'model_id'=>$content['model_id'],'category_id'=>$content['category_id'],'revision'=>null];
    }); }
    /**
     * 搜索可插入内容的附件（最多 100 条），并返回已选附件。
     *
     * @OA\Get(
     *     path="/admin/plugins/cms/content/attachments",
     *     operationId="admin_plugins_cms_controller_Content_attachments_get",
     *     tags={"CMS 内容"},
     *     summary="搜索可插入内容的附件（最多 100 条），并返回已选附件。",
     *     description="普通后台业务协议：成功 code=200；HTTP 200 也可能表示 code=400/422 的业务失败，请同时检查响应体 code。 先校验站点访问与本站操作权限（403），他站记录返回 404；写操作成功后刷新该站点的公开页面缓存。 需要本站内容新增或编辑权限之一。",
     *     security={{"AdminSession"={}}},
     *     @OA\Parameter(name="site_id", in="query", required=true, description="站点 ID。", @OA\Schema(type="string", pattern="^[1-9][0-9]{0,19}$")),
     *     @OA\Parameter(name="ids[]", in="query", required=false, description="已选附件 ID，最多 100 个。", @OA\Schema(type="array", @OA\Items(type="string"))),
     *     @OA\Parameter(name="q", in="query", required=false, description="原始文件名关键字。", @OA\Schema(type="string")),
     *     @OA\Response(response=200, description="业务响应；以响应体 code 判断成功与失败。", @OA\JsonContent(ref="#/components/schemas/AdminResult")),
     *     @OA\Response(response=401, ref="#/components/responses/AdminError"),
     *     @OA\Response(response=403, ref="#/components/responses/AdminError"),
     *     @OA\Response(response="default", ref="#/components/responses/AdminError")
     * )
     */
    #[Get('/attachments')]
    public function attachments(): Response { return $this->action(function(): array {
        $site=$this->siteAny(['cms:content:create','cms:content:edit']);if(!Db::schema()->hasTable('attach'))return [];
        $query=Attach::query()->withoutGlobalScopes()->where('status',1)->whereNull('deleted_at');
        $ids=$this->input()['ids']??[];if(!is_array($ids)||count($ids)>100)throw new \InvalidArgumentException('附件列表无效');
        $ids=array_map(Input::id(...),$ids);$columns=['id','original_name','path','url','ext'];
        $selected=$ids?Input::rows((clone $query)->whereIn('id',$ids)->select($columns)->get()):[];
        if(!empty($this->input()['q']))$query->where('original_name','like','%'.Input::text($this->input()['q'],100).'%');
        $files=Input::rows($query->select($columns)->orderByDesc('id')->limit(100)->get());
        $result=[];foreach([...$selected,...$files] as $file)$result[$file['id']]=$file;return array_values($result);
    }); }
    /**
     * 上传内容附件，沿用后台通用上传。
     *
     * @OA\Post(
     *     path="/admin/plugins/cms/content/upload",
     *     operationId="admin_plugins_cms_controller_Content_upload_post",
     *     tags={"CMS 内容"},
     *     summary="上传内容附件，沿用后台通用上传。",
     *     description="普通后台业务协议：成功 code=200；HTTP 200 也可能表示 code=400/422 的业务失败，请同时检查响应体 code。 先校验站点访问与本站操作权限（403），他站记录返回 404；写操作成功后刷新该站点的公开页面缓存。 需要本站内容新增或编辑权限之一；文件类型与大小限制同 /admin/upload。",
     *     security={{"AdminSession"={}, "CsrfToken"={}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\MediaType(
     *             mediaType="multipart/form-data",
     *             @OA\Schema(
     *                 type="object",
     *                 required={"site_id", "file"},
     *                 @OA\Property(property="site_id", type="string", pattern="^[1-9][0-9]{0,19}$", description="站点 ID。"),
     *                 @OA\Property(property="file", type="string", format="binary", description="上传文件。")
     *             )
     *         )
     *     ),
     *     @OA\Response(response=200, description="业务响应；以响应体 code 判断成功与失败。", @OA\JsonContent(ref="#/components/schemas/AdminResult")),
     *     @OA\Response(response=401, ref="#/components/responses/AdminError"),
     *     @OA\Response(response=403, ref="#/components/responses/AdminError"),
     *     @OA\Response(response="default", ref="#/components/responses/AdminError")
     * )
     */
    #[Post('/upload')]
    public function upload(): Response { return $this->action(function(): array {
        $this->siteAny(['cms:content:create','cms:content:edit']);
        $response=(new \app\admin\system\controller\AdminUpload())->upload();
        $result=json_decode($response->rawBody(),true,512,JSON_THROW_ON_ERROR);
        if(($result['code']??500)!==200)throw new \InvalidArgumentException($result['msg']??'上传失败',(int)($result['code']??500));
        return $result['data'];
    }); }
    /**
     * 获取内容语言版本的编辑详情。
     *
     * @OA\Get(
     *     path="/admin/plugins/cms/content/detail",
     *     operationId="admin_plugins_cms_controller_Content_detail_get",
     *     tags={"CMS 内容"},
     *     summary="获取内容语言版本的编辑详情。",
     *     description="普通后台业务协议：成功 code=200；HTTP 200 也可能表示 code=400/422 的业务失败，请同时检查响应体 code。 先校验站点访问与本站操作权限（403），他站记录返回 404；写操作成功后刷新该站点的公开页面缓存。",
     *     security={{"AdminSession"={}}},
     *     @OA\Parameter(name="site_id", in="query", required=true, description="站点 ID。", @OA\Schema(type="string", pattern="^[1-9][0-9]{0,19}$")),
     *     @OA\Parameter(name="id", in="query", required=true, description="内容语言版本 ID。", @OA\Schema(type="string", pattern="^[1-9][0-9]{0,19}$")),
     *     @OA\Response(response=200, description="业务响应；以响应体 code 判断成功与失败。", @OA\JsonContent(ref="#/components/schemas/AdminResult")),
     *     @OA\Response(response=401, ref="#/components/responses/AdminError"),
     *     @OA\Response(response=403, ref="#/components/responses/AdminError"),
     *     @OA\Response(response=404, ref="#/components/responses/AdminError"),
     *     @OA\Response(response="default", ref="#/components/responses/AdminError")
     * )
     */
    #[Get('/detail')]
    public function detail(): Response { return $this->action(fn()=>(new ContentService())->editor($this->site('cms:content:list'),Input::id($this->input()['id']??''))); }
    /**
     * 获取内容语言版本的修订列表。
     *
     * @OA\Get(
     *     path="/admin/plugins/cms/content/revisions",
     *     operationId="admin_plugins_cms_controller_Content_revisions_get",
     *     tags={"CMS 内容"},
     *     summary="获取内容语言版本的修订列表。",
     *     description="普通后台业务协议：成功 code=200；HTTP 200 也可能表示 code=400/422 的业务失败，请同时检查响应体 code。 先校验站点访问与本站操作权限（403），他站记录返回 404；写操作成功后刷新该站点的公开页面缓存。",
     *     security={{"AdminSession"={}}},
     *     @OA\Parameter(name="site_id", in="query", required=true, description="站点 ID。", @OA\Schema(type="string", pattern="^[1-9][0-9]{0,19}$")),
     *     @OA\Parameter(name="id", in="query", required=true, description="内容语言版本 ID。", @OA\Schema(type="string", pattern="^[1-9][0-9]{0,19}$")),
     *     @OA\Response(response=200, description="业务响应；以响应体 code 判断成功与失败。", @OA\JsonContent(ref="#/components/schemas/AdminResult")),
     *     @OA\Response(response=401, ref="#/components/responses/AdminError"),
     *     @OA\Response(response=403, ref="#/components/responses/AdminError"),
     *     @OA\Response(response=404, ref="#/components/responses/AdminError"),
     *     @OA\Response(response="default", ref="#/components/responses/AdminError")
     * )
     */
    #[Get('/revisions')]
    public function revisions(): Response { return $this->action(fn()=>(new ContentService())->revisions($this->site('cms:content:list'),Input::id($this->input()['id']??''))); }
    /**
     * 新增或保存内容草稿；带 content_id 时需编辑权限，否则需新增权限。
     *
     * @OA\Post(
     *     path="/admin/plugins/cms/content/save",
     *     operationId="admin_plugins_cms_controller_Content_save_post",
     *     tags={"CMS 内容"},
     *     summary="新增或保存内容草稿；带 content_id 时需编辑权限，否则需新增权限。",
     *     description="普通后台业务协议：成功 code=200；HTTP 200 也可能表示 code=400/422 的业务失败，请同时检查响应体 code。 先校验站点访问与本站操作权限（403），他站记录返回 404；写操作成功后刷新该站点的公开页面缓存。",
     *     security={{"AdminSession"={}, "CsrfToken"={}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             type="object",
     *             required={"site_id"},
     *             description="内容字段由 ContentService::save 校验。",
     *             additionalProperties=true,
     *             @OA\Property(property="site_id", type="string", pattern="^[1-9][0-9]{0,19}$", description="站点 ID。"),
     *             @OA\Property(property="locale", type="string", description="内容语言代码，默认 zh-cn。"),
     *             @OA\Property(property="content_id", type="string", pattern="^[1-9][0-9]{0,19}$", description="内容 ID；省略时新增。")
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
    public function save(): Response { return $this->action(fn()=>(new ContentService())->save($this->site(empty($this->input()['content_id'])?'cms:content:create':'cms:content:edit'),$this->locale(),$this->input(),$this->actor())); }
    /**
     * 发布内容的指定修订。
     *
     * @OA\Post(
     *     path="/admin/plugins/cms/content/publish",
     *     operationId="admin_plugins_cms_controller_Content_publish_post",
     *     tags={"CMS 内容"},
     *     summary="发布内容的指定修订。",
     *     description="普通后台业务协议：成功 code=200；HTTP 200 也可能表示 code=400/422 的业务失败，请同时检查响应体 code。 先校验站点访问与本站操作权限（403），他站记录返回 404；写操作成功后刷新该站点的公开页面缓存。",
     *     security={{"AdminSession"={}, "CsrfToken"={}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             type="object",
     *             required={"site_id", "id", "revision_id", "lock_version"},
     *             @OA\Property(property="site_id", type="string", pattern="^[1-9][0-9]{0,19}$", description="站点 ID。"),
     *             @OA\Property(property="id", type="string", pattern="^[1-9][0-9]{0,19}$", description="内容语言版本 ID。"),
     *             @OA\Property(property="revision_id", type="string", pattern="^[1-9][0-9]{0,19}$", description="修订 ID。"),
     *             @OA\Property(property="lock_version", type="integer", description="乐观锁版本。")
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
    public function publish(): Response { return $this->action(fn()=>(new ContentService())->publish($this->site('cms:content:publish'),Input::id($this->input()['id']??''),Input::id($this->input()['revision_id']??''),(int)($this->input()['lock_version']??-1))); }
    /**
     * 下线已发布内容。
     *
     * @OA\Post(
     *     path="/admin/plugins/cms/content/offline",
     *     operationId="admin_plugins_cms_controller_Content_offline_post",
     *     tags={"CMS 内容"},
     *     summary="下线已发布内容。",
     *     description="普通后台业务协议：成功 code=200；HTTP 200 也可能表示 code=400/422 的业务失败，请同时检查响应体 code。 先校验站点访问与本站操作权限（403），他站记录返回 404；写操作成功后刷新该站点的公开页面缓存。",
     *     security={{"AdminSession"={}, "CsrfToken"={}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             type="object",
     *             required={"site_id", "id", "lock_version"},
     *             @OA\Property(property="site_id", type="string", pattern="^[1-9][0-9]{0,19}$", description="站点 ID。"),
     *             @OA\Property(property="id", type="string", pattern="^[1-9][0-9]{0,19}$", description="内容语言版本 ID。"),
     *             @OA\Property(property="lock_version", type="integer", description="乐观锁版本。")
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
    #[Post('/offline')]
    public function offline(): Response { return $this->action(function(): bool {(new ContentService())->offline($this->site('cms:content:offline'),Input::id($this->input()['id']??''),(int)($this->input()['lock_version']??-1));return true;}); }
    /**
     * 渲染内容修订的预览 HTML（data.html，noindex）。
     *
     * @OA\Get(
     *     path="/admin/plugins/cms/content/preview",
     *     operationId="admin_plugins_cms_controller_Content_preview_get",
     *     tags={"CMS 内容"},
     *     summary="渲染内容修订的预览 HTML（data.html，noindex）。",
     *     description="普通后台业务协议：成功 code=200；HTTP 200 也可能表示 code=400/422 的业务失败，请同时检查响应体 code。 先校验站点访问与本站操作权限（403），他站记录返回 404；写操作成功后刷新该站点的公开页面缓存。",
     *     security={{"AdminSession"={}}},
     *     @OA\Parameter(name="site_id", in="query", required=true, description="站点 ID。", @OA\Schema(type="string", pattern="^[1-9][0-9]{0,19}$")),
     *     @OA\Parameter(name="id", in="query", required=true, description="内容语言版本 ID。", @OA\Schema(type="string", pattern="^[1-9][0-9]{0,19}$")),
     *     @OA\Parameter(name="revision_id", in="query", required=false, description="修订 ID，默认草稿修订。", @OA\Schema(type="string", pattern="^[1-9][0-9]{0,19}$")),
     *     @OA\Response(response=200, description="业务响应；以响应体 code 判断成功与失败。", @OA\JsonContent(ref="#/components/schemas/AdminResult")),
     *     @OA\Response(response=401, ref="#/components/responses/AdminError"),
     *     @OA\Response(response=403, ref="#/components/responses/AdminError"),
     *     @OA\Response(response=404, ref="#/components/responses/AdminError"),
     *     @OA\Response(response="default", ref="#/components/responses/AdminError")
     * )
     */
    #[Get('/preview')]
    public function preview(): Response { return $this->action(function(): array {
        $siteId=$this->site('cms:content:preview');$editor=(new ContentService())->editor($siteId,Input::id($this->input()['id']??''));$revisionId=Input::id($this->input()['revision_id']??$editor['draft_revision_id']);
        $revision=Input::row(ContentRevision::query()->where('site_id',$siteId)->where('content_locale_id',$editor['id'])->where('id',$revisionId)->first())??throw new \InvalidArgumentException('修订不存在');
        $sites=new SiteService();$site=$sites->get($siteId);$primary=Domain::query()->where('site_id',$siteId)->where('id',$site['primary_domain_id'])->first();if(!$primary)throw new \InvalidArgumentException('请配置主域名');$site['primary_host']=$primary->host;
        $language=$sites->language($siteId,$editor['locale']);$page=['target_type'=>'preview','path'=>$revision['desired_path'],'title'=>$revision['title'],'robots'=>'noindex,nofollow','page'=>1];
        return ['html'=>(new Renderer())->render($site,$language,$page,$revision['template'],$revision+['content_id'=>$editor['content_id'],'published_at'=>'预览'])];
    }); }
}
