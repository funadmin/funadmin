<?php

declare(strict_types=1);

namespace app\admin\development\service;

use app\admin\development\model\BusinessModule;
use app\common\crud\CrudDefinition;
use app\common\crud\GeneratedSoftDeleteColumns;
use app\common\crud\PathGuard;
use app\common\model\concern\TokenSoftDeletingScope;
use app\common\model\SystemMigration;
use app\common\plugin\model\Plugin;
use app\common\service\database\MigrationService;
use Illuminate\Database\Connection;
use Illuminate\Database\Schema\Blueprint;
use InvalidArgumentException;
use RuntimeException;
use Throwable;
use support\Db;

/**
 * 已发布业务表的软删除升级（business:soft-delete-upgrade），规则与核心 005_soft_delete 相同：
 *
 * - 只处理 business_module 登记的已发布表（published_schema_hash 非空）；核心表、插件基线表一律拒绝；
 * - 没有 deleted_at 的表（softDeletes=false）只报告并跳过，不静默加软删除；
 * - 补 deleted_at 索引；有非主键唯一键时补 deleted_token，主键不是整数时另加自增 row_no 作为令牌来源，
 *   已删除行回填 deleted_token，唯一键按原名重建为「原列 + deleted_token」；
 * - MySQL 按 information_schema / SHOW INDEX 读取现状，每张表只发一条 ALTER，只含仍缺的子句；DDL 不放在事务里；
 *   全部到位时不发 ALTER，可重复执行；
 * - 插件目标的生成表（tableStrategy=owned）另写一份可重复执行的插件迁移，携带含令牌的结构快照：
 *   其他安装随插件迁移升级，重新生成代码时也不会回到旧形状。
 */
final class BusinessSoftDeleteUpgradeService
{
    private const INTEGER_TYPES = ['tinyint', 'smallint', 'mediumint', 'int', 'integer', 'bigint'];

    /** @var array<string, string>|null 逻辑表名 => 归属（core 或插件代码） */
    private ?array $baselineOwners = null;

    /** @var array<string, string>|null 物理表名 => 插件代码（插件生成表） */
    private ?array $generatedOwners = null;

    public function __construct(private readonly string $projectRoot)
    {
    }

    /**
     * @return list<array{module: string, table: string, status: string, message: string, sql: list<string>, backfill: int, migration: ?string, notes: list<string>}>
     */
    public function upgrade(?string $table, bool $dryRun): array
    {
        $modules = $this->modules($table);
        $results = [];
        foreach ($modules as $module) {
            try {
                $results[] = $this->upgradeModule($module, $dryRun);
            } catch (InvalidArgumentException $error) {
                $results[] = $this->result($module, (string) $module['table_name'], 'refused', $error->getMessage());
            } catch (Throwable $error) {
                // 单张表失败不影响其余表，结果中写明失败原因，命令以非零退出。
                $results[] = $this->result($module, (string) $module['table_name'], 'failed', $error->getMessage());
            }
        }
        TokenSoftDeletingScope::flushDeletedTokenCache();
        return $results;
    }

    /** @return list<array<string, mixed>> */
    private function modules(?string $table): array
    {
        $rows = BusinessModule::query()->whereNotNull('published_schema_hash')->where('published_schema_hash', '<>', '')
            ->orderBy('id')->get()->map(static fn (BusinessModule $module): array => $module->toArray())->all();
        if ($table === null || $table === '') return $rows;
        // 先按物理表名精确匹配；没有时再按「连接前缀 + 参数」匹配（--table=order 对应 fun_order）。
        $matched = array_values(array_filter($rows, static fn (array $module): bool => (string) ($module['table_name'] ?? '') === $table));
        if ($matched === []) {
            $matched = array_values(array_filter($rows, fn (array $module): bool
                => (string) ($module['table_name'] ?? '') === $this->connection($module)->getTablePrefix() . $table));
        }
        if ($matched === []) {
            throw new InvalidArgumentException('表 ' . $table . ' 不是已发布的业务模块表（business_module 未登记或未发布），拒绝处理');
        }
        return $matched;
    }

