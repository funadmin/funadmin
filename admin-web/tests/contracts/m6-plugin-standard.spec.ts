import { describe, expect, it } from 'vitest';
import { existsSync, readFileSync } from 'node:fs';
import { resolve } from 'node:path';

const root = resolve(import.meta.dirname, '../../..');

describe('M6 插件标准 Admin Web 契约', () => {
  it('使用 system/plugin 标准 API 文件并保留完整端点', () => {
    expect(existsSync(resolve(root, 'admin-web/src/api/system/plugin.ts'))).toBe(true);
    const api = readFileSync(resolve(root, 'admin-web/src/api/system/plugin.ts'), 'utf8');
    for (const endpoint of ['/account/login', '/account/logout', '/market/search', '/local/install', '/operations']) {
      expect(api).toContain(endpoint);
    }
  });

  it('plugin.json 只使用动态 ESM adminWeb 契约', () => {
    const manifest = JSON.parse(readFileSync(resolve(root, 'tests/fixtures/plugins/example/plugin.json'), 'utf8'));
    expect(manifest.adminWeb).toMatchObject({
      component: expect.stringMatching(/\.m?js$/),
      files: expect.any(Array),
      minFrontendVersion: expect.any(String),
      menu: expect.any(Array),
      permissions: expect.any(Array),
      routes: expect.any(Array)
    });
    expect(manifest.admin_web).toBeUndefined();
    expect(manifest.permissions).toBeUndefined();
    expect(manifest.menus).toBeUndefined();
    expect(manifest.resources.admin).toBeUndefined();
  });

  it('Admin ESM 与公开资源统一发布到 public/plugin-assets', () => {
    const publisher = readFileSync(resolve(root, 'app/console/service/PluginResourcePublisher.php'), 'utf8');
    const query = readFileSync(resolve(root, 'app/console/service/PluginCenterQueryService.php'), 'utf8');
    const pipeline = readFileSync(resolve(root, 'app/console/service/PluginPackagePipeline.php'), 'utf8');
    const controller = readFileSync(resolve(root, 'app/console/controller/system/SystemPlugin.php'), 'utf8');
    const pluginService = readFileSync(resolve(root, 'app/console/service/PluginService.php'), 'utf8');

    expect(publisher).toContain("['resources']");
    expect(publisher).toContain('plugin-assets');
    expect(publisher).not.toContain('adminWebRoot');
    expect(publisher).not.toContain('src/modules');
    expect(publisher + query + pipeline + controller).not.toContain('rebuildRequired');
    expect(query).toContain("['adminWeb']");
    expect(query).toContain("['component']");
    expect(query).toContain("'entryUrl'");
    expect(query).toContain("'hash'");
    expect(query).toContain("'routes'");
    expect(pluginService).toContain("$manifestData['adminWeb']['permissions']");
    expect(pluginService).toContain("$manifestData['adminWeb']['menu']");
  });

  it('仅动态导入受控同源 plugin-assets ESM', () => {
    const loader = readFileSync(resolve(root, 'admin-web/src/router/pluginModules.ts'), 'utf8');
    expect(loader).toContain('@vite-ignore');
    expect(loader).toContain('entryUrl');
    expect(loader).toContain('/plugin-assets/');
    expect(loader).toContain('url.origin === origin');
    expect(loader).not.toContain('import.meta.glob');
    expect(loader).not.toContain('/src/modules/');
    expect(loader).not.toContain('rebuildRequired');
  });

  it('Manifest 与 Schema 只读取 adminWeb 并保留安全静态校验', () => {
    const manifest = readFileSync(resolve(root, 'extend/fun/plugins/Manifest.php'), 'utf8');
    const schema = readFileSync(resolve(root, 'extend/fun/plugins/schema/plugin.schema.json'), 'utf8');
    expect(schema).toContain('"adminWeb"');
    expect(schema).not.toContain('"admin_web"');
    expect(manifest).toContain("$data['adminWeb']");
    expect(manifest).toContain('adminWeb.routes.meta.permission');
    expect(manifest).toContain('validateClosureRouteFile');
    expect(manifest).toContain('validateChannels');
    expect(manifest).toContain('validatePurgeContract');
  });

  it('插件 API 的未知异常不向客户端泄露内部消息', () => {
    const controller = readFileSync(resolve(root, 'app/console/controller/system/SystemPlugin.php'), 'utf8');
    const fallback = controller.match(/catch \(\\Throwable \$exception\) \{([\s\S]*?)\n\s*\}/)?.[1] ?? '';
    expect(fallback).toContain("return $this->fail(msg: '插件操作失败', code: 500)");
    expect(fallback).not.toContain('return $this->fail(msg: $exception->getMessage()');
  });

  it('页面具备计划中的安装、配置、生命周期、市场和账号组件', () => {
    for (const component of ['InstallDialog.vue', 'ConfigDialog.vue', 'LifecycleDrawer.vue', 'Market.vue', 'Account.vue']) {
      expect(existsSync(resolve(root, `admin-web/src/views/system/plugin/components/${component}`))).toBe(true);
    }
  });
});