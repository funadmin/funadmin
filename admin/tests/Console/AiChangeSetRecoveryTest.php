<?php

declare(strict_types=1);

namespace Tests\Console;

use app\admin\ai\command\AiChangeSetRecover;
use app\admin\ai\service\AiChangeSetTransactionService;
use app\admin\ai\exception\AiChangeSetInterruptionException;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Tester\CommandTester;

final class AiChangeSetRecoveryTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        $this->root = sys_get_temp_dir() . '/funadmin-changeset-test-' . bin2hex(random_bytes(8));
        mkdir($this->root . '/private', 0700, true);
        file_put_contents($this->root . '/example.php', 'before');
    }

    protected function tearDown(): void
    {
        $files = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($this->root, \FilesystemIterator::SKIP_DOTS), \RecursiveIteratorIterator::CHILD_FIRST);
        foreach ($files as $file) $file->isDir() ? rmdir($file->getPathname()) : unlink($file->getPathname());
        rmdir($this->root);
    }

    private function interrupted(): string
    {
        $service = new AiChangeSetTransactionService($this->root, $this->root . '/private', static function (string $stage): void {
            if ($stage === 'after_file_rename') throw new AiChangeSetInterruptionException('模拟崩溃');
        });
        try {
            $service->execute(1, 7, 2, 3, ['files' => [[
                'path' => 'example.php', 'localHash' => hash('sha256', 'before'), 'status' => 'modify', 'content' => 'after', 'mergedHash' => hash('sha256', 'after'),
            ]]], ['status' => 'approved', 'operation' => 'apply_workspace', 'requested_by' => 7, 'decided_by' => 7, 'conversation_id' => 2, 'task_id' => 3]);
            self::fail('故障注入应中断事务');
        } catch (AiChangeSetInterruptionException) {
            self::assertSame('after', file_get_contents($this->root . '/example.php'));
        }
        return basename(glob($this->root . '/private/wal/*.json')[0], '.json');
    }

    public function test_recovery_command_rolls_back_proven_changes_and_is_idempotent(): void
    {
        $transaction = $this->interrupted();
        $tester = new CommandTester(new AiChangeSetRecover(new AiChangeSetTransactionService($this->root, $this->root . '/private')));
        self::assertSame(0, $tester->execute(['transaction' => $transaction]));
        self::assertSame('rolled_back', json_decode($tester->getDisplay(), true)['state']);
        self::assertSame('before', file_get_contents($this->root . '/example.php'));
        self::assertSame(0, $tester->execute(['transaction' => $transaction]));
        self::assertSame('before', file_get_contents($this->root . '/example.php'));
    }

    public function test_recovery_command_refuses_unknown_external_writes(): void
    {
        $transaction = $this->interrupted();
        file_put_contents($this->root . '/example.php', 'external-edit');
        $tester = new CommandTester(new AiChangeSetRecover(new AiChangeSetTransactionService($this->root, $this->root . '/private')));
        self::assertSame(1, $tester->execute(['transaction' => $transaction]));
        self::assertStringContainsString('人工恢复', $tester->getDisplay());
        self::assertSame('external-edit', file_get_contents($this->root . '/example.php'));
    }
}
