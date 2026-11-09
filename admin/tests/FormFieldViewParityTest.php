<?php

declare(strict_types=1);

namespace Tests;

use app\admin\form\repository\FormSchemaRepository;
use app\common\form\schema\FormFieldView;
use app\common\form\schema\FormSchema;
use PHPUnit\Framework\TestCase;

/**
 * 临时特征测试：FormFieldView 与旧 v1 投影逐字段一致。
 * 投影删除时（Task 12）一并删除本测试。
 */
final class FormFieldViewParityTest extends TestCase
{
    public function test_views_match_projection(): void
    {
        $schema = $this->schema();
        $layoutIds = $this->layoutIds($schema->document()['nodes']);
        $rows = array_values(array_filter(
            $schema->fieldProjection(),
            static fn (array $row): bool => !in_array($row['field_name'], $layoutIds, true)
        ));

        $views = $schema->fields();
        self::assertSame(
            array_column($rows, 'field_name'),
            array_map(static fn (FormFieldView $view): string => $view->name(), $views)
        );
        self::assertSame(['title', 'category_id', 'email', 'status', 'secret', 'tags', 'items'], array_column($rows, 'field_name'));

        foreach ($views as $index => $view) {
            $this->assertParity($view, $rows[$index]);
        }
    }

    private function assertParity(FormFieldView $view, array $row): void
    {
        $name = $view->name();
        $props = $row['control_props'];
        unset($props['schemaAccess']);
        $expected = [
            'title' => $row['label'],
            'type' => $row['type'],
            'columnType' => $row['column_type'],
            'nullable' => $row['nullable'] === 1,
            'unsigned' => $row['unsigned'] === 1,
            'comment' => $row['comment'],
            'index' => $row['index_type'],
            'relationType' => $row['relation_type'],
            'relationTable' => $row['relation_table'],
            'relationLabelField' => $row['relation_label_field'],
            'relationValueField' => $row['relation_value_field'],
            'relationMultiple' => $row['relation_multiple'] === 1,
            'relationOnDelete' => $row['relation_on_delete'],
            'listShow' => $row['list_show'] === 1,
            'listSort' => $row['list_sort'] === 1,
            'listFilter' => $row['list_filter'],
            'listFormatter' => $row['list_formatter'],
            'listWidth' => $row['list_width'],
            'hidden' => $row['form_show'] === 0,
            'disabled' => $row['form_readonly'] === 1,
            'required' => $row['form_required'] === 1,
            'span' => $row['form_span'],
            'group' => $row['form_group'],
            'rules' => $row['validate_rules'] ?? [],
            'access' => $row['control_props']['schemaAccess'],
            'props' => $props,
            'dataSource' => $row['options_source'],
            'conditions' => $row['link_rules']['rules'],
        ];
        foreach ($expected as $getter => $value) {
            self::assertSame($value, $view->{$getter}(), $name . '.' . $getter);
        }
    }

    /** @return list<string> */
    private function layoutIds(array $nodes): array
    {
        $ids = [];
        foreach ($nodes as $node) {
            if (($node['kind'] ?? 'field') === 'layout') $ids[] = (string) $node['id'];
            if (!in_array($node['type'] ?? '', ['repeatable', 'subform'], true)) {
                $ids = array_merge($ids, $this->layoutIds((array) ($node['children'] ?? [])));
            }
        }
        return $ids;
    }

    private function schema(): FormSchema
    {
        return (new FormSchemaRepository())->compile([
            'schemaVersion' => 2, 'key' => 'field_view_parity', 'title' => '视图一致性',
            'database' => ['connection' => 'mysql', 'table' => 'fun_field_view_parity', 'source' => 'created'],
            'nodes' => [
                [
                    'id' => 'node_base', 'kind' => 'layout', 'type' => 'group', 'field' => null, 'title' => '基础信息',
                    'layout' => ['span' => 24],
                    'children' => [
                        [
                            'id' => 'node_title', 'kind' => 'field', 'type' => 'input', 'field' => 'title', 'title' => '标题',
                            'database' => ['columnType' => 'varchar(120)', 'nullable' => false, 'comment' => '标题', 'index' => 'index'],
                            'list' => ['show' => true, 'sort' => true, 'filter' => 'like', 'width' => 180],
                            'layout' => ['span' => 12, 'group' => '基础'],
                            'validation' => [['type' => 'required'], ['type' => 'maxLength', 'value' => 120]],
                            'props' => ['placeholder' => '请输入标题'],
                        ],
                        [
                            'id' => 'node_category', 'kind' => 'field', 'type' => 'select', 'field' => 'category_id', 'title' => '分类',
                            'database' => [
                                'columnType' => 'int', 'unsigned' => true, 'nullable' => true, 'index' => 'index',
                                'relation' => ['type' => 'belongs_to', 'table' => 'fun_category', 'labelField' => 'name', 'valueField' => 'id', 'onDelete' => 'cascade'],
                            ],
                            'list' => ['show' => true, 'filter' => 'eq', 'formatter' => 'relation'],
                            'layout' => ['span' => 12],
                            'dataSource' => ['kind' => 'static', 'options' => [['label' => '新闻', 'value' => 1], ['label' => '公告', 'value' => 2]]],
                        ],
                    ],
                ],
                [
                    'id' => 'node_email', 'kind' => 'field', 'type' => 'input', 'field' => 'email', 'title' => '邮箱',
                    'database' => ['columnType' => 'varchar(190)', 'index' => 'unique'],
                    'validation' => [['type' => 'email'], ['type' => 'maxLength', 'value' => 190]],
                    'disabled' => true,
                ],
                [
                    'id' => 'node_status', 'kind' => 'field', 'type' => 'switch', 'field' => 'status', 'title' => '状态',
                    'database' => ['columnType' => 'tinyint', 'nullable' => false],
                    'defaultValue' => 1,
                    'list' => ['show' => true, 'formatter' => 'switch', 'width' => 90],
                    'conditions' => [['when' => ['op' => 'eq', 'field' => 'email', 'value' => ''], 'then' => ['action' => 'disable', 'target' => 'status']]],
                ],
                [
                    'id' => 'node_secret', 'kind' => 'field', 'type' => 'password', 'field' => 'secret', 'title' => '密钥',
                    'database' => ['columnType' => 'varchar(255)'],
                    'access' => ['include' => 'never', 'read' => ['demo.secret.read'], 'write' => ['demo.secret.write']],
                    'hidden' => true,
                ],
                [
                    'id' => 'node_tags', 'kind' => 'field', 'type' => 'select', 'field' => 'tags', 'title' => '标签',
                    'database' => ['relation' => ['type' => 'belongs_to', 'table' => 'fun_tag', 'labelField' => 'name', 'multiple' => true]],
                    'props' => ['multiple' => true],
                    'dataSource' => ['kind' => 'dictionary', 'dictionary' => 'tag'],
                ],
                [
                    'id' => 'node_items', 'kind' => 'field', 'type' => 'repeatable', 'field' => 'items', 'title' => '明细',
                    'database' => ['relation' => ['type' => 'has_many', 'table' => 'fun_item', 'valueField' => 'parity_id']],
                    'children' => [
                        ['id' => 'node_sku', 'kind' => 'field', 'type' => 'input', 'field' => 'sku', 'title' => '编码', 'database' => ['columnType' => 'varchar(50)']],
                    ],
                ],
            ],
        ]);
    }
}
