<?php
declare(strict_types=1);
namespace app\admin\plugins\qa\controller;

use OpenApi\Annotations as OA;

use app\qa\service\AdminQuestionService;
use app\qa\service\QaRecycleBin;
use app\admin\plugins\qa\service\AdminEndpoint;
use app\common\controller\AdminController;
use support\Response;
use support\annotation\route\{Get,Post,RouteGroup};
/** 后台回答管理：隐藏/显示、删除，以及回答回收站（列表、恢复、彻底删除）。 */
#[RouteGroup('/admin/plugins/qa/answers')]
final class Answers extends AdminController
{
    use AdminEndpoint;
    /**
     * 隐藏或显示回答。
     *
     * @OA\Post(
     *     path="/admin/plugins/qa/answers/visibility",
     *     operationId="admin_plugins_qa_controller_Answers_visibility_post",
     *     tags={"问答回答管理"},
     *     summary="隐藏或显示回答。",
     *     description="普通后台业务协议：成功 code=200；HTTP 200 也可能表示 code=400/422 的业务失败，请同时检查响应体 code。 站点内读取需要 CMS 投稿查看权限，写操作需要投稿审核权限。",
     *     security={{"AdminSession"={}, "CsrfToken"={}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             type="object",
     *             required={"site_id", "id", "hidden"},
     *             @OA\Property(property="site_id", type="string", pattern="^[1-9][0-9]{0,19}$", description="站点 ID。"),
     *             @OA\Property(property="locale", type="string", description="内容语言代码，默认 zh-cn。"),
     *             @OA\Property(property="id", type="string", pattern="^[1-9][0-9]{0,19}$", description="回答 ID。"),
     *             @OA\Property(property="hidden", type="boolean", description="是否隐藏，必须为 JSON 布尔值。")
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
    #[Post('/visibility')]
    public function visibility(): Response { return $this->action(fn()=>(new AdminQuestionService())->answerVisibility($this->site('cms:submission:review'),$this->locale(),$this->id(),$this->flag('hidden'))); }
    /**
     * 删除回答（进入回答回收站）。
     *
     * @OA\Post(
     *     path="/admin/plugins/qa/answers/delete",
     *     operationId="admin_plugins_qa_controller_Answers_delete_post",
     *     tags={"问答回答管理"},
     *     summary="删除回答（进入回答回收站）。",
     *     description="普通后台业务协议：成功 code=200；HTTP 200 也可能表示 code=400/422 的业务失败，请同时检查响应体 code。 站点内读取需要 CMS 投稿查看权限，写操作需要投稿审核权限。",
     *     security={{"AdminSession"={}, "CsrfToken"={}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             type="object",
     *             required={"site_id", "id"},
     *             @OA\Property(property="site_id", type="string", pattern="^[1-9][0-9]{0,19}$", description="站点 ID。"),
     *             @OA\Property(property="locale", type="string", description="内容语言代码，默认 zh-cn。"),
     *             @OA\Property(property="id", type="string", pattern="^[1-9][0-9]{0,19}$", description="回答 ID。")
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
    public function delete(): Response { return $this->action(fn()=>(new AdminQuestionService())->deleteAnswer($this->site('cms:submission:review'),$this->locale(),$this->id())); }
    /**
     * 回答回收站：已删除的回答（分页）。
     *
     * @OA\Get(
     *     path="/admin/plugins/qa/answers/recycle",
     *     operationId="admin_plugins_qa_controller_Answers_recycle_get",
     *     tags={"问答回答管理"},
     *     summary="回答回收站：已删除的回答（分页）。",
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
    public function recycle(): Response { return $this->action(fn()=>(new QaRecycleBin())->trashedAnswers($this->site('cms:submission:list'),$this->locale(),$this->input()['page']??1,$this->input()['pageSize']??20)); }
    /**
     * 从回收站恢复回答；冲突时返回 409。
     *
     * @OA\Post(
     *     path="/admin/plugins/qa/answers/restore",
     *     operationId="admin_plugins_qa_controller_Answers_restore_post",
     *     tags={"问答回答管理"},
     *     summary="从回收站恢复回答；冲突时返回 409。",
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
    public function restore(): Response { return $this->action(fn()=>(new QaRecycleBin())->restoreAnswers($this->site('cms:submission:review'),$this->input()['ids']??null)); }
    /**
     * 彻底删除回收站中的回答，不可恢复。
     *
     * @OA\Post(
     *     path="/admin/plugins/qa/answers/destroy",
     *     operationId="admin_plugins_qa_controller_Answers_destroy_post",
     *     tags={"问答回答管理"},
     *     summary="彻底删除回收站中的回答，不可恢复。",
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
    public function destroy(): Response { return $this->action(fn()=>(new QaRecycleBin())->destroyAnswers($this->site('cms:submission:review'),$this->input()['ids']??null)); }
}
