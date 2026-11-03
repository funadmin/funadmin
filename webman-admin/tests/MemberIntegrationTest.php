<?php

declare(strict_types=1);

namespace Tests;

use app\admin\system\controller\SystemMember;
use app\admin\system\model\Member;
use app\admin\system\model\MemberGroup;
use app\admin\system\model\MemberLevel;
use app\common\service\database\MigrationService;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\TestCase;
use support\Context;
use support\Db;
use support\Request;

#[RunTestsInSeparateProcesses]
#[PreserveGlobalState(false)]
final class MemberIntegrationTest extends TestCase
{
    protected function setUp(): void
    {
        (new MigrationService())->run();
        Db::beginTransaction();
        Context::destroy();
    }

    protected function tearDown(): void
    {
        Db::rollBack();
        Context::destroy();
    }

    private function createMarketAccountFixture(): void
    {
        Db::schema()->create('market_token', static function (\Illuminate\Database\Schema\Blueprint $table): void {
            $table->id(); $table->integer('member_id'); $table->string('access_hash'); $table->string('refresh_hash');
            $table->integer('access_expires_at'); $table->integer('refresh_expires_at'); $table->string('ip');
            $table->dateTime('created_at'); $table->dateTime('revoked_at')->nullable();
        });
        Db::schema()->table('member', static function (\Illuminate\Database\Schema\Blueprint $table): void { $table->string('nickname')->default(''); });
        foreach (['identity_user', 'identity_member_link', 'identity_credential', 'identity_audit_log'] as $name) {
            Db::schema()->create($name, static function (\Illuminate\Database\Schema\Blueprint $table) use ($name): void {
                $table->id(); $table->integer('tenant_id'); $table->timestamps();
                if ($name === 'identity_user') {
                    foreach (['public_id', 'realm', 'username', 'display_name', 'email', 'mobile', 'avatar', 'locale', 'last_login_at'] as $field) $table->string($field)->nullable();
                    $table->integer('source_id'); $table->integer('status')->default(1); $table->integer('password_version')->default(1); $table->integer('session_version')->default(1);
                }
                if ($name === 'identity_member_link') { $table->integer('user_id'); $table->integer('member_id'); }
                if ($name === 'identity_credential') {
                    $table->integer('user_id'); $table->string('type'); $table->string('secret_hash'); $table->integer('status'); $table->softDeletes();
                }
                if ($name === 'identity_audit_log') {
                    $table->integer('user_id')->nullable(); $table->integer('client_id')->nullable();
                    foreach (['event_type', 'outcome', 'subject_hash', 'ip_hash', 'context'] as $field) $table->text($field)->nullable();
                }
            });
        }
        Db::table('member')->insert(['id' => 91, 'username' => 'market_member', 'nickname' => '市场会员', 'password' => password_hash('market-password-91', PASSWORD_DEFAULT), 'sex' => '0', 'level_id' => 1, 'status' => 1]);
        $request = new Request("POST /api/v3/auth/login HTTP/1.1\r\n\r\n");
        Context::set(\Webman\Http\Request::class, $request);
    }

