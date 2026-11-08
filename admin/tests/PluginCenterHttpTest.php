<?php

declare(strict_types=1);

namespace Tests;

use app\admin\authentication\service\BrowserAuthService;
use app\admin\authorization\service\CasbinService;
use app\common\service\database\MigrationService;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use support\Context;
use support\Db;
use support\Request;
use Webman\App;
use Webman\Route;

#[RunTestsInSeparateProcesses]
#[PreserveGlobalState(false)]
final class PluginCenterHttpTest extends TestCase
{
    private array $session;

    protected function setUp(): void
    {
        (new MigrationService())->run();
        Db::beginTransaction();
        foreach (['admin_auth_tokens', 'browser_sessions', 'login_attempts', 'casbin_rule', 'auth_group', 'admin'] as $table) Db::table($table)->delete();
        Db::table('admin')->insert(['id' => 1, 'username' => 'plugin_root', 'password' => password_hash('plugin-root-password', PASSWORD_DEFAULT), 'status' => 1]);
        Db::table('admin')->insert(['id' => 2, 'username' => 'plugin_reader', 'password' => password_hash('plugin-reader-password', PASSWORD_DEFAULT), 'status' => 1]);
        Db::table('auth_group')->insert(['id' => 2, 'name' => '插件只读', 'code' => 'plugin_reader', 'level' => 10, 'data_scope' => 'self', 'status' => 1]);
        $permissionIds = Db::table('permission')->whereIn('code', ['plugin:plugin:installed', 'plugin:plugin:discovered'])->pluck('id')->all();
        (new CasbinService())->syncAdminRoles(2, [2]);
        (new CasbinService())->syncRolePermissions(2, $permissionIds);
        $auth = new BrowserAuthService(); $anonymous = $auth->bootstrap('');
        $this->session = $auth->login($anonymous['cookie'], $anonymous['csrfToken'], 'plugin_reader', 'plugin-reader-password', '127.0.0.2');
        (new \ReflectionProperty(App::class, 'logger'))->setValue(null, new NullLogger());
        Route::load([config_path()]);
    }

    protected function tearDown(): void { Db::rollBack(); Context::destroy(); }

    public function test_plugin_center_read_routes_require_authentication_and_exact_permissions(): void
    {
        self::assertSame(401, $this->call('GET', '/admin/system/plugin/local/installed', authenticated: false)->getStatusCode());
        $info = (new \app\admin\authorization\service\AuthorizationService())->userInfo(['id' => 2]);
        self::assertContains('plugin:plugin:installed', $info['permissions']);
        $installed = $this->call('GET', '/admin/system/plugin/local/installed');
        self::assertSame(200, $installed->getStatusCode(), $installed->rawBody());
        self::assertSame(200, $this->call('GET', '/admin/system/plugin/local/discovered')->getStatusCode());
        self::assertSame(403, $this->call('POST', '/admin/system/plugin/local/install', [], csrf: true)->getStatusCode());
    }

    public function test_plugin_routes_reject_anonymous_and_undeclared_permissions_before_services(): void
    {
        new App(Request::class, new NullLogger(), app_path(), public_path());
        self::assertSame(401, $this->call('GET', '/admin/plugins/qa/moderation', authenticated: false)->getStatusCode());
        self::assertSame(403, $this->call('GET', '/admin/plugins/qa/moderation')->getStatusCode());
        self::assertSame(403, $this->call('POST', '/admin/plugins/qa/moderation/review', ['id' => '1'])->getStatusCode());
    }

    public function test_authorized_plugin_request_reaches_validation_and_keeps_csrf(): void
    {
        new App(Request::class, new NullLogger(), app_path(), public_path());
        $auth = new BrowserAuthService(); $anonymous = $auth->bootstrap('');
        $this->session = $auth->login($anonymous['cookie'], $anonymous['csrfToken'], 'plugin_root', 'plugin-root-password', '127.0.0.3');
        $response = $this->call('GET', '/admin/plugins/qa/moderation');
        self::assertSame(200, $response->getStatusCode());
        self::assertSame(422, json_decode($response->rawBody(), true, flags: JSON_THROW_ON_ERROR)['code']);
        self::assertSame(403, $this->call('POST', '/admin/plugins/qa/moderation/review', [], csrf: false)->getStatusCode());
        $response = $this->call('POST', '/admin/plugins/qa/moderation/review');
        self::assertSame(200, $response->getStatusCode());
        self::assertSame(422, json_decode($response->rawBody(), true, flags: JSON_THROW_ON_ERROR)['code']);
        $permissionIds = Db::table('permission')->where('code', 'qa:moderation:index')->pluck('id')->all();
        (new CasbinService())->syncRolePermissions(2, $permissionIds);
        $anonymous = $auth->bootstrap('');
        $this->session = $auth->login($anonymous['cookie'], $anonymous['csrfToken'], 'plugin_reader', 'plugin-reader-password', '127.0.0.4');
        $response = $this->call('GET', '/admin/plugins/qa/moderation');
        self::assertSame(200, $response->getStatusCode());
        self::assertSame(422, json_decode($response->rawBody(), true, flags: JSON_THROW_ON_ERROR)['code']);
        self::assertSame(403, $this->call('POST', '/admin/plugins/qa/moderation/review')->getStatusCode());
    }

