<?php

declare(strict_types=1);

namespace app\console\controller\ai;

use app\console\controller\base\AdminApiController;
use app\console\ai\job\AiAgentJob;
use app\console\middleware\CheckAdminApiCsrf;
use app\console\middleware\CheckAdminApiRole;
use app\console\middleware\SystemLog;
use app\console\ai\infrastructure\NativeDockerProcessRunner;
use app\console\ai\model\AiApproval;
use app\console\ai\model\AiChangeSet;
use app\console\ai\model\AiOutbox;
use app\console\ai\model\AiTask;
use app\console\ai\model\AiToolCall;
use app\console\ai\repository\DatabaseAiConversationStore;
use app\console\ai\repository\DatabaseAiSecurityStore;
use app\console\ai\service\AgentSandboxManager;
use app\console\ai\service\AiApprovalService;
use app\console\ai\service\AiChangeSetApplicationService;
use app\console\ai\service\AiChangeSetService;
use app\console\ai\service\AiChangeSetTransactionService;
use app\common\model\SystemMigration;
use app\console\ai\service\AiConversationService;
use app\console\ai\service\AiCrudProposalService;
use app\console\ai\service\AiEventStreamService;
use app\console\ai\service\AiProviderSettingsService;
use app\console\ai\service\AiResumeOutboxService;
use app\console\authorization\service\AdminAuthorizationService;
use RuntimeException;
use think\annotation\route\Delete;
use think\annotation\route\Get;
use think\annotation\route\Group;
use think\annotation\route\Pattern;
use think\annotation\route\Post;
use think\annotation\route\Put;
use think\facade\Db;
use think\facade\Queue;
use think\facade\Session;
use think\Response;
use Throwable;

/** AI 开发助手阶段二 Admin API。 */
#[Group('development/ai')]
final class Ai extends AdminApiController
{
    protected array $middleware = [CheckAdminApiRole::class, CheckAdminApiCsrf::class, SystemLog::class];
    private readonly AiConversationService $ai;
    private readonly AiEventStreamService $events;
    private readonly DatabaseAiSecurityStore $security;
    private readonly AiApprovalService $approvals;
    private readonly AgentSandboxManager $sandbox;

    public function __construct(\think\App $app)
    {
        parent::__construct($app);
        $store = new DatabaseAiConversationStore();
        $this->ai = new AiConversationService($store, (array) config('ai.limits', []));
        $this->events = new AiEventStreamService($store, (string) config('ai.stream.ticket_secret', ''));
        $this->security = new DatabaseAiSecurityStore();
        $this->approvals = new AiApprovalService($this->security);
        $this->sandbox = new AgentSandboxManager(new NativeDockerProcessRunner(), root_path(), (string) config('ai.storage.private_path'), (array) config('ai.sandbox', []));
    }

    #[Get('conversations')]
    public function conversationIndex(): Response { return $this->run(fn () => $this->ai->listConversations($this->adminId())); }
    #[Post('conversations')]
    public function conversationCreate(): Response { return $this->run(fn () => $this->ai->createConversation($this->adminId(), $this->input(), $this->can('development:ai:full-access'), $this->can('development:ai:approve'))); }
    #[Get('conversations/:id')]
    #[Pattern('id', '\d+')]
    public function conversationRead(int $id): Response { return $this->run(fn () => $this->ai->getConversation($id, $this->adminId())); }
    #[Put('conversations/:id')]
    #[Pattern('id', '\d+')]
    public function conversationUpdate(int $id): Response { return $this->run(fn () => $this->ai->updateConversation($id, $this->adminId(), $this->input(), $this->can('development:ai:full-access'), $this->can('development:ai:approve'))); }
    #[Delete('conversations/:id')]
    #[Pattern('id', '\d+')]
    public function conversationDelete(int $id): Response { return $this->run(fn () => ['deleted' => $this->ai->deleteConversation($id, $this->adminId())]); }
    #[Get('conversations/:id/messages')]
    #[Pattern('id', '\d+')]
    public function messageIndex(int $id): Response { return $this->run(fn () => $this->ai->listMessages($id, $this->adminId())); }
    #[Post('conversations/:id/messages')]
    #[Pattern('id', '\d+')]
    public function messageCreate(int $id): Response { return $this->run(fn () => $this->ai->appendMessage($id, $this->adminId(), $this->input())); }

    #[Post('conversations/:id/tasks')]
    #[Pattern('id', '\d+')]
    public function taskExecute(int $id): Response
    {
        return $this->run(function () use ($id): array {
            $task = $this->ai->createTask($id, $this->adminId(), $this->input());
            Queue::connection('ai-agent')->push(AiAgentJob::class . '@fire', ['taskId' => $task['id'], 'operationToken' => $task['operation_token']], 'ai-agent');
            return $task;
        });
    }

