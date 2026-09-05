import { readFileSync } from 'node:fs';
import { resolve } from 'node:path';
import { describe, expect, it } from 'vitest';

const readProjectFile = (path: string) => readFileSync(resolve(process.cwd(), '..', path), 'utf8');
const middleware = readProjectFile('app/backend/middleware/SystemLog.php');
const service = readProjectFile('app/common/service/AdminLogService.php');
const controller = readProjectFile('app/backend/controller/system/SystemOperationLog.php');
const api = readProjectFile('admin-web/src/api/system/log.ts');
const view = readProjectFile('admin-web/src/views/system/log/operation.vue');
const migration = readProjectFile('database/migrations/012_admin_log_audit.sql');

describe('操作日志中间件契约', () => {
  it('控制器执行完成后根据响应状态写日志', () => {
    expect(middleware).toMatch(/\$response\s*=\s*\$next\(\$request\)/);
    expect(middleware).toContain('->save($request, $response, $status, $durationMs, $errorMessage)');
    expect(middleware.indexOf('$next($request)')).toBeLessThan(middleware.indexOf('->save('));
  });

  it('无请求参数和无权限标题时仍记录审计日志', () => {
    expect(service).not.toMatch(/!empty\(\$this->name\)\s*&&\s*!empty\(\$content\)/);
    expect(service).toContain("$controller . '/' . $action");
    expect(service).toContain("'status' => $succeeded");
  });

  it('日志记录失败不能影响业务响应', () => {
    expect(middleware).toMatch(/use Throwable;/);
    expect(middleware).toMatch(/catch\s*\(\s*\\?Throwable/);
    expect(middleware).toContain('Log::error');
  });

  it('记录响应状态、耗时、请求标识和异常摘要', () => {
    for (const field of ['response_code', 'duration_ms', 'request_id', 'error_message']) {
      expect(service).toContain(`'${field}'`);
      expect(controller).toContain(`$log->${field}`);
    }
    expect(middleware).toContain('microtime(true)');
    expect(middleware).toContain('$exception->getMessage()');
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
      expect(controller).toContain(`$log->${field}`);
    }
    expect(service).not.toContain("'plugins' =>");
    expect(service).not.toContain("'module' =>");
    for (const field of ['appName', 'sourceType', 'sourceName', 'responseCode', 'durationMs', 'requestId', 'errorMessage']) {
      expect(api).toContain(field);
      expect(view).toContain(field);
    }
  });
});
