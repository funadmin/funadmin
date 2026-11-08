<?php

declare(strict_types=1);

namespace Tests\Console;

use app\common\crud\ProductionTemplateContext;
use PHPUnit\Framework\TestCase;

final class CrudGeneratedValidatorTest extends TestCase
{
    public function test_generated_validator_executes_webman_rules(): void
    {
        $context = ProductionTemplateContext::build(CrudDefinitionFixture::make('console-validator'));
        self::assertStringNotContainsString('think\\', $context['validateContent']);
        $file = tempnam(sys_get_temp_dir(), 'funadmin-validator-');
        try {
            file_put_contents($file, $context['validateContent']);
            require $file;
            $validator = new \app\admin\business\console_validator\validate\ConsoleValidatorValidate();
            self::assertFalse($validator->check(['title' => '']));
            self::assertNotSame('', $validator->getError());
            self::assertTrue($validator->check(['title' => '合法标题']));
            self::assertFalse($validator->check(['title' => str_repeat('x', 81)]));
            self::assertTrue($validator->forUpdate(1, ['title' => '更新'])->check(['title' => '更新']));
        } finally { unlink($file); }
    }

    public function test_generated_validator_compiles_declaration_rules_to_native_arrays(): void
    {
        $data = CrudDefinitionFixture::make('console-native-rules')->toArray();
        $data['fields'][1]['rules'] = ['require', 'alphaNum', 'length:3,8', 'regex:/^(abc|xyz)[0-9]+$/'];
        $context = ProductionTemplateContext::build(\app\common\crud\CrudDefinition::fromArray($data));
        $file = tempnam(sys_get_temp_dir(), 'funadmin-rules-');
        try {
            file_put_contents($file, $context['validateContent']); require $file;
            $validator = new \app\admin\business\console_native_rules\validate\ConsoleNativeRulesValidate();
            self::assertTrue($validator->check(['title' => 'abc12']));
            self::assertTrue($validator->check(['title' => 'xyz9']));
            self::assertFalse($validator->check(['title' => 'abc123456789']));
            self::assertFalse($validator->check(['title' => 'bad-value']));
            self::assertFalse($validator->check(['title' => '']));
        } finally { unlink($file); }
    }

    public function test_no_writable_fields_still_produces_executable_validation(): void
    {
        $data = CrudDefinitionFixture::make('console-readonly-rules')->toArray();
        foreach ($data['fields'] as &$field) $field['writable'] = false;
        unset($field);
        $context = ProductionTemplateContext::build(\app\common\crud\CrudDefinition::fromArray($data));
        $file = tempnam(sys_get_temp_dir(), 'funadmin-readonly-rules-');
        try {
            file_put_contents($file, $context['validateContent']); require $file;
            self::assertTrue((new \app\admin\business\console_readonly_rules\validate\ConsoleReadonlyRulesValidate())->check([]));
        } finally { unlink($file); }
    }

    public function test_unsupported_rule_is_rejected_before_generating_unexecutable_code(): void
    {
        $data = CrudDefinitionFixture::make('console-invalid-rules')->toArray();
        $data['fields'][1]['rules'] = ['unknownLegacyRule:argument'];
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('不支持的校验规则');
        ProductionTemplateContext::build(\app\common\crud\CrudDefinition::fromArray($data));
    }
}
