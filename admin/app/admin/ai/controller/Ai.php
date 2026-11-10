<?php

declare(strict_types=1);

namespace app\admin\ai\controller;

use OpenApi\Annotations as OA;

use app\common\controller\AdminController;
use Webman\RedisQueue\Redis;
use app\admin\ai\infrastructure\NativeDockerProcessRunner;
use app\admin\ai\model\AiApproval;
use app\admin\ai\model\AiChangeSet;
use app\admin\ai\model\AiOutbox;
use app\admin\ai\model\AiTask;
use app\admin\ai\model\AiToolCall;
use app\admin\ai\repository\DatabaseAiConversationStore;
use app\admin\ai\repository\DatabaseAiSecurityStore;
use app\admin\ai\service\AgentSandboxManager;
use app\admin\ai\service\AiApprovalService;
use app\admin\ai\service\AiChangeSetApplicationService;
use app\admin\ai\service\AiChangeSetService;
use app\admin\ai\service\AiChangeSetTransactionService;
use app\common\model\SystemMigration;
use app\admin\ai\service\AiConversationService;
use app\admin\ai\service\AiCrudProposalService;
use app\admin\ai\service\AiEventStreamService;
use app\admin\ai\service\AiProviderSettingsService;
use app\admin\ai\service\AiResumeOutboxService;
use app\admin\authorization\service\AdminAuthorizationService;
use support\annotation\route\Delete;
use support\annotation\route\Get;
use support\annotation\route\Patch;
use support\annotation\route\Post;
use support\annotation\route\Put;
use support\Db;
use support\Response;
use RuntimeException;
use Throwable;
use Webman\Http\UploadFile;

/** AI 开发助手阶段二 Admin API。 */
#[\support\annotation\route\RouteGroup('/admin/development/ai')]
final class Ai extends AdminController
{
    private readonly AiConversationService $ai;
    private readonly AiEventStreamService $events;
    private readonly DatabaseAiSecurityStore $security;
    private readonly AiApprovalService $approvals;
    private readonly AgentSandboxManager $sandbox;

    public function __construct()
    {
        parent::__construct();
        $store = new DatabaseAiConversationStore();
        $this->ai = new AiConversationService($store, (array) config('ai.limits', []));
        $this->events = new AiEventStreamService($store, (string) config('ai.stream.ticket_secret', ''));
        $this->security = new DatabaseAiSecurityStore();
        $this->approvals = new AiApprovalService($this->security);
        $this->sandbox = new AgentSandboxManager(new NativeDockerProcessRunner(), base_path(), (string) config('ai.storage.private_path'), (array) config('ai.sandbox', []));
    }

    /**
     * 分页查询当前管理员的 AI 会话。
     *
     * @return Response JSON 格式的接口响应。
     *
     * @OA\Get(
     *     path="/admin/development/ai/conversations",
     *     operationId="admin_ai_controller_Ai_conversationIndex_get",
     *     tags={"AI 开发"},
     *     summary="分页查询当前管理员的 AI 会话。",
     *     description="普通后台业务协议：成功 code=200；HTTP 200 也可能表示 code=400/422 的业务失败，请同时检查响应体 code。",
     *     security={{"AdminSession"={}}},
     *     @OA\Parameter(
     *         name="limit",
     *         in="query",
     *         required=false,
     *         description="每批读取上限。",
     *         @OA\Schema(type="integer", minimum=1, maximum=100, default=30)
     *     ),
     *     @OA\Parameter(name="cursor", in="query", required=false, description="分页或事件游标。", @OA\Schema(type="string", pattern="^[1-9][0-9]*$")),
     *     @OA\Parameter(name="search", in="query", required=false, description="search", @OA\Schema(type="string", maxLength=255)),
     *     @OA\Parameter(name="is_archived", in="query", required=false, description="是否查询归档会话。", @OA\Schema(type="integer", enum={0, 1})),
     *     @OA\Parameter(name="group_id", in="query", required=false, description="group_id", @OA\Schema(type="integer", minimum=0)),
     *     @OA\Parameter(name="is_unread", in="query", required=false, description="is_unread", @OA\Schema(type="integer", enum={0, 1})),
     *     @OA\Response(response=200, description="业务响应；以响应体 code 判断成功与失败。", @OA\JsonContent(ref="#/components/schemas/AiConversationIndexResult")),
     *     @OA\Response(response=401, ref="#/components/responses/AdminError"),
     *     @OA\Response(response=403, ref="#/components/responses/AdminError"),
     *     @OA\Response(response="default", ref="#/components/responses/AdminError")
     * )
     */
    #[Get('/conversations')]
    public function conversationIndex(): Response { return $this->run(fn () => $this->ai->conversationPage($this->adminId(), $this->request->get())); }
    /**
     * 获取当前管理员的会话分组。
     *
     * @return Response JSON 格式的接口响应。
     *
     * @OA\Get(
     *     path="/admin/development/ai/conversation-groups",
     *     operationId="admin_ai_controller_Ai_conversationGroupIndex_get",
     *     tags={"AI 开发"},
     *     summary="获取当前管理员的会话分组。",
     *     description="普通后台业务协议：成功 code=200；HTTP 200 也可能表示 code=400/422 的业务失败，请同时检查响应体 code。",
     *     security={{"AdminSession"={}}},
     *     @OA\Response(response=200, description="业务响应；以响应体 code 判断成功与失败。", @OA\JsonContent(ref="#/components/schemas/AdminResult")),
     *     @OA\Response(response=401, ref="#/components/responses/AdminError"),
     *     @OA\Response(response=403, ref="#/components/responses/AdminError"),
     *     @OA\Response(response="default", ref="#/components/responses/AdminError")
     * )
     */
    #[Get('/conversation-groups')]
    public function conversationGroupIndex(): Response { return $this->run(fn () => $this->ai->listConversationGroups($this->adminId())); }
    /**
     * 为当前管理员创建会话分组。
     *
     * @return Response JSON 格式的接口响应。
     *
     * @OA\Post(
     *     path="/admin/development/ai/conversation-groups",
     *     operationId="admin_ai_controller_Ai_conversationGroupCreate_post",
     *     tags={"AI 开发"},
     *     summary="为当前管理员创建会话分组。",
     *     description="普通后台业务协议：成功 code=200；HTTP 200 也可能表示 code=400/422 的业务失败，请同时检查响应体 code。",
     *     security={{"AdminSession"={}, "CsrfToken"={}}},
     *     @OA\RequestBody(required=true, @OA\JsonContent(type="object", required={"name"}, @OA\Property(property="name", type="string"))),
     *     @OA\Response(response=200, description="业务响应；以响应体 code 判断成功与失败。", @OA\JsonContent(ref="#/components/schemas/AdminResult")),
     *     @OA\Response(response=401, ref="#/components/responses/AdminError"),
     *     @OA\Response(response=403, ref="#/components/responses/AdminError"),
     *     @OA\Response(response="default", ref="#/components/responses/AdminError")
     * )
     */
    #[Post('/conversation-groups')]
    public function conversationGroupCreate(): Response { return $this->run(fn () => $this->ai->createConversationGroup($this->adminId(), $this->input())); }
    /**
     * 更新当前管理员的会话分组。
     *
     * @param int $id 路由中的目标记录 ID。
     * @return Response JSON 格式的接口响应。
     *
     * @OA\Put(
     *     path="/admin/development/ai/conversation-groups/{id}",
     *     operationId="admin_ai_controller_Ai_conversationGroupUpdate_put",
     *     tags={"AI 开发"},
     *     summary="更新当前管理员的会话分组。",
     *     description="普通后台业务协议：成功 code=200；HTTP 200 也可能表示 code=400/422 的业务失败，请同时检查响应体 code。",
     *     security={{"AdminSession"={}, "CsrfToken"={}}},
     *     @OA\Parameter(name="id", in="path", required=true, description="路由中的目标记录 ID。", @OA\Schema(type="integer", minimum=1)),
     *     @OA\RequestBody(required=true, @OA\JsonContent(type="object", required={"name"}, @OA\Property(property="name", type="string"))),
     *     @OA\Response(response=200, description="业务响应；以响应体 code 判断成功与失败。", @OA\JsonContent(ref="#/components/schemas/AdminResult")),
     *     @OA\Response(response=401, ref="#/components/responses/AdminError"),
     *     @OA\Response(response=403, ref="#/components/responses/AdminError"),
     *     @OA\Response(response=404, ref="#/components/responses/AdminError"),
     *     @OA\Response(response="default", ref="#/components/responses/AdminError")
     * )
     */
    #[Put('/conversation-groups/{id:\d+}')]
    public function conversationGroupUpdate(int $id): Response { return $this->run(fn () => $this->ai->updateConversationGroup($id, $this->adminId(), $this->input())); }
    /**
     * 删除当前管理员的会话分组。
     *
     * @param int $id 路由中的目标记录 ID。
     * @return Response JSON 格式的接口响应。
     *
     * @OA\Delete(
     *     path="/admin/development/ai/conversation-groups/{id}",
     *     operationId="admin_ai_controller_Ai_conversationGroupDelete_delete",
     *     tags={"AI 开发"},
     *     summary="删除当前管理员的会话分组。",
     *     description="普通后台业务协议：成功 code=200；HTTP 200 也可能表示 code=400/422 的业务失败，请同时检查响应体 code。",
     *     security={{"AdminSession"={}, "CsrfToken"={}}},
     *     @OA\Parameter(name="id", in="path", required=true, description="路由中的目标记录 ID。", @OA\Schema(type="integer", minimum=1)),
     *     @OA\Response(response=200, description="业务响应；以响应体 code 判断成功与失败。", @OA\JsonContent(ref="#/components/schemas/AdminResult")),
     *     @OA\Response(response=401, ref="#/components/responses/AdminError"),
     *     @OA\Response(response=403, ref="#/components/responses/AdminError"),
     *     @OA\Response(response=404, ref="#/components/responses/AdminError"),
     *     @OA\Response(response="default", ref="#/components/responses/AdminError")
     * )
     */
    #[Delete('/conversation-groups/{id:\d+}')]
    public function conversationGroupDelete(int $id): Response { return $this->run(fn () => ['deleted' => $this->ai->deleteConversationGroup($id, $this->adminId())]); }