    /**
     * table_name 存的就是物理表名（新建表带前缀，采纳的表可能没有前缀），原样使用，绝不再补前缀；
     * 逻辑名只用于核对核心/插件基线表：去掉连接前缀后比较。
     *
     * @return array{0: string, 1: string} [物理表名, 逻辑表名]
     */
    private function names(array $module): array
    {
        $prefix = $this->connection($module)->getTablePrefix();
        $table = (string) ($module['table_name'] ?? '');
        $logical = $prefix !== '' && str_starts_with($table, $prefix) ? substr($table, strlen($prefix)) : $table;
        return [$table, $logical];
    }

    private function connection(array $module): Connection
    {
        $name = (string) ($module['connection_name'] ?? '');
        return Db::connection($name === '' ? null : $name);
    }

    private function upgradeModule(array $module, bool $dryRun): array
    {
        [$physical, $logical] = $this->names($module);
        if (preg_match('/\A[a-z_][a-z0-9_]*\z/D', $physical) !== 1) {
            throw new InvalidArgumentException('业务表名不合法：' . $physical);
        }
        $target = (array) ($module['metadata']['target'] ?? ['type' => 'core']);
        $plugin = ($target['type'] ?? 'core') === 'plugin' ? (string) ($target['pluginCode'] ?? '') : '';
        $this->assertBusinessTable($physical, $logical, $plugin);

        $connection = $this->connection($module);
        $reader = clone $connection;
        $reader->setTablePrefix('');
        $reader->useDefaultSchemaGrammar();
        $reader->useDefaultQueryGrammar();
        if (!$reader->getSchemaBuilder()->hasTable($physical)) {
            return $this->result($module, $physical, 'skipped', '物理表不存在，跳过');
        }
        $state = $this->inspect($reader, $physical);
        if (!in_array('deleted_at', array_keys($state['columns']), true)) {
            return $this->result($module, $physical, 'skipped', '没有 deleted_at（生成时 softDeletes=false），只报告不处理，不自动加软删除');
        }
        $plan = $this->plan($physical, $state);
        $notes = [];
        if ($plan['rowNo'] !== null) {
            $notes[] = '主键不是整数：加自增序号 row_no 作为删除令牌来源（软删除 trait 检测到 row_no 自动使用，模型无需重新生成）；加列会复制整表并阻塞写入，请在维护窗口执行';
        }
        $sql = [];
        if ($plan['clauses'] !== []) $sql[] = 'ALTER TABLE `' . $physical . '` ' . implode(', ', $plan['clauses']);
        $backfill = 0;
        if ($plan['token']) {
            $sql[] = 'UPDATE `' . $physical . '` SET `deleted_token` = `' . $plan['source'] . '` WHERE `deleted_at` IS NOT NULL AND `deleted_token` = 0';
            $query = $reader->table($physical)->whereNotNull('deleted_at');
            if (isset($state['columns'][GeneratedSoftDeleteColumns::TOKEN])) $query->where(GeneratedSoftDeleteColumns::TOKEN, 0);
            $backfill = $query->count();
        }
        if ($plugin !== '' && ($target['tableStrategy'] ?? 'owned') === 'owned') {
            return $this->upgradePluginTable($module, $plugin, $physical, $logical, $connection, $plan, $sql, $backfill, $notes, $dryRun);
        }
        if ($plan['clauses'] === [] && $backfill === 0) {
            return $this->result($module, $physical, 'current', '无需升级', notes: $notes);
        }
        if ($dryRun) {
            return $this->result($module, $physical, 'planned', 'dry-run：仅列出计划，未执行', $sql, $backfill, notes: $notes);
        }
        if ($plan['clauses'] !== []) $this->apply($reader, $physical, $plan);
        if ($plan['token']) {
            $backfill = $reader->table($physical)->whereNotNull('deleted_at')->where(GeneratedSoftDeleteColumns::TOKEN, 0)
                ->update([GeneratedSoftDeleteColumns::TOKEN => $reader->raw($reader->getQueryGrammar()->wrap($plan['source']))]);
        }
        return $this->result($module, $physical, 'upgraded', '已升级', $sql, $backfill, notes: $notes);
    }

