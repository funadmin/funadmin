import { readFileSync } from 'node:fs';
import { resolve } from 'node:path';
import { describe, expect, it } from 'vitest';

const routeSource = readFileSync(resolve(process.cwd(), '../app/console/route/app.php'), 'utf8');
const controllerSource = readFileSync(resolve(process.cwd(), '../app/console/controller/system/SystemConfig.php'), 'utf8');
const apiSource = readFileSync(resolve(process.cwd(), 'src/api/system/config.ts'), 'utf8');
const dialogSource = readFileSync(resolve(process.cwd(), 'src/views/system/config/components/ConfigFormDialog.vue'), 'utf8');

describe('配置选项加载契约', () => {
  it('options 使用明确 Attribute 路由且不再重复注册显式路由', () => {
    expect(controllerSource).toContain("#[Group('system', ['complete_match' => true])]");
    expect(controllerSource).toContain("#[Get('config/options')]");
    expect(controllerSource).toContain("#[Get('config')]");
    expect(routeSource).not.toContain("Route::get('system/config/options'");
  });

  it('配置 API 按 http.get 的直接参数约定发送查询', () => {
    expect(apiSource).toContain('list: (params: ConfigQuery) => http.get<API.PageResult<ConfigModel>>(PREFIX, params)');
  });

  it('配置分组下拉有当前值兜底且不显示空面板', () => {
    expect(dialogSource).toContain('availableGroups');
    expect(dialogSource).toContain('no-data-text="暂无可用配置分组"');
  });
});