    public function test_v2_member_routes_authenticate_native_requests_and_reject_wrong_token_types(): void
    {
        $this->createMarketAccountFixture();
        \Webman\Config::set('api', array_merge(config('api'), ['jwt_secret' => bin2hex(random_bytes(32)), 'refresh_jwt_secret' => bin2hex(random_bytes(32))]));
        \Webman\Limiter\Limiter::init(null);
        new \Webman\App(Request::class, new \Psr\Log\NullLogger(), app_path(), public_path());
        \Webman\Route::load([config_path()]);
        $call = static function (string $method, string $path, array $data = [], string $token = ''): \Webman\Http\Response {
            $body = json_encode($data, JSON_THROW_ON_ERROR);
            $header = $token === '' ? '' : "Authorization: Bearer $token\r\n";
            $request = new Request("$method $path HTTP/1.1\r\nHost: localhost\r\nCookie: funadmin_admin=forged\r\n" . $header . "Content-Type: application/json\r\nContent-Length: " . strlen($body) . "\r\n\r\n" . $body);
            Context::set(\Webman\Http\Request::class, $request);
            $matched = \Webman\Route::dispatch($method, $path);
            self::assertSame(\FastRoute\Dispatcher::FOUND, $matched[0], $path);
            $request->controller = $matched[1]['callback'][0];
            $request->action = $matched[1]['callback'][1];
            $request->route = $matched[1]['route'];
            return (\Webman\App::getCallback('', 'api', $matched[1]['callback'], $matched[2], false, $request->route))($request);
        };
        self::assertSame(401, $call('GET', '/v2/member')->getStatusCode());
        $response = $call('POST', '/v2/token', ['username' => 'market_member', 'password' => 'market-password-91']);
        self::assertSame(200, $response->getStatusCode(), $response->rawBody() . ' ' . (string) $response->exception());
        $tokens = json_decode($response->rawBody(), true)['data'];
        $profile = $call('GET', '/v2/member', token: $tokens['access_token']);
        self::assertSame(200, $profile->getStatusCode(), $profile->rawBody());
        self::assertSame(91, json_decode($profile->rawBody(), true)['data']['user']['id']);
        self::assertSame(401, $call('GET', '/v2/member', token: $tokens['refresh_token'])->getStatusCode());
        self::assertSame(200, $call('POST', '/v2/token/refresh', ['refresh_token' => $tokens['refresh_token']])->getStatusCode());
        Db::table('member')->where('id', 91)->update(['status' => 0]);
        self::assertSame(401, $call('GET', '/v2/member', token: $tokens['access_token'])->getStatusCode());
        self::assertSame(401, $call('POST', '/v2/token/refresh', ['refresh_token' => $tokens['refresh_token']])->getStatusCode());
    }

    public function test_market_tokens_rotate_once_and_reject_disabled_members(): void
    {
        $this->createMarketAccountFixture();
        $service = new \app\market\service\MarketTokenService();
        $tokens = $service->login('market_member', 'market-password-91');
        self::assertSame(91, $service->memberId($tokens['access_token']));
        self::assertSame(91, $service->authenticate($tokens['access_token'])['id']);
        self::assertSame(0, Db::table('market_token')->where('access_hash', $tokens['access_token'])->count());
        $next = $service->refresh($tokens['refresh_token']);
        self::assertNotNull($next);
        self::assertNull($service->refresh($tokens['refresh_token']));
        self::assertNull($service->memberId($tokens['access_token']));
        Db::table('member')->where('id', 91)->update(['status' => 0]);
        self::assertNull($service->memberId($next['access_token']));
        self::assertNull($service->authenticate($next['access_token']));
        self::assertNull($service->refresh($next['refresh_token']));
    }

    public function test_market_registration_uses_native_cache_and_commits_identity_with_member(): void
    {
        $this->createMarketAccountFixture();
        Db::schema()->table('member', static function (\Illuminate\Database\Schema\Blueprint $table): void {
            $table->string('motto')->default(''); $table->string('real_name')->default(''); $table->integer('birthday')->default(0); $table->integer('scores')->default(0);
        });
        request()->connection = (new \ReflectionClass(\Workerman\Connection\TcpConnection::class))->newInstanceWithoutConstructor();
        request()->context['session'] = new \Workerman\Protocols\Http\Session(bin2hex(random_bytes(16)));
        \support\Cache::delete('market_throttle_' . hash('sha256', 'register:127.0.0.5'));
        $service = new \app\market\service\MarketAccountService();
        $id = $service->register(['username' => 'newbuyer', 'email' => 'newbuyer@example.com', 'password' => 'new-password-91', 'password_confirm' => 'new-password-91'], '127.0.0.5');
        self::assertSame($id, $service::currentId());
        self::assertSame(1, Db::table('identity_member_link')->where('member_id', $id)->count());
        self::assertSame('newbuyer', $service::current()['username']);
        $service->logout();
        self::assertSame(0, $service::currentId());
        self::assertSame(302, (new \app\market\controller\User())->index()->getStatusCode());
        $service->login('newbuyer', 'new-password-91', '127.0.0.5');
        self::assertSame($id, $service::currentId());
        self::assertNotNull(Db::table('member')->where('id', $id)->value('last_login'));
        foreach (['market_grant', 'market_order'] as $table) {
            Db::schema()->create($table, static function (\Illuminate\Database\Schema\Blueprint $schema) use ($table): void {
                $schema->id(); $schema->integer('member_id'); $schema->integer('plugin_id'); $schema->string('status');
                if ($table === 'market_order') { $schema->dateTime('expire_at')->nullable(); $schema->string('remark')->default(''); $schema->integer('paid_amount')->default(0); $schema->string('plan')->default(''); $schema->integer('amount')->default(0); $schema->string('channel')->default(''); $schema->string('order_no')->default(''); $schema->dateTime('paid_at')->nullable(); }
                else { $schema->dateTime('expires_at')->nullable(); $schema->string('source')->default(''); $schema->string('plan')->default(''); }
                $schema->timestamps();
            });
        }
        Db::schema()->create('market_plugin', static function (\Illuminate\Database\Schema\Blueprint $table): void {
            $table->id(); foreach (['code', 'name', 'cover', 'license_type'] as $field) $table->string($field)->default(''); $table->integer('price_yearly')->default(0); $table->integer('status')->default(1);
        });
        self::assertSame(200, (new \app\market\controller\User())->index()->getStatusCode());
    }

