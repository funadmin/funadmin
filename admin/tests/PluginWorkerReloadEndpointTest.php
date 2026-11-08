<?php

declare(strict_types=1);

use app\admin\plugin\controller\SystemPlugin;
use app\admin\plugin\service\PluginService;
use app\common\plugin\sdk\ComposerConflictException;
use app\common\service\runtime\WorkerReloader;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\TestCase;
use support\{Container, Context, Request};

/** 改变插件路由集合的生命周期接口在完整成功后请求一次 worker 平滑重载；其他接口与失败路径不重载。 */
#[RunTestsInSeparateProcesses]
#[PreserveGlobalState(false)]
final class PluginWorkerReloadEndpointTest extends TestCase
{
    private const RELOADING = ['installLocal', 'installDiscovered', 'installCloud', 'updateLocal', 'update', 'enable', 'disable', 'uninstall', 'redeployHistory'];
    private const NOT_RELOADING = ['migrate', 'purge', 'deletePackage', 'saveConfig'];

    private string $pidFile;
    private array $signals = [];

    protected function setUp(): void
    {
        $this->pidFile = sys_get_temp_dir() . '/plugin-reload-' . bin2hex(random_bytes(5)) . '.pid';
        file_put_contents($this->pidFile, '4321');
        $signals = &$this->signals;
        $service = new PluginService(null, new WorkerReloader($this->pidFile, static function (int $pid, int $signal) use (&$signals): bool {
            $signals[] = [$pid, $signal];
            return true;
        }));
        Container::instance()->addDefinitions([PluginService::class => static fn (): PluginService => $service]);
        $request = new Request("POST /admin/system/plugin/demo/enable HTTP/1.1\r\nHost: localhost\r\n\r\n");
        $request->context['admin'] = ['id' => 7];
        Context::set(\Webman\Http\Request::class, $request);
    }

    protected function tearDown(): void
    {
        Context::destroy();
        @unlink($this->pidFile);
    }

    public function test_route_changing_lifecycle_endpoints_request_reload_and_others_do_not(): void
    {
        foreach (self::RELOADING as $method) {
            self::assertStringContainsString('reload: true', $this->methodSource($method), $method . ' 必须在成功后请求 worker 重载');
        }
        foreach (self::NOT_RELOADING as $method) {
            self::assertStringNotContainsString('reload: true', $this->methodSource($method), $method . ' 不改变路由集合，不应重载');
        }
    }

    public function test_successful_operation_reloads_once_and_merges_reload_fields(): void
    {
        $response = $this->execute(static fn (): array => ['code' => 'demo', 'rebuildRequired' => true], true);
        self::assertSame(200, $response['code']);
        self::assertSame(['code' => 'demo', 'rebuildRequired' => true, 'reloadRequested' => true, 'reloadReason' => null], $response['data']);
        self::assertSame([[4321, 0], [4321, WorkerReloader::reloadSignal()]], $this->signals);
    }

    public function test_failures_and_composer_conflicts_do_not_reload(): void
    {
        $conflict = $this->execute(static function (): never {
            throw new ComposerConflictException([['package' => 'test/lib', 'pluginVersion' => '2.0.0', 'otherVersion' => '1.0.0', 'source' => 'host']]);
        }, true);
        self::assertSame(409, $conflict['code']);
        $failure = $this->execute(static function (): never { throw new RuntimeException('插件安装失败'); }, true);
        self::assertSame(422, $failure['code']);
        self::assertArrayNotHasKey('reloadRequested', (array) ($failure['data'] ?? []));
        self::assertSame([], $this->signals);
    }

    public function test_non_lifecycle_operations_keep_their_response_shape(): void
    {
        $response = $this->execute(static fn (): array => ['version' => '3'], false);
        self::assertSame(['version' => '3'], $response['data']);
        self::assertSame([], $this->signals);
    }

    private function execute(callable $operation, bool $reload): array
    {
        $controller = new SystemPlugin();
        $response = (new ReflectionMethod(SystemPlugin::class, 'execute'))->invoke($controller, $operation, '操作成功', $reload);
        return json_decode($response->rawBody(), true, 512, JSON_THROW_ON_ERROR);
    }

    private function methodSource(string $method): string
    {
        $reflection = new ReflectionMethod(SystemPlugin::class, $method);
        $lines = file((string) $reflection->getFileName());
        return implode('', array_slice($lines, $reflection->getStartLine() - 1, $reflection->getEndLine() - $reflection->getStartLine() + 1));
    }
}
