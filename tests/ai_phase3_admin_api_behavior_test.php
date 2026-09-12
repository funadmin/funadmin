<?php

declare(strict_types=1);

require dirname(__DIR__) . '/vendor/autoload.php';

use app\console\ai\contract\AiConversationStore;
use app\console\ai\service\AiConversationService;
use app\console\middleware\CheckAdminApiRole;
use app\console\authorization\service\AdminAuthorizationService;
use app\console\authentication\service\AdminSessionService;
use think\App;
use think\exception\HttpResponseException;

function phase3AdminExpect(bool $condition, string $message): void
{
    if (!$condition) throw new RuntimeException($message);
}

final class Phase3AdminStore implements AiConversationStore
{
    public array $conversations = [];
    public array $tasks = [];
    private int $id = 1;

    public function createConversation(array $data): array { $data['id'] = $this->id++; return $this->conversations[$data['id']] = $data; }
    public function conversations(int $adminId): array { return array_values(array_filter($this->conversations, fn (array $row): bool => $row['admin_id'] === $adminId)); }
    public function conversation(int $id, int $adminId): ?array { $row = $this->conversations[$id] ?? null; return $row && $row['admin_id'] === $adminId ? $row : null; }
    public function updateConversation(int $id, int $adminId, array $data): bool { if (!isset($this->conversations[$id]) || $this->conversations[$id]['admin_id'] !== $adminId) return false; $this->conversations[$id] = array_replace($this->conversations[$id], $data); return true; }
    public function deleteConversation(int $id, int $adminId): bool { return false; }
    public function appendMessage(int $conversationId, array $data): array { return $data; }
    public function messages(int $conversationId): array { return []; }
    public function createTask(array $data): array { $data['id'] = $this->id++; return $this->tasks[$data['id']] = $data; }
    public function task(int $id): ?array { return $this->tasks[$id] ?? null; }
    public function compareAndSetTask(int $id, array $from, array $data): bool { return false; }
public function compareAndSetTaskOperation(int $id, string $operationToken, array $from, array $data): bool { return false; }
    public function updateTask(int $id, array $data): void {}
    public function appendEvent(int $taskId, string $type, array $payload): array { return []; }
    public function events(int $taskId, int $afterId, int $limit): array { return []; }
    public function consumeNonce(string $nonce, int $expiresAt): bool { return false; }
}

$store = new Phase3AdminStore();
$auditEvents = [];
$service = new AiConversationService($store, ['max_rounds' => 1], static function (string $event, array $payload) use (&$auditEvents): void { $auditEvents[] = compact('event', 'payload'); });
$conversation = $service->createConversation(7, ['title' => 'owner']);
foreach ([[['approval_mode'=>'full_access'],false,true,'full_access 必须要求独立 capability'],[['approval_mode'=>'agent_approval'],false,false,'agent_approval 升级必须要求 approve capability']] as [$input,$full,$approve,$message]) {
    $denied = false;
    try { $service->createConversation(7, $input, $full, $approve); } catch (RuntimeException $exception) { $denied = $exception->getCode() === 403; }
    phase3AdminExpect($denied, $message);
}
$fullConversation = $service->createConversation(7, ['approval_mode'=>'full_access'], true, true);
phase3AdminExpect($fullConversation['approval_mode'] === 'full_access', '服务端授权后才可创建 full_access 会话');
$denied = false;
try { $service->updateConversation($conversation['id'], 7, ['approval_mode'=>'full_access'], false, true); } catch (RuntimeException $exception) { $denied = $exception->getCode() === 403; }
phase3AdminExpect($denied && $store->conversations[$conversation['id']]['approval_mode'] === 'request_approval', '更新不得相信客户端 full_access');
$updated = $service->updateConversation($conversation['id'], 7, ['approval_mode'=>'agent_approval'], false, true);
phase3AdminExpect($updated['approval_mode'] === 'agent_approval', '已有 approve capability 才可升级 agent_approval');
phase3AdminExpect(count(array_filter($auditEvents, static fn (array $row): bool => $row['event'] === 'ai.full_access.denied')) >= 2, 'full access 拒绝必须审计');
$task = $service->createTask($conversation['id'], 7, ['idempotency_key' => 'phase3-owner', 'type' => 'chat']);
phase3AdminExpect($service->getTask($task['id'], 7)['id'] === $task['id'], '实际管理员必须能读取自己的任务');
$crossAdminDenied = false;
try {
    $service->getTask($task['id'], 8);
} catch (RuntimeException $exception) {
    $crossAdminDenied = $exception->getCode() === 404;
}
phase3AdminExpect($crossAdminDenied, '跨管理员读取任务必须按 404 拒绝且不泄露存在性');

$sessionStub = static fn (bool $loggedIn): AdminSessionService => new class ($loggedIn) extends AdminSessionService {
    public function __construct(private readonly bool $loggedIn) {}
    public function isLogin(): bool { return $this->loggedIn; }
};
$authorizationStub = static fn (bool $allowed): AdminAuthorizationService => new class ($allowed) extends AdminAuthorizationService {
    public array $authenticated = [];
    public function __construct(private readonly bool $allowed) {}
    public function roleAccess(bool $authenticated = false): bool
    {
        $this->authenticated[] = $authenticated;
        if (!$this->allowed) throw new HttpResponseException(json(['code' => 403], 403));
        return true;
    }
};
$assertStatus = static function (object $response, int $status): void {
    phase3AdminExpect($response->getCode() === $status, "权限中间件 HTTP 状态必须为 {$status}");
    $body = json_decode($response->getContent(), true, 512, JSON_THROW_ON_ERROR);
    phase3AdminExpect(($body['code'] ?? null) === $status, "权限中间件响应 code 必须为 {$status}");
};

$app = new App();
$app->initialize();
$handlerCalls = 0;
$next = static function () use (&$handlerCalls): object { $handlerCalls++; return json(['code' => 200], 200); };
$authorization = $authorizationStub(true);
$assertStatus((new CheckAdminApiRole($sessionStub(false), $authorization))->handle(request(), $next), 401);
phase3AdminExpect($authorization->authenticated === [] && $handlerCalls === 0, '未登录不得授权或进入阶段三 API');
$authorization = $authorizationStub(false);
$assertStatus((new CheckAdminApiRole($sessionStub(true), $authorization))->handle(request(), $next), 403);
phase3AdminExpect($authorization->authenticated === [true] && $handlerCalls === 0, '无权限管理员不得进入阶段三 API');
$authorization = $authorizationStub(true);
$assertStatus((new CheckAdminApiRole($sessionStub(true), $authorization))->handle(request(), $next), 200);
phase3AdminExpect($authorization->authenticated === [true] && $handlerCalls === 1, '仅已认证且已授权管理员可进入阶段三 API');

echo "AI phase 3 admin API behavior tests: PASS\n";
