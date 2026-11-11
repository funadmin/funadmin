<?php

declare(strict_types=1);

namespace Tests\Console;

use app\admin\form\service\FormDataService;
use app\common\form\builder\Form as FormBuilder;
use app\common\service\database\MigrationService;
use Illuminate\Database\QueryException;
use PHPUnit\Framework\TestCase;
use support\Db;

/**
 * 表单数据软删除在 MySQL 8 严格模式下：UUID 主键表的 deleted_token（BIGINT）取 row_no，单条、批量、级联与子表移除都不写字符串主键。
 * 需要一次性 MySQL 测试库（tests/Console/run-mysql.php）。
 */
final class MySqlFormDataSoftDeleteTest extends TestCase
{
    private const UUID = '00000000-0000-4000-8000-%012d';

    protected function setUp(): void
    {
        if (getenv('CONSOLE_TEST_MYSQL') !== '1') self::markTestSkipped('需要一次性 MySQL 测试库');
        \Tests\Support\MySqlTestDatabase::clear();
        $service = new MigrationService();
        foreach (['001_baseline', '002_code_generator', '003_system_secret', '004_plugin_admin_web_directory', '005_soft_delete', '006_operation_log_force_delete'] as $version) {
            $service->runVersion(MigrationService::coreDirectory(), $version);
        }
    }

    protected function tearDown(): void
    {
        if (getenv('CONSOLE_TEST_MYSQL') === '1') \Tests\Support\MySqlTestDatabase::clear();
    }

    private static function uuid(int $n): string
    {
        return sprintf(self::UUID, $n);
    }

    private function publish(FormBuilder $document, string $key, string $table): string
    {
        $compiled = $document->compile();
        $formId = Db::table('form')->insertGetId([
            'form_key' => $key, 'name' => $key, 'table_name' => $table, 'connection' => 'mysql', 'source_type' => 'created', 'status' => 1,
        ]);
        Db::table('form_schema_version')->insert([
            'form_id' => $formId, 'version' => 1, 'schema_version' => 2, 'schema_hash' => $compiled->hash(),
            'schema_document' => $compiled->canonicalJson(), 'origin' => 'designer', 'change_summary' => '', 'created_by' => 'test', 'created_at' => date('Y-m-d H:i:s'),
        ]);
        Db::table('business_module')->insert([
            'code' => $key, 'name' => $key, 'form_id' => $formId, 'origin' => 'visual', 'connection_name' => 'mysql',
            'table_name' => $table, 'runtime_route' => '', 'module_route' => '', 'lifecycle_status' => 'dynamic_published',
            'generation_status' => 'none', 'metadata' => '{}', 'published_schema_hash' => $compiled->hash(), 'published_schema_version' => 1,
        ]);
        return $compiled->hash();
    }

