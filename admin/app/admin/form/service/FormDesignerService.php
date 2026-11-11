<?php

declare(strict_types=1);

namespace app\admin\form\service;

use app\common\crud\GeneratedSoftDeleteColumns;
use app\common\crud\SchemaInspector;
use app\common\crud\SqlLiteral;
use app\common\form\registry\FieldCapabilityRegistry;
use app\common\form\schema\FormFieldView;
use app\common\form\schema\FormSchema;
use app\common\form\schema\FormSchemaException;
use app\admin\form\exception\FormMigrationException;
use app\admin\form\model\Form;
use app\admin\form\repository\FormSchemaRepository;
use InvalidArgumentException;
use support\Db;
use Throwable;

/**
 * 表单管理应用服务：定义校验/保存/推断/DDL 预览与应用（M1+M2）。
 * 运行态通用数据 API 属 M3，不在本服务职责内。
 */
final class FormDesignerService
{
    private const INTEGER_TYPES = ['tinyint', 'smallint', 'mediumint', 'int', 'integer', 'bigint'];

    /** 表单字段/业务/布局控件注册表：type => 默认列类型，布局类型为空。 */
    public const CONTROL_TYPES = [
        'input' => 'varchar(255)', 'password' => 'varchar(255)', 'textarea' => 'text',
        'mention' => 'varchar(500)', 'number' => 'int', 'select' => 'varchar(100)',
        'selectV2' => 'varchar(100)', 'treeSelect' => 'bigint', 'cascader' => 'varchar(255)',
        'radio' => 'varchar(100)', 'checkbox' => 'json', 'switch' => 'tinyint(1)',
        'transfer' => 'json', 'date' => 'date', 'datetime' => 'datetime',
        'daterange' => 'json', 'datetimerange' => 'json', 'time' => 'time',
        'timeSelect' => 'time', 'slider' => 'int', 'rate' => 'tinyint',
        'color' => 'varchar(20)', 'image' => 'varchar(500)', 'images' => 'json',
        'file' => 'json', 'files' => 'json', 'dictionary' => 'varchar(100)',
        'relation' => 'bigint', 'department' => 'bigint', 'user' => 'bigint',
        'richtext' => 'longtext', 'json' => 'json', 'hidden' => 'varchar(255)',
        'readonly' => 'varchar(255)', 'repeatable' => '', 'subform' => '', 'group' => '', 'grid' => '', 'divider' => '',
        'text' => '', 'collapse' => '', 'tabs' => '',
    ];

    private const INDEX_TYPES = ['none', 'unique', 'index'];
    private const RELATION_TYPES = ['none', 'belongs_to', 'has_many'];
    private const ON_DELETE = ['restrict' => 'RESTRICT', 'cascade' => 'CASCADE', 'set_null' => 'SET NULL'];

    private readonly FormSchemaRepository $schemas;
    private readonly FieldCapabilityRegistry $fieldCapabilities;

    public function __construct(
        private readonly string $projectRoot,
        ?FormSchemaRepository $schemas = null,
        ?FieldCapabilityRegistry $fieldCapabilities = null
    ) {
        $this->schemas = $schemas ?? new FormSchemaRepository();
        $this->fieldCapabilities = $fieldCapabilities ?? $this->schemas->fieldCapabilities();
    }

    /** 表单详情；字段只存在于 schema_document。 */
    public function detail(int $id): array
    {
        $form = Form::find($id);
        if (!$form) {
            throw new InvalidArgumentException('表单不存在');
        }
        return ['form' => $form];
    }