    /**
     * 创建 AI 会话，并检查审批模式所需权限。
     *
     * @return Response JSON 格式的接口响应。
     *
     * @OA\Post(
     *     path="/admin/development/ai/conversations",
     *     operationId="admin_ai_controller_Ai_conversationCreate_post",
     *     tags={"AI 开发"},
     *     summary="创建 AI 会话，并检查审批模式所需权限。",
     *     description="普通后台业务协议：成功 code=200；HTTP 200 也可能表示 code=400/422 的业务失败，请同时检查响应体 code。",
     *     security={{"AdminSession"={}, "CsrfToken"={}}},
     *     @OA\RequestBody(
     *         required=false,
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="title", type="string"),
     *             @OA\Property(property="group_id", type="integer", nullable=true),
     *             @OA\Property(property="profile_id", type="integer", nullable=true),
     *             @OA\Property(property="model", type="string"),
     *             @OA\Property(property="provider", type="string"),
     *             @OA\Property(property="approval_mode", type="string", enum={"request_approval", "agent_approval", "full_access"}),
     *             @OA\Property(property="reasoning_effort", type="string", nullable=true),
     *             @OA\Property(property="context", oneOf={@OA\Schema(type="object", additionalProperties=true), @OA\Schema(type="array", @OA\Items())})
     *         )
     *     ),
     *     @OA\Response(response=200, description="业务响应；以响应体 code 判断成功与失败。", @OA\JsonContent(ref="#/components/schemas/AdminResult")),
     *     @OA\Response(response=401, ref="#/components/responses/AdminError"),
     *     @OA\Response(response=403, ref="#/components/responses/AdminError"),
     *     @OA\Response(response="default", ref="#/components/responses/AdminError")
     * )
     */
    #[Post('/conversations')]
    public function conversationCreate(): Response { return $this->run(fn () => $this->ai->createConversation($this->adminId(), $this->input(), $this->can('development:ai:full-access'), $this->can('development:ai:approve'))); }
    /**
     * 读取当前管理员所属的 AI 会话详情。
     *
     * @param int $id 路由中的目标记录 ID。
     * @return Response JSON 格式的接口响应。
     *
     * @OA\Get(
     *     path="/admin/development/ai/conversations/{id}",
     *     operationId="admin_ai_controller_Ai_conversationRead_get",
     *     tags={"AI 开发"},
     *     summary="读取当前管理员所属的 AI 会话详情。",
     *     description="普通后台业务协议：成功 code=200；HTTP 200 也可能表示 code=400/422 的业务失败，请同时检查响应体 code。",
     *     security={{"AdminSession"={}}},
     *     @OA\Parameter(name="id", in="path", required=true, description="路由中的目标记录 ID。", @OA\Schema(type="integer", minimum=1)),
     *     @OA\Response(response=200, description="业务响应；以响应体 code 判断成功与失败。", @OA\JsonContent(ref="#/components/schemas/AdminResult")),
     *     @OA\Response(response=401, ref="#/components/responses/AdminError"),
     *     @OA\Response(response=403, ref="#/components/responses/AdminError"),
     *     @OA\Response(response=404, ref="#/components/responses/AdminError"),
     *     @OA\Response(response="default", ref="#/components/responses/AdminError")
     * )
     */
    #[Get('/conversations/{id:\d+}')]
    public function conversationRead(int $id): Response { return $this->run(fn () => $this->ai->getConversation($id, $this->adminId())); }
    /**
     * 更新 AI 会话配置，并检查审批模式所需权限。
     *
     * @param int $id 路由中的目标记录 ID。
     * @return Response JSON 格式的接口响应。
     *
     * @OA\Put(
     *     path="/admin/development/ai/conversations/{id}",
     *     operationId="admin_ai_controller_Ai_conversationUpdate_put",
     *     tags={"AI 开发"},
     *     summary="更新 AI 会话配置，并检查审批模式所需权限。",
     *     description="普通后台业务协议：成功 code=200；HTTP 200 也可能表示 code=400/422 的业务失败，请同时检查响应体 code。",
     *     security={{"AdminSession"={}, "CsrfToken"={}}},
     *     @OA\Parameter(name="id", in="path", required=true, description="路由中的目标记录 ID。", @OA\Schema(type="integer", minimum=1)),
     *     @OA\RequestBody(
     *         required=false,
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="title", type="string"),
     *             @OA\Property(property="profile_id", type="integer", nullable=true),
     *             @OA\Property(property="model", type="string"),
     *             @OA\Property(property="provider", type="string"),
     *             @OA\Property(property="approval_mode", type="string", enum={"request_approval", "agent_approval", "full_access"}),
     *             @OA\Property(property="reasoning_effort", type="string", nullable=true),
     *             @OA\Property(property="context", oneOf={@OA\Schema(type="object", additionalProperties=true), @OA\Schema(type="array", @OA\Items())})
     *         )
     *     ),
     *     @OA\Response(response=200, description="业务响应；以响应体 code 判断成功与失败。", @OA\JsonContent(ref="#/components/schemas/AdminResult")),
     *     @OA\Response(response=401, ref="#/components/responses/AdminError"),
     *     @OA\Response(response=403, ref="#/components/responses/AdminError"),
     *     @OA\Response(response=404, ref="#/components/responses/AdminError"),
     *     @OA\Response(response="default", ref="#/components/responses/AdminError")
     * )
     */
    #[Put('/conversations/{id:\d+}')]
    public function conversationUpdate(int $id): Response { return $this->run(fn () => $this->ai->updateConversation($id, $this->adminId(), $this->input(), $this->can('development:ai:full-access'), $this->can('development:ai:approve'))); }
    /**
     * 部分更新 AI 会话的展示状态。
     *
     * @param int $id 路由中的目标记录 ID。
     * @return Response JSON 格式的接口响应。
     *
     * @OA\Patch(
     *     path="/admin/development/ai/conversations/{id}/state",
     *     operationId="admin_ai_controller_Ai_conversationStateUpdate_patch",
     *     tags={"AI 开发"},
     *     summary="部分更新 AI 会话的展示状态。",
     *     description="普通后台业务协议：成功 code=200；HTTP 200 也可能表示 code=400/422 的业务失败，请同时检查响应体 code。",
     *     security={{"AdminSession"={}, "CsrfToken"={}}},
     *     @OA\Parameter(name="id", in="path", required=true, description="路由中的目标记录 ID。", @OA\Schema(type="integer", minimum=1)),
     *     @OA\RequestBody(
     *         required=false,
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="group_id", type="integer", nullable=true),
     *             @OA\Property(property="is_archived", type="boolean"),
     *             @OA\Property(property="is_unread", type="boolean")
     *         )
     *     ),
     *     @OA\Response(response=200, description="业务响应；以响应体 code 判断成功与失败。", @OA\JsonContent(ref="#/components/schemas/AdminResult")),
     *     @OA\Response(response=401, ref="#/components/responses/AdminError"),
     *     @OA\Response(response=403, ref="#/components/responses/AdminError"),
     *     @OA\Response(response=404, ref="#/components/responses/AdminError"),
     *     @OA\Response(response="default", ref="#/components/responses/AdminError")
     * )
     */
    #[Patch('/conversations/{id:\d+}/state')]
    public function conversationStateUpdate(int $id): Response { return $this->run(fn () => $this->ai->updateConversationState($id, $this->adminId(), $this->input())); }
    /**
     * 删除当前管理员所属的 AI 会话。
     *
     * @param int $id 路由中的目标记录 ID。
     * @return Response JSON 格式的接口响应。
     *
     * @OA\Delete(
     *     path="/admin/development/ai/conversations/{id}",
     *     operationId="admin_ai_controller_Ai_conversationDelete_delete",
     *     tags={"AI 开发"},
     *     summary="删除当前管理员所属的 AI 会话。",
     *     description="普通后台业务协议：成功 code=200；HTTP 200 也可能表示 code=400/422 的业务失败，请同时检查响应体 code。",
     *     security={{"AdminSession"={}, "CsrfToken"={}}},
     *     @OA\Parameter(name="id", in="path", required=true, description="路由中的目标记录 ID。", @OA\Schema(type="integer", minimum=1)),
     *     @OA\Response(response=200, description="业务响应；以响应体 code 判断成功与失败。", @OA\JsonContent(ref="#/components/schemas/AdminResult")),
     *     @OA\Response(response=401, ref="#/components/responses/AdminError"),
     *     @OA\Response(response=403, ref="#/components/responses/AdminError"),
     *     @OA\Response(response=404, ref="#/components/responses/AdminError"),
     *     @OA\Response(response="default", ref="#/components/responses/AdminError")
     * )
     */
    #[Delete('/conversations/{id:\d+}')]
    public function conversationDelete(int $id): Response { return $this->run(fn () => ['deleted' => $this->ai->deleteConversation($id, $this->adminId())]); }
    /**
     * 按游标读取指定会话的消息。
     *
     * @param int $id 路由中的目标记录 ID。
     * @return Response JSON 格式的接口响应。
     *
     * @OA\Get(
     *     path="/admin/development/ai/conversations/{id}/messages",
     *     operationId="admin_ai_controller_Ai_messageIndex_get",
     *     tags={"AI 开发"},
     *     summary="按游标读取指定会话的消息。",
     *     description="普通后台业务协议：成功 code=200；HTTP 200 也可能表示 code=400/422 的业务失败，请同时检查响应体 code。 before 与 after 互斥，消息游标为 sequence:id；after 允许 0:0。",
     *     security={{"AdminSession"={}}},
     *     @OA\Parameter(name="id", in="path", required=true, description="路由中的目标记录 ID。", @OA\Schema(type="integer", minimum=1)),
     *     @OA\Parameter(
     *         name="limit",
     *         in="query",
     *         required=false,
     *         description="每批读取上限。",
     *         @OA\Schema(type="integer", minimum=1, maximum=100, default=50)
     *     ),
     *     @OA\Parameter(name="before", in="query", required=false, description="before", @OA\Schema(type="string")),
     *     @OA\Parameter(name="after", in="query", required=false, description="after", @OA\Schema(type="string")),
     *     @OA\Response(response=200, description="业务响应；以响应体 code 判断成功与失败。", @OA\JsonContent(ref="#/components/schemas/AiMessageIndexResult")),
     *     @OA\Response(response=401, ref="#/components/responses/AdminError"),
     *     @OA\Response(response=403, ref="#/components/responses/AdminError"),
     *     @OA\Response(response=404, ref="#/components/responses/AdminError"),
     *     @OA\Response(response="default", ref="#/components/responses/AdminError")
     * )
     */
    #[Get('/conversations/{id:\d+}/messages')]
    public function messageIndex(int $id): Response { return $this->run(fn () => $this->ai->messagePage($id, $this->adminId(), $this->request->get())); }
    /**
     * 向指定会话追加用户消息。
     *
     * @param int $id 路由中的目标记录 ID。
     * @return Response JSON 格式的接口响应。
     *
     * @OA\Post(
     *     path="/admin/development/ai/conversations/{id}/messages",
     *     operationId="admin_ai_controller_Ai_messageCreate_post",
     *     tags={"AI 开发"},
     *     summary="向指定会话追加用户消息。",
     *     description="普通后台业务协议：成功 code=200；HTTP 200 也可能表示 code=400/422 的业务失败，请同时检查响应体 code。",
     *     security={{"AdminSession"={}, "CsrfToken"={}}},
     *     @OA\Parameter(name="id", in="path", required=true, description="路由中的目标记录 ID。", @OA\Schema(type="integer", minimum=1)),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(allOf={@OA\Schema(ref="#/components/schemas/AiMessageInput"), @OA\Schema(type="object", required={"content"})})
     *     ),
     *     @OA\Response(response=200, description="业务响应；以响应体 code 判断成功与失败。", @OA\JsonContent(ref="#/components/schemas/AdminResult")),
     *     @OA\Response(response=401, ref="#/components/responses/AdminError"),
     *     @OA\Response(response=403, ref="#/components/responses/AdminError"),
     *     @OA\Response(response=404, ref="#/components/responses/AdminError"),
     *     @OA\Response(response="default", ref="#/components/responses/AdminError")
     * )
     */
    #[Post('/conversations/{id:\d+}/messages')]
    public function messageCreate(int $id): Response { return $this->run(fn () => $this->ai->appendUserMessage($id, $this->adminId(), $this->input())); }

