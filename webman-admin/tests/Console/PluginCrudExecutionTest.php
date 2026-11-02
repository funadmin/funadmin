<?php

declare(strict_types=1);

namespace Tests\Console;

use app\admin\plugin\command\PluginCrudGenerate;
use app\admin\plugin\command\PluginCrudPreview;
use app\common\crud\CrudDefinition;
use app\common\crud\CrudGenerator;
use app\common\plugin\sdk\Manifest;
use app\common\plugin\sdk\PluginScaffolder;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Tester\CommandTester;

final class PluginCrudExecutionTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        $this->root = sys_get_temp_dir() . '/funadmin-plugin-crud-' . bin2hex(random_bytes(8));
        mkdir($this->root, 0700, true);
        (new PluginScaffolder($this->root . '/plugins'))->scaffold('crudfixture');
    }

    protected function tearDown(): void
    {
        $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($this->root, \FilesystemIterator::SKIP_DOTS), \RecursiveIteratorIterator::CHILD_FIRST);
        foreach ($iterator as $file) $file->isDir() && !$file->isLink() ? rmdir($file->getPathname()) : unlink($file->getPathname());
        rmdir($this->root);
    }

    private function definition(): CrudDefinition
    {
        $data = CrudDefinitionFixture::make('console-item')->toArray();
        $data['module'] = 'crudfixture';
        $data['target'] = ['type' => 'plugin', 'plugin' => 'crudfixture', 'scope' => 'both'];
        $data['permissionPrefix'] = 'crudfixture:console-item';
        $data['apiPrefix'] = '/console-item';
        $data['routePath'] = '/plugin/crudfixture/console-item';
        $data['tableIdentity'] = ['source' => 'created', 'kind' => 'physical'];
        unset($data['generationTargets']);
        return CrudDefinition::fromArray($data);
    }

    public function test_preview_generate_cli_enforces_token_permissions_and_replay(): void
    {
        self::assertSame(PluginCrudPreview::class, (new \ReflectionMethod(PluginCrudPreview::class, '__construct'))->getDeclaringClass()->getName());
        self::assertSame(PluginCrudGenerate::class, (new \ReflectionMethod(PluginCrudGenerate::class, '__construct'))->getDeclaringClass()->getName());
        file_put_contents($this->root . '/definition.json', json_encode($this->definition()->toArray(), JSON_THROW_ON_ERROR));
        $preview = new CommandTester(new PluginCrudPreview($this->root));
        self::assertSame(1, $preview->execute(['definition' => $this->root . '/missing.json']));
        $manifest = file_get_contents($this->root . '/plugins/crudfixture/plugin.json');
        self::assertSame(0, $preview->execute(['definition' => $this->root . '/definition.json', '--token-output' => $this->root . '/token']), $preview->getDisplay());
        self::assertSame(0600, fileperms($this->root . '/token') & 0777);
        self::assertSame($manifest, file_get_contents($this->root . '/plugins/crudfixture/plugin.json'));
        self::assertFileDoesNotExist($this->root . '/plugins/crudfixture/app/admin/model/ConsoleItem.php');
        $generate = new CommandTester(new PluginCrudGenerate($this->root));
        $args = ['definition' => $this->root . '/definition.json', '--confirm-token-file' => $this->root . '/token'];
        chmod($this->root . '/token', 0644);
        self::assertSame(1, $generate->execute($args));
        chmod($this->root . '/token', 0600);
        self::assertSame(0, $generate->execute($args), $generate->getDisplay());
        self::assertFileExists($this->root . '/plugins/crudfixture/app/admin/model/ConsoleItem.php');
        self::assertSame(1, $generate->execute($args));
        self::assertSame('crudfixture', Manifest::fromDirectory($this->root . '/plugins/crudfixture')->code());
    }

    public function test_application_controllers_register_native_member_auth_without_changing_plugin_urls(): void
    {
        $definition = $this->definition();
        $context = \app\common\crud\PluginTemplateContext::build($definition, 'crudfixture', false);
        self::assertStringContainsString('#[Middleware(MApi::class)]', $context['controllerContent']);
        self::assertStringContainsString("#[Group('/crudfixture/console-item')]", $context['controllerContent']);
        $data = $definition->toArray();
        $data['memberApi'] = ['enabled' => true, 'ownerField' => 'member_id'];
        $data['fields'][] = ['name' => 'member_id', 'dbType' => 'bigint', 'nullable' => false, 'component' => 'input'];
        $context = \app\common\crud\PluginTemplateContext::build(CrudDefinition::fromArray($data), 'crudfixture', false);
        self::assertStringContainsString("#[Group('/crudfixture/console-item')]", $context['memberControllerContent']);
        self::assertStringContainsString('#[Middleware(MApi::class)]', $context['memberControllerContent']);
        foreach (['modelContent', 'serviceContent', 'validateContent', 'memberControllerContent'] as $artifact) {
            $file = $this->root . '/' . $artifact . '.php';
            file_put_contents($file, $context[$artifact]); require $file;
        }
        $class = new \ReflectionClass('app\\crudfixture\\controller\\ConsoleItemController');
        self::assertCount(1, $class->getAttributes(\support\annotation\Middleware::class));
        self::assertSame('/crudfixture/console-item', $class->getAttributes(\support\annotation\route\RouteGroup::class)[0]->newInstance()->prefix);
    }

    public function test_both_scopes_generate_native_php_and_history_is_immutable(): void
    {
        $definition = $this->definition();
        $generator = new CrudGenerator($this->root);
        $plan = $generator->plan($definition);
        $result = $generator->generate($definition, $plan['confirmToken']);
        self::assertSame('written', $result['write']['status']);
        foreach ($plan['files'] as $file) {
            if (!str_ends_with($file['path'], '.php')) continue;
            $source = file_get_contents($this->root . '/' . $file['path']);
            self::assertStringNotContainsString('think\\', $source, $file['path']);
            token_get_all($source, TOKEN_PARSE);
        }
        $migration = $this->root . '/plugins/crudfixture/database/migrations/001_create_console_item.sql';
        $original = file_get_contents($migration);
        self::assertStringContainsString('`created_at`', $original);
        self::assertStringContainsString('`updated_at`', $original);
        $nextPlan = $generator->plan($definition);
        self::assertSame($original, file_get_contents($migration));
        $paths = array_column($nextPlan['files'], 'path');
        self::assertContains('plugins/crudfixture/database/migrations/001_create_console_item.sql', $paths);
        self::assertFileDoesNotExist($this->root . '/plugins/crudfixture/database/migrations/002_alter_console_item.sql');
    }
}
