import { readFileSync } from 'node:fs';
import { resolve } from 'node:path';
import { describe, expect, it } from 'vitest';

const readProjectFile = (path: string) => readFileSync(resolve(process.cwd(), '..', path), 'utf8');
const middleware = readProjectFile('app/backend/middleware/SystemLog.php');
const service = readProjectFile('app/common/service/AdminLogService.php');

describe('操作日志中间件契约', () => {
  it('控制器执行完成后根据响应状态写日志', () => {
    expect(middleware).toMatch(/\$response\s*=\s*\$next\(\$request\)/);
    expect(middleware).toMatch(/->save\(\$request,\s*\$response(?:,\s*\$status)?\)/);
    expect(middleware.indexOf('$next($request)')).toBeLessThan(middleware.indexOf('->save('));
  });

  it('无请求参数和无权限标题时仍记录审计日志', () => {
    expect(service).not.toMatch(/!empty\(\$this->title\)\s*&&\s*!empty\(\$content\)/);
    expect(service).toContain("$controller . '/' . $action");
    expect(service).toContain("'status' => $succeeded");
  });

  it('日志记录失败不能影响业务响应', () => {
    expect(middleware).toMatch(/use Throwable;/);
    expect(middleware).toMatch(/catch\s*\(\s*\\?Throwable/);
    expect(middleware).toContain('Log::error');
  });
});
