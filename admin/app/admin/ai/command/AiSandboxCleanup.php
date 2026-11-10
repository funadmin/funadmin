<?php

declare(strict_types=1);

namespace app\admin\ai\command;

use app\admin\ai\infrastructure\NativeDockerProcessRunner;
use app\admin\ai\model\AiTask;
use app\admin\ai\repository\DatabaseAiConversationStore;
use app\admin\ai\service\AgentSandboxManager;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface as Input;
use Symfony\Component\Console\Output\OutputInterface as Output;
use Symfony\Component\Console\Style\SymfonyStyle;
use Throwable;

/** 运维专用：清理所有管理员名下满足租约与保留策略的 AI sandbox。 */
#[AsCommand(name: 'ai:sandbox-cleanup')]
final class AiSandboxCleanup extends Command
{
    public function __construct(private readonly ?AgentSandboxManager $sandbox = null)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->setName('ai:sandbox-cleanup')->setDescription('全局清理已过期的终态 AI sandbox');
    }

    protected function execute(Input $input, Output $output): int
    {
        try {
            $retention = (int) config('ai.sandbox.retention_seconds', 86400);
            $cutoff = date('Y-m-d H:i:s', time() - $retention);
            $tasks = AiTask::sandboxCleanupCandidates($cutoff);
            $store = new DatabaseAiConversationStore();
            $manager = $this->sandbox ?? new AgentSandboxManager(new NativeDockerProcessRunner(), base_path() . DIRECTORY_SEPARATOR, (string) config('ai.storage.private_path'), (array) config('ai.sandbox', []));
            $failed = 0;
            $cleaned = $manager->cleanupOrphans(
                $tasks,
                $retention,
                (int) config('ai.sandbox.heartbeat_timeout_seconds', 300),
                AiTask::finishSandboxCleanup(...),
                static function (string $event, array $data) use ($store, &$failed): void {
                    if ($event === 'ai.sandbox.cleanup_failed') $failed++;
                    $store->appendEvent((int) $data['task_id'], $event, array_merge($data, ['actor' => 'cli']));
                },
                null,
                static fn (array $task, string $owner, int $expiresAt): bool => AiTask::claimSandboxCleanup((int) $task['id'], $owner, $expiresAt)
            );
            $result = ['cleaned' => $cleaned, 'candidates' => count($tasks)];
            if ($failed > 0) $result['failed'] = $failed;
            $output->writeln(json_encode($result, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));
            return $failed > 0 ? 1 : 0;
        } catch (Throwable $exception) {
            (new SymfonyStyle($input, $output))->error($exception->getMessage());
            return 1;
        }
    }
}
