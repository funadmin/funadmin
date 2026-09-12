<?php

declare(strict_types=1);

namespace app\console\command;

use app\console\ai\infrastructure\NativeDockerProcessRunner;
use app\console\ai\model\AiTask;
use app\console\ai\repository\DatabaseAiConversationStore;
use app\console\ai\service\AgentSandboxManager;
use think\console\Command;
use think\console\Input;
use think\console\Output;
use Throwable;

/** 运维专用：清理所有管理员名下满足租约与保留策略的 AI sandbox。 */
final class AiSandboxCleanup extends Command
{
    protected function configure(): void
    {
        $this->setName('ai:sandbox-cleanup')->setDescription('全局清理已过期的终态 AI sandbox');
    }

    protected function execute(Input $input, Output $output): int
    {
        try {
            $retention = (int) config('ai.sandbox.retention_seconds', 86400);
            $cutoff = date('Y-m-d H:i:s', time() - $retention);
            $tasks = AiTask::whereIn('status', ['failed', 'cancelled', 'succeeded'])
                ->whereIn('sandbox_status', ['created', 'running', 'exported', 'failed', 'cleaning'])
                ->where('completed_at', '<=', $cutoff)
                ->select()->toArray();
            $store = new DatabaseAiConversationStore();
            $manager = new AgentSandboxManager(new NativeDockerProcessRunner(), root_path(), (string) config('ai.storage.private_path'), (array) config('ai.sandbox', []));
            $cleaned = $manager->cleanupOrphans(
                $tasks,
                $retention,
                (int) config('ai.sandbox.heartbeat_timeout_seconds', 300),
                static fn (int $taskId, array $data, string $owner) => AiTask::where('id', $taskId)
                    ->where('sandbox_status', 'cleaning')
                    ->where('cleanup_lease_owner', $owner)
                    ->update(array_merge($data, ['cleanup_lease_owner'=>null,'cleanup_lease_expires_at'=>null])) === 1,
                static fn (string $event, array $data) => $store->appendEvent((int) $data['task_id'], $event, array_merge($data, ['actor' => 'cli'])),
                null,
                static fn (array $task, string $owner, int $expiresAt): bool => AiTask::where('id', (int)$task['id'])
                    ->whereIn('sandbox_status', ['created','running','exported','failed','cleaning'])
                    ->where(static fn ($query) => $query->whereNull('cleanup_lease_expires_at')->whereOr('cleanup_lease_expires_at', '<=', date('Y-m-d H:i:s')))
                    ->update(['sandbox_status'=>'cleaning','cleanup_lease_owner'=>$owner,'cleanup_lease_expires_at'=>date('Y-m-d H:i:s', $expiresAt)]) === 1
            );
            $output->writeln(json_encode(['cleaned' => $cleaned, 'candidates' => count($tasks)], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));
            return 0;
        } catch (Throwable $exception) {
            $output->error($exception->getMessage());
            return 1;
        }
    }
}
