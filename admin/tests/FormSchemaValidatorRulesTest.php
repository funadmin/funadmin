<?php

declare(strict_types=1);

namespace Tests;

use app\common\form\schema\FormSchemaException;
use app\common\form\schema\FormSchemaValidator;
use PHPUnit\Framework\TestCase;

/** FormSchemaValidator 的节点 kind/type 一致性与标题长度约束。 */
final class FormSchemaValidatorRulesTest extends TestCase
{
    public function test_field_kind_with_layout_type_is_rejected(): void
    {
        $this->assertRejected([$this->node(['kind' => 'field', 'type' => 'divider', 'field' => 'x'])], '/nodes/0/kind');
    }

    public function test_missing_kind_with_layout_type_is_rejected(): void
    {
        $node = $this->node(['type' => 'group', 'field' => 'x']);
        unset($node['kind']);
        $this->assertRejected([$node], '/nodes/0/kind');
    }

    public function test_layout_kind_with_field_type_is_rejected(): void
    {
        $this->assertRejected([$this->node(['kind' => 'layout', 'type' => 'input', 'field' => null])], '/nodes/0/kind');
    }

    public function test_matching_kinds_are_accepted(): void
    {
        (new FormSchemaValidator())->validate($this->schema([
            $this->node(['id' => 'g', 'kind' => 'layout', 'type' => 'group', 'field' => null, 'children' => [$this->node(['id' => 'f'])]]),
        ]));
        self::assertTrue(true);
    }

    public function test_title_longer_than_100_characters_is_rejected(): void
    {
        try {
            (new FormSchemaValidator())->validate(['title' => str_repeat('标', 101)] + $this->schema([$this->node()]));
            self::fail('expected FormSchemaException');
        } catch (FormSchemaException $exception) {
            self::assertSame('/title', $exception->schemaPath());
        }
        (new FormSchemaValidator())->validate(['title' => str_repeat('标', 100)] + $this->schema([$this->node()]));
    }

    private function assertRejected(array $nodes, string $path): void
    {
        try {
            (new FormSchemaValidator())->validate($this->schema($nodes));
            self::fail('expected FormSchemaException');
        } catch (FormSchemaException $exception) {
            self::assertSame($path, $exception->schemaPath());
        }
    }

    private function schema(array $nodes): array
    {
        return ['schemaVersion' => 2, 'key' => 'rules_form', 'title' => '规则', 'nodes' => $nodes];
    }

    private function node(array $extra = []): array
    {
        return array_replace(['id' => 'n1', 'kind' => 'field', 'type' => 'input', 'field' => 'title', 'title' => '标题',
            'database' => ['columnType' => 'varchar(60)', 'nullable' => true], 'children' => []], $extra);
    }
}
