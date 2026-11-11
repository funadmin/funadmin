<?php

declare(strict_types=1);

namespace app\common\crud;

use InvalidArgumentException;

/** 从可信插件目标派生全部制品路径，禁止 Definition 注入路径。 */
final class PluginCrudTarget
{
    public function __construct(private readonly string $projectRoot)
    {
    }

    public function files(CrudDefinition $definition, TemplateRenderer $renderer, array $manifestBase = [], bool $managed = false): array
    {
        $target = (array) $definition->get('target', []);
        $plugin = (string) ($target['plugin'] ?? '');
        $scope = (string) ($target['scope'] ?? '');
        $entity = (string) $definition->get('entity', '');
        $class = self::studly($entity);
        $templates = (array) $definition->get('templates', []);
        $files = [];

        // 前台会员接口渲染在插件 application 层；未启用时 application/both 仍为只读控制器。
        $memberApi = (((array) $definition->get('memberApi', []))['enabled'] ?? false) === true;
        if (in_array($scope, ['application', 'both'], true) || $memberApi) {
            $context = PluginTemplateContext::build($definition, $plugin, false);
            $this->renderBackend($files, $renderer, $templates, $context, "plugins/{$plugin}/app/{$plugin}", $class, $memberApi ? 'memberApiController' : 'controller');
        }
        if (in_array($scope, ['admin', 'both'], true)) {
            $context = PluginTemplateContext::build($definition, $plugin, true);
            $this->renderBackend($files, $renderer, $templates, $context, "plugins/{$plugin}/app/admin/plugins/{$plugin}", $class);
            $this->renderRelationModels($files, $definition, $renderer, $templates, $context, $plugin);
            $base = "plugins/{$plugin}/web/src/admin/plugins/{$plugin}/{$entity}";
            foreach (['api' => 'api.ts', 'view' => 'index.vue', 'form' => "components/{$class}Form.vue", 'detail' => "components/{$class}Detail.vue"] as $type => $path) {
                $files["{$base}/{$path}"] = $this->render($renderer, $templates, $type, $context);
            }
        }

        $context = PluginTemplateContext::build($definition, $plugin, $scope !== 'application');
        if (!$definition->isAdopted()) {
            $migration = $this->migration($definition, $plugin, $entity, $this->render($renderer, $templates, 'migration', $context));
            $files[$migration['path']] = $migration['content'];
        }
        $manifest = new ManifestMerger($this->projectRoot);
        $files["plugins/{$plugin}/plugin.json"] = $managed
                    ? $manifest->plan($definition, $manifestBase)['content']
                    : $manifest->merge($definition, $scope !== 'application', $manifestBase);
        return $files;
    }

    private function renderBackend(array &$files, TemplateRenderer $renderer, array $templates, array $context, string $base, string $class, string $controllerTemplate = 'controller'): void
    {
        foreach (['model' => "model/{$class}.php", 'validate' => "validate/{$class}Validate.php", 'service' => "service/{$class}Service.php", $controllerTemplate => "controller/{$class}Controller.php"] as $type => $path) {
            $files["{$base}/{$path}"] = $this->render($renderer, $templates, $type, $context);
        }
    }

    /** 关联模型只允许位于插件 admin 模型命名空间，路径由 modelClass 派生。 */
    private function renderRelationModels(array &$files, CrudDefinition $definition, TemplateRenderer $renderer, array $templates, array $context, string $plugin): void
    {
        $namespace = "app\\admin\\plugins\\{$plugin}\\model\\relation\\";
        foreach ((array) $definition->get('relations', []) as $relation) {
            if (!isset($relation['targetTable'], $relation['modelClass'])) continue;
            $name = substr((string) $relation['modelClass'], strlen($namespace));
            if (!str_starts_with((string) $relation['modelClass'], $namespace) || preg_match('/^[A-Z][A-Za-z0-9]*$/D', $name) !== 1) {
                throw new InvalidArgumentException('插件关联模型必须位于插件模型命名空间：' . $relation['modelClass']);
            }
            $files["plugins/{$plugin}/app/admin/plugins/{$plugin}/model/relation/{$name}.php"] = CrudGenerator::renderRelationModel($renderer, $templates, $context, $name);
        }
    }

    private function render(TemplateRenderer $renderer, array $templates, string $type, array $context): string
    {
        if (!isset($templates[$type]) || !is_string($templates[$type])) {
            throw new InvalidArgumentException('插件目标缺少模板：' . $type);
        }
        return $renderer->render($templates[$type], $context);
    }

