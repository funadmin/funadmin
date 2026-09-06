import { readFileSync, existsSync } from 'node:fs';
import { resolve } from 'node:path';
import { describe, expect, it } from 'vitest';

const read = (p: string) => readFileSync(resolve(process.cwd(), '..', p), 'utf8');

const schema = JSON.parse(read('extend/fun/plugins/schema/plugin.schema.json'));
const loader = read('extend/fun/plugins/RuntimeLoader.php');
const service = read('extend/fun/plugins/Service.php');
const fixtureManifest = read('tests/fixtures/plugins/example/plugin.json');

describe('插件前端 API 一等通道契约', () => {
  it('manifest schema 支持 channels 段且仅允许 api/frontend 两个通道', () => {
    expect(schema.properties.channels).toBeTruthy();
    expect(Object.keys(schema.properties.channels.properties)).toEqual(['api', 'frontend']);
    expect(schema.$defs.channel.required).toEqual(['routes']);
  });

  it('RuntimeLoader 提供通道路由加载且统一使用 Closure 约定', () => {
    expect(loader).toMatch(/function channelRoutesPath\(Manifest \$manifest, string \$channel\): \?string/);
    expect(loader).toMatch(/function loadChannelRoutes\(Route \$route, Manifest \$manifest, string \$channel\): void/);
    expect(loader).toContain("channels");
  });

  it('Service 按当前应用名隔离加载通道路由', () => {
    const boot = service.match(/public function boot\(\)[\s\S]*?\n    \}/)?.[0] ?? '';
    expect(boot).toMatch(/getName\(\)/);
    expect(boot).toMatch(/'api'/);
    expect(boot).toMatch(/loadChannelRoutes\(/);
  });

  it('fixture 插件声明 api 通道并提供路由文件', () => {
    const manifest = JSON.parse(fixtureManifest);
    expect(manifest.channels?.api?.routes).toBe('routes/api.php');
    expect(existsSync(resolve(process.cwd(), '../tests/fixtures/plugins/example/routes/api.php'))).toBe(true);
  });
});
