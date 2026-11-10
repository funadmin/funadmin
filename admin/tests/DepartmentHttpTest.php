<?php

declare(strict_types=1);

namespace Tests;

use app\admin\authentication\service\BrowserAuthService;
use Illuminate\Database\Schema\Blueprint;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use support\Context;
use support\Db;
use support\Request;
use Webman\App;
use Webman\Http\Response;
use Webman\Route;

#[RunTestsInSeparateProcesses]
#[PreserveGlobalState(false)]
final class DepartmentHttpTest extends TestCase
{
    private array $session;

    protected function setUp(): void
    {
        \Tests\Support\TestDatabase::migrate();
        foreach (['fun_department', 'fun_auth_group_department', 'fun_auth_group_inherit'] as $table) {
            Db::connection()->getPdo()->exec('DROP TABLE IF EXISTS "' . $table . '"');
        }
        Db::connection()->getPdo()->exec('DELETE FROM "fun_permission"');
        foreach (['admin_auth_tokens', 'browser_sessions', 'login_attempts', 'admin', 'auth_group', 'permission', 'casbin_rule'] as $table) {
            if (Db::schema()->hasTable($table)) {
                Db::table($table)->delete();
            }
        }
        Db::schema()->create('department', static function (Blueprint $table): void {
            $table->id(); $table->integer('pid')->default(0); $table->string('name');
            foreach (['leader', 'phone', 'email'] as $field) $table->string($field)->default('');
            $table->integer('sort_order')->default(0); $table->boolean('status')->default(true); $table->timestamps(); $table->softDeletes();
        });
        foreach (['auth_group_department' => ['role_id', 'dept_id'], 'auth_group_inherit' => ['role_id', 'parent_role_id']] as $name => $fields) Db::schema()->create($name, static function (Blueprint $table) use ($fields): void { $table->id(); foreach ($fields as $field) $table->integer($field); $table->softDeletes(); });
        foreach ([1, 2] as $id) Db::table('admin')->insert(['id' => $id, 'username' => 'admin' . $id, 'password' => password_hash('department-test-password', PASSWORD_DEFAULT), 'status' => 1]);
        $auth = new BrowserAuthService();
        $anonymous = $auth->bootstrap('');
        $this->session = $auth->login($anonymous['cookie'], $anonymous['csrfToken'], 'admin1', 'department-test-password', '127.0.0.1');
        (new \ReflectionProperty(App::class, 'logger'))->setValue(null, new NullLogger());
        Route::load([config_path()]);
    }

    protected function tearDown(): void { Context::destroy(); }

    public function test_department_create_tree_partial_update_and_soft_delete(): void
    {
        $response = $this->call('POST', '/admin/system/dept', ['name' => '总部']);
        self::assertSame(200, $response->getStatusCode(), $response->rawBody());
        $root = json_decode($response->rawBody(), true)['data'];
        self::assertSame('总部', $root['name']);
        $childResponse = $this->call('POST', '/admin/system/dept', ['name' => '研发', 'parentId' => $root['id']]);
        $childPayload = json_decode($childResponse->rawBody(), true);
        self::assertSame(200, $childResponse->getStatusCode(), $childResponse->rawBody());
        $child = $childPayload['data'];
        $tree = json_decode($this->call('GET', '/admin/system/dept/tree')->rawBody(), true)['data'];
        self::assertCount(1, $tree);
        self::assertSame('研发', $tree[0]['children'][0]['name']);
        $updated = json_decode($this->call('PUT', '/admin/system/dept/' . $child['id'], ['leader' => '负责人'])->rawBody(), true);
        self::assertSame(200, $updated['code']);
        self::assertSame('研发', $updated['data']['name']);
        self::assertSame('负责人', Db::table('department')->where('id', $child['id'])->value('leader'));
        self::assertSame(422, json_decode($this->call('DELETE', '/admin/system/dept/' . $root['id'])->rawBody(), true)['code']);
        self::assertSame(200, json_decode($this->call('DELETE', '/admin/system/dept', ['ids' => [$root['id'], $child['id']]])->rawBody(), true)['code']);
        self::assertSame(2, Db::table('department')->whereNotNull('deleted_at')->count());
        self::assertSame(404, $this->call('GET', '/admin/system/dept/' . $root['id'])->getStatusCode());
    }

