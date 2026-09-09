<?php

declare(strict_types=1);

namespace app\console\service;

use app\common\form\action\FormActionRegistry;
use app\common\form\component\PluginFormComponentRegistry;
use app\common\form\dataSource\FormDataSourceRegistry;
use app\common\form\validation\FormAsyncValidationException;
use app\common\form\validation\FormAsyncValidatorRegistry;
use app\common\form\validation\FormSchemaDataValidator;
use app\common\model\DictItem;
use app\common\model\DictType;
use app\console\model\Admin;
use app\console\model\BusinessModule;
use app\console\model\Department;
use app\console\model\Form;
use app\console\model\FormField;
use app\console\model\FormSchemaVersion;
use Closure;
use InvalidArgumentException;
use think\facade\Db;
use think\facade\Validate;

/**
 * 表单数据运行态服务（M3）：元数据驱动的通用读写/校验/选项/子表。
 * 所有列名经元数据白名单约束，禁止任意列读写。
 */
final class FormDataService
{
    private readonly FormAsyncValidatorRegistry $asyncValidators;
    private readonly FormDataSourceRegistry $dataSources;
    private readonly PluginFormComponentRegistry $pluginComponents;
    private readonly Closure $permissionChecker;

    public function __construct(
        ?FormAsyncValidatorRegistry $asyncValidators = null,
        ?FormDataSourceRegistry $dataSources = null,
        ?callable $permissionChecker = null,
        ?PluginFormComponentRegistry $pluginComponents = null
    ) {
        $this->asyncValidators = $asyncValidators ?? new FormAsyncValidatorRegistry();
        $this->dataSources = $dataSources ?? FormDataSourceRegistry::core();
        $this->permissionChecker = Closure::fromCallable($permissionChecker ?? static fn (string $permission): bool => false);
        $this->pluginComponents = $pluginComponents ?? new PluginFormComponentRegistry();
    }

    private const SORT_WHITELIST_EXTRA = ['id', 'created_at', 'updated_at'];
    private const EXPORT_LIMIT = 5000;
    private const LAYOUT_TYPES = ['group', 'grid', 'divider', 'text', 'collapse', 'tabs'];

    /** 表单元数据只使用不可变的已发布 FormSchema 快照，禁止当前草稿污染运行态。 */
    public function meta(string $key): array
    {
        $form = $this->form($key);
        $published = $this->publishedRuntime($form);
        $schema = Db::connect((string) $form->connection)->getFields((string) $form->table_name);
        $safeFields = array_map(function ($field): array {
            $row = $field->toArray();
            if ($this->isSensitiveField($row)) $row['default_value'] = '';
            return $row;
        }, $published['fields']->all());
        $formData = $form->toArray();
        unset($formData['schema_document'], $formData['schema_hash']);
        return [
            'form' => $formData,
            'fields' => $safeFields,
            'primaryKey' => $this->primaryKey($schema),
            'schema' => $published['schema'],
            'schemaHash' => $published['schemaHash'],
            'etag' => '"' . $published['schemaHash'] . '"',
        ];
    }

    /** 返回不含业务值的已发布运行态观测上下文。 */
    public function runtimeContext(string $key, string $nodeId = '', string $dataSource = '', string $actionKey = ''): array
    {
        $published = $this->publishedRuntime($this->form($key));
        if ($actionKey !== '') {
            $reference = $this->publishedActionReference($published['schema'], $actionKey);
            $nodeId = $reference['nodeId'];
        }
        return [
            'schemaHash' => $published['schemaHash'],
            'nodeId' => $nodeId,
            'dataSource' => $dataSource,
        ];
    }

    /** 校验提交基于当前已发布快照，防止旧页面向新 schema 写入数据。 */
    public function assertPublishedSchemaHash(string $actual, string $expected): string
    {
        if ($actual === '' || !hash_equals($expected, $actual)) {
            throw new InvalidArgumentException('FORM_SCHEMA_CONFLICT');
        }
        return $actual;
    }

    /** 列表：筛选/排序/分页/关联标签 LEFT JOIN。 */
    public function listing(string $key, array $filters, string $sort, string $order, int $page, int $pageSize): array
    {
        $fields = $this->fields($key);
        $form = $this->form($key);
        $schema = Db::connect((string) $form->connection)->getFields((string) $form->table_name);
        $primary = $this->primaryKey($schema);
        $query = $this->baseQuery($form, $fields);
        foreach ($fields as $field) {
            $filterType = (string) $field->list_filter;
            $name = (string) $field->field_name;
            $value = $filters[$name] ?? '';
            if ($filterType === 'eq' && $value !== '') {
                $query->where($name, $value);
            } elseif ($filterType === 'ne' && $value !== '') {
                $query->where($name, '<>', $value);
            } elseif ($filterType === 'like' && $value !== '') {
                $query->whereLike($name, '%' . $value . '%');
            } elseif ($filterType === 'not_like' && $value !== '') {
                $query->where($name, 'not like', '%' . $value . '%');
            } elseif ($filterType === 'starts_with' && $value !== '') {
                $query->whereLike($name, $value . '%');
            } elseif ($filterType === 'ends_with' && $value !== '') {
                $query->whereLike($name, '%' . $value);
            } elseif (in_array($filterType, ['gt', 'gte', 'lt', 'lte'], true) && $value !== '') {
                $operators = ['gt' => '>', 'gte' => '>=', 'lt' => '<', 'lte' => '<='];
                $query->where($name, $operators[$filterType], $value);
            } elseif (in_array($filterType, ['range', 'date'], true)) {
                $from = (string) ($filters[$name . '_from'] ?? '');
                $to = (string) ($filters[$name . '_to'] ?? '');
                if ($from !== '' && $to !== '') {
                    $query->whereBetween($name, [$from, $to]);
                } elseif ($from !== '') {
                    $query->where($name, '>=', $from);
                } elseif ($to !== '') {
                    $query->where($name, '<=', $to);
                }
            } elseif (in_array($filterType, ['in', 'not_in'], true) && $value !== '') {
                $values = array_values(array_filter(array_map('trim', explode(',', (string) $value)), static fn (string $item): bool => $item !== ''));
                if ($values !== []) {
                    $filterType === 'in' ? $query->whereIn($name, $values) : $query->whereNotIn($name, $values);
                }
            } elseif ($filterType === 'is_null' && (string) $value === '1') {
                $query->whereNull($name);
            } elseif ($filterType === 'not_null' && (string) $value === '1') {
                $query->whereNotNull($name);
            }
        }
        $sortable = $this->sortableColumns($fields);
        $order = strtolower($order) === 'desc' ? 'desc' : 'asc';
        $query->order(in_array($sort, $sortable, true) ? $sort : $primary['name'], $order);
        $total = (clone $query)->count();
        $rows = $query->page($page, $pageSize)->select()->toArray();
        $rows = array_map(fn (array $row): array => $this->sanitizeRecord($fields, $row), $rows);
        return ['list' => $rows, 'total' => (int) $total];
    }

