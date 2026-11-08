<?php
declare(strict_types=1);
namespace Tests;
use app\admin\authentication\service\AdminLoginPath;
use app\common\service\database\MigrationService;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\TestCase;
use support\Db;
use support\Request;
use support\Context;
use Webman\Route;
#[PreserveGlobalState(false)]
final class DynamicAdminLoginEntryTest extends TestCase
{
    protected function setUp():void{parent::setUp();(new MigrationService())->run();Db::table('config')->where('code',AdminLoginPath::CODE)->delete();}
    #[RunInSeparateProcess]
    public function test_login_entry_validates_reserved_and_safe_values():void{
        self::assertSame('admin',AdminLoginPath::normalize('admin'));self::assertSame('secure-login',AdminLoginPath::normalize('secure-login'));$this->expectException(\InvalidArgumentException::class);AdminLoginPath::normalize('auth');
    }
    #[RunInSeparateProcess]
    public function test_install_default_and_manual_rotation_change_the_dynamic_auth_routes():void{
        AdminLoginPath::set('secure-login');Route::load([config_path()]);
        foreach([['GET','/admin/secure-login/auth/csrf',true],['POST','/admin/secure-login/auth/login',true],['GET','/admin/auth/csrf',true],['GET','/admin/old-entry/auth/csrf',true]] as [$method,$path,$found])self::assertSame($found,Route::dispatch($method,$path)[0]===\FastRoute\Dispatcher::FOUND,$path);
        $this->expectException(\DomainException::class);
        $this->expectExceptionCode(404);
        (new \app\admin\authentication\controller\AdminAuth())->csrf(new Request("GET /admin/old-entry/auth/csrf HTTP/1.1\r\nHost: localhost\r\n\r\n"), 'old-entry');
    }

    #[RunInSeparateProcess]
    public function test_rotation_removes_old_entry_at_controller_boundary():void{
        AdminLoginPath::set('secure-login');
        AdminLoginPath::set('rotated-login');Route::load([config_path()]);
        self::assertSame(\FastRoute\Dispatcher::FOUND,Route::dispatch('GET','/admin/rotated-login/auth/csrf')[0]);
        self::assertSame(\FastRoute\Dispatcher::FOUND,Route::dispatch('GET','/admin/secure-login/auth/csrf')[0]);
        $this->expectException(\DomainException::class);
        $this->expectExceptionCode(404);
        (new \app\admin\authentication\controller\AdminAuth())->csrf(new Request("GET /admin/secure-login/auth/csrf HTTP/1.1\r\nHost: localhost\r\n\r\n"), 'secure-login');
    }

    #[RunInSeparateProcess]
    public function test_only_super_admin_can_rotate_the_system_entry():void{
        AdminLoginPath::ensure();
        $id = (int) Db::table('config')->where('code', AdminLoginPath::CODE)->value('id');
        $request = new Request("PUT /admin/system/config/$id/value HTTP/1.1\r\nHost: localhost\r\n\r\n");
        $request->context['admin'] = ['id' => 2];
        Context::set(\Webman\Http\Request::class, $request);
        $response = (new \app\admin\system\controller\SystemConfig())->value($id);
        self::assertSame(403, $response->getStatusCode());
        $request->context['admin'] = ['id' => 1];
        Context::set(\Webman\Http\Request::class, $request);
        $request->setPost(['value' => 'safe-login']);
        $response = (new \app\admin\system\controller\SystemConfig())->value($id);
        self::assertSame(200, $response->getStatusCode());
        self::assertSame('safe-login', AdminLoginPath::current());
    }
}