    /** 服务端字段校验：按编译后的字段视图逐个校验，不合法抛 InvalidArgumentException。 */
    public function validateFields(FormSchema $schema, string $sourceType): void
    {
        $seen = [];
        foreach ($schema->fields() as $index => $field) {
            $label = '第' . ($index + 1) . '个字段';
            $fieldName = $field->name();
            if (!preg_match('/^[a-z][a-z0-9_]{0,60}$/', $fieldName)) {
                throw new InvalidArgumentException($label . '字段名不合法：' . $fieldName);
            }
            if (in_array($fieldName, ['deleted_token', 'row_no'], true)) {
                throw new InvalidArgumentException($label . '字段名 ' . $fieldName . ' 是软删除保留字段，不能使用');
            }
            if (isset($seen[$fieldName])) {
                throw new InvalidArgumentException('字段名重复：' . $fieldName);
            }
            $seen[$fieldName] = true;
            if (trim($field->title()) === '') {
                throw new InvalidArgumentException($label . '显示名称不能为空');
            }
            $type = $field->type();
            if (!$this->fieldCapabilities->has($type)) {
                throw new InvalidArgumentException($label . '控件类型不支持：' . $type);
            }
            if (!in_array($field->index(), self::INDEX_TYPES, true)) {
                throw new InvalidArgumentException($label . '索引类型不合法');
            }
            $relationType = $field->relationType();
            if ($field->isRelationContainer() && $relationType !== 'has_many') {
                throw new InvalidArgumentException($label . '重复行或子表单必须配置 has_many');
            }
            if (!in_array($relationType, self::RELATION_TYPES, true)) {
                throw new InvalidArgumentException($label . '关联类型不合法');
            }
            if ($relationType === 'belongs_to') {
                $this->assertIdentifier($field->relationTable(), $label . '关联表');
                $this->assertIdentifier($field->relationLabelField(), $label . '关联显示字段');
                $this->assertIdentifier($field->relationValueField() ?: 'id', $label . '关联值字段');
                if (!array_key_exists($field->relationOnDelete(), self::ON_DELETE)) {
                    throw new InvalidArgumentException($label . '外键删除规则不合法');
                }
            }
            if ($relationType === 'has_many') {
                $this->assertIdentifier($field->relationTable(), $label . '子表');
                $this->assertIdentifier($field->relationValueField(), $label . '子表外键字段');
            }
            $this->validateOptionsSource($field->dataSource(), $label);
            $span = $field->span();
            if ($span < 1 || $span > 24) {
                throw new InvalidArgumentException($label . '栅格 span 必须在 1-24');
            }
            if ($sourceType === 'created' && !$field->isVirtual()) {
                $columnType = trim($field->columnType());
                if ($columnType === '' || !preg_match('/^[a-z]+(?:\(\d+(?:,\d+)?\))?$/D', $columnType)) {
                    throw new InvalidArgumentException($label . '列类型不合法：' . $columnType);
                }
                $default = $this->defaultText($field);
                if ($default !== '' && preg_match('/^(tinyint|int|bigint|decimal)/', $columnType) && !preg_match('/^-?\d+(?:\.\d+)?$/D', $default)) {
                    throw new InvalidArgumentException($label . '数字默认值不合法');
                }
            }
        }
    }