    /**
     * 插件目标的生成表：不直接 ALTER。先做前置检查（dry-run 同样执行）：插件迁移目录可写、插件没有进行中的操作、
     * 版本号未被占用；再写出可重复执行的插件迁移，并经 MigrationService::runVersion(..., 'plugin:<code>') 执行
     * （库级迁移锁、登记 system_migration，与其他安装执行的是同一份 SQL）。写入失败时不做任何改动；执行失败时撤回文件。
     */
    private function upgradePluginTable(array $module, string $plugin, string $physical, string $logical, Connection $connection,
        array $plan, array $sql, int $backfill, array $notes, bool $dryRun): array
    {
        if ($connection->getName() !== Db::connection()->getName()) {
            throw new InvalidArgumentException('插件目标的业务表必须位于默认连接');
        }
        $migration = $this->pluginMigration($plugin, $physical, $logical, $connection->getTablePrefix(), $plan);
        $scope = 'plugin:' . $plugin;
        $version = $migration['version'];
        if ($version === null) {
            // 最新快照已是升级后的形状：该迁移尚未在本库执行时执行它，否则表结构必须已经到位。
            $latest = $migration['latest'];
            $pending = !$this->recorded($scope, $latest);
            if (!$pending && $plan['clauses'] === [] && $backfill === 0) {
                return $this->result($module, $physical, 'current', '无需升级', notes: $notes);
            }
            if (!$pending) {
                throw new RuntimeException('表结构与插件迁移 ' . $latest . ' 的结构快照不一致，请人工检查后手写插件迁移');
            }
            $this->assertPluginWritable($plugin, $migration['directory'], null);
            $file = $migration['relative'] . '/' . $latest . '.sql';
            if ($dryRun) {
                return $this->result($module, $physical, 'planned', 'dry-run：将执行尚未在本库执行的插件迁移，未执行', $sql, $backfill, $file, $notes);
            }
            $this->assertMysql($connection);
            (new MigrationService())->runVersion($migration['directory'], $latest, $scope);
            return $this->result($module, $physical, 'upgraded', '已执行插件迁移 ' . $latest, $sql, $backfill, $file, $notes);
        }
        $this->assertPluginWritable($plugin, $migration['directory'], $version);
        $file = $migration['relative'] . '/' . $version . '.sql';
        $sql = [$migration['content']];
        if ($dryRun) {
            return $this->result($module, $physical, 'planned', 'dry-run：仅列出计划，未写文件、未执行', $sql, $backfill, $file, $notes);
        }
        $this->assertMysql($connection);
        $absolute = $migration['directory'] . '/' . $version . '.sql';
        $handle = @fopen($absolute, 'x');
        if ($handle === false) {
            throw new RuntimeException('无法写入插件迁移 ' . $file . '，未做任何改动');
        }
        $written = fwrite($handle, $migration['content']);
        fclose($handle);
        if ($written !== strlen($migration['content'])) {
            @unlink($absolute);
            throw new RuntimeException('无法写入插件迁移 ' . $file . '，未做任何改动');
        }
        try {
            (new MigrationService())->runVersion($migration['directory'], $version, $scope);
        } catch (Throwable $error) {
            // 未登记的文件撤回；迁移的每个子句都按 information_schema 守卫，重新执行本命令即可继续。
            if (!$this->recorded($scope, $version)) @unlink($absolute);
            throw new RuntimeException('插件迁移 ' . $file . ' 执行失败，已撤回该文件：' . $error->getMessage(), 0, $error);
        }
        return $this->result($module, $physical, 'upgraded', '已写出并执行插件迁移（已登记 ' . $scope . '/' . $version . '）', $sql, $backfill, $file, $notes);
    }

    private function assertMysql(Connection $connection): void
    {
        if ($connection->getDriverName() !== 'mysql') {
            throw new RuntimeException('插件目标的升级经插件迁移执行，只支持 MySQL');
        }
    }