    public function test_plugin_install_write_requires_csrf_before_package_processing(): void
    {
        self::assertSame(403, $this->call('POST', '/admin/system/plugin/local/install', [], csrf: false)->getStatusCode());
    }

    public function test_plugin_users_discover_only_permitted_pages_without_plugin_center_permission(): void
    {
        $code = 'catalog' . bin2hex(random_bytes(5));
        $parent = base_path('plugins');
        $createdParent = !is_dir($parent);
        $directory = $parent . '/' . $code;
        (new \app\common\plugin\sdk\PluginScaffolder($parent))->scaffold($code);
        $activation = sys_get_temp_dir() . '/plugin-http-activation-' . bin2hex(random_bytes(5));
        \support\Container::instance()->addDefinitions([\app\common\plugin\service\FrontendHookService::class => static fn () => new \app\common\plugin\service\FrontendHookService(new \app\common\plugin\sdk\PluginActivationReader($activation))]);
        try {
            $manifest = json_decode(file_get_contents($directory . '/plugin.json'), true, 512, JSON_THROW_ON_ERROR);
            $manifest['adminWeb']['permissions'][] = ['code' => $code . ':item:manage', 'name' => '管理'];
        $manifest['adminWeb']['routes'][] = ['path' => '/plugins/' . $code . '/manage', 'name' => 'Plugin_' . $code . '_Manage', 'component' => 'Index', 'meta' => ['permission' => $code . ':item:manage']];
            file_put_contents($directory . '/plugin.json', json_encode($manifest, JSON_THROW_ON_ERROR));
            Db::table('plugin')->insert(['code' => $code, 'name' => '目录测试', 'version' => '1.0.0', 'lifecycle_state' => 'enabled', 'status' => 1, 'manifest' => json_encode($manifest, JSON_THROW_ON_ERROR)]);
            (new \app\common\plugin\sdk\PluginActivationCompiler($activation, $parent))->compile([$code => ['code'=>$code,'status'=>1,'lifecycle_state'=>'enabled','needs_reinstall'=>false,'version'=>'1.0.0']], [$code=>$manifest]);
            \app\admin\authorization\service\ResourceRegistryService::instance()->registerPermissions($manifest['adminWeb']['permissions'], 'plugin', $code);
            (new CasbinService())->syncRolePermissions(2, Db::table('permission')->whereIn('code', ['plugin:plugin:installed', 'plugin:plugin:discovered', $code . ':index:index'])->pluck('id')->all());
            self::assertSame(401, $this->call('GET', '/admin/system/plugin/modules/enabled', authenticated: false)->getStatusCode());
            $response = $this->call('GET', '/admin/system/plugin/modules/enabled');
            self::assertSame(200, $response->getStatusCode(), $response->rawBody());
            $modules = json_decode($response->rawBody(), true, 512, JSON_THROW_ON_ERROR)['data'];
            $module = array_values(array_filter($modules, static fn ($item) => $item['code'] === $code))[0] ?? null;
            self::assertNotNull($module);
            self::assertSame(['/plugins/' . $code . '/items'], array_column($module['routes'], 'path'));
            (new CasbinService())->syncRolePermissions(2, []);
            $response = $this->call('GET', '/admin/system/plugin/modules/enabled');
            self::assertSame(200, $response->getStatusCode());
            self::assertSame([], json_decode($response->rawBody(), true, 512, JSON_THROW_ON_ERROR)['data']);
        } finally {
            $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($directory, \FilesystemIterator::SKIP_DOTS), \RecursiveIteratorIterator::CHILD_FIRST);
            foreach ($iterator as $file) $file->isDir() ? rmdir($file->getPathname()) : unlink($file->getPathname());
            rmdir($directory);
            if ($createdParent) @rmdir($parent);
            $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($activation, \FilesystemIterator::SKIP_DOTS), \RecursiveIteratorIterator::CHILD_FIRST);
            foreach ($iterator as $item) $item->isDir() ? rmdir($item->getPathname()) : unlink($item->getPathname());
            rmdir($activation);
        }
    }

    public function test_frontend_hook_endpoint_authenticates_exact_audience_and_projects_dependencies(): void
    {
        new App(Request::class, new NullLogger(), app_path(), public_path());
        $csrfResponse = (new \app\admin\authentication\controller\AdminAuth())->csrf(new Request("GET /admin/auth/csrf HTTP/1.1\r\nHost: localhost\r\n\r\n"));
        self::assertStringContainsString('path=/admin', strtolower(implode(';', (array) $csrfResponse->getHeader('Set-Cookie'))));
        putenv('MEMBER_JWT_SECRET=' . bin2hex(random_bytes(32)));
        \Webman\Config::load(config_path(), ['route']);
        $reader = new class('unused') extends \app\common\plugin\sdk\PluginActivationReader {
            public function activeGeneration(): ?string { return str_repeat('a',32); }
            public function read(): \app\common\plugin\sdk\PluginActivationSnapshot {
                $base=['enabled'=>true,'state'=>'enabled','needs_reinstall'=>false,'operation_token'=>null,'version'=>'1.0.0','applications'=>['app'=>false,'admin'=>false]];
                return \app\common\plugin\sdk\PluginActivationSnapshot::trusted([
                    'hookdemo'=>$base+['code'=>'hookdemo','dependencies'=>[]],
                    'blocked'=>$base+['code'=>'blocked','dependencies'=>['missing']],
                ]);
            }
        };
        $make = static fn(string $code) => \app\common\plugin\sdk\Manifest::fromCompiled('/unused/'.$code,['code'=>$code,'adminWeb'=>['components'=>[]],'clientHooks'=>[
            ['id'=>'open','point'=>'demo.run','type'=>'action','file'=>'hooks.ts','export'=>'run','audiences'=>['public']],
            ['id'=>'member','point'=>'demo.run','type'=>'action','file'=>'hooks.ts','export'=>'run','audiences'=>['member']],
            ['id'=>'admin','point'=>'demo.run','type'=>'action','file'=>'hooks.ts','export'=>'run'],
        ]]);
        \support\Container::instance()->addDefinitions([\app\common\plugin\service\FrontendHookService::class=>static fn()=>new \app\common\plugin\service\FrontendHookService($reader,static fn()=>['hookdemo'=>$make('hookdemo'),'blocked'=>$make('blocked')])]);
        Db::schema()->table('member', static function (\Illuminate\Database\Schema\Blueprint $table): void { $table->string('nickname')->default(''); });
        Db::table('member')->insert(['id'=>991,'username'=>'hook_member','nickname'=>'Hooks','sex'=>'0','level_id'=>1,'status'=>1]);
        $token=(new \app\common\service\authentication\TokenService())->build(['id'=>991]);
        $call=function(string $audience,bool $cookie=false,?string $bearer=null) {
            $headers=$cookie ? 'Cookie: '.BrowserAuthService::COOKIE.'='.$this->session['cookie']."\r\n" : '';
            if ($bearer!==null) $headers.='Authorization: Bearer '.$bearer."\r\n";
            $request=new Request("GET ".($audience==='admin'?'/admin/plugins/hooks/frontend':'/plugins/hooks/frontend')."?audience=$audience HTTP/1.1\r\nHost: localhost\r\n".$headers."\r\n");
            $matched=Route::dispatch('GET',$audience==='admin'?'/admin/plugins/hooks/frontend':'/plugins/hooks/frontend');
            self::assertSame(\FastRoute\Dispatcher::FOUND,$matched[0]);
            $request->controller=$matched[1]['callback'][0]; $request->action='index'; $request->route=$matched[1]['route'];
            Context::set(\Webman\Http\Request::class,$request);
            return (App::getCallback('','',$matched[1]['callback'],$matched[2],false,$request->route))($request);
        };
        foreach ([['public',true,$token,'open'],['member',false,$token,'member'],['admin',true,null,'admin']] as [$audience,$cookie,$bearer,$id]) {
            $response=$call($audience,$cookie,$bearer);
            self::assertSame(200,$response->getStatusCode(),$response->rawBody());
            $data=json_decode($response->rawBody(),true)['data'];
            self::assertSame(['hookdemo'],array_column($data['modules'],'code'));
            self::assertSame([$id],array_column($data['modules'][0]['clientHooks'],'id'));
        }
        self::assertSame(401,$call('member',true)->getStatusCode());
        self::assertSame(401,$call('admin',false,$token)->getStatusCode());
        Db::table('member')->where('id',991)->update(['status'=>0]);
        self::assertSame(401,$call('member',false,$token)->getStatusCode());
        self::assertSame(400,$call('invalid')->getStatusCode());
    }

    private function call(string $method, string $path, array $data = [], bool $authenticated = true, bool $csrf = true): \Webman\Http\Response
    {
        $body = json_encode($data, JSON_THROW_ON_ERROR);
        $headers = $authenticated ? 'Cookie: ' . BrowserAuthService::COOKIE . '=' . $this->session['cookie'] . "\r\n" : '';
        if ($csrf) $headers .= 'X-CSRF-TOKEN: ' . $this->session['csrfToken'] . "\r\n";
        $request = new Request("$method $path HTTP/1.1\r\nHost: localhost\r\n" . $headers . "Content-Type: application/json\r\nContent-Length: " . strlen($body) . "\r\n\r\n" . $body);
        $matched = Route::dispatch($method, $path); self::assertSame(\FastRoute\Dispatcher::FOUND, $matched[0], "$method $path");
        $request->controller = $matched[1]['callback'][0]; $request->action = $matched[1]['callback'][1]; $request->route = $matched[1]['route']; Context::set(\Webman\Http\Request::class, $request);
        return (App::getCallback('', 'admin', $matched[1]['callback'], $matched[2], false, $request->route))($request);
    }
}
