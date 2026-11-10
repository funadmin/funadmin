<?php

declare(strict_types=1);

namespace Tests;

use app\admin\authorization\service\CasbinService;
use app\admin\authorization\service\PermissionCode;
use app\admin\middleware\CheckAdminApiRoleMiddleware;
use app\admin\system\controller\SystemOperationLog;
use app\admin\system\service\AdminLogService;
use app\common\service\database\MigrationService;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\TestCase;
use support\Context;
use support\Db;
use support\Request;
use support\Response;
use Webman\Route;

#[RunTestsInSeparateProcesses]
#[PreserveGlobalState(false)]
final class OperationLogIntegrationTest extends TestCase
{
    protected function setUp(): void
    {
        (new MigrationService())->run();
        Db::beginTransaction();
        $this->asActor(1);
    }

    protected function tearDown(): void
    {
        Db::rollBack();
        Context::destroy();
    }

    private function asActor(int $id, string $query = ''): void
    {
        $request = new Request("GET /admin/system/log/operation$query HTTP/1.1\r\n\r\n");
        $request->context['admin'] = ['id' => $id, 'username' => 'operator'];
        Context::set(\Webman\Http\Request::class, $request);
    }

    public function test_fresh_install_has_audit_storage_and_menu(): void
    {
        self::assertTrue(Db::schema()->hasTable('admin_log'));
        self::assertTrue(Db::table('admin_menu')->where('href', '/admin/system/log/operation')->exists());
        self::assertTrue(Db::table('permission')->where('code', 'system:operation-log:index')->exists());
    }

    public function test_real_audit_write_redacts_secrets_and_preserves_request_metadata(): void
    {
        $body = json_encode(['name' => '记录操作', 'nested' => ['password' => 'must-not-store', 'client_secret' => 'must-not-store', 'access_key' => 'must-not-store', 'secret_key' => 'must-not-store']], JSON_THROW_ON_ERROR);
        $request = new Request("POST /admin/system/dict/types HTTP/1.1\r\nAuthorization: must-not-store\r\nCookie: must-not-store\r\nX-CSRF-TOKEN: must-not-store\r\nContent-Type: application/json\r\nContent-Length: " . strlen($body) . "\r\n\r\n" . $body);
        $request->controller = \app\admin\system\controller\SystemDict::class;
        $request->action = 'createType';
        $request->context['admin'] = ['id' => 1, 'username' => 'root'];
        (new AdminLogService())->save($request, new Response(200, ['Content-Type' => 'application/json'], '{"code":200}'), durationMs: 12);
        $row = Db::table('admin_log')->orderByDesc('id')->first();
        self::assertNotNull($row);
        self::assertStringNotContainsString('must-not-store', json_encode($row));
        self::assertStringContainsString('[REDACTED]', $row->post_data);
        self::assertSame(12, (int) $row->duration_ms);
        self::assertSame('system/dict', $row->controller);
        self::assertSame(1, (int) $row->status);
    }

    public function test_log_detail_and_deletion_cannot_escape_self_scope(): void
    {
        Db::table('admin')->insert(['id' => 22, 'username' => 'auditor', 'password' => 'hidden', 'status' => 1]);
        $own = Db::table('admin_log')->insertGetId(['admin_id' => 22, 'username' => 'auditor', 'name' => '本人操作']);
        $other = Db::table('admin_log')->insertGetId(['admin_id' => 33, 'username' => 'other', 'name' => '其他操作']);
        $this->asActor(22);
        $controller = new SystemOperationLog();
        $list = json_decode($controller->index()->rawBody(), true);
        self::assertSame(1, $list['data']['total']);
        self::assertSame($own, $list['data']['list'][0]['id']);
        $detail = json_decode($controller->detail($own)->rawBody(), true);
        self::assertSame('本人操作', $detail['data']['name']);
        self::assertSame(404, json_decode($controller->detail($other)->rawBody(), true)['code']);
        $controller->clear();
        self::assertNotNull(Db::table('admin_log')->where('id', $own)->value('deleted_at'));
        self::assertNull(Db::table('admin_log')->where('id', $other)->value('deleted_at'));
    }