    public function test_market_profile_and_password_changes_persist_and_revoke_tokens(): void
    {
        $this->createMarketAccountFixture();
        Db::schema()->table('member', static function (\Illuminate\Database\Schema\Blueprint $table): void { $table->string('motto')->default(''); });
        $request = request();
        $request->connection = (new \ReflectionClass(\Workerman\Connection\TcpConnection::class))->newInstanceWithoutConstructor();
        $request->context['session'] = new \Workerman\Protocols\Http\Session(bin2hex(random_bytes(16)));
        $request->session()->set('admin', ['id' => 1]);
        $tokens = (new \app\market\service\MarketTokenService())->login('market_member', 'market-password-91');
        $service = new \app\market\service\MarketAccountService();
        $service->updateProfile(91, ['nickname' => '更新昵称', 'motto' => '介绍', 'avatar' => 'https://example.com/avatar.png']);
        self::assertSame('更新昵称', $service->profile(91)['nickname']);
        $service->changePassword(91, 'market-password-91', 'new-password-92', 'new-password-92');
        self::assertTrue(password_verify('new-password-92', Db::table('member')->where('id', 91)->value('password')));
        self::assertSame(0, Db::table('market_token')->whereNull('revoked_at')->count());
        self::assertSame(1, request()->session()->get('admin')['id']);
        self::assertNull((new \app\market\service\MarketTokenService())->memberId($tokens['access_token']));
    }

    public function test_market_auth_routes_use_native_requests_and_never_accept_admin_cookie_as_member(): void
    {
        new \Webman\App(Request::class, new \Psr\Log\NullLogger(), app_path(), public_path());
        \Webman\Route::load([config_path()]);
        foreach ([['GET', '/api/v3/plugins/categories'], ['POST', '/api/v3/plugins'], ['POST', '/api/v3/plugins/example/download'], ['GET', '/api/v3/packages/1/1/1/' . str_repeat('a', 64)], ['POST', '/api/v3/telemetry/installs']] as [$method, $path]) {
            self::assertSame(\FastRoute\Dispatcher::FOUND, \Webman\Route::dispatch($method, $path)[0], $path);
        }
        $matched = \Webman\Route::dispatch('POST', '/api/v3/auth/me');
        self::assertSame(\FastRoute\Dispatcher::FOUND, $matched[0]);
        $request = new Request("POST /api/v3/auth/me HTTP/1.1\r\nCookie: funadmin_admin=forged\r\n\r\n");
        Context::set(\Webman\Http\Request::class, $request);
        $response = (new \app\market\controller\Auth())->me();
        self::assertSame(401, $response->getStatusCode());
        self::assertSame(401, json_decode($response->rawBody(), true)['code']);
        $response = (new \app\market\controller\Auth())->login();
        self::assertSame(422, $response->getStatusCode());
        $request = new Request("GET /api/v3/plugins/categories HTTP/1.1\r\n\r\n");
        Context::set(\Webman\Http\Request::class, $request);
        self::assertSame(401, (new \app\market\controller\Plugins())->categories()->getStatusCode());
        self::assertSame(422, (new \app\market\controller\Telemetry())->installs()->getStatusCode());
    }

