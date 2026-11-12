<?php
declare(strict_types=1);
namespace app\admin\plugins\cms\controller;

use OpenApi\Annotations as OA;

use app\cms\service\FormRecycleBin;
use app\cms\service\FormService;
use app\cms\service\FormSubmissionService;
use app\cms\service\Input;
use app\cms\service\SiteService;
use support\annotation\route\{Get,Post,RouteGroup};
use support\Response;
/**
 * 自定义表单后台接口：表单配置与提交跟进。每个接口先校验本站操作权限（403），再按站点查询，他站 ID 一律 404。
 * 附件下载与 CSV 导出直接返回文件响应，出错时仍返回统一 JSON。
 */
#[RouteGroup('/admin/plugins/cms/forms')]
final class Forms extends Base
{
    /** 只有这些图片类型在 preview=1 时内联显示，其余一律作为附件下载。 */
    private const PREVIEW_MIMES = ['image/jpeg', 'image/png', 'image/gif', 'image/webp', 'image/bmp'];
    private const FILTERS = ['form_id', 'status', 'from', 'to', 'keyword'];

    /**
     * 获取本站自定义表单列表。
     *
     * @OA\Get(
     *     path="/admin/plugins/cms/forms",
     *     operationId="admin_plugins_cms_controller_Forms_index_get",
     *     tags={"CMS 自定义表单"},
     *     summary="获取本站自定义表单列表。",
     *     description="普通后台业务协议：成功 code=200；HTTP 200 也可能表示 code=400/422 的业务失败，请同时检查响应体 code。 先校验站点访问与本站操作权限（403），他站记录返回 404；写操作成功后刷新该站点的公开页面缓存。",
     *     security={{"AdminSession"={}}},
     *     @OA\Parameter(name="site_id", in="query", required=true, description="站点 ID。", @OA\Schema(type="string", pattern="^[1-9][0-9]{0,19}$")),
     *     @OA\Response(response=200, description="业务响应；以响应体 code 判断成功与失败。", @OA\JsonContent(ref="#/components/schemas/AdminResult")),
     *     @OA\Response(response=401, ref="#/components/responses/AdminError"),
     *     @OA\Response(response=403, ref="#/components/responses/AdminError"),
     *     @OA\Response(response="default", ref="#/components/responses/AdminError")
     * )
     */
    #[Get('')]
    public function index(): Response
    {
        return $this->action(fn(): array => (new FormService())->list($this->site('cms:form:list')));
    }
    /**
     * 获取本站未处理提交数（data.new_count）。
     *
     * @OA\Get(
     *     path="/admin/plugins/cms/forms/summary",
     *     operationId="admin_plugins_cms_controller_Forms_summary_get",
     *     tags={"CMS 自定义表单"},
     *     summary="获取本站未处理提交数（data.new_count）。",
     *     description="普通后台业务协议：成功 code=200；HTTP 200 也可能表示 code=400/422 的业务失败，请同时检查响应体 code。 先校验站点访问与本站操作权限（403），他站记录返回 404；写操作成功后刷新该站点的公开页面缓存。",
     *     security={{"AdminSession"={}}},
     *     @OA\Parameter(name="site_id", in="query", required=true, description="站点 ID。", @OA\Schema(type="string", pattern="^[1-9][0-9]{0,19}$")),
     *     @OA\Response(response=200, description="业务响应；以响应体 code 判断成功与失败。", @OA\JsonContent(ref="#/components/schemas/AdminResult")),
     *     @OA\Response(response=401, ref="#/components/responses/AdminError"),
     *     @OA\Response(response=403, ref="#/components/responses/AdminError"),
     *     @OA\Response(response="default", ref="#/components/responses/AdminError")
     * )
     */
    #[Get('/summary')]
    public function summary(): Response
    {
        return $this->action(fn(): array => ['new_count' => (new FormService())->newCount($this->site('cms:form:list'))]);
    }
    /**
     * 新增或保存自定义表单。
     *
     * @OA\Post(
     *     path="/admin/plugins/cms/forms/save",
     *     operationId="admin_plugins_cms_controller_Forms_save_post",
     *     tags={"CMS 自定义表单"},
     *     summary="新增或保存自定义表单。",
     *     description="普通后台业务协议：成功 code=200；HTTP 200 也可能表示 code=400/422 的业务失败，请同时检查响应体 code。 先校验站点访问与本站操作权限（403），他站记录返回 404；写操作成功后刷新该站点的公开页面缓存。",
     *     security={{"AdminSession"={}, "CsrfToken"={}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             type="object",
     *             required={"site_id"},
     *             description="表单字段由 FormService::save 校验。",
     *             additionalProperties=true,
     *             @OA\Property(property="site_id", type="string", pattern="^[1-9][0-9]{0,19}$", description="站点 ID。"),
     *             @OA\Property(property="id", type="string", pattern="^[1-9][0-9]{0,19}$", description="表单 ID；省略时新增。"),
     *             @OA\Property(property="enabled", type="boolean", description="是否启用。")
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
    public function save(): Response
    {
        return $this->action(function (): array {
            $site = $this->site('cms:form:manage');
            $input = $this->input();
            if (isset($input['enabled'])) $input['enabled'] = self::bool($input['enabled']);
            return (new FormService())->save($site, $input);
        });
    }
    /**
     * 启用或停用自定义表单。
     *
     * @OA\Post(
     *     path="/admin/plugins/cms/forms/status",
     *     operationId="admin_plugins_cms_controller_Forms_status_post",
     *     tags={"CMS 自定义表单"},
     *     summary="启用或停用自定义表单。",
     *     description="普通后台业务协议：成功 code=200；HTTP 200 也可能表示 code=400/422 的业务失败，请同时检查响应体 code。 先校验站点访问与本站操作权限（403），他站记录返回 404；写操作成功后刷新该站点的公开页面缓存。",
     *     security={{"AdminSession"={}, "CsrfToken"={}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             type="object",
     *             required={"site_id", "id", "enabled", "lock_version"},
     *             @OA\Property(property="site_id", type="string", pattern="^[1-9][0-9]{0,19}$", description="站点 ID。"),
     *             @OA\Property(property="id", type="string", pattern="^[1-9][0-9]{0,19}$", description="表单 ID。"),
     *             @OA\Property(property="enabled", type="boolean", description="是否启用。"),
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
    #[Post('/status')]
    public function status(): Response
    {
        return $this->action(function (): array {
            $site = $this->site('cms:form:manage');
            $input = $this->input();
            return (new FormService())->setStatus($site, Input::id($input['id'] ?? ''), self::bool($input['enabled'] ?? null), self::version($input));
        });
    }
    /**
     * 删除自定义表单（进入表单回收站，其提交一并删除）。
     *
     * @OA\Post(
     *     path="/admin/plugins/cms/forms/delete",
     *     operationId="admin_plugins_cms_controller_Forms_delete_post",
     *     tags={"CMS 自定义表单"},
     *     summary="删除自定义表单（进入表单回收站，其提交一并删除）。",
     *     description="普通后台业务协议：成功 code=200；HTTP 200 也可能表示 code=400/422 的业务失败，请同时检查响应体 code。 先校验站点访问与本站操作权限（403），他站记录返回 404；写操作成功后刷新该站点的公开页面缓存。",
     *     security={{"AdminSession"={}, "CsrfToken"={}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             type="object",
     *             required={"site_id", "id"},
     *             @OA\Property(property="site_id", type="string", pattern="^[1-9][0-9]{0,19}$", description="站点 ID。"),
     *             @OA\Property(property="id", type="string", pattern="^[1-9][0-9]{0,19}$", description="表单 ID。")
     *         )
     *     ),
     *     @OA\Response(response=200, description="业务响应；以响应体 code 判断成功与失败。", @OA\JsonContent(ref="#/components/schemas/AdminResult")),
     *     @OA\Response(response=401, ref="#/components/responses/AdminError"),
     *     @OA\Response(response=403, ref="#/components/responses/AdminError"),
     *     @OA\Response(response=404, ref="#/components/responses/AdminError"),
     *     @OA\Response(response="default", ref="#/components/responses/AdminError")
     * )
     */
    #[Post('/delete')]
    public function delete(): Response
    {
        return $this->action(function (): void {
            $site = $this->site('cms:form:manage');
            (new FormService())->delete($site, Input::id($this->input()['id'] ?? ''), $this->actor());
        });
    }
    /**
     * 获取表单提交列表。
     *
     * @OA\Get(
     *     path="/admin/plugins/cms/forms/submissions",
     *     operationId="admin_plugins_cms_controller_Forms_submissions_get",
     *     tags={"CMS 自定义表单"},
     *     summary="获取表单提交列表。",
     *     description="普通后台业务协议：成功 code=200；HTTP 200 也可能表示 code=400/422 的业务失败，请同时检查响应体 code。 先校验站点访问与本站操作权限（403），他站记录返回 404；写操作成功后刷新该站点的公开页面缓存。",
     *     security={{"AdminSession"={}}},
     *     @OA\Parameter(name="site_id", in="query", required=true, description="站点 ID。", @OA\Schema(type="string", pattern="^[1-9][0-9]{0,19}$")),
     *     @OA\Parameter(name="form_id", in="query", required=false, description="表单 ID。", @OA\Schema(type="string", pattern="^[1-9][0-9]{0,19}$")),
     *     @OA\Parameter(name="status", in="query", required=false, description="处理状态。", @OA\Schema(type="string")),
     *     @OA\Parameter(name="from", in="query", required=false, description="提交时间起（日期）。", @OA\Schema(type="string")),
     *     @OA\Parameter(name="to", in="query", required=false, description="提交时间止（日期）。", @OA\Schema(type="string")),
     *     @OA\Parameter(name="keyword", in="query", required=false, description="关键字。", @OA\Schema(type="string")),
     *     @OA\Parameter(name="page", in="query", required=false, description="页码，从 1 开始。", @OA\Schema(type="integer", minimum=1)),
     *     @OA\Response(response=200, description="业务响应；以响应体 code 判断成功与失败。", @OA\JsonContent(ref="#/components/schemas/AdminResult")),
     *     @OA\Response(response=401, ref="#/components/responses/AdminError"),
     *     @OA\Response(response=403, ref="#/components/responses/AdminError"),
     *     @OA\Response(response="default", ref="#/components/responses/AdminError")
     * )
     */
    #[Get('/submissions')]
    public function submissions(): Response
    {
        return $this->action(function (): array {
            $site = $this->site('cms:form:submissions');
            return (new FormSubmissionService())->list($site, $this->filters(), (int)($this->input()['page'] ?? 1));
        });
    }
    /**
     * 获取表单提交详情。
     *
     * @OA\Get(
     *     path="/admin/plugins/cms/forms/submissions/detail",
     *     operationId="admin_plugins_cms_controller_Forms_detail_get",
     *     tags={"CMS 自定义表单"},
     *     summary="获取表单提交详情。",
     *     description="普通后台业务协议：成功 code=200；HTTP 200 也可能表示 code=400/422 的业务失败，请同时检查响应体 code。 先校验站点访问与本站操作权限（403），他站记录返回 404；写操作成功后刷新该站点的公开页面缓存。",
     *     security={{"AdminSession"={}}},
     *     @OA\Parameter(name="site_id", in="query", required=true, description="站点 ID。", @OA\Schema(type="string", pattern="^[1-9][0-9]{0,19}$")),
     *     @OA\Parameter(name="id", in="query", required=true, description="提交 ID。", @OA\Schema(type="string", pattern="^[1-9][0-9]{0,19}$")),
     *     @OA\Response(response=200, description="业务响应；以响应体 code 判断成功与失败。", @OA\JsonContent(ref="#/components/schemas/AdminResult")),
     *     @OA\Response(response=401, ref="#/components/responses/AdminError"),
     *     @OA\Response(response=403, ref="#/components/responses/AdminError"),
     *     @OA\Response(response=404, ref="#/components/responses/AdminError"),
     *     @OA\Response(response="default", ref="#/components/responses/AdminError")
     * )
     */
    #[Get('/submissions/detail')]
    public function detail(): Response
    {
        return $this->action(function (): array {
            $site = $this->site('cms:form:submissions');
            return (new FormSubmissionService())->detail($site, Input::id($this->input()['id'] ?? ''));
        });
    }
    /**
     * 下载提交附件；preview=1 且为图片时内联显示。
     *
     * @OA\Get(
     *     path="/admin/plugins/cms/forms/files/download",
     *     operationId="admin_plugins_cms_controller_Forms_file_get",
     *     tags={"CMS 自定义表单"},
     *     summary="下载提交附件；preview=1 且为图片时内联显示。",
     *     description="普通后台业务协议：成功 code=200；HTTP 200 也可能表示 code=400/422 的业务失败，请同时检查响应体 code。 先校验站点访问与本站操作权限（403），他站记录返回 404；写操作成功后刷新该站点的公开页面缓存。",
     *     security={{"AdminSession"={}}},
     *     @OA\Parameter(name="site_id", in="query", required=true, description="站点 ID。", @OA\Schema(type="string", pattern="^[1-9][0-9]{0,19}$")),
     *     @OA\Parameter(name="id", in="query", required=true, description="附件 ID。", @OA\Schema(type="string", pattern="^[1-9][0-9]{0,19}$")),
     *     @OA\Parameter(name="preview", in="query", required=false, description="为 1 时图片内联显示。", @OA\Schema(type="string")),
     *     @OA\Response(response=200, description="文件内容；Content-Disposition 给出文件名。出错时返回 JSON 错误信封。", @OA\MediaType(mediaType="application/octet-stream", @OA\Schema(type="string", format="binary"))),
     *     @OA\Response(response=401, ref="#/components/responses/AdminError"),
     *     @OA\Response(response=403, ref="#/components/responses/AdminError"),
     *     @OA\Response(response=404, ref="#/components/responses/AdminError"),
     *     @OA\Response(response="default", ref="#/components/responses/AdminError")
     * )
     */
    #[Get('/files/download')]
    public function file(): Response
    {
        return $this->raw(function (): Response {
            $site = $this->site('cms:form:submissions');
            $input = $this->input();
            $file = (new FormSubmissionService())->file($site, Input::id($input['id'] ?? ''));
            $mime = $file['mime'];
            $inline = ($input['preview'] ?? null) === '1' && in_array($mime, self::PREVIEW_MIMES, true);
            // 文本类型（含被误存为文本的 HTML）一律按二进制下载，浏览器不会渲染。
            if ($mime === '' || str_starts_with(strtolower($mime), 'text/')) $mime = 'application/octet-stream';
            return response('', 200, [
                'Content-Type' => $mime,
                'Content-Disposition' => self::disposition($inline ? 'inline' : 'attachment', $file['name']),
                'X-Content-Type-Options' => 'nosniff',
                'Content-Security-Policy' => "default-src 'none'; sandbox",
                'Cache-Control' => 'private, no-store',
            ])->withFile($file['absolute']);
        });
    }
    /**
     * 为表单提交添加跟进备注。
     *
     * @OA\Post(
     *     path="/admin/plugins/cms/forms/submissions/note",
     *     operationId="admin_plugins_cms_controller_Forms_note_post",
     *     tags={"CMS 自定义表单"},
     *     summary="为表单提交添加跟进备注。",
     *     description="普通后台业务协议：成功 code=200；HTTP 200 也可能表示 code=400/422 的业务失败，请同时检查响应体 code。 先校验站点访问与本站操作权限（403），他站记录返回 404；写操作成功后刷新该站点的公开页面缓存。",
     *     security={{"AdminSession"={}, "CsrfToken"={}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             type="object",
     *             required={"site_id", "id", "body"},
     *             @OA\Property(property="site_id", type="string", pattern="^[1-9][0-9]{0,19}$", description="站点 ID。"),
     *             @OA\Property(property="id", type="string", pattern="^[1-9][0-9]{0,19}$", description="提交 ID。"),
     *             @OA\Property(property="body", type="string", description="备注内容。")
     *         )
     *     ),
     *     @OA\Response(response=200, description="业务响应；以响应体 code 判断成功与失败。", @OA\JsonContent(ref="#/components/schemas/AdminResult")),
     *     @OA\Response(response=401, ref="#/components/responses/AdminError"),
     *     @OA\Response(response=403, ref="#/components/responses/AdminError"),
     *     @OA\Response(response=404, ref="#/components/responses/AdminError"),
     *     @OA\Response(response="default", ref="#/components/responses/AdminError")
     * )
     */
    #[Post('/submissions/note')]
    public function note(): Response
    {
        return $this->action(function (): array {
            $site = $this->site('cms:form:handle');
            $input = $this->input();
            return (new FormSubmissionService())->note($site, Input::id($input['id'] ?? ''), $this->actor(), self::string($input['body'] ?? ''));
        });
    }
    /**
     * 变更表单提交的处理状态。
     *
     * @OA\Post(
     *     path="/admin/plugins/cms/forms/submissions/transition",
     *     operationId="admin_plugins_cms_controller_Forms_transition_post",
     *     tags={"CMS 自定义表单"},
     *     summary="变更表单提交的处理状态。",
     *     description="普通后台业务协议：成功 code=200；HTTP 200 也可能表示 code=400/422 的业务失败，请同时检查响应体 code。 先校验站点访问与本站操作权限（403），他站记录返回 404；写操作成功后刷新该站点的公开页面缓存。",
     *     security={{"AdminSession"={}, "CsrfToken"={}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             type="object",
     *             required={"site_id", "id", "status", "lock_version"},
     *             @OA\Property(property="site_id", type="string", pattern="^[1-9][0-9]{0,19}$", description="站点 ID。"),
     *             @OA\Property(property="id", type="string", pattern="^[1-9][0-9]{0,19}$", description="提交 ID。"),
     *             @OA\Property(property="status", type="string", description="目标状态。"),
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
    #[Post('/submissions/transition')]
    public function transition(): Response
    {
        return $this->action(function (): array {
            $site = $this->site('cms:form:handle');
            $input = $this->input();
            return (new FormSubmissionService())->transition($site, Input::id($input['id'] ?? ''), $this->actor(), self::string($input['status'] ?? ''), self::version($input));
        });
    }
    /**
     * 批量将表单提交标记为无效，返回处理条数。
     *
     * @OA\Post(
     *     path="/admin/plugins/cms/forms/submissions/invalidate",
     *     operationId="admin_plugins_cms_controller_Forms_invalidate_post",
     *     tags={"CMS 自定义表单"},
     *     summary="批量将表单提交标记为无效，返回处理条数。",
     *     description="普通后台业务协议：成功 code=200；HTTP 200 也可能表示 code=400/422 的业务失败，请同时检查响应体 code。 先校验站点访问与本站操作权限（403），他站记录返回 404；写操作成功后刷新该站点的公开页面缓存。",
     *     security={{"AdminSession"={}, "CsrfToken"={}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             type="object",
     *             required={"site_id", "ids"},
     *             @OA\Property(property="site_id", type="string", pattern="^[1-9][0-9]{0,19}$", description="站点 ID。"),
     *             @OA\Property(property="ids", description="要处理的记录 ID 列表。", type="array", @OA\Items(type="string"))
     *         )
     *     ),
     *     @OA\Response(response=200, description="业务响应；以响应体 code 判断成功与失败。", @OA\JsonContent(ref="#/components/schemas/AdminResult")),
     *     @OA\Response(response=401, ref="#/components/responses/AdminError"),
     *     @OA\Response(response=403, ref="#/components/responses/AdminError"),
     *     @OA\Response(response="default", ref="#/components/responses/AdminError")
     * )
     */
    #[Post('/submissions/invalidate')]
    public function invalidate(): Response
    {
        return $this->action(function (): array {
            $site = $this->site('cms:form:handle');
            return ['count' => (new FormSubmissionService())->invalidate($site, $this->batchIds(), $this->actor())];
        });
    }
    /**
     * 按当前筛选导出 CSV：UTF-8 BOM + 逐行 fputcsv。先在临时流中生成完整内容，再一次性返回。
     *
     * @OA\Get(
     *     path="/admin/plugins/cms/forms/submissions/export",
     *     operationId="admin_plugins_cms_controller_Forms_export_get",
     *     tags={"CMS 自定义表单"},
     *     summary="按当前筛选导出表单提交 CSV。",
     *     description="普通后台业务协议：成功 code=200；HTTP 200 也可能表示 code=400/422 的业务失败，请同时检查响应体 code。 先校验站点访问与本站操作权限（403），他站记录返回 404；写操作成功后刷新该站点的公开页面缓存。",
     *     security={{"AdminSession"={}}},
     *     @OA\Parameter(name="site_id", in="query", required=true, description="站点 ID。", @OA\Schema(type="string", pattern="^[1-9][0-9]{0,19}$")),
     *     @OA\Parameter(name="locale", in="query", required=false, description="内容语言代码，默认 zh-cn。", @OA\Schema(type="string")),
     *     @OA\Parameter(name="form_id", in="query", required=false, description="表单 ID。", @OA\Schema(type="string", pattern="^[1-9][0-9]{0,19}$")),
     *     @OA\Parameter(name="status", in="query", required=false, description="处理状态。", @OA\Schema(type="string")),
     *     @OA\Parameter(name="from", in="query", required=false, description="提交时间起。", @OA\Schema(type="string")),
     *     @OA\Parameter(name="to", in="query", required=false, description="提交时间止。", @OA\Schema(type="string")),
     *     @OA\Parameter(name="keyword", in="query", required=false, description="关键字。", @OA\Schema(type="string")),
     *     @OA\Response(response=200, description="CSV 文件（UTF-8 BOM）。出错时返回 JSON 错误信封。", @OA\MediaType(mediaType="text/csv", @OA\Schema(type="string"))),
     *     @OA\Response(response=401, ref="#/components/responses/AdminError"),
     *     @OA\Response(response=403, ref="#/components/responses/AdminError"),
     *     @OA\Response(response="default", ref="#/components/responses/AdminError")
     * )
     */
    #[Get('/submissions/export')]
    public function export(): Response
    {
        return $this->raw(function (): Response {
            $site = $this->site('cms:form:export');
            $rows = (new FormSubmissionService())->export($site, $this->filters(), $this->locale());
            $code = (new SiteService())->get($site)['code'];
            $stream = fopen('php://temp/maxmemory:' . (8 * 1024 * 1024), 'w+b');
            try {
                fwrite($stream, "\xEF\xBB\xBF");
                foreach ($rows as $row) fputcsv($stream, $row, ',', '"', '');
                rewind($stream);
                $csv = (string)stream_get_contents($stream);
            } finally {
                fclose($stream);
            }
            return response($csv, 200, [
                'Content-Type' => 'text/csv; charset=UTF-8',
                'Content-Disposition' => 'attachment; filename="forms-' . $code . '-' . date('Ymd') . '.csv"',
                'X-Content-Type-Options' => 'nosniff',
                'Cache-Control' => 'private, no-store',
            ]);
        });
    }
    /**
     * 批量删除表单提交（进入提交回收站），返回处理条数。
     *
     * @OA\Post(
     *     path="/admin/plugins/cms/forms/submissions/remove",
     *     operationId="admin_plugins_cms_controller_Forms_remove_post",
     *     tags={"CMS 自定义表单"},
     *     summary="批量删除表单提交（进入提交回收站），返回处理条数。",
     *     description="普通后台业务协议：成功 code=200；HTTP 200 也可能表示 code=400/422 的业务失败，请同时检查响应体 code。 先校验站点访问与本站操作权限（403），他站记录返回 404；写操作成功后刷新该站点的公开页面缓存。",
     *     security={{"AdminSession"={}, "CsrfToken"={}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             type="object",
     *             required={"site_id", "ids"},
     *             @OA\Property(property="site_id", type="string", pattern="^[1-9][0-9]{0,19}$", description="站点 ID。"),
     *             @OA\Property(property="ids", description="要处理的记录 ID 列表。", type="array", @OA\Items(type="string"))
     *         )
     *     ),
     *     @OA\Response(response=200, description="业务响应；以响应体 code 判断成功与失败。", @OA\JsonContent(ref="#/components/schemas/AdminResult")),
     *     @OA\Response(response=401, ref="#/components/responses/AdminError"),
     *     @OA\Response(response=403, ref="#/components/responses/AdminError"),
     *     @OA\Response(response="default", ref="#/components/responses/AdminError")
     * )
     */
    #[Post('/submissions/remove')]
    public function remove(): Response
    {
        return $this->action(function (): array {
            $site = $this->site('cms:form:delete');
            return ['count' => (new FormSubmissionService())->delete($site, $this->batchIds())];
        });
    }

