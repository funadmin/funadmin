<?php

declare(strict_types=1);

namespace app\console\service;

use app\common\crud\SchemaInspector;
use app\common\model\SystemMigration;
use app\console\model\Form;
use app\console\model\FormField;
use InvalidArgumentException;
use think\facade\Db;
use Throwable;

/**
 * 表单管理应用服务：定义校验/保存/推断/DDL 预览与应用（M1+M2）。
 * 运行态通用数据 API 属 M3，不在本服务职责内。
 */
final class FormDesignerService
{
    /** 控件注册表 v1：type => 默认列类型 */
    public const CONTROL_TYPES = [
        'input' => 'varchar(100)',
        'textarea' => 'varchar(500)',
        'number' => 'int',
        'switch' => 'tinyint(1)',
        'select' => 'varchar(50)',
        'date' => 'date',
    ];

    private const INDEX_TYPES = ['none', 'unique', 'index'];
    private const RELATION_TYPES = ['none', 'belongs_to', 'has_many'];
    private const ON_DELETE = ['restrict' => 'RESTRICT', 'cascade' => 'CASCADE', 'set_null' => 'SET NULL'];

    public function __construct(private readonly string $projectRoot)
    {
    }

    /** 表单分页列表（含字段数）。 */
    public function listing(int $page, int $pageSize, string $keyword, mixed $status): array
    {
        $query = Form::order('sort_order', 'asc')->order('id', 'asc');
        if ($keyword !== '') {
            $query->where(function ($q) use ($keyword): void {
                $q->whereLike('name', '%' . $keyword . '%')->whereOr('form_key', 'like', '%' . $keyword . '%');
            });
        }
        if ($status !== null && $status !== '') {
            $query->where('status', (int) $status);
        }
        $result = $query->withCount(['fields'])->paginate(['list_rows' => $pageSize, 'page' => $page]);
        return ['list' => $result->items(), 'total' => $result->total()];
    }

    /** 表单详情（含按 sort_order 排序的字段）。 */
    public function detail(int $id): array
    {
        $form = Form::find($id);
        if (!$form) {
            throw new InvalidArgumentException('表单不存在');
        }
        $fields = FormField::where('form_id', $id)->order('sort_order', 'asc')->order('id', 'asc')->select();
        return ['form' => $form, 'fields' => $fields];
    }