    public function migrationPrecondition(CrudDefinition $definition): array
    {
        $target = (array) $definition->get('target', []);
        $plugin = (string) ($target['plugin'] ?? '');
        $relative = "plugins/{$plugin}/database/migrations";
        return [
            'type' => 'plugin-migration-sequence',
            'path' => $relative,
            'hash' => $this->migrationStateHash($relative),
        ];
    }

    private function migration(CrudDefinition $definition, string $plugin, string $entity, string $createSql): array
    {
        $table = (string) $definition->get('table', '');
        $relative = "plugins/{$plugin}/database/migrations";
        $directory = PathGuard::resolve($this->projectRoot, $relative, '插件目录');
        $snapshot = $this->schemaSnapshot($definition);
        $maximum = 0;
        $previous = null;
        foreach (glob($directory . DIRECTORY_SEPARATOR . '*.sql') ?: [] as $file) {
            $name = basename($file);
            if (is_link($file) || !is_file($file)) {
                throw new InvalidArgumentException('插件 migration 禁止符号链接或非文件对象：' . $name);
            }
            if (preg_match('/^(\d{3})_/', $name, $match) === 1) {
                $maximum = max($maximum, (int) $match[1]);
            }
            $content = file_get_contents($file);
            if (!is_string($content)) {
                continue;
            }
            $templatePrefix = (string) config('funadmin.mysqlPrefix', 'fun_');
            $prefix = (string) (\app\common\service\database\DatabaseConnections::settings((string) $definition->get('connection', 'mysql'))['prefix'] ?? '');
            $effectiveContent = \app\common\service\database\MigrationService::rewritePrefix($content, $templatePrefix, $prefix);
            $stored = $this->migrationSnapshot($content);
            if ($stored !== null && !str_starts_with($content, "-- funadmin-physical-table\n")) {
                // 仅解析比较视图，不修改历史文件及其 checksum。
                $stored = json_decode(\app\common\service\database\MigrationService::rewritePrefix(CrudDefinition::canonicalJson($stored), $templatePrefix, $prefix), true, 512, JSON_THROW_ON_ERROR);
            }
            if (($stored['table'] ?? null) === $table) {
                $previous = ['path' => $relative . '/' . $name, 'content' => $content, 'snapshot' => $stored];
            } elseif ($previous === null
                && preg_match('/CREATE\s+TABLE(?:\s+IF\s+NOT\s+EXISTS)?\s+`' . preg_quote($table, '/') . '`/i', $effectiveContent) === 1) {
                $previous = ['path' => $relative . '/' . $name, 'content' => $content, 'effectiveContent' => $effectiveContent, 'snapshot' => null];
            }
        }
        if ($previous !== null && $previous['snapshot'] !== null && self::legacyUniqueSnapshot($previous['snapshot'])) {
            // 旧表的唯一键不以 deleted_token 结尾：沿用旧形状（不加 deleted_token/row_no、不重建唯一键），
            // 重建需要 DROP INDEX，只能由升级命令执行；trait 在没有令牌列时只写 deleted_at，行为安全。
            unset($snapshot['fields'][GeneratedSoftDeleteColumns::TOKEN], $snapshot['fields'][GeneratedSoftDeleteColumns::ROW_NO]);
        }
        $metadata = '-- funadmin-crud-schema: ' . base64_encode(CrudDefinition::canonicalJson($snapshot)) . "\n";
        if ($previous === null) {
            return [
                'path' => sprintf('%s/%03d_create_%s.sql', $relative, $this->nextSequence($maximum), str_replace('-', '_', $entity)),
                'content' => rtrim($createSql) . "\n" . $metadata,
            ];
        }
        if ($previous['snapshot'] === null) {
            $comparableSql = static fn (string $sql): string => rtrim(str_starts_with($sql, "-- funadmin-physical-table\n") ? substr($sql, strlen("-- funadmin-physical-table\n")) : $sql);
            $existing = hash('sha256', $comparableSql($previous['effectiveContent']));
            // 软删除规则之前生成的无 metadata 建表文件：与旧形状渲染一致时同样视为无变更。
            if (hash_equals($existing, hash('sha256', $comparableSql($createSql)))
                || hash_equals($existing, hash('sha256', $comparableSql(self::legacyCreateSql($createSql, $table))))) {
                return ['path' => $previous['path'], 'content' => $previous['content']];
            }
            throw new InvalidArgumentException('已有表结构变更不可表达：旧 migration 缺少 schema metadata，请手写 forward migration');
        }
        if (CrudDefinition::canonicalJson($previous['snapshot']) === CrudDefinition::canonicalJson($snapshot)) {
            return ['path' => $previous['path'], 'content' => $previous['content']];
        }
        $alter = $this->forwardAlter($previous['snapshot'], $snapshot);
        return [
            'path' => sprintf('%s/%03d_alter_%s.sql', $relative, $this->nextSequence($maximum), str_replace('-', '_', $entity)),
            'content' => "-- funadmin-physical-table\n-- Generated forward migration; review before applying.\nALTER TABLE `{$table}`\n  "
                . implode(",\n  ", $alter) . ";\n" . $metadata,
        ];
    }