    /** 导出：上限 5000 行。 */
    public function export(string $key, array $filters): array
    {
        $result = $this->listing($key, $filters, '', 'asc', 1, self::EXPORT_LIMIT);
        return $result['list'];
    }

    /** 详情：行 + has_many 子表首屏。 */
    public function detail(string $key, int|string $id): array
    {
        $form = $this->form($key);
        $fields = $this->fields($key);
        $schema = Db::connect((string) $form->connection)->getFields((string) $form->table_name);
        $primary = $this->primaryKey($schema);
        $row = $this->baseQuery($form, $fields)->where($form->table_name . '.' . $primary['name'], $id)->find();
        if (!$row) {
            throw new InvalidArgumentException('数据不存在');
        }
        $children = [];
        foreach ($fields as $field) {
            if ((string) $field->relation_type === 'has_many') {
                $children[(string) $field->field_name] = $this->subRows($field, $id, 1, 20);
            }
        }
        return ['row' => $this->sanitizeRecord($fields, $row), 'children' => $children];
    }

    /** 新增：白名单过滤 + 动态校验。 */
    public function create(string $key, array $data, array $include = [], string $schemaHash = ''): array
    {
        $form = $this->form($key);
        $published = $this->publishedRuntime($form);
        $this->assertPublishedSchemaHash($schemaHash, $published['schemaHash']);
        $fields = $published['fields'];
        $this->assertSchemaValid($published['schema'], $data);
        $this->assertValid($fields, $data, false);
        $this->assertAsyncValid($published['schema'], $data);
        $split = $this->splitPayload($fields, $data, false, $include);
        $connection = Db::connect((string) $form->connection);
        $schema = $connection->getFields((string) $form->table_name);
        $columns = array_keys($schema);
        $primary = $this->primaryKey($schema);
        return $connection->transaction(function () use ($connection, $form, $fields, $split, $data, $columns, $primary, $published): array {
            $payload = $this->applyWriteDataScope($split['parent'], $form, $columns);
            $now = date('Y-m-d H:i:s');
            if (in_array('created_at', $columns, true)) $payload['created_at'] = $now;
            if (in_array('updated_at', $columns, true)) $payload['updated_at'] = $now;
            if ($primary['type'] === 'string') {
                $provided = $data[$primary['name']] ?? null;
                if (!is_scalar($provided) || trim((string) $provided) === '') throw new InvalidArgumentException('字符串主键创建时必须提供：' . $primary['name']);
                $payload[$primary['name']] = (string) $provided;
                $connection->table((string) $form->table_name)->insert($payload);
                $id = (string) $provided;
            } else {
                $id = (int) $connection->table((string) $form->table_name)->insertGetId($payload, $primary['name']);
            }
            $this->syncRelations($connection, $form, $fields, $id, $split['relations'], false);
            return ['id' => $id, 'primaryKey' => $primary['name']];
        });
    }

    /** 更新：禁改字段剔除 + 动态校验。 */
    public function update(string $key, int|string $id, array $data, array $include = [], string $schemaHash = ''): array
    {
        $form = $this->form($key);
        $published = $this->publishedRuntime($form);
        $this->assertPublishedSchemaHash($schemaHash, $published['schemaHash']);
        $fields = $published['fields'];
        $this->assertSchemaValid($published['schema'], $data);
        $this->assertValid($fields, $data, true);
        $this->assertAsyncValid($published['schema'], $data);
        $split = $this->splitPayload($fields, $data, true, $include);
        $connection = Db::connect((string) $form->connection);
        $schema = $connection->getFields((string) $form->table_name);
        $columns = array_keys($schema);
        $primary = $this->primaryKey($schema);
        return $connection->transaction(function () use ($connection, $form, $fields, $split, $columns, $primary, $id, $published): array {
            $query = $this->applyDataScope(
                $connection->table((string) $form->table_name)->where($primary['name'], $id)->lock(true),
                $form,
                $columns,
                (string) $form->table_name
            );
            if (!$query->find()) throw new InvalidArgumentException('数据不存在');
            $payload = $this->applyWriteDataScope($split['parent'], $form, $columns);
            if (in_array('updated_at', $columns, true)) $payload['updated_at'] = date('Y-m-d H:i:s');
            if ($payload !== []) {
                $this->applyDataScope(
                    $connection->table((string) $form->table_name)->where($primary['name'], $id),
                    $form,
                    $columns,
                    (string) $form->table_name
                )->update($payload);
            }
            $this->syncRelations($connection, $form, $fields, $id, $split['relations'], true);
            return ['id' => $id, 'primaryKey' => $primary['name']];
        });
    }

    /** 删除：含 deleted_at 列则软删。 */
    public function remove(string $key, int|string $id, string $schemaHash = ''): array
    {
        $form = $this->form($key);
        $published = $this->publishedRuntime($form);
        $this->assertPublishedSchemaHash($schemaHash, $published['schemaHash']);
        $schema = Db::connect((string) $form->connection)->getFields((string) $form->table_name);
        $columns = array_keys($schema);
        $primary = $this->primaryKey($schema);
        $connection = $this->applyDataScope(
            Db::connect((string) $form->connection)->table((string) $form->table_name),
            $form,
            $columns,
            (string) $form->table_name
        );
        if (in_array('deleted_at', $columns, true)) {
            $connection->where($primary['name'], $id)->update(['deleted_at' => date('Y-m-d H:i:s')]);
            return ['removed' => 1, 'mode' => 'soft'];
        }
        $connection->where($primary['name'], $id)->delete();
        return ['removed' => 1, 'mode' => 'hard'];
    }