    /** 保存表单（事务＋乐观锁）；身份与字段均以编译后的文档为准。 */
    public function save(array $payload): array
    {
        $id = (int) ($payload['id'] ?? 0);
        $expectedUpdatedAt = (string) ($payload['updated_at'] ?? '');
        $expectedSchemaHash = trim((string) ($payload['expected_schema_hash'] ?? ''));
        $compiled = $this->schemas->compile($this->schemaPayload($payload));
        $identity = $this->identity($compiled);
        $this->validateFields($compiled, $identity['source_type']);
        return Db::transaction(function () use ($payload, $id, $expectedUpdatedAt, $expectedSchemaHash, $compiled, $identity): array {
            $form = $id > 0 ? Form::lockForUpdate()->firstWhere('id', $id) : new Form();
            if ($id > 0 && !$form) {
                throw new InvalidArgumentException('表单不存在');
            }
            if ($id > 0) $this->schemas->assertIdentity($form, $compiled);
            if ($id > 0 && $expectedSchemaHash !== '' && (string) ($form->schema_hash ?? '') !== ''
                && !hash_equals((string) $form->schema_hash, $expectedSchemaHash)) {
                throw new InvalidArgumentException('表单 Schema 已被他人修改，请刷新后重试');
            }
            if ($id > 0 && $expectedSchemaHash === '' && $expectedUpdatedAt !== '' && (string) $form->updated_at !== $expectedUpdatedAt) {
                throw new InvalidArgumentException('表单已被他人修改，请刷新后重试');
            }
            if ($id === 0 && Form::where('form_key', $identity['form_key'])->first()) {
                throw new InvalidArgumentException('表单标识已存在');
            }
            // 只写身份与非派生列；schema_*、publish_status 与 name 由 saveCompiledVersion() → persistCurrent() 写入。
            // 更新时载荷缺省的管理列（status/remark/sort_order）保持原值，避免动态发布重置它们。
            $keep = static fn (string $key, mixed $default): mixed => array_key_exists($key, $payload) ? $payload[$key] : ($id > 0 ? $form->{$key} : $default);
            $form->fill([
                'form_key' => $identity['form_key'],
                'table_name' => $identity['table_name'],
                'connection' => $identity['connection'],
                'source_type' => $identity['source_type'],
                'status' => (int) $keep('status', 1),
                'publish_config' => $payload['publish_config'] ?? null,
                'remark' => trim((string) $keep('remark', '')),
                'sort_order' => (int) $keep('sort_order', 0),
            ]);
            // 新建行在 persistCurrent() 之前就要落库，name 为 NOT NULL 列，此处先写入文档 title。
            if ($id === 0) $form->name = $identity['name'];
            $form->save();
            $this->schemas->saveCompiledVersion(
                (int) $form->id,
                $compiled,
                (string) ($payload['schema_origin'] ?? 'designer'),
                (string) ($payload['schema_actor'] ?? 'system'),
                (string) ($payload['schema_change_summary'] ?? '')
            );
            return $this->detail((int) $form->id);
        });
    }

    /**
     * 编译文档中的表单身份，校验后返回 form_key/name/table_name/connection/source_type。
     *
     * @return array{form_key: string, name: string, table_name: string, connection: string, source_type: string}
     */
    private function identity(FormSchema $schema): array
    {
        $document = $schema->document();
        $database = (array) ($document['database'] ?? []);
        $identity = [
            'form_key' => $schema->key(),
            'name' => trim((string) ($document['title'] ?? '')),
            'table_name' => trim((string) ($database['table'] ?? '')),
            'connection' => trim((string) ($database['connection'] ?? '')) ?: 'mysql',
            'source_type' => (string) ($database['source'] ?? 'created'),
        ];
        if (!preg_match('/^[a-z][a-z0-9_]{0,60}$/', $identity['form_key'])) {
            throw new InvalidArgumentException('表单标识须以小写字母开头，仅包含小写字母、数字和下划线，最长 61 个字符');
        }
        if ($identity['name'] === '' || mb_strlen($identity['name']) > 100) {
            throw new InvalidArgumentException('表单名称不能为空且不能超过 100 个字符');
        }
        if (!preg_match('/^[a-z][a-z0-9_]*$/', $identity['table_name'])) {
            throw new InvalidArgumentException('表名必须为小写字母开头的字母数字下划线');
        }
        if (!in_array($identity['source_type'], ['created', 'adopted'], true)) {
            throw new InvalidArgumentException('来源类型只能为 created 或 adopted');
        }
        return $identity;
    }

    /**
     * 发布 / DDL 前置校验：身份合法、至少一个字段、逐字段校验；返回编译文档中的身份。
     *
     * @return array{form_key: string, name: string, table_name: string, connection: string, source_type: string}
     */
    public function assertPublishable(FormSchema $schema): array
    {
        $identity = $this->identity($schema);
        if ($schema->fields() === []) {
            throw new InvalidArgumentException('至少需要一个字段');
        }
        $this->validateFields($schema, $identity['source_type']);
        return $identity;
    }

    /** DDL 预览：created 表不存在→CREATE；已存在→仅加列/加索引；adopted→禁止。 */
    public function previewMigration(FormSchema $schema): array
    {
        $identity = $this->assertPublishable($schema);
        if ($identity['source_type'] === 'adopted') {
            return ['mode' => 'none', 'sql' => '', 'file' => '', 'message' => '采纳表禁止 DDL，仅保存元数据'];
        }
        $table = $identity['table_name'];
        $connection = $identity['connection'];
        $exists = in_array($table, array_column(SchemaInspector::forConnection($connection)->tables(), 'name'), true);
        $sql = $exists ? $this->additiveSql($table, $schema->fields(), $connection) : $this->createTableSql($table, $schema->fields());
        return [
            'mode' => $exists ? 'additive' : 'create',
            'sql' => $sql,
            'file' => $this->migrationPath($schema, $sql),
            'message' => $sql === '' ? '无结构变更' : ($exists ? '仅新增列/索引' : '创建新表'),
        ];
    }