    private function schemaSnapshot(CrudDefinition $definition): array
    {
        $data = $definition->toArray();
        $fields = [];
        foreach ($data['fields'] as $field) {
            // 软删除内部列按生成器自己的定义记录，与建表 SQL 一致。
            if ($field['name'] === GeneratedSoftDeleteColumns::TOKEN) {
                $fields[$field['name']] = ['definition' => GeneratedSoftDeleteColumns::TOKEN_DEFINITION, 'index' => ''];
                continue;
            }
            if ($field['name'] === GeneratedSoftDeleteColumns::ROW_NO) {
                $fields[$field['name']] = ['definition' => GeneratedSoftDeleteColumns::ROW_NO_DEFINITION, 'index' => 'index'];
                continue;
            }
            $fields[$field['name']] = [
                'definition' => $this->columnDefinition($field),
                'index' => ($field['unique'] ?? false) ? 'unique' : ((($field['search'] ?? false) || ($field['relation'] ?? '') !== '') ? 'index' : ''),
            ];
        }
        if ($data['timestamps']) {
            foreach (['created_at', 'updated_at'] as $timestamp) {
                $fields[$timestamp] ??= ['definition' => 'datetime NULL', 'index' => ''];
            }
        }
        $soft = GeneratedSoftDeleteColumns::plan($data);
        if ($data['softDeletes'] && !isset($fields['deleted_at'])) {
            $fields['deleted_at'] = ['definition' => 'datetime NULL', 'index' => 'index'];
        }
        if ($soft['token']) {
            $fields[GeneratedSoftDeleteColumns::TOKEN] ??= ['definition' => GeneratedSoftDeleteColumns::TOKEN_DEFINITION, 'index' => ''];
        }
        if ($soft['rowNo']) {
            $fields[GeneratedSoftDeleteColumns::ROW_NO] ??= ['definition' => GeneratedSoftDeleteColumns::ROW_NO_DEFINITION, 'index' => 'index'];
        }
        return ['table' => $data['table'], 'fields' => $fields];
    }

    /**
     * 结构快照约定的二级索引：unique → uk_<表>_<列>，index → idx_<表>_<列>；
     * 快照含 deleted_token 时唯一键覆盖 [列, deleted_token]，旧快照（无令牌）仍为 [列]。
     *
     * @return array<string, array{name: string, unique: bool, columns: list<string>}>
     */
    public static function snapshotIndexes(string $table, array $snapshot): array
    {
        $fields = (array) ($snapshot['fields'] ?? []);
        $token = isset($fields[GeneratedSoftDeleteColumns::TOKEN]);
        $indexes = [];
        foreach ($fields as $name => $field) {
            $kind = (string) ($field['index'] ?? '');
            if ($kind === '') continue;
            if (!in_array($kind, ['unique', 'index'], true)) {
                throw new InvalidArgumentException('无效索引快照');
            }
            $indexName = ($kind === 'unique' ? 'uk_' : 'idx_') . $table . '_' . $name;
            $indexes[$indexName] = [
                'name' => $indexName,
                'unique' => $kind === 'unique',
                'columns' => $kind === 'unique' ? GeneratedSoftDeleteColumns::uniqueColumns((string) $name, $token) : [(string) $name],
            ];
        }
        return $indexes;
    }

    private function columnDefinition(array $field): string
    {
        $null = $field['nullable'] ? 'NULL' : 'NOT NULL';
        $auto = ($field['primary'] ?? false) && str_contains(strtolower($field['dbType']), 'int') ? ' AUTO_INCREMENT' : '';
        $default = array_key_exists('default', $field) && $field['default'] !== null
            ? ' DEFAULT ' . (is_numeric($field['default']) ? (string) $field['default'] : SqlLiteral::quote((string) $field['default']))
            : '';
        return $field['dbType'] . ' ' . $null . $default . $auto;
    }

    private function migrationSnapshot(string $content): ?array
    {
        if (preg_match('/^-- funadmin-crud-schema: ([A-Za-z0-9+\/=]+)$/m', $content, $match) !== 1) {
            return null;
        }
        $decoded = base64_decode($match[1], true);
        $snapshot = is_string($decoded) ? json_decode($decoded, true) : null;
        return is_array($snapshot) ? $snapshot : null;
    }