    public function test_market_order_settlement_is_atomic_and_idempotent(): void
    {
        Db::schema()->create('market_order', static function (\Illuminate\Database\Schema\Blueprint $table): void {
            $table->id(); $table->string('order_no'); $table->integer('member_id'); $table->integer('plugin_id'); $table->string('plan');
            $table->integer('amount'); $table->integer('paid_amount')->default(0); $table->string('channel'); $table->string('status')->default('pending');
            $table->string('trade_no')->nullable(); $table->integer('grant_id')->nullable(); $table->string('remark')->default(''); $table->dateTime('paid_at')->nullable(); $table->timestamps();
        });
        Db::schema()->create('market_grant', static function (\Illuminate\Database\Schema\Blueprint $table): void {
            $table->id(); $table->integer('plugin_id'); $table->integer('member_id'); $table->integer('order_id'); $table->integer('status');
            $table->string('source'); $table->string('plan'); $table->string('remark'); $table->dateTime('expires_at')->nullable(); $table->timestamps();
            $table->unique(['plugin_id', 'member_id']);
        });
        Db::table('market_order')->insert(['order_no' => '202611030000000000001', 'member_id' => 91, 'plugin_id' => 5, 'plan' => 'yearly', 'amount' => 1200, 'channel' => 'mock']);
        $service = new \app\market\service\MarketOrderService();
        try {
            $service->markPaid('202611030000000000001', 'mock', 'trade', 100);
            self::fail('金额不一致不得开通授权');
        } catch (\RuntimeException $error) {
            self::assertSame('支付金额与订单金额不一致', $error->getMessage());
        }
        self::assertSame(0, Db::table('market_grant')->count());
        self::assertSame('pending', Db::table('market_order')->value('status'));
        self::assertTrue($service->markPaid('202611030000000000001', 'mock', 'trade', 1200));
        $expires = Db::table('market_grant')->value('expires_at');
        self::assertTrue($service->markPaid('202611030000000000001', 'mock', 'trade', 1200));
        self::assertSame(1, Db::table('market_grant')->count());
        self::assertSame($expires, Db::table('market_grant')->value('expires_at'));
        Db::schema()->create('market_plugin', static function (\Illuminate\Database\Schema\Blueprint $table): void {
            $table->id(); $table->string('code'); $table->string('name'); $table->string('cover')->default('');
        });
        Db::schema()->table('market_order', static function (\Illuminate\Database\Schema\Blueprint $table): void { $table->dateTime('expire_at')->nullable(); });
        Db::schema()->table('member', static function (\Illuminate\Database\Schema\Blueprint $table): void { $table->string('nickname')->default(''); });
        Db::table('market_plugin')->insert(['id' => 5, 'code' => 'paid', 'name' => '付费插件']);
        Db::table('member')->insert(['id' => 91, 'username' => 'buyer', 'sex' => '0', 'level_id' => 1, 'status' => 1]);
        $admin = new \app\admin\plugin\business\market\service\MarketAdminService();
        Db::schema()->create('market_payment_log', static function (\Illuminate\Database\Schema\Blueprint $table): void {
            $table->id(); $table->string('order_no'); $table->string('channel'); $table->string('event'); $table->boolean('verified'); $table->string('result'); $table->dateTime('created_at');
        });
        self::assertSame('buyer', $admin->orderDetail('202611030000000000001')['username']);
        $admin->saveGrant(null, ['pluginId' => 5, 'account' => 'buyer']);
        self::assertSame(1, $admin->grants(5, 1, 10)['total']);
        self::assertSame(1, $admin->orders([], 1, 10)['total']);
        self::assertSame(1200, $admin->orders([], 1, 10)['summary']['paidAmount']);
        self::assertSame(91, $service->activeGrant(5, 91)['member_id']);
        self::assertNull($service->activeGrant(5, 92));
    }