    /** 动态发布专用 DDL：二次预检后仅执行 forward-only SQL，不生成或登记迁移文件。 */
    public function applyDynamicDdl(FormSchema $schema, string $expectedDdlHash): array
    {
        $expectedHash = trim($expectedDdlHash);
        $preview = $this->previewMigration($schema);
        $actualHash = hash('sha256', (string) $preview['sql']);
        if ($expectedHash === '' || !hash_equals($expectedHash, $actualHash)) {
            throw new InvalidArgumentException('FORM_SCHEMA_CONFLICT');
        }
        if ($preview['mode'] === 'none' || $preview['sql'] === '') {
            return $preview + ['applied' => false];
        }
        $connection = Db::connection($this->identity($schema)['connection']);
        $statements = $this->assertDynamicForwardSql((string) $preview['sql']);
        $ddlApplied = false;
        try {
            \app\common\service\database\DdlEncodingGuard::check($connection, (string) $preview['sql']);
            foreach ($statements as $statement) {
                $connection->statement($statement);
                $ddlApplied = true;
            }
        } catch (Throwable $exception) {
            throw new FormMigrationException($exception->getMessage(), $ddlApplied, $exception);
        }
        return array_diff_key($preview, ['file' => true]) + ['applied' => true];
    }

    /**
     * 动态发布只接受 forward-only SQL：单条 CREATE TABLE IF NOT EXISTS 或单条只新增列/索引/约束的 ALTER TABLE；
     * 唯一的多语句形状是已有表补删除令牌：ALTER（含 ADD COLUMN `deleted_token`）→ 回填已删除行令牌的固定 UPDATE →（可选）ALTER，三者同一张表。
     *
     * @return list<string> 按顺序执行的语句
     */
    private function assertDynamicForwardSql(string $sql): array
    {
        $message = '动态发布仅允许单条 forward-only CREATE TABLE 或 ADDITIVE ALTER TABLE';
        $statements = array_map('trim', explode(';', rtrim(trim($sql), ';')));
        if (in_array('', $statements, true)) {
            throw new InvalidArgumentException($message);
        }
        if (count($statements) === 1 && preg_match('/^CREATE\s+TABLE\s+IF\s+NOT\s+EXISTS\b/is', $statements[0]) === 1) {
            return $statements;
        }
        if (count($statements) === 1) {
            $this->additiveAlterTable($statements[0], $message);
            return $statements;
        }
        if (count($statements) > 3) {
            throw new InvalidArgumentException($message);
        }
        [$table, $actions] = $this->additiveAlterTable($statements[0], $message);
        if (!in_array(true, array_map(static fn (string $action): bool => preg_match('/^ADD\s+COLUMN\s+`deleted_token`\s/is', $action) === 1, $actions), true)
            || preg_match('/^UPDATE\s+`([a-z_][a-z0-9_]*)`\s+SET\s+`deleted_token`\s*=\s*`[a-z_][a-z0-9_]*`\s+WHERE\s+`deleted_at`\s+IS\s+NOT\s+NULL\s+AND\s+`deleted_token`\s*=\s*0$/D', $statements[1], $update) !== 1
            || $update[1] !== $table
            || (isset($statements[2]) && $this->additiveAlterTable($statements[2], $message)[0] !== $table)) {
            throw new InvalidArgumentException($message);
        }
        return $statements;
    }

