import { readFileSync } from 'node:fs';
import { resolve } from 'node:path';
import { describe, expect, it } from 'vitest';

const read = (p: string) => readFileSync(resolve(process.cwd(), '..', p), 'utf8');

const registry = read('app/console/service/ResourceRegistryService.php');
const infrastructure = read('app/console/service/PluginInfrastructureService.php');
const pluginService = read('app/console/service/PluginService.php');

describe('插件权限节点注入契约', () => {
  it('资源注册表提供权限节点的写入/停用/回收', () => {
    expect(registry).toMatch(/function registerPermissions\(array \$permissions, string \$sourceType, string \$sourceName\): void/);
    expect(registry).toMatch(/function disablePermissions\(string \$sourceType, string \$sourceName\): void/);
    expect(registry).toMatch(/function removePermissions\(string \$sourceType, string \$sourceName\): void/);
  });

  it('权限节点按 schema code 规则解析 module/obj/act 并带源标记', () => {
    const block = registry.match(/function registerPermissions\([\s\S]*?\n    \}/)?.[0] ?? '';
    expect(block).toContain('source_type');
    expect(block).toMatch(/explode\(':'/);
    expect(block).toMatch(/TYPE_ROUTE|TYPE_GROUP/);
  });

  it('基础设施层提供权限生命周期及权限先于菜单的原子注册', () => {
    expect(infrastructure).toMatch(/function enablePermissions\(array \$permissions, string \$name\): void/);
    expect(infrastructure).toMatch(/function disablePermissions\(string \$name\): void/);
    expect(infrastructure).toMatch(/function removePermissions\(string \$name\): void/);
    const registerBlock = infrastructure.match(/function registerResources\([\s\S]*?\n    \}/)?.[0] ?? '';
    expect(registerBlock).toMatch(/Db::transaction/);
    expect(registerBlock.indexOf('enablePermissions(')).toBeLessThan(registerBlock.indexOf('enableMenus('));
  });

  it('安装、更新和启用统一原子对账资源，禁用和卸载回收权限', () => {
    expect(pluginService.match(/registerResources\(/g)?.length).toBeGreaterThanOrEqual(3);
    expect(pluginService).toMatch(/disablePermissions\(/);
    expect(pluginService).toMatch(/removePermissions\(/);
    expect(pluginService).not.toMatch(/registerPermissions\(/);
  });
});