    /** 与升级命令 / 生成器一致的 UUID 表：row_no 自增、唯一键以 deleted_token 结尾。 */
    private function uuidTable(string $physical, string $foreignKey = ''): void
    {
        Db::statement("CREATE TABLE `{$physical}` (`id` char(36) NOT NULL, " . ($foreignKey !== '' ? "`{$foreignKey}` bigint unsigned NULL, " : '')
            . "`code` varchar(40) NOT NULL, `created_at` datetime NULL, `updated_at` datetime NULL, `deleted_at` datetime NULL,"
            . " `deleted_token` bigint unsigned NOT NULL DEFAULT 0, `row_no` bigint unsigned NOT NULL AUTO_INCREMENT,"
            . " PRIMARY KEY (`id`), UNIQUE KEY `uk_{$physical}_row_no` (`row_no`), UNIQUE KEY `uk_{$physical}_code` (`code`, `deleted_token`),"
            . " KEY `idx_{$physical}_deleted_at` (`deleted_at`)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    }

    private function assertRowNoToken(string $table, string $id): void
    {
        $row = Db::table($table)->where('id', $id)->first();
        self::assertNotNull($row->deleted_at);
        self::assertGreaterThan(0, (int) $row->deleted_token);
        self::assertSame((int) $row->row_no, (int) $row->deleted_token, $table . '.' . $id . ' 令牌取 row_no');
    }

    public function test_uuid_rows_soft_delete_with_row_no_token_under_strict_mode(): void
    {
        self::assertStringContainsString('STRICT_TRANS_TABLES', (string) Db::selectOne('SELECT @@SESSION.sql_mode m')->m);
        $p = Db::connection()->getTablePrefix();
        $service = new FormDataService(permissionChecker: static fn (string $permission): bool => true);

        $this->uuidTable($p . 'uuid_items');
        foreach ([1, 2, 3] as $n) Db::table('uuid_items')->insert(['id' => self::uuid($n), 'code' => 'I' . $n]);
        try {
            Db::table('uuid_items')->where('id', self::uuid(3))->update(['deleted_token' => Db::raw('`id`')]);
            self::fail('严格模式应拒绝把字符串主键写入 BIGINT 令牌');
        } catch (QueryException $error) {
            self::assertContains((int) ($error->errorInfo[1] ?? 0), [1265, 1292, 1366], $error->getMessage());
        }
        $items = FormBuilder::make('uuid_items')->title('UUID')->table($p . 'uuid_items', 'mysql', 'created');
        $items->input('id', '主键')->column('char(36)', false, false);
        $items->input('code', '编码')->column('varchar(40)', false, false, 'unique');
        $hash = $this->publish($items, 'uuid_items', $p . 'uuid_items');
        self::assertSame('soft', $service->remove('uuid_items', self::uuid(1), $hash)['mode']);
        $this->assertRowNoToken('uuid_items', self::uuid(1));
        self::assertSame(2, $service->batchRemove('uuid_items', [self::uuid(2), self::uuid(3)], $hash)['removed']);
        $this->assertRowNoToken('uuid_items', self::uuid(2));
        $this->assertRowNoToken('uuid_items', self::uuid(3));
        Db::table('uuid_items')->insert(['id' => self::uuid(4), 'code' => 'I1']);
        self::assertSame(1, $service->restore('uuid_items', [self::uuid(2)], $hash)['restored']);
        self::assertSame(0, (int) Db::table('uuid_items')->where('id', self::uuid(2))->value('deleted_token'));

        // 父记录（整数主键）软删除级联到 UUID 子表。
        Db::statement("CREATE TABLE `{$p}fk_parent` (`id` bigint unsigned NOT NULL AUTO_INCREMENT, `title` varchar(120) NOT NULL DEFAULT '', `created_at` datetime NULL,"
            . " `updated_at` datetime NULL, `deleted_at` datetime NULL, PRIMARY KEY (`id`)) ENGINE=InnoDB");
        $parent = FormBuilder::make('fk_parent')->title('父')->table($p . 'fk_parent', 'mysql', 'created');
        $parent->input('title', '标题')->column('varchar(120)')->list(true);
        $parentHash = $this->publish($parent, 'fk_parent', $p . 'fk_parent');
        $this->uuidTable($p . 'uuid_cascade', 'parent_id');
        $cascade = FormBuilder::make('uuid_cascade')->title('级联')->table($p . 'uuid_cascade', 'mysql', 'created');
        $cascade->number('parent_id', '父')->column('bigint', true, true)->relation('belongs_to', $p . 'fk_parent', 'title', 'id', false, 'cascade');
        $cascade->input('code', '编码')->column('varchar(40)', false, false, 'unique');
        $this->publish($cascade, 'uuid_cascade', $p . 'uuid_cascade');
        $first = (int) Db::table('fk_parent')->insertGetId(['title' => 'P']);
        $second = (int) Db::table('fk_parent')->insertGetId(['title' => 'Q']);
        Db::table('uuid_cascade')->insert([['id' => self::uuid(11), 'parent_id' => $first, 'code' => 'C1'], ['id' => self::uuid(12), 'parent_id' => $second, 'code' => 'C2']]);
        $service->remove('fk_parent', $first, $parentHash);
        $this->assertRowNoToken('uuid_cascade', self::uuid(11));
        $service->batchRemove('fk_parent', [$second], $parentHash);
        $this->assertRowNoToken('uuid_cascade', self::uuid(12));
        self::assertSame(1, $service->restore('fk_parent', [$second], $parentHash)['restored']);
        self::assertSame(0, (int) Db::table('uuid_cascade')->where('id', self::uuid(12))->value('deleted_token'));

        // 子表编辑时移除的 UUID 行。
        Db::statement("CREATE TABLE `{$p}uuid_host` (`id` bigint unsigned NOT NULL AUTO_INCREMENT, `title` varchar(120) NOT NULL DEFAULT '', `created_at` datetime NULL,"
            . " `updated_at` datetime NULL, `deleted_at` datetime NULL, PRIMARY KEY (`id`)) ENGINE=InnoDB");
        $this->uuidTable($p . 'uuid_lines', 'host_id');
        $lines = FormBuilder::make('uuid_lines')->title('明细')->table($p . 'uuid_lines', 'mysql', 'created');
        $lines->input('id', '主键')->column('char(36)', false, false);
        $lines->number('host_id', '主表')->column('bigint', true, true);
        $lines->input('code', '编码')->column('varchar(40)', false, false, 'unique');
        $this->publish($lines, 'uuid_lines', $p . 'uuid_lines');
        $host = FormBuilder::make('uuid_host')->title('主表')->table($p . 'uuid_host', 'mysql', 'created');
        $host->input('title', '标题')->column('varchar(120)');
        $host->subform('lines', '明细', $p . 'uuid_lines', 'host_id', static function ($children): void { $children->input('code', '编码'); });
        $hostHash = $this->publish($host, 'uuid_host', $p . 'uuid_host');
        $hostId = (int) Db::table('uuid_host')->insertGetId(['title' => 'H']);
        Db::table('uuid_lines')->insert([['id' => self::uuid(21), 'host_id' => $hostId, 'code' => 'L1'], ['id' => self::uuid(22), 'host_id' => $hostId, 'code' => 'L2']]);
        $service->update('uuid_host', $hostId, ['title' => 'H', 'lines' => [['id' => self::uuid(22), 'code' => 'L2']]], ['lines'], $hostHash);
        $this->assertRowNoToken('uuid_lines', self::uuid(21));
        self::assertNull(Db::table('uuid_lines')->where('id', self::uuid(22))->value('deleted_at'));
    }
    /** 表单设计器给旧表补唯一字段：UUID 表另加 row_no，整数表只加令牌；已删除行先回填令牌，再建唯一键，严格模式下删除只写数字令牌。 */
    public function test_designer_adds_row_no_and_backfills_tokens_on_legacy_tables_under_strict_mode(): void
    {
        self::assertStringContainsString('STRICT_TRANS_TABLES', (string) Db::selectOne('SELECT @@SESSION.sql_mode m')->m);
        $p = Db::connection()->getTablePrefix();
        $forms = new \app\admin\form\service\FormDesignerService(sys_get_temp_dir() . DIRECTORY_SEPARATOR);
        $service = new FormDataService(permissionChecker: static fn (string $permission): bool => true);
        $trashed = '2026-01-01 00:00:00';

        Db::statement("CREATE TABLE `{$p}legacy_uuid` (`id` char(36) NOT NULL, `code` varchar(40) NOT NULL, `created_at` datetime NULL, `updated_at` datetime NULL,"
            . " `deleted_at` datetime NULL, PRIMARY KEY (`id`)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        Db::table('legacy_uuid')->insert([
            ['id' => self::uuid(31), 'code' => 'U1', 'deleted_at' => $trashed],
            ['id' => self::uuid(32), 'code' => 'U1', 'deleted_at' => null],
            ['id' => self::uuid(33), 'code' => 'U2', 'deleted_at' => null],
        ]);
        $uuid = FormBuilder::make('legacy_uuid')->title('旧 UUID')->table($p . 'legacy_uuid', 'mysql', 'created');
        $uuid->input('code', '编码')->column('varchar(40)', false, false, 'unique');
        $compiled = $uuid->compile();
        $preview = $forms->previewMigration($compiled);
        self::assertSame('additive', $preview['mode']);
        self::assertStringContainsString('ADD COLUMN `row_no` bigint unsigned NOT NULL AUTO_INCREMENT', $preview['sql']);
        self::assertTrue($forms->applyDynamicDdl($compiled, hash('sha256', (string) $preview['sql']))['applied']);
        $old = Db::table('legacy_uuid')->where('id', self::uuid(31))->first();
        self::assertGreaterThan(0, (int) $old->row_no);
        self::assertSame((int) $old->row_no, (int) $old->deleted_token, '已删除行令牌回填为 row_no');
        self::assertSame(0, (int) Db::table('legacy_uuid')->where('id', self::uuid(32))->value('deleted_token'));
        $hash = $this->publish($uuid, 'legacy_uuid', $p . 'legacy_uuid');
        self::assertSame('soft', $service->remove('legacy_uuid', self::uuid(33), $hash)['mode']);
        $this->assertRowNoToken('legacy_uuid', self::uuid(33));
        Db::table('legacy_uuid')->insert(['id' => self::uuid(34), 'code' => 'U2']);

        Db::statement("CREATE TABLE `{$p}legacy_int` (`id` bigint unsigned NOT NULL AUTO_INCREMENT, `code` varchar(40) NOT NULL, `created_at` datetime NULL,"
            . " `updated_at` datetime NULL, `deleted_at` datetime NULL, PRIMARY KEY (`id`)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        $deletedId = (int) Db::table('legacy_int')->insertGetId(['code' => 'N1', 'deleted_at' => $trashed]);
        $liveId = (int) Db::table('legacy_int')->insertGetId(['code' => 'N1']);
        $integer = FormBuilder::make('legacy_int')->title('旧整数')->table($p . 'legacy_int', 'mysql', 'created');
        $integer->input('code', '编码')->column('varchar(40)', false, false, 'unique');
        $compiled = $integer->compile();
        $preview = $forms->previewMigration($compiled);
        self::assertStringNotContainsString('row_no', $preview['sql']);
        self::assertTrue($forms->applyDynamicDdl($compiled, hash('sha256', (string) $preview['sql']))['applied']);
        self::assertSame($deletedId, (int) Db::table('legacy_int')->where('id', $deletedId)->value('deleted_token'), '已删除行令牌回填为 id');
        self::assertSame(0, (int) Db::table('legacy_int')->where('id', $liveId)->value('deleted_token'));
        self::assertFalse(Db::getSchemaBuilder()->hasColumn('legacy_int', 'row_no'));
    }
}