    /** @return array{0: string, 1: list<string>} 表名与 ALTER 动作；任一动作不是新增列/索引/约束即拒绝。 */
    private function additiveAlterTable(string $statement, string $message): array
    {
        if (preg_match('/^ALTER\s+TABLE\s+(?:`([^`]+)`|([a-z_][a-z0-9_]*))\s+(.+)$/is', $statement, $match) !== 1) {
            throw new InvalidArgumentException($message);
        }
        $actions = $this->dynamicAlterActions($match[3]);
        foreach ($actions as $action) {
            if (preg_match('/^ADD\s+(?:COLUMN|KEY|UNIQUE\s+KEY|CONSTRAINT)\b/is', $action) !== 1) {
                throw new InvalidArgumentException('动态发布 ALTER TABLE 仅允许新增列、索引或约束');
            }
        }
        return [$match[1] !== '' ? $match[1] : $match[2], $actions];
    }

    /** 按顶层逗号切分 ALTER 动作，避免把 decimal(10,2) 或注释文本误识别为新动作。 */
    private function dynamicAlterActions(string $sql): array
    {
        $actions = [];
        $start = 0;
        $depth = 0;
        $quote = '';
        $length = strlen($sql);
        for ($index = 0; $index < $length; $index++) {
            $character = $sql[$index];
            if ($quote !== '') {
                if ($character === $quote && ($quote === '`' || $index === 0 || $sql[$index - 1] !== '\\')) {
                    $quote = '';
                }
                continue;
            }
            if (in_array($character, ["'", '"', '`'], true)) {
                $quote = $character;
            } elseif ($character === '(') {
                $depth++;
            } elseif ($character === ')') {
                $depth--;
            } elseif ($character === ',' && $depth === 0) {
                $actions[] = trim(substr($sql, $start, $index - $start));
                $start = $index + 1;
            }
        }
        $actions[] = trim(substr($sql, $start));
        return $actions;
    }

    /** 只接受 FormSchema v2 文档（schema_document.schemaVersion = 2）。 */
    private function schemaPayload(array $payload): array
    {
        $document = $payload['schema_document'] ?? null;
        if (!is_array($document) || ($document['schemaVersion'] ?? null) !== 2) {
            throw new FormSchemaException('schema_document 必须为 FormSchema v2 文档', '/schemaVersion', 'FORM_SCHEMA_VERSION_UNSUPPORTED');
        }
        // 只归一化新建身份，已有记录与采纳表不得随配置变化重命名。
        $database = (array) ($document['database'] ?? []);
        if ((int) ($payload['id'] ?? 0) === 0 && ($database['source'] ?? 'created') === 'created') {
            $connection = (string) ($database['connection'] ?? 'mysql');
            $prefix = (string) (\app\common\service\database\DatabaseConnections::settings($connection)['prefix'] ?? '');
            $table = trim((string) ($database['table'] ?? ''));
            if ($table !== '' && $prefix !== '' && !str_starts_with($table, $prefix)) {
                $document['database']['table'] = $prefix . $table;
            }
        }
        return $document;
    }

    private function migrationPath(FormSchema $schema, string $sql): string
    {
        $version = substr(hash('sha256', $sql), 0, 12);
        return $this->projectRoot . 'database' . DIRECTORY_SEPARATOR . 'business' . DIRECTORY_SEPARATOR . 'form_' . $schema->key() . '_' . $version . '.sql';
    }

