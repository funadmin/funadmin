<?php

declare(strict_types=1);

use app\admin\plugin\controller\SystemPlugin;
use app\common\plugin\sdk\ComposerConflictException;
use PHPUnit\Framework\TestCase;
use support\{Context, Request};

final class PluginComposerHttpTest extends TestCase
{
    protected function tearDown(): void { Context::destroy(); }

    public function testConflictResponseIsStructuredAndRetainsCsrfHeader(): void
    {
        $request = new Request("POST /admin/system/plugin/local/install HTTP/1.1\r\nHost: localhost\r\n\r\n");
        $request->context['admin'] = ['id'=>42];
        $request->context['admin_csrf_token'] = 'test-csrf-token';
        Context::set(\Webman\Http\Request::class, $request);
        $controller = new SystemPlugin();
        $response = (new ReflectionMethod(SystemPlugin::class, 'execute'))->invoke($controller, static function () {
            throw new ComposerConflictException([['package'=>'test/lib','pluginVersion'=>'2.0.0','otherVersion'=>'1.0.0','source'=>'host']]);
        });
        self::assertSame(409, $response->getStatusCode());
        self::assertSame('test-csrf-token', $response->getHeader('X-CSRF-TOKEN'));
        $body = json_decode($response->rawBody(), true, 512, JSON_THROW_ON_ERROR);
        self::assertSame(409, $body['code']);
        self::assertSame('composer_conflict', $body['data']['reason']);
        self::assertSame('test/lib', $body['data']['conflicts'][0]['package']);
    }
}