    /** 选项源：static / 关联表（belongs_to 或 options_source.mode=relation）。 */
    public function options(string $key, string $fieldName, array $context = []): array
    {
        $field = $this->fields($key)->firstWhere('field_name', $fieldName);
        if (!$field) {
            throw new InvalidArgumentException('字段不存在：' . $fieldName);
        }
        $source = is_array($field->options_source) ? $field->options_source : [];
        $mode = (string) ($source['kind'] ?? $source['mode'] ?? ((string) $field->relation_type === 'belongs_to' ? 'relation' : 'static'));
        $source['kind'] = $mode;
        $source['mode'] = $mode;
        if (in_array($mode, ['endpoint', 'computed'], true)) {
            return $this->executeOptionsSource($source, [
                'form' => ['key' => $key],
                'context' => $context,
                'search' => (string) ($context['keyword'] ?? ''),
            ]);
        }
        if ($mode === 'static') {
            $options = $source['options'] ?? [];
            return is_array($options) ? array_values($options) : [];
        }
        if ($mode === 'dictionary') {
            $code = (string) ($source['dictionary'] ?? '');
            if (!preg_match('/^[A-Za-z][A-Za-z0-9_]{0,59}$/', $code)) {
                return [];
            }
            $type = DictType::where('code', $code)->where('status', 1)->find();
            if (!$type) {
                return [];
            }
            return DictItem::where('type_id', (int) $type->id)
                ->where('status', 1)
                ->order('sort_order', 'asc')
                ->order('id', 'asc')
                ->field('value,label')
                ->select()
                ->toArray();
        }
        if ($mode === 'department') {
            $scope = (new DataScopeService())->resolve();
            $query = Department::where('status', 1);
            if (!$scope['all']) $query->whereIn('id', $scope['departmentIds'] ?: [0]);
            return $query->order('sort_order', 'asc')->field('id as value,name as label,pid')->select()->toArray();
        }
        if ($mode === 'user') {
            $ids = (new DataScopeService())->visibleAdminIds();
            return Admin::where('status', 1)->whereIn('id', $ids ?: [0])->order('id', 'asc')->field('id as value,nickname as label')->limit(500)->select()->toArray();
        }
        if ($mode === 'relation') {
            $table = (string) ($source['table'] ?? $field->relation_table);
            $label = (string) ($source['label_field'] ?? $field->relation_label_field);
            $value = (string) ($source['value_field'] ?? $field->relation_value_field);
            if ($table === '' || $label === '' || $value === '') {
                throw new InvalidArgumentException('关联选项源配置不完整');
            }
            $this->assertIdentifier($table, '关联选项表');
            $this->assertIdentifier($label, '关联选项显示字段');
            $this->assertIdentifier($value, '关联选项值字段');
            $form = $this->form($key);
            $connection = Db::connect((string) $form->connection);
            $columns = array_keys($connection->getFields($table));
            $query = $connection->table($table)->field($value . ' as value,' . $label . ' as label');
            $scopeField = trim((string) ($source['department_field'] ?? $source['departmentField'] ?? ''));
            if ($scopeField !== '') {
                $this->assertIdentifier($scopeField, '关联选项部门字段');
                if (!in_array($scopeField, $columns, true)) throw new InvalidArgumentException('关联选项部门字段不存在');
                $scope = (new DataScopeService())->resolve();
                if (!$scope['all']) $query->whereIn($scopeField, $scope['departmentIds'] ?: [0]);
            }
            $tenantField = trim((string) ($source['tenant_field'] ?? $source['tenantField'] ?? ''));
            if ($tenantField !== '') {
                $this->assertIdentifier($tenantField, '关联选项租户字段');
                if (!in_array($tenantField, $columns, true)) throw new InvalidArgumentException('关联选项租户字段不存在');
                $tenantId = (int) session('tenant.id');
                if ($tenantId < 1) throw new InvalidArgumentException('当前租户上下文不可用');
                $query->where($tenantField, $tenantId);
            }
            return $query->limit(200)->select()->toArray();
        }
        return [];
    }

    /**
     * 对已受控加载的选项执行搜索与分页。
     *
     * @param array<int, array<string, mixed>> $options
     * @return array{options: array<int, array<string, mixed>>, total: int}
     */
    public function paginateOptions(array $options, string $keyword, int $page, int $pageSize): array
    {
        $filtered = $keyword === '' ? array_values($options) : array_values(array_filter(
            $options,
            static fn (array $option): bool => str_contains((string) ($option['label'] ?? ''), $keyword)
        ));
        $size = max(1, min(200, $pageSize));
        $offset = (max(1, $page) - 1) * $size;
        return ['options' => array_slice($filtered, $offset, $size), 'total' => count($filtered)];
    }

    /** 将列表或分页结果归一为 v2 list/total，并保留旧 options 契约。 */
    public function normalizeOptionsResult(array $result): array
    {
        $list = array_is_list($result) ? $result : (array) ($result['list'] ?? $result['options'] ?? []);
        return [
            'list' => array_values($list),
            'options' => array_values($list),
            'total' => (int) ($result['total'] ?? count($list)),
        ];
    }

    /** 执行已归一的数据源定义，供运行态与独立契约测试复用。 */
    public function executeOptionsSource(array $source, array $context = []): array
    {
        return $this->dataSources->execute($source, $context, $this->permissionChecker);
    }

