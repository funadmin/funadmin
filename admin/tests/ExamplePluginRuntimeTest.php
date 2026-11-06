<?php

declare(strict_types=1);

namespace Tests;

use PHPUnit\Framework\TestCase;
use Webman\Route;

#[\PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses]
#[\PHPUnit\Framework\Attributes\PreserveGlobalState(false)]
final class ExamplePluginRuntimeTest extends TestCase
{
    public function test_fresh_install_has_explicit_example_permissions_without_granting_other_actions(): void
    {
        (new \app\common\service\database\MigrationService())->run();
        Route::load([config_path()]);
        \Webman\Middleware::load(['' => [\app\common\middleware\PluginApplicationGuardMiddleware::class]]);
        (new \ReflectionProperty(\Webman\App::class, 'logger'))->setValue(null, new \Psr\Log\NullLogger());
        \support\Db::table('admin')->insert(['id' => 99, 'username' => 'example_reader', 'password' => password_hash('reader-password', PASSWORD_DEFAULT), 'status' => 1]);
        \support\Db::table('auth_group')->insert(['id' => 99, 'name' => '示例读取', 'code' => 'example_reader', 'status' => 1]);
        $casbin = new \app\admin\authorization\service\CasbinService();
        $casbin->syncAdminRoles(99, [99]);
        $casbin->syncRolePermissions(99, \support\Db::table('permission')->where('code', 'example:entry:index')->pluck('id')->all());
        $auth = new \app\admin\authentication\service\BrowserAuthService();
        $anonymous = $auth->bootstrap('');
        $login = $auth->login($anonymous['cookie'], $anonymous['csrfToken'], 'example_reader', 'reader-password', '127.0.0.1');
        $call = static function (string $path) use ($login): int {
            $match = Route::dispatch('GET', $path);
            self::assertSame(\FastRoute\Dispatcher::FOUND, $match[0]);
            $request = new \support\Request("GET $path HTTP/1.1\r\nCookie: " . \app\admin\authentication\service\BrowserAuthService::COOKIE . '=' . $login['cookie'] . "\r\n\r\n");
            $request->controller = $match[1]['callback'][0]; $request->action = $match[1]['callback'][1];
            \support\Context::set(\Webman\Http\Request::class, $request);
            return (\Webman\App::getCallback('', 'admin', $match[1]['callback']))($request)->getStatusCode();
        };
        try {
            self::assertSame(200, $call('/admin/plugin/example/index'));
            self::assertSame(403, $call('/admin/plugin/example/test'));
        } finally {
            \support\Context::destroy();
        }
    }

    public function test_example_registers_native_routes_and_validation(): void
    {
        Route::load([config_path()]);
        foreach ([['GET', '/admin/plugin/example/index', 'index'], ['GET', '/admin/plugin/example/test', 'index'], ['PUT', '/admin/plugin/example/test/15', 'update'], ['POST', '/admin/plugin/example/test/15/restore', 'restore']] as [$verb, $path, $action]) {
            $match = Route::dispatch($verb, $path);
            self::assertSame(\FastRoute\Dispatcher::FOUND, $match[0], $verb . ' ' . $path);
            self::assertSame($action, $match[1]['callback'][1]);
        }
        $validate = new \app\admin\plugins\example\validate\TestValidate();
        self::assertTrue($validate->check(['field_1' => 'value']));
        self::assertSame('', $validate->getError());
        $model = new \app\admin\plugins\example\model\Test();
        $model->fill(['field_1' => 'value']);
        self::assertSame('value', $model->field_1);
    }
}