    private function recorded(string $scope, string $version): bool
    {
        return Db::schema()->hasTable('system_migration')
            && SystemMigration::ledger()->where('scope', $scope)->where('version', $version)->exists();
    }

    /** 前置检查：迁移目录可写、插件没有进行中的生命周期操作或待恢复操作、新版本号未被占用。 */
    private function assertPluginWritable(string $plugin, string $directory, ?string $version): void
    {
        clearstatcache(true, $directory);
        if (!is_dir($directory) || !is_writable($directory)) {
            throw new InvalidArgumentException('插件 ' . $plugin . ' 的迁移目录不可写，未做任何改动');
        }
        if (Db::schema()->hasTable('plugin')) {
            $state = Plugin::query()->where('code', $plugin)->first();
            if ($state !== null && (!empty($state->operation_token) || !empty($state->recovery_token)
                || !in_array((string) ($state->lifecycle_state ?? 'enabled'), ['discovered', 'enabled', 'disabled'], true))) {
                throw new InvalidArgumentException('插件 ' . $plugin . ' 正在执行生命周期操作或待恢复，未做任何改动');
            }
        }
        if ($version !== null && (file_exists($directory . '/' . $version . '.sql') || $this->recorded('plugin:' . $plugin, $version))) {
            throw new InvalidArgumentException('插件迁移版本 ' . $version . ' 已被占用，未做任何改动');
        }
    }

    /** 核心表与插件基线表不属于业务表；插件生成表只属于它的插件目标。 */
    private function assertBusinessTable(string $physical, string $logical, string $plugin): void
    {
        // 物理名去掉前缀后与核心/插件基线表重名也拒绝（例如采纳的无前缀表 admin），宁可拒绝也不误改。
        $owners = $this->baselineOwners();
        $owner = $owners[$logical] ?? $owners[$physical] ?? null;
        if ($owner === 'core') {
            throw new InvalidArgumentException('表 ' . $physical . ' 是核心表或与核心表重名，由核心迁移 005_soft_delete 处理，拒绝');
        }
        if ($owner !== null) {
            throw new InvalidArgumentException('表 ' . $physical . ' 是插件 ' . $owner . ' 的基线表或与之重名，由插件迁移处理，拒绝');
        }
        $generated = $this->generatedOwners()[$physical] ?? null;
        if ($generated !== null && $generated !== $plugin) {
            throw new InvalidArgumentException('表 ' . $physical . ' 由插件 ' . $generated . ' 生成，与业务模块目标不一致，拒绝');
        }
    }

    /** @return array<string, string> */
    private function baselineOwners(): array
    {
        if ($this->baselineOwners !== null) return $this->baselineOwners;
        $templatePrefix = (string) config('funadmin.mysqlPrefix', 'fun_');
        $prefix = Db::connection()->getTablePrefix();
        $owners = [];
        $files = array_map(static fn (string $file): array => [$file, 'core'], glob(MigrationService::coreDirectory() . '/*.sql') ?: []);
        foreach (glob($this->projectRoot . '/plugins/*/database/migrations/*.sql') ?: [] as $file) {
            $files[] = [$file, basename(dirname($file, 3))];
        }
        foreach ($files as [$file, $owner]) {
            $sql = (string) file_get_contents($file);
            // 生成器写出的插件迁移（带结构快照）属于插件生成表，见 generatedOwners()。
            if ($owner !== 'core' && preg_match('/^-- funadmin-crud-schema: /m', $sql) === 1) continue;
            preg_match_all('/CREATE\s+TABLE\s+(?:IF\s+NOT\s+EXISTS\s+)?`?([a-z][a-z0-9_]*)`?/i', $sql, $matches);
            foreach ($matches[1] as $table) {
                $table = strtolower($table);
                // 统一成逻辑名：模板 SQL 去掉模板前缀，物理表文件去掉当前连接前缀。
                $strip = str_starts_with($sql, "-- funadmin-physical-table\n") ? $prefix : $templatePrefix;
                if ($strip !== '' && str_starts_with($table, $strip)) $table = substr($table, strlen($strip));
                $owners[$table] ??= $owner;
            }
        }
        return $this->baselineOwners = $owners;
    }