    public function test_market_catalog_filters_published_versions_and_member_grants(): void
    {
        Db::schema()->create('market_category', static function (\Illuminate\Database\Schema\Blueprint $table): void {
            $table->id(); $table->string('name'); $table->integer('sort')->default(0); $table->integer('status')->default(1);
        });
        Db::schema()->create('market_plugin', static function (\Illuminate\Database\Schema\Blueprint $table): void {
            $table->id(); foreach (['code', 'name', 'description', 'author', 'license_type'] as $field) $table->string($field)->default('');
            $table->integer('category_id')->default(1); $table->integer('sort')->default(0); $table->integer('status')->default(1);
            $table->integer('price_yearly')->default(0); $table->integer('price_perpetual')->default(0);
        });
        Db::schema()->create('market_version', static function (\Illuminate\Database\Schema\Blueprint $table): void {
            $table->id(); $table->integer('plugin_id'); $table->string('code_version'); $table->string('status');
            foreach (['sha256', 'signature', 'tree_hash', 'database_capability', 'requires'] as $field) $table->string($field)->default('');
            $table->integer('size')->default(0); $table->integer('download_count')->default(0); $table->integer('app_enabled')->default(1); $table->integer('admin_enabled')->default(0);
        });
        Db::schema()->create('market_grant', static function (\Illuminate\Database\Schema\Blueprint $table): void {
            $table->id(); $table->integer('plugin_id'); $table->integer('member_id'); $table->integer('status'); $table->dateTime('expires_at')->nullable();
        });
        Db::table('market_category')->insert(['name' => '分类']);
        Db::table('market_plugin')->insert([['id' => 1, 'code' => 'visible', 'name' => 'Visible', 'license_type' => 'grant'], ['id' => 2, 'code' => 'draft', 'name' => 'Draft', 'license_type' => 'free']]);
        Db::table('market_version')->insert([['plugin_id' => 1, 'code_version' => '1.0.0', 'status' => 'published', 'database_capability' => '001'], ['plugin_id' => 2, 'code_version' => '1.0.0', 'status' => 'draft', 'database_capability' => '001']]);
        Context::set(\Webman\Http\Request::class, new Request("GET /api/v3/plugins HTTP/1.1\r\nHost: localhost\r\n\r\n"));
        $service = new \app\market\service\MarketCatalogService();
        $context = ['manifest_schema' => 2, 'package_format' => \app\market\service\MarketProtocol::PACKAGE_FORMAT];
        self::assertSame('分类', $service->categories()[0]['name']);
        $result = $service->search($context, '', 0, 1, 10);
        self::assertSame(1, $result['total']); self::assertSame('visible', $result['items'][0]['code']);
        self::assertFalse($service->authorize(91, 'visible', '1.0.0', '', $context)['authorized']);
        Db::table('market_grant')->insert(['plugin_id' => 1, 'member_id' => 92, 'status' => 1]);
        self::assertFalse($service->authorize(91, 'visible', '1.0.0', '', $context)['authorized']);
        self::assertTrue($service->authorize(92, 'visible', '1.0.0', '', $context)['authorized']);
        self::assertFalse($service->authorize(92, 'visible', '1.0.0', '002', $context)['authorized']);
        $admin = new \app\admin\plugin\business\market\service\MarketAdminService();
        self::assertSame(2, $admin->plugins('', 1, 20)['total']);
        self::assertSame(1, $admin->plugins('Visible', 1, 20)['total']);
        self::assertCount(1, $admin->versions(1));
        Db::schema()->table('market_plugin', static function (\Illuminate\Database\Schema\Blueprint $table): void { $table->string('cover')->default(''); $table->string('homepage')->default(''); });
        Db::schema()->table('market_version', static function (\Illuminate\Database\Schema\Blueprint $table): void { $table->string('changelog')->default(''); $table->dateTime('published_at')->nullable(); });
        Db::schema()->create('market_site_plugin', static function (\Illuminate\Database\Schema\Blueprint $table): void { $table->id(); $table->integer('plugin_id'); $table->string('state'); });
        $store = new \app\market\service\MarketStoreService();
        self::assertSame(1, $store->listing('', 0, 1)['total']);
        self::assertTrue($store->detail('visible', 92)['owned']);
        self::assertNull($store->detail('draft', 92));
    }

