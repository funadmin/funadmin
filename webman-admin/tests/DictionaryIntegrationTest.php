<?php

declare(strict_types=1);

namespace Tests;

use app\admin\system\controller\SystemDict;
use app\common\service\database\MigrationService;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\TestCase;
use support\Context;
use support\Db;
use support\Request;

#[RunTestsInSeparateProcesses]
#[PreserveGlobalState(false)]
final class DictionaryIntegrationTest extends TestCase
{
    protected function setUp(): void
    {
        (new MigrationService())->run();
        Db::beginTransaction();
    }

    protected function tearDown(): void
    {
        Db::rollBack();
        Context::destroy();
    }

    private function request(string $method, array $data = [], string $query = ''): SystemDict
    {
        $body = json_encode($data, JSON_THROW_ON_ERROR);
        $request = new Request("$method /admin/system/dict$query HTTP/1.1\r\nContent-Type: application/json\r\nContent-Length: " . strlen($body) . "\r\n\r\n" . $body);
        $request->context['admin'] = ['id' => 1];
        Context::set(\Webman\Http\Request::class, $request);
        return new SystemDict();
    }

    private function data(\Webman\Http\Response $response): array
    {
        $body = json_decode($response->rawBody(), true, flags: JSON_THROW_ON_ERROR);
        self::assertSame(200, $body['code'], $body['msg']);
        return $body['data'] ?? [];
    }

    public function test_fresh_install_contains_dictionary_tables_and_builtin_options(): void
    {
        self::assertTrue(Db::schema()->hasTable('dict_type'));
        self::assertTrue(Db::schema()->hasTable('dict_item'));
        $items = $this->data($this->request('GET')->options('yes_no'));
        self::assertSame(['1', '0'], array_column($items, 'value'));
    }

    public function test_type_and_item_crud_preserve_partial_updates_and_options(): void
    {
        $type = $this->data($this->request('POST', ['code' => 'delivery', 'name' => '配送状态'])->createType());
        $item = $this->data($this->request('POST', ['typeCode' => 'delivery', 'label' => '待发货', 'value' => 'pending', 'sort' => 9, 'cssClass' => 'warning'])->createItem());
        $updated = $this->data($this->request('PUT', ['label' => '准备发货'])->updateItem($item['id']));
        self::assertSame('pending', $updated['value']);
        self::assertSame(9, $updated['sort']);
        self::assertSame('准备发货', $updated['label']);
        $rows = $this->data($this->request('GET', query: '?typeCode=delivery&pageSize=10')->items());
        self::assertSame(1, $rows['total']);
        self::assertSame('delivery', $rows['list'][0]['typeCode']);
        self::assertSame(422, json_decode($this->request('DELETE')->deleteType($type['id'])->rawBody(), true)['code']);
        $this->data($this->request('PUT', ['status' => 0])->updateItem($item['id']));
        self::assertSame([], $this->data($this->request('GET')->options('delivery')));
        $this->data($this->request('DELETE', ['ids' => [$item['id']]])->deleteItems());
        $this->data($this->request('DELETE')->deleteType($type['id']));
        self::assertNotNull(Db::table('dict_type')->where('id', $type['id'])->value('deleted_at'));
    }

    public function test_batch_returns_enabled_options_and_empty_unknown_dictionary(): void
    {
        $result = $this->data($this->request('POST', ['codes' => ['yes_no', 'missing']])->batch());
        self::assertCount(2, $result['yes_no']);
        self::assertSame([], $result['missing']);
    }

    public function test_invalid_scalar_fields_are_validation_errors(): void
    {
        foreach ([['name' => []], ['name' => null], ['status' => []], ['status' => null], ['sort' => []]] as $invalid) {
            $response = $this->request('POST', array_merge(['code' => 'invalid', 'name' => '测试'], $invalid))->createType();
            self::assertSame(422, json_decode($response->rawBody(), true)['code']);
        }
    }

    public function test_dictionary_lists_filter_by_code_and_return_pagination(): void
    {
        $rows = $this->data($this->request('GET', query: '?code=yes_no')->types());
        self::assertSame(1, $rows['total']);
        self::assertSame('yes_no', $rows['list'][0]['code']);
    }

    public function test_batch_rejects_non_string_codes_without_conversion_warnings(): void
    {
        foreach ([[[]], [null], [123]] as $codes) {
            $response = $this->request('POST', ['codes' => $codes])->batch();
            self::assertSame(422, json_decode($response->rawBody(), true)['code']);
        }
    }