    public function test_audit_middleware_persists_success_and_business_failures(): void
    {
        $request = new Request("PUT /admin/system/dict/types/1 HTTP/1.1\r\n\r\n");
        $request->controller = \app\admin\system\controller\SystemDict::class;
        $request->action = 'updateType';
        $request->context['admin'] = ['id' => 1, 'username' => 'root'];
        $middleware = new \app\admin\middleware\SystemLogMiddleware();
        foreach ([200, 422] as $code) {
            $middleware->process($request, static fn () => new Response(200, ['Content-Type' => 'application/json'], json_encode(['code' => $code])));
        }
        $rows = Db::table('admin_log')->where('url', '/admin/system/dict/types/1')->orderBy('id')->get();
        self::assertCount(2, $rows);
        self::assertSame(1, (int) $rows[0]->status);
        self::assertSame(0, (int) $rows[1]->status);
    }

    public function test_custom_authentication_headers_are_redacted(): void
    {
        $request = new Request("GET /admin/system/dict/types HTTP/1.1\r\nX-API-Key: must-not-store\r\nProxy-Authorization: must-not-store\r\n\r\n");
        $request->controller = \app\admin\system\controller\SystemDict::class;
        $request->action = 'types';
        (new AdminLogService())->save($request, new Response(200));
        $row = Db::table('admin_log')->orderByDesc('id')->first();
        self::assertStringNotContainsString('must-not-store', $row->header_data);
    }

    public function test_batch_delete_rejects_mixed_scope_without_deleting_any_record(): void
    {
        Db::table('admin')->insert(['id' => 22, 'username' => 'auditor', 'password' => 'hidden', 'status' => 1]);
        $own = Db::table('admin_log')->insertGetId(['admin_id' => 22, 'name' => '本人']);
        $other = Db::table('admin_log')->insertGetId(['admin_id' => 33, 'name' => '其他']);
        $this->asActor(22, '?ids[]=' . $own . '&ids[]=' . $other);
        $result = json_decode((new SystemOperationLog())->delete()->rawBody(), true);
        self::assertSame(403, $result['code']);
        self::assertNull(Db::table('admin_log')->where('id', $own)->value('deleted_at'));
        $this->asActor(22, '?ids[]=' . $own);
        $result = json_decode((new SystemOperationLog())->delete()->rawBody(), true);
        self::assertSame(1, $result['data']['removed']);
    }

    public function test_log_list_username_filter_matches_existing_records(): void
    {
        Db::table('admin_log')->insert(['admin_id' => 1, 'username' => 'root', 'name' => '操作']);
        $this->asActor(1, '?username=root');
        $list = json_decode((new SystemOperationLog())->index()->rawBody(), true);
        self::assertSame(1, $list['data']['total']);
    }

    public function test_soft_delete_moves_log_to_recycle_bin(): void
    {
        $kept = Db::table('admin_log')->insertGetId(['admin_id' => 1, 'username' => 'root', 'name' => '保留']);
        $removed = Db::table('admin_log')->insertGetId(['admin_id' => 1, 'username' => 'root', 'name' => '移入回收站']);
        $this->asActor(1, '?ids[]=' . $removed);
        self::assertSame(1, $this->json((new SystemOperationLog())->delete())['data']['removed']);
        self::assertTrue(Db::table('admin_log')->where('id', $removed)->exists(), '软删除不得物理删除');

        $this->asActor(1);
        $live = $this->json((new SystemOperationLog())->index())['data'];
        self::assertSame([$kept], array_column($live['list'], 'id'));

        $this->asActor(1, '?trashed=1');
        $trashed = $this->json((new SystemOperationLog())->index())['data'];
        self::assertSame(1, $trashed['total']);
        self::assertSame($removed, $trashed['list'][0]['id']);
        self::assertSame('移入回收站', $trashed['list'][0]['name']);
        self::assertNotSame('', (string) $trashed['list'][0]['deletedAt']);
        self::assertSame(array_keys($live['list'][0]), array_keys($trashed['list'][0]), '回收站与普通列表同一行结构');
        self::assertSame(array_keys($live), array_keys($trashed), '回收站与普通列表同一分页结构');
        self::assertSame('', (string) $live['list'][0]['deletedAt']);
    }