    /**
     * 上传单个 multipart file 到当前会话的私有附件存储。
     *
     * @param int $id 路由中的目标记录 ID。
     * @return Response JSON 格式的接口响应。
     *
     * @OA\Post(
     *     path="/admin/development/ai/conversations/{id}/attachments",
     *     operationId="admin_ai_controller_Ai_attachmentCreate_post",
     *     tags={"AI 开发"},
     *     summary="上传单个 multipart file 到当前会话的私有附件存储。",
     *     description="普通后台业务协议：成功 code=200；HTTP 200 也可能表示 code=400/422 的业务失败，请同时检查响应体 code。",
     *     security={{"AdminSession"={}, "CsrfToken"={}}},
     *     @OA\Parameter(name="id", in="path", required=true, description="路由中的目标记录 ID。", @OA\Schema(type="integer", minimum=1)),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\MediaType(
     *             mediaType="multipart/form-data",
     *             @OA\Schema(
     *                 type="object",
     *                 required={"file"},
     *                 @OA\Property(property="file", type="string", description="上传文件，使用 multipart/form-data。", format="binary")
     *             )
     *         )
     *     ),
     *     @OA\Response(response=200, description="业务响应；以响应体 code 判断成功与失败。", @OA\JsonContent(ref="#/components/schemas/AiAttachmentCreateResult")),
     *     @OA\Response(response=401, ref="#/components/responses/AdminError"),
     *     @OA\Response(response=403, ref="#/components/responses/AdminError"),
     *     @OA\Response(response=404, ref="#/components/responses/AdminError"),
     *     @OA\Response(response=413, description="上传内容超过大小上限，或请求体超过服务端限制。", @OA\JsonContent(ref="#/components/schemas/AdminEnvelope")),
     *     @OA\Response(response="default", ref="#/components/responses/AdminError")
     * )
     */
    #[Post('/conversations/{id:\d+}/attachments')]
    public function attachmentCreate(int $id): Response
    {
        return $this->run(function () use ($id): array {
            $adminId = $this->adminId();
            $this->ai->getConversation($id, $adminId);
            $files = $this->request->file();
            $file = $files['file'] ?? null;
            if (count($files) !== 1 || !$file instanceof UploadFile || !$file->isValid()) throw new \InvalidArgumentException('必须上传单个 multipart file', 400);
            if ($file->getSize() > \app\admin\ai\service\AiAttachmentStorage::IMAGE_BYTES) throw new \InvalidArgumentException('附件超过 5MiB', 413);
            $bytes = file_get_contents($file->getPathname(), false, null, 0, \app\admin\ai\service\AiAttachmentStorage::IMAGE_BYTES + 1);
            if (!is_string($bytes)) throw new RuntimeException('无法读取上传文件', 400);
            return \app\admin\ai\service\AiAttachmentService::production()->upload($id, $adminId, $file->getUploadName(), $bytes);
        });
    }

    /**
     * 验证会话归属后返回私有附件内容，并设置禁止缓存响应头。
     *
     * @param int $id 路由中的目标记录 ID。
     * @param int $attachmentId 当前会话的附件 ID。
     * @return Response 成功时为附件原始内容，失败时为 JSON 错误响应。
     *
     * @OA\Get(
     *     path="/admin/development/ai/conversations/{id}/attachments/{attachmentId}/content",
     *     operationId="admin_ai_controller_Ai_attachmentContent_get",
     *     tags={"AI 开发"},
     *     summary="验证会话归属后返回私有附件内容，并设置禁止缓存响应头。",
     *     description="普通后台业务协议：成功 code=200；HTTP 200 也可能表示 code=400/422 的业务失败，请同时检查响应体 code。",
     *     security={{"AdminSession"={}}},
     *     @OA\Parameter(name="id", in="path", required=true, description="路由中的目标记录 ID。", @OA\Schema(type="integer", minimum=1)),
     *     @OA\Parameter(name="attachmentId", in="path", required=true, description="当前会话的附件 ID。", @OA\Schema(type="integer", minimum=1)),
     *     @OA\Response(
     *         response=200,
     *         description="文件内容；实际 MIME 与 Content-Disposition 由服务端返回。",
     *         @OA\MediaType(mediaType="application/octet-stream", @OA\Schema(type="string", format="binary"))
     *     ),
     *     @OA\Response(response=401, ref="#/components/responses/AdminError"),
     *     @OA\Response(response=403, ref="#/components/responses/AdminError"),
     *     @OA\Response(response=404, ref="#/components/responses/AdminError"),
     *     @OA\Response(response=409, ref="#/components/responses/AdminError"),
     *     @OA\Response(response="default", ref="#/components/responses/AdminError")
     * )
     */
    #[Get('/conversations/{id:\d+}/attachments/{attachmentId:\d+}/content')]
    public function attachmentContent(int $id, int $attachmentId): Response
    {
        try {
            $result = \app\admin\ai\service\AiAttachmentService::production()->content($id, $this->adminId(), $attachmentId);
            $response = response($result['body'], 200);
            foreach ((array) $result['headers'] as $name => $value) $response->header((string) $name, (string) $value);
            return $response;
        } catch (Throwable $exception) {
            $code = in_array($exception->getCode(), [401,403,404,409], true) ? $exception->getCode() : 400;
            $response = $this->fail(msg: '附件不可用', code: $code);
            foreach (['Cache-Control' => 'no-store', 'X-Content-Type-Options' => 'nosniff'] as $name => $value) $response->header($name, $value);
            return $response;
        }
    }