    public function test_payment_callbacks_accept_native_requests_and_reject_unsigned_notifications(): void
    {
        $request = new Request("POST /market/pay/notify/alipay HTTP/1.1\r\nContent-Type: application/x-www-form-urlencoded\r\nContent-Length: 12\r\n\r\napp_id=wrong");
        $alipay = new \app\market\service\payment\AlipayChannel(['app_id' => 'expected', 'alipay_public_key' => '']);
        self::assertNull($alipay->parseNotify($request));
        self::assertSame('fail', $alipay->notifyAck(false)->rawBody());
        $wechat = new \app\market\service\payment\WechatChannel(['platform_public_key_id' => 'expected']);
        self::assertNull($wechat->parseNotify($request));
        self::assertSame(500, $wechat->notifyAck(false)->getStatusCode());
        self::assertSame(204, $wechat->notifyAck(true)->getStatusCode());
        self::assertNull((new \app\market\service\payment\MockChannel())->parseNotify($request));
    }

    public function test_store_login_page_renders_with_native_session_and_escaped_return_url(): void
    {
        $request = new Request("GET /market/login?next=https%3A%2F%2Fevil.example HTTP/1.1\r\n\r\n");
        $request->context['session'] = new \Workerman\Protocols\Http\Session(bin2hex(random_bytes(16)));
        Context::set(\Webman\Http\Request::class, $request);
        $response = (new \app\market\controller\Account())->loginForm();
        self::assertSame(200, $response->getStatusCode());
        self::assertStringContainsString('name="_csrf"', $response->rawBody());
        self::assertStringNotContainsString('{extend', $response->rawBody());
        self::assertStringNotContainsString('evil.example', $response->rawBody());
        self::assertStringContainsString('<style>', $response->rawBody());
        self::assertStringNotContainsString('/store.css', $response->rawBody());
        self::assertStringNotContainsString('/store.js', $response->rawBody());
        self::assertSame('/market', \app\market\service\MarketSettings::publicUrl());
        putenv('MARKET_PUBLIC_URL=https://market.example/market');
        self::assertSame('https://market.example/market', \app\market\service\MarketSettings::publicUrl());
        putenv('MARKET_PUBLIC_URL');
        \Webman\Route::load([config_path()]);
        self::assertSame(\FastRoute\Dispatcher::FOUND, \Webman\Route::dispatch('POST', '/api/v3/auth/me')[0]);
        self::assertSame(\FastRoute\Dispatcher::FOUND, \Webman\Route::dispatch('POST', '/market/api/v3/auth/login')[0]);
        self::assertSame('DENY', $response->getHeader('X-Frame-Options'));
        foreach ([['GET', '/market'], ['GET', '/market/plugin/example'], ['GET', '/market/user'], ['POST', '/market/order'], ['GET', '/market/order/202611030000000000001'], ['POST', '/market/pay/notify/alipay']] as [$method, $path]) {
            self::assertSame(\FastRoute\Dispatcher::FOUND, \Webman\Route::dispatch($method, $path)[0], $path);
        }
    }

