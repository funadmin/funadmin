<?php

declare(strict_types=1);

namespace Tests;

use app\admin\form\repository\FormSchemaRepository;
use app\common\form\schema\FormSchema;
use PHPUnit\Framework\TestCase;

final class FormFieldViewTest extends TestCase
{
    public function test_defaults_for_bare_field_node(): void
    {
        $view = $this->compile([$this->node('a')])->fields()[0];

        self::assertSame('a', $view->name());
        self::assertSame('node_a', $view->nodeId());
        self::assertSame('标题 a', $view->title());
        self::assertSame('input', $view->type());
        self::assertTrue($view->nullable());
        self::assertFalse($view->unsigned());
        self::assertSame('none', $view->index());
        self::assertSame('', $view->columnType());
        self::assertSame('', $view->comment());
        self::assertSame('none', $view->relationType());
        self::assertSame('', $view->relationTable());
        self::assertSame('', $view->relationLabelField());
        self::assertSame('restrict', $view->relationOnDelete());
        self::assertSame('', $view->relationValueField());
        self::assertFalse($view->relationMultiple());
        self::assertSame(24, $view->span());
        self::assertSame('', $view->group());
        self::assertSame(0, $view->listWidth());
        self::assertFalse($view->listShow());
        self::assertFalse($view->listSort());
        self::assertSame('', $view->listFilter());
        self::assertSame('', $view->listFormatter());
        self::assertFalse($view->hidden());
        self::assertFalse($view->disabled());
        self::assertNull($view->defaultValue());
        self::assertSame([], $view->props());
        self::assertSame([], $view->access());
        self::assertSame([], $view->validation());
        self::assertFalse($view->required());
        self::assertSame([], $view->rules());
        self::assertSame([], $view->conditions());
        self::assertNull($view->dataSource());
        self::assertSame('', $view->dataSourceKind());
        self::assertFalse($view->isRelationContainer());
        self::assertFalse($view->isVirtual());
    }

    public function test_traversal_skips_layout_and_hides_container_children(): void
    {
        $schema = $this->compile([
            ['id' => 'node_group', 'kind' => 'layout', 'type' => 'group', 'field' => null, 'title' => '分组', 'children' => [$this->node('a')]],
            $this->node('b'),
            [
                'id' => 'node_r', 'kind' => 'field', 'type' => 'repeatable', 'field' => 'r', 'title' => '明细',
                'database' => ['relation' => ['type' => 'has_many', 'table' => 'demo_item', 'valueField' => 'demo_id']],
                'children' => [$this->node('c')],
            ],
        ]);

        $names = array_map(static fn ($view): string => $view->name(), $schema->fields());
        self::assertSame(['a', 'b', 'r'], $names);
        $container = $schema->field('r');
        self::assertNotNull($container);
        self::assertTrue($container->isRelationContainer());
        self::assertTrue($container->isVirtual());
        self::assertSame('has_many', $container->relationType());
        self::assertSame('demo_id', $container->relationValueField());
        self::assertSame($schema->fields(), $schema->fields(), 'fields() 应缓存结果');
    }

    public function test_required_and_rules(): void
    {
        $view = $this->compile([$this->node('a', ['validation' => [
            ['type' => 'required'],
            ['type' => 'maxLength', 'value' => 32],
            ['type' => 'email'],
        ]])])->fields()[0];

        self::assertTrue($view->required());
        self::assertSame(['maxLength' => 32, 'email' => true], $view->rules());
        self::assertCount(3, $view->validation());
    }

    public function test_responsive_span_falls_back_to_24(): void
    {
        $schema = $this->compile([
            $this->node('a', ['layout' => ['span' => ['xs' => 24, 'md' => 12]]]),
            $this->node('b', ['layout' => ['span' => 8, 'group' => '基础']]),
        ]);

        self::assertSame(24, $schema->field('a')?->span());
        self::assertSame(8, $schema->field('b')?->span());
        self::assertSame('基础', $schema->field('b')?->group());
    }

    public function test_field_lookup(): void
    {
        $schema = $this->compile([$this->node('a'), $this->node('b')]);

        self::assertSame('b', $schema->field('b')?->name());
        self::assertNull($schema->field('missing'));
    }

    public function test_node_returns_compiled_node(): void
    {
        $schema = $this->compile([$this->node('a', ['database' => ['columnType' => 'varchar(32)', 'nullable' => false]])]);

        self::assertSame($schema->document()['nodes'][0], $schema->field('a')?->node());
        self::assertFalse($schema->field('a')?->nullable());
        self::assertSame('varchar(32)', $schema->field('a')?->columnType());
    }

    public function test_sensitive_uses_strict_truthy_rule(): void
    {
        $view = static fn (array $node): \app\common\form\schema\FormFieldView => \app\common\form\schema\FormFieldView::fromNode($node + ['field' => 'x']);
        self::assertTrue($view(['type' => 'password'])->sensitive());
        foreach ([true, 1, '1'] as $value) {
            self::assertTrue($view(['type' => 'input', 'props' => ['sensitive' => $value]])->sensitive());
            self::assertTrue($view(['type' => 'input', 'props' => ['writeOnly' => $value]])->sensitive());
        }
        foreach ([false, 0, '0', '', 'false', 'true', 'yes', 'on', null] as $value) {
            self::assertFalse($view(['type' => 'input', 'props' => ['sensitive' => $value, 'writeOnly' => $value]])->sensitive(), var_export($value, true));
        }
    }

    private function node(string $field, array $extra = []): array
    {
        return ['id' => 'node_' . $field, 'kind' => 'field', 'type' => 'input', 'field' => $field, 'title' => '标题 ' . $field] + $extra;
    }

    private function compile(array $nodes): FormSchema
    {
        return (new FormSchemaRepository())->compile([
            'schemaVersion' => 2, 'key' => 'field_view_demo', 'title' => '字段视图',
            'database' => ['connection' => 'mysql', 'table' => 'fun_field_view_demo', 'source' => 'created'],
            'nodes' => $nodes,
        ]);
    }
}
