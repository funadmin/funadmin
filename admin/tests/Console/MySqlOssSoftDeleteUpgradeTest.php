<?php

declare(strict_types=1);

namespace Tests\Console;

use app\admin\plugins\oss\model\OssProfile;
use app\admin\plugins\oss\service\ProfileService;
use app\admin\plugins\oss\service\Schema;
use app\common\model\concern\TokenSoftDeletingScope;
use app\common\service\database\MigrationService;
use PHPUnit\Framework\TestCase;
use support\Db;

// 插件源码在安装前位于 admin/plugins/oss；直接从源包加载类（不依赖已安装副本）。
spl_autoload_register(static function (string $class): void {
    $prefix = 'app\\admin\\plugins\\oss\\';
    if (str_starts_with($class, $prefix)) {
        $path = dirname(__DIR__, 2) . '/plugins/oss/app/admin/plugins/oss/' . str_replace('\\', '/', substr($class, strlen($prefix))) . '.php';
        if (is_file($path)) require_once $path;
    }
}, true, true);

/**
 * OSS 插件 002（软删除结构）在 MySQL 上的升级：从 001 带数据升级，配置行与原值保留，字符串主键不变，
 * oss_setting（豁免）不变且 current_profile_id 仍解析到原配置；重放 002 无变化；SQL 001–002 与 Schema::install() 结构一致（不含 003 的 path_style）。
 * 需要一次性 MySQL 测试库（tests/Console/run-mysql.php）；库里没有核心表时先执行核心 001–005。
 */
final class MySqlOssSoftDeleteUpgradeTest extends TestCase
{
    private const TABLES = ['oss_profile', 'oss_setting'];

