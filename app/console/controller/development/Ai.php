<?php

declare(strict_types=1);

namespace app\console\controller\development;

use app\common\ai\provider\OpenAiCompatibleGateway;
use app\console\controller\base\AdminApiController;
use app\console\job\AiAgentJob;
use app\console\middleware\CheckAdminApiCsrf;
use app\console\middleware\CheckAdminApiRole;
use app\console\middleware\SystemLog;
use app\console\service\AiConversationService;
use app\console\service\AiEventStreamService;
use app\console\service\DatabaseAiConversationStore;
use GuzzleHttp\Client;
use RuntimeException;
use think\annotation\route\Delete;
use think\annotation\route\Get;
use think\annotation\route\Group;
use think\annotation\route\Pattern;
use think\annotation\route\Post;
use think\annotation\route\Put;
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

    public function __construct(\think\App $app)
    {
        parent::__construct($app);
        $store = new DatabaseAiConversationStore();
        $this->ai = new AiConversationService($store, (array) config('ai.limits', []));
        $this->events = new AiEventStreamService($store, (string) config('ai.stream.ticket_secret', ''));
    }

    #[Get('conversations')]
    public function conversationIndex(): Response { return $this->run(fn () => $this->ai->listConversations($this->adminId())); }
    #[Post('conversations')]
    public function conversationCreate(): Response { return $this->run(fn () => $this->ai->createConversation($this->adminId(), $this->input())); }
    #[Get('conversations/:id')]
    #[Pattern('id', '\d+')]
    public function conversationRead(int $id): Response { return $this->run(fn () => $this->ai->getConversation($id, $this->adminId())); }
    #[Put('conversations/:id')]
    #[Pattern('id', '\d+')]
    public function conversationUpdate(int $id): Response { return $this->run(fn () => $this->ai->updateConversation($id, $this->adminId(), $this->input())); }
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

    #[Get('settings')]
    public function settingsRead(): Response
    {
        $provider = (array) config('ai.provider', []);
        unset($provider['api_key']);
        return $this->ok(data: ['provider' => $provider, 'limits' => config('ai.limits', [])]);
    }

    #[Post('settings/test')]
    public function settingsTest(): Response
    {
        return $this->run(function (): array {
            $gateway = new OpenAiCompatibleGateway(new Client(), (array) config('ai.provider', []));
            $result = $gateway->chat([['role' => 'user', 'content' => 'Reply OK only.']]);
            return ['reachable' => true, 'model' => config('ai.provider.model'), 'finishReason' => $result['finishReason']];
        });
    }

    public function approvalDecide(): Response { return $this->notImplemented(); }
    public function changeSetApply(): Response { return $this->notImplemented(); }
    public function configurationUpdate(): Response { return $this->notImplemented(); }
    public function auditIndex(): Response { return $this->notImplemented(); }

    private function input(): array { $input = $this->request->post(); return is_array($input) ? $input : []; }
    private function adminId(): int { $id = (int) Session::get('admin.id', 0); if ($id <= 0) throw new RuntimeException('未登录', 401); return $id; }
    private function notImplemented(): Response { return $this->fail(msg: '该能力将在后续阶段实现', code: 501); }
    private function run(callable $operation): Response
    {
        try { return $this->ok(data: $operation()); } catch (Throwable $exception) { $code = in_array($exception->getCode(), [400, 401, 403, 404], true) ? $exception->getCode() : 400; return $this->fail(msg: $exception->getMessage(), code: $code); }
    }
}