    /** @return array<string, string> */
    private function generatedOwners(): array
    {
        if ($this->generatedOwners !== null) return $this->generatedOwners;
        $owners = [];
        foreach (glob($this->projectRoot . '/plugins/*/database/migrations/*.sql') ?: [] as $file) {
            $snapshot = self::snapshotOf((string) file_get_contents($file), Db::connection()->getTablePrefix());
            if ($snapshot !== null && is_string($snapshot['table'] ?? null)) $owners[$snapshot['table']] ??= basename(dirname($file, 3));
        }
        return $this->generatedOwners = $owners;
    }

    /**
     * @return array{driver: string, columns: array<string, array{type: string, auto: bool}>, primary: list<string>, indexes: array<string, array{unique: bool, columns: list<string>, parts: list<string>}>}
     */
    private function inspect(Connection $reader, string $physical): array
    {
        $driver = $reader->getDriverName();
        $columns = [];
        $indexes = [];
        if ($driver === 'mysql') {
            foreach ($reader->select('SELECT COLUMN_NAME, DATA_TYPE, EXTRA FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? ORDER BY ORDINAL_POSITION', [$physical]) as $row) {
                $row = array_change_key_case((array) $row, CASE_LOWER);
                $columns[(string) $row['column_name']] = ['type' => strtolower((string) $row['data_type']), 'auto' => str_contains(strtolower((string) $row['extra']), 'auto_increment')];
            }
            // SHOW INDEX 按表内索引顺序返回，并带前缀长度（sub_part），重建时原样保留。
            foreach ($reader->select('SHOW INDEX FROM ' . self::quote($physical)) as $row) {
                $row = array_change_key_case((array) $row, CASE_LOWER);
                if (($row['column_name'] ?? null) === null) throw new RuntimeException('不支持函数索引：' . $physical . '.' . $row['key_name']);
                $name = (string) $row['key_name'];
                $indexes[$name]['unique'] = (int) $row['non_unique'] === 0;
                $indexes[$name]['columns'][] = (string) $row['column_name'];
                $indexes[$name]['parts'][] = self::quote((string) $row['column_name']) . ($row['sub_part'] !== null ? '(' . (int) $row['sub_part'] . ')' : '');
            }
        } elseif ($driver === 'sqlite') {
            $schema = $reader->getSchemaBuilder();
            foreach ($schema->getColumns($physical) as $column) {
                $columns[(string) $column['name']] = ['type' => strtolower((string) $column['type_name']), 'auto' => (bool) $column['auto_increment']];
            }
            foreach ($schema->getIndexes($physical) as $index) {
                $name = $index['primary'] ? 'PRIMARY' : (string) $index['name'];
                $indexes[$name] = ['unique' => (bool) $index['unique'], 'columns' => array_values($index['columns']),
                    'parts' => array_map(self::quote(...), array_values($index['columns']))];
            }
        } else {
            throw new RuntimeException('只支持 MySQL 与 SQLite，当前驱动：' . $driver);
        }
        return ['driver' => $driver, 'columns' => $columns, 'primary' => $indexes['PRIMARY']['columns'] ?? [], 'indexes' => $indexes];
    }