    /** @param list<FormFieldView> $fields */
    private function createTableSql(string $table, array $fields): string
    {
        $lines = ['  `id` bigint unsigned NOT NULL AUTO_INCREMENT'];
        $indexes = [];
        // 软删除规则：有唯一字段才加 deleted_token，唯一键以 deleted_token 结尾（删除后可重建相同值）。
        $token = array_filter($fields, static fn (FormFieldView $field): bool => !$field->isVirtual() && $field->index() === 'unique') !== [];
        foreach ($fields as $field) {
            if ($field->isVirtual()) {
                continue;
            }
            $lines[] = '  ' . $this->columnDdl($field);
            $indexType = $field->index();
            $name = trim($field->name());
            if ($indexType === 'unique') {
                $indexes[] = '  UNIQUE KEY `uk_' . $table . '_' . $name . '` (`' . $name . '`' . ($token ? ', `deleted_token`' : '') . ')';
            } elseif ($indexType === 'index') {
                $indexes[] = '  KEY `idx_' . $table . '_' . $name . '` (`' . $name . '`)';
            }
            if ($field->relationType() === 'belongs_to') {
                $refTable = trim($field->relationTable());
                $refValue = trim($field->relationValueField()) ?: 'id';
                $onDelete = self::ON_DELETE[$field->relationOnDelete()] ?? 'RESTRICT';
                $indexes[] = '  KEY `idx_' . $table . '_' . $name . '_fk` (`' . $name . '`)';
                $indexes[] = '  CONSTRAINT `fk_' . $table . '_' . $name . '` FOREIGN KEY (`' . $name . '`) REFERENCES `' . $refTable . '` (`' . $refValue . '`) ON DELETE ' . $onDelete;
            }
        }
        $lines[] = '  `created_at` datetime NULL';
        $lines[] = '  `updated_at` datetime NULL';
        $lines[] = '  `deleted_at` datetime NULL';
        if ($token) $lines[] = '  `deleted_token` bigint unsigned NOT NULL DEFAULT 0';
        $lines[] = '  PRIMARY KEY (`id`)';
        $indexes[] = '  KEY `idx_' . $table . '_deleted_at` (`deleted_at`)';
        $sql = 'CREATE TABLE IF NOT EXISTS `' . $table . "` (\n" . implode(",\n", array_merge($lines, $indexes)) . "\n) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='表单数据表';\n";
        return $sql;
    }

    /**
     * 已有表只新增列/索引。新增唯一键以 deleted_token 结尾；表上还没有令牌列时先单独补令牌列（主键不是整数且没有 row_no 时另加自增 row_no），
     * 再把已删除行的令牌回填为令牌来源（整数主键为主键，否则 row_no），最后加业务列与索引，避免已删除行以令牌 0 占用唯一值。
     * 已有唯一键的改造由升级命令处理。
     *
     * @param list<FormFieldView> $fields
     */
    private function additiveSql(string $table, array $fields, string $connection = 'mysql'): string
    {
        $metadata = SchemaInspector::forConnection($connection)->inspect($table);
        $columns = array_column($metadata['columns'], null, 'name');
        $existing = array_keys($columns);
        $existingIndexes = array_column($metadata['indexes'], 'name');
        $prelude = '';
        $token = in_array(GeneratedSoftDeleteColumns::TOKEN, $existing, true);
        foreach ($fields as $field) {
            if (!$token && in_array('deleted_at', $existing, true) && !$field->isVirtual() && $field->index() === 'unique'
                && !in_array('uk_' . $table . '_' . trim($field->name()), $existingIndexes, true)) {
                $prelude = $this->tokenPreludeSql($table, $columns, (array) $metadata['primaryKey']);
                $token = true;
            }
        }
        $parts = [];
        foreach ($fields as $field) {
            if ($field->isVirtual()) {
                continue;
            }
            $name = trim($field->name());
            if (!in_array($name, $existing, true)) {
                $parts[] = 'ADD COLUMN ' . $this->columnDdl($field);
            }
            $indexType = $field->index();
            if ($indexType === 'unique' && !in_array('uk_' . $table . '_' . $name, $existingIndexes, true)) {
                $parts[] = 'ADD UNIQUE KEY `uk_' . $table . '_' . $name . '` (' . GeneratedSoftDeleteColumns::columnList(GeneratedSoftDeleteColumns::uniqueColumns($name, $token)) . ')';
            } elseif ($indexType === 'index' && !in_array('idx_' . $table . '_' . $name, $existingIndexes, true)) {
                $parts[] = 'ADD KEY `idx_' . $table . '_' . $name . '` (`' . $name . '`)';
            }
        }
        return $prelude . ($parts === [] ? '' : 'ALTER TABLE `' . $table . "`\n" . implode(",\n  ", $parts) . ";\n");
    }

