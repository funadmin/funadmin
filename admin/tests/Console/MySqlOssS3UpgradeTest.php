<?php

declare(strict_types=1);

namespace Tests\Console;

use app\admin\plugins\oss\model\OssProfile;
use app\admin\plugins\oss\model\OssSetting;
use app\admin\plugins\oss\service\ProfileService;
use app\admin\plugins\oss\service\Schema;
use app\common\model\SystemMigration;
use app\common\service\database\MigrationService;
use Defuse\Crypto\Key;
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
 * OSS 插件 003（S3 兼容驱动，1.2.0）在 MySQL 上的升级：从 002 带数据升级（含已软删除的配置），配置行与原值保留并得到
 * path_style = 0，oss_setting 不变；之后可保存 path_style 的 S3 配置、软删除与彻底删除；重放 003 数据与结构不变；
 * SQL 001–003 与 Schema::install() 结构一致。需要一次性 MySQL 测试库（tests/Console/run-mysql.php）。
 */
final class MySqlOssS3UpgradeTest extends TestCase
{
    private const TABLES = ['oss_profile', 'oss_setting'];

    public function test_upgrade_from_002_adds_path_style_and_replays_cleanly(): void
    {
        if (getenv('CONSOLE_TEST_MYSQL') !== '1') self::markTestSkipped('需要一次性 MySQL 测试库');
        self::assertMatchesRegularExpression('/^webman_console_test_[a-f0-9]{12}$/D', Db::connection()->getDatabaseName());
        $root = dirname(__DIR__, 2) . '/plugins/oss/database/migrations';
        $service = new MigrationService();
        $this->drop();
        Schema::install();
        $installed = $this->structure();
        $this->drop();
        if (!Db::schema()->hasTable('system_migration')) {
            foreach (['001_baseline', '002_code_generator', '003_system_secret', '004_plugin_admin_web_directory', '005_soft_delete'] as $version) {
                $service->runVersion(MigrationService::coreDirectory(), $version);
            }
        }
        try {
            SystemMigration::query()->where('scope', 'plugin:oss')->forceDelete();
            foreach (['001_oss', '002_soft_delete'] as $version) self::assertSame([$version], $service->runVersion($root, $version, 'plugin:oss'));
            self::assertFalse(Db::schema()->hasColumn('oss_profile', 'path_style'));
            $profile = static fn (string $id, string $name, ?string $deleted): array => ['id' => $id, 'name' => $name, 'provider' => 'qiniu', 'bucket' => 'bucket-' . $name, 'region' => 'z0', 'endpoint' => '', 'domain' => 'https://cdn.example.com', 'prefix' => 'assets', 'credentials' => 'cipher-' . $name, 'created_at' => '2026-01-02 03:04:05', 'updated_at' => '2026-01-02 03:04:05', 'deleted_at' => $deleted];
            OssProfile::withTrashed()->insert([$profile('0123456789abcdef', 'current', null), $profile('fedcba9876543210', 'trashed', '2026-02-03 04:05:06')]);
            OssSetting::query()->where('id', 1)->update(['current_profile_id' => '0123456789abcdef']);
            $before = $this->dump();

            self::assertSame(['003_s3_path_style'], $service->runVersion($root, '003_s3_path_style', 'plugin:oss'));
            $after = $this->dump();
            self::assertSame($before['oss_setting'], $after['oss_setting'], 'oss_setting 不变');
            self::assertSame($before['oss_profile'], array_map(static fn (array $row): array => array_diff_key($row, ['path_style' => true]), $after['oss_profile']), '配置行与原值保留');
            self::assertSame([0, 0], array_map('intval', array_column($after['oss_profile'], 'path_style')));
            $prefix = Db::connection()->getTablePrefix();
            $column = Db::selectOne("SELECT COLUMN_TYPE t, IS_NULLABLE n, COLUMN_DEFAULT d FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? AND COLUMN_NAME='path_style'", [$prefix . 'oss_profile']);
            self::assertSame(['tinyint(1)', 'NO', '0'], [strtolower((string) $column->t), (string) $column->n, (string) $column->d]);
            self::assertSame($installed, $this->structure(), 'SQL 001–003 与 Schema::install() 结构一致');

            $profiles = new ProfileService(Key::createNewRandomKey()->saveToAsciiSafeString());
            $s3 = $profiles->save(['name' => 'MinIO', 'provider' => 's3', 'bucket' => 'media', 'region' => 'us-east-1', 'endpoint' => 'https://minio.example.com:9000', 'domain' => 'https://cdn.example.com', 'prefix' => '', 'path_style' => true, 'access_key' => 'access', 'secret_key' => 'secret']);
            self::assertTrue($s3['path_style']);
            self::assertSame(1, (int) OssProfile::query()->findOrFail($s3['id'])->path_style);
            self::assertSame('0123456789abcdef', $profiles->current());
            self::assertSame('fedcba9876543210', $profiles->recycle()['list'][0]['id'], '升级前已删除的配置在回收站');
            $profiles->delete($s3['id']);
            self::assertSame(['id' => $s3['id']], $profiles->destroy($s3['id']));

            $data = $this->dump();
            $structure = $this->structure();
            SystemMigration::query()->where('scope', 'plugin:oss')->where('version', '003_s3_path_style')->forceDelete();
            self::assertSame(['003_s3_path_style'], $service->runVersion($root, '003_s3_path_style', 'plugin:oss'));
            self::assertSame($data, $this->dump(), '重放 003 数据不变');
            self::assertSame($structure, $this->structure(), '重放 003 结构不变');
        } finally {
            $this->drop();
            SystemMigration::query()->where('scope', 'plugin:oss')->forceDelete();
        }
    }

    private function drop(): void
    {
        foreach (array_reverse(self::TABLES) as $table) Db::schema()->dropIfExists($table);
    }

    /** @return array<string, list<array<string, mixed>>> */
    private function dump(): array
    {
        return [
            'oss_profile' => array_map(static fn (OssProfile $row): array => $row->getAttributes(), OssProfile::withTrashed()->orderBy('id')->get()->all()),
            'oss_setting' => array_map(static fn (OssSetting $row): array => $row->getAttributes(), OssSetting::query()->orderBy('id')->get()->all()),
        ];
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
