<?php

declare(strict_types=1);

namespace Tests\Console;

use app\admin\development\command\BusinessSoftDeleteUpgrade;
use app\common\crud\CrudDefinition;
use app\common\crud\PluginCrudTarget;
use app\common\crud\ProductionTemplateContext;
use app\common\model\concern\TokenSoftDeletingScope;
use app\common\service\database\MigrationService;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Database\QueryException;
use PHPUnit\Framework\TestCase;
use support\Db;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * business:soft-delete-upgrade 在 MySQL 8 上：每张表一条 ALTER；整数主键回填 id，UUID 主键加自增 row_no 并以它回填；
 * 唯一键按原名重建（前缀长度保留）；重复执行不发 ALTER；数据保留。插件目标的生成表写出的升级迁移可在另一套安装上
 * 通过迁移运行器执行并重放，重新生成沿用该迁移。需要一次性 MySQL 测试库（tests/Console/run-mysql.php）。
 */
final class MySqlLegacyTicket extends \app\common\model\BaseModel
{
    use \app\common\model\concern\LaravelSoftDelete;

    protected $table = 'biz_ticket';
    public $incrementing = false;
    protected $keyType = 'string';
    protected $guarded = [];
}

final class MySqlBusinessSoftDeleteUpgradeTest extends TestCase
{
    private const TABLES = ['biz_order', 'biz_ticket', 'biz_note', 'biz_plain', 'plugin_soft'];

    private string $root = '';

    protected function setUp(): void
    {
        if (getenv('CONSOLE_TEST_MYSQL') !== '1') self::markTestSkipped('需要一次性 MySQL 测试库');
        self::assertMatchesRegularExpression('/^webman_console_test_[a-f0-9]{12}$/D', Db::connection()->getDatabaseName());
        if (!Db::schema()->hasTable('system_migration')) {
            $service = new MigrationService();
            foreach (['001_baseline', '002_code_generator', '003_system_secret', '004_plugin_admin_web_directory', '005_soft_delete', '006_operation_log_force_delete'] as $version) {
                $service->runVersion(MigrationService::coreDirectory(), $version);
            }
        }
        $this->cleanup();
    }

    protected function tearDown(): void
    {
        if (getenv('CONSOLE_TEST_MYSQL') === '1') $this->cleanup();
        if ($this->root !== '' && is_dir($this->root)) exec('rm -rf ' . escapeshellarg($this->root));
    }

    private function cleanup(): void
    {
        foreach (self::TABLES as $table) Db::schema()->dropIfExists($table);
        Db::table('business_module')->where('code', 'like', 'sdu_%')->delete();
        Db::table('system_migration')->where('scope', 'plugin:softkit')->delete();
        TokenSoftDeletingScope::flushDeletedTokenCache();
    }

    private function register(string $code, string $table, array $metadata = []): void
    {
        Db::table('business_module')->insert([
            'code' => 'sdu_' . $code, 'name' => $code, 'origin' => 'visual', 'connection_name' => 'mysql',
            'table_name' => Db::connection()->getTablePrefix() . $table, 'runtime_route' => '', 'module_route' => '',
            'lifecycle_status' => 'dynamic_published', 'generation_status' => 'idle',
            'metadata' => json_encode($metadata === [] ? new \stdClass() : $metadata, JSON_THROW_ON_ERROR),
            'published_schema_hash' => str_repeat('b', 64), 'published_schema_version' => 1,
        ]);
    }

    private function upgrade(array $input, ?string $root = null): CommandTester
    {
        $tester = new CommandTester(new BusinessSoftDeleteUpgrade($root ?? base_path()));
        $tester->execute($input);
        TokenSoftDeletingScope::flushDeletedTokenCache();
        return $tester;
    }

    private function structure(string $table): array
    {
        $physical = Db::connection()->getTablePrefix() . $table;
        $columns = Db::select('SELECT COLUMN_NAME n, COLUMN_TYPE t, IS_NULLABLE nl, COLUMN_DEFAULT d, EXTRA e FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? ORDER BY ORDINAL_POSITION', [$physical]);
        $indexes = Db::select('SELECT INDEX_NAME n, NON_UNIQUE u, GROUP_CONCAT(CONCAT(COLUMN_NAME, IFNULL(CONCAT(\'(\', SUB_PART, \')\'), \'\')) ORDER BY SEQ_IN_INDEX) c FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? GROUP BY INDEX_NAME, NON_UNIQUE ORDER BY INDEX_NAME', [$physical]);
        return ['columns' => array_map(static fn ($c): array => (array) $c, $columns), 'indexes' => array_column(array_map(static fn ($i): array => (array) $i, $indexes), 'c', 'n')];
    }

