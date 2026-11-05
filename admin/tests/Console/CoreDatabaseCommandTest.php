<?php

declare(strict_types=1);

namespace Tests\Console;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Tester\CommandTester;

final class CoreDatabaseCommandTest extends TestCase
{
    public function test_command_requires_explicit_action_and_private_reference_without_connecting(): void
    {
        self::assertTrue(class_exists(\app\admin\system\command\CoreDatabase::class), '缺少核心数据库运维命令');
        $command = new CommandTester(new \app\admin\system\command\CoreDatabase());
        self::assertSame(1, $command->execute(['action' => 'invalid']));
        self::assertStringContainsString('操作', $command->getDisplay());
        self::assertSame(1, $command->execute(['action' => 'preflight', '--reference' => '/outside/reference.json']));
        self::assertStringContainsString('私有', $command->getDisplay());
        self::assertSame(1, $command->execute(['action' => 'apply']));
        self::assertStringContainsString('私有', $command->getDisplay());
    }
}