    /**
     * 补令牌列的前置语句：ALTER 加 deleted_token（必要时加 row_no 与 idx_<表>_row_no），再回填已删除行。
     *
     * @param array<string, array{name: string, type: string, extra: string}> $columns
     * @param list<string> $primary
     */
    private function tokenPreludeSql(string $table, array $columns, array $primary): string
    {
        if (count($primary) !== 1) {
            throw new InvalidArgumentException('表 ' . $table . ' 没有单列主键，无法确定删除令牌来源，请先运行软删除升级或手写迁移');
        }
        $parts = ['ADD COLUMN `' . GeneratedSoftDeleteColumns::TOKEN . '` ' . GeneratedSoftDeleteColumns::TOKEN_DEFINITION];
        $source = $primary[0];
        if (!in_array(preg_replace('/\W.*$/s', '', (string) ($columns[$source]['type'] ?? '')), self::INTEGER_TYPES, true)) {
            $source = GeneratedSoftDeleteColumns::ROW_NO;
            if (!isset($columns[$source])) {
                foreach ($columns as $name => $column) {
                    if (str_contains(strtolower((string) $column['extra']), 'auto_increment')) {
                        throw new InvalidArgumentException('表 ' . $table . ' 已有自增列 ' . $name . '，无法添加 row_no，请手写迁移');
                    }
                }
                $parts[] = 'ADD COLUMN `' . $source . '` ' . GeneratedSoftDeleteColumns::ROW_NO_DEFINITION;
                $parts[] = 'ADD KEY `idx_' . $table . '_' . $source . '` (`' . $source . '`)';
            }
        }
        return 'ALTER TABLE `' . $table . "`\n" . implode(",\n  ", $parts) . ";\n"
            . 'UPDATE `' . $table . '` SET `' . GeneratedSoftDeleteColumns::TOKEN . '` = `' . $source . '` WHERE `deleted_at` IS NOT NULL AND `' . GeneratedSoftDeleteColumns::TOKEN . "` = 0;\n";
    }

    private function columnDdl(FormFieldView $field): string
    {
        $name = trim($field->name());
        $type = trim($field->columnType())
            ?: (string) ($this->fieldCapabilities->get($field->type())['defaultColumnType'] ?? '');
        if ($field->unsigned() && preg_match('/^(tinyint|int|bigint|decimal)/', $type)) {
            $type .= ' unsigned';
        }
        $comment = trim($field->comment());
        $nullable = $field->nullable();
        $default = $this->defaultText($field);
        $numeric = (bool) preg_match('/^(tinyint|int|bigint|decimal)/', $type);
        if ($numeric && $default !== '' && !preg_match('/^-?\d+(?:\.\d+)?$/D', $default)) {
            throw new InvalidArgumentException('字段 ' . $name . ' 数字默认值不合法');
        }
        $defaultLiteral = $numeric ? $default : SqlLiteral::quote($default);
        if ($nullable) {
            $nullDdl = $default === '' ? 'NULL DEFAULT NULL' : 'NULL DEFAULT ' . $defaultLiteral;
        } else {
            $nullDdl = 'NOT NULL DEFAULT ' . ($default === '' ? ($numeric ? '0' : "''") : $defaultLiteral);
        }
        $ddl = '`' . $name . '` ' . $type . ' ' . $nullDdl;
        if ($comment !== '') {
            $ddl .= ' COMMENT ' . SqlLiteral::quote($comment);
        }
        return $ddl;
    }

    /** 列默认值的 DDL 文本：标量转字符串，其它（null/数组）视为无默认值。 */
    private function defaultText(FormFieldView $field): string
    {
        $value = $field->defaultValue();
        return is_scalar($value) ? (string) $value : '';
    }

    private function assertIdentifier(string $identifier, string $label): void
    {
        if (!preg_match('/^[a-z_][a-z0-9_]*$/', trim($identifier))) {
            throw new InvalidArgumentException($label . '不合法');
        }
    }

    private function validateOptionsSource(?array $source, string $label): void
    {
        if ($source === null || $source === []) {
            return;
        }
        if (($source['kind'] ?? '') !== 'relation') {
            return;
        }
        $this->assertIdentifier((string) ($source['table'] ?? ''), $label . '选项关联表');
        $this->assertIdentifier((string) ($source['label_field'] ?? ''), $label . '选项显示字段');
        $this->assertIdentifier((string) ($source['value_field'] ?? ''), $label . '选项值字段');
    }
}