    public function test_department_rejects_cycles_associations_and_invalid_payloads(): void
    {
        $root = Db::table('department')->insertGetId(['name' => '根']);
        $child = Db::table('department')->insertGetId(['name' => '子', 'pid' => $root]);
        self::assertSame(422, json_decode($this->call('PUT', '/admin/system/dept/' . $root, ['parentId' => $child])->rawBody(), true)['code']);
        foreach ([['name' => []], ['name' => ''], ['name' => '部门', 'email' => 'bad'], ['name' => '部门', 'status' => []]] as $data) self::assertSame(422, json_decode($this->call('POST', '/admin/system/dept', $data)->rawBody(), true)['code']);
        Db::table('admin_department')->insert(['admin_id' => 2, 'dept_id' => $child]);
        self::assertSame(422, json_decode($this->call('DELETE', '/admin/system/dept/' . $child)->rawBody(), true)['code']);
        self::assertNull(Db::table('department')->where('id', $child)->value('deleted_at'));
        self::assertSame(403, $this->call('POST', '/admin/system/dept', ['name' => '不应创建'], true, false)->getStatusCode());
        self::assertSame(401, $this->call('GET', '/admin/system/dept/tree', [], false)->getStatusCode());
    }

    public function test_non_super_user_tree_and_writes_remain_in_department_scope(): void
    {
        $root = Db::table('department')->insertGetId(['name' => '可见']);
        $other = Db::table('department')->insertGetId(['name' => '不可见']);
        Db::table('admin')->where('id', 2)->update(['dept_id' => $root]);
        Db::table('auth_group')->insert(['id' => 2, 'code' => 'dept_reader', 'name' => '部门管理', 'data_scope' => 'dept']);
        $this->rule('g', ['admin:2', 'role:2', 'default']);
        foreach (['tree', 'detail', 'create', 'update', 'delete', 'deleteById'] as $action) {
            $code = \app\admin\authorization\service\PermissionCode::derive(\app\admin\authorization\controller\SystemDepartment::class, $action);
            ['obj' => $obj, 'act' => $act] = \app\admin\authorization\service\PermissionCode::resource($code);
            Db::table('permission')->insert(['code' => $code, 'obj' => $obj, 'act' => $act, 'name' => '部门']);
            $this->rule('p', ['role:2', 'default', $obj, $act]);
        }
        $auth = new BrowserAuthService();
        $anonymous = $auth->bootstrap('');
        $this->session = $auth->login($anonymous['cookie'], $anonymous['csrfToken'], 'admin2', 'department-test-password', '127.0.0.2');
        $tree = json_decode($this->call('GET', '/admin/system/dept/tree')->rawBody(), true)['data'];
        self::assertCount(1, $tree);
        self::assertSame($root, $tree[0]['id']);
        self::assertSame(404, $this->call('GET', '/admin/system/dept/' . $other)->getStatusCode());
        self::assertSame(403, $this->call('POST', '/admin/system/dept', ['name' => '顶级'])->getStatusCode());
        self::assertSame(404, $this->call('PUT', '/admin/system/dept/' . $other, ['name' => '越权'])->getStatusCode());
        self::assertSame('不可见', Db::table('department')->where('id', $other)->value('name'));
    }

    private function rule(string $type, array $values): void
    {
        $row = ['ptype' => $type, 'rule_hash' => hash('sha256', $type . implode('|', $values))];
        foreach ($values as $index => $value) $row['v' . $index] = $value;
        Db::table('casbin_rule')->insert($row);
    }

    private function call(string $method, string $path, array $data = [], bool $authenticated = true, bool $csrf = true): Response
    {
        $body = json_encode($data, JSON_THROW_ON_ERROR);
        $headers = $authenticated ? 'Cookie: ' . BrowserAuthService::COOKIE . '=' . $this->session['cookie'] . "\r\n" : '';
        if ($csrf) $headers .= 'X-CSRF-TOKEN: ' . $this->session['csrfToken'] . "\r\n";
        $request = new Request("$method $path HTTP/1.1\r\nHost: localhost\r\n" . $headers . "Content-Type: application/json\r\nContent-Length: " . strlen($body) . "\r\n\r\n" . $body);
        $matched = Route::dispatch($method, $path);
        self::assertSame(\FastRoute\Dispatcher::FOUND, $matched[0], "$method $path");
        $request->controller = $matched[1]['callback'][0]; $request->action = $matched[1]['callback'][1]; $request->route = $matched[1]['route'];
        Context::set(\Webman\Http\Request::class, $request);
        return (App::getCallback('', 'admin', $matched[1]['callback'], $matched[2], false, $request->route))($request);
    }
}
