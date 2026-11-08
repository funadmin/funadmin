<?php

declare(strict_types=1);

namespace Tests;

use app\common\form\action\FormActionRegistry;
use app\common\form\component\PluginFormComponentRegistry;
use app\common\form\dataSource\FormDataSourceRegistry;
use app\common\form\dependency\FormSchemaDependencyChecker;
use app\common\form\schema\FormSchemaCompiler;
use app\common\form\schema\FormSchemaException;
use app\common\form\schema\FormSchemaValidator;
use app\common\form\validation\FormAsyncValidatorRegistry;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class FormSchemaCustomActionsTest extends TestCase
{
    public function test_empty_or_missing_custom_actions_do_not_change_hash(): void
    {
        $missing = $this->compile($this->document());
        $empty = $this->compile($this->document(['customActions' => []]));

        self::assertSame($missing->hash(), $empty->hash());
        self::assertSame($missing->canonicalJson(), $empty->canonicalJson());
        self::assertArrayNotHasKey('customActions', $empty->document()['list']);
    }

    public function test_valid_custom_actions_are_kept_in_canonical_document(): void
    {
        $compiled = $this->compile($this->document(['customActions' => self::actions()]));

        $keys = array_column($compiled->document()['list']['customActions'], 'key');
        self::assertSame(['approve', 'mark_paid'], $keys);
        self::assertSame(['name' => 'id', 'type' => 'integer'], $compiled->document()['list']['customActions'][0]['parameters'][0]);
        self::assertNotSame($this->compile($this->document())->hash(), $compiled->hash());
    }

    public static function invalidActions(): array
    {
        $base = self::actions()[0];
        $with = static fn (array $patch): array => [array_replace($base, $patch)];
        return [
            'uppercase key' => [$with(['key' => 'Approve']), '/list/customActions/0/key'],
            'digit first key' => [$with(['key' => '1approve']), '/list/customActions/0/key'],
            'hyphen key' => [$with(['key' => 'mark-paid']), '/list/customActions/0/key'],
            'key too long' => [$with(['key' => 'a' . str_repeat('b', 41)]), '/list/customActions/0/key'],
            'duplicate key' => [[$base, $base], '/list/customActions/1/key'],
            'method collision' => [[array_replace($base, ['key' => 'mark_paid']), array_replace($base, ['key' => 'markpaid'])], '/list/customActions/1/key'],
            'empty label' => [$with(['label' => ' ']), '/list/customActions/0/label'],
            'label too long' => [$with(['label' => str_repeat('名', 51)]), '/list/customActions/0/label'],
            'empty locations' => [$with(['locations' => []]), '/list/customActions/0/locations'],
            'unknown location' => [$with(['locations' => ['categoryNode']]), '/list/customActions/0/locations'],
            'duplicate location' => [$with(['locations' => ['row', 'row']]), '/list/customActions/0/locations'],
            'batch without toolbar' => [$with(['batch' => true]), '/list/customActions/0/batch'],
            'toolbar without batch' => [$with(['locations' => ['toolbar'], 'batch' => false]), '/list/customActions/0/batch'],
            'row and toolbar without batch' => [$with(['locations' => ['row', 'toolbar'], 'batch' => false]), '/list/customActions/0/batch'],
            'batch not boolean' => [$with(['batch' => 1]), '/list/customActions/0/batch'],
            'confirmation not boolean' => [$with(['requiresConfirmation' => 'yes']), '/list/customActions/0/requiresConfirmation'],
            'unknown effect' => [$with(['effect' => 'delete']), '/list/customActions/0/effect'],
            'too many parameters' => [$with(['parameters' => array_map(static fn (int $i): array => ['name' => 'p' . $i, 'type' => 'string'], range(1, 6))]), '/list/customActions/0/parameters'],
            'duplicate parameter' => [$with(['parameters' => [['name' => 'id', 'type' => 'integer'], ['name' => 'id', 'type' => 'string']]]), '/list/customActions/0/parameters/1/name'],
            'invalid parameter name' => [$with(['parameters' => [['name' => 'Bad-Name', 'type' => 'string']]]), '/list/customActions/0/parameters/0/name'],
            'ids parameter type' => [$with(['parameters' => [['name' => 'ids', 'type' => 'ids']]]), '/list/customActions/0/parameters/0/type'],
            'unknown parameter key' => [$with(['parameters' => [['name' => 'id', 'type' => 'integer', 'default' => 1]]]), '/list/customActions/0/parameters/0'],
            'unknown action key' => [$with(['handler' => 'system']), '/list/customActions/0'],
            'missing effect' => [[array_diff_key($base, ['effect' => true])], '/list/customActions/0'],
            'not a list' => [['approve' => $base], '/list/customActions'],
        ];
    }

    #[DataProvider('invalidActions')]
    public function test_invalid_custom_actions_are_rejected(array $actions, string $path): void
    {
        try {
            $this->compile($this->document(['customActions' => $actions]));
            self::fail('非法自定义动作必须被拒绝');
        } catch (FormSchemaException $error) {
            self::assertSame($path, $error->schemaPath(), $error->getMessage());
            self::assertSame('FORM_LIST_CUSTOM_ACTION_INVALID', $error->errorCode());
        }
    }

    public function test_registry_key_length_is_bounded_by_the_form_key(): void
    {
        $document = $this->document(['customActions' => [array_replace(self::actions()[0], ['key' => str_repeat('a', 41)])]]);
        $document['key'] = 'shop_' . str_repeat('o', 20);
        try {
            $this->compile($document);
            self::fail('注册键超过 64 字符必须拒绝');
        } catch (FormSchemaException $error) {
            self::assertSame('/list/customActions/0/key', $error->schemaPath());
        }
    }

    public function test_dynamic_publish_accepts_buttons_bound_to_pending_custom_actions(): void
    {
        $schema = $this->compile($this->document(['customActions' => self::actions(), 'buttons' => ['row' => [self::button()]]]))->document();

        $result = $this->checker()->check($schema, 'core-dynamic');

        self::assertSame([], $result['diagnostics']);
    }

    public function test_generated_host_validates_pending_custom_actions_against_schema_metadata(): void
    {
        $schema = $this->compile($this->document(['customActions' => self::actions(), 'buttons' => ['row' => [self::button()]]]))->document();
        $ready = $this->checker(true)->check($schema, 'core-generated');
        self::assertSame([], $ready['diagnostics']);

        $mismatch = $schema;
        $mismatch['list']['buttons']['row'][0]['permission'] = 'generated:shop-order:index';
        self::assertSame(['FORM_ACTION_PERMISSION_MISMATCH'], array_column($this->checker(true)->check($mismatch, 'core-generated')['diagnostics'], 'code'));

        $undeclared = $schema;
        $undeclared['list']['buttons']['row'][0]['params']['extra'] = ['source' => 'literal', 'value' => 1];
        self::assertContains('FORM_ACTION_PARAMETER_NOT_ALLOWED', array_column($this->checker(true)->check($undeclared, 'core-generated')['diagnostics'], 'code'));

        // 不属于本 Schema 的 generated 键仍须在注册表中存在。
        $foreign = $schema;
        $foreign['list']['buttons']['row'][0]['action']['key'] = 'generated.other.approve';
        self::assertContains('FORM_ACTION_NOT_REGISTERED', array_column($this->checker(true)->check($foreign, 'core-dynamic')['diagnostics'], 'code'));

        // 适配未就绪时与普通注册动作一样阻断正式生成。
        self::assertContains('FORM_LIST_ACTION_ADAPTER_UNAVAILABLE', array_column($this->checker(false)->check($schema, 'core-generated')['diagnostics'], 'code'));
    }

    public function test_button_not_binding_every_custom_action_parameter_gets_a_specific_diagnostic(): void
    {
        $schema = $this->compile($this->document(['customActions' => self::actions(), 'buttons' => ['row' => [self::button()]]]))->document();
        unset($schema['list']['buttons']['row'][0]['params']['note']);
        $diagnostics = $this->checker(true)->check($schema, 'core-generated')['diagnostics'];
        self::assertSame(['FORM_ACTION_PARAMETER_MISSING'], array_column($diagnostics, 'code'));
        self::assertSame('/list/buttons/row/0/params/note', $diagnostics[0]['path']);
        self::assertStringContainsString('审核', $diagnostics[0]['message']);
        self::assertStringContainsString('note', $diagnostics[0]['message']);
    }

    public static function actions(): array
    {
        return [
            ['key' => 'approve', 'label' => '审核', 'locations' => ['row'], 'batch' => false, 'requiresConfirmation' => true, 'effect' => 'write',
                'parameters' => [['name' => 'id', 'type' => 'integer'], ['name' => 'note', 'type' => 'string']]],
            ['key' => 'mark_paid', 'label' => '标记已付', 'locations' => ['toolbar'], 'batch' => true, 'requiresConfirmation' => false, 'effect' => 'read', 'parameters' => []],
        ];
    }

    private static function button(): array
    {
        return ['id' => 'approve-row', 'label' => '审核', 'permission' => 'generated:shop-order:action-approve',
            'action' => ['type' => 'registered', 'key' => 'generated.shop-order.approve', 'capabilityVersion' => '1'],
            'params' => ['id' => ['source' => 'row', 'field' => 'id'], 'note' => ['source' => 'literal', 'value' => '同意']]];
    }

    private function checker(bool $ready = false): FormSchemaDependencyChecker
    {
        return new FormSchemaDependencyChecker(new FormActionRegistry(), FormDataSourceRegistry::core([]), new FormAsyncValidatorRegistry([]),
            new PluginFormComponentRegistry(), static fn (): bool => $ready);
    }

    private function compile(array $document): \app\common\form\schema\FormSchema
    {
        return (new FormSchemaCompiler(new FormSchemaValidator()))->compile($document);
    }

    private function document(array $list = []): array
    {
        return [
            'schemaVersion' => 2, 'key' => 'shop_order', 'title' => '订单',
            'database' => ['connection' => 'mysql', 'table' => 'fun_shop_order', 'source' => 'created'],
            'nodes' => [['id' => 'title_node', 'kind' => 'field', 'type' => 'input', 'field' => 'title', 'title' => '标题',
                'database' => ['columnType' => 'varchar(255)', 'nullable' => false], 'children' => []]],
            'list' => ['tools' => ['refresh' => true]] + $list,
        ];
    }
}