    /**
     * 在已发布快照中定位 request 动作，返回节点与声明链深度。
     *
     * @return array{nodeId: string, chainDepth: int}
     */
    public function publishedActionReference(array $schema, string $actionKey): array
    {
        $found = $this->findActionReference((array) ($schema['actions'] ?? []), $actionKey, '', 0);
        if ($found !== null) {
            return $found;
        }
        $visit = function (array $nodes) use (&$visit, $actionKey): ?array {
            foreach ($nodes as $node) {
                if (!is_array($node)) {
                    continue;
                }
                foreach ((array) ($node['events'] ?? []) as $actions) {
                    $found = $this->findActionReference((array) $actions, $actionKey, (string) ($node['id'] ?? ''), 0);
                    if ($found !== null) {
                        return $found;
                    }
                }
                $found = $visit((array) ($node['children'] ?? []));
                if ($found !== null) {
                    return $found;
                }
            }
            return null;
        };
        $found = $visit((array) ($schema['nodes'] ?? []));
        if ($found === null) {
            throw new InvalidArgumentException('FORM_ACTION_NOT_DECLARED');
        }
        return $found;
    }

    /** 执行已发布 Schema 明确引用的生产动作。 */
    public function executeAction(
        string $key,
        string $actionKey,
        array $parameters,
        string $idempotencyKey,
        int $chainDepth,
        FormActionRegistry $actions
    ): array {
        $published = $this->publishedRuntime($this->form($key));
        $reference = $this->publishedActionReference($published['schema'], $actionKey);
        $result = $actions->execute($actionKey, $parameters, $this->permissionChecker, [
            'formKey' => $key,
            'idempotencyKey' => $idempotencyKey,
            'chainDepth' => max($chainDepth, $reference['chainDepth']),
        ]);
        return [
            'result' => $result,
            'schemaHash' => $published['schemaHash'],
            'nodeId' => $reference['nodeId'],
        ];
    }

    /** 子表分页（has_many）。 */
    public function sub(string $key, string $relation, int|string $id, int $page, int $pageSize): array
    {
        $form = $this->form($key);
        $fields = $this->fields($key);
        $schema = Db::connect((string) $form->connection)->getFields((string) $form->table_name);
        $primary = $this->primaryKey($schema);
        $parent = $this->baseQuery($form, $fields)->where($form->table_name . '.' . $primary['name'], $id)->find();
        if (!$parent) throw new InvalidArgumentException('父数据不存在或无访问权限');
        $field = $fields->firstWhere('field_name', $relation);
        if (!$field || (string) $field->relation_type !== 'has_many') {
            throw new InvalidArgumentException('子表不存在：' . $relation);
        }
        return $this->subRows($field, $id, $page, $pageSize);
    }

    /** 动态校验规则构建（纯函数，供契约测试）。 */
    public function buildRules(array $fieldRows): array
    {
        $rules = [];
        $messages = [];
        foreach ($fieldRows as $field) {
            if ($this->isLayoutField($field)) {
                continue;
            }
            $name = (string) ($field['field_name'] ?? '');
            $label = (string) ($field['label'] ?? $name);
            $parts = [];
            if ((int) ($field['form_required'] ?? 0) === 1) {
                $parts[] = 'require';
                $messages[$name . '.require'] = $label . '不能为空';
            }
            $type = (string) ($field['type'] ?? 'input');
            $columnType = (string) ($field['column_type'] ?? '');
            if ($type === 'number' || preg_match('/^(int|bigint|decimal)/', $columnType)) {
                $parts[] = 'number';
            } elseif ($type === 'switch') {
                $parts[] = 'in:0,1';
            } elseif ($type === 'date' || preg_match('/^(date|datetime)/', $columnType)) {
                $parts[] = str_starts_with($columnType, 'datetime')
                    ? 'dateFormat:Y-m-d H:i:s'
                    : 'dateFormat:Y-m-d';
            }
            $extra = is_array($field['validate_rules'] ?? null) ? $field['validate_rules'] : [];
            if (isset($extra['minlen'], $extra['maxlen'])) {
                $parts[] = 'length:' . (int) $extra['minlen'] . ',' . (int) $extra['maxlen'];
            } elseif (isset($extra['minlen'])) {
                $parts[] = 'length:' . (int) $extra['minlen'];
            }
            if (isset($extra['min'])) {
                $parts[] = 'egt:' . $extra['min'];
            }
            if (isset($extra['max'])) {
                $parts[] = 'elt:' . $extra['max'];
            }
            if (isset($extra['pattern']) && is_string($extra['pattern']) && $extra['pattern'] !== '') {
                $parts[] = 'regex:' . $extra['pattern'];
            }
            if ($parts !== []) {
                $rules[$name] = implode('|', $parts);
            }
        }
        return ['rules' => $rules, 'messages' => $messages];
    }

    /**
     * 服务端复验字段声明的异步验证器。
     *
     * @param iterable<int, mixed> $fieldRows
     * @param array<string, mixed> $data
     * @return array<int, array{path: string, message: string}>
     */
    public function revalidateAsync(iterable $fieldRows, array $data): array
    {
        $errors = [];
        foreach ($fieldRows as $field) {
            $row = is_array($field) ? $field : $field->toArray();
            $name = (string) ($row['field_name'] ?? '');
            $rules = is_array($row['validate_rules'] ?? null) ? $row['validate_rules'] : [];
            $declarations = $rules['async'] ?? [];
            if (isset($declarations['key'])) {
                $declarations = [$declarations];
            }
            if ($name === '' || !is_array($declarations) || !array_key_exists($name, $data)) {
                continue;
            }
            foreach ($declarations as $declaration) {
                if (!is_array($declaration)) {
                    continue;
                }
                $message = $this->asyncValidators->validate(
                    (string) ($declaration['key'] ?? ''),
                    $data[$name],
                    $data,
                    is_array($declaration['params'] ?? null) ? $declaration['params'] : []
                );
                if ($message !== null) {
                    $errors[] = ['path' => $name, 'message' => $message];
                    break;
                }
            }
        }
        return $errors;
    }