    /** 服务端定义校验：不合法抛 InvalidArgumentException。 */
    public function validateDefinition(array $payload, bool $allowEmptyFields = false): array
    {
        $formKey = trim((string) ($payload['form_key'] ?? ''));
        $name = trim((string) ($payload['name'] ?? ''));
        $table = trim((string) ($payload['table_name'] ?? ''));
        $sourceType = (string) ($payload['source_type'] ?? 'created');
        if (!preg_match('/^[a-z][a-z0-9_]{1,60}$/', $formKey)) {
            throw new InvalidArgumentException('表单标识必须为小写字母开头的字母数字下划线');
        }
        if ($name === '' || mb_strlen($name) > 100) {
            throw new InvalidArgumentException('表单名称不能为空且不能超过 100 个字符');
        }
        if (!preg_match('/^[a-z][a-z0-9_]*$/', $table)) {
            throw new InvalidArgumentException('表名必须为小写字母开头的字母数字下划线');
        }
        if (!in_array($sourceType, ['created', 'adopted'], true)) {
            throw new InvalidArgumentException('来源类型只能为 created 或 adopted');
        }
        $fields = $payload['fields'] ?? [];
        if (!is_array($fields)) {
            throw new InvalidArgumentException('fields 必须为数组');
        }
        if ($fields === [] && !$allowEmptyFields) {
            throw new InvalidArgumentException('至少需要一个字段');
        }
        $seen = [];
        foreach ($fields as $index => $field) {
            $label = '第' . ($index + 1) . '个字段';
            $fieldName = trim((string) ($field['field_name'] ?? ''));
            if (!preg_match('/^[a-z][a-z0-9_]{0,60}$/', $fieldName)) {
                throw new InvalidArgumentException($label . '字段名不合法：' . $fieldName);
            }
            if (isset($seen[$fieldName])) {
                throw new InvalidArgumentException('字段名重复：' . $fieldName);
            }
            $seen[$fieldName] = true;
            if (trim((string) ($field['label'] ?? '')) === '') {
                throw new InvalidArgumentException($label . '显示名称不能为空');
            }
            $type = (string) ($field['type'] ?? 'input');
            if (!array_key_exists($type, self::CONTROL_TYPES)) {
                throw new InvalidArgumentException($label . '控件类型不支持：' . $type);
            }
            if (!in_array((string) ($field['index_type'] ?? 'none'), self::INDEX_TYPES, true)) {
                throw new InvalidArgumentException($label . '索引类型不合法');
            }
            $relationType = (string) ($field['relation_type'] ?? 'none');
            if (!in_array($relationType, self::RELATION_TYPES, true)) {
                throw new InvalidArgumentException($label . '关联类型不合法');
            }
            if ($relationType === 'belongs_to') {
                $this->assertIdentifier((string) ($field['relation_table'] ?? ''), $label . '关联表');
                $this->assertIdentifier((string) ($field['relation_label_field'] ?? ''), $label . '关联显示字段');
                $this->assertIdentifier((string) ($field['relation_value_field'] ?? 'id'), $label . '关联值字段');
                if (!array_key_exists((string) ($field['relation_on_delete'] ?? 'restrict'), self::ON_DELETE)) {
                    throw new InvalidArgumentException($label . '外键删除规则不合法');
                }
            }
            if ($relationType === 'has_many') {
                $this->assertIdentifier((string) ($field['relation_table'] ?? ''), $label . '子表');
                $this->assertIdentifier((string) ($field['relation_value_field'] ?? ''), $label . '子表外键字段');
            }
            $this->validateOptionsSource($field['options_source'] ?? null, $label);
            $span = (int) ($field['form_span'] ?? 24);
            if ($span < 1 || $span > 24) {
                throw new InvalidArgumentException($label . '栅格 span 必须在 1-24');
            }
            if ($sourceType === 'created') {
                $columnType = trim((string) ($field['column_type'] ?? ''));
                if ($columnType === '' || !preg_match('/^[a-z]+(?:\(\d+(?:,\d+)?\))?$/', $columnType)) {
                    throw new InvalidArgumentException($label . '列类型不合法：' . $columnType);
                }
                $default = (string) ($field['default_value'] ?? '');
                if ($default !== '' && preg_match('/^(tinyint|int|bigint|decimal)/', $columnType) && !preg_match('/^-?\d+(?:\.\d+)?$/', $default)) {
                    throw new InvalidArgumentException($label . '数字默认值不合法');
                }
            }
        }
        return ['valid' => true];
    }

    /** 保存表单与字段（事务＋乐观锁）。 */
    public function save(array $payload): array
    {
        $this->validateDefinition($payload, true);
        $id = (int) ($payload['id'] ?? 0);
        $expectedUpdatedAt = (string) ($payload['updated_at'] ?? '');
        return Db::transaction(function () use ($payload, $id, $expectedUpdatedAt): array {
            $form = $id > 0 ? Form::find($id) : new Form();
            if ($id > 0 && !$form) {
                throw new InvalidArgumentException('表单不存在');
            }
            if ($id > 0 && $expectedUpdatedAt !== '' && (string) $form->updated_at !== $expectedUpdatedAt) {
                throw new InvalidArgumentException('表单已被他人修改，请刷新后重试');
            }
            if ($id === 0 && Form::where('form_key', $payload['form_key'])->find()) {
                throw new InvalidArgumentException('表单标识已存在');
            }
            $form->save([
                'form_key' => trim((string) $payload['form_key']),
                'name' => trim((string) $payload['name']),
                'table_name' => trim((string) $payload['table_name']),
                'connection' => trim((string) ($payload['connection'] ?? 'mysql')) ?: 'mysql',
                'source_type' => (string) ($payload['source_type'] ?? 'created'),
                'status' => (int) ($payload['status'] ?? 1),
                'list_config' => $payload['list_config'] ?? null,
                'form_config' => $payload['form_config'] ?? null,
                'remark' => trim((string) ($payload['remark'] ?? '')),
                'sort_order' => (int) ($payload['sort_order'] ?? 0),
            ]);
            FormField::where('form_id', (int) $form->id)->delete();
            foreach (array_values($payload['fields']) as $sort => $field) {
                (new FormField())->save($this->fieldRow((int) $form->id, $field, $sort));
            }
            return $this->detail((int) $form->id);
        });
    }