    /**
     * 表单回收站：已删除的表单；恢复时带回与它一起删除的提交。彻底删除连同提交、附件与时间线，不可恢复。
     *
     * @OA\Get(
     *     path="/admin/plugins/cms/forms/recycle",
     *     operationId="admin_plugins_cms_controller_Forms_recycle_get",
     *     tags={"CMS 自定义表单"},
     *     summary="表单回收站：已删除的表单（分页）。",
     *     description="普通后台业务协议：成功 code=200；HTTP 200 也可能表示 code=400/422 的业务失败，请同时检查响应体 code。 先校验站点访问与本站操作权限（403），他站记录返回 404；写操作成功后刷新该站点的公开页面缓存。",
     *     security={{"AdminSession"={}}},
     *     @OA\Parameter(name="site_id", in="query", required=true, description="站点 ID。", @OA\Schema(type="string", pattern="^[1-9][0-9]{0,19}$")),
     *     @OA\Parameter(name="page", in="query", required=false, description="页码，从 1 开始。", @OA\Schema(type="integer", minimum=1)),
     *     @OA\Parameter(name="pageSize", in="query", required=false, description="每页记录数，默认 20。", @OA\Schema(type="integer", minimum=1, maximum=100)),
     *     @OA\Response(response=200, description="业务响应；以响应体 code 判断成功与失败。", @OA\JsonContent(ref="#/components/schemas/AdminResult")),
     *     @OA\Response(response=401, ref="#/components/responses/AdminError"),
     *     @OA\Response(response=403, ref="#/components/responses/AdminError"),
     *     @OA\Response(response="default", ref="#/components/responses/AdminError")
     * )
     */
    #[Get('/recycle')]
    public function recycle(): Response
    {
        return $this->action(fn(): array => (new FormRecycleBin())->trashedForms($this->site('cms:form:recycle'), $this->input()['page'] ?? 1, $this->input()['pageSize'] ?? 20));
    }
    /**
     * 从回收站恢复表单，并带回与它一起删除的提交。
     *
     * @OA\Post(
     *     path="/admin/plugins/cms/forms/restore",
     *     operationId="admin_plugins_cms_controller_Forms_restore_post",
     *     tags={"CMS 自定义表单"},
     *     summary="从回收站恢复表单，并带回与它一起删除的提交。",
     *     description="普通后台业务协议：成功 code=200；HTTP 200 也可能表示 code=400/422 的业务失败，请同时检查响应体 code。 先校验站点访问与本站操作权限（403），他站记录返回 404；写操作成功后刷新该站点的公开页面缓存。",
     *     security={{"AdminSession"={}, "CsrfToken"={}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             type="object",
     *             required={"site_id", "ids"},
     *             @OA\Property(property="site_id", type="string", pattern="^[1-9][0-9]{0,19}$", description="站点 ID。"),
     *             @OA\Property(property="ids", description="要处理的记录 ID 列表。", type="array", @OA\Items(type="string"))
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
    #[Post('/restore')]
    public function restore(): Response
    {
        return $this->action(fn(): array => (new FormRecycleBin())->restoreForms($this->site('cms:form:restore'), $this->input()['ids'] ?? null));
    }
    /**
     * 彻底删除回收站中的表单及其提交、附件与时间线，不可恢复。
     *
     * @OA\Post(
     *     path="/admin/plugins/cms/forms/destroy",
     *     operationId="admin_plugins_cms_controller_Forms_destroy_post",
     *     tags={"CMS 自定义表单"},
     *     summary="彻底删除回收站中的表单及其提交、附件与时间线，不可恢复。",
     *     description="普通后台业务协议：成功 code=200；HTTP 200 也可能表示 code=400/422 的业务失败，请同时检查响应体 code。 先校验站点访问与本站操作权限（403），他站记录返回 404；写操作成功后刷新该站点的公开页面缓存。",
     *     security={{"AdminSession"={}, "CsrfToken"={}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             type="object",
     *             required={"site_id", "ids"},
     *             @OA\Property(property="site_id", type="string", pattern="^[1-9][0-9]{0,19}$", description="站点 ID。"),
     *             @OA\Property(property="ids", description="要处理的记录 ID 列表。", type="array", @OA\Items(type="string"))
     *         )
     *     ),
     *     @OA\Response(response=200, description="业务响应；以响应体 code 判断成功与失败。", @OA\JsonContent(ref="#/components/schemas/AdminResult")),
     *     @OA\Response(response=401, ref="#/components/responses/AdminError"),
     *     @OA\Response(response=403, ref="#/components/responses/AdminError"),
     *     @OA\Response(response=404, ref="#/components/responses/AdminError"),
     *     @OA\Response(response="default", ref="#/components/responses/AdminError")
     * )
     */
    #[Post('/destroy')]
    public function destroy(): Response
    {
        return $this->action(fn(): array => (new FormRecycleBin())->destroyForms($this->site('cms:form:destroy'), $this->input()['ids'] ?? null));
    }
    /**
     * 提交回收站：表单仍存在的已删除提交（分页）。
     *
     * @OA\Get(
     *     path="/admin/plugins/cms/forms/submissions/recycle",
     *     operationId="admin_plugins_cms_controller_Forms_submissionRecycle_get",
     *     tags={"CMS 自定义表单"},
     *     summary="提交回收站：表单仍存在的已删除提交（分页）。",
     *     description="普通后台业务协议：成功 code=200；HTTP 200 也可能表示 code=400/422 的业务失败，请同时检查响应体 code。 先校验站点访问与本站操作权限（403），他站记录返回 404；写操作成功后刷新该站点的公开页面缓存。",
     *     security={{"AdminSession"={}}},
     *     @OA\Parameter(name="site_id", in="query", required=true, description="站点 ID。", @OA\Schema(type="string", pattern="^[1-9][0-9]{0,19}$")),
     *     @OA\Parameter(name="page", in="query", required=false, description="页码，从 1 开始。", @OA\Schema(type="integer", minimum=1)),
     *     @OA\Parameter(name="pageSize", in="query", required=false, description="每页记录数，默认 20。", @OA\Schema(type="integer", minimum=1, maximum=100)),
     *     @OA\Response(response=200, description="业务响应；以响应体 code 判断成功与失败。", @OA\JsonContent(ref="#/components/schemas/AdminResult")),
     *     @OA\Response(response=401, ref="#/components/responses/AdminError"),
     *     @OA\Response(response=403, ref="#/components/responses/AdminError"),
     *     @OA\Response(response="default", ref="#/components/responses/AdminError")
     * )
     */
    #[Get('/submissions/recycle')]
    public function submissionRecycle(): Response
    {
        return $this->action(fn(): array => (new FormRecycleBin())->trashedSubmissions($this->site('cms:form:submission-recycle'), $this->input()['page'] ?? 1, $this->input()['pageSize'] ?? 20));
    }
    /**
     * 从回收站恢复表单提交。
     *
     * @OA\Post(
     *     path="/admin/plugins/cms/forms/submissions/restore",
     *     operationId="admin_plugins_cms_controller_Forms_submissionRestore_post",
     *     tags={"CMS 自定义表单"},
     *     summary="从回收站恢复表单提交。",
     *     description="普通后台业务协议：成功 code=200；HTTP 200 也可能表示 code=400/422 的业务失败，请同时检查响应体 code。 先校验站点访问与本站操作权限（403），他站记录返回 404；写操作成功后刷新该站点的公开页面缓存。",
     *     security={{"AdminSession"={}, "CsrfToken"={}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             type="object",
     *             required={"site_id", "ids"},
     *             @OA\Property(property="site_id", type="string", pattern="^[1-9][0-9]{0,19}$", description="站点 ID。"),
     *             @OA\Property(property="ids", description="要处理的记录 ID 列表。", type="array", @OA\Items(type="string"))
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
    #[Post('/submissions/restore')]
    public function submissionRestore(): Response
    {
        return $this->action(fn(): array => (new FormRecycleBin())->restoreSubmissions($this->site('cms:form:submission-restore'), $this->input()['ids'] ?? null));
    }
    /**
     * 彻底删除回收站中的表单提交，不可恢复。
     *
     * @OA\Post(
     *     path="/admin/plugins/cms/forms/submissions/destroy",
     *     operationId="admin_plugins_cms_controller_Forms_submissionDestroy_post",
     *     tags={"CMS 自定义表单"},
     *     summary="彻底删除回收站中的表单提交，不可恢复。",
     *     description="普通后台业务协议：成功 code=200；HTTP 200 也可能表示 code=400/422 的业务失败，请同时检查响应体 code。 先校验站点访问与本站操作权限（403），他站记录返回 404；写操作成功后刷新该站点的公开页面缓存。",
     *     security={{"AdminSession"={}, "CsrfToken"={}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             type="object",
     *             required={"site_id", "ids"},
     *             @OA\Property(property="site_id", type="string", pattern="^[1-9][0-9]{0,19}$", description="站点 ID。"),
     *             @OA\Property(property="ids", description="要处理的记录 ID 列表。", type="array", @OA\Items(type="string"))
     *         )
     *     ),
     *     @OA\Response(response=200, description="业务响应；以响应体 code 判断成功与失败。", @OA\JsonContent(ref="#/components/schemas/AdminResult")),
     *     @OA\Response(response=401, ref="#/components/responses/AdminError"),
     *     @OA\Response(response=403, ref="#/components/responses/AdminError"),
     *     @OA\Response(response=404, ref="#/components/responses/AdminError"),
     *     @OA\Response(response="default", ref="#/components/responses/AdminError")
     * )
     */
    #[Post('/submissions/destroy')]
    public function submissionDestroy(): Response
    {
        return $this->action(fn(): array => (new FormRecycleBin())->destroySubmissions($this->site('cms:form:submission-destroy'), $this->input()['ids'] ?? null));
    }