    /**
     * 只含仍缺的子句；子句顺序：令牌列、row_no、deleted_at 索引、唯一键重建（同一条 ALTER 内先删后建）。
     *
     * @return array{clauses: list<string>, token: bool, addToken: bool, rowNo: ?string, deletedAtIndex: ?string, rebuild: array<string, list<string>>, source: string}
     */
    private function plan(string $physical, array $state): array
    {
        $columns = $state['columns'];
        $primary = $state['primary'];
        $unique = [];
        foreach ($state['indexes'] as $name => $index) {
            if ($name === 'PRIMARY' || !$index['unique'] || $index['columns'] === $primary) continue;
            $unique[$name] = $index;
        }
        $token = $unique !== [];
        if (count($primary) !== 1 && $token) {
            throw new RuntimeException('表 ' . $physical . ' 没有单列主键，无法确定删除令牌来源，请手写迁移');
        }
        $integerPrimary = $primary !== [] && in_array(preg_replace('/\W.*$/', '', $columns[$primary[0]]['type'] ?? ''), self::INTEGER_TYPES, true);
        $needsRowNo = $token && !$integerPrimary;
        $clauses = [];
        $addToken = $token && !isset($columns[GeneratedSoftDeleteColumns::TOKEN]);
        if ($addToken) $clauses[] = 'ADD COLUMN `deleted_token` BIGINT UNSIGNED NOT NULL DEFAULT 0';
        $rowNo = null;
        if ($needsRowNo && !isset($columns[GeneratedSoftDeleteColumns::ROW_NO])) {
            foreach ($columns as $name => $column) {
                if ($column['auto']) throw new RuntimeException('表 ' . $physical . ' 已有自增列 ' . $name . '，无法添加 row_no，请手写迁移');
            }
            $rowNo = 'idx_' . $physical . '_row_no';
            $clauses[] = 'ADD COLUMN `row_no` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, ADD KEY ' . self::quote($rowNo) . ' (`row_no`)';
        }
        $deletedAtIndex = null;
        if (array_filter($state['indexes'], static fn (array $index): bool => ($index['columns'][0] ?? null) === 'deleted_at') === []) {
            $deletedAtIndex = 'idx_' . $physical . '_deleted_at';
            $clauses[] = 'ADD INDEX ' . self::quote($deletedAtIndex) . ' (`deleted_at`)';
        }
        $rebuild = [];
        foreach ($unique as $name => $index) {
            if (in_array(GeneratedSoftDeleteColumns::TOKEN, $index['columns'], true)) continue;
            $rebuild[$name] = [...$index['columns'], GeneratedSoftDeleteColumns::TOKEN];
            $clauses[] = 'DROP INDEX ' . self::quote($name) . ', ADD UNIQUE KEY ' . self::quote($name) . ' (' . implode(', ', [...$index['parts'], '`deleted_token`']) . ')';
        }
        return [
            'clauses' => $clauses, 'token' => $token, 'addToken' => $addToken, 'rowNo' => $rowNo,
            'deletedAtIndex' => $deletedAtIndex, 'rebuild' => $rebuild,
            'source' => $needsRowNo ? GeneratedSoftDeleteColumns::ROW_NO : ($primary[0] ?? 'id'),
        ];
    }

    private function apply(Connection $reader, string $physical, array $plan): void
    {
        if ($reader->getDriverName() === 'mysql') {
            // DDL 隐式提交，不能放在外层事务里。
            if ($reader->transactionLevel() > 0) throw new RuntimeException('软删除升级的 MySQL DDL 禁止在外层事务中执行');
            $reader->statement('ALTER TABLE ' . self::quote($physical) . ' ' . implode(', ', $plan['clauses']));
            return;
        }
        // SQLite（测试库）：按同样的计划逐项执行；不支持非主键自增列，row_no 只在 MySQL 上执行。
        if ($plan['rowNo'] !== null) {
            throw new RuntimeException('主键不是整数的表需要自增列 row_no，只能在 MySQL 上执行升级');
        }
        $schema = $reader->getSchemaBuilder();
        if ($plan['addToken']) {
            $schema->table($physical, static function (Blueprint $table): void {
                $table->unsignedBigInteger(GeneratedSoftDeleteColumns::TOKEN)->default(0);
            });
        }
        $schema->table($physical, static function (Blueprint $table) use ($plan): void {
            if ($plan['deletedAtIndex'] !== null) $table->index('deleted_at', $plan['deletedAtIndex']);
            foreach ($plan['rebuild'] as $name => $columns) {
                $table->dropUnique($name);
                $table->unique($columns, $name);
            }
        });
    }

