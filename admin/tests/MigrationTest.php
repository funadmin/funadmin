<?php

declare(strict_types=1);

namespace Tests;

use app\common\service\database\MigrationService;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\TestCase;
use support\Db;

#[RunTestsInSeparateProcesses]
#[PreserveGlobalState(false)]
final class MigrationTest extends TestCase
{
    public function test_migrations_are_repeatable_and_create_an_isolated_auth_schema(): void
    {
        self::assertTrue(class_exists(MigrationService::class), '独立迁移执行器尚未实现');
        $migrations = new MigrationService();
        $migrations->run();
        $migrations->run();
        self::assertTrue(Db::schema()->hasTable('admin'));
        self::assertTrue(Db::schema()->hasTable('login_attempts'));
        self::assertTrue(Db::schema()->hasTable('browser_sessions'));
        // 插件市场的后台接口权限按控制器方法推导并登记；迁移前的粗粒度权限码已停用。
        foreach (['market:plugin:index', 'market:plugin:publish', 'market:category:create', 'market:grant:index', 'market:order:index', 'market:order:confirm', 'market:setting:save-payment', 'market:stat:index'] as $code) {
            self::assertTrue(Db::table('permission')->where('code', $code)->where('source_type', 'plugin')->where('source_name', 'market')->where('status', 1)->exists(), $code);
        }
        foreach (['market:plugin:view', 'market:plugin:manage', 'market:order:view', 'market:stat:view'] as $code) {
            self::assertSame(0, (int) Db::table('permission')->where('code', $code)->value('status'), $code);
        }
        self::assertSame(count(glob(base_path() . '/database/migrations/*.php')), Db::table('migrations')->count());
        Db::table('login_attempts')->delete();
        Db::table('browser_sessions')->delete();
        self::assertSame(0, Db::table('login_attempts')->count());
    }

    public function test_executed_migration_checksum_cannot_be_changed(): void
    {
        (new MigrationService())->run();
        $name = Db::table('migrations')->value('name');
        $checksum = Db::table('migrations')->where('name', $name)->value('checksum');
        Db::table('migrations')->where('name', $name)->update(['checksum' => str_repeat('0', 64)]);
        try {
            $this->expectException(\RuntimeException::class);
            (new MigrationService())->run();
        } finally {
            Db::table('migrations')->where('name', $name)->update(['checksum' => $checksum]);
        }
    }
}
