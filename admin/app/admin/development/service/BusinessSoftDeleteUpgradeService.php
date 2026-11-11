<?php

declare(strict_types=1);

namespace app\admin\development\service;

use app\admin\development\model\BusinessModule;
use app\common\crud\CrudDefinition;
use app\common\crud\GeneratedSoftDeleteColumns;
use app\common\crud\PathGuard;
use app\common\model\concern\TokenSoftDeletingScope;
use app\common\service\database\MigrationService;
use Illuminate\Database\Connection;
use Illuminate\Database\Schema\Blueprint;
use InvalidArgumentException;
use RuntimeException;
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
            } catch (InvalidArgumentException|RuntimeException $error) {
                $results[] = $this->result($module, (string) $module['table_name'], 'refused', $error->getMessage());
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
        $matched = array_values(array_filter($rows, function (array $module) use ($table): bool {
            [$physical, $logical] = $this->names($module);
            return in_array($table, [$physical, $logical], true);
        }));
        if ($matched === []) {
            throw new InvalidArgumentException('表 ' . $table . ' 不是已发布的业务模块表（business_module 未登记或未发布），拒绝处理');
        }
        return $matched;
    }

    /** @return array{0: string, 1: string} [物理表名, 逻辑表名] */
    private function names(array $module): array
    {
        $prefix = $this->connection($module)->getTablePrefix();
        $table = (string) ($module['table_name'] ?? '');
        $logical = $prefix !== '' && str_starts_with($table, $prefix) ? substr($table, strlen($prefix)) : $table;
        return [$prefix . $logical, $logical];
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
            $notes[] = '主键不是整数：令牌来源为 row_no，请重新生成该模块代码（模型 getDeletedTokenSourceColumn() 返回 row_no），否则软删除会把主键写入数字令牌列';
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
        $migration = $plugin !== '' && ($target['tableStrategy'] ?? 'owned') === 'owned'
            ? $this->pluginMigration($plugin, $physical, $logical, $connection->getTablePrefix(), $plan) : null;
        if ($migration !== null && $migration['note'] !== null) $notes[] = $migration['note'];
        $file = $migration['path'] ?? null;
        if ($plan['clauses'] === [] && $backfill === 0 && $file === null) {
            return $this->result($module, $physical, 'current', '无需升级', notes: $notes);
        }
        if ($dryRun) {
            return $this->result($module, $physical, 'planned', 'dry-run：仅列出计划，未执行', $sql, $backfill, $file, $notes);
        }
        if ($plan['clauses'] !== []) $this->apply($reader, $physical, $plan);
        if ($plan['token']) {
            $backfill = $reader->table($physical)->whereNotNull('deleted_at')->where(GeneratedSoftDeleteColumns::TOKEN, 0)
                ->update([GeneratedSoftDeleteColumns::TOKEN => $reader->raw($reader->getQueryGrammar()->wrap($plan['source']))]);
        }
        if ($migration !== null && $file !== null) {
            $absolute = PathGuard::resolve($this->projectRoot, $file, '插件迁移');
            if (file_exists($absolute)) throw new RuntimeException('插件迁移已存在，拒绝覆盖：' . $file);
            if (file_put_contents($absolute, $migration['content'], LOCK_EX) === false) throw new RuntimeException('无法写入插件迁移：' . $file);
        }
        return $this->result($module, $physical, 'upgraded', '已升级', $sql, $backfill, $file, $notes);
    }

    /** 核心表与插件基线表不属于业务表；插件生成表只属于它的插件目标。 */
    private function assertBusinessTable(string $physical, string $logical, string $plugin): void
    {
        $owners = $this->baselineOwners();
        if (($owners[$logical] ?? null) === 'core') {
            throw new InvalidArgumentException('表 ' . $physical . ' 是核心表，由核心迁移 005_soft_delete 处理，拒绝');
        }
        if (isset($owners[$logical])) {
            throw new InvalidArgumentException('表 ' . $physical . ' 是插件 ' . $owners[$logical] . ' 的基线表，由插件迁移处理，拒绝');
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
                if (!str_starts_with($sql, "-- funadmin-physical-table\n") && $templatePrefix !== '' && str_starts_with($table, $templatePrefix)) {
                    $table = substr($table, strlen($templatePrefix));
                }
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
     * 与最新快照相同时不写。SQL 只用 ASCII，每个子句按 information_schema 守卫，DROP INDEX 只出现在 PREPARE 中。
     *
     * @return array{path: ?string, content: string, note: ?string}
     */
    private function pluginMigration(string $plugin, string $physical, string $logical, string $prefix, array $plan): array
    {
        if (preg_match('/\A[a-z][a-z0-9]*\z/D', $plugin) !== 1) throw new InvalidArgumentException('插件代码不合法：' . $plugin);
        $relative = 'plugins/' . $plugin . '/database/migrations';
        $directory = $this->projectRoot . '/' . $relative;
        if (!is_dir($directory)) {
            return ['path' => null, 'content' => '', 'note' => '插件 ' . $plugin . ' 没有迁移目录，未写升级迁移；其他安装需各自运行本命令'];
        }
        $maximum = 0;
        $previous = null;
        $files = glob($directory . '/*.sql') ?: [];
        sort($files, SORT_STRING);
        foreach ($files as $file) {
            if (preg_match('/^(\d{3})_/', basename($file), $match) === 1) $maximum = max($maximum, (int) $match[1]);
            $snapshot = self::snapshotOf((string) file_get_contents($file), $prefix);
            if ($snapshot !== null && ($snapshot['table'] ?? null) === $physical) $previous = $snapshot;
        }
        if ($previous === null) {
            return ['path' => null, 'content' => '', 'note' => '插件迁移中没有该表的结构快照，未写升级迁移；其他安装需各自运行本命令'];
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
        if (CrudDefinition::canonicalJson($snapshot) === CrudDefinition::canonicalJson($previous)) {
            return ['path' => null, 'content' => '', 'note' => null];
        }
        if ($maximum >= 999) throw new RuntimeException('插件 migration 三位编号已耗尽');
        $name = sprintf('%03d_soft_delete_%s.sql', $maximum + 1, preg_replace('/[^a-z0-9_]/', '_', $logical));
        return ['path' => $relative . '/' . $name, 'content' => $this->pluginMigrationSql($physical, $snapshot, $plan) . '-- funadmin-crud-schema: ' . base64_encode(CrudDefinition::canonicalJson($snapshot)) . "\n", 'note' => null];
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
