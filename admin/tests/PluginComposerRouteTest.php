<?php

declare(strict_types=1);

use app\common\plugin\sdk\{PluginRouteLoader, PluginActivationReader, PluginActivationSnapshot, PluginOwnershipSnapshot};
use PHPUnit\Framework\TestCase;
use Webman\Finder\FileInfo;

final class PluginComposerRouteTest extends TestCase
{
    private string $root;
    protected function setUp(): void { $this->root = sys_get_temp_dir() . '/composer-route-' . bin2hex(random_bytes(6)); mkdir($this->root); }
    protected function tearDown(): void {
        foreach (\Composer\Autoload\ClassLoader::getRegisteredLoaders() as $path=>$loader) {
            if (str_starts_with($path, $this->root . '/')) $loader->unregister();
        }
        $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($this->root, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);
        foreach ($it as $file) $file->isDir() ? rmdir($file->getPathname()) : unlink($file->getPathname()); rmdir($this->root);
    }
    private function reader(string $code, bool $enabled, bool $trusted = true): PluginActivationReader {
        $plugins = [$code=>['code'=>$code,'enabled'=>$enabled,'state'=>$enabled?'enabled':'disabled','operation_token'=>null,'dependencies'=>[],'applications'=>['admin'=>true,'app'=>true]]];
        return new class($plugins, $trusted) extends PluginActivationReader {
            public function __construct(private array $plugins, private bool $trusted) {}
            public function readBundle(): array { return ['activation'=>$this->trusted ? PluginActivationSnapshot::trusted($this->plugins) : PluginActivationSnapshot::untrusted(), 'ownership'=>PluginOwnershipSnapshot::trusted($this->plugins)]; }
        };
    }
    private function controller(string $code, bool $admin): FileInfo {
        $dir = $this->root . '/plugins/' . $code; mkdir($dir . '/vendor', 0755, true);
        file_put_contents($dir . '/plugin.json', '{}');
        $vendor = 'ColdVendor' . $code;
        file_put_contents($dir . '/vendor/Base.php', '<?php namespace ' . $vendor . '; class Base {}');
        file_put_contents($dir . '/vendor/autoload.php', '<?php $l = new \\Composer\\Autoload\\ClassLoader(__DIR__); $l->addClassMap([' . var_export($vendor . '\\Base', true) . '=>__DIR__."/Base.php"]); $l->register(true); return $l;');
        $namespace = $admin ? 'app\\admin\\plugins\\' . $code . '\\controller' : 'app\\' . $code . '\\controller';
        $path = $this->root . '/' . $code . '.php';
        file_put_contents($path, '<?php namespace ' . $namespace . '; class Main extends \\' . $vendor . '\\Base { #[\\support\\annotation\\route\\Get("/' . $code . '")] public function index() {} }');
        return new FileInfo($path, ['class'=>$namespace . '\\Main']);
    }
    public function testColdStartSkipsDisabledControllersWithoutLoadingTheirVendor(): void {
        foreach ([true, false] as $admin) {
            $code = 'cold' . bin2hex(random_bytes(4)); $file = $this->controller($code, $admin);
            self::assertSame([], PluginRouteLoader::definitionsFor([$file], $this->reader($code, false), $this->root . '/plugins'));
            self::assertFalse(class_exists('ColdVendor' . $code . '\\Base', false));
            self::assertFalse(class_exists($file->meta()['class'], false));
        }
    }
    public function testColdStartLoadsEnabledControllerVendorBeforeAnnotationReflection(): void {
        $code = 'cold' . bin2hex(random_bytes(4)); $file = $this->controller($code, true);
        $routes = PluginRouteLoader::definitionsFor([$file], $this->reader($code, true), $this->root . '/plugins');
        self::assertSame('/' . $code, $routes[0]['path']);
        self::assertSame([$file->meta()['class'], 'index'], $routes[0]['callback']);
    }
    public function testUntrustedActivationSkipsOwnedControllersWithoutRequiringManifest(): void {
        $code = 'cold' . bin2hex(random_bytes(4)); $file = $this->controller($code, false);
        unlink($this->root . '/plugins/' . $code . '/plugin.json');
        self::assertSame([], PluginRouteLoader::definitionsFor([$file], $this->reader($code, true, false), $this->root . '/plugins'));
        self::assertFalse(class_exists($file->meta()['class'], false));
    }
    public function testStartupLoaderSharesRegisteredRoutesWithFrameworkDispatcher(): void {
        $code = 'cold' . bin2hex(random_bytes(4)); $file = $this->controller($code, true);
        $controllers = $this->root . '/app/admin/plugins/' . $code . '/controller'; mkdir($controllers, 0755, true);
        copy($file->getPathname(), $controllers . '/Main.php');
        mkdir($this->root . '/config');
        file_put_contents($this->root . '/config/route.php', '<?php \\Webman\\Route::get("/composer-startup-fixture", static fn()=>"ok");');
        $script = $this->root . '/start.php';
        file_put_contents($script, '<?php define("BASE_PATH", ' . var_export($this->root, true) . '); require ' . var_export(base_path('vendor/autoload.php'), true) . '; '
            . '\\app\\common\\plugin\\sdk\\PluginRouteLoader::load([BASE_PATH . "/config"]); '
            . '$matched = \\Webman\\Route::dispatch("GET", "/composer-startup-fixture"); '
            . 'echo json_encode(["status"=>$matched[0], "value"=>($matched[1]["callback"])(), "vendorLoaded"=>class_exists(' . var_export('ColdVendor' . $code . '\\Base', true) . ', false)]);');
        $process = proc_open([PHP_BINARY, $script], [0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']], $pipes);
        self::assertIsResource($process); fclose($pipes[0]);
        $output = stream_get_contents($pipes[1]); $error = stream_get_contents($pipes[2]); fclose($pipes[1]); fclose($pipes[2]);
        self::assertSame(0, proc_close($process), $error);
        self::assertSame(['status'=>\FastRoute\Dispatcher::FOUND,'value'=>'ok','vendorLoaded'=>false], json_decode($output, true, 512, JSON_THROW_ON_ERROR));
    }

}