    /** 返回非 JSON 响应的接口：成功时原样返回构造的响应，出错时沿用 action() 的 JSON 错误规则。 */
    private function raw(callable $build): Response
    {
        $response = null;
        $error = $this->action(function () use ($build, &$response): void {
            $response = $build();
        });
        return $response ?? $error;
    }
    private function filters(): array
    {
        return array_intersect_key($this->input(), array_flip(self::FILTERS));
    }
    /** @return list<mixed> */
    private function batchIds(): array
    {
        $ids = $this->input()['ids'] ?? [];
        if (!is_array($ids)) throw new \InvalidArgumentException('ids 必须为数组', 422);
        return array_values($ids);
    }
    private static function version(array $input): int
    {
        $version = filter_var($input['lock_version'] ?? null, FILTER_VALIDATE_INT);
        if ($version === false || $version < 0) throw new \InvalidArgumentException('lock_version 必须为非负整数', 422);
        return $version;
    }
    private static function bool(mixed $value): bool
    {
        if (is_bool($value)) return $value;
        if (in_array($value, [1, '1', 'true'], true)) return true;
        if (in_array($value, [0, '0', 'false'], true)) return false;
        throw new \InvalidArgumentException('启用状态必须为布尔值', 422);
    }
    private static function string(mixed $value): string
    {
        if ($value !== null && !is_scalar($value)) throw new \InvalidArgumentException('文本格式无效', 422);
        return (string)$value;
    }
    /** RFC 6266/5987：ASCII 回退名（非 ASCII 字符替换为 `_`，去掉引号与反斜杠）+ UTF-8 原名。 */
    private static function disposition(string $type, string $name): string
    {
        $fallback = str_replace(['"', '\\'], '', (string)preg_replace('/[^\x20-\x7e]/u', '_', $name));
        if (trim($fallback, " ._") === '') $fallback = 'download';
        return $type . '; filename="' . $fallback . '"; filename*=UTF-8\'\'' . rawurlencode($name);
    }
}