    /** 返回已发布 FormSchema AST 中字段声明的全部同 key 异步规则。 */
    public function schemaAsyncDeclarations(array $schema, string $field, string $validator): array
    {
        $declarations = [];
        $visit = function (array $nodes) use (&$visit, &$declarations, $field, $validator): void {
            foreach ($nodes as $node) {
                if (!is_array($node)) continue;
                if (($node['field'] ?? null) === $field) {
                    foreach ((array) ($node['validation'] ?? []) as $rule) {
                        if (!is_array($rule) || ($rule['type'] ?? '') !== 'async' || !is_array($rule['validator'] ?? null)) continue;
                        if (($rule['validator']['key'] ?? null) === $validator) $declarations[] = $rule;
                    }
                }
                $visit((array) ($node['children'] ?? []));
            }
        };
        $visit((array) ($schema['nodes'] ?? []));
        return $declarations;
    }

    /** 直接按已发布 FormSchema AST 原序复验异步规则。 */
    public function revalidateSchemaAsync(array $schema, array $data): array
    {
        $errors = [];
        $dataValidator = new FormSchemaDataValidator();
        $visit = function (array $nodes) use (&$visit, &$errors, $data, $dataValidator): void {
            foreach ($nodes as $node) {
                if (!is_array($node)) continue;
                $field = (string) ($node['field'] ?? '');
                if ($field !== '' && array_key_exists($field, $data) && !$dataValidator->valueIsEmpty($data[$field])) {
                    foreach ((array) ($node['validation'] ?? []) as $rule) {
                        if (!is_array($rule) || ($rule['type'] ?? '') !== 'async' || !is_array($rule['validator'] ?? null)) continue;
                        if (!$dataValidator->conditionMatches($rule['when'] ?? null, $data)) continue;
                        $validator = $rule['validator'];
                        $message = $this->asyncValidators->validate(
                            (string) ($validator['key'] ?? ''), $data[$field], $data,
                            is_array($validator['params'] ?? null) ? $validator['params'] : []
                        );
                        if ($message === null) continue;
                        $errors[] = ['path' => $field, 'message' => $message];
                        if (($rule['bail'] ?? true) === true) break;
                    }
                }
                $visit((array) ($node['children'] ?? []));
            }
        };
        $visit((array) ($schema['nodes'] ?? []));
        return $errors;
    }

    /**
     * 执行单字段受控异步验证，供前端即时反馈；提交仍会再次整表复验。
     *
     * @param array<string, mixed> $values
     * @param array<string, mixed> $params
     * @return array{valid: bool, fieldErrors: array<int, array{path: string, message: string}>}
     */
    public function validateAsync(string $key, string $field, string $validator, mixed $value, array $values, array $params = []): array
    {
        $published = $this->publishedRuntime($this->form($key));
        $declarations = $this->schemaAsyncDeclarations($published['schema'], $field, $validator);
        if ($declarations === []) {
            throw new FormAsyncValidationException('FORM_ASYNC_VALIDATOR_NOT_DECLARED');
        }
        $values[$field] = $value;
        $dataValidator = new FormSchemaDataValidator();
        if ($dataValidator->valueIsEmpty($value)) {
            return ['valid' => true, 'fieldErrors' => []];
        }
        $errors = [];
        foreach ($declarations as $rule) {
            if (!$dataValidator->conditionMatches($rule['when'] ?? null, $values)) continue;
            $declaration = $rule['validator'];
            $message = $this->asyncValidators->validate(
                $validator,
                $value,
                $values,
                is_array($declaration['params'] ?? null) ? $declaration['params'] : []
            );
            if ($message === null) continue;
            $errors[] = ['path' => $field, 'message' => $message];
            if (($rule['bail'] ?? true) === true) break;
        }
        return ['valid' => $errors === [], 'fieldErrors' => $errors];
    }

    /** 白名单过滤写入载荷（纯函数，供契约测试）。 */
    public function splitPayload($fieldRows, array $data, bool $isUpdate, array $include = []): array
    {
        $rows = array_map(static fn ($field): array => is_array($field) ? $field : $field->toArray(), is_array($fieldRows) ? $fieldRows : $fieldRows->all());
        $relations = [];
        foreach ($rows as $field) {
            if ((string) ($field['relation_type'] ?? 'none') !== 'has_many') continue;
            $name = (string) ($field['field_name'] ?? '');
            if (!array_key_exists($name, $data)) continue;
            if (!is_array($data[$name]) || !array_is_list($data[$name])) throw new InvalidArgumentException($name . ' 必须为子表行数组');
            $relations[$name] = $data[$name];
        }
        return ['parent' => $this->filterPayload($rows, $data, $isUpdate, $include), 'relations' => $relations];
    }

    public function filterPayload(array $fieldRows, array $data, bool $isUpdate, array $include = []): array
    {
        $payload = [];
        $included = array_fill_keys(array_map('strval', $include), true);
        foreach ($fieldRows as $field) {
            if ($this->isLayoutField($field) || (string) ($field['relation_type'] ?? 'none') === 'has_many') {
                continue;
            }
            $name = (string) ($field['field_name'] ?? '');
            if ($name === '' || !array_key_exists($name, $data)) {
                continue;
            }
            if (!$this->fieldAccessAllowed($field, 'write')) {
                continue;
            }
            $props = is_array($field['control_props'] ?? null) ? $field['control_props'] : [];
            $access = is_array($props['schemaAccess'] ?? null) ? $props['schemaAccess'] : [];
            if ($this->isExcludedFromSubmission($field)
                && ($access['include'] ?? 'auto') !== 'always'
                && !isset($included[$name])) {
                continue;
            }
            $value = $data[$name];
            $type = (string) ($field['type'] ?? '');
            if (str_contains($type, ':')) {
                $message = $this->pluginComponents->validateValue($type, $value, $data, (array) ($field['control_props'] ?? []));
                if ($message !== null) {
                    throw new InvalidArgumentException($message);
                }
                $value = $this->pluginComponents->encode($type, $value);
            }
            if ((int) ($field['relation_multiple'] ?? 0) === 1 && is_array($value)) {
                $value = implode(',', array_map('strval', $value));
            } elseif (strtolower((string) ($field['column_type'] ?? '')) === 'json' && (is_array($value) || is_object($value))) {
                $value = json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
            }
            $payload[$name] = $value;
        }
        return $payload;
    }

