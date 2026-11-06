<?php

declare(strict_types=1);
namespace Tests;

use app\admin\system\controller\SystemLanguage;
use app\common\service\database\MigrationService;
use PHPUnit\Framework\TestCase;
use support\Context;
use support\Db;
use support\Request;

#[\PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses]
#[\PHPUnit\Framework\Attributes\PreserveGlobalState(false)]
final class LanguageIntegrationTest extends TestCase
{
    protected function setUp(): void { (new MigrationService())->run(); Db::beginTransaction(); }
    protected function tearDown(): void { Db::rollBack(); Context::destroy(); }
    private function controller(array $payload = [], string $query = ''): SystemLanguage
    {
        $body = json_encode($payload);
        $request = new Request("POST /admin/system/language$query HTTP/1.1\r\nContent-Type: application/json\r\nContent-Length: " . strlen($body) . "\r\n\r\n" . $body);
        $request->context['admin'] = ['id' => 1];
        Context::set(\Webman\Http\Request::class, $request);
        return new SystemLanguage();
    }
    private function payloadOf(\Webman\Http\Response $response): array { return json_decode($response->rawBody(), true); }
    public function test_languages_list_default_protection_and_registration(): void
    {
        $list = $this->payloadOf($this->controller()->index());
        self::assertSame(200, $list['code']);
        self::assertContains('zh-cn', array_column($list['data']['list'], 'name'));
        $default = Db::table('language')->where('name', 'zh-cn')->value('id');
        self::assertSame(422, $this->payloadOf($this->controller(['name' => 'fr-fr'])->update($default))['code']);
        $created = $this->payloadOf($this->controller(['name' => 'fr-fr'])->create());
        self::assertSame(200, $created['code']);
        self::assertSame('fr-fr', $created['data']['name']);
    }
    public function test_translation_update_delete_and_restore_change_pack_version(): void
    {
        $saved = $this->payloadOf($this->controller(['locale' => 'en-us', 'key' => 'common.search', 'value' => 'FindNow'])->saveLine());
        self::assertSame(200, $saved['code']);
        $first = $this->payloadOf($this->controller(query: '?locale=en-us')->pack())['data'];
        self::assertSame('FindNow', $first['messages']['common.search']);
        $this->controller(['locale' => 'en-us', 'key' => 'common.search', 'value' => 'FindAgain'])->saveLine();
        $second = $this->payloadOf($this->controller(query: '?locale=en-us&version=' . $first['version'])->pack())['data'];
        self::assertNotEquals($first['version'], $second['version']);
        self::assertSame('FindAgain', $second['messages']['common.search']);
        $removed = $this->payloadOf($this->controller(query: '?id=' . $saved['data']['id'])->removeLine());
        self::assertSame(200, $removed['code']);
        $third = $this->payloadOf($this->controller(query: '?locale=en-us')->pack())['data'];
        self::assertArrayNotHasKey('common.search', $third['messages']);
        $restored = $this->payloadOf($this->controller(['locale' => 'en-us', 'key' => 'common.search', 'value' => 'Restored'])->saveLine());
        self::assertSame($saved['data']['id'], $restored['data']['id']);
    }
    public function test_translation_namespace_and_keyword_filter(): void
    {
        $this->controller(['locale' => 'en-us', 'key' => 'crud.orders.name', 'value' => 'Orders'])->saveLine();
        $list = $this->payloadOf($this->controller(query: '?locale=en-us&keyword=Orders&ns=crud.orders')->lines());
        self::assertSame(1, $list['data']['total']);
        self::assertSame(['crud.orders'], $this->payloadOf($this->controller(query: '?locale=en-us')->namespaces())['data']);
    }
    public function test_registered_language_name_must_be_a_locale_code(): void
    {
        self::assertSame(422, $this->payloadOf($this->controller(['name' => 'not a locale'])->create())['code']);
    }

    public function test_rename_moves_translations_and_delete_removes_their_pack(): void
    {
        $language = $this->payloadOf($this->controller(['name' => 'fr-fr'])->create())['data'];
        $this->controller(['locale' => 'fr-fr', 'key' => 'common.search', 'value' => 'Chercher'])->saveLine();
        $this->controller(['name' => 'fr-ca'])->update($language['id']);
        $pack = $this->payloadOf($this->controller(query: '?locale=fr-ca')->pack());
        self::assertSame('Chercher', $pack['data']['messages']['common.search']);
        $this->controller()->delete($language['id']);
        $pack = $this->payloadOf($this->controller(query: '?locale=fr-ca')->pack());
        self::assertSame([], $pack['data']['messages']);
    }

