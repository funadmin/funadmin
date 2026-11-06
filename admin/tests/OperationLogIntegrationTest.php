<?php

declare(strict_types=1);

namespace Tests;

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
        self::assertTrue(Db::table('permission')->where('code', 'systemoperationlog:index')->exists());
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
        self::assertSame('systemdict', $row->controller);
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
}