    /**
     * 移除任何不能回显到列表、详情、默认值或客户端缓存的敏感字段。
     *
     * @param iterable<int, mixed> $fieldRows
     * @param array<string, mixed> $record
     * @return array<string, mixed>
     */
    public function sanitizeRecord(iterable $fieldRows, array $record): array
    {
        foreach ($fieldRows as $field) {
            $row = is_array($field) ? $field : $field->toArray();
            $name = (string) ($row['field_name'] ?? '');
            if ($this->isSensitiveField($row) || !$this->fieldAccessAllowed($row, 'read')) {
                unset($record[$name]);
                continue;
            }
            $type = (string) ($row['type'] ?? '');
            if ($name !== '' && array_key_exists($name, $record) && str_contains($type, ':')) {
                $record[$name] = $this->pluginComponents->decode($type, $record[$name]);
            }
        }
        return $record;
    }

    /** 将写入请求中的敏感字段替换为审计占位符。 */
    public function redactRequestPayload(string $key, array $data): array
    {
        foreach ($this->fields($key) as $field) {
            $row = $field->toArray();
            $name = (string) ($row['field_name'] ?? '');
            if ($name !== '' && array_key_exists($name, $data) && $this->isSensitiveField($row)) {
                $data[$name] = '[REDACTED]';
            }
        }
        return $data;
    }

    private function form(string $key): Form
    {
        $form = Form::where('form_key', $key)->where('status', 1)->find();
        if (!$form) {
            throw new InvalidArgumentException('表单不存在或已禁用：' . $key);
        }
        $published = $this->publishedRuntime($form);
        $database = (array) ($published['schema']['database'] ?? []);
        $form->name = (string) ($published['schema']['title'] ?? $form->name);
        $form->table_name = (string) ($database['table'] ?? $form->table_name);
        $form->connection = (string) ($database['connection'] ?? $form->connection);
        $form->source_type = (string) ($database['source'] ?? $form->source_type);
        return $form;
    }

    private function fields(string $key)
    {
        return $this->publishedRuntime($this->form($key))['fields'];
    }

    /**
     * @return array{schema: array<string, mixed>, schemaHash: string, fields: \think\Collection, module: BusinessModule}
     */
    private function publishedRuntime(Form $form): array
    {
        try {
            $module = BusinessModule::where(function ($query) use ($form): void {
                $query->where('form_id', (int) $form->id)->whereOr('code', (string) $form->form_key);
            })->find();
        } catch (\Throwable $exception) {
            throw new InvalidArgumentException('业务模块存储不可用，请先执行 077_business_development_center 迁移', 0, $exception);
        }
        if (!$module || !in_array((string) $module->lifecycle_status, ['published', 'dynamic_published'], true)) {
            throw new InvalidArgumentException('业务模块尚未发布');
        }
        $publishedHash = trim((string) ($module->published_schema_hash ?? ''));
        $publishedVersion = (int) ($module->published_schema_version ?? 0);
        if ($publishedHash === '' || $publishedVersion < 1) {
            throw new InvalidArgumentException('业务模块已发布 Schema 绑定不完整');
        }
        $version = FormSchemaVersion::where('form_id', (int) $module->form_id)
            ->where('version', $publishedVersion)
            ->where('schema_hash', $publishedHash)
            ->find();
        if (!$version) {
            throw new InvalidArgumentException('业务模块已发布 FormSchema 快照不存在');
        }
        $compiled = (new FormSchemaRepository())->compile((array) $version->schema_document);
        if (!hash_equals($publishedHash, $compiled->hash())) {
            throw new InvalidArgumentException('已发布表单快照校验失败');
        }
        $fields = new \think\Collection(array_map(
            static fn (array $field): FormField => new FormField($field),
            $compiled->fieldProjection()
        ));
        return ['schema' => $compiled->document(), 'schemaHash' => $compiled->hash(), 'fields' => $fields, 'module' => $module];
    }

    private function baseQuery(Form $form, $fields)
    {
        $table = (string) $form->table_name;
        $this->assertIdentifier($table, '绑定表');
        $columns = array_keys(Db::connect((string) $form->connection)->getFields($table));
        $primary = $this->primaryKey(Db::connect((string) $form->connection)->getFields($table));
        $readable = array_values(array_intersect([$primary['name'], 'created_at', 'updated_at'], $columns));
        foreach ($fields as $field) {
            if (in_array((string) $field->type, self::LAYOUT_TYPES, true)
                || $this->isSensitiveField($field->toArray())
                || !$this->fieldAccessAllowed($field->toArray(), 'read')) {
                continue;
            }
            $name = (string) $field->field_name;
            $this->assertIdentifier($name, '表单字段');
            if (in_array($name, $columns, true)) {
                $readable[] = $name;
            }
        }
        $select = array_map(static fn (string $column): string => $table . '.' . $column, array_values(array_unique($readable)));
        $query = Db::connect((string) $form->connection)->table($table)->field($select);
        if (in_array('deleted_at', $columns, true)) {
            $query->whereNull($table . '.deleted_at');
        }
        $query = $this->applyDataScope($query, $form, $columns, $table);
        $aliasIndex = 0;
        foreach ($fields as $field) {
            if ((string) $field->relation_type !== 'belongs_to' || (int) $field->list_show !== 1 || $this->isSensitiveField($field->toArray())) {
                continue;
            }
            $relationTable = (string) $field->relation_table;
            $label = (string) $field->relation_label_field;
            $value = (string) $field->relation_value_field;
            if ($relationTable === '' || $label === '' || $value === '') {
                continue;
            }
            $this->assertIdentifier($relationTable, '关联表');
            $this->assertIdentifier($label, '关联显示字段');
            $this->assertIdentifier($value, '关联值字段');
            $alias = 'rel_' . $aliasIndex;
            $aliasIndex++;
            $query->leftJoin($relationTable . ' ' . $alias, $alias . '.' . $value . ' = ' . $table . '.' . $field->field_name);
            $query->addField($alias . '.' . $label . ' as __label_' . $field->field_name);
        }
        return $query;
    }

