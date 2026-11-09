<?php

declare(strict_types=1);

namespace Tests\Console;

use app\admin\development\service\BusinessDevelopmentService;
use app\admin\development\service\FormCrudDefinitionFactory;
use app\common\crud\ConfirmationToken;
use app\common\crud\CrudDefinition;
use app\common\crud\CrudGenerator;
use app\common\crud\DefinitionValidator;
use app\common\crud\ProductionTemplateContext;
use app\common\form\schema\FormSchemaCompiler;
use app\common\form\schema\FormSchemaValidator;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use support\Context;

#[\PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses]
#[\PHPUnit\Framework\Attributes\PreserveGlobalState(false)]
final class CodeGeneratorFormSizeTest extends TestCase
{
    /** 未配置表单尺寸时与 CodeGeneratorRelationsTest::PLAIN_BASELINE 一致的 core 基准。 */
    private const PLAIN_DEFINITION_HASH = '3bd6f00fb003a0aa5c571b99dc722023d2b0f2577d066103c16899be69e69cae';
    private const PLAIN_FORM_HASH = '9957f5c04f51f87b1458c9968f410176d0fe45c2ba2e26cb7f79936a57c1c5a9';
    private const PLAIN_VIEW_HASH = 'f1c0628836ba60c6d4c457d8d19078b92714dc3f7e1eab924b8b0a4e4ff861f6';
    private const FORM_PATH = 'web/src/admin/views/business/shop-plain/components/ShopPlainForm.vue';
    private const VIEW_PATH = 'web/src/admin/views/business/shop-plain/index.vue';

    private string $root = '';