    public function test_upgrade_from_001_with_data_keeps_rows(): void
    {
        if (getenv('CONSOLE_TEST_MYSQL') !== '1') self::markTestSkipped('需要一次性 MySQL 测试库');
        self::assertMatchesRegularExpression('/^webman_console_test_[a-f0-9]{12}$/D', Db::connection()->getDatabaseName());
        $root = dirname(__DIR__, 2) . '/plugins/oss/database/migrations';
        $service = new MigrationService();
        $this->drop();
        Schema::install();
        // Schema::install() 已包含 003 的 path_style（见 MySqlOssS3UpgradeTest）；这里只比较 001–002 的部分。
        $installed = $this->structure();
        $installed['oss_profile']['columns'] = array_values(array_filter($installed['oss_profile']['columns'], static fn (array $c): bool => $c['n'] !== 'path_style'));
        $this->drop();
        if (!Db::schema()->hasTable('system_migration')) {
            foreach (['001_baseline', '002_code_generator', '003_system_secret', '004_plugin_admin_web_directory', '005_soft_delete'] as $version) {
                $service->runVersion(MigrationService::coreDirectory(), $version);
            }
        }
        Db::table('system_migration')->where('scope', 'plugin:oss')->delete();
        self::assertSame(['001_oss'], $service->runVersion($root, '001_oss', 'plugin:oss'));
        self::assertFalse(Db::schema()->hasColumn('oss_profile', 'deleted_at'));

        [$current, $other] = ['0123456789abcdef', 'fedcba9876543210'];
        $profile = static fn (string $id, string $name, string $at): array => ['id' => $id, 'name' => $name, 'provider' => 'qiniu', 'bucket' => 'bucket-' . $name, 'region' => 'z0', 'endpoint' => '', 'domain' => 'https://cdn.example.com', 'prefix' => 'assets', 'credentials' => 'cipher-' . $name, 'created_at' => $at, 'updated_at' => $at];
        Db::table('oss_profile')->insert([$profile($current, 'current', '2026-01-02 03:04:05'), $profile($other, 'other', '2026-01-03 03:04:05')]);
        Db::table('oss_setting')->where('id', 1)->update(['current_profile_id' => $current]);
        $before = $this->dump();

        self::assertSame(['002_soft_delete'], $service->runVersion($root, '002_soft_delete', 'plugin:oss'));
        TokenSoftDeletingScope::flushDeletedTokenCache();
        $after = $this->dump();
        self::assertSame($before['oss_setting'], $after['oss_setting'], 'oss_setting 不变');
        self::assertSame($before['oss_profile'], array_map(static fn (array $row): array => array_diff_key($row, ['deleted_at' => true]), $after['oss_profile']), '配置行与原值保留');
        self::assertSame([null, null], array_column($after['oss_profile'], 'deleted_at'));
        $prefix = Db::connection()->getTablePrefix();
        $column = Db::selectOne("SELECT DATA_TYPE t, IS_NULLABLE n FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? AND COLUMN_NAME='deleted_at'", [$prefix . 'oss_profile']);
        self::assertSame(['datetime', 'YES'], [strtolower((string) $column->t), (string) $column->n]);
        self::assertFalse(Db::schema()->hasColumn('oss_profile', 'deleted_token'), '没有非主键唯一索引，不加 deleted_token');
        $primary = Db::selectOne("SELECT c.COLUMN_NAME n, c.COLUMN_TYPE t FROM information_schema.STATISTICS s JOIN information_schema.COLUMNS c ON c.TABLE_SCHEMA=s.TABLE_SCHEMA AND c.TABLE_NAME=s.TABLE_NAME AND c.COLUMN_NAME=s.COLUMN_NAME WHERE s.TABLE_SCHEMA=DATABASE() AND s.TABLE_NAME=? AND s.INDEX_NAME='PRIMARY'", [$prefix . 'oss_profile']);
        self::assertSame(['id', 'varchar(16)'], [(string) $primary->n, (string) $primary->t], '主键仍是字符串 id');
        foreach (['deleted_at', 'deleted_token'] as $column) self::assertFalse(Db::schema()->hasColumn('oss_setting', $column), 'oss_setting 豁免');

        $profiles = new ProfileService('');
        self::assertSame($current, $profiles->current(), 'current_profile_id 仍解析');
        self::assertSame([$other, $current], array_column($profiles->listing(), 'id'), '按创建时间倒序');
        self::assertTrue(OssProfile::query()->findOrFail($other)->delete());
        self::assertSame([$current], array_column($profiles->listing(), 'id'), '软删除的配置不再列出');
        self::assertSame($current, $profiles->current());
        self::assertTrue(OssProfile::withTrashed()->findOrFail($other)->restore());
        self::assertSame($after, $this->dump(), '恢复后与升级后一致');

        $data = $this->dump();
        $structure = $this->structure();
        Db::table('system_migration')->where('scope', 'plugin:oss')->where('version', '002_soft_delete')->delete();
        self::assertSame(['002_soft_delete'], $service->runVersion($root, '002_soft_delete', 'plugin:oss'));
        self::assertSame($data, $this->dump(), '重放 002 数据不变');
        self::assertSame($structure, $this->structure(), '重放 002 结构不变');
        self::assertSame($installed, $structure, 'SQL 001–002 与 Schema::install() 结构一致');

        $this->drop();
        Db::table('system_migration')->where('scope', 'plugin:oss')->delete();
    }

    private function drop(): void
    {
        foreach (array_reverse(self::TABLES) as $table) Db::schema()->dropIfExists($table);
    }

    /** @return array<string, list<array<string, mixed>>> */
    private function dump(): array
    {
        $result = [];
        foreach (self::TABLES as $table) $result[$table] = array_map(static fn ($row): array => (array) $row, Db::table($table)->orderBy('id')->get()->all());
        return $result;
    }

    /** 表结构的规范化描述：列（顺序、类型、可空、默认值）与索引（名称、唯一、列）。 */
    private function structure(): array
    {
        $prefix = Db::connection()->getTablePrefix();
        $result = [];
        foreach (self::TABLES as $table) {
            $columns = Db::select('SELECT COLUMN_NAME n, COLUMN_TYPE t, IS_NULLABLE nl, COLUMN_DEFAULT d, EXTRA e FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? ORDER BY ORDINAL_POSITION', [$prefix . $table]);
            $indexes = Db::select('SELECT INDEX_NAME n, NON_UNIQUE u, GROUP_CONCAT(COLUMN_NAME ORDER BY SEQ_IN_INDEX) c FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? GROUP BY INDEX_NAME, NON_UNIQUE ORDER BY INDEX_NAME', [$prefix . $table]);
            $result[$table] = ['columns' => array_map(static fn ($c): array => (array) $c, $columns), 'indexes' => array_map(static fn ($i): array => (array) $i, $indexes)];
        }
        return $result;
    }
}