    /**
     * 删除当前会话中的私有附件。
     *
     * @param int $id 路由中的目标记录 ID。
     * @param int $attachmentId 当前会话的附件 ID。
     * @return Response JSON 格式的接口响应。
     *
     * @OA\Delete(
     *     path="/admin/development/ai/conversations/{id}/attachments/{attachmentId}",
     *     operationId="admin_ai_controller_Ai_attachmentDelete_delete",
     *     tags={"AI 开发"},
     *     summary="删除当前会话中的私有附件。",
     *     description="普通后台业务协议：成功 code=200；HTTP 200 也可能表示 code=400/422 的业务失败，请同时检查响应体 code。",
     *     security={{"AdminSession"={}, "CsrfToken"={}}},
     *     @OA\Parameter(name="id", in="path", required=true, description="路由中的目标记录 ID。", @OA\Schema(type="integer", minimum=1)),
     *     @OA\Parameter(name="attachmentId", in="path", required=true, description="当前会话的附件 ID。", @OA\Schema(type="integer", minimum=1)),
     *     @OA\Response(response=200, description="业务响应；以响应体 code 判断成功与失败。", @OA\JsonContent(ref="#/components/schemas/AdminResult")),
     *     @OA\Response(response=401, ref="#/components/responses/AdminError"),
     *     @OA\Response(response=403, ref="#/components/responses/AdminError"),
     *     @OA\Response(response=404, ref="#/components/responses/AdminError"),
     *     @OA\Response(response="default", ref="#/components/responses/AdminError")
     * )
     */
    #[Delete('/conversations/{id:\d+}/attachments/{attachmentId:\d+}')]
    public function attachmentDelete(int $id, int $attachmentId): Response
    {
        return $this->run(fn () => ['deleted'=>\app\admin\ai\service\AiAttachmentService::production()->delete($id, $this->adminId(), $attachmentId)]);
    }

    /**
     * 创建 AI 任务并投递至 ai-agent 队列。
     *
     * @param int $id 路由中的目标记录 ID。
     * @return Response JSON 格式的接口响应。
     *
     * @OA\Post(
     *     path="/admin/development/ai/conversations/{id}/tasks",
     *     operationId="admin_ai_controller_Ai_taskExecute_post",
     *     tags={"AI 开发"},
     *     summary="创建 AI 任务并投递至 ai-agent 队列。",
     *     description="普通后台业务协议：成功 code=200；HTTP 200 也可能表示 code=400/422 的业务失败，请同时检查响应体 code。",
     *     security={{"AdminSession"={}, "CsrfToken"={}}},
     *     @OA\Parameter(name="id", in="path", required=true, description="路由中的目标记录 ID。", @OA\Schema(type="integer", minimum=1)),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             type="object",
     *             required={"message_id", "idempotency_key"},
     *             @OA\Property(property="message_id", type="integer", description="本会话中已保存的 user 消息 ID。", minimum=1),
     *             @OA\Property(property="idempotency_key", type="string", minLength=1, maxLength=128),
     *             @OA\Property(property="type", type="string", enum={"chat"}, default="chat")
     *         )
     *     ),
     *     @OA\Response(response=200, description="业务响应；以响应体 code 判断成功与失败。", @OA\JsonContent(ref="#/components/schemas/AdminResult")),
     *     @OA\Response(response=401, ref="#/components/responses/AdminError"),
     *     @OA\Response(response=403, ref="#/components/responses/AdminError"),
     *     @OA\Response(response=404, ref="#/components/responses/AdminError"),
     *     @OA\Response(response="default", ref="#/components/responses/AdminError")
     * )
     */
    #[Post('/conversations/{id:\d+}/tasks')]
    public function taskExecute(int $id): Response
    {
        return $this->run(function () use ($id): array {
            $task = $this->ai->createPublicTask($id, $this->adminId(), $this->input());
            if (!Redis::connection('ai-agent')->send('ai-agent', ['taskId' => $task['id'], 'operationToken' => $task['operation_token']])) {
                throw new RuntimeException('AI 队列投递未确认');
            }
            return $task;
        });
    }

    /**
     * 获取当前管理员所属的 AI 任务详情。
     *
     * @param int $id 路由中的目标记录 ID。
     * @return Response JSON 格式的接口响应。
     *
     * @OA\Get(
     *     path="/admin/development/ai/tasks/{id}",
     *     operationId="admin_ai_controller_Ai_taskRead_get",
     *     tags={"AI 开发"},
     *     summary="获取当前管理员所属的 AI 任务详情。",
     *     description="普通后台业务协议：成功 code=200；HTTP 200 也可能表示 code=400/422 的业务失败，请同时检查响应体 code。",
     *     security={{"AdminSession"={}}},
     *     @OA\Parameter(name="id", in="path", required=true, description="路由中的目标记录 ID。", @OA\Schema(type="integer", minimum=1)),
     *     @OA\Response(response=200, description="业务响应；以响应体 code 判断成功与失败。", @OA\JsonContent(ref="#/components/schemas/AdminResult")),
     *     @OA\Response(response=401, ref="#/components/responses/AdminError"),
     *     @OA\Response(response=403, ref="#/components/responses/AdminError"),
     *     @OA\Response(response=404, ref="#/components/responses/AdminError"),
     *     @OA\Response(response="default", ref="#/components/responses/AdminError")
     * )
     */
    #[Get('/tasks/{id:\d+}')]
    public function taskRead(int $id): Response { return $this->run(fn () => $this->ai->getTask($id, $this->adminId())); }

    /**
     * 请求取消当前管理员所属的 AI 任务。
     *
     * @param int $id 路由中的目标记录 ID。
     * @return Response JSON 格式的接口响应。
     *
     * @OA\Post(
     *     path="/admin/development/ai/tasks/{id}/cancel",
     *     operationId="admin_ai_controller_Ai_taskCancel_post",
     *     tags={"AI 开发"},
     *     summary="请求取消当前管理员所属的 AI 任务。",
     *     description="普通后台业务协议：成功 code=200；HTTP 200 也可能表示 code=400/422 的业务失败，请同时检查响应体 code。",
     *     security={{"AdminSession"={}, "CsrfToken"={}}},
     *     @OA\Parameter(name="id", in="path", required=true, description="路由中的目标记录 ID。", @OA\Schema(type="integer", minimum=1)),
     *     @OA\Response(response=200, description="业务响应；以响应体 code 判断成功与失败。", @OA\JsonContent(ref="#/components/schemas/AdminResult")),
     *     @OA\Response(response=401, ref="#/components/responses/AdminError"),
     *     @OA\Response(response=403, ref="#/components/responses/AdminError"),
     *     @OA\Response(response=404, ref="#/components/responses/AdminError"),
     *     @OA\Response(response="default", ref="#/components/responses/AdminError")
     * )
     */
    #[Post('/tasks/{id:\d+}/cancel')]
    public function taskCancel(int $id): Response { return $this->run(fn () => ['cancelled' => $this->ai->cancelTask($id, $this->adminId())]); }
    /**
     * 验证任务归属后签发事件读取票据。
     *
     * @param int $id 路由中的目标记录 ID。
     * @return Response JSON 格式的接口响应。
     *
     * @OA\Post(
     *     path="/admin/development/ai/tasks/{id}/events/ticket",
     *     operationId="admin_ai_controller_Ai_eventTicket_post",
     *     tags={"AI 开发"},
     *     summary="验证任务归属后签发事件读取票据。",
     *     description="普通后台业务协议：成功 code=200；HTTP 200 也可能表示 code=400/422 的业务失败，请同时检查响应体 code。",
     *     security={{"AdminSession"={}, "CsrfToken"={}}},
     *     @OA\Parameter(name="id", in="path", required=true, description="路由中的目标记录 ID。", @OA\Schema(type="integer", minimum=1)),
     *     @OA\Response(response=200, description="业务响应；以响应体 code 判断成功与失败。", @OA\JsonContent(ref="#/components/schemas/AiEventTicketResult")),
     *     @OA\Response(response=401, ref="#/components/responses/AdminError"),
     *     @OA\Response(response=403, ref="#/components/responses/AdminError"),
     *     @OA\Response(response=404, ref="#/components/responses/AdminError"),
     *     @OA\Response(response="default", ref="#/components/responses/AdminError")
     * )
     */
    #[Post('/tasks/{id:\d+}/events/ticket')]
    public function eventTicket(int $id): Response { return $this->run(function () use ($id): array { $this->ai->getTask($id, $this->adminId()); return ['ticket' => $this->events->issueTicket($this->adminId(), $id)]; }); }