    #[Get('tasks/:id')]
    #[Pattern('id', '\d+')]
    public function taskRead(int $id): Response { return $this->run(fn () => $this->ai->getTask($id, $this->adminId())); }

    #[Post('tasks/:id/cancel')]
    #[Pattern('id', '\d+')]
    public function taskCancel(int $id): Response { return $this->run(fn () => ['cancelled' => $this->ai->cancelTask($id, $this->adminId())]); }
    #[Post('tasks/:id/events/ticket')]
    #[Pattern('id', '\d+')]
    public function eventTicket(int $id): Response { return $this->run(function () use ($id): array { $this->ai->getTask($id, $this->adminId()); return ['ticket' => $this->events->issueTicket($this->adminId(), $id)]; }); }

    #[Get('tasks/:id/events')]
    #[Pattern('id', '\d+')]
    public function eventStream(int $id): Response
    {
        $this->ai->getTask($id, $this->adminId());
        $events = $this->events->read((string) $this->request->get('ticket', ''), $this->adminId(), $id, (int) $this->request->get('cursor', 0));
        $body = '';
        foreach ($events as $event) $body .= 'id: ' . $event['id'] . "\nevent: " . $event['type'] . "\ndata: " . json_encode($event['payload'], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR) . "\n\n";
        return response($body, 200)->header(['Content-Type' => 'text/event-stream', 'Cache-Control' => 'no-cache, no-store', 'X-Accel-Buffering' => 'no']);
    }

    #[Get('approvals')]
    public function approvalIndex(): Response { return $this->run(fn () => $this->approvals->pending($this->adminId())); }

    #[Post('approvals/:id/decision')]
    #[Pattern('id', '\\d+')]
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

    #[Get('tasks/:id/tool-calls')]
    #[Pattern('id', '\\d+')]
    public function taskToolCalls(int $id): Response { return $this->run(function () use ($id): array { $this->ai->getTask($id, $this->adminId()); return $this->security->toolCalls($id); }); }

    #[Get('tool-calls/:id/logs/:stream')]
    #[Pattern('id', '\\d+')]
    #[Pattern('stream', 'stdout|stderr')]
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

    #[Get('sandbox/status')]
    public function sandboxStatus(): Response { return $this->run(fn () => $this->sandbox->status()); }

    #[Post('sandbox/cleanup')]
    public function sandboxCleanup(): Response
    {
        return $this->run(function (): array {
            $adminId = $this->adminId();
            $cutoff = date('Y-m-d H:i:s', time() - (int) config('ai.sandbox.retention_seconds', 86400));
            $tasks = AiTask::hasWhere('conversation', static fn ($query) => $query->where('admin_id', $adminId))
                ->whereIn('status', ['failed', 'cancelled', 'succeeded'])
                ->whereIn('sandbox_status', ['created', 'running', 'exported', 'failed', 'cleaning'])
                ->where('completed_at', '<=', $cutoff)
                ->select()->toArray();
            $cleaned = $this->sandbox->cleanupOrphans(
                $tasks,
                (int) config('ai.sandbox.retention_seconds', 86400),
                (int) config('ai.sandbox.heartbeat_timeout_seconds', 300),
                static fn (int $taskId, array $data, string $owner) => AiTask::where('id', $taskId)
                    ->where('sandbox_status', 'cleaning')
                    ->where('cleanup_lease_owner', $owner)
                    ->update(array_merge($data, ['cleanup_lease_owner'=>null,'cleanup_lease_expires_at'=>null])) === 1,
                fn (string $event, array $data) => $this->securityAudit($event, array_merge($data, ['admin_id' => $adminId])),
                null,
                static fn (array $task, string $owner, int $expiresAt): bool => AiTask::where('id', (int)$task['id'])
                    ->whereIn('sandbox_status', ['created','running','exported','failed','cleaning'])
                    ->where(static fn ($query) => $query->whereNull('cleanup_lease_expires_at')->whereOr('cleanup_lease_expires_at', '<=', date('Y-m-d H:i:s')))
                    ->update(['sandbox_status'=>'cleaning','cleanup_lease_owner'=>$owner,'cleanup_lease_expires_at'=>date('Y-m-d H:i:s', $expiresAt)]) === 1
            );
            return ['cleaned' => $cleaned];
        });
    }

    #[Get('change-sets/:id')]
    #[Pattern('id', '\\d+')]
    public function changeSetDetail(int $id): Response
    {
        return $this->run(function () use ($id): array {
            return $this->publicChangeSetRecord($this->changeSetRecord($id, $this->adminId()));
        });
    }

    #[Post('change-sets/:id/preview')]
    #[Pattern('id', '\\d+')]
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