    /**
     * 插件生成表的升级迁移：以该表最新结构快照为基础，加上升级后的形状（deleted_at 索引、令牌、row_no）；
     * 与最新快照相同时不写（version 为 null）。SQL 只用 ASCII，每个子句按 information_schema 守卫，DROP INDEX 只出现在 PREPARE 中。
     *
     * @return array{relative: string, directory: string, latest: string, version: ?string, content: string}
     */
    private function pluginMigration(string $plugin, string $physical, string $logical, string $prefix, array $plan): array
    {
        if (preg_match('/\A[a-z][a-z0-9]*\z/D', $plugin) !== 1) throw new InvalidArgumentException('插件代码不合法：' . $plugin);
        $relative = 'plugins/' . $plugin . '/database/migrations';
        $directory = PathGuard::resolve($this->projectRoot, $relative, '插件迁移目录');
        if (!is_dir($directory)) {
            throw new InvalidArgumentException('插件 ' . $plugin . ' 没有迁移目录，无法写插件升级迁移，未做任何改动');
        }
        $maximum = 0;
        $previous = null;
        $latest = '';
        $files = glob($directory . '/*.sql') ?: [];
        sort($files, SORT_STRING);
        foreach ($files as $file) {
            if (preg_match('/^(\d{3})_/', basename($file), $match) === 1) $maximum = max($maximum, (int) $match[1]);
            $snapshot = self::snapshotOf((string) file_get_contents($file), $prefix);
            if ($snapshot !== null && ($snapshot['table'] ?? null) === $physical) {
                $previous = $snapshot;
                $latest = pathinfo($file, PATHINFO_FILENAME);
            }
        }
        if ($previous === null) {
            throw new InvalidArgumentException('插件迁移中没有该表的结构快照，无法生成插件升级迁移；请手写插件迁移，未做任何改动');
        }
        $snapshot = $previous;
        $fields = (array) ($snapshot['fields'] ?? []);
        if (isset($fields['deleted_at'])) $fields['deleted_at']['index'] = 'index';
        if ($plan['token']) {
            $fields[GeneratedSoftDeleteColumns::TOKEN] ??= ['definition' => GeneratedSoftDeleteColumns::TOKEN_DEFINITION, 'index' => ''];
            if ($plan['source'] === GeneratedSoftDeleteColumns::ROW_NO) {
                $fields[GeneratedSoftDeleteColumns::ROW_NO] ??= ['definition' => GeneratedSoftDeleteColumns::ROW_NO_DEFINITION, 'index' => 'index'];
            }
        }
        $snapshot['fields'] = $fields;
        $result = ['relative' => $relative, 'directory' => $directory, 'latest' => $latest, 'version' => null, 'content' => ''];
        if (CrudDefinition::canonicalJson($snapshot) === CrudDefinition::canonicalJson($previous)) return $result;
        if ($maximum >= 999) throw new RuntimeException('插件 migration 三位编号已耗尽');
        $result['version'] = sprintf('%03d_soft_delete_%s', $maximum + 1, preg_replace('/[^a-z0-9_]/', '_', $logical));
        $result['content'] = $this->pluginMigrationSql($physical, $snapshot, $plan) . '-- funadmin-crud-schema: ' . base64_encode(CrudDefinition::canonicalJson($snapshot)) . "\n";
        return $result;
    }

