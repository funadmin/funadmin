<?php

declare(strict_types=1);

require dirname(__DIR__) . '/vendor/autoload.php';

use app\common\middleware\PluginApplicationGuard;
use fun\plugins\PluginActivationCompiler;
use fun\plugins\PluginActivationReader;
use think\exception\HttpException;
use think\Request;
use think\Response;

function guardExpect(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

function guardStatus(callable $callback): int
{
    try {
        $callback();
    } catch (HttpException $exception) {
        return $exception->getStatusCode();
    }
    return 200;
}

function guardRemoveTree(string $directory): void
{
    if (!is_dir($directory)) {
        return;
    }
    $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($directory, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);
    foreach ($iterator as $item) {
        $item->isDir() ? rmdir($item->getPathname()) : unlink($item->getPathname());
    }
    rmdir($directory);
}

$root = sys_get_temp_dir() . '/funadmin-plugin-guard-' . bin2hex(random_bytes(4));
$runtime = $root . '/runtime/plugins/activation';
$pluginsPath = $root . '/plugins';
mkdir($pluginsPath . '/shop/app/shop', 0755, true);
mkdir($pluginsPath . '/shop/app/console', 0755, true);
mkdir($pluginsPath . '/worker/app/console', 0755, true);
$records = [
    'shop' => ['code' => 'shop', 'lifecycle_state' => 'enabled', 'status' => 1, 'needs_reinstall' => 0, 'operation_token' => null, 'version' => '1.0.0', 'package_hash' => 'hash'],
    'worker' => ['code' => 'worker', 'lifecycle_state' => 'disabled', 'status' => 0, 'needs_reinstall' => 0, 'operation_token' => null, 'version' => '1.0.0', 'package_hash' => 'hash'],
];
$manifests = [
    'shop' => ['schema_version' => 2, 'code' => 'shop', 'version' => '1.0.0', 'requires' => ['plugins' => []]],
    'worker' => ['schema_version' => 2, 'code' => 'worker', 'version' => '1.0.0', 'requires' => ['plugins' => []]],
];
(new PluginActivationCompiler($runtime, $pluginsPath))->compile($records, $manifests);
$reader = new class($runtime) extends PluginActivationReader {
    public int $bundleReads = 0;

    public function readBundle(): array
    {
        $this->bundleReads++;
        return parent::readBundle();
    }
};
$guard = new PluginApplicationGuard($reader);
$next = static fn (Request $request): Response => Response::create('ok');

foreach (['shop/products', '/console/plugin/shop/orders'] as $path) {
    $request = (new Request())->setPathinfo($path);
    $reads = $reader->bundleReads;
    guardExpect(guardStatus(fn () => $guard->handle($request, $next)) === 200, '启用插件路径必须放行：' . $path);
    guardExpect($reader->bundleReads === $reads + 1, 'Guard 每个请求只能读取一次 activation bundle：' . $path);
}
foreach (['worker/task', '/console/plugin/worker/run'] as $path) {
    $request = (new Request())->setPathinfo($path);
    guardExpect(guardStatus(fn () => $guard->handle($request, $next)) === 404, '已知禁用插件路径必须返回 404：' . $path);
}
foreach (['console/system/index', 'api/v2/token', 'index/home', 'install/index', 'common/internal', 'news/article', 'unknown/path'] as $path) {
    $request = (new Request())->setPathinfo($path);
    guardExpect(guardStatus(fn () => $guard->handle($request, $next)) === 200, '核心或未知普通路径必须放行：' . $path);
}
$request = (new Request())->setPathinfo('console/plugin/unknown/action');
guardExpect(guardStatus(fn () => $guard->handle($request, $next)) === 404, '明确 console 插件路径中的未知 code 必须返回 404');

unlink($runtime . '/active');
$request = (new Request())->setPathinfo('api/v2/token');
guardExpect(guardStatus(fn () => $guard->handle($request, $next)) === 200, '清单损坏不得阻断核心应用');
$request = (new Request())->setPathinfo('shop/products');
guardExpect(guardStatus(fn () => $guard->handle($request, $next)) === 200, 'active 缺失时不得从其他 generation 单独读取 ownership');
$request = (new Request())->setPathinfo('news/article');
guardExpect(guardStatus(fn () => $guard->handle($request, $next)) === 200, 'activation 损坏时未知普通路径仍必须放行');
$request = (new Request())->setPathinfo('console/plugin/shop/orders');
guardExpect(guardStatus(fn () => $guard->handle($request, $next)) === 503, '明确插件路径遇到清单损坏必须返回 503');

$valid = (new PluginActivationCompiler($runtime, $pluginsPath))->compile($records, $manifests);
$file = $runtime . '/generations/' . $valid['generation'] . '/snapshot.json';
$payload = json_decode((string) file_get_contents($file), true, 512, JSON_THROW_ON_ERROR);
$payload['plugins']['shop']['version'] = 'tampered';
file_put_contents($file, json_encode($payload, JSON_THROW_ON_ERROR));
$request = (new Request())->setPathinfo('shop/products');
guardExpect(guardStatus(fn () => $guard->handle($request, $next)) === 503, 'activation 损坏但 ownership 可信且命中 app 时必须返回 503');
$request = (new Request())->setPathinfo('news/article');
guardExpect(guardStatus(fn () => $guard->handle($request, $next)) === 200, 'activation 损坏但 ownership 可信时未知普通路径必须放行');
$request = (new Request())->setPathinfo('console/plugin/shop/orders');
guardExpect(guardStatus(fn () => $guard->handle($request, $next)) === 503, 'activation 损坏时明确插件路径必须 fail closed');

unlink($runtime . '/active');
$request = (new Request())->setPathinfo('news/article');
guardExpect(guardStatus(fn () => $guard->handle($request, $next)) === 200, 'activation 与 ownership 均损坏时不得阻断无法判定的普通路径');
$request = (new Request())->setPathinfo('console/plugin/shop/orders');
guardExpect(guardStatus(fn () => $guard->handle($request, $next)) === 503, 'activation 与 ownership 均损坏时明确 console 插件路径必须返回 503');

$middleware = (string) file_get_contents(dirname(__DIR__) . '/app/middleware.php');
guardExpect(str_contains($middleware, 'PluginApplicationGuard::class'), '必须注册根 PluginApplicationGuard');
$service = (string) file_get_contents(dirname(__DIR__) . '/vendor/topthink/think-multi-app/src/Service.php');
guardExpect(str_contains($service, 'middleware->add(MultiApp::class)'), '测试前提：MultiApp 由服务层追加');
guardExpect(strpos($middleware, 'PluginApplicationGuard::class') < strpos($middleware, 'SessionInit::class'), '根 Guard 必须位于现有全局中间件最前，锁定其早于 MultiApp');

$guardSource = (string) file_get_contents(dirname(__DIR__) . '/app/common/middleware/PluginApplicationGuard.php');
foreach (['app\\common\\model\\Plugin', 'Registry', 'Manifest', 'glob(', 'scandir('] as $forbidden) {
    guardExpect(!str_contains($guardSource, $forbidden), 'Guard 普通请求禁止 DB/Manifest/扫描：' . $forbidden);
}

guardRemoveTree($root);
echo "plugin application guard tests passed\n";
