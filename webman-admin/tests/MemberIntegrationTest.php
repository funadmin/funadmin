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

    public function test_market_tokens_rotate_once_and_reject_disabled_members(): void
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