    public function test_dictionary_menu_and_route_permissions_are_seeded(): void
    {
        self::assertTrue(Db::table('admin_menu')->where('href', '/admin/system/dict')->exists());
        foreach (['types', 'items', 'options', 'batch', 'createtype', 'updatetype', 'deletetype', 'deletetypes', 'createitem', 'updateitem', 'deleteitem', 'deleteitems'] as $action) {
            self::assertTrue(Db::table('permission')->where('code', 'systemdict:' . $action)->exists(), $action);
        }
    }

    public function test_native_http_requires_authentication_csrf_and_exact_permissions(): void
    {
        Db::table('admin')->delete();
        Db::table('auth_group')->delete();
        Db::table('casbin_rule')->delete();
        Db::table('login_attempts')->delete();
        Db::table('admin')->insert(['id' => 1, 'username' => 'dictadmin', 'password' => password_hash('dictionary-safe-password', PASSWORD_DEFAULT), 'status' => 1]);
        (new \ReflectionProperty(\Webman\App::class, 'logger'))->setValue(null, new \Psr\Log\NullLogger());
        \Webman\Route::load([config_path()]);
        $call = static function (string $method, string $path, array $payload = [], array $session = [], bool $csrf = true): \Webman\Http\Response {
            $body = json_encode($payload, JSON_THROW_ON_ERROR);
            $headers = isset($session['cookie']) ? 'Cookie: ' . \app\admin\authentication\service\BrowserAuthService::COOKIE . '=' . $session['cookie'] . "\r\n" : '';
            if ($csrf && isset($session['csrfToken'])) $headers .= 'X-CSRF-TOKEN: ' . $session['csrfToken'] . "\r\n";
            $request = new Request("$method $path HTTP/1.1\r\n" . $headers . 'Content-Type: application/json' . "\r\nContent-Length: " . strlen($body) . "\r\n\r\n" . $body);
            $matched = \Webman\Route::dispatch($method, $path);
            self::assertSame(\FastRoute\Dispatcher::FOUND, $matched[0]);
            $request->controller = $matched[1]['callback'][0];
            $request->action = $matched[1]['callback'][1];
            $request->route = $matched[1]['route'];
            Context::set(\Webman\Http\Request::class, $request);
            return (\Webman\App::getCallback('', 'admin', $matched[1]['callback'], $matched[2], false, $request->route))($request);
        };
        self::assertSame(401, $call('GET', '/admin/system/dict/types')->getStatusCode());
        $auth = new \app\admin\authentication\service\BrowserAuthService();
        $anonymous = $auth->bootstrap('');
        $session = $auth->login($anonymous['cookie'], $anonymous['csrfToken'], 'dictadmin', 'dictionary-safe-password', 'dictionary-http');
        self::assertSame(403, $call('POST', '/admin/system/dict/types', ['code' => 'denied', 'name' => '拒绝'], $session, false)->getStatusCode());
        self::assertSame(200, $call('GET', '/admin/system/dict/types', [], $session)->getStatusCode());
        $result = $this->data($call('POST', '/admin/system/dict/types', ['code' => 'http_dictionary', 'name' => 'HTTP 字典'], $session));
        self::assertSame('http_dictionary', $result['code']);
        Db::table('admin')->insert(['id' => 2, 'username' => 'dictreader', 'password' => password_hash('dictionary-safe-password', PASSWORD_DEFAULT), 'status' => 1]);
        Db::table('auth_group')->insert(['id' => 2, 'name' => '字典读者', 'code' => 'dict_reader']);
        $casbin = new \app\admin\authorization\service\CasbinService();
        $casbin->syncAdminRoles(2, [2]);
        $casbin->syncRolePermissions(2, [Db::table('permission')->where('code', 'systemdict:types')->value('id')]);
        $anonymous = $auth->bootstrap('');
        $session = $auth->login($anonymous['cookie'], $anonymous['csrfToken'], 'dictreader', 'dictionary-safe-password', 'dictionary-reader');
        self::assertSame(200, $call('GET', '/admin/system/dict/types', [], $session)->getStatusCode());
        self::assertSame(403, $call('POST', '/admin/system/dict/types', ['code' => 'forbidden', 'name' => '越权'], $session)->getStatusCode());
    }

    public function test_duplicate_codes_including_soft_deleted_records_are_rejected(): void
    {
        $type = $this->data($this->request('POST', ['code' => 'unique_type', 'name' => '测试'])->createType());
        $this->data($this->request('DELETE')->deleteType($type['id']));
        $result = $this->request('POST', ['code' => 'unique_type', 'name' => '重复'])->createType();
        self::assertSame(422, json_decode($result->rawBody(), true)['code']);
    }
}
