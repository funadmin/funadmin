<?php

declare(strict_types=1);

namespace Tests\Console;

use app\admin\development\service\GenerationResourceTransaction;
use app\common\service\MigrationService;
use PHPUnit\Framework\TestCase;
use support\Db;

#[\PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses]
#[\PHPUnit\Framework\Attributes\PreserveGlobalState(false)]
final class BusinessGenerationResourceTest extends TestCase
{
    public function test_real_permissions_menus_and_language_lines_share_commit_and_rollback(): void
    {
        self::assertSame(':memory:', Db::connection()->getDatabaseName());
        (new MigrationService())->run();
        if (!Db::schema()->hasTable('language_line')) {
            Db::schema()->create('language_line', static function (\Illuminate\Database\Schema\Blueprint $table): void {
                $table->id(); $table->string('locale'); $table->string('key'); $table->text('value'); $table->timestamps(); $table->unique(['locale', 'key']);
            });
        }
        $resources = [
            ['resourceKey' => 'permission|console-recovery|generated:console-recovery:list', 'resourceType' => 'permission', 'sourceName' => 'console-recovery', 'code' => 'generated:console-recovery:list', 'obj' => 'generated/console-recovery', 'act' => 'list', 'permissionType' => 'capability', 'name' => '测试权限'],
            ['resourceKey' => 'menu|console-recovery|/generated/console-recovery', 'resourceType' => 'menu', 'sourceName' => 'console-recovery', 'name' => '测试菜单', 'href' => '/generated/console-recovery', 'permission' => 'generated:console-recovery:list'],
            ['resourceKey' => 'language|console-recovery|console.recovery.title', 'resourceType' => 'language', 'sourceName' => 'console-recovery', 'locale' => 'zh-cn', 'langKey' => 'console.recovery.title', 'value' => '测试标题'],
        ];
        $transaction = new GenerationResourceTransaction();
        $transaction->begin();
        self::assertSame(['inserted' => 1, 'skipped' => 0], $transaction->apply($resources));
        self::assertSame(1, Db::table('permission')->where('code', 'generated:console-recovery:list')->count());
        self::assertSame(1, Db::table('admin_menu')->where('source_name', 'console-recovery')->count());
        $transaction->rollback();
        self::assertSame(0, Db::table('permission')->where('code', 'generated:console-recovery:list')->count());
        self::assertSame(0, Db::table('admin_menu')->where('source_name', 'console-recovery')->count());
        self::assertSame(0, Db::table('language_line')->where('key', 'console.recovery.title')->count());
        $transaction->begin();
        $transaction->apply($resources);
        $transaction->commit();
        self::assertSame(1, Db::table('permission')->where('code', 'generated:console-recovery:list')->count());
        self::assertSame(1, Db::table('language_line')->where('key', 'console.recovery.title')->count());
        $transaction->begin();
        self::assertSame(['inserted' => 0, 'skipped' => 1], $transaction->apply($resources));
        $transaction->commit();
        self::assertSame(1, Db::table('admin_menu')->where('source_name', 'console-recovery')->whereNull('deleted_at')->count());
        self::assertSame(0, Db::connection()->transactionLevel());
    }
}
