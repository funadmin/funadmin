<?php

declare(strict_types=1);

namespace app\common\form\schema;

final class FormSchema
{
    /** @var list<FormFieldView>|null */
    private ?array $fields = null;

    public function __construct(
        private readonly array $document,
        private readonly string $canonicalJson
    ) {
    }

    public function version(): int
    {
        return (int) $this->document['schemaVersion'];
    }

    public function key(): string
    {
        return (string) $this->document['key'];
    }

    public function document(): array
    {
        return $this->document;
    }

    public function canonicalJson(): string
    {
        return $this->canonicalJson;
    }

    public function hash(): string
    {
        return hash('sha256', $this->canonicalJson);
    }

    /**
     * 文档顺序深度优先：跳过 layout 节点但访问其子节点；
     * 其它节点都是字段，repeatable/subform 的子节点不进入顶层。
     *
     * @return list<FormFieldView>
     */
    public function fields(): array
    {
        return $this->fields ??= self::collectFields((array) ($this->document['nodes'] ?? []));
    }

    public function field(string $name): ?FormFieldView
    {
        foreach ($this->fields() as $field) {
            if ($field->name() === $name) return $field;
        }
        return null;
    }

    /** @return list<FormFieldView> */
    private static function collectFields(array $nodes): array
    {
        $fields = [];
        foreach ($nodes as $node) {
            if (!is_array($node)) continue;
            if (($node['kind'] ?? 'field') !== 'layout') {
                $view = FormFieldView::fromNode($node);
                $fields[] = $view;
                if ($view->isRelationContainer()) continue;
            }
            $children = $node['children'] ?? [];
            if (is_array($children) && $children !== []) {
                array_push($fields, ...self::collectFields($children));
            }
        }
        return $fields;
    }
}