    public function test_language_menu_and_login_only_pack_permission_are_seeded(): void
    {
        self::assertTrue(Db::table('admin_menu')->where('href', '/admin/system/language')->exists());
        self::assertSame(1, (int) Db::table('permission')->where('code', 'system:language:pack')->value('is_public'));
        self::assertSame(0, (int) Db::table('permission')->where('code', 'system:language:save-line')->value('is_public'));
    }

    public function test_pack_negotiation_returns_unchanged_for_identical_content(): void
    {
        $first = $this->payloadOf($this->controller(query: '?locale=en-us')->pack())['data'];
        $second = $this->payloadOf($this->controller(query: '?locale=en-us&version=' . $first['version'])->pack())['data'];
        self::assertTrue($second['unchanged']);
    }

    public function test_locale_query_arrays_are_validation_errors(): void
    {
        foreach (['pack', 'lines', 'namespaces'] as $method) {
            self::assertSame(422, $this->payloadOf($this->controller(query: '?locale[]=en-us')->$method())['code']);
        }
    }

    public function test_native_http_pack_is_login_only_but_translation_writes_need_grants_and_csrf(): void
    {
        Db::table('admin')->delete();
        Db::table('login_attempts')->delete();
        Db::table('admin')->insert(['id' => 22, 'username' => 'language_reader', 'password' => password_hash('language-safe-password', PASSWORD_DEFAULT), 'status' => 1]);
        (new \ReflectionProperty(\Webman\App::class, 'logger'))->setValue(null, new \Psr\Log\NullLogger());
        \Webman\Route::load([config_path()]);
        $auth = new \app\admin\authentication\service\BrowserAuthService();
        $anonymous = $auth->bootstrap('');
        $session = $auth->login($anonymous['cookie'], $anonymous['csrfToken'], 'language_reader', 'language-safe-password', 'language-http');
        $call = static function (string $method, string $path, bool $authenticated = true, bool $csrf = true) use ($session): \Webman\Http\Response {
            $headers = $authenticated ? 'Cookie: ' . \app\admin\authentication\service\BrowserAuthService::COOKIE . '=' . $session['cookie'] . "\r\n" : '';
            if ($csrf) $headers .= 'X-CSRF-TOKEN: ' . $session['csrfToken'] . "\r\n";
            $request = new Request("$method $path HTTP/1.1\r\n" . $headers . "\r\n");
            $matched = \Webman\Route::dispatch($method, explode('?', $path)[0]);
            self::assertSame(\FastRoute\Dispatcher::FOUND, $matched[0]);
            $request->controller = $matched[1]['callback'][0];
            $request->action = $matched[1]['callback'][1];
            $request->route = $matched[1]['route'];
            Context::set(\Webman\Http\Request::class, $request);
            return (\Webman\App::getCallback('', 'admin', $matched[1]['callback'], $matched[2], false, $request->route))($request);
        };
        self::assertSame(401, $call('GET', '/admin/system/language/pack?locale=en-us', false)->getStatusCode());
        self::assertSame(200, $call('GET', '/admin/system/language/pack?locale=en-us')->getStatusCode());
        self::assertSame(403, $call('POST', '/admin/system/language/lines')->getStatusCode());
        $role = Db::table('auth_group')->insertGetId(['name' => '语言维护', 'code' => 'language_maintainer']);
        $casbin = new \app\admin\authorization\service\CasbinService();
        $casbin->syncAdminRoles(22, [$role]);
        $casbin->syncRolePermissions($role, [Db::table('permission')->where('code', 'system:language:save-line')->value('id')]);
        self::assertSame(403, $call('POST', '/admin/system/language/lines', csrf: false)->getStatusCode());
    }

    public function test_invalid_translation_keys_and_values_are_rejected(): void
    {
        foreach ([['key' => '__proto__.polluted'], ['locale' => []], ['value' => []], ['name' => []]] as $invalid) {
            if (array_key_exists('name', $invalid)) $response = $this->controller($invalid)->create();
            else $response = $this->controller(array_merge(['locale' => 'en-us', 'key' => 'common.search', 'value' => 'Query'], $invalid))->saveLine();
            self::assertSame(422, $this->payloadOf($response)['code']);
        }
    }
}
