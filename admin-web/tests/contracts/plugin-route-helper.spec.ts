import { readFileSync } from 'node:fs';
import { resolve } from 'node:path';
import { describe, expect, it } from 'vitest';

const read = (p: string) => readFileSync(resolve(process.cwd(), '..', p), 'utf8');

const helper = read('extend/fun/plugins/PluginRoute.php');
const fixtureManifest = read('tests/fixtures/plugins/example/plugin.json');

describe('插件路由鉴权约定 helper 契约', () => {
  it('后台组显式接收插件与 manifest 权限并保留 CSRF', () => {
    expect(helper).toMatch(/function adminGroup\(Route \$route, string \$plugin, string \$permission, Closure \$registrar\): void/);
    expect(helper).toMatch(/middleware\(CheckPluginPermission::class, \$plugin, \$permission\)/);
    expect(helper).toContain('CheckAdminApiCsrf');
    expect(helper).toMatch(/function memberApiGroup\(Route \$route, Closure \$registrar\): void/);
    expect(helper).toContain('MApi');
  });

  it('helper 从 Manifest 显式验证插件权限', () => {
    expect(helper).toContain("$manifest->toArray()['adminWeb']['permissions']");
    expect(helper).toContain('插件权限未在 manifest 中声明');
    expect(fixtureManifest).toContain('"code": "example:dashboard:view"');
  });
});
