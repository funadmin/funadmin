<?php

declare(strict_types=1);

namespace Tests;

use app\admin\authorization\controller\SystemAdmin;
use app\admin\authorization\service\CasbinService;
use app\admin\system\controller\SystemDashboard;
use app\admin\system\service\DashboardOverviewService;
use app\common\auth\AuthRequirementResolver;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\TestCase;
use support\Cache;
use support\Context;
use support\Db;
use support\Request;
use Webman\Route;

/**
 * 仪表盘系统概览：每块数据按对应列表页的查看权限返回，统计排除软删除并受数据范围约束，按管理员缓存 60 秒。
 */
#[RunTestsInSeparateProcesses]
#[PreserveGlobalState(false)]
final class DashboardOverviewTest extends TestCase
{
    private const DELETED = '2026-01-01 00:00:00';

    protected function setUp(): void
    {
        \Tests\Support\TestDatabase::migrate();
        Db::beginTransaction();
        $this->asActor(1);
    }

    protected function tearDown(): void
    {
        foreach ([1, 2] as $id) {
            $this->asActor($id);
            Cache::delete(DashboardOverviewService::cacheKey($id));
        }
        Db::rollBack();
        Context::destroy();
    }

    private function asActor(int $id): void
    {
        $request = new Request("GET /admin/dashboard/overview HTTP/1.1\r\n\r\n");
        $request->context['admin'] = ['id' => $id, 'username' => 'actor' . $id];
        Context::set(\Webman\Http\Request::class, $request);
    }

    private function overview(int $id): array
    {
        $this->asActor($id);
        Cache::delete(DashboardOverviewService::cacheKey($id));
        $result = json_decode((new SystemDashboard())->overview()->rawBody(), true, flags: JSON_THROW_ON_ERROR);
        self::assertSame(200, $result['code'], (string) ($result['msg'] ?? ''));
        return $result['data'];
    }

    /** 管理员 2：角色 2（仅本人数据），只授予给定权限码。 */
    private function limitedAdmin(array $codes, string $dataScope = 'self'): void
    {
        Db::table('admin')->insertOrIgnore(['id' => 2, 'username' => 'viewer', 'password' => 'x', 'status' => 1]);
        Db::table('auth_group')->insertOrIgnore(['id' => 2, 'name' => '查看', 'code' => 'viewer', 'level' => 10, 'data_scope' => $dataScope, 'status' => 1]);
        $casbin = new CasbinService();
        $casbin->syncAdminRoles(2, [2]);
        $casbin->syncRolePermissions(2, Db::table('permission')->whereIn('code', $codes ?: ['-'])->pluck('id')->all());
    }

    public function test_route_requires_login_only_and_is_registered(): void
    {
        Route::load([config_path()]);
        $matched = Route::dispatch('GET', '/admin/dashboard/overview');
        self::assertSame(\FastRoute\Dispatcher::FOUND, $matched[0]);
        self::assertSame([SystemDashboard::class, 'overview'], $matched[1]['callback']);
        $requirement = (new AuthRequirementResolver())->for(SystemDashboard::class, 'overview');
        self::assertTrue($requirement->login);
        self::assertFalse($requirement->permission);
    }

