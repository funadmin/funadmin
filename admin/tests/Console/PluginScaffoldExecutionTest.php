<?php

declare(strict_types=1);

namespace Tests\Console;

use app\admin\plugin\command\PluginMake;
use app\admin\plugin\command\PluginValidate;
use app\common\plugin\model\Plugin as PluginRecord;
use app\common\plugin\sdk\Manifest;
use app\common\plugin\sdk\PluginEntryFactory;
use app\common\plugin\sdk\PluginScaffolder;
use Illuminate\Database\Schema\Blueprint;
use PHPUnit\Framework\TestCase;
use support\Container;
use support\Db;
use Symfony\Component\Console\Tester\CommandTester;

#[\PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses]
#[\PHPUnit\Framework\Attributes\PreserveGlobalState(false)]
final class PluginScaffoldExecutionTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        $this->root = sys_get_temp_dir() . '/funadmin-plugin-console-' . bin2hex(random_bytes(8));
        mkdir($this->root, 0700, true);
    }

    protected function tearDown(): void
    {
        \Webman\Config::load(config_path(), ['route']);
        $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($this->root, \FilesystemIterator::SKIP_DOTS), \RecursiveIteratorIterator::CHILD_FIRST);
        foreach ($iterator as $file) $file->isDir() && !$file->isLink() ? rmdir($file->getPathname()) : unlink($file->getPathname());
        rmdir($this->root);
    }

    public function test_make_preview_is_read_only_and_command_writes_only_to_explicit_project(): void
    {
        self::assertTrue((new \ReflectionClass(PluginMake::class))->hasMethod('__construct'));
        self::assertSame(PluginMake::class, (new \ReflectionMethod(PluginMake::class, '__construct'))->getDeclaringClass()->getName());
        $command = new CommandTester(new PluginMake($this->root));
        self::assertSame(0, $command->execute(['name' => 'consolefixture', '--preview' => true]), $command->getDisplay());
        self::assertDirectoryDoesNotExist($this->root . '/plugins');
        self::assertSame(0, $command->execute(['name' => 'consolefixture']), $command->getDisplay());
        self::assertFileExists($this->root . '/plugins/consolefixture/plugin.json');
        self::assertSame(1, $command->execute(['name' => 'consolefixture']));
        self::assertSame(1, $command->execute(['name' => '../escape']));
    }

    public function test_scaffolded_php_is_native_and_controllers_execute(): void
    {
        $code = 'native' . bin2hex(random_bytes(4));
        $scaffolder = new PluginScaffolder($this->root . '/plugins');
        $scaffolder->scaffold($code);
        $directory = $this->root . '/plugins/' . $code;
        foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($directory, \FilesystemIterator::SKIP_DOTS)) as $file) {
            if ($file->getExtension() !== 'php') continue;
            $source = file_get_contents($file->getPathname());
            self::assertStringNotContainsString('think\\', $source, $file->getPathname());
            token_get_all($source, TOKEN_PARSE);
        }
        require $directory . '/app/' . $code . '/controller/Index.php';
        require $directory . '/app/admin/plugins/' . $code . '/controller/Index.php';
        foreach (['app\\' . $code . '\\controller\\Index', 'app\\admin\\plugins\\' . $code . '\\controller\\Index'] as $class) {
            self::assertTrue(class_exists($class), '生成类必须匹配当前模块目录：' . $class);
            $controller = new $class();
            self::assertSame(200, $controller->index()->getStatusCode());
            $reflection = new \ReflectionClass($class);
            $attributes = $reflection->getAttributes(\support\annotation\route\RouteGroup::class);
            self::assertCount(1, $attributes);
            self::assertNotEmpty($attributes[0]->newInstance());
        }
        $adminClass = 'app\\admin\\plugins\\' . $code . '\\controller\\Index';
        self::assertSame('/admin/plugins/' . $code, (new \ReflectionClass($adminClass))->getAttributes(\support\annotation\route\RouteGroup::class)[0]->newInstance()->prefix);
        self::assertSame([], (new \ReflectionMethod($adminClass, 'index'))->getAttributes(\app\common\annotation\Permission::class));
        self::assertSame($code . ':index:index', \app\admin\authorization\service\PermissionCode::derive($adminClass, 'index'));
        $adminMiddleware = \Webman\Middleware::getMiddleware('', 'admin', [$adminClass, 'index'], null, false);
        self::assertCount(3, $adminMiddleware);
        self::assertCount(1, array_filter($adminMiddleware, static fn (array $item): bool => $item[0] === \app\admin\middleware\CheckAdminApiRoleMiddleware::class));
        self::assertCount(1, array_filter($adminMiddleware, static fn (array $item): bool => $item[0] === \app\admin\middleware\CheckAdminApiCsrfMiddleware::class));
        self::assertCount(1, array_filter($adminMiddleware, static fn (array $item): bool => $item[0] === \app\admin\middleware\SystemLogMiddleware::class));
    }

    public function test_generated_entry_lifecycle_and_config_work_without_http_request_or_think_runtime(): void
    {
        $code = 'entry' . bin2hex(random_bytes(4));
        (new PluginScaffolder($this->root . '/plugins'))->scaffold($code);
        $manifest = Manifest::fromDirectory($this->root . '/plugins/' . $code);
        $entry = (new PluginEntryFactory())->create($manifest, static fn (string $class): object => Container::make($class, []));
        self::assertTrue($entry->install());
        self::assertTrue($entry->enabled());
        self::assertTrue($entry->disabled());
        self::assertTrue($entry->uninstall());
        self::assertFalse($entry->purgeData());
        self::assertSame($code, $entry->getInfo()['code']);
        self::assertSame(20, $entry->getConfig()['page_size']);
        self::assertSame('number', $entry->getConfig(true)['page_size']['type']);
        self::assertSame(20, $entry->getConfig()['page_size']);
        (new \ReflectionMethod($entry, 'assign'))->invoke($entry, ['title' => '<script>']);
        self::assertSame('&lt;script&gt;', (new \ReflectionMethod($entry, 'display'))->invoke($entry, '{{title}}'));
        $rejected = false;
        try { (new \ReflectionMethod($entry, 'engine'))->invoke($entry, \stdClass::class); }
        catch (\RuntimeException) { $rejected = true; }
        self::assertTrue($rejected);
        $rejected = false;
        try { (new \ReflectionMethod($entry, 'fetch'))->invoke($entry, '../escape'); }
        catch (\RuntimeException) { $rejected = true; }
        self::assertTrue($rejected);
    }

    public function test_component_registry_resolves_entry_dependencies_through_native_container(): void
    {
        $code = 'component' . bin2hex(random_bytes(4));
        (new PluginScaffolder($this->root . '/plugins'))->scaffold($code);
        $directory = $this->root . '/plugins/' . $code;
        $data = json_decode(file_get_contents($directory . '/plugin.json'), true, 512, JSON_THROW_ON_ERROR);
        $data['adminWeb']['components']['value'] = 'components/Value.vue';
        mkdir($directory . '/web/src/admin/plugins/' . $code . '/components', 0700, true);
        file_put_contents($directory . '/web/src/admin/plugins/' . $code . '/components/Value.vue', '<template><span /></template>');
        $data['formComponents'] = [['type' => $code . ':value', 'component' => 'value', 'propertySchema' => (object) [], 'valueType' => 'object', 'defaultProps' => (object) [], 'allowedEvents' => [], 'capabilityVersion' => 1, 'validator' => $code . ':validate', 'codec' => $code . ':codec']];
        file_put_contents($directory . '/plugin.json', json_encode($data, JSON_THROW_ON_ERROR));
        file_put_contents($directory . '/Plugin.php', '<?php namespace plugins\\' . $code . '; final class Plugin extends \\app\\common\\plugin\\sdk\\Plugin {'
            . 'public function __construct(\\app\\common\\service\\authentication\\TokenService $tokens) { parent::__construct(); }'
            . 'public function install(): bool { return true; } public function uninstall(): bool { return true; } public function enabled(): bool { return true; } public function disabled(): bool { return true; }'
            . 'public function formComponentValidators(): array { return [' . var_export($code . ':validate', true) . ' => [$this, "validateValue"]]; }'
            . 'public function formComponentCodecs(): array { return [' . var_export($code . ':codec', true) . ' => ["encode" => [$this, "encodeValue"], "decode" => [$this, "decodeValue"]]]; }'
            . 'public function validateValue($value): bool { return is_array($value); }'
            . 'public function encodeValue($value): string { return json_encode($value); }'
            . 'public function decodeValue($value): array { return json_decode($value, true); } }');
        $manifest = Manifest::fromDirectory($directory);
        $container = Container::instance();
        $container->addDefinitions(['plugins\\' . $code . '\\Plugin' => static fn () => new ('plugins\\' . $code . '\\Plugin')(new \app\common\service\authentication\TokenService())]);
        $registry = new \app\common\form\component\PluginFormComponentRegistry(static fn (): array => [$code => $manifest]);
        self::assertNull($registry->validateValue($code . ':value', ['value' => 1]));
        self::assertNotNull($registry->validateValue($code . ':value', 'invalid'));
        self::assertSame(['value' => 1], $registry->decode($code . ':value', $registry->encode($code . ':value', ['value' => 1])));
    }

    public function test_validate_reads_logical_plugin_registry_and_rejects_missing_dependency(): void
    {
        self::assertSame('plugin', (new PluginRecord())->getTable());
        self::assertSame(PluginValidate::class, (new \ReflectionMethod(PluginValidate::class, '__construct'))->getDeclaringClass()->getName());
        self::assertSame(':memory:', Db::connection()->getDatabaseName());
        Db::schema()->create('plugin', static function (Blueprint $table): void {
            $table->id(); $table->string('code'); $table->string('version'); $table->string('lifecycle_state');
            $table->boolean('needs_reinstall')->default(false); $table->timestamps(); $table->softDeletes();
        });
        try {
            (new PluginScaffolder($this->root . '/plugins'))->scaffold('validatefixture');
            $command = new CommandTester(new PluginValidate($this->root));
            self::assertSame(0, $command->execute(['name' => 'validatefixture']), $command->getDisplay());
            self::assertTrue(json_decode($command->getDisplay(), true, 512, JSON_THROW_ON_ERROR)['valid']);
            $path = $this->root . '/plugins/validatefixture/plugin.json';
            $manifest = json_decode(file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);
            $manifest['requires']['plugins'] = ['dependency' => '>=1.0.0'];
            file_put_contents($path, json_encode($manifest, JSON_THROW_ON_ERROR));
            self::assertSame(1, $command->execute(['name' => 'validatefixture']));
            self::assertStringContainsString('依赖插件未安装', $command->getDisplay());
            PluginRecord::query()->create(['code' => 'dependency', 'version' => '1.0.0', 'lifecycle_state' => 'enabled']);
            self::assertSame(0, $command->execute(['name' => 'validatefixture']), $command->getDisplay());
            PluginRecord::query()->where('code', 'dependency')->update(['needs_reinstall' => 1]);
            self::assertSame(1, $command->execute(['name' => 'validatefixture']));
            self::assertStringContainsString('重新安装', $command->getDisplay());
            PluginRecord::query()->where('code', 'dependency')->firstOrFail()->delete();
            self::assertSame(1, $command->execute(['name' => 'validatefixture']));
        } finally { Db::schema()->dropIfExists('plugin'); }
    }
}