    private function pluginMigrationSql(string $physical, array $snapshot, array $plan): string
    {
        $table = "TABLE_SCHEMA=DATABASE() AND TABLE_NAME='" . $physical . "'";
        $guards = [];
        $fields = (array) $snapshot['fields'];
        $token = isset($fields[GeneratedSoftDeleteColumns::TOKEN]);
        if ($token) {
            $guards[] = "IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE {$table} AND COLUMN_NAME='deleted_token')=0, 'ADD COLUMN `deleted_token` BIGINT UNSIGNED NOT NULL DEFAULT 0', NULL)";
        }
        if (isset($fields[GeneratedSoftDeleteColumns::ROW_NO])) {
            $guards[] = "IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE {$table} AND COLUMN_NAME='row_no')=0, 'ADD COLUMN `row_no` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, ADD KEY `idx_{$physical}_row_no` (`row_no`)', NULL)";
        }
        if (isset($fields['deleted_at'])) {
            $guards[] = "IF((SELECT COUNT(*) FROM information_schema.STATISTICS WHERE {$table} AND COLUMN_NAME='deleted_at' AND SEQ_IN_INDEX=1)=0, 'ADD INDEX `idx_{$physical}_deleted_at` (`deleted_at`)', NULL)";
        }
        if ($token) {
            foreach ($fields as $name => $field) {
                if (($field['index'] ?? '') !== 'unique') continue;
                $index = 'uk_' . $physical . '_' . $name;
                $guards[] = "IF((SELECT COUNT(*) FROM information_schema.STATISTICS WHERE {$table} AND INDEX_NAME='{$index}')>0"
                    . " AND (SELECT COUNT(*) FROM information_schema.STATISTICS WHERE {$table} AND INDEX_NAME='{$index}' AND COLUMN_NAME='deleted_token')=0,"
                    . " 'DROP INDEX `{$index}`, ADD UNIQUE KEY `{$index}` (`{$name}`, `deleted_token`)', NULL)";
            }
        }
        $sql = "-- funadmin-physical-table\n"
            . "-- Soft delete upgrade for {$physical} (written by business:soft-delete-upgrade), same rules as core 005_soft_delete:\n"
            . "-- index deleted_at; when the table has unique keys add deleted_token (row_no as token source for non-integer primary keys),\n"
            . "-- backfill deleted_token for trashed rows and rebuild each unique key as (column, deleted_token) under the same name.\n"
            . "-- Every clause is guarded by information_schema and can be re-run; one ALTER, executed with PREPARE.\n"
            . "SET @soft_delete_ddl = CONCAT_WS(', ',\n  " . implode(",\n  ", $guards) . "\n);\n"
            . "SET @soft_delete_ddl = IF(@soft_delete_ddl = '', 'DO 0', CONCAT('ALTER TABLE `{$physical}` ', @soft_delete_ddl));\n"
            . "PREPARE soft_delete_stmt FROM @soft_delete_ddl;\nEXECUTE soft_delete_stmt;\nDEALLOCATE PREPARE soft_delete_stmt;\n";
        if ($token) {
            $sql .= "UPDATE `{$physical}` SET `deleted_token` = `{$plan['source']}` WHERE `deleted_at` IS NOT NULL AND `deleted_token` = 0;\n";
        }
        return $sql;
    }

    /** 迁移里的结构快照；旧文件（无物理表标记）按模板前缀改写成当前前缀后比较，与 PluginCrudTarget 一致。 */
    private static function snapshotOf(string $content, string $prefix): ?array
    {
        if (preg_match('/^-- funadmin-crud-schema: ([A-Za-z0-9+\/=]+)$/m', $content, $match) !== 1) return null;
        $decoded = base64_decode($match[1], true);
        $snapshot = is_string($decoded) ? json_decode($decoded, true) : null;
        if (!is_array($snapshot)) return null;
        if (!str_starts_with($content, "-- funadmin-physical-table\n")) {
            $snapshot = json_decode(MigrationService::rewritePrefix(CrudDefinition::canonicalJson($snapshot), (string) config('funadmin.mysqlPrefix', 'fun_'), $prefix), true, 512, JSON_THROW_ON_ERROR);
        }
        return $snapshot;
    }

    private static function quote(string $identifier): string
    {
        return '`' . str_replace('`', '``', $identifier) . '`';
    }

    private function result(array $module, string $table, string $status, string $message, array $sql = [], int $backfill = 0, ?string $migration = null, array $notes = []): array
    {
        return ['module' => (string) ($module['code'] ?? ''), 'table' => $table, 'status' => $status, 'message' => $message,
            'sql' => $sql, 'backfill' => $backfill, 'migration' => $migration, 'notes' => $notes];
    }
}
