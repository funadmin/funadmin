<?php
declare(strict_types=1);
namespace app\admin\plugins\qa\controller;

use OpenApi\Annotations as OA;

use app\cms\service\Input;
use app\qa\service\AdminQuestionService;
use app\qa\service\AttachmentService;
use app\qa\service\QaRecycleBin;
use app\admin\plugins\qa\service\AdminEndpoint;
use app\common\controller\AdminController;
use support\Response;
use support\annotation\route\{Get,Post,RouteGroup};
/** 后台问题管理：列表、详情、附件下载、关闭/重新开放、置顶、删除，以及问题回收站（列表、恢复、彻底删除）。 */
#[RouteGroup('/admin/plugins/qa/questions')]
final class Questions extends AdminController
{
    use AdminEndpoint;
    /**
     * 获取问题列表。
     *
     * @OA\Get(
     *     path="/admin/plugins/qa/questions",
     *     operationId="admin_plugins_qa_controller_Questions_index_get",
     *     tags={"问答问题管理"},
     *     summary="获取问题列表。",
     *     description="普通后台业务协议：成功 code=200；HTTP 200 也可能表示 code=400/422 的业务失败，请同时检查响应体 code。 站点内读取需要 CMS 投稿查看权限，写操作需要投稿审核权限。",
     *     security={{"AdminSession"={}}},
     *     @OA\Parameter(name="site_id", in="query", required=true, description="站点 ID。", @OA\Schema(type="string", pattern="^[1-9][0-9]{0,19}$")),
     *     @OA\Parameter(name="locale", in="query", required=false, description="内容语言代码，默认 zh-cn。", @OA\Schema(type="string")),
     *     @OA\Parameter(name="status", in="query", required=false, description="问题状态。", @OA\Schema(type="string")),
     *     @OA\Parameter(name="q", in="query", required=false, description="标题关键字。", @OA\Schema(type="string")),
     *     @OA\Parameter(name="page", in="query", required=false, description="页码，从 1 开始。", @OA\Schema(type="integer", minimum=1)),
     *     @OA\Response(response=200, description="业务响应；以响应体 code 判断成功与失败。", @OA\JsonContent(ref="#/components/schemas/AdminResult")),
     *     @OA\Response(response=401, ref="#/components/responses/AdminError"),
     *     @OA\Response(response=403, ref="#/components/responses/AdminError"),
     *     @OA\Response(response="default", ref="#/components/responses/AdminError")
     * )
     */
    #[Get('')]
    public function index(): Response { return $this->action(fn()=>(new AdminQuestionService())->listing($this->site('cms:submission:list'),$this->locale(),Input::text($this->input()['status']??'',20),Input::text($this->input()['q']??'',100),$this->page())); }
    /**
     * 获取问题详情及其回答。
     *
     * @OA\Get(
     *     path="/admin/plugins/qa/questions/detail",
     *     operationId="admin_plugins_qa_controller_Questions_detail_get",
     *     tags={"问答问题管理"},
     *     summary="获取问题详情及其回答。",
     *     description="普通后台业务协议：成功 code=200；HTTP 200 也可能表示 code=400/422 的业务失败，请同时检查响应体 code。 站点内读取需要 CMS 投稿查看权限，写操作需要投稿审核权限。",
     *     security={{"AdminSession"={}}},
     *     @OA\Parameter(name="site_id", in="query", required=true, description="站点 ID。", @OA\Schema(type="string", pattern="^[1-9][0-9]{0,19}$")),
     *     @OA\Parameter(name="locale", in="query", required=false, description="内容语言代码，默认 zh-cn。", @OA\Schema(type="string")),
     *     @OA\Parameter(name="id", in="query", required=true, description="问题 ID。", @OA\Schema(type="string", pattern="^[1-9][0-9]{0,19}$")),
     *     @OA\Response(response=200, description="业务响应；以响应体 code 判断成功与失败。", @OA\JsonContent(ref="#/components/schemas/AdminResult")),
     *     @OA\Response(response=401, ref="#/components/responses/AdminError"),
     *     @OA\Response(response=403, ref="#/components/responses/AdminError"),
     *     @OA\Response(response=404, ref="#/components/responses/AdminError"),
     *     @OA\Response(response="default", ref="#/components/responses/AdminError")
     * )
     */
    #[Get('/detail')]
    public function detail(): Response { return $this->action(fn()=>(new AdminQuestionService())->detail($this->site('cms:submission:list'),$this->locale(),$this->id())); }
    /**
     * 下载问题或回答的会员附件（1.3.0）：本站未删除的附件；鉴权同详情（cms:submission:list）。
     *
     * @OA\Get(
     *     path="/admin/plugins/qa/questions/attachment",
     *     operationId="admin_plugins_qa_controller_Questions_attachment_get",
     *     tags={"问答问题管理"},
     *     summary="下载问题或回答的会员附件。",
     *     description="普通后台业务协议：成功 code=200；HTTP 200 也可能表示 code=400/422 的业务失败，请同时检查响应体 code。 站点内读取需要 CMS 投稿查看权限，写操作需要投稿审核权限。",
     *     security={{"AdminSession"={}}},
     *     @OA\Parameter(name="site_id", in="query", required=true, description="站点 ID。", @OA\Schema(type="string", pattern="^[1-9][0-9]{0,19}$")),
     *     @OA\Parameter(name="id", in="query", required=true, description="附件 ID。", @OA\Schema(type="string", pattern="^[1-9][0-9]{0,19}$")),
     *     @OA\Response(response=200, description="文件内容；Content-Disposition 给出文件名。出错时返回 JSON 错误信封。", @OA\MediaType(mediaType="application/octet-stream", @OA\Schema(type="string", format="binary"))),
     *     @OA\Response(response=401, ref="#/components/responses/AdminError"),
     *     @OA\Response(response=403, ref="#/components/responses/AdminError"),
     *     @OA\Response(response=404, ref="#/components/responses/AdminError"),
     *     @OA\Response(response="default", ref="#/components/responses/AdminError")
     * )
     */
    #[Get('/attachment')]
    public function attachment(): Response { return $this->download(fn()=>(new AttachmentService())->adminFile($this->site('cms:submission:list'),$this->id())); }
    /**
     * 关闭或重新开放问题。
     *
     * @OA\Post(
     *     path="/admin/plugins/qa/questions/status",
     *     operationId="admin_plugins_qa_controller_Questions_status_post",
     *     tags={"问答问题管理"},
     *     summary="关闭或重新开放问题。",
     *     description="普通后台业务协议：成功 code=200；HTTP 200 也可能表示 code=400/422 的业务失败，请同时检查响应体 code。 站点内读取需要 CMS 投稿查看权限，写操作需要投稿审核权限。",
     *     security={{"AdminSession"={}, "CsrfToken"={}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             type="object",
     *             required={"site_id", "id", "question_version", "closed"},
     *             @OA\Property(property="site_id", type="string", pattern="^[1-9][0-9]{0,19}$", description="站点 ID。"),
     *             @OA\Property(property="locale", type="string", description="内容语言代码，默认 zh-cn。"),
     *             @OA\Property(property="id", type="string", pattern="^[1-9][0-9]{0,19}$", description="问题 ID。"),
     *             @OA\Property(property="question_version", type="integer", description="问题版本。"),
     *             @OA\Property(property="closed", type="boolean", description="是否关闭，必须为 JSON 布尔值。")
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
    public function status(): Response { return $this->action(fn()=>(new AdminQuestionService())->status($this->site('cms:submission:review'),$this->locale(),$this->id(),$this->version(),$this->flag('closed'))); }
    /**
     * 置顶或取消置顶问题。
     *
     * @OA\Post(
     *     path="/admin/plugins/qa/questions/pin",
     *     operationId="admin_plugins_qa_controller_Questions_pin_post",
     *     tags={"问答问题管理"},
     *     summary="置顶或取消置顶问题。",
     *     description="普通后台业务协议：成功 code=200；HTTP 200 也可能表示 code=400/422 的业务失败，请同时检查响应体 code。 站点内读取需要 CMS 投稿查看权限，写操作需要投稿审核权限。",
     *     security={{"AdminSession"={}, "CsrfToken"={}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             type="object",
     *             required={"site_id", "id", "question_version", "pinned"},
     *             @OA\Property(property="site_id", type="string", pattern="^[1-9][0-9]{0,19}$", description="站点 ID。"),
     *             @OA\Property(property="locale", type="string", description="内容语言代码，默认 zh-cn。"),
     *             @OA\Property(property="id", type="string", pattern="^[1-9][0-9]{0,19}$", description="问题 ID。"),
     *             @OA\Property(property="question_version", type="integer", description="问题版本。"),
     *             @OA\Property(property="pinned", type="boolean", description="是否置顶，必须为 JSON 布尔值。")
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
    #[Post('/pin')]
    public function pin(): Response { return $this->action(fn()=>(new AdminQuestionService())->pin($this->site('cms:submission:review'),$this->locale(),$this->id(),$this->version(),$this->flag('pinned'))); }
    /**
     * 删除问题（进入问题回收站）。
     *
     * @OA\Post(
     *     path="/admin/plugins/qa/questions/delete",
     *     operationId="admin_plugins_qa_controller_Questions_delete_post",
     *     tags={"问答问题管理"},
     *     summary="删除问题（进入问题回收站）。",
     *     description="普通后台业务协议：成功 code=200；HTTP 200 也可能表示 code=400/422 的业务失败，请同时检查响应体 code。 站点内读取需要 CMS 投稿查看权限，写操作需要投稿审核权限。",
     *     security={{"AdminSession"={}, "CsrfToken"={}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             type="object",
     *             required={"site_id", "id"},
     *             @OA\Property(property="site_id", type="string", pattern="^[1-9][0-9]{0,19}$", description="站点 ID。"),
     *             @OA\Property(property="locale", type="string", description="内容语言代码，默认 zh-cn。"),
     *             @OA\Property(property="id", type="string", pattern="^[1-9][0-9]{0,19}$", description="问题 ID。")
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
    public function delete(): Response { return $this->action(fn()=>(new AdminQuestionService())->delete($this->site('cms:submission:review'),$this->locale(),$this->id())); }
    /**
     * 问题回收站：已删除的问题（分页）。
     *
     * @OA\Get(
     *     path="/admin/plugins/qa/questions/recycle",
     *     operationId="admin_plugins_qa_controller_Questions_recycle_get",
     *     tags={"问答问题管理"},
     *     summary="问题回收站：已删除的问题（分页）。",
     *     description="普通后台业务协议：成功 code=200；HTTP 200 也可能表示 code=400/422 的业务失败，请同时检查响应体 code。 站点内读取需要 CMS 投稿查看权限，写操作需要投稿审核权限。",
     *     security={{"AdminSession"={}}},
     *     @OA\Parameter(name="site_id", in="query", required=true, description="站点 ID。", @OA\Schema(type="string", pattern="^[1-9][0-9]{0,19}$")),
     *     @OA\Parameter(name="locale", in="query", required=false, description="内容语言代码，默认 zh-cn。", @OA\Schema(type="string")),
     *     @OA\Parameter(name="page", in="query", required=false, description="页码，从 1 开始。", @OA\Schema(type="integer", minimum=1)),
     *     @OA\Parameter(name="pageSize", in="query", required=false, description="每页记录数，默认 20。", @OA\Schema(type="integer", minimum=1, maximum=100)),
     *     @OA\Response(response=200, description="业务响应；以响应体 code 判断成功与失败。", @OA\JsonContent(ref="#/components/schemas/AdminResult")),
     *     @OA\Response(response=401, ref="#/components/responses/AdminError"),
     *     @OA\Response(response=403, ref="#/components/responses/AdminError"),
     *     @OA\Response(response="default", ref="#/components/responses/AdminError")
     * )
     */
    #[Get('/recycle')]
    public function recycle(): Response { return $this->action(fn()=>(new QaRecycleBin())->trashedQuestions($this->site('cms:submission:list'),$this->locale(),$this->input()['page']??1,$this->input()['pageSize']??20)); }
    /**
     * 从回收站恢复问题；冲突时返回 409。
     *
     * @OA\Post(
     *     path="/admin/plugins/qa/questions/restore",
     *     operationId="admin_plugins_qa_controller_Questions_restore_post",
     *     tags={"问答问题管理"},
     *     summary="从回收站恢复问题；冲突时返回 409。",
     *     description="普通后台业务协议：成功 code=200；HTTP 200 也可能表示 code=400/422 的业务失败，请同时检查响应体 code。 站点内读取需要 CMS 投稿查看权限，写操作需要投稿审核权限。",
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
    public function restore(): Response { return $this->action(fn()=>(new QaRecycleBin())->restoreQuestions($this->site('cms:submission:review'),$this->input()['ids']??null)); }
    /**
     * 彻底删除回收站中的问题及其回答，不可恢复。
     *
     * @OA\Post(
     *     path="/admin/plugins/qa/questions/destroy",
     *     operationId="admin_plugins_qa_controller_Questions_destroy_post",
     *     tags={"问答问题管理"},
     *     summary="彻底删除回收站中的问题及其回答，不可恢复。",
     *     description="普通后台业务协议：成功 code=200；HTTP 200 也可能表示 code=400/422 的业务失败，请同时检查响应体 code。 站点内读取需要 CMS 投稿查看权限，写操作需要投稿审核权限。",
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
    public function destroy(): Response { return $this->action(fn()=>(new QaRecycleBin())->destroyQuestions($this->site('cms:submission:review'),$this->input()['ids']??null)); }
}
