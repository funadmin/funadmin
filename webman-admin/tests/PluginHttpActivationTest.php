<?php

declare(strict_types=1);

namespace Tests;

use app\common\middleware\PluginApplicationGuard;
use app\common\plugin\sdk\PluginActivationCompiler;
use app\common\plugin\sdk\PluginActivationReader;
use app\common\plugin\sdk\PluginScaffolder;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use support\Container;
use support\Request;
use Webman\App;
use Webman\Middleware;

#[\PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses]
#[\PHPUnit\Framework\Attributes\PreserveGlobalState(false)]
final class PluginHttpActivationTest extends TestCase
{
    public function test_configured_webman_pipeline_checks_activation_on_every_request(): void
    {
        $root = sys_get_temp_dir() . '/plugin-http-' . bin2hex(random_bytes(8));
        (new PluginScaffolder($root . '/plugins'))->scaffold('httpfixture');
        $reader = new PluginActivationReader($root . '/activation');
        $compiler = new PluginActivationCompiler($root . '/activation', $root . '/plugins');
        Container::instance()->addDefinitions([PluginApplicationGuard::class => static fn () => new PluginApplicationGuard($reader, $root . '/plugins')]);
        Middleware::load(['' => array_values(array_filter(config('middleware')[''], static fn ($class) => $class === PluginApplicationGuard::class))]);
        (new \ReflectionProperty(App::class, 'logger'))->setValue(null, new NullLogger());
        try {
            $callback = App::getCallback('', 'admin', static fn () => response('controller reached'));
            $record = ['code' => 'httpfixture', 'version' => '1.0.0', 'lifecycle_state' => 'enabled', 'status' => 1];
            foreach ([
                ['enabled', 1, null, 200],
                ['disabled', 0, null, 404],
                ['enabled', 1, 'busy-operation', 404],
                ['deleted', 0, null, 404],
            ] as [$state, $status, $token, $expected]) {
                $compiler->compile(['httpfixture' => array_replace($record, ['lifecycle_state' => $state, 'status' => $status, 'operation_token' => $token])], []);
                foreach (['/admin/plugin/httpfixture/index', '/httpfixture/index'] as $path) {
                    $response = $callback(new Request("GET $path HTTP/1.1\r\nHost: localhost\r\n\r\n"));
                    self::assertSame($expected, $response->getStatusCode(), $path . ' ' . $state);
                    if ($expected !== 200) self::assertStringNotContainsString('controller reached', $response->rawBody());
                }
            }
            $alias = new Request("GET /custom-alias HTTP/1.1\r\n\r\n");
            $alias->controller = 'app\\admin\\plugins\\httpfixture\\controller\\Index';
            self::assertSame(404, $callback($alias)->getStatusCode(), '插件控制器别名路由也必须检查启停状态');
            file_put_contents($root . '/activation/active', 'corrupt');
            self::assertSame(503, $callback(new Request("GET /admin/plugin/httpfixture/index HTTP/1.1\r\n\r\n"))->getStatusCode());
            self::assertSame(503, $callback(new Request("GET /httpfixture/index HTTP/1.1\r\n\r\n"))->getStatusCode());
            self::assertSame(200, $callback(new Request("GET /admin/system/plugin/local/installed HTTP/1.1\r\n\r\n"))->getStatusCode());
            self::assertSame(200, $callback(new Request("GET /admin/plugin/market/category/ HTTP/1.1\r\n\r\n"))->getStatusCode());
        } finally {
            $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS), \RecursiveIteratorIterator::CHILD_FIRST);
            foreach ($iterator as $file) $file->isDir() ? rmdir($file->getPathname()) : unlink($file->getPathname());
            rmdir($root);
        }
    }
}
