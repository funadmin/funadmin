import { readFileSync } from 'node:fs';
import { resolve } from 'node:path';
import { scaffoldedPluginManifest } from '../helpers/pluginFixture';
import { describe, expect, it } from 'vitest';

const read = (p: string) => readFileSync(resolve(process.cwd(), '..', p), 'utf8');

const helper = read('admin/app/common/plugin/sdk/PluginRoute.php');
const fixtureManifest = scaffoldedPluginManifest();

describe('插件路由鉴权约定 helper 契约', () => {
  it('后台组显式接收插件与 manifest 权限并保留 CSRF', () => {
    expect(helper).toMatch(/function adminGroup\(Route \$route, string \$plugin, string \$permission, Closure \$registrar, \?string \$projectRoot = null\): void/);
    expect(helper).toMatch(/new PluginRoutePermissionMiddleware\(plugin: \$plugin, permission: \$permission\)/);
    expect(helper).toContain('CheckAdminApiCsrfMiddleware');
    expect(helper).toMatch(/function memberApiGroup\(Route \$route, Closure \$registrar\): void/);
    expect(helper).toContain('ApiMiddleware');
  });

  it('helper 从 Manifest 显式验证插件权限', () => {
    expect(helper).toContain("$manifest->toArray()['adminWeb']['permissions']");
    expect(helper).toContain('插件权限未在 manifest 中声明');
    // 脚手架清单为后台列表方法的推导码命名（{插件}:{资源}:{方法}）。
    expect(fixtureManifest.adminWeb.permissions).toContainEqual({ code: 'example:index:index', name: expect.any(String) });
  });
});