    public function test_force_delete_removes_live_and_trashed_logs(): void
    {
        Db::table('admin')->insert(['id' => 22, 'username' => 'auditor', 'password' => 'hidden', 'status' => 1]);
        $live = Db::table('admin_log')->insertGetId(['admin_id' => 22, 'name' => '未删除']);
        $trashed = Db::table('admin_log')->insertGetId(['admin_id' => 22, 'name' => '已删除', 'deleted_at' => date('Y-m-d H:i:s')]);
        $kept = Db::table('admin_log')->insertGetId(['admin_id' => 22, 'name' => '保留']);
        $other = Db::table('admin_log')->insertGetId(['admin_id' => 33, 'name' => '其他', 'deleted_at' => date('Y-m-d H:i:s')]);

        $this->asActor(22);
        self::assertSame(422, $this->json((new SystemOperationLog())->forceDelete())['code']);

        $this->asActor(22, '?ids[]=' . $live . '&ids[]=' . $other);
        self::assertSame(403, $this->json((new SystemOperationLog())->forceDelete())['code']);
        self::assertSame(4, Db::table('admin_log')->whereIn('id', [$live, $trashed, $kept, $other])->count(), '包含越权记录时一条都不删');

        $this->asActor(22, '?ids[]=' . $live . '&ids[]=' . $trashed);
        $result = $this->json((new SystemOperationLog())->forceDelete());
        self::assertSame(200, $result['code']);
        self::assertSame(['removed' => 2], $result['data']);
        self::assertFalse(Db::table('admin_log')->whereIn('id', [$live, $trashed])->exists(), '未删除和回收站中的日志都被真实删除');
        self::assertSame(2, Db::table('admin_log')->whereIn('id', [$kept, $other])->count());
    }

    public function test_clear_force_removes_everything_in_scope_only(): void
    {
        Db::table('admin')->insert(['id' => 22, 'username' => 'auditor', 'password' => 'hidden', 'status' => 1]);
        $now = date('Y-m-d H:i:s');
        Db::table('admin_log')->insert([
            ['admin_id' => 22, 'name' => '本人未删除', 'deleted_at' => null],
            ['admin_id' => 22, 'name' => '本人已删除', 'deleted_at' => $now],
        ]);
        $others = [
            Db::table('admin_log')->insertGetId(['admin_id' => 33, 'name' => '其他未删除']),
            Db::table('admin_log')->insertGetId(['admin_id' => 33, 'name' => '其他已删除', 'deleted_at' => $now]),
        ];

        $this->asActor(22);
        $result = $this->json((new SystemOperationLog())->clearForce());
        self::assertSame(200, $result['code']);
        self::assertSame(['removed' => 2], $result['data']);
        self::assertFalse(Db::table('admin_log')->where('admin_id', 22)->exists());
        self::assertSame($others, Db::table('admin_log')->where('admin_id', 33)->orderBy('id')->pluck('id')->map('intval')->all(), '数据范围外的日志（含回收站）保持不动');
        self::assertNull(Db::table('admin_log')->where('id', $others[0])->value('deleted_at'));
    }

