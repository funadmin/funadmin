<?php

declare(strict_types=1);

namespace app\console\service;

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

    /** 表单元数据（启用态）。 */
    public function meta(string $key): array
    {
        return ['form' => $this->form($key), 'fields' => $this->fields($key)];
    }

    /** 列表：筛选/排序/分页/关联标签 LEFT JOIN。 */
    public function listing(string $key, array $filters, string $sort, string $order, int $page, int $pageSize): array
    {
        $fields = $this->fields($key);
        $form = $this->form($key);
        $query = $this->baseQuery($form, $fields);
        foreach ($fields as $field) {
            $filterType = (string) $field->list_filter;
            $name = (string) $field->field_name;
            if ($filterType === 'eq' && isset($filters[$name]) && $filters[$name] !== '') {
                $query->where($name, $filters[$name]);
            } elseif ($filterType === 'like' && isset($filters[$name]) && $filters[$name] !== '') {
                $query->whereLike($name, '%' . $filters[$name] . '%');
            } elseif ($filterType === 'range' || $filterType === 'date') {
                $from = (string) ($filters[$name . '_from'] ?? '');
                $to = (string) ($filters[$name . '_to'] ?? '');
                if ($from !== '' && $to !== '') {
                    $query->whereBetween($name, [$from, $to]);
                } elseif ($from !== '') {
                    $query->where($name, '>=', $from);
                } elseif ($to !== '') {
                    $query->where($name, '<=', $to);
                }
            }
        }
        $sortable = $this->sortableColumns($fields);
        $order = strtolower($order) === 'desc' ? 'desc' : 'asc';
        $query->order(in_array($sort, $sortable, true) ? $sort : 'id', $order);
        $total = (clone $query)->count();
        $rows = $query->page($page, $pageSize)->select()->toArray();
        return ['list' => $rows, 'total' => (int) $total];
    }

    /** 导出：上限 5000 行。 */
    public function export(string $key, array $filters): array
    {
        $result = $this->listing($key, $filters, 'id', 'asc', 1, self::EXPORT_LIMIT);
        return $result['list'];
    }

    /** 详情：行 + has_many 子表首屏。 */
    public function detail(string $key, int $id): array
    {
        $form = $this->form($key);
        $fields = $this->fields($key);
        $row = $this->baseQuery($form, $fields)->where($form->table_name . '.id', $id)->find();
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
        $payload = $this->filterPayload($fields, $data, false);
        $columns = array_keys(Db::connect((string) $form->connection)->getFields((string) $form->table_name));
        $now = date('Y-m-d H:i:s');
        if (in_array('created_at', $columns, true)) {
            $payload['created_at'] = $now;
        }
        if (in_array('updated_at', $columns, true)) {
            $payload['updated_at'] = $now;
        }
        $id = (int) Db::connect((string) $form->connection)->table((string) $form->table_name)->insertGetId($payload);
        return ['id' => $id];
    }

    /** 更新：禁改字段剔除 + 动态校验。 */
    public function update(string $key, int $id, array $data): array
    {
        $form = $this->form($key);
        $fields = $this->fields($key);
        $this->assertValid($fields, $data, true);
        $payload = $this->filterPayload($fields, $data, true);
        $columns = array_keys(Db::connect((string) $form->connection)->getFields((string) $form->table_name));
        if (in_array('updated_at', $columns, true)) {
            $payload['updated_at'] = date('Y-m-d H:i:s');
        }
        Db::connect((string) $form->connection)->table((string) $form->table_name)->where('id', $id)->update($payload);
        return ['id' => $id];
    }

    /** 删除：含 deleted_at 列则软删。 */
    public function remove(string $key, int $id): array
    {
        $form = $this->form($key);
        $columns = array_keys(Db::connect((string) $form->connection)->getFields((string) $form->table_name));
        $connection = Db::connect((string) $form->connection)->table((string) $form->table_name);
        if (in_array('deleted_at', $columns, true)) {
            $connection->where('id', $id)->update(['deleted_at' => date('Y-m-d H:i:s')]);
            return ['removed' => 1, 'mode' => 'soft'];
        }
        $connection->where('id', $id)->delete();
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
    public function sub(string $key, string $relation, int $id, int $page, int $pageSize): array
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
    public function filterPayload(array $fieldRows, array $data, bool $isUpdate): array
    {
        $payload = [];
        foreach ($fieldRows as $field) {
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
        $readable = array_values(array_intersect(['id', 'created_at', 'updated_at'], $columns));
        foreach ($fields as $field) {
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

    private function subRows(FormField $field, int $id, int $page, int $pageSize): array
    {
        $childTable = (string) $field->relation_table;
        $foreignKey = (string) $field->relation_value_field;
        if ($childTable === '' || $foreignKey === '') {
            return ['list' => [], 'total' => 0];
        }
        $this->assertIdentifier($childTable, '子表');
        $this->assertIdentifier($foreignKey, '子表外键字段');
        $columns = array_keys(Db::getFields($childTable));
        $readable = array_values(array_intersect(['id', $foreignKey, 'created_at', 'updated_at'], $columns));
        $childForm = Form::where('table_name', $childTable)->where('status', 1)->find();
        if ($childForm) {
            $configured = FormField::where('form_id', (int) $childForm->id)->column('field_name');
            $readable = array_values(array_unique(array_merge($readable, array_intersect($configured, $columns))));
        }
        $query = Db::table($childTable)->field($readable)->where($foreignKey, $id);
        $total = (clone $query)->count();
        return ['list' => $query->page($page, $pageSize)->select()->toArray(), 'total' => (int) $total];
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