    /** 删除表单（仅 created 且绑定表无数据时允许删表记录；元数据始终可删）。 */
    public function remove(int $id): array
    {
        $form = Form::find($id);
        if (!$form) {
            throw new InvalidArgumentException('表单不存在');
        }
        return Db::transaction(function () use ($form): array {
            FormField::where('form_id', (int) $form->id)->delete();
            $form->delete();
            return ['removed' => 1];
        });
    }

    /** 启用/禁用。 */
    public function setStatus(int $id, int $status): array
    {
        $form = Form::find($id);
        if (!$form) {
            throw new InvalidArgumentException('表单不存在');
        }
        $form->save(['status' => $status === 1 ? 1 : 0]);
        return ['status' => (int) $form->status];
    }

    /** 采纳模式：读取已有表结构并映射为字段行。 */
    public function inferFields(string $connection, string $table): array
    {
        if ($connection !== 'mysql') {
            throw new InvalidArgumentException('当前仅支持 mysql 连接');
        }
        $inspector = new SchemaInspector(static fn (string $sql, array $bindings): array => Db::connect($connection)->query($sql, $bindings));
        $schema = $inspector->inspect($table);
        $rows = [];
        foreach ($schema['columns'] as $index => $column) {
            $name = (string) $column['name'];
            if ($name === 'id' || in_array($name, ['created_at', 'updated_at', 'deleted_at'], true)) {
                continue;
            }
            $dbType = (string) $column['type'];
            $rows[] = [
                'field_name' => $name,
                'label' => trim((string) ($column['comment'] ?? '')) ?: $name,
                'type' => $this->controlFromDbType($dbType),
                'column_type' => $dbType,
                'nullable' => (bool) ($column['nullable'] ?? true) ? 1 : 0,
                'default_value' => (string) ($column['default'] ?? ''),
                'comment' => (string) ($column['comment'] ?? ''),
                'index_type' => $this->indexFromSchema($schema, $name),
                'list_show' => 1,
                'form_show' => 1,
                'sort_order' => $index,
            ];
        }
        return $rows;
    }

    /** DDL 预览：created 表不存在→CREATE；已存在→仅加列/加索引；adopted→禁止。 */
    public function previewMigration(array $payload): array
    {
        $this->validateDefinition($payload);
        $sourceType = (string) ($payload['source_type'] ?? 'created');
        if ($sourceType === 'adopted') {
            return ['mode' => 'none', 'sql' => '', 'file' => '', 'message' => '采纳表禁止 DDL，仅保存元数据'];
        }
        $table = trim((string) $payload['table_name']);
        $exists = in_array($table, Db::connect()->getTables(), true);
        $sql = $exists
            ? $this->additiveSql($table, $payload['fields'])
            : $this->createTableSql($table, $payload['fields']);
        return [
            'mode' => $exists ? 'additive' : 'create',
            'sql' => $sql,
            'file' => $this->migrationPath($payload, $sql),
            'message' => $sql === '' ? '无结构变更' : ($exists ? '仅新增列/索引' : '创建新表'),
        ];
    }

    /** 应用 DDL：写守卫式迁移文件→执行→登记仓库。 */
    public function applyMigration(array $payload): array
    {
        $preview = $this->previewMigration($payload);
        if ($preview['mode'] === 'none') {
            return $preview;
        }
        if ($preview['sql'] === '') {
            return $preview;
        }
        $file = $preview['file'];
        $dir = dirname($file);
        if (!is_dir($dir) && !mkdir($dir, 0755, true) && !is_dir($dir)) {
            throw new InvalidArgumentException('无法创建迁移目录：' . $dir);
        }
        if (!is_file($file) && file_put_contents($file, $preview['sql']) === false) {
            throw new InvalidArgumentException('迁移文件写入失败：' . basename($file));
        }
        $version = pathinfo($file, PATHINFO_FILENAME);
        Db::transaction(function () use ($preview, $file, $version): void {
            Db::execute(rtrim(trim((string) $preview['sql']), ';'));
            $registered = SystemMigration::where('scope', 'generated')->where('version', $version)->find();
            if (!$registered) {
                SystemMigration::create([
                    'scope' => 'generated',
                    'version' => $version,
                    'checksum' => hash_file('sha256', $file) ?: '',
                    'executed_at' => time(),
                ]);
            }
        });
        return $preview + ['applied' => true];
    }