    private function forwardAlter(array $old, array $new): array
    {
        $oldFields = (array) ($old['fields'] ?? []);
        $newFields = (array) ($new['fields'] ?? []);
        foreach ($oldFields as $name => $field) {
            if (!isset($newFields[$name]) || ($newFields[$name]['definition'] ?? null) !== ($field['definition'] ?? null)) {
                throw new InvalidArgumentException('删除或修改字段不可自动演进，请手写 forward migration：' . $name);
            }
            $oldIndex = (string) ($field['index'] ?? '');
            $newIndex = (string) ($newFields[$name]['index'] ?? '');
            if ($oldIndex !== '' && $oldIndex !== $newIndex) {
                throw new InvalidArgumentException('删除或修改索引不可自动演进，请手写 forward migration：' . $name);
            }
        }
        $table = (string) $new['table'];
        $token = isset($newFields[GeneratedSoftDeleteColumns::TOKEN]);
        $operations = [];
        foreach ($newFields as $name => $field) {
            if (!isset($oldFields[$name])) {
                $operations[] = "ADD COLUMN `{$name}` {$field['definition']}";
                if (($field['index'] ?? '') !== '') {
                    $operations[] = $this->addIndex($table, (string) $name, (string) $field['index'], $token);
                }
            } elseif (($oldFields[$name]['index'] ?? '') === '' && ($field['index'] ?? '') !== '') {
                $operations[] = $this->addIndex($table, (string) $name, (string) $field['index'], $token);
            }
        }
        if ($operations === []) {
            throw new InvalidArgumentException('已有表结构变更不可表达，请手写 forward migration');
        }
        return $operations;
    }

    /** 旧快照有唯一字段却没有 deleted_token：表上的唯一键仍是单列。 */
    private static function legacyUniqueSnapshot(array $snapshot): bool
    {
        $fields = (array) ($snapshot['fields'] ?? []);
        if (isset($fields[GeneratedSoftDeleteColumns::TOKEN])) return false;
        foreach ($fields as $field) {
            if (($field['index'] ?? '') === 'unique') return true;
        }
        return false;
    }

    /** 软删除规则之前的建表渲染：无 deleted_at 索引、deleted_token、row_no，唯一键为单列。 */
    private static function legacyCreateSql(string $sql, string $table): string
    {
        $quoted = preg_quote($table, '/');
        return (string) preg_replace([
            '/,\n  KEY `idx_' . $quoted . '_(?:deleted_at|row_no)` \(`(?:deleted_at|row_no)`\)/',
            '/,\n  `(?:deleted_token|row_no)` bigint unsigned NOT NULL (?:DEFAULT 0|AUTO_INCREMENT)/',
            '/(UNIQUE KEY `uk_' . $quoted . '_[a-z0-9_]+` \(`[a-z0-9_]+`), `deleted_token`\)/',
        ], ['', '', '$1)'], $sql);
    }

    private function addIndex(string $table, string $field, string $type, bool $token = false): string
    {
        if ($type === 'unique') {
            return 'ADD UNIQUE KEY `uk_' . $table . '_' . $field . '` (' . GeneratedSoftDeleteColumns::columnList(GeneratedSoftDeleteColumns::uniqueColumns($field, $token)) . ')';
        }
        return 'ADD KEY `idx_' . $table . '_' . $field . '` (`' . $field . '`)';
    }

    private function nextSequence(int $maximum): int
    {
        if ($maximum >= 999) {
            throw new InvalidArgumentException('插件 migration 三位编号已耗尽');
        }
        return $maximum + 1;
    }

    private function migrationStateHash(string $relative): string
    {
        $directory = PathGuard::resolve($this->projectRoot, $relative, '插件目录');
        $state = [];
        $versions = [];
        foreach (glob($directory . DIRECTORY_SEPARATOR . '*.sql') ?: [] as $file) {
            $name = basename($file);
            if (is_link($file) || !is_file($file)) {
                throw new InvalidArgumentException('插件 migration 禁止符号链接或非文件对象：' . $name);
            }
            if (preg_match('/^(\d{3})_/', $name, $match) !== 1 || isset($versions[$match[1]])) {
                throw new InvalidArgumentException('插件 migration 版本重复或无效：' . $name);
            }
            $versions[$match[1]] = true;
            $state[$name] = hash_file('sha256', $file);
        }
        ksort($state, SORT_STRING);
        return hash('sha256', CrudDefinition::canonicalJson($state));
    }

    private static function studly(string $value): string
    {
        return str_replace(' ', '', ucwords(str_replace(['-', '_'], ' ', $value)));
    }
}