    /**
     * 使用事件票据和游标读取任务事件，返回 SSE 格式响应。
     *
     * 查询参数：ticket、cursor。
     *
     * @param int $id 路由中的目标记录 ID。
     * @return Response text/event-stream 格式的任务事件，读取失败时按异常处理。
     *
     * @OA\Get(
     *     path="/admin/development/ai/tasks/{id}/events",
     *     operationId="admin_ai_controller_Ai_eventStream_get",
     *     tags={"AI 开发"},
     *     summary="使用事件票据和游标读取任务事件，返回 SSE 格式响应。",
     *     description="普通后台业务协议：成功 code=200；HTTP 200 也可能表示 code=400/422 的业务失败，请同时检查响应体 code。",
     *     security={{"AdminSession"={}}},
     *     @OA\Parameter(name="id", in="path", required=true, description="路由中的目标记录 ID。", @OA\Schema(type="integer", minimum=1)),
     *     @OA\Parameter(name="ticket", in="query", required=true, description="ticket 参数。", @OA\Schema(type="string")),
     *     @OA\Parameter(name="cursor", in="query", required=false, description="分页或事件游标。", @OA\Schema(type="integer")),
     *     @OA\Response(
     *         response=200,
     *         description="SSE 事件文本，每条事件包含 id、event 和 JSON data。",
     *         @OA\MediaType(mediaType="text/event-stream", @OA\Schema(type="string"))
     *     ),
     *     @OA\Response(response=401, ref="#/components/responses/AdminError"),
     *     @OA\Response(response=403, ref="#/components/responses/AdminError"),
     *     @OA\Response(response="default", ref="#/components/responses/AdminError")
     * )
     */
    #[Get('/tasks/{id:\d+}/events')]
    public function eventStream(int $id): Response
    {
        $this->ai->getTask($id, $this->adminId());
        $events = $this->events->read((string) $this->request->get('ticket', ''), $this->adminId(), $id, (int) $this->request->get('cursor', 0));
        $body = '';
        foreach ($events as $event) $body .= 'id: ' . $event['id'] . "\nevent: " . $event['type'] . "\ndata: " . json_encode($event['payload'], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR) . "\n\n";
        $response = response($body, 200);
        foreach (['Content-Type' => 'text/event-stream', 'Cache-Control' => 'no-cache, no-store', 'X-Accel-Buffering' => 'no'] as $name => $value) $response->header($name, $value);
        return $response;
    }

    /**
     * 获取当前管理员待处理的 AI 审批。
     *
     * @return Response JSON 格式的接口响应。
     *
     * @OA\Get(
     *     path="/admin/development/ai/approvals",
     *     operationId="admin_ai_controller_Ai_approvalIndex_get",
     *     tags={"AI 开发"},
     *     summary="获取当前管理员待处理的 AI 审批。",
     *     description="普通后台业务协议：成功 code=200；HTTP 200 也可能表示 code=400/422 的业务失败，请同时检查响应体 code。",
     *     security={{"AdminSession"={}}},
     *     @OA\Response(response=200, description="业务响应；以响应体 code 判断成功与失败。", @OA\JsonContent(ref="#/components/schemas/AdminResult")),
     *     @OA\Response(response=401, ref="#/components/responses/AdminError"),
     *     @OA\Response(response=403, ref="#/components/responses/AdminError"),
     *     @OA\Response(response="default", ref="#/components/responses/AdminError")
     * )
     */
    #[Get('/approvals')]
    public function approvalIndex(): Response { return $this->run(fn () => $this->approvals->pending($this->adminId())); }

    /**
     * 校验审批上下文后批准或拒绝工具调用，并处理任务续跑与沙箱收尾。
     *
     * @param int $id 路由中的目标记录 ID。
     * @return Response JSON 格式的接口响应。
     *
     * @OA\Post(
     *     path="/admin/development/ai/approvals/{id}/decision",
     *     operationId="admin_ai_controller_Ai_approvalDecide_post",
     *     tags={"AI 开发"},
     *     summary="校验审批上下文后批准或拒绝工具调用，并处理任务续跑与沙箱收尾。",
     *     description="普通后台业务协议：成功 code=200；HTTP 200 也可能表示 code=400/422 的业务失败，请同时检查响应体 code。",
     *     security={{"AdminSession"={}, "CsrfToken"={}}},
     *     @OA\Parameter(name="id", in="path", required=true, description="路由中的目标记录 ID。", @OA\Schema(type="integer", minimum=1)),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             allOf={
     *                 @OA\Schema(ref="#/components/schemas/AiApprovalInput"),
     *                 @OA\Schema(type="object", required={"action", "nonce", "digest", "casVersion", "mode"})
     *             }
     *         )
     *     ),
     *     @OA\Response(response=200, description="业务响应；以响应体 code 判断成功与失败。", @OA\JsonContent(ref="#/components/schemas/AdminResult")),
     *     @OA\Response(response=401, ref="#/components/responses/AdminError"),
     *     @OA\Response(response=403, ref="#/components/responses/AdminError"),
     *     @OA\Response(response=404, ref="#/components/responses/AdminError"),
     *     @OA\Response(response="default", ref="#/components/responses/AdminError")
     * )
     */
    #[Post('/approvals/{id:\d+}/decision')]
    public function approvalDecide(int $id): Response
    {
        return $this->run(function () use ($id): array {
            $input = $this->input();
            $adminId = $this->adminId();
            $pending = $this->security->approval($id, $adminId);
            if ($pending === null) throw new RuntimeException('资源不存在', 404);
            $task = $this->ai->getTask((int) $pending['task_id'], $adminId);
            try {
                $resume = new AiResumeOutboxService(
                    static fn (callable $operation) => Db::transaction($operation),
                    fn () => $this->approvals->decide($id, $adminId, (string)($input['action'] ?? ''), (string)($input['scope'] ?? 'once'), (string)($input['nonce'] ?? ''), (string)($input['digest'] ?? ''), (int)($input['casVersion'] ?? -1), (string)($input['mode'] ?? '')),
                    static fn (int $taskId, string $from, array $data): bool => AiTask::where('id', $taskId)->where('status', $from)->update($data) === 1,
                    static fn (array $event) => AiOutbox::create($event)
                );
                $approval = ($input['action'] ?? '') === 'approve'
                    ? $resume->approveAndSchedule($id, $adminId, $input, $task)
                    : $this->approvals->decide($id, $adminId, 'reject', (string)($input['scope'] ?? 'once'), (string)($input['nonce'] ?? ''), (string)($input['digest'] ?? ''), (int)($input['casVersion'] ?? -1), (string)($input['mode'] ?? ''));
            } catch (Throwable $exception) {
                $expired = $this->security->approval($id, $adminId);
                if (($expired['status'] ?? '') === 'expired') {
                    $this->finalizeRetainedSandbox($task, $adminId);
                    $this->security->updateToolCall((int)$pending['tool_call_id'], ['status'=>'denied','approval_decision'=>'expired','completed_at'=>date('Y-m-d H:i:s')]);
                }
                throw $exception;
            }
            if (($approval['status'] ?? '') !== 'approved') {
                $this->finalizeRetainedSandbox($task, $adminId);
                $this->security->updateToolCall((int)$pending['tool_call_id'], ['status'=>'denied','approval_decision'=>'denied','completed_at'=>date('Y-m-d H:i:s')]);
            }
            return $approval;
        });
    }

    /**
     * 验证任务归属后获取工具调用记录。
     *
     * @param int $id 路由中的目标记录 ID。
     * @return Response JSON 格式的接口响应。
     *
     * @OA\Get(
     *     path="/admin/development/ai/tasks/{id}/tool-calls",
     *     operationId="admin_ai_controller_Ai_taskToolCalls_get",
     *     tags={"AI 开发"},
     *     summary="验证任务归属后获取工具调用记录。",
     *     description="普通后台业务协议：成功 code=200；HTTP 200 也可能表示 code=400/422 的业务失败，请同时检查响应体 code。",
     *     security={{"AdminSession"={}}},
     *     @OA\Parameter(name="id", in="path", required=true, description="路由中的目标记录 ID。", @OA\Schema(type="integer", minimum=1)),
     *     @OA\Response(response=200, description="业务响应；以响应体 code 判断成功与失败。", @OA\JsonContent(ref="#/components/schemas/AdminResult")),
     *     @OA\Response(response=401, ref="#/components/responses/AdminError"),
     *     @OA\Response(response=403, ref="#/components/responses/AdminError"),
     *     @OA\Response(response=404, ref="#/components/responses/AdminError"),
     *     @OA\Response(response="default", ref="#/components/responses/AdminError")
     * )
     */
    #[Get('/tasks/{id:\d+}/tool-calls')]
    public function taskToolCalls(int $id): Response { return $this->run(function () use ($id): array { $this->ai->getTask($id, $this->adminId()); return $this->security->toolCalls($id); }); }