    public function test_market_telemetry_updates_current_state_without_inflating_active_counts(): void
    {
        foreach (['market_plugin', 'market_site', 'market_site_plugin', 'market_install_event', 'market_version', 'market_download', 'market_order'] as $name) {
            Db::schema()->create($name, static function (\Illuminate\Database\Schema\Blueprint $table) use ($name): void {
                $table->id();
                if ($name === 'market_plugin') { $table->string('code'); $table->string('name'); }
                if (in_array($name, ['market_site', 'market_site_plugin', 'market_install_event'], true)) $table->string('site_id');
                if (in_array($name, ['market_site_plugin', 'market_install_event', 'market_version', 'market_order'], true)) $table->integer('plugin_id');
                if (in_array($name, ['market_site', 'market_install_event'], true)) $table->integer('member_id')->default(0);
                if ($name === 'market_site') { foreach (['platform_version', 'php_version', 'ip', 'last_seen_at', 'first_seen_at'] as $field) $table->string($field); $table->unique('site_id'); }
                if ($name === 'market_site_plugin') { $table->string('state'); $table->string('version'); $table->dateTime('installed_at'); $table->dateTime('updated_at'); $table->unique(['site_id', 'plugin_id']); }
                if ($name === 'market_install_event') { foreach (['event', 'version', 'from_version', 'occurred_at', 'created_at'] as $field) $table->string($field); }
                if ($name === 'market_version') $table->integer('download_count')->default(0);
                if ($name === 'market_download') $table->dateTime('created_at');
                if ($name === 'market_order') { $table->string('status'); $table->dateTime('paid_at')->nullable(); $table->integer('paid_amount')->default(0); }
            });
        }
        Db::table('market_plugin')->insert(['id' => 1, 'code' => 'example', 'name' => '示例']);
        Context::set(\Webman\Http\Request::class, new Request("POST /api/v3/telemetry/installs HTTP/1.1\r\n\r\n"));
        $service = new \app\market\service\MarketStatsService();
        $site = '00000000-0000-4000-8000-000000000001';
        self::assertSame(1, $service->ingest($site, 1, [], [['code' => 'example', 'event' => 'install', 'version' => '1.0.0']]));
        self::assertSame(1, $service->ingest($site, 1, [], [['code' => 'example', 'event' => 'enable', 'version' => '1.0.0']]));
        self::assertSame(1, $service->overview()['totals']['activeInstalls']);
        self::assertSame(1, $service->overview()['totals']['enabledInstalls']);
        self::assertSame(1, $service->overview()['top'][0]['activeInstalls']);
    }

    public function test_member_index_filters_keyword_without_thinkphp_query_methods(): void
    {
        Db::table('member')->insert([
            ['id' => 1, 'username' => 'member_one', 'mobile' => '13800138000', 'sex' => '0', 'level_id' => 1, 'status' => 1],
            ['id' => 2, 'username' => 'other_member', 'mobile' => '13900139000', 'sex' => '0', 'level_id' => 1, 'status' => 1],
        ]);
        $request = new Request("GET /admin/system/member?keyword=member_one&page=1&pageSize=20 HTTP/1.1\r\n\r\n");
        $request->context['admin'] = ['id' => 1];
        Context::set(\Webman\Http\Request::class, $request);
        $payload = json_decode((new SystemMember())->index()->rawBody(), true, flags: JSON_THROW_ON_ERROR);
        self::assertSame(1, $payload['data']['total']);
        self::assertSame('member_one', $payload['data']['list'][0]['username']);
    }
    public function test_member_keyword_matches_mobile_and_email_in_controller_and_model(): void
    {
        Db::table('member')->insert([
            ['id' => 1, 'username' => 'phone_member', 'mobile' => '13800138000', 'email' => 'phone@example.com', 'sex' => '0', 'level_id' => 1, 'status' => 1],
            ['id' => 2, 'username' => 'email_member', 'mobile' => '13900139000', 'email' => 'target@example.com', 'sex' => '0', 'level_id' => 1, 'status' => 1],
        ]);
        foreach (['13800138000' => 'phone_member', 'target@example.com' => 'email_member'] as $keyword => $username) {
            $request = new Request('GET /admin/system/member?keyword=' . urlencode((string) $keyword) . " HTTP/1.1\r\n\r\n");
            $request->context['admin'] = ['id' => 1];
            Context::set(\Webman\Http\Request::class, $request);
            $payload = json_decode((new SystemMember())->index()->rawBody(), true, flags: JSON_THROW_ON_ERROR);
            self::assertSame(1, $payload['data']['total']);
            self::assertSame($username, $payload['data']['list'][0]['username']);
            self::assertSame([$username], Member::filteredQuery(false, (string) $keyword)->pluck('username')->all());
        }
    }

