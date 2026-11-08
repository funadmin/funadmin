<?php

declare(strict_types=1);

namespace Tests\Console;

use app\admin\development\service\FormCrudDefinitionFactory;
use app\common\crud\ProductionTemplateContext;
use app\common\form\schema\FormSchemaCompiler;
use app\common\form\schema\FormSchemaValidator;
use app\common\service\translation\GeneratedLanguagePacks;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Translation\Loader\PhpFileLoader;
use Symfony\Component\Translation\Translator;

/** 生成模块的后端消息：占位符按 Symfony 规则替换，英文为正式文案而非键名占位。 */
final class GeneratedBackendMessagesTest extends TestCase
{
    private string $root = '';

    protected function tearDown(): void
    {
        if ($this->root === '') return;
        foreach (glob($this->root . '/*/*.php') ?: [] as $file) unlink($file);
        foreach (glob($this->root . '/*', GLOB_ONLYDIR) ?: [] as $dir) rmdir($dir);
        rmdir($this->root);
    }

    /** @return array{0: array<string, mixed>, 1: string} 生成上下文与实体名 */
    private function context(): array
    {
        $schema = (new FormSchemaCompiler(new FormSchemaValidator()))->compile([
            'schemaVersion' => 2, 'key' => 'msg_probe', 'title' => '消息探针',
            'database' => ['connection' => 'mysql', 'table' => 'fun_msg_probe', 'source' => 'created'],
            'nodes' => [
                ['id' => 'title_node', 'kind' => 'field', 'type' => 'input', 'field' => 'title', 'title' => '标题', 'database' => ['columnType' => 'varchar(120)', 'nullable' => true], 'list' => ['show' => true], 'children' => []],
                ['id' => 'dept_node', 'kind' => 'field', 'type' => 'input', 'field' => 'dept_id', 'title' => '部门', 'database' => ['columnType' => 'bigint', 'nullable' => true], 'list' => ['show' => true], 'children' => []],
            ],
        ]);
        $definition = (new FormCrudDefinitionFactory())->createFromSchema($schema, [], ['dataScopeEnabled' => true, 'dataScopeField' => 'dept_id']);
        return [ProductionTemplateContext::build($definition), (string) $definition->toArray()['entity']];
    }

    private function translator(array $context, string $entity): Translator
    {
        $this->root = sys_get_temp_dir() . '/funadmin-msg-' . bin2hex(random_bytes(6));
        foreach (['zh-cn' => 'langZhContent', 'en-us' => 'langEnContent'] as $locale => $key) {
            mkdir($this->root . '/' . $locale, 0700, true);
            file_put_contents($this->root . '/' . $locale . '/' . $entity . '.php', $context[$key]);
        }
        $translator = new Translator('zh-cn');
        $translator->addLoader('phpfile', new PhpFileLoader());
        GeneratedLanguagePacks::register($translator, [$this->root]);
        return $translator;
    }

    public function test_placeholders_are_replaced_in_both_languages(): void
    {
        [$context, $entity] = $this->context();
        $translator = $this->translator($context, $entity);
        foreach (['zh-cn', 'en-us'] as $locale) {
            $referenced = $translator->trans("$entity.referenced", ['{target}' => 'fun_order_item.order_id'], 'messages', $locale);
            self::assertStringContainsString('fun_order_item.order_id', $referenced, $locale);
            self::assertStringNotContainsString('{', $referenced, $locale);
            $required = $translator->trans("$entity.dataScopeRequired", ['{field}' => 'dept_id'], 'messages', $locale);
            self::assertStringContainsString('dept_id', $required, $locale);
            self::assertStringNotContainsString('{', $required, $locale);
        }
    }

    public function test_generated_code_passes_symfony_style_parameters(): void
    {
        [$context] = $this->context();
        $code = implode("\n", array_filter($context, 'is_string'));
        self::assertStringContainsString("['{target}' =>", $code);
        self::assertStringContainsString("['{field}' =>", $code);
        self::assertStringNotContainsString('{:', $code, '不得再生成 ThinkPHP 风格占位符');
    }

    public function test_english_messages_are_real_sentences_not_key_placeholders(): void
    {
        [$context, $entity] = $this->context();
        $english = (require $this->writeTemp($context['langEnContent']))[$entity];
        self::assertNotEmpty($english);
        foreach ($english as $purpose => $message) {
            self::assertGreaterThanOrEqual(3, str_word_count((string) preg_replace('/\{[a-z]+\}/', 'x', $message)), "$purpose: $message");
        }
        self::assertStringContainsString('{target}', $english['referenced']);
        self::assertStringContainsString('{field}', $english['dataScopeRequired']);
    }

    private function writeTemp(string $content): string
    {
        $this->root = $this->root !== '' ? $this->root : sys_get_temp_dir() . '/funadmin-msg-' . bin2hex(random_bytes(6));
        if (!is_dir($this->root . '/en-us')) mkdir($this->root . '/en-us', 0700, true);
        $file = $this->root . '/en-us/probe.php';
        file_put_contents($file, $content);
        return $file;
    }
}
