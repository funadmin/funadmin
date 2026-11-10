<?php

declare(strict_types=1);

namespace Tests\Console;

use app\common\service\database\MigrationService;
use PHPUnit\Framework\TestCase;
use support\Db;
use Tests\Support\SoftDeleteUpgradeFixture;

/** 005 在已有数据的 001–004 库上执行：行与原值保留、id 唯一、已软删除行 deleted_token = id，重放 005 无变化。需独立空库。 */
final class MySqlSoftDeleteUpgradeTest extends TestCase
{
    public function test_005_upgrades_populated_database_and_replays_as_no_op(): void
    {
        if (getenv('CONSOLE_TEST_MYSQL') !== '1') $this->markTestSkipped('需要一次性核心 MySQL 测试库');
        self::assertMatchesRegularExpression('/^webman_console_test_[a-f0-9]{12}$/D', Db::connection()->getDatabaseName());
        self::assertSame([], Db::connection()->select('SHOW TABLES'), '需要空库');
        $directory = MigrationService::coreDirectory();
        $service = new MigrationService();
        foreach (['001_baseline', '002_code_generator', '003_system_secret', '004_plugin_admin_web_directory'] as $version) {
            self::assertSame([$version], $service->runVersion($directory, $version));
        }
        self::assertFalse(Db::schema()->hasColumn('login_attempts', 'id'));
        $before = SoftDeleteUpgradeFixture::seed();
        self::assertCount(count(SoftDeleteUpgradeFixture::TABLES), $before);

        self::assertSame(['005_soft_delete'], $service->runVersion($directory, '005_soft_delete'));
        self::assertSame([], SoftDeleteUpgradeFixture::violations($before));
        $deleted = 0;
        foreach (SoftDeleteUpgradeFixture::dump() as $rows) {
            foreach ($rows as $row) if ($row['deleted_at'] !== null) $deleted++;
        }
        self::assertGreaterThan(5, $deleted, '夹具须包含已软删除的行');

        $data = SoftDeleteUpgradeFixture::dump();
        $schema = $this->schema();
        Db::table('system_migration')->where('scope', 'core')->where('version', '005_soft_delete')->delete();
        self::assertSame(['005_soft_delete'], $service->runVersion($directory, '005_soft_delete'));
        self::assertSame($data, SoftDeleteUpgradeFixture::dump(), '重放 005 数据不变');
        self::assertSame($schema, $this->schema(), '重放 005 结构不变');
    }

    private function schema(): array
    {
        $result = [];
        foreach (SoftDeleteUpgradeFixture::TABLES as $table) {
            $create = (array) Db::connection()->selectOne('SHOW CREATE TABLE `' . Db::connection()->getTablePrefix() . $table . '`');
            $result[$table] = preg_replace('/ AUTO_INCREMENT=\d+/', '', (string) $create['Create Table']);
        }
        return $result;
    }
}