    /**
     * 验证任务归属及日志路径后读取 stdout 或 stderr 日志。
     *
     * @param int $id 路由中的目标记录 ID。
     * @param string $stream 日志流名称，仅允许 stdout 或 stderr。
     * @return Response JSON 格式的接口响应。
     *
     * @OA\Get(
     *     path="/admin/development/ai/tool-calls/{id}/logs/{stream}",
     *     operationId="admin_ai_controller_Ai_toolCallLog_get",
     *     tags={"AI 开发"},
     *     summary="验证任务归属及日志路径后读取 stdout 或 stderr 日志。",
     *     description="普通后台业务协议：成功 code=200；HTTP 200 也可能表示 code=400/422 的业务失败，请同时检查响应体 code。",
     *     security={{"AdminSession"={}}},
     *     @OA\Parameter(name="id", in="path", required=true, description="路由中的目标记录 ID。", @OA\Schema(type="integer", minimum=1)),
     *     @OA\Parameter(
     *         name="stream",
     *         in="path",
     *         required=true,
     *         description="日志流名称，仅允许 stdout 或 stderr。",
     *         @OA\Schema(type="string", pattern="^(?:stdout|stderr)$")
     *     ),
     *     @OA\Response(response=200, description="业务响应；以响应体 code 判断成功与失败。", @OA\JsonContent(ref="#/components/schemas/AiToolCallLogResult")),
     *     @OA\Response(response=401, ref="#/components/responses/AdminError"),
     *     @OA\Response(response=403, ref="#/components/responses/AdminError"),
     *     @OA\Response(response=404, ref="#/components/responses/AdminError"),
     *     @OA\Response(response="default", ref="#/components/responses/AdminError")
     * )
     */
    #[Get('/tool-calls/{id:\d+}/logs/{stream:stdout|stderr}')]
    public function toolCallLog(int $id, string $stream): Response
    {
        return $this->run(function () use ($id, $stream): array {
            $call = AiToolCall::find($id)?->toArray();
            if (!$call) throw new RuntimeException('资源不存在', 404);
            $this->ai->getTask((int)$call['task_id'], $this->adminId());
            $path = (string)($call[$stream . '_path'] ?? '');
            $root = realpath((string)config('ai.storage.log_path'));
            $real = $path !== '' ? realpath($path) : false;
            if ($root === false || $real === false || !str_starts_with($real, $root . DIRECTORY_SEPARATOR)) throw new RuntimeException('日志不存在', 404);
            return ['content'=>(string)file_get_contents($real),'hash'=>$call[$stream . '_hash'] ?? null];
        });
    }

    /**
     * 查询 AI 沙箱运行环境状态。
     *
     * @return Response JSON 格式的接口响应。
     *
     * @OA\Get(
     *     path="/admin/development/ai/sandbox/status",
     *     operationId="admin_ai_controller_Ai_sandboxStatus_get",
     *     tags={"AI 开发"},
     *     summary="查询 AI 沙箱运行环境状态。",
     *     description="普通后台业务协议：成功 code=200；HTTP 200 也可能表示 code=400/422 的业务失败，请同时检查响应体 code。",
     *     security={{"AdminSession"={}}},
     *     @OA\Response(response=200, description="业务响应；以响应体 code 判断成功与失败。", @OA\JsonContent(ref="#/components/schemas/AdminResult")),
     *     @OA\Response(response=401, ref="#/components/responses/AdminError"),
     *     @OA\Response(response=403, ref="#/components/responses/AdminError"),
     *     @OA\Response(response="default", ref="#/components/responses/AdminError")
     * )
     */
    #[Get('/sandbox/status')]
    public function sandboxStatus(): Response { return $this->run(fn () => $this->sandbox->status()); }

    /**
     * 清理当前管理员已结束且超过保留期的任务沙箱。
     *
     * @return Response JSON 格式的接口响应。
     *
     * @OA\Post(
     *     path="/admin/development/ai/sandbox/cleanup",
     *     operationId="admin_ai_controller_Ai_sandboxCleanup_post",
     *     tags={"AI 开发"},
     *     summary="清理当前管理员已结束且超过保留期的任务沙箱。",
     *     description="普通后台业务协议：成功 code=200；HTTP 200 也可能表示 code=400/422 的业务失败，请同时检查响应体 code。",
     *     security={{"AdminSession"={}, "CsrfToken"={}}},
     *     @OA\Response(response=200, description="业务响应；以响应体 code 判断成功与失败。", @OA\JsonContent(ref="#/components/schemas/AdminResult")),
     *     @OA\Response(response=401, ref="#/components/responses/AdminError"),
     *     @OA\Response(response=403, ref="#/components/responses/AdminError"),
     *     @OA\Response(response="default", ref="#/components/responses/AdminError")
     * )
     */
    #[Post('/sandbox/cleanup')]
    public function sandboxCleanup(): Response
    {
        return $this->run(function (): array {
            $adminId = $this->adminId();
            $cutoff = date('Y-m-d H:i:s', time() - (int) config('ai.sandbox.retention_seconds', 86400));
            $tasks = AiTask::sandboxCleanupCandidates($cutoff, $adminId);
            $cleaned = $this->sandbox->cleanupOrphans(
                $tasks,
                (int) config('ai.sandbox.retention_seconds', 86400),
                (int) config('ai.sandbox.heartbeat_timeout_seconds', 300),
                AiTask::finishSandboxCleanup(...),
                fn (string $event, array $data) => $this->securityAudit($event, array_merge($data, ['admin_id' => $adminId])),
                null,
                static fn (array $task, string $owner, int $expiresAt): bool => AiTask::claimSandboxCleanup((int) $task['id'], $owner, $expiresAt)
            );
            return ['cleaned' => $cleaned];
        });
    }

    /**
     * 读取当前管理员可见的变更集信息。
     *
     * @param int $id 路由中的目标记录 ID。
     * @return Response JSON 格式的接口响应。
     *
     * @OA\Get(
     *     path="/admin/development/ai/change-sets/{id}",
     *     operationId="admin_ai_controller_Ai_changeSetDetail_get",
     *     tags={"AI 开发"},
     *     summary="读取当前管理员可见的变更集信息。",
     *     description="普通后台业务协议：成功 code=200；HTTP 200 也可能表示 code=400/422 的业务失败，请同时检查响应体 code。",
     *     security={{"AdminSession"={}}},
     *     @OA\Parameter(name="id", in="path", required=true, description="路由中的目标记录 ID。", @OA\Schema(type="integer", minimum=1)),
     *     @OA\Response(response=200, description="业务响应；以响应体 code 判断成功与失败。", @OA\JsonContent(ref="#/components/schemas/AdminResult")),
     *     @OA\Response(response=401, ref="#/components/responses/AdminError"),
     *     @OA\Response(response=403, ref="#/components/responses/AdminError"),
     *     @OA\Response(response=404, ref="#/components/responses/AdminError"),
     *     @OA\Response(response="default", ref="#/components/responses/AdminError")
     * )
     */
    #[Get('/change-sets/{id:\d+}')]
    public function changeSetDetail(int $id): Response
    {
        return $this->run(function () use ($id): array {
            return $this->publicChangeSetRecord($this->changeSetRecord($id, $this->adminId()));
        });
    }

    /**
     * 检查预览权限后生成变更计划，并记录选择范围与计划摘要。
     *
     * @param int $id 路由中的目标记录 ID。
     * @return Response JSON 格式的接口响应。
     *
     * @OA\Post(
     *     path="/admin/development/ai/change-sets/{id}/preview",
     *     operationId="admin_ai_controller_Ai_changeSetPreview_post",
     *     tags={"AI 开发"},
     *     summary="检查预览权限后生成变更计划，并记录选择范围与计划摘要。",
     *     description="普通后台业务协议：成功 code=200；HTTP 200 也可能表示 code=400/422 的业务失败，请同时检查响应体 code。",
     *     security={{"AdminSession"={}, "CsrfToken"={}}},
     *     @OA\Parameter(name="id", in="path", required=true, description="路由中的目标记录 ID。", @OA\Schema(type="integer", minimum=1)),
     *     @OA\RequestBody(required=false, @OA\JsonContent(type="object", @OA\Property(property="selection", type="array", @OA\Items(type="string")))),
     *     @OA\Response(response=200, description="业务响应；以响应体 code 判断成功与失败。", @OA\JsonContent(ref="#/components/schemas/AdminResult")),
     *     @OA\Response(response=401, ref="#/components/responses/AdminError"),
     *     @OA\Response(response=403, ref="#/components/responses/AdminError"),
     *     @OA\Response(response=404, ref="#/components/responses/AdminError"),
     *     @OA\Response(response="default", ref="#/components/responses/AdminError")
     * )
     */
    #[Post('/change-sets/{id:\d+}/preview')]
    public function changeSetPreview(int $id): Response
    {
        return $this->run(function () use ($id): array {
            $adminId = $this->adminId();
            if (!$this->can('development:ai:changesetpreview')) throw new RuntimeException('无权预览 ChangeSet', 403);
            $record = $this->changeSetRecord($id, $adminId);
            $result = $this->changeSetService()->preview($record, $adminId, (array) $this->input()['selection'] ?? [], $this->can('development:ai:changesetapply'), (int) $record['conversation_id'], (int) $record['task_id']);
            $this->changeSetApplication()->recordPreview($id, $adminId, (array) ($result['selection'] ?? []), (string) ($result['planDigest'] ?? ''));
            $this->securityAudit('ai.change_set.preview', ['admin_id'=>$adminId,'conversation_id'=>(int)$record['conversation_id'],'task_id'=>(int)$record['task_id'],'change_set_id'=>$id,'plan_digest'=>$result['planDigest'] ?? null]);
            return $result;
        });
    }

