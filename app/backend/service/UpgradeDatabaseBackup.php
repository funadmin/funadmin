<?php

declare(strict_types=1);

namespace app\backend\service;

use PDO;
use RuntimeException;
use Throwable;

/** 创建完整 MySQL 快照，并仅通过已验证的 shadow schema 执行恢复。 */
final class UpgradeDatabaseBackup
{
    private const FORMAT = 'funadmin-mysql-backup-v2';
    private const SHADOW_PREFIX = 'funadmin_shadow_';
    private const BACKUP_PREFIX = 'funadmin_backup_';

    public function __construct(private readonly ?PDO $connection = null)
    {
    }

    public function backup(string $path): string
    {
        $this->assertSafeTarget($path, false);
        $directory = dirname($path);
        if (!is_dir($directory) && !mkdir($directory, 0700, true) && !is_dir($directory)) {
            throw new RuntimeException('无法创建数据库备份目录');
        }
        chmod($directory, 0700);
        $handle = fopen($path, 'xb');
        if ($handle === false) {
            throw new RuntimeException('无法创建数据库备份文件');
        }
        chmod($path, 0600);
        $pdo = $this->pdo();
        try {
            $database = $this->databaseName($pdo);
            $this->assertSnapshotPrivileges($pdo, $database);
            $pdo->exec('SET TRANSACTION ISOLATION LEVEL REPEATABLE READ');
            $pdo->exec('START TRANSACTION WITH CONSISTENT SNAPSHOT');
            $snapshot = $this->snapshotMetadata($pdo, $database);
            $this->write($handle, ['format' => self::FORMAT] + $snapshot);
            $tableStats = [];
            foreach ($snapshot['objects']['tables'] as $table) {
                $columns = $this->dataColumns($pdo, $database, $table);
                $this->write($handle, [
                    'type' => 'schema', 'object_type' => 'table', 'name' => $table,
                    'sql' => $this->showCreate($pdo, 'TABLE', $table, $database), 'columns' => $columns,
                ]);
                $context = hash_init('sha256');
                $count = 0;
                $select = implode(',', array_map(fn (string $column): string => $this->quoteIdentifier($column), $columns));
                $statement = $pdo->query('SELECT ' . $select . ' FROM ' . $this->quoteIdentifier($table));
                while (($row = $statement->fetch(PDO::FETCH_ASSOC)) !== false) {
                    $encoded = $this->encodeRow($row);
                    $canonical = json_encode($encoded, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
                    hash_update($context, pack('N', strlen($canonical)) . $canonical);
                    $this->write($handle, ['type' => 'row', 'table' => $table, 'values' => $encoded]);
                    $count++;
                }
                $statement->closeCursor();
                $tableStats[$table] = ['rows' => $count, 'hash' => hash_final($context)];
            }
            foreach (['functions', 'procedures', 'views', 'triggers', 'events'] as $type) {
                foreach ($snapshot['objects'][$type] as $name) {
                    $this->write($handle, [
                        'type' => 'schema', 'object_type' => rtrim($type, 's'), 'name' => $name,
                        'sql' => $this->objectDefinition($pdo, $type, $name, $database),
                    ]);
                }
            }
            $this->write($handle, ['type' => 'manifest', 'table_stats' => $tableStats]);
            $pdo->commit();
            fflush($handle);
        } catch (Throwable $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            fclose($handle);
            @unlink($path);
            throw new RuntimeException('创建一致性数据库备份失败：' . $exception->getMessage(), 0, $exception);
        }
        fclose($handle);
        $hash = hash_file('sha256', $path);
        if (!is_string($hash)) {
            throw new RuntimeException('无法计算数据库备份哈希');
        }
        return $hash;
    }

    /** @return array{backup_schema:string,shadow_schema:string} */
    public function restore(string $path, string $expectedHash): array
    {
        $this->assertSafeTarget($path, true);
        $actualHash = hash_file('sha256', $path);
        if (!is_string($actualHash) || !hash_equals($expectedHash, $actualHash)) {
            throw new RuntimeException('数据库备份 SHA-256 校验失败');
        }
        $records = $this->read($path);
        $header = array_shift($records);
        $manifest = array_pop($records);
        $this->assertHeader($header, $manifest);
        $pdo = $this->pdo();
        $production = $this->databaseName($pdo);
        $shadow = self::SHADOW_PREFIX . bin2hex(random_bytes(8));
        $backup = self::BACKUP_PREFIX . bin2hex(random_bytes(8));
        $lock = 'funadmin_restore_' . substr(hash('sha256', $production), 0, 32);
        if ((int) $pdo->query('SELECT GET_LOCK(' . $pdo->quote($lock) . ', 0)')->fetchColumn() !== 1) {
            throw new RuntimeException('数据库恢复维护模式锁定失败');
        }
        try {
            $this->createSchema($pdo, $shadow, $header['database']);
            try {
                $this->restoreIntoSchema($pdo, $shadow, $records, $header);
                $this->validateSchema($pdo, $shadow, $header, $manifest['table_stats']);
            } catch (Throwable $exception) {
                $this->useSchema($pdo, $production);
                $this->dropInternalSchema($pdo, $shadow);
                throw new RuntimeException('shadow 数据库恢复验证失败：' . $exception->getMessage(), 0, $exception);
            }
            $current = $this->snapshotMetadata($pdo, $production);
            $this->createSchema($pdo, $backup, $current['database']);
            try {
                $this->switchSchemas($pdo, $production, $shadow, $backup, $records, $header, $current, $manifest['table_stats']);
            } catch (Throwable $exception) {
                $this->useSchema($pdo, $production);
                throw new RuntimeException('shadow 数据库安全切换失败，backup schema 已保留供人工恢复：' . $exception->getMessage(), 0, $exception);
            }
            $this->useSchema($pdo, $production);
            $this->dropInternalSchema($pdo, $shadow);
            return ['backup_schema' => $backup, 'shadow_schema' => $shadow];
        } finally {
            try {
                $this->useSchema($pdo, $production);
                $pdo->query('SELECT RELEASE_LOCK(' . $pdo->quote($lock) . ')')->fetchColumn();
            } catch (Throwable) {
                // 释放维护锁失败不得覆盖恢复阶段的原始异常。
            }
        }
    }

    /** 只清理带内部 marker 且超过保留期的孤立 shadow；backup 必须人工确认后另行清理。 */
    public function cleanupOldShadows(int $olderThanSeconds = 86400): array
    {
        $pdo = $this->pdo();
        $cutoff = time() - max(3600, $olderThanSeconds);
        $removed = [];
        foreach ($this->schemasLike($pdo, self::SHADOW_PREFIX . '%') as $schema) {
            try {
                $createdAt = $pdo->query('SELECT `created_at` FROM ' . $this->qualified($schema, '__funadmin_shadow_marker') . ' LIMIT 1')->fetchColumn();
                if (is_string($createdAt) && strtotime($createdAt) <= $cutoff) {
                    $this->dropInternalSchema($pdo, $schema);
                    $removed[] = $schema;
                }
            } catch (Throwable) {
                // 没有合法 marker 的 schema 不属于本服务，禁止清理。
            }
        }
        return $removed;
    }

    private function restoreIntoSchema(PDO $pdo, string $schema, array $records, array $header): void
    {
        $this->useSchema($pdo, $schema);
        $pdo->exec('CREATE TABLE `__funadmin_shadow_marker` (`created_at` datetime NOT NULL) ENGINE=InnoDB');
        $pdo->exec("INSERT INTO `__funadmin_shadow_marker` VALUES (NOW())");
        $originalForeignKeys = (int) $pdo->query('SELECT @@SESSION.FOREIGN_KEY_CHECKS')->fetchColumn();
        $failure = null;
        try {
            $pdo->exec('SET FOREIGN_KEY_CHECKS=0');
            $nonTables = [];
            foreach ($records as $record) {
                if (($record['type'] ?? '') === 'schema' && ($record['object_type'] ?? '') !== 'table') {
                    $nonTables[] = $record;
                } elseif (($record['type'] ?? '') === 'schema') {
                    $this->createRecord($pdo, $record, $header['database']['name'], $schema);
                } elseif (($record['type'] ?? '') === 'row') {
                    $this->insertRow($pdo, $schema, (string) ($record['table'] ?? ''), (array) ($record['values'] ?? []));
                } else {
                    throw new RuntimeException('数据库备份包含未知记录');
                }
            }
            $created = [];
            $this->createDefinitions($pdo, $nonTables, $header['database']['name'], $schema, $created);
        } catch (Throwable $exception) {
            $failure = $exception;
        }
        try {
            $pdo->exec('SET FOREIGN_KEY_CHECKS=' . ($originalForeignKeys === 0 ? '0' : '1'));
        } catch (Throwable $foreignKeyException) {
            if ($failure === null) {
                $failure = $foreignKeyException;
            }
        }
        if ($failure !== null) {
            throw $failure;
        }
    }

    private function switchSchemas(PDO $pdo, string $production, string $shadow, string $backup, array $records, array $header, array $current, array $tableStats): void
    {
        $stage = 'capture_production_definitions';
        $currentDefinitions = $this->definitions($pdo, $production, $current['objects']);
        $this->validateDefinitions($currentDefinitions, $current['objects']);
        $snapshotSource = (string) $header['database']['name'];
        $snapshotDefinitions = $this->snapshotDefinitions($records);
        $this->validateDefinitions($snapshotDefinitions, $header['objects']);
        $droppedProduction = [];
        $droppedShadow = [];
        $createdBackup = [];
        $createdProduction = [];
        $renamed = false;
        try {
            $stage = 'drop_production_objects';
            $this->dropNonTableObjects($pdo, $production, $current['objects'], $droppedProduction);
            $stage = 'drop_shadow_objects';
            $this->dropNonTableObjects($pdo, $shadow, $header['objects'], $droppedShadow);
            $renames = $this->switchRenames($production, $shadow, $backup, $current, $header);
            $stage = 'rename_tables';
            $pdo->exec('RENAME TABLE ' . implode(',', $renames));
            $renamed = true;
            $stage = 'create_backup_objects';
            $this->createDefinitions($pdo, $currentDefinitions, $production, $backup, $createdBackup);
            $stage = 'create_production_objects';
            $this->createDefinitions($pdo, $snapshotDefinitions, $snapshotSource, $production, $createdProduction);
            $stage = 'alter_production_schema';
            $database = $header['database'];
            $pdo->exec('ALTER DATABASE ' . $this->quoteIdentifier($production) . ' CHARACTER SET ' . $this->validatedIdentifier($database['charset']) . ' COLLATE ' . $this->validatedIdentifier($database['collation']));
            $stage = 'validate_production_schema';
            $this->validateSchema($pdo, $production, $header, $tableStats);
        } catch (Throwable $exception) {
            if (!$renamed) {
                $this->restoreBeforeRename($pdo, $production, $shadow, $snapshotSource, $currentDefinitions, $snapshotDefinitions, $droppedProduction, $droppedShadow, $stage, $exception);
            } else {
                $this->rollbackAfterRename($pdo, $production, $shadow, $backup, $current, $header, $currentDefinitions, $createdProduction, $createdBackup, $stage, $exception);
            }
            throw new RuntimeException('schema switch failed at stage ' . $stage . '：' . $exception->getMessage(), 0, $exception);
        }
    }

    private function switchRenames(string $production, string $shadow, string $backup, array $current, array $header): array
    {
        $renames = [];
        foreach ($current['objects']['tables'] as $table) {
            $renames[] = $this->qualified($production, $table) . ' TO ' . $this->qualified($backup, $table);
        }
        foreach ($header['objects']['tables'] as $table) {
            $renames[] = $this->qualified($shadow, $table) . ' TO ' . $this->qualified($production, $table);
        }
        if ($renames === []) {
            throw new RuntimeException('快照与生产 schema 均不包含 base table');
        }
        return $renames;
    }

    private function restoreBeforeRename(PDO $pdo, string $production, string $shadow, string $snapshotSource, array $currentDefinitions, array $snapshotDefinitions, array $droppedProduction, array $droppedShadow, string $stage, Throwable $exception): void
    {
        try {
            $restored = [];
            $this->createDefinitions($pdo, $this->selectDefinitions($currentDefinitions, $droppedProduction), $production, $production, $restored);
            $restored = [];
            $this->createDefinitions($pdo, $this->selectDefinitions($snapshotDefinitions, $droppedShadow), $snapshotSource, $shadow, $restored);
        } catch (Throwable $recoveryException) {
            throw $this->manualRecoveryException('restore_pre_rename_objects', $stage, $exception, $recoveryException);
        }
    }

    private function rollbackAfterRename(PDO $pdo, string $production, string $shadow, string $backup, array $current, array $header, array $currentDefinitions, array $createdProduction, array $createdBackup, string $stage, Throwable $exception): void
    {
        $recoveryStage = 'drop_switched_objects';
        try {
            $dropped = [];
            $this->dropDefinitionObjects($pdo, $production, $createdProduction, $dropped);
            $dropped = [];
            $this->dropDefinitionObjects($pdo, $backup, $createdBackup, $dropped);
            $recoveryStage = 'rollback_tables';
            $renames = [];
            foreach ($header['objects']['tables'] as $table) {
                if ($this->tableExists($pdo, $production, $table)) {
                    $renames[] = $this->qualified($production, $table) . ' TO ' . $this->qualified($shadow, $table);
                }
            }
            foreach ($current['objects']['tables'] as $table) {
                if ($this->tableExists($pdo, $backup, $table)) {
                    $renames[] = $this->qualified($backup, $table) . ' TO ' . $this->qualified($production, $table);
                }
            }
            if ($renames !== []) {
                $pdo->exec('RENAME TABLE ' . implode(',', $renames));
            }
            $recoveryStage = 'restore_production_schema';
            $database = $current['database'];
            $pdo->exec('ALTER DATABASE ' . $this->quoteIdentifier($production) . ' CHARACTER SET ' . $this->validatedIdentifier($database['charset']) . ' COLLATE ' . $this->validatedIdentifier($database['collation']));
            $recoveryStage = 'rebuild_production_objects';
            $restored = [];
            $this->createDefinitions($pdo, $currentDefinitions, $production, $production, $restored);
        } catch (Throwable $recoveryException) {
            throw $this->manualRecoveryException($recoveryStage, $stage, $exception, $recoveryException);
        }
    }

    private function manualRecoveryException(string $recoveryStage, string $failedStage, Throwable $original, Throwable $recovery): RuntimeException
    {
        return new RuntimeException(
            'manual recovery required at stage ' . $recoveryStage . ' after ' . $failedStage . '：' . $recovery->getMessage() . '；original：' . $original->getMessage(),
            0,
            $recovery
        );
    }

    private function validateSchema(PDO $pdo, string $schema, array $header, array $tableStats): void
    {
        $metadata = $this->snapshotMetadata($pdo, $schema);
        if (($metadata['database']['charset'] ?? '') !== ($header['database']['charset'] ?? '')
            || ($metadata['database']['collation'] ?? '') !== ($header['database']['collation'] ?? '')) {
            throw new RuntimeException('数据库默认字符集或 collation 不一致');
        }
        foreach ($header['object_hashes'] as $type => $expectedHash) {
            if ($type !== 'tables' && !hash_equals((string) $expectedHash, (string) ($metadata['object_hashes'][$type] ?? ''))) {
                throw new RuntimeException('数据库对象定义 hash 不一致：' . $type);
            }
        }
        $objects = $metadata['objects'];
        foreach ($header['objects'] as $type => $expected) {
            $actual = $objects[$type] ?? [];
            sort($actual);
            sort($expected);
            if ($actual !== $expected) {
                throw new RuntimeException($type . ' 对象清单不一致');
            }
        }
        foreach ($tableStats as $table => $expected) {
            $columns = $this->dataColumns($pdo, $schema, $table);
            $select = implode(',', array_map(fn (string $column): string => $this->quoteIdentifier($column), $columns));
            $statement = $pdo->query('SELECT ' . $select . ' FROM ' . $this->qualified($schema, $table));
            $context = hash_init('sha256');
            $count = 0;
            while (($row = $statement->fetch(PDO::FETCH_ASSOC)) !== false) {
                $encoded = $this->encodeRow($row);
                $canonical = json_encode($encoded, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
                hash_update($context, pack('N', strlen($canonical)) . $canonical);
                $count++;
            }
            if ($count !== (int) $expected['rows'] || !hash_equals((string) $expected['hash'], hash_final($context))) {
                throw new RuntimeException('表行数或数据 hash 不一致：' . $table);
            }
        }
        $invalidForeignKeys = (int) $pdo->query("SELECT COUNT(*) FROM information_schema.REFERENTIAL_CONSTRAINTS WHERE CONSTRAINT_SCHEMA=" . $pdo->quote($schema) . " AND UNIQUE_CONSTRAINT_SCHEMA<>" . $pdo->quote($schema))->fetchColumn();
        if ($invalidForeignKeys !== 0) {
            throw new RuntimeException('外键指向 shadow schema 之外');
        }
    }

    private function snapshotMetadata(PDO $pdo, string $database): array
    {
        $schema = $pdo->query("SELECT DEFAULT_CHARACTER_SET_NAME,DEFAULT_COLLATION_NAME FROM information_schema.SCHEMATA WHERE SCHEMA_NAME=" . $pdo->quote($database))->fetch(PDO::FETCH_ASSOC);
        if (!is_array($schema)) {
            throw new RuntimeException('无法读取数据库默认字符集');
        }
        $objects = $this->objectLists($pdo, $database);
        $hashes = [];
        foreach ($objects as $type => $names) {
            $definitions = [];
            foreach ($names as $name) {
                $definition = $type === 'tables'
                    ? $this->showCreate($pdo, 'TABLE', $name, $database)
                    : $this->objectDefinition($pdo, $type, $name, $database);
                $definitions[$name] = $this->canonicalDefinition($definition, $database);
            }
            $hashes[$type] = hash('sha256', json_encode($definitions, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
        }
        return [
            'database' => ['name' => $database, 'charset' => $schema['DEFAULT_CHARACTER_SET_NAME'], 'collation' => $schema['DEFAULT_COLLATION_NAME']],
            'objects' => $objects, 'object_hashes' => $hashes,
        ];
    }

    private function objectLists(PDO $pdo, string $database): array
    {
        $quoted = $pdo->quote($database);
        return [
            'tables' => $this->column($pdo, "SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA={$quoted} AND TABLE_TYPE='BASE TABLE' AND TABLE_NAME<>'__funadmin_shadow_marker' ORDER BY TABLE_NAME"),
            'views' => $this->column($pdo, "SELECT TABLE_NAME FROM information_schema.VIEWS WHERE TABLE_SCHEMA={$quoted} ORDER BY TABLE_NAME"),
            'triggers' => $this->column($pdo, "SELECT TRIGGER_NAME FROM information_schema.TRIGGERS WHERE TRIGGER_SCHEMA={$quoted} ORDER BY TRIGGER_NAME"),
            'events' => $this->column($pdo, "SELECT EVENT_NAME FROM information_schema.EVENTS WHERE EVENT_SCHEMA={$quoted} ORDER BY EVENT_NAME"),
            'procedures' => $this->column($pdo, "SELECT ROUTINE_NAME FROM information_schema.ROUTINES WHERE ROUTINE_SCHEMA={$quoted} AND ROUTINE_TYPE='PROCEDURE' ORDER BY ROUTINE_NAME"),
            'functions' => $this->column($pdo, "SELECT ROUTINE_NAME FROM information_schema.ROUTINES WHERE ROUTINE_SCHEMA={$quoted} AND ROUTINE_TYPE='FUNCTION' ORDER BY ROUTINE_NAME"),
        ];
    }

    private function definitions(PDO $pdo, string $schema, array $objects): array
    {
        $definitions = [];
        foreach (['functions', 'procedures', 'views', 'triggers', 'events'] as $type) {
            foreach ($objects[$type] ?? [] as $name) {
                $definitions[] = [
                    'object_type' => rtrim($type, 's'),
                    'name' => $name,
                    'sql' => $this->objectDefinition($pdo, $type, $name, $schema),
                ];
            }
        }
        return $definitions;
    }

    private function snapshotDefinitions(array $records): array
    {
        return array_values(array_filter($records, static fn (array $record): bool =>
            ($record['type'] ?? '') === 'schema' && ($record['object_type'] ?? '') !== 'table'
        ));
    }

    private function validateDefinitions(array $definitions, array $objects): void
    {
        $expected = [];
        foreach (['functions', 'procedures', 'views', 'triggers', 'events'] as $type) {
            foreach ($objects[$type] ?? [] as $name) {
                $expected[] = rtrim($type, 's') . ':' . $name;
            }
        }
        $actual = [];
        foreach ($definitions as $definition) {
            $type = (string) ($definition['object_type'] ?? '');
            $name = (string) ($definition['name'] ?? '');
            $sql = (string) ($definition['sql'] ?? '');
            if ($name === '' || $sql === '' || stripos($sql, 'CREATE') !== 0) {
                throw new RuntimeException('production definitions 验证失败：' . $type . ':' . $name);
            }
            $actual[] = $type . ':' . $name;
        }
        sort($expected);
        sort($actual);
        if ($actual !== $expected) {
            throw new RuntimeException('production definitions 清单不完整');
        }
    }

    private function createDefinitions(PDO $pdo, array $definitions, string $source, string $target, array &$created): void
    {
        $this->useSchema($pdo, $target);
        $definitions = array_map(fn (array $definition): array => $definition + [
            'name' => $this->definitionName((string) ($definition['object_type'] ?? ''), (string) ($definition['sql'] ?? '')),
        ], $definitions);
        $groups = ['function' => [], 'procedure' => [], 'view' => [], 'trigger' => [], 'event' => []];
        foreach ($definitions as $definition) {
            $groups[(string) $definition['object_type']][] = $definition;
        }
        foreach (['function', 'procedure'] as $type) {
            $this->createDefinitionGroup($pdo, $groups[$type], $source, $target, $created);
        }
        $this->createViews($pdo, $groups['view'], $source, $target, $created);
        $this->createDefinitionGroup($pdo, $groups['trigger'], $source, $target, $created);
        $this->createDefinitionGroup($pdo, $groups['event'], $source, $target, $created);
    }

    private function createDefinitionGroup(PDO $pdo, array $definitions, string $source, string $target, array &$created): void
    {
        foreach ($definitions as $definition) {
            $pdo->exec($this->normalizeDefinition((string) $definition['sql'], $source, $target));
            $created[] = $definition;
        }
    }

    private function createViews(PDO $pdo, array $views, string $source, string $target, array &$created): void
    {
        $pending = $views;
        $lastException = null;
        while ($pending !== []) {
            $remaining = [];
            $progress = false;
            foreach ($pending as $view) {
                try {
                    $pdo->exec($this->normalizeDefinition((string) $view['sql'], $source, $target));
                    $created[] = $view;
                    $progress = true;
                } catch (Throwable $exception) {
                    $lastException = $exception;
                    $remaining[] = $view;
                }
            }
            if (!$progress) {
                throw new RuntimeException('view 依赖重建失败：' . ($lastException?->getMessage() ?? '未知错误'), 0, $lastException);
            }
            $pending = $remaining;
        }
    }

    private function selectDefinitions(array $definitions, array $selected): array
    {
        $keys = array_fill_keys(array_map(static fn (array $item): string => $item['object_type'] . ':' . $item['name'], $selected), true);
        return array_values(array_filter($definitions, static fn (array $definition): bool => isset($keys[$definition['object_type'] . ':' . $definition['name']])));
    }

    private function definitionName(string $type, string $sql): string
    {
        if (!in_array($type, ['function', 'procedure', 'view', 'trigger', 'event'], true)
            || preg_match('/\b' . strtoupper($type) . '\s+(?:IF\s+NOT\s+EXISTS\s+)?(?:`[^`]+`\.)?`([^`]+)`/i', $sql, $matches) !== 1) {
            throw new RuntimeException('数据库对象定义缺少有效名称：' . $type);
        }
        return $this->validatedIdentifier($matches[1]);
    }

    private function createRecord(PDO $pdo, array $record, string $source, string $target): void
    {
        $type = (string) ($record['object_type'] ?? '');
        if (!in_array($type, ['table', 'view', 'trigger', 'procedure', 'function', 'event'], true)) {
            throw new RuntimeException('数据库备份对象类型无效');
        }
        $name = $this->validatedIdentifier((string) ($record['name'] ?? ''));
        $sql = $this->normalizeDefinition((string) ($record['sql'] ?? ''), $source, $target);
        if ($sql === '' || stripos($sql, 'CREATE') !== 0) {
            throw new RuntimeException('数据库备份对象定义无效：' . $name);
        }
        $pdo->exec($sql);
    }

    private function dropNonTableObjects(PDO $pdo, string $schema, array $objects, array &$dropped): void
    {
        $definitions = [];
        foreach (['events', 'triggers', 'views', 'procedures', 'functions'] as $type) {
            foreach ($objects[$type] ?? [] as $name) {
                $definitions[] = ['object_type' => rtrim($type, 's'), 'name' => $name];
            }
        }
        $this->dropDefinitionObjects($pdo, $schema, $definitions, $dropped);
    }

    private function dropDefinitionObjects(PDO $pdo, string $schema, array $definitions, array &$dropped): void
    {
        $keywords = ['view' => 'VIEW', 'trigger' => 'TRIGGER', 'event' => 'EVENT', 'procedure' => 'PROCEDURE', 'function' => 'FUNCTION'];
        $groups = array_fill_keys(array_keys($keywords), []);
        foreach ($definitions as $definition) {
            $groups[(string) $definition['object_type']][] = $definition;
        }
        foreach (['event', 'trigger', 'view', 'procedure', 'function'] as $type) {
            foreach ($groups[$type] as $definition) {
                $name = (string) $definition['name'];
                $pdo->exec('DROP ' . $keywords[$type] . ' IF EXISTS ' . $this->qualified($schema, $name));
                $dropped[] = $definition;
            }
        }
    }

    private function objectDefinition(PDO $pdo, string $type, string $name, string $schema): string
    {
        $keyword = match ($type) {
            'views' => 'VIEW', 'triggers' => 'TRIGGER', 'events' => 'EVENT',
            'procedures' => 'PROCEDURE', 'functions' => 'FUNCTION',
            default => throw new RuntimeException('不支持的数据库对象类型'),
        };
        return $this->showCreate($pdo, $keyword, $name, $schema);
    }

    private function showCreate(PDO $pdo, string $keyword, string $name, string $schema): string
    {
        $row = $pdo->query('SHOW CREATE ' . $keyword . ' ' . $this->qualified($schema, $name))->fetch(PDO::FETCH_ASSOC);
        if (!is_array($row)) {
            throw new RuntimeException('无法读取数据库对象定义：' . $name);
        }
        foreach ($row as $key => $value) {
            if ((str_starts_with((string) $key, 'Create ') || (string) $key === 'SQL Original Statement')
                && is_string($value) && $value !== '') {
                return $this->stripDefiner($value);
            }
        }
        throw new RuntimeException('数据库对象定义为空：' . $name);
    }

    private function stripDefiner(string $sql): string
    {
        return preg_replace('/\s+DEFINER\s*=\s*(?:`[^`]+`|[^\s@]+)@(?:`[^`]+`|[^\s]+)\s*/i', ' ', $sql) ?? $sql;
    }

    private function normalizeDefinition(string $sql, string $source, string $target): string
    {
        $sql = $this->stripDefiner($sql);
        return str_replace('`' . str_replace('`', '``', $source) . '`.', $this->quoteIdentifier($target) . '.', $sql);
    }

    private function canonicalDefinition(string $sql, string $schema): string
    {
        $sql = str_replace($this->quoteIdentifier($schema) . '.', '`__schema__`.', $this->stripDefiner($sql));
        return preg_replace('/\sAUTO_INCREMENT=\d+/i', '', $sql) ?? $sql;
    }

    private function dataColumns(PDO $pdo, string $schema, string $table): array
    {
        $statement = $pdo->prepare('SELECT COLUMN_NAME,EXTRA,GENERATION_EXPRESSION FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=? AND TABLE_NAME=? ORDER BY ORDINAL_POSITION');
        $statement->execute([$schema, $table]);
        $columns = [];
        foreach ($statement->fetchAll(PDO::FETCH_ASSOC) as $column) {
            $extra = strtoupper((string) $column['EXTRA']);
            if (str_contains($extra, 'VIRTUAL GENERATED') || str_contains($extra, 'STORED GENERATED') || (string) $column['GENERATION_EXPRESSION'] !== '') {
                continue;
            }
            $columns[] = $this->validatedIdentifier((string) $column['COLUMN_NAME']);
        }
        if ($columns === []) {
            throw new RuntimeException('数据表没有可导出的非生成列：' . $table);
        }
        return $columns;
    }

    private function encodeRow(array $row): array
    {
        $encoded = [];
        foreach ($row as $column => $value) {
            $encoded[$column] = $value === null ? null : base64_encode((string) $value);
        }
        return $encoded;
    }

    private function insertRow(PDO $pdo, string $schema, string $table, array $values): void
    {
        $table = $this->validatedIdentifier($table);
        if ($values === []) {
            return;
        }
        $columns = array_map(fn (string $column): string => $this->quoteIdentifier($this->validatedIdentifier($column)), array_keys($values));
        $decoded = array_map(static function (mixed $value): ?string {
            if ($value === null) {
                return null;
            }
            $decoded = base64_decode((string) $value, true);
            if ($decoded === false) {
                throw new RuntimeException('数据库备份数据编码无效');
            }
            return $decoded;
        }, array_values($values));
        $sql = 'INSERT INTO ' . $this->qualified($schema, $table) . ' (' . implode(',', $columns) . ') VALUES (' . implode(',', array_fill(0, count($columns), '?')) . ')';
        $pdo->prepare($sql)->execute($decoded);
    }

    private function assertSnapshotPrivileges(PDO $pdo, string $database): void
    {
        $grants = $pdo->query('SHOW GRANTS')->fetchAll(PDO::FETCH_COLUMN);
        $normalized = strtoupper(implode("\n", array_map('strval', $grants)));
        $databasePattern = preg_quote(strtoupper($database), '/');
        $required = ['SELECT', 'SHOW VIEW', 'TRIGGER', 'EVENT', 'EXECUTE'];
        $hasAll = str_contains($normalized, 'ALL PRIVILEGES ON *.*')
            || preg_match('/ALL PRIVILEGES ON [`]?' . $databasePattern . '[`]?\.\*/', $normalized) === 1;
        if (!$hasAll) {
            foreach ($required as $privilege) {
                if (!str_contains($normalized, $privilege)) {
                    throw new RuntimeException('数据库账户缺少完整对象快照权限：' . $privilege);
                }
            }
        }
    }

    private function assertHeader(array $header, array $manifest): void
    {
        if (($header['format'] ?? '') !== self::FORMAT || !is_array($header['database'] ?? null)
            || !is_array($header['objects'] ?? null) || !is_array($header['object_hashes'] ?? null)
            || ($manifest['type'] ?? '') !== 'manifest' || !is_array($manifest['table_stats'] ?? null)) {
            throw new RuntimeException('数据库备份格式或 manifest 无效');
        }
        foreach (['name', 'charset', 'collation'] as $key) {
            $this->validatedIdentifier((string) ($header['database'][$key] ?? ''));
        }
        foreach (['tables', 'views', 'triggers', 'events', 'procedures', 'functions'] as $type) {
            if (!is_array($header['objects'][$type] ?? null) || preg_match('/^[a-f0-9]{64}$/', (string) ($header['object_hashes'][$type] ?? '')) !== 1) {
                throw new RuntimeException('数据库对象清单或 hash 无效：' . $type);
            }
            foreach ($header['objects'][$type] as $name) {
                $this->validatedIdentifier((string) $name);
            }
        }
    }

    private function createSchema(PDO $pdo, string $schema, array $database): void
    {
        $sql = 'CREATE DATABASE ' . $this->quoteInternalIdentifier($schema)
            . ' CHARACTER SET ' . $this->validatedIdentifier((string) $database['charset'])
            . ' COLLATE ' . $this->validatedIdentifier((string) $database['collation']);
        $pdo->exec($sql);
    }

    private function dropInternalSchema(PDO $pdo, string $schema): void
    {
        $pdo->exec('DROP DATABASE IF EXISTS ' . $this->quoteInternalIdentifier($schema));
    }

    private function useSchema(PDO $pdo, string $schema): void
    {
        $pdo->exec('USE ' . $this->quoteIdentifier($schema));
    }

    private function schemasLike(PDO $pdo, string $pattern): array
    {
        $statement = $pdo->prepare('SELECT SCHEMA_NAME FROM information_schema.SCHEMATA WHERE SCHEMA_NAME LIKE ?');
        $statement->execute([$pattern]);
        return array_map('strval', $statement->fetchAll(PDO::FETCH_COLUMN));
    }

    private function tableExists(PDO $pdo, string $schema, string $table): bool
    {
        $statement = $pdo->prepare("SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA=? AND TABLE_NAME=? AND TABLE_TYPE='BASE TABLE'");
        $statement->execute([$schema, $table]);
        return (int) $statement->fetchColumn() === 1;
    }

    private function column(PDO $pdo, string $sql): array
    {
        return array_map(fn (mixed $value): string => $this->validatedIdentifier((string) $value), $pdo->query($sql)->fetchAll(PDO::FETCH_COLUMN));
    }

    private function read(string $path): array
    {
        $handle = fopen($path, 'rb');
        if ($handle === false) {
            throw new RuntimeException('无法读取数据库备份');
        }
        $records = [];
        try {
            while (($line = fgets($handle)) !== false) {
                $record = json_decode($line, true, 512, JSON_THROW_ON_ERROR);
                if (!is_array($record)) {
                    throw new RuntimeException('数据库备份记录无效');
                }
                $records[] = $record;
            }
        } finally {
            fclose($handle);
        }
        return $records;
    }

    private function write(mixed $handle, array $record): void
    {
        $line = json_encode($record, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . "\n";
        if (fwrite($handle, $line) !== strlen($line)) {
            throw new RuntimeException('写入数据库备份失败');
        }
    }

    private function databaseName(PDO $pdo): string
    {
        return $this->validatedIdentifier((string) $pdo->query('SELECT DATABASE()')->fetchColumn());
    }

    private function pdo(): PDO
    {
        if ($this->connection instanceof PDO) {
            return $this->connection;
        }
        $config = (array) config('database.connections.mysql');
        if (($config['type'] ?? 'mysql') !== 'mysql') {
            throw new RuntimeException('系统升级数据库备份仅支持 MySQL');
        }
        $dsn = sprintf('mysql:host=%s;port=%s;dbname=%s;charset=%s', $config['hostname'], $config['hostport'], $config['database'], $config['charset'] ?? 'utf8mb4');
        return new PDO($dsn, (string) $config['username'], (string) $config['password'], [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
            PDO::MYSQL_ATTR_USE_BUFFERED_QUERY => true,
        ]);
    }

    private function assertSafeTarget(string $path, bool $mustExist): void
    {
        if ($path === '' || is_link($path) || is_link(dirname($path))) {
            throw new RuntimeException('数据库备份路径无效或包含符号链接');
        }
        if ($mustExist && !is_file($path)) {
            throw new RuntimeException('数据库备份不存在');
        }
    }

    private function qualified(string $schema, string $object): string
    {
        return $this->quoteIdentifier($schema) . '.' . $this->quoteIdentifier($object);
    }

    private function quoteIdentifier(string $identifier): string
    {
        return '`' . $this->validatedIdentifier($identifier) . '`';
    }

    private function quoteInternalIdentifier(string $identifier): string
    {
        if (preg_match('/^funadmin_(?:shadow|backup)_[a-f0-9]{16}$/', $identifier) !== 1) {
            throw new RuntimeException('内部数据库标识符无效');
        }
        return '`' . $identifier . '`';
    }

    private function validatedIdentifier(string $identifier): string
    {
        if (preg_match('/^[A-Za-z0-9_$]+$/', $identifier) !== 1) {
            throw new RuntimeException('数据库标识符无效');
        }
        return $identifier;
    }
}