    public function test_super_admin_sees_every_block_with_soft_deleted_rows_excluded(): void
    {
        $live = static fn (string $table): int => Db::table($table)->whereNull('deleted_at')->count();
        $before = ['admin' => $live('admin'), 'member' => $live('member'), 'attach' => $live('attach'), 'plugin' => $live('plugin')];
        $sizeBefore = (int) Db::table('attach')->whereNull('deleted_at')->sum('size');
        $enabledBefore = Db::table('plugin')->whereNull('deleted_at')->where('lifecycle_state', 'enabled')->count();
        $todayMembersBefore = Db::table('member')->whereNull('deleted_at')->where('created_at', '>=', date('Y-m-d 00:00:00'))->count();

        Db::table('admin')->insert([
            ['id' => 31, 'username' => 'live_admin', 'password' => 'x', 'status' => 1, 'deleted_at' => null],
            ['id' => 32, 'username' => 'gone_admin', 'password' => 'x', 'status' => 1, 'deleted_at' => self::DELETED],
        ]);
        Db::table('member')->insert([
            ['username' => 'm_today', 'level_id' => 0, 'created_at' => date('Y-m-d H:i:s'), 'deleted_at' => null],
            ['username' => 'm_old', 'level_id' => 0, 'created_at' => '2020-01-01 00:00:00', 'deleted_at' => null],
            ['username' => 'm_gone', 'level_id' => 0, 'created_at' => date('Y-m-d H:i:s'), 'deleted_at' => self::DELETED],
        ]);
        Db::table('attach')->insert([
            ['name' => 'a.png', 'size' => 100, 'deleted_at' => null],
            ['name' => 'b.png', 'size' => 50, 'deleted_at' => null],
            ['name' => 'c.png', 'size' => 9999, 'deleted_at' => self::DELETED],
        ]);
        Db::table('plugin')->insert([
            ['code' => 'dashon', 'lifecycle_state' => 'enabled', 'deleted_at' => null],
            ['code' => 'dashoff', 'lifecycle_state' => 'disabled', 'deleted_at' => null],
            ['code' => 'dashgone', 'lifecycle_state' => 'enabled', 'deleted_at' => self::DELETED],
        ]);

        $data = $this->overview(1);

        self::assertSame(['total' => $before['admin'] + 1], $data['admins']);
        self::assertSame(['total' => $before['member'] + 2, 'today' => $todayMembersBefore + 1], $data['members']);
        self::assertSame(['total' => $before['attach'] + 2, 'size' => $sizeBefore + 150], $data['attachments']);
        self::assertSame(['installed' => $before['plugin'] + 2, 'enabled' => $enabledBefore + 1], $data['plugins']);
        self::assertArrayHasKey('logs', $data);
        self::assertIsString($data['generatedAt']);
        // 管理员数与管理员列表的总数一致（同一管理范围）。
        $list = json_decode((new SystemAdmin())->index()->rawBody(), true);
        self::assertSame($list['data']['total'], $data['admins']['total']);
    }

    public function test_logs_block_has_seven_day_trend_and_ten_summary_rows_without_request_bodies(): void
    {
        Db::table('admin_log')->delete();
        $day = static fn (int $offset, string $time = '10:00:00'): string => date('Y-m-d', strtotime("-$offset days")) . ' ' . $time;
        // 先写较早的日志：最近日志与操作日志列表一样按 id 倒序。
        $rows = [
            ['admin_id' => 1, 'username' => 'root', 'name' => '七天前不计入', 'created_at' => $day(7, '23:59:59')],
            ['admin_id' => 1, 'username' => 'root', 'name' => '六天前', 'created_at' => $day(6, '00:00:00')],
            ['admin_id' => 1, 'username' => 'root', 'name' => '两天前', 'created_at' => $day(2)],
            ['admin_id' => 1, 'username' => 'root', 'name' => '两天前', 'created_at' => $day(2, '23:59:59')],
        ];
        for ($i = 0; $i < 12; $i++) {
            $rows[] = ['admin_id' => 1, 'username' => 'root', 'name' => "今日$i", 'action' => 'index', 'method' => 'get', 'url' => '/admin/x', 'ip' => '127.0.0.1',
                'status' => 1, 'get_data' => 'secret-get', 'post_data' => 'secret-post', 'agent' => 'ua', 'created_at' => $day(0, sprintf('00:00:%02d', $i))];
        }
        $rows[] = ['admin_id' => 1, 'username' => 'root', 'name' => '已删除', 'created_at' => $day(0), 'deleted_at' => self::DELETED];
        foreach ($rows as $row) {
            Db::table('admin_log')->insert($row + ['deleted_at' => null]);
        }

        $logs = $this->overview(1)['logs'];

        self::assertSame(12, $logs['today']);
        self::assertSame(
            array_map(static fn (int $offset): string => date('Y-m-d', strtotime("-$offset days")), [6, 5, 4, 3, 2, 1, 0]),
            array_column($logs['trend'], 'date')
        );
        self::assertSame([1, 0, 0, 0, 2, 0, 12], array_column($logs['trend'], 'count'));
        self::assertCount(10, $logs['latest']);
        self::assertSame(['id', 'username', 'name', 'action', 'method', 'url', 'ip', 'status', 'createdAt'], array_keys($logs['latest'][0]));
        self::assertSame('今日11', $logs['latest'][0]['name']);
        self::assertSame('GET', $logs['latest'][0]['method']);
        self::assertStringNotContainsString('secret-', json_encode($logs, JSON_UNESCAPED_UNICODE));
        self::assertStringNotContainsString('已删除', json_encode($logs, JSON_UNESCAPED_UNICODE));
    }