    /**
     * 验证应用权限、最终审批及确认令牌后应用变更集。
     *
     * @param int $id 路由中的目标记录 ID。
     * @return Response JSON 格式的接口响应。
     *
     * @OA\Post(
     *     path="/admin/development/ai/change-sets/{id}/apply",
     *     operationId="admin_ai_controller_Ai_changeSetApply_post",
     *     tags={"AI 开发"},
     *     summary="验证应用权限、最终审批及确认令牌后应用变更集。",
     *     description="普通后台业务协议：成功 code=200；HTTP 200 也可能表示 code=400/422 的业务失败，请同时检查响应体 code。",
     *     security={{"AdminSession"={}, "CsrfToken"={}}},
     *     @OA\Parameter(name="id", in="path", required=true, description="路由中的目标记录 ID。", @OA\Schema(type="integer", minimum=1)),
     *     @OA\RequestBody(
     *         required=false,
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="selection", type="array", @OA\Items(type="string")),
     *             @OA\Property(property="confirmToken", type="string"),
     *             @OA\Property(property="finalApprovalId", type="integer")
     *         )
     *     ),
     *     @OA\Response(response=200, description="业务响应；以响应体 code 判断成功与失败。", @OA\JsonContent(ref="#/components/schemas/AdminResult")),
     *     @OA\Response(response=401, ref="#/components/responses/AdminError"),
     *     @OA\Response(response=403, ref="#/components/responses/AdminError"),
     *     @OA\Response(response=404, ref="#/components/responses/AdminError"),
     *     @OA\Response(response="default", ref="#/components/responses/AdminError")
     * )
     */
    #[Post('/change-sets/{id:\d+}/apply')]
    public function changeSetApply(int $id): Response
    {
        return $this->run(function () use ($id): array {
            $adminId = $this->adminId();
            if (!$this->can('development:ai:changesetapply')) throw new RuntimeException('无权应用 ChangeSet', 403);
            $record = $this->changeSetRecord($id, $adminId);
            $input = $this->input();
            $approvalId = (int) ($input['finalApprovalId'] ?? $record['final_approval_id'] ?? 0);
            $approval = AiApproval::where('id', $approvalId)->where('requested_by', $adminId)->where('operation', 'apply_workspace')
                ->where('conversation_id', (int) $record['conversation_id'])->where('task_id', (int) $record['task_id'])->first()?->toArray();
            if (!$approval) throw new RuntimeException('最终审批不存在或不属于当前 ChangeSet', 403);
            $changeSetService = $this->changeSetService();
            $changeSetService->assertFinalApproval($approval, $adminId, (int) $record['conversation_id'], (int) $record['task_id']);
            return $this->changeSetApplication()->run($id, $adminId, ['proposed', 'approved'], fn (): array =>
                $changeSetService->apply($record, $adminId, (array) ($input['selection'] ?? []), (string) ($input['confirmToken'] ?? ''), $approval, $this->changeSetTransaction(), (int) $record['conversation_id'], (int) $record['task_id']),
                ['final_approval_id' => $approvalId]
            );
        });
    }

    /**
     * 验证恢复权限及 recoveryVersion 后恢复变更集事务。
     *
     * @param int $id 路由中的目标记录 ID。
     * @return Response JSON 格式的接口响应。
     *
     * @OA\Post(
     *     path="/admin/development/ai/change-sets/{id}/recover",
     *     operationId="admin_ai_controller_Ai_changeSetRecover_post",
     *     tags={"AI 开发"},
     *     summary="验证恢复权限及 recoveryVersion 后恢复变更集事务。",
     *     description="普通后台业务协议：成功 code=200；HTTP 200 也可能表示 code=400/422 的业务失败，请同时检查响应体 code。",
     *     security={{"AdminSession"={}, "CsrfToken"={}}},
     *     @OA\Parameter(name="id", in="path", required=true, description="路由中的目标记录 ID。", @OA\Schema(type="integer", minimum=1)),
     *     @OA\RequestBody(required=false, @OA\JsonContent(type="object", @OA\Property(property="recoveryVersion", type="integer"))),
     *     @OA\Response(response=200, description="业务响应；以响应体 code 判断成功与失败。", @OA\JsonContent(ref="#/components/schemas/AdminResult")),
     *     @OA\Response(response=401, ref="#/components/responses/AdminError"),
     *     @OA\Response(response=403, ref="#/components/responses/AdminError"),
     *     @OA\Response(response=404, ref="#/components/responses/AdminError"),
     *     @OA\Response(response=409, ref="#/components/responses/AdminError"),
     *     @OA\Response(response="default", ref="#/components/responses/AdminError")
     * )
     */
    #[Post('/change-sets/{id:\d+}/recover')]
    public function changeSetRecover(int $id): Response
    {
        return $this->run(function () use ($id): array {
            $adminId = $this->adminId();
            if (!$this->can('development:ai:changesetrecover')) throw new RuntimeException('无权恢复 ChangeSet', 403);
            $record = $this->changeSetRecord($id, $adminId);
            $transactionId = (string) ($record['transaction_id'] ?? '');
            if ($transactionId === '') throw new RuntimeException('ChangeSet 缺少 transaction', 409);
            $result = $this->changeSetApplication()->recover(
                $id,
                $adminId,
                (int) ($this->input()['recoveryVersion'] ?? -1),
                fn (): array => $this->changeSetTransaction()->recover($transactionId)
            );
            $this->securityAudit('ai.change_set.recover', ['admin_id'=>$adminId,'conversation_id'=>(int)$record['conversation_id'],'task_id'=>(int)$record['task_id'],'change_set_id'=>$id,'transaction_id'=>$transactionId]);
            return $result;
        });
    }

    /**
     * 验证会话和任务归属后预览 AI 提交的 CRUD 生成提案。
     *
     * @return Response JSON 格式的接口响应。
     *
     * @OA\Post(
     *     path="/admin/development/ai/crud-proposals/preview",
     *     operationId="admin_ai_controller_Ai_crudProposalPreview_post",
     *     tags={"AI 开发"},
     *     summary="验证会话和任务归属后预览 AI 提交的 CRUD 生成提案。",
     *     description="普通后台业务协议：成功 code=200；HTTP 200 也可能表示 code=400/422 的业务失败，请同时检查响应体 code。",
     *     security={{"AdminSession"={}, "CsrfToken"={}}},
     *     @OA\RequestBody(
     *         required=false,
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="moduleId", type="integer"),
     *             @OA\Property(property="taskId", type="integer"),
     *             @OA\Property(property="conversationId", type="integer"),
     *             @OA\Property(property="proposal", ref="#/components/schemas/DynamicObject"),
     *             @OA\Property(property="nonce", type="string")
     *         )
     *     ),
     *     @OA\Response(response=200, description="业务响应；以响应体 code 判断成功与失败。", @OA\JsonContent(ref="#/components/schemas/AdminResult")),
     *     @OA\Response(response=401, ref="#/components/responses/AdminError"),
     *     @OA\Response(response=403, ref="#/components/responses/AdminError"),
     *     @OA\Response(response="default", ref="#/components/responses/AdminError")
     * )
     */
    #[Post('/crud-proposals/preview')]
    public function crudProposalPreview(): Response
    {
        return $this->run(function (): array {
            $adminId = $this->adminId();
            $input = $this->input();
            $moduleId = (int) ($input['moduleId'] ?? 0);
            $task = $this->assertOwnedTask((int) ($input['taskId'] ?? 0), (int) ($input['conversationId'] ?? 0), $adminId);
            $result = $this->crudProposalService()->preview((array) ($input['proposal'] ?? $input), $moduleId, $adminId, (int) $task['conversation_id'], $this->can('development:ai:crudproposalapply'), isset($input['nonce']) ? (string) $input['nonce'] : null);
            $this->securityAudit('ai.crud_proposal.preview', ['admin_id'=>$adminId,'conversation_id'=>(int)$task['conversation_id'],'task_id'=>(int)$task['id'],'module_id'=>$moduleId,'generation_id'=>$result['generationId'] ?? null]);
            return $result;
        });
    }