    public function test_member_second_page_preserves_total_matching_rows(): void
    {
        Db::table('member')->insert([
            ['id' => 1, 'username' => 'page_one', 'mobile' => '13800138000', 'sex' => '0', 'level_id' => 1, 'status' => 1],
            ['id' => 2, 'username' => 'page_two', 'mobile' => '13900139000', 'sex' => '0', 'level_id' => 1, 'status' => 1],
        ]);
        $request = new Request("GET /admin/system/member?page=2&pageSize=1 HTTP/1.1\r\n\r\n");
        $request->context['admin'] = ['id' => 1];
        Context::set(\Webman\Http\Request::class, $request);
        $payload = json_decode((new SystemMember())->index()->rawBody(), true, flags: JSON_THROW_ON_ERROR);
        self::assertSame(2, $payload['data']['total']);
        self::assertSame('page_one', $payload['data']['list'][0]['username']);
    }

    public function test_member_group_filter_uses_native_relations_for_index_and_export(): void
    {
        Db::table('member')->insert([
            ['id' => 1, 'username' => 'group_one', 'mobile' => '13800138000', 'sex' => '0', 'level_id' => 1, 'status' => 1],
            ['id' => 2, 'username' => 'group_two', 'mobile' => '13900139000', 'sex' => '0', 'level_id' => 1, 'status' => 1],
        ]);
        Db::table('member_group')->insert(['id' => 2, 'name' => '筛选组', 'status' => 1]);
        Db::table('member_group_relation')->insert(['member_id' => 1, 'group_id' => 2]);
        $request = new Request("GET /admin/system/member?groupId=2&status=1&levelId=1 HTTP/1.1\r\n\r\n");
        $request->context['admin'] = ['id' => 1];
        Context::set(\Webman\Http\Request::class, $request);
        $controller = new SystemMember();
        $payload = json_decode($controller->index()->rawBody(), true, flags: JSON_THROW_ON_ERROR);
        self::assertSame(1, $payload['data']['total']);
        self::assertSame('group_one', $payload['data']['list'][0]['username']);
        $export = json_decode($controller->export()->rawBody(), true, flags: JSON_THROW_ON_ERROR);
        self::assertSame(['group_one'], array_column($export['data'], 'username'));
        self::assertSame([1], Member::filteredQuery(false, status: 1, groupId: 2, levelId: 1)->pluck('id')->all());
    }

    public function test_member_group_relation_reads_member_groups_from_pivot_keys(): void
    {
        Db::table('member')->insert(['id' => 1, 'username' => 'relation_member', 'mobile' => '13800138001', 'sex' => '0', 'level_id' => 1, 'status' => 1]);
        Db::table('member_group')->insert(['id' => 2, 'name' => '关联组', 'status' => 1]);
        Db::table('member_group_relation')->insert(['member_id' => 1, 'group_id' => 2]);
        $groups = Member::find(1)->groups()->pluck('member_group.id')->all();
        self::assertSame([2], array_map('intval', $groups));
    }

    public function test_member_models_use_logical_tables_and_index_returns_native_pagination(): void
    {
        self::assertSame('member', (new Member())->getTable());
        self::assertSame('member_group', (new MemberGroup())->getTable());
        self::assertSame('member_level', (new MemberLevel())->getTable());
        Db::table('member')->insert(['id' => 1, 'username' => 'member_one', 'mobile' => '13800138000', 'sex' => '0', 'level_id' => 1, 'status' => 1]);
        $body = '{}';
        $request = new Request("GET /admin/system/member?page=1&pageSize=20 HTTP/1.1\r\nContent-Type: application/json\r\nContent-Length: " . strlen($body) . "\r\n\r\n" . $body);
        $request->context['admin'] = ['id' => 1];
        Context::set(\Webman\Http\Request::class, $request);
        $response = (new SystemMember())->index();
        self::assertSame(200, $response->getStatusCode());
        self::assertSame(1, json_decode($response->rawBody(), true, flags: JSON_THROW_ON_ERROR)['data']['total']);
    }
}