    public function test_limited_admin_only_gets_permitted_blocks_within_data_scope(): void
    {
        $this->limitedAdmin(['system:operation-log:index']);
        Db::table('admin_log')->insert([
            ['admin_id' => 2, 'username' => 'viewer', 'name' => '本人', 'created_at' => date('Y-m-d H:i:s')],
            ['admin_id' => 1, 'username' => 'root', 'name' => '他人', 'created_at' => date('Y-m-d H:i:s')],
        ]);

        $data = $this->overview(2);

        self::assertSame(['logs', 'generatedAt'], array_keys($data));
        self::assertSame(1, $data['logs']['today']);
        self::assertSame(['本人'], array_column($data['logs']['latest'], 'name'));
    }

    public function test_admin_without_any_module_permission_gets_no_blocks(): void
    {
        $this->limitedAdmin([]);
        self::assertSame(['generatedAt'], array_keys($this->overview(2)));
    }

    public function test_admin_count_for_limited_admin_matches_their_admin_list(): void
    {
        $this->limitedAdmin(['authorization:admin:index', 'system:member:index', 'system:attachment:index', 'plugin:plugin:installed'], 'all');
        Db::table('admin')->insert(['id' => 41, 'username' => 'peer', 'password' => 'x', 'status' => 1]);

        $data = $this->overview(2);

        self::assertSame(['admins', 'members', 'attachments', 'plugins', 'generatedAt'], array_keys($data));
        $list = json_decode((new SystemAdmin())->index()->rawBody(), true);
        self::assertSame($list['data']['total'], $data['admins']['total']);
    }

    public function test_result_is_cached_per_admin_for_sixty_seconds(): void
    {
        $this->limitedAdmin(['system:operation-log:index']);
        Db::table('admin_log')->insert(['admin_id' => 2, 'username' => 'viewer', 'name' => '首条', 'created_at' => date('Y-m-d H:i:s')]);
        $first = $this->overview(2)['logs']['today'];
        self::assertSame(DashboardOverviewService::CACHE_TTL, 60);

        Db::table('admin_log')->insert(['admin_id' => 2, 'username' => 'viewer', 'name' => '缓存期内新增', 'created_at' => date('Y-m-d H:i:s')]);
        $this->asActor(2);
        $cached = json_decode((new SystemDashboard())->overview()->rawBody(), true)['data'];
        self::assertSame($first, $cached['logs']['today'], '缓存期内返回同一结果');

        // 不同管理员使用各自的缓存键，不会读到别人的块。
        self::assertNotSame(DashboardOverviewService::cacheKey(1), DashboardOverviewService::cacheKey(2));
        $this->asActor(1);
        $super = json_decode((new SystemDashboard())->overview()->rawBody(), true)['data'];
        self::assertArrayHasKey('admins', $super);
    }
}