    /**
     * 检查提案应用权限及审批后执行 CRUD 生成提案。
     *
     * @return Response JSON 格式的接口响应。
     *
     * @OA\Post(
     *     path="/admin/development/ai/crud-proposals/apply",
     *     operationId="admin_ai_controller_Ai_crudProposalApply_post",
     *     tags={"AI 开发"},
     *     summary="检查提案应用权限及审批后执行 CRUD 生成提案。",
     *     description="普通后台业务协议：成功 code=200；HTTP 200 也可能表示 code=400/422 的业务失败，请同时检查响应体 code。",
     *     security={{"AdminSession"={}, "CsrfToken"={}}},
     *     @OA\RequestBody(
     *         required=false,
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="taskId", type="integer"),
     *             @OA\Property(property="conversationId", type="integer"),
     *             @OA\Property(property="generationId", type="integer"),
     *             @OA\Property(property="confirmToken", type="string"),
     *             @OA\Property(property="finalApprovalId", type="integer")
     *         )
     *     ),
     *     @OA\Response(response=200, description="业务响应；以响应体 code 判断成功与失败。", @OA\JsonContent(ref="#/components/schemas/AdminResult")),
     *     @OA\Response(response=401, ref="#/components/responses/AdminError"),
     *     @OA\Response(response=403, ref="#/components/responses/AdminError"),
     *     @OA\Response(response="default", ref="#/components/responses/AdminError")
     * )
     */
    #[Post('/crud-proposals/apply')]
    public function crudProposalApply(): Response
    {
        return $this->run(function (): array {
            $adminId = $this->adminId();
            if (!$this->can('development:ai:crudproposalapply')) throw new RuntimeException('无权应用 CRUD proposal', 403);
            $input = $this->input();
            $conversationId = (int) ($input['conversationId'] ?? 0);
            $task = $this->assertOwnedTask((int) ($input['taskId'] ?? 0), $conversationId, $adminId);
            $approvalId = (int) ($input['finalApprovalId'] ?? 0);
            $approval = AiApproval::where('id', $approvalId)->where('requested_by', $adminId)->where('operation', 'apply_workspace')->first()?->toArray() ?? [];
            $result = $this->crudProposalService()->apply($input, $adminId, $conversationId, $approval);
            $this->securityAudit('ai.crud_proposal.apply', ['admin_id'=>$adminId,'conversation_id'=>$conversationId,'task_id'=>(int)$task['id'],'generation_id'=>$input['generationId'] ?? null]);
            return $result;
        });
    }

    /**
     * 读取 AI 供应商设置及运行限制。
     *
     * @return Response JSON 格式的接口响应。
     *
     * @OA\Get(
     *     path="/admin/development/ai/settings",
     *     operationId="admin_ai_controller_Ai_settingsRead_get",
     *     tags={"AI 开发"},
     *     summary="读取 AI 供应商设置及运行限制。",
     *     description="普通后台业务协议：成功 code=200；HTTP 200 也可能表示 code=400/422 的业务失败，请同时检查响应体 code。",
     *     security={{"AdminSession"={}}},
     *     @OA\Response(response=200, description="业务响应；以响应体 code 判断成功与失败。", @OA\JsonContent(ref="#/components/schemas/AdminResult")),
     *     @OA\Response(response=401, ref="#/components/responses/AdminError"),
     *     @OA\Response(response=403, ref="#/components/responses/AdminError"),
     *     @OA\Response(response="default", ref="#/components/responses/AdminError")
     * )
     */
    #[Get('/settings')]
    public function settingsRead(): Response
    {
        return $this->ok(data: $this->providerSettings()->read((array) config('ai.limits', [])));
    }

    /**
     * 使用提交的临时供应商配置测试连接。
     *
     * @return Response JSON 格式的接口响应。
     *
     * @OA\Post(
     *     path="/admin/development/ai/settings/test",
     *     operationId="admin_ai_controller_Ai_settingsTest_post",
     *     tags={"AI 开发"},
     *     summary="使用提交的临时供应商配置测试连接。",
     *     description="普通后台业务协议：成功 code=200；HTTP 200 也可能表示 code=400/422 的业务失败，请同时检查响应体 code。",
     *     security={{"AdminSession"={}, "CsrfToken"={}}},
     *     @OA\RequestBody(
     *         required=false,
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="name", type="string", maxLength=100),
     *             @OA\Property(property="provider", type="string", maxLength=64),
     *             @OA\Property(property="protocol", type="string", enum={"openai-chat", "openai-responses", "anthropic-messages"}),
     *             @OA\Property(property="base_url", type="string", format="uri", maxLength=2048),
     *             @OA\Property(property="model", type="string", maxLength=200),
     *             @OA\Property(
     *                 property="api_key",
     *                 type="string",
     *                 description="省略保留密钥，空字符串清空，非空加密替换。",
     *                 format="password",
     *                 writeOnly=true,
     *                 maxLength=8192
     *             ),
     *             @OA\Property(property="max_output_tokens", type="integer", minimum=1, maximum=10000000, nullable=true),
     *             @OA\Property(property="connect_timeout", type="integer", minimum=1, maximum=30),
     *             @OA\Property(property="request_timeout", type="integer", minimum=1, maximum=300),
     *             @OA\Property(property="max_retries", type="integer", minimum=0, maximum=3)
     *         )
     *     ),
     *     @OA\Response(response=200, description="业务响应；以响应体 code 判断成功与失败。", @OA\JsonContent(ref="#/components/schemas/AdminResult")),
     *     @OA\Response(response=401, ref="#/components/responses/AdminError"),
     *     @OA\Response(response=403, ref="#/components/responses/AdminError"),
     *     @OA\Response(response="default", ref="#/components/responses/AdminError")
     * )
     */
    #[Post('/settings/test')]
    public function settingsTest(): Response
    {
        return $this->run(fn (): array => $this->providerSettings()->test($this->input()));
    }

    private function finalizeRetainedSandbox(array $task, int $adminId): void
    {
        $containerId = (string)($task['container_task_id'] ?? '');
        $workspace = (string)($task['workspace_path'] ?? '');
        if ($containerId === '' || $workspace === '') return;
        $hasChangeSet = (int)($task['change_set_id'] ?? 0) > 0;
        if (!$hasChangeSet) {
            $artifact = $this->sandbox->exportChanges($containerId, $workspace);
            $changeSet = AiChangeSet::create(array_merge(
                AiChangeSetService::attributes($task, $artifact, $adminId),
                ['created_at'=>date('Y-m-d H:i:s')]
            ));
            AiTask::where('id', (int)$task['id'])->update(['change_set_id'=>$changeSet->id,'sandbox_status'=>'exported']);
        }
        $this->sandbox->cleanup($containerId, $workspace, (int)$task['id'], (int)$task['conversation_id'], (string)($task['sandbox_volume'] ?? ''));
        AiTask::where('id', (int)$task['id'])->update(['sandbox_status'=>'cleaned','cleanup_at'=>date('Y-m-d H:i:s')]);
    }

    private function input(): array { $input = $this->request->post(); return is_array($input) ? $input : []; }
    private function assertOwnedTask(int $taskId, int $conversationId, int $adminId): array
    {
        if ($taskId <= 0 || $conversationId <= 0) throw new RuntimeException('资源不存在', 404);
        $task = $this->ai->getTask($taskId, $adminId);
        if ((int) ($task['conversation_id'] ?? 0) !== $conversationId) throw new RuntimeException('资源不存在', 404);
        return $task;
    }
    private function changeSetRecord(int $id, int $adminId): array { $record = AiChangeSet::where('id', $id)->where('created_by', $adminId)->first()?->toArray(); if (!$record) throw new RuntimeException('资源不存在', 404); return $record; }
    private function publicChangeSetRecord(array $record): array
    {
        unset($record['patch_path']);
        if (is_array($record['manifest'] ?? null)) {
            foreach (array_keys($record['manifest']) as $key) {
                if (str_ends_with((string) $key, '_path')) unset($record['manifest'][$key]);
            }
        }
        return $record;
    }
    private function changeSetApplication(): AiChangeSetApplicationService { return AiChangeSetApplicationService::production(); }
    private function providerSettings(): AiProviderSettingsService { return new AiProviderSettingsService((array) config('ai.provider', [])); }
    private function changeSetService(): AiChangeSetService { return new AiChangeSetService(base_path(), (string) config('ai.storage.private_path'), new \app\common\crud\ConfirmationToken(base_path()), fn (): int => $this->databaseMaximumMigration()); }
    private function databaseMaximumMigration(): int
    {
        $maximum = 0;
        foreach ((array) SystemMigration::ledger()->where('scope', 'core')->pluck('version')->all() as $version) {
            if (preg_match('/^(\d+)_/', (string) $version, $matches) === 1) $maximum = max($maximum, (int) $matches[1]);
        }
        return $maximum;
    }
    private function changeSetTransaction(): AiChangeSetTransactionService { return new AiChangeSetTransactionService(base_path(), (string) config('ai.storage.private_path')); }
    private function crudProposalService(): AiCrudProposalService { return AiCrudProposalService::production(base_path(), (array) config('crud.connections', [])); }
    private function securityAudit(string $event, array $payload): void
    {
        $taskId = (int) ($payload['task_id'] ?? 0);
        if ($taskId <= 0) throw new RuntimeException('审计事件缺少 task_id');
        (new DatabaseAiConversationStore())->appendEvent($taskId, $event, $payload);
    }
    private function can(string $capability): bool { return (new AdminAuthorizationService($this->request))->can($capability); }
    private function adminId(): int
    {
        $id = (int) (request()?->context['admin']['id'] ?? 0);
        if ($id <= 0) throw new RuntimeException('未登录', 401);
        return $id;
    }
    private function run(callable $operation): Response
    {
        try {
            return $this->ok(data: \app\admin\ai\service\AiAuditService::publicValue($operation()));
        } catch (Throwable $exception) {
            $expected = $exception instanceof \InvalidArgumentException || $exception instanceof RuntimeException;
            $code = $expected && in_array($exception->getCode(), [400, 401, 403, 404, 409, 413, 503], true) ? $exception->getCode() : 500;
            return $this->fail(msg: $exception->getMessage(), code: $code);
        }
    }
}