    private function fieldRow(int $formId, array $field, int $sort): array
    {
        $type = (string) ($field['type'] ?? 'input');
        return [
            'form_id' => $formId,
            'field_name' => trim((string) $field['field_name']),
            'label' => trim((string) $field['label']),
            'type' => $type,
            'column_type' => trim((string) ($field['column_type'] ?? '')) ?: self::CONTROL_TYPES[$type],
            'nullable' => (int) ($field['nullable'] ?? 1),
            'default_value' => (string) ($field['default_value'] ?? ''),
            'comment' => trim((string) ($field['comment'] ?? '')),
            'unsigned' => (int) ($field['unsigned'] ?? 0),
            'index_type' => (string) ($field['index_type'] ?? 'none'),
            'placeholder' => trim((string) ($field['placeholder'] ?? '')),
            'options_source' => $field['options_source'] ?? null,
            'control_props' => $field['control_props'] ?? null,
            'validate_rules' => $field['validate_rules'] ?? null,
            'link_rules' => $field['link_rules'] ?? null,
            'relation_type' => (string) ($field['relation_type'] ?? 'none'),
            'relation_table' => trim((string) ($field['relation_table'] ?? '')),
            'relation_label_field' => trim((string) ($field['relation_label_field'] ?? '')),
            'relation_value_field' => trim((string) ($field['relation_value_field'] ?? '')) ?: 'id',
            'relation_multiple' => (int) ($field['relation_multiple'] ?? 0),
            'relation_on_delete' => (string) ($field['relation_on_delete'] ?? 'restrict'),
            'list_show' => (int) ($field['list_show'] ?? 1),
            'list_sort' => (int) ($field['list_sort'] ?? 0),
            'list_filter' => (string) ($field['list_filter'] ?? ''),
            'list_formatter' => (string) ($field['list_formatter'] ?? ''),
            'list_width' => (int) ($field['list_width'] ?? 0),
            'form_show' => (int) ($field['form_show'] ?? 1),
            'form_required' => (int) ($field['form_required'] ?? 0),
            'form_group' => trim((string) ($field['form_group'] ?? '')),
            'form_span' => (int) ($field['form_span'] ?? 24),
            'form_readonly' => (int) ($field['form_readonly'] ?? 0),
            'sort_order' => (int) ($field['sort_order'] ?? $sort),
        ];
    }

    private function migrationPath(array $payload, string $sql): string
    {
        $key = trim((string) $payload['form_key']);
        $version = substr(hash('sha256', $sql), 0, 12);
        return $this->projectRoot . 'database' . DIRECTORY_SEPARATOR . 'generated' . DIRECTORY_SEPARATOR . 'form_' . $key . '_' . $version . '.sql';
    }

    private function createTableSql(string $table, array $fields): string
    {
        $lines = ['  `id` bigint unsigned NOT NULL AUTO_INCREMENT'];
        $indexes = [];
        foreach ($fields as $field) {
            $lines[] = '  ' . $this->columnDdl($field);
            $indexType = (string) ($field['index_type'] ?? 'none');
            $name = trim((string) $field['field_name']);
            if ($indexType === 'unique') {
                $indexes[] = '  UNIQUE KEY `uk_' . $table . '_' . $name . '` (`' . $name . '`)';
            } elseif ($indexType === 'index') {
                $indexes[] = '  KEY `idx_' . $table . '_' . $name . '` (`' . $name . '`)';
            }
            if ((string) ($field['relation_type'] ?? 'none') === 'belongs_to') {
                $refTable = trim((string) $field['relation_table']);
                $refValue = trim((string) ($field['relation_value_field'] ?? '')) ?: 'id';
                $onDelete = self::ON_DELETE[(string) ($field['relation_on_delete'] ?? 'restrict')] ?? 'RESTRICT';
                $indexes[] = '  KEY `idx_' . $table . '_' . $name . '_fk` (`' . $name . '`)';
                $indexes[] = '  CONSTRAINT `fk_' . $table . '_' . $name . '` FOREIGN KEY (`' . $name . '`) REFERENCES `' . $refTable . '` (`' . $refValue . '`) ON DELETE ' . $onDelete;
            }
        }
        $lines[] = '  `created_at` datetime NULL';
        $lines[] = '  `updated_at` datetime NULL';
        $lines[] = '  `deleted_at` datetime NULL';
        $lines[] = '  PRIMARY KEY (`id`)';
        $sql = 'CREATE TABLE IF NOT EXISTS `' . $table . "` (\n" . implode(",\n", array_merge($lines, $indexes)) . "\n) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='表单数据表';\n";
        return $sql;
    }