    private function rows(string $table): array
    {
        return array_map(static fn ($row): array => (array) $row, Db::table($table)->orderBy('id')->get()->all());
    }

    /** @return list<string> 执行期间发出的 ALTER 语句 */
    private function alters(callable $callback): array
    {
        $log = [];
        Db::connection()->listen(static function (QueryExecuted $query) use (&$log): void {
            if (str_starts_with(strtoupper(ltrim($query->sql)), 'ALTER')) $log[] = $query->sql;
        });
        $callback();
        return $log;
    }

    public function test_upgrades_integer_and_uuid_tables_in_one_alter_each_and_is_idempotent(): void
    {
        $p = Db::connection()->getTablePrefix();
        Db::statement("CREATE TABLE `{$p}biz_order` (`id` bigint unsigned NOT NULL AUTO_INCREMENT, `code` varchar(40) NOT NULL, `title` varchar(255) NOT NULL,"
            . " `deleted_at` datetime NULL, PRIMARY KEY (`id`), UNIQUE KEY `uk_{$p}biz_order_code` (`code`), UNIQUE KEY `uk_{$p}biz_order_title` (`title`(20))) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        Db::statement("CREATE TABLE `{$p}biz_ticket` (`id` char(36) NOT NULL, `title` varchar(80) NOT NULL, `metadata` json NULL, `code` varchar(40) NOT NULL,"
            . " `created_at` datetime NULL, `updated_at` datetime NULL, `deleted_at` datetime NULL, PRIMARY KEY (`id`), UNIQUE KEY `uk_{$p}biz_ticket_code` (`code`)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        Db::statement("CREATE TABLE `{$p}biz_note` (`id` bigint unsigned NOT NULL AUTO_INCREMENT, `title` varchar(80) NOT NULL, `deleted_at` datetime NULL, PRIMARY KEY (`id`)) ENGINE=InnoDB");
        Db::statement("CREATE TABLE `{$p}biz_plain` (`id` bigint unsigned NOT NULL AUTO_INCREMENT, `code` varchar(40) NOT NULL, PRIMARY KEY (`id`), UNIQUE KEY `uk_{$p}biz_plain_code` (`code`)) ENGINE=InnoDB");
        Db::table('biz_order')->insert([
            ['id' => 1, 'code' => 'A', 'title' => 'alpha', 'deleted_at' => null],
            ['id' => 2, 'code' => 'B', 'title' => 'beta', 'deleted_at' => '2026-02-01 00:00:00'],
        ]);
        Db::table('biz_ticket')->insert([
            ['id' => '00000000-0000-4000-8000-000000000001', 'title' => 'one', 'code' => 'T1', 'deleted_at' => null],
            ['id' => '00000000-0000-4000-8000-000000000002', 'title' => 'two', 'code' => 'T2', 'deleted_at' => '2026-02-01 00:00:00'],
        ]);
        Db::table('biz_note')->insert(['id' => 1, 'title' => 'n', 'deleted_at' => null]);
        foreach (['biz_order', 'biz_ticket', 'biz_note', 'biz_plain'] as $table) $this->register($table, $table);
        $before = array_map(fn (string $table): array => $this->rows($table), ['order' => 'biz_order', 'ticket' => 'biz_ticket', 'note' => 'biz_note']);
        $structures = array_map(fn (string $table): array => $this->structure($table), array_combine(self::TABLES, self::TABLES) ?: []);
        unset($structures['plugin_soft']);

        $alters = $this->alters(function () use (&$dry): void { $dry = $this->upgrade(['--dry-run' => true]); });
        self::assertSame(0, $dry->getStatusCode(), $dry->getDisplay());
        self::assertSame([], $alters, 'dry-run 不执行 DDL');
        foreach ($structures as $table => $structure) self::assertSame($structure, $this->structure($table));

        $alters = $this->alters(function () use (&$real): void { $real = $this->upgrade([]); });
        self::assertSame(0, $real->getStatusCode(), $real->getDisplay());
        self::assertCount(3, $alters, '每张需要升级的表只发一条 ALTER（biz_plain 没有 deleted_at，跳过）');
        self::assertStringContainsString('没有 deleted_at', $real->getDisplay());

        $order = $this->structure('biz_order');
        self::assertSame('code,deleted_token', $order['indexes']['uk_' . $p . 'biz_order_code']);
        self::assertSame('title(20),deleted_token', $order['indexes']['uk_' . $p . 'biz_order_title'], '前缀长度保留');
        self::assertSame('deleted_at', $order['indexes']['idx_' . $p . 'biz_order_deleted_at']);
        self::assertSame([0, 2], array_map('intval', Db::table('biz_order')->orderBy('id')->pluck('deleted_token')->all()));

        $ticket = $this->structure('biz_ticket');
        $rowNo = array_values(array_filter($ticket['columns'], static fn (array $c): bool => $c['n'] === 'row_no'))[0];
        self::assertSame(['bigint unsigned', 'NO', 'auto_increment'], [$rowNo['t'], $rowNo['nl'], $rowNo['e']]);
        self::assertSame('row_no', $ticket['indexes']['idx_' . $p . 'biz_ticket_row_no']);
        self::assertSame('code,deleted_token', $ticket['indexes']['uk_' . $p . 'biz_ticket_code']);
        $trashed = Db::table('biz_ticket')->where('id', '00000000-0000-4000-8000-000000000002')->first();
        self::assertGreaterThan(0, (int) $trashed->row_no);
        self::assertSame((int) $trashed->row_no, (int) $trashed->deleted_token, 'UUID 主键表以 row_no 回填');
        self::assertSame(0, (int) Db::table('biz_ticket')->where('id', '00000000-0000-4000-8000-000000000001')->value('deleted_token'));
        self::assertStringContainsString('无需重新生成', $real->getDisplay());

        // I3：未重新生成、没有覆盖令牌来源的旧 UUID 模型，在升级后的表上同样以 row_no 写令牌。
        MySqlLegacyTicket::query()->create(['id' => '00000000-0000-4000-8000-000000000009', 'title' => 'old', 'code' => 'OLD']);
        MySqlLegacyTicket::query()->findOrFail('00000000-0000-4000-8000-000000000009')->delete();
        $old = Db::table('biz_ticket')->where('id', '00000000-0000-4000-8000-000000000009')->first();
        self::assertSame((int) $old->row_no, (int) $old->deleted_token);
        self::assertTrue(MySqlLegacyTicket::withTrashed()->findOrFail('00000000-0000-4000-8000-000000000009')->restore());
        self::assertSame(0, (int) Db::table('biz_ticket')->where('id', '00000000-0000-4000-8000-000000000009')->value('deleted_token'));
        Db::table('biz_ticket')->where('id', '00000000-0000-4000-8000-000000000009')->delete();

        $note = $this->structure('biz_note');
        ksort($note['indexes']);
        self::assertSame(['PRIMARY' => 'id', 'idx_' . $p . 'biz_note_deleted_at' => 'deleted_at'], $note['indexes']);
        self::assertSame($structures['biz_plain'], $this->structure('biz_plain'), '没有 deleted_at 的表不改');

        $strip = static fn (array $rows): array => array_map(static fn (array $row): array => array_diff_key($row, ['deleted_token' => true, 'row_no' => true]), $rows);
        self::assertSame($before['order'], $strip($this->rows('biz_order')), '数据保留');
        self::assertSame($before['ticket'], $strip($this->rows('biz_ticket')));
        self::assertSame($before['note'], $this->rows('biz_note'));

        // 重新生成的 UUID 模型以 row_no 为令牌来源：软删除后可重建相同 code，未删除的重复值仍被拒绝。
        $data = CrudDefinitionFixture::make('biz-ticket', 'mysql')->toArray();
        $data['table'] = $p . 'biz_ticket';
        $data['fields'][0] = array_merge($data['fields'][0], ['dbType' => 'char(36)', 'format' => 'uuid']);
        $data['fields'][] = ['name' => 'code', 'label' => '编码', 'dbType' => 'varchar(40)', 'nullable' => false, 'component' => 'input', 'unique' => true];
        $model = ProductionTemplateContext::build(CrudDefinition::fromArray($data), ['modelNamespace' => 'Tests\\SoftDeleteUpgradeMySql'])['modelContent'];
        $file = tempnam(sys_get_temp_dir(), 'funadmin-sdu-');
        file_put_contents($file, $model);
        try {
            require $file;
            $class = 'Tests\\SoftDeleteUpgradeMySql\\BizTicket';
            $class::query()->findOrFail('00000000-0000-4000-8000-000000000001')->delete();
            $row = Db::table('biz_ticket')->where('id', '00000000-0000-4000-8000-000000000001')->first();
            self::assertSame((int) $row->row_no, (int) $row->deleted_token);
            $class::create(['title' => 'again', 'code' => 'T1']);
            $class::create(['title' => 'again', 'code' => 'T2']);
            try {
                $class::create(['title' => 'dup', 'code' => 'T1']);
                self::fail('未删除的重复值必须被拒绝');
            } catch (QueryException) {
            }
        } finally {
            unlink($file);
        }

        $after = array_map(fn (string $table): array => $this->structure($table), ['biz_order', 'biz_ticket', 'biz_note', 'biz_plain']);
        $data = array_map(fn (string $table): array => $this->rows($table), ['biz_order', 'biz_ticket', 'biz_note']);
        $alters = $this->alters(function () use (&$again): void { $again = $this->upgrade([]); });
        self::assertSame(0, $again->getStatusCode(), $again->getDisplay());
        self::assertSame([], $alters, '重复执行不发 ALTER');
        self::assertSame($after, array_map(fn (string $table): array => $this->structure($table), ['biz_order', 'biz_ticket', 'biz_note', 'biz_plain']));
        self::assertSame($data, array_map(fn (string $table): array => $this->rows($table), ['biz_order', 'biz_ticket', 'biz_note']));
    }

    public function test_plugin_target_upgrade_migration_upgrades_other_installs_and_regeneration_reuses_it(): void
    {
        $this->pluginTargetRoundTrip(false);
    }

    /** M6：UUID 主键的插件目标表：升级迁移在另一套安装上加 row_no 并以它回填，结果与第一套安装一致。 */
    public function test_uuid_plugin_target_upgrade_migration_adds_row_no_on_second_install(): void
    {
        $this->pluginTargetRoundTrip(true);
    }

    private function pluginTargetRoundTrip(bool $uuid): void
    {
        $p = Db::connection()->getTablePrefix();
        $table = $p . 'plugin_soft';
        $data = CrudDefinitionFixture::make('plugin-soft', 'mysql')->toArray();
        $data['target'] = ['type' => 'plugin', 'plugin' => 'softkit', 'scope' => 'admin'];
        $data['table'] = $table;
        unset($data['generationTargets']);
        if ($uuid) $data['fields'][0] = array_merge($data['fields'][0], ['dbType' => 'char(36)', 'format' => 'uuid']);
        $data['fields'][] = ['name' => 'code', 'label' => '编码', 'dbType' => 'varchar(40)', 'nullable' => false, 'component' => 'input', 'unique' => true];
        $definition = CrudDefinition::fromArray($data);
        $this->root = sys_get_temp_dir() . '/funadmin-sdu-mysql-' . bin2hex(random_bytes(6));
        $migrations = $this->root . '/plugins/softkit/database/migrations';
        mkdir($migrations, 0700, true);
        $target = new PluginCrudTarget($this->root);
        $snapshot = (new \ReflectionMethod($target, 'schemaSnapshot'))->invoke($target, $definition);
        unset($snapshot['fields']['deleted_token'], $snapshot['fields']['row_no']);
        $snapshot['fields']['deleted_at']['index'] = '';
        $create = ProductionTemplateContext::build($definition)['migrationContent'];
        $legacy = (new \ReflectionMethod(PluginCrudTarget::class, 'legacyCreateSql'))->invoke(null, $create, $table);
        self::assertStringNotContainsString('row_no', $legacy);
        file_put_contents($migrations . '/001_create_plugin_soft.sql', rtrim($legacy) . "\n-- funadmin-crud-schema: " . base64_encode(CrudDefinition::canonicalJson($snapshot)) . "\n");
        $service = new MigrationService();
        $ids = $uuid ? ['00000000-0000-4000-8000-00000000000a', '00000000-0000-4000-8000-00000000000b'] : [1, 2];
        $seed = static function () use ($ids): void {
            Db::table('plugin_soft')->insert([
                ['id' => $ids[0], 'title' => 'a', 'code' => 'A', 'deleted_at' => null],
                ['id' => $ids[1], 'title' => 'b', 'code' => 'B', 'deleted_at' => '2026-03-01 00:00:00'],
            ]);
        };
        $tokens = static function () use ($uuid): array {
            $rows = Db::table('plugin_soft')->orderBy('code')->get()->all();
            return array_map(static fn ($row): array => [(int) $row->deleted_token, $uuid ? (int) $row->row_no : (int) $row->id], $rows);
        };

        // 安装 A：命令写出插件升级迁移并经迁移运行器执行（登记 system_migration），不直接 ALTER。
        self::assertSame(['001_create_plugin_soft'], $service->runVersion($migrations, '001_create_plugin_soft', 'plugin:softkit'));
        $seed();
        $this->register('plugin_soft', 'plugin_soft', ['target' => ['type' => 'plugin', 'pluginCode' => 'softkit', 'tableStrategy' => 'owned']]);
        $tester = $this->upgrade(['--table' => 'plugin_soft'], $this->root);
        self::assertSame(0, $tester->getStatusCode(), $tester->getDisplay());
        $file = $migrations . '/002_soft_delete_plugin_soft.sql';
        self::assertFileExists($file);
        $content = (string) file_get_contents($file);
        self::assertSame(1, preg_match('/^[\x00-\x7F]*$/D', $content), '插件升级迁移只用 ASCII');
        $ledger = Db::table('system_migration')->where('scope', 'plugin:softkit')->where('version', '002_soft_delete_plugin_soft')->first();
        self::assertNotNull($ledger, '升级迁移已登记');
        self::assertSame(hash('sha256', $content), (string) $ledger->checksum);
        $upgradedA = $this->structure('plugin_soft');
        self::assertSame('code,deleted_token', $upgradedA['indexes']['uk_' . $table . '_code']);
        self::assertSame('deleted_at', $upgradedA['indexes']['idx_' . $table . '_deleted_at']);
        if ($uuid) self::assertSame('row_no', $upgradedA['indexes']['idx_' . $table . '_row_no']);
        [$live, $trashed] = $tokens();
        self::assertSame(0, $live[0]);
        self::assertSame($trashed[1], $trashed[0], $uuid ? '以 row_no 回填' : '以 id 回填');
        self::assertGreaterThan(0, $trashed[0]);
        $rowsA = $this->rows('plugin_soft');

        // 再次执行：已登记、结构到位，无需升级。
        $again = $this->upgrade(['--table' => 'plugin_soft'], $this->root);
        self::assertSame(0, $again->getStatusCode(), $again->getDisplay());
        self::assertStringContainsString('无需升级', $again->getDisplay());
        self::assertSame(['001_create_plugin_soft.sql', '002_soft_delete_plugin_soft.sql'], array_map('basename', glob($migrations . '/*.sql') ?: []));

        // 安装 B：旧形状的表带数据，随插件迁移升级，结果与安装 A 一致；重放不变。
        Db::schema()->dropIfExists('plugin_soft');
        Db::table('system_migration')->where('scope', 'plugin:softkit')->delete();
        self::assertSame(['001_create_plugin_soft'], $service->runVersion($migrations, '001_create_plugin_soft', 'plugin:softkit'));
        $seed();
        self::assertSame(['002_soft_delete_plugin_soft'], $service->runVersion($migrations, '002_soft_delete_plugin_soft', 'plugin:softkit'));
        self::assertSame($upgradedA, $this->structure('plugin_soft'));
        [$live, $trashed] = $tokens();
        self::assertSame(0, $live[0]);
        self::assertSame($trashed[1], $trashed[0]);
        if (!$uuid) self::assertSame($rowsA, $this->rows('plugin_soft'));
        $rowsB = $this->rows('plugin_soft');
        Db::table('system_migration')->where('scope', 'plugin:softkit')->where('version', '002_soft_delete_plugin_soft')->delete();
        self::assertSame(['002_soft_delete_plugin_soft'], $service->runVersion($migrations, '002_soft_delete_plugin_soft', 'plugin:softkit'));
        self::assertSame($upgradedA, $this->structure('plugin_soft'));
        self::assertSame($rowsB, $this->rows('plugin_soft'));

        // 重新生成：沿用 002，不生成新的 ALTER。
        $regenerated = (new \ReflectionMethod(PluginCrudTarget::class, 'migration'))->invoke($target, $definition, 'softkit', 'plugin-soft', $create);
        self::assertSame('plugins/softkit/database/migrations/002_soft_delete_plugin_soft.sql', $regenerated['path']);
    }
}
