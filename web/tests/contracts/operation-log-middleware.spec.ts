import { readFileSync } from 'node:fs';
import { resolve } from 'node:path';
import { describe, expect, it } from 'vitest';
import { backendJson, runBackendTests } from './backend-runtime';

const readProjectFile = (path: string) => readFileSync(resolve(process.cwd(), '..', path), 'utf8');
const middleware = readProjectFile('admin/app/admin/middleware/SystemLogMiddleware.php');
const service = readProjectFile('admin/app/admin/system/service/AdminLogService.php');
const controller = readProjectFile('admin/app/admin/system/controller/SystemOperationLog.php');
const model = readProjectFile('admin/app/admin/system/model/AdminLog.php');
const api = readProjectFile('web/src/admin/api/system/log.ts');
const view = readProjectFile('web/src/admin/views/system/log/operation.vue');
const migration = readProjectFile('admin/database/core/migrations/archive/012_admin_log_audit.sql');

describe('操作日志中间件契约', () => {
  it('控制器执行完成后实际持久化成功及业务失败状态', () => {
    expect(middleware).toMatch(/\$response\s*=\s*\$handler\(\$request\)/);
    expect(middleware).toContain('->save($request, $response, $status, $durationMs, $errorMessage)');
    expect(middleware.indexOf('$handler($request)')).toBeLessThan(middleware.indexOf('->save('));
    expect(runBackendTests('OperationLogIntegrationTest.php', 'test_audit_middleware_persists_success_and_business_failures')).toContain('OK (1 test');
  }, 60_000);

  it('无请求参数和无权限标题时仍记录审计日志', () => {
    expect(service).not.toMatch(/!empty\(\$this->name\)\s*&&\s*!empty\(\$content\)/);
    expect(service).toContain("$controller . '/' . $action");
    expect(service).toContain("'status' => $succeeded");
  });

  it('日志记录失败不能影响业务响应', () => {
    expect(middleware).toMatch(/use Throwable;/);
    expect(middleware).toMatch(/catch\s*\(\s*\\?Throwable/);
    expect(middleware).toContain('Log::error');
    expect(runBackendTests('AdminHttpFoundationTest.php', 'test_audit_failure_(preserves_original_response|does_not_replace_original_exception)')).toContain('OK (2 tests');
  });

  it('记录响应状态、耗时、请求标识和不含敏感消息的异常类型摘要', () => {
    for (const field of ['response_code', 'duration_ms', 'request_id', 'error_message']) {
      expect(service).toContain(`'${field}'`);
      expect(model).toContain(`$this->${field}`);
    }
    expect(middleware).toContain('microtime(true)');
    expect(middleware).toContain('get_class($exception)');
    expect(middleware).not.toContain('$exception->getMessage()');
    const audit = backendJson(String.raw`
      (new \app\common\service\database\MigrationService())->run();
      \support\Db::table('admin_log')->delete();
      $request = new \support\Request("POST /admin/system/role HTTP/1.1\r\nX-Request-Id: contract-audit-request\r\n\r\n");
      $request->controller = \app\admin\authorization\controller\SystemRole::class;
      $request->action = 'create';
      $request->context['admin'] = ['id' => 7, 'username' => 'operator'];
      $failure = new \DomainException('password=must-not-be-logged');
      $preserved = false;
      try {
          (new \app\admin\middleware\SystemLogMiddleware())->process($request, static function () use ($failure): never { throw $failure; });
      } catch (\DomainException $actual) {
          $preserved = $actual === $failure;
      }
      $row = \support\Db::table('admin_log')->sole();
      echo json_encode(['preserved' => $preserved, 'status' => (int) $row->status, 'durationMs' => (int) $row->duration_ms, 'requestId' => $row->request_id, 'errorMessage' => $row->error_message], JSON_THROW_ON_ERROR);
    `);
    expect(audit).toMatchObject({ preserved: true, status: 0, requestId: 'contract-audit-request', errorMessage: 'DomainException' });
    expect((audit as { durationMs: number }).durationMs).toBeGreaterThanOrEqual(0);
    expect(JSON.stringify(audit)).not.toContain('must-not-be-logged');
  });

  it('数据库迁移统一应用与来源字段并增加查询索引', () => {
    for (const field of ['response_code', 'duration_ms', 'request_id', 'error_message', 'source_type']) {
      expect(migration).toContain(`COLUMN_NAME = '${field}'`);
    }
    expect(migration).toContain('CHANGE COLUMN `module` `app_name`');
    expect(migration).toContain('CHANGE COLUMN `plugins` `source_name`');
    expect(migration).toContain("WHEN `source_name` IS NULL OR TRIM(`source_name`) = '' OR `source_name` = 'app' THEN 'system'");
    expect(migration).toContain("WHEN `source_name` IS NULL OR TRIM(`source_name`) = '' OR `source_name` = 'app' THEN 'core'");
    for (const index of ['idx_admin_log_admin_time', 'idx_admin_log_app_time', 'idx_admin_log_source_time', 'idx_admin_log_status_time', 'idx_admin_log_create_time']) {
      expect(migration).toContain(`INDEX_NAME = '${index}'`);
    }
    expect(migration).not.toMatch(/\b(?:DROP|TRUNCATE|RENAME)\b/i);
  });

  it('服务与前端统一使用应用及来源字段', () => {
    for (const field of ['app_name', 'source_type', 'source_name']) {
      expect(service).toContain(`'${field}'`);
      expect(model).toContain(`$this->${field}`);
    }
    expect(controller).toContain('$log->toApiData(');
    expect(service).not.toContain("'plugins' =>");
    expect(service).not.toContain("'module' =>");
    for (const field of ['appName', 'sourceType', 'sourceName', 'responseCode', 'durationMs', 'requestId', 'errorMessage']) {
      expect(api).toContain(field);
      expect(view).toContain(field);
    }
  });
});