    private function additiveSql(string $table, array $fields): string
    {
        $existing = array_column(
            Db::connect()->query('SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?', [$table]),
            'COLUMN_NAME'
        );
        $existingIndexes = array_column(
            Db::connect()->query('SELECT DISTINCT INDEX_NAME FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?', [$table]),
            'INDEX_NAME'
        );
        $parts = [];
        foreach ($fields as $field) {
            $name = trim((string) $field['field_name']);
            if (!in_array($name, $existing, true)) {
                $parts[] = 'ADD COLUMN ' . $this->columnDdl($field);
            }
            $indexType = (string) ($field['index_type'] ?? 'none');
            if ($indexType === 'unique' && !in_array('uk_' . $table . '_' . $name, $existingIndexes, true)) {
                $parts[] = 'ADD UNIQUE KEY `uk_' . $table . '_' . $name . '` (`' . $name . '`)';
            } elseif ($indexType === 'index' && !in_array('idx_' . $table . '_' . $name, $existingIndexes, true)) {
                $parts[] = 'ADD KEY `idx_' . $table . '_' . $name . '` (`' . $name . '`)';
            }
        }
        return $parts === [] ? '' : 'ALTER TABLE `' . $table . "`\n" . implode(",\n  ", $parts) . ";\n";
    }

    private function columnDdl(array $field): string
    {
        $name = trim((string) $field['field_name']);
        $type = trim((string) ($field['column_type'] ?? '')) ?: self::CONTROL_TYPES[(string) ($field['type'] ?? 'input')];
        if ((int) ($field['unsigned'] ?? 0) === 1 && preg_match('/^(tinyint|int|bigint|decimal)/', $type)) {
            $type .= ' unsigned';
        }
        $comment = trim((string) ($field['comment'] ?? ''));
        $nullable = (int) ($field['nullable'] ?? 1) === 1;
        $default = (string) ($field['default_value'] ?? '');
        $numeric = (bool) preg_match('/^(tinyint|int|bigint|decimal)/', $type);
        if ($nullable) {
            $nullDdl = $default === '' ? 'NULL DEFAULT NULL' : 'NULL DEFAULT ' . ($numeric ? $default : "'" . str_replace("'", "''", $default) . "'");
        } else {
            $nullDdl = 'NOT NULL DEFAULT ' . ($default === '' ? ($numeric ? '0' : "''") : ($numeric ? $default : "'" . str_replace("'", "''", $default) . "'"));
        }
        $ddl = '`' . $name . '` ' . $type . ' ' . $nullDdl;
        if ($comment !== '') {
            $ddl .= " COMMENT '" . str_replace("'", "''", $comment) . "'";
        }
        return $ddl;
    }

    private function assertIdentifier(string $identifier, string $label): void
    {
        if (!preg_match('/^[a-z_][a-z0-9_]*$/', trim($identifier))) {
            throw new InvalidArgumentException($label . '不合法');
        }
    }

    private function validateOptionsSource(mixed $source, string $label): void
    {
        if ($source === null || $source === []) {
            return;
        }
        if (!is_array($source)) {
            throw new InvalidArgumentException($label . '选项来源必须为对象');
        }
        if (($source['mode'] ?? '') !== 'relation') {
            return;
        }
        $this->assertIdentifier((string) ($source['table'] ?? ''), $label . '选项关联表');
        $this->assertIdentifier((string) ($source['label_field'] ?? ''), $label . '选项显示字段');
        $this->assertIdentifier((string) ($source['value_field'] ?? ''), $label . '选项值字段');
    }

    private function controlFromDbType(string $dbType): string
    {
        return match (true) {
            (bool) preg_match('/^tinyint\(1\)/', $dbType) => 'switch',
            (bool) preg_match('/^(tinyint|int|bigint|decimal|float|double)/', $dbType) => 'number',
            (bool) preg_match('/^(text|longtext|mediumtext)/', $dbType) => 'textarea',
            (bool) preg_match('/^date$/', $dbType) => 'date',
            (bool) preg_match('/^(datetime|timestamp)/', $dbType) => 'date',
            default => 'input',
        };
    }

    private function indexFromSchema(array $schema, string $column): string
    {
        foreach ($schema['indexes'] ?? [] as $index) {
            $columns = (array) ($index['columns'] ?? []);
            if ($columns === [$column]) {
                return (bool) ($index['unique'] ?? false) ? 'unique' : 'index';
            }
        }
        return 'none';
    }
}
