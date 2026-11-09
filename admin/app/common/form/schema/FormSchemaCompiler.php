<?php

declare(strict_types=1);

namespace app\common\form\schema;

use JsonException;

final class FormSchemaCompiler
{
    public function __construct(private readonly FormSchemaValidator $validator)
    {
    }

    public function compile(array $schema): FormSchema
    {
        $schema = $this->normalizeOrigin($schema);
        $this->validator->validate($schema);
        $schema = $this->applyTreeControl($schema);
        // 空 relations 不进入 canonical 文档，保证未配置关联的 schemaHash 不变。
        if (($schema['relations'] ?? null) === []) unset($schema['relations']);
        // 空 list.customActions 同理剔除，未新建自定义动作的模块 schemaHash 不变。
        if (is_array($schema['list'] ?? null) && ($schema['list']['customActions'] ?? null) === []) unset($schema['list']['customActions']);
        $canonical = $this->canonicalize($schema);
        try {
            $json = json_encode($canonical, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new FormSchemaException('Schema 无法编码：' . $exception->getMessage(), '/');
        }
        return new FormSchema($canonical, $json);
    }

    /**
     * 树形表格：父级字段统一投影为 self-tree 树选择控件，新增/编辑页必须可见，
     * 单/多选由 list.tree.selectionMode 决定。
     */
    private function applyTreeControl(array $schema): array
    {
        $tree = $schema['list']['tree'] ?? null;
        if (!is_array($tree) || ($tree['enabled'] ?? false) !== true) return $schema;
        $parent = (string) ($tree['parentField'] ?? '');
        if ($parent === '') return $schema;
        $multiple = ($tree['selectionMode'] ?? 'single') === 'multiple';
        $walk = function (array $nodes) use (&$walk, $parent, $multiple): array {
            foreach ($nodes as &$node) {
                if (($node['kind'] ?? 'field') === 'field' && ($node['field'] ?? '') === $parent) {
                    $node['type'] = 'treeSelect';
                    $node['dataSource'] = ['kind' => 'self-tree'];
                    $node['props'] = is_array($node['props'] ?? null) ? $node['props'] : [];
                    $node['props']['multiple'] = $multiple;
                    $node['hidden'] = false;
                }
                if (in_array($node['type'] ?? '', ['group', 'grid', 'collapse', 'tabs', 'repeatable', 'subform'], true)) {
                    $node['children'] = $walk($node['children'] ?? []);
                }
            }
            return $nodes;
        };
        $schema['nodes'] = $walk($schema['nodes'] ?? []);
        return $schema;
    }

    /**
     * origin 仅描述入口来源，不属于业务文档语义，编译和 hash 前统一移除。
     */
    private function normalizeOrigin(array $schema): array
    {
        if (!is_array($schema['extensions'] ?? null)) {
            return $schema;
        }
        unset($schema['extensions']['origin']);
        return $schema;
    }

    private function canonicalize(mixed $value): mixed
    {
        if (!is_array($value)) return $value;
        if (array_is_list($value)) return array_map(fn (mixed $item): mixed => $this->canonicalize($item), $value);
        ksort($value, SORT_STRING);
        foreach ($value as $key => $item) $value[$key] = $this->canonicalize($item);
        return $value;
    }
}
