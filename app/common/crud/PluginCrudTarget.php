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

    public function files(CrudDefinition $definition, TemplateRenderer $renderer): array
    {
        $target = (array) $definition->get('target', []);
        $plugin = (string) ($target['plugin'] ?? '');
        $scope = (string) ($target['scope'] ?? '');
        $entity = (string) $definition->get('entity', '');
        $class = self::studly($entity);
        $templates = (array) $definition->get('templates', []);
        $files = [];

        if (in_array($scope, ['application', 'both'], true)) {
            $context = PluginTemplateContext::build($definition, $plugin, false);
            $this->renderBackend($files, $renderer, $templates, $context, "plugins/{$plugin}/app/{$plugin}", $class);
        }
        if (in_array($scope, ['console', 'both'], true)) {
            $context = PluginTemplateContext::build($definition, $plugin, true);
            $this->renderBackend($files, $renderer, $templates, $context, "plugins/{$plugin}/app/console", $class);
            $base = "plugins/{$plugin}/admin-web/{$entity}";
            foreach (['api' => 'api.ts', 'view' => 'index.vue', 'form' => "components/{$class}Form.vue", 'detail' => "components/{$class}Detail.vue"] as $type => $path) {
                $files["{$base}/{$path}"] = $this->render($renderer, $templates, $type, $context);
            }
        }

        $context = PluginTemplateContext::build($definition, $plugin, $scope !== 'application');
        $migration = $this->migration($definition, $plugin, $entity, $this->render($renderer, $templates, 'migration', $context));
        $files[$migration['path']] = $migration['content'];
        $manifest = new ManifestMerger($this->projectRoot);
        $files["plugins/{$plugin}/plugin.json"] = $manifest->merge($definition, $scope !== 'application');
        return $files;
    }

    private function renderBackend(array &$files, TemplateRenderer $renderer, array $templates, array $context, string $base, string $class): void
    {
        foreach (['model' => "model/{$class}.php", 'validate' => "validate/{$class}Validate.php", 'service' => "service/{$class}Service.php", 'controller' => "controller/{$class}Controller.php"] as $type => $path) {
            $files["{$base}/{$path}"] = $this->render($renderer, $templates, $type, $context);
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
        $metadata = '-- funadmin-crud-schema: ' . base64_encode(CrudDefinition::canonicalJson($snapshot)) . "\n";
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
            $stored = $this->migrationSnapshot($content);
            if (($stored['table'] ?? null) === $table) {
                $previous = ['path' => $relative . '/' . $name, 'content' => $content, 'snapshot' => $stored];
            } elseif ($previous === null
                && preg_match('/CREATE\s+TABLE(?:\s+IF\s+NOT\s+EXISTS)?\s+`' . preg_quote($table, '/') . '`/i', $content) === 1) {
                $previous = ['path' => $relative . '/' . $name, 'content' => $content, 'snapshot' => null];
            }
        }
        if ($previous === null) {
            return [
                'path' => sprintf('%s/%03d_create_%s.sql', $relative, $this->nextSequence($maximum), str_replace('-', '_', $entity)),
                'content' => rtrim($createSql) . "\n" . $metadata,
            ];
        }
        if ($previous['snapshot'] === null) {
            if (hash_equals(hash('sha256', rtrim($previous['content'])), hash('sha256', rtrim($createSql)))) {
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
            'content' => "-- Generated forward migration; review before applying.\nALTER TABLE `{$table}`\n  "
                . implode(",\n  ", $alter) . ";\n" . $metadata,
        ];
    }

    private function schemaSnapshot(CrudDefinition $definition): array
    {
        $data = $definition->toArray();
        $fields = [];
        foreach ($data['fields'] as $field) {
            $fields[$field['name']] = [
                'definition' => $this->columnDefinition($field),
                'index' => ($field['unique'] ?? false) ? 'unique' : ((($field['search'] ?? false) || ($field['relation'] ?? '') !== '') ? 'index' : ''),
            ];
        }
        if ($data['softDeletes'] && !isset($fields['deleted_at'])) {
            $fields['deleted_at'] = ['definition' => 'datetime NULL', 'index' => ''];
        }
        return ['table' => $data['table'], 'fields' => $fields];
    }

    private function columnDefinition(array $field): string
    {
        $null = $field['nullable'] ? 'NULL' : 'NOT NULL';
        $auto = ($field['primary'] ?? false) && str_contains(strtolower($field['dbType']), 'int') ? ' AUTO_INCREMENT' : '';
        $default = array_key_exists('default', $field) && $field['default'] !== null
            ? ' DEFAULT ' . (is_numeric($field['default']) ? (string) $field['default'] : "'" . str_replace("'", "''", (string) $field['default']) . "'")
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
        $operations = [];
        foreach ($newFields as $name => $field) {
            if (!isset($oldFields[$name])) {
                $operations[] = "ADD COLUMN `{$name}` {$field['definition']}";
                if (($field['index'] ?? '') !== '') {
                    $operations[] = $this->addIndex($table, (string) $name, (string) $field['index']);
                }
            } elseif (($oldFields[$name]['index'] ?? '') === '' && ($field['index'] ?? '') !== '') {
                $operations[] = $this->addIndex($table, (string) $name, (string) $field['index']);
            }
        }
        if ($operations === []) {
            throw new InvalidArgumentException('已有表结构变更不可表达，请手写 forward migration');
        }
        return $operations;
    }

    private function addIndex(string $table, string $field, string $type): string
    {
        $prefix = $type === 'unique' ? 'ADD UNIQUE KEY `uk_' : 'ADD KEY `idx_';
        return $prefix . $table . '_' . $field . '` (`' . $field . '`)';
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
        foreach (glob($directory . DIRECTORY_SEPARATOR . '*.sql') ?: [] as $file) {
            $name = basename($file);
            if (is_link($file) || !is_file($file)) {
                throw new InvalidArgumentException('插件 migration 禁止符号链接或非文件对象：' . $name);
            }
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