    public function test_force_endpoints_require_their_own_permission(): void
    {
        $force = PermissionCode::derive(SystemOperationLog::class, 'forceDelete');
        $clearForce = PermissionCode::derive(SystemOperationLog::class, 'clearForce');
        self::assertSame('system:operation-log:force-delete', $force);
        self::assertSame('system:operation-log:clear-force', $clearForce);
        $sibling = Db::table('permission')->where('code', 'system:operation-log:delete')->first();
        foreach ([$force => '彻底删除', $clearForce => '彻底清空'] as $code => $name) {
            $row = Db::table('permission')->where('code', $code)->first();
            self::assertNotNull($row, $code . ' 必须登记');
            self::assertSame(['system/operation-log', PermissionCode::resource($code)['act'], 'route', 1, 0, $name], [$row->obj, $row->act, $row->resource_type, (int) $row->status, (int) $row->is_public, $row->name]);
            self::assertSame([(int) $sibling->pid, $sibling->source_type, $sibling->source_name], [(int) $row->pid, $row->source_type, $row->source_name], '与软删除权限同一父级与来源');
        }

        Route::load([config_path()]);
        foreach (['/force' => 'forceDelete', '/clear-force' => 'clearForce', '/7' => 'deleteById', '/clear' => 'clear'] as $path => $action) {
            $route = Route::dispatch('DELETE', '/admin/system/log/operation' . $path);
            self::assertSame(\FastRoute\Dispatcher::FOUND, $route[0], $path);
            self::assertSame([SystemOperationLog::class, $action], $route[1]['callback'], $path . ' 不得被 /{id} 遮蔽');
        }

        $now = date('Y-m-d H:i:s');
        Db::table('admin')->insert(['id' => 2, 'username' => 'op', 'password' => password_hash('x', PASSWORD_DEFAULT), 'status' => 1, 'dept_id' => 0, 'created_at' => $now]);
        Db::table('auth_group')->insert(['id' => 5, 'pid' => 0, 'name' => 'op', 'code' => 'op', 'level' => 10, 'data_scope' => 'self', 'status' => 1]);
        $token = bin2hex(random_bytes(32));
        $password = (string) Db::table('admin')->where('id', 2)->value('password');
        Db::table('admin_auth_tokens')->insert(['token_hash' => hash('sha256', $token), 'admin_id' => 2, 'purpose' => 'browser', 'credential_version' => hash('sha256', $password), 'expires_at' => date('Y-m-d H:i:s', time() + 3600), 'created_at' => $now]);
        Db::table('browser_sessions')->insert(['session_hash' => hash('sha256', $token), 'csrf_token' => str_repeat('a', 64), 'expires_at' => date('Y-m-d H:i:s', time() + 3600)]);
        $grant = static function (array $codes): void {
            CasbinService::instance()->syncRolePermissions(5, Db::table('permission')->whereIn('code', $codes)->pluck('id')->map('intval')->all());
            CasbinService::instance()->syncAdminRoles(2, [5]);
        };
        $decide = static function (string $action) use ($token): int {
            Context::destroy();
            $request = new Request("DELETE /admin/system/log/operation HTTP/1.1\r\nHost: x\r\nCookie: WEBMAN_ADMIN_SESSION={$token}\r\nContent-Length: 0\r\n\r\n");
            Context::set(\Webman\Http\Request::class, $request);
            $request->controller = SystemOperationLog::class;
            $request->action = $action;
            $response = (new CheckAdminApiRoleMiddleware())->process($request, static fn () => json(['code' => 200]));
            return (int) json_decode($response->rawBody(), true)['code'];
        };

        $grant(['system:operation-log:index', 'system:operation-log:delete', 'system:operation-log:clear']);
        self::assertSame(200, $decide('delete'));
        self::assertSame(200, $decide('clear'));
        self::assertSame(403, $decide('forceDelete'), '软删除权限不能彻底删除');
        self::assertSame(403, $decide('clearForce'), '软清空权限不能彻底清空');

        $grant([$force]);
        self::assertSame(200, $decide('forceDelete'));
        self::assertSame(403, $decide('clearForce'));

        $grant([$clearForce]);
        self::assertSame(403, $decide('forceDelete'));
        self::assertSame(200, $decide('clearForce'));
    }

    public function test_core_sql_registers_force_permissions_idempotently_without_grants(): void
    {
        $file = base_path() . '/database/core/migrations/006_operation_log_force_delete.sql';
        $sql = (string) file_get_contents($file);
        (new \ReflectionMethod(MigrationService::class, 'assertForwardOnly'))->invoke(new MigrationService(), $sql, $file);
        $code = (string) preg_replace('/^\s*--.*$/m', '', $sql);
        self::assertDoesNotMatchRegularExpression('/[^\x00-\x7F]/', $code, '语句中的中文必须使用 UTF-8 十六进制字面量');
        foreach (['彻底删除', '彻底清空'] as $text) {
            self::assertStringContainsString("CONVERT(X'" . strtoupper(bin2hex($text)) . "' USING utf8mb4)", $code, $text);
        }
        foreach (["'system:operation-log:force-delete'", "'system:operation-log:clear-force'", "'system:operation-log:delete'"] as $needle) {
            self::assertStringContainsString($needle, $code);
        }
        self::assertStringNotContainsString('casbin_rule', $code, '只登记，不授予任何角色');
        foreach (preg_split('/;\s*$/m', $code) ?: [] as $statement) {
            if (preg_match('/^\s*INSERT\b/i', $statement) === 1) self::assertMatchesRegularExpression('/(WHERE|AND)\s+NOT\s+EXISTS/i', $statement, '插入必须幂等');
        }
        self::assertSame(0, Db::table('casbin_rule')->whereIn('v3', ['force-delete', 'clear-force'])->where('v2', 'system/operation-log')->count(), 'PHP 迁移同样不授权');
    }

    private function json(Response $response): array
    {
        return json_decode($response->rawBody(), true, flags: JSON_THROW_ON_ERROR);
    }
}
