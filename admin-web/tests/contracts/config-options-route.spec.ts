import { readFileSync } from 'node:fs';
import { resolve } from 'node:path';
import { describe, expect, it } from 'vitest';

const routeSource = readFileSync(resolve(process.cwd(), '../app/backend/route/app.php'), 'utf8');
const apiSource = readFileSync(resolve(process.cwd(), 'src/api/system/config.ts'), 'utf8');
const dialogSource = readFileSync(resolve(process.cwd(), 'src/views/system/config/components/ConfigFormDialog.vue'), 'utf8');

describe('配置选项加载契约', () => {
  it('options 静态路由优先于配置列表路由', () => {
    expect(routeSource.indexOf("Route::get('system/config/options'")).toBeGreaterThan(-1);
    expect(routeSource.indexOf("Route::get('system/config/options'")).toBeLessThan(
      routeSource.indexOf("Route::get('system/config',")
    );
  });

  it('配置 API 按 http.get 的直接参数约定发送查询', () => {
    expect(apiSource).toContain('list: (params: ConfigQuery) => http.get<API.PageResult<ConfigModel>>(PREFIX, params)');
  });

  it('配置分组下拉有当前值兜底且不显示空面板', () => {
    expect(dialogSource).toContain('availableGroups');
    expect(dialogSource).toContain('no-data-text="暂无可用配置分组"');
  });
});
