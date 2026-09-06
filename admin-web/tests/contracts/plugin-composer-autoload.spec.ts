import { readFileSync, existsSync } from 'node:fs';
import { resolve } from 'node:path';
import { describe, expect, it } from 'vitest';

const read = (p: string) => readFileSync(resolve(process.cwd(), '..', p), 'utf8');

const composer = JSON.parse(read('composer.json'));
const loader = read('extend/fun/plugins/RuntimeLoader.php');
const service = read('extend/fun/plugins/Service.php');

describe('插件 composer 依赖加载契约', () => {
  it('根 autoload 映射 plugins 命名空间到插件目录', () => {
    expect(composer.autoload['psr-4'][`plugins${'\\'}`]).toBe('plugins');
  });

  it('RuntimeLoader 提供插件 vendor 自动加载挂载且防重复', () => {
    expect(loader).toMatch(/function loadComposerAutoload\(Manifest \$manifest\): void/);
    expect(loader).toContain("vendor' . DIRECTORY_SEPARATOR . 'autoload.php");
    expect(loader).toMatch(/static array \$loadedAutoloads/);
  });

  it('Service 在 entry 边界之前挂载插件 vendor', () => {
    const boot = service.match(/private function loadRuntimeBoundaries\(\)[\s\S]*?\n    \}/)?.[0] ?? '';
    const composerAt = boot.indexOf("'composer'");
    const entryAt = boot.indexOf("'entry'");
    expect(composerAt).toBeGreaterThan(-1);
    expect(entryAt).toBeGreaterThan(-1);
    expect(composerAt).toBeLessThan(entryAt);
  });

  it('fixture 插件携带假 vendor autoload 供行为验证', () => {
    expect(existsSync(resolve(process.cwd(), '../tests/fixtures/plugins/example/vendor/autoload.php'))).toBe(true);
  });
});