    protected function tearDown(): void
    {
        if ($this->root !== '' && is_dir($this->root)) {
            $files = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($this->root, \FilesystemIterator::SKIP_DOTS), \RecursiveIteratorIterator::CHILD_FIRST);
            foreach ($files as $file) $file->isDir() && !$file->isLink() ? rmdir($file->getPathname()) : unlink($file->getPathname());
            rmdir($this->root);
        }
        Context::destroy();
    }

    public function test_unset_size_keeps_generated_view_identical(): void
    {
        // 未设置、显式 null、显式 false 均视为未配置：不写入 features，生成文件逐字节不变。
        foreach ([[], ['formWidth' => null, 'formFullscreen' => null], ['formWidth' => '', 'formFullscreen' => false]] as $config) {
            $definition = $this->definition($config);
            $features = $definition->toArray()['features'];
            self::assertArrayNotHasKey('formWidth', $features);
            self::assertArrayNotHasKey('formFullscreen', $features);
            self::assertSame(self::PLAIN_DEFINITION_HASH, $definition->hash());
            $files = array_column($this->generator()->plan($definition)['files'], 'hash', 'path');
            self::assertSame(self::PLAIN_FORM_HASH, $files[self::FORM_PATH]);
            self::assertSame(self::PLAIN_VIEW_HASH, $files[self::VIEW_PATH]);
        }
    }

    public function test_form_width_and_fullscreen_reach_dialog_and_drawer(): void
    {
        $dialog = $this->definition(['formWidth' => 800, 'formFullscreen' => true]);
        self::assertSame(800, $dialog->toArray()['features']['formWidth']);
        self::assertTrue($dialog->toArray()['features']['formFullscreen']);
        $form = $this->generator()->plan($dialog)['files'];
        $content = array_column($form, 'content', 'path')[self::FORM_PATH];
        self::assertStringContainsString('<el-dialog v-model="visible"', $content);
        self::assertStringContainsString('width="800px" :fullscreen="true">', $content);
        self::assertStringNotContainsString('720px', $content);

        $widthOnly = ProductionTemplateContext::build($this->definition(['formWidth' => 960]))['formContent'];
        self::assertStringContainsString('width="960px">', $widthOnly);
        self::assertStringNotContainsString(':fullscreen', $widthOnly);

        $fullscreenOnly = ProductionTemplateContext::build($this->definition(['formFullscreen' => true]))['formContent'];
        self::assertStringContainsString('width="720px" :fullscreen="true">', $fullscreenOnly);

        // 字符串数字来自发布设置表单时按整数处理。
        self::assertSame(800, $this->definition(['formWidth' => '800'])->toArray()['features']['formWidth']);

        $drawer = ProductionTemplateContext::build($this->definition(['formMode' => 'drawer', 'formWidth' => 800]))['formContent'];
        self::assertStringContainsString('<el-drawer v-model="visible"', $drawer);
        self::assertStringContainsString('size="800px">', $drawer);
        self::assertStringNotContainsString('width=', $drawer);

        $drawerFull = ProductionTemplateContext::build($this->definition(['formMode' => 'drawer', 'formWidth' => 800, 'formFullscreen' => true]))['formContent'];
        self::assertStringContainsString('size="100%">', $drawerFull);
        self::assertStringNotContainsString(':fullscreen', $drawerFull);

        // 未设置宽度的 drawer 同样用 size，默认 720px。
        $drawerPlain = ProductionTemplateContext::build($this->definition(['formMode' => 'drawer']))['formContent'];
        self::assertStringContainsString('size="720px">', $drawerPlain);
        self::assertStringNotContainsString('width=', $drawerPlain);

        // 存储在表单记录上的发布设置同样生效。
        $stored = (new FormCrudDefinitionFactory())->createFromSchema($this->schema(), ['publish_config' => ['formWidth' => 1200]]);
        self::assertSame(1200, $stored->toArray()['features']['formWidth']);
    }

    public function test_form_width_out_of_range_is_rejected(): void
    {
        foreach ([360, 1600] as $width) {
            (new DefinitionValidator())->validate($this->withFeatures(['formWidth' => $width]), $this->root());
        }
        foreach ([359, 1601, '800', 800.0, true] as $width) {
            try {
                (new DefinitionValidator())->validate($this->withFeatures(['formWidth' => $width]), $this->root());
                self::fail('formWidth 越界未被拒绝：' . var_export($width, true));
            } catch (InvalidArgumentException $exception) {
                self::assertStringContainsString('formWidth', $exception->getMessage());
            }
        }
        foreach ([359, 1601] as $width) {
            try {
                $this->generator()->plan($this->definition(['formWidth' => $width]));
                self::fail('生成未拒绝越界 formWidth：' . $width);
            } catch (InvalidArgumentException $exception) {
                self::assertStringContainsString('formWidth', $exception->getMessage());
            }
        }
        foreach ([1, 'true', null] as $fullscreen) {
            try {
                (new DefinitionValidator())->validate($this->withFeatures(['formFullscreen' => $fullscreen]), $this->root());
                self::fail('formFullscreen 非布尔未被拒绝');
            } catch (InvalidArgumentException $exception) {
                self::assertStringContainsString('formFullscreen', $exception->getMessage());
            }
        }
        (new DefinitionValidator())->validate($this->withFeatures(['formFullscreen' => false]), $this->root());
        $this->addToAssertionCount(1);
    }

    public function test_publish_config_accepts_new_keys(): void
    {
        $service = (new \ReflectionClass(BusinessDevelopmentService::class))->newInstanceWithoutConstructor();
        // 白名单校验发生在读取模块之前：新键通过白名单后才会因模块 id 非法而失败。
        try {
            $service->savePublishConfig(0, ['formWidth' => 800, 'formFullscreen' => true]);
            self::fail('非法模块 id 应失败');
        } catch (\Throwable $exception) {
            self::assertStringNotContainsString('未知字段', $exception->getMessage());
        }
        try {
            $service->savePublishConfig(0, ['formHeight' => 800]);
            self::fail('未知字段应被拒绝');
        } catch (InvalidArgumentException $exception) {
            self::assertStringContainsString('未知字段：formHeight', $exception->getMessage());
        }
    }

    private function withFeatures(array $features): CrudDefinition
    {
        $data = CrudDefinitionFixture::make()->toArray();
        $data['features'] = array_replace($data['features'], $features);
        return CrudDefinition::fromArray($data);
    }

    private function definition(array $config): CrudDefinition
    {
        return (new FormCrudDefinitionFactory())->createFromSchema($this->schema(), [], $config);
    }

    private function schema(): \app\common\form\schema\FormSchema
    {
        return (new FormSchemaCompiler(new FormSchemaValidator()))->compile([
            'schemaVersion' => 2, 'key' => 'shop_plain', 'title' => '无关联模块',
            'database' => ['connection' => 'mysql', 'table' => 'fun_shop_plain', 'source' => 'created'],
            'nodes' => [
                $this->node('title', 'input', 'varchar(120)', ['list' => ['show' => true, 'filter' => 'like']]),
                $this->node('stock', 'number', 'int'),
                $this->node('remark', 'textarea', 'text'),
            ],
        ]);
    }

    private function node(string $field, string $type, string $column, array $extra = []): array
    {
        return array_replace(['id' => $field . '_node', 'kind' => 'field', 'type' => $type, 'field' => $field, 'title' => $field,
            'database' => ['columnType' => $column, 'nullable' => true], 'list' => ['show' => true], 'children' => []], $extra);
    }

    private function root(): string
    {
        if ($this->root === '') {
            $this->root = sys_get_temp_dir() . '/funadmin-form-size-' . bin2hex(random_bytes(8));
            mkdir($this->root, 0700, true);
        }
        return $this->root;
    }

    private function generator(): CrudGenerator
    {
        return new CrudGenerator($this->root(), tokens: new ConfirmationToken($this->root(), 'form-size-test-secret'));
    }
}
