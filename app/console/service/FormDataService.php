<?php

declare(strict_types=1);

namespace app\console\service;

use app\common\model\DictItem;
use app\common\model\DictType;
use app\console\model\Admin;
use app\console\model\Department;
use app\console\model\Form;
use app\console\model\FormField;
use InvalidArgumentException;
use think\facade\Db;
use think\facade\Validate;

/**
 * 表单数据运行态服务（M3）：元数据驱动的通用读写/校验/选项/子表。
 * 所有列名经元数据白名单约束，禁止任意列读写。
 */
final class FormDataService
{
    private const SORT_WHITELIST_EXTRA = ['id', 'created_at', 'updated_at'];
    private const EXPORT_LIMIT = 5000;
    private const LAYOUT_TYPES = ['group', 'grid', 'divider', 'text', 'collapse', 'tabs'];

    /** 表单元数据（启用态）。 */
    public function meta(string $key): array
    {
        $form = $this->form($key);
        $fields = $this->fields($key);
        $schema = Db::connect((string) $form->connection)->getFields((string) $form->table_name);
        return ['form' => $form, 'fields' => $fields, 'primaryKey' => $this->primaryKey($schema)];
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
        return ['row' => $row, 'children' => $children];
    }

    /** 新增：白名单过滤 + 动态校验。 */
    public function create(string $key, array $data): array
    {
        $form = $this->form($key);
        $fields = $this->fields($key);
        $this->assertValid($fields, $data, false);
        $split = $this->splitPayload($fields, $data, false);
        $connection = Db::connect((string) $form->connection);
        $schema = $connection->getFields((string) $form->table_name);
        $columns = array_keys($schema);
        $primary = $this->primaryKey($schema);
        return $connection->transaction(function () use ($connection, $form, $fields, $split, $data, $columns, $primary): array {
            $payload = $split['parent'];
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
    public function update(string $key, int|string $id, array $data): array
    {
        $form = $this->form($key);
        $fields = $this->fields($key);
        $this->assertValid($fields, $data, true);
        $split = $this->splitPayload($fields, $data, true);
        $connection = Db::connect((string) $form->connection);
        $schema = $connection->getFields((string) $form->table_name);
        $columns = array_keys($schema);
        $primary = $this->primaryKey($schema);
        return $connection->transaction(function () use ($connection, $form, $fields, $split, $columns, $primary, $id): array {
            $query = $connection->table((string) $form->table_name)->where($primary['name'], $id)->lock(true);
            if (!$query->find()) throw new InvalidArgumentException('数据不存在');
            $payload = $split['parent'];
            if (in_array('updated_at', $columns, true)) $payload['updated_at'] = date('Y-m-d H:i:s');
            if ($payload !== []) $connection->table((string) $form->table_name)->where($primary['name'], $id)->update($payload);
            $this->syncRelations($connection, $form, $fields, $id, $split['relations'], true);
            return ['id' => $id, 'primaryKey' => $primary['name']];
        });
    }

    /** 删除：含 deleted_at 列则软删。 */
    public function remove(string $key, int|string $id): array
    {
        $form = $this->form($key);
        $schema = Db::connect((string) $form->connection)->getFields((string) $form->table_name);
        $columns = array_keys($schema);
        $primary = $this->primaryKey($schema);
        $connection = Db::connect((string) $form->connection)->table((string) $form->table_name);
        if (in_array('deleted_at', $columns, true)) {
            $connection->where($primary['name'], $id)->update(['deleted_at' => date('Y-m-d H:i:s')]);
            return ['removed' => 1, 'mode' => 'soft'];
        }
        $connection->where($primary['name'], $id)->delete();
        return ['removed' => 1, 'mode' => 'hard'];
    }

    /** 选项源：static / 关联表（belongs_to 或 options_source.mode=relation）。 */
    public function options(string $key, string $fieldName): array
    {
        $field = $this->fields($key)->firstWhere('field_name', $fieldName);
        if (!$field) {
            throw new InvalidArgumentException('字段不存在：' . $fieldName);
        }
        $source = is_array($field->options_source) ? $field->options_source : [];
        $mode = (string) ($source['mode'] ?? ((string) $field->relation_type === 'belongs_to' ? 'relation' : 'static'));
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
            return Department::where('status', 1)->order('sort_order', 'asc')->field('id as value,name as label,pid')->select()->toArray();
        }
        if ($mode === 'user') {
            return Admin::where('status', 1)->order('id', 'asc')->field('id as value,nickname as label')->limit(500)->select()->toArray();
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
            return Db::connect((string) $this->form($key)->connection)->table($table)
                ->field($value . ' as value,' . $label . ' as label')
                ->limit(200)
                ->select()
                ->toArray();
        }
        return [];
    }

    /** 子表分页（has_many）。 */
    public function sub(string $key, string $relation, int|string $id, int $page, int $pageSize): array
    {
        $field = $this->fields($key)->firstWhere('field_name', $relation);
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

    /** 白名单过滤写入载荷（纯函数，供契约测试）。 */
    public function splitPayload($fieldRows, array $data, bool $isUpdate): array
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
        return ['parent' => $this->filterPayload($rows, $data, $isUpdate), 'relations' => $relations];
    }

    public function filterPayload(array $fieldRows, array $data, bool $isUpdate): array
    {
        $payload = [];
        foreach ($fieldRows as $field) {
            if ($this->isLayoutField($field) || (string) ($field['relation_type'] ?? 'none') === 'has_many') {
                continue;
            }
            $name = (string) ($field['field_name'] ?? '');
            if ($name === '' || !array_key_exists($name, $data)) {
                continue;
            }
            if ($isUpdate && (int) ($field['form_readonly'] ?? 0) === 1) {
                continue;
            }
            $value = $data[$name];
            if ((int) ($field['relation_multiple'] ?? 0) === 1 && is_array($value)) {
                $value = implode(',', array_map('strval', $value));
            } elseif (strtolower((string) ($field['column_type'] ?? '')) === 'json' && (is_array($value) || is_object($value))) {
                $value = json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
            }
            $payload[$name] = $value;
        }
        return $payload;
    }

    private function form(string $key): Form
    {
        $form = Form::where('form_key', $key)->where('status', 1)->find();
        if (!$form) {
            throw new InvalidArgumentException('表单不存在或已禁用：' . $key);
        }
        return $form;
    }

    private function fields(string $key)
    {
        $form = Form::where('form_key', $key)->find();
        return FormField::where('form_id', (int) ($form->id ?? 0))->order('sort_order', 'asc')->order('id', 'asc')->select();
    }

    private function baseQuery(Form $form, $fields)
    {
        $table = (string) $form->table_name;
        $this->assertIdentifier($table, '绑定表');
        $columns = array_keys(Db::connect((string) $form->connection)->getFields($table));
        $primary = $this->primaryKey(Db::connect((string) $form->connection)->getFields($table));
        $readable = array_values(array_intersect([$primary['name'], 'created_at', 'updated_at'], $columns));
        foreach ($fields as $field) {
            if (in_array((string) $field->type, self::LAYOUT_TYPES, true)) {
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
        $aliasIndex = 0;
        foreach ($fields as $field) {
            if ((string) $field->relation_type !== 'belongs_to' || (int) $field->list_show !== 1) {
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
        $configured = FormField::where('form_id', (int) $context['form']->id)->column('field_name');
        $readable = array_values(array_unique(array_merge($readable, array_intersect($configured, $columns))));
        $query = Db::connect((string) $context['form']->connection)->table($context['table'])->field($readable)->where($context['foreignKey'], $id);
        if (in_array('deleted_at', $columns, true)) $query->whereNull('deleted_at');
        $total = (clone $query)->count();
        return ['list' => $query->order($context['primary']['name'], 'asc')->page($page, $pageSize)->select()->toArray(), 'total' => (int) $total];
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
                if ($rowId !== null && $rowId !== '') {
                    $key = (string) $rowId;
                    if (isset($submitted[$key])) throw new InvalidArgumentException($name . ' 子表主键重复：' . $key);
                    if (!in_array($key, $existingIds, true)) throw new InvalidArgumentException($name . ' 子表数据不属于当前父记录');
                    $submitted[$key] = true;
                }
                $payload = $this->filterChildPayload($context['fields'], $row, $rowId !== null && $rowId !== '');
                $payload[$context['foreignKey']] = $parentId;
                if ($rowId === null || $rowId === '') {
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
        $schema = Db::connect((string) $form->connection)->getFields($table);
        return [
            'table' => $table,
            'foreignKey' => $foreignKey,
            'form' => $form,
            'schema' => $schema,
            'primary' => $this->primaryKey($schema),
            'fields' => FormField::where('form_id', (int) $form->id)->order('sort_order', 'asc')->select(),
        ];
    }

    private function filterChildPayload($fields, array $row, bool $isUpdate): array
    {
        $payload = $this->filterPayload(array_map(static fn ($field): array => $field->toArray(), $fields->all()), $row, $isUpdate);
        return $payload;
    }

    private function isLayoutField(array $field): bool
    {
        return in_array((string) ($field['type'] ?? ''), self::LAYOUT_TYPES, true);
    }

    private function assertIdentifier(string $identifier, string $label): void
    {
        if (!preg_match('/^[a-z_][a-z0-9_]*$/', $identifier)) {
            throw new InvalidArgumentException($label . '不合法');
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