    private function dataScopeField(Form $form, array $columns): string
    {
        $published = $this->publishedRuntime($form);
        $publish = (array) ($published['module']->metadata['publishConfig'] ?? []);
        $enabled = (bool) ($publish['dataScopeEnabled'] ?? false);
        $field = trim((string) ($publish['dataScopeField'] ?? ''));
        if (!$enabled) return '';
        if ($field === '' || !in_array($field, $columns, true)) {
            throw new InvalidArgumentException('已发布数据权限字段不存在，拒绝无范围访问');
        }
        $this->assertIdentifier($field, '数据权限字段');
        return $field;
    }

    private function applyDataScope($query, Form $form, array $columns, string $table)
    {
        $field = $this->dataScopeField($form, $columns);
        if ($field === '') return $query;
        $scope = (new DataScopeService())->resolve();
        if ($scope['all']) return $query;
        return $query->whereIn($table . '.' . $field, $scope['departmentIds'] ?: [0]);
    }

    private function applyWriteDataScope(array $payload, Form $form, array $columns): array
    {
        $field = $this->dataScopeField($form, $columns);
        if ($field === '') return $payload;
        $scope = (new DataScopeService())->resolve();
        if ($scope['all']) return $payload;
        $requested = (int) ($payload[$field] ?? 0);
        if (!in_array($requested, $scope['departmentIds'], true)) {
            throw new InvalidArgumentException('数据不在当前部门权限范围内');
        }
        return $payload;
    }

    private function primaryKey(array $schema): array
    {
        $primary = array_filter($schema, static fn (array $field): bool => ($field['primary'] ?? false) === true);
        if (count($primary) !== 1) throw new InvalidArgumentException('业务表必须且只能包含一个主键');
        $name = (string) array_key_first($primary);
        $type = strtolower((string) ($primary[$name]['type'] ?? ''));
        return ['name' => $name, 'type' => preg_match('/(?:tinyint|smallint|mediumint|bigint|int)/', $type) ? 'integer' : 'string'];
    }

    private function sortableColumns($fields): array
    {
        $columns = self::SORT_WHITELIST_EXTRA;
        foreach ($fields as $field) {
            if ((int) $field->list_sort === 1) {
                $columns[] = (string) $field->field_name;
            }
        }
        return $columns;
    }

    private function subRows(FormField $field, int|string $id, int $page, int $pageSize): array
    {
        $context = $this->childContext($field);
        $columns = array_keys($context['schema']);
        $readable = array_values(array_intersect([$context['primary']['name'], $context['foreignKey'], 'created_at', 'updated_at'], $columns));
        $configured = array_values(array_filter(
            array_map(static fn ($field): array => $field->toArray(), $context['fields']->all()),
            fn (array $field): bool => !$this->isSensitiveField($field)
        ));
        $configuredNames = array_column($configured, 'field_name');
        $readable = array_values(array_unique(array_merge($readable, array_intersect($configuredNames, $columns))));
        $query = Db::connect((string) $context['form']->connection)->table($context['table'])->field($readable)->where($context['foreignKey'], $id);
        if (in_array('deleted_at', $columns, true)) $query->whereNull('deleted_at');
        $total = (clone $query)->count();
        $rows = $query->order($context['primary']['name'], 'asc')->page($page, $pageSize)->select()->toArray();
        $rows = array_map(fn (array $row): array => $this->sanitizeRecord($context['fields'], $row), $rows);
        return ['list' => $rows, 'total' => (int) $total];
    }

    private function syncRelations($connection, Form $parentForm, $fields, int|string $parentId, array $relations, bool $isUpdate): void
    {
        foreach ($fields as $field) {
            $name = (string) $field->field_name;
            if ((string) $field->relation_type !== 'has_many' || !array_key_exists($name, $relations)) continue;
            $context = $this->childContext($field);
            if ((string) $context['form']->connection !== (string) $parentForm->connection) throw new InvalidArgumentException('父子表必须使用同一数据库连接');
            $primaryName = $context['primary']['name'];
            $existing = $connection->table($context['table'])->where($context['foreignKey'], $parentId);
            if (in_array('deleted_at', array_keys($context['schema']), true)) $existing->whereNull('deleted_at');
            $existingIds = array_map('strval', $existing->column($primaryName));
            $submitted = [];
            foreach ($relations[$name] as $row) {
                if (!is_array($row)) throw new InvalidArgumentException($name . ' 子表行必须为对象');
                $rowId = $row[$primaryName] ?? null;
                $hasRowId = $rowId !== null && $rowId !== '';
                $updatesExisting = $hasRowId && in_array((string) $rowId, $existingIds, true);
                if ($hasRowId) {
                    $key = (string) $rowId;
                    if (isset($submitted[$key])) throw new InvalidArgumentException($name . ' 子表主键重复：' . $key);
                    if (!$updatesExisting && $connection->table($context['table'])->where($primaryName, $rowId)->find()) {
                        throw new InvalidArgumentException($name . ' 子表数据不属于当前父记录');
                    }
                    if (!$updatesExisting && $context['primary']['type'] !== 'string') {
                        throw new InvalidArgumentException($name . ' 子表数据不属于当前父记录');
                    }
                    $submitted[$key] = true;
                }
                $payload = $this->filterChildPayload($context['fields'], $row, $updatesExisting);
                $payload[$context['foreignKey']] = $parentId;
                if (!$updatesExisting) {
                    if ($hasRowId) $payload[$primaryName] = (string) $rowId;
                    $connection->table($context['table'])->insert($payload);
                } else {
                    unset($payload[$primaryName]);
                    $connection->table($context['table'])->where($primaryName, $rowId)->where($context['foreignKey'], $parentId)->update($payload);
                }
            }
            if ($isUpdate) {
                $removed = array_values(array_diff($existingIds, array_keys($submitted)));
                if ($removed !== []) {
                    $query = $connection->table($context['table'])->where($context['foreignKey'], $parentId)->whereIn($primaryName, $removed);
                    if (in_array('deleted_at', array_keys($context['schema']), true)) $query->update(['deleted_at' => date('Y-m-d H:i:s')]);
                    else $query->delete();
                }
            }
        }
    }

