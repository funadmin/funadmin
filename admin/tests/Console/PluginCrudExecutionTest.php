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
        $data['routePath'] = '/plugins/crudfixture/console-item';
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
        self::assertFileDoesNotExist($this->root . '/plugins/crudfixture/app/admin/plugins/crudfixture/model/ConsoleItem.php');
        $generate = new CommandTester(new PluginCrudGenerate($this->root));
        $args = ['definition' => $this->root . '/definition.json', '--confirm-token-file' => $this->root . '/token'];
        chmod($this->root . '/token', 0644);
        self::assertSame(1, $generate->execute($args));
        chmod($this->root . '/token', 0600);
        self::assertSame(0, $generate->execute($args), $generate->getDisplay());
        self::assertFileExists($this->root . '/plugins/crudfixture/app/admin/plugins/crudfixture/model/ConsoleItem.php');
        self::assertSame(1, $generate->execute($args));
        self::assertSame('crudfixture', Manifest::fromDirectory($this->root . '/plugins/crudfixture')->code());
    }

    public function test_application_controllers_register_native_member_auth_without_changing_plugin_urls(): void
    {
        $definition = $this->definition();
        $context = \app\common\crud\PluginTemplateContext::build($definition, 'crudfixture', false);
        self::assertStringContainsString('extends ApiController', $context['controllerContent']);
        self::assertStringNotContainsString('#[Middleware(ApiMiddleware::class)]', $context['controllerContent']);
        self::assertStringContainsString("#[Group('/crudfixture/console-item')]", $context['controllerContent']);
        $data = $definition->toArray();
        $data['memberApi'] = ['enabled' => true, 'ownerField' => 'member_id'];
        $data['fields'][] = ['name' => 'member_id', 'dbType' => 'bigint', 'nullable' => false, 'component' => 'input'];
        $context = \app\common\crud\PluginTemplateContext::build(CrudDefinition::fromArray($data), 'crudfixture', false);
        self::assertStringContainsString("#[Group('/crudfixture/console-item')]", $context['memberControllerContent']);
        self::assertStringContainsString('extends ApiController', $context['memberControllerContent']);
        self::assertStringNotContainsString('#[Middleware(ApiMiddleware::class)]', $context['memberControllerContent']);
        foreach (['modelContent', 'serviceContent', 'validateContent', 'memberControllerContent'] as $artifact) {
            $file = $this->root . '/' . $artifact . '.php';
            file_put_contents($file, $context[$artifact]); require $file;
        }
        $class = new \ReflectionClass('app\\crudfixture\\controller\\ConsoleItemController');
        self::assertTrue($class->isSubclassOf(\app\common\controller\ApiController::class), '会员鉴权由 ApiController 统一提供');
        self::assertSame([], $class->getAttributes(\support\annotation\Middleware::class));
        self::assertSame('/crudfixture/console-item', $class->getAttributes(\support\annotation\route\RouteGroup::class)[0]->newInstance()->prefix);
    }

    public function test_admin_crud_binds_generated_actions_to_manifest_permissions(): void
    {
        $data = $this->definition()->toArray();
        $data['permission'] = ['enabled' => true];
        $definition = CrudDefinition::fromArray($data);
        $context = \app\common\crud\PluginTemplateContext::build($definition, 'crudfixture', true);
        foreach (['modelContent', 'serviceContent', 'validateContent', 'controllerContent'] as $artifact) {
            $path = $this->root . '/' . $artifact . '.php';
            file_put_contents($path, $context[$artifact]);
            require $path;
        }
        self::assertTrue(class_exists('app\\admin\\plugins\\crudfixture\\controller\\ConsoleItemController'), '插件 CRUD 必须使用模块命名空间');
        $class = new \ReflectionClass('app\\admin\\plugins\\crudfixture\\controller\\ConsoleItemController');
        self::assertSame('/admin/plugins/crudfixture/console-item', $class->getAttributes(\support\annotation\route\RouteGroup::class)[0]->newInstance()->prefix);
        // 代码即权限：生成的插件方法不再声明权限注解；清单与前端使用的权限码等于控制器方法推导码。
        $declared = array_map(static fn (array $action): string => 'crudfixture:console-item:' . $action['codeSuffix'], $definition->toArray()['permission']['actions']);
        foreach (['index', 'create', 'update', 'remove', 'recycle', 'restoreMany', 'destroyMany'] as $method) {
            self::assertSame([], $class->getMethod($method)->getAttributes(\app\common\annotation\Permission::class), $method);
            $code = \app\admin\authorization\service\PermissionCode::derive($class->getName(), $method);
            self::assertContains($code, $declared, $method);
        }
        self::assertStringNotContainsString('annotation\\Permission', $context['controllerContent']);
        $definitions = (new \ReflectionMethod(\Webman\Route::class, 'buildAnnotationRouteDefinitions'))->invoke(null, [new \Webman\Finder\FileInfo($this->root . '/controllerContent.php', ['class' => $class->getName()])]);
        self::assertContains('/admin/plugins/crudfixture/console-item/restore', array_column($definitions, 'path'));
        $dispatcher = \FastRoute\simpleDispatcher(static function (\FastRoute\RouteCollector $collector) use ($definitions): void {
            foreach ($definitions as $route) $collector->addRoute($route['methods'], $route['path'], $route['callback']);
        });
        self::assertSame('destroyMany', $dispatcher->dispatch('DELETE', '/admin/plugins/crudfixture/console-item/destroy')[1][1]);
        self::assertSame('export', $dispatcher->dispatch('GET', '/admin/plugins/crudfixture/console-item/export')[1][1]);
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