    #[Post('change-sets/:id/apply')]
    #[Pattern('id', '\\d+')]
    public function changeSetApply(int $id): Response
    {
        return $this->run(function () use ($id): array {
            $adminId = $this->adminId();
            if (!$this->can('development:ai:changesetapply')) throw new RuntimeException('无权应用 ChangeSet', 403);
            $record = $this->changeSetRecord($id, $adminId);
            $input = $this->input();
            $approvalId = (int) ($input['finalApprovalId'] ?? $record['final_approval_id'] ?? 0);
            $approval = AiApproval::where('id', $approvalId)->where('requested_by', $adminId)->where('operation', 'apply_workspace')
                ->where('conversation_id', (int) $record['conversation_id'])->where('task_id', (int) $record['task_id'])->find()?->toArray();
            if (!$approval) throw new RuntimeException('最终审批不存在或不属于当前 ChangeSet', 403);
            $changeSetService = $this->changeSetService();
            $changeSetService->assertFinalApproval($approval, $adminId, (int) $record['conversation_id'], (int) $record['task_id']);
            return $this->changeSetApplication()->run($id, $adminId, ['proposed', 'approved'], fn (): array =>
                $changeSetService->apply($record, $adminId, (array) ($input['selection'] ?? []), (string) ($input['confirmToken'] ?? ''), $approval, $this->changeSetTransaction(), (int) $record['conversation_id'], (int) $record['task_id']),
                ['final_approval_id' => $approvalId]
            );
        });
    }

    #[Post('change-sets/:id/recover')]
    #[Pattern('id', '\\d+')]
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

    #[Post('crud-proposals/preview')]
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

    #[Post('crud-proposals/apply')]
    public function crudProposalApply(): Response
    {
        return $this->run(function (): array {
            $adminId = $this->adminId();
            if (!$this->can('development:ai:crudproposalapply')) throw new RuntimeException('无权应用 CRUD proposal', 403);
            $input = $this->input();
            $conversationId = (int) ($input['conversationId'] ?? 0);
            $task = $this->assertOwnedTask((int) ($input['taskId'] ?? 0), $conversationId, $adminId);
            $approvalId = (int) ($input['finalApprovalId'] ?? 0);
            $approval = AiApproval::where('id', $approvalId)->where('requested_by', $adminId)->where('operation', 'apply_workspace')->find()?->toArray() ?? [];
            $result = $this->crudProposalService()->apply($input, $adminId, $conversationId, $approval);
            $this->securityAudit('ai.crud_proposal.apply', ['admin_id'=>$adminId,'conversation_id'=>$conversationId,'task_id'=>(int)$task['id'],'generation_id'=>$input['generationId'] ?? null]);
            return $result;
        });
    }

    #[Get('settings')]
    public function settingsRead(): Response
    {
        return $this->ok(data: $this->providerSettings()->read((array) config('ai.limits', [])));
    }

    #[Post('settings/test')]
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
    private function changeSetRecord(int $id, int $adminId): array { $record = AiChangeSet::where('id', $id)->where('created_by', $adminId)->find()?->toArray(); if (!$record) throw new RuntimeException('资源不存在', 404); return $record; }
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
    private function changeSetService(): AiChangeSetService { return new AiChangeSetService(root_path(), (string) config('ai.storage.private_path'), new \app\common\crud\ConfirmationToken(root_path()), fn (): int => $this->databaseMaximumMigration()); }
    private function databaseMaximumMigration(): int
    {
        $maximum = 0;
        foreach ((array) SystemMigration::where('scope', 'core')->column('version') as $version) {
            if (preg_match('/^(\d+)_/', (string) $version, $matches) === 1) $maximum = max($maximum, (int) $matches[1]);
        }
        return $maximum;
    }
    private function changeSetTransaction(): AiChangeSetTransactionService { return new AiChangeSetTransactionService(root_path(), (string) config('ai.storage.private_path')); }
    private function crudProposalService(): AiCrudProposalService { return AiCrudProposalService::production(root_path(), (array) config('crud.connections', [])); }
    private function securityAudit(string $event, array $payload): void
    {
        $taskId = (int) ($payload['task_id'] ?? 0);
        if ($taskId <= 0) throw new RuntimeException('审计事件缺少 task_id');
        (new DatabaseAiConversationStore())->appendEvent($taskId, $event, $payload);
    }
    private function can(string $capability): bool { return (new AdminAuthorizationService($this->request))->nodeAccess(str_replace(':', '/', $capability)); }
    private function adminId(): int { $id = (int) Session::get('admin.id', 0); if ($id <= 0) throw new RuntimeException('未登录', 401); return $id; }
    private function run(callable $operation): Response
    {
        try { return $this->ok(data: $operation()); } catch (Throwable $exception) { $code = in_array($exception->getCode(), [400, 401, 403, 404, 409], true) ? $exception->getCode() : 400; return $this->fail(msg: $exception->getMessage(), code: $code); }
    }
}