    private function childContext(FormField $field): array
    {
        $table = (string) $field->relation_table;
        $foreignKey = (string) $field->relation_value_field;
        $this->assertIdentifier($table, '子表');
        $this->assertIdentifier($foreignKey, '子表外键字段');
        $form = Form::where('table_name', $table)->where('status', 1)->find();
        if (!$form) throw new InvalidArgumentException('子表必须配置启用的表单元数据：' . $table);
        $published = $this->publishedRuntime($form);
        $database = (array) ($published['schema']['database'] ?? []);
        $publishedTable = (string) ($database['table'] ?? $table);
        if ($publishedTable !== $table) throw new InvalidArgumentException('已发布子表快照与关系配置不一致');
        $form->connection = (string) ($database['connection'] ?? $form->connection);
        $schema = Db::connect((string) $form->connection)->getFields($publishedTable);
        return [
            'table' => $publishedTable,
            'foreignKey' => $foreignKey,
            'form' => $form,
            'schema' => $schema,
            'primary' => $this->primaryKey($schema),
            'fields' => $published['fields'],
        ];
    }

    private function filterChildPayload($fields, array $row, bool $isUpdate): array
    {
        $payload = $this->filterPayload(array_map(static fn ($field): array => $field->toArray(), $fields->all()), $row, $isUpdate);
        return $payload;
    }

    private function findActionReference(array $actions, string $actionKey, string $nodeId, int $depth): ?array
    {
        foreach ($actions as $action) {
            if (!is_array($action)) {
                continue;
            }
            $currentDepth = array_key_exists('type', $action) ? $depth + 1 : $depth;
            if (($action['type'] ?? '') === 'request' && ($action['key'] ?? '') === $actionKey) {
                return ['nodeId' => $nodeId, 'chainDepth' => $currentDepth];
            }
            $found = $this->findActionReference((array) ($action['steps'] ?? []), $actionKey, $nodeId, $currentDepth);
            if ($found !== null) {
                return $found;
            }
        }
        return null;
    }

    private function isLayoutField(array $field): bool
    {
        return in_array((string) ($field['type'] ?? ''), self::LAYOUT_TYPES, true);
    }

    private function fieldAccessAllowed(array $field, string $operation): bool
    {
        $props = is_array($field['control_props'] ?? null) ? $field['control_props'] : [];
        $access = is_array($props['schemaAccess'] ?? null) ? $props['schemaAccess'] : [];
        if ($operation === 'write' && ($access['include'] ?? 'auto') === 'never') return false;
        $permissions = is_array($access[$operation] ?? null) ? $access[$operation] : [];
        foreach ($permissions as $permission) {
            if (!is_string($permission) || !($this->permissionChecker)($permission)) return false;
        }
        return true;
    }

    private function isExcludedFromSubmission(array $field): bool
    {
        $props = is_array($field['control_props'] ?? null) ? $field['control_props'] : [];
        $source = is_array($field['options_source'] ?? null) ? $field['options_source'] : [];
        return (string) ($field['type'] ?? '') === 'hidden'
            || filter_var($props['disabled'] ?? false, FILTER_VALIDATE_BOOL)
            || (int) ($field['form_readonly'] ?? 0) === 1
            || (string) ($source['kind'] ?? $source['mode'] ?? '') === 'computed'
            || filter_var($props['computed'] ?? false, FILTER_VALIDATE_BOOL)
            || filter_var($props['primary'] ?? false, FILTER_VALIDATE_BOOL)
            || filter_var($props['system'] ?? false, FILTER_VALIDATE_BOOL);
    }

    private function isSensitiveField(array $field): bool
    {
        $props = is_array($field['control_props'] ?? null) ? $field['control_props'] : [];
        return (string) ($field['type'] ?? '') === 'password'
            || filter_var($props['sensitive'] ?? false, FILTER_VALIDATE_BOOL)
            || filter_var($props['writeOnly'] ?? false, FILTER_VALIDATE_BOOL);
    }

    private function assertIdentifier(string $identifier, string $label): void
    {
        if (!preg_match('/^[a-z_][a-z0-9_]*$/', $identifier)) {
            throw new InvalidArgumentException($label . '不合法');
        }
    }

    private function assertSchemaValid(array $schema, array $data): void
    {
        $rules = [];
        $visit = function (array $nodes) use (&$visit, &$rules): void {
            foreach ($nodes as $node) {
                if (!is_array($node)) continue;
                $field = (string) ($node['field'] ?? '');
                if ($field !== '' && is_array($node['validation'] ?? null)) $rules[$field] = $node['validation'];
                $visit((array) ($node['children'] ?? []));
            }
        };
        $visit((array) ($schema['nodes'] ?? []));
        $errors = (new FormSchemaDataValidator())->validate($data, $rules);
        if ($errors !== []) {
            throw new FormAsyncValidationException('FORM_SCHEMA_VALIDATION_FAILED', array_map(
                static fn (array $error): array => ['path' => '/' . $error['field'], 'message' => $error['message']],
                $errors
            ));
        }
    }

    private function assertAsyncValid(array $schema, array $data): void
    {
        $errors = $this->revalidateSchemaAsync($schema, $data);
        if ($errors !== []) {
            throw new FormAsyncValidationException('FORM_ASYNC_VALIDATION_FAILED', $errors);
        }
    }

    private function assertValid($fields, array $data, bool $isUpdate): void
    {
        $rows = array_map(static fn ($field): array => is_array($field) ? $field : $field->toArray(), is_array($fields) ? $fields : $fields->all());
        $built = $this->buildRules($rows);
        if ($built['rules'] === []) {
            return;
        }
        $validate = Validate::rule($built['rules'])->message($built['messages']);
        $payload = $isUpdate ? $data : array_intersect_key($data, $built['rules']) + array_fill_keys(array_keys($built['rules']), '');
        if (!$validate->check($payload)) {
            throw new InvalidArgumentException((string) $validate->getError());
        }
    }
}
